<?php
// student/leadership_hub.php
session_start();
require '../config/db.php';

// 1. SECURITY & ROLE CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is Head Boy or Head Girl
$stmt = $pdo->prepare("SELECT leadership_role, full_name FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.student_id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

if (!in_array($me['leadership_role'], ['Head Boy', 'Head Girl'])) {
    die("Access Denied. This area is restricted to the Head Boy and Head Girl.");
}

$message = "";

// --- ACTIONS ---

// A. PIN ISSUE
if (isset($_GET['pin'])) {
    $pdo->prepare("UPDATE student_issues SET is_pinned = 1 WHERE issue_id = ?")->execute([$_GET['pin']]);
    header("Location: leadership_hub.php"); exit;
}

// B. FORWARD TO ADMIN
if (isset($_GET['forward'])) {
    $issue_id = $_GET['forward'];
    // Fetch issue details
    $i = $pdo->prepare("SELECT * FROM student_issues WHERE issue_id = ?")->execute([$issue_id]);
    $issue = $pdo->query("SELECT * FROM student_issues WHERE issue_id = $issue_id")->fetch();
    
    // Find Admin ID (Assuming ID 1 or fetch first admin)
    $admin = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    
    if ($issue && $admin) {
        $msg = "FORWARDED ISSUE: " . $issue['title'] . "\n\n" . $issue['description'];
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())")
            ->execute([$user_id, $admin['user_id'], $msg]);
        
        $pdo->prepare("UPDATE student_issues SET status = 'Forwarded' WHERE issue_id = ?")->execute([$issue_id]);
        $message = "Issue forwarded to Administration.";
    }
}

// C. APPOINT PREFECT
if (isset($_POST['make_prefect'])) {
    $adm_no = trim($_POST['admission_number']);
    $check = $pdo->prepare("SELECT student_id FROM students WHERE admission_number = ?");
    $check->execute([$adm_no]);
    $target = $check->fetch();

    if ($target) {
        $pdo->prepare("UPDATE students SET leadership_role = 'Prefect' WHERE student_id = ?")->execute([$target['student_id']]);
        $message = "Student $adm_no has been appointed as Prefect.";
    } else {
        $message = "Student not found.";
    }
}

// --- FETCH DATA ---
// 1. Pinned Issues
$pinned = $pdo->query("SELECT si.*, u.full_name FROM student_issues si JOIN users u ON si.sender_id = u.user_id WHERE is_pinned = 1 ORDER BY created_at DESC")->fetchAll();

// 2. Recent Issues
$issues = $pdo->query("SELECT si.*, u.full_name FROM student_issues si JOIN users u ON si.sender_id = u.user_id WHERE is_pinned = 0 ORDER BY created_at DESC")->fetchAll();

