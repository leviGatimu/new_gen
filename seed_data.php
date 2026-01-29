<?php
// seed_data.php
require 'config/db.php';

// --- CONFIGURATION ---
$STUDENTS_PER_CLASS = 20;
$PASSWORD_HASH = password_hash("123456", PASSWORD_DEFAULT); // Default password for everyone

// --- DATA LISTS (To make it look real) ---
$first_names = ["James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda", "William", "Elizabeth", "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica", "Thomas", "Sarah", "Charles", "Karen", "Christopher", "Nancy", "Daniel", "Lisa", "Matthew", "Betty", "Anthony", "Margaret", "Mark", "Sandra", "Levi", "Tiana", "Eric", "Brian", "Isaro", "Keza", "Mutesi", "Gatimu", "Ngugi", "Mugisha"];
$last_names = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas", "Taylor", "Moore", "Jackson", "Martin", "Lee", "Perez", "Thompson", "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson", "Walker", "Young", "Allen", "King", "Wright", "Scott", "Torres", "Nguyen", "Hill", "Flores"];

function getRandomName() {
    global $first_names, $last_names;
    return $first_names[array_rand($first_names)] . " " . $last_names[array_rand($last_names)];
}

try {
    $pdo->beginTransaction();
    echo "<h1>🌱 Seeding Database...</h1>";

    // 1. FETCH CLASSES & SUBJECTS
    $classes = $pdo->query("SELECT class_id, class_name FROM classes")->fetchAll();
    $subjects = $pdo->query("SELECT subject_id FROM subjects")->fetchAll();
    
    if (empty($classes)) die("Error: No classes found. Please create classes in the DB first.");
    if (empty($subjects)) die("Error: No subjects found. Please create subjects in the DB first.");

    // --- PART A: CREATE TEACHERS ---
    echo "<h3>Creating Teachers...</h3>";
    
    foreach ($classes as $class) {
        $c_id = $class['class_id'];
        $c_name = $class['class_name'];
        
        // 1. Create a Teacher User
        $t_name = getRandomName();
        $t_email = strtolower(str_replace(' ', '.', $t_name)) . rand(10,99) . "@nga.rw";
        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'teacher')");
        $stmt->execute([$t_name, $t_email, $PASSWORD_HASH]);
        $teacher_id = $pdo->lastInsertId();
        
        echo "Created Teacher: $t_name ($t_email) <br>";

        // 2. Assign as Class Teacher (Homeroom)
        $pdo->prepare("UPDATE classes SET class_teacher_id = ? WHERE class_id = ?")->execute([$teacher_id, $c_id]);
        echo " &rarr; Assigned as Class Teacher for $c_name <br>";

        // 3. Assign Random Subjects to this Teacher
        // We assign 3 random subjects to this teacher for this class
        $random_subjects = array_rand($subjects, min(3, count($subjects)));
        if (!is_array($random_subjects)) $random_subjects = [$random_subjects];

        foreach ($random_subjects as $key_index) {
            $s_id = $subjects[$key_index]['subject_id'];
            $pdo->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id) VALUES (?, ?, ?)")
                ->execute([$teacher_id, $c_id, $s_id]);
        }
        echo " &rarr; Allocated random subjects.<br><br>";
    }

    // --- PART B: CREATE STUDENTS ---
    echo "<h3>Creating Students ($STUDENTS_PER_CLASS per class)...</h3>";

    $adm_counter = 2026001; // Starting Admission Number

    foreach ($classes as $class) {
        $c_id = $class['class_id'];
        $c_name = $class['class_name'];
        
        echo "<strong>Populating $c_name:</strong> ";

        for ($i = 0; $i < $STUDENTS_PER_CLASS; $i++) {
            $s_name = getRandomName();
            // Some students might not have emails (optional logic), but we'll give them one for login
            $s_email = strtolower(str_replace(' ', '', $s_name)) . $adm_counter . "@student.nga.rw";
            
            // Insert User
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$s_name, $s_email, $PASSWORD_HASH]);
            $student_user_id = $pdo->lastInsertId();

            // Generate Parent Access Code
            $access_code = "NGA-" . rand(1000, 9999);

            // Insert Student Profile
            $stmt = $pdo->prepare("INSERT INTO students (student_id, admission_number, class_id, parent_access_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_user_id, $adm_counter, $c_id, $access_code]);

            $adm_counter++;
            echo "."; // Progress dot
        }
        echo " Done!<br>";
    }

    $pdo->commit();
    echo "<h2>✅ Database Seeding Complete!</h2>";
    echo "<p>All users have password: <strong>123456</strong></p>";
    echo "<a href='index.php'>Go to Login</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Error: " . $e->getMessage());
}
?>