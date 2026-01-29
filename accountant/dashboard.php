<?php
// accountant/dashboard.php
session_start();
require '../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../index.php"); exit;
}

// --- FINANCIAL CALCULATIONS ---
// 1. Total Fees Collected
$income_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_income = $income_stmt->fetchColumn() ?: 0;

// 2. Total Expenses
$expense_stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
$total_expense = $expense_stmt->fetchColumn() ?: 0;

// 3. Current Balance
$balance = $total_income - $total_expense;

// 4. Recent Transactions
$recent_fees = $pdo->query("SELECT fp.*, u.full_name FROM fee_payments fp JOIN users u ON fp.student_id = u.user_id ORDER BY fp.payment_date DESC LIMIT 5")->fetchAll();
$recent_exps = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Dashboard | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* === THEME VARIABLES === */
        :root { 
            --primary: #FF6600; --primary-hover: #e65c00; 
            --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; 
            --green: #00ab55; --red: #ff4d4f; --blue: #2d99ff;
            --nav-height: 75px;
        }
        
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* === TOP NAVIGATION BAR (Unified Theme) === */
        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: var(--white); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid var(--border); box-sizing: border-box;
        }

        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .nav-menu { display: flex; gap: 10px; align-items: center; }
        .nav-item {
            text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem;
            padding: 10px 15px; border-radius: 8px; transition: 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }

        .btn-logout {
            text-decoration: none; color: var(--red); font-weight: 700; font-size: 0.85rem;
            padding: 8px 16px; border: 1.5px solid var(--red); border-radius: 8px; transition: 0.2s;
        }
        .btn-logout:hover { background: var(--red); color: white; }

        /* === MAIN CONTENT === */
        .main-content {
            margin-top: var(--nav-height); padding: 40px 5%;
            max-width: 1400px; margin-left: auto; margin-right: auto;
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: var(--white); padding: 30px; border-radius: 16px; margin-bottom: 35px;
            border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            background: linear-gradient(120deg, #fff 0%, #fffbf7 100%);
        }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card {
            background: var(--white); padding: 25px; border-radius: 16px;
            border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: relative; transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .stat-icon-bg {
            position: absolute; right: 20px; top: 20px; width: 50px; height: 50px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; opacity: 0.2;
        }
        .stat-label { font-size: 0.85rem; color: #637381; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 2rem; font-weight: 800; color: var(--dark); margin: 10px 0; }
        .stat-trend { font-size: 0.85rem; font-weight: 600; }

        /* SPLIT LAYOUT */
        .dashboard-split { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f4f6f8; padding-bottom: 15px; }
        .card-title { margin: 0; font-size: 1.1rem; color: var(--dark); font-weight: 700; }
        
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f4f6f8; }
        .list-item:last-child { border-bottom: none; }
        .list-info h4 { margin: 0 0 5px; font-size: 0.95rem; color: var(--dark); }
        .list-info span { font-size: 0.8rem; color: #637381; }
        .money-badge { font-weight: 700; font-family: monospace; font-size: 0.95rem; }

        /* COLORS */
        .text-green { color: var(--green); }
        .bg-green-light { background: #e9fcd4; color: var(--green); }
        .text-red { color: var(--red); }
        .bg-red-light { background: #ffe7d9; color: var(--red); }
        .text-blue { color: var(--blue); }
        .bg-blue-light { background: #d0f2ff; color: var(--blue); }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">NGA Finance</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="fees.php" class="nav-item">
            <i class='bx bx-money'></i> <span>Fees</span>
        </a>
        <a href="expenses.php" class="nav-item">
            <i class='bx bx-cart'></i> <span>Expenses</span>
        </a>
        <a href="report.php" class="nav-item">
            <i class='bx bx-cart'></i> <span>Report</span>
        </a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    
    <div class="welcome-banner">
        <div>
            <h2 style="margin:0; font-size:1.8rem; color:var(--dark);">Financial Overview</h2>
            <p style="color: #637381; margin: 8px 0 0; font-size: 0.95rem;">
                Tracking income, expenses, and school liquidity.
            </p>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 800; color: var(--dark); font-size: 1rem;"><?php echo date("l, d M Y"); ?></div>
            <div style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">Logged in as Accountant</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-bg bg-green-light"><i class='bx bx-trending-up'></i></div>
            <div class="stat-label">Total Fees Collected</div>
            <div class="stat-number text-green">$<?php echo number_format($total_income, 2); ?></div>
            <div class="stat-trend">Student Payments</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-bg bg-red-light"><i class='bx bx-trending-down'></i></div>
            <div class="stat-label">Total Expenses</div>
            <div class="stat-number text-red">$<?php echo number_format($total_expense, 2); ?></div>
            <div class="stat-trend">Operational Costs</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-bg bg-blue-light"><i class='bx bxs-wallet'></i></div>
            <div class="stat-label">Net Balance</div>
            <div class="stat-number text-blue">$<?php echo number_format($balance, 2); ?></div>
            <div class="stat-trend">Available Liquidity</div>
        </div>
    </div>

    <div class="dashboard-split">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Fee Payments</h3>
                <a href="fees.php" style="color:var(--primary); text-decoration:none; font-weight:700; font-size:0.85rem;">View All &rarr;</a>
            </div>
            <?php if(empty($recent_fees)): ?>
                <p style="color:#999; text-align:center;">No payments recorded yet.</p>
            <?php else: ?>
                <?php foreach($recent_fees as $f): ?>
                <div class="list-item">
                    <div class="list-info">
                        <h4><?php echo htmlspecialchars($f['full_name']); ?></h4>
                        <span><?php echo htmlspecialchars($f['term']); ?> &bull; <?php echo date("M d", strtotime($f['payment_date'])); ?></span>
                    </div>
                    <div class="money-badge text-green">+<?php echo number_format($f['amount']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Expenses</h3>
                <a href="expenses.php" style="color:var(--red); text-decoration:none; font-weight:700; font-size:0.85rem;">View All &rarr;</a>
            </div>
            <?php if(empty($recent_exps)): ?>
                <p style="color:#999; text-align:center;">No expenses recorded yet.</p>
            <?php else: ?>
                <?php foreach($recent_exps as $e): ?>
                <div class="list-item">
                    <div class="list-info">
                        <h4><?php echo htmlspecialchars($e['category']); ?></h4>
                        <span><?php echo htmlspecialchars($e['description']); ?> &bull; <?php echo date("M d", strtotime($e['expense_date'])); ?></span>
                    </div>
                    <div class="money-badge text-red">-<?php echo number_format($e['amount']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>