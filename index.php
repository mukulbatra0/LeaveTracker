<?php
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    return;
}

// Include database connection
require_once 'config/db.php';

// Include header
include_once 'includes/header.php';

// Get user role
$user_role = $_SESSION['role'];

// Redirect to appropriate dashboard based on role
$allowed_dashboards = [
    'staff' => 'dashboards/staff_dashboard.php',
    'department_head' => 'dashboards/department_head_dashboard.php',
    'dean' => 'dashboards/dean_dashboard.php',
    'principal' => 'dashboards/principal_dashboard.php',
    'hr_admin' => 'dashboards/hr_admin_dashboard.php'
];

if (isset($allowed_dashboards[$user_role])) {
    $dashboard_file = $allowed_dashboards[$user_role];
    if (file_exists($dashboard_file)) {
        include_once $dashboard_file;
    } else {
        echo "<div class='alert alert-danger'>Error: Dashboard file not found.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Error: Invalid user role.</div>";
}

// Include footer
include_once 'includes/footer.php';
?>