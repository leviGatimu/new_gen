<?php
// teacher/my_classes.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];

// 2. FETCH CLASSES
$sql = "SELECT ta.*, c.class_name, s.subject_name, cat.category_name, cat.color_code,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id) as student_count
        FROM teacher_allocations ta 
        JOIN classes c ON ta.class_id = c.class_id 
        JOIN subjects s ON ta.subject_id = s.subject_id
        LEFT JOIN class_categories cat ON c.category_id = cat.category_id
        WHERE ta.teacher_id = :tid 
        ORDER BY c.class_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['tid' => $teacher_id]);
$my_classes = $stmt->fetchAll();

$total_classes = count($my_classes);

// INCLUDE HEADER
$page_title = "My Classes";
include '../includes/header.php';
?>

<div class="container">
    <style>
        /* === PREMIUM STYLES === */
        :root { 
            --primary: #FF6600; 
            --dark: #0f172a; 
            --gray: #64748b; 
            --bg: #f8fafc; 
            --card: #ffffff; 
            --border: #e2e8f0;
            --radius: 20px;
        }
        
        body { background-color: var(--bg); }
        .wrapper { max-width: 1200px; margin: 0 auto; padding-bottom: 80px; }

        /* 1. HEADER SECTION */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 40px;
        }
        .title-box h1 { margin: 0; font-size: 2rem; font-weight: 800; color: var(--dark); letter-spacing: -1px; }
        .title-box p { margin: 5px 0 0; color: var(--gray); font-size: 1rem; }

        /* 2. SEARCH BAR */
        .search-container { position: relative; width: 100%; max-width: 400px; }
        .search-input {
            width: 100%; padding: 15px 20px 15px 50px; 
            border: 1px solid var(--border); border-radius: 14px;
            background: var(--card); font-size: 0.95rem; outline: none;
            transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255,102,0,0.1); }
        .search-icon {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            font-size: 1.4rem; color: #94a3b8;
        }

        /* 3. GRID LAYOUT */
        .classes-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 30px;
        }

        /* 4. CLASS CARD */
        .class-card {
            background: var(--card); border-radius: var(--radius);
            border: 1px solid var(--border); padding: 30px;
            position: relative; display: flex; flex-direction: column;
            transition: all 0.3s ease; overflow: hidden;
        }
        .class-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            border-color: var(--primary);
        }
        
        .card-stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 6px; }

        .card-meta { 
            display: flex; justify-content: space-between; margin-bottom: 20px;
            font-size: 0.8rem; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .card-body { margin-bottom: 30px; }
        .subject-name { font-size: 1.3rem; font-weight: 800; color: var(--dark); margin: 0 0 8px 0; line-height: 1.3; }
        .class-name { 
            font-size: 1rem; color: var(--gray); font-weight: 500; 
            display: flex; align-items: center; gap: 8px; 
        }

        /* BUTTONS */
        .card-footer { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.9rem;
            text-decoration: none; transition: 0.2s;
        }
        .btn-pri { background: var(--dark); color: white; border: 1px solid var(--dark); }
        .btn-pri:hover { background: var(--primary); border-color: var(--primary); }
        
        .btn-sec { background: white; border: 1px solid var(--border); color: var(--dark); }
        .btn-sec:hover { background: #f8fafc; border-color: var(--dark); }

        /* EMPTY STATE */
        .empty-state {
            grid-column: 1 / -1; text-align: center; padding: 80px; 
            background: white; border-radius: var(--radius); border: 2px dashed var(--border);
        }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .search-container { max-width: 100%; }
            .classes-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="wrapper">
        
        <div class="page-header">
            <div class="title-box">
                <h1>My Classes</h1>
                <p>Manage your <strong><?php echo $total_classes; ?></strong> active subjects.</p>
            </div>
            
            <div class="search-container">
                <i class='bx bx-search search-icon'></i>
                <input type="text" id="classSearch" class="search-input" onkeyup="filterClasses()" placeholder="Search subject or class...">
            </div>
        </div>

        <?php if($total_classes > 0): ?>
            <div class="classes-grid">
                <?php foreach($my_classes as $row): 
                    $color = $row['color_code'] ?? '#94a3b8'; 
                ?>
                <div class="class-card">
                    <div class="card-stripe" style="background: <?php echo $color; ?>;"></div>

                    <div class="card-meta">
                        <span><?php echo htmlspecialchars($row['category_name'] ?? 'Subject'); ?></span>
                        <span><i class='bx bxs-user'></i> <?php echo $row['student_count']; ?></span>
                    </div>

                    <div class="card-body">
                        <h3 class="subject-name search-target-sub"><?php echo htmlspecialchars($row['subject_name']); ?></h3>
                        <div class="class-name search-target-cls">
                            <i class='bx bxs-school'></i> <?php echo htmlspecialchars($row['class_name']); ?>
                        </div>
                    </div>

                    <div class="card-footer">
                        <a href="assessments.php?class_id=<?php echo $row['class_id']; ?>&subject_id=<?php echo $row['subject_id']; ?>" class="btn btn-pri">
                            <i class='bx bxs-edit'></i> Enter Grades
                        </a>
                        
                        <a href="my_students.php?class_id=<?php echo $row['class_id']; ?>" class="btn btn-sec">
                            <i class='bx bxs-group'></i> Students
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-ghost' style="font-size:4rem; color:#cbd5e1; margin-bottom:20px;"></i>
                <h3 style="margin:0; color:var(--dark);">No Classes Assigned</h3>
                <p style="color:var(--gray);">Contact the admin to get your teaching allocations.</p>
            </div>
        <?php endif; ?>

    </div>
    
    <div style="height: 60px;"></div>
</div>

<script>
    function filterClasses() {
        let input = document.getElementById('classSearch').value.toLowerCase();
        let cards = document.getElementsByClassName('class-card');

        for (let i = 0; i < cards.length; i++) {
            let subject = cards[i].querySelector('.search-target-sub').innerText.toLowerCase();
            let className = cards[i].querySelector('.search-target-cls').innerText.toLowerCase();
            
            if (subject.includes(input) || className.includes(input)) {
                cards[i].style.display = "flex";
            } else {
                cards[i].style.display = "none";
            }
        }
    }
</script>

</body>
</html>