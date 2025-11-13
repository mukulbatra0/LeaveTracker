<?php
// Debug script to check director leave approvals
require_once '../config/db.php';

echo "<h2>Director Leave Applications Debug</h2>";

// Check all director leave applications
$all_director_apps_sql = "SELECT la.id, la.status, u.first_name, u.last_name, u.role, la.created_at
                         FROM leave_applications la 
                         JOIN users u ON la.user_id = u.id 
                         WHERE u.role = 'director'
                         ORDER BY la.created_at DESC";
$all_director_apps_stmt = $conn->prepare($all_director_apps_sql);
$all_director_apps_stmt->execute();
$all_director_apps = $all_director_apps_stmt->fetchAll();

echo "<h3>All Director Applications:</h3>";
if (count($all_director_apps) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Director</th><th>Status</th><th>Created</th></tr>";
    foreach ($all_director_apps as $app) {
        echo "<tr>";
        echo "<td>" . $app['id'] . "</td>";
        echo "<td>" . $app['first_name'] . " " . $app['last_name'] . "</td>";
        echo "<td>" . $app['status'] . "</td>";
        echo "<td>" . $app['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No director applications found.</p>";
}

// Check approval records for director applications
$approval_records_sql = "SELECT lap.*, la.id as app_id, u.first_name, u.last_name
                        FROM leave_approvals lap
                        JOIN leave_applications la ON lap.leave_application_id = la.id
                        JOIN users u ON la.user_id = u.id
                        WHERE u.role = 'director'
                        ORDER BY lap.created_at DESC";
$approval_records_stmt = $conn->prepare($approval_records_sql);
$approval_records_stmt->execute();
$approval_records = $approval_records_stmt->fetchAll();

echo "<h3>Approval Records for Director Applications:</h3>";
if (count($approval_records) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>App ID</th><th>Director</th><th>Approver ID</th><th>Level</th><th>Status</th><th>Created</th></tr>";
    foreach ($approval_records as $record) {
        echo "<tr>";
        echo "<td>" . $record['app_id'] . "</td>";
        echo "<td>" . $record['first_name'] . " " . $record['last_name'] . "</td>";
        echo "<td>" . $record['approver_id'] . "</td>";
        echo "<td>" . $record['approver_level'] . "</td>";
        echo "<td>" . $record['status'] . "</td>";
        echo "<td>" . $record['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No approval records found for director applications.</p>";
}

// Check admin users
$admin_users_sql = "SELECT id, first_name, last_name, role FROM users WHERE role IN ('admin', 'hr_admin') AND status = 'active'";
$admin_users_stmt = $conn->prepare($admin_users_sql);
$admin_users_stmt->execute();
$admin_users = $admin_users_stmt->fetchAll();

echo "<h3>Available Admin Users:</h3>";
if (count($admin_users) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Role</th></tr>";
    foreach ($admin_users as $admin) {
        echo "<tr>";
        echo "<td>" . $admin['id'] . "</td>";
        echo "<td>" . $admin['first_name'] . " " . $admin['last_name'] . "</td>";
        echo "<td>" . $admin['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No admin users found!</p>";
}
?>