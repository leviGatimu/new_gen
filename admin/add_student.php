<?php
// admin/add_student.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Add New Student";
$success = '';
$error = '';

// Helper: Generate Access Key (e.g., NGA-7392)
function generateAccessKey() {
    return "NGA-" . rand(1000, 9999);
}

// 2. FORM HANDLING
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['full_name']);
    $class_id = $_POST['class_id'];
    
    $access_key = generateAccessKey();

    try {
        $pdo->beginTransaction();

        // Create User Entry (Role: Student)
        $temp_pass = password_hash("temp123", PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, role, access_key, password, created_at) VALUES (:name, 'student', :key, :pass, NOW())");
        $stmt->execute([
            'name' => $name,
            'key' => $access_key,
            'pass' => $temp_pass
        ]);
        $new_user_id = $pdo->lastInsertId();

        // Generate Admission Number
        $year = date("Y");
        $adm_number = "NGA-" . $year . "-" . str_pad($new_user_id, 3, "0", STR_PAD_LEFT);

        // Create Student Entry
        $stmt = $pdo->prepare("INSERT INTO students (student_id, class_id, admission_number) VALUES (:uid, :cid, :adm)");
        $stmt->execute([
            'uid' => $new_user_id,
            'cid' => $class_id,
            'adm' => $adm_number
        ]);

        $pdo->commit();
        $success = $access_key; // Store just the key for display
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "System Error: " . $e->getMessage();
    }
}

// Fetch Classes for Dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();

// 3. INCLUDE HEADER
include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === PAGE STYLES === */
        :root { --primary: #FF6600; --dark: #212b36; --gray: #637381; --bg-card: #ffffff; }

        /* Header Area */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; 
        }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        
        .btn-back { 
            text-decoration: none; color: var(--gray); font-weight: 600; 
            display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;
            padding: 8px 15px; border-radius: 8px; background: white; border: 1px solid #dfe3e8;
        }
        .btn-back:hover { background: #f4f6f8; color: var(--dark); }

        /* Form Card */
        .form-card { 
            background: white; border-radius: 20px; padding: 40px; 
            max-width: 600px; margin: 0 auto; box-shadow: 0 5px 20px rgba(0,0,0,0.05); 
            border: 1px solid #f1f5f9;
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 700; color: var(--dark); margin-bottom: 8px; font-size: 0.95rem; }
        
        .form-input { 
            width: 100%; padding: 14px; border: 2px solid #f1f5f9; 
            border-radius: 12px; font-size: 1rem; outline: none; transition: 0.2s;
            box-sizing: border-box; color: var(--dark);
        }
        .form-input:focus { border-color: var(--primary); background: #fffcf9; }

        /* Submit Button */
        .btn-submit { 
            background: var(--dark); color: white; border: none; 
            padding: 15px; width: 100%; border-radius: 12px; font-size: 1rem; 
            font-weight: 700; cursor: pointer; display: flex; align-items: center; 
            justify-content: center; gap: 10px; transition: 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 102, 0, 0.2); }

        /* Info Box */
        .info-box { 
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; 
            padding: 15px; margin-bottom: 25px; display: flex; gap: 12px; 
            font-size: 0.9rem; color: var(--gray); line-height: 1.5;
        }
        .info-icon { font-size: 1.4rem; color: var(--primary); }

        /* Success State */
        .success-box { text-align: center; padding: 20px 0; animation: fadeIn 0.5s ease; }
        .success-icon { font-size: 4rem; color: #22c55e; margin-bottom: 15px; }
        .key-display { 
            background: #f0fdf4; border: 2px dashed #86efac; color: #15803d; 
            font-size: 2rem; font-weight: 800; padding: 15px; 
            border-radius: 12px; margin: 20px 0; letter-spacing: 2px;
        }
        .btn-new { 
            background: var(--primary); color: white; padding: 12px 25px; 
            border-radius: 8px; text-decoration: none; font-weight: 700; 
            display: inline-block; margin-top: 10px; 
        }

        /* Error */
        .error-msg { background: #fef2f2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; text-align: center; font-weight: 600; }

        @keyframes fadeIn { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

        /* Mobile */
        @media (max-width: 600px) {
            .form-card { padding: 25px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .btn-back { width: 100%; justify-content: center; }
        }
    </style>

    <div class="page-header">
        <h1 class="page-title">Pre-Register Student</h1>
        <a href="students.php" class="btn-back"><i class='bx bx-arrow-back'></i> Back to List</a>
    </div>

    <div class="form-card">
        
        <?php if($success): ?>
            <div class="success-box">
                <i class='bx bxs-check-circle success-icon'></i>
                <h2 style="margin:0; color:var(--dark);">Registration Successful!</h2>
                <p style="color:var(--gray);">Provide this Access Key to the student:</p>
                
                <div class="key-display"><?php echo $success; ?></div>
                
                <p style="font-size:0.9rem; color:var(--gray);">They will use this key to set up their own account.</p>
                
                <a href="add_student.php" class="btn-new">Register Another</a>
            </div>

        <?php else: ?>
            <?php if($error): ?>
                <div class="error-msg"><i class='bx bx-error-circle'></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-box">
                    <i class='bx bxs-info-circle info-icon'></i>
                    <div>
                        <strong>How this works:</strong>
                        <br>You are creating a placeholder profile. The system will generate a unique <strong>Access Key</strong> which the student uses to finish their signup.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Student's Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="e.g. Keza Marie" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label">Assign Class Level</label>
                    <select name="class_id" class="form-input" required>
                        <option value="" disabled selected>-- Select Class --</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-submit">
                    <i class='bx bx-key'></i> Generate Access Key
                </button>
            </form>
        <?php endif; ?>

    </div>

</div>

</body>
</html>