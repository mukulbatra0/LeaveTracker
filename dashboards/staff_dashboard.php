<?php
// Staff Dashboard

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit;
}

// Include dashboard widgets
require_once __DIR__ . '/../modules/dashboard_widgets.php';

// Get user information
$user_id = $_SESSION['user_id'];

// Get leave balances for current year
$current_year = date('Y');
$leave_balances_sql = "SELECT lt.name, lb.balance, lb.used, lt.max_days 
                      FROM leave_balances lb 
                      JOIN leave_types lt ON lb.leave_type_id = lt.id 
                      WHERE lb.user_id = :user_id AND lb.year = :year";
$leave_balances_stmt = $conn->prepare($leave_balances_sql);
$leave_balances_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$leave_balances_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
$leave_balances_stmt->execute();
$leave_balances = $leave_balances_stmt->fetchAll();

// Get recent leave applications
$recent_applications_sql = "SELECT la.id, lt.name as leave_type, la.start_date, la.end_date, la.days, la.status, la.created_at 
                           FROM leave_applications la 
                           JOIN leave_types lt ON la.leave_type_id = lt.id 
                           WHERE la.user_id = :user_id 
                           ORDER BY la.created_at DESC LIMIT 5";
$recent_applications_stmt = $conn->prepare($recent_applications_sql);
$recent_applications_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$recent_applications_stmt->execute();
$recent_applications = $recent_applications_stmt->fetchAll();

// Get upcoming holidays
$upcoming_holidays_sql = "SELECT name, date, description 
                         FROM holidays 
                         WHERE date >= CURDATE() 
                         ORDER BY date ASC LIMIT 5";
$upcoming_holidays_stmt = $conn->prepare($upcoming_holidays_sql);
$upcoming_holidays_stmt->execute();
$upcoming_holidays = $upcoming_holidays_stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Staff Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="./modules/apply_leave.php" class="btn btn-primary me-2">
                <i class="fas fa-plus-circle me-1"></i> Apply Leave
            </a>
            <a href="./modules/leave_calendar.php" class="btn btn-outline-info">
                <i class="fas fa-calendar-alt me-1"></i> Calendar
            </a>
        </div>
    </div>
    
    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <?php 
        $total_balance = 0;
        $total_used = 0;
        foreach($leave_balances as $balance) {
            $total_balance += $balance['balance'];
            $total_used += $balance['used'];
        }
        ?>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($total_balance, 1); ?></h4>
                            <p class="mb-0">Available Days</p>
                        </div>
                        <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($total_used, 1); ?></h4>
                            <p class="mb-0">Days Used</p>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo count($recent_applications); ?></h4>
                            <p class="mb-0">Recent Applications</p>
                        </div>
                        <i class="fas fa-file-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo count($upcoming_holidays); ?></h4>
                            <p class="mb-0">Upcoming Holidays</p>
                        </div>
                        <i class="fas fa-umbrella-beach fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Row -->
    <div class="row">
        <!-- Leave Balances -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>My Leave Balances (<?php echo $current_year; ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if(count($leave_balances) > 0): ?>
                        <div class="row">
                            <?php foreach($leave_balances as $balance): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold"><?php echo htmlspecialchars($balance['name']); ?></h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="text-primary">
                                                    <strong><?php echo number_format($balance['balance'], 1); ?></strong>
                                                    <small class="d-block text-muted">Available</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-danger">
                                                    <strong><?php echo number_format($balance['used'], 1); ?></strong>
                                                    <small class="d-block text-muted">Used</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-secondary">
                                                    <strong><?php echo number_format($balance['max_days'], 1); ?></strong>
                                                    <small class="d-block text-muted">Total</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php 
                                        $percentage = ($balance['used'] / $balance['max_days']) * 100;
                                        if ($percentage > 75) {
                                            $color = 'danger';
                                        } elseif ($percentage > 50) {
                                            $color = 'warning';
                                        } else {
                                            $color = 'success';
                                        }
                                        ?>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <h5>No Leave Balances</h5>
                            <p class="text-muted">No leave balances found for the current year.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Notifications -->
        <div class="col-lg-4 mb-4">
            <!-- Quick Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="./modules/apply_leave.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Apply for Leave
                        </a>
                        <a href="./modules/my_leaves.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>My Applications
                        </a>
                        <a href="./modules/leave_calendar.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-calendar me-1"></i>Leave Calendar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Holidays -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Upcoming Holidays</h6>
                </div>
                <div class="card-body">
                    <?php if(count($upcoming_holidays) > 0): ?>
                        <?php foreach(array_slice($upcoming_holidays, 0, 3) as $holiday): ?>
                            <?php 
                            $holiday_date = new DateTime($holiday['date']);
                            $days_until = $holiday_date->diff(new DateTime())->days;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($holiday['name']); ?></strong>
                                    <small class="d-block text-muted"><?php echo $holiday_date->format('M d, Y'); ?></small>
                                </div>
                                <span class="badge bg-info"><?php echo $days_until; ?> days</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No upcoming holidays</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Leave Applications</h5>
                    <a href="./modules/my_leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if(count($recent_applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_applications as $application): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($application['leave_type']); ?></td>
                                            <td>
                                                <?php 
                                                $start_date = new DateTime($application['start_date']);
                                                $end_date = new DateTime($application['end_date']);
                                                echo $start_date->format('M d, Y');
                                                if($application['start_date'] != $application['end_date']) {
                                                    echo ' - ' . $end_date->format('M d, Y');
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
                                            <td><?php echo (new DateTime($application['created_at']))->format('M d, Y'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5>No Recent Applications</h5>
                            <p class="text-muted">You haven't submitted any leave applications yet.</p>
                            <a href="./modules/apply_leave.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Apply for Leave
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>