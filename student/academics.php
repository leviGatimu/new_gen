<?php
// student/academics.php
session_start();
require '../config/db.php';

// --- TIMEZONE FIX ---
date_default_timezone_set('Africa/Kigali'); 

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$current_time = date("Y-m-d H:i:s");

// 2. INCLUDE HEADER
$page_title = "Academic Portal";
include '../includes/header.php';

// 3. GET STUDENT CLASS
$stmt = $pdo->prepare("SELECT class_id FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$class_id = $stmt->fetchColumn();

// 4. FETCH SUBJECTS
$sub_sql = "SELECT s.subject_id, s.subject_name,
            (SELECT COUNT(*) FROM online_assessments oa 
             WHERE oa.subject_id = s.subject_id AND oa.class_id = ? AND oa.status = 'published') as task_count
            FROM class_subjects cs
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE cs.class_id = ? ORDER BY s.subject_name";
$sub_stmt = $pdo->prepare($sub_sql);
$sub_stmt->execute([$class_id, $class_id]);
$subjects = $sub_stmt->fetchAll();

// 5. FETCH ASSESSMENTS
$task_sql = "SELECT oa.*, s.subject_name, u.full_name as teacher_name, 
             sub.id as submission_id, sub.obtained_marks, sub.is_marked
             FROM online_assessments oa
             JOIN subjects s ON oa.subject_id = s.subject_id
             JOIN users u ON oa.teacher_id = u.user_id
             LEFT JOIN assessment_submissions sub 
                ON oa.id = sub.assessment_id AND sub.student_id = ?
             WHERE oa.class_id = ? AND oa.status = 'published'
             ORDER BY oa.end_time ASC";

$task_stmt = $pdo->prepare($task_sql);
$task_stmt->execute([$student_id, $class_id]);
$all_tasks = $task_stmt->fetchAll();

// Count active tasks
$total_active = 0;
foreach($all_tasks as $t) {
    if (!$t['submission_id'] && $current_time >= $t['start_time'] && $current_time <= $t['end_time']) {
        $total_active++;
    }
}

// Helper: Calculate Time Left
function getTimeLeft($end_time) {
    $now = new DateTime();
    $end = new DateTime($end_time);
    
    if ($now > $end) return "Expired";
    
    $interval = $now->diff($end);
    $parts = [];
    if ($interval->d > 0) $parts[] = $interval->d . "d";
    if ($interval->h > 0) $parts[] = $interval->h . "h";
    if ($interval->i > 0) $parts[] = $interval->i . "m";
    
    return implode(' ', array_slice($parts, 0, 2)) . " left";
}
?>

<div class="container">

    <style>
        /* === PAGE SPECIFIC CSS === */
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { margin: 0; font-size: 1.8rem; color: var(--dark); }
        .active-badge { background: #e6f7ed; color: #00ab55; font-weight: 700; padding: 8px 15px; border-radius: 20px; font-size: 0.9rem; white-space: nowrap; }

        /* LAYOUT CONTAINER */
        .portal-container { display: flex; gap: 30px; align-items: flex-start; }
        .sidebar { width: 260px; flex-shrink: 0; background: white; padding: 25px; border-radius: 16px; border: 1px solid var(--border); position: sticky; top: 100px; }
        .content-area { flex-grow: 1; min-width: 0; }

        /* FILTERS */
        .sb-title { font-size: 0.75rem; text-transform: uppercase; color: #919eab; font-weight: 800; letter-spacing: 1px; margin-bottom: 15px; }
        .filter-btn { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 12px 15px; border: none; background: transparent; color: #637381; font-weight: 600; cursor: pointer; border-radius: 10px; transition: 0.2s; margin-bottom: 5px; text-align: left; font-size: 0.9rem; }
        .filter-btn:hover { background: #f4f6f8; color: var(--dark); }
        .filter-btn.active { background: #fff0e6; color: var(--primary); font-weight: 700; }
        .badge { background: #dfe3e8; color: var(--dark); padding: 2px 8px; border-radius: 8px; font-size: 0.75rem; }
        .filter-btn.active .badge { background: var(--primary); color: white; }

        /* TASK CARDS */
        .task-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        
        .task-card {
            background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px;
            position: relative; transition: 0.3s; display: flex; flex-direction: column;
            animation: fadeIn 0.4s ease;
        }
        .task-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-color: var(--primary); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .type-badge { position: absolute; top: 20px; right: 20px; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .bg-exam { background: #fee2e2; color: #dc2626; }
        .bg-quiz { background: #fef3c7; color: #d97706; }
        .bg-assign { background: #e0f2f1; color: #00695c; }

        .info-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border); }
        .time-badge { font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 5px; }
        
        .timer-active { color: #d97706; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        /* Score & Buttons */
        .score-box { text-align: right; }
        .score-val { font-size: 1.2rem; font-weight: 800; color: var(--dark); }
        .score-max { font-size: 0.8rem; color: #919eab; }
        .score-pending { font-size: 0.9rem; font-weight: 700; color: #ffc107; background: #fff7e6; padding: 4px 8px; border-radius: 8px; }

        .btn-action { margin-top: 20px; width: 100%; padding: 12px; border-radius: 10px; font-weight: 700; text-decoration: none; text-align: center; display: block; border: none; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-start { background: var(--dark); color: white; }
        .btn-start:hover { background: var(--primary); }
        .btn-results { background: white; color: #00ab55; border: 2px solid #00ab55; }
        .btn-results:hover { background: #e6f7ed; }
        .btn-locked { background: #f4f6f8; color: #919eab; cursor: not-allowed; border: 1px solid var(--border); }
        .btn-closed { background: #fff0f0; color: #ff4d4f; cursor: not-allowed; border: 1px solid #ffebe6; }

        /* === MOBILE RESPONSIVE TWEAKS === */
        @media (max-width: 900px) {
            .container { padding: 20px; margin: 0; }
            .portal-container { flex-direction: column; gap: 20px; }
            
            /* Make Sidebar a Horizontal Scroll Bar on Mobile */
            .sidebar { 
                width: 100%; 
                position: static; 
                padding: 15px; 
                display: flex; 
                overflow-x: auto; 
                gap: 10px; 
                border-radius: 12px;
                white-space: nowrap;
                /* Hide Scrollbar */
                -ms-overflow-style: none;  
                scrollbar-width: none;  
            }
            .sidebar::-webkit-scrollbar { display: none; }

            .sb-title { display: none; /* Hide title on mobile to save space */ }
            
            .filter-btn { 
                width: auto; 
                margin: 0; 
                padding: 8px 15px; 
                background: #f4f6f8; 
                border-radius: 20px; 
                flex-shrink: 0; 
                border: 1px solid transparent;
            }
            .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
            /* Hide Badge on Mobile unless active to save width */
            .filter-btn .badge { margin-left: 8px; background: rgba(0,0,0,0.1); color: inherit; }

            .task-grid { grid-template-columns: 1fr; } /* Single column cards */
            
            .page-header h1 { font-size: 1.5rem; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1>Assessment Center</h1>
            <p style="color: #637381; margin: 5px 0 0;">Manage your exams, quizzes, and digital assignments.</p>
        </div>
        <span class="active-badge">
            <i class='bx bx-pulse'></i> <?php echo $total_active; ?> Active Tasks
        </span>
    </div>

    <div class="portal-container">
        
        <div class="sidebar">
            <div class="sb-title">Filter by Subject</div>
            <button class="filter-btn active" onclick="filterTasks('all', this)">
                <span>All Subjects</span>
                <span class="badge"><?php echo count($all_tasks); ?></span>
            </button>
            <?php foreach($subjects as $sub): ?>
                <button class="filter-btn" onclick="filterTasks(<?php echo $sub['subject_id']; ?>, this)">
                    <span><?php echo htmlspecialchars($sub['subject_name']); ?></span>
                    <?php if($sub['task_count'] > 0): ?>
                        <span class="badge"><?php echo $sub['task_count']; ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="content-area">
            <div class="task-grid">
                <?php if(empty($all_tasks)): ?>
                    <div style="grid-column: 1/-1; text-align:center; padding: 80px; background:white; border-radius:16px; border:2px dashed #dfe3e8;">
                        <i class='bx bx-check-circle' style="font-size:3rem; color:#dfe3e8; margin-bottom:15px;"></i>
                        <h3 style="margin:0; color:var(--dark);">All caught up!</h3>
                        <p style="color:#919eab;">No active assessments assigned to your class.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($all_tasks as $task): 
                        // Logic
                        $start = $task['start_time'];
                        $end = $task['end_time'];
                        $is_submitted = !empty($task['submission_id']);
                        
                        $is_locked = ($current_time < $start);
                        $is_active = ($current_time >= $start && $current_time <= $end);
                        $is_expired = ($current_time > $end);
                        
                        $timeLeft = getTimeLeft($end);

                        // Styling
                        $type_class = 'bg-quiz';
                        if($task['type'] == 'exam') $type_class = 'bg-exam';
                        if($task['type'] == 'assignment') $type_class = 'bg-assign';
                    ?>
                    
                    <div class="task-card" data-subid="<?php echo $task['subject_id']; ?>">
                        <span class="type-badge <?php echo $type_class; ?>"><?php echo strtoupper($task['type']); ?></span>
                        
                        <h3 style="margin: 0 0 5px; color: var(--dark); padding-right: 60px;"><?php echo htmlspecialchars($task['title']); ?></h3>
                        <p style="margin:0; font-size: 0.85rem; color: #637381; font-weight: 600;">
                            <?php echo htmlspecialchars($task['subject_name']); ?>
                        </p>
                        <p style="font-size: 0.8rem; color: #919eab; margin-top: 5px;">
                            By: <?php echo htmlspecialchars($task['teacher_name']); ?>
                        </p>

                        <div class="info-row">
                            <div>
                                <?php if($is_submitted): ?>
                                    <div class="time-badge" style="color:#00ab55;"><i class='bx bxs-check-circle'></i> Done</div>
                                <?php elseif($is_active): ?>
                                    <div class="time-badge timer-active"><i class='bx bx-time'></i> <?php echo $timeLeft; ?></div>
                                <?php elseif($is_locked): ?>
                                    <div class="time-badge" style="color:#919eab;"><i class='bx bxs-lock-alt'></i> Locked</div>
                                <?php else: ?>
                                    <div class="time-badge" style="color:#ff4d4f;"><i class='bx bxs-x-circle'></i> Closed</div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if($is_submitted): ?>
                                    <?php if($task['is_marked']): ?>
                                        <div class="score-box">
                                            <span class="score-val"><?php echo $task['obtained_marks']; ?></span>
                                            <span class="score-max">/<?php echo $task['total_marks']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="score-pending">Pending</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size:0.85rem; color:#637381; font-weight:600;"><?php echo $task['duration_minutes']; ?> mins</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($is_submitted): ?>
                            <a href="view_result.php?id=<?php echo $task['submission_id']; ?>" class="btn-action btn-results">
                                <i class='bx bx-show'></i> View Results
                            </a>
                        <?php elseif($is_locked): ?>
                            <button class="btn-action btn-locked">
                                Starts: <?php echo date("d M, H:i", strtotime($start)); ?>
                            </button>
                        <?php elseif($is_expired): ?>
                            <button class="btn-action btn-closed">
                                Closed
                            </button>
                        <?php else: ?>
                            <button onclick="confirmStart(<?php echo $task['id']; ?>, '<?php echo addslashes($task['title']); ?>')" class="btn-action btn-start">
                                Start Assessment <i class='bx bx-right-arrow-alt'></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // FILTER LOGIC
    function filterTasks(subId, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const cards = document.querySelectorAll('.task-card');
        cards.forEach(card => {
            if (subId === 'all' || card.dataset.subid == subId) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // CONFIRM START
    function confirmStart(id, title) {
        if(confirm("Are you sure you want to start '" + title + "'?\n\nOnce started, the timer will begin and cannot be paused.")) {
            window.location.href = "take_assessment.php?id=" + id;
        }
    }
</script>

</body>
</html>