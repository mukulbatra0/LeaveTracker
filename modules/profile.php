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
$role = $_SESSION['role'];

// Initialize variables
$errors = [];
$success = false;

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['alert'] = "User not found.";
        $_SESSION['alert_type'] = "danger";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['alert'] = "Error: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic profile update
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $emergency_contact = trim($_POST['emergency_contact']);
        
        // Validate inputs
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            // Check if email exists for another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already in use by another account.";
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, emergency_contact = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $address, $emergency_contact, $user_id]);
                
                // Update session variables
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                
                // Log the action (with error handling)
                try {
                    $action = "Updated profile information";
                    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
                } catch (PDOException $e) {
                    // Audit log table might not exist, continue without logging
                }
                
                $_SESSION['alert'] = "Profile updated successfully.";
                $_SESSION['alert_type'] = "success";
                $success = true;
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password)) {
            $errors[] = "Current password is required.";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        }
        
        if (empty($errors)) {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            } else {
                try {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update the password
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    // Log the action (with error handling)
                    try {
                        $action = "Changed password";
                        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
                    } catch (PDOException $e) {
                        // Audit log table might not exist, continue without logging
                    }
                    
                    $_SESSION['alert'] = "Password changed successfully.";
                    $_SESSION['alert_type'] = "success";
                    $success = true;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    
    // Profile picture upload
    if (isset($_POST['upload_picture'])) {
        // Check if file was uploaded without errors
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            // Get system settings for file uploads with error handling
            try {
                $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_file_size'");
                $stmt->execute();
                $max_file_size = ($stmt->fetchColumn() ?: 5) * 1024 * 1024; // Convert MB to bytes
                
                $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allowed_file_types'");
                $stmt->execute();
                $allowed_types_str = $stmt->fetchColumn() ?: 'jpg,jpeg,png';
            } catch (PDOException $e) {
                // Use default values if system_settings table doesn't exist
                $max_file_size = 5 * 1024 * 1024; // 5MB default
                $allowed_types_str = 'jpg,jpeg,png';
            }
            $allowed_types = explode(',', $allowed_types_str);
            
            $file = $_FILES['profile_picture'];
            $file_size = $file['size'];
            $file_tmp = $file['tmp_name'];
            $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file size
            if ($file_size > $max_file_size) {
                $errors[] = "File size exceeds the maximum limit of " . ($max_file_size / 1024 / 1024) . "MB.";
            }
            
            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Invalid file type. Allowed types: " . $allowed_types_str;
            }
            
            if (empty($errors)) {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../uploads/profile_pictures/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_type;
                $upload_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    try {
                        // Delete old profile picture if exists
                        $old_profile_pic = $user['profile_picture'] ?? $user['profile_image'] ?? '';
                        if (!empty($old_profile_pic)) {
                            $old_file = '../' . $old_profile_pic;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        // Update database with new profile picture path
                        $profile_picture_path = 'uploads/profile_pictures/' . $new_filename;
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$profile_picture_path, $user_id]);
                        
                        // Log the action (with error handling)
                        try {
                            $action = "Updated profile picture";
                            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                            $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
                        } catch (PDOException $e) {
                            // Audit log table might not exist, continue without logging
                        }
                        
                        $_SESSION['alert'] = "Profile picture updated successfully.";
                        $_SESSION['alert_type'] = "success";
                        $success = true;
                        
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Failed to upload file. Please try again.";
                }
            }
        } else {
            $errors[] = "Please select a file to upload.";
        }
    }
}

// Set page title
$pageTitle = "My Profile";

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">My Profile</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">My Profile</li>
    </ol>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Information -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-circle me-1"></i>
                    Profile Information
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php 
                        $profile_pic = $user['profile_picture'] ?? $user['profile_image'] ?? '';
                        if (!empty($profile_pic) && file_exists('../' . $profile_pic)): ?>
                            <img src="../<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px; font-size: 4rem;">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted">
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                        <br>
                        Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?>
                    </p>
                    
                    <form method="post" enctype="multipart/form-data" class="mt-3">
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Update Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        </div>
                        <button type="submit" name="upload_picture" class="btn btn-outline-primary">Upload Picture</button>
                    </form>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Account Information
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Department</span>
                            <?php 
                            try {
                                $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                                $stmt->execute([$user['department_id']]);
                                $department_name = $stmt->fetchColumn() ?: 'Not Assigned';
                                echo "<span>" . htmlspecialchars($department_name) . "</span>";
                            } catch (PDOException $e) {
                                echo "<span>Error retrieving department</span>";
                            }
                            ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Role</span>
                            <span><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Joined Date</span>
                            <span><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Last Login</span>
                            <?php 
                            try {
                                $stmt = $conn->prepare("SELECT created_at FROM audit_logs WHERE user_id = ? AND action = 'Logged in' ORDER BY created_at DESC LIMIT 1 OFFSET 1");
                                $stmt->execute([$user_id]);
                                $last_login = $stmt->fetchColumn();
                                echo "<span>" . ($last_login ? date('M d, Y h:i A', strtotime($last_login)) : 'First Login') . "</span>";
                            } catch (PDOException $e) {
                                echo "<span>Error retrieving login info</span>";
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-edit me-1"></i>
                    Edit Profile
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>">
                            <div class="form-text">Name and phone number of emergency contact person</div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-key me-1"></i>
                    Change Password
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Leave Balances -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-alt me-1"></i>
                    My Leave Balances
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Total Days</th>
                                    <th>Used Days</th>
                                    <th>Remaining Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->prepare("SELECT lb.*, lt.name as leave_type, lt.color 
                                                          FROM leave_balances lb 
                                                          JOIN leave_types lt ON lb.leave_type_id = lt.id 
                                                          WHERE lb.user_id = ?");
                                    $stmt->execute([$user_id]);
                                    $leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($leave_balances)) {
                                        echo "<tr><td colspan='4' class='text-center'>No leave balances found</td></tr>";
                                    } else {
                                        foreach ($leave_balances as $balance) {
                                            $remaining = $balance['total_days'] - $balance['used_days'];
                                            $percentage = ($balance['total_days'] > 0) ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                            
                                            echo "<tr>";
                                            echo "<td><span class='badge' style='background-color: {$balance['color']};'>&nbsp;</span> {$balance['leave_type']}</td>";
                                            echo "<td>{$balance['total_days']}</td>";
                                            echo "<td>{$balance['used_days']}</td>";
                                            echo "<td>";
                                            echo "<div class='d-flex align-items-center'>";
                                            echo "<span class='me-2'>{$remaining}</span>";
                                            echo "<div class='progress flex-grow-1' style='height: 5px;'>";
                                            echo "<div class='progress-bar" . ($percentage > 75 ? " bg-danger" : ($percentage > 50 ? " bg-warning" : "")) . "' role='progressbar' style='width: {$percentage}%' aria-valuenow='{$percentage}' aria-valuemin='0' aria-valuemax='100'></div>";
                                            echo "</div>";
                                            echo "</div>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='4' class='text-center'>Error retrieving leave balances</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <a href="my_leaves.php" class="btn btn-outline-primary">View My Leave History</a>
                        <a href="apply_leave.php" class="btn btn-outline-success ms-2">Apply for Leave</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    // File upload preview
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
});
</script>

<?php include '../includes/footer.php'; ?>