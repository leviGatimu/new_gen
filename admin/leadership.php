<?php
// admin/leadership.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Student Leadership";
$message = "";

// 2. HANDLE ROLE REMOVAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_role'])) {
    $s_id = $_POST['student_id'];
    $pdo->prepare("UPDATE students SET leadership_role = NULL WHERE student_id = ?")->execute([$s_id]);
    $message = "Role removed successfully.";
}

// 3. FETCH LEADERS
$sql = "SELECT u.full_name, u.email, s.student_id, s.admission_number, s.leadership_role, c.class_name 
        FROM students s 
        JOIN users u ON s.student_id = u.user_id 
        JOIN classes c ON s.class_id = c.class_id 
        WHERE s.leadership_role IS NOT NULL 
        ORDER BY FIELD(s.leadership_role, 'Head Boy', 'Head Girl', 'Prefect'), u.full_name ASC";
$leaders = $pdo->query($sql)->fetchAll();

// Separate Data for UI
$head_boy = null;
$head_girl = null;
$prefects = [];

foreach ($leaders as $l) {
    if ($l['leadership_role'] === 'Head Boy') $head_boy = $l;
    elseif ($l['leadership_role'] === 'Head Girl') $head_girl = $l;
    else $prefects[] = $l;
}

// 4. INCLUDE HEADER
include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === PAGE VARIABLES === */
        :root { --primary: #FF6600; --dark: #1e293b; --gray: #64748b; --bg-card: #ffffff; }

        /* Header Area */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 40px; flex-wrap: wrap; gap: 15px; 
        }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        
        .btn-assign { 
            text-decoration: none; color: white; background: var(--primary); 
            padding: 10px 20px; border-radius: 8px; font-weight: 700; 
            transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2);
        }
        .btn-assign:hover { background: #e65c00; transform: translateY(-2px); }

        /* HEADS SECTION (Gold Card) */
        .heads-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 30px; margin-bottom: 50px; 
        }
        
        .head-card { 
            background: white; border-radius: 20px; padding: 30px; text-align: center; 
            border: 1px solid #f1f5f9; position: relative; overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s;
        }
        .head-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        .head-card.gold { border-top: 6px solid #FFD700; }

        .role-badge { 
            background: #fffbea; color: #b45309; padding: 6px 15px; border-radius: 30px; 
            font-weight: 800; font-size: 0.8rem; text-transform: uppercase; 
            display: inline-block; margin-bottom: 20px; border: 1px solid #fde68a;
        }

        .head-avatar { 
            width: 110px; height: 110px; background: var(--dark); color: white; 
            font-size: 3rem; border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; margin: 0 auto 20px; font-weight: 800; 
            border: 5px solid #f8fafc; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .head-name { margin: 0; font-size: 1.5rem; color: var(--dark); font-weight: 800; }
        .head-class { color: var(--gray); font-weight: 600; margin-top: 5px; }

        .btn-remove { 
            background: #fff1f2; color: #e11d48; border: none; padding: 8px 16px; 
            border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; 
            margin-top: 20px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-remove:hover { background: #ffe4e6; }

        /* PREFECTS SECTION */
        .section-title { 
            font-size: 1.2rem; color: var(--dark); font-weight: 800; 
            margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; 
        }

        .prefects-grid { 
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); 
            gap: 25px; 
        }
        
        .prefect-card { 
            background: white; border-radius: 16px; padding: 20px; 
            border: 1px solid #f1f5f9; display: flex; flex-direction: column; 
            align-items: center; text-align: center; transition: 0.2s; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .prefect-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 10px 20px rgba(0,0,0,0.06); }

        .p-avatar { 
            width: 60px; height: 60px; background: #f1f5f9; color: var(--gray); 
            font-size: 1.5rem; border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; margin-bottom: 15px; font-weight: 700; 
        }
        
        .p-name { font-weight: 700; color: var(--dark); font-size: 1rem; }
        .p-class { font-size: 0.85rem; color: var(--gray); margin-top: 3px; font-family: monospace; }
        
        .p-badge { 
            background: #f3e8ff; color: #7e22ce; font-size: 0.7rem; font-weight: 700; 
            padding: 4px 10px; border-radius: 6px; margin-top: 10px; text-transform: uppercase; 
        }

        /* Empty State */
        .empty-box { 
            text-align: center; padding: 40px; color: var(--gray); 
            background: #f8fafc; border-radius: 16px; border: 2px dashed #e2e8f0; 
        }

        /* Alert */
        .alert { 
            background: #f0fdf4; color: #166534; padding: 15px; border-radius: 10px; 
            margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid #bbf7d0; 
        }

        /* Mobile */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-assign { width: 100%; justify-content: center; }
            .heads-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Student Leadership</h1>
            <p style="color:var(--gray); margin:5px 0 0;">Managing Head Boy, Head Girl, and School Prefects.</p>
        </div>
        <a href="students.php" class="btn-assign">
            <i class='bx bx-user-plus'></i> Assign New Leaders
        </a>
    </div>

    <?php if($message): ?>
        <div class="alert"><i class='bx bxs-check-circle'></i> <?php echo $message; ?></div>
    <?php endif; ?>

    <div class="heads-grid">
        
        <div class="head-card gold">
            <span class="role-badge"><i class='bx bxs-crown'></i> Head Boy</span>
            <?php if($head_boy): ?>
                <div class="head-avatar"><?php echo substr($head_boy['full_name'], 0, 1); ?></div>
                <h2 class="head-name"><?php echo htmlspecialchars($head_boy['full_name']); ?></h2>
                <div class="head-class"><?php echo htmlspecialchars($head_boy['class_name']); ?></div>
                
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $head_boy['student_id']; ?>">
                    <button type="submit" name="remove_role" class="btn-remove" onclick="return confirm('Remove Head Boy position?');">
                        <i class='bx bx-trash'></i> Remove Position
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-box" style="background:white; border:none;">
                    <i class='bx bx-user-x' style="font-size:3rem; opacity:0.3; margin-bottom:10px;"></i>
                    <p>No Head Boy Assigned</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="head-card gold">
            <span class="role-badge"><i class='bx bxs-crown'></i> Head Girl</span>
            <?php if($head_girl): ?>
                <div class="head-avatar"><?php echo substr($head_girl['full_name'], 0, 1); ?></div>
                <h2 class="head-name"><?php echo htmlspecialchars($head_girl['full_name']); ?></h2>
                <div class="head-class"><?php echo htmlspecialchars($head_girl['class_name']); ?></div>
                
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $head_girl['student_id']; ?>">
                    <button type="submit" name="remove_role" class="btn-remove" onclick="return confirm('Remove Head Girl position?');">
                        <i class='bx bx-trash'></i> Remove Position
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-box" style="background:white; border:none;">
                    <i class='bx bx-user-x' style="font-size:3rem; opacity:0.3; margin-bottom:10px;"></i>
                    <p>No Head Girl Assigned</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="section-title">School Prefects</div>
    
    <?php if(empty($prefects)): ?>
        <div class="empty-box">
            <i class='bx bx-group' style="font-size:3rem; opacity:0.3; margin-bottom:10px;"></i>
            <p>No prefects assigned yet.</p>
        </div>
    <?php else: ?>
        <div class="prefects-grid">
            <?php foreach($prefects as $p): ?>
                <div class="prefect-card">
                    <div class="p-avatar"><?php echo substr($p['full_name'], 0, 1); ?></div>
                    <div class="p-name"><?php echo htmlspecialchars($p['full_name']); ?></div>
                    <div class="p-class"><?php echo htmlspecialchars($p['class_name']); ?></div>
                    <div class="p-badge"><?php echo $p['leadership_role']; ?></div>
                    
                    <form method="POST" style="margin-top:auto; width:100%;">
                        <input type="hidden" name="student_id" value="<?php echo $p['student_id']; ?>">
                        <button type="submit" name="remove_role" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:0.8rem; margin-top:15px; font-weight:600;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                            Remove Role
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="height: 50px;"></div>
</div>

</body>
</html>