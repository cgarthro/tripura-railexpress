<?php
// database/setup.php - Database initialization script and schema creator
require_once '../config/db_connect.php';

echo "<h2>Tripura RailExpress - Database Setup Helper</h2>";

$sql_file = 'railway_db.sql';

if (!file_exists($sql_file)) {
    die("<p style='color:red;'>Error: railway_db.sql file not found in database folder.</p>");
}

$sql_content = file_get_contents($sql_file);

// Disable foreign keys checks to drop/import cleanly
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");

// Execute the multi-query setup script
if (mysqli_multi_query($conn, $sql_content)) {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));

    echo "<p style='color:green; font-weight:bold;'>✓ Database tables recreated and seeded with real Tripura train segments successfully!</p>";
} else {
    echo "<p style='color:red;'>Setup Error: " . htmlspecialchars(mysqli_error($conn)) . "</p>";
}

// Re-enable foreign key checks
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");

echo "<p><a href='../index.php' style='padding:8px 16px; background:#0b3c5d; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;'>Go to Search Homepage</a></p>";
?>
