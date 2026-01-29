<?php
// accountant/manage_fees.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../index.php"); exit;
}

$message = "";

// --- 1. HANDLE UPDATES (Single & Batch) ---
if (isset($_POST['update_student_fee'])) {
    $s_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $stmt = $pdo->prepare("UPDATE students SET expected_fees = ? WHERE student_id = ?");
    $stmt->execute([$amount, $s_id]);
    $message = "Student fee target updated.";
}

if (isset($_POST['batch_update'])) {
    $class_id = $_POST['class_id'];
    $amount = $_POST['amount'];
    $stmt = $pdo->prepare("UPDATE students SET expected_fees = ? WHERE class_id = ?");
    $stmt->execute([$amount, $class_id]);
    $message = "Class fee structure applied to all students.";
}

// --- 2. FILTER LOGIC ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$class_filter = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// --- 3. SMART QUERY (Joins Parent, Student, Payments) ---
// --- 3. SMART QUERY (Joins Parent, Student, Payments) ---
$sql = "SELECT 
            u.full_name as student_name, 
            s.student_id, 
            s.admission_number, 
            s.expected_fees, 
            c.class_name,
            
            -- Fetch Parent Details
            (SELECT full_name FROM users u2 
             JOIN parent_student_link psl ON u2.user_id = psl.parent_id 
             WHERE psl.student_id = s.student_id LIMIT 1) as parent_name,
             
            (SELECT email FROM users u3 
             JOIN parent_student_link psl2 ON u3.user_id = psl2.parent_id 
             WHERE psl2.student_id = s.student_id LIMIT 1) as parent_email,

            COALESCE(SUM(fp.amount), 0) as total_paid
        FROM students s
        JOIN users u ON s.student_id = u.user_id
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN fee_payments fp ON s.student_id = fp.student_id
        
        WHERE 1=1 ";

if ($class_filter) {
    $sql .= " AND s.class_id = " . intval($class_filter);
}

$sql .= " GROUP BY s.student_id";

// --- FIX: WE REPEAT THE CALCULATION INSTEAD OF USING THE ALIAS 'total_paid' ---
if ($filter == 'unpaid') {
    $sql .= " HAVING (s.expected_fees - COALESCE(SUM(fp.amount), 0)) > 0";
} elseif ($filter == 'paid') {
    $sql .= " HAVING (s.expected_fees - COALESCE(SUM(fp.amount), 0)) <= 0 AND s.expected_fees > 0";
}

$sql .= " ORDER BY (s.expected_fees - COALESCE(SUM(fp.amount), 0)) DESC, c.class_name";

