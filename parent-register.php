<?php
// parent-register.php
session_start();
require 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $parent_code = trim($_POST['parent_code']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 1. VERIFY THE PARENT CODE (Updated to check USERS table)
    // We check the 'users' table because that is where you manually added the key.
    $stmt = $pdo->prepare("SELECT u.user_id, u.full_name 
                           FROM users u 
                           WHERE u.access_key = ? AND u.role = 'student'");
    $stmt->execute([$parent_code]);
    $student = $stmt->fetch();

    if ($student) {
        // Code is valid! Now check if parent email exists
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $error = "An account with this email already exists. Please login.";
        } else {
            try {
                $pdo->beginTransaction();

                // 2. CREATE PARENT ACCOUNT
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'parent')");
                $ins->execute([$full_name, $email, $hash]);
                $parent_id = $pdo->lastInsertId();

                // 3. LINK PARENT TO STUDENT
                $link = $pdo->prepare("INSERT INTO parent_student_link (parent_id, student_id) VALUES (?, ?)");
                $link->execute([$parent_id, $student['user_id']]);

                $pdo->commit();
                $success = "Account created! You are now linked to " . htmlspecialchars($student['full_name']);
                
                // Auto-login (Optional) or Redirect
                header("Refresh: 2; url=index.php");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error creating account: " . $e->getMessage();
            }
        }
    } else {
        $error = "Invalid Parent Code. Please check the code provided by your child.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Registration | NGA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f6f8; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Public Sans', sans-serif; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); width: 100%; max-width: 450px; }
        .form-control { width: 100%; padding: 12px; margin: 8px 0 20px; border: 1px solid #dfe3e8; border-radius: 8px; box-sizing: border-box; }
        .btn-submit { width: 100%; background: #FF6600; color: white; padding: 14px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #e65c00; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
        .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
    </style>
</head>
<body>

<div class="card">
    <h2 style="text-align:center; margin-top:0; color:#212b36;">Parent Registration</h2>
    <p style="text-align:center; color:#637381; margin-bottom:30px;">Connect to your child's academic records.</p>

    <?php if($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label style="font-weight:700; font-size:0.85rem; color:#212b36;">Parent Access Code</label>
        <input type="text" name="parent_code" class="form-control" placeholder="e.g. NGA-7777" required>

        <label style="font-weight:700; font-size:0.85rem; color:#212b36;">Your Full Name</label>
        <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>

        <label style="font-weight:700; font-size:0.85rem; color:#212b36;">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="parent@email.com" required>

        <label style="font-weight:700; font-size:0.85rem; color:#212b36;">Create Password</label>
        <input type="password" name="password" class="form-control" placeholder="********" required>

        <button type="submit" class="btn-submit">Register & Link Account</button>
    </form>

    <div style="text-align:center; margin-top:20px;">
        <a href="index.php" style="color:#637381; text-decoration:none; font-size:0.9rem;">Already have an account? Login</a>
    </div>
</div>

</body>
</html>