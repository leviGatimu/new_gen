<?php
// admin/finance_report.php
session_start();
require '../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

// --- 1. GET TOTALS (The Big Numbers) ---
$inc_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_income = $inc_stmt->fetchColumn() ?: 0;

$exp_stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
$total_expenses = $exp_stmt->fetchColumn() ?: 0;

$net_profit = $total_income - $total_expenses;

// --- 2. GET TERMLY BREAKDOWN (System Generated) ---
$term_stmt = $pdo->query("
    SELECT term, SUM(amount) as term_total, COUNT(*) as transaction_count, MAX(payment_date) as last_payment 
    FROM fee_payments 
    GROUP BY term 
    ORDER BY last_payment DESC
");
$term_data = $term_stmt->fetchAll();

// --- 3. GET EXPENSE BREAKDOWN (By Category) ---
$cat_stmt = $pdo->query("
    SELECT category, SUM(amount) as cat_total 
    FROM expenses 
    GROUP BY category 
    ORDER BY cat_total DESC
");
$cat_data = $cat_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report | NGA Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* === THEME VARIABLES (MATCHING DASHBOARD) === */
        :root { --primary: #FF6600; --primary-hover: #e65c00; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* === NAV === */
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

        /* === MAIN CONTENT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; max-width: 1400px; margin-left: auto; margin-right: auto; }

        /* Welcome Banner style reused for Header */
        .welcome-banner { background: var(--white); padding: 30px; border-radius: 16px; margin-bottom: 35px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: linear-gradient(120deg, #fff 0%, #fffbf7 100%); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: var(--white); padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.3s; position: relative; }
        .stat-label { font-size: 0.85rem; color: #637381; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--dark); margin: 12px 0; }
        .stat-trend { font-size: 0.85rem; font-weight: 600; }

        /* Report Sections (Card Style) */
        .report-card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .sec-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f4f6f8; }
        .sec-title { margin: 0; color: var(--dark); font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f9fafb; color: #637381; font-size: 0.85rem; text-transform: uppercase; font-weight: 700; }
        td { padding: 15px; border-bottom: 1px solid #f4f6f8; color: var(--dark); font-weight: 600; vertical-align: middle; font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }

        /* Bars */
        .bar-container { background: #eee; height: 6px; width: 100px; border-radius: 4px; overflow: hidden; display: inline-block; vertical-align: middle; margin-left: 10px; }
        .bar-fill { height: 100%; background: var(--primary); }

        /* Print Button */
        .btn-print { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-print:hover { background: var(--primary); }

        /* Print Styles */
        @media print {
            .top-navbar, .btn-print { display: none; }
            .main-content { margin: 0; padding: 0; max-width: 100%; }
            body { background: white; }
            .report-card, .stat-card { border: 1px solid #000; box-shadow: none; page-break-inside: avoid; }
        }
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
        <a href="finance_report.php" class="nav-item active"><i class='bx bxs-bar-chart-alt-2'></i> <span>Finance</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    
    <div class="welcome-banner">
        <div>
            <h2 style="margin:0; font-size:1.8rem; color:var(--dark);">Financial Report</h2>
            <p style="color: #637381; margin: 5px 0 0; font-size: 0.95rem;">System generated analysis of school finances.</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn-print"><i class='bx bxs-printer'></i> Print Report</button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number" style="color:#00ab55;">RWF <?php echo number_format($total_income); ?></div>
            <div class="stat-trend">All Time Collected</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-number" style="color:#ff4d4f;">RWF <?php echo number_format($total_expenses); ?></div>
            <div class="stat-trend">Operational Costs</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Net Balance</div>
            <div class="stat-number" style="color:<?php echo $net_profit > 0 ? '#212b36' : 'red'; ?>;">
                RWF <?php echo number_format($net_profit); ?>
            </div>
            <div class="stat-trend">Available Funds</div>
        </div>
    </div>

    <div class="report-card">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-calendar'></i> Termly Income Breakdown</h3>
            <span style="color:#637381; font-size:0.85rem;">Grouped by Term</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Term / Period</th>
                    <th>Transactions</th>
                    <th>Revenue Collected</th>
                    <th>Contribution</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($term_data as $term): ?>
                <?php 
                    $percent = ($total_income > 0) ? ($term['term_total'] / $total_income) * 100 : 0; 
                ?>
                <tr>
                    <td>
                        <div style="font-size:1rem;"><?php echo htmlspecialchars($term['term']); ?></div>
                        <span style="font-size:0.8rem; color:#999;">Last active: <?php echo date("d M Y", strtotime($term['last_payment'])); ?></span>
                    </td>
                    <td><?php echo $term['transaction_count']; ?> Payments</td>
                    <td style="color:#00ab55;">RWF <?php echo number_format($term['term_total']); ?></td>
                    <td>
                        <span style="font-size:0.85rem; width:40px; display:inline-block;"><?php echo round($percent, 1); ?>%</span>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($term_data)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No fee data recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="report-card">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-wallet-alt'></i> Expense Analysis</h3>
            <span style="color:#637381; font-size:0.85rem;">Grouped by Category</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Expense Category</th>
                    <th>Total Spent</th>
                    <th>Impact</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cat_data as $cat): ?>
                <?php 
                    $exp_percent = ($total_expenses > 0) ? ($cat['cat_total'] / $total_expenses) * 100 : 0; 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat['category']); ?></td>
                    <td style="color:#ff4d4f;">RWF <?php echo number_format($cat['cat_total']); ?></td>
                    <td>
                        <span style="font-size:0.85rem; width:40px; display:inline-block;"><?php echo round($exp_percent, 1); ?>%</span>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: <?php echo $exp_percent; ?>%; background:#ff4d4f;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($cat_data)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:#999;">No expenses recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>