<?php
// includes/header.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default Title if not set
if (!isset($page_title)) { $page_title = "NGA Portal"; }

// Get User Role & ID
$role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// --- DEFINE MENUS BASED ON ROLE ---
$navItems = [];

switch ($role) {
    case 'admin':
        $navItems = [
            ['link' => 'dashboard.php', 'icon' => 'bxs-dashboard', 'text' => 'Dashboard'],
            ['link' => 'students.php', 'icon' => 'bxs-user-detail', 'text' => 'Students'],
            ['link' => 'teachers.php', 'icon' => 'bxs-id-card', 'text' => 'Teachers'],
            ['link' => 'leadership.php', 'icon' => 'bxs-star', 'text' => 'Leadership'],
            ['link' => 'events.php', 'icon' => 'bxs-calendar-event', 'text' => 'Events'],
            ['link' => 'classes.php', 'icon' => 'bxs-school', 'text' => 'Classes'],
            ['link' => 'finance_report.php', 'icon' => 'bxs-bar-chart-alt-2', 'text' => 'Finance'],
            ['link' => 'settings.php', 'icon' => 'bxs-cog', 'text' => 'Settings'],
        ];
        break;

    case 'student':
        $navItems = [
            ['link' => 'dashboard.php', 'icon' => 'bxs-dashboard', 'text' => 'Dashboard'],
            ['link' => 'academics.php', 'icon' => 'bxs-graduation', 'text' => 'Academics'],
            ['link' => 'results.php', 'icon' => 'bxs-bar-chart-alt-2', 'text' => 'My Results'],
            ['link' => 'messages.php', 'icon' => 'bxs-chat', 'text' => 'Messages'],
            ['link' => 'attendance.php', 'icon' => 'bxs-calendar-check', 'text' => 'Attendance'],
            ['link' => 'class_ranking.php', 'icon' => 'bxs-trophy', 'text' => 'Ranking'],
            ['link' => 'profile.php', 'icon' => 'bxs-user-circle', 'text' => 'Profile'],
        ];
        break;

    case 'teacher':
        $navItems = [
            ['link' => 'dashboard.php', 'icon' => 'bxs-dashboard', 'text' => 'Dashboard'],
            ['link' => 'my_classes.php', 'icon' => 'bxs-chalkboard', 'text' => 'My Classes'],
            ['link' => 'assessments.php', 'icon' => 'bxs-edit', 'text' => 'Assessments'],
            ['link' => 'grading.php', 'icon' => 'bxs-pen', 'text' => 'Grading'],
            ['link' => 'messages.php', 'icon' => 'bxs-chat', 'text' => 'Messages'],
            ['link' => 'profile.php', 'icon' => 'bxs-user', 'text' => 'Profile'],
        ];
        break;

    case 'parent':
        $navItems = [
            ['link' => 'dashboard.php', 'icon' => 'bxs-dashboard', 'text' => 'Dashboard'],
            ['link' => 'messages.php', 'icon' => 'bxs-chat', 'text' => 'Messages'],
            ['link' => 'report_card.php', 'icon' => 'bxs-file-pdf', 'text' => 'Report Cards'],
            ['link' => 'homework.php', 'icon' => 'bxs-book-content', 'text' => 'Homework'],
            ['link' => 'notifications.php', 'icon' => 'bxs-bell', 'text' => 'Notices'],
        ];
        break;

    case 'accountant':
        $navItems = [
            ['link' => 'dashboard.php', 'icon' => 'bxs-dashboard', 'text' => 'Dashboard'],
            ['link' => 'fees.php', 'icon' => 'bx-money', 'text' => 'Collect Fees'],
            ['link' => 'manage_fees.php', 'icon' => 'bx-list-check', 'text' => 'Manage Debts'],
            ['link' => 'set_fees.php', 'icon' => 'bx-cog', 'text' => 'Fee Structure'],
            ['link' => 'expenses.php', 'icon' => 'bx-wallet-alt', 'text' => 'Expenses'],
            ['link' => 'report.php', 'icon' => 'bxs-report', 'text' => 'Reports'],
        ];
        break;
}

// Get current file name for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?> | NGA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-top: 80px; }

        /* === UNIFIED HEADER STYLES === */
        .top-navbar { 
            position: fixed; top: 0; left: 0; width: 100%; height: 70px; 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #dfe3e8; 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 0 5%; box-sizing: border-box; z-index: 1000; 
        }
        
        .nav-brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .logo-img { width: 35px; }

        /* Desktop Menu */
        .nav-menu { display: flex; gap: 10px; align-items: center; }
        .nav-item { color: #637381; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 6px; transition: 0.2s; display:flex; align-items:center; gap:5px; font-size: 0.95rem; }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .btn-logout { color: #ff4d4f; border: 1px solid #ff4d4f; padding: 6px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.9rem; transition:0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }
        
        /* Mobile Toggle */
        .menu-toggle { display: none; font-size: 2rem; color: var(--dark); cursor: pointer; }

        /* Mobile Breakpoint */
        @media (max-width: 900px) {
            .menu-toggle { display: block; }
            .nav-menu {
                position: absolute; top: 70px; left: 0; width: 100%;
                background: white; flex-direction: column; align-items: stretch;
                padding: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
                border-bottom: 1px solid var(--border);
                opacity: 0; pointer-events: none; transform: translateY(-20px);
                transition: all 0.3s ease;
            }
            .nav-menu.show { opacity: 1; pointer-events: auto; transform: translateY(0); }
            .nav-item { padding: 15px; border-bottom: 1px solid #f0f0f0; font-size: 1rem; }
            .btn-logout { display: block; text-align: center; background: #fff0f0; border: none; padding: 15px; margin-top: 10px; }
        }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="../assets/images/logo.png" alt="NGA" class="logo-img">
        <span>NGA Portal</span>
    </a>
    
    <div class="menu-toggle" onclick="toggleMenu()">
        <i class='bx bx-menu'></i>
    </div>

    <div class="nav-menu" id="navMenu">
        <?php foreach($navItems as $item): ?>
            <?php $isActive = ($current_page == $item['link']) ? 'active' : ''; ?>
            <a href="<?php echo $item['link']; ?>" class="nav-item <?php echo $isActive; ?>">
                <i class='bx <?php echo $item['icon']; ?>'></i> <?php echo $item['text']; ?>
            </a>
        <?php endforeach; ?>
        
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<script>
    function toggleMenu() {
        document.getElementById('navMenu').classList.toggle('show');
    }
</script>