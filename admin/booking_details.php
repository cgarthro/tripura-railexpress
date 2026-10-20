<?php
// admin/booking_details.php - Admin Detailed Passenger Manifest & Booking Inspector
require_once '../config/db_connect.php';

// Auth Guard: Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$admin_msg  = isset($_GET['msg']) && $_GET['msg'] === 'cancelled' ? 'Booking has been successfully cancelled by Admin. Seat quota restored.' : '';

// Fetch complete booking record dynamically
$stmt = mysqli_prepare($conn, "
    SELECT b.*, t.train_number, t.train_name,
           (SELECT departure_time FROM train_stops WHERE train_id = b.train_id AND station_name = b.boarding_station LIMIT 1) as departure_time,
           (SELECT arrival_time FROM train_stops WHERE train_id = b.train_id AND station_name = b.deboarding_station LIMIT 1) as arrival_time,
           u.full_name as account_name, u.email, u.phone
    FROM bookings b
    LEFT JOIN trains t ON b.train_id = t.train_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$booking_res = mysqli_stmt_get_result($stmt);
$booking     = mysqli_fetch_assoc($booking_res);

if (!$booking) {
    die("Booking Record Not Found.");
}

// Fetch passengers list
$p_stmt = mysqli_prepare($conn, "SELECT * FROM passengers WHERE booking_id = ?");
mysqli_stmt_bind_param($p_stmt, "i", $booking_id);
mysqli_stmt_execute($p_stmt);
$passengers_res = mysqli_stmt_get_result($p_stmt);

include '../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h2>📜 Admin Passenger Manifest - PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?></h2>
        <p>Detailed reservation records, passenger seats, and transaction information.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="all_bookings.php" class="btn btn-secondary">← Back to All Bookings</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Manifest</button>
    </div>
</div>

<?php if (!empty($admin_msg)): ?>
    <div class="alert alert-success">✓ <?php echo $admin_msg; ?></div>
<?php endif; ?>

<div class="card">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.2rem; background:#f8f9fa; padding:1.2rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid #e2e8f0;">
        <div>
            <small style="color:var(--text-muted);">PNR & Booking Status</small>
            <p style="font-size:1.2rem; font-weight:bold; font-family:monospace; color:var(--primary-color);"><?php echo htmlspecialchars($booking['pnr_number']); ?></p>
            <?php if ($booking['status'] === 'Confirmed'): ?>
                <span class="badge badge-confirmed">Confirmed</span>
            <?php elseif ($booking['status'] === 'Pending'): ?>
                <span class="badge" style="background:#f39c12; color:#fff;">Payment Pending</span>
            <?php else: ?>
                <span class="badge badge-cancelled">Cancelled</span>
            <?php endif; ?>
        </div>

        <div>
            <small style="color:var(--text-muted);">Train Schedule</small>
            <p style="font-weight:bold;">#<?php echo htmlspecialchars($booking['train_number'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($booking['train_name'] ?? 'Discontinued'); ?></p>
            <small style="color:var(--text-muted);"><?php echo !empty($booking['departure_time']) ? date('h:i A', strtotime($booking['departure_time'])) : '-'; ?> Departure</small>
        </div>

        <div>
            <small style="color:var(--text-muted);">Boarding ➔ Destination</small>
            <p style="font-weight:bold; color:var(--primary-color);">
                <?php echo htmlspecialchars($booking['boarding_station']); ?> ➔
                <?php echo htmlspecialchars($booking['deboarding_station']); ?>
            </p>
            <small style="color:var(--text-muted);">Journey Date: <?php echo date('d-M-Y', strtotime($booking['journey_date'])); ?> | Class: <?php echo htmlspecialchars($booking['travel_class']); ?></small>
        </div>

        <div>
            <small style="color:var(--text-muted);">Passenger Account</small>
            <p style="font-weight:bold;"><?php echo htmlspecialchars($booking['account_name']); ?></p>
            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($booking['email']); ?> | <?php echo htmlspecialchars($booking['phone']); ?></small>
        </div>

        <div>
            <small style="color:var(--text-muted);">Payment Info</small>
            <p style="font-weight:bold; color:var(--success-color);">₹<?php echo number_format($booking['total_fare'], 2); ?></p>
            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($booking['payment_method'] ?? 'UPI / QR Code'); ?> (Txn: <code><?php echo htmlspecialchars($booking['txn_id'] ?? 'N/A'); ?></code>)</small>
        </div>
    </div>

    <!-- Complete Passenger List Table -->
    <h4 style="margin-bottom:0.8rem; color:var(--primary-color);">Passengers Manifest</h4>
    <div class="table-responsive" style="margin-bottom:1.5rem;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Assigned Seat</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                while ($p = mysqli_fetch_assoc($passengers_res)):
                ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['passenger_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['age']); ?> yrs</td>
                        <td><?php echo htmlspecialchars($p['gender']); ?></td>
                        <td><span style="background:#e9ecef; padding:4px 8px; border-radius:4px; font-weight:bold; font-family:monospace;"><?php echo htmlspecialchars($p['seat_number']); ?></span></td>
                        <td>
                            <?php if ($booking['status'] === 'Confirmed'): ?>
                                <span style="color:var(--success-color); font-weight:bold;">CNF</span>
                            <?php elseif ($booking['status'] === 'Pending'): ?>
                                <span style="color:#b7791f; font-weight:bold;">PND</span>
                            <?php else: ?>
                                <span style="color:var(--danger-color); font-weight:bold;">CAN</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Admin Action Controls -->
    <?php
    $is_past_journey = (strtotime($booking['journey_date']) < strtotime(date('Y-m-d')));
    if ($booking['status'] === 'Confirmed'):
        if (!$is_past_journey):
    ?>
        <div style="border-top:2px solid #e2e8f0; padding-top:1rem; display:flex; justify-content:flex-end;">
            <a href="../users/confirm_cancel.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-danger">Admin Cancel Booking</a>
        </div>
    <?php else: ?>
        <div style="border-top:2px solid #e2e8f0; padding-top:1rem; display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted); font-size:0.88rem;">Journey Date has passed. Cancellation period closed.</span>
            <span class="badge" style="background:#059669; color:#fff;">Journey Completed</span>
        </div>
    <?php
        endif;
    endif;
    ?>
</div>

<?php include '../includes/footer.php'; ?>
