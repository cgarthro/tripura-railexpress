<?php
// users/confirm_cancel.php - Ticket Cancellation Confirmation Screen
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['role'] ?? 'user';

// Fetch booking details (strict user or admin check)
if ($role === 'admin') {
    $stmt = mysqli_prepare($conn, "
        SELECT b.*, t.train_number, t.train_name
        FROM bookings b
        JOIN trains t ON b.train_id = t.train_id
        WHERE b.booking_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT b.*, t.train_number, t.train_name
        FROM bookings b
        JOIN trains t ON b.train_id = t.train_id
        WHERE b.booking_id = ? AND b.user_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
}

mysqli_stmt_execute($stmt);
$res     = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($res);

if (!$booking) {
    die("Access denied or booking record not found.");
}

if ($booking['status'] === 'Cancelled') {
    header("Location: my_bookings.php?msg=already_cancelled&pnr=" . urlencode($booking['pnr_number']));
    exit();
}

// Fetch passengers
$p_stmt = mysqli_prepare($conn, "SELECT * FROM passengers WHERE booking_id = ?");
mysqli_stmt_bind_param($p_stmt, "i", $booking_id);
mysqli_stmt_execute($p_stmt);
$p_res = mysqli_stmt_get_result($p_stmt);

include '../includes/header.php';
?>

<div style="max-width: 700px; margin: 2rem auto;">
    <div class="card" style="border-top:5px solid var(--danger-color);">
        <h2 class="card-title" style="color:var(--danger-color);">⚠️ Confirm Ticket Cancellation</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">Are you sure you want to cancel this ticket? Please review refund details below.</p>

        <!-- Booking Summary -->
        <div style="background:#fff5f5; padding:1.2rem; border-radius:8px; border:1px solid #feb2b2; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                <div>
                    <h3 style="color:var(--primary-color);">PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?></h3>
                    <p>Train: <strong>#<?php echo htmlspecialchars($booking['train_number']); ?> - <?php echo htmlspecialchars($booking['train_name']); ?></strong></p>
                    <p>Route: <strong><?php echo htmlspecialchars($booking['boarding_station']); ?></strong> ➔ <strong><?php echo htmlspecialchars($booking['deboarding_station']); ?></strong></p>
                    <p>Journey Date: <strong><?php echo date('d-M-Y', strtotime($booking['journey_date'])); ?></strong></p>
                </div>
                <div style="text-align:right;">
                    <small style="color:var(--text-muted);">Refund Amount:</small><br>
                    <span style="font-size:1.8rem; font-weight:bold; color:var(--danger-color);">₹<?php echo number_format($booking['total_fare'], 2); ?></span>
                </div>
            </div>

            <div style="background:#ffffff; padding:10px; border-radius:6px; font-size:0.88rem; border:1px solid #cbd5e1;">
                💳 <strong>Payment Method Used:</strong> <?php echo htmlspecialchars($booking['payment_method'] ?? 'Simulated UPI'); ?><br>
                🔖 <strong>Txn Reference ID:</strong> <?php echo htmlspecialchars($booking['txn_id'] ?? 'N/A'); ?>
            </div>
        </div>

        <!-- Passenger List -->
        <h4 style="margin-bottom:0.8rem; color:var(--primary-color);">Passengers to be Cancelled:</h4>
        <ul style="margin-bottom:1.5rem; padding-left:1.5rem; line-height:1.8;">
            <?php while ($p = mysqli_fetch_assoc($p_res)): ?>
                <li><strong><?php echo htmlspecialchars($p['passenger_name']); ?></strong> (Age: <?php echo $p['age']; ?>, Gender: <?php echo $p['gender']; ?>) - Seat: <code><?php echo htmlspecialchars($p['seat_number']); ?></code></li>
            <?php endwhile; ?>
        </ul>

        <!-- Refund Policy Notice -->
        <div class="alert alert-info" style="margin-bottom:1.5rem;">
            ℹ️ <strong>Refund Guarantee:</strong> Upon confirmation, the full refund of <strong>₹<?php echo number_format($booking['total_fare'], 2); ?></strong> will be credited back to your original payment method within 24 hours.
        </div>

        <form method="POST" action="cancel_ticket.php">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <div style="display:flex; gap:15px; justify-content:flex-end;">
                <a href="my_bookings.php" class="btn btn-secondary">Keep Ticket (Go Back)</a>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Confirm ticket cancellation?');">❌ Confirm Ticket Cancellation</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
