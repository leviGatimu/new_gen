<?php
// admin/dashboard.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$page_title = "Admin Dashboard";

// --- HANDLE ANNOUNCEMENT BROADCAST ---
$message = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['broadcast_msg'])) {
    $txt = trim($_POST['message']);
    
    if (!empty($txt)) {
        try {
            $pdo->beginTransaction();

            $classes = $pdo->query("SELECT class_id FROM classes")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type, created_at) VALUES (?, ?, ?, 'system', NOW())");
            
            $count = 0;
            foreach ($classes as $class_id) {
                $formatted_msg = "📢 SYSTEM NOTICE: " . $txt;
                $stmt->execute([$admin_id, $class_id, $formatted_msg]);
                $count++;
            }
            
            $pdo->commit();
            $message = "Broadcast sent to $count classes.";
            $msg_type = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// --- FETCH DATA ---
$student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$teacher_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$parent_count  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();
$class_count   = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Financials
$income_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_revenue = $income_stmt->fetchColumn() ?: 0;

// Newest Users
$new_users = $pdo->query("SELECT full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent Broadcasts
$recent_broadcasts = $pdo->prepare("SELECT message, MAX(created_at) as created_at FROM messages WHERE sender_id = ? AND msg_type = 'system' GROUP BY message ORDER BY created_at DESC LIMIT 3");
$recent_broadcasts->execute([$admin_id]);
$broadcasts = $recent_broadcasts->fetchAll();

// INCLUDE HEADER
include '../includes/header.php';
include '../includes/preloader.php';
?>

<div class="container">
    
    <style>
        /* DASHBOARD GRID SYSTEM */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #dfe3e8;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: #FF6600;
        }
        
        .stat-data h3 { font-size: 1.8rem; font-weight: 800; color: #212b36; margin: 5px 0; }
        .stat-data p { margin: 0; color: #637381; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        
        .icon-box {
            width: 45px; height: 45px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        /* Specific Colors */
        .ic-blue { background: #e3f2fd; color: #007bff; }
        .ic-orange { background: #fff7e6; color: #b78103; }
        .ic-green { background: #e6fffa; color: #008080; }
        .ic-purple { background: #f3e5f5; color: #7b1fa2; }

        /* REVENUE CARD (Cleaner Look) */
        .revenue-card {
            background: #212b36; /* Dark professional theme */
            color: white;
            border: none;
        }
        .revenue-card .stat-data h3 { color: white; }
        .revenue-card .stat-data p { color: rgba(255,255,255,0.6); }
        .revenue-card .icon-box { background: rgba(255,255,255,0.1); color: #4caf50; }

        /* MAIN SECTION SPLIT */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        /* Cards Generic */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #dfe3e8;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 1px solid #f4f6f8; padding-bottom: 15px;
        }
        .card-title { font-size: 1.1rem; font-weight: 700; color: #212b36; margin: 0; display: flex; align-items: center; gap: 10px; }

        /* BROADCAST FORM */
        .broadcast-form textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            background: #f9fafb;
            font-size: 0.95rem;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        .broadcast-form textarea:focus { outline: 2px solid #FF6600; background: white; }

        .btn-send {
            background: #FF6600;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.2s;
        }
        .btn-send:hover { background: #e65c00; transform: translateY(-1px); }

        /* LISTS */
        .user-list { list-style: none; padding: 0; margin: 0; }
        .user-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 0; border-bottom: 1px solid #f4f6f8;
        }
        .user-item:last-child { border-bottom: none; }
        .u-info { display: flex; align-items: center; gap: 10px; }
        .u-avatar {
            width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #666; font-size: 0.8rem;
        }
        .u-role { font-size: 0.7rem; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 700; }
        .bg-student { background: #e3f2fd; color: #007bff; }
        .bg-teacher { background: #fff7e6; color: #b78103; }
        .bg-parent { background: #e6fffa; color: #008080; }
        .bg-accountant { background: #ffe7d9; color: #b72136; }

        /* Broadcast Logs */
        .log-item { background: #f9fafb; padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid #FF6600; }
        .log-msg { font-size: 0.9rem; color: #212b36; margin-bottom: 5px; }
        .log-time { font-size: 0.75rem; color: #919eab; }

        /* ALERT */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #e6fffa; color: #008080; border: 1px solid #b2f5ea; }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="stats-row">
        <div class="stat-card revenue-card">
            <div class="stat-data">
                <p>Total Revenue</p>
                <h3>RWF <?php echo number_format($total_revenue); ?></h3>
            </div>
            <div class="icon-box">
                <i class='bx bxs-dollar-circle'></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-data">
                <p>Total Students</p>
                <h3><?php echo $student_count; ?></h3>
            </div>
            <div class="icon-box ic-blue"><i class='bx bxs-user'></i></div>
        </div>

        <div class="stat-card">
            <div class="stat-data">
                <p>Teachers</p>
                <h3><?php echo $teacher_count; ?></h3>
            </div>
            <div class="icon-box ic-orange"><i class='bx bxs-id-card'></i></div>
        </div>

        <div class="stat-card">
            <div class="stat-data">
                <p>Classes</p>
                <h3><?php echo $class_count; ?></h3>
            </div>
            <div class="icon-box ic-purple"><i class='bx bxs-school'></i></div>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="main-grid">
        
        <div style="display: flex; flex-direction: column; gap: 25px;">
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class='bx bx-broadcast'></i> System Announcement</h3>
                </div>
                <div style="background: #fff7e6; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #d48806; font-size: 0.9rem;">
                    <i class='bx bx-info-circle'></i> Sending this will post a notification to <strong><?php echo $class_count; ?> active classes</strong> instantly.
                </div>
                
                <form method="POST" class="broadcast-form">
                    <textarea name="message" rows="4" placeholder="Write your announcement here..." required></textarea>
                    <div style="text-align: right;">
                        <button type="submit" name="broadcast_msg" class="btn-send">
                            <i class='bx bx-paper-plane'></i> Send Broadcast
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class='bx bx-history'></i> Recent Broadcasts</h3>
                </div>
                <?php if(empty($broadcasts)): ?>
                    <p style="color:#999; font-style:italic;">No recent announcements sent.</p>
                <?php else: ?>
                    <?php foreach($broadcasts as $b): ?>
                        <div class="log-item">
                            <div class="log-msg"><?php echo htmlspecialchars(str_replace("📢 SYSTEM NOTICE: ", "", $b['message'])); ?></div>
                            <div class="log-time"><?php echo date("d M Y • H:i", strtotime($b['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class='bx bx-user-plus'></i> Newest Members</h3>
                <a href="students.php" style="font-size:0.85rem; color:#FF6600; text-decoration:none; font-weight:700;">View All</a>
            </div>
            
            <ul class="user-list">
                <?php foreach($new_users as $u): ?>
                <li class="user-item">
                    <div class="u-info">
                        <div class="u-avatar"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></div>
                        <div>
                            <div style="font-weight:700; font-size:0.9rem; color:#212b36;"><?php echo htmlspecialchars($u['full_name']); ?></div>
                            <div style="font-size:0.75rem; color:#999;">Joined <?php echo date("M d", strtotime($u['created_at'])); ?></div>
                        </div>
                    </div>
                    <span class="u-role bg-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
</div>

</body>
</html>