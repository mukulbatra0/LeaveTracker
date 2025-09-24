<?php
class EmailNotification {
    private $conn;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        try {
            $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'from_email', 'from_name')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $this->smtp_host = $settings['smtp_host'] ?? 'localhost';
            $this->smtp_port = $settings['smtp_port'] ?? 587;
            $this->smtp_username = $settings['smtp_username'] ?? '';
            $this->smtp_password = $settings['smtp_password'] ?? '';
            $this->from_email = $settings['from_email'] ?? 'noreply@elms.local';
            $this->from_name = $settings['from_name'] ?? 'ELMS System';
        } catch (Exception $e) {
            error_log("Failed to load email settings: " . $e->getMessage());
        }
    }
    
    public function sendLeaveApplicationNotification($approver_email, $applicant_name, $leave_type, $start_date, $end_date, $application_id) {
        $subject = "Leave Approval Required - " . $applicant_name;
        $message = "
        <h3>Leave Approval Required</h3>
        <p>A new leave application requires your approval:</p>
        <ul>
            <li><strong>Employee:</strong> {$applicant_name}</li>
            <li><strong>Leave Type:</strong> {$leave_type}</li>
            <li><strong>Period:</strong> {$start_date} to {$end_date}</li>
            <li><strong>Application ID:</strong> #{$application_id}</li>
        </ul>
        <p>Please log in to the ELMS system to review and approve this application.</p>
        ";
        
        return $this->sendEmail($approver_email, $subject, $message);
    }
    
    public function sendLeaveStatusNotification($applicant_email, $applicant_name, $status, $leave_type, $start_date, $end_date, $comments = '') {
        $subject = "Leave Application " . ucfirst($status) . " - " . $leave_type;
        $status_message = $status === 'approved' ? 'has been approved' : 'has been rejected';
        
        $message = "
        <h3>Leave Application Update</h3>
        <p>Dear {$applicant_name},</p>
        <p>Your leave application {$status_message}:</p>
        <ul>
            <li><strong>Leave Type:</strong> {$leave_type}</li>
            <li><strong>Period:</strong> {$start_date} to {$end_date}</li>
            <li><strong>Status:</strong> " . ucfirst($status) . "</li>
        </ul>";
        
        if (!empty($comments)) {
            $message .= "<p><strong>Comments:</strong> {$comments}</p>";
        }
        
        $message .= "<p>Please log in to the ELMS system for more details.</p>";
        
        return $this->sendEmail($applicant_email, $subject, $message);
    }
    
    private function sendEmail($to, $subject, $message) {
        // Check if email notifications are enabled
        $stmt = $this->conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'enable_email_notifications'");
        $stmt->execute();
        $enabled = $stmt->fetchColumn();
        
        if (!$enabled) {
            return false;
        }
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>" . "\r\n";
        
        try {
            return mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }
}
?>