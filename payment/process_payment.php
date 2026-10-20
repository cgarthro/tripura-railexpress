<?php
// payment/process_payment.php - Transaction Processing Script
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id     = intval($_POST['booking_id']);
    $payment_method = trim($_POST['payment_method']);
    $user_id        = $_SESSION['user_id'];

    // Generate unique transaction reference ID
    $txn_id = 'TXN' . random_int(10000000, 99999999);

    mysqli_begin_transaction($conn);

    try {
        // 1. Fetch and Lock the pending booking row.
        $stmt = mysqli_prepare($conn, "
            SELECT b.pnr_number, b.train_id, b.journey_date, b.travel_class, b.total_passengers
            FROM bookings b
            JOIN trains t ON b.train_id = t.train_id
            WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'Pending' AND t.is_deleted = 0
            FOR UPDATE
        ");
        mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
        mysqli_stmt_execute($stmt);
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$booking) {
            throw new Exception("This booking is no longer available for payment.");
        }

        $train_id     = $booking['train_id'];
        $journey_date = $booking['journey_date'];
        $travel_class = $booking['travel_class'];
        $passengers   = intval($booking['total_passengers']);

        // 2. Lock the train row to prevent concurrent capacity updates.
        $t_stmt = mysqli_prepare($conn, "SELECT seats_gn, seats_2s, seats_sl, seats_cc, seats_3ac FROM trains WHERE train_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($t_stmt, "i", $train_id);
        mysqli_stmt_execute($t_stmt);
        $train = mysqli_fetch_assoc(mysqli_stmt_get_result($t_stmt));

        if (!$train) {
            throw new Exception("Train schedule details not found.");
        }

        // Map travel class to capacity column
        $class_col = 'seats_gn';
        if ($travel_class === '2S') $class_col = 'seats_2s';
        elseif ($travel_class === 'SL') $class_col = 'seats_sl';
        elseif ($travel_class === 'CC') $class_col = 'seats_cc';
        elseif ($travel_class === '3AC') $class_col = 'seats_3ac';

        $class_capacity = intval($train[$class_col]);

        // 3. Count other confirmed bookings for this date and class.
        $chk_stmt = mysqli_prepare($conn, "
            SELECT COALESCE(SUM(total_passengers), 0) as confirmed_count
            FROM bookings
            WHERE train_id = ? AND journey_date = ? AND travel_class = ? AND status = 'Confirmed'
        ");
        mysqli_stmt_bind_param($chk_stmt, "iss", $train_id, $journey_date, $travel_class);
        mysqli_stmt_execute($chk_stmt);
        $confirmed_count = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt))['confirmed_count'];

        // 4. Validate if seats are still available in the quota.
        if (($class_capacity - $confirmed_count) < $passengers) {
            throw new Exception("Seats in class $travel_class are no longer available for this journey date.");
        }

        // 5. Confirm the booking and record payment.
        $u_stmt = mysqli_prepare($conn, "UPDATE bookings SET payment_method = ?, txn_id = ?, status = 'Confirmed' WHERE booking_id = ? AND status = 'Pending'");
        mysqli_stmt_bind_param($u_stmt, "ssi", $payment_method, $txn_id, $booking_id);
        mysqli_stmt_execute($u_stmt);

        if (mysqli_stmt_affected_rows($u_stmt) !== 1) {
            throw new Exception("Payment processing failed or was already processed.");
        }

        mysqli_commit($conn);
        header("Location: ../users/view_ticket.php?pnr=" . urlencode($booking['pnr_number']));
        exit();
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        die("Payment confirmation error: " . htmlspecialchars($e->getMessage()));
    }
}

header("Location: ../users/my_bookings.php");
exit();
?>
