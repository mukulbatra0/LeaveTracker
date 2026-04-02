<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is an admin
if ($role != 'admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Check if we have reset session data
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_user_name'])) {
    $_SESSION['alert'] = "No password reset in progress. Please initiate a reset from the Users page.";
    $_SESSION['alert_type'] = "warning";
    header('Location: users.php');
    exit;
}

$reset_user_id = $_SESSION['reset_user_id'];
$reset_user_name = $_SESSION['reset_user_name'];
$reset_user_email = $_SESSION['reset_user_email'] ?? '';

// Mask the email for display
$email_parts = explode('@', $reset_user_email);
if (count($email_parts) == 2) {
    $name_part = $email_parts[0];
    $domain_part = $email_parts[1];
    $masked_name = substr($name_part, 0, 2) . str_repeat('*', max(strlen($name_part) - 2, 0));
    $masked_email = $masked_name . '@' . $domain_part;
} else {
    $masked_email = '***';
}

$error_message = '';
$success_message = '';
$otp_expired = false;

// Check if there's a valid OTP pending - using unified users table
$check_otp_sql = "SELECT otp_code, otp_expiry FROM users WHERE id = :user_id";
$check_otp_stmt = $conn->prepare($check_otp_sql);
$check_otp_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
$check_otp_stmt->execute();
$pending_otp = $check_otp_stmt->fetch();

error_log("Checking OTP for user ID: " . $reset_user_id);
error_log("OTP in DB: " . ($pending_otp['otp_code'] ?? 'NULL'));
error_log("OTP expiry: " . ($pending_otp['otp_expiry'] ?? 'NULL'));

if (!$pending_otp || empty($pending_otp['otp_code'])) {
    $_SESSION['alert'] = "No OTP found. Please initiate a new password reset.";
    $_SESSION['alert_type'] = "warning";
    unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name'], $_SESSION['reset_user_email'], $_SESSION['reset_by_admin']);
    header('Location: users.php');
    exit;
}

// Check if OTP has expired
if (strtotime($pending_otp['otp_expiry']) < time()) {
    $otp_expired = true;
    error_log("OTP expired for user ID: " . $reset_user_id);
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp_code'] ?? '');
        
        error_log("OTP verification attempt for user ID: " . $reset_user_id);
        error_log("Entered OTP: " . $entered_otp);
        error_log("Expected OTP: " . $pending_otp['otp_code']);
        
        if (empty($entered_otp)) {
            $error_message = "Please enter the OTP code.";
            error_log("OTP verification failed: Empty OTP");
        } elseif ($otp_expired) {
            $error_message = "This OTP has expired. Please go back and initiate a new password reset.";
            error_log("OTP verification failed: Expired");
        } else {
            // Verify OTP - trim and cast both values to handle type mismatches
            $db_otp = trim((string)($pending_otp['otp_code'] ?? ''));
            $entered_otp_clean = trim((string)$entered_otp);
            
            error_log("Comparing DB OTP: '{$db_otp}' with Entered: '{$entered_otp_clean}'");
            
            if ($entered_otp_clean == $db_otp) {
                // OTP is correct! Reset the password
                error_log("OTP verified successfully for user ID: " . $reset_user_id);
                
                try {
                    $conn->beginTransaction();
                    
                    // Get user's first name to generate default password
                    $user_info_sql = "SELECT first_name, last_name FROM users WHERE id = :user_id";
                    $user_info_stmt = $conn->prepare($user_info_sql);
                    $user_info_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
                    $user_info_stmt->execute();
                    $user_info = $user_info_stmt->fetch();
                    
                    // Generate default password: First 3 letters of first name in CAPS + @123
                    $name_prefix = strtoupper(substr($user_info['first_name'], 0, 3));
                    $default_password = $name_prefix . '@123';
                    $new_password = password_hash($default_password, PASSWORD_DEFAULT);
                    
                    error_log("Resetting password for user ID: " . $reset_user_id . " to default");
                    
                    // Update user's password and clear OTP
                    $reset_sql = "UPDATE users SET password = :password, otp_code = NULL, otp_expiry = NULL, updated_at = NOW() WHERE id = :user_id";
                    $reset_stmt = $conn->prepare($reset_sql);
                    $reset_stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
                    $reset_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
                    $reset_stmt->execute();
                    
                    error_log("Password reset successful, rows affected: " . $reset_stmt->rowCount());
                    
                    // Add audit log
                    $action = "Password reset verified via OTP for user ID $reset_user_id: {$user_info['first_name']} {$user_info['last_name']}";
                    $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                    $audit_stmt = $conn->prepare($audit_sql);
                    $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                    $audit_stmt->execute();
                    
                    $conn->commit();
                    
                    error_log("Transaction committed successfully");
                    
                    // Clear session data
                    unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name'], $_SESSION['reset_user_email'], $_SESSION['reset_by_admin']);
                    
                    $_SESSION['alert'] = "Password reset successfully for {$reset_user_name}! The new default password is: <strong>{$default_password}</strong>";
                    $_SESSION['alert_type'] = "success";
                    header("Location: users.php");
                    exit;
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error_message = "Database error: " . $e->getMessage();
                    error_log("Password reset failed: " . $e->getMessage());
                }
            } else {
                $error_message = "Incorrect OTP. Please check and try again.";
                error_log("OTP verification failed: DB='{$db_otp}', Entered='{$entered_otp_clean}'");
            }
        }
    }
    
    // Handle resend OTP
    if (isset($_POST['resend_otp'])) {
        error_log("Resending OTP for user ID: " . $reset_user_id);
        
        try {
            // Generate new OTP
            $otp_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            error_log("New OTP generated: " . $otp_code);
            
            // Update OTP in users table
            $update_sql = "UPDATE users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE id = :user_id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bindParam(':otp_code', $otp_code, PDO::PARAM_STR);
            $update_stmt->bindParam(':otp_expiry', $expires_at, PDO::PARAM_STR);
            $update_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            // Send new OTP via email
            require_once '../classes/EmailNotification.php';
            $emailNotification = new EmailNotification($conn);
            $emailNotification->sendPasswordResetOTP($reset_user_email, $reset_user_name, $otp_code);
            
            $success_message = "A new OTP has been sent to {$masked_email}. It will expire in 10 minutes.";
            $otp_expired = false;
            
            error_log("New OTP sent successfully");
            
            // Refresh OTP data
            $check_otp_stmt->execute();
            $pending_otp = $check_otp_stmt->fetch();
            
        } catch (Exception $e) {
            $error_message = "Error resending OTP: " . $e->getMessage();
            error_log("Resend OTP failed: " . $e->getMessage());
        }
    }
    
    // Handle cancel
    if (isset($_POST['cancel_reset'])) {
        error_log("Password reset cancelled for user ID: " . $reset_user_id);
        
        // Clear OTP
        $clear_sql = "UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = :user_id";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bindParam(':user_id', $reset_user_id, PDO::PARAM_INT);
        $clear_stmt->execute();
        
        unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name'], $_SESSION['reset_user_email'], $_SESSION['reset_by_admin']);
        
        $_SESSION['alert'] = "Password reset cancelled.";
        $_SESSION['alert_type'] = "info";
        header("Location: users.php");
        exit;
    }
}

