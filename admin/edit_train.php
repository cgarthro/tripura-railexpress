<?php
// admin/edit_train.php - Unified Train Details, Class Capacities & Route Stops Editor (PRG Pattern)
require_once '../config/db_connect.php';

// Auth Guard: Admin access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$error = '';
$success = '';
$train_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : (isset($_REQUEST['train_id']) ? intval($_REQUEST['train_id']) : 0);

if ($train_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// -------------------------------------------------------------
// 1. Action: Remove Intermediate Stop (PRG Pattern)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stop'])) {
    $stop_id = isset($_POST['stop_id']) ? intval($_POST['stop_id']) : 0;

    if ($stop_id > 0) {
        $d_stmt = mysqli_prepare($conn, "DELETE FROM train_stops WHERE stop_id = ? AND train_id = ?");
        mysqli_stmt_bind_param($d_stmt, "ii", $stop_id, $train_id);
        mysqli_stmt_execute($d_stmt);

        // Re-index remaining stop orders sequentially (1, 2, 3...)
        $all_s = mysqli_query($conn, "SELECT stop_id FROM train_stops WHERE train_id = $train_id ORDER BY stop_order ASC");
        $idx = 1;
        while ($st_row = mysqli_fetch_assoc($all_s)) {
            $sid = $st_row['stop_id'];
            mysqli_query($conn, "UPDATE train_stops SET stop_order = $idx WHERE stop_id = $sid");
            $idx++;
        }

        header("Location: edit_train.php?id=" . $train_id . "&msg=stop_deleted");
        exit();
    }
}

// -------------------------------------------------------------
// 2. Action: Update Train Details & Capacities (PRG Pattern)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_train_info'])) {
    $train_number = trim($_POST['train_number']);
    $train_name   = trim($_POST['train_name']);
    $seats_gn     = intval($_POST['seats_gn']);
    $seats_2s     = intval($_POST['seats_2s']);
    $seats_sl     = intval($_POST['seats_sl']);
    $seats_cc     = intval($_POST['seats_cc']);
    $seats_3ac    = intval($_POST['seats_3ac']);
    $days_array   = isset($_POST['running_days']) ? $_POST['running_days'] : [];
    $running_days = implode(',', $days_array);

    if (empty($train_number) || empty($train_name) || empty($running_days) || $seats_gn < 0 || $seats_2s < 0 || $seats_sl < 0 || $seats_cc < 0 || $seats_3ac < 0) {
        $error = "Please fill in all train info fields with valid non-negative values and select at least one operating day.";
    } else {
        $u_stmt = mysqli_prepare($conn, "
            UPDATE trains
            SET train_number = ?, train_name = ?, seats_gn = ?, seats_2s = ?, seats_sl = ?, seats_cc = ?, seats_3ac = ?, running_days = ?
            WHERE train_id = ?
        ");
        mysqli_stmt_bind_param($u_stmt, "ssiiiiisi", $train_number, $train_name, $seats_gn, $seats_2s, $seats_sl, $seats_cc, $seats_3ac, $running_days, $train_id);

        if (mysqli_stmt_execute($u_stmt)) {
            header("Location: edit_train.php?id=" . $train_id . "&msg=info_updated");
            exit();
        } else {
            $error = "Error updating train info: " . mysqli_error($conn);
        }
    }
}

// -------------------------------------------------------------
// 3. Action: Add New Route Stop Segment (PRG Pattern)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stop_info'])) {
    $station_name  = trim($_POST['station_name']);
    $stop_order    = intval($_POST['stop_order']);
    $arr_time      = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : NULL;
    $dep_time      = !empty($_POST['departure_time']) ? $_POST['departure_time'] : NULL;
    $arr_day       = intval($_POST['arrival_day']);
    $dep_day       = intval($_POST['departure_day']);
    $segment_fare  = floatval($_POST['segment_fare']);

    if (empty($station_name) || $stop_order <= 0 || $segment_fare < 0) {
        $error = "Please enter valid station details and segment fares.";
    } else {
        // Shift existing stops if there is a duplicate order
        $chk_order = mysqli_query($conn, "SELECT stop_id FROM train_stops WHERE train_id = $train_id AND stop_order = $stop_order");
        if (mysqli_num_rows($chk_order) > 0) {
            mysqli_query($conn, "UPDATE train_stops SET stop_order = stop_order + 1 WHERE train_id = $train_id AND stop_order >= $stop_order");
        }

        $ins_stmt = mysqli_prepare($conn, "INSERT INTO train_stops (train_id, station_name, stop_order, arrival_time, departure_time, arrival_day, departure_day, segment_fare) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "isissiid", $train_id, $station_name, $stop_order, $arr_time, $dep_time, $arr_day, $dep_day, $segment_fare);

        if (mysqli_stmt_execute($ins_stmt)) {
            header("Location: edit_train.php?id=" . $train_id . "&msg=stop_added");
            exit();
        } else {
            $error = "Error adding station stop: " . mysqli_error($conn);
        }
    }
}

