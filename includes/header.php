<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Calculate base relative path depending on subfolder depth
$base_path = (file_exists('config/db_connect.php')) ? '' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tripura RailExpress - Railway Ticket Booking System</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>
<body>

<header>
    <nav class="navbar">
        <a href="<?php echo $base_path; ?>index.php" class="logo">
            🚆 <span>Tripura RailExpress</span>
        </a>
        <ul class="nav-links">
            <li><a href="<?php echo $base_path; ?>index.php">Search Trains</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="<?php echo $base_path; ?>users/my_bookings.php">My Bookings</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li><a href="<?php echo $base_path; ?>admin/dashboard.php" class="btn-nav-primary">Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="<?php echo $base_path; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['full_name']); ?>)</a></li>
            <?php else: ?>
                <li><a href="<?php echo $base_path; ?>login.php">Login</a></li>
                <li><a href="<?php echo $base_path; ?>register.php" class="btn-nav-primary">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main class="main-container">
