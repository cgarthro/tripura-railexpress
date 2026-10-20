<?php
// payment/checkout.php - Simulated Payment Gateway Module
require_once '../config/db_connect.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id    = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "
    SELECT b.*, t.train_number, t.train_name, u.full_name, u.email
    FROM bookings b
    JOIN trains t ON b.train_id = t.train_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ? AND b.user_id = ? AND t.is_deleted = 0
");
mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$res     = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($res);

if (!$booking) {
    die("Invalid booking selection or session expired.");
}

// Redirect if ticket is already confirmed or cancelled
if ($booking['status'] !== 'Pending') {
    header("Location: ../users/view_ticket.php?pnr=" . urlencode($booking['pnr_number']));
    exit();
}

include '../includes/header.php';
?>

<!-- 5-Second Payment Processing Overlay -->
<div id="payment-loader-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(11, 60, 93, 0.92); backdrop-filter:blur(6px); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#ffffff; max-width:440px; width:90%; padding:2.2rem; border-radius:12px; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; animation:fadeIn 0.3s ease;">

        <div id="loader-spinner" style="margin:0 auto 1.5rem;">
            <div style="width:60px; height:60px; border:6px solid #e2e8f0; border-top:6px solid var(--primary-color); border-radius:50%; margin:0 auto; animation:spin 1s linear infinite;"></div>
        </div>

        <div id="loader-check" style="display:none; margin:0 auto 1.5rem; font-size:3.2rem; color:var(--success-color); animation:pulse 0.5s ease;">
            ✅
        </div>

        <h3 id="loader-title" style="color:var(--primary-color); margin-bottom:0.5rem; font-size:1.4rem;">Connecting to Bank...</h3>
        <p id="loader-subtitle" style="color:#64748b; font-size:0.92rem; margin-bottom:1.5rem; line-height:1.5;">Verifying transaction details with simulated payment gateway...</p>

        <div style="width:100%; background:#e2e8f0; height:10px; border-radius:5px; overflow:hidden;">
            <div id="loader-progress" style="width:0%; height:100%; background:linear-gradient(90deg, var(--primary-color), var(--secondary-color)); transition:width 5s linear;"></div>
        </div>
    </div>
