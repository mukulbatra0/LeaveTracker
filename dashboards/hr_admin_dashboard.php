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

// Check if user is an HR admin
if ($role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
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
                d.name as department_name,
                lap.id as approval_id
         FROM leave_applications la 
         JOIN leave_types lt ON la.leave_type_id = lt.id 
         JOIN users u ON la.user_id = u.id 
         JOIN departments d ON u.department_id = d.id
         JOIN leave_approvals lap ON la.id = lap.leave_application_id
         WHERE lap.approver_id = :user_id 
         AND lap.status = 'pending'
         AND la.status = 'pending'
         ORDER BY la.created_at ASC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$pending_stmt->execute();
$pending_approvals = $pending_stmt->fetchAll();

// Get total staff count
$staff_count_sql = "SELECT COUNT(*) as count FROM users WHERE role != 'hr_admin' AND role != 'principal'";
$staff_count_stmt = $conn->prepare($staff_count_sql);
$staff_count_stmt->execute();
$staff_count = $staff_count_stmt->fetch()['count'];

// Get total departments count
$dept_count_sql = "SELECT COUNT(*) as count FROM departments";
$dept_count_stmt = $conn->prepare($dept_count_sql);
$dept_count_stmt->execute();
$dept_count = $dept_count_stmt->fetch()['count'];

// Get staff currently on leave
$on_leave_sql = "SELECT COUNT(DISTINCT la.user_id) as count 
                FROM leave_applications la 
                WHERE la.status = 'approved' 
                AND CURDATE() BETWEEN la.start_date AND la.end_date";
$on_leave_stmt = $conn->prepare($on_leave_sql);
$on_leave_stmt->execute();
$on_leave_count = $on_leave_stmt->fetch()['count'];

// Get leave applications this month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$month_apps_sql = "SELECT COUNT(*) as count 
                  FROM leave_applications la 
                  WHERE la.created_at BETWEEN :month_start AND :month_end";
$month_apps_stmt = $conn->prepare($month_apps_sql);
$month_apps_stmt->bindParam(':month_start', $month_start, PDO::PARAM_STR);
$month_apps_stmt->bindParam(':month_end', $month_end, PDO::PARAM_STR);
$month_apps_stmt->execute();
$month_apps_count = $month_apps_stmt->fetch()['count'];

// Get recent leave applications (last 10)
$recent_apps_sql = "SELECT la.*, 
                    lt.name as leave_type_name, 
                    
                    u.first_name, 
                    u.last_name,
                    d.name as department_name
             FROM leave_applications la 
             JOIN leave_types lt ON la.leave_type_id = lt.id 
             JOIN users u ON la.user_id = u.id 
             JOIN departments d ON u.department_id = d.id
             ORDER BY la.created_at DESC
             LIMIT 10";
$recent_apps_stmt = $conn->prepare($recent_apps_sql);
$recent_apps_stmt->execute();
$recent_applications = $recent_apps_stmt->fetchAll();

// Get leave type distribution
$leave_types_sql = "SELECT lt.name, COUNT(la.id) as count 
                   FROM leave_applications la 
                   JOIN leave_types lt ON la.leave_type_id = lt.id 
                   WHERE la.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                   GROUP BY lt.id 
                   ORDER BY count DESC";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->execute();
$leave_types_distribution = $leave_types_stmt->fetchAll();

// Get upcoming holidays
$holidays_sql = "SELECT * FROM holidays 
                WHERE date >= CURDATE() 
                ORDER BY date ASC 
                LIMIT 5";
$holidays_stmt = $conn->prepare($holidays_sql);
$holidays_stmt->execute();
$upcoming_holidays = $holidays_stmt->fetchAll();

// Get upcoming academic events
$academic_events_sql = "SELECT * FROM academic_calendar 
                       WHERE end_date >= CURDATE() 
                       ORDER BY start_date ASC 
                       LIMIT 5";
$academic_events_stmt = $conn->prepare($academic_events_sql);
$academic_events_stmt->execute();
$upcoming_academic_events = $academic_events_stmt->fetchAll();

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-user-tie me-2"></i>HR Admin Dashboard</h2>
            <p class="text-muted">Comprehensive Leave Management System</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="dropdown d-inline-block me-2">
                <button class="btn btn-primary dropdown-toggle" type="button" id="adminActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-1"></i> Admin Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="adminActionsDropdown">
                    <li><a class="dropdown-item" href="../modules/leave_approvals.php"><i class="fas fa-check-circle me-2"></i>Manage Approvals</a></li>
                    <li><a class="dropdown-item" href="../modules/leave_types.php"><i class="fas fa-tags me-2"></i>Manage Leave Types</a></li>
                    <li><a class="dropdown-item" href="../modules/departments.php"><i class="fas fa-building me-2"></i>Manage Departments</a></li>
                    <li><a class="dropdown-item" href="../modules/users.php"><i class="fas fa-users me-2"></i>Manage Users</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../modules/holidays.php"><i class="fas fa-calendar-alt me-2"></i>Manage Holidays</a></li>
                    <li><a class="dropdown-item" href="../modules/academic_calendar.php"><i class="fas fa-graduation-cap me-2"></i>Academic Calendar</a></li>
                </ul>
            </div>
            <a href="./reports/leave_report.php" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt me-1"></i> Reports
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
    
    <!-- Institution Stats -->
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
                            <h6 class="card-subtitle mb-2 text-muted">Departments</h6>
                            <h2 class="card-title mb-0"><?php echo $dept_count; ?></h2>
                        </div>
                        <div class="dashboard-icon bg-secondary">
                            <i class="fas fa-building"></i>
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
        <!-- Recent Applications -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Recent Leave Applications</h5>
                    <a href="./reports/leave_report.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_applications) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>No Recent Applications</h5>
                            <p class="text-muted">There are no recent leave applications in the system.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Applied On</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_applications as $application): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($application['department_name']); ?></td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $application['leave_type_color'] ?? '#6c757d'; ?>">
                                                    <?php echo htmlspecialchars($application['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start_date = date('M d', strtotime($application['start_date']));
                                                    $end_date = date('M d', strtotime($application['end_date']));
                                                    echo $start_date;
                                                    if ($start_date != $end_date) {
                                                        echo ' - ' . $end_date;
                                                    }
                                                ?>
                                                (<?php echo $application['days']; ?> days)
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                    switch ($application['status']) {
                                                        case 'pending':
                                                            echo '<span class="badge bg-warning">Pending</span>';
                                                            break;
                                                        case 'approved':
                                                            echo '<span class="badge bg-success">Approved</span>';
                                                            break;
                                                        case 'rejected':
                                                            echo '<span class="badge bg-danger">Rejected</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="badge bg-secondary">Cancelled</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-info">Unknown</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="./modules/view_leave.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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
        
        <!-- Right Column -->
        <div class="col-md-4 mb-4">
            <!-- Pending Approvals -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (count($pending_approvals) == 0): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-double fa-2x text-muted mb-2"></i>
                            <h6>No Pending Approvals</h6>
                            <p class="text-muted small">You don't have any leave applications waiting for your approval.</p>
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
                                    <small class="text-muted"><?php echo htmlspecialchars($approval['department_name']); ?> Department</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($pending_approvals) > 3): ?>
                            <div class="text-center mt-3">
                                <a href="./modules/leave_approvals.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Leave Type Distribution -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Leave Type Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (count($leave_types_distribution) == 0): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                            <h6>No Data Available</h6>
                            <p class="text-muted small">There are no leave applications in the past 12 months.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="leaveTypeChart"></canvas>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('leaveTypeChart').getContext('2d');
                                
                                // Prepare data for chart
                                const labels = [];
                                const data = [];
                                const backgroundColor = [];
                                
                                <?php foreach ($leave_types_distribution as $type): ?>
                                    labels.push('<?php echo addslashes($type['name']); ?>');
                                    data.push(<?php echo $type['count']; ?>);
                                    backgroundColor.push('<?php echo $type['color'] ?? '#6c757d'; ?>');
                                <?php endforeach; ?>
                                
                                // Display simple list instead of chart
                                const chartContainer = document.querySelector('.chart-container');
                                let listHTML = '<div class="list-group">';
                                
                                for (let i = 0; i < labels.length; i++) {
                                    listHTML += `<div class="list-group-item d-flex justify-content-between align-items-center">`;
                                    listHTML += `<span><span class="badge" style="background-color: ${backgroundColor[i]}; margin-right: 8px;"></span>${labels[i]}</span>`;
                                    listHTML += `<span class="badge bg-secondary">${data[i]}</span>`;
                                    listHTML += `</div>`;
                                }
                                
                                listHTML += '</div>';
                                chartContainer.innerHTML = listHTML;
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Upcoming Holidays -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Holidays</h5>
                    <a href="./modules/holidays.php" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_holidays) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                            <h5>No Upcoming Holidays</h5>
                            <p class="text-muted">There are no upcoming holidays in the system.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($upcoming_holidays as $holiday): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($holiday['name']); ?></h6>
                                        <span class="badge bg-info"><?php echo date('D', strtotime($holiday['date'])); ?></span>
                                    </div>
                                    <p class="mb-1">
                                        <i class="far fa-calendar-alt me-2"></i>
                                        <?php echo date('F d, Y', strtotime($holiday['date'])); ?>
                                    </p>
                                    <?php if (!empty($holiday['description'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($holiday['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Academic Calendar Events -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Calendar Events</h5>
                    <a href="./modules/academic_calendar.php" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_academic_events) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-week fa-3x text-muted mb-3"></i>
                            <h5>No Upcoming Academic Events</h5>
                            <p class="text-muted">There are no upcoming academic events in the system.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($upcoming_academic_events as $event): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <span class="badge bg-<?php echo $event['is_restricted'] ? 'danger' : 'success'; ?>">
                                            <?php echo $event['is_restricted'] ? 'Restricted' : 'Normal'; ?>
                                        </span>
                                    </div>
                                    <p class="mb-1">
                                        <i class="far fa-calendar-alt me-2"></i>
                                        <?php 
                                            $start_date = date('M d', strtotime($event['start_date']));
                                            $end_date = date('M d, Y', strtotime($event['end_date']));
                                            echo $start_date . ' - ' . $end_date;
                                        ?>
                                    </p>
                                    <?php if (!empty($event['description'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($event['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="./modules/users.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-users fa-2x mb-2"></i><br>
                                Manage Users
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="./modules/departments.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="fas fa-building fa-2x mb-2"></i><br>
                                Manage Departments
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="./modules/leave_types.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-tags fa-2x mb-2"></i><br>
                                Leave Types
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="./reports/leave_report.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                Reports & Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
?>