<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    return;
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
    return;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new leave type
    if (isset($_POST['add_leave_type'])) {
        $name = trim($_POST['name'] ?? '');
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
                $insert_sql = "INSERT INTO leave_types (name, description, max_days, color, requires_attachment, 
                               is_paid, applicable_to, created_at) 
                               VALUES (:name, :description, :max_days, :color, :requires_attachment, 
                               1, :applicable_to, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':max_days', $default_days, PDO::PARAM_INT);
                $insert_stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $insert_stmt->bindParam(':requires_attachment', $requires_attachment, PDO::PARAM_INT);
                $insert_stmt->bindParam(':applicable_to', $applicable_to, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Leave type created successfully!";
                $_SESSION['alert_type'] = "success";
                header("Location: leave_types_fixed.php");
                return;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating leave type: " . htmlspecialchars($e->getMessage());
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Get all leave types with proper JOIN for performance
$leave_types_sql = "SELECT lt.*, 
                   COUNT(DISTINCT la.id) as application_count,
                   COUNT(DISTINCT lb.id) as user_count
                   FROM leave_types lt 
                   LEFT JOIN leave_applications la ON la.leave_type_id = lt.id
                   LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id
                   GROUP BY lt.id
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
            <h2><i class="fas fa-calendar-alt me-2"></i>Leave Types Management (Fixed)</h2>
            <p class="text-muted">Manage leave types and their policies</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                <i class="fas fa-plus-circle me-1"></i> Add New Leave Type
            </button>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['alert_type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['alert']); ?>
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
                            <th>Max Days</th>
                            <th>Description</th>
                            <th>Requirements</th>
                            <th>Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_types as $leave_type): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="color-indicator me-2" style="background-color: <?php echo htmlspecialchars($leave_type['color'] ?? '#3498db'); ?>;"></div>
                                        <?php echo htmlspecialchars($leave_type['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($leave_type['max_days'] ?? 0); ?> days</td>
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
                                        
                                        <?php if (!empty($leave_type['applicable_to']) && $leave_type['applicable_to'] != 'all'): ?>
                                            <li><i class="fas fa-user-tag text-secondary me-1"></i> For <?php echo htmlspecialchars($leave_type['applicable_to']); ?> only</li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-primary mb-1"><?php echo intval($leave_type['user_count']); ?> users</span>
                                        <span class="badge bg-secondary"><?php echo intval($leave_type['application_count']); ?> applications</span>
                                    </div>
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
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
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
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="color" name="color" value="#3498db">
                            </div>
                            
                            <div class="mb-3">
                                <label for="applicable_to" class="form-label">Applicable To</label>
                                <select class="form-select" id="applicable_to" name="applicable_to">
                                    <option value="all">All Staff</option>
                                    <option value="teaching">Teaching Staff Only</option>
                                    <option value="non-teaching">Non-Teaching Staff Only</option>
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
        // Initialize DataTable if available
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $('#leaveTypesTable').DataTable({
                "order": [[0, "asc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>