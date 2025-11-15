<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
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
    // Add new leave type
    if (isset($_POST['add_leave_type'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $default_days = isset($_POST['default_days']) ? intval($_POST['default_days']) : 0;
        $color = trim($_POST['color'] ?? '#3498db');
        $requires_attachment = isset($_POST['requires_attachment']) ? 1 : 0;
        $is_academic = isset($_POST['is_academic']) ? 1 : 0;
        $max_days_per_request = !empty($_POST['max_days_per_request']) ? intval($_POST['max_days_per_request']) : null;
        $min_notice_days = !empty($_POST['min_notice_days']) ? intval($_POST['min_notice_days']) : 0;
        $applicable_to = isset($_POST['applicable_to']) ? $_POST['applicable_to'] : 'all';
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Leave type name is required";
        if ($default_days < 0) $errors[] = "Default days cannot be negative";
        if ($max_days_per_request !== null && $max_days_per_request <= 0) $errors[] = "Maximum days per request must be greater than zero";
        if ($min_notice_days < 0) $errors[] = "Minimum notice days cannot be negative";
        
        // Check if leave type name already exists
        $check_name_sql = "SELECT id FROM leave_types WHERE name = :name";
        $check_name_stmt = $conn->prepare($check_name_sql);
        $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $check_name_stmt->execute();
        
        if ($check_name_stmt->rowCount() > 0) {
            $errors[] = "Leave type name already exists";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert leave type
                $insert_sql = "INSERT INTO leave_types (name, description, default_days, color, requires_attachment, 
                               is_academic, max_days_per_request, min_notice_days, applicable_to, created_at) 
                               VALUES (:name, :description, :default_days, :color, :requires_attachment, 
                               :is_academic, :max_days_per_request, :min_notice_days, :applicable_to, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':default_days', $default_days, PDO::PARAM_INT);
                $insert_stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $insert_stmt->bindParam(':requires_attachment', $requires_attachment, PDO::PARAM_INT);
                $insert_stmt->bindParam(':is_academic', $is_academic, PDO::PARAM_INT);
                $insert_stmt->bindParam(':max_days_per_request', $max_days_per_request, PDO::PARAM_INT);
                $insert_stmt->bindParam(':min_notice_days', $min_notice_days, PDO::PARAM_INT);
                $insert_stmt->bindParam(':applicable_to', $applicable_to, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_leave_type_id = $conn->lastInsertId();
                
                // Update leave balances for all users
                $update_balances_sql = "INSERT INTO leave_balances (user_id, leave_type_id, total_days, used_days, created_at) 
                                      SELECT id, :leave_type_id, :default_days, 0, NOW() FROM users";
                $update_balances_stmt = $conn->prepare($update_balances_sql);
                $update_balances_stmt->bindParam(':leave_type_id', $new_leave_type_id, PDO::PARAM_INT);
                $update_balances_stmt->bindParam(':default_days', $default_days, PDO::PARAM_INT);
                $update_balances_stmt->execute();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'leave_type', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_leave_type_id, PDO::PARAM_INT);
                $description = "Created new leave type: $name with $default_days default days";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Leave type created successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: leave_types.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating leave type: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Edit leave type
    if (isset($_POST['edit_leave_type'])) {
        $edit_leave_type_id = $_POST['edit_leave_type_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $default_days = isset($_POST['default_days']) ? intval($_POST['default_days']) : 0;
        $color = trim($_POST['color'] ?? '#3498db');
        $requires_attachment = isset($_POST['requires_attachment']) ? 1 : 0;
        $is_academic = isset($_POST['is_academic']) ? 1 : 0;
        $max_days_per_request = !empty($_POST['max_days_per_request']) ? intval($_POST['max_days_per_request']) : null;
        $min_notice_days = !empty($_POST['min_notice_days']) ? intval($_POST['min_notice_days']) : 0;
        $applicable_to = isset($_POST['applicable_to']) ? $_POST['applicable_to'] : 'all';
        $update_balances = isset($_POST['update_balances']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Leave type name is required";
        if ($default_days < 0) $errors[] = "Default days cannot be negative";
        if ($max_days_per_request !== null && $max_days_per_request <= 0) $errors[] = "Maximum days per request must be greater than zero";
        if ($min_notice_days < 0) $errors[] = "Minimum notice days cannot be negative";
        
        // Check if leave type name already exists for other leave types
        $check_name_sql = "SELECT id FROM leave_types WHERE name = :name AND id != :leave_type_id";
        $check_name_stmt = $conn->prepare($check_name_sql);
        $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $check_name_stmt->bindParam(':leave_type_id', $edit_leave_type_id, PDO::PARAM_INT);
        $check_name_stmt->execute();
        
        if ($check_name_stmt->rowCount() > 0) {
            $errors[] = "Leave type name already exists for another leave type";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Get current leave type info for comparison
                $current_leave_type_sql = "SELECT default_days FROM leave_types WHERE id = :leave_type_id";
                $current_leave_type_stmt = $conn->prepare($current_leave_type_sql);
                $current_leave_type_stmt->bindParam(':leave_type_id', $edit_leave_type_id, PDO::PARAM_INT);
                $current_leave_type_stmt->execute();
                $current_leave_type = $current_leave_type_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update leave type
                $update_sql = "UPDATE leave_types SET 
                              name = :name, 
                              description = :description, 
                              default_days = :default_days, 
                              color = :color, 
                              requires_attachment = :requires_attachment, 
                              is_academic = :is_academic, 
                              max_days_per_request = :max_days_per_request, 
                              min_notice_days = :min_notice_days, 
                              applicable_to = :applicable_to, 
                              updated_at = NOW() 
                              WHERE id = :leave_type_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':default_days', $default_days, PDO::PARAM_INT);
                $update_stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $update_stmt->bindParam(':requires_attachment', $requires_attachment, PDO::PARAM_INT);
                $update_stmt->bindParam(':is_academic', $is_academic, PDO::PARAM_INT);
                $update_stmt->bindParam(':max_days_per_request', $max_days_per_request, PDO::PARAM_INT);
                $update_stmt->bindParam(':min_notice_days', $min_notice_days, PDO::PARAM_INT);
                $update_stmt->bindParam(':applicable_to', $applicable_to, PDO::PARAM_STR);
                $update_stmt->bindParam(':leave_type_id', $edit_leave_type_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // If default days changed and update_balances is checked, update all leave balances
                if ($update_balances && $current_leave_type['default_days'] != $default_days) {
                    $days_difference = $default_days - $current_leave_type['default_days'];
                    
                    $update_balances_sql = "UPDATE leave_balances 
                                          SET total_days = total_days + :days_difference, 
                                          updated_at = NOW() 
                                          WHERE leave_type_id = :leave_type_id";
                    $update_balances_stmt = $conn->prepare($update_balances_sql);
                    $update_balances_stmt->bindParam(':days_difference', $days_difference, PDO::PARAM_INT);
                    $update_balances_stmt->bindParam(':leave_type_id', $edit_leave_type_id, PDO::PARAM_INT);
                    $update_balances_stmt->execute();
                }
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'update', 'leave_type', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $edit_leave_type_id, PDO::PARAM_INT);
                $description = "Updated leave type: $name";
                if ($update_balances && $current_leave_type['default_days'] != $default_days) {
                    $description .= " and updated all balances from {$current_leave_type['default_days']} to $default_days days";
                }
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Leave type updated successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: leave_types.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error updating leave type: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Delete leave type
    if (isset($_POST['delete_leave_type'])) {
        $delete_leave_type_id = $_POST['delete_leave_type_id'];
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Check if leave type has active applications
            $check_applications_sql = "SELECT COUNT(*) as count FROM leave_applications WHERE leave_type_id = :leave_type_id";
            $check_applications_stmt = $conn->prepare($check_applications_sql);
            $check_applications_stmt->bindParam(':leave_type_id', $delete_leave_type_id, PDO::PARAM_INT);
            $check_applications_stmt->execute();
            $application_count = $check_applications_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($application_count > 0) {
                throw new Exception("Cannot delete leave type with active applications. Please archive it instead.");
            }
            
            // Get leave type info for logging
            $leave_type_info_sql = "SELECT name FROM leave_types WHERE id = :leave_type_id";
            $leave_type_info_stmt = $conn->prepare($leave_type_info_sql);
            $leave_type_info_stmt->bindParam(':leave_type_id', $delete_leave_type_id, PDO::PARAM_INT);
            $leave_type_info_stmt->execute();
            $leave_type_info = $leave_type_info_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete leave balances associated with this leave type
            $delete_balances_sql = "DELETE FROM leave_balances WHERE leave_type_id = :leave_type_id";
            $delete_balances_stmt = $conn->prepare($delete_balances_sql);
            $delete_balances_stmt->bindParam(':leave_type_id', $delete_leave_type_id, PDO::PARAM_INT);
            $delete_balances_stmt->execute();
            
            // Delete leave type
            $delete_leave_type_sql = "DELETE FROM leave_types WHERE id = :leave_type_id";
            $delete_leave_type_stmt = $conn->prepare($delete_leave_type_sql);
            $delete_leave_type_stmt->bindParam(':leave_type_id', $delete_leave_type_id, PDO::PARAM_INT);
            $delete_leave_type_stmt->execute();
            
            // Log action
            $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                       VALUES (:user_id, 'delete', 'leave_type', :entity_id, :description, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $log_stmt->bindParam(':entity_id', $delete_leave_type_id, PDO::PARAM_INT);
            $description = "Deleted leave type: {$leave_type_info['name']}";
            $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['alert'] = "Leave type deleted successfully!";
            $_SESSION['alert_type'] = "success";
            header('Location: leave_types.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $_SESSION['alert'] = "Error deleting leave type: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Get all leave types
$leave_types_sql = "SELECT lt.*, 
                   (SELECT COUNT(*) FROM leave_applications WHERE leave_type_id = lt.id) as application_count,
                   (SELECT COUNT(*) FROM leave_balances WHERE leave_type_id = lt.id) as user_count
                   FROM leave_types lt 
                   ORDER BY lt.name";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->execute();
$leave_types = $leave_types_stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calendar-alt me-2"></i>Leave Types Management</h2>
            <p class="text-muted">Manage leave types and their policies</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                <i class="fas fa-plus-circle me-1"></i> Add New Leave Type
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
                <table class="table table-hover" id="leaveTypesTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Default Days</th>
                            <th>Description</th>
                            <th>Requirements</th>
                            <th>Usage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_types as $leave_type): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="color-indicator me-2" style="background-color: <?php echo htmlspecialchars($leave_type['color'] ?? '#3498db'); ?>;"></div>
                                        <?php echo htmlspecialchars($leave_type['name']); ?>
                                        <?php if ($leave_type['is_academic'] ?? false): ?>
                                            <span class="badge bg-info ms-2">Academic</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo isset($leave_type['default_days']) ? $leave_type['default_days'] : 0; ?> days</td>
                                <td>
                                    <?php 
                                        if (!empty($leave_type['description'])) {
                                            echo htmlspecialchars(substr($leave_type['description'], 0, 50));
                                            if (strlen($leave_type['description']) > 50) {
                                                echo '...';
                                            }
                                        } else {
                                            echo '<span class="text-muted">No description</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <ul class="list-unstyled mb-0">
                                        <?php if ($leave_type['requires_attachment']): ?>
                                            <li><i class="fas fa-paperclip text-secondary me-1"></i> Attachment required</li>
                                        <?php endif; ?>
                                        
                                        <?php if (($leave_type['min_notice_days'] ?? 0) > 0): ?>
                                            <li><i class="fas fa-clock text-secondary me-1"></i> <?php echo $leave_type['min_notice_days'] ?? 0; ?> days notice</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($leave_type['max_days_per_request'] ?? null): ?>
                                            <li><i class="fas fa-calendar-day text-secondary me-1"></i> Max <?php echo $leave_type['max_days_per_request'] ?? ''; ?> days/request</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($leave_type['applicable_to'] != 'all'): ?>
                                            <li><i class="fas fa-user-tag text-secondary me-1"></i> For <?php echo htmlspecialchars($leave_type['applicable_to']); ?> only</li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-primary mb-1"><?php echo $leave_type['user_count']; ?> users</span>
                                        <span class="badge bg-secondary"><?php echo $leave_type['application_count']; ?> applications</span>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-leave-type-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editLeaveTypeModal"
                                            data-id="<?php echo $leave_type['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($leave_type['name']); ?>"
                                            data-description="<?php echo htmlspecialchars($leave_type['description'] ?? ''); ?>"
                                            data-default-days="<?php echo $leave_type['default_days'] ?? 0; ?>"
                                            data-color="<?php echo htmlspecialchars($leave_type['color'] ?? '#3498db'); ?>"
                                            data-requires-attachment="<?php echo $leave_type['requires_attachment'] ?? 0; ?>"
                                            data-is-academic="<?php echo $leave_type['is_academic'] ?? 0; ?>"
                                            data-max-days="<?php echo $leave_type['max_days_per_request'] ?? ''; ?>"
                                            data-min-notice="<?php echo $leave_type['min_notice_days'] ?? 0; ?>"
                                            data-applicable-to="<?php echo htmlspecialchars($leave_type['applicable_to']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-leave-type-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteLeaveTypeModal"
                                            data-id="<?php echo $leave_type['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($leave_type['name']); ?>"
                                            data-application-count="<?php echo $leave_type['application_count']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    
                                    <a href="./reports/leave_type_report.php?id=<?php echo $leave_type['id']; ?>" class="btn btn-sm btn-outline-info">
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

<!-- Add Leave Type Modal -->
<div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveTypeModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="leave_types.php" method="post">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Leave Type Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="default_days" class="form-label">Default Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="default_days" name="default_days" min="0" value="0" required>
                                <small class="form-text text-muted">Number of days allocated to each employee by default</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="color" name="color" value="#3498db">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_days_per_request" class="form-label">Maximum Days Per Request</label>
                                <input type="number" class="form-control" id="max_days_per_request" name="max_days_per_request" min="1">
                                <small class="form-text text-muted">Leave blank for no limit</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="min_notice_days" class="form-label">Minimum Notice Days</label>
                                <input type="number" class="form-control" id="min_notice_days" name="min_notice_days" min="0" value="0">
                                <small class="form-text text-muted">Minimum days in advance to apply for this leave</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="applicable_to" class="form-label">Applicable To</label>
                                <select class="form-select" id="applicable_to" name="applicable_to">
                                    <option value="all">All Staff</option>
                                    <option value="teaching">Teaching Staff Only</option>
                                    <option value="non-teaching">Non-Teaching Staff Only</option>
                                    <option value="contract">Contract Staff Only</option>
                                    <option value="permanent">Permanent Staff Only</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="requires_attachment" name="requires_attachment">
                                    <label class="form-check-label" for="requires_attachment">
                                        Requires Supporting Document
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_academic" name="is_academic">
                                    <label class="form-check-label" for="is_academic">
                                        Academic Leave Type
                                    </label>
                                    <small class="form-text text-muted d-block">For conference, research, or other academic purposes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_leave_type" class="btn btn-primary">Add Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Leave Type Modal -->
<div class="modal fade" id="editLeaveTypeModal" tabindex="-1" aria-labelledby="editLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeaveTypeModalLabel"><i class="fas fa-edit me-2"></i>Edit Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="leave_types.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_leave_type_id" name="edit_leave_type_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Leave Type Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_default_days" class="form-label">Default Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_default_days" name="default_days" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="edit_color" name="color">
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="update_balances" name="update_balances">
                                    <label class="form-check-label" for="update_balances">
                                        Update existing leave balances
                                    </label>
                                    <small class="form-text text-muted d-block">If checked, all staff leave balances will be updated to reflect the new default days</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_max_days_per_request" class="form-label">Maximum Days Per Request</label>
                                <input type="number" class="form-control" id="edit_max_days_per_request" name="max_days_per_request" min="1">
                                <small class="form-text text-muted">Leave blank for no limit</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_min_notice_days" class="form-label">Minimum Notice Days</label>
                                <input type="number" class="form-control" id="edit_min_notice_days" name="min_notice_days" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_applicable_to" class="form-label">Applicable To</label>
                                <select class="form-select" id="edit_applicable_to" name="applicable_to">
                                    <option value="all">All Staff</option>
                                    <option value="teaching">Teaching Staff Only</option>
                                    <option value="non-teaching">Non-Teaching Staff Only</option>
                                    <option value="contract">Contract Staff Only</option>
                                    <option value="permanent">Permanent Staff Only</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_requires_attachment" name="requires_attachment">
                                    <label class="form-check-label" for="edit_requires_attachment">
                                        Requires Supporting Document
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_academic" name="is_academic">
                                    <label class="form-check-label" for="edit_is_academic">
                                        Academic Leave Type
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_leave_type" class="btn btn-primary">Update Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Leave Type Modal -->
<div class="modal fade" id="deleteLeaveTypeModal" tabindex="-1" aria-labelledby="deleteLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteLeaveTypeModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="leave_types.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_leave_type_id" name="delete_leave_type_id">
                    <p>Are you sure you want to delete the leave type <strong id="delete_leave_type_name"></strong>?</p>
                    <div id="application_warning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-circle me-2"></i> This leave type has <span id="application_count"></span> applications. You cannot delete it while it has active applications.
                    </div>
                    <div id="no_application_message" class="alert alert-info d-none">
                        <i class="fas fa-info-circle me-2"></i> This leave type has no applications and can be safely deleted.
                    </div>
                    <p class="text-danger"><strong>Warning:</strong> This will also delete all leave balances associated with this leave type.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_leave_type" class="btn btn-danger" id="confirm_delete_btn">Delete Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .color-indicator {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#leaveTypesTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Edit Leave Type Modal
        document.querySelectorAll('.edit-leave-type-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const defaultDays = this.getAttribute('data-default-days');
                const color = this.getAttribute('data-color');
                const requiresAttachment = this.getAttribute('data-requires-attachment') === '1';
                const isAcademic = this.getAttribute('data-is-academic') === '1';
                const maxDays = this.getAttribute('data-max-days');
                const minNotice = this.getAttribute('data-min-notice');
                const applicableTo = this.getAttribute('data-applicable-to');
                
                document.getElementById('edit_leave_type_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_default_days').value = defaultDays;
                document.getElementById('edit_color').value = color;
                document.getElementById('edit_requires_attachment').checked = requiresAttachment;
                document.getElementById('edit_is_academic').checked = isAcademic;
                document.getElementById('edit_max_days_per_request').value = maxDays;
                document.getElementById('edit_min_notice_days').value = minNotice;
                document.getElementById('edit_applicable_to').value = applicableTo;
                document.getElementById('update_balances').checked = false;
            });
        });
        
        // Delete Leave Type Modal
        document.querySelectorAll('.delete-leave-type-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const applicationCount = parseInt(this.getAttribute('data-application-count'));
                
                document.getElementById('delete_leave_type_id').value = id;
                document.getElementById('delete_leave_type_name').textContent = name;
                document.getElementById('application_count').textContent = applicationCount;
                
                const applicationWarning = document.getElementById('application_warning');
                const noApplicationMessage = document.getElementById('no_application_message');
                const confirmDeleteBtn = document.getElementById('confirm_delete_btn');
                
                if (applicationCount > 0) {
                    applicationWarning.classList.remove('d-none');
                    noApplicationMessage.classList.add('d-none');
                    confirmDeleteBtn.disabled = true;
                } else {
                    applicationWarning.classList.add('d-none');
                    noApplicationMessage.classList.remove('d-none');
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