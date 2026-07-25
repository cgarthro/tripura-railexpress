<?php
// config/db_connect.php
// Database Connection for Railway Booking System

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "railway_db";

// Connect to MySQL server
$conn = mysqli_connect($db_host, $db_user, $db_pass);

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

// Auto create database if not exists
$create_db_query = "CREATE DATABASE IF NOT EXISTS " . $db_name;
if (mysqli_query($conn, $create_db_query)) {
    mysqli_select_db($conn, $db_name);
} else {
    die("Database Selection Error: " . mysqli_error($conn));
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper: Render running days badge | M | T | W | T | F | S | S |
 */
if (!function_exists('render_running_days')) {
    function render_running_days($running_days_str) {
        $active_days = array_map('trim', explode(',', $running_days_str ?? ''));
        $all_days = [
            'Mon' => 'M',
            'Tue' => 'T',
            'Wed' => 'W',
            'Thu' => 'T',
            'Fri' => 'F',
            'Sat' => 'S',
            'Sun' => 'S'
        ];
        
        $html = '<div class="running-days-badge" style="display:inline-flex; gap:3px; align-items:center; background:#f1f5f9; padding:3px 6px; border-radius:6px; border:1px solid #cbd5e1; font-family:sans-serif; font-size:0.75rem;">';
        foreach ($all_days as $key => $label) {
            $isActive = in_array($key, $active_days);
            if ($isActive) {
                $html .= '<span style="background:#0284c7; color:#ffffff; font-weight:bold; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; border-radius:3px;" title="Runs on ' . $key . '">' . $label . '</span>';
            } else {
                $html .= '<span style="background:#e2e8f0; color:#94a3b8; font-weight:normal; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; border-radius:3px;" title="Does NOT run on ' . $key . '">' . $label . '</span>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}
?>
