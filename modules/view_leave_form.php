<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';

$leave_id = $_GET['id'] ?? null;
if (!$leave_id) {
    header('Location: index.php');
    exit;
}

// Get leave application details with all related information
$sql = "SELECT la.*, lt.name as leave_type_name, 
        u.first_name, u.last_name, u.email, u.employee_id, u.phone, u.role,
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

// Check permissions
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Verify user has permission to view this application
$can_view = false;

// Staff can view their own applications
if ($leave['user_id'] == $user_id) {
    $can_view = true;
}

// Admin, Director can view all applications
if (in_array($role, ['admin', 'director'])) {
    $can_view = true;
}

// HOD can view applications from their department
if ($role == 'head_of_department') {
    $hod_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
    $hod_dept_stmt = $conn->prepare($hod_dept_sql);
    $hod_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $hod_dept_stmt->execute();
    $hod_dept = $hod_dept_stmt->fetchColumn();
    
    // Get applicant's department
    $app_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
    $app_dept_stmt = $conn->prepare($app_dept_sql);
    $app_dept_stmt->bindParam(':user_id', $leave['user_id'], PDO::PARAM_INT);
    $app_dept_stmt->execute();
    $app_dept = $app_dept_stmt->fetchColumn();
    
    if ($hod_dept == $app_dept) {
        $can_view = true;
    }
}

if (!$can_view) {
    $_SESSION['alert'] = "You don't have permission to view this leave application.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Get approval history
$approval_sql = "SELECT lap.*, u.first_name, u.last_name, lap.approver_level
                FROM leave_approvals lap
                JOIN users u ON lap.approver_id = u.id
                WHERE lap.leave_application_id = :application_id
                ORDER BY lap.created_at ASC";
$approval_stmt = $conn->prepare($approval_sql);
$approval_stmt->bindParam(':application_id', $leave_id, PDO::PARAM_INT);
$approval_stmt->execute();
$approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../includes/header.php';
?>

<style>
.leave-form-container {
    background: white;
    border: 2px solid #000;
    padding: 30px;
    margin: 20px auto;
    max-width: 900px;
}

.form-header {
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.form-header h3 {
    margin: 0;
    font-weight: bold;
    font-size: 18px;
}

.form-header h4 {
    margin: 5px 0;
    font-size: 14px;
}

.form-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.form-table td, .form-table th {
    border: 1px solid #000;
    padding: 8px;
    vertical-align: top;
}

.form-table th {
    background-color: #f0f0f0;
    font-weight: bold;
    text-align: left;
    width: 30%;
}

.signature-section {
    margin-top: 30px;
    display: flex;
    justify-content: space-between;
}

.signature-box {
    width: 45%;
    border: 1px solid #000;
    padding: 15px;
    min-height: 80px;
}

.signature-box h5 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: bold;
}

.approval-history {
    margin-top: 20px;
    border: 1px solid #ddd;
    padding: 15px;
    background-color: #f9f9f9;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .leave-form-container {
        border: 2px solid #000;
        page-break-after: always;
    }
}
</style>

<div class="container">
    <div class="row mb-3 no-print">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-file-alt me-2"></i>Leave Application Form</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <a href="download_leave_pdf.php?id=<?php echo $leave_id; ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-download me-1"></i>Download PDF
                    </a>
                    <button onclick="history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="leave-form-container">
        <div class="form-header">
            <h3>The Technological Institute of Textile & Sciences, Bhiwani-127021</h3>
            <h4>Application form for Comm Leave /CL/ EL/ On Duty/ Duty Leave</h4>
        </div>

        <table class="form-table">
            <tr>
                <th>NAME</th>
                <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
            </tr>
            <tr>
                <th>DESIGNATION</th>
                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $leave['role']))); ?></td>
            </tr>
            <tr>
                <th>DEPARTMENT</th>
                <td><?php echo htmlspecialchars($leave['department_name']); ?></td>
            </tr>
            <tr>
                <th>TYPE OF LEAVE REQUIRED</th>
                <td><strong><?php echo htmlspecialchars($leave['leave_type_name']); ?></strong></td>
            </tr>
            <tr>
                <th>FROM</th>
                <td><?php echo date('d/m/Y', strtotime($leave['start_date'])); ?></td>
            </tr>
            <tr>
                <th>TO</th>
                <td><?php echo date('d/m/Y', strtotime($leave['end_date'])); ?></td>
            </tr>
            <tr>
                <th>DATE</th>
                <td><?php echo date('d/m/Y', strtotime($leave['created_at'])); ?></td>
            </tr>
            <tr>
                <th>DEPARTMENT</th>
                <td><?php echo htmlspecialchars($leave['department_name']); ?></td>
            </tr>
            <tr>
                <th>NUMBER OF DAYS</th>
                <td>
                    <?php echo $leave['days']; ?> day(s)
                    <?php if ($leave['is_half_day']): ?>
                        <br><span class="badge bg-info">
                            <?php echo $leave['half_day_period'] == 'first_half' ? 'First Half (Morning)' : 'Second Half (Afternoon)'; ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>PURPOSE/REASON FOR LEAVE</th>
                <td><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></td>
            </tr>
            <?php if (!empty($leave['mode_of_transport'])): ?>
            <tr>
                <th>MODE OF TRANSPORT FOR OFFICIAL WORK, IF ANY</th>
                <td><?php echo nl2br(htmlspecialchars($leave['mode_of_transport'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($leave['work_adjustment'])): ?>
            <tr>
                <th>CLASS / WORK ADJUSTMENT DURING LEAVE PERIOD</th>
                <td><?php echo nl2br(htmlspecialchars($leave['work_adjustment'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($leave['attachment'])): ?>
            <tr>
                <th>ATTACHMENT</th>
                <td>
                    <i class="fas fa-paperclip"></i> 
                    <a href="../uploads/<?php echo htmlspecialchars($leave['attachment']); ?>" target="_blank" class="no-print">
                        View Attachment: <?php echo htmlspecialchars($leave['attachment']); ?>
                    </a>
                    <span class="d-print-inline d-none"><?php echo htmlspecialchars($leave['attachment']); ?> (Attached)</span>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>APPLICATION SIGNATURE</th>
                <td>
                    <div style="min-height: 40px;">
                        <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="approval-history">
            <h5><strong>Approval Status</strong></h5>
            <?php if (count($approvals) > 0): ?>
                <table class="form-table">
                    <thead>
                        <tr>
                            <th>Approver Level</th>
                            <th>Approver Name</th>
                            <th>Status</th>
                            <th>Comments</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $approval): ?>
                            <tr>
                                <td><?php echo ucwords(str_replace('_', ' ', $approval['approver_level'])); ?></td>
                                <td><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $approval['status'] == 'approved' ? 'success' : ($approval['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($approval['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($approval['comments'] ?? '-'); ?></td>
                                <td><?php echo $approval['status'] != 'pending' ? date('d/m/Y H:i', strtotime($approval['updated_at'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No approval actions yet</p>
            <?php endif; ?>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <h5>HEAD OF DEPARTMENT</h5>
                <p class="mb-1">Signature: _________________</p>
                <p class="mb-0">Date: _________________</p>
            </div>
            <div class="signature-box">
                <h5>DIRECTOR</h5>
                <p class="mb-1">Signature: _________________</p>
                <p class="mb-0">Date: _________________</p>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
