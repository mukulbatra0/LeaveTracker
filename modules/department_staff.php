<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department_id = $_SESSION['department_id'];

// Debug: Check what role is in session
// Temporarily add debug info
if (!isset($_SESSION['debug_shown'])) {
    $_SESSION['debug_info'] = "Current role: " . $role . " | Department ID: " . $department_id;
    $_SESSION['debug_shown'] = true;
}

// Check if user has permission to access department staff
$allowed_roles = ['department_head', 'head_of_department', 'dean', 'principal', 'hr_admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page. Current role: " . $role;
    $_SESSION['alert_type'] = "danger";
    header('Location: /index.php');
    exit;
}

// Get department information
$dept_sql = "SELECT name FROM departments WHERE id = :dept_id";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
$dept_stmt->execute();
$department = $dept_stmt->fetch();

// Get department staff with their leave balances
$staff_sql = "SELECT u.id, u.first_name, u.last_name, u.employee_id, u.email, u.phone, 
                     u.position, u.status, u.created_at,
                     d.name as department_name
              FROM users u
              JOIN departments d ON u.department_id = d.id
              WHERE u.department_id = :dept_id 
              AND u.role IN ('staff', 'department_head', 'head_of_department')
              AND u.status = 'active'
              ORDER BY u.last_name, u.first_name";

$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll();

// Get leave balances for all staff
$current_year = date('Y');
$balances_sql = "SELECT lb.user_id, lt.name as leave_type, 
                        lb.total_days, lb.used_days, 
                        (lb.total_days - lb.used_days) as remaining_days
                 FROM leave_balances lb
                 JOIN leave_types lt ON lb.leave_type_id = lt.id
                 WHERE lb.year = :year
                 AND lb.user_id IN (SELECT id FROM users WHERE department_id = :dept_id)
                 ORDER BY lb.user_id, lt.name";

$balances_stmt = $conn->prepare($balances_sql);
$balances_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
$balances_stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
$balances_stmt->execute();
$leave_balances = $balances_stmt->fetchAll();

// Organize balances by user
$user_balances = [];
foreach ($leave_balances as $balance) {
    $user_balances[$balance['user_id']][] = $balance;
}

// Get current leave applications for staff
$current_leaves_sql = "SELECT la.user_id, la.start_date, la.end_date, la.status, lt.name as leave_type
                       FROM leave_applications la
                       JOIN leave_types lt ON la.leave_type_id = lt.id
                       JOIN users u ON la.user_id = u.id
                       WHERE u.department_id = :dept_id
                       AND la.start_date <= CURDATE() 
                       AND la.end_date >= CURDATE()
                       AND la.status = 'approved'";

$current_leaves_stmt = $conn->prepare($current_leaves_sql);
$current_leaves_stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
$current_leaves_stmt->execute();
$current_leaves = $current_leaves_stmt->fetchAll();

// Organize current leaves by user
$user_current_leaves = [];
foreach ($current_leaves as $leave) {
    $user_current_leaves[$leave['user_id']][] = $leave;
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Department Staff</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Department Staff</li>
    </ol>

    <?php if (isset($_SESSION['debug_info'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            Debug: <?php echo $_SESSION['debug_info']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['debug_info']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>

    <!-- Department Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h3><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($department['name'] ?? 'Department'); ?></h3>
            <p class="text-muted">Total Staff: <?php echo count($staff_members); ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="reports.php?department_id=<?php echo $department_id; ?>" class="btn btn-primary">
                <i class="fas fa-chart-bar me-1"></i> Department Reports
            </a>
        </div>
    </div>

    <!-- Staff Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($staff_members); ?></h4>
                    <p class="mb-0">Total Staff</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($user_current_leaves); ?></h4>
                    <p class="mb-0">Currently on Leave</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($staff_members) - count($user_current_leaves); ?></h4>
                    <p class="mb-0">Available Staff</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($leave_balances); ?></h4>
                    <p class="mb-0">Leave Allocations</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Staff Members</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($staff_members)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="staffTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Member Since</th>
                                <th>Current Status</th>
                                <th>Leave Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $staff): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></strong>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($staff['employee_id']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($staff['position'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div>
                                            <small><?php echo htmlspecialchars($staff['email']); ?></small>
                                            <?php if ($staff['phone']): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($staff['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($staff['created_at']): ?>
                                            <?php echo date('M d, Y', strtotime($staff['created_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($user_current_leaves[$staff['id']])): ?>
                                            <?php $current_leave = $user_current_leaves[$staff['id']][0]; ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-plane me-1"></i>On Leave
                                            </span>
                                            <small class="d-block text-muted">
                                                <?php echo htmlspecialchars($current_leave['leave_type']); ?>
                                                <br>Until <?php echo date('M d', strtotime($current_leave['end_date'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Available
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($user_balances[$staff['id']])): ?>
                                            <?php 
                                            $total_remaining = 0;
                                            foreach ($user_balances[$staff['id']] as $balance) {
                                                $total_remaining += $balance['remaining_days'];
                                            }
                                            ?>
                                            <span class="badge bg-primary"><?php echo number_format($total_remaining, 1); ?> days</span>
                                            <button class="btn btn-sm btn-outline-info ms-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#balanceModal<?php echo $staff['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">No data</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="my_leaves.php?user_id=<?php echo $staff['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Leave History">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <?php if (in_array($role, ['department_head', 'head_of_department']) || $role == 'hr_admin'): ?>
                                                <a href="leave_calendar.php?user_id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Calendar">
                                                    <i class="fas fa-calendar"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No Staff Members</h5>
                    <p class="text-muted">No staff members found in this department.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Leave Balance Modals -->
<?php foreach ($staff_members as $staff): ?>
    <?php if (isset($user_balances[$staff['id']])): ?>
        <div class="modal fade" id="balanceModal<?php echo $staff['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Leave Balance - <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Allocated</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_balances[$staff['id']] as $balance): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($balance['leave_type']); ?></td>
                                            <td><?php echo number_format($balance['total_days'], 1); ?></td>
                                            <td><?php echo number_format($balance['used_days'], 1); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $balance['remaining_days'] > 0 ? 'success' : 'warning'; ?>">
                                                    <?php echo number_format($balance['remaining_days'], 1); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php include '../includes/footer.php'; ?>

<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#staffTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Actions column
        ]
    });
});
</script>