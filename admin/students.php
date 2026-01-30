<?php
// admin/students.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$view_student_id = $_GET['view_id'] ?? null;
$message = '';
$msg_type = ''; 

// --- FETCH CLASSES & SORT NATURALLY ---
$classes_stmt = $pdo->query("SELECT * FROM classes");
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// FIX: Natural Sort (So 'Grade 2' comes before 'Grade 10', and 'Grade 9' before 'Year 1')
usort($classes, function($a, $b) {
    return strnatcmp($a['class_name'], $b['class_name']);
});

$class_ids = array_column($classes, 'class_id'); 

// --- ACTIONS HANDLER (Same Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_id = $_POST['student_id'] ?? null;
    $c_id = $_POST['current_class_id'] ?? null;

    // Reset Password
    if (isset($_POST['reset_password'])) {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$pass, $s_id]);
        $message = "Password reset to default (123456)."; $msg_type = "success";
        $view_student_id = $s_id;
    }
    // Delete Student
    if (isset($_POST['delete_student'])) {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$s_id]);
        $message = "Student removed permanently."; $msg_type = "error";
    }
    // Promote
    if (isset($_POST['promote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index + 1])) {
            $new_class_id = $class_ids[$current_index + 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Student promoted successfully."; $msg_type = "success";
        } else {
            $message = "Student is already in the highest class."; $msg_type = "warning";
        }
    }
    // Demote
    if (isset($_POST['demote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index - 1])) {
            $new_class_id = $class_ids[$current_index - 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Student demoted."; $msg_type = "warning";
        } else {
            $message = "Student is in the lowest class."; $msg_type = "warning";
        }
    }
    // Leadership Role
    if (isset($_POST['assign_role'])) {
        $role = $_POST['assign_role'];
        try {
            $pdo->beginTransaction();
            if ($role === 'Head Boy' || $role === 'Head Girl') {
                $pdo->prepare("UPDATE students SET leadership_role = NULL WHERE leadership_role = ?")->execute([$role]);
            }
            $final_role = ($role === 'None') ? NULL : $role;
            $pdo->prepare("UPDATE students SET leadership_role = ? WHERE student_id = ?")->execute([$final_role, $s_id]);
            $pdo->commit();
            $message = "Leadership role updated."; $msg_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error assigning role."; $msg_type = "error";
        }
    }
}

// --- DATA FETCHING ---
$student_data = null;
$student_marks = [];

if ($view_student_id) {
    // Profile Data
    $stmt = $pdo->prepare("SELECT u.*, s.admission_number, s.parent_access_code, s.leadership_role, c.class_name, c.class_id 
                           FROM users u JOIN students s ON u.user_id = s.student_id 
                           LEFT JOIN classes c ON s.class_id = c.class_id
                           WHERE u.user_id = ?");
    $stmt->execute([$view_student_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $m_sql = "SELECT sm.score, s.subject_name, ca.max_score, gc.name as cat_name
                  FROM student_marks sm
                  JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                  JOIN subjects s ON ca.subject_id = s.subject_id
                  JOIN grading_categories gc ON ca.category_id = gc.id
                  WHERE sm.student_id = ?";
        $stmt = $pdo->prepare($m_sql);
        $stmt->execute([$view_student_id]);
        $raw_marks = $stmt->fetchAll();

        foreach ($raw_marks as $m) {
            $sub = $m['subject_name'];
            if (!isset($student_marks[$sub])) { $student_marks[$sub] = ['total'=>0, 'max'=>0, 'details'=>[]]; }
            $student_marks[$sub]['details'][] = $m['cat_name'] . " (" . $m['score'] . "/" . $m['max_score'] . ")";
            $student_marks[$sub]['total'] += $m['score'];
            $student_marks[$sub]['max'] += $m['max_score'];
        }
    }
} else {
    // List Data
    $sql = "SELECT users.user_id, users.full_name, users.email, students.admission_number, students.class_id, students.leadership_role 
            FROM students JOIN users ON students.student_id = users.user_id 
            ORDER BY users.full_name ASC";
    $all_students = $pdo->query($sql)->fetchAll();
    $students_by_class = [];
    foreach ($all_students as $s) { $students_by_class[$s['class_id']][] = $s; }
}

$page_title = "Manage Students";
include '../includes/header.php';
?>

<div class="container">

<style>
    /* === UPGRADED DESIGN SYSTEM === */
    :root { 
        --primary: #FF6600; 
        --primary-soft: #fff0e6;
        --dark: #1e293b; 
        --gray: #64748b; 
        --bg-card: #ffffff;
        --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
    .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; letter-spacing: -0.5px; }
    
    .btn-add { 
        background: var(--dark); color: white; padding: 12px 24px; 
        border-radius: 12px; text-decoration: none; font-weight: 700; 
        display: inline-flex; align-items: center; gap: 8px; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-soft);
    }
    .btn-add:hover { background: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-hover); }

    /* === NEW CARD GRID === */
    .grid-container { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
        gap: 30px; 
    }
    
    .class-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 25px; 
        position: relative; 
        overflow: hidden; 
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid #f1f5f9;
        box-shadow: var(--shadow-soft);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 160px;
    }

    .class-card:hover { 
        transform: translateY(-8px); 
        box-shadow: var(--shadow-hover);
        border-color: var(--primary-soft);
    }

    /* Watermark Icon (Background) */
    .class-card::before {
        content: '\ec59'; /* Boxicons: bxs-school */
        font-family: 'boxicons';
        position: absolute;
        bottom: -20px;
        right: -20px;
        font-size: 8rem;
        color: rgba(0,0,0,0.03);
        transform: rotate(-15deg);
        transition: 0.3s;
    }
    .class-card:hover::before {
        color: rgba(255, 102, 0, 0.08);
        transform: rotate(0deg) scale(1.1);
    }

    .card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 1;
    }

    .class-icon-box { 
        width: 55px; height: 55px; 
        background: linear-gradient(135deg, #FF6600 0%, #ff8534 100%);
        color: white; 
        border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 1.8rem; 
        box-shadow: 0 4px 10px rgba(255, 102, 0, 0.3);
    }

    .arrow-icon {
        color: var(--gray);
        font-size: 1.5rem;
        opacity: 0;
        transform: translateX(-10px);
        transition: 0.3s;
    }
    .class-card:hover .arrow-icon { opacity: 1; transform: translateX(0); color: var(--primary); }

    .card-info { z-index: 1; margin-top: 15px; }
    .class-name { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--dark); line-height: 1.2; }
    
    .student-pill { 
        display: inline-flex; align-items: center; gap: 5px;
        margin-top: 8px; font-size: 0.85rem; font-weight: 700; 
        color: var(--gray); background: #f8fafc; 
        padding: 6px 12px; border-radius: 30px;
        border: 1px solid #e2e8f0;
    }
    .class-card:hover .student-pill { background: var(--primary); color: white; border-color: var(--primary); }

    /* === MODERN MODAL === */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; }
    .modal-box { background: #f8fafc; width: 95%; max-width: 1000px; height: 85vh; border-radius: 24px; display: flex; flex-direction: column; overflow: hidden; animation: zoomIn 0.2s ease; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .modal-header { padding: 25px 30px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-title h2 { margin: 0; font-size: 1.5rem; color: var(--dark); }
    .modal-title span { color: var(--gray); font-size: 0.9rem; font-weight: 600; }
    .btn-close { background: #f1f5f9; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; color: var(--gray); transition: 0.2s; display:flex; align-items:center; justify-content:center; }
    .btn-close:hover { background: #fee2e2; color: #ef4444; }

    .modal-body { padding: 20px; overflow-y: auto; flex: 1; }

    /* === ROSTER LIST (Responsive) === */
    .roster-list { display: flex; flex-direction: column; gap: 12px; }
    
    .roster-item { 
        background: white; border-radius: 16px; padding: 15px 25px; 
        display: flex; align-items: center; justify-content: space-between;
        border: 1px solid transparent; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
        transition: 0.2s;
    }
    .roster-item:hover { border-color: var(--primary); transform: translateX(5px); box-shadow: var(--shadow-soft); }

    .s-profile { display: flex; align-items: center; gap: 15px; }
    .s-avatar { 
        width: 45px; height: 45px; background: #f1f5f9; border-radius: 12px; 
        display: flex; align-items: center; justify-content: center; 
        font-weight: 800; color: #64748b; font-size: 1.1rem;
    }
    .s-info div { font-weight: 700; color: var(--dark); font-size: 1rem; }
    .s-info span { font-size: 0.85rem; color: var(--gray); font-family: 'Courier New', monospace; background: #f8fafc; padding: 2px 6px; border-radius: 4px; }

    /* Action Menu */
    .action-wrapper { position: relative; }
    .action-btn { background: white; border: 1px solid #e2e8f0; width: 35px; height: 35px; border-radius: 10px; cursor: pointer; color: var(--gray); transition: 0.2s; display:flex; align-items:center; justify-content:center; }
    .action-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }

    .dropdown-menu {
        display: none; position: absolute; right: 0; top: 45px;
        background: white; width: 200px; box-shadow: var(--shadow-hover);
        border-radius: 12px; z-index: 100; overflow: hidden; border: 1px solid #e2e8f0;
    }
    .dropdown-menu.active { display: block; animation: slideDown 0.2s; }
    @keyframes slideDown { from{opacity:0; transform:translateY(-10px);} to{opacity:1; transform:translateY(0);} }

    .dropdown-menu a, .dropdown-menu button {
        display: flex; align-items: center; gap: 12px; width: 100%;
        padding: 12px 20px; text-decoration: none; color: var(--dark);
        font-size: 0.9rem; font-weight: 600; border: none; background: none;
        cursor: pointer; text-align: left; transition: 0.2s;
    }
    .dropdown-menu a:hover, .dropdown-menu button:hover { background: #f8fafc; color: var(--primary); padding-left: 25px; }
    
    .menu-divider { height: 1px; background: #f1f5f9; margin: 5px 0; }
    .menu-header { font-size: 0.7rem; color: #94a3b8; padding: 8px 20px 4px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
    .text-danger { color: #ef4444 !important; }
    .text-danger:hover { background: #fef2f2 !important; }

    /* Profile View Layout */
    .profile-wrap { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
    .profile-card { background: white; border-radius: 24px; padding: 35px; border: 1px solid #e2e8f0; text-align: center; height: fit-content; box-shadow: var(--shadow-soft); }
    .big-avatar { width: 120px; height: 120px; background: var(--dark); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; margin: 0 auto 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    
    /* Table Responsive */
    .table-container { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; min-width: 600px; }
    .data-table th { text-align: left; padding: 15px; background: #f8fafc; font-size: 0.8rem; color: var(--gray); text-transform:uppercase; font-weight:700; border-radius: 8px; }
    .data-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; font-weight: 600; color: var(--dark); }

    /* Alerts */
    .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; text-align: center; }
    .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Mobile */
    @media (max-width: 900px) {
        .profile-wrap { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        .btn-add { width: 100%; justify-content: center; }
        .modal-box { width: 100%; height: 100%; border-radius: 0; }
        .roster-item { flex-direction: column; align-items: flex-start; gap: 15px; }
        .action-wrapper { width: 100%; display: flex; justify-content: flex-end; }
        .grid-container { grid-template-columns: 1fr; }
    }
</style>

<?php if ($view_student_id && $student_data): ?>
    <div class="page-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="students.php" style="background:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--dark); box-shadow:var(--shadow-soft); text-decoration:none;"><i class='bx bx-arrow-back'></i></a>
            <h1 class="page-title">Student Profile</h1>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="profile-wrap">
        <div class="profile-card">
            <div class="big-avatar"><?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?></div>
            <h2 style="margin:0; font-size:1.5rem;"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
            <p style="color:var(--gray); margin-top:5px; font-weight:600;"><?php echo htmlspecialchars($student_data['class_name'] ?? 'Unassigned'); ?></p>
            
            <?php if($student_data['leadership_role']): ?>
                <span class="student-pill" style="background:#f3e5f5; color:#9c27b0; border:none; margin-top:10px;">
                    <i class='bx bxs-star'></i> <?php echo $student_data['leadership_role']; ?>
                </span>
            <?php endif; ?>

            <div style="margin-top:25px; text-align:left; background:#f8fafc; padding:20px; border-radius:16px; border:1px solid #f1f5f9;">
                <div style="margin-bottom:12px; font-size:0.9rem; display:flex; justify-content:space-between;">
                    <span style="color:var(--gray);">Adm No:</span> <strong><?php echo $student_data['admission_number']; ?></strong>
                </div>
                <div style="margin-bottom:12px; font-size:0.9rem; display:flex; justify-content:space-between;">
                    <span style="color:var(--gray);">Email:</span> <strong><?php echo $student_data['email'] ?: '-'; ?></strong>
                </div>
                <div style="font-size:0.9rem; display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--gray);">Parent Code:</span> 
                    <code style="background:#e2e8f0; padding:4px 8px; border-radius:6px; font-weight:bold; color:var(--dark);"><?php echo $student_data['parent_access_code']; ?></code>
                </div>
            </div>

            <form method="POST" style="margin-top:25px;" onsubmit="return confirm('Reset password to 123456?');">
                <input type="hidden" name="student_id" value="<?php echo $student_data['user_id']; ?>">
                <button type="submit" name="reset_password" style="width:100%; padding:14px; background:white; border:2px solid #fed7aa; color:#ea580c; border-radius:12px; cursor:pointer; font-weight:bold; transition:0.2s; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class='bx bx-reset'></i> Reset Password
                </button>
            </form>
        </div>

        <div class="profile-card" style="text-align:left;">
            <h3 style="margin-top:0; padding-bottom:15px; border-bottom:1px solid #eee; display:flex; align-items:center; gap:10px;">
                <i class='bx bxs-graduation' style="color:var(--primary);"></i> Academic Results
            </h3>
            <?php if (empty($student_marks)): ?>
                <div style="text-align:center; padding:60px; color:var(--gray);">
                    <i class='bx bx-ghost' style="font-size:3rem; opacity:0.3; margin-bottom:10px;"></i>
                    <p>No marks recorded for this student yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Subject</th><th>Breakdown</th><th>Score</th><th>Grade</th></tr></thead>
                        <tbody>
                            <?php foreach($student_marks as $sub => $data): 
                                $pct = ($data['max'] > 0) ? ($data['total'] / $data['max']) * 100 : 0;
                                $grade = ($pct >= 80) ? 'A' : (($pct >= 70) ? 'B' : (($pct >= 50) ? 'C' : 'F'));
                                $color = ($grade == 'A' || $grade == 'B') ? '#16a34a' : (($grade == 'F') ? '#dc2626' : '#d97706');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub); ?></td>
                                <td style="color:var(--gray); font-size:0.8rem;"><?php echo implode(", ", $data['details']); ?></td>
                                <td><?php echo $data['total']; ?> <span style="color:#aaa;">/ <?php echo $data['max']; ?></span></td>
                                <td><span style="background:<?php echo $color; ?>20; color:<?php echo $color; ?>; padding:4px 10px; border-radius:6px; font-weight:800;"><?php echo $grade; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Manage Students</h1>
            <p style="color:var(--gray); margin:5px 0 0;">Select a class to manage roster and roles.</p>
        </div>
        <a href="add_student.php" class="btn-add"><i class='bx bx-user-plus'></i> Add Student</a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid-container">
        <?php foreach($classes as $class): ?>
            <?php $count = isset($students_by_class[$class['class_id']]) ? count($students_by_class[$class['class_id']]) : 0; ?>
            
            <div class="class-card" onclick="openModal('modal-<?php echo $class['class_id']; ?>')">
                <div class="card-top">
                    <div class="class-icon-box">
                        <i class='bx bxs-graduation'></i>
                    </div>
                    <i class='bx bx-right-arrow-alt arrow-icon'></i>
                </div>
                
                <div class="card-info">
                    <h3 class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                    <span class="student-pill">
                        <i class='bx bxs-user'></i> <?php echo $count; ?> Students
                    </span>
                </div>
            </div>

            <div id="modal-<?php echo $class['class_id']; ?>" class="modal">
                <div class="modal-box">
                    <div class="modal-header">
                        <div class="modal-title">
                            <h2><?php echo htmlspecialchars($class['class_name']); ?></h2>
                            <span>Class Roster Management</span>
                        </div>
                        <button onclick="closeModal('modal-<?php echo $class['class_id']; ?>')" class="btn-close"><i class='bx bx-x'></i></button>
                    </div>
                    
                    <div class="modal-body">
                        <?php if($count > 0): ?>
                            <div class="roster-list">
                                <?php foreach($students_by_class[$class['class_id']] as $s): ?>
                                    <div class="roster-item">
                                        <div class="s-profile">
                                            <div class="s-avatar"><?php echo substr($s['full_name'], 0, 1); ?></div>
                                            <div class="s-info">
                                                <div>
                                                    <?php echo htmlspecialchars($s['full_name']); ?>
                                                    <?php if($s['leadership_role']): ?>
                                                        <span style="font-size:0.7rem; background:#f3e5f5; color:#9c27b0; padding:2px 6px; border-radius:4px; text-transform:uppercase; margin-left:5px;">
                                                            <i class='bx bxs-star'></i> <?php echo $s['leadership_role']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <span><?php echo $s['admission_number']; ?></span>
                                            </div>
                                        </div>

                                        <div class="action-wrapper">
                                            <button class="action-btn" onclick="toggleDropdown(event, 'dd-<?php echo $s['user_id']; ?>')">
                                                <i class='bx bx-dots-vertical-rounded'></i>
                                            </button>
                                            
                                            <div id="dd-<?php echo $s['user_id']; ?>" class="dropdown-menu">
                                                <a href="?view_id=<?php echo $s['user_id']; ?>"><i class='bx bx-id-card'></i> Profile</a>
                                                
                                                <div class="menu-divider"></div>
                                                <div class="menu-header">ACADEMIC</div>
                                                
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="student_id" value="<?php echo $s['user_id']; ?>">
                                                    <input type="hidden" name="current_class_id" value="<?php echo $class['class_id']; ?>">
                                                    
                                                    <button type="submit" name="promote_student"><i class='bx bx-up-arrow-alt'></i> Promote</button>
                                                    <button type="submit" name="demote_student"><i class='bx bx-down-arrow-alt'></i> Demote</button>
                                                    
                                                    <div class="menu-divider"></div>
                                                    <div class="menu-header">ROLE</div>
                                                    <button type="submit" name="assign_role" value="Head Boy"><i class='bx bxs-star'></i> Head Boy</button>
                                                    <button type="submit" name="assign_role" value="Head Girl"><i class='bx bxs-star'></i> Head Girl</button>
                                                    <button type="submit" name="assign_role" value="Prefect"><i class='bx bxs-badge'></i> Prefect</button>
                                                    <?php if($s['leadership_role']): ?>
                                                        <button type="submit" name="assign_role" value="None" style="color:#666;"><i class='bx bx-x'></i> Remove Role</button>
                                                    <?php endif; ?>

                                                    <div class="menu-divider"></div>
                                                    <button type="submit" name="delete_student" class="text-danger" onclick="return confirm('Delete this user permanently?');"><i class='bx bx-trash'></i> Delete User</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center; padding:50px; color:var(--gray);">
                                <i class='bx bx-ghost' style="font-size:3rem;"></i>
                                <p>No students enrolled in this class.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<script>
    // Open Class Modal
    function openModal(id) { 
        document.getElementById(id).style.display = 'flex'; 
    }
    
    // Close Modal
    function closeModal(id) { 
        document.getElementById(id).style.display = 'none';
        closeAllDropdowns();
    }

    // Toggle Dropdown (Stop Propagation)
    function toggleDropdown(event, id) {
        event.stopPropagation();
        closeAllDropdowns(); // Close others first
        var menu = document.getElementById(id);
        if (menu) menu.classList.toggle('active');
    }

    // Close all dropdowns
    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
    }

    // Window Click
    window.onclick = function(event) {
        if (!event.target.closest('.action-wrapper')) {
            closeAllDropdowns();
        }
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            closeAllDropdowns();
        }
    }
</script>

</body>
</html>