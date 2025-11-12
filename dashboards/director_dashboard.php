<?php
// Director Dashboard

// Check if user is logged in and has director role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'director') {
    header('Location: login.php');
    exit;
}

// Include dashboard widgets
require_once __DIR__ . '/../modules/dashboard_widgets.php';

// Get user information
$user_id = $_SESSION['user_id'];

// Get applications pending director approval (second level - from head of department to director)
$pending_approvals_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, lt.name as leave_type, 
                         la.start_date, la.end_date, la.days, la.reason, la.created_at, d.name as department,
                         hod.first_name as hod_first_name, hod.last_name as hod_last_name
                         FROM leave_applications la 
                         JOIN users u ON la.user_id = u.id 
                         JOIN leave_types lt ON la.leave_type_id = lt.id 
                         JOIN departments d ON u.department_id = d.id
                         LEFT JOIN users hod ON d.head_id = hod.id
                         WHERE la.status = 'pending'
                         AND EXISTS (
                             SELECT 1 FROM leave_approvals lap 
                             WHERE lap.leave_application_id = la.id 
                             AND lap.approver_level = 'head_of_department'
                             AND lap.status = 'approved'
                         )
                         AND NOT EXISTS (
                             SELECT 1 FROM leave_approvals lap2 
                             WHERE lap2.leave_application_id = la.id 
                             AND lap2.approver_level = 'director'
                         )
                         ORDER BY la.created_at ASC";
$pending_approvals_stmt = $conn->prepare($pending_approvals_sql);
$pending_approvals_stmt->execute();
$pending_approvals = $pending_approvals_stmt->fetchAll();

