<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Fix Department Structure (With Code Field)</h1>";

// First, let's check the departments table structure
echo "<h2>Step 1: Check Departments Table Structure</h2>";
try {
    $structure_sql = "DESCRIBE departments";
    $structure_stmt = $conn->prepare($structure_sql);
    $structure_stmt->execute();
    $structure = $structure_stmt->fetchAll();
    
    echo "<p>Departments table structure:</p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $field) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p>Error checking table structure: " . $e->getMessage() . "</p>";
}

// Check existing departments to see what codes are used
echo "<h2>Step 2: Check Existing Departments</h2>";
try {
    $existing_sql = "SELECT id, name, code FROM departments ORDER BY id";
    $existing_stmt = $conn->prepare($existing_sql);
    $existing_stmt->execute();
    $existing_depts = $existing_stmt->fetchAll();
    
    if (count($existing_depts) > 0) {
        echo "<p>Existing departments:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Code</th></tr>";
        foreach ($existing_depts as $dept) {
            echo "<tr>";
            echo "<td>{$dept['id']}</td>";
            echo "<td>{$dept['name']}</td>";
            echo "<td>" . ($dept['code'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No existing departments found.</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking existing departments: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 3: Create Institute Administration Department</h2>";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'create_with_code') {
    $dept_code = trim($_POST['dept_code']);
    $dept_name = trim($_POST['dept_name']);
    
    if (!empty($dept_code) && !empty($dept_name)) {
        try {
            // Check if department ID 4 already exists
            $check_sql = "SELECT id FROM departments WHERE id = 4";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                // Create new department with code
                $create_sql = "INSERT INTO departments (id, name, code, description, created_at) VALUES (4, :name, :code, :description, NOW())";
                $create_stmt = $conn->prepare($create_sql);
                $create_stmt->bindParam(':name', $dept_name, PDO::PARAM_STR);
                $create_stmt->bindParam(':code', $dept_code, PDO::PARAM_STR);
                $description = "Executive and administrative leadership of the institute";
                $create_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $create_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "✅ Successfully created department: {$dept_name} (Code: {$dept_code}, ID: 4)";
                echo "</div>";
                
            } else {
                // Update existing department
                $update_sql = "UPDATE departments SET name = :name, code = :code, description = :description WHERE id = 4";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $dept_name, PDO::PARAM_STR);
                $update_stmt->bindParam(':code', $dept_code, PDO::PARAM_STR);
                $description = "Executive and administrative leadership of the institute";
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->execute();
                
                echo "<div style='color: green; font-weight: bold; margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "✅ Successfully updated department ID 4: {$dept_name} (Code: {$dept_code})";
                echo "</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div style='color: red; font-weight: bold; margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "❌ Error creating/updating department: " . $e->getMessage();
            echo "</div>";
        }
    }
}

// Show form to create department with proper code
echo "<form method='post'>";
echo "<input type='hidden' name='action' value='create_with_code'>";
echo "<table>";
echo "<tr>";
echo "<td><label for='dept_name'>Department Name:</label></td>";
echo "<td><input type='text' id='dept_name' name='dept_name' value='Institute Administration' required style='width: 250px;'></td>";
echo "</tr>";
echo "<tr>";
echo "<td><label for='dept_code'>Department Code:</label></td>";
echo "<td><input type='text' id='dept_code' name='dept_code' value='ADMIN' required style='width: 100px;' placeholder='e.g., ADMIN'></td>";
echo "</tr>";
echo "</table>";
echo "<br>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Create Institute Administration Department</button>";
echo "</form>";

echo "<h2>Alternative: Quick Fix Options</h2>";
echo "<p>If you want to use a simple code:</p>";
echo "<ul>";
echo "<li><strong>ADMIN</strong> - Simple and clear</li>";
echo "<li><strong>INST</strong> - Short for Institute</li>";
echo "<li><strong>EXEC</strong> - Executive level</li>";
echo "<li><strong>DIR</strong> - Director's department</li>";
echo "</ul>";

// Show current director status
echo "<h2>Current Director Status</h2>";
$director_sql = "SELECT u.id, u.first_name, u.last_name, u.department_id, d.name as dept_name, d.code as dept_code
                 FROM users u
                 LEFT JOIN departments d ON u.department_id = d.id
                 WHERE u.role = 'director'";
$director_stmt = $conn->prepare($director_sql);
$director_stmt->execute();
$directors = $director_stmt->fetchAll();

if (count($directors) > 0) {
    foreach ($directors as $director) {
        echo "<p>Director: {$director['first_name']} {$director['last_name']}</p>";
        echo "<p>Current Department ID: " . ($director['department_id'] ?? 'NULL') . "</p>";
        echo "<p>Current Department: " . ($director['dept_name'] ?? 'No Department') . "</p>";
        echo "<p>Current Department Code: " . ($director['dept_code'] ?? 'No Code') . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='director_leave_approvals.php'>Test Director Approvals Page</a></p>";
?>