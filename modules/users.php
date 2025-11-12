<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is an HR admin
if ($role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);
        $department_id = trim($_POST['department_id']);
        $phone = trim($_POST['phone'] ?? '');
        $employee_id = trim($_POST['employee_id'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $join_date = trim($_POST['join_date'] ?? null);
        
        // Validate inputs
        $errors = [];
        
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($role)) $errors[] = "Role is required";
        if (empty($department_id)) $errors[] = "Department is required";
        
        // Check if email already exists
        $check_email_sql = "SELECT id FROM users WHERE email = :email";
        $check_email_stmt = $conn->prepare($check_email_sql);
        $check_email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $check_email_stmt->execute();
        
        if ($check_email_stmt->rowCount() > 0) {
            $errors[] = "Email already exists in the system";
        }
        
        if (empty($errors)) {
            try {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert user
                $insert_sql = "INSERT INTO users (first_name, last_name, email, password, role, department_id, phone, employee_id, position, join_date, created_at) 
                               VALUES (:first_name, :last_name, :email, :password, :role, :department_id, :phone, :employee_id, :position, :join_date, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insert_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $insert_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $insert_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $insert_stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
                $insert_stmt->bindParam(':position', $position, PDO::PARAM_STR);
                $insert_stmt->bindParam(':join_date', $join_date, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_user_id = $conn->lastInsertId();
                
                // Create leave balances for the new user
                $leave_types_sql = "SELECT id FROM leave_types";
                $leave_types_stmt = $conn->prepare($leave_types_sql);
                $leave_types_stmt->execute();
                $leave_types = $leave_types_stmt->fetchAll();
                
                foreach ($leave_types as $leave_type) {
                    // Get default balance from settings
                    $default_balance_sql = "SELECT value FROM settings WHERE name = :setting_name";
                    $default_balance_stmt = $conn->prepare($default_balance_sql);
                    $setting_name = 'default_balance_' . $leave_type['id'];
                    $default_balance_stmt->bindParam(':setting_name', $setting_name, PDO::PARAM_STR);
                    $default_balance_stmt->execute();
                    $default_balance = $default_balance_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $balance = isset($default_balance['value']) ? $default_balance['value'] : 0;
                    
                    $balance_sql = "INSERT INTO leave_balances (user_id, leave_type_id, total_days, used_days, created_at) 
                                   VALUES (:user_id, :leave_type_id, :balance, 0, NOW())";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->bindParam(':user_id', $new_user_id, PDO::PARAM_INT);
                    $balance_stmt->bindParam(':leave_type_id', $leave_type['id'], PDO::PARAM_INT);
                    $balance_stmt->bindParam(':balance', $balance, PDO::PARAM_INT);
                    $balance_stmt->execute();
                }
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'user', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_user_id, PDO::PARAM_INT);
                $description = "Created new user: $first_name $last_name ($role)";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "User created successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/users.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating user: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Edit user
    if (isset($_POST['edit_user'])) {
        $edit_user_id = $_POST['edit_user_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $department_id = trim($_POST['department_id']);
        $phone = trim($_POST['phone'] ?? '');
        $employee_id = trim($_POST['employee_id'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $join_date = trim($_POST['join_date'] ?? null);
        $password = trim($_POST['password'] ?? '');
        
        // Validate inputs
        $errors = [];
        
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($role)) $errors[] = "Role is required";
        if (empty($department_id)) $errors[] = "Department is required";
        
        // Check if email already exists for other users
        $check_email_sql = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $check_email_stmt = $conn->prepare($check_email_sql);
        $check_email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $check_email_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
        $check_email_stmt->execute();
        
        if ($check_email_stmt->rowCount() > 0) {
            $errors[] = "Email already exists for another user";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update user
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET 
                                  first_name = :first_name, 
                                  last_name = :last_name, 
                                  email = :email, 
                                  password = :password, 
                                  role = :role, 
                                  department_id = :department_id, 
                                  phone = :phone, 
                                  employee_id = :employee_id, 
                                  position = :position, 
                                  join_date = :join_date, 
                                  updated_at = NOW() 
                                  WHERE id = :user_id";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                } else {
                    // Update without changing password
                    $update_sql = "UPDATE users SET 
                                  first_name = :first_name, 
                                  last_name = :last_name, 
                                  email = :email, 
                                  role = :role, 
                                  department_id = :department_id, 
                                  phone = :phone, 
                                  employee_id = :employee_id, 
                                  position = :position, 
                                  join_date = :join_date, 
                                  updated_at = NOW() 
                                  WHERE id = :user_id";
                    $update_stmt = $conn->prepare($update_sql);
                }
                
                $update_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $update_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $update_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $update_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $update_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $update_stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
                $update_stmt->bindParam(':position', $position, PDO::PARAM_STR);
                $update_stmt->bindParam(':join_date', $join_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'update', 'user', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $edit_user_id, PDO::PARAM_INT);
                $description = "Updated user: $first_name $last_name ($role)";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "User updated successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/users.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error updating user: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $delete_user_id = $_POST['delete_user_id'];
        
        // Prevent deleting self
        if ($delete_user_id == $user_id) {
            $_SESSION['alert'] = "You cannot delete your own account!";
            $_SESSION['alert_type'] = "danger";
        } else {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Get user info for logging
                $user_info_sql = "SELECT first_name, last_name, role FROM users WHERE id = :user_id";
                $user_info_stmt = $conn->prepare($user_info_sql);
                $user_info_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $user_info_stmt->execute();
                $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete leave balances
                $delete_balances_sql = "DELETE FROM leave_balances WHERE user_id = :user_id";
                $delete_balances_stmt = $conn->prepare($delete_balances_sql);
                $delete_balances_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $delete_balances_stmt->execute();
                
                // Delete leave applications
                $delete_applications_sql = "DELETE FROM leave_applications WHERE user_id = :user_id";
                $delete_applications_stmt = $conn->prepare($delete_applications_sql);
                $delete_applications_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $delete_applications_stmt->execute();
                
                // Delete leave approvals
                $delete_approvals_sql = "DELETE FROM leave_approvals WHERE approver_id = :user_id";
                $delete_approvals_stmt = $conn->prepare($delete_approvals_sql);
                $delete_approvals_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $delete_approvals_stmt->execute();
                
                // Delete notifications
                $delete_notifications_sql = "DELETE FROM notifications WHERE user_id = :user_id OR created_by = :user_id";
                $delete_notifications_stmt = $conn->prepare($delete_notifications_sql);
                $delete_notifications_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $delete_notifications_stmt->execute();
                
                // Delete user
                $delete_user_sql = "DELETE FROM users WHERE id = :user_id";
                $delete_user_stmt = $conn->prepare($delete_user_sql);
                $delete_user_stmt->bindParam(':user_id', $delete_user_id, PDO::PARAM_INT);
                $delete_user_stmt->execute();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'delete', 'user', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $delete_user_id, PDO::PARAM_INT);
                $description = "Deleted user: {$user_info['first_name']} {$user_info['last_name']} ({$user_info['role']})";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "User deleted successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/users.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error deleting user: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
}

// Get all users
$users_sql = "SELECT u.*, d.name as department_name 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             ORDER BY u.first_name, u.last_name";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Get all departments for dropdown
$departments_sql = "SELECT * FROM departments ORDER BY name";
$departments_stmt = $conn->prepare($departments_sql);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-users me-2"></i>User Management</h2>
            <p class="text-muted">Manage users, roles, and permissions</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-1"></i> Add New User
            </button>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']); ?>
                                    <?php if (!empty($user_item['employee_id'])): ?>
                                        <small class="text-muted d-block">ID: <?php echo htmlspecialchars($user_item['employee_id']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                <td>
                                    <?php 
                                        switch ($user_item['role']) {
                                            case 'hr_admin':
                                                echo '<span class="badge bg-danger">HR Admin</span>';
                                                break;
                                            case 'principal':
                                                echo '<span class="badge bg-primary">Principal</span>';
                                                break;
                                            case 'dean':
                                                echo '<span class="badge bg-info">Dean</span>';
                                                break;
                                            case 'department_head':
                                                echo '<span class="badge bg-warning">Department Head</span>';
                                                break;
                                            case 'staff':
                                                echo '<span class="badge bg-secondary">Staff</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-light text-dark">Unknown</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($user_item['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user_item['position'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                        if (!empty($user_item['join_date'])) {
                                            echo date('M d, Y', strtotime($user_item['join_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-user-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserModal"
                                            data-id="<?php echo $user_item['id']; ?>"
                                            data-first-name="<?php echo htmlspecialchars($user_item['first_name']); ?>"
                                            data-last-name="<?php echo htmlspecialchars($user_item['last_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($user_item['email']); ?>"
                                            data-role="<?php echo htmlspecialchars($user_item['role']); ?>"
                                            data-department-id="<?php echo $user_item['department_id']; ?>"
                                            data-phone="<?php echo htmlspecialchars($user_item['phone'] ?? ''); ?>"
                                            data-employee-id="<?php echo htmlspecialchars($user_item['employee_id'] ?? ''); ?>"
                                            data-position="<?php echo htmlspecialchars($user_item['position'] ?? ''); ?>"
                                            data-join-date="<?php echo $user_item['join_date'] ?? ''; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($user_item['id'] != $user_id): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteUserModal"
                                                data-id="<?php echo $user_item['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="/modules/user_profile.php?id=<?php echo $user_item['id']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/users.php" method="post">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="staff">Staff</option>
                                <option value="department_head">Department Head</option>
                                <option value="dean">Dean</option>
                                <option value="principal">Principal</option>
                                <option value="hr_admin">HR Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label">Employee ID</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position">
                        </div>
                        <div class="col-md-6">
                            <label for="join_date" class="form-label">Join Date</label>
                            <input type="date" class="form-control" id="join_date" name="join_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/users.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_user_id" name="edit_user_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="staff">Staff</option>
                                <option value="department_head">Department Head</option>
                                <option value="dean">Dean</option>
                                <option value="principal">Principal</option>
                                <option value="hr_admin">HR Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_employee_id" class="form-label">Employee ID</label>
                            <input type="text" class="form-control" id="edit_employee_id" name="employee_id">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="edit_position" name="position">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_join_date" class="form-label">Join Date</label>
                            <input type="date" class="form-control" id="edit_join_date" name="join_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/users.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_user_id" name="delete_user_id">
                    <p>Are you sure you want to delete the user <strong id="delete_user_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i> This action will delete all associated leave applications, balances, and approvals. This cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#usersTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Edit User Modal
        document.querySelectorAll('.edit-user-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const firstName = this.getAttribute('data-first-name');
                const lastName = this.getAttribute('data-last-name');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                const departmentId = this.getAttribute('data-department-id');
                const phone = this.getAttribute('data-phone');
                const employeeId = this.getAttribute('data-employee-id');
                const position = this.getAttribute('data-position');
                const joinDate = this.getAttribute('data-join-date');
                
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_first_name').value = firstName;
                document.getElementById('edit_last_name').value = lastName;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_role').value = role;
                document.getElementById('edit_department_id').value = departmentId;
                document.getElementById('edit_phone').value = phone;
                document.getElementById('edit_employee_id').value = employeeId;
                document.getElementById('edit_position').value = position;
                document.getElementById('edit_join_date').value = joinDate;
            });
        });
        
        // Delete User Modal
        document.querySelectorAll('.delete-user-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_user_id').value = id;
                document.getElementById('delete_user_name').textContent = name;
            });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>