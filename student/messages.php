<?php
// student/messages.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$page_title = "Messages";

// 2. FETCH USER INFO (For Class ID)
$stmt = $pdo->prepare("SELECT s.class_id, c.class_name 
                       FROM students s 
                       JOIN classes c ON s.class_id = c.class_id 
                       WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$me = $stmt->fetch();
$my_class_id = $me['class_id'];
$my_class_name = $me['class_name'];

// 3. FETCH TEACHERS (Direct Contacts)
$teachers = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name ASC")->fetchAll();

// 4. DETERMINE ACTIVE VIEW
// mode = 'private' (default), 'class', 'global'
$mode = $_GET['mode'] ?? 'global'; 
$chat_id = $_GET['id'] ?? 0; // User ID for private, Class ID for class

// 5. HANDLE SENDING
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send'])) {
    $text = trim($_POST['message']);
    
    if($text) {
        if ($_POST['msg_type'] == 'private') {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type, created_at) VALUES (?, ?, ?, 'private', NOW())");
            $stmt->execute([$student_id, $_POST['target_id'], $text]);
            header("Location: messages.php?mode=private&id=" . $_POST['target_id']); 
        } 
        elseif ($_POST['msg_type'] == 'class') {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type, created_at) VALUES (?, ?, ?, 'class', NOW())");
            $stmt->execute([$student_id, $my_class_id, $text]);
            header("Location: messages.php?mode=class"); 
        }
        exit;
    }
}

// 6. FETCH MESSAGES BASED ON MODE
$chats = [];
$chat_title = "";
$is_read_only = false;

