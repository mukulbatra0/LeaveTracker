<?php
/**
 * Email Action Handler - One-click Approve/Reject from Email
 * 
 * This page processes leave approval/rejection via secure email tokens.
 * No login required - the token authenticates the action.
 * 
 * URL: email_action.php?token=xxx&action=approve|reject
 */

require_once '../config/db.php';
require_once '../classes/EmailNotification.php';
require_once '../classes/LeaveApplicationPDF.php';

$emailNotification = new EmailNotification($conn);
$pdfGenerator = new LeaveApplicationPDF($conn);

// Get parameters
$token = $_GET['token'] ?? null;
$action = $_GET['action'] ?? null;
$confirmed = $_GET['confirm'] ?? null;

// Validate basic params
if (!$token || !$action || !in_array($action, ['approve', 'reject'])) {
    showPage('error', 'Invalid Request', 'The link you clicked is invalid or incomplete.');
    exit;
}

// Look up the token
$token_sql = "SELECT la_app.*, lap.id as approval_id, lap.approver_id, lap.approver_level, 
              lap.status as approval_status, lap.token_used, lap.token_expires_at,
              u_applicant.first_name as app_first_name, u_applicant.last_name as app_last_name, 
              u_applicant.email as app_email, u_applicant.department_id,
              u_approver.first_name as approver_first_name, u_approver.last_name as approver_last_name,
              u_approver.role as approver_role,
              lt.name as leave_type_name, d.name as department_name
              FROM leave_approvals lap
              JOIN leave_applications la_app ON lap.leave_application_id = la_app.id
              JOIN users u_applicant ON la_app.user_id = u_applicant.id
              JOIN users u_approver ON lap.approver_id = u_approver.id
              JOIN leave_types lt ON la_app.leave_type_id = lt.id
              JOIN departments d ON u_applicant.department_id = d.id
              WHERE lap.email_token = :token";
$token_stmt = $conn->prepare($token_sql);
$token_stmt->bindParam(':token', $token, PDO::PARAM_STR);
$token_stmt->execute();
$data = $token_stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    showPage('error', 'Invalid Token', 'This approval link is invalid or has already been used.');
    exit;
}

// Check if token was already used
if ($data['token_used']) {
    showPage('warning', 'Already Processed', 'This leave application has already been ' . $data['approval_status'] . '. No further action is needed.');
    exit;
}

// Check if token has expired (48 hours)
if ($data['token_expires_at'] && strtotime($data['token_expires_at']) < time()) {
    showPage('warning', 'Link Expired', 'This approval link has expired. Please log in to the system to take action on this leave application.');
    exit;
}

// Check if application is still pending
if ($data['status'] !== 'pending') {
    showPage('warning', 'Already Processed', 'This leave application has already been ' . $data['status'] . '.');
    exit;
}

// If not confirmed yet, show confirmation page
if ($confirmed !== 'yes') {
    showConfirmationPage($data, $action, $token);
    exit;
}

// ====== PROCESS THE ACTION ======
$approver_id = $data['approver_id'];
$approval_id = $data['approval_id'];
$application_id = $data['id'];
$approver_role = $data['approver_role'];
$approver_level = $data['approver_level'];
$app_user_id = (int)$data['user_id'];
$comments = $_GET['comments'] ?? '';

$conn->beginTransaction();

