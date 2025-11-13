<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Debug: JOIN Issues</h1>";

// Test each JOIN step by step
echo "<h2>Step-by-step JOIN testing</h2>";

// Step 1: Base query (we know this works)
echo "<h3>Step 1: Base query (works)</h3>";
$step1_sql = "SELECT la.id, u.first_name, u.last_name, la.status, lap.approver_level, lap.status as approval_status
              FROM leave_applications la 
              JOIN users u ON la.user_id = u.id 
              JOIN leave_approvals lap ON la.id = lap.leave_application_id
              WHERE u.role = 'director' AND la.status = 'pending' 
              AND lap.approver_level = 'admin' AND lap.status = 'pending'";
$step1_stmt = $conn->prepare($step1_sql);
$step1_stmt->execute();
$step1_results = $step1_stmt->fetchAll();
echo "<p>Results: " . count($step1_results) . "</p>";

// Step 2: Add leave_types JOIN
echo "<h3>Step 2: Add leave_types JOIN</h3>";
$step2_sql = "SELECT la.id, u.first_name, u.last_name, lt.name as leave_type, la.status, lap.approver_level, lap.status as approval_status
              FROM leave_applications la 
              JOIN users u ON la.user_id = u.id 
              JOIN leave_types lt ON la.leave_type_id = lt.id
              JOIN leave_approvals lap ON la.id = lap.leave_application_id
              WHERE u.role = 'director' AND la.status = 'pending' 
              AND lap.approver_level = 'admin' AND lap.status = 'pending'";
$step2_stmt = $conn->prepare($step2_sql);
$step2_stmt->execute();
$step2_results = $step2_stmt->fetchAll();
echo "<p>Results: " . count($step2_results) . "</p>";
if (count($step2_results) == 0) {
    echo "<p><strong>❌ ISSUE FOUND: leave_types JOIN is failing!</strong></p>";
    
    // Check if leave_type_id exists for director applications
    $check_leave_types_sql = "SELECT la.id, la.leave_type_id, u.first_name, u.last_name
                              FROM leave_applications la 
                              JOIN users u ON la.user_id = u.id 
                              WHERE u.role = 'director' AND la.status = 'pending'";
    $check_leave_types_stmt = $conn->prepare($check_leave_types_sql);
    $check_leave_types_stmt->execute();
    $check_leave_types_results = $check_leave_types_stmt->fetchAll();
    
    echo "<p>Director applications and their leave_type_id:</p>";
    foreach ($check_leave_types_results as $result) {
        echo "<li>App ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, leave_type_id: {$result['leave_type_id']}</li>";
        
        // Check if this leave_type_id exists in leave_types table
        $check_type_exists_sql = "SELECT id, name FROM leave_types WHERE id = :leave_type_id";
        $check_type_exists_stmt = $conn->prepare($check_type_exists_sql);
        $check_type_exists_stmt->bindParam(':leave_type_id', $result['leave_type_id'], PDO::PARAM_INT);
        $check_type_exists_stmt->execute();
        $type_exists = $check_type_exists_stmt->fetch();
        
        if ($type_exists) {
            echo "<span style='color: green;'>  ✅ Leave type exists: {$type_exists['name']}</span><br>";
        } else {
            echo "<span style='color: red;'>  ❌ Leave type ID {$result['leave_type_id']} does not exist in leave_types table!</span><br>";
        }
    }
} else {
    echo "<p>✅ leave_types JOIN is working</p>";
    
    // Step 3: Add departments JOIN
    echo "<h3>Step 3: Add departments JOIN</h3>";
    $step3_sql = "SELECT la.id, u.first_name, u.last_name, lt.name as leave_type, d.name as department, la.status, lap.approver_level, lap.status as approval_status
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_types lt ON la.leave_type_id = lt.id
                  JOIN departments d ON u.department_id = d.id
                  JOIN leave_approvals lap ON la.id = lap.leave_application_id
                  WHERE u.role = 'director' AND la.status = 'pending' 
                  AND lap.approver_level = 'admin' AND lap.status = 'pending'";
    $step3_stmt = $conn->prepare($step3_sql);
    $step3_stmt->execute();
    $step3_results = $step3_stmt->fetchAll();
    echo "<p>Results: " . count($step3_results) . "</p>";
    
    if (count($step3_results) == 0) {
        echo "<p><strong>❌ ISSUE FOUND: departments JOIN is failing!</strong></p>";
        
        // Check if department_id exists for director users
        $check_departments_sql = "SELECT u.id, u.first_name, u.last_name, u.department_id
                                  FROM users u 
                                  WHERE u.role = 'director' AND u.status = 'active'";
        $check_departments_stmt = $conn->prepare($check_departments_sql);
        $check_departments_stmt->execute();
        $check_departments_results = $check_departments_stmt->fetchAll();
        
        echo "<p>Director users and their department_id:</p>";
        foreach ($check_departments_results as $result) {
            echo "<li>User ID: {$result['id']}, Director: {$result['first_name']} {$result['last_name']}, department_id: " . ($result['department_id'] ?? 'NULL') . "</li>";
            
            if ($result['department_id']) {
                // Check if this department_id exists in departments table
                $check_dept_exists_sql = "SELECT id, name FROM departments WHERE id = :department_id";
                $check_dept_exists_stmt = $conn->prepare($check_dept_exists_sql);
                $check_dept_exists_stmt->bindParam(':department_id', $result['department_id'], PDO::PARAM_INT);
                $check_dept_exists_stmt->execute();
                $dept_exists = $check_dept_exists_stmt->fetch();
                
                if ($dept_exists) {
                    echo "<span style='color: green;'>  ✅ Department exists: {$dept_exists['name']}</span><br>";
                } else {
                    echo "<span style='color: red;'>  ❌ Department ID {$result['department_id']} does not exist in departments table!</span><br>";
                }
            } else {
                echo "<span style='color: red;'>  ❌ Director has NULL department_id!</span><br>";
            }
        }
    } else {
        echo "<p>✅ departments JOIN is working</p>";
        echo "<p>✅ All JOINs are working - the issue might be elsewhere</p>";
    }
}

echo "<hr>";
echo "<p><a href='debug_dedicated_page.php'>← Back to Debug</a></p>";
?>