<?php
// accountant/fees.php
session_start();
require '../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../index.php"); exit;
}

$message = "";
$msg_type = "";

// HANDLE PAYMENT FORM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_fee'])) {
    $s_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $method = $_POST['payment_method'];

    if(!empty($s_id) && !empty($amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO fee_payments (student_id, amount, term, payment_method, recorded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$s_id, $amount, $term, $method, $_SESSION['user_id']]);
            $message = "Payment of $$amount recorded successfully.";
            $msg_type = "success";
        } catch (PDOException $e) {
            $message = "Error recording payment: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $message = "Please fill in all fields.";
        $msg_type = "error";
    }
}

// FETCH STUDENTS FOR DROPDOWN
$students = $pdo->query("SELECT user_id, full_name, email FROM users WHERE role='student' ORDER BY full_name")->fetchAll();

// FETCH PAYMENT HISTORY
$history = $pdo->query("SELECT fp.*, u.full_name, u.email FROM fee_payments fp JOIN users u ON fp.student_id = u.user_id ORDER BY fp.payment_date DESC")->fetchAll();

// CALC TODAY'S TOTAL
$today_total = $pdo->query("SELECT SUM(amount) FROM fee_payments WHERE DATE(payment_date) = CURDATE()")->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Fees | NGA Finance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --border: #dfe3e8; --green: #00ab55; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }

        /* NAV (Matches Dashboard) */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: 75px; background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* CONTENT */
        .main-content { margin-top: 75px; padding: 40px 5%; max-width: 1400px; margin-left: auto; margin-right: auto; }
        
        /* HEADER SECTION */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        .breadcrumb { color: #637381; font-size: 0.9rem; margin-top: 5px; }

        /* LAYOUT GRID */
        .fees-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        
        /* CARDS */
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #f4f6f8; padding-bottom: 15px; }
        .card-title { margin: 0; font-size: 1.1rem; color: var(--dark); font-weight: 700; display: flex; align-items: center; gap: 10px; }

        /* FORM ELEMENTS */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: #637381; margin-bottom: 8px; text-transform: uppercase; }
        .form-control, .styled-select {
            width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-family: inherit; font-size: 0.95rem; color: var(--dark); background: #f9fafb; outline: none; transition: 0.2s; box-sizing: border-box;
        }
        .form-control:focus, .styled-select:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1); }
        
        .btn-submit { width: 100%; background: var(--dark); color: white; border: none; padding: 14px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 10px; }
        .btn-submit:hover { background: var(--primary); transform: translateY(-2px); }

        /* TABLE */
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th { text-align: left; padding: 15px; font-size: 0.75rem; color: #637381; text-transform: uppercase; border-bottom: 1px solid var(--border); background: #f9fafb; font-weight: 700; }
        .styled-table td { padding: 15px; border-bottom: 1px solid #f4f6f8; font-size: 0.9rem; color: var(--dark); vertical-align: middle; }
        .styled-table tr:hover td { background: #f9fafb; }

        .status-badge { background: #e9fcd4; color: #229a16; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .amount-text { font-weight: 700; color: var(--dark); }

        /* ALERT */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">NGA Finance</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="fees.php" class="nav-item active"><i class='bx bx-money'></i> <span>Fees</span></a>
        <a href="expenses.php" class="nav-item"><i class='bx bx-cart'></i> <span>Expenses</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Tuition & Fees</h1>
            <div class="breadcrumb">Manage student payments and records</div>
        </div>
        <div style="background:white; padding:10px 20px; border-radius:8px; border:1px solid var(--border); display:flex; gap:15px; align-items:center;">
            <div style="font-size:0.8rem; color:#637381; font-weight:700; text-transform:uppercase;">Collected Today</div>
            <div style="font-size:1.2rem; font-weight:800; color:var(--green);">+$<?php echo number_format($today_total); ?></div>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class='bx <?php echo $msg_type == "success" ? "bx-check-circle" : "bx-error-circle"; ?>'></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="fees-grid">
        
        <div class="card" style="height: fit-content;">
            <div class="card-header">
                <h3 class="card-title"><i class='bx bxs-credit-card-alt'></i> Record Payment</h3>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Select Student</label>
                    <select name="student_id" class="styled-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach($students as $s): ?>
                            <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Amount ($)</label>
                    <input type="number" name="amount" class="form-control" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment For (Term)</label>
                    <input type="text" name="term" class="form-control" placeholder="e.g. Term 1 2026" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="styled-select">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>

                <button type="submit" name="pay_fee" class="btn-submit">
                    <i class='bx bx-check'></i> Confirm Payment
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class='bx bx-history'></i> Transaction History</h3>
                <button class="btn-logout" style="border:1px solid var(--border); color:#637381; padding:5px 10px; font-size:0.8rem;" onclick="window.print()"><i class='bx bx-printer'></i> Print</button>
            </div>

            <div style="overflow-x: auto;">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student Name</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($history) > 0): ?>
                            <?php foreach($history as $h): ?>
                            <tr>
                                <td style="color:#637381; font-family:monospace;"><?php echo date("M d, Y", strtotime($h['payment_date'])); ?></td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($h['full_name']); ?></div>
                                    <div style="font-size:0.75rem; color:#999;"><?php echo htmlspecialchars($h['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($h['term']); ?></td>
                                <td><span style="background:#f4f6f8; padding:2px 6px; border-radius:4px; font-size:0.8rem;"><?php echo $h['payment_method']; ?></span></td>
                                <td class="amount-text" style="color:var(--green);">+$<?php echo number_format($h['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>