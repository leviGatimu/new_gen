<?php
// admin/settings.php
session_start();
require '../config/db.php';

// 1. SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$page_title = "System Settings";
$success = '';
$error = '';

// 2. HANDLE UPDATES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_name = trim($_POST['school_name']);
    $new_term_id = $_POST['current_term_id'];
    
    if(empty($new_name) || empty($new_term_id)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Update Main Settings Table
            $stmt = $pdo->prepare("UPDATE settings SET school_name = :name, current_term_id = :term WHERE setting_id = 1");
            $stmt->execute(['name' => $new_name, 'term' => $new_term_id]);
            
            // B. Global Term Reset (Crucial for Dashboards)
            // 1. Set ALL terms to inactive
            $pdo->query("UPDATE academic_terms SET is_active = 0");
            
            // 2. Set the CHOSEN term to active
            $pdo->prepare("UPDATE academic_terms SET is_active = 1 WHERE term_id = :id")->execute(['id' => $new_term_id]);
            
            $pdo->commit();
            $success = "System updated. The active term has been switched globally.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating settings: " . $e->getMessage();
        }
    }
}

// 3. FETCH DATA
$settings = $pdo->query("SELECT * FROM settings WHERE setting_id = 1")->fetch();
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY start_date DESC")->fetchAll(); // Ordered by date usually makes more sense

include '../includes/header.php';
?>

<div class="container">

    <style>
        /* === SETTINGS PAGE STYLES === */
        :root { --primary: #FF6600; --dark: #1e293b; --gray: #64748b; --bg-card: #ffffff; }

        /* Page Layout */
        .page-header { margin-bottom: 40px; text-align: center; }
        .page-title { font-size: 2rem; color: var(--dark); font-weight: 800; margin: 0 0 10px 0; }
        .page-desc { color: var(--gray); font-size: 1rem; }

        /* Settings Card */
        .settings-card { 
            background: var(--bg-card); max-width: 600px; margin: 0 auto; 
            padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
            border: 1px solid #f1f5f9;
        }

        /* Form Elements */
        .form-group { margin-bottom: 30px; }
        
        .form-label { 
            display: block; font-weight: 700; margin-bottom: 10px; 
            color: var(--dark); font-size: 0.95rem; display: flex; align-items: center; gap: 8px;
        }
        .form-label i { font-size: 1.2rem; color: var(--primary); }

        .help-text { 
            background: #f8fafc; padding: 12px; border-radius: 8px; 
            color: var(--gray); font-size: 0.85rem; margin-bottom: 15px; 
            border-left: 4px solid #cbd5e1; line-height: 1.5;
        }

        .form-control { 
            width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; 
            border-radius: 12px; font-size: 1rem; outline: none; transition: 0.2s;
            box-sizing: border-box; background-color: #fff; color: var(--dark);
            font-family: inherit;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.1); }

        .divider { height: 1px; background: #e2e8f0; margin: 30px 0; }

        /* Submit Button */
        .btn-save { 
            background: var(--dark); color: white; width: 100%; padding: 16px; 
            border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; 
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; 
            justify-content: center; gap: 10px;
        }
        .btn-save:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 102, 0, 0.2); }

        /* Alerts */
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Mobile */
        @media (max-width: 600px) {
            .settings-card { padding: 25px; }
            .page-title { font-size: 1.5rem; }
        }
    </style>

    <div class="page-header">
        <h1 class="page-title">Global Configuration</h1>
        <p class="page-desc">Manage school identity and academic periods.</p>
    </div>

    <div class="settings-card">
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class='bx bxs-check-circle' style="font-size:1.4rem;"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-error">
                <i class='bx bxs-error-circle' style="font-size:1.4rem;"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            
            <div class="form-group">
                <label class="form-label"><i class='bx bxs-school'></i> School Name</label>
                <div class="help-text">This name will appear on the login screen, top navigation bars, and official student reports.</div>
                <input type="text" name="school_name" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['school_name'] ?? 'New Generation Academy'); ?>" 
                       placeholder="Enter School Name" required>
            </div>

            <div class="divider"></div>

            <div class="form-group">
                <label class="form-label"><i class='bx bxs-calendar'></i> Active Academic Term</label>
                <div class="help-text">
                    <strong>Warning:</strong> Changing this will immediately switch the view for all Teachers, Students, and Parents. They will only see data related to the selected term.
                </div>
                
                <select name="current_term_id" class="form-control" required>
                    <?php foreach($terms as $term): ?>
                        <option value="<?php echo $term['term_id']; ?>" 
                            <?php if($term['term_id'] == $settings['current_term_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($term['term_name']); ?> 
                            <?php if($term['is_active']) echo ' (Active Now)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-save">
                <i class='bx bxs-save'></i> Save Changes
            </button>

        </form>
    </div>

    <div style="height: 60px;"></div>
</div>

</body>
</html>