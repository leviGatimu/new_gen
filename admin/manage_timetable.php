<?php
// admin/manage_timetable.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Timetable Master";
$selected_class_id = $_GET['class_id'] ?? null;
$msg = "";

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // DELETE
    if (isset($_POST['delete_entry'])) {
        $pdo->prepare("DELETE FROM timetable_entries WHERE entry_id = ?")->execute([$_POST['entry_id']]);
        $msg = "Entry deleted.";
    }

    // ADD / EDIT
    if (isset($_POST['save_entry'])) {
        $class_id = $_POST['class_id'];
        $day = $_POST['day'];
        $start = $_POST['start_time']; // Hidden input from clicked cell
        $end = $_POST['end_time'];     // Hidden input from clicked cell
        $type = $_POST['type'];
        $color = $_POST['color'];
        
        $sub_id = ($type === 'subject') ? $_POST['subject_id'] : null;
        $custom = ($type === 'custom') ? $_POST['custom_activity'] : null;

        // Simple overlap check
        $stmt = $pdo->prepare("DELETE FROM timetable_entries WHERE class_id=? AND day_of_week=? AND start_time=?");
        $stmt->execute([$class_id, $day, $start]);

        $stmt = $pdo->prepare("INSERT INTO timetable_entries (class_id, day_of_week, start_time, end_time, subject_id, custom_activity, color_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $day, $start, $end, $sub_id, $custom, $color]);
        $msg = "Schedule updated.";
    }
}

// FETCH DATA
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();

// TIMETABLE STRUCTURE (Based on your Image)
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

// LOAD ENTRIES
$timetable_data = [];
if ($selected_class_id) {
    $stmt = $pdo->prepare("SELECT t.*, s.subject_name FROM timetable_entries t LEFT JOIN subjects s ON t.subject_id = s.subject_id WHERE t.class_id = ?");
    $stmt->execute([$selected_class_id]);
    while($row = $stmt->fetch()) {
        // Key format: "Monday_09:00"
        $key = $row['day_of_week'] . '_' . substr($row['start_time'], 0, 5);
        $timetable_data[$key] = $row;
    }
}

include '../includes/header.php';
?>