if ($mode == 'global') {
    $chat_title = "📢 School Announcements";
    $is_read_only = true; // Students can't reply to global
    // Fetch global messages (admin broadcasts)
    $stmt = $pdo->query("SELECT m.*, u.full_name, u.role FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.msg_type = 'global' OR m.msg_type = 'system' ORDER BY m.created_at ASC");
    $chats = $stmt->fetchAll();

} elseif ($mode == 'class') {
    $chat_title = "👥 $my_class_name Group";
    // Fetch class messages
    $stmt = $pdo->prepare("SELECT m.*, u.full_name, u.role FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.class_id = ? AND m.msg_type = 'class' ORDER BY m.created_at ASC");
    $stmt->execute([$my_class_id]);
    $chats = $stmt->fetchAll();

} elseif ($mode == 'private' && $chat_id) {
    // Get Contact Name
    $u_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $u_stmt->execute([$chat_id]);
    $user_name = $u_stmt->fetchColumn();
    $chat_title = "💬 " . $user_name;

    // Fetch private chat
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name, u.role 
        FROM messages m 
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?) 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$student_id, $chat_id, $chat_id, $student_id]);
    $chats = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<style>
    /* LAYOUT */
    body { overflow: hidden; }
    .chat-wrapper { display: flex; height: calc(100vh - 80px); background: #fff; }

    /* SIDEBAR */
    .chat-sidebar { width: 320px; border-right: 1px solid #dfe3e8; display: flex; flex-direction: column; background: #fff; flex-shrink: 0; }
    
    .sb-section { padding: 15px 20px 5px; font-size: 0.75rem; font-weight: 800; color: #919eab; text-transform: uppercase; letter-spacing: 0.5px; }
    .sb-list { overflow-y: auto; flex: 1; }

    .chat-item {
        padding: 12px 20px; display: flex; align-items: center; gap: 12px;
        text-decoration: none; color: var(--dark); border-bottom: 1px solid #f9fafb; transition: 0.2s;
    }
    .chat-item:hover { background: #f4f6f8; }
    .chat-item.active { background: #fff7e6; border-right: 3px solid var(--primary); }

    .c-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .c-global { background: #e3f2fd; color: #1976d2; }
    .c-class { background: #f3e5f5; color: #7b1fa2; }
    .c-user { background: #e0e6ed; color: #637381; font-size: 0.9rem; font-weight: 700; }

    /* MAIN AREA */
    .chat-main { flex: 1; display: flex; flex-direction: column; background: #f4f6f8; position: relative; }
    
    .chat-header {
        height: 60px; background: white; border-bottom: 1px solid #dfe3e8; 
        display: flex; align-items: center; padding: 0 20px; gap: 15px;
        font-weight: 800; font-size: 1.1rem; color: var(--dark);
    }
    .back-btn { display: none; font-size: 1.5rem; color: #637381; text-decoration: none; }

    .msgs-container {
        flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;
        background-image: radial-gradient(#e0e0e0 1px, transparent 1px); background-size: 20px 20px;
    }

    /* BUBBLES */
    .msg-row { display: flex; width: 100%; margin-bottom: 5px; }
    .msg-row.sent { justify-content: flex-end; }
    .msg-row.received { justify-content: flex-start; }

    .bubble {
        max-width: 70%; padding: 10px 15px; border-radius: 12px; font-size: 0.95rem; line-height: 1.4; position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .sent .bubble { background: var(--primary); color: white; border-bottom-right-radius: 2px; }
    .received .bubble { background: white; color: var(--dark); border-bottom-left-radius: 2px; border: 1px solid #e0e0e0; }

    .sender-name { font-size: 0.7rem; font-weight: 700; margin-bottom: 3px; display: block; opacity: 0.8; }
    .sent .sender-name { display: none; } /* Hide my own name */
    .received .sender-name { color: var(--primary); }
    
    .admin-tag { background: #000; color: #fff; padding: 2px 4px; border-radius: 4px; font-size: 0.6rem; margin-left: 5px; }

    /* FOOTER */
    .chat-footer { padding: 15px; background: white; border-top: 1px solid #dfe3e8; }
    .input-box { display: flex; gap: 10px; background: #f9fafb; padding: 8px; border-radius: 30px; border: 1px solid #dfe3e8; }
    .chat-input { flex: 1; border: none; background: transparent; padding: 8px 15px; outline: none; }
    .send-btn { background: var(--primary); color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display:flex; align-items:center; justify-content:center; }

    /* MOBILE */
    @media (max-width: 768px) {
        .chat-wrapper { height: calc(100vh - 70px); }
        .chat-sidebar { width: 100%; display: flex; }
        .chat-main { display: none; width: 100%; }
        
        .chat-wrapper.active .chat-sidebar { display: none; }
        .chat-wrapper.active .chat-main { display: flex; }
        .chat-wrapper.active .back-btn { display: block; }
    }
</style>

<div class="chat-wrapper <?php echo ($mode != 'none') ? 'active' : ''; ?>">

    <div class="chat-sidebar">
        
        <div class="sb-section">Groups</div>
        <div class="sb-list" style="flex: 0 0 auto;">
            <a href="messages.php?mode=global" class="chat-item <?php echo $mode=='global'?'active':''; ?>">
                <div class="c-icon c-global"><i class='bx bx-broadcast'></i></div>
                <div>
                    <div style="font-weight:700;">Announcements</div>
                    <div style="font-size:0.8rem; color:#637381;">School Board</div>
                </div>
            </a>
            <a href="messages.php?mode=class" class="chat-item <?php echo $mode=='class'?'active':''; ?>">
                <div class="c-icon c-class"><i class='bx bx-group'></i></div>
                <div>
                    <div style="font-weight:700;"><?php echo htmlspecialchars($my_class_name); ?></div>
                    <div style="font-size:0.8rem; color:#637381;">Class Group Chat</div>
                </div>
            </a>
        </div>

        <div class="sb-section" style="border-top:1px solid #f0f0f0; margin-top:10px; padding-top:15px;">Teachers</div>
        <div class="sb-list">
            <?php foreach($teachers as $t): ?>
                <a href="messages.php?mode=private&id=<?php echo $t['user_id']; ?>" class="chat-item <?php echo ($mode=='private' && $chat_id==$t['user_id'])?'active':''; ?>">
                    <div class="c-icon c-user"><?php echo substr($t['full_name'], 0, 1); ?></div>
                    <div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($t['full_name']); ?></div>
                        <div style="font-size:0.8rem; color:#637381;">Direct Message</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="chat-main">
        <div class="chat-header">
            <a href="messages.php" class="back-btn"><i class='bx bx-arrow-back'></i></a>
            <span><?php echo $chat_title; ?></span>
        </div>

        <div class="msgs-container" id="scrollBox">
            <?php if(empty($chats)): ?>
                <div style="text-align:center; margin-top:50px; color:#919eab;">
                    <i class='bx bx-message-dots' style="font-size:3rem; opacity:0.3;"></i>
                    <p>No messages yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($chats as $msg): 
                    $is_me = ($msg['sender_id'] == $student_id);
                ?>
                <div class="msg-row <?php echo $is_me ? 'sent' : 'received'; ?>">
                    <div class="bubble">
                        <?php if(!$is_me): ?>
                            <span class="sender-name">
                                <?php echo htmlspecialchars($msg['full_name']); ?>
                                <?php if($msg['role'] == 'admin') echo '<span class="admin-tag">ADMIN</span>'; ?>
                                <?php if($msg['role'] == 'teacher') echo '<span class="admin-tag" style="background:#007bff;">TEACHER</span>'; ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        
                        <div style="text-align:right; font-size:0.65rem; opacity:0.6; margin-top:5px;">
                            <?php echo date("H:i", strtotime($msg['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if(!$is_read_only): ?>
            <div class="chat-footer">
                <form method="POST" class="input-box">
                    <input type="hidden" name="msg_type" value="<?php echo $mode; ?>">
                    <input type="hidden" name="target_id" value="<?php echo $chat_id; ?>">
                    <input type="text" name="message" class="chat-input" placeholder="Type a message..." autocomplete="off" required>
                    <button type="submit" name="send" class="send-btn"><i class='bx bxs-send'></i></button>
                </form>
            </div>
        <?php else: ?>
            <div class="chat-footer" style="text-align:center; color:#919eab; font-size:0.8rem;">
                <i class='bx bxs-lock-alt'></i> Only Admins can post in Announcements.
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    var box = document.getElementById('scrollBox');
    if(box) { box.scrollTop = box.scrollHeight; }
</script>

</body>
</html>