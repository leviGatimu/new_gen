<?php
// index.php
session_start();
require 'config/db.php';

$error = '';

// Handle Login Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // The query remains the same—it finds the user regardless of their role
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];

        // Automatic Redirect based on the role found in the DB
        $destinations = [
            'admin' => 'admin/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php',
            'parent' => 'parent/dashboard.php', 
            'accountant' => 'accountant/dashboard.php'
        ];
        
        if(array_key_exists($user['role'], $destinations)){
            header("Location: " . $destinations[$user['role']]);
            exit;
        }
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Bridge | New Generation Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* --- COOL DEV BANNER CSS --- */
        .dev-badge-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.8); /* Less transparent for readability */
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            animation: float 4s ease-in-out infinite;
            cursor: default;
            transition: all 0.3s ease;
        }

        .dev-badge-container:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: scale(1.05);
            box-shadow: 0 8px 32px 0 rgba(255, 102, 0, 0.2);
        }

        .dev-icon {
            font-size: 1.5rem;
            color: #FF6600;
            animation: spin 6s linear infinite;
        }

        .dev-text {
            font-family: 'Public Sans', sans-serif;
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            background: linear-gradient(45deg, #FF6600, #ff9f43);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .dev-status {
            width: 8px;
            height: 8px;
            background-color: #00b894;
            border-radius: 50%;
            box-shadow: 0 0 10px #00b894;
            animation: blink 2s infinite;
        }

        /* Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        /* --- MOBILE RESPONSIVENESS FIXES --- */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden; /* Prevent horizontal scroll */
            }

            /* Fix Layout Structure */
            .main-container {
                display: flex;
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            /* Info Section (Top part on mobile) */
            .info-section {
                width: 100%;
                padding: 60px 20px 40px 20px; /* More padding top for the badge */
                text-align: center;
                background-size: cover;
                box-sizing: border-box;
            }

            .info-section ul {
                display: none; /* Hide bullet points on mobile to save space */
            }
            
            .school-title { font-size: 1.8rem; }
            .school-desc { font-size: 0.9rem; }

            /* Login Section (Bottom part) */
            .login-section {
                width: 100%;
                padding: 30px 20px;
                border-radius: 20px 20px 0 0; /* Rounded top corners */
                margin-top: -20px; /* Slight overlap effect */
                background: white;
                box-sizing: border-box;
                flex: 1; /* Take remaining height */
            }

            .login-wrapper {
                width: 100%;
                max-width: 100%;
            }

            /* FIX THE DEV BADGE POSITION ON MOBILE */
            .dev-badge-container {
                top: 15px;         /* Move to TOP */
                bottom: auto;      /* Unset bottom */
                right: 50%;
                transform: translateX(50%);
                width: 85%;        /* Fit screen width */
                justify-content: center;
                padding: 8px 15px;
                animation: none;   /* Stop floating on mobile to be cleaner */
            }
            
            .dev-text { font-size: 0.7rem; }
        }
    </style>
</head>
<body>

<div class="dev-badge-container">
    <i class='bx bx-cog dev-icon'></i>
    <div class="dev-text">System Under Development</div>
    <div class="dev-status"></div>
</div>
<?php include 'includes/preloader.php'; ?>

<div class="main-container">
    
    <div class="info-section">
        <div>
            <div class="school-title">New Generation Academy</div>
            <p class="school-desc">
                Welcome to the official <strong>Academic Bridge</strong>. 
                A unified system for results, attendance, and growth tracking.
            </p>
            <ul class="feature-list">
                <li><i class='bx bx-check-circle'></i> Real-time Academic Reports</li>
                <li><i class='bx bx-check-circle'></i> Digital Attendance Tracking</li>
                <li><i class='bx bx-check-circle'></i> Parent-Teacher Communication</li>
                <li><i class='bx bx-check-circle'></i> Secure & Private Data</li>
            </ul>
        </div>
    </div>

    <div class="login-section">
        <div class="login-wrapper">
            
            <div style="text-align: center; margin-bottom: 20px;">
                <div class="logo-area">
                    <img src="assets/images/logo.png" alt="NGA Logo" class="school-logo fire-glow">
                </div>
            </div>

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Login to access your personalized dashboard.</p>
            </div>

            <?php if($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center; font-size:0.9rem; border:1px solid #fca5a5;">
                    <i class='bx bx-error-circle'></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required style="width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                </div>
                <div style="margin-bottom: 20px;">
                    <input type="password" name="password" class="form-control" placeholder="Password" required style="width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <button type="submit" class="btn-login" style="width: 100%; padding: 14px; background: #FF6600; color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.3s;">Sign In</button>
            </form>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">New student or parent?</p>
                <div style="display: grid; gap: 10px;">
                    <a href="activate.php" style="text-decoration: none;">
                        <button style="background: white; border: 1.5px solid #FF6600; color: #FF6600; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition:0.2s;">
                            Activate Student Account
                        </button>
                    </a>
                    <a href="parent-register.php" style="text-decoration: none;">
                        <button style="background: white; border: 1.5px solid #FF6600; color: #FF6600; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition:0.2s;">
                            Activate Parent Account
                        </button>
                    </a>
                </div>
            </div>

            <p style="text-align:center; margin-top:40px; color:#ccc; font-size:0.75rem;">
                &copy; <?php echo date("Y"); ?> New Generation Academy.
            </p>
        </div>
    </div>
</div>
</body>
</html>