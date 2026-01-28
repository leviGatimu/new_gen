<?php
session_start();
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') { header("Location: ../index.php"); exit; }

$msg = "";

// RECORD EXPENSE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $cat = $_POST['category'];
    $desc = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['expense_date'];
    
    $stmt = $pdo->prepare("INSERT INTO expenses (category, description, amount, expense_date, recorded_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$cat, $desc, $amount, $date, $_SESSION['user_id']]);
    $msg = "Expense recorded.";
}

$expenses = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Expenses | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { background: #f4f6f8; font-family: 'Public Sans', sans-serif; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 16px; margin-bottom: 30px; }
        .form-control { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #dfe3e8; border-radius: 8px; }
        .btn-submit { background: #ff4d4f; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 15px; background: #f9fafb; color: #637381; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>

<a href="dashboard.php" style="text-decoration:none; font-weight:700; color:#333;">&larr; Back to Dashboard</a>

<?php if($msg) echo "<div style='background:#ffe7d9; color:#7a0c2e; padding:15px; border-radius:8px; margin:20px 0;'>$msg</div>"; ?>

<div class="card">
    <h2><i class='bx bx-cart'></i> Record School Expense</h2>
    <form method="POST">
        <label>Category</label>
        <select name="category" class="form-control">
            <option value="Tax">Tax / Government Fees</option>
            <option value="Food">Food / Kitchen Supplies</option>
            <option value="Materials">Classroom Materials</option>
            <option value="Maintenance">Repairs & Maintenance</option>
            <option value="Salaries">Staff Salaries</option>
            <option value="Other">Other</option>
        </select>
        
        <label>Description</label>
        <input type="text" name="description" class="form-control" placeholder="e.g. Monthly Income Tax or Rice for Kitchen" required>
        
        <label>Amount ($)</label>
        <input type="number" name="amount" class="form-control" required>
        
        <label>Date Spent</label>
        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        
        <button type="submit" name="add_expense" class="btn-submit">Record Expense</button>
    </form>
</div>

<div class="card">
    <h2>Expense Log</h2>
    <table>
        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
            <?php foreach($expenses as $e): ?>
            <tr>
                <td><?php echo date("d M Y", strtotime($e['expense_date'])); ?></td>
                <td><span style="background:#eee; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:0.8rem;"><?php echo $e['category']; ?></span></td>
                <td><?php echo htmlspecialchars($e['description']); ?></td>
                <td style="font-weight:bold; color:#ff4d4f;">-$<?php echo number_format($e['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>