<?php
// admin/classes.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Academics Manager";
$success = ''; 
$error = '';

// --- ACTIONS: CATEGORIES ---
if (isset($_POST['add_category'])) {
    $name = trim($_POST['cat_name']);
    $color = $_POST['cat_color'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO class_categories (category_name, color_code) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$color]);
        $success = "Category created.";
    }
}
if (isset($_GET['del_cat'])) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE category_id = ?");
    $count->execute([$_GET['del_cat']]);
    if($count->fetchColumn() > 0) {
        $error = "Cannot delete: Classes exist in this category.";
    } else {
        $pdo->prepare("DELETE FROM class_categories WHERE category_id = ?")->execute([$_GET['del_cat']]);
        $success = "Category deleted.";
    }
}

// --- ACTIONS: CLASSES ---
if (isset($_POST['add_class'])) {
    $name = trim($_POST['class_name']);
    $cat_id = $_POST['category_id'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO classes (class_name, category_id) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$cat_id]);
        $success = "Class added.";
    }
}
if (isset($_GET['del_class'])) {
    $pdo->prepare("DELETE FROM classes WHERE class_id = ?")->execute([$_GET['del_class']]);
    header("Location: classes.php"); exit;
}

// --- ACTIONS: CURRICULUM ---
if (isset($_POST['assign_subject'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    
    // Check duplication
    $check = $pdo->prepare("SELECT * FROM class_subjects WHERE class_id=:c AND subject_id=:s");
    $check->execute(['c'=>$class_id, 's'=>$subject_id]);
    
    if($check->rowCount() == 0) {
        $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (:c, :s)")
            ->execute(['c'=>$class_id, 's'=>$subject_id]);
        $success = "Subject assigned to class.";
    }
}
if (isset($_POST['remove_assigned_subject'])) {
    $link_id = $_POST['link_id'];
    $pdo->prepare("DELETE FROM class_subjects WHERE id = :id")->execute(['id'=>$link_id]);
    $success = "Subject removed from class.";
}

// --- ACTIONS: MASTER SUBJECTS ---
if (isset($_POST['add_subject'])) {
    $name = trim($_POST['subject_name']);
    $cat_id = $_POST['category_id'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO subjects (subject_name, category_id) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$cat_id]);
        $success = "Master subject created.";
    }
}
if (isset($_GET['del_subject'])) {
    $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?")->execute([$_GET['del_subject']]);
    header("Location: classes.php"); exit;
}

// FETCH DATA
$categories = $pdo->query("SELECT * FROM class_categories")->fetchAll();
$sub_sql = "SELECT s.*, c.category_name, c.color_code FROM subjects s LEFT JOIN class_categories c ON s.category_id = c.category_id ORDER BY s.subject_name ASC";
$subjects = $pdo->query($sub_sql)->fetchAll();
$class_sql = "SELECT c.*, cat.category_name, cat.color_code FROM classes c LEFT JOIN class_categories cat ON c.category_id = cat.category_id ORDER BY c.class_name ASC";
$classes = $pdo->query($class_sql)->fetchAll();

// Get assignments for JS
$curr_sql = "SELECT cs.id as link_id, cs.class_id, s.subject_id, s.subject_name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.subject_id";
$curr_links = $pdo->query($curr_sql)->fetchAll();
$class_assignments = [];
foreach($curr_links as $link) { $class_assignments[$link['class_id']][] = $link; }

