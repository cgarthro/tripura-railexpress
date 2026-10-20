<?php
// admin/all_bookings.php - Admin View All System Reservations
require_once '../config/db_connect.php';

// Auth Guard: Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'All';
$today_str     = date('Y-m-d');

// Build SQL Query based on filter
$sql = "
    SELECT b.*, t.train_number, t.train_name, u.full_name, u.email, u.phone
    FROM bookings b
    LEFT JOIN trains t ON b.train_id = t.train_id
    JOIN users u ON b.user_id = u.user_id
";

if ($status_filter === 'Confirmed') {
    $sql .= " WHERE b.status = 'Confirmed' AND b.journey_date >= '$today_str'";
} elseif ($status_filter === 'Completed') {
    $sql .= " WHERE b.status = 'Confirmed' AND b.journey_date < '$today_str'";
} elseif ($status_filter === 'Pending') {
    $sql .= " WHERE b.status = 'Pending'";
} elseif ($status_filter === 'Cancelled') {
    $sql .= " WHERE b.status = 'Cancelled'";
}

$sql .= " ORDER BY b.booking_date DESC";

$all_bookings = mysqli_query($conn, $sql);

include '../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
        <h2>All System Reservations</h2>
        <p>Complete record of passenger bookings, payment details and status controls.</p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-secondary">← Admin Dashboard</a>
    </div>
</div>

<!-- Status Filter Tabs -->
<div style="display:flex; gap:10px; margin-bottom:1.5rem; flex-wrap:wrap;">
    <a href="all_bookings.php?status=All" class="btn <?php echo ($status_filter === 'All') ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size:0.88rem;">All Reservations</a>
    <a href="all_bookings.php?status=Confirmed" class="btn <?php echo ($status_filter === 'Confirmed') ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size:0.88rem;">Confirmed (Upcoming)</a>
    <a href="all_bookings.php?status=Completed" class="btn <?php echo ($status_filter === 'Completed') ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size:0.88rem;">Completed (Past)</a>
    <a href="all_bookings.php?status=Pending" class="btn <?php echo ($status_filter === 'Pending') ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size:0.88rem;">Pending Only</a>
    <a href="all_bookings.php?status=Cancelled" class="btn <?php echo ($status_filter === 'Cancelled') ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size:0.88rem;">Cancelled Only</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>PNR</th>
                    <th>Passenger Account</th>
                    <th>Train & Route</th>
                    <th>Journey Date</th>
                    <th>Passengers</th>
                    <th>Total Fare</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($all_bookings) > 0): ?>
                    <?php while ($b = mysqli_fetch_assoc($all_bookings)): ?>
                        <?php $is_past = (strtotime($b['journey_date']) < strtotime($today_str)); ?>
                        <tr>
                            <td><strong style="font-family:monospace; color:var(--primary-color);"><?php echo htmlspecialchars($b['pnr_number']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($b['full_name']); ?></strong><br>
                                <small style="color:var(--text-muted);"><?php echo htmlspecialchars($b['email']); ?></small>
                            </td>
                            <td>
                                #<?php echo htmlspecialchars($b['train_number'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($b['train_name'] ?? 'Discontinued'); ?><br>
                                <small style="color:var(--primary-color); font-weight:bold;">
                                    <?php echo htmlspecialchars($b['boarding_station']); ?> ➔
                                    <?php echo htmlspecialchars($b['deboarding_station']); ?>
                                </small>
                            </td>
                            <td><?php echo date('d-M-Y', strtotime($b['journey_date'])); ?></td>
                            <td><?php echo $b['total_passengers']; ?> Person(s)</td>
                            <td><strong>₹<?php echo number_format($b['total_fare'], 2); ?></strong></td>
                            <td>
                                <?php if ($b['status'] === 'Confirmed'): ?>
                                    <?php if ($is_past): ?>
                                        <span class="badge" style="background:#059669; color:#fff;">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-confirmed">Confirmed</span>
                                    <?php endif; ?>
                                <?php elseif ($b['status'] === 'Pending'): ?>
                                    <span class="badge" style="background:#f39c12; color:#fff;">Payment Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-cancelled">Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="booking_details.php?id=<?php echo $b['booking_id']; ?>" class="btn btn-primary" style="padding:4px 10px; font-size:0.82rem;">Inspect Manifest</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No reservations found for the selected filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
