    <?php
    // admin/assign_teacher.php
    session_start();
    require '../config/db.php';

    // 1. SECURITY
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_GET['id'])) {
        header("Location: teachers.php"); exit;
    }

    $teacher_id = $_GET['id'];
    $message = "";
    $msg_type = "";

    // 2. FETCH TEACHER INFO
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $teacher_id]);
    $teacher = $stmt->fetch();

    if (!$teacher) { die("Teacher not found."); }

    // 3. HANDLE: SET CLASS TEACHER ROLE
    if (isset($_POST['set_class_teacher'])) {
        $class_id = $_POST['class_teacher_select']; 
        
        // Remove from previous classes
        $pdo->prepare("UPDATE classes SET class_teacher_id = NULL WHERE class_teacher_id = :tid")->execute(['tid' => $teacher_id]);
        
        // Assign new if selected
        if (!empty($class_id)) {
            $pdo->prepare("UPDATE classes SET class_teacher_id = :tid WHERE class_id = :cid")->execute(['tid' => $teacher_id, 'cid' => $class_id]);
            $message = "Teacher assigned as Class Teacher.";
            $msg_type = "success";
        } else {
            $message = "Teacher removed from Class Teacher role.";
            $msg_type = "warning";
        }
    }

    // 4. HANDLE: ADD SUBJECT ALLOCATION
    if (isset($_POST['add_allocation'])) {
        $c_id = $_POST['class_id'];
        $s_id = $_POST['subject_id'];
        
        // Prevent duplicates
        $check = $pdo->prepare("SELECT * FROM teacher_allocations WHERE teacher_id=:t AND class_id=:c AND subject_id=:s");
        $check->execute(['t'=>$teacher_id, 'c'=>$c_id, 's'=>$s_id]);
        
        if($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id) VALUES (:t, :c, :s)");
            $stmt->execute(['t'=>$teacher_id, 'c'=>$c_id, 's'=>$s_id]);
            $message = "Subject assigned successfully.";
            $msg_type = "success";
        } else {
            $message = "This subject is already assigned to this class for this teacher.";
            $msg_type = "error";
        }
    }

    // 5. HANDLE: REMOVE ALLOCATION
    if (isset($_GET['remove'])) {
        $alloc_id = $_GET['remove'];
        $pdo->prepare("DELETE FROM teacher_allocations WHERE allocation_id = :aid")->execute(['aid' => $alloc_id]);
        header("Location: assign_teacher.php?id=$teacher_id"); 
        exit;
    }

    // --- DATA FETCHING ---
    $classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();

    // Get Allocations
    $allocs = $pdo->prepare("SELECT ta.*, c.class_name, s.subject_name 
                            FROM teacher_allocations ta 
                            JOIN classes c ON ta.class_id = c.class_id 
                            JOIN subjects s ON ta.subject_id = s.subject_id 
                            WHERE ta.teacher_id = :tid
                            ORDER BY c.class_name ASC");
    $allocs->execute(['tid' => $teacher_id]);
    $current_allocations = $allocs->fetchAll();

    // Get Class Teacher Status
    $ct_check = $pdo->prepare("SELECT * FROM classes WHERE class_teacher_id = :tid");
    $ct_check->execute(['tid' => $teacher_id]);
    $is_class_teacher_of = $ct_check->fetch();

    // Get Recent Assignments Created by Teacher (New Feature)
    // Assuming table is 'class_assessments' or 'online_assessments'. Adjust if needed.
    $recent_work = [];
    try {
        $rw_stmt = $pdo->prepare("SELECT title, created_at, 'Assessment' as type FROM class_assessments WHERE teacher_id = :tid ORDER BY created_at DESC LIMIT 5");
        $rw_stmt->execute(['tid' => $teacher_id]);
        $recent_work = $rw_stmt->fetchAll();
    } catch (Exception $e) { /* Table might not exist yet */ }

    $page_title = "Edit Teacher";
    include '../includes/header.php';
    ?>

    <div class="container">

    <style>
            /* === PAGE STYLES === */
            :root { --primary: #FF6600; --dark: #212b36; --gray: #637381; --bg-card: #ffffff; }

            .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
            .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
            
            .btn-back { 
                text-decoration: none; color: var(--gray); font-weight: 600; 
                display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;
                background: white; padding: 8px 15px; border-radius: 8px; border: 1px solid #dfe3e8;
            }
            .btn-back:hover { background: #f4f6f8; color: var(--dark); }

            /* GRID LAYOUT */
            .assign-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }

            /* CARDS */
            .card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #dfe3e8; box-shadow: 0 4px 12px rgba(0,0,0,0.02); margin-bottom: 25px; }
            .card-title { margin: 0 0 20px 0; font-size: 1.1rem; color: var(--dark); font-weight: 700; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f4f6f8; padding-bottom: 15px; }

            /* Profile Section */
            .profile-box { text-align: center; }
            .p-avatar { width: 100px; height: 100px; background: var(--dark); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 15px; }
            .p-name { margin: 0; font-size: 1.4rem; color: var(--dark); }
            .p-email { color: var(--gray); font-size: 0.9rem; margin-top: 5px; }

            /* Class Teacher Status */
            .role-status { margin-top: 20px; padding: 15px; border-radius: 12px; text-align: left; }
            .rs-active { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
            .rs-inactive { background: #f8fafc; border: 1px solid #e2e8f0; color: var(--gray); }
            .rs-icon { font-size: 1.5rem; float: right; opacity: 0.5; }

            /* Forms */
            .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--gray); margin-bottom: 8px; }
            .form-select { width: 100%; padding: 10px; border: 1px solid #dfe3e8; border-radius: 8px; margin-bottom: 10px; font-family: inherit; }
            .btn-save { width: 100%; padding: 10px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; transition: 0.2s; }
            .btn-primary { background: var(--primary); color: white; }
            .btn-primary:hover { background: #e65c00; }
            .btn-outline { background: white; border: 1px solid #dfe3e8; color: var(--dark); }
            .btn-outline:hover { background: #f4f6f8; }

            /* --- SUBJECT LIST STYLES (IMPROVED) --- */
            .sub-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
            
            .sub-item { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 15px; border-radius: 12px; background: #fff; 
                border: 1px solid #f1f5f9; transition: 0.2s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            }
            .sub-item:hover { border-color: var(--primary); transform: translateX(5px); }
            
            .sub-info { display: flex; align-items: center; gap: 15px; }
            
            .sub-icon { 
                width: 40px; height: 40px; background: #fff7e6; color: var(--primary); 
                border-radius: 10px; display: flex; align-items: center; justify-content: center; 
                font-size: 1.4rem; flex-shrink: 0;
            }
            
            .sub-name { font-weight: 700; color: var(--dark); font-size: 0.95rem; margin-bottom: 3px; }
            
            .class-badge { 
                background: #f3e5f5; color: #9c27b0; padding: 3px 8px; 
                border-radius: 6px; font-size: 0.7rem; font-weight: 800; 
                text-transform: uppercase; letter-spacing: 0.5px;
            }
            
            .btn-remove { 
                color: #ff4d4f; background: #fff1f0; width: 32px; height: 32px; 
                border-radius: 8px; display: flex; align-items: center; justify-content: center; 
                text-decoration: none; transition: 0.2s; font-size: 1.1rem;
            }
            .btn-remove:hover { background: #ff4d4f; color: white; }

            /* Add Form */
            .add-box { background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px dashed #cbd5e1; display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }

            /* Work List */
            .work-item { padding: 10px 0; border-bottom: 1px solid #eee; font-size: 0.9rem; color: var(--dark); display: flex; justify-content: space-between; }
            .work-date { color: #999; font-size: 0.8rem; }

            /* Alerts */
            .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
            .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
            .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }

            @media (max-width: 900px) {
                .assign-grid { grid-template-columns: 1fr; }
                .add-box { grid-template-columns: 1fr; }
                .btn-save { margin-top: 10px; }
            }
        </style>

        <div class="page-header">
            <div>
                <h1 class="page-title">Manage Workload</h1>
                <p style="color:var(--gray); margin:5px 0 0;">Assign classes, subjects, and roles.</p>
            </div>
            <a href="teachers.php" class="btn-back"><i class='bx bx-arrow-back'></i> Back to List</a>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo ($msg_type == 'success') ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="assign-grid">
            
            <div class="left-col">
                <div class="card profile-box">
                    <div class="p-avatar"><?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?></div>
                    <h2 class="p-name"><?php echo htmlspecialchars($teacher['full_name']); ?></h2>
                    <div class="p-email"><?php echo htmlspecialchars($teacher['email'] ?: 'No Email Linked'); ?></div>
                </div>

                <div class="card">
                    <div class="card-title"><i class='bx bxs-graduation'></i> Class Teacher Role</div>
                    
                    <?php if($is_class_teacher_of): ?>
                        <div class="role-status rs-active">
                            <i class='bx bxs-badge-check rs-icon'></i>
                            <div style="font-size:0.8rem; text-transform:uppercase; font-weight:700; margin-bottom:5px;">Currently Assigned To</div>
                            <div style="font-size:1.2rem; font-weight:800;"><?php echo htmlspecialchars($is_class_teacher_of['class_name']); ?></div>
                        </div>
                        <form method="POST" style="margin-top:15px;">
                            <input type="hidden" name="class_teacher_select" value="">
                            <button type="submit" name="set_class_teacher" class="btn-save btn-outline" style="color:#ff4d4f; border-color:#ff4d4f;" onclick="return confirm('Remove class teacher role?');">
                                <i class='bx bx-x'></i> Resign from Class
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="role-status rs-inactive">
                            <div style="font-size:0.9rem;">Not currently a Class Teacher.</div>
                        </div>
                        <form method="POST" style="margin-top:15px;">
                            <label class="form-label">Assign to Class</label>
                            <select name="class_teacher_select" class="form-select" required>
                                <option value="" disabled selected>Select Class...</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="set_class_teacher" class="btn-save btn-primary">Assign Role</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-col">
                
                <div class="card">
                    <div class="card-title"><i class='bx bxs-book-add'></i> Assign New Subject</div>
                    <form method="POST" class="add-box">
                        <div>
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Target Class</label>
                            <select name="class_id" class="form-select" required>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_allocation" class="btn-save btn-primary" style="height: 42px; margin-bottom: 10px;">
                            <i class='bx bx-plus'></i> Add
                        </button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-title"><i class='bx bxs-layer'></i> Current Allocations</div>
                    
                    <?php if(empty($current_allocations)): ?>
                        <div style="text-align:center; padding:30px; color:#999;">
                            <i class='bx bx-book-open' style="font-size:2rem; opacity:0.3;"></i>
                            <p>No subjects assigned yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="sub-list">
                            <?php foreach($current_allocations as $row): ?>
                            <li class="sub-item">
                                <div class="sub-info">
                                    <div class="sub-icon"><i class='bx bxs-book'></i></div>
                                    <div>
                                        <div style="font-weight:700; color:var(--dark);"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                        <span class="class-badge"><?php echo htmlspecialchars($row['class_name']); ?></span>
                                    </div>
                                </div>
                                <a href="?id=<?php echo $teacher_id; ?>&remove=<?php echo $row['allocation_id']; ?>" class="btn-remove" onclick="return confirm('Remove this subject assignment?');">
                                    <i class='bx bx-trash'></i>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if(!empty($recent_work)): ?>
                <div class="card">
                    <div class="card-title"><i class='bx bx-history'></i> Recent Assignments Created</div>
                    <?php foreach($recent_work as $work): ?>
                        <div class="work-item">
                            <span><?php echo htmlspecialchars($work['title']); ?></span>
                            <span class="work-date"><?php echo date("d M", strtotime($work['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

    </body>
    </html>