<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is a department head
if ($role != 'department_head') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Get department information
$dept_sql = "SELECT d.* FROM departments d JOIN users u ON d.head_id = u.id WHERE u.id = :user_id";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$dept_stmt->execute();
$department = $dept_stmt->fetch();

$department_id = $department['id'];

if (!$department) {
    $_SESSION['alert'] = "Department information not found.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Get pending leave applications for approval
$pending_sql = "SELECT la.*, 
                lt.name as leave_type_name, 
                
                u.first_name, 
                u.last_name, 
                u.email,
                lap.id as approval_id
         FROM leave_applications la 
         JOIN leave_types lt ON la.leave_type_id = lt.id 
         JOIN users u ON la.user_id = u.id 
         JOIN leave_approvals lap ON la.id = lap.leave_application_id
         WHERE lap.approver_id = :user_id 
         AND lap.status = 'pending'
         AND la.status = 'pending'
         ORDER BY la.created_at ASC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$pending_stmt->execute();
$pending_approvals = $pending_stmt->fetchAll();

// Get department staff count
$staff_count_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = :department_id";
$staff_count_stmt = $conn->prepare($staff_count_sql);
$staff_count_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$staff_count_stmt->execute();
$staff_count = $staff_count_stmt->fetch()['count'];

// Get staff currently on leave
$on_leave_sql = "SELECT COUNT(DISTINCT la.user_id) as count 
                FROM leave_applications la 
                JOIN users u ON la.user_id = u.id 
                WHERE u.department_id = :department_id 
                AND la.status = 'approved' 
                AND CURDATE() BETWEEN la.start_date AND la.end_date";
$on_leave_stmt = $conn->prepare($on_leave_sql);
$on_leave_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$on_leave_stmt->execute();
$on_leave_count = $on_leave_stmt->fetch()['count'];

// Get leave applications this month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$month_apps_sql = "SELECT COUNT(*) as count 
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  WHERE u.department_id = :department_id 
                  AND la.created_at BETWEEN :month_start AND :month_end";
$month_apps_stmt = $conn->prepare($month_apps_sql);
$month_apps_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$month_apps_stmt->bindParam(':month_start', $month_start, PDO::PARAM_STR);
$month_apps_stmt->bindParam(':month_end', $month_end, PDO::PARAM_STR);
$month_apps_stmt->execute();
$month_apps_count = $month_apps_stmt->fetch()['count'];

// Get upcoming leaves in the next 30 days
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));
$upcoming_sql = "SELECT la.*, 
                 lt.name as leave_type_name, 
                 u.first_name, 
                 u.last_name
          FROM leave_applications la 
          JOIN leave_types lt ON la.leave_type_id = lt.id 
          JOIN users u ON la.user_id = u.id 
          WHERE u.department_id = :department_id 
          AND la.status = 'approved' 
          AND ((la.start_date BETWEEN :today AND :next_month) OR 
               (la.end_date BETWEEN :today AND :next_month) OR
               (la.start_date <= :today AND la.end_date >= :next_month))
          ORDER BY la.start_date ASC";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$upcoming_stmt->bindParam(':today', $today, PDO::PARAM_STR);
$upcoming_stmt->bindParam(':next_month', $next_month, PDO::PARAM_STR);
$upcoming_stmt->execute();
$upcoming_leaves = $upcoming_stmt->fetchAll();

// Get leave type distribution for department
$leave_types_sql = "SELECT lt.name, COUNT(la.id) as count 
                   FROM leave_applications la 
                   JOIN leave_types lt ON la.leave_type_id = lt.id 
                   JOIN users u ON la.user_id = u.id 
                   WHERE u.department_id = :department_id 
                   AND la.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                   GROUP BY lt.id 
                   ORDER BY count DESC";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$leave_types_stmt->execute();
$leave_types_distribution = $leave_types_stmt->fetchAll();

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Department Head Dashboard</h2>
            <p class="text-muted">Managing <?php echo htmlspecialchars($department['name']); ?> Department</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="./modules/leave_approvals.php" class="btn btn-primary">
                <i class="fas fa-check-circle me-1"></i> Manage Approvals
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <!-- Department Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Staff</h6>
                            <h2 class="card-title mb-0"><?php echo $staff_count; ?></h2>
                        </div>
                        <div class="dashboard-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Currently On Leave</h6>
                            <h2 class="card-title mb-0"><?php echo $on_leave_count; ?></h2>
                        </div>
                        <div class="dashboard-icon bg-success">
                            <i class="fas fa-umbrella-beach"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Pending Approvals</h6>
                            <h2 class="card-title mb-0"><?php echo count($pending_approvals); ?></h2>
                        </div>
                        <div class="dashboard-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Applications This Month</h6>
                            <h2 class="card-title mb-0"><?php echo $month_apps_count; ?></h2>
                        </div>
                        <div class="dashboard-icon bg-info">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Pending Approvals -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (count($pending_approvals) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-double fa-3x text-muted mb-3"></i>
                            <h5>No Pending Approvals</h5>
                            <p class="text-muted">You don't have any leave applications waiting for your approval.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($pending_approvals as $approval): ?>
                                <a href="./modules/leave_approvals.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></h6>
                                        <small><?php echo date('M d', strtotime($approval['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge" style="background-color: <?php echo $approval['leave_type_color'] ?? '#6c757d'; ?>">
                                            <?php echo htmlspecialchars($approval['leave_type_name']); ?>
                                        </span>
                                        <?php 
                                            $start_date = date('M d', strtotime($approval['start_date']));
                                            $end_date = date('M d', strtotime($approval['end_date']));
                                            echo $start_date;
                                            if ($start_date != $end_date) {
                                                echo ' - ' . $end_date;
                                            }
                                        ?>
                                        (<?php echo $approval['days']; ?> days)
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($pending_approvals) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="./modules/leave_approvals.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Leave Type Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Leave Type Distribution (12 Months)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($leave_types_distribution) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted">There are no leave applications in the past 12 months.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Applications</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total = array_sum(array_column($leave_types_distribution, 'count'));
                                    foreach ($leave_types_distribution as $type): 
                                        $percentage = $total > 0 ? round(($type['count'] / $total) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $type['count']; ?></span></td>
                                            <td><?php echo $percentage; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Leaves -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Department Leaves (Next 30 Days)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_leaves) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <h5>No Upcoming Leaves</h5>
                            <p class="text-muted">There are no approved leaves scheduled for the next 30 days.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_leaves as $leave): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $leave['leave_type_color'] ?? '#6c757d'; ?>">
                                                    <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                            <td><?php echo $leave['days']; ?> days</td>
                                            <td>
                                                <?php 
                                                    $today = date('Y-m-d');
                                                    if ($leave['start_date'] > $today) {
                                                        echo '<span class="badge bg-info">Upcoming</span>';
                                                    } elseif ($leave['end_date'] < $today) {
                                                        echo '<span class="badge bg-secondary">Completed</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success">Active</span>';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>