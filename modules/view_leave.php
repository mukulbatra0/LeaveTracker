<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

$leave_id = $_GET['id'] ?? null;
if (!$leave_id) {
    header('Location: index.php');
    exit;
}

// Get leave application details
$sql = "SELECT la.*, lt.name as leave_type_name, 
        u.first_name, u.last_name, u.email, u.employee_id,
        d.name as department_name
        FROM leave_applications la 
        JOIN leave_types lt ON la.leave_type_id = lt.id 
        JOIN users u ON la.user_id = u.id 
        JOIN departments d ON u.department_id = d.id
        WHERE la.id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
$stmt->execute();
$leave = $stmt->fetch();

if (!$leave) {
    $_SESSION['alert'] = "Leave application not found.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

include_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-eye me-2"></i>Leave Application Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Employee:</strong><br>
                            <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                            <?php if ($leave['employee_id']): ?>
                                <small class="text-muted">(ID: <?php echo htmlspecialchars($leave['employee_id']); ?>)</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Department:</strong><br>
                            <?php echo htmlspecialchars($leave['department_name']); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Leave Type:</strong><br>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($leave['leave_type_name']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
                            <?php 
                                $status_class = match($leave['status']) {
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'cancelled' => 'bg-secondary',
                                    default => 'bg-info'
                                };
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($leave['status']); ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Start Date:</strong><br>
                            <?php echo date('F d, Y', strtotime($leave['start_date'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>End Date:</strong><br>
                            <?php echo date('F d, Y', strtotime($leave['end_date'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Duration:</strong><br>
                            <?php echo $leave['days']; ?> day(s)
                        </div>
                    </div>
                    
                    <?php if ($leave['reason']): ?>
                    <div class="mb-3">
                        <strong>Reason:</strong><br>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Applied On:</strong><br>
                            <?php echo date('F d, Y g:i A', strtotime($leave['created_at'])); ?>
                        </div>
                        <?php if ($leave['updated_at']): ?>
                        <div class="col-md-6">
                            <strong>Last Updated:</strong><br>
                            <?php echo date('F d, Y g:i A', strtotime($leave['updated_at'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-end">
                        <button onclick="history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>