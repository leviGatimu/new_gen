<?php
// student/results.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$current_user_id = $_SESSION['user_id'];
$view_target_id = $_GET['view_student'] ?? $current_user_id;

// 2. INCLUDE HEADER
$page_title = "Academic Results";
include '../includes/header.php';

// 3. CHECK PERMISSIONS (President Viewing Peer)
$role_stmt = $pdo->prepare("SELECT leadership_role AS class_role, full_name FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.student_id = ?");
$role_stmt->execute([$current_user_id]);
$me = $role_stmt->fetch();

// Logic: If trying to view someone else, MUST be President (or Head Boy/Girl)
if ($view_target_id != $current_user_id && !in_array($me['class_role'], ['President', 'Head Boy', 'Head Girl'])) {
    echo "<div class='container' style='padding-top:100px; text-align:center;'>
            <h2>Access Denied</h2>
            <p>Only Class Presidents or Head Students can view other reports.</p>
            <a href='dashboard.php' class='btn-back'>Go Back</a>
          </div>";
    include '../includes/footer.php'; // Optional footer
    exit;
}

// 4. FETCH TARGET NAME
if ($view_target_id != $current_user_id) {
    $target_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $target_stmt->execute([$view_target_id]);
    $target_name = $target_stmt->fetchColumn();
    $show_back_btn = true;
} else {
    $target_name = "My Results";
    $show_back_btn = false;
}

// 5. FETCH TERMS
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY term_id ASC")->fetchAll();
$selected_term = $_GET['term_id'] ?? 1; // Default to Term 1

// 6. FETCH MARKS (Combined Manual & Online)
$grouped_results = [];

// A. Manual Marks
$sql_manual = "
    SELECT 
        s.subject_name, 
        sm.score, 
        ca.max_score, 
        gc.name as type, 
        ca.created_at as date
    FROM student_marks sm
    JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
    JOIN subjects s ON ca.subject_id = s.subject_id
    JOIN grading_categories gc ON ca.category_id = gc.id
    WHERE sm.student_id = ? AND ca.term_id = ?
";
$stmt = $pdo->prepare($sql_manual);
$stmt->execute([$view_target_id, $selected_term]);
$manual_data = $stmt->fetchAll();

foreach ($manual_data as $row) {
    $grouped_results[$row['subject_name']][] = [
        'title' => $row['type'],
        'score' => $row['score'],
        'max'   => $row['max_score'],
        'date'  => $row['date'],
        'source'=> 'Manual'
    ];
}

// B. Online Marks
// (Note: Online marks usually need a Term ID in the online_assessments table to filter correctly. 
// Assuming here we fetch all for simplicity, or you add WHERE term_id = ?)
$sql_online = "
    SELECT 
        s.subject_name, 
        sub.obtained_marks as score, 
        oa.total_marks as max_score, 
        oa.title as type, 
        sub.submitted_at as date
    FROM assessment_submissions sub
    JOIN online_assessments oa ON sub.assessment_id = oa.id
    JOIN subjects s ON oa.subject_id = s.subject_id
    WHERE sub.student_id = ? AND sub.is_marked = 1
";
$stmt = $pdo->prepare($sql_online);
$stmt->execute([$view_target_id]);
$online_data = $stmt->fetchAll();

foreach ($online_data as $row) {
    $grouped_results[$row['subject_name']][] = [
        'title' => $row['type'] . ' (Online)',
        'score' => $row['score'],
        'max'   => $row['max_score'],
        'date'  => $row['date'],
        'source'=> 'Online'
    ];
}

ksort($grouped_results);
?>

