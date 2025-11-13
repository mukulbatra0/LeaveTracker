<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Fix Department Issue</h1>";

// Check what departments exist
echo "<h2>Step 1: Check Existing Departments</h2>";
$existing_depts_sql = "SELECT id, name FROM departments ORDER BY id";
$existing_depts_stmt = $conn->prepare($existing_depts_sql);
$existing_depts_stmt->execute();
$existing_depts = $existing_depts_stmt->fetchAll();

if (count($existing_depts) > 0) {
    echo "<p>Existing departments:</p>";
    foreach ($existing_depts as $dept) {
        echo "<li>ID: {$dept['id']}, Name: {$dept['name']}</li>";
    }
} else {
    echo "<p>❌ No departments found!</p>";
}

// Check users with missing departments
echo "<h2>Step 2: Check Users with Missing Departments</h2>";
$missing_dept_sql = "SELECT u.id, u.first_name, u.last_name, u.role, u.department_id
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.department_id IS NOT NULL AND d.id IS NULL";
$missing_dept_stmt = $conn->prepare($missing_dept_sql);
$missing_dept_stmt->execute();
$missing_dept_users = $missing_dept_stmt->fetchAll();

if (count($missing_dept_users) > 0) {
    echo "<p>Users with missing departments:</p>";
    foreach ($missing_dept_users as $user) {
        echo "<li>User ID: {$user['id']}, Name: {$user['first_name']} {$user['last_name']}, Role: {$user['role']}, Missing Dept ID: {$user['department_id']}</li>";
    }
} else {
    echo "<p>✅ No users with missing departments</p>";
}

// Provide fix options
echo "<h2>Step 3: Fix Options</h2>";

if (count($existing_depts) > 0 && count($missing_dept_users) > 0) {
    echo "<p>Choose a fix option:</p>";
    
    echo "<h3>Option 1: Create Missing Department</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='action' value='create_department'>";
    echo "<p>Create Department ID 4:</p>";
    echo "<input type='text' name='dept_name' placeholder='Department Name (e.g., Administration)' required style='width: 300px;'>";
    echo "<button type='submit' style='margin-left: 10px;'>Create Department</button>";
    echo "</form>";
    
    echo "<h3>Option 2: Assign to Existing Department</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='action' value='reassign_department'>";
    echo "<p>Assign Dr. Alexandra Thompson to existing department:</p>";
    echo "<select name='new_dept_id' required>";
    echo "<option value=''>Select Department</option>";
    foreach ($existing_depts as $dept) {
        echo "<option value='{$dept['id']}'>{$dept['name']} (ID: {$dept['id']})</option>";
    }
    echo "</select>";
    echo "<button type='submit' style='margin-left: 10px;'>Reassign Department</button>";
    echo "</form>";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create_department') {
        $dept_name = trim($_POST['dept_name']);
        if (!empty($dept_name)) {
            try {
                // Create department with ID 4
                $create_dept_sql = "INSERT INTO departments (id, name, created_at) VALUES (4, :name, NOW())";
                $create_dept_stmt = $conn->prepare($create_dept_sql);
                $create_dept_stmt->bindParam(':name', $dept_name, PDO::PARAM_STR);
                $create_dept_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0;'>";
                echo "✅ Successfully created department: {$dept_name} (ID: 4)";
                echo "</div>";
                
                // Refresh the page to show updated data
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
                
            } catch (PDOException $e) {
                echo "<div style='color: red; font-weight: bold; margin: 20px 0;'>";
                echo "❌ Error creating department: " . $e->getMessage();
                echo "</div>";
            }
        }
    } elseif ($_POST['action'] == 'reassign_department') {
        $new_dept_id = (int)$_POST['new_dept_id'];
        if ($new_dept_id > 0) {
            try {
                // Update user's department
                $update_user_sql = "UPDATE users SET department_id = :new_dept_id WHERE id = 27";
                $update_user_stmt = $conn->prepare($update_user_sql);
                $update_user_stmt->bindParam(':new_dept_id', $new_dept_id, PDO::PARAM_INT);
                $update_user_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0;'>";
                echo "✅ Successfully reassigned Dr. Alexandra Thompson to department ID: {$new_dept_id}";
                echo "</div>";
                
                // Refresh the page to show updated data
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
                
            } catch (PDOException $e) {
                echo "<div style='color: red; font-weight: bold; margin: 20px 0;'>";
                echo "❌ Error reassigning department: " . $e->getMessage();
                echo "</div>";
            }
        }
    }
}

echo "<hr>";
echo "<h2>Test After Fix</h2>";
echo "<p><a href='debug_joins.php'>Test JOINs Again</a> | <a href='director_leave_approvals.php'>Check Director Approvals Page</a></p>";
?>