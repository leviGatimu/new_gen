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
// Groups payments by the 'term' column saved during payment
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
        :root { --primary: #FF6600; --dark: #212b36; --light: #f4f6f8; --white: #ffffff; --border: #dfe3e8; }
        body { background: var(--light); font-family: 'Public Sans', sans-serif; margin: 0; padding: 0; display: flex; }
        
        /* SIDEBAR */
        .sidebar { width: 250px; background: var(--dark); min-height: 100vh; color: white; padding: 20px; box-sizing: border-box; position: fixed; }
        .brand { font-size: 1.5rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; margin-bottom: 40px; }
        .menu a { display: flex; align-items: center; gap: 15px; color: #b0b0b0; text-decoration: none; padding: 12px; border-radius: 8px; transition: 0.2s; margin-bottom: 5px; }
        .menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu a.active { background: var(--primary); color: white; }

        /* CONTENT */
        .content { margin-left: 250px; padding: 40px; width: 100%; max-width: 1200px; }

        /* HEADER */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { margin: 0; color: var(--dark); font-size: 1.8rem; }
        .btn-print { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: bold; }
        .btn-print:hover { background: var(--primary); }

        /* METRIC CARDS */
        .overview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .metric-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .metric-title { font-size: 0.85rem; font-weight: 700; color: #637381; text-transform: uppercase; margin-bottom: 10px; }
        .metric-val { font-size: 2rem; font-weight: 800; color: var(--dark); }

        /* REPORT TABLES */
        .report-section { background: white; border-radius: 16px; border: 1px solid #dfe3e8; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .sec-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .sec-title { margin: 0; color: var(--dark); font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f9fafb; color: #637381; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: var(--dark); font-weight: 600; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        /* PROGRESS BARS */
        .bar-container { background: #eee; height: 8px; width: 100px; border-radius: 4px; overflow: hidden; display: inline-block; vertical-align: middle; margin-left: 10px; }
        .bar-fill { height: 100%; background: var(--primary); }
        
        /* PRINT MODE */
        @media print {
            .sidebar, .btn-print { display: none; }
            .content { margin: 0; padding: 20px; max-width: 100%; }
            body { background: white; }
            .metric-card, .report-section { border: 1px solid #000; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class='bx bxs-school'></i> NGA Admin</div>
    <div class="menu">
        <a href="dashboard.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="students.php"><i class='bx bxs-user-detail'></i> Students</a>
        <a href="teachers.php"><i class='bx bxs-id-card'></i> Teachers</a>
        <a href="finance_report.php" class="active"><i class='bx bxs-bar-chart-alt-2'></i> Financial Reports</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ff4d4f;"><i class='bx bx-log-out'></i> Logout</a>
    </div>
</div>

<div class="content">
    
    <div class="page-header">
        <div>
            <h1 class="page-title">System Financial Report</h1>
            <p style="color:#666; margin:5px 0 0;">Generated on <?php echo date("d M Y, H:i"); ?></p>
        </div>
        <button onclick="window.print()" class="btn-print"><i class='bx bxs-printer'></i> Print Report</button>
    </div>

    <div class="overview-grid">
        <div class="metric-card">
            <div class="metric-title">Total Revenue (All Time)</div>
            <div class="metric-val" style="color:#00ab55;">RWF <?php echo number_format($total_income); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Total Expenses (All Time)</div>
            <div class="metric-val" style="color:#ff4d4f;">RWF <?php echo number_format($total_expenses); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Net School Balance</div>
            <div class="metric-val" style="color:<?php echo $net_profit > 0 ? '#212b36' : 'red'; ?>;">
                RWF <?php echo number_format($net_profit); ?>
            </div>
        </div>
    </div>

    <div class="report-section">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-calendar'></i> Termly Income Breakdown</h3>
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

    <div class="report-section">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-wallet-alt'></i> Expense Analysis</h3>
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