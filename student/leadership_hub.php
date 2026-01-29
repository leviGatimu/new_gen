<?php
// student/leadership_hub.php
session_start();
require '../config/db.php';

// 1. SECURITY & ROLE CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$user_id = $_SESSION['user_id'];

// --- FIX: SPECIFY TABLE ALIASES (s.class_id) TO PREVENT AMBIGUITY ---
$stmt = $pdo->prepare("SELECT s.leadership_role, u.full_name, s.class_id 
                       FROM students s 
                       JOIN users u ON s.student_id = u.user_id 
                       WHERE s.student_id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

if (!in_array($me['leadership_role'], ['Head Boy', 'Head Girl'])) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:#666;'>
            <h1>Access Denied</h1>
            <p>This command center is restricted to the Head Boy and Head Girl.</p>
            <a href='dashboard.php'>Return to Dashboard</a>
         </div>");
}

$page_title = "Leadership Hub";
include '../includes/header.php'; // Use the Master Header

$message = "";
$msg_type = "";

// --- ACTIONS ---

// A. PIN / UNPIN ISSUE
if (isset($_GET['toggle_pin'])) {
    $id = $_GET['toggle_pin'];
    // Check current state to toggle
    $curr = $pdo->prepare("SELECT is_pinned FROM student_issues WHERE issue_id = ?");
    $curr->execute([$id]);
    $state = $curr->fetchColumn();
    
    $new_state = ($state == 1) ? 0 : 1;
    $pdo->prepare("UPDATE student_issues SET is_pinned = ? WHERE issue_id = ?")->execute([$new_state, $id]);
    
    header("Location: leadership_hub.php"); exit;
}

// B. FORWARD TO ADMIN
if (isset($_GET['forward'])) {
    $issue_id = $_GET['forward'];
    
    // Fetch issue details
    $issue = $pdo->query("SELECT si.*, u.full_name FROM student_issues si JOIN users u ON si.sender_id = u.user_id WHERE si.issue_id = $issue_id")->fetch();
    
    // Fetch Admin ID
    $admin = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    
    if ($issue && $admin) {
        $msg_content = "⚠️ ESCALATED ISSUE\n\nFROM: " . $issue['full_name'] . "\nSUBJECT: " . $issue['title'] . "\n\n" . $issue['description'];
        
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type, created_at) VALUES (?, ?, ?, 'private', NOW())")
            ->execute([$user_id, $admin['user_id'], $msg_content]);
        
        $pdo->prepare("UPDATE student_issues SET status = 'Escalated' WHERE issue_id = ?")->execute([$issue_id]);
        
        $message = "Issue successfully escalated to Administration.";
        $msg_type = "success";
    }
}

// C. MARK AS RESOLVED
if (isset($_GET['resolve'])) {
    $pdo->prepare("UPDATE student_issues SET status = 'Resolved' WHERE issue_id = ?")->execute([$_GET['resolve']]);
    header("Location: leadership_hub.php"); exit;
}

// D. APPOINT PREFECT
if (isset($_POST['make_prefect'])) {
    $adm_no = trim($_POST['admission_number']);
    
    // Check if student exists
    $check = $pdo->prepare("SELECT student_id, class_id FROM students WHERE admission_number = ?");
    $check->execute([$adm_no]);
    $target = $check->fetch();

    if ($target) {
        $pdo->prepare("UPDATE students SET leadership_role = 'Prefect' WHERE student_id = ?")->execute([$target['student_id']]);
        $message = "Student assigned as Prefect successfully.";
        $msg_type = "success";
    } else {
        $message = "Student with Admission No. '$adm_no' not found.";
        $msg_type = "error";
    }
}

