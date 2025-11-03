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

// Check if user is an admin or HR admin
if ($role != 'admin' && $role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../dashboards/admin_dashboard.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = $_POST['department_id'];
        $role = $_POST['role'];
        $default_password = $_ENV['DEFAULT_PASSWORD'] ?? 'TempPass123!';
        $password = password_hash($default_password, PASSWORD_DEFAULT);
        
        // Validate input
        $errors = [];
        
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email already exists
            $check_email_sql = "SELECT COUNT(*) FROM users WHERE email = :email";
            $check_email_stmt = $conn->prepare($check_email_sql);
            $check_email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $check_email_stmt->execute();
            
            if ($check_email_stmt->fetchColumn() > 0) {
                $errors[] = "Email already exists";
            }
        }
        
        if (empty($department_id)) {
            $errors[] = "Department is required";
        }
        
        if (empty($role)) {
            $errors[] = "Role is required";
        }
        
        // If no errors, insert new user
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO users (first_name, last_name, email, phone, department_id, role, password, created_at) 
                              VALUES (:first_name, :last_name, :email, :phone, :department_id, :role, :password, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insert_stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $insert_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $insert_stmt->bindParam(':password', $password, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_user_id = $conn->lastInsertId();
                
                // Add audit log
                $action = "Created new user: $first_name $last_name ($email)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                // Create leave balances for the new user
                $leave_types_sql = "SELECT id FROM leave_types";
                $leave_types_stmt = $conn->prepare($leave_types_sql);
                $leave_types_stmt->execute();
                $leave_types = $leave_types_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($leave_types as $leave_type_id) {
                    $balance_sql = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                                   VALUES (:user_id, :leave_type_id, YEAR(CURDATE()), 
                                   (SELECT default_days FROM leave_types WHERE id = :leave_type_id2), 0)";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->bindParam(':user_id', $new_user_id, PDO::PARAM_INT);
                    $balance_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
                    $balance_stmt->bindParam(':leave_type_id2', $leave_type_id, PDO::PARAM_INT);
                    $balance_stmt->execute();
                }
                
                $conn->commit();
                
                $_SESSION['alert'] = "User added successfully. Default password has been set.";
                $_SESSION['alert_type'] = "success";
                header("Location: ../admin/users.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Edit user
    if (isset($_POST['edit_user'])) {
        $edit_user_id = $_POST['edit_user_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = $_POST['department_id'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Validate input
        $errors = [];
        
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email already exists for other users
            $check_email_sql = "SELECT COUNT(*) FROM users WHERE email = :email AND id != :user_id";
            $check_email_stmt = $conn->prepare($check_email_sql);
            $check_email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $check_email_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
            $check_email_stmt->execute();
            
            if ($check_email_stmt->fetchColumn() > 0) {
                $errors[] = "Email already exists";
            }
        }
        
        if (empty($department_id)) {
            $errors[] = "Department is required";
        }
        
        if (empty($role)) {
            $errors[] = "Role is required";
        }
        
        // If no errors, update user
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $update_sql = "UPDATE users SET 
                              first_name = :first_name, 
                              last_name = :last_name, 
                              email = :email, 
                              phone = :phone, 
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
                $update_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $update_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $action = "Updated user ID $edit_user_id: $first_name $last_name ($email)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "User updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: ../admin/users.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Reset password
    if (isset($_POST['reset_password'])) {
        $reset_user_id = $_POST['reset_user_id'];
        $default_password = $_ENV['DEFAULT_PASSWORD'] ?? 'TempPass123!';
        $new_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        try {
            $conn->beginTransaction();
            
            $reset_sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
            $reset_stmt = $conn->prepare($reset_sql);
            $reset_stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
            $reset_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
            $reset_stmt->execute();
            
            // Get user details for audit log
            $user_details_sql = "SELECT first_name, last_name FROM users WHERE id = :user_id";
            $user_details_stmt = $conn->prepare($user_details_sql);
            $user_details_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
            $user_details_stmt->execute();
            $user_details = $user_details_stmt->fetch();
            
            // Add audit log
            $action = "Reset password for user ID $reset_user_id: {$user_details['first_name']} {$user_details['last_name']}";
            $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $audit_stmt->execute();
            
            $conn->commit();
            
            $_SESSION['alert'] = "Password reset successfully. New password has been set.";
            $_SESSION['alert_type'] = "success";
            header("Location: ../admin/users.php");
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get all users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($department_filter)) {
    $where_clauses[] = "u.department_id = :department_id";
    $params[':department_id'] = $department_filter;
}

if (!empty($role_filter)) {
    $where_clauses[] = "u.role = :role";
    $params[':role'] = $role_filter;
}

if (!empty($status_filter)) {
    $where_clauses[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$users_sql = "SELECT u.*, d.name as department_name 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             $where_sql 
             ORDER BY u.created_at DESC 
             LIMIT :limit OFFSET :offset";
$users_stmt = $conn->prepare($users_sql);

foreach ($params as $key => $value) {
    $users_stmt->bindValue($key, $value);
}

$users_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$users_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Get total users count for pagination
$count_sql = "SELECT COUNT(*) FROM users u $where_sql";
$count_stmt = $conn->prepare($count_sql);

foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}

$count_stmt->execute();
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Get all departments for dropdown
$departments_sql = "SELECT * FROM departments ORDER BY name ASC";
$departments_stmt = $conn->prepare($departments_sql);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll();

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">User Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../dashboards/admin_dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Users</li>
    </ol>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-users me-1"></i>
                Users
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search name or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="staff" <?php echo ($role_filter == 'staff') ? 'selected' : ''; ?>>Staff</option>
                            <option value="department_head" <?php echo ($role_filter == 'department_head') ? 'selected' : ''; ?>>Department Head</option>
                            <option value="dean" <?php echo ($role_filter == 'dean') ? 'selected' : ''; ?>>Dean</option>
                            <option value="principal" <?php echo ($role_filter == 'principal') ? 'selected' : ''; ?>>Principal</option>
                            <option value="hr_admin" <?php echo ($role_filter == 'hr_admin') ? 'selected' : ''; ?>>HR Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="/admin/users.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($u['department_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        switch ($u['role']) {
                                            case 'staff':
                                                echo '<span class="badge bg-primary">Staff</span>';
                                                break;
                                            case 'department_head':
                                                echo '<span class="badge bg-success">Department Head</span>';
                                                break;
                                            case 'dean':
                                                echo '<span class="badge bg-info">Dean</span>';
                                                break;
                                            case 'principal':
                                                echo '<span class="badge bg-warning">Principal</span>';
                                                break;
                                            case 'hr_admin':
                                                echo '<span class="badge bg-danger">HR Admin</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">' . ucfirst($u['role']) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($u['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-primary
                                            btn-outline-primary1 edit-user" 
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-id="<?php echo $u['id']; ?>"
                                                    data-first-name="<?php echo htmlspecialchars($u['first_name']); ?>"
                                                    data-last-name="<?php echo htmlspecialchars($u['last_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"
                                                    data-department="<?php echo $u['department_id']; ?>"
                                                    data-role="<?php echo $u['role']; ?>"
                                                    data-status="<?php echo $u['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning reset-password"
                                                    data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                    data-id="<?php echo $u['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
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
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Default password will be set. User will be prompted to change it on first login.
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
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <div class="modal-body">
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
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="reset_user_id" id="reset_user_id">
                <div class="modal-body">
                    <p>Are you sure you want to reset the password for <strong id="reset_user_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> The password will be reset to the default password.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit User Modal
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-user');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const firstName = this.getAttribute('data-first-name');
                const lastName = this.getAttribute('data-last-name');
                const email = this.getAttribute('data-email');
                const phone = this.getAttribute('data-phone');
                const department = this.getAttribute('data-department');
                const role = this.getAttribute('data-role');
                const status = this.getAttribute('data-status');
                
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_first_name').value = firstName;
                document.getElementById('edit_last_name').value = lastName;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_phone').value = phone;
                document.getElementById('edit_department_id').value = department;
                document.getElementById('edit_role').value = role;
                document.getElementById('edit_status').value = status;
            });
        });
        
        // Reset Password Modal
        const resetButtons = document.querySelectorAll('.reset-password');
        resetButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('reset_user_id').value = id;
                document.getElementById('reset_user_name').textContent = name;
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>