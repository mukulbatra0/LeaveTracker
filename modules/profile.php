<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['alert'] = "Please log in to access your profile";
    $_SESSION['alert_type'] = "warning";
    header('Location: /login.php');
    exit;
}

// Check if database connection file exists
if (!file_exists('../config/db.php')) {
    die("Error: Database configuration file not found at ../config/db.php");
}

require_once '../config/db.php';

// Verify database connection
if (!isset($conn) || !($conn instanceof PDO)) {
    die("Error: Database connection not established. Please check your database configuration.");
}

require_once '../includes/security.php';

class ProfileManager {
    private $conn;
    private $user_id;
    private $errors = [];
    private $success_messages = [];
    
    public function __construct($database, $user_id) {
        $this->conn = $database;
        $this->user_id = $user_id;
    }
    
    public function getUserData() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$this->user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->errors[] = "Error retrieving user data: " . $e->getMessage();
            return false;
        }
    }
    
    public function updateProfile($data) {
        // Validate input
        if (!$this->validateProfileData($data)) {
            error_log("Profile validation failed: " . implode(", ", $this->errors));
            return false;
        }
        
        try {
            // Log the update attempt
            error_log("Attempting to update profile for user ID: " . $this->user_id);
            
            $stmt = $this->conn->prepare("
                UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    emergency_contact = ? 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['first_name'],
                $data['last_name'], 
                $data['email'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['emergency_contact'] ?? null,
                $this->user_id
            ]);
            
            if ($result) {
                $rowsAffected = $stmt->rowCount();
                error_log("Profile update successful. Rows affected: " . $rowsAffected);
                
                $this->logAction("Updated profile information");
                
                // Update session data
                $_SESSION['first_name'] = $data['first_name'];
                $_SESSION['last_name'] = $data['last_name'];
                $_SESSION['email'] = $data['email'];
                
                return true;
            } else {
                error_log("Profile update failed - execute returned false");
                $this->errors[] = "Update failed - no changes made";
            }
            
        } catch (PDOException $e) {
            error_log("Profile update PDO error: " . $e->getMessage());
            $this->errors[] = "Database error: " . $e->getMessage();
        }
        
        return false;
    }
    

    
    public function uploadProfilePicture($file) {
        if (!$this->validateFileUpload($file)) {
            return false;
        }
        
        $upload_dir = '../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = 'profile_' . $this->user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            try {
                // Remove old profile picture
                $user = $this->getUserData();
                if (!empty($user['profile_picture'])) {
                    $old_file = '../' . $user['profile_picture'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Update database
                $profile_picture_path = 'uploads/profile_pictures/' . $new_filename;
                $stmt = $this->conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $result = $stmt->execute([$profile_picture_path, $this->user_id]);
                
                if ($result) {
                    $this->logAction("Updated profile picture");
                    return true;
                }
                
            } catch (PDOException $e) {
                $this->errors[] = "Database error: " . $e->getMessage();
            }
        } else {
            $this->errors[] = "Failed to upload file";
        }
        
        return false;
    }
    
    public function getLeaveBalances() {
        try {
            $stmt = $this->conn->prepare("
                SELECT lb.*, lt.name as leave_type, lt.color 
                FROM leave_balances lb 
                JOIN leave_types lt ON lb.leave_type_id = lt.id 
                WHERE lb.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getDepartmentName($department_id) {
        try {
            $stmt = $this->conn->prepare("SELECT name FROM departments WHERE id = ?");
            $stmt->execute([$department_id]);
            return $stmt->fetchColumn() ?: 'Not Assigned';
        } catch (PDOException $e) {
            return 'Error retrieving department';
        }
    }
    
    public function getLastLogin() {
        try {
            $stmt = $this->conn->prepare("
                SELECT created_at FROM audit_logs 
                WHERE user_id = ? AND action = 'Logged in' 
                ORDER BY created_at DESC LIMIT 1 OFFSET 1
            ");
            $stmt->execute([$this->user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    private function validateProfileData($data) {
        $this->errors = [];
        
        if (empty(trim($data['first_name']))) {
            $this->errors[] = "First name is required";
        }
        
        if (empty(trim($data['last_name']))) {
            $this->errors[] = "Last name is required";
        }
        
        if (empty(trim($data['email']))) {
            $this->errors[] = "Email is required";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Invalid email format";
        } else {
            // Check if email exists for another user
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $this->user_id]);
            if ($stmt->rowCount() > 0) {
                $this->errors[] = "Email already in use by another account";
            }
        }
        
        return empty($this->errors);
    }
    

    
    private function validateFileUpload($file) {
        $this->errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = "File upload error";
            return false;
        }
        
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            $this->errors[] = "File size exceeds 5MB limit";
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            $this->errors[] = "Only JPG, JPEG, and PNG files are allowed";
        }
        
        return empty($this->errors);
    }
    
    private function logAction($action) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$this->user_id, $action, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (PDOException $e) {
            // Audit logging is optional
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getSuccessMessages() {
        return $this->success_messages;
    }
}

// Initialize profile manager
$profile = new ProfileManager($conn, $_SESSION['user_id']);
$user = $profile->getUserData();

if (!$user) {
    $_SESSION['alert'] = "User not found";
    $_SESSION['alert_type'] = "danger";
    header('Location: /index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        if ($profile->updateProfile($_POST)) {
            // Redirect to prevent form resubmission
            $_SESSION['alert'] = 'Profile updated successfully';
            $_SESSION['alert_type'] = 'success';
            header('Location: profile.php');
            exit;
        }
        $user = $profile->getUserData(); // Refresh user data
    }
    

    
    if (isset($_POST['upload_picture']) && isset($_FILES['profile_picture'])) {
        if ($profile->uploadProfilePicture($_FILES['profile_picture'])) {
            $_SESSION['alert'] = 'Profile picture updated successfully';
            $_SESSION['alert_type'] = 'success';
            header('Location: profile.php');
            exit;
        }
        $user = $profile->getUserData(); // Refresh user data
    }
}

$pageTitle = "My Profile";
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">My Profile</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">My Profile</li>
    </ol>
    
    <!-- Error Messages -->
    <?php if (!empty($profile->getErrors())): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($profile->getErrors() as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Session Alerts -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Sidebar -->
        <div class="col-xl-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-circle me-1"></i>
                    Profile Information
                </div>
                <div class="card-body text-center">
                    <!-- Profile Picture -->
                    <div class="mb-3">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <img src="/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile Picture" 
                                 class="img-fluid rounded-circle profile-image">
                        <?php else: ?>
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info -->
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-3">
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                        <br>
                        <small>ID: <?php echo htmlspecialchars($user['employee_id']); ?></small>
                    </p>
                    
                    <!-- Upload Form -->
                    <form method="post" enctype="multipart/form-data" class="mt-3">
                        <input type="hidden" name="upload_picture" value="1">
                        <div class="mb-3">
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        </div>
                        <button type="submit" name="upload_picture" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-upload me-1"></i>Update Picture
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Account Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Account Details
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-item">
                            <span class="label">Department:</span>
                            <span class="value"><?php echo htmlspecialchars($profile->getDepartmentName($user['department_id'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Role:</span>
                            <span class="value"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Joined:</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Last Login:</span>
                            <span class="value">
                                <?php 
                                $last_login = $profile->getLastLogin();
                                echo $last_login ? date('M d, Y h:i A', strtotime($last_login)) : 'First Login';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-xl-8">
            <!-- Edit Profile Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-edit me-1"></i>
                    Edit Profile
                </div>
                <div class="card-body">
                    <form method="post" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                   value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>"
                                   placeholder="Name and phone number">
                            <div class="form-text">Emergency contact person's name and phone number</div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Profile
                        </button>
                        <a href="change_password.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-key me-1"></i>Change Password
                        </a>
                    </form>
                </div>
            </div>
            

            <!-- Leave Balances -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Leave Balances
                </div>
                <div class="card-body">
                    <?php $leave_balances = $profile->getLeaveBalances(); ?>
                    <?php if (empty($leave_balances)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No leave balances found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Total</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Usage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leave_balances as $balance): ?>
                                        <?php 
                                        $remaining = $balance['total_days'] - $balance['used_days'];
                                        $percentage = ($balance['total_days'] > 0) ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                        $progress_class = $percentage > 75 ? 'bg-danger' : ($percentage > 50 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge me-2" style="background-color: <?php echo $balance['color']; ?>;">&nbsp;</span>
                                                <?php echo htmlspecialchars($balance['leave_type']); ?>
                                            </td>
                                            <td><?php echo $balance['total_days']; ?></td>
                                            <td><?php echo $balance['used_days']; ?></td>
                                            <td><strong><?php echo $remaining; ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                                         style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo round($percentage, 1); ?>% used</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <a href="my_leaves.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-1"></i>View Leave History
                            </a>
                            <a href="apply_leave.php" class="btn btn-outline-success ms-2">
                                <i class="fas fa-plus me-1"></i>Apply for Leave
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
}

.profile-avatar {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: bold;
    margin: 0 auto;
}

.account-info .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
}

.account-info .info-item:last-child {
    border-bottom: none;
}

.account-info .label {
    font-weight: 600;
    color: #6c757d;
}

.account-info .value {
    color: #495057;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.progress {
    background-color: #e9ecef;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    
    // File upload validation
    const profilePictureInput = document.getElementById('profile_picture');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPG, JPEG, and PNG files are allowed');
                    this.value = '';
                    return;
                }
            }
        });
    }
    
    // Form submission confirmation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                
                // Re-enable after 3 seconds to prevent permanent disable
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.innerHTML.replace('Processing...', 'Update Profile');
                }, 3000);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>