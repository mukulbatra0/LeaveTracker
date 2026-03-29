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
$success_message = '';
$error_message = '';

// Get user details for display
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle password change with OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // Log the form submission
    error_log("Password change form submitted for user ID: " . $user_id);
    error_log("POST data: " . print_r($_POST, true));
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $otp = trim($_POST['otp'] ?? ''); // Trim whitespace from OTP
    
    error_log("Current password length: " . strlen($current_password));
    error_log("New password length: " . strlen($new_password));
    error_log("OTP value: " . $otp);
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password) || empty($otp)) {
        $error_message = "All fields including OTP are required.";
        error_log("Validation failed: Missing fields");
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
        error_log("Validation failed: Passwords don't match");
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
        error_log("Validation failed: Password too short");
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Current password verification: " . (password_verify($current_password, $user['password']) ? 'PASS' : 'FAIL'));
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
            error_log("Current password verification failed");
        } else {
            // Verify OTP
            $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $otp_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("OTP from DB: " . ($otp_data['otp_code'] ?? 'NULL'));
            error_log("OTP from form: " . $otp);
            error_log("OTP expiry: " . ($otp_data['otp_expiry'] ?? 'NULL'));
            error_log("Current time: " . date('Y-m-d H:i:s'));
            
            if (empty($otp_data['otp_code'])) {
                $error_message = "No OTP found. Please request a new OTP.";
                error_log("OTP verification failed: No OTP in database");
            } elseif ($otp_data['otp_code'] !== $otp) {
                $error_message = "Invalid OTP. Please check and try again.";
                error_log("OTP verification failed: OTP mismatch");
            } elseif (strtotime($otp_data['otp_expiry']) < time()) {
                $error_message = "OTP has expired. Please request a new OTP.";
                error_log("OTP verification failed: OTP expired");
            } else {
                // Update password and clear OTP
                error_log("All validations passed, updating password...");
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = :password, otp_code = NULL, otp_expiry = NULL WHERE id = :user_id");
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                    error_log("Password updated successfully for user ID: " . $user_id);
                    
                    // Log the password change in audit logs if table exists
                    try {
                        $audit_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (:user_id, 'password_change', 'users', :entity_id, 'User changed their password via OTP verification')";
                        $audit_stmt = $conn->prepare($audit_sql);
                        $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $audit_stmt->bindParam(':entity_id', $user_id, PDO::PARAM_INT);
                        $audit_stmt->execute();
                    } catch (Exception $e) {
                        error_log("Could not log to audit_logs: " . $e->getMessage());
                    }
                } else {
                    $error_message = "Failed to update password. Please try again.";
                    error_log("Password update failed: " . print_r($update_stmt->errorInfo(), true));
                }
            }
        }
    }
}

$pageTitle = "Change Password";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>Change Password
                    </h5>
                    <a href="profile.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Profile
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8 col-lg-6">
                            <div class="mb-4">
                                <h6 class="text-muted">User Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?></p>
                                <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($user_details['email']); ?></p>
                            </div>

                            <form method="POST" action="" id="changePasswordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="otp" class="form-label">OTP (One-Time Password) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}" required>
                                        <button class="btn btn-primary" type="button" id="sendOtpBtn">
                                            <i class="fas fa-paper-plane me-1"></i>Send OTP
                                        </button>
                                    </div>
                                    <div class="form-text">An OTP will be sent to your registered email address.</div>
                                    <div id="otpStatus" class="mt-2"></div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Change Password
                                    </button>
                                    <a href="profile.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <div class="col-md-4 col-lg-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-shield-alt me-2 text-primary"></i>Password Security Tips
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Use at least 6 characters
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Include uppercase and lowercase letters
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Include numbers and special characters
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Avoid using personal information
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Don't reuse old passwords
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card bg-info bg-opacity-10">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-2 text-info"></i>OTP Verification
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-envelope text-info me-2"></i>
                                            OTP will be sent to your email
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-info me-2"></i>
                                            OTP expires in 10 minutes
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-lock text-info me-2"></i>
                                            Required for password change
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle functionality
    function togglePasswordVisibility(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        const icon = button.querySelector('i');
        
        button.addEventListener('click', function() {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }
    
    // Apply to all password fields
    togglePasswordVisibility('current_password', 'toggleCurrentPassword');
    togglePasswordVisibility('new_password', 'toggleNewPassword');
    togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
    
    // Form validation
    const form = document.getElementById('changePasswordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    
    form.addEventListener('submit', function(e) {
        validatePasswords();
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    // Send OTP functionality
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const otpStatus = document.getElementById('otpStatus');
    let otpCooldown = false;

    sendOtpBtn.addEventListener('click', function() {
        console.log('Send OTP button clicked'); // Debug log
        
        if (otpCooldown) {
            console.log('OTP cooldown active, ignoring click');
            return;
        }

        sendOtpBtn.disabled = true;
        sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
        otpStatus.innerHTML = '';

        console.log('Sending OTP request to: send_password_otp.php'); // Debug log

        fetch('send_password_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            console.log('Response headers:', response.headers); // Debug log
            
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data); // Debug log
            
            if (data.success) {
                otpStatus.innerHTML = '<div class="alert alert-success alert-sm py-2"><i class="fas fa-check-circle me-1"></i>' + data.message + '</div>';
                
                // Start cooldown timer (60 seconds)
                otpCooldown = true;
                let countdown = 60;
                const timer = setInterval(function() {
                    countdown--;
                    sendOtpBtn.innerHTML = '<i class="fas fa-clock me-1"></i>Resend in ' + countdown + 's';
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        otpCooldown = false;
                        sendOtpBtn.disabled = false;
                        sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send OTP';
                    }
                }, 1000);
            } else {
                otpStatus.innerHTML = '<div class="alert alert-danger alert-sm py-2"><i class="fas fa-exclamation-circle me-1"></i>' + (data.message || 'Unknown error occurred') + '</div>';
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send OTP';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error); // Debug log
            otpStatus.innerHTML = '<div class="alert alert-danger alert-sm py-2"><i class="fas fa-exclamation-circle me-1"></i>Failed to send OTP: ' + error.message + '. Check browser console for details.</div>';
            sendOtpBtn.disabled = false;
            sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send OTP';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
