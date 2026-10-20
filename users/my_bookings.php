<?php
// users/my_bookings.php - User Ticket Bookings Dashboard
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's bookings (LEFT JOIN to load bookings on soft-deleted trains)
$stmt = mysqli_prepare($conn, "
    SELECT b.*, t.train_number, t.train_name, t.is_deleted as train_deleted
    FROM bookings b
    LEFT JOIN trains t ON b.train_id = t.train_id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

include '../includes/header.php';
?>

<div class="page-header">
    <h2>📜 My Ticket Bookings</h2>
    <p>View your booked train tickets, complete pending payments, or process cancellations.</p>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
    <?php
    $cancelled_pnr = isset($_GET['pnr']) ? htmlspecialchars($_GET['pnr']) : 'Ticket';
    $refund_amount = isset($_GET['amount']) ? htmlspecialchars($_GET['amount']) : '';
    ?>
    <div class="alert alert-success" style="font-size:1rem; padding:1.2rem; border-left:5px solid var(--success-color);">
        ✓ <strong>Ticket #<?php echo $cancelled_pnr; ?> has been successfully cancelled.</strong><br>
        The refund of <strong>₹<?php echo $refund_amount; ?></strong> will be credited back to your original payment method within 24 hours.
    </div>
<?php endif; ?>

<?php
$upcoming = [];
$completed = [];
$cancelled = [];
$today_str = date('Y-m-d');

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['status'] === 'Cancelled') {
        $cancelled[] = $row;
    } elseif ($row['status'] === 'Confirmed' && strtotime($row['journey_date']) < strtotime($today_str)) {
        $completed[] = $row;
    } else {
        $upcoming[] = $row;
    }
}

function render_booking_table($rows, $type) {
    if (empty($rows)) return;
    ?>
    <div style="margin-bottom:2rem;">
        <h4 style="color:var(--primary-color); margin-bottom:0.8rem; border-bottom:2px solid #e2e8f0; padding-bottom:6px;">
            <?php
            if ($type === 'upcoming') echo "Upcoming Active Reservations";
            elseif ($type === 'completed') echo "Completed Journeys";
            else echo "Cancelled Reservations";
            ?>
        </h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PNR Number</th>
                        <th>Train & Route Details</th>
                        <th>Journey Date</th>
                        <th>Passengers</th>
                        <th>Total Fare</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--primary-color); font-size:1.05rem; font-family:monospace;"><?php echo htmlspecialchars($row['pnr_number']); ?></strong>
                            </td>
                            <td>
                                <strong>#<?php echo htmlspecialchars($row['train_number'] ?? 'N/A'); ?></strong> - <?php echo htmlspecialchars($row['train_name'] ?? 'Discontinued Train'); ?><br>
                                <small style="color:var(--primary-color); font-weight:bold;">
                                    <?php echo htmlspecialchars($row['boarding_station']); ?> ➔
                                    <?php echo htmlspecialchars($row['deboarding_station']); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo date('d-M-Y', strtotime($row['journey_date'])); ?>
                            </td>
                            <td>
                                <?php echo $row['total_passengers']; ?> Person(s)
                            </td>
                            <td>
                                <strong>₹<?php echo number_format($row['total_fare'], 2); ?></strong>
                            </td>
                            <td>
                                <?php if ($type === 'completed'): ?>
                                    <span class="badge" style="background:#059669; color:#fff;">Completed</span>
                                <?php elseif ($type === 'cancelled'): ?>
                                    <span class="badge badge-cancelled">Cancelled</span>
                                <?php elseif ($row['status'] === 'Confirmed'): ?>
                                    <span class="badge badge-confirmed">Confirmed</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f39c12; color:#ffffff;">Payment Pending</span>
                                <?php endif; ?>

                                <?php if (isset($row['train_deleted']) && $row['train_deleted'] == 1): ?>
                                    <br><small style="color:var(--danger-color); font-weight:bold;">(Train Cancelled/Removed)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'Confirmed'): ?>
                                    <a href="view_ticket.php?pnr=<?php echo $row['pnr_number']; ?>" class="btn btn-primary" style="padding:4px 10px; font-size:0.82rem;">View Ticket</a>
                                    <?php if ((!isset($row['train_deleted']) || $row['train_deleted'] != 1) && $type === 'upcoming'): ?>
                                        <a href="confirm_cancel.php?booking_id=<?php echo $row['booking_id']; ?>" class="btn btn-danger" style="padding:4px 10px; font-size:0.82rem;">Cancel Ticket</a>
                                    <?php endif; ?>
                                <?php elseif ($row['status'] === 'Pending'): ?>
                                    <?php if (!isset($row['train_deleted']) || $row['train_deleted'] != 1): ?>
                                        <a href="../payment/checkout.php?booking_id=<?php echo $row['booking_id']; ?>" class="btn btn-success" style="padding:4px 10px; font-size:0.82rem;">Complete Payment</a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.85rem;">Train Cancelled</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.85rem;">Ticket Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>

<div class="card">
    <h3 class="card-title">Your Booking History</h3>

    <?php if (count($upcoming) > 0 || count($completed) > 0 || count($cancelled) > 0): ?>
        <?php render_booking_table($upcoming, 'upcoming'); ?>
        <?php render_booking_table($completed, 'completed'); ?>
        <?php render_booking_table($cancelled, 'cancelled'); ?>
    <?php else: ?>
        <p style="color: var(--text-muted);">No booking history found. <a href="../index.php">Search trains to book a ticket.</a></p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
