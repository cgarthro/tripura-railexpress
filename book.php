<?php
// book.php - Ticket Reservation, Segment Fares & Dynamic Class-Date Quota
require_once 'config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$train_id     = isset($_GET['train_id']) ? intval($_GET['train_id']) : 0;
$journey_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$pref_source  = isset($_GET['source']) ? trim($_GET['source']) : '';
$pref_dest    = isset($_GET['dest']) ? trim($_GET['dest']) : '';

// Validate journey date is not in the past
if (strtotime($journey_date) < strtotime(date('Y-m-d'))) {
    $journey_date = date('Y-m-d');
}

// Fetch train details
$stmt = mysqli_prepare($conn, "SELECT * FROM trains WHERE train_id = ? AND is_deleted = 0");
mysqli_stmt_bind_param($stmt, "i", $train_id);
mysqli_stmt_execute($stmt);
$train_res = mysqli_stmt_get_result($stmt);
$train     = mysqli_fetch_assoc($train_res);

if (!$train) {
    die("Invalid train selection.");
}

// Fetch stops ordered by stop_order
$stops_stmt = mysqli_prepare($conn, "SELECT * FROM train_stops WHERE train_id = ? ORDER BY stop_order ASC");
mysqli_stmt_bind_param($stops_stmt, "i", $train_id);
mysqli_stmt_execute($stops_stmt);
$stops_res = mysqli_stmt_get_result($stops_stmt);

$route_stops = [];
while ($s = mysqli_fetch_assoc($stops_res)) {
    $route_stops[] = $s;
}

if (empty($route_stops)) {
    die("Train route is not configured. Please contact administration.");
}

// Build full route arrow chain array
$station_names_chain = array_map(function($st) {
    return $st['station_name'];
}, $route_stops);
$route_chain_string = implode(' ➔ ', $station_names_chain);

// Verify running days for selected journey date
$journey_day = date('D', strtotime($journey_date));
$active_days_arr = array_map('trim', explode(',', $train['running_days'] ?? ''));
$runs_on_selected_day = in_array($journey_day, $active_days_arr);

