<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is HOD, Director, or Admin
if (!in_array($role, ['head_of_department', 'director', 'admin'])) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Get filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_leave_type = isset($_GET['leave_type']) ? $_GET['leave_type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get leave types for filter
$leave_types_sql = "SELECT id, name FROM leave_types ORDER BY name";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->execute();
$leave_types = $leave_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query
$sql = "SELECT la.*, 
        lt.name as leave_type_name,
        u.first_name, u.last_name, u.employee_id, u.email,
        d.name as department_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        JOIN users u ON la.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE 1=1";

$params = [];

// Role-based filtering
if ($role == 'head_of_department') {
    // Get the HOD's department first
    $dept_check_sql = "SELECT department_id FROM users WHERE id = ?";
    $dept_check_stmt = $conn->prepare($dept_check_sql);
    $dept_check_stmt->execute([$user_id]);
    $hod_dept_id = $dept_check_stmt->fetchColumn();
    
    if ($hod_dept_id) {
        $sql .= " AND u.department_id = :hod_dept_id";
        $params[':hod_dept_id'] = $hod_dept_id;
    }
}

// Status filter
if (!empty($filter_status)) {
    $sql .= " AND la.status = :status";
    $params[':status'] = $filter_status;
}

// Leave type filter
if (!empty($filter_leave_type)) {
    $sql .= " AND la.leave_type_id = :leave_type_id";
    $params[':leave_type_id'] = $filter_leave_type;
}

// Search filter
if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.employee_id LIKE :search3)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
}

$sql .= " ORDER BY la.created_at DESC";

// Debug: Uncomment to see SQL and params
// echo "<pre>SQL: " . $sql . "\n\nParams: "; print_r($params); echo "</pre>"; exit;

$stmt = $conn->prepare($sql);
// Bind parameters explicitly with correct types
foreach ($params as $key => $value) {
    if ($key === ':hod_dept_id' || $key === ':leave_type_id') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department name for HOD
$department_name = '';
if ($role == 'head_of_department') {
    $dept_sql = "SELECT d.name FROM departments d 
                 JOIN users u ON d.id = u.department_id 
                 WHERE u.id = :user_id";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $dept_stmt->execute();
    $department_name = $dept_stmt->fetchColumn();
}

include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-list me-2"></i>
                <?php echo $role == 'head_of_department' ? 'Department' : 'All'; ?> Leave Applications
            </h2>
            <?php if ($role == 'head_of_department' && $department_name): ?>
                <p class="text-muted">Department: <?php echo htmlspecialchars($department_name); ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
            <button onclick="history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="leave_type" class="form-label">Leave Type</label>
                    <select name="leave_type" id="leave_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $filter_leave_type == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Employee</label>
                    <input type="text" name="search" id="search" class="form-control" 
                           placeholder="Name or Employee ID" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i>Applications 
                <span class="badge bg-primary"><?php echo count($applications); ?> total</span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($applications) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($app['employee_id']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($app['leave_type_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo date('M d, Y', strtotime($app['start_date']));
                                        if ($app['start_date'] != $app['end_date']) {
                                            echo '<br>to ' . date('M d, Y', strtotime($app['end_date']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $app['days']; ?>
                                        <?php if ($app['is_half_day']): ?>
                                            <br><small class="badge bg-info">
                                                <?php echo $app['half_day_period'] == 'first_half' ? '1st Half' : '2nd Half'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = match($app['status']) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'cancelled' => 'secondary',
                                            default => 'warning'
                                        };
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_leave_form.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-info" target="_blank" title="View Form">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                            <a href="download_leave_pdf.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-success" target="_blank" title="Download PDF">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="view_application.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5>No Applications Found</h5>
                    <p class="text-muted">No leave applications match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
