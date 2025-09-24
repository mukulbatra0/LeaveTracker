<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is a principal
if ($role != 'principal') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header("Location: ../index.php");
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

// Get monthly leave statistics for the past 12 months
$monthly_stats_sql = "SELECT 
                      DATE_FORMAT(la.start_date, '%Y-%m') as month,
                      DATE_FORMAT(la.start_date, '%b %Y') as month_name,
                      COUNT(*) as total_applications,
                      SUM(la.days) as total_days
                      FROM leave_applications la
                      WHERE la.status = 'approved'
                      AND la.start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                      GROUP BY month
                      ORDER BY month ASC";
$monthly_stats_stmt = $conn->prepare($monthly_stats_sql);
$monthly_stats_stmt->execute();
$monthly_stats = $monthly_stats_stmt->fetchAll();

// Get department-wise leave statistics
$dept_stats_sql = "SELECT d.name as department_name, 
                  COUNT(la.id) as total_applications,
                  SUM(CASE WHEN la.status = 'approved' THEN 1 ELSE 0 END) as approved,
                  SUM(CASE WHEN la.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                  SUM(CASE WHEN la.status = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM departments d
                  LEFT JOIN users u ON d.id = u.department_id
                  LEFT JOIN leave_applications la ON u.id = la.user_id AND la.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY d.id
                  ORDER BY total_applications DESC";
$dept_stats_stmt = $conn->prepare($dept_stats_sql);
$dept_stats_stmt->execute();
$department_stats = $dept_stats_stmt->fetchAll();

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

// Get upcoming leaves in the next 30 days
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));
$upcoming_sql = "SELECT la.*, 
                 lt.name as leave_type_name, 
                 u.first_name, 
                 u.last_name,
                 d.name as department_name
          FROM leave_applications la 
          JOIN leave_types lt ON la.leave_type_id = lt.id 
          JOIN users u ON la.user_id = u.id 
          JOIN departments d ON u.department_id = d.id
          WHERE la.status = 'approved' 
          AND ((la.start_date BETWEEN :today AND :next_month) OR 
               (la.end_date BETWEEN :today AND :next_month) OR
               (la.start_date <= :today AND la.end_date >= :next_month))
          ORDER BY la.start_date ASC
          LIMIT 10";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bindParam(':today', $today, PDO::PARAM_STR);
$upcoming_stmt->bindParam(':next_month', $next_month, PDO::PARAM_STR);
$upcoming_stmt->execute();
$upcoming_leaves = $upcoming_stmt->fetchAll();

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-landmark me-2"></i>Principal Dashboard</h2>
            <p class="text-muted">College-wide Leave Management Overview</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="../modules/leave_approvals.php" class="btn btn-primary">
                <i class="fas fa-check-circle me-1"></i> Manage Approvals
            </a>
            <a href="../reports/leave_report.php" class="btn btn-outline-secondary ms-2">
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
    </div>
    
    <div class="row">
        <!-- Monthly Leave Trends -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Leave Trends (12 Months)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($monthly_stats) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted">There are no leave applications in the past 12 months.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('monthlyTrendsChart').getContext('2d');
                                
                                // Prepare data for chart
                                const labels = [];
                                const applicationsData = [];
                                const daysData = [];
                                
                                <?php foreach ($monthly_stats as $month): ?>
                                    labels.push('<?php echo addslashes($month['month_name']); ?>');
                                    applicationsData.push(<?php echo $month['total_applications']; ?>);
                                    daysData.push(<?php echo $month['total_days']; ?>);
                                <?php endforeach; ?>
                                
                                // Chart.js not available - chart disabled
                                    type: 'line',
                                    data: {
                                        labels: labels,
                                        datasets: [
                                            {
                                                label: 'Applications',
                                                data: applicationsData,
                                                backgroundColor: 'rgba(13, 110, 253, 0.2)',
                                                borderColor: 'rgba(13, 110, 253, 1)',
                                                borderWidth: 2,
                                                tension: 0.3,
                                                yAxisID: 'y'
                                            },
                                            {
                                                label: 'Total Days',
                                                data: daysData,
                                                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                                                borderColor: 'rgba(220, 53, 69, 1)',
                                                borderWidth: 2,
                                                tension: 0.3,
                                                yAxisID: 'y1'
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                position: 'left',
                                                title: {
                                                    display: true,
                                                    text: 'Applications'
                                                }
                                            },
                                            y1: {
                                                beginAtZero: true,
                                                position: 'right',
                                                grid: {
                                                    drawOnChartArea: false
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Days'
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'bottom'
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Leave Type Distribution -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Leave Type Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (count($leave_types_distribution) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted">There are no leave applications in the past 12 months.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="height: 300px;">
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
                                
                                // Chart.js not available - chart disabled
                                    type: 'doughnut',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            data: data,
                                            backgroundColor: backgroundColor,
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom'
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Department Statistics -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Department Statistics (6 Months)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($department_stats) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted">There are no departments with leave applications.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="height: 400px;">
                            <canvas id="departmentChart"></canvas>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('departmentChart').getContext('2d');
                                
                                // Prepare data for chart
                                const labels = [];
                                const approvedData = [];
                                const rejectedData = [];
                                const pendingData = [];
                                
                                <?php foreach ($department_stats as $dept): ?>
                                    labels.push('<?php echo addslashes($dept['department_name']); ?>');
                                    approvedData.push(<?php echo $dept['approved']; ?>);
                                    rejectedData.push(<?php echo $dept['rejected']; ?>);
                                    pendingData.push(<?php echo $dept['pending']; ?>);
                                <?php endforeach; ?>
                                
                                // Chart.js not available - chart disabled
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [
                                            {
                                                label: 'Approved',
                                                data: approvedData,
                                                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                                borderColor: 'rgba(40, 167, 69, 1)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Rejected',
                                                data: rejectedData,
                                                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                                                borderColor: 'rgba(220, 53, 69, 1)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Pending',
                                                data: pendingData,
                                                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                                                borderColor: 'rgba(255, 193, 7, 1)',
                                                borderWidth: 1
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            x: {
                                                stacked: true
                                            },
                                            y: {
                                                stacked: true,
                                                beginAtZero: true
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'bottom'
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Pending Approvals -->
        <div class="col-md-4 mb-4">
            <div class="card">
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
                                <a href="../modules/leave_approvals.php" class="list-group-item list-group-item-action">
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
                        <?php if (count($pending_approvals) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="../modules/leave_approvals.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Leaves -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Leaves (Next 30 Days)</h5>
                    <a href="../reports/leave_report.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                        <th>Department</th>
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
                                            <td><?php echo htmlspecialchars($leave['department_name']); ?></td>
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

<?php

?>