<?php
// admin/events.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "Events Manager";
$message = "";
$msg_type = "";

// 2. HANDLE ADD EVENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $date = $_POST['event_date'];
    $time = $_POST['event_time'];
    $loc = $_POST['location'];
    
    if(!empty($title) && !empty($date)) {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location) VALUES (?, ?, ?, ?, ?)");
        if($stmt->execute([$title, $desc, $date, $time, $loc])) {
            $message = "Event created successfully!";
            $msg_type = "success";
        } else {
            $message = "Error creating event.";
            $msg_type = "error";
        }
    }
}

// 3. HANDLE DELETE EVENT
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM events WHERE event_id = ?")->execute([$id]);
    $message = "Event removed.";
    $msg_type = "success";
}

// 4. FETCH EVENTS
$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

// 5. INCLUDE HEADER
include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === PAGE VARIABLES === */
        :root { --primary: #FF6600; --dark: #1e293b; --gray: #64748b; --bg-card: #ffffff; }

        /* Header Area */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 35px; flex-wrap: wrap; gap: 15px; 
        }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }
        
        /* Layout Grid */
        .events-grid { 
            display: grid; grid-template-columns: 1fr 2fr; gap: 30px; 
        }

        /* Card Styles */
        .card { 
            background: var(--bg-card); border-radius: 16px; padding: 25px; 
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            height: fit-content;
        }
        .card-header { 
            border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; 
            font-size: 1.1rem; font-weight: 700; color: var(--dark);
        }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--gray); margin-bottom: 8px; }
        .form-control { 
            width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; 
            font-family: inherit; font-size: 0.95rem; color: var(--dark); transition: 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1); }
        
        .btn-submit { 
            background: var(--dark); color: white; width: 100%; padding: 12px; 
            border: none; border-radius: 8px; font-weight: 700; cursor: pointer; 
            transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        .btn-submit:hover { background: var(--primary); transform: translateY(-2px); }

        /* Event List */
        .event-list { display: flex; flex-direction: column; gap: 15px; }
        .event-item { 
            display: flex; gap: 20px; padding: 20px; border: 1px solid #f1f5f9; 
            border-radius: 12px; transition: 0.2s; position: relative; background: white;
        }
        .event-item:hover { border-color: var(--primary); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        
        .date-badge { 
            background: #f8fafc; padding: 15px; border-radius: 12px; text-align: center; 
            min-width: 60px; height: fit-content; border: 1px solid #e2e8f0;
        }
        .day { font-size: 1.8rem; font-weight: 800; color: var(--dark); display: block; line-height: 1; }
        .month { font-size: 0.8rem; font-weight: 700; color: var(--primary); text-transform: uppercase; margin-top: 5px; display: block; }
        
        .event-content h3 { margin: 0 0 8px 0; font-size: 1.1rem; color: var(--dark); }
        .event-meta { 
            display: flex; gap: 15px; font-size: 0.85rem; color: var(--gray); margin-bottom: 10px; font-weight: 600;
        }
        .event-desc { font-size: 0.9rem; color: #475569; line-height: 1.5; margin: 0; }
        
        .btn-delete { 
            position: absolute; top: 20px; right: 20px; color: #cbd5e1; 
            background: none; border: none; font-size: 1.2rem; cursor: pointer; transition: 0.2s; 
        }
        .btn-delete:hover { color: #ef4444; transform: scale(1.1); }

        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid transparent; }
        .alert-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

        /* Mobile */
        @media (max-width: 900px) {
            .events-grid { grid-template-columns: 1fr; }
            .event-item { flex-direction: column; gap: 15px; }
            .date-badge { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; }
            .month { margin-top: 0; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Events Manager</h1>
            <p style="color:var(--gray); margin:5px 0 0;">Schedule school competitions, meetings, and holidays.</p>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="events-grid">
        
        <div class="card">
            <div class="card-header">Create New Event</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Event Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Inter-Class Debate" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" name="event_time" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Main Hall">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="4" class="form-control" placeholder="Details about the event..."></textarea>
                </div>

                <button type="submit" name="add_event" class="btn-submit">
                    <i class='bx bx-plus-circle'></i> Publish Event
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Upcoming & Past Events</div>
            
            <?php if(empty($events)): ?>
                <div style="text-align:center; padding:50px; color:var(--gray);">
                    <i class='bx bx-calendar-x' style="font-size:3rem; opacity:0.3; margin-bottom:10px;"></i>
                    <p>No events scheduled.</p>
                </div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach($events as $e): ?>
                        <div class="event-item">
                            <div class="date-badge">
                                <span class="day"><?php echo date("d", strtotime($e['event_date'])); ?></span>
                                <span class="month"><?php echo date("M", strtotime($e['event_date'])); ?></span>
                            </div>
                            
                            <div class="event-content">
                                <h3><?php echo htmlspecialchars($e['title']); ?></h3>
                                <div class="event-meta">
                                    <span><i class='bx bx-time'></i> <?php echo date("H:i", strtotime($e['event_time'])); ?></span>
                                    <span><i class='bx bx-map'></i> <?php echo htmlspecialchars($e['location']); ?></span>
                                </div>
                                <p class="event-desc"><?php echo htmlspecialchars($e['description']); ?></p>
                            </div>

                            <a href="?delete=<?php echo $e['event_id']; ?>" class="btn-delete" onclick="return confirm('Delete this event?');">
                                <i class='bx bxs-trash'></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>