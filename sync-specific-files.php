<?php
/**
 * Sync Specific Files to Deployment
 * Use this when you only want to update specific files
 */

// Files you want to update (modify this list as needed)
$filesToUpdate = [
    // Example: uncomment the files you want to update
    // 'index.php',
    // 'login.php',
    // 'css/style.css',
    // 'js/main.js',
    // 'admin/dashboard.php',
    // 'api/leave_applications.php'
];

if (empty($filesToUpdate)) {
    echo "Please edit this script and specify which files you want to update.\n";
    echo "Uncomment the files in the \$filesToUpdate array.\n";
    exit(1);
}

echo "=== Syncing Specific Files ===\n\n";

$deploymentDir = 'deployment';
$updated = [];
$failed = [];

foreach ($filesToUpdate as $file) {
    if (file_exists($file)) {
        $deploymentPath = $deploymentDir . '/' . $file;
        
        // Create directory if it doesn't exist
        $dir = dirname($deploymentPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (copy($file, $deploymentPath)) {
            $updated[] = $file;
            echo "✓ Updated: $file\n";
        } else {
            $failed[] = $file;
            echo "✗ Failed: $file\n";
        }
    } else {
        $failed[] = $file;
        echo "✗ Not found: $file\n";
    }
}

echo "\n=== Summary ===\n";
echo "Updated: " . count($updated) . " files\n";
echo "Failed: " . count($failed) . " files\n";

if (!empty($updated)) {
    echo "\nNow upload these files to your InfinityFree hosting:\n";
    foreach ($updated as $file) {
        echo "- $file\n";
    }
}
?>