<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Debug: Director Leave Approvals</h1>";
echo "<p>Current user: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . " (Role: " . $_SESSION['role'] . ")</p>";

// Step 1: Check if any directors exist
echo "<h2>Step 1: Check Directors</h2>";
$directors_sql = "SELECT id, first_name, last_name, email FROM users WHERE role = 'director' AND status = 'active'";
$directors_stmt = $conn->prepare($directors_sql);
$directors_stmt->execute();
$directors = $directors_stmt->fetchAll();

if (count($directors) > 0) {
    echo "<p>✅ Found " . count($directors) . " director(s):</p>";
    foreach ($directors as $director) {
        echo "<li>ID: {$director['id']}, Name: {$director['first_name']} {$director['last_name']}</li>";
    }
} else {
    echo "<p>❌ No directors found in the system!</p>";
}

// Step 2: Check if any director applications exist
echo "<h2>Step 2: Check Director Applications</h2>";
$director_apps_sql = "SELECT la.id, la.status, la.created_at, u.first_name, u.last_name 
                     FROM leave_applications la 
                     JOIN users u ON la.user_id = u.id 
                     WHERE u.role = 'director'
                     ORDER BY la.created_at DESC";
$director_apps_stmt = $conn->prepare($director_apps_sql);
$director_apps_stmt->execute();
$director_apps = $director_apps_stmt->fetchAll();

if (count($director_apps) > 0) {
    echo "<p>✅ Found " . count($director_apps) . " director application(s):</p>";
    foreach ($director_apps as $app) {
        echo "<li>App ID: {$app['id']}, Director: {$app['first_name']} {$app['last_name']}, Status: {$app['status']}, Created: {$app['created_at']}</li>";
    }
} else {
    echo "<p>❌ No director applications found!</p>";
}

// Step 3: Check approval records for director applications
echo "<h2>Step 3: Check Approval Records</h2>";
$approvals_sql = "SELECT lap.*, la.id as app_id, u.first_name, u.last_name, la.status as app_status
                  FROM leave_approvals lap
                  JOIN leave_applications la ON lap.leave_application_id = la.id
                  JOIN users u ON la.user_id = u.id
                  WHERE u.role = 'director'
                  ORDER BY lap.created_at DESC";
$approvals_stmt = $conn->prepare($approvals_sql);
$approvals_stmt->execute();
$approvals = $approvals_stmt->fetchAll();

if (count($approvals) > 0) {
    echo "<p>✅ Found " . count($approvals) . " approval record(s) for director applications:</p>";
    foreach ($approvals as $approval) {
        echo "<li>App ID: {$approval['app_id']}, Director: {$approval['first_name']} {$approval['last_name']}, Approver Level: {$approval['approver_level']}, Status: {$approval['status']}, App Status: {$approval['app_status']}</li>";
    }
} else {
    echo "<p>❌ No approval records found for director applications!</p>";
}

// Step 4: Check admin users
echo "<h2>Step 4: Check Admin Users</h2>";
$admins_sql = "SELECT id, first_name, last_name, role FROM users WHERE role IN ('admin', 'hr_admin') AND status = 'active'";
$admins_stmt = $conn->prepare($admins_sql);
$admins_stmt->execute();
$admins = $admins_stmt->fetchAll();

if (count($admins) > 0) {
    echo "<p>✅ Found " . count($admins) . " admin(s):</p>";
    foreach ($admins as $admin) {
        echo "<li>ID: {$admin['id']}, Name: {$admin['first_name']} {$admin['last_name']}, Role: {$admin['role']}</li>";
    }
} else {
    echo "<p>❌ No admin users found!</p>";
}

// Step 5: Run the exact query from director_leave_approvals.php
echo "<h2>Step 5: Test Exact Query</h2>";
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
$director_approvals_stmt = $conn->prepare($director_approvals_sql);
$director_approvals_stmt->execute();
$director_approvals = $director_approvals_stmt->fetchAll();

if (count($director_approvals) > 0) {
    echo "<p>✅ Query returned " . count($director_approvals) . " result(s):</p>";
    foreach ($director_approvals as $app) {
        echo "<li>App ID: {$app['id']}, Director: {$app['first_name']} {$app['last_name']}, Leave Type: {$app['leave_type']}, Assigned Admin: {$app['assigned_admin_name']}</li>";
    }
} else {
    echo "<p>❌ Query returned no results!</p>";
    echo "<p><strong>This means either:</strong></p>";
    echo "<ul>";
    echo "<li>No director applications with status 'pending'</li>";
    echo "<li>No approval records with approver_level = 'admin' and status = 'pending'</li>";
    echo "<li>Missing join conditions not met</li>";
    echo "</ul>";
}

