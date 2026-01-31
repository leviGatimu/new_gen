<?php
// teacher/dashboard.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'] ?? 'Teacher';

// 2. FETCH TEACHING ALLOCATIONS
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
$allocations = $stmt->fetchAll();

// 3. FETCH CLASS TEACHER ROLE
$ct_sql = "SELECT c.* FROM classes c WHERE c.class_teacher_id = :tid";
$stmt = $pdo->prepare($ct_sql);
$stmt->execute(['tid' => $teacher_id]);
$my_class = $stmt->fetch();

// 4. FETCH TODAY'S SCHEDULE
$today_day = date('l'); 
$sched_sql = "SELECT t.*, c.class_name, s.subject_name, t.start_time, t.end_time 
              FROM timetable_entries t
              JOIN teacher_allocations ta ON t.class_id = ta.class_id AND t.subject_id = ta.subject_id
              JOIN classes c ON t.class_id = c.class_id
              JOIN subjects s ON t.subject_id = s.subject_id
              WHERE ta.teacher_id = :tid AND t.day_of_week = :day
              ORDER BY t.start_time ASC";
$stmt = $pdo->prepare($sched_sql);
$stmt->execute(['tid' => $teacher_id, 'day' => $today_day]);
$todays_classes = $stmt->fetchAll();

// 5. FETCH RECENT ASSESSMENTS
$ass_sql = "SELECT oa.*, c.class_name FROM online_assessments oa 
            JOIN classes c ON oa.class_id = c.class_id
            WHERE oa.teacher_id = :tid 
            ORDER BY oa.created_at DESC LIMIT 5";
$stmt = $pdo->prepare($ass_sql);
$stmt->execute(['tid' => $teacher_id]);
$recent_exams = $stmt->fetchAll();

// 6. CALCULATE TOTALS
$total_courses = count($allocations);
$total_students = 0;
foreach($allocations as $a) { $total_students += $a['student_count']; }

// INCLUDE HEADER
$page_title = "Teacher Dashboard";
include '../includes/header.php';
?>

