<?php
// admin/delete_train.php - Secure Train Schedule Deletion Handler
require_once '../config/db_connect.php';

// Auth Guard: Admin access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$train_id = isset($_REQUEST['train_id']) ? intval($_REQUEST['train_id']) : (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);

if ($train_id > 0) {
    mysqli_begin_transaction($conn);

    try {
        // 1. Mark all active bookings for this train as Cancelled (Train Discontinued)
        $b_stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'Cancelled' WHERE train_id = ? AND status IN ('Confirmed', 'Pending')");
        mysqli_stmt_bind_param($b_stmt, "i", $train_id);
        mysqli_stmt_execute($b_stmt);

        // 2. Soft delete the train schedule
        $t_stmt = mysqli_prepare($conn, "UPDATE trains SET is_deleted = 1 WHERE train_id = ?");
        mysqli_stmt_bind_param($t_stmt, "i", $train_id);
        mysqli_stmt_execute($t_stmt);

        mysqli_commit($conn);

        header("Location: dashboard.php?msg=deleted");
        exit();
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        die("<div style='padding:2rem; font-family:sans-serif;'><h2>Deletion Error ❌</h2><p>" . htmlspecialchars($e->getMessage()) . "</p><a href='dashboard.php'>Return to Dashboard</a></div>");
    }
}

header("Location: dashboard.php");
exit();
?>