try {
    if ($action === 'approve') {
        // Update approval record
        $update_sql = "UPDATE leave_approvals 
                       SET status = 'approved', comments = :comments, updated_at = NOW(), token_used = 1 
                       WHERE id = :approval_id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
        $update_stmt->bindParam(':approval_id', $approval_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Determine if final approval
        $approval_chain_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'default_approval_chain'";
        $approval_chain_stmt = $conn->prepare($approval_chain_sql);
        $approval_chain_stmt->execute();
        $approval_chain_result = $approval_chain_stmt->fetch();
        $approval_chain = $approval_chain_result ? $approval_chain_result['setting_value'] : 'hod,director';
        
        $is_final = false;
        if ($approver_level === 'admin' || $approver_level === 'director') {
            $is_final = true;
        } elseif ($approver_level === 'head_of_department' && $approval_chain === 'hod') {
            $is_final = true;
        }
        
        if ($is_final) {
            // Final approval
            $update_app_sql = "UPDATE leave_applications SET status = 'approved' WHERE id = :app_id";
            $update_app_stmt = $conn->prepare($update_app_sql);
            $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
            $update_app_stmt->execute();
            
            // Update leave balance
            $current_year = date('Y');
            $days = $data['days'];
            $leave_type_id = $data['leave_type_id'];
            $update_balance_sql = "UPDATE leave_balances 
                                   SET total_days = total_days - :days_sub, used_days = used_days + :days_add 
                                   WHERE user_id = :user_id AND leave_type_id = :lt_id AND year = :year";
            $update_balance_stmt = $conn->prepare($update_balance_sql);
            $update_balance_stmt->bindParam(':days_sub', $days, PDO::PARAM_STR);
            $update_balance_stmt->bindParam(':days_add', $days, PDO::PARAM_STR);
            $update_balance_stmt->bindParam(':user_id', $app_user_id, PDO::PARAM_INT);
            $update_balance_stmt->bindParam(':lt_id', $leave_type_id, PDO::PARAM_INT);
            $update_balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
            $update_balance_stmt->execute();
            
            // Notification
            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                VALUES (:uid, 'Leave Application Approved', 'Your leave application has been approved.', 'leave_application', :rid)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bindParam(':uid', $app_user_id, PDO::PARAM_INT);
            $notification_stmt->bindParam(':rid', $application_id, PDO::PARAM_INT);
            $notification_stmt->execute();
            
            // Send email with PDF
            $pdf_path = '';
            $pdf_filename = '';
            try {
                $pdf_path = $pdfGenerator->generatePDFToFile($application_id);
                $pdf_filename = $pdfGenerator->getFilename($application_id);
            } catch (Exception $e) {
                error_log("PDF generation failed in email_action: " . $e->getMessage());
            }
            
            $emailNotification->sendLeaveStatusNotification(
                $data['app_email'],
                $data['app_first_name'] . ' ' . $data['app_last_name'],
                'approved',
                $data['leave_type_name'],
                $data['start_date'],
                $data['end_date'],
                'Approved via email.',
                $pdf_path,
                $pdf_filename
            );
            
            $result_msg = 'Leave application has been <strong>approved</strong> successfully.';
        } else {
            // Intermediate approval - forward to director
            $director_sql = "SELECT id, email, first_name, last_name FROM users WHERE role = 'director' AND status = 'active' LIMIT 1";
            $director_stmt = $conn->prepare($director_sql);
            $director_stmt->execute();
            
            if ($director_stmt->rowCount() > 0) {
                $director = $director_stmt->fetch();
                
                // Generate token for director
                $director_token = bin2hex(random_bytes(32));
                $director_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $check_dir = "SELECT COUNT(*) FROM leave_approvals WHERE leave_application_id = :app_id AND approver_level = 'director'";
                $check_dir_stmt = $conn->prepare($check_dir);
                $check_dir_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                $check_dir_stmt->execute();
                
                if ($check_dir_stmt->fetchColumn() == 0) {
                    $dir_approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, email_token, token_expires_at) 
                                        VALUES (:app_id, :approver_id, 'director', 'pending', :token, :expires)";
                    $dir_stmt = $conn->prepare($dir_approval_sql);
                    $dir_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $dir_stmt->bindParam(':approver_id', $director['id'], PDO::PARAM_INT);
                    $dir_stmt->bindParam(':token', $director_token, PDO::PARAM_STR);
                    $dir_stmt->bindParam(':expires', $director_expires, PDO::PARAM_STR);
                    $dir_stmt->execute();
                }
                
                // Notify director
                $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                    VALUES (:uid, 'Leave Approval Required', 'A leave application requires your final approval.', 'leave_application', :rid)";
                $notif_stmt = $conn->prepare($notification_sql);
                $notif_stmt->bindParam(':uid', $director['id'], PDO::PARAM_INT);
                $notif_stmt->bindParam(':rid', $application_id, PDO::PARAM_INT);
                $notif_stmt->execute();
                
                // Send email to director with action buttons
                $emailNotification->sendLeaveApplicationNotification(
                    $director['email'],
                    $data['app_first_name'] . ' ' . $data['app_last_name'],
                    $data['leave_type_name'],
                    $data['start_date'],
                    $data['end_date'],
                    $application_id,
                    $director_token
                );
            }
            
            // Notify applicant
            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                VALUES (:uid, 'Leave Application Update', 'Your leave has been approved by HOD, awaiting Director approval.', 'leave_application', :rid)";
            $notif_stmt = $conn->prepare($notification_sql);
            $notif_stmt->bindParam(':uid', $app_user_id, PDO::PARAM_INT);
            $notif_stmt->bindParam(':rid', $application_id, PDO::PARAM_INT);
            $notif_stmt->execute();
            
            $result_msg = 'Leave application has been <strong>approved by HOD</strong> and forwarded to Director for final approval.';
        }
        
    } elseif ($action === 'reject') {
        // Update approval record
        $update_sql = "UPDATE leave_approvals 
                       SET status = 'rejected', comments = :comments, updated_at = NOW(), token_used = 1 
                       WHERE id = :approval_id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
        $update_stmt->bindParam(':approval_id', $approval_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Update application status
        $update_app_sql = "UPDATE leave_applications SET status = 'rejected' WHERE id = :app_id";
        $update_app_stmt = $conn->prepare($update_app_sql);
        $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
        $update_app_stmt->execute();
        
        // Notification
        $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                            VALUES (:uid, 'Leave Application Rejected', 'Your leave application has been rejected.', 'leave_application', :rid)";
        $notif_stmt = $conn->prepare($notification_sql);
        $notif_stmt->bindParam(':uid', $app_user_id, PDO::PARAM_INT);
        $notif_stmt->bindParam(':rid', $application_id, PDO::PARAM_INT);
        $notif_stmt->execute();
        
        // Send email with PDF
        $pdf_path = '';
        $pdf_filename = '';
        try {
            $pdf_path = $pdfGenerator->generatePDFToFile($application_id);
            $pdf_filename = $pdfGenerator->getFilename($application_id);
        } catch (Exception $e) {
            error_log("PDF generation failed in email_action (reject): " . $e->getMessage());
        }
        
        $emailNotification->sendLeaveStatusNotification(
            $data['app_email'],
            $data['app_first_name'] . ' ' . $data['app_last_name'],
            'rejected',
            $data['leave_type_name'],
            $data['start_date'],
            $data['end_date'],
            !empty($comments) ? $comments : 'Rejected via email.',
            $pdf_path,
            $pdf_filename
        );
        
        $result_msg = 'Leave application has been <strong>rejected</strong>.';
    }
    
    // Log the action
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Email Action';
        $action_type = $action . '_leave_via_email';
        $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                    VALUES (:uid, :action, 'leave_applications', :eid, :details, :ip, :ua)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bindParam(':uid', $approver_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':action', $action_type, PDO::PARAM_STR);
        $log_stmt->bindParam(':eid', $application_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':details', $comments, PDO::PARAM_STR);
        $log_stmt->bindParam(':ip', $ip_address, PDO::PARAM_STR);
        $log_stmt->bindParam(':ua', $user_agent, PDO::PARAM_STR);
        $log_stmt->execute();
    } catch (Exception $e) {
        error_log("Audit log failed in email_action: " . $e->getMessage());
    }
    
    $conn->commit();
    showPage('success', $action === 'approve' ? 'Leave Approved ✅' : 'Leave Rejected ❌', $result_msg);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Email action error: " . $e->getMessage());
    showPage('error', 'Error', 'An error occurred while processing the action. Please try again or log in to the system.');
}

