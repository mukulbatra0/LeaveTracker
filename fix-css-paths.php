<?php
/**
 * Quick fix for CSS/JS path issues on InfinityFree
 */

echo "=== Fixing CSS/JS Path Issues ===\n\n";

// Update the header file in deployment
$deploymentDir = 'deployment';
$headerFile = $deploymentDir . '/includes/header.php';

if (!file_exists($headerFile)) {
    echo "❌ Header file not found in deployment folder\n";
    echo "Run prepare-deployment.php or update-deployment.php first\n";
    exit(1);
}

// Read current header
$headerContent = file_get_contents($headerFile);

// Fix the path calculation
$oldPathCode = '    $currentScript = $_SERVER[\'SCRIPT_NAME\'];
    $currentDir = dirname($currentScript);
    $levels = substr_count(trim($currentDir, \'/\'), \'/\');
    $basePath = str_repeat(\'../\', $levels);';

$newPathCode = '    // Simple and reliable path detection for InfinityFree
    $currentScript = $_SERVER[\'SCRIPT_NAME\'];
    $currentDir = dirname($currentScript);
    
    // Remove leading slash and count directory levels
    $currentDir = ltrim($currentDir, \'/\');
    $levels = empty($currentDir) ? 0 : substr_count($currentDir, \'/\');
    
    // Create relative path back to root
    if ($levels == 0) {
        $basePath = \'./\';
    } else {
        $basePath = str_repeat(\'../\', $levels);
    }';

// Replace the path calculation
$updatedContent = str_replace($oldPathCode, $newPathCode, $headerContent);

if ($updatedContent !== $headerContent) {
    file_put_contents($headerFile, $updatedContent);
    echo "✅ Fixed header.php path calculation\n";
} else {
    echo "ℹ️ Header.php already has the correct path calculation\n";
}

// Create a simple test file to debug paths
$testFile = $deploymentDir . '/test-paths.php';
$testContent = '<?php
// Path debugging for InfinityFree
echo "<h3>Path Debug Information</h3>";
echo "<p><strong>SCRIPT_NAME:</strong> " . $_SERVER["SCRIPT_NAME"] . "</p>";
echo "<p><strong>Current Directory:</strong> " . dirname($_SERVER["SCRIPT_NAME"]) . "</p>";

$currentScript = $_SERVER["SCRIPT_NAME"];
$currentDir = dirname($currentScript);
$currentDir = ltrim($currentDir, "/");
$levels = empty($currentDir) ? 0 : substr_count($currentDir, "/");

if ($levels == 0) {
    $basePath = "./";
} else {
    $basePath = str_repeat("../", $levels);
}

echo "<p><strong>Calculated Base Path:</strong> " . htmlspecialchars($basePath) . "</p>";
echo "<p><strong>CSS Path:</strong> " . htmlspecialchars($basePath . "css/style.css") . "</p>";

// Test if CSS file exists
$cssPath = $basePath . "css/style.css";
if (file_exists($cssPath)) {
    echo "<p style=\"color: green;\">✅ CSS file found at: " . htmlspecialchars($cssPath) . "</p>";
} else {
    echo "<p style=\"color: red;\">❌ CSS file NOT found at: " . htmlspecialchars($cssPath) . "</p>";
}
?>';

file_put_contents($testFile, $testContent);
echo "✅ Created test-paths.php for debugging\n";

// Create updated ZIP
$zipFile = 'elms-css-fix-' . date('Y-m-d-H-i-s') . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Add only the files that need updating
    $filesToUpdate = [
        'includes/header.php',
        'test-paths.php'
    ];
    
    foreach ($filesToUpdate as $file) {
        $fullPath = $deploymentDir . '/' . $file;
        if (file_exists($fullPath)) {
            $zip->addFile($fullPath, $file);
            echo "📦 Added to ZIP: $file\n";
        }
    }
    
    $zip->close();
    echo "✅ Created fix ZIP: $zipFile\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Upload '$zipFile' to your InfinityFree htdocs folder\n";
echo "2. Extract the ZIP file (overwrite existing files)\n";
echo "3. Visit https://yourdomain.com/test-paths.php to debug paths\n";
echo "4. Check if CSS is now loading on other pages\n";
echo "5. Delete test-paths.php when done\n\n";

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>