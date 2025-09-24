<?php
// Generate password hash - get password from environment or command line
$password = $_ENV['PASSWORD'] ?? ($argv[1] ?? null);
if (!$password) {
    die("Usage: php generate_password_hash.php <password> or set PASSWORD environment variable\n");
}
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash: " . $hash . "\n";
?>