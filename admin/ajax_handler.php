<?php
session_start();

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Set JSON header for AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Response array
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    // Handle different actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add_user':
                if ($role != 'admin') {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $employee_id = trim($_POST['employee_id']);
                $staff_type = $_POST['staff_type'];
                $gender = $_POST['gender'];
                $employment_type = $_POST['employment_type'] ?? 'full_time';
                $department_id = $_POST['department_id'];
                $user_role = $_POST['role'];
                
                // Generate default password
                $name_prefix = strtoupper(substr($first_name, 0, 3));
                $default_password = $name_prefix . '@123';
                $password = password_hash($default_password, PASSWORD_DEFAULT);
                
                // Validate
                if (empty($first_name) || empty($email) || empty($employee_id)) {
                    throw new Exception("Required fields are missing");
                }
                
                // Check if email exists
                $check_email_sql = "SELECT COUNT(*) FROM users WHERE email = :email";
                $check_email_stmt = $conn->prepare($check_email_sql);
                $check_email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $check_email_stmt->execute();
                
                if ($check_email_stmt->fetchColumn() > 0) {
                    throw new Exception("Email already exists");
                }
                
                // Check if employee_id exists
                $check_emp_sql = "SELECT COUNT(*) FROM users WHERE employee_id = :employee_id";
                $check_emp_stmt = $conn->prepare($check_emp_sql);
                $check_emp_stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
                $check_emp_stmt->execute();
                
                if ($check_emp_stmt->fetchColumn() > 0) {
                    throw new Exception("Employee ID already exists");
                }
                
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO users (first_name, last_name, email, phone, department_id, role, password, employee_id, staff_type, gender, employment_type, created_at) 
                              VALUES (:first_name, :last_name, :email, :phone, :department_id, :role, :password, :employee_id, :staff_type, :gender, :employment_type, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insert_stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $insert_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':role', $user_role, PDO::PARAM_STR);
                $insert_stmt->bindParam(':password', $password, PDO::PARAM_STR);
                $insert_stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
                $insert_stmt->bindParam(':staff_type', $staff_type, PDO::PARAM_STR);
                $insert_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
                $insert_stmt->bindParam(':employment_type', $employment_type, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_user_id = $conn->lastInsertId();
                
                // Add audit log
                $audit_action = "Created new user: $first_name $last_name ($email)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                // Create leave balances
                $policy_sql = "SELECT lpr.leave_type_id, lpr.allocated_days 
                              FROM leave_policy_rules lpr 
                              JOIN leave_types lt ON lpr.leave_type_id = lt.id 
                              WHERE lpr.is_active = 1 AND lt.is_active = 1
                              AND (lpr.staff_type = :staff_type OR lpr.staff_type = 'all')
                              AND (lpr.gender = :gender OR lpr.gender = 'all')
                              AND (lpr.employment_type = :employment_type OR lpr.employment_type = 'all')";
                $policy_stmt = $conn->prepare($policy_sql);
                $policy_stmt->bindParam(':staff_type', $staff_type, PDO::PARAM_STR);
                $policy_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
                $policy_stmt->bindParam(':employment_type', $employment_type, PDO::PARAM_STR);
                $policy_stmt->execute();
                $policies = $policy_stmt->fetchAll();
                
                $allocated = [];
                foreach ($policies as $p) {
                    if (!isset($allocated[$p['leave_type_id']])) {
                        $allocated[$p['leave_type_id']] = $p['allocated_days'];
                    }
                }
                
                foreach ($allocated as $lt_id => $days) {
                    $balance_sql = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days, created_at) 
                                   VALUES (:user_id, :leave_type_id, YEAR(CURDATE()), :total_days, 0, NOW())";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->bindParam(':user_id', $new_user_id, PDO::PARAM_INT);
                    $balance_stmt->bindParam(':leave_type_id', $lt_id, PDO::PARAM_INT);
                    $balance_stmt->bindParam(':total_days', $days);
                    $balance_stmt->execute();
                }
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "User added successfully!",
                    'reload' => true,
                    'data' => ['user_id' => $new_user_id]
                ];
                break;

            case 'edit_user':
                if ($role != 'admin') {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $edit_user_id = $_POST['edit_user_id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $employee_id = trim($_POST['employee_id']);
                $staff_type = $_POST['staff_type'];
                $gender = $_POST['gender'];
                $employment_type = $_POST['employment_type'] ?? 'full_time';
                $department_id = $_POST['department_id'];
                $user_role = $_POST['role'];
                $status = $_POST['status'];
                
                // Validate
                if (empty($first_name) || empty($email) || empty($employee_id)) {
                    throw new Exception("Required fields are missing");
                }
                
                $conn->beginTransaction();
                
                $update_sql = "UPDATE users SET 
                              first_name = :first_name, 
                              last_name = :last_name, 
                              email = :email, 
                              phone = :phone, 
                              employee_id = :employee_id,
                              staff_type = :staff_type,
                              gender = :gender,
                              employment_type = :employment_type,
                              department_id = :department_id, 
                              role = :role, 
                              status = :status, 
                              updated_at = NOW() 
                              WHERE id = :user_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $update_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $update_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $update_stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $update_stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
                $update_stmt->bindParam(':staff_type', $staff_type, PDO::PARAM_STR);
                $update_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
                $update_stmt->bindParam(':employment_type', $employment_type, PDO::PARAM_STR);
                $update_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':role', $user_role, PDO::PARAM_STR);
                $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $update_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $audit_action = "Updated user ID $edit_user_id: $first_name $last_name ($email)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "User updated successfully!",
                    'reload' => true
                ];
                break;

            case 'add_department':
                if ($role != 'admin' && $role != 'hr_admin') {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
                
                if (empty($name) || empty($code)) {
                    throw new Exception("Department name and code are required");
                }
                
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO departments (name, code, description, head_id, created_at) 
                              VALUES (:name, :code, :description, :head_id, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':code', $code, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $insert_stmt->execute();
                
                $new_dept_id = $conn->lastInsertId();
                
                // Add audit log
                $audit_action = "Created new department: $name ($code)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
                             VALUES (:user_id, :action, 'department', :entity_id, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->bindParam(':entity_id', $new_dept_id, PDO::PARAM_INT);
                $audit_stmt->execute();
                
                if (!empty($head_id)) {
                    $update_role_sql = "UPDATE users SET role = 'department_head' WHERE id = :head_id";
                    $update_role_stmt = $conn->prepare($update_role_sql);
                    $update_role_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                    $update_role_stmt->execute();
                }
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "Department added successfully!",
                    'reload' => true
                ];
                break;

            case 'edit_department':
                if ($role != 'admin' && $role != 'hr_admin') {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $edit_dept_id = $_POST['edit_dept_id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
                
                if (empty($name) || empty($code)) {
                    throw new Exception("Department name and code are required");
                }
                
                $conn->beginTransaction();
                
                $update_sql = "UPDATE departments SET 
                              name = :name, 
                              code = :code,
                              description = :description, 
                              head_id = :head_id, 
                              updated_at = NOW() 
                              WHERE id = :dept_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':code', $code, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $audit_action = "Updated department ID $edit_dept_id: $name ($code)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
                             VALUES (:user_id, :action, 'department', :entity_id, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->bindParam(':entity_id', $edit_dept_id, PDO::PARAM_INT);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "Department updated successfully!",
                    'reload' => true
                ];
                break;

            case 'add_leave_type':
                $allowed_roles = ['admin', 'hr_admin'];
                if (!in_array($role, $allowed_roles)) {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $default_days = (int)$_POST['default_days'];
                $color = '#3498db';
                $requires_document = isset($_POST['requires_document']) ? 1 : 0;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $accrual_method = trim($_POST['accrual_method']);
                $carry_forward_days = (int)$_POST['carry_forward_days'];
                $max_consecutive_days = (int)$_POST['max_consecutive_days'];
                
                if (empty($name)) {
                    throw new Exception("Leave type name is required");
                }
                
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO leave_types (name, description, default_days, color, requires_attachment, is_active, accrual_method, carry_forward_days, max_consecutive_days, created_at) 
                              VALUES (:name, :description, :default_days, :color, :requires_attachment, :is_active, :accrual_method, :carry_forward_days, :max_consecutive_days, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':default_days', $default_days, PDO::PARAM_INT);
                $insert_stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $insert_stmt->bindParam(':requires_attachment', $requires_document, PDO::PARAM_INT);
                $insert_stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $insert_stmt->bindParam(':accrual_method', $accrual_method, PDO::PARAM_STR);
                $insert_stmt->bindParam(':carry_forward_days', $carry_forward_days, PDO::PARAM_INT);
                $insert_stmt->bindParam(':max_consecutive_days', $max_consecutive_days, PDO::PARAM_INT);
                $insert_stmt->execute();
                
                // Add audit log
                $audit_action = "Created new leave type: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "Leave type added successfully!",
                    'reload' => true
                ];
                break;

            case 'edit_leave_type':
                $allowed_roles = ['admin', 'hr_admin'];
                if (!in_array($role, $allowed_roles)) {
                    throw new Exception("You don't have permission to perform this action.");
                }
                
                $edit_type_id = $_POST['edit_type_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $default_days = (int)$_POST['default_days'];
                $color = '#3498db';
                $requires_document = isset($_POST['requires_document']) ? 1 : 0;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $accrual_method = trim($_POST['accrual_method']);
                $carry_forward_days = (int)$_POST['carry_forward_days'];
                $max_consecutive_days = (int)$_POST['max_consecutive_days'];
                
                if (empty($name)) {
                    throw new Exception("Leave type name is required");
                }
                
                $conn->beginTransaction();
                
                $update_sql = "UPDATE leave_types SET 
                              name = :name, 
                              description = :description, 
                              default_days = :default_days, 
                              color = :color, 
                              requires_attachment = :requires_attachment, 
                              is_active = :is_active, 
                              accrual_method = :accrual_method, 
                              carry_forward_days = :carry_forward_days, 
                              max_consecutive_days = :max_consecutive_days, 
                              updated_at = NOW() 
                              WHERE id = :type_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':default_days', $default_days, PDO::PARAM_INT);
                $update_stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $update_stmt->bindParam(':requires_attachment', $requires_document, PDO::PARAM_INT);
                $update_stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $update_stmt->bindParam(':accrual_method', $accrual_method, PDO::PARAM_STR);
                $update_stmt->bindParam(':carry_forward_days', $carry_forward_days, PDO::PARAM_INT);
                $update_stmt->bindParam(':max_consecutive_days', $max_consecutive_days, PDO::PARAM_INT);
                $update_stmt->bindParam(':type_id', $edit_type_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $audit_action = "Updated leave type ID $edit_type_id: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $audit_action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $response = [
                    'success' => true,
                    'message' => "Leave type updated successfully!",
                    'reload' => true
                ];
                break;

            case 'test':
                // Test action for demonstration
                $test_name = $_POST['test_name'] ?? '';
                $test_email = $_POST['test_email'] ?? '';
                
                if (empty($test_name) || empty($test_email)) {
                    throw new Exception("Please fill in all fields");
                }
                
                // Simulate processing delay
                sleep(1);
                
                $response = [
                    'success' => true,
                    'message' => "Test successful! Name: {$test_name}, Email: {$test_email}",
                    'reload' => false
                ];
                break;

            default:
                throw new Exception("Invalid action");
        }
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Return JSON response for AJAX requests
if ($isAjax) {
    echo json_encode($response);
    exit;
}

// For non-AJAX requests, redirect back with session message
if ($response['success']) {
    $_SESSION['alert'] = $response['message'];
    $_SESSION['alert_type'] = 'success';
} else {
    $_SESSION['alert'] = $response['message'];
    $_SESSION['alert_type'] = 'danger';
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
