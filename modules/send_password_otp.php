<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Log the request
error_log("OTP Request received - Session ID: " . session_id());
error_log("User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("OTP Request failed: No user_id in session");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

require_once '../config/db.php';
require_once '../classes/EmailNotification.php';

$user_id = $_SESSION['user_id'];

try {
    // Get user details
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set OTP expiry (10 minutes from now)
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP in database
    $stmt = $conn->prepare("UPDATE users SET otp_code = :otp, otp_expiry = :expiry WHERE id = :user_id");
    $stmt->bindParam(':otp', $otp);
    $stmt->bindParam(':expiry', $expiry);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Send OTP via email
    $emailNotification = new EmailNotification($conn);
    $user_name = $user['first_name'] . ' ' . $user['last_name'];
    
    $subject = "Password Change OTP - LeaveTracker";
    $message = '
    <div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 0;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%); padding: 30px 30px 25px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 600;">🔐 Password Change Verification</h1>
            <p style="margin: 8px 0 0; color: #bbdefb; font-size: 14px;">Verify your identity to change your password</p>
        </div>
        
        <!-- Body -->
        <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
            
            <!-- Greeting -->
            <p style="margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;">
                Dear <strong>' . htmlspecialchars($user_name) . '</strong>,<br/><br/>
                You have requested to change your password. Please use the OTP below to verify and complete the password change.
            </p>
            
            <!-- OTP Display -->
            <div style="text-align: center; margin: 30px 0;">
                <p style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: 500;">Your One-Time Password (OTP)</p>
                <div style="display: inline-block; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 20px 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);">
                    <span style="font-size: 36px; font-weight: 700; color: #ffffff; letter-spacing: 12px; font-family: \'Courier New\', monospace;">' . $otp . '</span>
                </div>
            </div>
            
            <!-- Expiry Notice -->
            <div style="background-color: #fff3e0; border: 1px solid #ff9800; border-radius: 6px; padding: 15px; margin: 25px 0;">
                <p style="margin: 0; color: #e65100; font-size: 13px; line-height: 1.5;">
                    <strong>⏰ This OTP expires in 10 minutes.</strong><br/>
                    If your OTP has expired, please request a new one from the password change page.
                </p>
            </div>
            
            <!-- Security Notice -->
            <div style="background-color: #fce4ec; border: 1px solid #ef5350; border-radius: 6px; padding: 15px; margin-bottom: 10px;">
                <p style="margin: 0; color: #c62828; font-size: 13px; line-height: 1.5;">
                    <strong>🔒 Security Notice:</strong><br/>
                    • Do not share this OTP with anyone.<br/>
                    • If you did not request a password change, please contact your administrator immediately.
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
    
    $emailSent = $emailNotification->sendEmail($user['email'], $subject, $message);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent successfully to ' . $user['email'] . '. Please check your email.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send OTP email. Please try again or contact administrator.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("OTP Send Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while sending OTP. Please try again.'
    ]);
}
?>
