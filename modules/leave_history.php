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

// Initialize variables
$errors = [];
$leave_applications = [];
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_leave_type = isset($_GET['leave_type']) ? $_GET['leave_type'] : '';

// Get leave types for filter
try {
    $stmt = $conn->prepare("SELECT * FROM leave_types ORDER BY name");
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving leave types: " . $e->getMessage();
    $leave_types = [];
}

// Get years for filter (from the earliest leave application to current year)
try {
    $stmt = $conn->prepare("SELECT MIN(YEAR(applied_at)) as min_year FROM leave_applications");
    $stmt->execute();
    $min_year = $stmt->fetchColumn();
    
    if (!$min_year) {
        $min_year = date('Y');
    }
    
    $years = range(date('Y'), $min_year);
} catch (PDOException $e) {
    $errors[] = "Error retrieving years: " . $e->getMessage();
    $years = [date('Y')];
}

// Get pagination settings with error handling
try {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'pagination_limit'");
    $stmt->execute();
    $pagination_limit = $stmt->fetchColumn() ?: 10;
} catch (PDOException $e) {
    // Use default value if system_settings table doesn't exist
    $pagination_limit = 10;
}

// Calculate pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $pagination_limit;

// Build query based on role and filters
try {
    $params = [];
    $where_clauses = [];
    
    // Base query
    $base_query = "FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_types lt ON la.leave_type_id = lt.id 
                  LEFT JOIN departments d ON u.department_id = d.id";
    
    // Role-based filtering
    if ($role == 'staff') {
        // Staff can only see their own applications
        $where_clauses[] = "la.user_id = ?";
        $params[] = $user_id;
    } elseif ($role == 'department_head') {
        // Department heads can see applications from their department
        $where_clauses[] = "u.department_id = (SELECT department_id FROM users WHERE id = ?)";
        $params[] = $user_id;
    } elseif ($role == 'dean') {
        // Deans can see applications from their faculty
        $where_clauses[] = "d.faculty_id = (SELECT faculty_id FROM departments WHERE id = (SELECT department_id FROM users WHERE id = ?))";
        $params[] = $user_id;
    }
    // Principal and HR admin can see all applications
    
    // Status filter
    if (!empty($filter_status)) {
        $where_clauses[] = "la.status = ?";
        $params[] = $filter_status;
    }
    
    // Year filter
    if (!empty($filter_year)) {
        $where_clauses[] = "YEAR(la.applied_at) = ?";
        $params[] = $filter_year;
    }
    
    // Leave type filter
    if (!empty($filter_leave_type)) {
        $where_clauses[] = "la.leave_type_id = ?";
        $params[] = $filter_leave_type;
    }
    
    // Combine where clauses
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Count total records
    $count_sql = "SELECT COUNT(*) " . $base_query . " " . $where_sql;
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $pagination_limit);
    
    // Get leave applications with pagination
    $sql = "SELECT la.*, u.first_name, u.last_name, u.employee_id, lt.name as leave_type_name, 
           d.name as department_name " . $base_query . " " . $where_sql . "
           ORDER BY la.applied_at DESC 
           LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $param_count = count($params);
    for ($i = 0; $i < $param_count; $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }
    $stmt->bindParam($param_count + 1, $pagination_limit, PDO::PARAM_INT);
    $stmt->bindParam($param_count + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leave_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get approval details for each application
    foreach ($leave_applications as &$application) {
        $stmt = $conn->prepare("SELECT la.*, u.first_name, u.last_name, u.employee_id 
                              FROM leave_approvals la 
                              JOIN users u ON la.approver_id = u.id 
                              WHERE la.leave_application_id = ? 
                              ORDER BY la.created_at ASC");
        $stmt->execute([$application['id']]);
        $application['approvals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get documents attached to this application
        $stmt = $conn->prepare("SELECT * FROM documents WHERE leave_application_id = ?");
        $stmt->execute([$application['id']]);
        $application['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errors[] = "Error retrieving leave applications: " . $e->getMessage();
    $leave_applications = [];
    $total_pages = 1;
}

// Handle leave application cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $application_id = $_GET['cancel'];
    
    try {
        // Check if application exists and belongs to user
        $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            $_SESSION['alert'] = "Leave application not found.";
            $_SESSION['alert_type'] = "danger";
            header('Location: ./modules/leave_history.php');
            exit;
        }
        
        // Check if user has permission to cancel
        $can_cancel = false;
        
        // Application owner can cancel if status is pending
        if ($application['user_id'] == $user_id && $application['status'] == 'pending') {
            $can_cancel = true;
        }
        
        // HR admin can cancel any pending application
        if ($role == 'hr_admin' && $application['status'] == 'pending') {
            $can_cancel = true;
        }
        
        if (!$can_cancel) {
            $_SESSION['alert'] = "You don't have permission to cancel this application or it's already processed.";
            $_SESSION['alert_type'] = "danger";
            header('Location: ./modules/leave_history.php');
            exit;
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Update application status
        $stmt = $conn->prepare("UPDATE leave_applications SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$application_id]);
        
        // Delete pending approvals
        $stmt = $conn->prepare("DELETE FROM leave_approvals WHERE leave_application_id = ? AND status = 'pending'");
        $stmt->execute([$application_id]);
        
        // Log the action
        $action = "Cancelled leave application #" . $application_id;
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
        
        $conn->commit();
        
        $_SESSION['alert'] = "Leave application cancelled successfully.";
        $_SESSION['alert_type'] = "success";
        header('Location: ./modules/leave_history.php');
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header('Location: ./modules/leave_history.php');
        exit;
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Leave History</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Leave History</li>
    </ol>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-history me-1"></i>
            Leave Applications
            <div class="float-end">
                <a href="./modules/apply_leave.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Apply for Leave
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="leave_type" class="form-label">Leave Type</label>
                            <select class="form-select" id="leave_type" name="leave_type">
                                <option value="">All Types</option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $filter_leave_type == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="./modules/leave_history.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Leave Applications Table -->
            <?php if (empty($leave_applications)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No leave applications found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <?php if (in_array($role, ['department_head', 'dean', 'principal', 'hr_admin'])): ?>
                                    <th>Employee</th>
                                    <th>Department</th>
                                <?php endif; ?>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th>Days</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_applications as $application): ?>
                                <tr>
                                    <td><?php echo $application['id']; ?></td>
                                    <?php if (in_array($role, ['department_head', 'dean', 'principal', 'hr_admin'])): ?>
                                        <td><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($application['department_name']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($application['leave_type_name']); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($application['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                                    </td>
                                    <td><?php echo $application['working_days']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($application['applied_at'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($application['status']) {
                                            case 'pending': $status_class = 'bg-warning'; break;
                                            case 'approved': $status_class = 'bg-success'; break;
                                            case 'rejected': $status_class = 'bg-danger'; break;
                                            case 'cancelled': $status_class = 'bg-secondary'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $application['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($application['status'] == 'pending' && ($application['user_id'] == $user_id || $role == 'hr_admin')): ?>
                                            <a href="?cancel=<?php echo $application['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this leave application?');">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Leave history pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&year=<?php echo $filter_year; ?>&leave_type=<?php echo $filter_leave_type; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&year=<?php echo $filter_year; ?>&leave_type=<?php echo $filter_leave_type; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&year=<?php echo $filter_year; ?>&leave_type=<?php echo $filter_leave_type; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Leave Application Details Modals -->
<?php foreach ($leave_applications as $application): ?>
    <div class="modal fade" id="detailsModal<?php echo $application['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $application['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel<?php echo $application['id']; ?>">
                        Leave Application #<?php echo $application['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Employee Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Name</th>
                                    <td><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Employee ID</th>
                                    <td><?php echo htmlspecialchars($application['employee_id']); ?></td>
                                </tr>
                                <tr>
                                    <th>Department</th>
                                    <td><?php echo htmlspecialchars($application['department_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Leave Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Leave Type</th>
                                    <td><?php echo htmlspecialchars($application['leave_type_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Period</th>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($application['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Working Days</th>
                                    <td><?php echo $application['working_days']; ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Applied On</th>
                                    <td><?php echo date('M d, Y H:i', strtotime($application['applied_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6>Reason for Leave</h6>
                            <p><?php echo nl2br(htmlspecialchars($application['reason'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($application['contact_during_leave'])): ?>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Contact During Leave</h6>
                                <p><?php echo htmlspecialchars($application['contact_during_leave']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($application['documents'])): ?>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Supporting Documents</h6>
                                <ul class="list-group">
                                    <?php foreach ($application['documents'] as $doc): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php 
                                            $icon_class = 'fa-file';
                                            switch ($doc['file_type']) {
                                                case 'pdf': $icon_class = 'fa-file-pdf'; break;
                                                case 'doc': case 'docx': $icon_class = 'fa-file-word'; break;
                                                case 'jpg': case 'jpeg': case 'png': $icon_class = 'fa-file-image'; break;
                                            }
                                            ?>
                                            <span>
                                                <i class="fas <?php echo $icon_class; ?> me-1"></i>
                                                <?php echo htmlspecialchars($doc['file_name']); ?>
                                            </span>
                                            <a href="/modules/documents.php?download=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($application['approvals'])): ?>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Approval History</h6>
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Approver</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($application['approvals'] as $approval): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></td>
                                                <td><?php echo ucwords(str_replace('_', ' ', $approval['approver_role'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $approval_status_class = '';
                                                    switch ($approval['status']) {
                                                        case 'pending': $approval_status_class = 'bg-warning'; break;
                                                        case 'approved': $approval_status_class = 'bg-success'; break;
                                                        case 'rejected': $approval_status_class = 'bg-danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $approval_status_class; ?>">
                                                        <?php echo ucfirst($approval['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($approval['status'] == 'pending') {
                                                        echo 'Awaiting response';
                                                    } else {
                                                        echo date('M d, Y H:i', strtotime($approval['updated_at']));
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo !empty($approval['comments']) ? nl2br(htmlspecialchars($approval['comments'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($application['status'] == 'pending' && ($application['user_id'] == $user_id || $role == 'hr_admin')): ?>
                        <a href="?cancel=<?php echo $application['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this leave application?');">
                            Cancel Application
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>