// Step 6: Simplified query to find the issue
echo "<h2>Step 6: Simplified Queries</h2>";

// Check pending director applications
$pending_director_sql = "SELECT la.id, la.status, u.first_name, u.last_name 
                        FROM leave_applications la 
                        JOIN users u ON la.user_id = u.id 
                        WHERE u.role = 'director' AND la.status = 'pending'";
$pending_director_stmt = $conn->prepare($pending_director_sql);
$pending_director_stmt->execute();
$pending_directors = $pending_director_stmt->fetchAll();

echo "<p>Pending director applications: " . count($pending_directors) . "</p>";

// Check admin level approvals
$admin_approvals_sql = "SELECT * FROM leave_approvals WHERE approver_level = 'admin' AND status = 'pending'";
$admin_approvals_stmt = $conn->prepare($admin_approvals_sql);
$admin_approvals_stmt->execute();
$admin_approvals = $admin_approvals_stmt->fetchAll();

echo "<p>Pending admin approvals: " . count($admin_approvals) . "</p>";

// Step 7: Detailed breakdown of the query conditions
echo "<h2>Step 7: Detailed Query Breakdown</h2>";

// Check each condition separately
echo "<h3>Condition 1: la.status = 'pending'</h3>";
$cond1_sql = "SELECT la.id, la.status, u.first_name, u.last_name 
              FROM leave_applications la 
              JOIN users u ON la.user_id = u.id 
              WHERE u.role = 'director' AND la.status = 'pending'";
$cond1_stmt = $conn->prepare($cond1_sql);
$cond1_stmt->execute();
$cond1_results = $cond1_stmt->fetchAll();
echo "<p>Director applications with status 'pending': " . count($cond1_results) . "</p>";
if (count($cond1_results) > 0) {
    foreach ($cond1_results as $result) {
        echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, Status: {$result['status']}</li>";
    }
}

echo "<h3>Condition 2: lap.approver_level = 'admin' AND lap.status = 'pending'</h3>";
$cond2_sql = "SELECT lap.*, la.id as app_id, u.first_name, u.last_name 
              FROM leave_approvals lap
              JOIN leave_applications la ON lap.leave_application_id = la.id
              JOIN users u ON la.user_id = u.id
              WHERE u.role = 'director' AND lap.approver_level = 'admin' AND lap.status = 'pending'";
$cond2_stmt = $conn->prepare($cond2_sql);
$cond2_stmt->execute();
$cond2_results = $cond2_stmt->fetchAll();
echo "<p>Director applications with admin approval records (pending): " . count($cond2_results) . "</p>";
if (count($cond2_results) > 0) {
    foreach ($cond2_results as $result) {
        echo "<li>App ID: {$result['app_id']}, Director: {$result['first_name']} {$result['last_name']}, Approver Level: {$result['approver_level']}, Approval Status: {$result['status']}</li>";
    }
}

echo "<h3>Condition 3: Check if approval records exist at all for director apps</h3>";
$cond3_sql = "SELECT lap.*, la.id as app_id, u.first_name, u.last_name, la.status as app_status
              FROM leave_approvals lap
              JOIN leave_applications la ON lap.leave_application_id = la.id
              JOIN users u ON la.user_id = u.id
              WHERE u.role = 'director'";
$cond3_stmt = $conn->prepare($cond3_sql);
$cond3_stmt->execute();
$cond3_results = $cond3_stmt->fetchAll();
echo "<p>All approval records for director applications: " . count($cond3_results) . "</p>";
if (count($cond3_results) > 0) {
    foreach ($cond3_results as $result) {
        echo "<li>App ID: {$result['app_id']}, Director: {$result['first_name']} {$result['last_name']}, App Status: {$result['app_status']}, Approver Level: {$result['approver_level']}, Approval Status: {$result['status']}</li>";
    }
} else {
    echo "<p><strong>❌ ISSUE FOUND: No approval records exist for director applications!</strong></p>";
    echo "<p>This means the apply_leave.php is not creating approval records when directors apply for leave.</p>";
}

echo "<hr>";
echo "<p><a href='director_leave_approvals.php'>← Back to Director Leave Approvals</a></p>";
?>