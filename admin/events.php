<?php
// admin/events.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$message = "";
$msg_type = "";

// --- ADD EVENT ---
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

// --- DELETE EVENT ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM events WHERE event_id = ?")->execute([$id]);
    $message = "Event removed.";
    $msg_type = "success";
}

// FETCH EVENTS
$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Manager | NGA Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* NAV (Standard Admin) */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        .main-content { margin-top: var(--nav-height); padding: 40px 5%; width: 100%; box-sizing: border-box; max-width: 1400px; margin-left: auto; margin-right: auto; }

        .dashboard-split { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        
        .card { background: white; border-radius: 16px; padding: 25px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        
        .form-label { display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.9rem; color: #637381; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #dfe3e8; border-radius: 8px; font-family: inherit; margin-bottom: 15px; box-sizing: border-box; }
        .btn-submit { background: var(--dark); color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: var(--primary); }

        .event-list { display: flex; flex-direction: column; gap: 15px; }
        .event-item { display: flex; gap: 20px; padding: 15px; border: 1px solid #eee; border-radius: 12px; transition: 0.2s; position: relative; }
        .event-item:hover { border-color: var(--primary); background: #fffbf7; }
        
        .date-box { background: #f9fafb; padding: 15px; border-radius: 10px; text-align: center; min-width: 60px; height: fit-content; }
        .day { font-size: 1.5rem; font-weight: 800; color: var(--dark); display: block; }
        .month { font-size: 0.8rem; font-weight: 700; color: var(--primary); text-transform: uppercase; }
        
        .event-info h3 { margin: 0 0 5px 0; font-size: 1.1rem; color: var(--dark); }
        .event-meta { font-size: 0.85rem; color: #637381; display: flex; gap: 15px; margin-bottom: 8px; }
        .event-desc { font-size: 0.9rem; color: #444; line-height: 1.5; }
        
        .btn-delete { position: absolute; top: 15px; right: 15px; color: #ff4d4f; text-decoration: none; font-size: 1.2rem; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align:center; }
        .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="students.php" class="nav-item"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="leadership.php" class="nav-item"><i class='bx bxs-star'></i> <span>Leadership</span></a>
        <a href="events.php" class="nav-item active"><i class='bx bxs-calendar-event'></i> <span>Events</span></a>
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="finance_report.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> <span>Finance</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    
    <div style="margin-bottom: 30px;">
        <h1 style="margin:0; color:var(--dark);">School Events Manager</h1>
        <p style="color:#637381; margin:5px 0 0;">Create and manage competitions, meetings, and assemblies.</p>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="dashboard-split">
        
        <div class="card" style="height:fit-content;">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Create New Event</h3>
            <form method="POST">
                <label class="form-label">Event Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Inter-Class Debate" required>

                <label class="form-label">Date</label>
                <input type="date" name="event_date" class="form-control" required>

                <label class="form-label">Time</label>
                <input type="time" name="event_time" class="form-control" required>

                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. Main Hall">

                <label class="form-label">Description</label>
                <textarea name="description" rows="4" class="form-control" placeholder="Details about the event..."></textarea>

                <button type="submit" name="add_event" class="btn-submit">Publish Event</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Upcoming & Past Events</h3>
            
            <?php if(empty($events)): ?>
                <p style="color:#999; text-align:center; padding:30px;">No events scheduled.</p>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach($events as $e): ?>
                        <div class="event-item">
                            <div class="date-box">
                                <span class="day"><?php echo date("d", strtotime($e['event_date'])); ?></span>
                                <span class="month"><?php echo date("M", strtotime($e['event_date'])); ?></span>
                            </div>
                            <div class="event-info">
                                <h3><?php echo htmlspecialchars($e['title']); ?></h3>
                                <div class="event-meta">
                                    <span><i class='bx bx-time'></i> <?php echo date("H:i", strtotime($e['event_time'])); ?></span>
                                    <span><i class='bx bx-map'></i> <?php echo htmlspecialchars($e['location']); ?></span>
                                </div>
                                <p class="event-desc"><?php echo htmlspecialchars($e['description']); ?></p>
                            </div>
                            <a href="?delete=<?php echo $e['event_id']; ?>" class="btn-delete" onclick="return confirm('Delete this event?');"><i class='bx bx-trash'></i></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>