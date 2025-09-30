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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new department
    if (isset($_POST['add_department'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Department name is required";
        } else {
            // Check if department name already exists
            $check_name_sql = "SELECT COUNT(*) FROM departments WHERE name = :name";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $check_name_stmt->execute();
            
            if ($check_name_stmt->fetchColumn() > 0) {
                $errors[] = "Department name already exists";
            }
        }
        
        // If no errors, insert new department
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO departments (name, description, head_id, created_at) 
                              VALUES (:name, :description, :head_id, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $insert_stmt->execute();
                
                // Add audit log
                $action = "Created new department: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                // If a department head is assigned, update their role
                if (!empty($head_id)) {
                    $update_role_sql = "UPDATE users SET role = 'department_head' WHERE id = :head_id";
                    $update_role_stmt = $conn->prepare($update_role_sql);
                    $update_role_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                    $update_role_stmt->execute();
                    
                    // Get head name for audit log
                    $head_name_sql = "SELECT first_name, last_name FROM users WHERE id = :head_id";
                    $head_name_stmt = $conn->prepare($head_name_sql);
                    $head_name_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                    $head_name_stmt->execute();
                    $head_name = $head_name_stmt->fetch();
                    
                    // Add audit log for role change
                    $action = "Updated user {$head_name['first_name']} {$head_name['last_name']} role to department_head";
                    $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                    $audit_stmt = $conn->prepare($audit_sql);
                    $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                    $audit_stmt->execute();
                }
                
                $conn->commit();
                
                $_SESSION['alert'] = "Department added successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: ../admin/departments.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Edit department
    if (isset($_POST['edit_department'])) {
        $edit_dept_id = $_POST['edit_dept_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Department name is required";
        } else {
            // Check if department name already exists for other departments
            $check_name_sql = "SELECT COUNT(*) FROM departments WHERE name = :name AND id != :dept_id";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $check_name_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
            $check_name_stmt->execute();
            
            if ($check_name_stmt->fetchColumn() > 0) {
                $errors[] = "Department name already exists";
            }
        }
        
        // If no errors, update department
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Get current department head
                $current_head_sql = "SELECT head_id FROM departments WHERE id = :dept_id";
                $current_head_stmt = $conn->prepare($current_head_sql);
                $current_head_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                $current_head_stmt->execute();
                $current_head_id = $current_head_stmt->fetchColumn();
                
                // Update department
                $update_sql = "UPDATE departments SET 
                              name = :name, 
                              description = :description, 
                              head_id = :head_id, 
                              updated_at = NOW() 
                              WHERE id = :dept_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $action = "Updated department ID $edit_dept_id: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                // If department head has changed
                if ($current_head_id != $head_id) {
                    // If there was a previous head, check if they're head of any other department
                    if (!empty($current_head_id)) {
                        $check_other_dept_sql = "SELECT COUNT(*) FROM departments WHERE head_id = :head_id AND id != :dept_id";
                        $check_other_dept_stmt = $conn->prepare($check_other_dept_sql);
                        $check_other_dept_stmt->bindParam(':head_id', $current_head_id, PDO::PARAM_INT);
                        $check_other_dept_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                        $check_other_dept_stmt->execute();
                        
                        // If not head of any other department, revert role to staff
                        if ($check_other_dept_stmt->fetchColumn() == 0) {
                            $update_old_head_sql = "UPDATE users SET role = 'staff' WHERE id = :head_id";
                            $update_old_head_stmt = $conn->prepare($update_old_head_sql);
                            $update_old_head_stmt->bindParam(':head_id', $current_head_id, PDO::PARAM_INT);
                            $update_old_head_stmt->execute();
                            
                            // Get old head name for audit log
                            $old_head_name_sql = "SELECT first_name, last_name FROM users WHERE id = :head_id";
                            $old_head_name_stmt = $conn->prepare($old_head_name_sql);
                            $old_head_name_stmt->bindParam(':head_id', $current_head_id, PDO::PARAM_INT);
                            $old_head_name_stmt->execute();
                            $old_head_name = $old_head_name_stmt->fetch();
                            
                            // Add audit log for role change
                            $action = "Updated user {$old_head_name['first_name']} {$old_head_name['last_name']} role to staff";
                            $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                            $audit_stmt = $conn->prepare($audit_sql);
                            $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                            $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                            $audit_stmt->execute();
                        }
                    }
                    
                    // If a new head is assigned, update their role
                    if (!empty($head_id)) {
                        $update_new_head_sql = "UPDATE users SET role = 'department_head' WHERE id = :head_id";
                        $update_new_head_stmt = $conn->prepare($update_new_head_sql);
                        $update_new_head_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                        $update_new_head_stmt->execute();
                        
                        // Get new head name for audit log
                        $new_head_name_sql = "SELECT first_name, last_name FROM users WHERE id = :head_id";
                        $new_head_name_stmt = $conn->prepare($new_head_name_sql);
                        $new_head_name_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                        $new_head_name_stmt->execute();
                        $new_head_name = $new_head_name_stmt->fetch();
                        
                        // Add audit log for role change
                        $action = "Updated user {$new_head_name['first_name']} {$new_head_name['last_name']} role to department_head";
                        $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                        $audit_stmt = $conn->prepare($audit_sql);
                        $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                        $audit_stmt->execute();
                    }
                }
                
                $conn->commit();
                
                $_SESSION['alert'] = "Department updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: ../admin/departments.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get all departments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE d.name LIKE :search OR d.description LIKE :search";
    $params[':search'] = "%$search%";
}

$departments_sql = "SELECT d.*, 
                   u.first_name as head_first_name, 
                   u.last_name as head_last_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id) as staff_count
                   FROM departments d 
                   LEFT JOIN users u ON d.head_id = u.id 
                   $where_clause 
                   ORDER BY d.name ASC 
                   LIMIT :limit OFFSET :offset";
$departments_stmt = $conn->prepare($departments_sql);

foreach ($params as $key => $value) {
    $departments_stmt->bindValue($key, $value);
}

$departments_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$departments_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll();

// Get total departments count for pagination
$count_sql = "SELECT COUNT(*) FROM departments d $where_clause";
$count_stmt = $conn->prepare($count_sql);

foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}

$count_stmt->execute();
$total_departments = $count_stmt->fetchColumn();
$total_pages = ceil($total_departments / $limit);

// Get all staff for department head dropdown
$staff_sql = "SELECT id, first_name, last_name, email, department_id, role 
             FROM users 
             WHERE status = 'active' 
             ORDER BY first_name, last_name ASC";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->execute();
$staff = $staff_stmt->fetchAll();

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Department Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/dashboards/hr_admin_dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Departments</li>
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
                <i class="fas fa-building me-1"></i>
                Departments
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus"></i> Add Department
            </button>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search department name or description" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="/admin/departments.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Departments Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Department Head</th>
                            <th>Staff Count</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No departments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo $dept['id']; ?></td>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['description'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($dept['head_first_name'])): ?>
                                            <?php echo htmlspecialchars($dept['head_first_name'] . ' ' . $dept['head_last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $dept['staff_count']; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-department" 
                                                data-bs-toggle="modal" data-bs-target="#editDepartmentModal"
                                                data-id="<?php echo $dept['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($dept['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($dept['description'] ?? ''); ?>"
                                                data-head-id="<?php echo $dept['head_id'] ?? ''; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="/admin/users.php?department=<?php echo $dept['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-users"></i> View Staff
                                        </a>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="head_id" class="form-label">Department Head</label>
                        <select class="form-select" id="head_id" name="head_id">
                            <option value="">Select Department Head</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">If selected, this user's role will be updated to Department Head.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_dept_id" id="edit_dept_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_head_id" class="form-label">Department Head</label>
                        <select class="form-select" id="edit_head_id" name="head_id">
                            <option value="">Select Department Head</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">If changed, roles will be updated accordingly.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_department" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit Department Modal
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-department');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const headId = this.getAttribute('data-head-id');
                
                document.getElementById('edit_dept_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_head_id').value = headId;
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>