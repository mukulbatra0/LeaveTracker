<?php
// Staff Dashboard

// Check if user is logged in and has staff role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit;
}

// Include dashboard widgets
require_once 'modules/dashboard_widgets.php';

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

// Get upcoming academic events
$upcoming_events_sql = "SELECT event_name, start_date, end_date, event_type 
                       FROM academic_calendar 
                       WHERE end_date >= CURDATE() 
                       ORDER BY start_date ASC LIMIT 5";
$upcoming_events_stmt = $conn->prepare($upcoming_events_sql);
$upcoming_events_stmt->execute();
$upcoming_events = $upcoming_events_stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Staff Dashboard</h1>
        <div>
            <a href="../modules/leave_calendar.php" class="btn btn-info me-2">
                <i class="fas fa-calendar-alt me-1"></i> Leave Calendar
            </a>
            <a href="../modules/apply_leave.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Apply for Leave
            </a>
        </div>
    </div>
    
    <!-- Enhanced Dashboard Widgets -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-professional">
            <?php echo getLeaveBalanceWidget($conn, $user_id); ?>
        </div>
        <div class="col-lg-4">
            <div class="quick-actions mb-professional">
                <?php echo getQuickActionsWidget($_SESSION['role']); ?>
            </div>
            <?php echo getUpcomingLeavesWidget($conn, $user_id); ?>
            <?php echo getNotificationWidget($conn, $user_id); ?>
        </div>
    </div>
    
    <!-- Leave Balance Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">My Leave Balances (<?php echo $current_year; ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if(count($leave_balances) > 0): ?>
                            <?php foreach($leave_balances as $balance): ?>
                                <div class="col-md-4 col-sm-6 mb-3">
                                    <div class="card dashboard-card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($balance['name']); ?></h5>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Available:</span>
                                                <strong><?php echo number_format($balance['balance'], 1); ?> days</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Used:</span>
                                                <strong><?php echo number_format($balance['used'], 1); ?> days</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total:</span>
                                                <strong><?php echo number_format($balance['max_days'], 1); ?> days</strong>
                                            </div>
                                            <div class="progress leave-balance-progress">
                                                <?php 
                                                $percentage = ($balance['used'] / $balance['max_days']) * 100;
                                                $color = $percentage > 75 ? 'danger' : ($percentage > 50 ? 'warning' : 'success');
                                                ?>
                                                <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No leave balances found for the current year.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications and Upcoming Events -->
    <div class="row">
        <!-- Recent Leave Applications -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Leave Applications</h5>
                    <a href="../modules/my_leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No recent leave applications found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Holidays and Events -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="upcomingTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="holidays-tab" data-bs-toggle="tab" data-bs-target="#holidays" type="button" role="tab" aria-controls="holidays" aria-selected="true">Upcoming Holidays</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab" aria-controls="events" aria-selected="false">Academic Events</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="upcomingTabsContent">
                        <!-- Holidays Tab -->
                        <div class="tab-pane fade show active" id="holidays" role="tabpanel" aria-labelledby="holidays-tab">
                            <?php if(count($upcoming_holidays) > 0): ?>
                                <div class="list-group">
                                    <?php foreach($upcoming_holidays as $holiday): ?>
                                        <?php 
                                        $holiday_date = new DateTime($holiday['date']);
                                        $days_until = $holiday_date->diff(new DateTime())->days;
                                        ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($holiday['name']); ?></h6>
                                                <small class="text-muted"><?php echo $holiday_date->format('M d, Y'); ?></small>
                                            </div>
                                            <?php if(!empty($holiday['description'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($holiday['description']); ?></small>
                                            <?php endif; ?>
                                            <?php if($days_until == 0): ?>
                                                <span class="badge bg-success">Today</span>
                                            <?php elseif($days_until == 1): ?>
                                                <span class="badge bg-primary">Tomorrow</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><?php echo $days_until; ?> days away</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No upcoming holidays found.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Academic Events Tab -->
                        <div class="tab-pane fade" id="events" role="tabpanel" aria-labelledby="events-tab">
                            <?php if(count($upcoming_events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach($upcoming_events as $event): ?>
                                        <?php 
                                        $event_start = new DateTime($event['start_date']);
                                        $event_end = new DateTime($event['end_date']);
                                        $days_until = $event_start->diff(new DateTime())->days;
                                        
                                        $event_class = '';
                                        switch($event['event_type']) {
                                            case 'semester':
                                                $event_class = 'semester';
                                                break;
                                            case 'exam':
                                                $event_class = 'exam';
                                                break;
                                            case 'staff_development':
                                                $event_class = 'staff-development';
                                                break;
                                            case 'restricted_leave_period':
                                                $event_class = 'restricted';
                                                break;
                                        }
                                        ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php 
                                                    echo $event_start->format('M d') . ' - ' . $event_end->format('M d, Y');
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="calendar-event <?php echo $event_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                            </div>
                                            <?php if($days_until == 0): ?>
                                                <span class="badge bg-success">Today</span>
                                            <?php elseif($days_until == 1): ?>
                                                <span class="badge bg-primary">Tomorrow</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><?php echo $days_until; ?> days away</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No upcoming academic events found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>