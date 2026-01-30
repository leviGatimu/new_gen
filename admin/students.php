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

// --- FETCH CLASSES ---
$classes_stmt = $pdo->query("SELECT * FROM classes ORDER BY class_id ASC");
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Natural Sort (So 'Grade 9' comes before 'Grade 10')
usort($classes, function($a, $b) {
    return strnatcmp($a['class_name'], $b['class_name']);
});

$class_ids = array_column($classes, 'class_id'); 

// --- ACTIONS HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_id = $_POST['student_id'] ?? null;
    $c_id = $_POST['current_class_id'] ?? null;

    // Reset Password
    if (isset($_POST['reset_password'])) {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$pass, $s_id]);
        $message = "Password reset to 123456."; $msg_type = "success";
        $view_student_id = $s_id;
    }

    // Delete Student
    if (isset($_POST['delete_student'])) {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$s_id]);
        $message = "Student removed."; $msg_type = "error";
    }

    // Promote
    if (isset($_POST['promote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index + 1])) {
            $new_class_id = $class_ids[$current_index + 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Promoted successfully."; $msg_type = "success";
        } else { $message = "Already in highest class."; $msg_type = "warning"; }
    }

    // Demote
    if (isset($_POST['demote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index - 1])) {
            $new_class_id = $class_ids[$current_index - 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Demoted successfully."; $msg_type = "warning";
        } else { $message = "Already in lowest class."; $msg_type = "warning"; }
    }

    // Assign Role
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
            $message = "Role updated."; $msg_type = "success";
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
    $stmt = $pdo->prepare("SELECT u.*, s.admission_number, s.parent_access_code, s.leadership_role, c.class_name, c.class_id 
                           FROM users u 
                           JOIN students s ON u.user_id = s.student_id 
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
    /* === MODERN DESIGN SYSTEM === */
    :root { 
        --primary: #FF6600; 
        --primary-soft: #fff0e6;
        --dark: #1e293b; 
        --gray: #64748b; 
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        --danger: #ef4444;
    }

    /* === LAYOUT === */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; flex-wrap: wrap; gap: 15px; }
    .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
    
    .btn-add { 
        background: var(--dark); color: white; padding: 12px 24px; 
        border-radius: 12px; text-decoration: none; font-weight: 700; 
        display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
    }
    .btn-add:hover { background: var(--primary); transform: translateY(-2px); }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; text-align:center; }
    .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
    .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
    .alert-warning { background: #fff7cd; color: #7a4f01; border: 1px solid #ffe58f; }

    /* === GRID & CARDS === */
    .grid-container { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
        gap: 30px; 
    }
    
    .class-card { 
        background: var(--bg-card); border-radius: 20px; padding: 25px; 
        position: relative; overflow: hidden; cursor: pointer;
        transition: all 0.3s ease; border: 1px solid #f1f5f9;
        box-shadow: var(--shadow-soft); height: 160px;
        display: flex; flex-direction: column; justify-content: space-between;
    }
    .class-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary-soft); }
    
    .class-card::before {
        content: '\ec59'; font-family: 'boxicons'; position: absolute; bottom: -20px; right: -20px;
        font-size: 8rem; color: rgba(0,0,0,0.03); transform: rotate(-15deg); transition: 0.3s;
    }
    .class-card:hover::before { color: rgba(255, 102, 0, 0.08); transform: rotate(0deg) scale(1.1); }

    .card-top { display: flex; align-items: center; justify-content: space-between; z-index: 1; }
    .class-icon-box { 
        width: 50px; height: 50px; background: linear-gradient(135deg, #FF6600, #ff8534);
        color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; 
    }
    .card-info { z-index: 1; }
    .class-name { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--dark); }
    .student-pill { 
        display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; font-size: 0.85rem; 
        font-weight: 700; color: var(--gray); background: #f8fafc; padding: 6px 12px; 
        border-radius: 30px; border: 1px solid #e2e8f0;
    }

    /* === PROFILE VIEW === */
    .profile-wrap { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
    .profile-sidebar { height: fit-content; background: white; padding: 25px; border-radius: 16px; border: 1px solid var(--border); }
    .profile-main { display: flex; flex-direction: column; gap: 30px; }

    /* Tables */
    .table-container { width: 100%; overflow-x: auto; display: block; -webkit-overflow-scrolling: touch; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; min-width: 600px; }
    .data-table th { text-align: left; padding: 15px; background: #f8fafc; font-size: 0.8rem; color: var(--gray); text-transform:uppercase; font-weight:700; }
    .data-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; font-weight: 600; color: var(--dark); }

    /* === OFFICIAL ID CARD STYLING === */
    .id-display-container {
        display: flex; flex-wrap: wrap; gap: 40px; justify-content: center;
        padding: 20px 0; margin-top: 30px;
    }
    
    .id-card {
        width: 350px; height: 220px;
        background: #fff; border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.15); overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05); position: relative;
        display: flex; flex-direction: column; transition: transform 0.3s;
    }
    .id-card:hover { transform: translateY(-5px); }

    /* FRONT DESIGN */
    .id-front-header { background: var(--primary); height: 45px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; color: white; position: relative; }
    .id-logo-text { font-size: 0.8rem; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
    .id-logo-img { height: 30px; width: auto; background: white; padding: 2px; border-radius: 4px; }
    
    .id-front-body { flex: 1; padding: 15px 20px; display: flex; gap: 15px; align-items: center; position: relative; z-index: 2; background-image: radial-gradient(#eee 1px, transparent 1px); background-size: 10px 10px; }
    .id-photo { 
        width: 80px; height: 80px; border-radius: 12px; 
        background: var(--dark); border: 2px solid var(--primary); 
        display: flex; align-items: center; justify-content: center; 
        color: white; font-size: 2rem; font-weight: 800; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
    }
    .id-details { flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .id-name { font-size: 1.1rem; font-weight: 900; color: var(--dark); margin: 0; line-height: 1.1; text-transform: uppercase; }
    .id-role { font-size: 0.7rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display:block; }
    .id-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
    .id-field { display: flex; flex-direction: column; }
    .id-lbl { font-size: 0.55rem; color: #888; text-transform: uppercase; font-weight: 700; }
    .id-val { font-size: 0.8rem; color: var(--dark); font-weight: 700; font-family: monospace; }
    .id-front-footer { height: 25px; background: var(--dark); display: flex; align-items: center; justify-content: center; }
    .barcode-fake { height: 12px; width: 80%; background: repeating-linear-gradient(to right, #fff 0px, #fff 2px, transparent 2px, transparent 4px); opacity: 0.5; }

    /* BACK DESIGN */
    .id-back { background: #fdfdfd; }
    .magnetic-strip { height: 35px; background: #222; margin-top: 15px; width: 100%; }
    .id-back-body { padding: 15px 20px; display: flex; align-items: center; gap: 15px; height: 100%; box-sizing: border-box; }
    .id-qr { width: 90px; height: 90px; border: 2px solid #000; padding: 2px; background: white; flex-shrink: 0; }
    .id-back-info { text-align: left; flex: 1; }
    .id-back-logo { height: 25px; opacity: 0.7; margin-bottom: 5px; }
    .id-school-title { font-size: 0.8rem; font-weight: 800; color: var(--dark); text-transform: uppercase; margin-bottom: 5px; }
    .school-info-block { font-size: 0.65rem; color: #444; margin-bottom: 10px; line-height: 1.4; border-left: 2px solid var(--primary); padding-left: 8px; }
    .disclaimer { font-size: 0.55rem; color: #888; font-style: italic; line-height: 1.2; }
    .validity-stamp { margin-top: 5px; border: 1px solid var(--primary); color: var(--primary); font-size: 0.6rem; font-weight: 800; padding: 2px 5px; display: inline-block; border-radius: 4px; text-transform: uppercase; }
    .holo-effect { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 70%); pointer-events: none; z-index: 10; opacity: 0.6; }

    /* === MODAL === */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; }
    .modal-box { background: #fff; width: 95%; max-width: 900px; height: 80vh; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; animation: zoomIn 0.2s ease; }
    @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    .modal-header { padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white; }
    .btn-close { background: #f1f5f9; border: none; width: 35px; height: 35px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; display:flex; align-items:center; justify-content:center; }
    .modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #f8fafc; }

    /* Roster Items */
    .roster-list { display: flex; flex-direction: column; gap: 10px; }
    .roster-item { 
        background: white; border-radius: 12px; padding: 15px; 
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid transparent; 
        flex-wrap: wrap; gap: 10px;
    }
    .roster-item:hover { border-color: var(--primary); transform: translateX(5px); transition:0.2s; }
    
    .s-profile { display: flex; align-items: center; gap: 15px; flex: 1; min-width: 200px; }
    .s-avatar { width: 40px; height: 40px; background: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #64748b; }
    
    /* Dropdown */
    .action-wrapper { position: relative; }
    .action-btn { width: 35px; height: 35px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; cursor: pointer; color: var(--gray); display:flex; align-items:center; justify-content:center; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: 40px; background: white; width: 180px; box-shadow: var(--shadow-hover); border-radius: 12px; z-index: 100; border: 1px solid #e2e8f0; overflow: hidden; }
    .dropdown-menu.active { display: block; animation: slideDown 0.2s; }
    @keyframes slideDown { from{opacity:0; transform:translateY(-10px);} to{opacity:1; transform:translateY(0);} }
    .dropdown-menu button, .dropdown-menu a { display: block; width: 100%; text-align: left; padding: 10px 15px; background: none; border: none; font-size: 0.85rem; font-weight: 600; color: var(--dark); cursor: pointer; text-decoration: none; }
    .dropdown-menu button:hover { background: #f8fafc; color: var(--primary); }
    .text-danger { color: var(--danger) !important; }
    
    /* Mobile Media Queries */
    @media (max-width: 900px) {
        .profile-wrap { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .btn-add { width: 100%; justify-content: center; margin-top: 10px; }
        .modal-box { width: 100%; height: 100%; border-radius: 0; }
        .roster-item { gap: 10px; }
        .id-display-container { padding: 0; }
    }
</style>

<?php if ($view_student_id && $student_data): ?>
    <div class="page-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="students.php" style="color:#637381; font-size:1.5rem;"><i class='bx bx-arrow-back'></i></a>
            <h1 class="page-title">Student Profile</h1>
        </div>
        <button onclick="window.print()" class="btn-add" style="background:var(--primary);"><i class='bx bxs-printer'></i> Print ID Cards</button>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>" style="padding:15px; background:#e0f2f1; color:#00695c; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold;"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="profile-wrap">
        
        <div class="profile-sidebar">
            <div style="text-align:center;">
                <div style="width:100px; height:100px; background:var(--dark); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:3rem; margin:0 auto 15px; font-weight:800;">
                    <?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?>
                </div>
                <h2 style="margin:0; font-size:1.4rem; color:var(--dark);"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                <p style="color:#666; margin:5px 0 15px; font-weight:600;"><?php echo htmlspecialchars($student_data['class_name'] ?? 'Unassigned'); ?></p>
                <?php if($student_data['leadership_role']): ?>
                    <span style="font-size:0.7rem; background:#f3e5f5; color:#9c27b0; padding:2px 6px; border-radius:4px; margin-left:5px; text-transform:uppercase; font-weight:700;"><?php echo $student_data['leadership_role']; ?></span>
                <?php endif; ?>
            </div>

            <div style="margin-top:25px; text-align:left;">
                <div style="padding:10px 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between; font-size:0.9rem;">
                    <span style="color:#999;">Adm No:</span>
                    <strong><?php echo $student_data['admission_number']; ?></strong>
                </div>
                <div style="padding:10px 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between; font-size:0.9rem;">
                    <span style="color:#999;">Email:</span>
                    <strong><?php echo $student_data['email'] ?: 'N/A'; ?></strong>
                </div>
                <div style="padding:10px 0; display:flex; justify-content:space-between; align-items:center; font-size:0.9rem;">
                    <span style="color:#999;">Parent Code:</span>
                    <code style="background:#f0f0f0; padding:2px 6px; border-radius:4px; font-weight:bold;"><?php echo $student_data['parent_access_code']; ?></code>
                </div>
            </div>

            <form method="POST" style="margin-top:25px;" onsubmit="return confirm('Reset password to 123456?');">
                <input type="hidden" name="student_id" value="<?php echo $student_data['user_id']; ?>">
                <button type="submit" name="reset_password" style="width:100%; padding:12px; background:white; border:1px solid #ffccbc; color:#d84315; border-radius:8px; cursor:pointer; font-weight:bold; transition:0.2s;">
                    <i class='bx bx-reset'></i> Reset Password
                </button>
            </form>
        </div>

        <div class="profile-main">
            
            <div style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:var(--shadow-soft);">
                <h3 style="margin-top:0; padding-bottom:15px; border-bottom:1px solid #eee;">Academic Results</h3>
                <?php if (empty($student_marks)): ?>
                    <p style="text-align:center; color:#999; padding:20px;">No marks recorded yet.</p>
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
                                    <td style="color:<?php echo $color; ?>; font-weight:800;"><?php echo $grade; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <h3 style="margin:0 0 10px 0; color:var(--dark);">Official Identification</h3>
            
            <div class="id-display-container">
                
                <div class="id-card">
                    <div class="id-front-header">
                        <span class="id-logo-text">New Generation Academy</span>
                        <img src="../assets/images/logo.png" class="id-logo-img" alt="Logo">
                    </div>
                    <div class="id-front-body">
                        <div class="id-photo">
                            <?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?>
                        </div>
                        <div class="id-details">
                            <h3 class="id-name"><?php echo htmlspecialchars($student_data['full_name']); ?></h3>
                            <span class="id-role"><?php echo $student_data['leadership_role'] ? strtoupper($student_data['leadership_role']) : 'STUDENT'; ?></span>
                            <div class="id-grid">
                                <div class="id-field">
                                    <span class="id-lbl">ID No</span>
                                    <span class="id-val"><?php echo $student_data['admission_number']; ?></span>
                                </div>
                                <div class="id-field">
                                    <span class="id-lbl">Grade</span>
                                    <span class="id-val"><?php echo htmlspecialchars($student_data['class_name'] ?? '-'); ?></span>
                                </div>
                                <div class="id-field">
                                    <span class="id-lbl">Issued</span>
                                    <span class="id-val"><?php echo date("M Y"); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="id-front-footer">
                        <div class="barcode-fake"></div>
                    </div>
                    <div class="holo-effect"></div>
                </div>

                <div class="id-card id-back">
                    <div class="magnetic-strip"></div>
                    <div class="id-back-body">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=NGA-<?php echo $student_data['admission_number']; ?>" class="id-qr" alt="QR">
                        <div class="id-back-info">
                            <img src="../assets/images/logo.png" class="id-back-logo" alt="Logo">
                            <div class="id-school-title">New Generation Academy</div>
                            <div class="school-info-block">
                                <strong>Tel:</strong> +250 788 123 456<br>
                                <strong>Email:</strong> info@nga.rw<br>
                                <strong>Web:</strong> www.nga.rw
                            </div>
                            <div class="disclaimer">
                                This card is property of NGA. If found, please return to the school administration office.
                            </div>
                            <div class="validity-stamp">VALID <?php echo date("Y"); ?></div>
                        </div>
                    </div>
                    <div class="holo-effect"></div>
                </div>

            </div>

        </div>
    </div>

<?php else: ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Manage Students</h1>
            <p style="color:var(--gray); margin:5px 0 0;">Select a class to manage roster.</p>
        </div>
        <a href="add_student.php" class="btn-add"><i class='bx bx-user-plus'></i> Add Student</a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>" style="background:#e0f2f1; color:#00695c; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid-container">
        <?php foreach($classes as $class): ?>
            <?php $count = isset($students_by_class[$class['class_id']]) ? count($students_by_class[$class['class_id']]) : 0; ?>
            
            <div class="class-card" onclick="openModal('modal-<?php echo $class['class_id']; ?>')">
                <div class="card-top">
                    <div class="class-icon-box"><i class='bx bxs-graduation'></i></div>
                    <i class='bx bx-right-arrow-alt' style="color:var(--primary); font-size:1.5rem;"></i>
                </div>
                <div class="card-info">
                    <h3 class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                    <span class="student-pill"><?php echo $count; ?> Students</span>
                </div>
            </div>

            <div id="modal-<?php echo $class['class_id']; ?>" class="modal">
                <div class="modal-box">
                    <div class="modal-header">
                        <div>
                            <h2 style="margin:0; font-size:1.4rem; color:var(--dark);"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                            <span style="color:var(--gray); font-size:0.9rem;">Class Roster</span>
                        </div>
                        <button onclick="closeModal('modal-<?php echo $class['class_id']; ?>')" class="btn-close">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <?php if($count > 0): ?>
                            <div class="roster-list">
                                <?php foreach($students_by_class[$class['class_id']] as $s): ?>
                                    <div class="roster-item">
                                        <div class="s-profile">
                                            <div class="s-avatar"><?php echo substr($s['full_name'], 0, 1); ?></div>
                                            <div>
                                                <div style="font-weight:700; color:var(--dark);"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                                <span style="font-size:0.85rem; color:var(--gray); font-family:monospace;"><?php echo $s['admission_number']; ?></span>
                                                <?php if($s['leadership_role']): ?>
                                                    <span style="font-size:0.7rem; background:#f3e5f5; color:#9c27b0; padding:2px 6px; border-radius:4px; margin-left:5px; text-transform:uppercase; font-weight:700;"><?php echo $s['leadership_role']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="action-wrapper">
                                            <button class="action-btn" onclick="toggleDropdown(event, 'dd-<?php echo $s['user_id']; ?>')">
                                                <i class='bx bx-dots-vertical-rounded'></i>
                                            </button>
                                            <div id="dd-<?php echo $s['user_id']; ?>" class="dropdown-menu">
                                                <a href="?view_id=<?php echo $s['user_id']; ?>"><i class='bx bx-id-card'></i> Profile & ID</a>
                                                
                                                <div style="height:1px; background:#eee; margin:5px 0;"></div>
                                                <div style="font-size:0.7rem; color:#999; padding:5px 15px; font-weight:700;">ACADEMIC</div>
                                                
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="student_id" value="<?php echo $s['user_id']; ?>">
                                                    <input type="hidden" name="current_class_id" value="<?php echo $class['class_id']; ?>">
                                                    
                                                    <button type="submit" name="promote_student">Promote</button>
                                                    <button type="submit" name="demote_student">Demote</button>
                                                    
                                                    <div style="height:1px; background:#eee; margin:5px 0;"></div>
                                                    <div style="font-size:0.7rem; color:#999; padding:5px 15px; font-weight:700;">ROLE</div>
                                                    
                                                    <button type="submit" name="assign_role" value="Head Boy">Head Boy</button>
                                                    <button type="submit" name="assign_role" value="Head Girl">Head Girl</button>
                                                    <button type="submit" name="assign_role" value="Prefect">Prefect</button>
                                                    <?php if($s['leadership_role']): ?>
                                                        <button type="submit" name="assign_role" value="None" style="color:var(--gray);">Remove Role</button>
                                                    <?php endif; ?>

                                                    <div style="height:1px; background:#eee; margin:5px 0;"></div>
                                                    <button type="submit" name="delete_student" class="text-danger" onclick="return confirm('Delete this user permanently?');">Delete User</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center; padding:50px; color:var(--gray);">
                                <i class='bx bx-ghost' style="font-size:3rem;"></i>
                                <p>No students enrolled.</p>
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
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { 
        document.getElementById(id).style.display = 'none'; 
        closeAllDropdowns();
    }
    
    function toggleDropdown(event, id) {
        event.stopPropagation();
        closeAllDropdowns();
        var menu = document.getElementById(id);
        if(menu) menu.classList.toggle('active');
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
    }

    window.onclick = function(event) {
        if (!event.target.closest('.action-wrapper')) { closeAllDropdowns(); }
        if (event.target.classList.contains('modal')) { 
            event.target.style.display = 'none';
            closeAllDropdowns();
        }
    }
</script>

</body>
</html>