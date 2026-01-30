<?php
// teacher/my_students.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$view_student_id = $_GET['student_id'] ?? null;
$selected_subject_id = $_GET['subject_id'] ?? null;

// --- 1. DATA FETCHING (Classes) ---
$sql = "SELECT DISTINCT c.class_id, c.class_name, s.subject_id, s.subject_name
        FROM teacher_allocations ta
        JOIN classes c ON ta.class_id = c.class_id 
        JOIN subjects s ON ta.subject_id = s.subject_id
        WHERE ta.teacher_id = ? ORDER BY c.class_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id]);
$allocations = $stmt->fetchAll();

$classes_data = [];
foreach ($allocations as $row) {
    $classes_data[$row['class_id']]['name'] = $row['class_name'];
    $classes_data[$row['class_id']]['subjects'][] = ['id' => $row['subject_id'], 'name' => $row['subject_name']];
}

$student_data = null;
$chart_labels = [];
$chart_scores = [];
$real_average = 0;
$class_rank = "-";
$total_students = 0;
$assessment_count = 0;

// --- 2. SINGLE STUDENT VIEW LOGIC ---
if ($view_student_id && $selected_subject_id) {
    // A. Fetch Student Details
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, st.admission_number, c.class_name, c.class_id, st.class_role, st.leadership_role 
                           FROM users u JOIN students st ON u.user_id = st.student_id 
                           JOIN classes c ON st.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$view_student_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $class_id_target = $student_data['class_id'];

        // B. Fetch Marks (Manual & Online)
        $m_sql = "SELECT sm.score, ca.max_score, ca.created_at
                  FROM student_marks sm
                  JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                  WHERE sm.student_id = ? AND ca.subject_id = ?";
        $stmt = $pdo->prepare($m_sql);
        $stmt->execute([$view_student_id, $selected_subject_id]);
        $manual_marks = $stmt->fetchAll();

        $o_sql = "SELECT sub.obtained_marks as score, oa.total_marks as max_score, sub.submitted_at as created_at
                  FROM assessment_submissions sub
                  JOIN online_assessments oa ON sub.assessment_id = oa.id
                  WHERE sub.student_id = ? AND oa.subject_id = ? AND sub.is_marked = 1";
        $stmt = $pdo->prepare($o_sql);
        $stmt->execute([$view_student_id, $selected_subject_id]);
        $online_marks = $stmt->fetchAll();

        $subject_performance = array_merge($manual_marks, $online_marks);
        
        // Sort by Date
        usort($subject_performance, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        $assessment_count = count($subject_performance);

        // C. Calculate Average & Chart Data
        $total_obt = 0; $total_max = 0;
        foreach($subject_performance as $p) {
            $total_obt += $p['score'];
            $total_max += $p['max_score'];
            $chart_labels[] = date("M d", strtotime($p['created_at']));
            $chart_scores[] = ($p['max_score'] > 0) ? round(($p['score'] / $p['max_score']) * 100, 1) : 0;
        }
        $real_average = ($total_max > 0) ? round(($total_obt / $total_max) * 100, 1) : 0;

        // D. Calculate Rank
        $peers_stmt = $pdo->prepare("SELECT student_id FROM students WHERE class_id = ?");
        $peers_stmt->execute([$class_id_target]);
        $classmates = $peers_stmt->fetchAll(PDO::FETCH_COLUMN);
        $total_students = count($classmates);

        $student_averages = [];
        foreach ($classmates as $sid) {
            // Quick sum query for rank
            $q1 = $pdo->prepare("SELECT SUM(sm.score) as obt, SUM(ca.max_score) as max FROM student_marks sm JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id WHERE sm.student_id = ? AND ca.subject_id = ?");
            $q1->execute([$sid, $selected_subject_id]);
            $r1 = $q1->fetch();

            $q2 = $pdo->prepare("SELECT SUM(sub.obtained_marks) as obt, SUM(oa.total_marks) as max FROM assessment_submissions sub JOIN online_assessments oa ON sub.assessment_id = oa.id WHERE sub.student_id = ? AND oa.subject_id = ? AND sub.is_marked = 1");
            $q2->execute([$sid, $selected_subject_id]);
            $r2 = $q2->fetch();

            $s_obt = ($r1['obt'] ?? 0) + ($r2['obt'] ?? 0);
            $s_max = ($r1['max'] ?? 0) + ($r2['max'] ?? 0);
            $s_avg = ($s_max > 0) ? ($s_obt / $s_max) * 100 : 0;
            $student_averages[$sid] = $s_avg;
        }
        arsort($student_averages);
        $rank = 1;
        foreach ($student_averages as $sid => $avg) {
            if ($sid == $view_student_id) { $class_rank = $rank; break; }
            $rank++;
        }
    }
}