</div>

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@keyframes pulse { 0% { transform: scale(0.7); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
</style>

<div style="max-width: 750px; margin: 1.5rem auto;">
    <div class="card">
        <h2 class="card-title">💳 Simulated Payment Gateway</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">Select your preferred payment method to complete ticket reservation (Academic Demo Prototype).</p>

        <!-- Payment Summary Box -->
        <div style="background:#eef6fc; padding:1.2rem; border-radius:8px; border-left:5px solid var(--primary-color); margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h4 style="color:var(--primary-color); margin-bottom:4px;">PNR Reference: <?php echo htmlspecialchars($booking['pnr_number']); ?></h4>
                <p>Train: <strong>#<?php echo htmlspecialchars($booking['train_number']); ?> - <?php echo htmlspecialchars($booking['train_name']); ?></strong></p>
                <p>Route: <strong><?php echo htmlspecialchars($booking['boarding_station']); ?></strong> ➔ <strong><?php echo htmlspecialchars($booking['deboarding_station']); ?></strong></p>
                <p>Passengers: <strong><?php echo $booking['total_passengers']; ?> Person(s)</strong> | Date: <strong><?php echo date('d-M-Y', strtotime($booking['journey_date'])); ?></strong></p>
            </div>
            <div style="text-align:right;">
                <small style="color:var(--text-muted);">Total Payable Amount:</small><br>
                <span style="font-size:1.8rem; font-weight:bold; color:var(--success-color);">₹<?php echo number_format($booking['total_fare'], 2); ?></span>
            </div>
        </div>

        <!-- Payment Method Tabs -->
        <div style="display:flex; gap:10px; margin-bottom:1.5rem; border-bottom:2px solid #cbd5e1; padding-bottom:8px;">
            <button type="button" class="btn btn-primary pay-tab-btn" onclick="switchPayTab('upi')" id="tab-upi">📲 UPI / QR Code</button>
            <button type="button" class="btn btn-secondary pay-tab-btn" onclick="switchPayTab('card')" id="tab-card">💳 Credit / Debit Card</button>
            <button type="button" class="btn btn-secondary pay-tab-btn" onclick="switchPayTab('netbanking')" id="tab-netbanking">🏦 Net Banking</button>
        </div>

        <!-- 1. UPI / QR Code Option -->
        <div id="pay-section-upi" class="pay-section">
            <form method="POST" action="process_payment.php" onsubmit="startPaymentProcess(event, this)">
                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                <input type="hidden" name="payment_method" value="Simulated UPI / QR Code">

                <div style="text-align:center; padding:1.5rem; background:#f8f9fa; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:1.5rem;">
                    <h4 style="color:var(--primary-color); margin-bottom:0.5rem;">Scan QR Code using any UPI App</h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">(GPay, PhonePe, Paytm, BHIM UPI)</p>

                    <!-- Render Clean Inline Vector QR Code -->
                    <div style="margin: 0 auto 1rem; width: 160px; height: 160px; background:#fff; padding:10px; border-radius:8px; box-shadow:var(--shadow);">
                        <svg width="140" height="140" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="100" fill="#ffffff"/>
                            <path d="M10,10 h30 v30 h-30 z M15,15 v20 h20 v-20 z M20,20 h10 v10 h-10 z" fill="#0b3c5d"/>
                            <path d="M60,10 h30 v30 h-30 z M65,15 v20 h20 v-20 z M70,20 h10 v10 h-10 z" fill="#0b3c5d"/>
                            <path d="M10,60 h30 v30 h-30 z M15,65 v20 h20 v-20 z M20,70 h10 v10 h-10 z" fill="#0b3c5d"/>
                            <rect x="45" y="15" width="8" height="25" fill="#0b3c5d"/>
                            <rect x="15" y="45" width="25" height="8" fill="#0b3c5d"/>
                            <rect x="50" y="50" width="40" height="40" fill="#0b3c5d"/>
                            <rect x="60" y="60" width="20" height="20" fill="#ffffff"/>
                        </svg>
                    </div>

                    <p style="font-weight:bold; font-family:monospace; color:var(--primary-color);">UPI ID: tripurarailway@upi</p>
                    <p style="font-size:0.88rem; color:#6c757d;">Amount: ₹<?php echo number_format($booking['total_fare'], 2); ?></p>
                </div>

                <div class="form-group">
                    <label for="upi_vpa">Enter Your UPI ID (VPA)</label>
                    <input type="text" name="upi_vpa" id="upi_vpa" class="form-control" placeholder="e.g. username@upi" value="<?php echo htmlspecialchars($_SESSION['email']); ?>">
                </div>

                <button type="submit" class="btn btn-success btn-block" style="font-size:1.1rem; padding:12px;">✓ Verify & Pay ₹<?php echo number_format($booking['total_fare'], 2); ?></button>
            </form>
        </div>

        <!-- 2. Credit / Debit Card Option -->
        <div id="pay-section-card" class="pay-section" style="display:none;">
            <form method="POST" action="process_payment.php" onsubmit="startPaymentProcess(event, this)">
                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                <input type="hidden" name="payment_method" value="Simulated Card">

                <div class="form-group">
                    <label for="card_name">Cardholder Name</label>
                    <input type="text" name="card_name" id="card_name" class="form-control" required placeholder="Name as printed on card" value="<?php echo htmlspecialchars($booking['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="card_number">Card Number</label>
                    <input type="text" name="card_number" id="card_number" class="form-control" required maxlength="19" placeholder="Enter 16-digit card number">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="expiry">Expiry Date</label>
                        <input type="text" name="expiry" id="expiry" class="form-control" required placeholder="MM/YY">
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="password" name="cvv" id="cvv" class="form-control" required maxlength="4" placeholder="CVV">
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-block" style="font-size:1.1rem; padding:12px;">✓ Pay ₹<?php echo number_format($booking['total_fare'], 2); ?> via Card</button>
            </form>
        </div>

        <!-- 3. Net Banking Option -->
        <div id="pay-section-netbanking" class="pay-section" style="display:none;">
            <form method="POST" action="process_payment.php" onsubmit="startPaymentProcess(event, this)">
                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                <input type="hidden" name="payment_method" value="Simulated NetBanking">

                <div class="form-group">
                    <label for="bank_name">Select Your Bank</label>
                    <select name="bank_name" id="bank_name" class="form-control" required>
                        <option value="State Bank of India">State Bank of India (SBI)</option>
                        <option value="HDFC Bank">HDFC Bank</option>
                        <option value="ICICI Bank">ICICI Bank</option>
                        <option value="Punjab National Bank">Punjab National Bank (PNB)</option>
                        <option value="Axis Bank">Axis Bank</option>
                        <option value="Bank of Baroda">Bank of Baroda</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success btn-block" style="font-size:1.1rem; padding:12px;">✓ Pay ₹<?php echo number_format($booking['total_fare'], 2); ?> via NetBanking</button>
            </form>
        </div>

    </div>
</div>

<script>
function switchPayTab(tabName) {
    document.querySelectorAll('.pay-section').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.pay-tab-btn').forEach(el => {
        el.className = 'btn btn-secondary pay-tab-btn';
    });

    document.getElementById('pay-section-' + tabName).style.display = 'block';
    document.getElementById('tab-' + tabName).className = 'btn btn-primary pay-tab-btn';
}

function startPaymentProcess(e, form) {
    e.preventDefault();

    const overlay = document.getElementById('payment-loader-overlay');
    const title = document.getElementById('loader-title');
    const subtitle = document.getElementById('loader-subtitle');
    const progress = document.getElementById('loader-progress');
    const spinner = document.getElementById('loader-spinner');
    const check = document.getElementById('loader-check');

    overlay.style.display = 'flex';

    setTimeout(() => {
        progress.style.width = '60%';
    }, 100);

    setTimeout(() => {
        title.innerText = "Payment Approved!";
        subtitle.innerText = "Confirming ticket reservation and seat allocation...";
        progress.style.width = '100%';
        spinner.style.display = 'none';
        check.style.display = 'block';
    }, 3000);

    setTimeout(() => {
        form.submit();
    }, 5000);
}
</script>

<?php include '../includes/footer.php'; ?>
