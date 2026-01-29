<?php
// admin/dashboard.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// --- HANDLE ANNOUNCEMENT BROADCAST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['broadcast_msg'])) {
    $txt = trim($_POST['message']);
    
    if (!empty($txt)) {
        try {
            // Start Transaction to ensure all or nothing
            $pdo->beginTransaction();

            // 1. Get ALL Class IDs
            $classes = $pdo->query("SELECT class_id FROM classes")->fetchAll(PDO::FETCH_COLUMN);
            
            // 2. Prepare Message Insert
            // We insert the message for EVERY class so all students/teachers see it in their group context
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type, created_at) VALUES (?, ?, ?, 'system', NOW())");
            
            $count = 0;
            foreach ($classes as $class_id) {
                $formatted_msg = "📢 SYSTEM NOTICE: " . $txt;
                $stmt->execute([$admin_id, $class_id, $formatted_msg]);
                $count++;
            }
            
            $pdo->commit();
            $message = "Broadcast successfully sent to $count active classes.";
            $msg_type = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error sending broadcast: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// --- FETCH DASHBOARD DATA ---
// Counts
$student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$teacher_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$parent_count  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();
$acct_count    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'accountant'")->fetchColumn(); // ADDED
$class_count   = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Financial Snapshot (For Admin View)
$income_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_revenue = $income_stmt->fetchColumn() ?: 0;

// Newest Users (Last 5)
$new_users = $pdo->query("SELECT full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent System Broadcasts (Last 3 unique messages sent by admin)
// We group by message content to avoid showing the same broadcast 10 times (once per class)
$recent_broadcasts = $pdo->prepare("SELECT message, MAX(created_at) as created_at FROM messages WHERE sender_id = ? AND msg_type = 'system' GROUP BY message ORDER BY created_at DESC LIMIT 3");
$recent_broadcasts->execute([$admin_id]);
$broadcasts = $recent_broadcasts->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Command Center | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* === THEME VARIABLES === */
        :root { --primary: #FF6600; --primary-hover: #e65c00; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* === NAV === */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        
        /* SIDEBAR / MENU (Integrated into Top for cleaner Admin view) */
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === MAIN CONTENT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; max-width: 1400px; margin-left: auto; margin-right: auto; }

        /* Welcome Banner */
        .welcome-banner { background: var(--white); padding: 30px; border-radius: 16px; margin-bottom: 35px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: linear-gradient(120deg, #fff 0%, #fffbf7 100%); }

        /* FINANCE BANNER (New) */
        .finance-banner {
            background: linear-gradient(135deg, #212b36 0%, #161c24 100%);
            color: white; padding: 25px 30px; border-radius: 16px; 
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .btn-report { background: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 0.9rem; transition:0.2s; }
        .btn-report:hover { background: var(--primary-hover); transform: translateY(-2px); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: var(--white); padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.3s; position: relative; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: var(--primary); }
        .stat-label { font-size: 0.85rem; color: #637381; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--dark); margin: 12px 0; }
        .stat-icon { position: absolute; right: 20px; top: 20px; font-size: 40px; opacity: 0.1; color: var(--dark); }

        /* Dashboard Split */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); height: 100%; box-sizing: border-box; }
        
        /* Broadcast Section */
        .broadcast-box { background: #212b36; color: white; padding: 30px; border-radius: 16px; position: relative; overflow: hidden; }
        .broadcast-box::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .form-control { width: 100%; padding: 12px; border-radius: 8px; border: none; font-family: inherit; margin-bottom: 15px; background: rgba(255,255,255,0.1); color: white; }
        .form-control::placeholder { color: rgba(255,255,255,0.6); }
        .form-control:focus { background: rgba(255,255,255,0.2); outline: none; }
        .btn-broadcast { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-broadcast:hover { background: var(--primary-hover); }

        /* Lists */
        .list-item { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f4f6f8; }
        .list-item:last-child { border-bottom: none; }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; background: #f4f6f8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #637381; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-student { background: #e3f2fd; color: #007bff; }
        .bg-teacher { background: #fff7e6; color: #b78103; }
        .bg-parent { background: #e6fffa; color: #008080; }
        .bg-accountant { background: #ffe7d9; color: #b72136; }

        .log-item { background: #f9fafb; padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid var(--primary); }
        .log-date { font-size: 0.75rem; color: #919eab; margin-top: 5px; display: block; }

        /* Alert Messages */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="students.php" class="nav-item"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
                <a href="leadership.php" class="nav-item active"><i class='bx bxs-star'></i> <span>Leadership</span></a>
        <a href="finance_report.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> <span>Finance</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    
    <div class="welcome-banner">
        <div>
            <h2 style="margin:0; font-size:1.8rem; color:var(--dark);">Overview</h2>
            <p style="color: #637381; margin: 5px 0 0; font-size: 0.95rem;">Command Center for New Generation Academy.</p>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 800; color: var(--dark); font-size: 1rem;"><?php echo date("l, d M Y"); ?></div>
            <a href="finance_report.php" style="color: var(--primary); font-weight: 700; font-size: 0.9rem; text-decoration:none;">View Financials &rarr;</a>
        </div>
    </div>

    <div class="finance-banner">
        <div>
            <h2 style="margin:0;">Total School Revenue</h2>
            <div style="font-size:2rem; font-weight:800; color:#4caf50; margin:5px 0;">
                RWF <?php echo number_format($total_revenue); ?>
            </div>
            <span style="opacity:0.7; font-size:0.85rem;">System generated from all collected fees.</span>
        </div>
        <a href="finance_report.php" class="btn-report">
            <i class='bx bxs-pie-chart-alt-2'></i> Termly Reports
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class='bx bxs-user-detail stat-icon'></i>
            <div class="stat-label">Students</div>
            <div class="stat-number"><?php echo $student_count; ?></div>
            <div style="color: #00ab55; font-size: 0.85rem; font-weight: 700;">Enrolled</div>
        </div>
        <div class="stat-card">
            <i class='bx bxs-id-card stat-icon'></i>
            <div class="stat-label">Teachers</div>
            <div class="stat-number"><?php echo $teacher_count; ?></div>
            <div style="color: #637381; font-size: 0.85rem;">Faculty</div>
        </div>
        <div class="stat-card">
            <i class='bx bxs-calculator stat-icon'></i>
            <div class="stat-label">Accountants</div>
            <div class="stat-number"><?php echo $acct_count; ?></div>
            <div style="color: #637381; font-size: 0.85rem;">Finance Team</div>
        </div>
        <div class="stat-card">
            <i class='bx bxs-face stat-icon'></i>
            <div class="stat-label">Parents</div>
            <div class="stat-number"><?php echo $parent_count; ?></div>
            <div style="color: #637381; font-size: 0.85rem;">Linked</div>
        </div>
    </div>

    <div class="dashboard-split">
        
        <div style="display:flex; flex-direction:column; gap:25px;">
            
            <?php if($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="broadcast-box">
                <h3 style="margin-top:0; color:white; display:flex; align-items:center; gap:10px;"><i class='bx bx-broadcast'></i> System Broadcast</h3>
                <p style="color:rgba(255,255,255,0.7); font-size:0.9rem; margin-bottom:20px;">Send a notification to ALL Students, Teachers, and Parents via their class boards.</p>
                
                <form method="POST">
                    <textarea name="message" class="form-control" rows="3" placeholder="Type your announcement here..." required></textarea>
                    <button type="submit" name="broadcast_msg" class="btn-broadcast">
                        SEND TO ALL
                    </button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0; font-size:1.1rem; color:var(--dark);">Recent Broadcasts</h3>
                <?php if(empty($broadcasts)): ?>
                    <p style="color:#999;">No recent system announcements.</p>
                <?php else: ?>
                    <?php foreach($broadcasts as $b): ?>
                        <div class="log-item">
                            <div style="font-weight:600; color:var(--dark);"><?php echo htmlspecialchars(str_replace("📢 SYSTEM NOTICE: ", "", $b['message'])); ?></div>
                            <span class="log-date"><?php echo date("M d, Y @ H:i", strtotime($b['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; font-size:1.1rem; color:var(--dark); border-bottom:1px solid #f4f6f8; padding-bottom:15px; margin-bottom:10px;">Newest Members</h3>
            
            <?php foreach($new_users as $u): ?>
            <div class="list-item">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></div>
                    <div>
                        <div style="font-weight:700; font-size:0.9rem; color:var(--dark);"><?php echo htmlspecialchars($u['full_name']); ?></div>
                        <div style="font-size:0.75rem; color:#999;">Joined <?php echo date("M d", strtotime($u['created_at'])); ?></div>
                    </div>
                </div>
                <span class="badge bg-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span>
            </div>
            <?php endforeach; ?>

            <a href="students.php" style="display:block; text-align:center; margin-top:20px; text-decoration:none; font-weight:700; color:var(--primary); font-size:0.9rem;">View All Students &rarr;</a>
        </div>

    </div>

</div>

</body>
</html>