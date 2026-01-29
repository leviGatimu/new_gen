<?php
// student/messages.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$page_title = "Messages"; // For header.php

// 2. FETCH TEACHERS
$teachers = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name ASC")->fetchAll();

// 3. DETERMINE ACTIVE CHAT
$active_id = $_GET['chat'] ?? null;
$active_contact = null;

if ($active_id) {
    $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ?");
    $stmt->execute([$active_id]);
    $active_contact = $stmt->fetch();
}

// 4. HANDLE SENDING
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send'])) {
    $receiver = $_POST['receiver_id'];
    $text = trim($_POST['message']);
    
    if($text) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$student_id, $receiver, $text]);
        header("Location: messages.php?chat=" . $receiver); exit;
    }
}

// 5. FETCH MESSAGES
$chats = [];
if ($active_contact) {
    // Mark as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_id, $student_id]);

    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$student_id, $active_id, $active_id, $student_id]);
    $chats = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<style>
    /* === CHAT LAYOUT === */
    body { overflow: hidden; }
    
    .chat-wrapper {
        display: flex;
        height: calc(100vh - 80px);
        background: #fff;
    }

    /* --- SIDEBAR --- */
    .chat-sidebar {
        width: 350px;
        border-right: 1px solid #dfe3e8;
        background: #fff;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .contacts-header { padding: 20px; border-bottom: 1px solid #f0f0f0; background: #fff; }
    .contacts-header h2 { margin: 0; font-size: 1.2rem; color: var(--dark); }

    .contacts-list { flex: 1; overflow-y: auto; }

    .contact-item {
        display: flex; align-items: center; gap: 15px;
        padding: 15px 20px;
        border-bottom: 1px solid #f9fafb;
        text-decoration: none;
        color: var(--dark);
        transition: 0.2s;
        cursor: pointer;
    }
    .contact-item:hover { background: #f4f6f8; }
    .contact-item.active { background: #fff7e6; border-right: 3px solid var(--primary); }

    .avatar {
        width: 45px; height: 45px;
        background: #e0e6ed; color: #637381;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1rem;
        flex-shrink: 0;
    }
    .contact-item.active .avatar { background: var(--primary); color: white; }

    /* --- MAIN CHAT --- */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #f4f6f8;
        position: relative;
    }

    .chat-header {
        padding: 15px 25px;
        background: white;
        border-bottom: 1px solid #dfe3e8;
        display: flex; align-items: center; gap: 15px;
        height: 70px; box-sizing: border-box;
    }
    
    .back-btn { display: none; font-size: 1.5rem; color: #637381; cursor: pointer; text-decoration: none; }

    .messages-container {
        flex: 1;
        padding: 25px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        background-image: radial-gradient(#e0e0e0 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .msg-row { display: flex; width: 100%; }
    .msg-row.sent { justify-content: flex-end; }
    .msg-row.received { justify-content: flex-start; }

    .msg-bubble {
        max-width: 70%; padding: 12px 18px; border-radius: 18px;
        font-size: 0.95rem; line-height: 1.5; position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .msg-row.sent .msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 4px; }
    .msg-row.received .msg-bubble { background: white; color: var(--dark); border-bottom-left-radius: 4px; border: 1px solid #e0e0e0; }

    .msg-time { display: block; font-size: 0.7rem; margin-top: 5px; opacity: 0.8; text-align: right; }

    .chat-footer { padding: 20px; background: white; border-top: 1px solid #dfe3e8; }
    .input-group { display: flex; gap: 10px; background: #f9fafb; padding: 8px; border-radius: 30px; border: 1px solid #dfe3e8; }
    .chat-input { flex: 1; border: none; background: transparent; padding: 10px 15px; outline: none; font-family: inherit; }
    .btn-send { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .empty-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #919eab; }

    /* === CLEAN MOBILE RESPONSIVE LOGIC === */
    @media (max-width: 768px) {
        .chat-wrapper { height: calc(100vh - 70px); }
        
        /* 1. Default State: Show Contacts, Hide Chat */
        .chat-sidebar { width: 100%; display: flex; }
        .chat-main { display: none; width: 100%; }

        /* 2. Chat Active State (Triggered by PHP Class) */
        .chat-wrapper.chat-active .chat-sidebar { display: none; }
        .chat-wrapper.chat-active .chat-main { display: flex; }
        .chat-wrapper.chat-active .back-btn { display: block; }
    }
</style>

<div class="chat-wrapper <?php echo $active_id ? 'chat-active' : ''; ?>">
    
    <div class="chat-sidebar">
        <div class="contacts-header">
            <h2>Messages</h2>
        </div>
        <div class="contacts-list">
            <?php foreach($teachers as $t): ?>
                <a href="messages.php?chat=<?php echo $t['user_id']; ?>" class="contact-item <?php echo $active_id == $t['user_id'] ? 'active' : ''; ?>">
                    <div class="avatar">
                        <?php echo substr($t['full_name'], 0, 1); ?>
                    </div>
                    <div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($t['full_name']); ?></div>
                        <div style="font-size:0.8rem; color:#637381;">Teacher</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="chat-main">
        <?php if($active_contact): ?>
            <div class="chat-header">
                <a href="messages.php" class="back-btn"><i class='bx bx-arrow-back'></i></a>
                <div class="avatar" style="background:var(--primary); color:white;">
                    <?php echo substr($active_contact['full_name'], 0, 1); ?>
                </div>
                <div>
                    <div style="font-weight:800; font-size:1rem;"><?php echo htmlspecialchars($active_contact['full_name']); ?></div>
                    <div style="font-size:0.75rem; color:#00ab55; font-weight:600;">• Online</div>
                </div>
            </div>

            <div class="messages-container" id="scrollBox">
                <?php if(empty($chats)): ?>
                    <div class="empty-chat" style="height:auto; margin-top:50px;">
                        <span style="background:#e0f7fa; color:#006064; padding:5px 15px; border-radius:20px; font-size:0.8rem;">
                            Say Hello!
                        </span>
                    </div>
                <?php else: ?>
                    <?php foreach($chats as $c): ?>
                        <div class="msg-row <?php echo $c['sender_id'] == $student_id ? 'sent' : 'received'; ?>">
                            <div class="msg-bubble">
                                <?php echo nl2br(htmlspecialchars($c['message'])); ?>
                                <span class="msg-time"><?php echo date('H:i', strtotime($c['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="chat-footer">
                <form method="POST" class="input-group">
                    <input type="hidden" name="receiver_id" value="<?php echo $active_id; ?>">
                    <input type="text" name="message" class="chat-input" placeholder="Type a message..." autocomplete="off" required>
                    <button type="submit" name="send" class="btn-send">
                        <i class='bx bxs-send'></i>
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="empty-chat">
                <i class='bx bxs-chat' style="font-size:4rem; margin-bottom:20px; opacity:0.2;"></i>
                <h3>Select a Teacher</h3>
                <p>Choose a teacher from the list to start messaging.</p>
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