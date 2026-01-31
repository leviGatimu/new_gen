<?php
// teacher/mark_online.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$assessment_id = $_GET['id'] ?? null;
$current_student_id = $_GET['student_id'] ?? null;

// 1. FETCH EXAM DETAILS
$stmt = $pdo->prepare("SELECT oa.*, c.class_name, s.subject_name 
                        FROM online_assessments oa
                        JOIN classes c ON oa.class_id = c.class_id
                        JOIN subjects s ON oa.subject_id = s.subject_id
                        WHERE oa.id = ? AND oa.teacher_id = ?");
$stmt->execute([$assessment_id, $teacher_id]);
$exam = $stmt->fetch();

if (!$exam) die("Assessment not found or access denied.");

// 2. FETCH STUDENTS & SUBMISSIONS
$std_sql = "SELECT st.student_id, u.full_name, st.admission_number, sub.id as sub_id, sub.is_marked, sub.obtained_marks
            FROM students st
            JOIN users u ON st.student_id = u.user_id
            LEFT JOIN assessment_submissions sub ON (st.student_id = sub.student_id AND sub.assessment_id = ?)
            WHERE st.class_id = ?
            ORDER BY u.full_name ASC";
$std_stmt = $pdo->prepare($std_sql);
$std_stmt->execute([$assessment_id, $exam['class_id']]);
$students = $std_stmt->fetchAll();

// Default to first student if none selected
if (!$current_student_id && !empty($students)) {
    $current_student_id = $students[0]['student_id'];
    // Redirect to ensure URL has student_id
    header("Location: mark_online.php?id=$assessment_id&student_id=$current_student_id");
    exit;
}

// 3. FETCH DATA FOR SELECTED STUDENT
$current_submission = null;
$student_answers = [];
$saved_scores = [];

if ($current_student_id) {
    // A. Get Total Submission Info
    $sub_stmt = $pdo->prepare("SELECT * FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $sub_stmt->execute([$assessment_id, $current_student_id]);
    $current_submission = $sub_stmt->fetch();

    if ($current_submission) {
        $student_answers = json_decode($current_submission['submission_text'], true) ?? [];
    }

    // B. Get Individual Saved Scores
    $res_stmt = $pdo->prepare("SELECT question_id, score_obtained FROM assessment_results WHERE assessment_id = ? AND student_id = ?");
    $res_stmt->execute([$assessment_id, $current_student_id]);
    while($row = $res_stmt->fetch()) {
        $saved_scores[$row['question_id']] = $row['score_obtained'];
    }
}

// 4. FETCH QUESTIONS & OPTIONS
$q_stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY id ASC");
$q_stmt->execute([$assessment_id]);
$questions = $q_stmt->fetchAll();

$opt_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN (SELECT id FROM assessment_questions WHERE assessment_id = ?)");
$opt_stmt->execute([$assessment_id]);
$options = $opt_stmt->fetchAll();
$opt_map = [];
foreach($options as $opt) { $opt_map[$opt['question_id']][] = $opt; }

// 5. HANDLE SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $total_score = 0;
    $manual_points = $_POST['points'] ?? [];
    $teacher_comment = $_POST['teacher_comment'] ?? '';
    
    try {
        $pdo->beginTransaction();

        foreach($questions as $q) {
            $qid = $q['id'];
            $points_for_this_q = 0;

            if ($q['question_type'] == 'multiple_choice') {
                $user_ans = $student_answers[$qid] ?? null;
                $is_correct = false;
                foreach ($opt_map[$qid] as $opt) {
                    if ($opt['is_correct'] && $opt['id'] == $user_ans) {
                        $is_correct = true;
                    }
                }
                $points_for_this_q = $is_correct ? $q['points'] : 0;
            } else {
                $points_for_this_q = $manual_points[$qid] ?? 0;
            }

            $total_score += $points_for_this_q;

            // SAVE INDIVIDUAL SCORE
            $check = $pdo->prepare("SELECT result_id FROM assessment_results WHERE assessment_id=? AND student_id=? AND question_id=?");
            $check->execute([$assessment_id, $current_student_id, $qid]);
            
            if ($check->rowCount() > 0) {
                $upd_res = $pdo->prepare("UPDATE assessment_results SET score_obtained = ? WHERE assessment_id=? AND student_id=? AND question_id=?");
                $upd_res->execute([$points_for_this_q, $assessment_id, $current_student_id, $qid]);
            } else {
                $ins_res = $pdo->prepare("INSERT INTO assessment_results (assessment_id, student_id, question_id, score_obtained) VALUES (?, ?, ?, ?)");
                $ins_res->execute([$assessment_id, $current_student_id, $qid, $points_for_this_q]);
            }
        }

        // SAVE TOTAL
        if ($current_submission) {
            $upd = $pdo->prepare("UPDATE assessment_submissions SET obtained_marks = ?, teacher_comment = ?, is_marked = 1 WHERE id = ?");
            $upd->execute([$total_score, $teacher_comment, $current_submission['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO assessment_submissions (assessment_id, student_id, obtained_marks, teacher_comment, is_marked, submission_text, submitted_at) VALUES (?, ?, ?, ?, 1, '{}', NOW())");
            $ins->execute([$assessment_id, $current_student_id, $total_score, $teacher_comment]);
        }

        $pdo->commit();
        $msg = "Grade Saved Successfully.";
        header("Location: mark_online.php?id=$assessment_id&student_id=$current_student_id&msg=" . urlencode($msg));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grading: <?php echo htmlspecialchars($exam['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === MODERN VARIABLES === */
        :root { 
            --primary: #FF6600; 
            --primary-light: #fff0e6;
            --dark: #1e293b; 
            --text-gray: #64748b;
            --bg-light: #f8fafc; 
            --white: #ffffff;
            --border: #e2e8f0; 
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --radius: 12px;
        }
        
        * { box-sizing: border-box; }
        body { background: var(--bg-light); height: 100vh; overflow: hidden; display: flex; flex-direction: column; font-family: 'Inter', 'Public Sans', sans-serif; margin: 0; color: var(--dark); }

        /* === TOP NAVIGATION === */
        .top-bar { 
            height: 65px; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border); display: flex; align-items: center; 
            justify-content: space-between; padding: 0 30px; flex-shrink: 0; z-index: 50;
        }
        .back-btn { 
            text-decoration: none; color: var(--text-gray); font-weight: 600; 
            display: flex; align-items: center; gap: 8px; transition: 0.2s; padding: 8px 12px; border-radius: 8px;
        }
        .back-btn:hover { background: var(--bg-light); color: var(--dark); }
        .exam-info { text-align: right; }
        .exam-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--dark); }
        .exam-sub { font-size: 0.8rem; color: var(--text-gray); font-weight: 500; }

        /* === WORKSPACE LAYOUT === */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* === STUDENT SIDEBAR === */
        .student-sidebar { 
            width: 320px; background: var(--white); border-right: 1px solid var(--border); 
            display: flex; flex-direction: column; z-index: 40; box-shadow: var(--shadow-sm);
        }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .search-input { 
            width: 100%; padding: 10px 15px; border: 1px solid var(--border); 
            border-radius: 8px; background: var(--bg-light); outline: none; transition: 0.2s; font-size: 0.9rem;
        }
        .search-input:focus { border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 3px var(--primary-light); }
        
        .student-list { overflow-y: auto; flex: 1; padding: 10px; }
        .student-item { 
            padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; 
            cursor: pointer; display: flex; justify-content: space-between; align-items: center; 
            text-decoration: none; color: inherit; transition: all 0.2s ease; border: 1px solid transparent;
        }
        .student-item:hover { background: var(--bg-light); }
        .student-item.active { 
            background: var(--primary-light); border-color: #ffdecb; 
            color: var(--primary); font-weight: 600; 
        }
        .st-name { font-size: 0.9rem; margin-bottom: 2px; }
        .st-id { font-size: 0.75rem; color: var(--text-gray); }
        .student-item.active .st-id { color: rgba(255, 102, 0, 0.7); }

        .status-badge { 
            font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; 
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-marked { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-none { background: #f1f5f9; color: #64748b; }

        /* === GRADING AREA === */
        .grading-area { flex: 1; overflow-y: auto; padding: 30px; background: #f1f5f9; position: relative; }
        .paper-container { max-width: 850px; margin: 0 auto 80px auto; } /* Margin bottom for footer */

        /* Alert */
        .success-alert { 
            background: #dcfce7; color: #166534; padding: 15px; border-radius: 10px; 
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px; 
            font-weight: 600; border: 1px solid #bbf7d0; box-shadow: var(--shadow-sm);
        }

        /* The "Paper" Card */
        .paper { 
            background: var(--white); border-radius: 16px; 
            box-shadow: var(--shadow-md); border: 1px solid var(--border); overflow: hidden;
        }
        
        .paper-header { 
            padding: 25px 30px; border-bottom: 1px solid var(--border); 
            background: rgba(255,255,255,0.8); backdrop-filter: blur(5px);
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 30;
        }
        .total-badge { 
            text-align: right; background: var(--bg-light); padding: 8px 15px; 
            border-radius: 10px; border: 1px solid var(--border);
        }
        .total-val { font-size: 1.4rem; font-weight: 800; color: var(--dark); line-height: 1; }
        .total-label { font-size: 0.7rem; color: var(--text-gray); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 2px;}

        /* Questions */
        .q-item { padding: 30px; border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .q-item:hover { background: #fafbfc; }
        .q-item:last-child { border-bottom: none; }

        .q-meta { 
            display: flex; justify-content: space-between; margin-bottom: 15px; 
            font-size: 0.85rem; font-weight: 700; color: var(--text-gray); letter-spacing: 0.5px;
        }
        .q-badge { background: var(--bg-light); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border); }
        
        .q-text { font-size: 1.05rem; font-weight: 600; line-height: 1.5; color: var(--dark); margin-bottom: 20px; }

        /* Answer Box */
        .ans-box { 
            background: #fff; border: 1px solid var(--border); border-radius: 10px; 
            padding: 20px; margin-bottom: 20px; box-shadow: var(--shadow-sm); 
        }
        .ans-text { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.95rem; color: #334155; white-space: pre-wrap; line-height: 1.6; }
        
        /* MCQ Styles */
        .mcq-opt { 
            display: flex; align-items: center; gap: 12px; padding: 10px 15px; 
            border-radius: 8px; margin-bottom: 8px; border: 1px solid transparent; 
            transition: 0.2s; font-size: 0.95rem;
        }
        .mcq-correct { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; font-weight: 600; }
        .mcq-wrong { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .mcq-neutral { background: var(--bg-light); border-color: var(--border); color: var(--text-gray); opacity: 0.8; }
        .check-icon { font-size: 1.2rem; }

        /* Grading Controls */
        .grading-panel { 
            background: var(--bg-light); padding: 15px 20px; border-radius: 10px; 
            display: flex; align-items: center; gap: 20px; border: 1px solid var(--border);
        }
        .score-group { display: flex; align-items: center; gap: 10px; }
        .points-input { 
            width: 70px; padding: 10px; text-align: center; font-size: 1.1rem; font-weight: 700; 
            border: 2px solid var(--border); border-radius: 8px; outline: none; transition: 0.2s; 
            color: var(--primary);
        }
        .points-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: white; }
        
        .feedback-box { padding: 30px; background: var(--white); border-top: 1px solid var(--border); }
        .feedback-input { 
            width: 100%; padding: 15px; border: 1px solid var(--border); border-radius: 10px; 
            font-family: inherit; font-size: 0.95rem; resize: vertical; outline: none; transition: 0.2s;
            background: var(--bg-light);
        }
        .feedback-input:focus { border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 3px var(--primary-light); }

        /* Footer Action Bar */
        .action-bar { 
            position: fixed; bottom: 0; right: 0; width: calc(100% - 320px); 
            background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);
            padding: 15px 40px; border-top: 1px solid var(--border); 
            display: flex; justify-content: flex-end; z-index: 100;
        }
        .btn-save { 
            background: var(--dark); color: white; border: none; padding: 12px 35px; 
            border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; 
            transition: all 0.2s; display: flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-save:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 15px rgba(255,102,0,0.2); }

        /* Scrollbars */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="assessments.php" class="back-btn">
        <i class='bx bx-arrow-back'></i> <span>Back to Assessments</span>
    </a>
    <div class="exam-info">
        <h1 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h1>
        <span class="exam-sub"><?php echo htmlspecialchars($exam['class_name']); ?> • <?php echo htmlspecialchars($exam['subject_name']); ?></span>
    </div>
</div>

<div class="workspace">
    
    <div class="student-sidebar">
        <div class="sidebar-header">
            <input type="text" id="studentSearch" onkeyup="filterStudents()" class="search-input" placeholder="Search student...">
        </div>
        <div class="student-list" id="studentList">
            <?php foreach($students as $st): 
                $active = ($st['student_id'] == $current_student_id) ? 'active' : '';
                
                $badge_class = 'status-none';
                $badge_text = 'Not Started';
                if ($st['is_marked']) {
                    $badge_class = 'status-marked'; $badge_text = 'Marked';
                } elseif ($st['sub_id']) {
                    $badge_class = 'status-pending'; $badge_text = 'Needs Grading';
                }

                $link = "?id=$assessment_id&student_id=" . $st['student_id'];
            ?>
            <a href="<?php echo $link; ?>" class="student-item <?php echo $active; ?>">
                <div>
                    <div class="st-name"><?php echo htmlspecialchars($st['full_name']); ?></div>
                    <div class="st-id"><?php echo $st['admission_number']; ?></div>
                </div>
                <span class="status-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grading-area">
        <div class="paper-container">
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="success-alert">
                    <i class='bx bxs-check-circle' style="font-size:1.4rem;"></i>
                    <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="paper">
                <div class="paper-header">
                    <div>
                        <h2 style="margin:0; font-size:1.4rem; color:var(--dark);">Grading Paper</h2>
                        <span style="font-size:0.9rem; color:var(--text-gray);">
                            <?php echo $current_submission ? "Submission received on " . date("M d, H:i", strtotime($current_submission['submitted_at'])) : "Manual Entry (No Submission)"; ?>
                        </span>
                    </div>
                    <div class="total-badge">
                        <span class="total-label">Total Score</span>
                        <span class="total-val" id="grandTotal">0</span>
                        <span style="font-size:1rem; color:var(--text-gray); font-weight:600;">/ <?php echo $exam['total_marks']; ?></span>
                    </div>
                </div>

                <?php 
                foreach($questions as $index => $q): 
                    $qid = $q['id'];
                    $user_ans = $student_answers[$qid] ?? null;
                    $points_earned = 0; 
                    // FETCH SAVED SCORE IF EXISTS
                    $saved_val = isset($saved_scores[$qid]) ? $saved_scores[$qid] : 0;
                ?>
                <div class="q-item">
                    <div class="q-meta">
                        <span class="q-badge">Question <?php echo $index + 1; ?></span>
                        <span><?php echo $q['points']; ?> Marks</span>
                    </div>
                    <div class="q-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>

                    <?php if($q['question_type'] == 'multiple_choice'): ?>
                        <div class="ans-box" style="border:none; background:transparent; padding:0; box-shadow:none;">
                            <?php 
                            $is_correct = false;
                            foreach($opt_map[$qid] as $opt):
                                $class = 'mcq-neutral';
                                $icon = 'bx-circle';
                                
                                if($opt['id'] == $user_ans) {
                                    $icon = 'bx-check-circle'; // User selection
                                    if($opt['is_correct']) { $class = 'mcq-correct'; $is_correct = true; } 
                                    else { $class = 'mcq-wrong'; $icon = 'bx-x-circle'; }
                                } elseif ($opt['is_correct']) {
                                    $class = 'mcq-correct'; // Show correct answer if user missed it
                                }
                            ?>
                            <div class="mcq-opt <?php echo $class; ?>">
                                <i class='bx <?php echo $icon; ?> check-icon'></i>
                                <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                <?php if($opt['id'] == $user_ans) echo '<span style="margin-left:auto; font-size:0.75rem; font-weight:700; opacity:0.7;">(STUDENT)</span>'; ?>
                            </div>
                            <?php endforeach; 
                            
                            if($is_correct) { $points_earned = $q['points']; }
                            ?>
                        </div>
                        <div style="margin-top:10px; font-weight:700; font-size:0.9rem; color:<?php echo $is_correct ? 'var(--success)' : 'var(--danger)'; ?>">
                            <i class='bx <?php echo $is_correct ? 'bx-check' : 'bx-x'; ?>'></i> 
                            Auto-Graded: <?php echo $points_earned; ?> / <?php echo $q['points']; ?>
                            <input type="hidden" class="calc-points" value="<?php echo $points_earned; ?>">
                        </div>

                    <?php else: ?>
                        <div class="ans-box">
                            <div class="ans-text"><?php echo htmlspecialchars($user_ans ?? 'No answer provided.'); ?></div>
                        </div>
                        
                        <div class="grading-panel">
                            <div class="score-group">
                                <label style="font-weight:700; font-size:0.9rem;">Score:</label>
                                <input type="number" name="points[<?php echo $qid; ?>]" 
                                    class="points-input calc-points" 
                                    value="<?php echo htmlspecialchars($saved_val); ?>" 
                                    min="0" max="<?php echo $q['points']; ?>" 
                                    oninput="calculateTotal()" step="0.5">
                                <span style="color:var(--text-gray); font-weight:600;">/ <?php echo $q['points']; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="feedback-box">
                    <label style="font-weight:700; display:block; margin-bottom:10px; color:var(--dark);">Teacher's Overall Feedback</label>
                    <textarea name="teacher_comment" rows="4" class="feedback-input" placeholder="Write feedback for the student here..."><?php echo htmlspecialchars($current_submission['teacher_comment'] ?? ''); ?></textarea>
                </div>

                <div class="action-bar">
                    <button type="submit" name="save_grades" class="btn-save">
                        <i class='bx bxs-save'></i> Save & Publish Grade
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function calculateTotal() {
        let total = 0;
        // Sum up manual inputs and hidden auto-graded inputs
        document.querySelectorAll('.calc-points').forEach(input => {
            let val = parseFloat(input.value);
            if(!isNaN(val)) total += val;
        });
        document.getElementById('grandTotal').innerText = total;
    }

    function filterStudents() {
        let input = document.getElementById('studentSearch').value.toUpperCase();
        let items = document.getElementsByClassName('student-item');
        for (let i = 0; i < items.length; i++) {
            let name = items[i].getElementsByClassName('st-name')[0].innerText;
            if (name.toUpperCase().indexOf(input) > -1) {
                items[i].style.display = "";
            } else {
                items[i].style.display = "none";
            }
        }
    }

    // Run once on load to show current total from DB
    calculateTotal();
</script>

</body>
</html>