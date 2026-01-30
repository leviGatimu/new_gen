<?php
// student/profile.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- 1. HANDLE PROFILE UPDATES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $bio = trim($_POST['bio']);
    $email = trim($_POST['email']); 
    
    // Image Upload Logic
    $profile_pic = $_POST['current_pic'];
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "../assets/uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_name = time() . "_" . basename($_FILES["profile_pic"]["name"]);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $profile_pic = $file_name;
        } else {
            $msg = "Error uploading image."; $msg_type = "error";
        }
    }

    if (empty($msg)) {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone_number=?, email=?, bio=?, profile_pic=? WHERE user_id=?");
        if ($stmt->execute([$full_name, $phone, $email, $bio, $profile_pic, $user_id])) {
            $msg = "Profile updated successfully!"; $msg_type = "success";
            $_SESSION['full_name'] = $full_name; 
        } else {
            $msg = "Database update failed."; $msg_type = "error";
        }
    }
}

// --- 2. FETCH DATA ---
$stmt = $pdo->prepare("SELECT u.*, s.admission_number, s.class_role, s.leadership_role, c.class_name 
                       FROM users u 
                       JOIN students s ON u.user_id = s.student_id 
                       LEFT JOIN classes c ON s.class_id = c.class_id 
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

// --- 3. HELPER FUNCTIONS ---
function getInitials($name) {
    $name = trim($name);
    if (empty($name)) return "?";
    $parts = explode(' ', $name);
    $first = strtoupper(substr($parts[0], 0, 1));
    $last = (count($parts) > 1) ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}
$initials = getInitials($me['full_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --border: #dfe3e8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }

        /* Navbar */
        .top-navbar { position: fixed; top: 0; width: 100%; height: 70px; background: white; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 40px; z-index: 1000; box-sizing: border-box; }
        .nav-brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { color: #637381; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 6px; transition: 0.2s; display: flex; align-items: center; gap: 5px; }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .btn-logout { color: #ff4d4f; border: 1px solid #ff4d4f; padding: 6px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }

        /* Layout */
        .container { max-width: 1200px; margin: 100px auto 0; padding: 0 20px; display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        
        .left-col { display: flex; flex-direction: column; gap: 25px; }
        .right-col { display: flex; flex-direction: column; gap: 25px; }

        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        
        /* Profile Sidebar Styles */
        .profile-header { text-align: center; margin-bottom: 20px; }
        .profile-img-container { width: 120px; height: 120px; margin: 0 auto 15px; position: relative; border-radius: 50%; border: 4px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center; }
        .profile-img { width: 100%; height: 100%; object-fit: cover; }
        .profile-initials { font-size: 3rem; font-weight: 800; color: white; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--primary); }
        
        .role-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-block; margin-top: 5px; background: #fff7e6; color: #b78103; }
        .info-item { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f4f6f8; font-size: 0.95rem; color: #454f5b; }
        .info-item i { font-size: 1.3rem; color: var(--primary); width: 20px; text-align: center; }

        /* === ID CARD STYLES === */
        .id-section-title { margin: 0 0 20px 0; font-size: 1.1rem; color: var(--dark); border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700; display:flex; justify-content:space-between; align-items:center; }
        .id-display-container { display: flex; flex-wrap: wrap; gap: 30px; justify-content: center; }
        
        .id-card {
            width: 320px; height: 200px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05); position: relative;
            display: flex; flex-direction: column; flex-shrink: 0;
        }

        /* FRONT */
        .id-front-header { background: var(--primary); height: 40px; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; color: white; }
        .id-logo-text { font-size: 0.75rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .id-logo-img { height: 25px; width: auto; background: white; padding: 2px; border-radius: 4px; }

        .id-front-body { flex: 1; padding: 12px 15px; display: flex; gap: 12px; align-items: center; position: relative; z-index: 2; background-image: radial-gradient(#eee 1px, transparent 1px); background-size: 10px 10px; }
        .id-photo { width: 70px; height: 70px; border-radius: 10px; background: var(--dark); border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem; font-weight: 800; box-shadow: 0 4px 10px rgba(0,0,0,0.2); overflow: hidden; }
        .id-photo img { width: 100%; height: 100%; object-fit: cover; }
        
        .id-details { flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .id-name { font-size: 1rem; font-weight: 800; color: var(--dark); margin: 0; line-height: 1.1; text-transform: uppercase; }
        .id-role { font-size: 0.65rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; display:block; }
        
        .id-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .id-field { display: flex; flex-direction: column; }
        .id-lbl { font-size: 0.5rem; color: #888; text-transform: uppercase; font-weight: 700; }
        .id-val { font-size: 0.75rem; color: var(--dark); font-weight: 700; font-family: monospace; }

        .id-front-footer { height: 20px; background: var(--dark); display: flex; align-items: center; justify-content: center; }
        .barcode-fake { height: 10px; width: 80%; background: repeating-linear-gradient(to right, #fff 0px, #fff 2px, transparent 2px, transparent 4px); opacity: 0.5; }

        /* BACK */
        .id-back { background: #fdfdfd; }
        .magnetic-strip { height: 30px; background: #222; margin-top: 15px; width: 100%; }
        .id-back-body { padding: 10px 15px; display: flex; align-items: center; gap: 15px; height: 100%; box-sizing: border-box; }
        .id-qr { width: 80px; height: 80px; border: 2px solid #000; padding: 2px; background: white; flex-shrink: 0; }
        
        .id-back-info { text-align: left; flex: 1; display:flex; flex-direction:column; }
        .id-back-logo { height: 20px; opacity: 0.7; margin-bottom: 5px; align-self: flex-start; }
        .id-school-title { font-size: 0.75rem; font-weight: 800; color: var(--dark); text-transform: uppercase; margin-bottom: 5px; }
        .school-info-block { font-size: 0.6rem; color: #444; margin-bottom: 8px; line-height: 1.3; border-left: 2px solid var(--primary); padding-left: 6px; }
        .disclaimer { font-size: 0.5rem; color: #888; font-style: italic; line-height: 1.2; margin-top: auto; }
        .validity-stamp { margin-top: 5px; border: 1px solid var(--primary); color: var(--primary); font-size: 0.55rem; font-weight: 800; padding: 2px 5px; display: inline-block; border-radius: 4px; text-transform: uppercase; }
        .holo-effect { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 70%); pointer-events: none; z-index: 10; opacity: 0.6; }

        /* Form */
        h2 { margin: 0 0 20px 0; color: var(--dark); font-size: 1.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #637381; font-size: 0.85rem; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); outline: none; }
        .form-control:disabled { background: #f9fafb; color: #919eab; cursor: not-allowed; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .btn-save { background: var(--dark); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem; transition: 0.2s; }
        .btn-save:hover { background: var(--primary); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert.success { background: #e6f7ed; color: #00ab55; border: 1px solid #b7eb8f; }
        .alert.error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }

        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; }
            .id-display-container { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div style="width:40px;"><img src="../assets/images/logo.png" alt="" style="width:100%;"></div>
        Student Portal
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> Academics</a>
        <a href="results.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> My Results</a>
        <a href="messages.php" class="nav-item"><i class='bx bxs-chat'></i> Messages</a>
        <a href="attendance.php" class="nav-item"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
         <a href="class_ranking.php" class="nav-item"><i class='bx bxs-chat'></i> <span>Ranking</span></a>
        <a href="profile.php" class="nav-item active"><i class='bx bxs-user-circle'></i> <span>Profile</span></a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="container">
    
    <div class="left-col">
        <div class="card">
            <div class="profile-header">
                <div class="profile-img-container">
                    <?php if (!empty($me['profile_pic']) && $me['profile_pic'] !== 'default.png'): ?>
                        <img src="../assets/uploads/<?php echo htmlspecialchars($me['profile_pic']); ?>" class="profile-img">
                    <?php else: ?>
                        <div class="profile-initials">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h2 style="justify-content: center; margin-bottom: 5px; font-size:1.4rem;"><?php echo htmlspecialchars($me['full_name']); ?></h2>
                
                <span class="role-badge">
                    <?php echo $me['class_role']; ?>
                </span>
            </div>

            <div class="info-list">
                <div class="info-item"><i class='bx bxs-id-card'></i> <?php echo htmlspecialchars($me['admission_number']); ?></div>
                <div class="info-item"><i class='bx bxs-school'></i> <?php echo htmlspecialchars($me['class_name'] ?? 'Not Assigned'); ?></div>
                <div class="info-item"><i class='bx bxs-envelope'></i> <?php echo htmlspecialchars($me['email']); ?></div>
                <div class="info-item"><i class='bx bxs-phone'></i> <?php echo htmlspecialchars($me['phone_number'] ?: 'No phone'); ?></div>
            </div>
        </div>
    </div>

    <div class="right-col">
        
        <div class="card">
            <h3 class="id-section-title">
                <span><i class='bx bxs-id-card' style="color:var(--primary);"></i> My ID Card</span>
                <button onclick="window.print()" style="border:none; background:none; cursor:pointer; color:#637381; font-weight:600; display:flex; align-items:center; gap:5px;">
                    <i class='bx bxs-printer'></i> Print
                </button>
            </h3>
            
            <div class="id-display-container">
                <div class="id-card">
                    <div class="id-front-header">
                        <span class="id-logo-text">New Generation Academy</span>
                        <img src="../assets/images/logo.png" class="id-logo-img" alt="NGA">
                    </div>
                    <div class="id-front-body">
                        <div class="id-photo">
                            <?php if (!empty($me['profile_pic']) && $me['profile_pic'] !== 'default.png'): ?>
                                <img src="../assets/uploads/<?php echo htmlspecialchars($me['profile_pic']); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="id-details">
                            <h3 class="id-name"><?php echo htmlspecialchars($me['full_name']); ?></h3>
                            <span class="id-role">
                                <?php echo $me['leadership_role'] ? strtoupper($me['leadership_role']) : 'STUDENT'; ?>
                            </span>
                            <div class="id-grid">
                                <div class="id-field">
                                    <span class="id-lbl">ID No</span>
                                    <span class="id-val"><?php echo $me['admission_number']; ?></span>
                                </div>
                                <div class="id-field">
                                    <span class="id-lbl">Grade</span>
                                    <span class="id-val"><?php echo htmlspecialchars($me['class_name'] ?? '-'); ?></span>
                                </div>
                                <div class="id-field">
                                    <span class="id-lbl">Issued</span>
                                    <span class="id-val"><?php echo date("M Y"); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="id-front-footer">
                        <div class="barcode-fake"></div>
                    </div>
                    <div class="holo-effect"></div>
                </div>

                <div class="id-card id-back">
                    <div class="magnetic-strip"></div>
                    <div class="id-back-body">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=NGA-<?php echo $me['admission_number']; ?>" class="id-qr" alt="QR">
                        
                        <div class="id-back-info">
                            <img src="../assets/images/logo.png" class="id-back-logo" alt="Logo">
                            <div class="id-school-title">New Generation Academy</div>
                            <div class="school-info-block">
                                <strong>Tel:</strong> +250 788 123 456<br>
                                <strong>Email:</strong> info@nga.rw
                            </div>
                            <div class="validity-stamp">VALID <?php echo date("Y"); ?></div>
                            <div class="disclaimer">
                                Property of NGA. Return if found.
                            </div>
                        </div>
                    </div>
                    <div class="holo-effect"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><i class='bx bx-edit-alt'></i> Edit Profile</h2>
            
            <?php if($msg): ?>
                <div class="alert <?php echo $msg_type; ?>"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="current_pic" value="<?php echo $me['profile_pic']; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($me['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($me['phone_number']); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Class</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($me['class_name']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Admission Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($me['admission_number']); ?>" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($me['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Bio / About Me</label>
                    <textarea name="bio" class="form-control" placeholder="What are your favorite subjects? Hobbies?"><?php echo htmlspecialchars($me['bio']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Update Profile Picture</label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/*">
                    <small style="color:#999;">Leave empty to keep current picture. This photo will appear on your ID Card.</small>
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class='bx bxs-save'></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>