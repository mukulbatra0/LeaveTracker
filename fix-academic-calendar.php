<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Academic Calendar - ELMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .fix-container { max-width: 600px; margin: 0 auto; }
        .fix-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <div class="fix-container">
        <div class="card fix-card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                    <h2>Fix Academic Calendar</h2>
                    <p class="text-muted">Add missing academic_calendar table</p>
                </div>
                
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
                    try {
                        // Include database connection
                        require_once 'config/db.php';
                        
                        echo '<div class="alert alert-info">Creating academic_calendar table...</div>';
                        
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
                        echo '<div class="alert alert-success">✅ Academic calendar table created successfully!</div>';
                        
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
                        echo '<div class="alert alert-success">✅ Sample academic calendar events added!</div>';
                        
                        // Verify the table exists and has data
                        $stmt = $conn->query("SELECT COUNT(*) as count FROM academic_calendar");
                        $count = $stmt->fetch()['count'];
                        echo '<div class="alert alert-success">✅ Academic calendar table now has ' . $count . ' events</div>';
                        
                        echo '<div class="alert alert-success">
                                <h4 class="alert-heading">Fix Complete!</h4>
                                <p>The academic calendar table has been created successfully. You can now access the Academic Calendar page.</p>
                                <hr>
                                <div class="mt-3">
                                    <a href="/admin/academic_calendar.php" class="btn btn-primary">Go to Academic Calendar</a>
                                    <a href="/index.php" class="btn btn-secondary">Back to Dashboard</a>
                                </div>
                              </div>';
                        
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">
                                <h4 class="alert-heading">Error!</h4>
                                <p>Database error: ' . htmlspecialchars($e->getMessage()) . '</p>
                                <p>Please check your database connection and try again.</p>
                              </div>';
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">
                                <h4 class="alert-heading">Error!</h4>
                                <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
                              </div>';
                    }
                } else {
                ?>
                    <form method="post">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Missing Table Detected</strong><br>
                            The academic_calendar table is missing from your database. This will create the table and add sample data.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="fix" class="btn btn-primary btn-lg">
                                <i class="fas fa-wrench"></i> Fix Academic Calendar Table
                            </button>
                            <a href="/index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>