// Calculate time remaining for OTP
$time_remaining = max(0, strtotime($pending_otp['otp_expiry']) - time());

$pageTitle = "Verify OTP - Reset Password";
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Verify OTP</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="users.php">Users</a></li>
        <li class="breadcrumb-item active">Verify OTP</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card otp-card">
                <div class="card-header otp-header">
                    <div class="otp-icon-circle">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4 class="mb-1">OTP Verification</h4>
                    <p class="mb-0 text-light opacity-75">Enter the OTP sent to the user's email</p>
                </div>
                <div class="card-body p-4">
                    
                    <!-- User Info -->
                    <div class="user-info-box mb-4">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0"><?php echo htmlspecialchars($reset_user_name); ?></h6>
                                <small class="text-muted">OTP sent to: <?php echo htmlspecialchars($masked_email); ?></small>
                            </div>
                        </div>
                    </div>
                    
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
                    
                    <?php if ($otp_expired): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i><strong>OTP Expired!</strong> The OTP has expired. Please resend a new one.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Timer -->
                    <?php if (!$otp_expired): ?>
                    <div class="timer-box text-center mb-4" id="timerBox">
                        <div class="timer-circle" id="timerCircle">
                            <span id="timerText" class="timer-text"><?php echo gmdate('i:s', $time_remaining); ?></span>
                        </div>
                        <small class="text-muted mt-2 d-block">Time remaining</small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- OTP Input Form -->
                    <?php if (!$otp_expired): ?>
                    <form method="POST" action="" id="otpForm">
                        <div class="otp-input-group mb-4">
                            <label class="form-label fw-bold">Enter 6-digit OTP</label>
                            <div class="otp-inputs d-flex justify-content-center gap-2" id="otpInputs">
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="0" autofocus>
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="1">
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="2">
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="3">
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="4">
                                <input type="text" class="otp-digit form-control text-center" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="5">
                            </div>
                            <input type="hidden" name="otp_code" id="otpCode">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_otp" class="btn btn-primary btn-lg" id="verifyBtn">
                                <i class="fas fa-check-circle me-2"></i>Verify & Reset Password
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <form method="POST" action="" class="d-inline">
                            <button type="submit" name="resend_otp" class="btn btn-outline-primary" id="resendBtn">
                                <i class="fas fa-paper-plane me-1"></i>Resend OTP
                            </button>
                        </form>
                        <form method="POST" action="" class="d-inline">
                            <button type="submit" name="cancel_reset" class="btn btn-outline-danger">
                                <i class="fas fa-times me-1"></i>Cancel Reset
                            </button>
                        </form>
                    </div>
                    
                    <!-- Info Note -->
                    <div class="alert alert-info mt-4 mb-0">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Ask the user to check their email inbox (and spam folder) for the OTP. 
                            The password will be reset to the default pattern: <strong>First 3 letters of first name (CAPS) + @123</strong>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.otp-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.otp-header {
    background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
    color: white;
    text-align: center;
    padding: 2rem 1.5rem 1.5rem;
    border: none;
}