// Handle Form Submission -> Create Pending Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $boarding_station   = trim($_POST['boarding_station']);
    $deboarding_station = trim($_POST['deboarding_station']);
    $travel_class       = isset($_POST['travel_class']) && in_array($_POST['travel_class'], ['GN', '2S', 'SL', 'CC', '3AC']) ? $_POST['travel_class'] : 'GN';
    $passenger_names    = isset($_POST['passenger_name']) ? $_POST['passenger_name'] : [];
    $ages               = isset($_POST['age']) ? $_POST['age'] : [];
    $genders            = isset($_POST['gender']) ? $_POST['gender'] : [];
    $total_passengers   = count($passenger_names);

    if (!$runs_on_selected_day) {
        $error = "Train #" . htmlspecialchars($train['train_number']) . " does not operate on " . date('l', strtotime($journey_date)) . "s. Operating days: " . htmlspecialchars($train['running_days']);
    } else {
        // Calculate stage fare & class multiplier
        $board_order = 0;
        $deboard_order = 0;

        foreach ($route_stops as $st) {
            if ($st['station_name'] === $boarding_station) {
                $board_order = intval($st['stop_order']);
            }
            if ($st['station_name'] === $deboarding_station) {
                $deboard_order = intval($st['stop_order']);
            }
        }

        $segment_fare_sum = 0.00;
        foreach ($route_stops as $st) {
            $order = intval($st['stop_order']);
            if ($order > $board_order && $order <= $deboard_order) {
                $segment_fare_sum += floatval($st['segment_fare']);
            }
        }

        $class_multipliers = [
            'GN' => 1.0,
            '2S' => 2.0,
            'SL' => 3.0,
            'CC' => 4.0,
            '3AC'=> 6.0
        ];
        $multiplier = $class_multipliers[$travel_class] ?? 1.0;
        $fare_per_passenger = round($segment_fare_sum * $multiplier, 2);

        // Fetch Dynamic Seat Availability for Date + Class
        $class_col = 'seats_gn';
        if ($travel_class === '2S') $class_col = 'seats_2s';
        elseif ($travel_class === 'SL') $class_col = 'seats_sl';
        elseif ($travel_class === 'CC') $class_col = 'seats_cc';
        elseif ($travel_class === '3AC') $class_col = 'seats_3ac';

        $class_capacity = intval($train[$class_col]);

        $bk_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(total_passengers), 0) as booked FROM bookings WHERE train_id = ? AND journey_date = ? AND travel_class = ? AND status != 'Cancelled'");
        mysqli_stmt_bind_param($bk_stmt, "iss", $train_id, $journey_date, $travel_class);
        mysqli_stmt_execute($bk_stmt);
        $booked_seats = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))['booked'];
        $available_seats = $class_capacity - $booked_seats;

        // Server-side validation
        if ($board_order >= $deboard_order || $board_order === 0 || $deboard_order === 0) {
            $error = "Boarding station must come before deboarding station in the route.";
        } elseif ($total_passengers <= 0 || $total_passengers > 6) {
            $error = "Please add between 1 and 6 passengers per booking.";
        } elseif (count($ages) !== $total_passengers || count($genders) !== $total_passengers) {
            $error = "Please enter complete details for all passengers.";
        } elseif ($total_passengers > $available_seats) {
            $error = "Only " . $available_seats . " seat(s) are available in class " . $travel_class . " on this date.";
        } else {
            $total_fare = $fare_per_passenger * $total_passengers;
            $pnr_number = 'PNR' . random_int(1000000, 9999999);
            $user_id    = $_SESSION['user_id'];

            mysqli_begin_transaction($conn);

            try {
                // Create pending booking
                $b_stmt = mysqli_prepare($conn, "INSERT INTO bookings (pnr_number, user_id, train_id, boarding_station, deboarding_station, travel_class, journey_date, total_passengers, total_fare, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                mysqli_stmt_bind_param($b_stmt, "siissssid", $pnr_number, $user_id, $train_id, $boarding_station, $deboarding_station, $travel_class, $journey_date, $total_passengers, $total_fare);
                if (!mysqli_stmt_execute($b_stmt)) {
                    throw new Exception("Could not create booking. Please try again.");
                }
                $booking_id = mysqli_insert_id($conn);

                // Insert passengers
                $p_stmt = mysqli_prepare($conn, "INSERT INTO passengers (booking_id, passenger_name, age, gender, seat_number) VALUES (?, ?, ?, ?, ?)");

                for ($i = 0; $i < $total_passengers; $i++) {
                    $p_name = trim($passenger_names[$i]);
                    $p_age  = intval($ages[$i]);
                    $p_gen  = in_array($genders[$i], ['Male', 'Female', 'Other']) ? $genders[$i] : 'Male';
                    $seat   = $travel_class . '-' . random_int(1, $class_capacity);

                    if (empty($p_name) || $p_age < 1 || $p_age > 120) {
                        throw new Exception("Please enter valid names and ages for all passengers.");
                    }

                    mysqli_stmt_bind_param($p_stmt, "isiss", $booking_id, $p_name, $p_age, $p_gen, $seat);
                    if (!mysqli_stmt_execute($p_stmt)) {
                        throw new Exception("Could not save passenger details.");
                    }
                }

                mysqli_commit($conn);

                header("Location: payment/checkout.php?booking_id=" . $booking_id);
                exit();
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = "Booking Error: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 850px; margin: 1.5rem auto;">
    <div class="card">
        <h2 class="card-title">Book Railway Ticket</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$runs_on_selected_day): ?>
            <div class="alert alert-danger" style="margin-bottom:1.5rem;">
                🚫 <strong>Schedule Alert:</strong> Train #<?php echo htmlspecialchars($train['train_number']); ?> does not operate on <strong><?php echo date('l', strtotime($journey_date)); ?>s</strong>.<br>
                Operating days: <?php echo render_running_days($train['running_days']); ?>
            </div>
        <?php endif; ?>

        <!-- Train Summary -->
        <div style="background:#eef6fc; padding:1.2rem; border-radius:8px; border-left:5px solid var(--primary-color); margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                <div>
                    <h3 style="color:var(--primary-color); display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span><?php echo htmlspecialchars($train['train_name']); ?> (#<?php echo htmlspecialchars($train['train_number']); ?>)</span>
                        <?php echo render_running_days($train['running_days']); ?>
                    </h3>
                    <p>Journey Date: <strong><?php echo date('d-M-Y', strtotime($journey_date)); ?> (<?php echo date('l', strtotime($journey_date)); ?>)</strong></p>
                    <p id="duration-display" style="color:var(--primary-color); font-weight:bold; margin-top:4px;">⏱️ Calculating journey duration...</p>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:0.85rem; color:#6c757d;">Calculated Stage Fare:</span><br>
                    <span style="font-size:1.5rem; font-weight:bold; color:var(--success-color);" id="fare-display">-</span>
                </div>
            </div>
            <div style="background:#ffffff; padding:10px 14px; border-radius:6px; border:1px solid #cbd5e1; font-size:0.9rem; color:var(--primary-color);">
                📍 <strong>Complete Train Route:</strong><br>
                <span style="font-weight:600; color:#1e293b; line-height:1.8;"><?php echo htmlspecialchars($route_chain_string); ?></span>
            </div>
        </div>

        <form method="POST" action="book.php?train_id=<?php echo $train_id; ?>&date=<?php echo urlencode($journey_date); ?>" id="booking-form">

            <h4 style="margin-bottom: 0.8rem; color:var(--primary-color);">Select Boarding, Destination and Travel Class:</h4>
            <div class="form-grid" style="margin-bottom:1.5rem; background:#f8f9fa; padding:1rem; border-radius:6px; border:1px solid #e2e8f0;">

                <div class="form-group">
                    <label for="boarding_station">Boarding Station (From)</label>
                    <select name="boarding_station" id="boarding_station" class="form-control" required onchange="updateDeboardingOptions()">
                        <?php foreach ($route_stops as $index => $stop): ?>
                            <?php if ($index < count($route_stops) - 1): ?>
                                <option value="<?php echo htmlspecialchars($stop['station_name']); ?>"
                                        data-order="<?php echo $stop['stop_order']; ?>"
                                        data-dep="<?php echo $stop['departure_time']; ?>"
                                        data-day="<?php echo $stop['departure_day']; ?>"
                                        <?php if (!empty($pref_source) && strpos(strtolower($stop['station_name']), strtolower($pref_source)) !== false) echo 'selected'; ?>>
                                    Stop #<?php echo $stop['stop_order']; ?>: <?php echo htmlspecialchars($stop['station_name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deboarding_station">Destination Station (To)</label>
                    <select name="deboarding_station" id="deboarding_station" class="form-control" required onchange="calculateFare()">
                        <!-- Dynamic Stops Options -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="travel_class">Travel Class / Coach Quota</label>
                    <select name="travel_class" id="travel_class" class="form-control" required onchange="calculateFare()">
                        <option value="GN" data-multiplier="1.0">General Class (GN) - Standard Fare</option>
                        <option value="2S" data-multiplier="2.0">Second Seating (2S) - 2.0x Fare</option>
                        <option value="SL" data-multiplier="3.0">Sleeper Class (SL) - 3.0x Fare</option>
                        <option value="CC" data-multiplier="4.0">AC Chair Car (CC) - 4.0x Fare</option>
                        <option value="3AC" data-multiplier="6.0">AC 3-Tier (3AC) - 6.0x Fare</option>
                    </select>
                </div>
            </div>

            <h4 style="margin-bottom: 1rem; color:var(--primary-color);">Passenger Details:</h4>
            <div id="passenger-container">
                <div class="passenger-row" style="background:#f8f9fa; padding:1rem; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:1rem; position:relative;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.8rem;">
                        <h5 class="passenger-title" style="color:#555; margin:0;">Passenger #1</h5>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="passenger_name[]" class="form-control" required placeholder="Passenger Name">
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age[]" class="form-control" min="1" max="120" required placeholder="Age">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender[]" class="form-control" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <button type="button" id="add-passenger-btn" class="btn btn-secondary" style="font-size:0.88rem;">➕ Add Another Passenger</button>
                <span style="font-weight:bold; color:var(--primary-color); font-size:1.1rem;">Total Amount to Pay: <span id="total-fare-display">₹0.00</span></span>
            </div>

            <button type="submit" class="btn btn-success btn-block" style="font-size:1.1rem; padding:12px;">💳 Proceed to Payment Gateway</button>
        </form>
    </div>
</div>

<script>
const allStops = <?php echo json_encode($route_stops); ?>;
const prefDest = <?php echo json_encode($pref_dest); ?>;
let passengerCount = 1;

function updateDeboardingOptions() {
    const boardSelect = document.getElementById('boarding_station');
    const deboardSelect = document.getElementById('deboarding_station');

    if (boardSelect.selectedIndex === -1) return;

    const selectedBoardOption = boardSelect.options[boardSelect.selectedIndex];
    const selectedBoardOrder = parseInt(selectedBoardOption.getAttribute('data-order')) || 1;

    deboardSelect.innerHTML = '';
    let defaultSelected = false;

    allStops.forEach(stop => {
        if (stop.stop_order > selectedBoardOrder) {
            const opt = document.createElement('option');
            opt.value = stop.station_name;
            opt.setAttribute('data-fare', stop.segment_fare);
            opt.setAttribute('data-order', stop.stop_order);
            opt.setAttribute('data-arr', stop.arrival_time);
            opt.setAttribute('data-day', stop.arrival_day);
            opt.innerText = 'Stop #' + stop.stop_order + ': ' + stop.station_name;

            if (prefDest && stop.station_name.toLowerCase().includes(prefDest.toLowerCase())) {
                opt.selected = true;
                defaultSelected = true;
            }

            deboardSelect.appendChild(opt);
        }
    });

    if (!defaultSelected && deboardSelect.options.length > 0) {
        deboardSelect.options[deboardSelect.options.length - 1].selected = true;
    }

    calculateFare();
}

function calculateFare() {
    const boardSelect = document.getElementById('boarding_station');
    const deboardSelect = document.getElementById('deboarding_station');
    const classSelect = document.getElementById('travel_class');

    if (boardSelect.selectedIndex === -1 || deboardSelect.selectedIndex === -1) return;

    const boardOption = boardSelect.options[boardSelect.selectedIndex];
    const deboardOption = deboardSelect.options[deboardSelect.selectedIndex];
    const classOption = classSelect.options[classSelect.selectedIndex];

    const boardOrder = parseInt(boardOption.getAttribute('data-order'));
    const deboardOrder = parseInt(deboardOption.getAttribute('data-order'));
    const multiplier = parseFloat(classOption.getAttribute('data-multiplier')) || 1.0;

    // Sum segment fares between stops
    let segmentFareSum = 0;
    allStops.forEach(stop => {
        const order = parseInt(stop.stop_order);
        if (order > boardOrder && order <= deboardOrder) {
            segmentFareSum += parseFloat(stop.segment_fare) || 0;
        }
    });

    const farePerPassenger = segmentFareSum * multiplier;
    document.getElementById('fare-display').innerText = '₹' + farePerPassenger.toFixed(2);

    const totalFare = farePerPassenger * passengerCount;
    document.getElementById('total-fare-display').innerText = '₹' + totalFare.toFixed(2);

    // Calculate Journey Duration including Day Offsets
    const depTimeStr = boardOption.getAttribute('data-dep');
    const depDay = parseInt(boardOption.getAttribute('data-day')) || 0;
    const arrTimeStr = deboardOption.getAttribute('data-arr');
    const arrDay = parseInt(deboardOption.getAttribute('data-day')) || 0;

    if (depTimeStr && arrTimeStr) {
        const depParts = depTimeStr.split(':');
        const arrParts = arrTimeStr.split(':');

        let depMins = depDay * 1440 + parseInt(depParts[0]) * 60 + parseInt(depParts[1]);
        let arrMins = arrDay * 1440 + parseInt(arrParts[0]) * 60 + parseInt(arrParts[1]);

        let diffMins = arrMins - depMins;
        if (diffMins < 0) diffMins = 0;

        const hours = Math.floor(diffMins / 60);
        const mins = diffMins % 60;

        document.getElementById('duration-display').innerHTML = `Estimated Journey Duration: <strong>${hours} Hours ${mins} Mins</strong> (Dep: ${depTimeStr} ➔ Arr: ${arrTimeStr})`;
    }
}

function reindexPassengers() {
    const rows = document.querySelectorAll('.passenger-row');
    passengerCount = rows.length;

    rows.forEach((row, idx) => {
        const title = row.querySelector('.passenger-title');
        title.innerText = 'Passenger #' + (idx + 1);

        let removeBtn = row.querySelector('.remove-passenger-btn');
        if (idx === 0) {
            if (removeBtn) removeBtn.remove();
        } else {
            if (!removeBtn) {
                const headerDiv = row.querySelector('div');
                removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-danger remove-passenger-btn';
                removeBtn.style = 'padding:4px 10px; font-size:0.8rem;';
                removeBtn.innerText = '❌ Remove';
                removeBtn.onclick = function() {
                    row.remove();
                    reindexPassengers();
                };
                headerDiv.appendChild(removeBtn);
            }
        }
    });

    calculateFare();
}

document.getElementById('add-passenger-btn').addEventListener('click', function() {
    if (passengerCount >= 6) {
        alert("Maximum 6 passengers allowed per booking.");
        return;
    }
    const container = document.getElementById('passenger-container');
    const div = document.createElement('div');
    div.className = 'passenger-row';
    div.style = 'background:#f8f9fa; padding:1rem; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:1rem; position:relative;';
    div.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.8rem;">
            <h5 class="passenger-title" style="color:#555; margin:0;">Passenger #${passengerCount + 1}</h5>
            <button type="button" class="btn btn-danger remove-passenger-btn" style="padding:4px 10px; font-size:0.8rem;" onclick="this.closest('.passenger-row').remove(); reindexPassengers();">❌ Remove</button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="passenger_name[]" class="form-control" required placeholder="Passenger Name">
            </div>
            <div class="form-group">
                <label>Age</label>
                <input type="number" name="age[]" class="form-control" min="1" max="120" required placeholder="Age">
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select name="gender[]" class="form-control" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
    `;
    container.appendChild(div);
    reindexPassengers();
});

// Initialize options
updateDeboardingOptions();
</script>

<?php include 'includes/footer.php'; ?>
