<?php
/**
 * Render.com startup script for ELMS
 * This script starts the PHP built-in server for Render deployment
 */

// Get the port from environment variable (Render provides this)
$port = $_ENV['PORT'] ?? getenv('PORT') ?? 10000;
$host = '0.0.0.0';

echo "Starting ELMS server on $host:$port\n";

// Start the PHP built-in server
$command = "php -S $host:$port -t .";
echo "Running: $command\n";

// Execute the server command
passthru($command);
?>