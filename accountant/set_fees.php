<?php
// accountant/set_fees.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../index.php"); exit;
}

$message = "";

// --- HANDLE: SETTING A NEW STANDARD FEE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_fee'])) {
    $class_id = $_POST['class_id'];
    $term = trim($_POST['term_name']);
    $amount = $_POST['amount'];
    $due = $_POST['due_date'];

    try {
        $pdo->beginTransaction();

        // 1. Save the "Rule" in fee_structure
        // Check if exists first to avoid duplicates
        $check = $pdo->prepare("SELECT id FROM fee_structure WHERE class_id = ? AND term_name = ?");
        $check->execute([$class_id, $term]);
        
        if ($check->rowCount() > 0) {
            // Update existing rule
            $stmt = $pdo->prepare("UPDATE fee_structure SET amount = ?, due_date = ? WHERE class_id = ? AND term_name = ?");
            $stmt->execute([$amount, $due, $class_id, $term]);
        } else {
            // Create new rule
            $stmt = $pdo->prepare("INSERT INTO fee_structure (class_id, term_name, amount, due_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$class_id, $term, $amount, $due]);
        }

        // 2. THE MAGIC: Auto-Update Every Student in that Class
        // We set their 'expected_fees' to be the SUM of all terms assigned to their class
        $update_students = $pdo->prepare("
            UPDATE students s
            SET s.expected_fees = (
                SELECT SUM(fs.amount) 
                FROM fee_structure fs 
                WHERE fs.class_id = s.class_id
            )
            WHERE s.class_id = ?
        ");
        $update_students->execute([$class_id]);

        $pdo->commit();
        $message = "Success! Fee of RWF " . number_format($amount) . " applied to all students in this class.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// FETCH DATA
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$structures = $pdo->query("SELECT fs.*, c.class_name FROM fee_structure fs JOIN classes c ON fs.class_id = c.class_id ORDER BY fs.due_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Set Term Fees | NGA Finance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light: #f4f6f8; }
        body { background: var(--light); font-family: 'Public Sans', sans-serif; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; }
        
        /* CARD STYLES */
        .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid #dfe3e8; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .form-label { display: block; font-weight: 700; color: #637381; margin-bottom: 8px; font-size: 0.85rem; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #dfe3e8; border-radius: 6px; box-sizing: border-box; margin-bottom: 20px; }
        .btn-submit { background: var(--dark); color: white; width: 100%; padding: 14px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: var(--primary); }

        /* LIST STYLES */
        .fee-list { list-style: none; padding: 0; margin: 0; }
        .fee-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .fee-item:last-child { border-bottom: none; }
        .term-badge { background: #e3f2fd; color: #1565c0; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container">
    <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0; color:var(--dark);">Fee Structure Settings</h2>
        <a href="manage_fees.php" style="text-decoration:none; font-weight:bold; color:#666;">&larr; Back to Manager</a>
    </div>

    <?php if($message): ?>
        <div style="background:#e9fcd4; color:#229a16; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #b7eb8f;"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid">
        
        <div class="card">
            <h3 style="margin-top:0;">Set Class Fee</h3>
            <p style="color:#666; font-size:0.9rem; margin-bottom:25px;">This will automatically update the "Amount Owed" for every student in the selected class.</p>

            <form method="POST">
                <label class="form-label">Select Grade / Class</label>
                <select name="class_id" class="form-control" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>"><?php echo $c['class_name']; ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label">Term Name</label>
                <input type="text" name="term_name" class="form-control" placeholder="e.g. Term 1 2026" required>

                <label class="form-label">Fee Amount (RWF)</label>
                <input type="number" name="amount" class="form-control" placeholder="0.00" required>

                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control" required>

                <button type="submit" name="apply_fee" class="btn-submit">Apply Standard Fee</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Active Fee Structures</h3>
            
            <ul class="fee-list">
                <?php foreach($structures as $s): ?>
                    <li class="fee-item">
                        <div>
                            <div style="font-weight:700; color:var(--dark); font-size:1.1rem;"><?php echo htmlspecialchars($s['class_name']); ?></div>
                            <div style="margin-top:5px;">
                                <span class="term-badge"><?php echo htmlspecialchars($s['term_name']); ?></span>
                                <span style="color:#666; font-size:0.85rem; margin-left:10px;">Due: <?php echo date("M d, Y", strtotime($s['due_date'])); ?></span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:var(--dark); font-size:1.1rem;">RWF <?php echo number_format($s['amount']); ?></div>
                            <div style="font-size:0.75rem; color:#999;">PER STUDENT</div>
                        </div>
                    </li>
                <?php endforeach; ?>
                
                <?php if(empty($structures)): ?>
                    <p style="color:#999; text-align:center;">No standard fees set yet.</p>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</div>

</body>
</html>