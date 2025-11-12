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

// Get application ID from URL
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$application_id) {
    $_SESSION['alert'] = "Invalid application ID.";
    $_SESSION['alert_type'] = "danger";
    header('Location: leave_history.php');
    exit;
}

// Get application details with user and department information
$sql = "SELECT la.*, 
               lt.name as leave_type_name, lt.color as leave_type_color,
               u.first_name, u.last_name, u.employee_id, u.email, u.phone,
               d.name as department_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        JOIN users u ON la.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE la.id = :application_id";

// Add permission check based on role
if ($role == 'staff') {
    $sql .= " AND la.user_id = :user_id";
} elseif ($role == 'head_of_department') {
    $sql .= " AND u.department_id = (SELECT department_id FROM users WHERE id = :user_id)";
}

$stmt = $conn->prepare($sql);
$stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
if ($role == 'staff' || $role == 'head_of_department') {
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
}

try {
    $stmt->execute();
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        // First check if the application exists at all
        $check_sql = "SELECT la.user_id, u.first_name, u.last_name FROM leave_applications la JOIN users u ON la.user_id = u.id WHERE la.id = :application_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$check_result) {
            $_SESSION['alert'] = "Leave application #$application_id does not exist.";
        } elseif ($role == 'staff' && $check_result['user_id'] != $user_id) {
            $_SESSION['alert'] = "You can only view your own leave applications. This application belongs to " . $check_result['first_name'] . " " . $check_result['last_name'] . ".";
        } else {
            $_SESSION['alert'] = "Application not found or you don't have permission to view it. Debug: Role=$role, UserID=$user_id, AppID=$application_id, OwnerID=" . ($check_result['user_id'] ?? 'N/A');
        }
        
        $_SESSION['alert_type'] = "danger";
        header('Location: leave_history.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in view_application.php: " . $e->getMessage());
    $_SESSION['alert'] = "Database error occurred: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header('Location: leave_history.php');
    exit;
}

// Get approval history
$approval_sql = "SELECT lap.*, u.first_name, u.last_name, lap.approver_level
                FROM leave_approvals lap
                JOIN users u ON lap.approver_id = u.id
                WHERE lap.leave_application_id = :application_id
                ORDER BY lap.created_at ASC";
$approval_stmt = $conn->prepare($approval_sql);
$approval_stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
$approval_stmt->execute();
$approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-file-alt me-2"></i>Leave Application Details</h2>
            <p class="text-muted">Application #<?php echo $application_id; ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <?php if ($role != 'staff'): ?>
                <a href="leave_approvals.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-list me-1"></i> All Applications
                </a>
            <?php else: ?>
                <a href="my_leaves.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-list me-1"></i> My Applications
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Application Details -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Application Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Employee:</strong><br>
                            <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($application['employee_id']); ?></small>
                        </div>
                        <div class="col-md-6">
                            <strong>Department:</strong><br>
                            <?php echo htmlspecialchars($application['department_name']); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Leave Type:</strong><br>
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($application['leave_type_color']); ?>">
                                <?php echo htmlspecialchars($application['leave_type_name']); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
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
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Start Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($application['start_date'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>End Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Total Days:</strong><br>
                            <?php echo number_format($application['days'], 1); ?> days
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>Reason:</strong><br>
                            <div class="border rounded p-2 bg-light">
                                <?php echo nl2br(htmlspecialchars($application['reason'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Applied On:</strong><br>
                            <?php echo date('M d, Y H:i', strtotime($application['created_at'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Applied By:</strong><br>
                            <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                        </div>
                    </div>

                    <?php if (!empty($application['attachment'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <strong>Attachment:</strong><br>
                            <div class="border rounded p-2 bg-light">
                                <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($application['attachment']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approval History -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Approval History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($approvals) > 0): ?>
                        <div class="timeline">
                            <?php foreach ($approvals as $approval): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="timeline-marker me-3">
                                            <?php if ($approval['status'] == 'approved'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php elseif ($approval['status'] == 'rejected'): ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-warning"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?>
                                                <small class="text-muted">(<?php echo ucwords(str_replace('_', ' ', $approval['approver_level'])); ?>)</small>
                                            </h6>
                                            <p class="mb-1">
                                                <span class="badge bg-<?php echo $approval['status'] == 'approved' ? 'success' : ($approval['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($approval['status']); ?>
                                                </span>
                                            </p>
                                            <?php if ($approval['comments']): ?>
                                                <p class="small text-muted mb-1">
                                                    "<?php echo htmlspecialchars($approval['comments']); ?>"
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($approval['status'] != 'pending'): ?>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y H:i', strtotime($approval['updated_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No approval actions yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions for Approvers -->
            <?php if ($role != 'staff' && $application['status'] == 'pending'): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="process_approval.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                    <input type="hidden" name="action" value="approve">
                    
                    <div class="mb-3">
                        <label for="approve_comments" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" id="approve_comments" name="comments" rows="3" placeholder="Add any comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="process_approval.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="mb-3">
                        <label for="reject_comments" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_comments" name="comments" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline-item {
    position: relative;
}

.timeline-marker {
    font-size: 1.2em;
}

.timeline-content {
    flex: 1;
}
</style>

<?php include_once '../includes/footer.php'; ?>