// Handle GET Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = "Train schedule created successfully! Add intermediate station stops below.";
    } elseif ($_GET['msg'] === 'stop_deleted') {
        $success = "Station stop removed and route sequence re-indexed!";
    } elseif ($_GET['msg'] === 'stop_added') {
        $success = "New station stop segment added to route successfully!";
    } elseif ($_GET['msg'] === 'info_updated') {
        $success = "Train header details and seat capacities updated!";
    }
}

// Fetch Train details
$stmt = mysqli_prepare($conn, "SELECT * FROM trains WHERE train_id = ? AND is_deleted = 0");
mysqli_stmt_bind_param($stmt, "i", $train_id);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$train = mysqli_fetch_assoc($res);

if (!$train) {
    die("<div style='padding:2rem; font-family:sans-serif; text-align:center;'><h2>Train Schedule Not Found ❌</h2><p>This train schedule was deleted or does not exist.</p><a href='dashboard.php'>Return to Dashboard</a></div>");
}

// Fetch intermediate stops ordered by stop_order ASC
$stops_stmt = mysqli_prepare($conn, "SELECT * FROM train_stops WHERE train_id = ? ORDER BY stop_order ASC");
mysqli_stmt_bind_param($stops_stmt, "i", $train_id);
mysqli_stmt_execute($stops_stmt);
$stops_result = mysqli_stmt_get_result($stops_stmt);
$total_stops  = mysqli_num_rows($stops_result);

// Auto-suggest next sequential stop order number
$next_order = $total_stops + 1;

// Dynamically compute full route summary info
$dynamic_source = "Not Configured";
$dynamic_dest   = "Not Configured";
$dynamic_dep    = "-";
$dynamic_arr    = "-";
$dynamic_total_fare = 0.00;

if ($total_stops > 0) {
    mysqli_data_seek($stops_result, 0);
    $stops_array = [];
    while ($row = mysqli_fetch_assoc($stops_result)) {
        $stops_array[] = $row;
        $dynamic_total_fare += floatval($row['segment_fare']);
    }

    $dynamic_source = $stops_array[0]['station_name'];
    $dynamic_dep    = !empty($stops_array[0]['departure_time']) ? date('h:i A', strtotime($stops_array[0]['departure_time'])) : '-';

    $last_stop   = $stops_array[count($stops_array) - 1];
    $dynamic_dest= $last_stop['station_name'];
    $dynamic_arr = !empty($last_stop['arrival_time']) ? date('h:i A', strtotime($last_stop['arrival_time'])) : '-';
    if ($last_stop['arrival_day'] > 0) {
        $dynamic_arr .= " (Day " . ($last_stop['arrival_day'] + 1) . ")";
    }
}

include '../includes/header.php';
?>