// --- FETCH DATA ---
// 1. Issues (Pinned First, then Newest)
$issues = $pdo->query("
    SELECT si.*, u.full_name, u.email 
    FROM student_issues si 
    JOIN users u ON si.sender_id = u.user_id 
    ORDER BY si.is_pinned DESC, si.created_at DESC
")->fetchAll();

// 2. Counts
$count_open = 0;
$count_pinned = 0;
foreach($issues as $i) {
    if($i['status'] == 'Open') $count_open++;
    if($i['is_pinned']) $count_pinned++;
}

// 3. Prefects List
$prefects = $pdo->query("
    SELECT u.full_name, s.admission_number, c.class_name 
    FROM students s 
    JOIN users u ON s.student_id = u.user_id 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE s.leadership_role = 'Prefect'
    ORDER BY c.class_name ASC
")->fetchAll();
?>

<div class="container">

    <style>
        /* === HUB SPECIFIC CSS === */
        
        /* Stats Bar */
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; border: 1px solid #dfe3e8; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .s-orange { background: #fff7e6; color: #d48806; }
        .s-blue { background: #e6f7ff; color: #0050b3; }
        .s-purple { background: #f9f0ff; color: #722ed1; }
        
        .stat-info h3 { margin: 0; font-size: 1.5rem; color: var(--dark); }
        .stat-info p { margin: 0; color: #637381; font-size: 0.85rem; text-transform: uppercase; font-weight: 700; }

        /* Layout */
        .hub-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        @media (max-width: 900px) { .hub-grid { grid-template-columns: 1fr; } }

        /* Feed Section */
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .feed-title { font-size: 1.1rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        
        /* Issue Ticket */
        .ticket { 
            background: white; border-radius: 12px; border: 1px solid #dfe3e8; padding: 20px; margin-bottom: 15px; 
            transition: 0.2s; position: relative; overflow: hidden;
        }
        .ticket:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        .ticket.pinned { border-left: 4px solid #FFD700; background: #fffdf5; }
        .ticket.resolved { opacity: 0.7; background: #f9fafb; }

        .t-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .t-user { display: flex; align-items: center; gap: 10px; }
        .t-avatar { width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #637381; }
        .t-name { font-weight: 700; color: var(--dark); font-size: 0.9rem; }
        .t-date { font-size: 0.75rem; color: #919eab; }

        .t-status { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .st-open { background: #e6fffb; color: #006d75; }
        .st-esc { background: #fff1f0; color: #cf1322; }
        .st-res { background: #f0f0f0; color: #666; }

        .t-body h4 { margin: 0 0 5px 0; font-size: 1rem; color: var(--dark); }
        .t-body p { margin: 0; font-size: 0.9rem; color: #637381; line-height: 1.5; }

        .t-actions { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #eee; display: flex; gap: 10px; }
        .btn-act { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 5px; cursor: pointer; border: 1px solid transparent; }
        
        .btn-pin { background: #fff7e6; color: #d48806; border-color: #ffe58f; }
        .btn-pin:hover { background: #ffe58f; }
        
        .btn-esc { background: #e6f7ff; color: #0050b3; border-color: #91caff; }
        .btn-esc:hover { background: #bae7ff; }

        .btn-res { background: #f6ffed; color: #389e0d; border-color: #b7eb8f; }
        .btn-res:hover { background: #d9f7be; }

        /* Sidebar Tools */
        .tool-card { background: white; border-radius: 12px; border: 1px solid #dfe3e8; padding: 20px; margin-bottom: 25px; }
        .tool-title { font-size: 1rem; font-weight: 800; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .pf-list { list-style: none; padding: 0; margin: 0; max-height: 300px; overflow-y: auto; }
        .pf-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f9fafb; font-size: 0.9rem; }
        .pf-item:last-child { border-bottom: none; }
        
        .form-input { width: 100%; padding: 10px; border: 1px solid #dfe3e8; border-radius: 6px; margin-bottom: 10px; box-sizing: border-box; }
        .btn-add { width: 100%; padding: 10px; background: var(--dark); color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .btn-add:hover { background: var(--primary); }

        /* Alert */
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .al-success { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; }
        .al-error { background: #fff1f0; color: #cf1322; border: 1px solid #ffa39e; }
    </style>

    <div style="margin-bottom: 30px;">
        <h1 style="margin:0; font-size:1.8rem; color:var(--dark);">Student Government Hub</h1>
        <p style="color:#637381; margin:5px 0 0;">Welcome, <?php echo htmlspecialchars($me['full_name']); ?>. Manage student voices and leadership.</p>
    </div>

    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-icon s-orange"><i class='bx bx-error-circle'></i></div>
            <div class="stat-info">
                <h3><?php echo $count_open; ?></h3>
                <p>Open Issues</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon s-purple"><i class='bx bxs-pin'></i></div>
            <div class="stat-info">
                <h3><?php echo $count_pinned; ?></h3>
                <p>Pinned Items</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon s-blue"><i class='bx bxs-badge-check'></i></div>
            <div class="stat-info">
                <h3><?php echo count($prefects); ?></h3>
                <p>Active Prefects</p>
            </div>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert-box <?php echo ($msg_type == 'success') ? 'al-success' : 'al-error'; ?>">
            <i class='bx <?php echo ($msg_type == 'success') ? 'bxs-check-circle' : 'bxs-error-circle'; ?>'></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="hub-grid">
        
        <div>
            <div class="feed-header">
                <div class="feed-title"><i class='bx bx-list-ul'></i> Student Issues Feed</div>
            </div>

            <?php if(empty($issues)): ?>
                <div style="text-align:center; padding:50px; background:white; border-radius:12px; border:1px dashed #dfe3e8; color:#999;">
                    <i class='bx bx-check-shield' style="font-size:3rem; margin-bottom:10px;"></i>
                    <p>All quiet. No issues reported yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($issues as $i): 
                    $is_pinned = $i['is_pinned'];
                    $is_resolved = ($i['status'] == 'Resolved');
                ?>
                <div class="ticket <?php echo $is_pinned ? 'pinned' : ''; ?> <?php echo $is_resolved ? 'resolved' : ''; ?>">
                    <div class="t-header">
                        <div class="t-user">
                            <div class="t-avatar"><?php echo substr($i['full_name'], 0, 1); ?></div>
                            <div>
                                <div class="t-name"><?php echo htmlspecialchars($i['full_name']); ?></div>
                                <div class="t-date"><?php echo date("M d, H:i", strtotime($i['created_at'])); ?></div>
                            </div>
                        </div>
                        <span class="t-status <?php echo ($i['status']=='Open')?'st-open':(($i['status']=='Escalated')?'st-esc':'st-res'); ?>">
                            <?php echo $i['status']; ?>
                        </span>
                    </div>

                    <div class="t-body">
                        <h4><?php echo htmlspecialchars($i['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($i['description'])); ?></p>
                    </div>

                    <?php if(!$is_resolved): ?>
                    <div class="t-actions">
                        <a href="?toggle_pin=<?php echo $i['issue_id']; ?>" class="btn-act btn-pin">
                            <i class='bx <?php echo $is_pinned ? 'bxs-pin' : 'bx-pin'; ?>'></i> <?php echo $is_pinned ? 'Unpin' : 'Pin'; ?>
                        </a>
                        
                        <?php if($i['status'] != 'Escalated'): ?>
                            <a href="?forward=<?php echo $i['issue_id']; ?>" class="btn-act btn-esc" onclick="return confirm('Forward this to the School Admin?');">
                                <i class='bx bx-send'></i> Escalate
                            </a>
                        <?php endif; ?>

                        <a href="?resolve=<?php echo $i['issue_id']; ?>" class="btn-act btn-res" onclick="return confirm('Mark this issue as resolved?');">
                            <i class='bx bx-check'></i> Resolve
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div>
            <div class="tool-card">
                <div class="tool-title"><i class='bx bxs-user-plus'></i> Appoint Prefect</div>
                <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">Grant 'Prefect' status to a student via Admission Number.</p>
                <form method="POST">
                    <input type="text" name="admission_number" class="form-input" placeholder="Enter Admission No (e.g. 2026001)" required>
                    <button type="submit" name="make_prefect" class="btn-add">Grant Authority</button>
                </form>
            </div>

            <div class="tool-card">
                <div class="tool-title"><i class='bx bxs-group'></i> Prefect Squad</div>
                <?php if(empty($prefects)): ?>
                    <p style="font-size:0.85rem; color:#999; font-style:italic;">No prefects appointed yet.</p>
                <?php else: ?>
                    <ul class="pf-list">
                        <?php foreach($prefects as $p): ?>
                        <li class="pf-item">
                            <div>
                                <strong><?php echo htmlspecialchars($p['full_name']); ?></strong><br>
                                <span style="font-size:0.75rem; color:#919eab;"><?php echo $p['admission_number']; ?></span>
                            </div>
                            <span style="font-size:0.75rem; background:#f0f0f0; padding:2px 6px; border-radius:4px;"><?php echo $p['class_name']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>