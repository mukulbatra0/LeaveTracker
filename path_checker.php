<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$rootDir = __DIR__;
$errors = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir));
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    
    // Skip vendor, .git, etc
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($path, DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR) !== false) continue;
    
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext !== 'php' && $ext !== 'html' && $ext !== 'js' && $ext !== 'css') continue;
    if (basename($path) == 'path_checker.php') continue;
    
    $content = file_get_contents($path);
    
    // 1. PHP relative requires
    if (preg_match_all('/(?:require|include)(?:_once)?\s*(?:\(\s*)?[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
        foreach ($matches[1] as $match) {
            // Ignore variables or standard library
            if (strpos($match, '$') !== false || strpos($match, '://') !== false) continue;
            // Does this file exist relative to the current file?
            $dir = dirname($path);
            $check = $dir . DIRECTORY_SEPARATOR . ltrim($match, '/\\');
            if (!file_exists($check)) {
                // Also check if it exists relative to include_path, which is usually rootDir or something.
                $check2 = __DIR__ . DIRECTORY_SEPARATOR . ltrim($match, '/\\');
                if (!file_exists($check) && !file_exists($check2)) {
                    $errors[] = "[PHP_REQUIRE] Broken link in $path => requires $match";
                }
            }
        }
    }
    
    // 2. HTML href/src/action
    if (preg_match_all('/(?:href|src|action)\s*=\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
        foreach ($matches[1] as $match) {
            if (empty(trim($match))) continue;
            if (preg_match('/^(http|https|mailto|tel|javascript|data|\/\/|\#)/', $match)) continue;
            if (strpos($match, '<?') !== false || strpos($match, '{{') !== false) continue; // Dynamic
            
            // Strip query string and fragment
            $matchClean = explode('?', $match)[0];
            $matchClean = explode('#', $matchClean)[0];
            if (empty($matchClean)) continue;

            $check = '';
            if (strpos($matchClean, '/') === 0) {
                // Absolute path relative to htdocs / Document root
                // we assume rootDir is inside htdocs
                // maybe it's /LeaveTracker/
                if (strpos($matchClean, '/LeaveTracker/') === 0) {
                    $check = $rootDir . str_replace('/LeaveTracker', '', $matchClean);
                } else {
                    $check = dirname($rootDir) . $matchClean;
                }
            } else {
                // Relative path
                $check = dirname($path) . DIRECTORY_SEPARATOR . $matchClean;
            }
            
            if (!file_exists($check)) {
                $errors[] = "[HTML_PATH] Broken link in $path => references $matchClean";
            }
        }
    }
}

if (empty($errors)) {
    echo "No broken paths found in the project!\n";
} else {
    echo "Found " . count($errors) . " potential broken paths:\n";
    foreach ($errors as $e) {
        echo $e . "\n";
    }
}
?>
