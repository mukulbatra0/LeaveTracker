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

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 2020 || $current_year > 2030) {
    $current_year = date('Y');
}

// Get leave applications for the current month
$start_date = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

$sql = "SELECT la.*, lt.name as leave_type_name, u.first_name, u.last_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        JOIN users u ON la.user_id = u.id
        WHERE la.status = 'approved'
        AND ((la.start_date <= :end_date AND la.end_date >= :start_date))";

$params = [':start_date' => $start_date, ':end_date' => $end_date];

// Role-based filtering
if ($role == 'staff') {
    $sql .= " AND la.user_id = :user_id";
    $params[':user_id'] = $user_id;
} elseif ($role == 'head_of_department') {
    $sql .= " AND u.department_id = (SELECT department_id FROM users WHERE id = :user_id)";
    $params[':user_id'] = $user_id;
}

$sql .= " ORDER BY la.start_date";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leave_applications = $stmt->fetchAll();

// Get holidays for the current month
$holidays_sql = "SELECT * FROM holidays 
                WHERE date BETWEEN :start_date AND :end_date
                ORDER BY date";
$holidays_stmt = $conn->prepare($holidays_sql);
$holidays_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$holidays = $holidays_stmt->fetchAll();

// Create calendar data
$calendar_data = [];

// Add leave applications to calendar
foreach ($leave_applications as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include end date
    
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    
    foreach ($period as $date) {
        if ($date->format('n') == $current_month && $date->format('Y') == $current_year) {
            $day = $date->format('j');
            if (!isset($calendar_data[$day])) {
                $calendar_data[$day] = ['leaves' => [], 'holidays' => []];
            }
            $calendar_data[$day]['leaves'][] = $leave;
        }
    }
}

// Add holidays to calendar
foreach ($holidays as $holiday) {
    $holiday_date = new DateTime($holiday['date']);
    if ($holiday_date->format('n') == $current_month && $holiday_date->format('Y') == $current_year) {
        $day = $holiday_date->format('j');
        if (!isset($calendar_data[$day])) {
            $calendar_data[$day] = ['leaves' => [], 'holidays' => []];
        }
        $calendar_data[$day]['holidays'][] = $holiday;
    }
}

// Calendar helper functions
function getMonthName($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[$month];
}

function getDaysInMonth($month, $year) {
    return date('t', mktime(0, 0, 0, $month, 1, $year));
}

function getFirstDayOfWeek($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calendar-alt me-2"></i>Leave Calendar</h2>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="?month=<?php echo $current_month == 1 ? 12 : $current_month - 1; ?>&year=<?php echo $current_month == 1 ? $current_year - 1 : $current_year; ?>" 
                   class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <button class="btn btn-primary" disabled>
                    <?php echo getMonthName($current_month) . ' ' . $current_year; ?>
                </button>
                <a href="?month=<?php echo $current_month == 12 ? 1 : $current_month + 1; ?>&year=<?php echo $current_month == 12 ? $current_year + 1 : $current_year; ?>" 
                   class="btn btn-outline-primary">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <span class="badge bg-success me-2"></span>Approved Leave
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-danger me-2"></span>Holiday
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-info me-2"></span>Weekend
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-light text-dark me-2"></span>Regular Day
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Calendar -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered calendar-table">
                    <thead>
                        <tr class="bg-light">
                            <th class="text-center">Sunday</th>
                            <th class="text-center">Monday</th>
                            <th class="text-center">Tuesday</th>
                            <th class="text-center">Wednesday</th>
                            <th class="text-center">Thursday</th>
                            <th class="text-center">Friday</th>
                            <th class="text-center">Saturday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days_in_month = getDaysInMonth($current_month, $current_year);
                        $first_day = getFirstDayOfWeek($current_month, $current_year);
                        $current_day = 1;
                        $weeks = ceil(($days_in_month + $first_day) / 7);
                        
                        for ($week = 0; $week < $weeks; $week++) {
                            echo '<tr>';
                            
                            for ($day_of_week = 0; $day_of_week < 7; $day_of_week++) {
                                if ($week == 0 && $day_of_week < $first_day) {
                                    // Empty cells before the first day of the month
                                    echo '<td class="calendar-day empty-day"></td>';
                                } elseif ($current_day > $days_in_month) {
                                    // Empty cells after the last day of the month
                                    echo '<td class="calendar-day empty-day"></td>';
                                } else {
                                    // Regular day
                                    $is_weekend = ($day_of_week == 0 || $day_of_week == 6);
                                    $is_today = ($current_day == date('j') && $current_month == date('n') && $current_year == date('Y'));
                                    
                                    $day_class = 'calendar-day';
                                    if ($is_weekend) $day_class .= ' weekend-day';
                                    if ($is_today) $day_class .= ' today';
                                    
                                    echo '<td class="' . $day_class . '">';
                                    echo '<div class="day-number">' . $current_day . '</div>';
                                    
                                    // Show holidays
                                    if (isset($calendar_data[$current_day]['holidays'])) {
                                        foreach ($calendar_data[$current_day]['holidays'] as $holiday) {
                                            echo '<div class="calendar-event holiday" title="' . htmlspecialchars($holiday['name']) . '">';
                                            echo '<i class="fas fa-star me-1"></i>' . htmlspecialchars($holiday['name']);
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // Show leaves
                                    if (isset($calendar_data[$current_day]['leaves'])) {
                                        foreach ($calendar_data[$current_day]['leaves'] as $leave) {
                                            $employee_name = $role == 'staff' ? 'You' : $leave['first_name'] . ' ' . $leave['last_name'];
                                            echo '<div class="calendar-event leave" style="background-color: ' . ($leave['color'] ?? '#28a745') . '" 
                                                      title="' . htmlspecialchars($employee_name . ' - ' . $leave['leave_type_name']) . '">';
                                            echo '<i class="fas fa-user-clock me-1"></i>';
                                            if ($role != 'staff') {
                                                echo htmlspecialchars($leave['first_name'] . ' ' . substr($leave['last_name'], 0, 1) . '.');
                                            } else {
                                                echo htmlspecialchars($leave['leave_type_name']);
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    
                                    echo '</td>';
                                    $current_day++;
                                }
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Leave Summary for the Month -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Leave Summary for <?php echo getMonthName($current_month) . ' ' . $current_year; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (count($leave_applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <?php if ($role != 'staff'): ?>
                                            <th>Employee</th>
                                        <?php endif; ?>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leave_applications as $leave): ?>
                                        <tr>
                                            <?php if ($role != 'staff'): ?>
                                                <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $leave['color'] ?? '#6c757d'; ?>">
                                                    <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                echo date('M d', strtotime($leave['start_date'])) . ' - ' . 
                                                     date('M d, Y', strtotime($leave['end_date'])); 
                                                ?>
                                            </td>
                                            <td><?php echo $leave['days']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No approved leaves found for this month.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-table {
    font-size: 0.9rem;
}

.calendar-day {
    height: 120px;
    vertical-align: top;
    padding: 5px;
    position: relative;
}

.empty-day {
    background-color: #f8f9fa;
}

.weekend-day {
    background-color: #e9ecef;
}

.today {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.calendar-event {
    font-size: 0.75rem;
    padding: 2px 4px;
    margin-bottom: 2px;
    border-radius: 3px;
    color: white;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.calendar-event.holiday {
    background-color: #dc3545;
}

.calendar-event.leave {
    background-color: #28a745;
}

@media (max-width: 768px) {
    .calendar-day {
        height: 80px;
        font-size: 0.8rem;
    }
    
    .calendar-event {
        font-size: 0.7rem;
        padding: 1px 2px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>