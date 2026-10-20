<?php
// users/view_ticket.php - Printable Ticket Confirmation Receipt
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$pnr     = isset($_GET['pnr']) ? trim($_GET['pnr']) : '';
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'user';

if (empty($pnr)) {
    die("Error: PNR Number missing.");
}

// Fetch booking record dynamically
$stmt = mysqli_prepare($conn, "
    SELECT b.*, t.train_number, t.train_name,
           (SELECT departure_time FROM train_stops WHERE train_id = b.train_id AND station_name = b.boarding_station LIMIT 1) as departure_time,
           t.is_deleted as train_deleted, u.full_name as booked_by, u.email, u.phone
    FROM bookings b
    LEFT JOIN trains t ON b.train_id = t.train_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.pnr_number = ?
");
mysqli_stmt_bind_param($stmt, "s", $pnr);
mysqli_stmt_execute($stmt);
$booking_res = mysqli_stmt_get_result($stmt);
$booking     = mysqli_fetch_assoc($booking_res);

if (!$booking) {
    die("Ticket Not Found for PNR: " . htmlspecialchars($pnr));
}

// Privacy Guard
if ($role !== 'admin' && $booking['user_id'] != $user_id) {
    die("<div style='padding:2rem; font-family:sans-serif;'><h2>Access Denied 🔒</h2><p>You are only authorized to view your own booked tickets.</p><a href='my_bookings.php'>Return to My Bookings</a></div>");
}

// Fetch passenger list
$p_stmt = mysqli_prepare($conn, "SELECT * FROM passengers WHERE booking_id = ?");
mysqli_stmt_bind_param($p_stmt, "i", $booking['booking_id']);
mysqli_stmt_execute($p_stmt);
$passengers_res = mysqli_stmt_get_result($p_stmt);

include '../includes/header.php';
?>

<div style="max-width: 850px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;" class="no-print">
        <a href="my_bookings.php" class="btn btn-secondary">← Back to My Bookings</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Ticket PDF</button>
    </div>

    <div class="ticket-card">
        <div class="ticket-header">
            <div>
                <h2 style="color:var(--primary-color); font-size:1.6rem;">🚆 TRIPURA RAILWAY e-TICKET</h2>
                <p style="color:var(--text-muted); font-size:0.88rem;">Official Boarding Pass & Reservation Receipt</p>
            </div>
            <div class="pnr-box">
                <small style="color:var(--text-muted); text-transform:uppercase;">PNR Number</small><br>
                <span><?php echo htmlspecialchars($booking['pnr_number']); ?></span>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; background:#f8f9fa; padding:1.2rem; border-radius:8px; margin-bottom:1.5rem;">
            <div>
                <small style="color:var(--text-muted);">Train Name & Number</small>
                <p style="font-weight:bold; color:var(--primary-color);"><?php echo htmlspecialchars($booking['train_name'] ?? 'Discontinued'); ?> (#<?php echo htmlspecialchars($booking['train_number'] ?? 'N/A'); ?>)</p>
            </div>
            <div>
                <small style="color:var(--text-muted);">Boarding ➔ Destination</small>
                <p style="font-weight:bold; color:var(--primary-color);">
                    <?php echo htmlspecialchars($booking['boarding_station']); ?> ➔
                    <?php echo htmlspecialchars($booking['deboarding_station']); ?>
                </p>
            </div>
            <div>
                <small style="color:var(--text-muted);">Journey Date & Class</small>
                <p style="font-weight:bold;"><?php echo date('d-M-Y', strtotime($booking['journey_date'])); ?> | Class: <span style="color:var(--primary-color); font-weight:bold;"><?php echo htmlspecialchars($booking['travel_class'] ?? 'GN'); ?></span></p>
            </div>
            <div>
                <small style="color:var(--text-muted);">Status</small><br>
                <?php if ($booking['status'] === 'Confirmed'): ?>
                    <span class="badge badge-confirmed">Confirmed</span>
                <?php elseif ($booking['status'] === 'Pending'): ?>
                    <span class="badge" style="background:#f39c12; color:#fff;">Payment Pending</span>
                <?php else: ?>
                    <span class="badge badge-cancelled">Cancelled</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($booking['train_deleted']) && $booking['train_deleted'] == 1): ?>
            <div class="alert alert-danger" style="margin-bottom:1.5rem; border-left:5px solid var(--danger-color);">
                🚨 <strong>Train Schedule Discontinued:</strong> This ticket reservation is cancelled because the train schedule was discontinued or removed by the administration.
            </div>
        <?php endif; ?>

        <h4 style="margin-bottom:0.8rem; color:var(--primary-color);">Passenger Details</h4>
        <table class="data-table" style="margin-bottom:1.5rem;">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Passenger Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Coach/Seat Number</th>
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

        <div style="display:flex; justify-content:space-between; align-items:center; border-top:2px solid #e2e8f0; padding-top:1rem;">
            <div>
                <small style="color:var(--text-muted);">Booked By:</small> <strong><?php echo htmlspecialchars($booking['booked_by']); ?></strong> (<?php echo htmlspecialchars($booking['phone']); ?>)
            </div>
            <div style="text-align:right;">
                <span style="font-size:1rem; color:var(--text-muted);">Total Paid Fare:</span>
                <span style="font-size:1.4rem; font-weight:bold; color:var(--primary-color); margin-left:10px;">₹<?php echo number_format($booking['total_fare'], 2); ?></span>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
