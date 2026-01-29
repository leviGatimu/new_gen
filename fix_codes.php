<?php
// fix_codes.php
require 'config/db.php';

try {
    // 1. Get all students who don't have a code yet
    // We check both tables just to be safe, but we store it in 'students' table
    $stmt = $pdo->query("SELECT student_id FROM students WHERE parent_access_code IS NULL OR parent_access_code = ''");
    $students = $stmt->fetchAll();

    echo "Found " . count($students) . " students with missing codes.<br><hr>";

    foreach ($students as $s) {
        // Generate a random code like "NGA-4829"
        $new_code = "NGA-" . rand(1000, 9999);
        
        // Update the database
        $update = $pdo->prepare("UPDATE students SET parent_access_code = ? WHERE student_id = ?");
        $update->execute([$new_code, $s['student_id']]);
        
        echo "Generated code <strong>$new_code</strong> for Student ID " . $s['student_id'] . "<br>";
    }

    echo "<hr><h2 style='color:green'>All Fixed! Go refresh your dashboard.</h2>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>