<div style="max-width: 900px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1.2rem; display:flex; justify-content:space-between; align-items:center;">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        <a href="confirm_delete_train.php?id=<?php echo $train_id; ?>" class="btn btn-danger">Delete Train Schedule</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">✓ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Card 1: Train Details & Class Seat Quotas -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title">Edit Train Schedule & Class Quotas (#<?php echo htmlspecialchars($train['train_number']); ?>)</h2>

        <form method="POST" action="edit_train.php?id=<?php echo $train_id; ?>">
            <input type="hidden" name="update_train_info" value="1">
            <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="train_number">Train Number</label>
                    <input type="text" name="train_number" id="train_number" class="form-control" required value="<?php echo htmlspecialchars($train['train_number']); ?>">
                </div>

                <div class="form-group">
                    <label for="train_name">Train Name</label>
                    <input type="text" name="train_name" id="train_name" class="form-control" required value="<?php echo htmlspecialchars($train['train_name']); ?>">
                </div>
            </div>

            <h4 style="margin: 1.5rem 0 0.8rem; color:var(--primary-color);">Days of Operation (Running Schedule)</h4>
            <div style="background:#f8f9fa; padding:1rem; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <label style="font-weight:600;">Operating Days:</label>
                    <?php echo render_running_days($train['running_days']); ?>
                </div>
                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:8px;">
                    <?php
                    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    $active_days = array_map('trim', explode(',', $train['running_days'] ?? ''));
                    foreach ($weekdays as $day):
                        $checked = in_array($day, $active_days) ? 'checked' : '';
                    ?>
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-weight:bold;">
                            <input type="checkbox" name="running_days[]" value="<?php echo $day; ?>" <?php echo $checked; ?>>
                            <?php echo $day; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <h4 style="margin: 1.5rem 0 0.8rem; color:var(--primary-color);">Class Capacities (Max Seats Available)</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label for="seats_gn">General (GN)</label>
                    <input type="number" name="seats_gn" id="seats_gn" class="form-control" required min="0" value="<?php echo $train['seats_gn']; ?>">
                </div>
                <div class="form-group">
                    <label for="seats_2s">Second Seating (2S)</label>
                    <input type="number" name="seats_2s" id="seats_2s" class="form-control" required min="0" value="<?php echo $train['seats_2s']; ?>">
                </div>
                <div class="form-group">
                    <label for="seats_sl">Sleeper Class (SL)</label>
                    <input type="number" name="seats_sl" id="seats_sl" class="form-control" required min="0" value="<?php echo $train['seats_sl']; ?>">
                </div>
                <div class="form-group">
                    <label for="seats_cc">AC Chair Car (CC)</label>
                    <input type="number" name="seats_cc" id="seats_cc" class="form-control" required min="0" value="<?php echo $train['seats_cc']; ?>">
                </div>
                <div class="form-group">
                    <label for="seats_3ac">AC 3-Tier (3AC)</label>
                    <input type="number" name="seats_3ac" id="seats_3ac" class="form-control" required min="0" value="<?php echo $train['seats_3ac']; ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:10px; font-size:0.95rem; font-weight:bold; margin-top:1rem;">Save Train Info & Seat Limits</button>
        </form>
    </div>

    <!-- Card 2: Manage Segment-based Route Stops -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h3 class="card-title">📍 Intermediate Route Stops, Timings & Day Offsets</h3>

        <div style="background:#f8f9fa; border:1px solid #e2e8f0; padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div>
                <small style="color:var(--text-muted);">Dynamic Route Origin ➔ Terminus</small>
                <p style="font-weight:bold; color:var(--primary-color); font-size:1.05rem;"><?php echo htmlspecialchars($dynamic_source); ?> ➔ <?php echo htmlspecialchars($dynamic_dest); ?></p>
            </div>
            <div>
                <small style="color:var(--text-muted);">Timings Summary</small>
                <p style="font-weight:bold;">🕒 <?php echo $dynamic_dep; ?> - <?php echo $dynamic_arr; ?></p>
            </div>
            <div>
                <small style="color:var(--text-muted);">Dynamically Calculated Full Fare</small>
                <p style="font-weight:bold; color:var(--success-color);">₹<?php echo number_format($dynamic_total_fare, 2); ?></p>
            </div>
        </div>

        <div class="table-responsive" style="margin-bottom:1.5rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Stop #</th>
                        <th>Station Name</th>
                        <th>Arrival Time / Day</th>
                        <th>Departure Time / Day</th>
                        <th>Stoppage Halt</th>
                        <th>Segment Fare (from prev stop)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_stops > 0): ?>
                        <?php
                        mysqli_data_seek($stops_result, 0);
                        while ($s = mysqli_fetch_assoc($stops_result)):
                            $halt_str = '-';
                            if ($s['stop_order'] == 1) {
                                $halt_str = "🟢 Origin";
                            } elseif ($s['stop_order'] == $total_stops) {
                                $halt_str = "🔴 Terminus";
                            } elseif (!empty($s['arrival_time']) && !empty($s['departure_time'])) {
                                // Compute halt duration considering day offsets
                                $arr_mins = $s['arrival_day'] * 1440 + (intval(date('H', strtotime($s['arrival_time']))) * 60) + intval(date('i', strtotime($s['arrival_time'])));
                                $dep_mins = $s['departure_day'] * 1440 + (intval(date('H', strtotime($s['departure_time']))) * 60) + intval(date('i', strtotime($s['departure_time'])));
                                $diff_mins = $dep_mins - $arr_mins;
                                if ($diff_mins > 0) {
                                    $halt_str = "⏱️ " . $diff_mins . " Mins";
                                } else {
                                    $halt_str = "-";
                                }
                            }
                        ?>
                            <tr>
                                <td><strong>Stop #<?php echo $s['stop_order']; ?></strong></td>
                                <td><strong style="color:var(--primary-color);"><?php echo htmlspecialchars($s['station_name']); ?></strong></td>
                                <td>
                                    <?php echo !empty($s['arrival_time']) ? date('h:i A', strtotime($s['arrival_time'])) : '-'; ?>
                                    <?php if ($s['arrival_day'] > 0): ?><span class="badge" style="background:#64748b; font-size:0.7rem; padding:1px 4px;">Day <?php echo ($s['arrival_day'] + 1); ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($s['departure_time']) ? date('h:i A', strtotime($s['departure_time'])) : '-'; ?>
                                    <?php if ($s['departure_day'] > 0): ?><span class="badge" style="background:#64748b; font-size:0.7rem; padding:1px 4px;">Day <?php echo ($s['departure_day'] + 1); ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <span style="background:#e0f2fe; color:#0369a1; padding:3px 7px; border-radius:4px; font-size:0.8rem; font-weight:bold;"><?php echo $halt_str; ?></span>
                                </td>
                                <td><strong>₹<?php echo number_format($s['segment_fare'], 2); ?></strong></td>
                                <td>
                                    <form method="POST" action="edit_train.php?id=<?php echo $train_id; ?>" style="display:inline;">
                                        <input type="hidden" name="delete_stop" value="1">
                                        <input type="hidden" name="stop_id" value="<?php echo $s['stop_id']; ?>">
                                        <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.76rem;">❌ Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No route stoppages configured yet. Enter stops below sequentially.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Stop Segment Form -->
        <h4 style="margin-bottom:1rem; color:var(--primary-color);">➕ Add Route Stop Station</h4>
        <form method="POST" action="edit_train.php?id=<?php echo $train_id; ?>">
            <input type="hidden" name="add_stop_info" value="1">
            <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="station_name">Station Name</label>
                    <input type="text" name="station_name" id="station_name" class="form-control" required placeholder="e.g. Udaipur Tripura (UDPU)">
                </div>

                <div class="form-group">
                    <label for="stop_order">Stop Order (Sequence Number)</label>
                    <input type="number" name="stop_order" id="stop_order" class="form-control" required min="1" value="<?php echo $next_order; ?>">
                </div>

                <div class="form-group">
                    <label for="segment_fare">Segment Fare from Prev Stop (₹)</label>
                    <input type="number" step="0.01" name="segment_fare" id="segment_fare" class="form-control" required placeholder="For Stop #1 enter 0.00" value="<?php echo ($next_order == 1) ? '0.00' : ''; ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="arrival_time">Arrival Time</label>
                    <input type="time" name="arrival_time" id="arrival_time" class="form-control" placeholder="Ignore for Origin Stop">
                </div>

                <div class="form-group">
                    <label for="arrival_day">Arrival Journey Day</label>
                    <select name="arrival_day" id="arrival_day" class="form-control">
                        <option value="0">Day 1 (Departure Day)</option>
                        <option value="1">Day 2 (Next Day)</option>
                        <option value="2">Day 3</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="departure_time">Departure Time</label>
                    <input type="time" name="departure_time" id="departure_time" class="form-control" placeholder="Ignore for Terminus Stop">
                </div>

                <div class="form-group">
                    <label for="departure_day">Departure Journey Day</label>
                    <select name="departure_day" id="departure_day" class="form-control">
                        <option value="0">Day 1 (Departure Day)</option>
                        <option value="1">Day 2 (Next Day)</option>
                        <option value="2">Day 3</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding:10px 18px; font-weight:bold; margin-top:0.5rem;">➕ Save Stop & Add Segment</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
