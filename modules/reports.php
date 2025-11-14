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

// Check if user has permission to access reports
$allowed_roles = ['admin', 'head_of_department', 'director', 'dean', 'principal', 'hr_admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Get current date and default date ranges
$current_date = new DateTime();
$current_year = $current_date->format('Y');
$current_month = $current_date->format('m');

// Default to current month if no date range is specified
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $current_year . '-' . $current_month . '-01';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $current_date->format('Y-m-t'); // Last day of current month

// Get department filter
$department_filter = isset($_GET['department_id']) ? $_GET['department_id'] : 'all';

// Get leave type filter
$leave_type_filter = isset($_GET['leave_type_id']) ? $_GET['leave_type_id'] : 'all';

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get report type
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'leave_utilization';

// Get all departments for filter dropdown
$dept_sql = "SELECT id, name FROM departments ORDER BY name ASC";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll();

// Get all leave types for filter dropdown
$leave_type_sql = "SELECT id, name, color FROM leave_types ORDER BY name ASC";
$leave_type_stmt = $conn->prepare($leave_type_sql);
$leave_type_stmt->execute();
$leave_types = $leave_type_stmt->fetchAll();

// Initialize report data array
$report_data = [];
$chart_data = [];

// Build query based on filters and report type
if ($report_type == 'leave_utilization') {
    // Leave Utilization Report
    $sql = "SELECT la.id, la.start_date, la.end_date, la.days, la.status, la.reason,
                  lt.name as leave_type, lt.color as leave_type_color,
                  u.first_name, u.last_name, u.employee_id, u.email,
                  d.name as department_name
           FROM leave_applications la
           JOIN leave_types lt ON la.leave_type_id = lt.id
           JOIN users u ON la.user_id = u.id
           JOIN departments d ON u.department_id = d.id
           WHERE la.start_date >= :start_date AND la.end_date <= :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);

        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }

    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND la.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }

    // Add status filter if specified
    if ($status_filter != 'all') {
        $sql .= " AND la.status = :status";
        $params[':status'] = $status_filter;
    }

    $sql .= " ORDER BY la.start_date DESC";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll();

    // Generate chart data for leave type distribution
    $chart_sql = "SELECT lt.name, lt.color, COUNT(la.id) as count, SUM(la.days) as total_days
                 FROM leave_applications la
                 JOIN leave_types lt ON la.leave_type_id = lt.id
                 JOIN users u ON la.user_id = u.id
                 WHERE la.start_date >= :start_date AND la.end_date <= :end_date";

    $chart_params = [':start_date' => $start_date, ':end_date' => $end_date];

    // Add department filter for chart if specified
    if ($department_filter != 'all') {
        $chart_sql .= " AND u.department_id = :department_id";
        $chart_params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        $chart_sql .= " AND u.department_id = :department_id";
        $chart_params[':department_id'] = $user_dept['department_id'];
    }

    // Add status filter for chart if specified
    if ($status_filter != 'all') {
        $chart_sql .= " AND la.status = :status";
        $chart_params[':status'] = $status_filter;
    }

    $chart_sql .= " GROUP BY lt.id ORDER BY total_days DESC";

    $chart_stmt = $conn->prepare($chart_sql);
    foreach ($chart_params as $key => $value) {
        $chart_stmt->bindValue($key, $value);
    }
    $chart_stmt->execute();
    $chart_data = $chart_stmt->fetchAll();
} elseif ($report_type == 'department_summary') {
    // Department Summary Report
    $sql = "SELECT d.name as department_name, 
                  COUNT(DISTINCT u.id) as total_staff,
                  COUNT(DISTINCT CASE WHEN la.status = 'approved' AND la.start_date <= CURDATE() AND la.end_date >= CURDATE() THEN la.user_id END) as staff_on_leave,
                  COUNT(la.id) as total_applications,
                  SUM(CASE WHEN la.status = 'approved' THEN la.days ELSE 0 END) as approved_days,
                  SUM(CASE WHEN la.status = 'rejected' THEN la.days ELSE 0 END) as rejected_days,
                  SUM(CASE WHEN la.status = 'pending' THEN la.days ELSE 0 END) as pending_days
           FROM departments d
           LEFT JOIN users u ON d.id = u.department_id
           LEFT JOIN leave_applications la ON u.id = la.user_id AND la.start_date >= :start_date AND la.end_date <= :end_date
           WHERE u.role != 'hr_admin'";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    // Department head can only see their department
    if ($role == 'department_head') {
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);

        $sql .= " AND d.id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    } elseif ($department_filter != 'all') {
        $sql .= " AND d.id = :department_id";
        $params[':department_id'] = $department_filter;
    }

    $sql .= " GROUP BY d.id ORDER BY d.name ASC";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll();

    // Generate chart data for department comparison
    $chart_data = $report_data;
} elseif ($report_type == 'monthly_trends') {
    // Monthly Trends Report
    $sql = "SELECT 
              DATE_FORMAT(la.start_date, '%Y-%m') as month,
              COUNT(la.id) as total_applications,
              SUM(CASE WHEN la.status = 'approved' THEN la.days ELSE 0 END) as approved_days,
              SUM(CASE WHEN la.status = 'rejected' THEN la.days ELSE 0 END) as rejected_days,
              SUM(CASE WHEN la.status = 'pending' THEN la.days ELSE 0 END) as pending_days,
              COUNT(DISTINCT la.user_id) as unique_applicants
           FROM leave_applications la
           JOIN users u ON la.user_id = u.id
           WHERE la.start_date >= :start_date AND la.end_date <= :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);

        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }

    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND la.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }

    $sql .= " GROUP BY DATE_FORMAT(la.start_date, '%Y-%m') ORDER BY month ASC";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll();

    // Chart data is the same as report data for monthly trends
    $chart_data = $report_data;
} elseif ($report_type == 'leave_balance') {
    // Leave Balance Report
    $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_id, u.email,
                  d.name as department_name,
                  lt.name as leave_type,
                  lb.total_days as allocated_days,
                  lb.used_days,
                  (lb.total_days - lb.used_days) as remaining_days
           FROM users u
           JOIN departments d ON u.department_id = d.id
           JOIN leave_balances lb ON u.id = lb.user_id
           JOIN leave_types lt ON lb.leave_type_id = lt.id
           WHERE u.role != 'hr_admin'";

    $params = [];

    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);

        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }

    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND lb.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }

    $sql .= " ORDER BY d.name, u.last_name, u.first_name, lt.name";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll();

    // Generate chart data for leave balance by department
    $chart_sql = "SELECT 
                    d.name as department_name,
                    lt.name as leave_type,
                    SUM(lb.total_days) as total_allocated,
                    SUM(lb.used_days) as total_used,
                    SUM(lb.total_days - lb.used_days) as total_remaining
                 FROM departments d
                 JOIN users u ON d.id = u.department_id
                 JOIN leave_balances lb ON u.id = lb.user_id
                 JOIN leave_types lt ON lb.leave_type_id = lt.id
                 WHERE u.role != 'hr_admin'";

    $chart_params = [];

    // Add department filter for chart if specified
    if ($department_filter != 'all') {
        $chart_sql .= " AND u.department_id = :department_id";
        $chart_params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        $chart_sql .= " AND u.department_id = :department_id";
        $chart_params[':department_id'] = $user_dept['department_id'];
    }

    // Add leave type filter for chart if specified
    if ($leave_type_filter != 'all') {
        $chart_sql .= " AND lb.leave_type_id = :leave_type_id";
        $chart_params[':leave_type_id'] = $leave_type_filter;
    }

    $chart_sql .= " GROUP BY d.id, lt.id ORDER BY d.name, lt.name";

    $chart_stmt = $conn->prepare($chart_sql);
    foreach ($chart_params as $key => $value) {
        $chart_stmt->bindValue($key, $value);
    }
    $chart_stmt->execute();
    $chart_data = $chart_stmt->fetchAll();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>
                <i class="fas fa-chart-bar me-2"></i>Leave Reports
            </h2>
            <p class="text-muted">Generate and analyze leave data across departments and time periods</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (!empty($report_data)): ?>
                <button type="button" class="btn btn-success" id="exportExcelBtn">
                    <i class="fas fa-file-excel me-1"></i> Export to Excel
                </button>
                <button type="button" class="btn btn-danger ms-2" id="exportPdfBtn">
                    <i class="fas fa-file-pdf me-1"></i> Export to PDF
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['alert']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']);
        unset($_SESSION['alert_type']); ?>
    <?php endif; ?>

    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="reports.php" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="leave_utilization" <?php echo ($report_type == 'leave_utilization') ? 'selected' : ''; ?>>Leave Utilization</option>
                        <option value="department_summary" <?php echo ($report_type == 'department_summary') ? 'selected' : ''; ?>>Department Summary</option>
                        <option value="monthly_trends" <?php echo ($report_type == 'monthly_trends') ? 'selected' : ''; ?>>Monthly Trends</option>
                        <option value="leave_balance" <?php echo ($report_type == 'leave_balance') ? 'selected' : ''; ?>>Leave Balance</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id" <?php echo ($role == 'department_head') ? 'disabled' : ''; ?>>
                        <?php if ($role != 'department_head'): ?>
                            <option value="all" <?php echo ($department_filter == 'all') ? 'selected' : ''; ?>>All Departments</option>
                        <?php endif; ?>

                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3" id="leave_type_filter_container">
                    <label for="leave_type_id" class="form-label">Leave Type</label>
                    <select class="form-select" id="leave_type_id" name="leave_type_id">
                        <option value="all" <?php echo ($leave_type_filter == 'all') ? 'selected' : ''; ?>>All Leave Types</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($leave_type_filter == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3" id="status_filter_container">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>

                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i> Generate Report
                    </button>
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Results -->
    <div class="row">
        <!-- Charts Section -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Data Visualization
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($chart_data)): ?>
                        <?php if ($report_type == 'leave_utilization'): ?>
                            <canvas id="leaveTypeChart" height="250"></canvas>
                        <?php elseif ($report_type == 'department_summary'): ?>
                            <canvas id="departmentChart" height="250"></canvas>
                        <?php elseif ($report_type == 'monthly_trends'): ?>
                            <canvas id="trendsChart" height="250"></canvas>
                        <?php elseif ($report_type == 'leave_balance'): ?>
                            <canvas id="balanceChart" height="250"></canvas>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-area fa-3x text-muted mb-3"></i>
                            <p>No data available for visualization.</p>
                            <p class="text-muted">Try adjusting your filters to see results.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <?php if ($report_type == 'leave_utilization'): ?>
                            <i class="fas fa-table me-2"></i>Leave Utilization Report
                        <?php elseif ($report_type == 'department_summary'): ?>
                            <i class="fas fa-building me-2"></i>Department Summary Report
                        <?php elseif ($report_type == 'monthly_trends'): ?>
                            <i class="fas fa-chart-line me-2"></i>Monthly Trends Report
                        <?php elseif ($report_type == 'leave_balance'): ?>
                            <i class="fas fa-balance-scale me-2"></i>Leave Balance Report
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($report_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="reportTable">
                                <?php if ($report_type == 'leave_utilization'): ?>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Leave Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($row['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($row['leave_type_color']); ?>">
                                                        <?php echo htmlspecialchars($row['leave_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                                <td><?php echo $row['days']; ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($row['status']) {
                                                        case 'approved':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-secondary';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                <?php elseif ($report_type == 'department_summary'): ?>
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Total Staff</th>
                                            <th>Staff on Leave</th>
                                            <th>Total Applications</th>
                                            <th>Approved Days</th>
                                            <th>Rejected Days</th>
                                            <th>Pending Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                                <td><?php echo $row['total_staff']; ?></td>
                                                <td><?php echo $row['staff_on_leave']; ?></td>
                                                <td><?php echo $row['total_applications']; ?></td>
                                                <td><?php echo $row['approved_days']; ?></td>
                                                <td><?php echo $row['rejected_days']; ?></td>
                                                <td><?php echo $row['pending_days']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                <?php elseif ($report_type == 'monthly_trends'): ?>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Applications</th>
                                            <th>Unique Applicants</th>
                                            <th>Approved Days</th>
                                            <th>Rejected Days</th>
                                            <th>Pending Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                                <td><?php echo $row['total_applications']; ?></td>
                                                <td><?php echo $row['unique_applicants']; ?></td>
                                                <td><?php echo $row['approved_days']; ?></td>
                                                <td><?php echo $row['rejected_days']; ?></td>
                                                <td><?php echo $row['pending_days']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                <?php elseif ($report_type == 'leave_balance'): ?>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Leave Type</th>
                                            <th>Allocated Days</th>
                                            <th>Used Days</th>
                                            <th>Remaining Days</th>
                                            <th>Usage %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <?php
                                            $usage_percent = ($row['allocated_days'] > 0) ?
                                                round(($row['used_days'] / $row['allocated_days']) * 100, 1) : 0;

                                            $progress_class = '';
                                            if ($usage_percent < 50) {
                                                $progress_class = 'bg-success';
                                            } elseif ($usage_percent < 80) {
                                                $progress_class = 'bg-warning';
                                            } else {
                                                $progress_class = 'bg-danger';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($row['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                                <td><?php echo $row['allocated_days']; ?></td>
                                                <td><?php echo $row['used_days']; ?></td>
                                                <td><?php echo $row['remaining_days']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?php echo $progress_class; ?>"
                                                            role="progressbar"
                                                            style="width: <?php echo $usage_percent; ?>%"
                                                            aria-valuenow="<?php echo $usage_percent; ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100">
                                                            <?php echo $usage_percent; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <p>No data available for the selected filters.</p>
                            <p class="text-muted">Try adjusting your filters or date range to see results.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export to PDF Form (Hidden) -->
<form id="exportPdfForm" action="export_pdf.php" method="post" target="_blank" style="display: none;">
    <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
    <input type="hidden" name="department_id" value="<?php echo $department_filter; ?>">
    <input type="hidden" name="leave_type_id" value="<?php echo $leave_type_filter; ?>">
    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
</form>

<!-- Export to Excel Form (Hidden) -->
<form id="exportExcelForm" action="export_excel.php" method="post" target="_blank" style="display: none;">
    <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
    <input type="hidden" name="department_id" value="<?php echo $department_filter; ?>">
    <input type="hidden" name="leave_type_id" value="<?php echo $leave_type_filter; ?>">
    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
</form>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php include_once '../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- DataTables JS - Load after jQuery -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#reportTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "dom": '<"top"lf>rt<"bottom"ip>'
        });

        // Handle report type change to show/hide relevant filters
        document.getElementById('report_type').addEventListener('change', function() {
            const reportType = this.value;
            const leaveTypeFilter = document.getElementById('leave_type_filter_container');
            const statusFilter = document.getElementById('status_filter_container');

            if (reportType === 'department_summary') {
                leaveTypeFilter.style.display = 'none';
                statusFilter.style.display = 'none';
            } else {
                leaveTypeFilter.style.display = 'block';
                statusFilter.style.display = 'block';
            }
        });

        // Trigger change event to set initial state
        document.getElementById('report_type').dispatchEvent(new Event('change'));

        // Handle export buttons
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportExcelBtn = document.getElementById('exportExcelBtn');
        
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', function() {
                document.getElementById('exportPdfForm').submit();
            });
        }

        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', function() {
                document.getElementById('exportExcelForm').submit();
            });
        }

        <?php if (!empty($chart_data)): ?>
            // Initialize charts based on report type
            <?php if ($report_type == 'leave_utilization'): ?>
                // Leave Type Distribution Chart
                const leaveTypeCtx = document.getElementById('leaveTypeChart').getContext('2d');
                const leaveTypeData = {
                    labels: [<?php echo implode(', ', array_map(function ($item) {
                                    return "'" . addslashes($item['name']) . "'";
                                }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Days Taken',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['total_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: [<?php echo implode(', ', array_map(function ($item) {
                                                return "'" . $item['color'] . "'";
                                            }, $chart_data)); ?>],
                        borderWidth: 1
                    }]
                };

                new Chart(leaveTypeCtx, {
                    type: 'doughnut',
                    data: leaveTypeData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'Leave Type Distribution'
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'department_summary'): ?>
                // Department Summary Chart
                const deptCtx = document.getElementById('departmentChart').getContext('2d');
                const deptData = {
                    labels: [<?php echo implode(', ', array_map(function ($item) {
                                    return "'" . addslashes($item['department_name']) . "'";
                                }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Approved Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['approved_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Pending Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['pending_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Rejected Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['rejected_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }]
                };

                new Chart(deptCtx, {
                    type: 'bar',
                    data: deptData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Department Summary'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'monthly_trends'): ?>
                // Monthly Trends Chart
                const trendsCtx = document.getElementById('trendsChart').getContext('2d');
                const trendsData = {
                    labels: [<?php echo implode(', ', array_map(function ($item) {
                                    $month = date('M Y', strtotime($item['month'] . '-01'));
                                    return "'" . addslashes($month) . "'";
                                }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Applications',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['total_applications'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        type: 'line',
                        yAxisID: 'y'
                    }, {
                        label: 'Approved Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['approved_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }, {
                        label: 'Pending Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['pending_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }, {
                        label: 'Rejected Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['rejected_days'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                };

                new Chart(trendsCtx, {
                    type: 'bar',
                    data: trendsData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Monthly Trends'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'leave_balance'): ?>
                // Leave Balance Chart
                const balanceCtx = document.getElementById('balanceChart').getContext('2d');
                const balanceData = {
                    labels: [<?php
                                $labels = [];
                                foreach ($chart_data as $item) {
                                    $labels[] = "'" . addslashes($item['department_name'] . ' - ' . $item['leave_type']) . "'";
                                }
                                echo implode(', ', $labels);
                                ?>],
                    datasets: [{
                        label: 'Used Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['total_used'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Remaining Days',
                        data: [<?php echo implode(', ', array_map(function ($item) {
                                    return $item['total_remaining'];
                                }, $chart_data)); ?>],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                };

                new Chart(balanceCtx, {
                    type: 'bar',
                    data: balanceData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Leave Balance Overview'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            x: {
                                stacked: true
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true
                            }
                        }
                    }
                });
            <?php endif; ?>
        <?php endif; ?>
    });
</script>