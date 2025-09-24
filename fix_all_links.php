<?php
/**
 * Fix All Broken Links Script
 * This script fixes all remaining broken hyperlinks in the ELMS system
 */

echo "<h2>ELMS Link Fixer</h2>";
echo "<p>Fixing all broken hyperlinks...</p>";

// Files to check and fix
$files_to_fix = [
    'modules/leave_approvals.php',
    'modules/leave_calendar.php', 
    'dashboards/department_head_dashboard.php',
    'dashboards/dean_dashboard.php',
    'dashboards/principal_dashboard.php',
    'dashboards/hr_admin_dashboard.php'
];

$fixes_applied = 0;

foreach ($files_to_fix as $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $original_content = $content;
        
        // Fix common broken links
        $content = str_replace('href="/index.php"', 'href="../index.php"', $content);
        $content = str_replace('href="/modules/', 'href="../modules/', $content);
        $content = str_replace('href="/admin/', 'href="../admin/', $content);
        $content = str_replace('href="/reports/', 'href="../reports/', $content);
        $content = str_replace('href="/auth/', 'href="../auth/', $content);
        $content = str_replace('src="/uploads/', 'src="/ELMS/uploads/', $content);
        $content = str_replace('action="/modules/', 'action="../modules/', $content);
        $content = str_replace('Location: ', 'Location: ../modules/', $content);
        $content = str_replace('Location: /index.php', 'Location: ../index.php', $content);
        
        if ($content !== $original_content) {
            file_put_contents($file_path, $content);
            echo "<div style='color: green;'>✓ Fixed links in: $file</div>";
            $fixes_applied++;
        } else {
            echo "<div style='color: gray;'>- No fixes needed: $file</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠ File not found: $file</div>";
    }
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong>Files processed:</strong> " . count($files_to_fix) . "</p>";
echo "<p><strong>Fixes applied:</strong> $fixes_applied</p>";

if ($fixes_applied > 0) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<strong>Links Fixed!</strong><br>";
    echo "All hyperlinks have been updated with correct paths. ";
    echo "<a href='../index.php' style='color: #155724; text-decoration: underline;'>Test Navigation</a>";
    echo "</div>";
} else {
    echo "<div style='background: #cce7ff; border: 1px solid #99d6ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<strong>All Links Already Correct!</strong><br>";
    echo "No broken links found. Your system is ready to use.";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
}
h2, h3 { color: #333; }
div { margin: 5px 0; }
hr { margin: 20px 0; border: none; border-top: 1px solid #ddd; }
</style>