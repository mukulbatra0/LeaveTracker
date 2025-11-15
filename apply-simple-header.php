<?php
/**
 * Apply Simple Header Fix for InfinityFree
 * This replaces the dynamic path calculation with hardcoded absolute paths
 */

echo "=== Applying Simple Header Fix ===\n\n";

$deploymentDir = 'deployment';

// Copy the simple header to deployment
if (!is_dir($deploymentDir)) {
    echo "❌ Deployment folder not found. Run update-deployment.php first\n";
    exit(1);
}

// Copy simple header to deployment
$simpleHeader = 'includes/header_simple.php';
$deploymentHeader = $deploymentDir . '/includes/header.php';

if (!file_exists($simpleHeader)) {
    echo "❌ Simple header file not found\n";
    exit(1);
}

// Create includes directory if it doesn't exist
if (!is_dir($deploymentDir . '/includes')) {
    mkdir($deploymentDir . '/includes', 0755, true);
}

// Copy the simple header
copy($simpleHeader, $deploymentHeader);
echo "✅ Applied simple header with absolute paths\n";

// Create ZIP for upload
$zipFile = 'elms-simple-header-' . date('Y-m-d-H-i-s') . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addFile($deploymentHeader, 'includes/header.php');
    $zip->close();
    echo "✅ Created ZIP: $zipFile\n";
}

echo "\n=== What This Does ===\n";
echo "• Uses absolute paths starting with / (e.g., /css/style.css)\n";
echo "• Works from any directory level\n";
echo "• No dynamic path calculation needed\n";
echo "• Should work reliably on InfinityFree\n\n";

echo "=== Upload Instructions ===\n";
echo "1. Upload '$zipFile' to your InfinityFree htdocs folder\n";
echo "2. Extract the ZIP file (overwrite existing header.php)\n";
echo "3. Test any page in modules/ or admin/ folders\n";
echo "4. CSS and navigation should now work properly\n\n";
?>