<?php
// Admin Dashboard

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// Include dashboard widgets
require_once __DIR__ . '/../modules/dashboard_widgets.php';

// Get system statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
    (SELECT COUNT(*) FROM leave_applications WHERE status = 'pending') as pending_applications,
    (SELECT COUNT(*) FROM leave_applications WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as monthly_applications,
    (SELECT COUNT(*) FROM departments) as total_departments,
    (SELECT COUNT(*) FROM leave_applications la 
     JOIN users u ON la.user_id = u.id 
     JOIN leave_approvals lap ON la.id = lap.leave_application_id
     WHERE la.status = 'pending' AND u.role = 'director' 
     AND lap.approver_level = 'admin' AND lap.status = 'pending') as director_approvals_pending";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get director leave applications pending admin approval (show all pending director applications for any admin)
$director_approvals_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, lt.name as leave_type, 
                          la.start_date, la.end_date, la.days, la.reason, la.created_at, d.name as department,
                          lap.approver_id, approver.first_name as assigned_admin_name
                          FROM leave_applications la 
                          JOIN users u ON la.user_id = u.id 
                          JOIN leave_types lt ON la.leave_type_id = lt.id 
                          JOIN departments d ON u.department_id = d.id
                          JOIN leave_approvals lap ON la.id = lap.leave_application_id
                          LEFT JOIN users approver ON lap.approver_id = approver.id
                          WHERE la.status = 'pending'
                          AND u.role = 'director'
                          AND lap.approver_level = 'admin'
                          AND lap.status = 'pending'
                          ORDER BY la.created_at ASC";
$director_approvals_stmt = $conn->prepare($director_approvals_sql);
$director_approvals_stmt->execute();
$director_approvals = $director_approvals_stmt->fetchAll();

// Get recent leave applications for admin overview
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

// Get department-wise leave statistics
$dept_stats_sql = "SELECT d.name, COUNT(la.id) as total_applications,
                   SUM(CASE WHEN la.status = 'approved' THEN 1 ELSE 0 END) as approved,
                   SUM(CASE WHEN la.status = 'pending' THEN 1 ELSE 0 END) as pending
                   FROM departments d
                   LEFT JOIN users u ON d.id = u.department_id
                   LEFT JOIN leave_applications la ON u.id = la.user_id
                   WHERE YEAR(la.created_at) = YEAR(CURDATE()) OR la.created_at IS NULL
                   GROUP BY d.id, d.name
                   ORDER BY total_applications DESC";
$dept_stats_stmt = $conn->prepare($dept_stats_sql);
$dept_stats_stmt->execute();
$dept_stats = $dept_stats_stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-cogs me-2"></i>Admin Dashboard</h2>
            <p class="text-muted">System administration and management overview</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="./admin/users.php" class="btn btn-primary me-2">
                <i class="fas fa-users me-1"></i> Manage Users
            </a>
            <a href="./admin/system_config.php" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> Settings
            </a>
        </div>
    </div>
    
    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['total_users']; ?></h4>
                            <p class="mb-0">Active Users</p>
                        </div>
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['pending_applications']; ?></h4>
                            <p class="mb-0">Pending Applications</p>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['director_approvals_pending']; ?></h4>
                            <p class="mb-0">Director Approvals</p>
                        </div>
                        <i class="fas fa-crown fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['monthly_applications']; ?></h4>
                            <p class="mb-0">This Month</p>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Second Row of Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['total_departments']; ?></h4>
                            <p class="mb-0">Departments</p>
                        </div>
                        <i class="fas fa-building fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add more stats cards here if needed -->
    </div>
    </div>
    
    <!-- Director Leave Approvals Section -->
    <?php if (count($director_approvals) > 0): ?>
    <div class="row mb-4" id="director-approvals">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-crown me-2"></i>Director Leave Approvals Required
                        <span class="badge bg-dark ms-2"><?php echo count($director_approvals); ?> pending</span>
                    </h5>
                    <a href="./admin/director_leave_approvals.php" class="btn btn-dark btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Director</th>
                                    <th>Department</th>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($director_approvals as $application): ?>
                                    <tr class="table-warning">
                                        <td>
                                            <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($application['employee_id']); ?></small>
                                            <span class="badge bg-warning text-dark">Director</span>
                                            <?php if (!empty($application['assigned_admin_name'])): ?>
                                                <br><small class="text-info">Originally assigned to: <?php echo htmlspecialchars($application['assigned_admin_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($application['department']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($application['leave_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $start_date = new DateTime($application['start_date']);
                                            $end_date = new DateTime($application['end_date']);
                                            echo $start_date->format('M d, Y');
                                            if($application['start_date'] != $application['end_date']) {
                                                echo ' to ' . $end_date->format('M d, Y');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo number_format($application['days'], 1); ?> days</span>
                                        </td>
                                        <td><?php echo (new DateTime($application['created_at']))->format('M d, Y H:i'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success btn-sm" onclick="approveDirectorLeave(<?php echo $application['id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="rejectDirectorLeave(<?php echo $application['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <button class="btn btn-info btn-sm" onclick="viewDirectorApplication(<?php echo $application['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Director leave applications require admin approval. Any admin can approve these applications regardless of original assignment. These are high-priority items that should be reviewed promptly.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Row -->
    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Leave Applications</h5>
                    <a href="./modules/leave_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if(count($recent_applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Applied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_applications as $application): ?>
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
                                            </td>
                                            <td><?php echo (new DateTime($application['created_at']))->format('M d'); ?></td>
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
        
        <!-- Admin Quick Actions & Department Stats -->
        <div class="col-lg-4 mb-4">
            <!-- Quick Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Admin Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($stats['director_approvals_pending'] > 0): ?>
                        <a href="./admin/director_leave_approvals.php" class="btn btn-danger btn-sm">
                            <i class="fas fa-crown me-1"></i>Director Approvals 
                            <span class="badge bg-light text-dark"><?php echo $stats['director_approvals_pending']; ?></span>
                        </a>
                        <?php endif; ?>
                        <a href="./admin/users.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-users me-1"></i>Manage Users
                        </a>
                        <a href="./admin/departments.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-building me-1"></i>Departments
                        </a>
                        <a href="./admin/leave_types.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>Leave Types
                        </a>
                        <a href="./admin/holidays.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-calendar me-1"></i>Holidays
                        </a>
                        <a href="./modules/reports.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Department Statistics -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Department Overview</h6>
                </div>
                <div class="card-body">
                    <?php if(count($dept_stats) > 0): ?>
                        <?php foreach(array_slice($dept_stats, 0, 5) as $dept): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                    <small class="text-muted"><?php echo $dept['total_applications']; ?> total</small>
                                </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <span class="badge bg-success"><?php echo $dept['approved']; ?> approved</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="badge bg-warning"><?php echo $dept['pending']; ?> pending</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No department data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function approveDirectorLeave(applicationId) {
    if(confirm('Are you sure you want to approve this Director leave application?')) {
        const comments = prompt('Add any comments (optional):');
        let url = 'modules/process_approval.php?action=approve&id=' + applicationId;
        if (comments && comments.trim() !== '') {
            url += '&reason=' + encodeURIComponent(comments);
        }
        window.location.href = url;
    }
}

function rejectDirectorLeave(applicationId) {
    const reason = prompt('Please provide a reason for rejection (required):');
    if(reason && reason.trim() !== '') {
        window.location.href = 'modules/process_approval.php?action=reject&id=' + applicationId + '&reason=' + encodeURIComponent(reason);
    } else if (reason !== null) {
        alert('Reason for rejection is required.');
    }
}

function viewDirectorApplication(applicationId) {
    window.location.href = 'modules/view_application.php?id=' + applicationId;
}
</script>