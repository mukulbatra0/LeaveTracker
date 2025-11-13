<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Fix Director Organizational Structure</h1>";

echo "<h2>Current Issue</h2>";
echo "<p>Directors oversee the entire institute and shouldn't be tied to specific departments. The current query fails because it requires a department JOIN.</p>";

echo "<h2>Solution Options</h2>";

echo "<h3>Option 1: Create 'Institute Administration' Department (Recommended)</h3>";
echo "<p>Create a special department for institute-level positions (Director, Admin, etc.)</p>";
echo "<form method='post'>";
echo "<input type='hidden' name='action' value='create_admin_department'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Create Institute Administration Department</button>";
echo "</form>";

echo "<h3>Option 2: Set Director Department to NULL</h3>";
echo "<p>Remove department requirement for directors and modify queries to handle NULL departments</p>";
echo "<form method='post'>";
echo "<input type='hidden' name='action' value='set_null_department'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Set Director Department to NULL</button>";
echo "</form>";

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create_admin_department') {
        try {
            // First check if department ID 4 already exists
            $check_dept_sql = "SELECT id FROM departments WHERE id = 4";
            $check_dept_stmt = $conn->prepare($check_dept_sql);
            $check_dept_stmt->execute();
            
            if ($check_dept_stmt->rowCount() == 0) {
                // Create Institute Administration department
                $create_dept_sql = "INSERT INTO departments (id, name, description, created_at) VALUES (4, 'Institute Administration', 'Executive and administrative leadership of the institute', NOW())";
                $create_dept_stmt = $conn->prepare($create_dept_sql);
                $create_dept_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "✅ Successfully created 'Institute Administration' department (ID: 4)";
                echo "<br>This department will house institute-level positions like Director, Admin, etc.";
                echo "</div>";
            } else {
                echo "<div style='color: orange; font-weight: bold; margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;'>";
                echo "ℹ️ Department ID 4 already exists. Updating it to 'Institute Administration'";
                echo "</div>";
                
                // Update existing department
                $update_dept_sql = "UPDATE departments SET name = 'Institute Administration', description = 'Executive and administrative leadership of the institute' WHERE id = 4";
                $update_dept_stmt = $conn->prepare($update_dept_sql);
                $update_dept_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "✅ Updated department ID 4 to 'Institute Administration'";
                echo "</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div style='color: red; font-weight: bold; margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "❌ Error creating department: " . $e->getMessage();
            echo "</div>";
        }
        
    } elseif ($_POST['action'] == 'set_null_department') {
        try {
            // Set director's department to NULL
            $update_user_sql = "UPDATE users SET department_id = NULL WHERE role = 'director'";
            $update_user_stmt = $conn->prepare($update_user_sql);
            $update_user_stmt->execute();
            
            echo "<div style='color: green; font-weight: bold; margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
            echo "✅ Set director's department to NULL";
            echo "<br>Note: You'll need to modify the queries to use LEFT JOIN for departments";
            echo "</div>";
            
            // Also need to update the queries to handle NULL departments
            echo "<div style='color: blue; font-weight: bold; margin: 20px 0; padding: 15px; background: #cce7ff; border: 1px solid #99d6ff; border-radius: 5px;'>";
            echo "⚠️ Important: The queries in director_leave_approvals.php need to be updated to use LEFT JOIN for departments";
            echo "</div>";
            
        } catch (PDOException $e) {
            echo "<div style='color: red; font-weight: bold; margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "❌ Error updating user: " . $e->getMessage();
            echo "</div>";
        }
    }
}

// Show current status
echo "<h2>Current Status</h2>";

// Check director's current department
$director_dept_sql = "SELECT u.id, u.first_name, u.last_name, u.department_id, d.name as dept_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.role = 'director'";
$director_dept_stmt = $conn->prepare($director_dept_sql);
$director_dept_stmt->execute();
$director_info = $director_dept_stmt->fetchAll();

if (count($director_info) > 0) {
    echo "<p>Director information:</p>";
    foreach ($director_info as $director) {
        echo "<li>Name: {$director['first_name']} {$director['last_name']}</li>";
        echo "<li>Department ID: " . ($director['department_id'] ?? 'NULL') . "</li>";
        echo "<li>Department Name: " . ($director['dept_name'] ?? 'No Department') . "</li>";
    }
}

echo "<hr>";
echo "<h2>Test After Fix</h2>";
echo "<p><a href='debug_joins.php'>Test JOINs Again</a> | <a href='director_leave_approvals.php'>Check Director Approvals Page</a></p>";

echo "<h2>Recommended Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Choose Option 1</strong> (Create Institute Administration Department) - This is cleaner and maintains data integrity</li>";
echo "<li><strong>Test the director approvals page</strong> - It should now work properly</li>";
echo "<li><strong>Consider creating similar structure</strong> for other institute-level roles if needed</li>";
echo "</ol>";
?>