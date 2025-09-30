<?php
/**
 * Path Helper Functions for LeaveTracker
 * This file provides utility functions for consistent path handling
 */

// Get the project root directory
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

/**
 * Get the correct relative path from current location to target directory
 * @param string $target Target directory relative to project root
 * @return string Correct relative path
 */
function getCorrectPath($target) {
    // Get current script directory
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $currentDir = dirname($currentScript);
    
    // Count directory levels from root
    $levels = substr_count(trim($currentDir, '/'), '/');
    
    // Build relative path
    $relativePath = str_repeat('../', $levels);
    
    return $relativePath . ltrim($target, '/');
}

/**
 * Get asset path (CSS, JS, Images)
 * @param string $asset Asset path relative to project root
 * @return string Correct asset path
 */
function getAssetPath($asset) {
    return getCorrectPath($asset);
}

/**
 * Get include path for PHP files
 * @param string $file File path relative to project root
 * @return string Correct include path
 */
function getIncludePath($file) {
    return PROJECT_ROOT . '/' . ltrim($file, '/');
}

/**
 * Get URL path for links and redirects
 * @param string $path Path relative to project root
 * @return string Correct URL path
 */
function getUrlPath($path) {
    // Get the base URL path
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname($scriptName);
    
    // Remove project folder name if it exists in the path
    $projectFolder = basename(PROJECT_ROOT);
    if (strpos($basePath, $projectFolder) !== false) {
        $basePath = substr($basePath, 0, strpos($basePath, $projectFolder) + strlen($projectFolder));
    }
    
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect to a path within the project
 * @param string $path Path relative to project root
 */
function redirectTo($path) {
    $url = getUrlPath($path);
    header("Location: $url");
    exit;
}

/**
 * Check if current page matches a path
 * @param string $path Path to check
 * @return bool True if current page matches
 */
function isCurrentPage($path) {
    $currentPath = $_SERVER['REQUEST_URI'];
    $checkPath = getUrlPath($path);
    return strpos($currentPath, $checkPath) !== false;
}

/**
 * Get navigation class for active menu items
 * @param string $path Path to check
 * @param string $activeClass Class to return if active
 * @return string Active class or empty string
 */
function getNavClass($path, $activeClass = 'active') {
    return isCurrentPage($path) ? $activeClass : '';
}

// Common path constants for easy access
define('CONFIG_DIR', 'config');
define('INCLUDES_DIR', 'includes');
define('MODULES_DIR', 'modules');
define('ADMIN_DIR', 'admin');
define('DASHBOARDS_DIR', 'dashboards');
define('REPORTS_DIR', 'reports');
define('AUTH_DIR', 'auth');
define('CSS_DIR', 'css');
define('JS_DIR', 'js');
define('IMAGES_DIR', 'images');
define('UPLOADS_DIR', 'uploads');
define('CLASSES_DIR', 'classes');

/**
 * Get common directory paths
 */
function getConfigPath($file = '') {
    return getIncludePath(CONFIG_DIR . '/' . $file);
}

function getIncludesPath($file = '') {
    return getIncludePath(INCLUDES_DIR . '/' . $file);
}

function getModulesPath($file = '') {
    return getIncludePath(MODULES_DIR . '/' . $file);
}

function getAdminPath($file = '') {
    return getIncludePath(ADMIN_DIR . '/' . $file);
}

function getDashboardsPath($file = '') {
    return getIncludePath(DASHBOARDS_DIR . '/' . $file);
}

function getReportsPath($file = '') {
    return getIncludePath(REPORTS_DIR . '/' . $file);
}

function getAuthPath($file = '') {
    return getIncludePath(AUTH_DIR . '/' . $file);
}

function getClassesPath($file = '') {
    return getIncludePath(CLASSES_DIR . '/' . $file);
}

function getUploadsPath($file = '') {
    return getIncludePath(UPLOADS_DIR . '/' . $file);
}

/**
 * Get URL paths for navigation
 */
function getConfigUrl($file = '') {
    return getUrlPath(CONFIG_DIR . '/' . $file);
}

function getModulesUrl($file = '') {
    return getUrlPath(MODULES_DIR . '/' . $file);
}

function getAdminUrl($file = '') {
    return getUrlPath(ADMIN_DIR . '/' . $file);
}

function getDashboardsUrl($file = '') {
    return getUrlPath(DASHBOARDS_DIR . '/' . $file);
}

function getReportsUrl($file = '') {
    return getUrlPath(REPORTS_DIR . '/' . $file);
}

function getAuthUrl($file = '') {
    return getUrlPath(AUTH_DIR . '/' . $file);
}

function getCssUrl($file = '') {
    return getUrlPath(CSS_DIR . '/' . $file);
}

function getJsUrl($file = '') {
    return getUrlPath(JS_DIR . '/' . $file);
}

function getImagesUrl($file = '') {
    return getUrlPath(IMAGES_DIR . '/' . $file);
}

function getUploadsUrl($file = '') {
    return getUrlPath(UPLOADS_DIR . '/' . $file);
}
?>