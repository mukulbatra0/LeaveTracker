<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is an admin
if ($role != 'admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['alert'] = "Invalid form submission. Please try again.";
        $_SESSION['alert_type'] = "danger";
        header('Location: leave_policies.php');
        exit;
    }

    // Add new policy rule
    if (isset($_POST['add_policy'])) {
        $leave_type_id = (int)$_POST['leave_type_id'];
        $staff_type = trim($_POST['staff_type']);
        $gender = trim($_POST['gender']);
        $employment_type = trim($_POST['employment_type']);
        $allocated_days = (float)$_POST['allocated_days'];
        $max_accumulation = !empty($_POST['max_accumulation']) ? (float)$_POST['max_accumulation'] : null;
        $max_at_once = !empty($_POST['max_at_once']) ? (float)$_POST['max_at_once'] : null;
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        if (empty($leave_type_id)) $errors[] = "Leave type is required";
        if ($allocated_days < 0) $errors[] = "Allocated days cannot be negative";

        // Check for duplicate
        $check_sql = "SELECT COUNT(*) FROM leave_policy_rules 
                     WHERE leave_type_id = :lt AND staff_type = :st AND gender = :g AND employment_type = :et";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':lt' => $leave_type_id, ':st' => $staff_type, ':g' => $gender, ':et' => $employment_type]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "A policy rule with this combination already exists";
        }

        if (empty($errors)) {
            try {
                $insert_sql = "INSERT INTO leave_policy_rules (leave_type_id, staff_type, gender, employment_type, allocated_days, max_accumulation, max_at_once, description)
                              VALUES (:lt, :st, :g, :et, :ad, :ma, :mao, :desc)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->execute([
                    ':lt' => $leave_type_id, ':st' => $staff_type, ':g' => $gender, ':et' => $employment_type,
                    ':ad' => $allocated_days, ':ma' => $max_accumulation, ':mao' => $max_at_once, ':desc' => $description
                ]);

                // Audit log
                $action = "Created leave policy rule for leave type ID $leave_type_id ($staff_type/$gender/$employment_type: $allocated_days days)";
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
                $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);

                $_SESSION['alert'] = "Leave policy rule added successfully.";
                $_SESSION['alert_type'] = "success";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: leave_policies.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        if (!empty($errors)) {
            $_SESSION['alert'] = implode('<br>', $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }

    // Edit policy rule
    if (isset($_POST['edit_policy'])) {
        $policy_id = (int)$_POST['policy_id'];
        $allocated_days = (float)$_POST['allocated_days'];
        $max_accumulation = !empty($_POST['max_accumulation']) ? (float)$_POST['max_accumulation'] : null;
        $max_at_once = !empty($_POST['max_at_once']) ? (float)$_POST['max_at_once'] : null;
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $update_sql = "UPDATE leave_policy_rules SET 
                          allocated_days = :ad, max_accumulation = :ma, max_at_once = :mao, 
                          description = :desc, is_active = :ia, updated_at = NOW()
                          WHERE id = :id";
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([':ad' => $allocated_days, ':ma' => $max_accumulation, ':mao' => $max_at_once, ':desc' => $description, ':ia' => $is_active, ':id' => $policy_id]);

            $action = "Updated leave policy rule ID $policy_id";
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
            $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);

            $_SESSION['alert'] = "Policy rule updated successfully.";
            $_SESSION['alert_type'] = "success";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: leave_policies.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['alert'] = "Error: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }

    // Delete policy rule
    if (isset($_POST['delete_policy'])) {
        $policy_id = (int)$_POST['policy_id'];
        try {
            $conn->prepare("DELETE FROM leave_policy_rules WHERE id = :id")->execute([':id' => $policy_id]);

            $action = "Deleted leave policy rule ID $policy_id";
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
            $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);

            $_SESSION['alert'] = "Policy rule deleted successfully.";
            $_SESSION['alert_type'] = "success";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: leave_policies.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['alert'] = "Error: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }

    // Recalculate all leave balances
    if (isset($_POST['recalculate_balances'])) {
        try {
            $conn->beginTransaction();
            
            // Get all active users
            $users_sql = "SELECT id, staff_type, gender, employment_type FROM users WHERE status = 'active'";
            $users_stmt = $conn->prepare($users_sql);
            $users_stmt->execute();
            $all_users = $users_stmt->fetchAll();
            
            $current_year = date('Y');
            $updated_count = 0;
            
            foreach ($all_users as $user) {
                $u_staff = $user['staff_type'] ?? 'teaching';
                $u_gender = $user['gender'] ?? 'male';
                $u_emp = $user['employment_type'] ?? 'full_time';
                
                // Find matching policies
                $policy_sql = "SELECT lpr.leave_type_id, lpr.allocated_days 
                              FROM leave_policy_rules lpr 
                              JOIN leave_types lt ON lpr.leave_type_id = lt.id 
                              WHERE lpr.is_active = 1 AND lt.is_active = 1
                              AND (lpr.staff_type = :staff_type OR lpr.staff_type = 'all')
                              AND (lpr.gender = :gender OR lpr.gender = 'all')
                              AND (lpr.employment_type = :employment_type OR lpr.employment_type = 'all')
                              ORDER BY 
                                CASE WHEN lpr.staff_type != 'all' THEN 0 ELSE 1 END,
                                CASE WHEN lpr.gender != 'all' THEN 0 ELSE 1 END,
                                CASE WHEN lpr.employment_type != 'all' THEN 0 ELSE 1 END";
                $policy_stmt = $conn->prepare($policy_sql);
                $policy_stmt->execute([':staff_type' => $u_staff, ':gender' => $u_gender, ':employment_type' => $u_emp]);
                $policies = $policy_stmt->fetchAll();
                
                $allocated = [];
                foreach ($policies as $p) {
                    if (!isset($allocated[$p['leave_type_id']])) {
                        $allocated[$p['leave_type_id']] = $p['allocated_days'];
                    }
                }
                
                foreach ($allocated as $lt_id => $days) {
                    $balance_sql = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days, created_at) 
                                   VALUES (:uid, :ltid, :year, :days, 0, NOW())
                                   ON DUPLICATE KEY UPDATE total_days = :days2";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->execute([':uid' => $user['id'], ':ltid' => $lt_id, ':year' => $current_year, ':days' => $days, ':days2' => $days]);
                    $updated_count++;
                }
            }
            
            $action = "Recalculated all leave balances based on policy rules ($updated_count balance records updated for " . count($all_users) . " users)";
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
            $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);
            
            $conn->commit();
            
            $_SESSION['alert'] = "Successfully recalculated leave balances for " . count($all_users) . " users ($updated_count balance records updated).";
            $_SESSION['alert_type'] = "success";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: leave_policies.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['alert'] = "Error recalculating balances: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Filter
$filter_staff = isset($_GET['staff_type']) ? $_GET['staff_type'] : '';
$filter_leave_type = isset($_GET['leave_type']) ? (int)$_GET['leave_type'] : 0;

// Get all policy rules
$where = [];
$params = [];
if ($filter_staff) { $where[] = "lpr.staff_type = ?"; $params[] = $filter_staff; }
if ($filter_leave_type) { $where[] = "lpr.leave_type_id = ?"; $params[] = $filter_leave_type; }
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$policies_sql = "SELECT lpr.*, lt.name as leave_type_name, lt.color as leave_color
                FROM leave_policy_rules lpr 
                JOIN leave_types lt ON lpr.leave_type_id = lt.id 
                $where_sql
                ORDER BY lt.name, 
                    FIELD(lpr.staff_type, 'teaching', 'non_teaching', 'all'), 
                    FIELD(lpr.gender, 'male', 'female', 'all'),
                    FIELD(lpr.employment_type, 'full_time', 'part_time', 'all')";
$policies_stmt = $conn->prepare($policies_sql);
$policies_stmt->execute($params);
$policy_rules = $policies_stmt->fetchAll();

// Get leave types for dropdown
$leave_types_sql = "SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->execute();
$leave_types = $leave_types_stmt->fetchAll();

// Stats
$total_rules = count($policy_rules);
$teaching_rules = count(array_filter($policy_rules, fn($r) => $r['staff_type'] === 'teaching'));
$non_teaching_rules = count(array_filter($policy_rules, fn($r) => $r['staff_type'] === 'non_teaching'));

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><i class="fas fa-balance-scale me-2"></i>Leave Policy Rules</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Leave Policies</li>
    </ol>

    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo $total_rules; ?></h3>
                    <small>Total Policy Rules</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo $teaching_rules; ?></h3>
                    <small>Teaching Staff Rules</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo $non_teaching_rules; ?></h3>
                    <small>Non-Teaching Staff Rules</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark shadow-sm">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo count($leave_types); ?></h3>
                    <small>Active Leave Types</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <i class="fas fa-list me-1"></i>
                Leave Allocation Policies
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('This will recalculate leave balances for ALL active users based on current policy rules. Continue?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" name="recalculate_balances" class="btn btn-warning btn-sm">
                        <i class="fas fa-sync-alt me-1"></i> Recalculate All Balances
                    </button>
                </form>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPolicyModal">
                    <i class="fas fa-plus me-1"></i> Add Policy Rule
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="staff_type">
                            <option value="">All Staff Types</option>
                            <option value="teaching" <?php echo $filter_staff === 'teaching' ? 'selected' : ''; ?>>Teaching Staff</option>
                            <option value="non_teaching" <?php echo $filter_staff === 'non_teaching' ? 'selected' : ''; ?>>Non-Teaching Staff</option>
                            <option value="all" <?php echo $filter_staff === 'all' ? 'selected' : ''; ?>>All (Universal)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="leave_type">
                            <option value="0">All Leave Types</option>
                            <?php foreach ($leave_types as $lt): ?>
                                <option value="<?php echo $lt['id']; ?>" <?php echo $filter_leave_type == $lt['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lt['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="leave_policies.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Policy Rules Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Leave Type</th>
                            <th>Staff Type</th>
                            <th>Gender</th>
                            <th>Employment</th>
                            <th>Allocated Days</th>
                            <th>Max Accumulation</th>
                            <th>Max at Once</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($policy_rules)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No policy rules found</td></tr>
                        <?php else: ?>
                            <?php 
                            $current_leave_type = '';
                            foreach ($policy_rules as $rule): 
                                $is_new_group = ($rule['leave_type_name'] !== $current_leave_type);
                                $current_leave_type = $rule['leave_type_name'];
                            ?>
                                <?php if ($is_new_group): ?>
                                <tr class="table-light">
                                    <td colspan="9">
                                        <strong><i class="fas fa-calendar-alt me-1" style="color: <?php echo $rule['leave_color'] ?? '#3498db'; ?>"></i>
                                        <?php echo htmlspecialchars($rule['leave_type_name']); ?></strong>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="ps-4">
                                        <small class="text-muted"><?php echo htmlspecialchars($rule['leave_type_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $staff_badges = ['teaching' => 'bg-success', 'non_teaching' => 'bg-info', 'all' => 'bg-secondary'];
                                        $staff_labels = ['teaching' => 'Teaching', 'non_teaching' => 'Non-Teaching', 'all' => 'All'];
                                        ?>
                                        <span class="badge <?php echo $staff_badges[$rule['staff_type']] ?? 'bg-secondary'; ?>">
                                            <?php echo $staff_labels[$rule['staff_type']] ?? $rule['staff_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $gender_icons = ['male' => 'fas fa-mars text-primary', 'female' => 'fas fa-venus text-danger', 'all' => 'fas fa-users text-secondary'];
                                        $gender_labels = ['male' => 'Male', 'female' => 'Female', 'all' => 'All'];
                                        ?>
                                        <i class="<?php echo $gender_icons[$rule['gender']] ?? 'fas fa-user'; ?> me-1"></i>
                                        <?php echo $gender_labels[$rule['gender']] ?? $rule['gender']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $emp_badges = ['full_time' => 'bg-primary', 'part_time' => 'bg-warning text-dark', 'all' => 'bg-secondary'];
                                        $emp_labels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'all' => 'All'];
                                        ?>
                                        <span class="badge <?php echo $emp_badges[$rule['employment_type']] ?? 'bg-secondary'; ?>">
                                            <?php echo $emp_labels[$rule['employment_type']] ?? $rule['employment_type']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-primary" style="font-size: 1.1em;"><?php echo number_format($rule['allocated_days'], 0); ?></strong>
                                        <small class="text-muted d-block">days</small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rule['max_accumulation']): ?>
                                            <span class="badge bg-outline-warning border border-warning text-dark"><?php echo number_format($rule['max_accumulation'], 0); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rule['max_at_once']): ?>
                                            <span class="badge bg-outline-info border border-info text-dark"><?php echo number_format($rule['max_at_once'], 0); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rule['is_active']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-policy-btn"
                                            data-bs-toggle="modal" data-bs-target="#editPolicyModal"
                                            data-id="<?php echo $rule['id']; ?>"
                                            data-leave-type="<?php echo htmlspecialchars($rule['leave_type_name']); ?>"
                                            data-staff-type="<?php echo $rule['staff_type']; ?>"
                                            data-gender="<?php echo $rule['gender']; ?>"
                                            data-employment-type="<?php echo $rule['employment_type']; ?>"
                                            data-allocated-days="<?php echo $rule['allocated_days']; ?>"
                                            data-max-accumulation="<?php echo $rule['max_accumulation'] ?? ''; ?>"
                                            data-max-at-once="<?php echo $rule['max_at_once'] ?? ''; ?>"
                                            data-description="<?php echo htmlspecialchars($rule['description'] ?? ''); ?>"
                                            data-is-active="<?php echo $rule['is_active']; ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-policy-btn"
                                            data-bs-toggle="modal" data-bs-target="#deletePolicyModal"
                                            data-id="<?php echo $rule['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($rule['leave_type_name'] . ' (' . ($staff_labels[$rule['staff_type']] ?? $rule['staff_type']) . '/' . ($gender_labels[$rule['gender']] ?? $rule['gender']) . ')'); ?>"
                                            title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Policy Modal -->
<div class="modal fade" id="addPolicyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Leave Policy Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Define how many leave days are allocated based on staff type, gender, and employment type. 
                        More specific rules (e.g., teaching/female) take priority over general rules (e.g., all/all).
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type_id" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leave_types as $lt): ?>
                                    <option value="<?php echo $lt['id']; ?>"><?php echo htmlspecialchars($lt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Allocated Days <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="allocated_days" min="0" step="0.5" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Staff Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="staff_type" required>
                                <option value="all">All Staff</option>
                                <option value="teaching">Teaching Staff</option>
                                <option value="non_teaching">Non-Teaching Staff</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" name="gender" required>
                                <option value="all">All Genders</option>
                                <option value="male">Male Only</option>
                                <option value="female">Female Only</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Max Accumulation Limit</label>
                            <input type="number" class="form-control" name="max_accumulation" min="0" step="0.5" placeholder="Leave blank if no limit">
                            <div class="form-text">Maximum days that can be accumulated (e.g., 180 for Earned Leave)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Days at Once</label>
                            <input type="number" class="form-control" name="max_at_once" min="0" step="0.5" placeholder="Leave blank if no limit">
                            <div class="form-text">Maximum days sanctioned at one time (e.g., 120 for Earned Leave)</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Policy rule description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_policy" class="btn btn-primary">Add Policy Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Policy Modal -->
<div class="modal fade" id="editPolicyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Policy Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="policy_id" id="edit_policy_id">
                <div class="modal-body">
                    <div class="alert alert-secondary mb-3">
                        <strong>Leave Type:</strong> <span id="edit_leave_type_display"></span><br>
                        <strong>For:</strong> <span id="edit_scope_display"></span>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Allocated Days <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_allocated_days" name="allocated_days" min="0" step="0.5" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Accumulation</label>
                            <input type="number" class="form-control" id="edit_max_accumulation" name="max_accumulation" min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max at Once</label>
                            <input type="number" class="form-control" id="edit_max_at_once" name="max_at_once" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_policy" class="btn btn-primary">Update Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Policy Modal -->
<div class="modal fade" id="deletePolicyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Policy Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="policy_id" id="delete_policy_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the policy rule for <strong id="delete_policy_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>This will not affect existing leave balances. You may want to recalculate balances after deleting.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_policy" class="btn btn-danger">Delete Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Policy Modal
    document.querySelectorAll('.edit-policy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffLabels = {'teaching': 'Teaching', 'non_teaching': 'Non-Teaching', 'all': 'All'};
            const genderLabels = {'male': 'Male', 'female': 'Female', 'all': 'All'};
            const empLabels = {'full_time': 'Full Time', 'part_time': 'Part Time', 'all': 'All'};
            
            document.getElementById('edit_policy_id').value = this.dataset.id;
            document.getElementById('edit_leave_type_display').textContent = this.dataset.leaveType;
            document.getElementById('edit_scope_display').textContent = 
                (staffLabels[this.dataset.staffType] || this.dataset.staffType) + ' / ' + 
                (genderLabels[this.dataset.gender] || this.dataset.gender) + ' / ' + 
                (empLabels[this.dataset.employmentType] || this.dataset.employmentType);
            document.getElementById('edit_allocated_days').value = this.dataset.allocatedDays;
            document.getElementById('edit_max_accumulation').value = this.dataset.maxAccumulation || '';
            document.getElementById('edit_max_at_once').value = this.dataset.maxAtOnce || '';
            document.getElementById('edit_description').value = this.dataset.description || '';
            document.getElementById('edit_is_active').checked = this.dataset.isActive === '1';
        });
    });

    // Delete Policy Modal
    document.querySelectorAll('.delete-policy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_policy_id').value = this.dataset.id;
            document.getElementById('delete_policy_name').textContent = this.dataset.name;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>


