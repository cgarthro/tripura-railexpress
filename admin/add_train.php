<?php
// admin/add_train.php - Add New Train Schedule Form
require_once '../config/db_connect.php';

// Auth Guard: Admin access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $error = "Please fill in all fields with valid data and select at least one operating day.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO trains (train_number, train_name, seats_gn, seats_2s, seats_sl, seats_cc, seats_3ac, running_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiiiiis", $train_number, $train_name, $seats_gn, $seats_2s, $seats_sl, $seats_cc, $seats_3ac, $running_days);

        if (mysqli_stmt_execute($stmt)) {
            $inserted_id = mysqli_insert_id($conn);
            header("Location: edit_train.php?id=" . $inserted_id . "&msg=added");
            exit();
        } else {
            $error = "Error adding train: " . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div style="max-width: 650px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1rem;">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <div class="card">
        <h2 class="card-title">Add New Train Schedule</h2>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Create the train schedule first. You will be redirected to add stops and set timings/fares next.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="add_train.php">
            <div class="form-grid">
                <div class="form-group">
                    <label for="train_number">Train Number</label>
                    <input type="text" name="train_number" id="train_number" class="form-control" required placeholder="e.g. 55676">
                </div>

                <div class="form-group">
                    <label for="train_name">Train Name</label>
                    <input type="text" name="train_name" id="train_name" class="form-control" required placeholder="e.g. Agartala Passenger">
                </div>
            </div>

            <h3 style="margin: 1.5rem 0 0.8rem; color: var(--primary-color);">Days of Operation (Running Schedule)</h3>
            <div style="background:#f8f9fa; padding:1rem; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:1.5rem;">
                <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Select Days Train Runs:</label>
                <div style="display:flex; gap:15px; flex-wrap:wrap;">
                    <?php
                    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($weekdays as $day):
                    ?>
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-weight:bold;">
                            <input type="checkbox" name="running_days[]" value="<?php echo $day; ?>" checked>
                            <?php echo $day; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <h3 style="margin: 1.5rem 0 0.8rem; color: var(--primary-color);">Seat Capacities per Travel Class</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label for="seats_gn">General Class (GN)</label>
                    <input type="number" name="seats_gn" id="seats_gn" class="form-control" min="0" value="50" required>
                </div>

                <div class="form-group">
                    <label for="seats_2s">Second Seating (2S)</label>
                    <input type="number" name="seats_2s" id="seats_2s" class="form-control" min="0" value="40" required>
                </div>

                <div class="form-group">
                    <label for="seats_sl">Sleeper Class (SL)</label>
                    <input type="number" name="seats_sl" id="seats_sl" class="form-control" min="0" value="60" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="seats_cc">AC Chair Car (CC)</label>
                    <input type="number" name="seats_cc" id="seats_cc" class="form-control" min="0" value="30" required>
                </div>

                <div class="form-group">
                    <label for="seats_3ac">AC 3-Tier (3AC)</label>
                    <input type="number" name="seats_3ac" id="seats_3ac" class="form-control" min="0" value="30" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:12px; margin-top:1.5rem; font-size:1rem; font-weight:bold;">Create Train & Add Stops →</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
