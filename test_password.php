<?php
// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db.php';

// Test password verification - get from environment or form
$email = $_POST['email'] ?? $_ENV['TEST_EMAIL'] ?? null;
$password = $_POST['password'] ?? $_ENV['TEST_PASSWORD'] ?? null;

if (!$email || !$password) {
    echo '<form method="post">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Test</button>
    </form>';
    exit;
}

// Get user from database
$sql = "SELECT id, password FROM users WHERE email = :email";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':email', $email);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch();
    echo "<p>User found with ID: {$user['id']}</p>";
    echo "<p>Stored password hash: {$user['password']}</p>";
    
    // Test password verification
    $result = password_verify($password, $user['password']);
    echo "<p>Password verification result: " . ($result ? 'Success' : 'Failed') . "</p>";
    
    // Test with direct hash comparison (for debugging only)
    $test_hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<p>Test hash: {$test_hash}</p>";
    echo "<p>Direct comparison: " . ($user['password'] === $test_hash ? 'Match' : 'No match') . " (expected no match)</p>";
} else {
    echo "<p>User not found</p>";
}
?>