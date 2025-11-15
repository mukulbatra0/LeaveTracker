<?php
/**
 * Add missing academic_calendar table to existing database
 */

// Include database connection
require_once 'config/db.php';

try {
    // Create academic_calendar table
    $sql = "
        CREATE TABLE IF NOT EXISTS `academic_calendar` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_name` varchar(255) NOT NULL,
            `start_date` date NOT NULL,
            `end_date` date NOT NULL,
            `description` text,
            `event_type` enum('semester','exam','holiday','break','registration','other') NOT NULL DEFAULT 'other',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $conn->exec($sql);
    echo "✅ Academic calendar table created successfully!\n";
    
    // Insert sample data
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    
    $sampleData = "
        INSERT IGNORE INTO `academic_calendar` (`event_name`, `start_date`, `end_date`, `description`, `event_type`) VALUES
        ('Fall Semester', '$currentYear-09-01', '$currentYear-12-15', 'Fall academic semester', 'semester'),
        ('Spring Semester', '$nextYear-01-15', '$nextYear-05-15', 'Spring academic semester', 'semester'),
        ('Winter Break', '$currentYear-12-16', '$nextYear-01-14', 'Winter holiday break', 'break'),
        ('Summer Break', '$nextYear-05-16', '$nextYear-08-31', 'Summer vacation break', 'break'),
        ('Final Exams - Fall', '$currentYear-12-01', '$currentYear-12-15', 'Fall semester final examinations', 'exam'),
        ('Final Exams - Spring', '$nextYear-05-01', '$nextYear-05-15', 'Spring semester final examinations', 'exam')
    ";
    
    $conn->exec($sampleData);
    echo "✅ Sample academic calendar events added!\n";
    
    // Verify the table exists and has data
    $stmt = $conn->query("SELECT COUNT(*) as count FROM academic_calendar");
    $count = $stmt->fetch()['count'];
    echo "✅ Academic calendar table now has $count events\n";
    
    echo "\n🎉 Academic calendar table setup complete! You can now access the Academic Calendar page.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>