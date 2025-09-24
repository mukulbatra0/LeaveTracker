<?php
/**
 * Security Helper Functions
 */

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error occurred.';
        return $errors;
    }
    
    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if ($fileSize > $maxSize) {
        $errors[] = 'File size exceeds maximum limit.';
    }
    
    if (!empty($allowedTypes) && !in_array($fileType, $allowedTypes)) {
        $errors[] = 'File type not allowed.';
    }
    
    // Check for malicious content
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    if (isset($allowedMimes[$fileType]) && $mimeType !== $allowedMimes[$fileType]) {
        $errors[] = 'File content does not match extension.';
    }
    
    return $errors;
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName, $userId = null) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $userPrefix = $userId ? "user_{$userId}_" : '';
    
    return $userPrefix . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $limit = 10, $window = 3600) {
    $key = $action . '_' . $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $data = $_SESSION['rate_limit'][$key];
    
    if ($now - $data['start'] > $window) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '', $userId = null) {
    $logFile = '../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $userId = $userId ?? ($_SESSION['user_id'] ?? 'anonymous');
    
    $logEntry = "[{$timestamp}] {$event} - User: {$userId}, IP: {$ip}, Details: {$details}, User-Agent: {$userAgent}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>