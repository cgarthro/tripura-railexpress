<?php
// login.php - User and Admin Login Form
require_once 'config/db_connect.php';

$error = '';

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_captcha = isset($_POST['captcha']) ? intval($_POST['captcha']) : -999;

    // Verify Math CAPTCHA Answer
    if (!isset($_SESSION['captcha_ans']) || $user_captcha !== $_SESSION['captcha_ans']) {
        $error = "Incorrect CAPTCHA math answer! Please try again.";
    } elseif (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Query user record by username/email
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, password, role FROM users WHERE email = ? OR full_name = ?");
        mysqli_stmt_bind_param($stmt, "ss", $email, $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Verify Hashed Password (or fallback check for sample demo accounts)
            if (
                password_verify($password, $user['password']) ||
                ($email === 'admin' && $password === 'admin123') ||
                ($email === 'user1' && $password === 'user123') ||
                ($email === 'user2' && $password === 'user123') ||
                ($email === 'user3' && $password === 'user123')
            ) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "Incorrect password! Please try again.";
            }
        } else {
            $error = "User account not found with this username.";
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 460px; margin: 2rem auto;">
    <div class="card">
        <h2 class="card-title">User Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Username</label>
                <input type="text" name="email" id="email" class="form-control" required placeholder="Enter Username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required
                    placeholder="Enter password">
            </div>

            <!-- Sliced SVG CAPTCHA Box with Refresh Button -->
            <div class="form-group">
                <label for="captcha" style="margin-bottom:2px;">Security Verification CAPTCHA</label>
                <small
                    style="color:#0284c7; font-weight:bold; font-size:0.75rem; display:block; margin-bottom:6px;">Unique
                    Anti-Bot Motion CAPTCHA</small>
                <div
                    style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; background:#f8f9fa; padding:10px 14px; border-radius:8px; border:1px solid #cbd5e1;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="captcha.php?v=<?php echo time(); ?>" id="login-captcha-img" alt="CAPTCHA Code"
                            style="height:42px; border-radius:6px; border:1px solid #cbd5e1;">
                        <button type="button" class="btn btn-secondary"
                            style="padding:6px 12px; font-size:0.82rem; font-weight:bold;"
                            onclick="document.getElementById('login-captcha-img').src = 'captcha.php?v=' + Date.now();"
                            title="Refresh CAPTCHA">🔄 Refresh</button>
                    </div>
                    <small style="color:var(--text-muted); font-size:0.75rem;">Solve sum</small>
                </div>
                <input type="number" name="captcha" id="captcha" class="form-control" required
                    placeholder="Enter numeric answer" autocomplete="off">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <div
            style="margin-top: 1.2rem; font-size:0.85rem; background:#f8f9fa; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
            <strong>Sample Logins:</strong><br>
            • Admin: <code>admin</code> / <code>admin123</code><br>
            • User 1: <code>user1</code> / <code>user123</code><br>
            • User 2: <code>user2</code> / <code>user123</code><br>
            • User 3: <code>user3</code> / <code>user123</code>
        </div>

        <p style="margin-top: 1.2rem; text-align: center; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="color: var(--primary-color); font-weight:600;">Register
                Here</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>