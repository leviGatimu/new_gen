<?php
// parent/pay.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../index.php"); exit;
}

$parent_id = $_SESSION['user_id'];

// --- FIX 1: VALIDATE ID ---
// If the URL does not have ?student_id=X, go back to dashboard immediately.
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

$student_id = $_GET['student_id'];
$error = "";
$success = "";

// 1. FETCH STUDENT & FEE INFO
// --- FIX 2: USE LEFT JOIN ---
// We use LEFT JOIN on classes so it works even if the student isn't assigned a class yet.
$stmt = $pdo->prepare("SELECT s.student_id, u.full_name, s.expected_fees, s.class_id, c.class_name 
                       FROM students s
                       JOIN users u ON s.student_id = u.user_id
                       LEFT JOIN classes c ON s.class_id = c.class_id
                       WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// --- FIX 3: BETTER ERROR HANDLING ---
if (!$student) {
    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h2>Student Not Found.</h2>
            <p>We could not find the student you are trying to pay for.</p>
            <a href='dashboard.php' style='color:blue; text-decoration:underline;'>Go Back to Dashboard</a>
          </div>";
    exit;
}

// Calculate Balance
$p_stmt = $pdo->prepare("SELECT SUM(amount) FROM fee_payments WHERE student_id = ?");
$p_stmt->execute([$student_id]);
$paid = $p_stmt->fetchColumn() ?: 0;
$balance = $student['expected_fees'] - $paid;

// 2. HANDLE PAYMENT SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $phone = $_POST['phone'];
    $method = $_POST['method']; // e.g. "MTN Mobile Money"

    if ($amount > 0 && !empty($phone)) {
        // SIMULATE PAYMENT (In real life, you would call an API here)
        
        // Insert into Database
        $pay_stmt = $pdo->prepare("INSERT INTO fee_payments (student_id, amount, term, payment_method, recorded_by, payment_date) VALUES (?, ?, ?, ?, ?, NOW())");
        
        $pay_stmt->execute([
            $student_id, 
            $amount, 
            "Online Payment (" . date('M Y') . ")", 
            $method, 
            $parent_id // Recorded by Parent
        ]);

        $success = "Payment successful! Receipt sent to $phone.";
        
        // Refresh Balance Display
        $paid += $amount;
        $balance -= $amount;
        
        // Redirect back to dashboard after 2 seconds
        header("refresh:2;url=dashboard.php");
    } else {
        $error = "Please enter a valid amount and phone number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Payment | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { background: #f4f6f8; font-family: 'Public Sans', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        
        .checkout-container {
            background: white; width: 100%; max-width: 900px; border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr 1.2fr; overflow: hidden;
        }

        /* LEFT SIDE: INFO */
        .info-panel {
            background: #212b36; color: white; padding: 40px; display: flex; flex-direction: column; justify-content: space-between;
        }
        .student-preview { margin-top: 30px; }
        .st-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 5px; }
        .st-val { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: block; }
        
        .total-box { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; }
        .big-price { font-size: 2.5rem; font-weight: 800; color: #FF6600; }

        /* RIGHT SIDE: FORM */
        .form-panel { padding: 50px; }
        .form-group { margin-bottom: 20px; }
        .label { display: block; font-weight: 700; color: #637381; margin-bottom: 8px; font-size: 0.9rem; }
        .input { width: 100%; padding: 15px; border: 1px solid #dfe3e8; border-radius: 8px; font-size: 1rem; box-sizing: border-box; transition: 0.3s; }
        .input:focus { border-color: #FF6600; outline: none; box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1); }

        .method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .method-option {
            border: 2px solid #eee; border-radius: 10px; padding: 15px; cursor: pointer; text-align: center; transition: 0.2s;
        }
        .method-option:hover { border-color: #FF6600; background: #fffbf7; }
        .method-option input { display: none; }
        .method-option.selected { border-color: #FF6600; background: #fff0e6; color: #FF6600; font-weight: bold; }
        
        .btn-pay { width: 100%; background: #FF6600; color: white; padding: 18px; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 800; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-pay:hover { background: #e65c00; transform: translateY(-2px); }

        .back-link { position: absolute; top: 20px; left: 20px; color: white; text-decoration: none; font-weight: bold; opacity: 0.8; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .alert-success { background: #e9fcd4; color: #229a16; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; }
    </style>
</head>
<body>

<div class="checkout-container">
    
    <div class="info-panel">
        <a href="dashboard.php" class="back-link">&larr; Cancel</a>
        
        <div>
            <h2 style="margin:0;">NGA Secure Pay</h2>
            <div class="student-preview">
                <span class="st-label">Paying For</span>
                <span class="st-val"><?php echo htmlspecialchars($student['full_name']); ?></span>

                <span class="st-label">Class</span>
                <span class="st-val"><?php echo htmlspecialchars($student['class_name']); ?></span>
            </div>
        </div>

        <div class="total-box">
            <span class="st-label">Outstanding Balance</span>
            <div class="big-price">RWF <?php echo number_format($balance); ?></div>
        </div>
    </div>

    <div class="form-panel">
        <h2 style="margin-top:0; color:#212b36;">Payment Details</h2>
        
        <?php if($success): ?>
            <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="label">Payment Method</label>
            <div class="method-grid">
                <label class="method-option selected" onclick="selectMethod(this)">
                    <input type="radio" name="method" value="MTN Mobile Money" checked>
                    <i class='bx bx-mobile-alt'></i> MTN MoMo
                </label>
                <label class="method-option" onclick="selectMethod(this)">
                    <input type="radio" name="method" value="Airtel Money">
                    <i class='bx bx-mobile'></i> Airtel Money
                </label>
            </div>

            <div class="form-group">
                <label class="label">Amount to Pay (RWF)</label>
                <input type="number" name="amount" class="input" value="<?php echo $balance > 0 ? $balance : ''; ?>" required>
            </div>

            <div class="form-group">
                <label class="label">Phone Number</label>
                <input type="text" name="phone" class="input" placeholder="078..." required>
            </div>

            <button type="submit" class="btn-pay">
                <i class='bx bxs-lock-alt'></i> Pay Securely
            </button>
            
            <p style="text-align:center; color:#999; font-size:0.8rem; margin-top:20px;">
                <i class='bx bxs-shield-check'></i> 128-bit SSL Encrypted Transaction
            </p>
        </form>
    </div>

</div>

<script>
    function selectMethod(element) {
        document.querySelectorAll('.method-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
    }
</script>

</body>
</html>