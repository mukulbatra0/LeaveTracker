<?php
// Script to fix all hardcoded /ELMS/ paths in the codebase

// Function to recursively get all PHP files
function get_php_files($dir) {
    $files = [];
    $scan = scandir($dir);
    
    foreach ($scan as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            $files = array_merge($files, get_php_files($path));
        } else if (pathinfo($path, PATHINFO_EXTENSION) === 'php' || pathinfo($path, PATHINFO_EXTENSION) === 'js') {
            $files[] = $path;
        }
    }
    
    return $files;
}

// Function to fix paths in a file
function fix_paths($file) {
    $content = file_get_contents($file);
    $original = $content;
    
    // Replace /ELMS/ with / in PHP files
    $content = str_replace('"/', '"/', $content);
    $content = str_replace("'/", "'/", $content);
    
    // Only write if changes were made
    if ($content !== $original) {
        file_put_contents($file, $content);
        return true;
    }
    
    return false;
}

// Get all PHP files
$base_dir = __DIR__;
$files = get_php_files($base_dir);

// Fix paths in each file
$fixed_count = 0;
foreach ($files as $file) {
    if (fix_paths($file)) {
        echo "Fixed paths in: " . str_replace($base_dir, '', $file) . "\n";
        $fixed_count++;
    }
}

echo "\nCompleted! Fixed paths in {$fixed_count} files.\n";
?>