.otp-icon-circle {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.8rem;
    backdrop-filter: blur(10px);
}

.user-info-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid #e9ecef;
}

.user-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.timer-box {
    margin: 1rem 0;
}

.timer-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    transition: border-color 0.5s ease;
}

.timer-circle.warning {
    border-color: #ffc107;
}

.timer-circle.danger {
    border-color: #dc3545;
    animation: pulse 1s infinite;
}

.timer-text {
    font-size: 1.2rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    color: #333;
}

.otp-digit {
    width: 52px;
    height: 60px;
    font-size: 1.5rem;
    font-weight: 700;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    transition: all 0.2s ease;
    color: #1a237e;
}

.otp-digit:focus {
    border-color: #1a237e;
    box-shadow: 0 0 0 0.2rem rgba(26, 35, 126, 0.25);
    transform: scale(1.05);
}

.otp-digit.filled {
    border-color: #28a745;
    background-color: #f0fff0;
}

.otp-digit.error {
    border-color: #dc3545;
    background-color: #fff5f5;
    animation: shake 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
    border: none;
    border-radius: 10px;
    padding: 0.75rem;
    font-weight: 600;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #283593 0%, #3949ab 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@media (max-width: 576px) {
    .otp-digit {
        width: 42px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .otp-inputs {
        gap: 0.3rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    const otpCodeInput = document.getElementById('otpCode');
    const otpForm = document.getElementById('otpForm');
    const verifyBtn = document.getElementById('verifyBtn');
    
    if (otpInputs.length > 0) {
        // Handle input for each OTP digit
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Only allow digits
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value.length === 1) {
                    this.classList.add('filled');
                    // Move to next input
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                } else {
                    this.classList.remove('filled');
                }
                
                updateHiddenInput();
            });
            
            input.addEventListener('keydown', function(e) {
                // Handle backspace
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                }
                
                // Handle left arrow
                if (e.key === 'ArrowLeft' && index > 0) {
                    otpInputs[index - 1].focus();
                }
                
                // Handle right arrow
                if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData).getData('text');
                const digits = pastedData.replace(/[^0-9]/g, '').split('');
                
                digits.forEach((digit, i) => {
                    if (index + i < otpInputs.length) {
                        otpInputs[index + i].value = digit;
                        otpInputs[index + i].classList.add('filled');
                    }
                });
                
                // Focus on next empty or last input
                const nextEmpty = Array.from(otpInputs).findIndex(inp => !inp.value);
                if (nextEmpty !== -1) {
                    otpInputs[nextEmpty].focus();
                } else {
                    otpInputs[otpInputs.length - 1].focus();
                }
                
                updateHiddenInput();
            });
        });
        
        function updateHiddenInput() {
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            otpCodeInput.value = otp;
        }
        
        // Form submission
        if (otpForm) {
            otpForm.addEventListener('submit', function(e) {
                updateHiddenInput();
                
                if (otpCodeInput.value.length !== 6) {
                    e.preventDefault();
                    otpInputs.forEach(input => {
                        if (!input.value) {
                            input.classList.add('error');
                            setTimeout(() => input.classList.remove('error'), 600);
                        }
                    });
                    return;
                }
                
                // Disable button to prevent double submission
                if (verifyBtn) {
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
                }
            });
        }
    }
    
    // Countdown timer
    <?php if (!$otp_expired): ?>
    let timeRemaining = <?php echo $time_remaining; ?>;
    const timerText = document.getElementById('timerText');
    const timerCircle = document.getElementById('timerCircle');
    const timerBox = document.getElementById('timerBox');
    
    if (timerText && timeRemaining > 0) {
        const timerInterval = setInterval(function() {
            timeRemaining--;
            
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                timerText.textContent = '00:00';
                timerCircle.classList.add('danger');
                
                // Show expired message
                const expiredAlert = document.createElement('div');
                expiredAlert.className = 'alert alert-warning mt-3';
                expiredAlert.innerHTML = '<i class="fas fa-clock me-2"></i><strong>OTP Expired!</strong> Please resend a new OTP.';
                timerBox.parentNode.insertBefore(expiredAlert, timerBox.nextSibling);
                
                // Disable verify button
                if (verifyBtn) {
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<i class="fas fa-clock me-2"></i>OTP Expired';
                }
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            timerText.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            // Change color based on time remaining
            if (timeRemaining <= 60) {
                timerCircle.classList.remove('warning');
                timerCircle.classList.add('danger');
            } else if (timeRemaining <= 180) {
                timerCircle.classList.add('warning');
            }
        }, 1000);
    }
    <?php endif; ?>
    
    // Resend cooldown
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        // 30s cooldown after page load (new OTP was just sent)
        let resendCooldown = 30;
        resendBtn.disabled = true;
        
        const cooldownInterval = setInterval(function() {
            resendCooldown--;
            resendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Resend OTP (' + resendCooldown + 's)';
            
            if (resendCooldown <= 0) {
                clearInterval(cooldownInterval);
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Resend OTP';
            }
        }, 1000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
