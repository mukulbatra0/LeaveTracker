<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get all director leave applications pending admin approval (show all pending director applications)
$director_approvals_sql = "SELECT la.id, u.first_name, u.last_name, u.employee_id, u.email, lt.name as leave_type, 
                          la.start_date, la.end_date, la.days, la.reason, la.created_at, d.name as department,
                          lap.id as approval_id, lap.created_at as approval_created, lap.approver_id,
                          approver.first_name as assigned_admin_name
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

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="fas fa-crown me-2"></i>Director Leave Approvals
        <span class="badge bg-danger ms-2"><?php echo count($director_approvals); ?> pending</span>
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Director Leave Approvals</li>
    </ol>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>    <div cl
ass="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>High Priority - Director Leave Applications
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($director_approvals) == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>No Pending Director Approvals</h4>
                    <p class="text-muted">All director leave applications have been processed.</p>
                    <a href="../index.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Important:</strong> Director leave applications require immediate admin attention. 
                    Any admin can approve these applications regardless of original assignment. Please review and approve/reject these applications promptly.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Director Details</th>
                                <th>Leave Information</th>
                                <th>Period & Duration</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($director_approvals as $application): ?>
                                <tr class="table-warning">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-crown fa-2x text-warning"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($application['employee_id']); ?></small>
                                                <br><span class="badge bg-warning text-dark">Director</span>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($application['department']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary mb-1"><?php echo htmlspecialchars($application['leave_type']); ?></span>
                                        <br><span class="badge bg-info"><?php echo number_format($application['days'], 1); ?> days</span>
                                    </td>
                                    <td>
                                        <strong>From:</strong> <?php echo date('M d, Y', strtotime($application['start_date'])); ?>
                                        <br><strong>To:</strong> <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                                        <?php 
                                        $start = new DateTime($application['start_date']);
                                        $end = new DateTime($application['end_date']);
                                        $interval = $start->diff($end);
                                        if ($interval->days > 0) {
                                            echo '<br><small class="text-muted">(' . ($interval->days + 1) . ' calendar days)</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="reason-text" style="max-width: 200px;">
                                            <?php echo nl2br(htmlspecialchars(substr($application['reason'], 0, 100))); ?>
                                            <?php if (strlen($application['reason']) > 100): ?>
                                                <span class="text-muted">...</span>
                                                <br><button class="btn btn-sm btn-link p-0" onclick="showFullReason('<?php echo $application['id']; ?>')">Read More</button>
                                            <?php endif; ?>
                                        </div>
                                        <div id="fullReason<?php echo $application['id']; ?>" style="display: none;">
                                            <?php echo nl2br(htmlspecialchars($application['reason'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($application['created_at'])); ?>
                                        <br><small class="text-muted"><?php echo date('H:i', strtotime($application['created_at'])); ?></small>
                                        <?php 
                                        $created = new DateTime($application['created_at']);
                                        $now = new DateTime();
                                        $diff = $now->diff($created);
                                        if ($diff->days > 0) {
                                            echo '<br><small class="text-danger">(' . $diff->days . ' days ago)</small>';
                                        } elseif ($diff->h > 0) {
                                            echo '<br><small class="text-warning">(' . $diff->h . ' hours ago)</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical d-grid gap-1">
                                            <button class="btn btn-success btn-sm" onclick="approveDirectorLeave(<?php echo $application['id']; ?>)">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="rejectDirectorLeave(<?php echo $application['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="viewDirectorApplication(<?php echo $application['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>Details
                                            </button>
                                        </div>
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

<script>
function approveDirectorLeave(applicationId) {
    if(confirm('Are you sure you want to approve this Director leave application?')) {
        const comments = prompt('Add any comments (optional):');
        let url = '../modules/process_approval.php?action=approve&id=' + applicationId;
        if (comments && comments.trim() !== '') {
            url += '&reason=' + encodeURIComponent(comments);
        }
        window.location.href = url;
    }
}

function rejectDirectorLeave(applicationId) {
    const reason = prompt('Please provide a reason for rejection (required):');
    if(reason && reason.trim() !== '') {
        window.location.href = '../modules/process_approval.php?action=reject&id=' + applicationId + '&reason=' + encodeURIComponent(reason);
    } else if (reason !== null) {
        alert('Reason for rejection is required.');
    }
}

function viewDirectorApplication(applicationId) {
    window.location.href = '../modules/view_application.php?id=' + applicationId;
}

function showFullReason(applicationId) {
    const fullReason = document.getElementById('fullReason' + applicationId);
    const reasonText = fullReason.previousElementSibling;
    
    if (fullReason.style.display === 'none') {
        fullReason.style.display = 'block';
        reasonText.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>