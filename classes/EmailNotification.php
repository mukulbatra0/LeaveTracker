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
    
    public function sendLeaveApplicationNotification($approver_email, $applicant_name, $leave_type, $start_date, $end_date, $application_id, $email_token = '') {
        $subject = "Leave Approval Required - " . $applicant_name;
        
        // Get APP_URL from environment
        $app_url = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : 'http://localhost/LeaveTracker';
        
        // Build direct links
        $dashboard_link = $app_url . '/index.php';
        $view_link = $app_url . '/modules/view_leave_form.php?id=' . $application_id;
        
        // Build action links - use token-based if token is available
        if (!empty($email_token)) {
            $approve_link = $app_url . '/modules/email_action.php?token=' . urlencode($email_token) . '&action=approve';
            $reject_link = $app_url . '/modules/email_action.php?token=' . urlencode($email_token) . '&action=reject';
            $action_note = '💡 <strong>Quick Action:</strong> Click the buttons above to approve or reject directly — no login required! You will see a confirmation page before the action is processed. Links expire in 48 hours.';
        } else {
            $approve_link = $view_link;
            $reject_link = $view_link;
            $action_note = '⚠️ <strong>Note:</strong> You will need to log in to the system to approve or reject this application. The buttons above will take you to the leave application page.';
        }
        
        // Format dates for display
        $start_display = date('d M Y', strtotime($start_date));
        $end_display = date('d M Y', strtotime($end_date));
        
        $message = '
        <div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 0;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%); padding: 30px 30px 25px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 600;">📋 Leave Approval Required</h1>
                <p style="margin: 8px 0 0; color: #bbdefb; font-size: 14px;">A new leave application needs your attention</p>
            </div>
            
            <!-- Body -->
            <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
                
                <!-- Greeting -->
                <p style="margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;">
                    Dear Approver,<br/>
                    <strong>' . htmlspecialchars($applicant_name) . '</strong> has submitted a leave application that requires your review and approval.
                </p>
                
                <!-- Leave Details Card -->
                <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="margin: 0 0 15px; color: #1a73e8; font-size: 16px; border-bottom: 2px solid #1a73e8; padding-bottom: 8px;">
                        📄 Leave Application Details
                    </h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; width: 40%; border-bottom: 1px solid #eee;">👤 Employee</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($applicant_name) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">📋 Leave Type</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($leave_type) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">📅 From</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . $start_display . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">📅 To</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . $end_display . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px;">🔢 Application ID</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600;">#' . $application_id . '</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Action Buttons -->
                <div style="text-align: center; margin: 30px 0;">
                    <p style="margin: 0 0 15px; color: #555; font-size: 14px; font-weight: 600;">Take Action on this Application:</p>
                    
                    <table style="margin: 0 auto;" cellpadding="0" cellspacing="0">
                        <tr>
                            <!-- Approve Button -->
                            <td style="padding: 0 8px;">
                                <a href="' . $approve_link . '" 
                                   style="display: inline-block; background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-size: 15px; font-weight: 600; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
                                    ✅ Approve
                                </a>
                            </td>
                            
                            <!-- Reject Button -->
                            <td style="padding: 0 8px;">
                                <a href="' . $reject_link . '" 
                                   style="display: inline-block; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-size: 15px; font-weight: 600; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);">
                                    ❌ Reject
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Direct Link -->
                <div style="text-align: center; margin: 20px 0 10px;">
                    <a href="' . $view_link . '" 
                       style="display: inline-block; background-color: #f8f9fa; color: #1a73e8; text-decoration: none; padding: 10px 25px; border-radius: 6px; font-size: 13px; font-weight: 500; border: 1px solid #1a73e8;">
                        📄 View Full Application
                    </a>
                </div>
                
                <!-- Info Note -->
                <div style="background-color: #e8f5e9; border: 1px solid #4caf50; border-radius: 6px; padding: 12px 16px; margin-top: 20px;">
                    <p style="margin: 0; color: #2e7d32; font-size: 12px; line-height: 1.5;">
                        ' . $action_note . '
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f1f3f5; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none;">
                <p style="margin: 0; color: #999; font-size: 11px; line-height: 1.5;">
                    This is an automated notification from ELMS (Leave Tracker System).<br/>
                    The Technological Institute of Textile & Sciences, Bhiwani-127021
                </p>
            </div>
        </div>';
        
        return $this->sendEmail($approver_email, $subject, $message);
    }
    
    public function sendLeaveStatusNotification($applicant_email, $applicant_name, $status, $leave_type, $start_date, $end_date, $comments = '', $pdf_attachment_path = '', $pdf_filename = '') {
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
        
        if (!empty($pdf_attachment_path)) {
            $message .= "<p><strong>📎 Attached:</strong> Your leave application form is attached as a PDF for your records.</p>";
        }
        
        $message .= "<p>Please log in to the ELMS system for more details.</p>";
        
        return $this->sendEmail($applicant_email, $subject, $message, $pdf_attachment_path, $pdf_filename);
    }
    
    public function sendWelcomeEmail($user_email, $user_name, $employee_id, $default_password, $role, $department_name = '') {
        $subject = "Welcome to LeaveTracker - Your Account Has Been Created";
        
        // Get APP_URL from environment
        $app_url = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : 'http://localhost/LeaveTracker';
        $login_link = $app_url . '/login.php';
        
        // Format role for display
        $role_display = ucwords(str_replace('_', ' ', $role));
        
        $message = '
        <div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 0;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); padding: 30px 30px 25px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">Welcome to LeaveTracker!</h1>
                <p style="margin: 8px 0 0; color: #d4edda; font-size: 14px;">Your account has been successfully created</p>
            </div>
            
            <!-- Body -->
            <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
                
                <!-- Greeting -->
                <p style="margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;">
                    Dear <strong>' . htmlspecialchars($user_name) . '</strong>,<br/><br/>
                    Welcome to the Employee Leave Management System! Your account has been created and you can now access the system to manage your leave applications.
                </p>
                
                <!-- Account Details Card -->
                <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="margin: 0 0 15px; color: #28a745; font-size: 16px; border-bottom: 2px solid #28a745; padding-bottom: 8px;">
                        Your Login Credentials
                    </h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; width: 40%; border-bottom: 1px solid #eee;">Name</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($user_name) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">Email</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($user_email) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">Employee ID</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($employee_id) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">Password</td>
                            <td style="padding: 8px 10px; color: #dc3545; font-size: 14px; font-weight: 700; font-family: monospace; border-bottom: 1px solid #eee;">' . htmlspecialchars($default_password) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px; border-bottom: 1px solid #eee;">Role</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600; border-bottom: 1px solid #eee;">' . htmlspecialchars($role_display) . '</td>
                        </tr>';
        
        if (!empty($department_name)) {
            $message .= '
                        <tr>
                            <td style="padding: 8px 10px; color: #666; font-size: 13px;">Department</td>
                            <td style="padding: 8px 10px; color: #333; font-size: 13px; font-weight: 600;">' . htmlspecialchars($department_name) . '</td>
                        </tr>';
        }
        
        $message .= '
                    </table>
                </div>
                
                <!-- Security Notice -->
                <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 25px;">
                    <p style="margin: 0; color: #856404; font-size: 13px; line-height: 1.5;">
                        <strong>Important Security Notice:</strong><br/>
                        For your security, please change your password immediately after your first login. Go to your profile settings and update your password.
                    </p>
                </div>
                
                <!-- Login Button -->
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $login_link . '" 
                       style="display: inline-block; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 16px; font-weight: 600; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);">
                        Login to LeaveTracker
                    </a>
                </div>
                
                <!-- Getting Started -->
                <div style="background-color: #e8f5e9; border: 1px solid #4caf50; border-radius: 6px; padding: 15px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px; color: #2e7d32; font-size: 14px;">Getting Started:</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #2e7d32; font-size: 12px; line-height: 1.6;">
                        <li>Log in using your email and the password provided above</li>
                        <li>Change your password in Profile Settings</li>
                        <li>View your leave balance on the dashboard</li>
                        <li>Submit leave applications when needed</li>
                        <li>Track your leave history and status</li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f1f3f5; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none;">
                <p style="margin: 0 0 5px; color: #666; font-size: 12px;">
                    If you have any questions or need assistance, please contact your system administrator.
                </p>
                <p style="margin: 0; color: #999; font-size: 11px; line-height: 1.5;">
                    This is an automated notification from LeaveTracker System.<br/>
                    The Technological Institute of Textile & Sciences, Bhiwani-127021
                </p>
            </div>
        </div>';
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send a password reset OTP email to the user
     * Called when admin initiates a password reset
     */
    public function sendPasswordResetOTP($user_email, $user_name, $otp_code) {
        $subject = "Password Reset OTP - LeaveTracker";
        
        $message = '
        <div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 0;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #e65100 0%, #bf360c 100%); padding: 30px 30px 25px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 600;">🔐 Password Reset OTP</h1>
                <p style="margin: 8px 0 0; color: #ffccbc; font-size: 14px;">Verify your identity to reset your password</p>
            </div>
            
            <!-- Body -->
            <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
                
                <!-- Greeting -->
                <p style="margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;">
                    Dear <strong>' . htmlspecialchars($user_name) . '</strong>,<br/><br/>
                    A password reset has been initiated for your account by the system administrator. Please use the OTP below to verify and complete the password reset.
                </p>
                
                <!-- OTP Display -->
                <div style="text-align: center; margin: 30px 0;">
                    <p style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: 500;">Your One-Time Password (OTP)</p>
                    <div style="display: inline-block; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 20px 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);">
                        <span style="font-size: 36px; font-weight: 700; color: #ffffff; letter-spacing: 12px; font-family: \'Courier New\', monospace;">' . $otp_code . '</span>
                    </div>
                </div>
                
                <!-- Expiry Notice -->
                <div style="background-color: #fff3e0; border: 1px solid #ff9800; border-radius: 6px; padding: 15px; margin: 25px 0;">
                    <p style="margin: 0; color: #e65100; font-size: 13px; line-height: 1.5;">
                        <strong>⏰ This OTP expires in 10 minutes.</strong><br/>
                        If your OTP has expired, please ask the admin to initiate a new password reset.
                    </p>
                </div>
                
                <!-- Security Notice -->
                <div style="background-color: #fce4ec; border: 1px solid #ef5350; border-radius: 6px; padding: 15px; margin-bottom: 10px;">
                    <p style="margin: 0; color: #c62828; font-size: 13px; line-height: 1.5;">
                        <strong>🔒 Security Notice:</strong><br/>
                        • Do not share this OTP with anyone.<br/>
                        • If you did not request a password reset, please contact your administrator immediately.
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f1f3f5; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none;">
                <p style="margin: 0; color: #999; font-size: 11px; line-height: 1.5;">
                    This is an automated notification from LeaveTracker System.<br/>
                    The Technological Institute of Textile &amp; Sciences, Bhiwani-127021
                </p>
            </div>
        </div>';
        
        return $this->sendEmail($user_email, $subject, $message);
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
    
    public function sendEmail($to, $subject, $message, $attachment_path = '', $attachment_name = '') {
        $this->last_error = '';
        
        // Check if email notifications are enabled
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_email_notifications'");
            $stmt->execute();
            $enabled = $stmt->fetchColumn();
            
            if (!$enabled || $enabled === '0' || $enabled === 'false') {
                error_log("Email notifications are disabled in system settings");
                $this->cleanupTempFile($attachment_path);
                return true; // Return true to not break the workflow
            }
        } catch (\Exception $e) {
            error_log("Could not check email notification settings: " . $e->getMessage());
            $this->cleanupTempFile($attachment_path);
            return true; // Return true to not break the workflow
        }
        
        // Check if we have valid SMTP configuration
        if (empty($this->smtp_host) || empty($this->smtp_username) || empty($this->smtp_password)) {
            $this->last_error = "SMTP not fully configured (missing host, username, or password)";
            error_log("Email not sent: " . $this->last_error);
            $this->cleanupTempFile($attachment_path);
            return true; // Return true to not break the workflow, but log the issue
        }
        
        // Check if we have valid email addresses
        if (empty($this->from_email) || empty($to)) {
            $this->last_error = "Invalid email configuration - missing from_email or recipient";
            error_log("Email not sent: " . $this->last_error);
            $this->cleanupTempFile($attachment_path);
            return true; // Return true to not break the workflow
        }
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->last_error = "PHPMailer not found. Please run: php install-phpmailer.php";
            error_log($this->last_error);
            $this->cleanupTempFile($attachment_path);
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
            $mail->Timeout = 30; // Increased timeout for attachments
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            // Add PDF attachment if provided
            if (!empty($attachment_path) && file_exists($attachment_path)) {
                $file_name = !empty($attachment_name) ? $attachment_name : basename($attachment_path);
                $mail->addAttachment($attachment_path, $file_name, 'base64', 'application/pdf');
                error_log("PDF attachment added to email: {$file_name}");
            }
            
            // Send email
            $result = $mail->send();
            
            if ($result) {
                error_log("Email sent successfully to {$to}" . (!empty($attachment_path) ? ' (with PDF attachment)' : ''));
            }
            
            // Cleanup temp file after sending
            $this->cleanupTempFile($attachment_path);
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer Error: " . $this->last_error);
            error_log("Exception: " . $e->getMessage());
            // Cleanup temp file even on failure
            $this->cleanupTempFile($attachment_path);
            // Return true for workflow emails so the leave application still goes through
            // The email failure is logged for admin to investigate
            return true;
        }
    }
    
    /**
     * Cleanup a temporary PDF file after email sending
     */
    private function cleanupTempFile($file_path) {
        if (!empty($file_path) && file_exists($file_path) && strpos($file_path, sys_get_temp_dir()) !== false) {
            @unlink($file_path);
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