<div class="container">
    <style>
        /* EXCEL SHEET LOOK */
        .excel-table { 
            width: 100%; border-collapse: collapse; background: white; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-shadow: 0 0 0 1px #999; 
        }
        
        .excel-table th, .excel-table td {
            border: 1px solid #444; /* Dark borders like image */
            padding: 8px; text-align: center; vertical-align: middle;
            font-size: 0.85rem; height: 50px;
        }

        /* Header Row */
        .excel-table thead th {
            background: #e6f7ff; /* Light blue header */
            font-weight: 800; font-size: 1rem; padding: 15px;
        }

        /* Time Column */
        .time-col { background: #eefcfc; font-weight: 700; width: 120px; }

        /* Cells */
        .slot-cell { 
            cursor: pointer; transition: 0.2s; position: relative; 
            font-weight: 600; color: #000;
        }
        .slot-cell:hover { outline: 3px solid #FF6600; z-index: 10; }
        .slot-cell:empty::after { content: '+'; color: #ccc; font-size: 1.5rem; visibility: hidden; }
        .slot-cell:hover::after { visibility: visible; }

        /* Specific Rows (Break/Lunch) */
        .row-break td { background: #fffbe6; font-weight: 800; letter-spacing: 1px; color: #8a6d3b; }
        .row-lunch td { background: #e0f2f1; font-weight: 800; color: #00695c; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center; }
        .modal-box { background:white; padding:30px; border-radius:8px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
        .color-picker label { display:inline-block; width:30px; height:30px; border-radius:50%; border:2px solid #ddd; cursor:pointer; margin:2px; }
        input[type="radio"]:checked + label { border-color: #000; transform:scale(1.2); }
    </style>

    <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
        <h1 style="margin:0;">Timetable Editor</h1>
        <form method="GET">
            <select name="class_id" onchange="this.form.submit()" style="padding:10px; font-weight:bold;">
                <option value="">-- Select Class --</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['class_id']; ?>" <?php if($selected_class_id == $c['class_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if($selected_class_id): ?>
        <div style="overflow-x:auto;">
            <table class="excel-table">
                <thead>
                    <tr>
                        <th style="background:#ddd;">TIME</th>
                        <?php foreach($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($periods as $p): 
                        $p_start = $p[0]; $p_end = $p[1]; $p_label = $p[2];
                        $is_break = ($p_label === 'BREAK');
                        $is_lunch = ($p_label === 'LUNCH');
                        $row_class = $is_break ? 'row-break' : ($is_lunch ? 'row-lunch' : '');
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td class="time-col">
                            <?php 
                                $dt_start = date("g:i A", strtotime($p_start));
                                $dt_end = date("g:i A", strtotime($p_end));
                                echo "$dt_start - $dt_end"; 
                            ?>
                        </td>

                        <?php if($p_label && ($is_break || $is_lunch || $p_label == 'Self Study / Devotion')): ?>
                            <td colspan="5"><?php echo $p_label; ?></td>
                        <?php else: ?>
                            <?php foreach($days as $d): 
                                $key = $d . '_' . $p_start;
                                $entry = $timetable_data[$key] ?? null;
                                $bg = $entry ? $entry['color_code'] : '';
                                $text = $entry ? ($entry['custom_activity'] ?: $entry['subject_name']) : '';
                            ?>
                                <td class="slot-cell" style="background:<?php echo $bg; ?>;" 
                                    onclick="openEditModal('<?php echo $d; ?>', '<?php echo $p_start; ?>', '<?php echo $p_end; ?>', '<?php echo addslashes($text); ?>', '<?php echo $entry['entry_id'] ?? ''; ?>')">
                                    <?php echo $text; ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top:0;">Edit Slot</h3>
        <p id="modalTimeDisplay" style="color:#666; font-size:0.9rem; margin-bottom:15px;"></p>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to save changes?');">
            <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
            <input type="hidden" name="day" id="inputDay">
            <input type="hidden" name="start_time" id="inputStart">
            <input type="hidden" name="end_time" id="inputEnd">
            <input type="hidden" name="entry_id" id="inputEntryId">

            <label style="display:block; font-weight:bold; margin-bottom:5px;">Type</label>
            <select name="type" id="inputType" style="width:100%; padding:8px; margin-bottom:15px;" onchange="toggleInputs(this.value)">
                <option value="subject">Subject</option>
                <option value="custom">Custom Activity</option>
            </select>

            <div id="divSubject">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Select Subject</label>
                <select name="subject_id" style="width:100%; padding:8px; margin-bottom:15px;">
                    <?php foreach($subjects as $s): ?>
                        <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="divCustom" style="display:none;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Activity Name</label>
                <input type="text" name="custom_activity" placeholder="e.g. Assembly" style="width:100%; padding:8px; margin-bottom:15px; box-sizing:border-box;">
            </div>

            <label style="display:block; font-weight:bold; margin-bottom:5px;">Color</label>
            <div class="color-picker" style="margin-bottom:20px;">
                <input type="radio" name="color" value="#4dabf7" id="c1" hidden checked><label for="c1" style="background:#4dabf7;"></label> <input type="radio" name="color" value="#00bcd4" id="c2" hidden><label for="c2" style="background:#00bcd4;"></label> <input type="radio" name="color" value="#d0bfff" id="c3" hidden><label for="c3" style="background:#d0bfff;"></label> <input type="radio" name="color" value="#fff9c4" id="c4" hidden><label for="c4" style="background:#fff9c4;"></label> <input type="radio" name="color" value="#ffccbc" id="c5" hidden><label for="c5" style="background:#ffccbc;"></label> <input type="radio" name="color" value="#c8e6c9" id="c6" hidden><label for="c6" style="background:#c8e6c9;"></label> <input type="radio" name="color" value="#b0bec5" id="c7" hidden><label for="c7" style="background:#b0bec5;"></label> </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" name="save_entry" style="flex:1; background:#000; color:white; padding:10px; border:none; border-radius:4px; cursor:pointer;">Save</button>
                <button type="submit" name="delete_entry" id="btnDelete" style="background:#ff4d4f; color:white; padding:10px; border:none; border-radius:4px; cursor:pointer;">Delete</button>
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="padding:10px; border:1px solid #ccc; background:white; border-radius:4px; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(day, start, end, currentText, entryId) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('inputDay').value = day;
    document.getElementById('inputStart').value = start;
    document.getElementById('inputEnd').value = end;
    document.getElementById('inputEntryId').value = entryId;
    document.getElementById('modalTimeDisplay').innerText = day + " @ " + start + " - " + end;
    
    // Hide delete if empty
    document.getElementById('btnDelete').style.display = entryId ? 'block' : 'none';
}

function toggleInputs(val) {
    document.getElementById('divSubject').style.display = (val === 'subject') ? 'block' : 'none';
    document.getElementById('divCustom').style.display = (val === 'custom') ? 'block' : 'none';
}
</script>