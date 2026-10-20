<?php
// admin/dashboard.php - Admin Control Panel
require_once '../config/db_connect.php';

// Auth Guard: Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div style='padding:2rem; font-family:sans-serif; text-align:center;'><h2>Access Denied 🔒</h2><p>Admin login required (<code>admin</code> / <code>admin123</code>).</p><a href='../login.php'>Go to Login Page</a></div>");
}

// Fetch Stats (excluding soft-deleted trains)
$total_trains   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM trains WHERE is_deleted = 0"))['count'];
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings"))['count'];
$total_revenue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_fare) as sum FROM bookings WHERE status = 'Confirmed'"))['sum'] ?? 0;
$total_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'user'"))['count'];

// Fetch all trains dynamically with first stop, last stop, timing, day, and fare sum
$trains_result = mysqli_query($conn, "
    SELECT t.*,
           (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as source_station,
           (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as destination_station,
           (SELECT departure_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as departure_time,
           (SELECT arrival_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_time,
           (SELECT arrival_day FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_day,
           (SELECT SUM(segment_fare) FROM train_stops WHERE train_id = t.train_id) as fare
    FROM trains t
    WHERE t.is_deleted = 0
    ORDER BY t.train_number ASC
");

// Fetch recent bookings
$recent_bookings = mysqli_query($conn, "
    SELECT b.*, t.train_number, t.train_name, u.full_name, u.email
    FROM bookings b
    LEFT JOIN trains t ON b.train_id = t.train_id
    JOIN users u ON b.user_id = u.user_id
    ORDER BY b.booking_date DESC LIMIT 5
");

include '../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
        <h2>Administrator Panel</h2>
        <p>Manage train schedules, intermediate route stops and view booking details.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="add_train.php" class="btn btn-success">+ Add Train Schedule</a>
        <a href="all_bookings.php" class="btn btn-primary">View All Bookings</a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">✓ Train schedule deleted successfully.</div>
<?php endif; ?>

<!-- Stats Grid -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.2rem; margin-bottom:2rem;">
    <div class="card" style="padding:1.2rem; border-left:5px solid var(--primary-color);">
        <small style="color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Total Trains</small>
        <h3 style="font-size:2rem; color:var(--primary-color); margin-top:5px;"><?php echo $total_trains; ?></h3>
    </div>
    <div class="card" style="padding:1.2rem; border-left:5px solid var(--secondary-color);">
        <small style="color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Total Bookings</small>
        <h3 style="font-size:2rem; color:var(--secondary-color); margin-top:5px;"><?php echo $total_bookings; ?></h3>
    </div>
    <div class="card" style="padding:1.2rem; border-left:5px solid var(--success-color);">
        <small style="color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Total Revenue</small>
        <h3 style="font-size:2rem; color:var(--success-color); margin-top:5px;">₹<?php echo number_format($total_revenue, 2); ?></h3>
    </div>
    <a href="users_list.php" class="card" style="padding:1.2rem; border-left:5px solid #f39c12; text-decoration:none; display:block; cursor:pointer;" title="View Complete Registered Users List">
        <small style="color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Registered Users →</small>
        <h3 style="font-size:2rem; color:#f39c12; margin-top:5px;"><?php echo $total_users; ?></h3>
    </a>
</div>

<!-- Train Schedules & Intermediate Route Management -->
<div class="card">
    <h3 class="card-title">Active Trains & Route Stops</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Train No / Name</th>
                    <th>Full Route</th>
                    <th>Timing</th>
                    <th>Operating Days</th>
                    <th>Seating Capacity</th>
                    <th>Base Fare</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = mysqli_fetch_assoc($trains_result)): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo htmlspecialchars($t['train_number']); ?></strong><br>
                            <span style="color:var(--text-muted); font-size:0.9rem;"><?php echo htmlspecialchars($t['train_name']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($t['source_station'] ?? 'Not Configured'); ?> ➔ <?php echo htmlspecialchars($t['destination_station'] ?? 'Not Configured'); ?></td>
                        <td>
                            <?php echo !empty($t['departure_time']) ? date('h:i A', strtotime($t['departure_time'])) : '-'; ?> -
                            <?php echo !empty($t['arrival_time']) ? date('h:i A', strtotime($t['arrival_time'])) : '-'; ?>
                            <?php if (isset($t['arrival_day']) && $t['arrival_day'] > 0): ?>
                                <small style="color:var(--text-muted); font-weight:bold;">(Day <?php echo ($t['arrival_day'] + 1); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo render_running_days($t['running_days'] ?? ''); ?>
                        </td>
                        <td>
                            <span style="font-size:0.85rem;">
                                GN: <strong><?php echo $t['seats_gn']; ?></strong> |
                                2S: <strong><?php echo $t['seats_2s']; ?></strong> |
                                SL: <strong><?php echo $t['seats_sl']; ?></strong> |
                                CC: <strong><?php echo $t['seats_cc']; ?></strong> |
                                3AC: <strong><?php echo $t['seats_3ac']; ?></strong>
                            </span>
                        </td>
                        <td><strong>₹<?php echo number_format($t['fare'] ?? 0, 2); ?></strong></td>
                        <td>
                            <a href="edit_train.php?id=<?php echo $t['train_id']; ?>" class="btn btn-primary" style="padding:6px 14px; font-size:0.85rem; font-weight:bold;">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 class="card-title" style="margin-bottom:0;">📋 Recent Ticket Reservations</h3>
        <a href="all_bookings.php" style="color:var(--primary-color); font-weight:bold;">View All →</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>PNR</th>
                    <th>User Details</th>
                    <th>Train Info</th>
                    <th>Route</th>
                    <th>Journey Date</th>
                    <th>Fare</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($b = mysqli_fetch_assoc($recent_bookings)): ?>
                    <tr>
                        <td><strong style="font-family:monospace; color:var(--primary-color);"><?php echo htmlspecialchars($b['pnr_number']); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($b['full_name']); ?></strong><br>
                            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($b['email']); ?></small>
                        </td>
                        <td>#<?php echo htmlspecialchars($b['train_number'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($b['train_name'] ?? 'Discontinued'); ?></td>
                        <td>
                            <small style="color:var(--primary-color); font-weight:bold;">
                                <?php echo !empty($b['boarding_station']) ? htmlspecialchars($b['boarding_station']) : ''; ?> ➔
                                <?php echo !empty($b['deboarding_station']) ? htmlspecialchars($b['deboarding_station']) : ''; ?>
                            </small>
                        </td>
                        <td>📅 <?php echo date('d-M-Y', strtotime($b['journey_date'])); ?></td>
                        <td><strong>₹<?php echo number_format($b['total_fare'], 2); ?></strong></td>
                        <td>
                            <?php if ($b['status'] === 'Confirmed'): ?>
                                <span class="badge badge-confirmed">Confirmed</span>
                            <?php elseif ($b['status'] === 'Pending'): ?>
                                <span class="badge" style="background:#f39c12; color:#fff;">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled">Cancelled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
