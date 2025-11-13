<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Debug: Director Leave Approvals Dedicated Page</h1>";
echo "<p>Current user: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . " (Role: " . $_SESSION['role'] . ")</p>";

// Test the EXACT query from director_leave_approvals.php
echo "<h2>Testing Exact Query from director_leave_approvals.php</h2>";

$director_approvals_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, u.email, lt.name as leave_type, 
                          la.start_date, la.end_date, la.days, la.reason, la.created_at, d.name as department,
                          lap.id as approval_id, lap.created_at as approval_created, lap.approver_id,
                          approver.first_name as assigned_admin_name
                          FROM leave_applications la 
                          JOIN users u ON la.user_id = u.id 
                          JOIN leave_types lt ON la.leave_type_id = lt.id 
                          JOIN departments d ON u.department_id = d.id
                          JOIN leave_approvals lap ON la.id = lap.leave_application_id
                          LEFT JOIN users approver ON lap.approver_id = approver.id
                          WHERE la.status = 'pending'
                          AND u.role = 'director'
                          AND lap.approver_level = 'admin'
                          AND lap.status = 'pending'
                          ORDER BY la.created_at ASC";

echo "<p><strong>Query:</strong></p>";
echo "<pre>" . htmlspecialchars($director_approvals_sql) . "</pre>";

$director_approvals_stmt = $conn->prepare($director_approvals_sql);
$director_approvals_stmt->execute();
$director_approvals = $director_approvals_stmt->fetchAll();

echo "<p><strong>Results:</strong> " . count($director_approvals) . " records found</p>";

if (count($director_approvals) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>App ID</th><th>Director</th><th>Leave Type</th><th>Start Date</th><th>End Date</th>";
    echo "<th>Days</th><th>Status</th><th>Approval ID</th><th>Approver ID</th><th>Assigned Admin</th>";
    echo "</tr>";
    
    foreach ($director_approvals as $app) {
        echo "<tr>";
        echo "<td>" . $app['id'] . "</td>";
        echo "<td>" . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($app['leave_type']) . "</td>";
        echo "<td>" . $app['start_date'] . "</td>";
        echo "<td>" . $app['end_date'] . "</td>";
        echo "<td>" . $app['days'] . "</td>";
        echo "<td>Pending</td>";
        echo "<td>" . $app['approval_id'] . "</td>";
        echo "<td>" . $app['approver_id'] . "</td>";
        echo "<td>" . htmlspecialchars($app['assigned_admin_name'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>❌ No results found!</strong></p>";
    
    // Let's debug why...
    echo "<h3>Debugging each JOIN condition:</h3>";
    
    // Test without the approval JOIN
    echo "<h4>1. Director applications (without approval join):</h4>";
    $test1_sql = "SELECT la.id, u.first_name, u.last_name, la.status
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  WHERE u.role = 'director' AND la.status = 'pending'";
    $test1_stmt = $conn->prepare($test1_sql);
    $test1_stmt->execute();
    $test1_results = $test1_stmt->fetchAll();
    echo "<p>Found " . count($test1_results) . " pending director applications</p>";
    
    // Test with approval JOIN but no conditions
    echo "<h4>2. Director applications with ANY approval records:</h4>";
    $test2_sql = "SELECT la.id, u.first_name, u.last_name, la.status, lap.approver_level, lap.status as approval_status
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_approvals lap ON la.id = lap.leave_application_id
                  WHERE u.role = 'director' AND la.status = 'pending'";
    $test2_stmt = $conn->prepare($test2_sql);
    $test2_stmt->execute();
    $test2_results = $test2_stmt->fetchAll();
    echo "<p>Found " . count($test2_results) . " director applications with approval records</p>";
    if (count($test2_results) > 0) {
        foreach ($test2_results as $result) {
            echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, Approver Level: {$result['approver_level']}, Approval Status: {$result['approval_status']}</li>";
        }
    }
    
    // Test with admin level condition
    echo "<h4>3. Director applications with admin level approvals:</h4>";
    $test3_sql = "SELECT la.id, u.first_name, u.last_name, la.status, lap.approver_level, lap.status as approval_status
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_approvals lap ON la.id = lap.leave_application_id
                  WHERE u.role = 'director' AND la.status = 'pending' AND lap.approver_level = 'admin'";
    $test3_stmt = $conn->prepare($test3_sql);
    $test3_stmt->execute();
    $test3_results = $test3_stmt->fetchAll();
    echo "<p>Found " . count($test3_results) . " director applications with admin level approvals</p>";
    if (count($test3_results) > 0) {
        foreach ($test3_results as $result) {
            echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, Approver Level: {$result['approver_level']}, Approval Status: {$result['approval_status']}</li>";
        }
    }
    
    // Test with both admin level and pending status
    echo "<h4>4. Director applications with admin level AND pending approval status:</h4>";
    $test4_sql = "SELECT la.id, u.first_name, u.last_name, la.status, lap.approver_level, lap.status as approval_status
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_approvals lap ON la.id = lap.leave_application_id
                  WHERE u.role = 'director' AND la.status = 'pending' 
                  AND lap.approver_level = 'admin' AND lap.status = 'pending'";
    $test4_stmt = $conn->prepare($test4_sql);
    $test4_stmt->execute();
    $test4_results = $test4_stmt->fetchAll();
    echo "<p>Found " . count($test4_results) . " director applications with admin level AND pending approval status</p>";
    if (count($test4_results) > 0) {
        foreach ($test4_results as $result) {
            echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, Approver Level: {$result['approver_level']}, Approval Status: {$result['approval_status']}</li>";
        }
    }
}

echo "<hr>";
echo "<h2>Compare with Admin Dashboard Query</h2>";

// Test the admin dashboard query
$admin_dashboard_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, lt.name as leave_type, 
                       la.start_date, la.end_date, la.days, la.reason, la.created_at, d.name as department,
                       lap.approver_id, approver.first_name as assigned_admin_name
                       FROM leave_applications la 
                       JOIN users u ON la.user_id = u.id 
                       JOIN leave_types lt ON la.leave_type_id = lt.id 
                       JOIN departments d ON u.department_id = d.id
                       JOIN leave_approvals lap ON la.id = lap.leave_application_id
                       LEFT JOIN users approver ON lap.approver_id = approver.id
                       WHERE la.status = 'pending'
                       AND u.role = 'director'
                       AND lap.approver_level = 'admin'
                       AND lap.status = 'pending'
                       ORDER BY la.created_at ASC";

echo "<p><strong>Admin Dashboard Query Results:</strong></p>";
$admin_dashboard_stmt = $conn->prepare($admin_dashboard_sql);
$admin_dashboard_stmt->execute();
$admin_dashboard_results = $admin_dashboard_stmt->fetchAll();

echo "<p>Found " . count($admin_dashboard_results) . " records with admin dashboard query</p>";

if (count($admin_dashboard_results) > 0) {
    foreach ($admin_dashboard_results as $result) {
        echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, Leave Type: {$result['leave_type']}</li>";
    }
}

echo "<hr>";
echo "<p><a href='director_leave_approvals.php'>← Back to Director Leave Approvals</a></p>";
?>