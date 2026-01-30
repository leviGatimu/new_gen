<?php
// admin/finance_report.php
session_start();
require '../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

// --- 1. GET TOTALS ---
$inc_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_income = $inc_stmt->fetchColumn() ?: 0;

$exp_stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
$total_expenses = $exp_stmt->fetchColumn() ?: 0;

$net_profit = $total_income - $total_expenses;

// --- 2. GET TERMLY BREAKDOWN ---
$term_stmt = $pdo->query("
    SELECT term, SUM(amount) as term_total, COUNT(*) as transaction_count, MAX(payment_date) as last_payment 
    FROM fee_payments 
    GROUP BY term 
    ORDER BY last_payment DESC
");
$term_data = $term_stmt->fetchAll();

// --- 3. GET EXPENSE BREAKDOWN ---
$cat_stmt = $pdo->query("
    SELECT category, SUM(amount) as cat_total 
    FROM expenses 
    GROUP BY category 
    ORDER BY cat_total DESC
");
$cat_data = $cat_stmt->fetchAll();

// INCLUDE HEADER
$page_title = "Financial Report";
include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === PAGE VARIABLES === */
        :root { --primary: #FF6600; --dark: #1e293b; --gray: #64748b; --bg-card: #ffffff; }

        /* Header Area */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 35px; flex-wrap: wrap; gap: 15px; 
        }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        
        .btn-print { 
            background: white; color: var(--dark); border: 1px solid #dfe3e8; 
            padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-print:hover { background: #f8fafc; border-color: var(--dark); }

        /* Stats Grid */
        .stats-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 25px; margin-bottom: 40px; 
        }
        .stat-card { 
            background: var(--bg-card); padding: 25px; border-radius: 16px; 
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            transition: 0.3s; 
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        .stat-label { font-size: 0.85rem; color: var(--gray); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--dark); margin: 10px 0; }
        .stat-trend { font-size: 0.85rem; font-weight: 600; }

        /* Report Sections */
        .report-card { 
            background: var(--bg-card); border-radius: 16px; border: 1px solid #e2e8f0; 
            padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; 
        }
        .sec-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; 
        }
        .sec-title { margin: 0; color: var(--dark); font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        /* Tables */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; padding: 15px; background: #f8fafc; color: var(--gray); font-size: 0.85rem; text-transform: uppercase; font-weight: 700; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: var(--dark); font-weight: 600; vertical-align: middle; font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }

        /* Progress Bars */
        .bar-container { 
            background: #f1f5f9; height: 8px; width: 100px; border-radius: 4px; 
            overflow: hidden; display: inline-block; vertical-align: middle; margin-left: 15px; 
        }
        .bar-fill { height: 100%; background: var(--primary); border-radius: 4px; }

        /* Print Styles */
        @media print {
            .top-navbar, .btn-print, .nav-menu, .nav-user { display: none !important; }
            .main-content { margin: 0; padding: 0; max-width: 100%; }
            body { background: white; }
            .report-card, .stat-card { border: 1px solid #000; box-shadow: none; break-inside: avoid; }
            .stat-number { color: black !important; }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .sec-header { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Financial Report</h1>
            <p style="color:var(--gray); margin:5px 0 0;">System generated analysis of school finances.</p>
        </div>
        <button onclick="window.print()" class="btn-print"><i class='bx bxs-printer'></i> Print Report</button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number" style="color:#10b981;">RWF <?php echo number_format($total_income); ?></div>
            <div class="stat-trend" style="color:#10b981;"><i class='bx bx-trending-up'></i> Income</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-number" style="color:#ef4444;">RWF <?php echo number_format($total_expenses); ?></div>
            <div class="stat-trend" style="color:#ef4444;"><i class='bx bx-trending-down'></i> Spending</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Net Balance</div>
            <div class="stat-number" style="color:<?php echo $net_profit > 0 ? 'var(--dark)' : '#ef4444'; ?>;">
                RWF <?php echo number_format($net_profit); ?>
            </div>
            <div class="stat-trend" style="color:var(--gray);">Available Funds</div>
        </div>
    </div>

    <div class="report-card">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-calendar'></i> Termly Income Breakdown</h3>
            <span style="color:var(--gray); font-size:0.85rem;">Revenue by Academic Term</span>
        </div>

        <div class="table-responsive">
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
                            <span style="font-size:0.8rem; color:var(--gray);">Last active: <?php echo date("d M Y", strtotime($term['last_payment'])); ?></span>
                        </td>
                        <td><?php echo $term['transaction_count']; ?> Payments</td>
                        <td style="color:#10b981;">RWF <?php echo number_format($term['term_total']); ?></td>
                        <td>
                            <span style="font-size:0.85rem; width:40px; display:inline-block; text-align:right;"><?php echo round($percent, 1); ?>%</span>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: <?php echo $percent; ?>%; background:#10b981;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($term_data)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--gray);">No fee data recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="sec-header">
            <h3 class="sec-title"><i class='bx bxs-wallet-alt'></i> Expense Analysis</h3>
            <span style="color:var(--gray); font-size:0.85rem;">Spending by Category</span>
        </div>
        
        <div class="table-responsive">
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
                        <td style="color:#ef4444;">RWF <?php echo number_format($cat['cat_total']); ?></td>
                        <td>
                            <span style="font-size:0.85rem; width:40px; display:inline-block; text-align:right;"><?php echo round($exp_percent, 1); ?>%</span>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: <?php echo $exp_percent; ?>%; background:#ef4444;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($cat_data)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--gray);">No expenses recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>