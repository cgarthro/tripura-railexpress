<?php
// admin/confirm_delete_train.php - Secure Train Deletion Confirmation Screen
require_once '../config/db_connect.php';

// Auth Guard: Admin access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$train_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : (isset($_REQUEST['train_id']) ? intval($_REQUEST['train_id']) : 0);

if ($train_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch Train details
$stmt = mysqli_prepare($conn, "SELECT * FROM trains WHERE train_id = ? AND is_deleted = 0");
mysqli_stmt_bind_param($stmt, "i", $train_id);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$train = mysqli_fetch_assoc($res);

if (!$train) {
    die("<div style='padding:2rem; font-family:sans-serif; text-align:center;'><h2>Train Not Found ❌</h2><p>This train schedule does not exist or was already deleted.</p><a href='dashboard.php'>Return to Dashboard</a></div>");
}

// Fetch dynamic route summary info
$stops_query = mysqli_query($conn, "SELECT station_name, departure_time, arrival_time, segment_fare FROM train_stops WHERE train_id = $train_id ORDER BY stop_order ASC");
$stops = [];
$total_fare = 0.00;
while ($row = mysqli_fetch_assoc($stops_query)) {
    $stops[] = $row;
    $total_fare += floatval($row['segment_fare']);
}

$source = count($stops) > 0 ? $stops[0]['station_name'] : 'Not Configured';
$dest   = count($stops) > 0 ? $stops[count($stops) - 1]['station_name'] : 'Not Configured';
$dep    = count($stops) > 0 ? (!empty($stops[0]['departure_time']) ? date('h:i A', strtotime($stops[0]['departure_time'])) : '-') : '-';
$arr    = count($stops) > 0 ? (!empty($stops[count($stops) - 1]['arrival_time']) ? date('h:i A', strtotime($stops[count($stops) - 1]['arrival_time'])) : '-') : '-';

// Count affected active bookings
$b_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM bookings WHERE train_id = ? AND status IN ('Confirmed', 'Pending')");
mysqli_stmt_bind_param($b_stmt, "i", $train_id);
mysqli_stmt_execute($b_stmt);
$b_count = mysqli_fetch_assoc(mysqli_stmt_get_result($b_stmt))['count'];

include '../includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="border-top: 5px solid var(--danger-color);">
        <h2 class="card-title" style="color:var(--danger-color);">Confirm Train Deletion</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">You are about to discontinue and hide the following train schedule from new bookings.</p>

        <!-- Train Summary -->
        <div style="background:#fff5f5; padding:1.2rem; border-radius:8px; border:1px solid #feb2b2; margin-bottom:1.5rem;">
            <h3 style="color:var(--primary-color); margin-bottom:5px;">#<?php echo htmlspecialchars($train['train_number']); ?> - <?php echo htmlspecialchars($train['train_name']); ?></h3>
            <p>Route: <strong><?php echo htmlspecialchars($source); ?> ➔ <?php echo htmlspecialchars($dest); ?></strong></p>
            <p>Timings: <strong><?php echo $dep; ?> - <?php echo $arr; ?></strong></p>
            <p>Full Route Fare: <strong>₹<?php echo number_format($total_fare, 2); ?></strong></p>
        </div>

        <!-- Deletion Warning Metrics -->
        <div class="alert alert-danger" style="margin-bottom:1.5rem;">
            <strong>Impact Assessment:</strong><br>
            • There are currently <strong><?php echo $b_count; ?> active booking(s)</strong> linked to this train schedule.<br>
            • Proceeding will automatically mark all associated reservations as <strong>"Cancelled (Train Discontinued)"</strong>.<br>
            • Passengers will see the cancelled status in their dashboards.
        </div>

        <form method="POST" action="delete_train.php?id=<?php echo $train_id; ?>">
            <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
            <div style="display:flex; gap:15px; justify-content:flex-end;">
                <a href="dashboard.php" class="btn btn-secondary">Cancel (Go Back)</a>
                <button type="submit" class="btn btn-danger">Delete Train & Cancel Bookings</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