<div class="container">
    
    <style>
        /* === PAGE SPECIFIC CSS === */
        
        /* Header Area */
        .results-header { 
            background: white; border-radius: 16px; border: 1px solid var(--border); 
            padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
        }
        
        .header-info h1 { margin: 0; font-size: 1.8rem; color: var(--dark); }
        .header-info p { margin: 5px 0 0; color: #637381; font-size: 0.95rem; }
        .back-link { text-decoration: none; color: var(--primary); font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 5px; }
        .back-link:hover { text-decoration: underline; }

        /* Term Selector */
        .term-form { display: flex; align-items: center; gap: 10px; }
        .term-select { padding: 10px 15px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; color: var(--dark); background: #f9fafb; font-weight: 600; cursor: pointer; min-width: 150px; }
        .term-select:focus { border-color: var(--primary); outline: none; }

        /* Content Layout */
        .results-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        
        /* Subject Block */
        .subject-card { background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        
        .subject-head { 
            background: #f9fafb; padding: 15px 25px; border-bottom: 1px solid var(--border); 
            display: flex; align-items: center; gap: 10px;
        }
        .subject-head h2 { margin: 0; font-size: 1.1rem; color: var(--dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .subject-icon { color: var(--primary); font-size: 1.3rem; }

        /* Table */
        .marks-table { width: 100%; border-collapse: collapse; }
        .marks-table th { background: white; color: #637381; text-align: left; padding: 12px 25px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .marks-table td { padding: 15px 25px; border-bottom: 1px solid #f4f6f8; color: #212b36; font-size: 0.95rem; vertical-align: middle; }
        .marks-table tr:last-child td { border-bottom: none; }
        .marks-table tr:hover { background: #fafbfc; }

        /* Score Badge */
        .score-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-weight: 800; font-size: 0.9rem; min-width: 50px; text-align: center; }
        .pass { background: #e6f7ed; color: #00ab55; }
        .avg { background: #fff7e6; color: #ffc107; }
        .fail { background: #fff1f0; color: #ff4d4f; }
        .max-score { color: #919eab; font-size: 0.8rem; margin-left: 5px; font-weight: 600; }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px; color: #919eab; background: white; border-radius: 16px; border: 2px dashed var(--border); }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .results-header { flex-direction: column; align-items: flex-start; }
            .term-form { width: 100%; }
            .term-select { width: 100%; }
            
            .marks-table th, .marks-table td { padding: 12px 15px; }
            .marks-table th:nth-child(2), .marks-table td:nth-child(2) { display: none; /* Hide Date on small screens */ }
        }
    </style>

    <div class="results-header">
        <div class="header-info">
            <?php if($show_back_btn): ?>
                <a href="class_ranking.php" class="back-link"><i class='bx bx-arrow-back'></i> Back to Leaderboard</a>
            <?php endif; ?>
            
            <h1><?php echo htmlspecialchars($target_name); ?></h1>
            <p>Official Academic Transcript</p>
        </div>
        
        <form method="GET" class="term-form">
            <?php if($show_back_btn): ?>
                <input type="hidden" name="view_student" value="<?php echo $view_target_id; ?>">
            <?php endif; ?>
            
            <select name="term_id" class="term-select" onchange="this.form.submit()">
                <?php foreach($terms as $t): ?>
                    <option value="<?php echo $t['term_id']; ?>" <?php if($t['term_id'] == $selected_term) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($t['term_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="results-grid">
        <?php if (empty($grouped_results)): ?>
            <div class="empty-state">
                <i class='bx bx-folder-open' style="font-size: 3rem; margin-bottom: 10px;"></i>
                <h3>No results found</h3>
                <p>Marks for this term have not been published yet.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($grouped_results as $subject => $marks): ?>
                <div class="subject-card">
                    <div class="subject-head">
                        <i class='bx bxs-book-bookmark subject-icon'></i>
                        <h2><?php echo htmlspecialchars($subject); ?></h2>
                    </div>
                    
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Assessment</th>
                                <th style="width: 25%;">Date Recorded</th>
                                <th style="width: 25%; text-align: right;">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marks as $mark): 
                                $pct = ($mark['max'] > 0) ? ($mark['score'] / $mark['max']) * 100 : 0;
                                $grade_class = ($pct >= 70) ? 'pass' : (($pct >= 50) ? 'avg' : 'fail');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mark['title']); ?></strong></td>
                                <td style="color: #637381;"><?php echo date("M d, Y", strtotime($mark['date'])); ?></td>
                                <td style="text-align: right;">
                                    <span class="score-badge <?php echo $grade_class; ?>"><?php echo $mark['score']; ?></span>
                                    <span class="max-score">/ <?php echo $mark['max']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

</div>

</body>
</html>