// Get institution-wide statistics
$institution_stats_sql = "SELECT 
    COUNT(CASE WHEN u.status = 'active' THEN 1 END) as total_staff,
    COUNT(CASE WHEN la.status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN la.status = 'approved' AND MONTH(la.created_at) = MONTH(CURDATE()) THEN 1 END) as monthly_approved,
    COUNT(CASE WHEN la.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as weekly_applications
    FROM users u
    LEFT JOIN leave_applications la ON u.id = la.user_id
    WHERE u.role IN ('staff', 'head_of_department')";
$institution_stats_stmt = $conn->prepare($institution_stats_sql);
$institution_stats_stmt->execute();
$institution_stats = $institution_stats_stmt->fetch();

// Get recent applications across all departments
$recent_applications_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, lt.name as leave_type, 
                           la.start_date, la.end_date, la.days, la.status, la.created_at, d.name as department
                           FROM leave_applications la 
                           JOIN users u ON la.user_id = u.id 
                           JOIN leave_types lt ON la.leave_type_id = lt.id 
                           JOIN departments d ON u.department_id = d.id
                           ORDER BY la.created_at DESC LIMIT 10";
$recent_applications_stmt = $conn->prepare($recent_applications_sql);
$recent_applications_stmt->execute();
$recent_applications = $recent_applications_stmt->fetchAll();

// Get department-wise summary
$department_summary_sql = "SELECT d.name, COUNT(la.id) as total_applications,
                          COUNT(CASE WHEN la.status = 'approved' THEN 1 END) as approved,
                          COUNT(CASE WHEN la.status = 'pending' THEN 1 END) as pending,
                          COUNT(CASE WHEN la.status = 'rejected' THEN 1 END) as rejected
                          FROM departments d
                          LEFT JOIN users u ON d.id = u.department_id
                          LEFT JOIN leave_applications la ON u.id = la.user_id
                          WHERE YEAR(la.created_at) = YEAR(CURDATE()) OR la.created_at IS NULL
                          GROUP BY d.id, d.name
                          ORDER BY total_applications DESC";
$department_summary_stmt = $conn->prepare($department_summary_sql);
$department_summary_stmt->execute();
$department_summary = $department_summary_stmt->fetchAll();

// Get own leave balances
$current_year = date('Y');
$leave_balances_sql = "SELECT lt.name, 
                             (lb.total_days - lb.used_days) as balance, 
                             lb.used_days as used, 
                             lt.max_days,
                             lb.total_days
                      FROM leave_balances lb 
                      JOIN leave_types lt ON lb.leave_type_id = lt.id 
                      WHERE lb.user_id = :user_id AND lb.year = :year";
$leave_balances_stmt = $conn->prepare($leave_balances_sql);
$leave_balances_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$leave_balances_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
$leave_balances_stmt->execute();
$leave_balances = $leave_balances_stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-crown me-2"></i>Director Dashboard</h2>
            <p class="text-muted">Institution-wide leave management and final approvals</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="./modules/apply_leave.php" class="btn btn-primary me-2">
                <i class="fas fa-plus-circle me-1"></i> Apply Leave
            </a>
            <a href="./modules/reports.php" class="btn btn-outline-info">
                <i class="fas fa-chart-line me-1"></i> Reports
            </a>
        </div>
    </div>
    
    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo count($pending_approvals); ?></h4>
                            <p class="mb-0">Final Approvals</p>
                        </div>
                        <i class="fas fa-gavel fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $institution_stats['total_staff']; ?></h4>
                            <p class="mb-0">Total Staff</p>
                        </div>
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $institution_stats['monthly_approved']; ?></h4>
                            <p class="mb-0">Approved This Month</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $institution_stats['weekly_applications']; ?></h4>
                            <p class="mb-0">This Week</p>
                        </div>
                        <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Row -->
    <div class="row">
        <!-- Pending Final Approvals -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Pending Final Approvals</h5>
                    <span class="badge bg-warning"><?php echo count($pending_approvals); ?> pending</span>
                </div>
                <div class="card-body">
                    <?php if(count($pending_approvals) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>HOD Approved</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_approvals as $application): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($application['employee_id']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($application['department']); ?></td>
                                            <td><?php echo htmlspecialchars($application['leave_type']); ?></td>
                                            <td>
                                                <?php 
                                                $start_date = new DateTime($application['start_date']);
                                                $end_date = new DateTime($application['end_date']);
                                                echo $start_date->format('M d');
                                                if($application['start_date'] != $application['end_date']) {
                                                    echo ' - ' . $end_date->format('M d');
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo number_format($application['days'], 1); ?></td>
                                            <td>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> 
                                                    <?php echo htmlspecialchars($application['hod_first_name'] . ' ' . $application['hod_last_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-success btn-sm" onclick="finalApprove(<?php echo $application['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="finalReject(<?php echo $application['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <button class="btn btn-info btn-sm" onclick="viewDetails(<?php echo $application['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No Pending Final Approvals</h5>
                            <p class="text-muted">All applications requiring director approval have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Department Summary -->
        <div class="col-lg-4 mb-4">
            <!-- Quick Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Director Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="./modules/apply_leave.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Apply for Leave
                        </a>
                        <a href="./modules/reports.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-building me-1"></i>Institution Overview
                        </a>
                        <a href="./modules/departments.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-sitemap me-1"></i>All Departments
                        </a>
                        <a href="./modules/reports.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-chart-line me-1"></i>Institution Reports
                        </a>
                        <a href="./admin/leave_types.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-contract me-1"></i>Leave Policies
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Personal Leave Balance Summary -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>My Leave Balance</h6>
                </div>
                <div class="card-body">
                    <?php if(count($leave_balances) > 0): ?>
                        <?php 
                        $total_balance = 0;
                        $total_used = 0;
                        foreach($leave_balances as $balance) {
                            $total_balance += $balance['balance'];
                            $total_used += $balance['used'];
                        }
                        ?>
                        <div class="text-center mb-3">
                            <h4 class="text-primary"><?php echo number_format($total_balance, 1); ?></h4>
                            <p class="mb-0">Days Available</p>
                        </div>
                        <?php foreach(array_slice($leave_balances, 0, 3) as $balance): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($balance['name']); ?></strong>
                                </div>
                                <span class="badge bg-primary"><?php echo number_format($balance['balance'], 1); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-2">
                            <a href="./modules/my_leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No leave balance data</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Department Summary and Recent Applications -->
    <div class="row">
        <!-- Department Summary -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Department Summary</h5>
                </div>
                <div class="card-body">
                    <?php if(count($department_summary) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Approved</th>
                                        <th class="text-center">Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($department_summary as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                            <td class="text-center"><?php echo $dept['total_applications']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?php echo $dept['approved']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning"><?php echo $dept['pending']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No department data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Applications</h5>
                    <a href="./modules/leave_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if(count($recent_applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <tbody>
                                    <?php foreach(array_slice($recent_applications, 0, 6) as $application): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($application['department']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($application['leave_type']); ?>
                                                <small class="d-block text-muted"><?php echo number_format($application['days'], 1); ?> days</small>
                                            </td>
                                            <td class="text-end">
                                                <?php 
                                                $status_class = '';
                                                switch($application['status']) {
                                                    case 'approved':
                                                        $status_class = 'success';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'danger';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'secondary';
                                                        break;
                                                    default:
                                                        $status_class = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($application['status'])); ?>
                                                </span>
                                                <small class="d-block text-muted"><?php echo (new DateTime($application['created_at']))->format('M d'); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5>No Applications</h5>
                            <p class="text-muted">No leave applications found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function finalApprove(applicationId) {
    if(confirm('Are you sure you want to give final approval to this leave application?')) {
        // Implementation for final approval
        window.location.href = 'modules/process_final_approval.php?action=approve&id=' + applicationId;
    }
}

function finalReject(applicationId) {
    const reason = prompt('Please provide a reason for rejection:');
    if(reason && reason.trim() !== '') {
        // Implementation for final rejection
        window.location.href = 'modules/process_final_approval.php?action=reject&id=' + applicationId + '&reason=' + encodeURIComponent(reason);
    }
}

function viewDetails(applicationId) {
    // Implementation for viewing details
    window.location.href = 'modules/view_application.php?id=' + applicationId;
}
</script>