<?php
// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db.php';

// Reset admin password - get from environment or form
$email = $_POST['email'] ?? $_ENV['RESET_EMAIL'] ?? null;
$new_password = $_POST['password'] ?? $_ENV['RESET_PASSWORD'] ?? null;

if (!$email || !$new_password) {
    echo '<form method="post">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="New Password" required>
        <button type="submit">Reset Password</button>
    </form>';
    exit;
}

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$sql = "UPDATE users SET password = :password WHERE email = :email";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':password', $hashed_password);
$stmt->bindParam(':email', $email);

if ($stmt->execute()) {
    echo "<div class='alert alert-success'>Password reset successful!</div>";
    echo "<p>You can now <a href='login.php'>login</a> with your new password</p>";
} else {
    echo "<div class='alert alert-danger'>Password reset failed!</div>";
}
?>