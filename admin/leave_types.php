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

// Check if user has permission to access leave types
$allowed_roles = ['admin', 'hr_admin', 'director'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: /index.php');
    exit;
}

// Directors have read-only access
$is_read_only = ($role == 'director');

// Initialize errors array
$errors = [];

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submissions (only for admin and hr_admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_read_only) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        // Add new leave type
        if (isset($_POST['add_leave_type'])) {
        // Quick fix for undefined array keys
        $_POST['min_notice_days'] = $_POST['min_notice_days'] ?? 0;
        $_POST['max_days_per_request'] = $_POST['max_days_per_request'] ?? 0;

        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $default_days = (int)$_POST['default_days'];
        $color = '#3498db'; // Default color since it's not shown in frontend
        $requires_document = isset($_POST['requires_document']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $accrual_method = trim($_POST['accrual_method']);
        $carry_forward_days = (int)$_POST['carry_forward_days'];
        $max_consecutive_days = (int)$_POST['max_consecutive_days'];

        // Validate input
        $errors = [];

        if (empty($name)) {
            $errors[] = "Leave type name is required";
        } else {
            // Check if leave type name already exists
            $check_name_sql = "SELECT COUNT(*) FROM leave_types WHERE name = :name";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $check_name_stmt->execute();

            if ($check_name_stmt->fetchColumn() > 0) {
                $errors[] = "Leave type name already exists";
            }
        }

        if ($default_days < 0) {
            $errors[] = "Default days cannot be negative";
        }

        if ($carry_forward_days < 0) {
            $errors[] = "Carry forward days cannot be negative";
        }

        if ($max_consecutive_days < 0) {
            $errors[] = "Maximum consecutive days cannot be negative";
        }

        // If no errors, insert new leave type
        if (empty($errors)) {
            try {
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
                $action = "Created new leave type: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();

                $conn->commit();

                $_SESSION['alert'] = "Leave type added successfully.";
                $_SESSION['alert_type'] = "success";
                // Regenerate CSRF token to prevent resubmission
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: leave_types.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }

    // Edit leave type
    if (isset($_POST['edit_leave_type'])) {
        // Quick fix for undefined array keys
        $_POST['min_notice_days'] = $_POST['min_notice_days'] ?? 0;
        $_POST['max_days_per_request'] = $_POST['max_days_per_request'] ?? 0;

        $edit_type_id = $_POST['edit_type_id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $default_days = (int)$_POST['default_days'];
        $color = '#3498db'; // Default color since it's not shown in frontend
        $requires_document = isset($_POST['requires_document']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $accrual_method = trim($_POST['accrual_method']);
        $carry_forward_days = (int)$_POST['carry_forward_days'];
        $max_consecutive_days = (int)$_POST['max_consecutive_days'];

        // Validate input
        $errors = [];

        if (empty($name)) {
            $errors[] = "Leave type name is required";
        } else {
            // Check if leave type name already exists for other leave types
            $check_name_sql = "SELECT COUNT(*) FROM leave_types WHERE name = :name AND id != :type_id";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $check_name_stmt->bindParam(':type_id', $edit_type_id, PDO::PARAM_INT);
            $check_name_stmt->execute();

            if ($check_name_stmt->fetchColumn() > 0) {
                $errors[] = "Leave type name already exists";
            }
        }

        if ($default_days < 0) {
            $errors[] = "Default days cannot be negative";
        }

        if ($carry_forward_days < 0) {
            $errors[] = "Carry forward days cannot be negative";
        }

        if ($max_consecutive_days < 0) {
            $errors[] = "Maximum consecutive days cannot be negative";
        }

        // If no errors, update leave type
        if (empty($errors)) {
            try {
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
                $action = "Updated leave type ID $edit_type_id: $name";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();

                $conn->commit();

                $_SESSION['alert'] = "Leave type updated successfully.";
                $_SESSION['alert_type'] = "success";
                // Regenerate CSRF token to prevent resubmission
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: leave_types.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    } // End of CSRF token validation
}

// Get all leave types with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause and parameters
$where_conditions = [];
$bind_params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $bind_params[] = "%$search%";
    $bind_params[] = "%$search%";
}

if ($status !== '') {
    $where_conditions[] = "is_active = ?";
    $bind_params[] = (int)$status;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Get leave types with pagination
$leave_types_sql = "SELECT * FROM leave_types $where_sql ORDER BY name ASC LIMIT $limit OFFSET $offset";
$leave_types_stmt = $conn->prepare($leave_types_sql);

if (!empty($bind_params)) {
    $leave_types_stmt->execute($bind_params);
} else {
    $leave_types_stmt->execute();
}

$leave_types = $leave_types_stmt->fetchAll();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM leave_types $where_sql";
$count_stmt = $conn->prepare($count_sql);

if (!empty($bind_params)) {
    $count_stmt->execute($bind_params);
} else {
    $count_stmt->execute();
}

$total_leave_types = $count_stmt->fetchColumn();
$total_pages = ceil($total_leave_types / $limit);

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Leave Types Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Leave Types</li>
    </ol>

    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>

    <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($is_read_only): ?>
    <!-- Director Summary Card -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($leave_types); ?></h4>
                    <p class="mb-0">Total Leave Types</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?php echo count(array_filter($leave_types, function($type) { return $type['is_active']; })); ?></h4>
                    <p class="mb-0">Active Types</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo count(array_filter($leave_types, function($type) { return isset($type['requires_attachment']) && $type['requires_attachment']; })); ?></h4>
                    <p class="mb-0">Require Documents</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h4><?php echo array_sum(array_column($leave_types, 'default_days')); ?></h4>
                    <p class="mb-0">Total Default Days</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-calendar-alt me-1"></i>
                Leave Types <?php if($is_read_only): ?><span class="badge bg-info ms-2">View Only</span><?php endif; ?>
            </div>
            <?php if (!$is_read_only): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                <i class="fas fa-plus"></i> Add Leave Type
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">

            
            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search leave type name or description" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All Status</option>
                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="leave_types.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Leave Types Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo $is_read_only ? 'Leave Type & Description' : 'Name'; ?></th>
                            <?php if (!$is_read_only): ?><th>Description</th><?php endif; ?>
                            <th>Default Days</th>
                            <th>Requires Document</th>
                            <th>Accrual Method</th>
                            <th>Carry Forward</th>
                            <th>Max Consecutive</th>
                            <th>Status</th>
                            <?php if (!$is_read_only): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leave_types)): ?>
                            <tr>
                                <td colspan="<?php echo $is_read_only ? '8' : '10'; ?>" class="text-center">No leave types found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leave_types as $type): ?>
                                <tr class="<?php echo $is_read_only ? 'director-view-row' : ''; ?>">
                                    <td><span class="badge bg-light text-dark"><?php echo $type['id']; ?></span></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                        <?php if ($is_read_only && !empty($type['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($type['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$is_read_only): ?>
                                    <td><?php echo htmlspecialchars($type['description'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $type['default_days']; ?></span>
                                        <?php if ($is_read_only): ?><small class="d-block text-muted">days/year</small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($type['requires_attachment']) && $type['requires_attachment']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Required</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($type['accrual_method']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?php echo $type['carry_forward_days']; ?></span>
                                        <?php if ($is_read_only): ?><small class="d-block text-muted">days max</small><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo $type['max_consecutive_days']; ?></span>
                                        <?php if ($is_read_only): ?><small class="d-block text-muted">days max</small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($type['is_active']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$is_read_only): ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-leave-type"
                                            data-bs-toggle="modal" data-bs-target="#editLeaveTypeModal"
                                            data-id="<?php echo $type['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                            data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                            data-default-days="<?php echo $type['default_days']; ?>"
                                            data-requires-document="<?php echo isset($type['requires_attachment']) ? $type['requires_attachment'] : 0; ?>"
                                            data-is-active="<?php echo $type['is_active']; ?>"
                                            data-accrual-method="<?php echo htmlspecialchars($type['accrual_method']); ?>"
                                            data-carry-forward-days="<?php echo $type['carry_forward_days']; ?>"
                                            data-max-consecutive-days="<?php echo $type['max_consecutive_days']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                    <?php endif; ?>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_read_only): ?>
<!-- Add Leave Type Modal -->
<div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveTypeModalLabel">Add New Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_leave_type" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Leave Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="default_days" class="form-label">Default Days <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="default_days" name="default_days" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="accrual_method" class="form-label">Accrual Method</label>
                            <select class="form-select" id="accrual_method" name="accrual_method">
                                <option value="annual">Annual (Beginning of Year)</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="none">None (Manual Allocation)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="carry_forward_days" class="form-label">Carry Forward Days</label>
                            <input type="number" class="form-control" id="carry_forward_days" name="carry_forward_days" min="0" value="0">
                            <div class="form-text">Maximum days that can be carried forward to next year</div>
                        </div>
                        <div class="col-md-6">
                            <label for="max_consecutive_days" class="form-label">Max Consecutive Days</label>
                            <input type="number" class="form-control" id="max_consecutive_days" name="max_consecutive_days" min="0" value="0">
                            <div class="form-text">0 means no limit</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_document" name="requires_document">
                                <label class="form-check-label" for="requires_document">
                                    Requires Supporting Document
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
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
                <h5 class="modal-title" id="editLeaveTypeModalLabel">Edit Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_leave_type" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="edit_type_id" id="edit_type_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Leave Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_default_days" class="form-label">Default Days <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_default_days" name="default_days" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_accrual_method" class="form-label">Accrual Method</label>
                            <select class="form-select" id="edit_accrual_method" name="accrual_method">
                                <option value="annual">Annual (Beginning of Year)</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="none">None (Manual Allocation)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_carry_forward_days" class="form-label">Carry Forward Days</label>
                            <input type="number" class="form-control" id="edit_carry_forward_days" name="carry_forward_days" min="0">
                            <div class="form-text">Maximum days that can be carried forward to next year</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_max_consecutive_days" class="form-label">Max Consecutive Days</label>
                            <input type="number" class="form-control" id="edit_max_consecutive_days" name="max_consecutive_days" min="0">
                            <div class="form-text">0 means no limit</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_requires_document" name="requires_document">
                                <label class="form-check-label" for="edit_requires_document">
                                    Requires Supporting Document
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active
                                </label>
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

<script>
    // Edit Leave Type Modal
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-leave-type');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const defaultDays = this.getAttribute('data-default-days');
                const requiresDocument = this.getAttribute('data-requires-document') === '1';
                const isActive = this.getAttribute('data-is-active') === '1';
                const accrualMethod = this.getAttribute('data-accrual-method');
                const carryForwardDays = this.getAttribute('data-carry-forward-days');
                const maxConsecutiveDays = this.getAttribute('data-max-consecutive-days');

                document.getElementById('edit_type_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_default_days').value = defaultDays;
                document.getElementById('edit_requires_document').checked = requiresDocument;
                document.getElementById('edit_is_active').checked = isActive;
                document.getElementById('edit_accrual_method').value = accrualMethod;
                document.getElementById('edit_carry_forward_days').value = carryForwardDays;
                document.getElementById('edit_max_consecutive_days').value = maxConsecutiveDays;
            });
        });
    });
</script>
<?php endif; // End of !$is_read_only conditional for modals ?>

<?php include '../includes/footer.php'; ?>