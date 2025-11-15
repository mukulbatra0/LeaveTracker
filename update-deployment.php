<?php
/**
 * Quick Deployment Update Script
 * Updates deployment folder with latest changes and creates ZIP for upload
 */

echo "=== ELMS Deployment Update ===\n\n";

$deploymentDir = 'deployment';

// Files and folders to sync
$itemsToSync = [
    'admin',
    'api', 
    'classes',
    'config',
    'css',
    'dashboards',
    'images',
    'includes',
    'js',
    'modules',
    'reports',
    'index.php',
    'login.php',
    'logout.php'
];

// Backup current .env in deployment
$envBackup = '';
if (file_exists($deploymentDir . '/.env')) {
    $envBackup = file_get_contents($deploymentDir . '/.env');
    echo "✓ Backed up current .env file\n";
}

// Clear deployment directory (except uploads and .env)
if (is_dir($deploymentDir)) {
    foreach (scandir($deploymentDir) as $item) {
        if ($item != '.' && $item != '..' && $item != 'uploads' && $item != '.env') {
            $path = $deploymentDir . '/' . $item;
            if (is_dir($path)) {
                removeDirectory($path);
            } else {
                unlink($path);
            }
        }
    }
    echo "✓ Cleaned deployment directory\n";
}

// Copy updated files
foreach ($itemsToSync as $item) {
    if (file_exists($item)) {
        if (is_dir($item)) {
            copyDirectory($item, $deploymentDir . '/' . $item);
            echo "✓ Updated directory: $item\n";
        } else {
            copy($item, $deploymentDir . '/' . $item);
            echo "✓ Updated file: $item\n";
        }
    }
}

// Restore .env file
if ($envBackup) {
    file_put_contents($deploymentDir . '/.env', $envBackup);
    echo "✓ Restored .env file with your database credentials\n";
}

// Copy .htaccess if exists
if (file_exists('htaccess-template')) {
    copy('htaccess-template', $deploymentDir . '/.htaccess');
    echo "✓ Updated .htaccess file\n";
}

// Create update ZIP
$zipFile = 'elms-update-' . date('Y-m-d-H-i-s') . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($deploymentDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen(realpath($deploymentDir)) + 1);
        
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    $zip->close();
    echo "✓ Created update ZIP: $zipFile\n";
    echo "📦 File size: " . formatBytes(filesize($zipFile)) . "\n\n";
}

echo "=== Update Complete! ===\n";
echo "Options to deploy:\n";
echo "1. Upload '$zipFile' to InfinityFree and extract\n";
echo "2. Use FTP to sync 'deployment' folder\n";
echo "3. Manually upload changed files\n\n";

// Show what was updated
echo "Files updated in this deployment:\n";
foreach ($itemsToSync as $item) {
    if (file_exists($item)) {
        echo "- $item\n";
    }
}

function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function removeDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    removeDirectory($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>