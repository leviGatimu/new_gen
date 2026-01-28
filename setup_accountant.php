<?php
// setup_accountant.php
require 'config/db.php';

// --- SETTINGS ---
$acc_name  = "School Accountant";
$acc_email = "accountant@nga.com";
$acc_pass  = "money123"; // This is your password
// ----------------

try {
    // 1. Hash the password
    $hashed_password = password_hash($acc_pass, PASSWORD_DEFAULT);

    // 2. Check if this email already exists
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$acc_email]);
    $exists = $check->fetch();

    if ($exists) {
        // UPDATE EXISTING USER
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'accountant', full_name = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $acc_name, $acc_email]);
        echo "<h2 style='color:green;'>Success! Accountant Updated.</h2>";
    } else {
        // CREATE NEW USER
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'accountant')");
        $stmt->execute([$acc_name, $acc_email, $hashed_password]);
        echo "<h2 style='color:green;'>Success! Accountant Created.</h2>";
    }

    echo "<p><strong>Email:</strong> $acc_email</p>";
    echo "<p><strong>Password:</strong> $acc_pass</p>";
    echo "<br><a href='index.php'>Go to Login</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
?>