$students = $pdo->query($sql)->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Fee Manager | NGA Finance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --green: #00ab55; --red: #ff4d4f; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }

        /* UNIFIED HEADER */
        .top-navbar { position: sticky; top: 0; width: 100%; height: 75px; background: white; border-bottom: 1px solid #dfe3e8; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; z-index: 1000; box-sizing: border-box; }
        .nav-brand { font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { color: #637381; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .nav-item:hover, .nav-item.active { background: rgba(255, 102, 0, 0.05); color: var(--primary); }

        .main-content { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

        /* CONTROLS BAR */
        .controls-bar { background: white; padding: 20px; border-radius: 12px; display: flex; gap: 20px; align-items: center; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); flex-wrap: wrap; }
        .control-group { display: flex; flex-direction: column; gap: 5px; }
        .label-small { font-size: 0.75rem; font-weight: 700; color: #637381; text-transform: uppercase; }
        .select-style { padding: 10px; border: 1px solid #dfe3e8; border-radius: 6px; min-width: 200px; font-family: inherit; }
        .btn-filter { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; height: 40px; margin-top: auto; }

        /* BATCH UPDATE BOX */
        .batch-box { background: linear-gradient(135deg, #212b36 0%, #161c24 100%); color: white; padding: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .batch-input { padding: 10px; border-radius: 6px; border: none; margin-right: 10px; }
        .btn-batch { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }

        /* TABLE STYLING */
        .styled-table { width: 100%; background: white; border-radius: 12px; border-collapse: collapse; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .styled-table th { background: #f9fafb; padding: 15px; text-align: left; font-size: 0.8rem; color: #637381; text-transform: uppercase; border-bottom: 2px solid #eee; }
        .styled-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; color: var(--dark); }
        .styled-table tr:hover { background: #f8f9fa; }

        /* BADGES & TEXT */
        .parent-info { display: flex; flex-direction: column; }
        .email-link { color: var(--primary); text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; margin-top: 2px; }
        .email-link:hover { text-decoration: underline; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-paid { background: #e9fcd4; color: var(--green); }
        .badge-owe { background: #ffe7d9; color: var(--red); }
        .badge-partial { background: #fff7cd; color: #b78103; }

        /* INLINE EDIT */
        .inline-form { display: flex; align-items: center; gap: 5px; }
        .input-mini { width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; }
        .btn-mini { background: #eee; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; color: var(--dark); }
        .btn-mini:hover { background: var(--green); color: white; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="../assets/images/logo.png" height="35"> NGA Finance
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="fees.php" class="nav-item"><i class='bx bx-money'></i> Payments</a>
        <a href="manage_fees.php" class="nav-item active"><i class='bx bx-slider-alt'></i> Fee Manager</a>
    </div>
</nav>

<div class="main-content">

    <?php if($message): ?>
        <div style="background:#e9fcd4; color:#229a16; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #b7eb8f; display:flex; gap:10px; align-items:center;">
            <i class='bx bxs-check-circle'></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="batch-box">
        <div>
            <h3 style="margin:0 0 5px 0;">🚀 Batch Fee Setup</h3>
            <span style="font-size:0.9rem; opacity:0.8;">Set the expected tuition for an entire class at once.</span>
        </div>
        <form method="POST">
            <select name="class_id" class="batch-input" required>
                <option value="">-- Select Class --</option>
                <?php foreach($classes as $c): echo "<option value='{$c['class_id']}'>{$c['class_name']}</option>"; endforeach; ?>
            </select>
            <input type="number" name="amount" class="batch-input" placeholder="Amount ($)" required>
            <button type="submit" name="batch_update" class="btn-batch">Apply</button>
        </form>
    </div>

    <form method="GET" class="controls-bar">
        <div class="control-group">
            <label class="label-small">Filter by Class</label>
            <select name="class_id" class="select-style" onchange="this.form.submit()">
                <option value="">Show All Classes</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['class_id']; ?>" <?php if($class_filter == $c['class_id']) echo 'selected'; ?>>
                        <?php echo $c['class_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="control-group">
            <label class="label-small">Payment Status</label>
            <select name="filter" class="select-style" onchange="this.form.submit()">
                <option value="all" <?php if($filter == 'all') echo 'selected'; ?>>Show Everyone</option>
                <option value="unpaid" <?php if($filter == 'unpaid') echo 'selected'; ?>>🔴 Debtors Only</option>
                <option value="paid" <?php if($filter == 'paid') echo 'selected'; ?>>🟢 Fully Paid</option>
            </select>
        </div>
    </form>

    <table class="styled-table">
        <thead>
            <tr>
                <th>Student Info</th>
                <th>Parent / Contact</th>
                <th>Expected Fee</th>
                <th>Paid So Far</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $s): ?>
                <?php 
                    $balance = $s['expected_fees'] - $s['total_paid'];
                    
                    // Logic for badges
                    if ($s['expected_fees'] == 0) { $badge_class = "background:#eee; color:#999;"; $status_text = "NOT SET"; }
                    elseif ($balance <= 0) { $badge_class = "badge-paid"; $status_text = "CLEARED"; }
                    elseif ($s['total_paid'] > 0) { $badge_class = "badge-partial"; $status_text = "PARTIAL"; }
                    else { $badge_class = "badge-owe"; $status_text = "UNPAID"; }
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700; font-size:0.95rem;"><?php echo htmlspecialchars($s['student_name']); ?></div>
                        <div style="font-size:0.8rem; color:#637381; margin-top:4px;">
                            <?php echo $s['admission_number']; ?> &bull; <?php echo htmlspecialchars($s['class_name']); ?>
                        </div>
                    </td>
                    
                    <td>
                        <div class="parent-info">
                            <?php if($s['parent_name']): ?>
                                <span style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($s['parent_name']); ?></span>
                                <a href="mailto:<?php echo $s['parent_email']; ?>?subject=Fee Reminder for <?php echo urlencode($s['student_name']); ?>" class="email-link">
                                    <i class='bx bx-envelope'></i> <?php echo htmlspecialchars($s['parent_email']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#999; font-style:italic; font-size:0.85rem;">No parent linked</span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                            <input type="number" name="amount" value="<?php echo $s['expected_fees']; ?>" class="input-mini">
                            <button type="submit" name="update_student_fee" class="btn-mini"><i class='bx bx-check'></i></button>
                        </form>
                    </td>

                    <td style="color:var(--green); font-weight:600;">$<?php echo number_format($s['total_paid']); ?></td>

                    <td>
                        <strong style="color:<?php echo $balance > 0 ? '#ff4d4f' : '#229a16'; ?>; font-size:1rem;">
                            $<?php echo number_format($balance); ?>
                        </strong>
                    </td>

                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span></td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($students)): ?>
                <tr><td colspan="6" style="text-align:center; padding:40px; color:#999;">No students found matching filters.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>