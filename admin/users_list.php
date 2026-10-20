<?php
// admin/users_list.php - Registered Users List
require_once '../config/db_connect.php';

// Auth Guard: Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$users_result = mysqli_query($conn, "
    SELECT u.*,
           (SELECT COUNT(*) FROM bookings WHERE user_id = u.user_id) as total_bookings
    FROM users u
    WHERE u.role = 'user'
    ORDER BY u.created_at DESC
");

include '../includes/header.php';
?>

<div style="max-width: 950px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1.2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h2 style="color:var(--primary-color);">Registered Users List</h2>
            <p style="color:var(--text-muted); font-size:0.9rem;">Overview of all registered passenger accounts and booking history.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">← Admin Dashboard</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Username / Email</th>
                        <th>Phone Number</th>
                        <th>Total Bookings</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($users_result) > 0): ?>
                        <?php while ($u = mysqli_fetch_assoc($users_result)): ?>
                            <tr>
                                <td><strong>#<?php echo $u['user_id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($u['email']); ?></code></td>
                                <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                <td>
                                    <span class="badge badge-confirmed" style="font-size:0.82rem; padding:4px 8px;"><?php echo $u['total_bookings']; ?> Booking(s)</span>
                                </td>
                                <td><?php echo date('d-M-Y h:i A', strtotime($u['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--text-muted);">No registered passenger accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