include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === PAGE STYLES === */
        :root { --primary: #FF6600; --dark: #1e293b; --gray: #64748b; --bg-card: #ffffff; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }

        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* LAYOUT */
        .admin-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }

        /* CARDS */
        .card { 
            background: white; border-radius: 16px; padding: 25px; 
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            height: fit-content;
        }
        .card-header { 
            font-size: 1.1rem; font-weight: 700; color: var(--dark); 
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
        }

        /* TABS */
        .tab-container { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .tab-btn { 
            padding: 8px 16px; border: 1px solid #e2e8f0; background: white; 
            border-radius: 20px; cursor: pointer; font-weight: 600; color: var(--gray); 
            transition: 0.2s; white-space: nowrap; font-size: 0.85rem;
        }
        .tab-btn.active { background: var(--dark); color: white; border-color: var(--dark); }

        /* CLASS ITEMS */
        .class-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 15px; background: white; border-radius: 12px; margin-bottom: 12px; 
            border: 1px solid #f1f5f9; border-left-width: 5px; transition: 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .class-item:hover { transform: translateX(5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        .c-name { font-weight: 700; font-size: 1rem; color: var(--dark); }
        .c-cat { font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; color: white; font-weight: 700; text-transform: uppercase; margin-left: 10px; }

        .btn-curr { 
            background: #f8fafc; border: 1px solid #e2e8f0; color: var(--dark); 
            padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-curr:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .btn-icon { color: #94a3b8; font-size: 1.2rem; margin-left: 10px; cursor: pointer; transition: 0.2s; }
        .btn-icon:hover { color: #ef4444; }

        /* FORMS */
        .form-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .form-input { flex: 1; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; }
        .form-select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; background: white; }
        .btn-submit { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: var(--primary); }

        /* MASTER SUBJECTS LIST */
        .sub-list { max-height: 400px; overflow-y: auto; }
        .sub-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 10px 0; border-bottom: 1px solid #f8fafc; font-size: 0.9rem;
        }
        .sub-cat { font-size: 0.7rem; font-weight: 700; margin-left: 8px; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 700px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: zoomIn 0.2s ease; }
        @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .curr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .curr-col h4 { margin: 0 0 15px 0; font-size: 0.9rem; text-transform: uppercase; color: var(--gray); border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        
        .assigned-tag { 
            display: flex; justify-content: space-between; align-items: center; 
            background: #f0f9ff; border: 1px solid #bae6fd; color: #0284c7; 
            padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;
        }
        .assigned-tag button { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem; display: flex; }

        @media (max-width: 900px) {
            .admin-grid { grid-template-columns: 1fr; }
            .curr-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="page-header">
        <h1 class="page-title">Academics Manager</h1>
        <button onclick="document.getElementById('catModal').style.display='flex'" class="btn-submit" style="background:white; color:var(--dark); border:1px solid #e2e8f0;">
            <i class='bx bx-category'></i> Categories
        </button>
    </div>

    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

    <div class="admin-grid">
        
        <div class="card">
            <div class="card-header">
                <span>Classes & Curriculum</span>
            </div>

            <form method="POST" class="form-row">
                <input type="text" name="class_name" class="form-input" placeholder="Class Name (e.g. Grade 1)" required>
                <select name="category_id" class="form-select" required>
                    <option value="" disabled selected>Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_class" class="btn-submit">Add</button>
            </form>

            <div class="tab-container">
                <button class="tab-btn active" onclick="filterClasses('all', this)">All</button>
                <?php foreach($categories as $cat): ?>
                    <button class="tab-btn" onclick="filterClasses(<?php echo $cat['category_id']; ?>, this)">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="classList">
                <?php foreach($classes as $c): ?>
                    <?php $count = isset($class_assignments[$c['class_id']]) ? count($class_assignments[$c['class_id']]) : 0; ?>
                    <div class="class-item" data-category="<?php echo $c['category_id']; ?>" style="border-left-color: <?php echo $c['color_code']; ?>;">
                        <div>
                            <span class="c-name"><?php echo htmlspecialchars($c['class_name']); ?></span>
                            <span class="c-cat" style="background: <?php echo $c['color_code']; ?>;">
                                <?php echo htmlspecialchars($c['category_name']); ?>
                            </span>
                        </div>
                        <div style="display:flex; align-items:center;">
                            <button class="btn-curr" onclick='openCurriculum(<?php echo $c['class_id']; ?>, "<?php echo addslashes($c['class_name']); ?>", <?php echo $c['category_id']; ?>, <?php echo json_encode($class_assignments[$c['class_id']] ?? []); ?>)'>
                                <i class='bx bx-book-open'></i> <?php echo $count; ?> Subjects
                            </button>
                            <a href="classes.php?del_class=<?php echo $c['class_id']; ?>" onclick="return confirm('Delete class?')" class="btn-icon">
                                <i class='bx bx-trash'></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>Master Subjects</span>
            </div>
            
            <p style="font-size:0.85rem; color:var(--gray); margin-top:0;">Create subjects here, then assign them to classes.</p>

            <form method="POST" style="margin-bottom:20px;">
                <input type="text" name="subject_name" class="form-input" placeholder="Subject Name" required style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                <select name="category_id" class="form-select" required style="width:100%; margin-bottom:10px;">
                    <option value="" disabled selected>Select Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_subject" class="btn-submit" style="width:100%;">Create Subject</button>
            </form>

            <div class="sub-list">
                <?php foreach($subjects as $s): ?>
                    <div class="sub-item">
                        <div>
                            <strong><?php echo htmlspecialchars($s['subject_name']); ?></strong>
                            <span class="sub-cat" style="color:<?php echo $s['color_code']; ?>;">
                                <?php echo htmlspecialchars($s['category_name']); ?>
                            </span>
                        </div>
                        <a href="classes.php?del_subject=<?php echo $s['subject_id']; ?>" class="btn-icon" onclick="return confirm('Delete this subject?')">
                            <i class='bx bx-x'></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<div id="currModal" class="modal">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px;">
            <h2 id="currTitle" style="margin:0; font-size:1.4rem; color:var(--dark);"></h2>
            <button onclick="document.getElementById('currModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <div class="curr-grid">
            <div class="curr-col">
                <h4>Currently Assigned</h4>
                <div id="assignedList" style="max-height: 300px; overflow-y: auto;"></div>
                <div id="emptyAssigned" style="color:#94a3b8; font-style:italic; display:none;">No subjects assigned yet.</div>
            </div>

            <div class="curr-col">
                <h4>Add Available Subject</h4>
                <p style="font-size:0.8rem; color:var(--gray); margin-top:-10px; margin-bottom:10px;">Only showing subjects in this category.</p>
                <form method="POST">
                    <input type="hidden" name="class_id" id="modalClassId">
                    <select name="subject_id" id="availableSelect" size="10" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding:10px; outline:none; background:#f8fafc;"></select>
                    <button type="submit" name="assign_subject" class="btn-submit" style="width: 100%; margin-top:10px;">Assign to Class</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="catModal" class="modal">
    <div class="modal-box" style="max-width:400px;">
        <h3 style="margin-top:0;">Manage Categories</h3>
        <form method="POST">
            <label class="form-label">Category Name</label>
            <input type="text" name="cat_name" class="form-input" required style="width:100%; box-sizing:border-box; margin-bottom:10px;">
            <label class="form-label">Color Code</label>
            <input type="color" name="cat_color" value="#FF6600" style="width:100%; height:40px; border:none; cursor:pointer; margin-bottom:15px;">
            <button type="submit" name="add_category" class="btn-submit" style="width:100%;">Create Category</button>
        </form>
        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:10px;">
            <?php foreach($categories as $cat): ?>
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f9f9f9;">
                    <span><i class='bx bxs-circle' style="color:<?php echo $cat['color_code']; ?>;"></i> <?php echo htmlspecialchars($cat['category_name']); ?></span>
                    <a href="classes.php?del_cat=<?php echo $cat['category_id']; ?>" style="color:#ef4444;"><i class='bx bx-trash'></i></a>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="document.getElementById('catModal').style.display='none'" style="width:100%; padding:10px; background:#f1f5f9; border:none; border-radius:8px; margin-top:15px; cursor:pointer; font-weight:600;">Close</button>
    </div>
</div>

<script>
    // Tab Filtering
    function filterClasses(catId, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.class-item').forEach(item => {
            if (catId === 'all' || item.dataset.category == catId) item.style.display = 'flex';
            else item.style.display = 'none';
        });
    }

    const allSubjects = <?php echo json_encode($subjects); ?>;

    // Curriculum Modal Logic
    function openCurriculum(classId, className, classCatId, assignedSubjects) {
        document.getElementById('modalClassId').value = classId;
        document.getElementById('currTitle').innerText = className + " Curriculum";
        document.getElementById('currModal').style.display = 'flex';
        
        // Populate Assigned List
        const assignedContainer = document.getElementById('assignedList');
        assignedContainer.innerHTML = ''; 
        const assignedIds = [];
        
        if (assignedSubjects.length === 0) {
            document.getElementById('emptyAssigned').style.display = 'block';
        } else {
            document.getElementById('emptyAssigned').style.display = 'none';
            assignedSubjects.forEach(sub => {
                assignedIds.push(parseInt(sub.subject_id));
                const div = document.createElement('div');
                div.className = 'assigned-tag';
                div.innerHTML = `<span>${sub.subject_name}</span>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="link_id" value="${sub.link_id}">
                        <button type="submit" name="remove_assigned_subject"><i class='bx bx-x'></i></button>
                    </form>`;
                assignedContainer.appendChild(div);
            });
        }

        // Populate Available List (Category Restricted)
        const availableSelect = document.getElementById('availableSelect');
        availableSelect.innerHTML = ''; 
        
        let availableCount = 0;
        allSubjects.forEach(s => {
            // LOGIC: Only show subjects matching class category AND not already assigned
            if (s.category_id == classCatId && !assignedIds.includes(parseInt(s.subject_id))) {
                const option = document.createElement('option');
                option.value = s.subject_id;
                option.innerText = s.subject_name;
                availableSelect.appendChild(option);
                availableCount++;
            }
        });

        if(availableCount === 0) {
            const option = document.createElement('option');
            option.disabled = true;
            option.innerText = "No subjects available in this category.";
            availableSelect.appendChild(option);
        }
    }

    // Close Modal on Click Outside
    window.onclick = function(e) { 
        if (e.target.className === 'modal') e.target.style.display = 'none'; 
    }
</script>

</body>
</html>