$page_title = "My Students";
include '../includes/header.php';
?>

<div class="container">

<style>
    /* === THEME VARIABLES === */
    :root { --primary: #FF6600; --dark: #212b36; --gray: #637381; --bg-card: #ffffff; --border: #dfe3e8; }

    /* LAYOUT */
    .top-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .main-dashboard-grid { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
    .white-card { background: white; border-radius: 20px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    
    /* STATS */
    .summary-box { display: flex; justify-content: space-between; align-items: center; }
    .stat-label { font-size: 0.8rem; color: var(--gray); font-weight: 700; text-transform: uppercase; }
    .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--dark); display: block; margin-top:5px; }
    .stat-icon { font-size: 2.5rem; color: var(--primary); opacity: 0.15; }

    /* PROFILE SIDEBAR */
    .btn-message { width: 100%; background: var(--dark); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; text-decoration: none; margin-top: 20px; }
    .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
    .role-Citizen { background: #f4f6f8; color: #637381; }
    .role-President { background: #fff7e6; color: #b78103; border: 1px solid #ffe7ba; }
    .role-Vice { background: #e6f7ff; color: #0050b3; border: 1px solid #bae7ff; }

    /* === ID CARD STYLES === */
    .id-section-title { font-size: 1rem; color: var(--gray); margin: 0 0 20px 0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700; text-transform: uppercase; }
    .id-display-container { display: flex; flex-wrap: wrap; gap: 30px; justify-content: center; }
    
    .id-card {
        width: 320px; height: 200px;
        background: #fff; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05); position: relative;
        display: flex; flex-direction: column; flex-shrink: 0;
    }

    /* FRONT */
    .id-front-header { background: var(--primary); height: 40px; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; color: white; }
    .id-logo-text { font-size: 0.75rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
    .id-logo-img { height: 25px; width: auto; background: white; padding: 2px; border-radius: 4px; }

    .id-front-body { flex: 1; padding: 12px 15px; display: flex; gap: 12px; align-items: center; position: relative; z-index: 2; background-image: radial-gradient(#eee 1px, transparent 1px); background-size: 10px 10px; }
    .id-photo { width: 70px; height: 70px; border-radius: 10px; background: var(--dark); border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem; font-weight: 800; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    
    .id-details { flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .id-name { font-size: 1rem; font-weight: 800; color: var(--dark); margin: 0; line-height: 1.1; text-transform: uppercase; }
    .id-role { font-size: 0.65rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; display:block; }
    
    .id-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
    .id-field { display: flex; flex-direction: column; }
    .id-lbl { font-size: 0.5rem; color: #888; text-transform: uppercase; font-weight: 700; }
    .id-val { font-size: 0.75rem; color: var(--dark); font-weight: 700; font-family: monospace; }

    .id-front-footer { height: 20px; background: var(--dark); display: flex; align-items: center; justify-content: center; }
    .barcode-fake { height: 10px; width: 80%; background: repeating-linear-gradient(to right, #fff 0px, #fff 2px, transparent 2px, transparent 4px); opacity: 0.5; }

    /* BACK */
    .id-back { background: #fdfdfd; }
    .magnetic-strip { height: 30px; background: #222; margin-top: 15px; width: 100%; }
    .id-back-body { padding: 10px 15px; display: flex; align-items: center; gap: 15px; height: 100%; box-sizing: border-box; }
    .id-qr { width: 80px; height: 80px; border: 2px solid #000; padding: 2px; background: white; flex-shrink: 0; }
    
    .id-back-info { text-align: left; flex: 1; display:flex; flex-direction:column; }
    .id-back-logo { height: 20px; opacity: 0.7; margin-bottom: 5px; align-self: flex-start; }
    .id-school-title { font-size: 0.75rem; font-weight: 800; color: var(--dark); text-transform: uppercase; margin-bottom: 5px; }
    .school-info-block { font-size: 0.6rem; color: #444; margin-bottom: 8px; line-height: 1.3; border-left: 2px solid var(--primary); padding-left: 6px; }
    .disclaimer { font-size: 0.5rem; color: #888; font-style: italic; line-height: 1.2; margin-top: auto; }
    .validity-stamp { margin-top: 5px; border: 1px solid var(--primary); color: var(--primary); font-size: 0.55rem; font-weight: 800; padding: 2px 5px; display: inline-block; border-radius: 4px; text-transform: uppercase; }
    .holo-effect { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 70%); pointer-events: none; z-index: 10; opacity: 0.6; }

    /* Other Styles */
    .styled-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .styled-table th { text-align: left; padding: 12px; color: #637381; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
    .styled-table td { padding: 12px; border-bottom: 1px solid #f4f6f8; font-size: 0.9rem; vertical-align: middle; }
    
    .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
    .folder-card { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border); text-align: center; cursor: pointer; transition: 0.3s; }
    .folder-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    .folder-icon { font-size: 3.5rem; color: #ffd1b3; margin-bottom: 10px; }

    @media (max-width: 900px) {
        .top-stats-row { grid-template-columns: 1fr; }
        .main-dashboard-grid { grid-template-columns: 1fr; }
        .id-display-container { flex-direction: column; align-items: center; }
    }
</style>

    <?php if ($student_data): ?>
        <a href="my_students.php?class_id=<?php echo $_GET['class_id'] ?? ''; ?>&subject_id=<?php echo $selected_subject_id; ?>&view_list=1" style="text-decoration:none; color:var(--gray); font-weight:600; display:flex; align-items:center; gap:5px; margin-bottom:20px;">
            <i class='bx bx-arrow-back'></i> Back to List
        </a>

        <div class="top-stats-row">
            <div class="white-card summary-box">
                <div>
                    <span class="stat-label">Current Average</span>
                    <span class="stat-val" style="color:<?php echo $real_average >= 70 ? '#00ab55' : '#ff4d4f'; ?>;">
                        <?php echo $real_average; ?>%
                    </span>
                </div>
                <i class='bx bx-trending-up stat-icon'></i>
            </div>
            <div class="white-card summary-box">
                <div><span class="stat-label">Subject Rank</span><span class="stat-val">#<?php echo $class_rank; ?> / <?php echo $total_students; ?></span></div>
                <i class='bx bx-medal stat-icon'></i>
            </div>
            <div class="white-card summary-box">
                <div><span class="stat-label">Assessments</span><span class="stat-val"><?php echo $assessment_count; ?></span></div>
                <i class='bx bx-check-double stat-icon'></i>
            </div>
        </div>

        <div class="main-dashboard-grid">
            
            <div class="white-card" style="height: fit-content;">
                <div style="text-align: center; border-bottom: 1px solid #f4f6f8; padding-bottom: 20px; margin-bottom: 20px;">
                    <div style="width: 90px; height: 90px; background: #fff0e6; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin: 0 auto 15px;">
                        <?php echo substr($student_data['full_name'], 0, 1); ?>
                    </div>
                    <h2 style="margin:0; font-size:1.3rem;"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                    <p style="color:var(--gray); font-size:0.9rem; margin-top:5px;"><?php echo $student_data['admission_number']; ?></p>
                    
                    <span class="role-badge role-<?php echo explode(' ', $student_data['class_role'])[0]; ?>" style="margin-top:10px;">
                        <?php echo $student_data['class_role']; ?>
                    </span>
                </div>
                <p style="font-size:0.9rem; color:var(--gray); margin-bottom: 20px;"><strong>Class:</strong> <?php echo $student_data['class_name']; ?></p>
                <a href="messages.php?user_id=<?php echo $view_student_id; ?>" class="btn-message"><i class='bx bxs-chat'></i> Send Message</a>
            </div>

            <div style="display: flex; flex-direction: column; gap: 25px;">
                
                <div class="white-card">
                    <h3 style="margin-top:0; font-size:1.1rem;"><i class='bx bx-bar-chart-alt-2' style="color:var(--primary);"></i> Score Velocity</h3>
                    <div style="height: 300px; position:relative;">
                        <?php if($assessment_count > 0): ?>
                            <canvas id="performanceChart"></canvas>
                        <?php else: ?>
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:#ccc; text-align:center;">
                                <i class='bx bx-bar-chart' style="font-size:3rem;"></i>
                                <p>No assessment data yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="white-card">
                    <h3 class="id-section-title"><i class='bx bxs-id-card' style="color:var(--primary);"></i> Student Identification</h3>
                    
                    <div class="id-display-container">
                        
                        <div class="id-card">
                            <div class="id-front-header">
                                <span class="id-logo-text">New Generation Academy</span>
                                <img src="../assets/images/logo.png" class="id-logo-img" alt="NGA">
                            </div>
                            <div class="id-front-body">
                                <div class="id-photo">
                                    <?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?>
                                </div>
                                <div class="id-details">
                                    <h3 class="id-name"><?php echo htmlspecialchars($student_data['full_name']); ?></h3>
                                    <span class="id-role">
                                        <?php echo $student_data['leadership_role'] ? strtoupper($student_data['leadership_role']) : 'STUDENT'; ?>
                                    </span>
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
                                        <strong>Email:</strong> info@nga.rw
                                    </div>
                                    <div class="validity-stamp">VALID <?php echo date("Y"); ?></div>
                                    <div class="disclaimer">
                                        Property of NGA. Return if found.
                                    </div>
                                </div>
                            </div>
                            <div class="holo-effect"></div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    <?php elseif (isset($_GET['view_list'])): ?>
        <?php $class_id = $_GET['class_id']; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="margin:0; font-size:1.5rem;">
                <?php echo $classes_data[$class_id]['name']; ?> Students
            </h1>
            <a href="my_students.php" style="color:var(--primary); text-decoration:none; font-weight:700;">Back to Classes</a>
        </div>
        
        <div class="white-card">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Adm No</th>
                        <th>Class Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, st.admission_number, st.class_role FROM users u JOIN students st ON u.user_id = st.student_id WHERE st.class_id = ? ORDER BY u.full_name ASC");
                    $stmt->execute([$class_id]);
                    foreach($stmt->fetchAll() as $st): 
                        $r_class = explode(' ', $st['class_role'])[0];
                    ?>
                    <tr>
                        <td style="font-weight:700;"><?php echo htmlspecialchars($st['full_name']); ?></td>
                        <td><?php echo $st['admission_number']; ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $r_class; ?>">
                                <?php echo $st['class_role']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?student_id=<?php echo $st['user_id']; ?>&subject_id=<?php echo $_GET['subject_id']; ?>&class_id=<?php echo $class_id; ?>" style="color:var(--primary); text-decoration:none; font-weight:800; font-size:0.85rem;">VIEW ID & STATS</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <h1 style="margin-bottom:10px; font-size:1.5rem;">Managed Students</h1>
        <p style="color:var(--gray); margin-bottom:40px;">Select a class to analyze metrics.</p>
        <div class="folder-grid">
            <?php foreach($classes_data as $class_id => $data): ?>
                <?php foreach($data['subjects'] as $sub): ?>
                <div class="folder-card" onclick="window.location.href='?class_id=<?php echo $class_id; ?>&subject_id=<?php echo $sub['id']; ?>&view_list=1'">
                    <i class='bx bxs-folder-open folder-icon'></i>
                    <h3 style="margin:0; font-size:1.1rem;"><?php echo $sub['name']; ?></h3>
                    <div style="color:var(--gray); font-weight:600; margin-top:5px;"><?php echo $data['name']; ?></div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<script>
<?php if ($student_data && $assessment_count > 0): ?>
const ctx = document.getElementById('performanceChart').getContext('2d');

// Create gradient for the graph
let gradient = ctx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(255, 102, 0, 0.3)');
gradient.addColorStop(1, 'rgba(255, 102, 0, 0.0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Score %',
            data: <?php echo json_encode($chart_scores); ?>,
            borderColor: '#FF6600',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#FF6600',
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(33, 43, 54, 0.9)',
                padding: 10,
                cornerRadius: 8,
                displayColors: false,
                callbacks: {
                    label: function(context) { return context.parsed.y + '%'; }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                max: 100, 
                grid: { color: '#f0f0f0', borderDash: [5, 5] } 
            },
            x: { 
                grid: { display: false } 
            }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>