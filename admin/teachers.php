<?php
// admin/teachers.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Manage Teachers";

// 2. FETCH TEACHERS DATA (FIXED QUERY)
// We use a subquery to fetch subjects from 'teacher_allocations' specifically.
$sql = "
    SELECT 
        u.*, 
        c_main.class_name AS main_class,
        (
            SELECT GROUP_CONCAT(DISTINCT s.subject_name SEPARATOR '||') 
            FROM teacher_allocations ta 
            JOIN subjects s ON ta.subject_id = s.subject_id 
            WHERE ta.teacher_id = u.user_id
        ) as subjects_list
    FROM users u
    LEFT JOIN classes c_main ON u.user_id = c_main.class_teacher_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name ASC
";

$teachers = $pdo->query($sql)->fetchAll();

// 3. INCLUDE HEADER
include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === MODERN CARD STYLING === */
        :root { 
            --primary: #FF6600; 
            --dark: #1e293b; 
            --gray: #64748b; 
            --bg-card: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px; 
        }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        
        .btn-add { 
            background: var(--dark); color: white; padding: 12px 24px; 
            border-radius: 10px; text-decoration: none; font-weight: 700; 
            display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-add:hover { background: var(--primary); transform: translateY(-2px); }

        /* GRID */
        .teacher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 25px;
        }

        /* CARD */
        .t-card {
            background: var(--bg-card); border-radius: 20px; padding: 25px;
            border: 1px solid #f1f5f9; box-shadow: var(--shadow);
            transition: all 0.3s ease; position: relative;
            display: flex; flex-direction: column;
        }
        .t-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: #ffd8a8; }

        /* Card Header */
        .t-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .t-avatar { 
            width: 55px; height: 55px; background: #f8fafc; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.4rem; font-weight: 800; color: var(--gray); border: 2px solid #e2e8f0;
        }
        .t-info h3 { margin: 0; font-size: 1.1rem; color: var(--dark); font-weight: 700; }
        .t-email { margin: 2px 0 0; color: var(--gray); font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: #22c55e; }

        /* Role Badge */
        .role-badge {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white; padding: 5px 12px; border-radius: 30px;
            font-size: 0.75rem; font-weight: 700; display: inline-flex; 
            align-items: center; gap: 6px; margin-bottom: 15px; width: fit-content;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }

        /* SUBJECTS SECTION (NEW CSS) */
        .t-subjects { 
            background: #f8fafc; padding: 15px; border-radius: 12px; 
            border: 1px dashed #e2e8f0; flex: 1; margin-bottom: 15px;
        }
        .ts-title { 
            font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; 
            font-weight: 800; margin-bottom: 10px; display: block; letter-spacing: 0.5px;
        }
        .tag-container { display: flex; flex-wrap: wrap; gap: 8px; }
        
        /* The "Good CSS" for Subjects */
        .sub-tag { 
            background: #f3e5f5; border: 1px solid #e9d5ff; color: #7e22ce; 
            padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;
            display: inline-block;
        }
        .sub-more {
            background: #e2e8f0; color: #64748b; padding: 4px 8px; 
            border-radius: 6px; font-size: 0.75rem; font-weight: 600;
        }
        .no-sub { color: #cbd5e1; font-style: italic; font-size: 0.85rem; }

        /* Footer */
        .t-footer { 
            margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9; 
            display: flex; justify-content: flex-end; align-items: center; 
        }
        .btn-manage { 
            color: #c2410c; background: #fff7ed; text-decoration: none; font-weight: 700; 
            font-size: 0.9rem; display: flex; align-items: center; gap: 5px; 
            padding: 8px 16px; border-radius: 8px; transition: 0.2s; 
        }
        .btn-manage:hover { background: #ffedd5; color: #ea580c; }

        @media (max-width: 768px) {
            .teacher-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Manage Teachers</h1>
            <p style="color:var(--gray); margin:5px 0 0;">Overview of faculty roles and assignments.</p>
        </div>
        <a href="add_teacher.php" class="btn-add">
            <i class='bx bx-user-plus'></i> Add Teacher
        </a>
    </div>

    <div class="teacher-grid">
        
        <?php if(empty($teachers)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding:60px; color:var(--gray);">
                <i class='bx bx-user-x' style="font-size:4rem; opacity:0.3; margin-bottom:15px;"></i>
                <p>No teachers found.</p>
            </div>
        <?php else: ?>
            
            <?php foreach($teachers as $t): 
                $isActive = !empty($t['email']);
                // Explode the subject list string into an array
                $subjects = !empty($t['subjects_list']) ? explode('||', $t['subjects_list']) : [];
            ?>
            <div class="t-card">
                
                <div class="t-header">
                    <div class="t-avatar">
                        <?php echo strtoupper(substr($t['full_name'], 0, 1)); ?>
                    </div>
                    <div class="t-info">
                        <h3><?php echo htmlspecialchars($t['full_name']); ?></h3>
                        <div class="t-email">
                            <span class="status-dot" style="background: <?php echo $isActive ? '#22c55e' : '#f59e0b'; ?>"></span> 
                            <?php echo $isActive ? htmlspecialchars($t['email']) : 'Pending Setup'; ?>
                        </div>
                    </div>
                </div>

                <?php if(!empty($t['main_class'])): ?>
                    <div class="role-badge">
                        <i class='bx bxs-graduation'></i> Class Teacher: <?php echo htmlspecialchars($t['main_class']); ?>
                    </div>
                <?php endif; ?>

                <div class="t-subjects">
                    <span class="ts-title">SUBJECTS ASSIGNED</span>
                    <div class="tag-container">
                        <?php if(!empty($subjects)): ?>
                            <?php 
                                $limit = 3; // Show first 3
                                foreach(array_slice($subjects, 0, $limit) as $sub) {
                                    echo '<span class="sub-tag">'.htmlspecialchars($sub).'</span>';
                                }
                                if(count($subjects) > $limit) {
                                    echo '<span class="sub-more">+'.(count($subjects)-$limit).' more</span>';
                                }
                            ?>
                        <?php else: ?>
                            <span class="no-sub">No subjects assigned yet.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="t-footer">
                    <a href="assign_teacher.php?id=<?php echo $t['user_id']; ?>" class="btn-manage">
                        Manage <i class='bx bx-chevron-right'></i>
                    </a>
                </div>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
    
    <div style="height: 50px;"></div>
</div>

</body>
</html>