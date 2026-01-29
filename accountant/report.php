<?php
// accountant/report.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../index.php"); exit;
}

// DEFAULT: SHOW THIS MONTH
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date   = isset($_GET['end'])   ? $_GET['end']   : date('Y-m-t');

// FETCH INCOME (FEES)
$fees_sql = "SELECT fp.*, u.full_name FROM fee_payments fp 
             JOIN users u ON fp.student_id = u.user_id 
             WHERE fp.payment_date BETWEEN ? AND ? 
             ORDER BY fp.payment_date ASC";
$stmt = $pdo->prepare($fees_sql);
$stmt->execute([$start_date . " 00:00:00", $end_date . " 23:59:59"]);
$income_rows = $stmt->fetchAll();

// FETCH EXPENSES
$exp_sql = "SELECT * FROM expenses 
            WHERE expense_date BETWEEN ? AND ? 
            ORDER BY expense_date ASC";
$stmt = $pdo->prepare($exp_sql);
$stmt->execute([$start_date, $end_date]);
$expense_rows = $stmt->fetchAll();

// CALCULATE TOTALS
$total_income = 0;
foreach($income_rows as $i) $total_income += $i['amount'];

$total_expense = 0;
foreach($expense_rows as $e) $total_expense += $e['amount'];

$net_balance = $total_income - $total_expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light: #f4f6f8; --border: #dfe3e8; }
        body { background: var(--light); font-family: 'Public Sans', sans-serif; }

        /* === SCREEN ONLY STYLES === */
        @media screen {
            .controls-bar {
                background: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center;
                border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .date-form { display: flex; gap: 10px; align-items: center; }
            .form-input { padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
            .btn-go { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
            .btn-print { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; }
            
            .report-container { max-width: 900px; margin: 40px auto; background: white; padding: 50px; min-height: 1000px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
        }

        /* === PRINT ONLY STYLES === */
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .controls-bar { display: none; }
            .report-container { width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
            @page { margin: 2cm; size: A4; }
        }

        /* === REPORT DOCUMENT DESIGN === */
        .doc-header { border-bottom: 2px solid var(--dark); padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: end; }
        .school-logo { width: 80px; }
        .doc-title { font-size: 2rem; font-weight: 800; color: var(--dark); margin: 0; text-transform: uppercase; }
        .doc-meta { text-align: right; color: #666; font-size: 0.9rem; }
        
        .summary-box { display: flex; justify-content: space-between; background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid var(--border); }
        .sum-item { text-align: center; flex: 1; border-right: 1px solid #ddd; }
        .sum-item:last-child { border-right: none; }
        .sum-label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 5px; }
        .sum-val { font-size: 1.4rem; font-weight: 800; color: var(--dark); }

        .section-title { font-size: 1.1rem; font-weight: 700; color: var(--dark); border-bottom: 1px solid var(--dark); padding-bottom: 5px; margin: 30px 0 15px; text-transform: uppercase; }

        .doc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .doc-table th { text-align: left; border-bottom: 1px solid #999; padding: 8px; text-transform: uppercase; font-size: 0.75rem; }
        .doc-table td { border-bottom: 1px solid #eee; padding: 8px; color: #333; }
        .doc-table tr:last-child td { border-bottom: none; }
        
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .bg-inc { background: #e9fcd4; color: #229a16; }
        .bg-exp { background: #ffe7d9; color: #7a0c2e; }

        .footer-sig { margin-top: 60px; display: flex; justify-content: space-between; padding-top: 40px; }
        .sig-line { width: 200px; border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 0.8rem; font-weight: 700; }
    </style>
</head>
<body>

<div class="controls-bar">
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="dashboard.php" style="text-decoration:none; font-weight:700; color:#666;">&larr; Back</a>
        <form class="date-form" method="GET">
            <input type="date" name="start" value="<?php echo $start_date; ?>" class="form-input">
            <span style="font-weight:bold;">to</span>
            <input type="date" name="end" value="<?php echo $end_date; ?>" class="form-input">
            <button type="submit" class="btn-go">Update View</button>
        </form>
    </div>
    <button onclick="window.print()" class="btn-print"><i class='bx bxs-printer'></i> Print Report</button>
</div>

<div class="report-container">
    
    <div class="doc-header">
        <div>
            <img src="../assets/images/logo.png" class="school-logo">
            <div style="font-weight:700; margin-top:5px;">New Generation Academy</div>
            <div style="font-size:0.8rem; color:#666;">Kigali, Rwanda | finance@nga.rw</div>
        </div>
        <div style="text-align:right;">
            <h1 class="doc-title">Financial Report</h1>
            <div class="doc-meta">
                Generated on: <?php echo date("d M Y"); ?><br>
                Period: <?php echo date("d/m/y", strtotime($start_date)); ?> - <?php echo date("d/m/y", strtotime($end_date)); ?><br>
                Prepared by: <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>
        </div>
    </div>

    <div class="summary-box">
        <div class="sum-item">
            <span class="sum-label">Total Income</span>
            <span class="sum-val" style="color:#229a16;">$<?php echo number_format($total_income, 2); ?></span>
        </div>
        <div class="sum-item">
            <span class="sum-label">Total Expenses</span>
            <span class="sum-val" style="color:#b72136;">$<?php echo number_format($total_expense, 2); ?></span>
        </div>
        <div class="sum-item">
            <span class="sum-label">Net Balance</span>
            <span class="sum-val" style="color:<?php echo $net_balance >= 0 ? '#000' : 'red'; ?>;">
                $<?php echo number_format($net_balance, 2); ?>
            </span>
        </div>
    </div>

    <div class="section-title">Income Statement (Fees Collected)</div>
    <table class="doc-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Description</th>
                <th>Method</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($income_rows as $row): ?>
            <tr>
                <td><?php echo date("M d, Y", strtotime($row['payment_date'])); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['term']); ?></td>
                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                <td style="text-align:right; font-weight:700;">$<?php echo number_format($row['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($income_rows)) echo "<tr><td colspan='5' style='text-align:center; color:#999;'>No income records in this period.</td></tr>"; ?>
        </tbody>
    </table>

    <div class="section-title">Expense Report</div>
    <table class="doc-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($expense_rows as $row): ?>
            <tr>
                <td><?php echo date("M d, Y", strtotime($row['expense_date'])); ?></td>
                <td><span class="badge bg-exp"><?php echo htmlspecialchars($row['category']); ?></span></td>
                <td><?php echo htmlspecialchars($row['description']); ?></td>
                <td style="text-align:right; font-weight:700; color:#b72136;">$<?php echo number_format($row['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($expense_rows)) echo "<tr><td colspan='4' style='text-align:center; color:#999;'>No expenses recorded in this period.</td></tr>"; ?>
        </tbody>
    </table>

    <div class="footer-sig">
        <div class="sig-line">School Accountant</div>
        <div class="sig-line">School Principal</div>
    </div>

</div>

</body>
</html>