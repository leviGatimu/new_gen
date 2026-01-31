<?php
// student/timetable.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];

// 1. Get Class
$stmt = $pdo->prepare("SELECT s.class_id, c.class_name FROM students s JOIN classes c ON s.class_id = c.class_id WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$my_class = $stmt->fetch();

if (!$my_class) {
    die("You are not assigned to any class yet.");
}

// 2. Fetch Timetable
$stmt = $pdo->prepare("SELECT t.*, s.subject_name FROM timetable_entries t LEFT JOIN subjects s ON t.subject_id = s.subject_id WHERE t.class_id = ?");
$stmt->execute([$my_class['class_id']]);
$timetable_data = [];
while($row = $stmt->fetch()) {
    $key = $row['day_of_week'] . '_' . substr($row['start_time'], 0, 5);
    $timetable_data[$key] = $row;
}

// 3. Fetch Homework (SAFELY)
$homeworks = [];
try {
    // Check if table exists first (or just try/catch the query)
    $hw_stmt = $pdo->prepare("SELECT subject_id, title, due_date FROM assignments WHERE class_id = ? AND due_date >= CURDATE()");
    $hw_stmt->execute([$my_class['class_id']]);
    while($hw = $hw_stmt->fetch()) {
        $homeworks[$hw['subject_id']][] = $hw;
    }
} catch (Exception $e) {
    // Table 'assignments' probably doesn't exist yet. 
    // We ignore this error so the timetable still loads.
}

// 4. Define Time Periods (Matches Admin)
$periods = [
    ['07:30', '09:00', 'Self Study / Devotion'],
    ['09:00', '09:50', ''],
    ['09:50', '10:40', ''],
    ['10:40', '11:00', 'BREAK'],
    ['11:00', '11:50', ''],
    ['11:50', '12:40', ''],
    ['12:40', '13:40', 'LUNCH'],
    ['13:40', '14:30', ''],
    ['14:30', '15:20', ''],
    ['15:20', '15:40', 'BREAK'],
    ['15:40', '16:30', ''],
    ['16:30', '17:20', '']
];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

include '../includes/header.php';
?>

<div class="container">
    <style>
        /* EXCEL STYLE CSS */
        :root { --border-color: #000; }
        
        .page-header { text-align: center; margin-bottom: 25px; }
        .class-title { margin: 0; font-size: 2rem; color: var(--dark); font-weight: 800; }
        .sub-text { color: #666; margin: 5px 0 0; }

        /* The Grid */
        .grid-container { overflow-x: auto; background: white; border: 2px solid #000; box-shadow: 5px 5px 0 rgba(0,0,0,0.1); }
        
        table.excel-table { 
            width: 100%; border-collapse: collapse; min-width: 800px; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        /* Headers */
        .excel-table th { 
            background: #e0f2f1; border: 1px solid #000; padding: 12px; 
            text-align: center; font-weight: 800; text-transform: uppercase; font-size: 0.9rem;
        }
        .excel-table th.time-head { background: #d1d5db; width: 120px; }

        /* Rows */
        .excel-table td { 
            border: 1px solid #000; padding: 0; vertical-align: middle; 
            height: 60px; font-size: 0.85rem; position: relative;
        }
        
        /* Time Cell */
        .time-cell { 
            background: #f3f4f6; font-weight: 700; text-align: center; 
            font-size: 0.8rem; padding: 5px !important; color: #4b5563;
        }

        /* Class Block (Clickable) */
        .class-block { 
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; 
            text-align: center; font-weight: 700; color: #000; cursor: pointer; 
            transition: 0.2s; padding: 5px; box-sizing: border-box;
        }
        .class-block:hover { filter: brightness(0.95); box-shadow: inset 0 0 0 3px rgba(0,0,0,0.2); }

        /* Special Rows */
        .break-row td { background: #fff9c4; font-weight: 800; text-align: center; letter-spacing: 2px; color: #854d0e; height: 40px; }
        .lunch-row td { background: #ccfbf1; font-weight: 800; text-align: center; letter-spacing: 2px; color: #0f766e; height: 50px; }

        /* Modal Styles */
        .modal-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;
            backdrop-filter: blur(3px);
        }
        .modal-box { 
            background: white; width: 400px; border-radius: 12px; overflow: hidden; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { margin: 0; font-size: 1.2rem; color: var(--dark); font-weight: 800; }
        .close-btn { cursor: pointer; font-size: 1.5rem; color: #94a3b8; transition: 0.2s; }
        .close-btn:hover { color: #ef4444; }

        .modal-body { padding: 25px; }
        .info-row { display: flex; margin-bottom: 15px; align-items: center; }
        .info-icon { width: 40px; height: 40px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--primary); margin-right: 15px; }
        .info-content div:first-child { font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .info-content div:last-child { font-weight: 600; color: var(--dark); }

        .hw-section { margin-top: 20px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; }
        .hw-title { font-size: 0.8rem; font-weight: 800; color: #991b1b; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
        .hw-item { font-size: 0.9rem; color: #7f1d1d; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .hw-item:last-child { border: none; margin: 0; padding: 0; }
    </style>

    <div class="page-header">
        <h1 class="class-title"><?php echo htmlspecialchars($my_class['class_name']); ?></h1>
        <p class="sub-text">Academic Schedule</p>
    </div>

    <div class="grid-container">
        <table class="excel-table">
            <thead>
                <tr>
                    <th class="time-head">Time</th>
                    <?php foreach($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($periods as $p): 
                    $p_start = $p[0]; $p_end = $p[1]; $p_label = $p[2];
                    $is_break = ($p_label === 'BREAK');
                    $is_lunch = ($p_label === 'LUNCH');
                    $row_class = $is_break ? 'break-row' : ($is_lunch ? 'lunch-row' : '');
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="time-cell">
                        <?php echo $p_start . ' - ' . $p_end; ?>
                    </td>

                    <?php if($p_label && ($is_break || $is_lunch || $p_label == 'Self Study / Devotion')): ?>
                        <td colspan="5" style="vertical-align: middle;"><?php echo $p_label; ?></td>
                    <?php else: ?>
                        <?php foreach($days as $d): 
                            $key = $d . '_' . $p_start;
                            $entry = $timetable_data[$key] ?? null;
                            $bg = $entry ? $entry['color_code'] : '';
                            $text = $entry ? ($entry['custom_activity'] ?: $entry['subject_name']) : '';
                            
                            // Calculate duration for modal
                            $mins = (strtotime($p_end) - strtotime($p_start)) / 60;
                            
                            // Check for homework
                            $sub_id = $entry['subject_id'] ?? 0;
                            $has_hw = isset($homeworks[$sub_id]) ? json_encode($homeworks[$sub_id]) : '[]';
                        ?>
                            <td>
                                <?php if($text): ?>
                                    <div class="class-block" style="background:<?php echo $bg; ?>;" 
                                         onclick='openModal("<?php echo addslashes($text); ?>", "<?php echo $mins; ?> mins", <?php echo $has_hw; ?>)'>
                                        <?php echo $text; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="infoModal" class="modal-overlay" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="mTitle">Subject Name</h3>
            <div class="close-btn" onclick="closeModalBtn()">&times;</div>
        </div>
        <div class="modal-body">
            <div class="info-row">
                <div class="info-icon"><i class='bx bx-time-five'></i></div>
                <div class="info-content">
                    <div>Duration</div>
                    <div id="mDuration">50 mins</div>
                </div>
            </div>
            
            <div id="hwSection" class="hw-section" style="display:none;">
                <div class="hw-title"><i class='bx bx-task'></i> Pending Homework</div>
                <div id="hwList"></div>
            </div>
            
            <div id="noHwMsg" style="text-align:center; color:#94a3b8; font-style:italic; margin-top:20px;">
                No pending homework due.
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(title, duration, homeworks) {
        document.getElementById('mTitle').innerText = title;
        document.getElementById('mDuration').innerText = duration;
        
        const hwSec = document.getElementById('hwSection');
        const hwList = document.getElementById('hwList');
        const noHw = document.getElementById('noHwMsg');
        
        if (homeworks && homeworks.length > 0) {
            hwSec.style.display = 'block';
            noHw.style.display = 'none';
            hwList.innerHTML = '';
            homeworks.forEach(hw => {
                const div = document.createElement('div');
                div.className = 'hw-item';
                div.innerHTML = `<strong>${hw.title}</strong> (Due: ${hw.due_date})`;
                hwList.appendChild(div);
            });
        } else {
            hwSec.style.display = 'none';
            noHw.style.display = 'block';
        }
        
        document.getElementById('infoModal').style.display = 'flex';
    }

    function closeModalBtn() {
        document.getElementById('infoModal').style.display = 'none';
    }

    function closeModal(e) {
        if(e.target.className === 'modal-overlay') {
            document.getElementById('infoModal').style.display = 'none';
        }
    }
</script>

<div style="height: 50px;"></div>
</body>
</html>