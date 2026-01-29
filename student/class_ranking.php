<?php
// student/class_ranking.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$page_title = "Leaderboard";

// 2. INCLUDE HEADER
include '../includes/header.php';

// 3. FETCH CLASS & ROLE
$stmt = $pdo->prepare("SELECT class_id, leadership_role AS class_role FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$me = $stmt->fetch();
$my_class_id = $me['class_id'];
$is_leader = in_array($me['class_role'], ['President', 'Head Boy', 'Head Girl']);

// 4. FETCH CLASSMATES
$peers_stmt = $pdo->prepare("SELECT s.student_id, u.full_name, s.admission_number, s.leadership_role AS class_role 
                             FROM students s JOIN users u ON s.student_id = u.user_id 
                             WHERE s.class_id = ?");
$peers_stmt->execute([$my_class_id]);
$all_students = $peers_stmt->fetchAll();

// 5. CALCULATE AVERAGES
$rankings = [];

foreach ($all_students as $st) {
    $sid = $st['student_id'];
    
    // Manual Marks
    $q1 = $pdo->prepare("SELECT SUM(score) as s, SUM(max_score) as m FROM student_marks m JOIN class_assessments a ON m.assessment_id = a.assessment_id WHERE m.student_id = ?");
    $q1->execute([$sid]);
    $r1 = $q1->fetch();
    
    // Online Marks
    $q2 = $pdo->prepare("SELECT SUM(obtained_marks) as s, SUM(total_marks) as m FROM assessment_submissions s JOIN online_assessments o ON s.assessment_id = o.id WHERE s.student_id = ? AND s.is_marked = 1");
    $q2->execute([$sid]);
    $r2 = $q2->fetch();

    $total_obt = ($r1['s'] ?? 0) + ($r2['s'] ?? 0);
    $total_max = ($r1['m'] ?? 0) + ($r2['m'] ?? 0);
    $avg = ($total_max > 0) ? ($total_obt / $total_max) * 100 : 0;

    $rankings[] = [
        'id' => $sid,
        'name' => $st['full_name'],
        'role' => $st['class_role'],
        'avg' => round($avg, 1)
    ];
}

// 6. SORT DESCENDING
usort($rankings, function($a, $b) {
    return $b['avg'] <=> $a['avg'];
});

// Separate Top 3 for Podium
$top3 = array_slice($rankings, 0, 3);
$rest = array_slice($rankings, 3);
?>

<div class="container">

    <style>
        /* === PAGE SPECIFIC CSS === */
        
        /* Podium Section */
        .podium-section { 
            display: flex; justify-content: center; align-items: flex-end; gap: 20px; 
            margin: 40px 0 60px; padding-bottom: 20px;
        }
        
        .podium-place { 
            display: flex; flex-direction: column; align-items: center; text-align: center; position: relative;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .podium-place:hover { transform: translateY(-10px); }

        .p-avatar { 
            width: 80px; height: 80px; border-radius: 50%; border: 4px solid #fff; 
            background: #e0e6ed; display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); z-index: 2; position: relative;
        }
        
        .p-block { 
            width: 100px; border-radius: 12px 12px 0 0; position: relative;
            display: flex; align-items: flex-end; justify-content: center; padding-bottom: 15px;
            font-weight: 900; font-size: 3rem; color: rgba(0,0,0,0.1);
        }

        /* Rank 1 (Gold) */
        .rank-1 { order: 2; }
        .rank-1 .p-avatar { background: #FFD700; border-color: #fffbe6; width: 100px; height: 100px; font-size: 2.5rem; }
        .rank-1 .p-block { height: 140px; background: linear-gradient(180deg, #FFD700 0%, #d4b106 100%); width: 120px; }
        .crown-icon { position: absolute; top: -35px; color: #FFD700; font-size: 2.5rem; animation: float 3s ease-in-out infinite; }

        /* Rank 2 (Silver) */
        .rank-2 { order: 1; }
        .rank-2 .p-avatar { background: #C0C0C0; }
        .rank-2 .p-block { height: 100px; background: linear-gradient(180deg, #C0C0C0 0%, #aaaaaa 100%); }

        /* Rank 3 (Bronze) */
        .rank-3 { order: 3; }
        .rank-3 .p-avatar { background: #CD7F32; }
        .rank-3 .p-block { height: 70px; background: linear-gradient(180deg, #CD7F32 0%, #a05a1c 100%); }

        .p-name { font-weight: 700; color: var(--dark); margin-top: 10px; font-size: 0.9rem; max-width: 120px; line-height: 1.2; }
        .p-score { font-weight: 800; color: var(--primary); font-size: 1.1rem; margin-top: 5px; }

        /* List Section */
        .rank-list { background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        
        .list-item { 
            display: flex; align-items: center; padding: 15px 25px; border-bottom: 1px solid #f9fafb; 
            transition: 0.2s; position: relative;
        }
        .list-item:hover { background: #fafbfc; }
        .list-item:last-child { border-bottom: none; }

        .l-rank { width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #637381; margin-right: 20px; }
        .l-info { flex: 1; }
        .l-name { font-weight: 600; color: var(--dark); font-size: 1rem; }
        .l-score { font-weight: 800; color: var(--primary); font-size: 1.1rem; }
        
        .role-tag { font-size: 0.7rem; background: #f3e5f5; color: #7b1fa2; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: 700; text-transform: uppercase; }
        .you-tag { font-size: 0.7rem; background: #e6f7ed; color: #00ab55; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: 700; text-transform: uppercase; }

        .btn-view { padding: 6px 12px; background: var(--dark); color: white; border-radius: 6px; text-decoration: none; font-size: 0.75rem; font-weight: 700; margin-left: 15px; }
        .btn-view:hover { background: var(--primary); }

        @keyframes float { 0% { transform: translateY(0); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0); } }

        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            .podium-section { flex-direction: column; align-items: center; gap: 40px; margin-top: 20px; }
            .rank-1 { order: 1; }
            .rank-2 { order: 2; }
            .rank-3 { order: 3; }
            
            .podium-place { width: 100%; flex-direction: row; align-items: center; text-align: left; padding: 15px; background: white; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
            .p-block { display: none; /* Hide blocks on mobile list view */ }
            .p-avatar { margin: 0 15px 0 0; width: 60px; height: 60px; font-size: 1.5rem; }
            .crown-icon { left: 15px; top: -15px; font-size: 2rem; }
            .p-name { font-size: 1.1rem; max-width: none; }
            .p-score { margin-left: auto; font-size: 1.3rem; }
        }
    </style>

    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="margin:0; font-size: 2rem; color: var(--dark);">Class Leaderboard</h1>
        <p style="color: #637381; margin: 5px 0 0;">Ranking based on overall academic performance.</p>
    </div>

    <?php if (count($rankings) > 0): ?>
    <div class="podium-section">
        
        <?php if(isset($top3[1])): $p = $top3[1]; ?>
        <div class="podium-place rank-2">
            <div class="p-avatar">2</div>
            <div class="p-block"></div>
            <div>
                <div class="p-name">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <?php if($p['id'] == $student_id) echo '<br><span class="you-tag" style="margin:0;">YOU</span>'; ?>
                </div>
                <div class="p-score"><?php echo $p['avg']; ?>%</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($top3[0])): $p = $top3[0]; ?>
        <div class="podium-place rank-1">
            <i class='bx bxs-crown crown-icon'></i>
            <div class="p-avatar">1</div>
            <div class="p-block"></div>
            <div>
                <div class="p-name">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <?php if($p['id'] == $student_id) echo '<br><span class="you-tag" style="margin:0;">YOU</span>'; ?>
                </div>
                <div class="p-score"><?php echo $p['avg']; ?>%</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($top3[2])): $p = $top3[2]; ?>
        <div class="podium-place rank-3">
            <div class="p-avatar">3</div>
            <div class="p-block"></div>
            <div>
                <div class="p-name">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <?php if($p['id'] == $student_id) echo '<br><span class="you-tag" style="margin:0;">YOU</span>'; ?>
                </div>
                <div class="p-score"><?php echo $p['avg']; ?>%</div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if (!empty($rest)): ?>
    <div class="rank-list">
        <?php foreach($rest as $index => $r): ?>
        <div class="list-item">
            <div class="l-rank"><?php echo $index + 4; ?></div>
            
            <div class="l-info">
                <span class="l-name"><?php echo htmlspecialchars($r['name']); ?></span>
                
                <?php if($r['role'] && $r['role'] !== 'Citizen'): ?>
                    <span class="role-tag"><?php echo $r['role']; ?></span>
                <?php endif; ?>
                
                <?php if($r['id'] == $student_id): ?>
                    <span class="you-tag">YOU</span>
                <?php endif; ?>
            </div>

            <div class="l-score"><?php echo $r['avg']; ?>%</div>

            <?php if($is_leader && $r['id'] != $student_id): ?>
                <a href="results.php?view_student=<?php echo $r['id']; ?>" class="btn-view">View</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($rankings)): ?>
        <div class="empty-state" style="text-align:center; padding:60px; background:white; border-radius:16px; border:2px dashed #dfe3e8;">
            <i class='bx bx-bar-chart-alt-2' style="font-size:3rem; color:#dfe3e8; margin-bottom:10px;"></i>
            <h3 style="margin:0; color:var(--dark);">No Data Yet</h3>
            <p style="color:#919eab;">Rankings will appear once marks are recorded.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>