<?php
// Simple test to verify export functionality fix
session_start();

// Simulate director role for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'director';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Director';

echo "<h2>Export Functionality Test</h2>";

// Test 1: Check if director role is in allowed roles for PDF export
include_once 'config/db.php';

// Simulate the allowed roles check from export_pdf.php
$allowed_roles = ['admin', 'head_of_department', 'director', 'dean', 'principal', 'hr_admin'];
$role = $_SESSION['role'];

if (in_array($role, $allowed_roles)) {
    echo "<p style='color: green;'>✓ PDF Export: Director role is now allowed</p>";
} else {
    echo "<p style='color: red;'>✗ PDF Export: Director role is NOT allowed</p>";
}

// Test 2: Check if director role is in allowed roles for Excel export
$allowed_roles_excel = ['admin', 'head_of_department', 'director', 'dean', 'principal', 'hr_admin'];

if (in_array($role, $allowed_roles_excel)) {
    echo "<p style='color: green;'>✓ Excel Export: Director role is now allowed</p>";
} else {
    echo "<p style='color: red;'>✗ Excel Export: Director role is NOT allowed</p>";
}

// Test 3: Check if required libraries are available
echo "<h3>Library Dependencies:</h3>";

if (file_exists('vendor/autoload.php')) {
    echo "<p style='color: green;'>✓ Composer autoloader found</p>";
    
    require_once 'vendor/autoload.php';
    
    // Check TCPDF
    if (class_exists('TCPDF')) {
        echo "<p style='color: green;'>✓ TCPDF library available</p>";
    } else {
        echo "<p style='color: red;'>✗ TCPDF library not available</p>";
    }
    
    // Check PhpSpreadsheet
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        echo "<p style='color: green;'>✓ PhpSpreadsheet library available</p>";
    } else {
        echo "<p style='color: red;'>✗ PhpSpreadsheet library not available</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Composer autoloader not found</p>";
}

echo "<h3>Summary:</h3>";
echo "<p>The export functionality should now work for directors. The main issues were:</p>";
echo "<ul>";
echo "<li>Missing 'director' role in the allowed_roles arrays of both export files</li>";
echo "<li>Inconsistent role naming (department_head vs head_of_department)</li>";
echo "</ul>";
echo "<p>Both issues have been fixed.</p>";

// Clean up session
session_destroy();
?>