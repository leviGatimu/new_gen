<?php
session_start();
require '../config/db.php';


// --- FINANCIAL CALCULATIONS ---
// 1. Total Fees Collected
$income_stmt = $pdo->query("SELECT SUM(amount) FROM fee_payments");
$total_income = $income_stmt->fetchColumn() ?: 0;

// 2. Total Expenses
$expense_stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
$total_expense = $expense_stmt->fetchColumn() ?: 0;

// 3. Current Balance
$balance = $total_income - $total_expense;

// 4. Recent Transactions (Merged View)
$recent_fees = $pdo->query("SELECT 'Income' as type, amount, payment_date as date, term as description FROM fee_payments ORDER BY payment_date DESC LIMIT 3")->fetchAll();
$recent_exps = $pdo->query("SELECT 'Expense' as type, amount, expense_date as date, category as description FROM expenses ORDER BY expense_date DESC LIMIT 3")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Finance Dashboard | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --green: #00ab55; --red: #ff4d4f; --blue: #2d99ff; --dark: #212b36; --light: #f4f6f8; }
        body { background: var(--light); font-family: 'Public Sans', sans-serif; margin: 0; }
        
        .top-nav { background: white; height: 70px; display: flex; align-items: center; justify-content: space-between; padding: 0 40px; border-bottom: 1px solid #dfe3e8; }
        .nav-brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .btn-logout { color: var(--red); text-decoration: none; font-weight: 700; border: 1px solid var(--red); padding: 8px 15px; border-radius: 6px; }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid transparent; }
        
        .stat-income { border-color: var(--green); }
        .stat-expense { border-color: var(--red); }
        .stat-balance { border-color: var(--blue); }

        .stat-val { font-size: 2rem; font-weight: 800; margin: 10px 0; color: var(--dark); }
        .stat-label { color: #637381; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; }

        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .panel { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .panel h3 { margin-top: 0; color: var(--dark); border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }

        .btn-big { display: block; width: 100%; padding: 15px; background: var(--dark); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 700; margin-bottom: 10px; transition: 0.2s; }
        .btn-big:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-green { background: var(--green); }
        .btn-red { background: var(--red); }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="#" class="nav-brand"><i class='bx bxs-wallet'></i> NGA Finance</a>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="container">
    <h1 style="margin-bottom:30px;">Financial Overview</h1>

    <div class="stats-grid">
        <div class="stat-card stat-income">
            <div class="stat-label">Total Fees Collected</div>
            <div class="stat-val" style="color:var(--green);">$<?php echo number_format($total_income, 2); ?></div>
        </div>
        <div class="stat-card stat-expense">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-val" style="color:var(--red);">$<?php echo number_format($total_expense, 2); ?></div>
        </div>
        <div class="stat-card stat-balance">
            <div class="stat-label">Net School Balance</div>
            <div class="stat-val" style="color:var(--blue);">$<?php echo number_format($balance, 2); ?></div>
        </div>
    </div>

    <div class="action-grid">
        <div class="panel">
            <h3>Quick Actions</h3>
            <a href="fees.php" class="btn-big btn-green"><i class='bx bx-money'></i> Record Student Fee Payment</a>
            <a href="expenses.php" class="btn-big btn-red"><i class='bx bx-cart'></i> Record New Expense</a>
        </div>

        <div class="panel">
            <h3>Recent Activity</h3>
            <?php foreach($recent_fees as $f): ?>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f9fafb;">
                    <div>
                        <span style="font-weight:700; color:var(--green);">+ Income</span>
                        <div style="font-size:0.85rem; color:#666;">Fees: <?php echo $f['description']; ?></div>
                    </div>
                    <div style="font-weight:700;">$<?php echo number_format($f['amount']); ?></div>
                </div>
            <?php endforeach; ?>
            
            <?php foreach($recent_exps as $e): ?>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f9fafb;">
                    <div>
                        <span style="font-weight:700; color:var(--red);">- Expense</span>
                        <div style="font-size:0.85rem; color:#666;"><?php echo $e['description']; ?></div>
                    </div>
                    <div style="font-weight:700;">$<?php echo number_format($e['amount']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>