<div class="container">
    <style>
        /* === PREMIUM DASHBOARD VARIABLES === */
        :root { 
            --primary: #FF6600; 
            --dark: #0f172a; 
            --gray: #64748b; 
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --border: #e2e8f0; 
            --radius: 20px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
            --shadow-hover: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        
        body { background-color: var(--bg-body); }

        /* LAYOUT WRAPPER */
        .dashboard-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 20px 80px 20px;
        }

        /* 1. HERO SECTION */
        .hero-card {
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            border-radius: var(--radius);
            padding: 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            box-shadow: var(--shadow-hover);
            position: relative;
            overflow: hidden;
        }
        .hero-card::after {
            content: ''; position: absolute; right: 0; bottom: 0; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%; pointer-events: none;
        }
        .hero-text h1 { margin: 0 0 10px 0; font-size: 2.2rem; font-weight: 800; letter-spacing: -0.5px; }
        .hero-text p { color: #cbd5e1; font-size: 1.1rem; margin: 0; }
        
        .date-badge {
            background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
            padding: 15px 30px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);
            text-align: center; min-width: 120px;
        }
        .date-badge .day { display: block; font-size: 1.8rem; font-weight: 800; line-height: 1; margin-bottom: 5px; }
        .date-badge .month { text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; opacity: 0.8; }

        /* 2. STATS GRID (4 Columns) */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); 
            gap: 30px; margin-bottom: 50px;
        }
        .stat-box {
            background: var(--bg-card); padding: 25px; border-radius: var(--radius);
            border: 1px solid var(--border); box-shadow: var(--shadow);
            display: flex; flex-direction: column; justify-content: center;
            transition: transform 0.2s ease;
        }
        .stat-box:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
        
        .stat-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .stat-icon { 
            width: 50px; height: 50px; border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; color: white;
        }
        .stat-val { font-size: 2rem; font-weight: 800; color: var(--dark); line-height: 1; }
        .stat-label { font-size: 0.9rem; color: var(--gray); font-weight: 600; margin-top: 5px; }

        /* SECTION HEADERS */
        .section-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;
        }
        .section-title { font-size: 1.4rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 12px; margin: 0; }
        
        /* 3. CLASSES GRID */
        .classes-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); 
            gap: 30px; margin-bottom: 60px;
        }
        .class-card {
            background: var(--bg-card); border-radius: var(--radius); 
            border: 1px solid var(--border); padding: 30px; 
            position: relative; display: flex; flex-direction: column;
            transition: all 0.3s ease; box-shadow: var(--shadow);
        }
        .class-card:hover { 
            border-color: var(--primary); transform: translateY(-5px); 
            box-shadow: var(--shadow-hover);
        }
        .class-stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 6px; border-radius: 6px 0 0 6px; }

        .class-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 0.85rem; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .class-body h3 { font-size: 1.3rem; font-weight: 800; color: var(--dark); margin: 0 0 10px 0; line-height: 1.3; }
        .class-body p { display: flex; align-items: center; gap: 8px; color: var(--gray); font-weight: 500; font-size: 1rem; margin: 0 0 30px 0; }

        .class-footer { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { 
            padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; 
            text-align: center; text-decoration: none; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-pri { background: var(--dark); color: white; }
        .btn-pri:hover { background: var(--primary); }
        .btn-sec { background: #f1f5f9; color: var(--dark); }
        .btn-sec:hover { background: #e2e8f0; }

        /* 4. SPLIT BOTTOM SECTION */
        .split-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; }
        
        .info-panel { 
            background: var(--bg-card); border-radius: var(--radius); 
            border: 1px solid var(--border); padding: 30px; 
            box-shadow: var(--shadow); height: 100%;
        }

        /* Schedule List */
        .sched-item { 
            display: flex; gap: 20px; padding: 20px 0; border-bottom: 1px solid #f1f5f9; align-items: center; 
        }
        .sched-item:last-child { border: none; padding-bottom: 0; }
        .sched-time { 
            min-width: 80px; text-align: right; font-weight: 800; font-size: 1rem; color: var(--dark); 
        }
        .sched-time span { display: block; font-size: 0.8rem; color: var(--gray); font-weight: 500; margin-top: 4px; }
        .sched-info h4 { margin: 0 0 5px 0; font-size: 1rem; color: var(--dark); }
        .sched-info p { margin: 0; font-size: 0.9rem; color: var(--gray); }

        /* Recent Exams List */
        .exam-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px; background: #f8fafc; border-radius: 12px; 
            border: 1px solid var(--border); margin-bottom: 15px;
        }
        .exam-meta h5 { margin: 0 0 5px 0; font-size: 0.95rem; color: var(--dark); }
        .exam-meta span { font-size: 0.8rem; color: var(--gray); }
        .grade-btn { 
            width: 35px; height: 35px; border-radius: 8px; background: white; border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center; color: var(--gray); transition: 0.2s;
        }
        .grade-btn:hover { border-color: var(--primary); color: var(--primary); }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .split-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .hero-card { flex-direction: column; text-align: left; align-items: flex-start; gap: 20px; }
        }
    </style>

    <div class="dashboard-wrapper">
        
        <div class="hero-card">
            <div class="hero-text">
                <h1>Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                <p>You have <strong><?php echo count($todays_classes); ?> classes</strong> on your schedule today.</p>
            </div>
            <div class="date-badge">
                <span class="day"><?php echo date('d'); ?></span>
                <span class="month"><?php echo date('M, Y'); ?></span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-top">
                    <div class="stat-icon" style="background: #3b82f6;"><i class='bx bxs-book-open'></i></div>
                </div>
                <div class="stat-val"><?php echo $total_courses; ?></div>
                <div class="stat-label">Allocated Subjects</div>
            </div>

            <div class="stat-box" style="<?php echo $my_class ? 'border-bottom: 4px solid #f59e0b;' : ''; ?>">
                <div class="stat-top">
                    <div class="stat-icon" style="background: #f59e0b;"><i class='bx bxs-star'></i></div>
                </div>
                <?php if($my_class): ?>
                    <div class="stat-val" style="font-size:1.5rem;"><?php echo htmlspecialchars($my_class['class_name']); ?></div>
                    <div class="stat-label" style="color:#d97706;">Class Teacher</div>
                <?php else: ?>
                    <div class="stat-val" style="color:#cbd5e1;">N/A</div>
                    <div class="stat-label">No Class Assigned</div>
                <?php endif; ?>
            </div>

            <div class="stat-box">
                <div class="stat-top">
                    <div class="stat-icon" style="background: #8b5cf6;"><i class='bx bxs-group'></i></div>
                </div>
                <div class="stat-val"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>

            <div class="stat-box">
                <div class="stat-top">
                    <div class="stat-icon" style="background: #10b981;"><i class='bx bxs-edit-alt'></i></div>
                </div>
                <div class="stat-val"><?php echo count($recent_exams); ?></div>
                <div class="stat-label">Assessments Created</div>
            </div>
        </div>

        <div class="section-header">
            <h2 class="section-title"><i class='bx bxs-grid-alt' style="color:var(--primary);"></i> Teaching Allocations</h2>
        </div>

        <?php if($total_courses > 0): ?>
            <div class="classes-grid">
                <?php foreach($allocations as $row): ?>
                <div class="class-card">
                    <div class="class-stripe" style="background: <?php echo $row['color_code'] ?? '#94a3b8'; ?>;"></div>
                    
                    <div class="class-header">
                        <span><?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?></span>
                        <span><i class='bx bxs-user'></i> <?php echo $row['student_count']; ?></span>
                    </div>

                    <div class="class-body">
                        <h3><?php echo htmlspecialchars($row['subject_name']); ?></h3>
                        <p><i class='bx bxs-school'></i> <?php echo htmlspecialchars($row['class_name']); ?></p>
                    </div>

                    <div class="class-footer">
                        <a href="assessments.php?class_id=<?php echo $row['class_id']; ?>" class="btn btn-pri">
                            <i class='bx bx-plus'></i> Exam
                        </a>
                        <a href="view_all_marks.php?class_id=<?php echo $row['class_id']; ?>&subject_id=<?php echo $row['subject_id']; ?>" class="btn btn-sec">
                            <i class='bx bx-spreadsheet'></i> Marks
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:60px; border:2px dashed #e2e8f0; border-radius:20px; margin-bottom:60px; color:var(--gray);">
                <i class='bx bx-ghost' style="font-size:3rem; margin-bottom:15px; opacity:0.5;"></i>
                <p>No subjects allocated yet.</p>
            </div>
        <?php endif; ?>

        <div class="split-grid">
            
            <div class="info-panel">
                <div class="section-header" style="border:none; padding:0; margin-bottom:20px;">
                    <h2 class="section-title"><i class='bx bx-time-five' style="color:var(--primary);"></i> Today's Schedule</h2>
                </div>

                <?php if(empty($todays_classes)): ?>
                    <div style="text-align:center; padding:40px 0; color:var(--gray);">
                        <i class='bx bx-coffee' style="font-size:2.5rem; margin-bottom:10px; opacity:0.5;"></i>
                        <p>No classes scheduled for today.</p>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach($todays_classes as $cls): ?>
                        <div class="sched-item">
                            <div class="sched-time">
                                <?php echo date("H:i", strtotime($cls['start_time'])); ?>
                                <span><?php echo date("H:i", strtotime($cls['end_time'])); ?></span>
                            </div>
                            <div class="sched-info">
                                <h4><?php echo htmlspecialchars($cls['subject_name']); ?></h4>
                                <p><?php echo htmlspecialchars($cls['class_name']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-panel">
                <div class="section-header" style="border:none; padding:0; margin-bottom:20px;">
                    <h2 class="section-title"><i class='bx bx-task' style="color:var(--primary);"></i> Recent Exams</h2>
                    <a href="assessments.php" style="color:var(--primary); font-weight:700; text-decoration:none;">View All</a>
                </div>

                <?php if(empty($recent_exams)): ?>
                    <div style="text-align:center; padding:40px 0; color:var(--gray);">No recent assessments.</div>
                <?php else: ?>
                    <div>
                        <?php foreach($recent_exams as $exam): ?>
                        <div class="exam-item">
                            <div class="exam-meta">
                                <h5><?php echo htmlspecialchars($exam['title']); ?></h5>
                                <span><?php echo htmlspecialchars($exam['class_name']); ?></span>
                            </div>
                            <a href="mark_online.php?id=<?php echo $exam['id']; ?>" class="grade-btn" title="Grade">
                                <i class='bx bxs-edit'></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top:20px;">
                    <a href="take_attendance.php" class="btn btn-sec" style="width:100%;">
                        <i class='bx bx-calendar-check'></i> Take Attendance
                    </a>
                </div>
            </div>

        </div>

    </div>
    
    <div style="height: 60px;"></div>
</div>

</body>
</html>