<?php
// teacher/debug_db.php
require 'config/db.php';

echo "<h1>Database Debugger</h1>";

// 1. Check if 'class_assessments' exists
try {
    $stmt = $pdo->query("SELECT * FROM class_assessments LIMIT 1");
    echo "<p style='color:green'>✅ Table <b>'class_assessments'</b> found.</p>";
    
    // Check columns
    $cols = $pdo->query("SHOW COLUMNS FROM class_assessments")->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $cols) . "<br><br>";
    
    // Check ID 15
    $check = $pdo->query("SELECT * FROM class_assessments WHERE assessment_id = 15");
    if ($check->rowCount() > 0) {
        echo "<p style='color:green'>✅ ID 15 exists.</p>";
        print_r($check->fetch(PDO::FETCH_ASSOC));
    } else {
        echo "<p style='color:red'>❌ Table exists, but ID 15 was NOT found in 'assessment_id' column.</p>";
        // Check if maybe 'id' column exists
        if(in_array('id', $cols)) {
            $check2 = $pdo->query("SELECT * FROM class_assessments WHERE id = 15");
            if ($check2->rowCount() > 0) {
                 echo "<p style='color:orange'>💡 Found ID 15 under the column <b>'id'</b>! You need to change your PHP code to look for 'id' instead of 'assessment_id'.</p>";
            }
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Table <b>'class_assessments'</b> NOT found.</p>";
    
    // Check if maybe 'assessments' exists
    try {
        $stmt = $pdo->query("SELECT * FROM assessments LIMIT 1");
        echo "<p style='color:orange'>💡 But table <b>'assessments'</b> WAS found! <br>You should rename it to 'class_assessments' in your database.</p>";
    } catch (Exception $ex) {
        echo "Table 'assessments' also not found.";
    }
}
?>