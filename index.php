<?php
// index.php - Tripura Railway Homepage, Train Search & PNR Tracker
require_once 'config/db_connect.php';

$source      = isset($_GET['source']) ? trim($_GET['source']) : '';
$destination = isset($_GET['destination']) ? trim($_GET['destination']) : '';
$journey_date= isset($_GET['journey_date']) ? $_GET['journey_date'] : date('Y-m-d');
$pnr_search  = isset($_GET['pnr_search']) ? trim($_GET['pnr_search']) : '';

// Validate journey date is not in the past
if (strtotime($journey_date) < strtotime(date('Y-m-d'))) {
    $journey_date = date('Y-m-d');
}

$pnr_result = null;
if (!empty($pnr_search)) {
    $pnr_stmt = mysqli_prepare($conn, "
        SELECT b.*, t.train_number, t.train_name,
               (SELECT departure_time FROM train_stops WHERE train_id = b.train_id ORDER BY stop_order ASC LIMIT 1) as departure_time,
               t.is_deleted as train_deleted
        FROM bookings b
        LEFT JOIN trains t ON b.train_id = t.train_id
        WHERE b.pnr_number = ?
    ");
    mysqli_stmt_bind_param($pnr_stmt, "s", $pnr_search);
    mysqli_stmt_execute($pnr_stmt);
    $pnr_result = mysqli_fetch_assoc(mysqli_stmt_get_result($pnr_stmt));
}

// Build SQL Query based on Intermediate Stops or Main Stations
if (!empty($source) && !empty($destination)) {
    $query = "
        SELECT t.*,
               s1.station_name as board_station,
               s2.station_name as deboard_station,
               s1.departure_time as departure_time,
               s2.arrival_time as arrival_time,
               s2.arrival_day as arrival_day,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as source_station,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as destination_station,
               (SELECT SUM(segment_fare) FROM train_stops WHERE train_id = t.train_id AND stop_order > s1.stop_order AND stop_order <= s2.stop_order) as calc_fare,
               ((t.seats_gn + t.seats_2s + t.seats_sl + t.seats_cc + t.seats_3ac) -
                COALESCE((SELECT SUM(total_passengers) FROM bookings WHERE train_id = t.train_id AND journey_date = ? AND status != 'Cancelled'), 0)
               ) as available_seats
        FROM trains t
        JOIN train_stops s1 ON t.train_id = s1.train_id
        JOIN train_stops s2 ON t.train_id = s2.train_id
        WHERE s1.station_name LIKE ?
          AND s2.station_name LIKE ?
          AND s2.stop_order > s1.stop_order
          AND t.is_deleted = 0
        ORDER BY s1.departure_time ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    $src_param = "%" . $source . "%";
    $dst_param = "%" . $destination . "%";
    mysqli_stmt_bind_param($stmt, "sss", $journey_date, $src_param, $dst_param);
} elseif (!empty($source)) {
    $query = "
        SELECT DISTINCT t.*,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as source_station,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as destination_station,
               (SELECT departure_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as departure_time,
               (SELECT arrival_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_time,
               (SELECT arrival_day FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_day,
               (SELECT SUM(segment_fare) FROM train_stops WHERE train_id = t.train_id) as fare,
               ((t.seats_gn + t.seats_2s + t.seats_sl + t.seats_cc + t.seats_3ac) -
                COALESCE((SELECT SUM(total_passengers) FROM bookings WHERE train_id = t.train_id AND journey_date = ? AND status != 'Cancelled'), 0)
               ) as available_seats
        FROM trains t
        JOIN train_stops s ON t.train_id = s.train_id
        WHERE s.station_name LIKE ?
          AND t.is_deleted = 0
        ORDER BY departure_time ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    $src_param = "%" . $source . "%";
    mysqli_stmt_bind_param($stmt, "ss", $journey_date, $src_param);
} elseif (!empty($destination)) {
    $query = "
        SELECT DISTINCT t.*,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as source_station,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as destination_station,
               (SELECT departure_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as departure_time,
               (SELECT arrival_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_time,
               (SELECT arrival_day FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_day,
               (SELECT SUM(segment_fare) FROM train_stops WHERE train_id = t.train_id) as fare,
               ((t.seats_gn + t.seats_2s + t.seats_sl + t.seats_cc + t.seats_3ac) -
                COALESCE((SELECT SUM(total_passengers) FROM bookings WHERE train_id = t.train_id AND journey_date = ? AND status != 'Cancelled'), 0)
               ) as available_seats
        FROM trains t
        JOIN train_stops s ON t.train_id = s.train_id
        WHERE s.station_name LIKE ?
          AND t.is_deleted = 0
        ORDER BY departure_time ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    $dst_param = "%" . $destination . "%";
    mysqli_stmt_bind_param($stmt, "ss", $journey_date, $dst_param);
} else {
    $query = "
        SELECT t.*,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as source_station,
               (SELECT station_name FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as destination_station,
               (SELECT departure_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order ASC LIMIT 1) as departure_time,
               (SELECT arrival_time FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_time,
               (SELECT arrival_day FROM train_stops WHERE train_id = t.train_id ORDER BY stop_order DESC LIMIT 1) as arrival_day,
               (SELECT SUM(segment_fare) FROM train_stops WHERE train_id = t.train_id) as fare,
               ((t.seats_gn + t.seats_2s + t.seats_sl + t.seats_cc + t.seats_3ac) -
                COALESCE((SELECT SUM(total_passengers) FROM bookings WHERE train_id = t.train_id AND journey_date = ? AND status != 'Cancelled'), 0)
               ) as available_seats
        FROM trains t
        WHERE t.is_deleted = 0
        ORDER BY t.train_number ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $journey_date);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

include 'includes/header.php';
?>

<div class="page-header">
    <h2>Tripura RailExpress - Railway Ticket Booking System</h2>
    <p>Search schedules, station stops, calculate fares and track PNR booking status.</p>
</div>

<!-- Search & PNR Tracker Grid -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.2rem; margin-bottom:1.5rem;" class="responsive-grid">

    <!-- Left: Search Form Card -->
    <div class="card" style="margin-bottom:0;">
        <h3 class="card-title">Search Trains</h3>
        <form method="GET" action="index.php">
            <div class="form-grid">
                <div class="form-group">
                    <label for="source">From Station (Boarding)</label>
                    <input type="text" name="source" id="source" class="form-control" placeholder="e.g. Agartala, Ambassa or Dharmanagar" value="<?php echo htmlspecialchars($source); ?>">
                </div>
                <div class="form-group">
                    <label for="destination">To Station (Destination)</label>
                    <input type="text" name="destination" id="destination" class="form-control" placeholder="e.g. Sealdah, Guwahati or Sabroom" value="<?php echo htmlspecialchars($destination); ?>">
                </div>
                <div class="form-group">
                    <label for="journey_date">Journey Date</label>
                    <input type="date" name="journey_date" id="journey_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($journey_date); ?>" onchange="this.form.submit()">
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:0.8rem;">
                <button type="submit" class="btn btn-primary">Find Trains</button>
                <a href="index.php" class="btn btn-secondary">Reset Search</a>
            </div>
        </form>
    </div>

    <!-- Right: Check PNR Status Card -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h3 class="card-title">PNR Status Tracker</h3>
            <form method="GET" action="index.php">
                <div class="form-group">
                    <label for="pnr_search">Enter 10-Digit PNR Number</label>
                    <input type="text" name="pnr_search" id="pnr_search" class="form-control" placeholder="e.g. PNR9861085" value="<?php echo htmlspecialchars($pnr_search); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Get Status →</button>
            </form>
        </div>

        <?php if (!empty($pnr_search)): ?>
            <div style="margin-top:1rem; padding:0.8rem; border-radius:6px; background:#f8f9fa; border:1px solid #cbd5e1;">
                <?php if ($pnr_result): ?>
                    <small style="color:var(--text-muted);">PNR Number:</small> <strong><?php echo htmlspecialchars($pnr_result['pnr_number']); ?></strong><br>
                    <small style="color:var(--text-muted);">Train:</small> <strong><?php echo htmlspecialchars($pnr_result['train_name'] ?? 'Discontinued'); ?></strong> (#<?php echo htmlspecialchars($pnr_result['train_number'] ?? 'N/A'); ?>)<br>
                    <small style="color:var(--text-muted);">Journey Date:</small> <?php echo date('d-M-Y', strtotime($pnr_result['journey_date'])); ?><br>
                    <small style="color:var(--text-muted);">Status:</small>
                    <?php if ($pnr_result['status'] === 'Confirmed'): ?>
                        <span class="badge badge-confirmed" style="padding: 2px 6px; font-size: 0.78rem;">Confirmed</span>
                    <?php elseif ($pnr_result['status'] === 'Pending'): ?>
                        <span class="badge" style="background:#f39c12; color:#fff; padding: 2px 6px; font-size: 0.78rem;">Payment Pending</span>
                    <?php else: ?>
                        <span class="badge badge-cancelled" style="padding: 2px 6px; font-size: 0.78rem;">Cancelled</span>
                    <?php endif; ?>
                    <?php if (isset($pnr_result['train_deleted']) && $pnr_result['train_deleted'] == 1): ?>
                        <div style="color:var(--danger-color); font-weight:bold; font-size:0.75rem; margin-top:4px;">(Train Cancelled/Removed)</div>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:var(--danger-color); font-weight:bold; font-size:0.9rem;">✕ PNR Record Not Found.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Trains Schedule Results -->
<div class="card">
    <h3 class="card-title">Tripura Train Schedules & Segment-Based Fares (Date: <?php echo date('d-M-Y', strtotime($journey_date)); ?>)</h3>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Train No / Name</th>
                        <th>Route Schedule</th>
                        <th>Timings</th>
                        <th>Operating Days</th>
                        <th>Seats Available</th>
                        <th>Segment Fare</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $search_day = date('D', strtotime($journey_date));
                    while ($row = mysqli_fetch_assoc($result)):
                        $fare_val = isset($row['calc_fare']) ? $row['calc_fare'] : $row['fare'];
                        if ($fare_val <= 0) $fare_val = 30.00;

                        $active_days_arr = array_map('trim', explode(',', $row['running_days'] ?? ''));
                        $runs_on_date = in_array($search_day, $active_days_arr);
                    ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($row['train_number']); ?></strong><br>
                                <span style="font-size:0.9rem; color:var(--text-muted);"><?php echo htmlspecialchars($row['train_name']); ?></span>
                            </td>
                            <td>
                                <span><?php echo htmlspecialchars($row['source_station'] ?? 'Not Configured'); ?> ➔ <?php echo htmlspecialchars($row['destination_station'] ?? 'Not Configured'); ?></span><br>
                                <?php if (isset($row['board_station']) && isset($row['deboard_station'])): ?>
                                    <small style="color:var(--primary-color); font-weight:bold;">Searched Segment: <?php echo htmlspecialchars($row['board_station']); ?> ➔ <?php echo htmlspecialchars($row['deboard_station']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span>
                                    <?php echo !empty($row['departure_time']) ? date('h:i A', strtotime($row['departure_time'])) : '-'; ?> -
                                    <?php echo !empty($row['arrival_time']) ? date('h:i A', strtotime($row['arrival_time'])) : '-'; ?>
                                </span>
                                <?php if (isset($row['arrival_day']) && $row['arrival_day'] > 0): ?>
                                    <br><small style="color:var(--text-muted);">(Day <?php echo ($row['arrival_day'] + 1); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo render_running_days($row['running_days'] ?? ''); ?>
                            </td>
                            <td>
                                <strong style="color:var(--success-color);"><?php echo max(0, intval($row['available_seats'])); ?> Seats Available</strong>
                            </td>
                            <td>
                                <strong style="color:var(--success-color);">₹<?php echo number_format($fare_val, 2); ?></strong>
                            </td>
                            <td>
                                <?php if ($runs_on_date): ?>
                                    <?php
                                    $book_url = "book.php?train_id=" . $row['train_id'] . "&date=" . urlencode($journey_date);
                                    if (isset($row['board_station'])) $book_url .= "&source=" . urlencode($row['board_station']);
                                    if (isset($row['deboard_station'])) $book_url .= "&dest=" . urlencode($row['deboard_station']);
                                    ?>
                                    <a href="<?php echo $book_url; ?>" class="btn btn-success" style="padding:6px 12px; font-size:0.9rem; font-weight:bold;">Book Ticket</a>
                                <?php else: ?>
                                    <span class="btn btn-secondary" style="padding:6px 10px; font-size:0.8rem; opacity:0.75; cursor:not-allowed; background:#94a3b8;" title="Train does not operate on <?php echo date('l', strtotime($journey_date)); ?>s">🚫 Doesn't Run on <?php echo $search_day; ?>s</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">✕ No trains found matching your search route criteria. Try searching other stations.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
