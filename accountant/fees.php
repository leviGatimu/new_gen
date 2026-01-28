<?php
session_start();
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') { header("Location: ../index.php"); exit; }

$msg = "";

// RECORD PAYMENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_fee'])) {
    $s_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    
    $stmt = $pdo->prepare("INSERT INTO fee_payments (student_id, amount, term, recorded_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$s_id, $amount, $term, $_SESSION['user_id']]);
    $msg = "Payment recorded successfully!";
}

// FETCH STUDENTS
$students = $pdo->query("SELECT user_id, full_name FROM users WHERE role='student' ORDER BY full_name")->fetchAll();

// FETCH PAYMENT HISTORY
$history = $pdo->query("SELECT fp.*, u.full_name FROM fee_payments fp JOIN users u ON fp.student_id = u.user_id ORDER BY fp.payment_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Fees | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { background: #f4f6f8; font-family: 'Public Sans', sans-serif; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-control { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #dfe3e8; border-radius: 8px; }
        .btn-submit { background: #00ab55; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 15px; background: #f9fafb; color: #637381; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>

<a href="dashboard.php" style="text-decoration:none; font-weight:700; color:#333;">&larr; Back to Dashboard</a>

<?php if($msg) echo "<div style='background:#e9fcd4; color:#229a16; padding:15px; border-radius:8px; margin:20px 0;'>$msg</div>"; ?>

<div class="card">
    <h2><i class='bx bx-money'></i> Record Fee Payment</h2>
    <form method="POST">
        <label>Select Student</label>
        <select name="student_id" class="form-control" required>
            <?php foreach($students as $s): ?>
                <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>Amount Paid ($)</label>
        <input type="number" name="amount" class="form-control" placeholder="e.g. 500" required>
        
        <label>Term / Description</label>
        <input type="text" name="term" class="form-control" placeholder="e.g. Term 1 2026 Tuition" required>
        
        <button type="submit" name="pay_fee" class="btn-submit">Record Payment</button>
    </form>
</div>

<div class="card">
    <h2>Payment History</h2>
    <table>
        <thead><tr><th>Date</th><th>Student</th><th>Term</th><th>Amount</th></tr></thead>
        <tbody>
            <?php foreach($history as $h): ?>
            <tr>
                <td><?php echo date("d M Y", strtotime($h['payment_date'])); ?></td>
                <td><?php echo htmlspecialchars($h['full_name']); ?></td>
                <td><?php echo htmlspecialchars($h['term']); ?></td>
                <td style="font-weight:bold; color:#00ab55;">+$<?php echo number_format($h['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>