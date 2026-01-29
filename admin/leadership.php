<?php
// admin/leadership.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$message = "";

// Handle Removal from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_role'])) {
    $s_id = $_POST['student_id'];
    $pdo->prepare("UPDATE students SET leadership_role = NULL WHERE student_id = ?")->execute([$s_id]);
    $message = "Role removed successfully.";
}

// Fetch All Leaders
$sql = "SELECT u.full_name, u.email, s.student_id, s.admission_number, s.leadership_role, c.class_name 
        FROM students s 
        JOIN users u ON s.student_id = u.user_id 
        JOIN classes c ON s.class_id = c.class_id 
        WHERE s.leadership_role IS NOT NULL 
        ORDER BY s.leadership_role ASC, u.full_name ASC";
$leaders = $pdo->query($sql)->fetchAll();

// Separate for UI
$head_boy = null;
$head_girl = null;
$prefects = [];

foreach ($leaders as $l) {
    if ($l['leadership_role'] === 'Head Boy') $head_boy = $l;
    elseif ($l['leadership_role'] === 'Head Girl') $head_girl = $l;
    else $prefects[] = $l;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Leadership | NGA Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* Nav (Standard) */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        .main-content { margin-top: var(--nav-height); padding: 40px 5%; width: 100%; box-sizing: border-box; max-width: 1400px; margin-left: auto; margin-right: auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }

        /* Leader Cards */
        .heads-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 50px; }
        .head-card { background: white; border-radius: 16px; padding: 30px; text-align: center; border: 1px solid var(--border); position: relative; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .head-card.gold { border-top: 5px solid #FFD700; }
        .head-avatar { width: 100px; height: 100px; background: #212b36; color: white; font-size: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-weight: 800; border: 4px solid #f4f6f8; }
        .role-badge { background: #FFD700; color: #212b36; padding: 5px 12px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 10px; display: inline-block; }
        
        .prefects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .prefect-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; text-align: center; transition: 0.3s; }
        .prefect-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .p-avatar { width: 60px; height: 60px; background: #f4f6f8; color: #637381; font-size: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; font-weight: 700; }

        .btn-remove { background: #ffe7d9; color: #7a0c2e; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; margin-top: 15px; transition: 0.2s; }
        .btn-remove:hover { background: #ff4d4f; color: white; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="students.php" class="nav-item"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="leadership.php" class="nav-item active"><i class='bx bxs-star'></i> <span>Leadership</span></a>
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="finance_report.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> <span>Finance</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    
    <div class="page-header">
        <div>
            <h1 class="page-title">Student Leadership Council</h1>
            <p style="color:#637381; margin:5px 0 0;">Managing Head Boy, Head Girl, and School Prefects.</p>
        </div>
        <a href="students.php" style="color:var(--primary); font-weight:700; text-decoration:none;">Assign New Leaders &rarr;</a>
    </div>

    <?php if($message): ?>
        <div style="background:#e9fcd4; color:#229a16; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600;"><?php echo $message; ?></div>
    <?php endif; ?>

    <h3 style="color:var(--dark); border-bottom:1px solid #dfe3e8; padding-bottom:10px;">School Heads</h3>
    <div class="heads-container">
        <div class="head-card gold">
            <span class="role-badge">Head Boy</span>
            <?php if($head_boy): ?>
                <div class="head-avatar"><?php echo substr($head_boy['full_name'], 0, 1); ?></div>
                <h2 style="margin:0 0 5px 0; font-size:1.4rem;"><?php echo htmlspecialchars($head_boy['full_name']); ?></h2>
                <p style="margin:0; color:#637381;"><?php echo htmlspecialchars($head_boy['class_name']); ?></p>
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $head_boy['student_id']; ?>">
                    <button type="submit" name="remove_role" class="btn-remove">Remove Position</button>
                </form>
            <?php else: ?>
                <div style="padding:40px 0; color:#999;">
                    <i class='bx bx-user-x' style="font-size:3rem; margin-bottom:10px;"></i><br>
                    No Head Boy Assigned
                </div>
            <?php endif; ?>
        </div>

        <div class="head-card gold">
            <span class="role-badge">Head Girl</span>
            <?php if($head_girl): ?>
                <div class="head-avatar"><?php echo substr($head_girl['full_name'], 0, 1); ?></div>
                <h2 style="margin:0 0 5px 0; font-size:1.4rem;"><?php echo htmlspecialchars($head_girl['full_name']); ?></h2>
                <p style="margin:0; color:#637381;"><?php echo htmlspecialchars($head_girl['class_name']); ?></p>
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $head_girl['student_id']; ?>">
                    <button type="submit" name="remove_role" class="btn-remove">Remove Position</button>
                </form>
            <?php else: ?>
                <div style="padding:40px 0; color:#999;">
                    <i class='bx bx-user-x' style="font-size:3rem; margin-bottom:10px;"></i><br>
                    No Head Girl Assigned
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h3 style="color:var(--dark); border-bottom:1px solid #dfe3e8; padding-bottom:10px; margin-top:40px;">School Prefects</h3>
    
    <?php if(empty($prefects)): ?>
        <p style="color:#637381; font-style:italic;">No prefects assigned yet.</p>
    <?php else: ?>
        <div class="prefects-grid">
            <?php foreach($prefects as $p): ?>
                <div class="prefect-card">
                    <div class="p-avatar"><?php echo substr($p['full_name'], 0, 1); ?></div>
                    <strong style="color:var(--dark); font-size:1rem;"><?php echo htmlspecialchars($p['full_name']); ?></strong>
                    <span style="font-size:0.85rem; color:#637381; margin-top:4px;"><?php echo htmlspecialchars($p['class_name']); ?></span>
                    <span style="background:#f3e5f5; color:#9c27b0; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; margin-top:8px;"><?php echo $p['leadership_role']; ?></span>
                    
                    <form method="POST" style="margin-top:auto;">
                        <input type="hidden" name="student_id" value="<?php echo $p['student_id']; ?>">
                        <button type="submit" name="remove_role" class="btn-remove" style="background:none; color:#ff4d4f; padding:0; margin-top:15px; font-size:0.8rem;">Remove Role</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>