// ====== HELPER FUNCTIONS ======

function showConfirmationPage($data, $action, $token) {
    $action_color = $action === 'approve' ? '#28a745' : '#dc3545';
    $action_icon = $action === 'approve' ? '✅' : '❌';
    $action_text = ucfirst($action);
    $applicant = htmlspecialchars($data['app_first_name'] . ' ' . $data['app_last_name']);
    $leave_type = htmlspecialchars($data['leave_type_name']);
    $department = htmlspecialchars($data['department_name']);
    $start = date('d M Y', strtotime($data['start_date']));
    $end = date('d M Y', strtotime($data['end_date']));
    $days = $data['days'];
    $reason = htmlspecialchars($data['reason']);
    $approver = htmlspecialchars($data['approver_first_name'] . ' ' . $data['approver_last_name']);
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $action_text . ' Leave Application - ELMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); max-width: 550px; width: 100%; overflow: hidden; }
        .card-header { background: ' . $action_color . '; color: #fff; padding: 30px; text-align: center; }
        .card-header h1 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .card-header p { opacity: 0.9; font-size: 14px; }
        .card-body { padding: 30px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { color: #666; font-size: 13px; font-weight: 500; }
        .detail-value { color: #333; font-size: 13px; font-weight: 600; text-align: right; max-width: 60%; }
        .reason-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 15px 0; }
        .reason-box label { font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .reason-box p { margin-top: 8px; color: #333; font-size: 13px; line-height: 1.5; }
        .approver-info { background: #e8f5e9; border-radius: 8px; padding: 12px 15px; margin: 15px 0; font-size: 13px; color: #2e7d32; }
        .btn-group { display: flex; gap: 12px; margin-top: 25px; }
        .btn { flex: 1; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; transition: transform 0.2s, box-shadow 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .btn-confirm { background: ' . $action_color . '; color: #fff; }
        .btn-cancel { background: #f8f9fa; color: #666; border: 1px solid #ddd; }
        .warning-note { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 20px; font-size: 12px; color: #856404; line-height: 1.5; }
        .institute { text-align: center; padding: 15px; color: #999; font-size: 11px; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h1>' . $action_icon . ' Confirm ' . $action_text . '</h1>
            <p>Please review the details before proceeding</p>
        </div>
        <div class="card-body">
            <div class="approver-info">
                👋 Hello <strong>' . $approver . '</strong>, you are about to <strong>' . strtolower($action_text) . '</strong> this leave application.
            </div>
            
            <div class="detail-row">
                <span class="detail-label">👤 Employee</span>
                <span class="detail-value">' . $applicant . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">🏢 Department</span>
                <span class="detail-value">' . $department . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">📋 Leave Type</span>
                <span class="detail-value">' . $leave_type . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">📅 From</span>
                <span class="detail-value">' . $start . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">📅 To</span>
                <span class="detail-value">' . $end . '</span>
            </div>
            <div class="detail-row" style="border: none;">
                <span class="detail-label">🔢 Days</span>
                <span class="detail-value">' . $days . ' day(s)</span>
            </div>
            
            <div class="reason-box">
                <label>📝 Reason for Leave</label>
                <p>' . $reason . '</p>
            </div>
            
            <div class="btn-group">
                <a href="email_action.php?token=' . urlencode($token) . '&action=' . $action . '&confirm=yes" class="btn btn-confirm">
                    ' . $action_icon . ' Yes, ' . $action_text . '
                </a>
                <a href="javascript:window.close();" class="btn btn-cancel" onclick="window.close(); return false;">
                    ← Cancel
                </a>
            </div>
            
            <div class="warning-note">
                ⚠️ This action cannot be undone. The applicant will be notified immediately via email.
            </div>
        </div>
        <div class="institute">
            The Technological Institute of Textile & Sciences, Bhiwani-127021<br/>
            ELMS - Leave Tracker System
        </div>
    </div>
</body>
</html>';
}

function showPage($type, $title, $message) {
    $colors = [
        'success' => ['bg' => '#28a745', 'icon' => '✅'],
        'error' => ['bg' => '#dc3545', 'icon' => '❌'],
        'warning' => ['bg' => '#ffc107', 'icon' => '⚠️'],
    ];
    $color = $colors[$type]['bg'] ?? '#1a73e8';
    $icon = $colors[$type]['icon'] ?? 'ℹ️';
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - ELMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); max-width: 480px; width: 100%; overflow: hidden; text-align: center; }
        .card-header { background: ' . $color . '; color: #fff; padding: 40px 30px; }
        .card-header .icon { font-size: 48px; margin-bottom: 15px; }
        .card-header h1 { font-size: 22px; font-weight: 700; }
        .card-body { padding: 30px; }
        .card-body p { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 25px; }
        .btn { display: inline-block; padding: 12px 30px; background: #1a73e8; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .institute { padding: 15px; color: #999; font-size: 11px; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="icon">' . $icon . '</div>
            <h1>' . htmlspecialchars($title) . '</h1>
        </div>
        <div class="card-body">
            <p>' . $message . '</p>
            <a href="../index.php" class="btn">🏠 Go to Dashboard</a>
        </div>
        <div class="institute">
            The Technological Institute of Textile & Sciences, Bhiwani-127021<br/>
            ELMS - Leave Tracker System
        </div>
    </div>
</body>
</html>';
}
?>
