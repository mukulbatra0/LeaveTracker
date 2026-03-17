<?php
// Load PHPMailer via Composer autoloader
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotification {
    private $conn;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $from_email;
    private $from_name;
    private $last_error = '';
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->loadSettings();
    }
    
    /**
     * Get the last error message (useful for debugging)
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    private function loadSettings() {
        // First, try to load from .env file
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        // Load from .env file (priority) or database (fallback)
        $this->smtp_host = !empty($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : null;
        $this->smtp_port = !empty($_ENV['SMTP_PORT']) ? $_ENV['SMTP_PORT'] : null;
        $this->smtp_username = !empty($_ENV['SMTP_USER']) ? $_ENV['SMTP_USER'] : null;
        $this->smtp_password = !empty($_ENV['SMTP_PASS']) ? $_ENV['SMTP_PASS'] : null;
        $this->smtp_encryption = !empty($_ENV['SMTP_ENCRYPTION']) ? $_ENV['SMTP_ENCRYPTION'] : null;
        $this->from_email = !empty($_ENV['SMTP_FROM']) ? $_ENV['SMTP_FROM'] : null;
        $this->from_name = !empty($_ENV['SMTP_FROM_NAME']) ? $_ENV['SMTP_FROM_NAME'] : null;
        
        // If not found in .env, try database
        if (empty($this->smtp_host)) {
            try {
                $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_email', 'from_name')");
                $stmt->execute();
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $this->smtp_host = !empty($settings['smtp_host']) ? $settings['smtp_host'] : '';
                $this->smtp_port = $this->smtp_port ?? (!empty($settings['smtp_port']) ? $settings['smtp_port'] : 587);
                $this->smtp_username = !empty($settings['smtp_username']) ? $settings['smtp_username'] : '';
                $this->smtp_password = !empty($settings['smtp_password']) ? $settings['smtp_password'] : '';
                $this->smtp_encryption = $this->smtp_encryption ?? (!empty($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls');
                $this->from_email = $this->from_email ?? (!empty($settings['from_email']) ? $settings['from_email'] : '');
                $this->from_name = $this->from_name ?? (!empty($settings['from_name']) ? $settings['from_name'] : 'ELMS System');
            } catch (\Exception $e) {
                error_log("Failed to load email settings from database: " . $e->getMessage());
            }
        }
        
        // Set defaults if still empty - use smtp_username as from_email if not set
        $this->smtp_host = $this->smtp_host ?: '';
        $this->smtp_port = $this->smtp_port ?: 587;
        $this->smtp_username = $this->smtp_username ?: '';
        $this->smtp_password = $this->smtp_password ?: '';
        $this->smtp_encryption = $this->smtp_encryption ?: 'tls';
        // Use smtp_username as from_email if from_email is not configured (common for Gmail)
        $this->from_email = $this->from_email ?: ($this->smtp_username ?: 'noreply@elms.local');
        $this->from_name = $this->from_name ?: 'ELMS System';
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
    
    /**
     * Send a test email with detailed debug output
     * Returns an array with 'success', 'message', and 'debug' keys
     */
    public function sendTestEmail($to) {
        $debug_output = [];
        $debug_output[] = "=== EMAIL DEBUG START ===";
        $debug_output[] = "To: {$to}";
        $debug_output[] = "SMTP Host: {$this->smtp_host}";
        $debug_output[] = "SMTP Port: {$this->smtp_port}";
        $debug_output[] = "SMTP Username: {$this->smtp_username}";
        $debug_output[] = "SMTP Password: " . (!empty($this->smtp_password) ? str_repeat('*', min(strlen($this->smtp_password), 4)) . '...' : 'NOT SET');
        $debug_output[] = "SMTP Encryption: {$this->smtp_encryption}";
        $debug_output[] = "From Email: {$this->from_email}";
        $debug_output[] = "From Name: {$this->from_name}";
        
        // Check prerequisites
        if (empty($this->smtp_host)) {
            $debug_output[] = "ERROR: SMTP Host is not configured!";
            return ['success' => false, 'message' => 'SMTP Host is not configured. Please set it in System Configuration or .env file.', 'debug' => implode("\n", $debug_output)];
        }
        
        if (empty($this->smtp_username)) {
            $debug_output[] = "ERROR: SMTP Username is not configured!";
            return ['success' => false, 'message' => 'SMTP Username is not configured. Please set it in System Configuration or .env file.', 'debug' => implode("\n", $debug_output)];
        }
        
        if (empty($this->smtp_password)) {
            $debug_output[] = "ERROR: SMTP Password is not configured!";
            return ['success' => false, 'message' => 'SMTP Password is not configured. For Gmail, you need an App Password (not your regular password).', 'debug' => implode("\n", $debug_output)];
        }
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $debug_output[] = "ERROR: PHPMailer class not found!";
            $debug_output[] = "Autoload path: " . __DIR__ . '/../vendor/autoload.php';
            $debug_output[] = "Autoload exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO');
            return ['success' => false, 'message' => 'PHPMailer is not installed. Please run install-phpmailer.php first.', 'debug' => implode("\n", $debug_output)];
        }
        
        $debug_output[] = "PHPMailer: Found ✓";
        
        try {
            $mail = new PHPMailer(true);
            
            // Enable verbose debug output
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $smtp_debug = '';
            $mail->Debugoutput = function($str, $level) use (&$smtp_debug) {
                $smtp_debug .= trim($str) . "\n";
            };
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->Port = (int)$this->smtp_port;
            
            // Set encryption
            if ($this->smtp_encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtp_encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Disable SSL verification for development
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set timeout
            $mail->Timeout = 15;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'ELMS Test Email - ' . date('Y-m-d H:i:s');
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #28a745;">✅ Email Configuration Working!</h2>
                    <p>This is a test email from your ELMS (Leave Tracker) system.</p>
                    <p>If you received this email, your email notification system is properly configured.</p>
                    <hr>
                    <p style="color: #6c757d; font-size: 12px;">
                        Sent at: ' . date('Y-m-d H:i:s') . '<br>
                        SMTP Host: ' . htmlspecialchars($this->smtp_host) . '<br>
                        From: ' . htmlspecialchars($this->from_email) . '
                    </p>
                </div>';
            $mail->AltBody = 'ELMS Test Email - Your email configuration is working! Sent at: ' . date('Y-m-d H:i:s');
            
            $debug_output[] = "Attempting to send...";
            $result = $mail->send();
            
            $debug_output[] = "Send result: " . ($result ? 'SUCCESS' : 'FAILED');
            $debug_output[] = "=== SMTP DEBUG LOG ===";
            $debug_output[] = $smtp_debug;
            
            return [
                'success' => true, 
                'message' => "Test email sent successfully to {$to}! Check your inbox (and spam folder).", 
                'debug' => implode("\n", $debug_output)
            ];
            
        } catch (Exception $e) {
            $debug_output[] = "EXCEPTION: " . $e->getMessage();
            if (isset($mail)) {
                $debug_output[] = "PHPMailer Error: " . $mail->ErrorInfo;
            }
            $debug_output[] = "=== SMTP DEBUG LOG ===";
            $debug_output[] = $smtp_debug ?? '';
            
            // Provide helpful error messages
            $error_msg = $e->getMessage();
            $helpful_message = $error_msg;
            
            if (stripos($error_msg, 'authentication') !== false || stripos($error_msg, 'credentials') !== false) {
                $helpful_message = "SMTP Authentication failed. If using Gmail, make sure you're using an App Password (not your regular password). Go to Google Account → Security → 2-Step Verification → App Passwords.";
            } elseif (stripos($error_msg, 'connect') !== false || stripos($error_msg, 'timed out') !== false) {
                $helpful_message = "Could not connect to SMTP server '{$this->smtp_host}:{$this->smtp_port}'. Check that the host and port are correct, and that your firewall/antivirus is not blocking the connection.";
            } elseif (stripos($error_msg, 'ssl') !== false || stripos($error_msg, 'tls') !== false) {
                $helpful_message = "SSL/TLS error. Try changing the encryption setting. For Gmail: use TLS with port 587, or SSL with port 465.";
            }
            
            return [
                'success' => false, 
                'message' => $helpful_message, 
                'debug' => implode("\n", $debug_output)
            ];
        }
    }
    
    private function sendEmail($to, $subject, $message) {
        $this->last_error = '';
        
        // Check if email notifications are enabled
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_email_notifications'");
            $stmt->execute();
            $enabled = $stmt->fetchColumn();
            
            if (!$enabled || $enabled === '0' || $enabled === 'false') {
                error_log("Email notifications are disabled in system settings");
                return true; // Return true to not break the workflow
            }
        } catch (\Exception $e) {
            error_log("Could not check email notification settings: " . $e->getMessage());
            return true; // Return true to not break the workflow
        }
        
        // Check if we have valid SMTP configuration
        if (empty($this->smtp_host) || empty($this->smtp_username) || empty($this->smtp_password)) {
            $this->last_error = "SMTP not fully configured (missing host, username, or password)";
            error_log("Email not sent: " . $this->last_error);
            return true; // Return true to not break the workflow, but log the issue
        }
        
        // Check if we have valid email addresses
        if (empty($this->from_email) || empty($to)) {
            $this->last_error = "Invalid email configuration - missing from_email or recipient";
            error_log("Email not sent: " . $this->last_error);
            return true; // Return true to not break the workflow
        }
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->last_error = "PHPMailer not found. Please run: php install-phpmailer.php";
            error_log($this->last_error);
            return $this->sendEmailBasic($to, $subject, $message);
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->Port = (int)$this->smtp_port;
            
            // Set encryption properly
            if ($this->smtp_encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtp_encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Disable SSL verification for local/development environments
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set timeout
            $mail->Timeout = 15;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            // Send email
            $result = $mail->send();
            
            if ($result) {
                error_log("Email sent successfully to {$to}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer Error: " . $this->last_error);
            error_log("Exception: " . $e->getMessage());
            // Return true for workflow emails so the leave application still goes through
            // The email failure is logged for admin to investigate
            return true;
        }
    }
    
    /**
     * Fallback method using basic PHP mail() function
     * This won't work with SMTP authentication but prevents errors
     */
    private function sendEmailBasic($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>" . "\r\n";
        
        try {
            $result = @mail($to, $subject, $message, $headers);
            
            if (!$result) {
                error_log("Failed to send email to {$to} using mail() function. SMTP configuration required.");
            }
            
            return true; // Always return true to not break the workflow
        } catch (\Exception $e) {
            error_log("Email sending exception: " . $e->getMessage());
            return true;
        }
    }
}
?>