// 3. Current Prefects
$prefects = $pdo->query("SELECT u.full_name, s.admission_number, c.class_name FROM students s JOIN users u ON s.student_id = u.user_id JOIN classes c ON s.class_id = c.class_id WHERE s.leadership_role = 'Prefect'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leadership Hub | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --gold: #FFD700; --dark: #212b36; --bg: #f4f6f8; }
        body { background: var(--bg); font-family: 'Public Sans', sans-serif; padding: 40px; }
        
        .header-card {
            background: linear-gradient(135deg, #212b36 0%, #000 100%);
            color: white; padding: 30px; border-radius: 16px; margin-bottom: 30px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 5px solid var(--gold);
        }
        
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .issue-item { border-left: 4px solid #ddd; padding: 15px; margin-bottom: 15px; background: #fff; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .issue-item.pinned { border-left-color: var(--gold); background: #fffdf0; }
        
        .issue-actions a { text-decoration: none; font-size: 0.8rem; font-weight: bold; margin-right: 15px; }
        .btn-pin { color: #b78103; }
        .btn-fwd { color: #007bff; }
        
        .badge-role { background: var(--gold); color: black; padding: 5px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; }
        
        input { padding: 10px; border-radius: 6px; border: 1px solid #ddd; width: 60%; }
        button { padding: 10px 15px; background: var(--dark); color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<div class="header-card">
    <div>
        <span class="badge-role"><i class='bx bxs-crown'></i> <?php echo $me['leadership_role']; ?></span>
        <h1 style="margin:10px 0 0;"><?php echo $me['full_name']; ?></h1>
        <p style="opacity:0.7; margin:5px 0 0;">Student Government Command Center</p>
    </div>
    <a href="dashboard.php" style="color:white; font-weight:bold; text-decoration:none;">&larr; Back to Dashboard</a>
</div>

<?php if($message): ?>
    <div style="background:#e9fcd4; color:#229a16; padding:15px; border-radius:8px; margin-bottom:20px;"><?php echo $message; ?></div>
<?php endif; ?>

<div class="grid">
    
    <div>
        <?php if(!empty($pinned)): ?>
        <h3 style="color:#b78103;"><i class='bx bxs-pin'></i> Priority Issues</h3>
        <?php foreach($pinned as $p): ?>
            <div class="issue-item pinned">
                <div style="display:flex; justify-content:space-between;">
                    <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                    <span style="font-size:0.8rem; color:#666;"><?php echo date("M d", strtotime($p['created_at'])); ?></span>
                </div>
                <p style="margin:5px 0; font-size:0.9rem; color:#444;"><?php echo htmlspecialchars($p['description']); ?></p>
                <div style="font-size:0.8rem; color:#888;">By: <?php echo htmlspecialchars($p['full_name']); ?></div>
                <div class="issue-actions" style="margin-top:10px;">
                    <a href="?forward=<?php echo $p['issue_id']; ?>" class="btn-fwd"><i class='bx bx-send'></i> Forward to Admin</a>
                    <span style="color:green; font-weight:bold;"><?php echo $p['status']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="color:var(--dark);"><i class='bx bx-inbox'></i> Student Voice Inbox</h3>
        <?php foreach($issues as $i): ?>
            <div class="issue-item">
                <div style="display:flex; justify-content:space-between;">
                    <strong><?php echo htmlspecialchars($i['title']); ?></strong>
                    <span style="font-size:0.8rem; color:#666;"><?php echo date("M d", strtotime($i['created_at'])); ?></span>
                </div>
                <p style="margin:5px 0; font-size:0.9rem; color:#444;"><?php echo htmlspecialchars($i['description']); ?></p>
                <div style="font-size:0.8rem; color:#888;">By: <?php echo htmlspecialchars($i['full_name']); ?></div>
                <div class="issue-actions" style="margin-top:10px;">
                    <a href="?pin=<?php echo $i['issue_id']; ?>" class="btn-pin"><i class='bx bxs-pin'></i> Pin</a>
                    <a href="?forward=<?php echo $i['issue_id']; ?>" class="btn-fwd"><i class='bx bx-send'></i> Forward to Admin</a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if(empty($issues) && empty($pinned)) echo "<p style='color:#999;'>No student issues reported yet.</p>"; ?>
    </div>

    <div>
        <div class="card">
            <h3><i class='bx bxs-badge-check'></i> Appoint Prefect</h3>
            <p style="font-size:0.85rem; color:#666;">Enter Admission Number to grant Prefect status.</p>
            <form method="POST">
                <input type="text" name="admission_number" placeholder="e.g. 2026001" required>
                <button type="submit" name="make_prefect" style="margin-top:10px; width:100%;">Grant Status</button>
            </form>
        </div>
        <div class="card" style="border-top: 4px solid #FF6600;">
    <h3><i class='bx bxs-calendar-event'></i> School Event Calendar</h3>
    <p style="font-size:0.8rem; color:#666;">Upcoming events managed by Administration.</p>
    
    <?php if(empty($school_events)): ?>
        <p style="color:#999; font-style:italic;">No upcoming events.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0;">
            <?php foreach($school_events as $ev): ?>
                <li style="padding:10px 0; border-bottom:1px solid #eee;">
                    <div style="font-weight:bold; color:#212b36; font-size:0.95rem;">
                        <?php echo htmlspecialchars($ev['title']); ?>
                    </div>
                    <div style="font-size:0.8rem; color:#637381; margin-top:3px;">
                        <i class='bx bx-calendar'></i> <?php echo date("M d", strtotime($ev['event_date'])); ?> 
                        &bull; <i class='bx bx-time'></i> <?php echo date("H:i", strtotime($ev['event_time'])); ?>
                    </div>
                    <div style="font-size:0.8rem; color:#FF6600; font-weight:600; margin-top:2px;">
                        @ <?php echo htmlspecialchars($ev['location']); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

        <div class="card">
            <h3>Current Prefect Squad</h3>
            <ul style="list-style:none; padding:0;">
                <?php foreach($prefects as $pref): ?>
                    <li style="padding:10px 0; border-bottom:1px solid #eee;">
                        <strong><?php echo htmlspecialchars($pref['full_name']); ?></strong><br>
                        <span style="font-size:0.8rem; color:#666;"><?php echo $pref['class_name']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</div>

</body>
</html>