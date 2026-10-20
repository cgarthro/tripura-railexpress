<?php
// register.php - User Registration Form with Security CAPTCHA
require_once 'config/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']);
    $email        = trim($_POST['email']);
    $phone        = trim($_POST['phone']);
    $password     = $_POST['password'];
    $confirm_pw   = $_POST['confirm_password'];
    $user_captcha = isset($_POST['captcha']) ? intval($_POST['captcha']) : -999;

    // Verify Math CAPTCHA Answer
    if (!isset($_SESSION['captcha_ans']) || $user_captcha !== $_SESSION['captcha_ans']) {
        $error = "Incorrect CAPTCHA math answer! Please try again.";
    } elseif (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_pw) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already registered
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "An account with this email address already exists!";
        } else {
            // Hash password using password_hash()
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, 'user')");
            mysqli_stmt_bind_param($insert_stmt, "ssss", $full_name, $email, $hashed_password, $phone);

            if (mysqli_stmt_execute($insert_stmt)) {
                $success = "Account registered successfully! You can now <a href='login.php'>Login here</a>.";
            } else {
                $error = "Error registering account: " . mysqli_error($conn);
            }
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 500px; margin: 1.5rem auto;">
    <div class="card">
        <h2 class="card-title">User Registration</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="e.g. Rahul Sharma">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" required placeholder="e.g. rahul@gmail.com">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" required placeholder="e.g. 9876543210">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="At least 6 characters">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Re-enter password">
                </div>

                <!-- Sliced SVG CAPTCHA Box with Refresh Button -->
                <div class="form-group">
                    <label for="captcha" style="margin-bottom:2px;">Security Verification CAPTCHA</label>
                    <small style="color:#0284c7; font-weight:bold; font-size:0.75rem; display:block; margin-bottom:6px;">Unique Anti-Bot Motion CAPTCHA</small>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; background:#f8f9fa; padding:10px 14px; border-radius:8px; border:1px solid #cbd5e1;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="captcha.php?v=<?php echo time(); ?>" id="reg-captcha-img" alt="CAPTCHA Code" style="height:42px; border-radius:6px; border:1px solid #cbd5e1;">
                            <button type="button" class="btn btn-secondary" style="padding:6px 12px; font-size:0.82rem; font-weight:bold;" onclick="document.getElementById('reg-captcha-img').src = 'captcha.php?v=' + Date.now();" title="Refresh CAPTCHA">🔄 Refresh</button>
                        </div>
                        <small style="color:var(--text-muted); font-size:0.75rem;">Solve sum</small>
                    </div>
                    <input type="number" name="captcha" id="captcha" class="form-control" required placeholder="Enter numeric answer" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
        <?php endif; ?>

        <p style="margin-top: 1.2rem; text-align: center; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="color: var(--primary-color); font-weight:600;">Login Here</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
