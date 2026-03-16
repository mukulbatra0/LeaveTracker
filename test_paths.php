<?php
session_start();

// Simple test to check if paths are working
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Path Test - LeaveTracker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-item { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <h1>LeaveTracker Path Test</h1>
    <p>This page tests if all CSS, JS, and image files are loading correctly.</p>
    
    <div class="test-item">
        <h3>CSS Files Test</h3>
        <p>Check if these CSS files load (open browser dev tools to see any 404 errors):</p>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/responsive-override.css">
        <link rel="stylesheet" href="css/mobile-tables.css">
        <p>✓ CSS files linked</p>
    </div>
    
    <div class="test-item">
        <h3>JavaScript Files Test</h3>
        <p>Check if these JS files load:</p>
        <script src="js/mobile-detector.js"></script>
        <script src="js/responsive-helpers.js"></script>
        <script src="js/mobile-enhancements.js"></script>
        <script src="js/notifications.js"></script>
        <script src="js/script.js"></script>
        <p>✓ JS files linked</p>
    </div>
    
    <div class="test-item">
        <h3>Image Test</h3>
        <p>Favicon test:</p>
        <img src="images/favicon.ico" alt="Favicon" style="width: 32px; height: 32px;">
        <p>✓ If you see the favicon above, images are working</p>
    </div>
    
    <div class="test-item">
        <h3>Bootstrap Test</h3>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <div class="alert alert-success">
            <strong>Bootstrap Test:</strong> If this alert looks styled, Bootstrap CSS is working.
        </div>
        <button class="btn btn-primary" onclick="alert('Bootstrap JS is working!')">Test Bootstrap JS</button>
    </div>
    
    <div class="test-item">
        <h3>Font Awesome Test</h3>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <p><i class="fas fa-check-circle"></i> If you see an icon here, Font Awesome is working</p>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <div class="test-item">
        <h3>Navigation Test</h3>
        <p>Test navigation links:</p>
        <a href="index.php" class="btn btn-primary">Dashboard</a>
        <a href="login.php" class="btn btn-secondary">Login</a>
        <a href="modules/apply_leave.php" class="btn btn-info">Apply Leave (requires login)</a>
    </div>
    
    <script>
        // Test if jQuery is working
        $(document).ready(function() {
            console.log('jQuery is working!');
            $('body').append('<div class="test-item success"><h3>jQuery Test</h3><p>✓ jQuery is loaded and working</p></div>');
        });
    </script>
</body>
</html>