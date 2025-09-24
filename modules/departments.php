<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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
    header("Location: ../index.php");
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new department
    if (isset($_POST['add_department'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Department name is required";
        
        // Check if department name already exists
        $check_name_sql = "SELECT id FROM departments WHERE name = :name";
        $check_name_stmt = $conn->prepare($check_name_sql);
        $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $check_name_stmt->execute();
        
        if ($check_name_stmt->rowCount() > 0) {
            $errors[] = "Department name already exists";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert department
                $insert_sql = "INSERT INTO departments (name, description, location, head_id, created_at) 
                               VALUES (:name, :description, :location, :head_id, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':location', $location, PDO::PARAM_STR);
                $insert_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $insert_stmt->execute();
                
                $new_dept_id = $conn->lastInsertId();
                
                // If head_id is provided, update the user's role to department_head
                if (!empty($head_id)) {
                    $update_head_sql = "UPDATE users SET role = 'department_head' WHERE id = :head_id";
                    $update_head_stmt = $conn->prepare($update_head_sql);
                    $update_head_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                    $update_head_stmt->execute();
                }
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'department', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_dept_id, PDO::PARAM_INT);
                $description = "Created new department: $name";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Department created successfully!";
                $_SESSION['alert_type'] = "success";
                header("Location: ../modules/departments.php");
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating department: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Edit department
    if (isset($_POST['edit_department'])) {
        $edit_dept_id = $_POST['edit_dept_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $head_id = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Department name is required";
        
        // Check if department name already exists for other departments
        $check_name_sql = "SELECT id FROM departments WHERE name = :name AND id != :dept_id";
        $check_name_stmt = $conn->prepare($check_name_sql);
        $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $check_name_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
        $check_name_stmt->execute();
        
        if ($check_name_stmt->rowCount() > 0) {
            $errors[] = "Department name already exists for another department";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Get current department info for comparison
                $current_dept_sql = "SELECT head_id FROM departments WHERE id = :dept_id";
                $current_dept_stmt = $conn->prepare($current_dept_sql);
                $current_dept_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                $current_dept_stmt->execute();
                $current_dept = $current_dept_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update department
                $update_sql = "UPDATE departments SET 
                              name = :name, 
                              description = :description, 
                              location = :location, 
                              head_id = :head_id, 
                              updated_at = NOW() 
                              WHERE id = :dept_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':location', $location, PDO::PARAM_STR);
                $update_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':dept_id', $edit_dept_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // If head_id has changed, update roles
                if ($current_dept['head_id'] != $head_id) {
                    // If previous head exists, revert role to staff
                    if (!empty($current_dept['head_id'])) {
                        $update_old_head_sql = "UPDATE users SET role = 'staff' WHERE id = :head_id AND role = 'department_head'";
                        $update_old_head_stmt = $conn->prepare($update_old_head_sql);
                        $update_old_head_stmt->bindParam(':head_id', $current_dept['head_id'], PDO::PARAM_INT);
                        $update_old_head_stmt->execute();
                    }
                    
                    // If new head exists, update role to department_head
                    if (!empty($head_id)) {
                        $update_new_head_sql = "UPDATE users SET role = 'department_head' WHERE id = :head_id";
                        $update_new_head_stmt = $conn->prepare($update_new_head_sql);
                        $update_new_head_stmt->bindParam(':head_id', $head_id, PDO::PARAM_INT);
                        $update_new_head_stmt->execute();
                    }
                }
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'update', 'department', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $edit_dept_id, PDO::PARAM_INT);
                $description = "Updated department: $name";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Department updated successfully!";
                $_SESSION['alert_type'] = "success";
                header("Location: ../modules/departments.php");
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error updating department: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Delete department
    if (isset($_POST['delete_department'])) {
        $delete_dept_id = $_POST['delete_dept_id'];
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Check if department has users
            $check_users_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = :dept_id";
            $check_users_stmt = $conn->prepare($check_users_sql);
            $check_users_stmt->bindParam(':dept_id', $delete_dept_id, PDO::PARAM_INT);
            $check_users_stmt->execute();
            $user_count = $check_users_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($user_count > 0) {
                throw new Exception("Cannot delete department with active users. Please reassign users first.");
            }
            
            // Get department info for logging
            $dept_info_sql = "SELECT name FROM departments WHERE id = :dept_id";
            $dept_info_stmt = $conn->prepare($dept_info_sql);
            $dept_info_stmt->bindParam(':dept_id', $delete_dept_id, PDO::PARAM_INT);
            $dept_info_stmt->execute();
            $dept_info = $dept_info_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete department
            $delete_dept_sql = "DELETE FROM departments WHERE id = :dept_id";
            $delete_dept_stmt = $conn->prepare($delete_dept_sql);
            $delete_dept_stmt->bindParam(':dept_id', $delete_dept_id, PDO::PARAM_INT);
            $delete_dept_stmt->execute();
            
            // Log action
            $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                       VALUES (:user_id, 'delete', 'department', :entity_id, :description, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $log_stmt->bindParam(':entity_id', $delete_dept_id, PDO::PARAM_INT);
            $description = "Deleted department: {$dept_info['name']}";
            $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['alert'] = "Department deleted successfully!";
            $_SESSION['alert_type'] = "success";
            header("Location: ../modules/departments.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $_SESSION['alert'] = "Error deleting department: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Get all departments
$departments_sql = "SELECT d.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as head_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id) as staff_count
                   FROM departments d 
                   LEFT JOIN users u ON d.head_id = u.id 
                   ORDER BY d.name";
$departments_stmt = $conn->prepare($departments_sql);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll();

// Get all staff for department head dropdown
$staff_sql = "SELECT id, first_name, last_name, department_id FROM users WHERE role IN ('staff', 'department_head') ORDER BY first_name, last_name";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-building me-2"></i>Department Management</h2>
            <p class="text-muted">Manage college departments and assign department heads</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus-circle me-1"></i> Add New Department
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
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Department Head</th>
                            <th>Staff Count</th>
                            <th>Location</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($department['name']); ?></td>
                                <td>
                                    <?php 
                                        if (!empty($department['head_name'])) {
                                            echo htmlspecialchars($department['head_name']);
                                        } else {
                                            echo '<span class="text-muted">Not Assigned</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $department['staff_count']; ?></span>
                                </td>
                                <td>
                                    <?php 
                                        if (!empty($department['location'])) {
                                            echo htmlspecialchars($department['location']);
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        if (!empty($department['description'])) {
                                            echo htmlspecialchars(substr($department['description'], 0, 50));
                                            if (strlen($department['description']) > 50) {
                                                echo '...';
                                            }
                                        } else {
                                            echo '<span class="text-muted">No description</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-dept-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editDepartmentModal"
                                            data-id="<?php echo $department['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($department['name']); ?>"
                                            data-description="<?php echo htmlspecialchars($department['description'] ?? ''); ?>"
                                            data-location="<?php echo htmlspecialchars($department['location'] ?? ''); ?>"
                                            data-head-id="<?php echo $department['head_id'] ?? ''; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-dept-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteDepartmentModal"
                                            data-id="<?php echo $department['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($department['name']); ?>"
                                            data-staff-count="<?php echo $department['staff_count']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    
                                    <a href="/reports/department_report.php?id=<?php echo $department['id']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-chart-bar"></i>
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/modules/departments.php" method="post">
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
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                    
                    <div class="mb-3">
                        <label for="head_id" class="form-label">Department Head</label>
                        <select class="form-select" id="head_id" name="head_id">
                            <option value="">Select Department Head</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">The selected staff member will be assigned the Department Head role.</small>
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
                <h5 class="modal-title" id="editDepartmentModalLabel"><i class="fas fa-edit me-2"></i>Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/modules/departments.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_dept_id" name="edit_dept_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="edit_location" name="location">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_head_id" class="form-label">Department Head</label>
                        <select class="form-select" id="edit_head_id" name="head_id">
                            <option value="">Select Department Head</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Changing the department head will update user roles accordingly.</small>
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

<!-- Delete Department Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteDepartmentModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/modules/departments.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_dept_id" name="delete_dept_id">
                    <p>Are you sure you want to delete the department <strong id="delete_dept_name"></strong>?</p>
                    <div id="staff_warning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-circle me-2"></i> This department has <span id="staff_count"></span> staff members. You must reassign these staff members before deleting the department.
                    </div>
                    <div id="no_staff_message" class="alert alert-info d-none">
                        <i class="fas fa-info-circle me-2"></i> This department has no staff members and can be safely deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_department" class="btn btn-danger" id="confirm_delete_btn">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Edit Department Modal
        document.querySelectorAll('.edit-dept-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const location = this.getAttribute('data-location');
                const headId = this.getAttribute('data-head-id');
                
                document.getElementById('edit_dept_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_location').value = location;
                document.getElementById('edit_head_id').value = headId || '';
            });
        });
        
        // Delete Department Modal
        document.querySelectorAll('.delete-dept-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const staffCount = parseInt(this.getAttribute('data-staff-count'));
                
                document.getElementById('delete_dept_id').value = id;
                document.getElementById('delete_dept_name').textContent = name;
                document.getElementById('staff_count').textContent = staffCount;
                
                const staffWarning = document.getElementById('staff_warning');
                const noStaffMessage = document.getElementById('no_staff_message');
                const confirmDeleteBtn = document.getElementById('confirm_delete_btn');
                
                if (staffCount > 0) {
                    staffWarning.classList.remove('d-none');
                    noStaffMessage.classList.add('d-none');
                    confirmDeleteBtn.disabled = true;
                } else {
                    staffWarning.classList.add('d-none');
                    noStaffMessage.classList.remove('d-none');
                    confirmDeleteBtn.disabled = false;
                }
            });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>