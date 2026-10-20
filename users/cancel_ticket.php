<?php
// users/cancel_ticket.php - Ticket Cancellation Handler (POST only)
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['role'] ?? 'user';
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

mysqli_begin_transaction($conn);

try {
    // 1. Lock the booking record.
    if ($role === 'admin') {
        $stmt = mysqli_prepare($conn, "SELECT pnr_number, total_fare FROM bookings WHERE booking_id = ? AND status = 'Confirmed' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT pnr_number, total_fare FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'Confirmed' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    }
    mysqli_stmt_execute($stmt);
    $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$booking) {
        throw new Exception("This ticket is not available for cancellation.");
    }

    // 2. Mark the ticket cancelled. The dynamic seat quota calculation naturally handles restoring capacity.
    $u_stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ? AND status = 'Confirmed'");
    mysqli_stmt_bind_param($u_stmt, "i", $booking_id);
    mysqli_stmt_execute($u_stmt);

    if (mysqli_stmt_affected_rows($u_stmt) !== 1) {
        throw new Exception("This ticket was already cancelled.");
    }

    mysqli_commit($conn);

    if ($role === 'admin') {
        header("Location: ../admin/booking_details.php?id=" . $booking_id . "&msg=cancelled");
    } else {
        header("Location: my_bookings.php?msg=cancelled&pnr=" . urlencode($booking['pnr_number']) . "&amount=" . urlencode(number_format($booking['total_fare'], 2)));
    }
    exit();
} catch (Throwable $e) {
    mysqli_rollback($conn);
    die("Cancellation Error: " . htmlspecialchars($e->getMessage()));
}
?>
