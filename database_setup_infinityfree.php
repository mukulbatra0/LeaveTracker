<?php
/**
 * InfinityFree Compatible Database Setup Script for ELMS
 * This version works with InfinityFree hosting restrictions
 */

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
define('DB_SERVER', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USERNAME', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'elms_db');

class InfinityFreeSetup {
    private $conn;
    private $isWebInterface = false;
    
    public function __construct($webInterface = false) {
        $this->isWebInterface = $webInterface;
    }
    
    private function output($message, $type = 'info') {
        if ($this->isWebInterface) {
            $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');
            echo "<div class='alert $class'>$message</div>";
        } else {
            $prefix = $type === 'success' ? '✓' : ($type === 'error' ? '✗' : '•');
            echo "$prefix $message\n";
        }
    }
    
    public function connect() {
        try {
            // Connect directly to the database (InfinityFree databases are pre-created)
            $this->conn = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            $this->output("Connected to database '" . DB_NAME . "' successfully", 'success');
            return true;
            
        } catch(PDOException $e) {
            $this->output("Database connection failed: " . $e->getMessage(), 'error');
            $this->output("Please check your database credentials in .env file", 'error');
            return false;
        }
    }
    
    private function executeSQL($sql, $description) {
        try {
            $this->conn->exec($sql);
            $this->output($description, 'success');
            return true;
        } catch (PDOException $e) {
            $this->output("$description - Error: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function tableExists($tableName) {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE '$tableName'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function createTables() {
        $this->output("Creating database tables...", 'info');
        
        // Create departments table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `departments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `code` varchar(20) NOT NULL,
                `head_id` int(11) DEFAULT NULL,
                `description` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating departments table");
        
        // Create users table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employee_id` varchar(20) NOT NULL,
                `first_name` varchar(50) NOT NULL,
                `last_name` varchar(50) NOT NULL,
                `email` varchar(100) NOT NULL,
                `password` varchar(255) NOT NULL,
                `role` enum('staff','head_of_department','director','admin') NOT NULL,
                `department_id` int(11) NOT NULL,
                `position` varchar(100) NOT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `address` text DEFAULT NULL,
                `emergency_contact` varchar(255) DEFAULT NULL,
                `profile_image` varchar(255) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                PRIMARY KEY (`id`),
                UNIQUE KEY `employee_id` (`employee_id`),
                UNIQUE KEY `email` (`email`),
                KEY `department_id` (`department_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating users table");
        
        // Create leave_types table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `leave_types` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `description` text,
                `max_days` int(11) NOT NULL,
                `default_days` int(11) DEFAULT 0,
                `applicable_to` set('staff','head_of_department','director','admin') NOT NULL,
                `requires_attachment` tinyint(1) NOT NULL DEFAULT '0',
                `is_paid` tinyint(1) NOT NULL DEFAULT '1',
                `accrual_rate` decimal(10,2) DEFAULT NULL COMMENT 'Days per month',
                `carry_forward` tinyint(1) NOT NULL DEFAULT '0',
                `max_carry_forward_days` int(11) DEFAULT NULL,
                `color` varchar(7) DEFAULT '#007bff',
                `is_active` tinyint(1) NOT NULL DEFAULT '1',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating leave_types table");
        
        // Create leave_applications table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `leave_applications` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `leave_type_id` int(11) NOT NULL,
                `start_date` date NOT NULL,
                `end_date` date NOT NULL,
                `days` decimal(10,2) NOT NULL,
                `working_days` decimal(10,2) DEFAULT NULL,
                `reason` text NOT NULL,
                `attachment` varchar(255) DEFAULT NULL,
                `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `leave_type_id` (`leave_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating leave_applications table");
        
        // Create leave_approvals table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `leave_approvals` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `leave_application_id` int(11) NOT NULL,
                `approver_id` int(11) NOT NULL,
                `approver_level` enum('head_of_department','director','admin') NOT NULL,
                `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `comments` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `leave_application_id` (`leave_application_id`),
                KEY `approver_id` (`approver_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating leave_approvals table");
        
        // Create leave_balances table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `leave_balances` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `leave_type_id` int(11) NOT NULL,
                `year` int(4) NOT NULL,
                `total_days` decimal(10,2) NOT NULL DEFAULT '0.00',
                `used_days` decimal(10,2) NOT NULL DEFAULT '0.00',
                `last_accrual_date` date DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_leave_year` (`user_id`,`leave_type_id`,`year`),
                KEY `leave_type_id` (`leave_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating leave_balances table");
        
        // Create notifications table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text NOT NULL,
                `related_to` varchar(50) DEFAULT NULL,
                `related_id` int(11) DEFAULT NULL,
                `is_read` tinyint(1) NOT NULL DEFAULT '0',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating notifications table");
        
        // Create holidays table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `holidays` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `date` date NOT NULL,
                `description` text,
                `type` enum('national','institutional','optional') NOT NULL DEFAULT 'institutional',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating holidays table");
        
        // Create documents table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `documents` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `leave_application_id` int(11) NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `file_path` varchar(255) NOT NULL,
                `file_type` varchar(100) NOT NULL,
                `file_size` int(11) NOT NULL,
                `uploaded_by` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `leave_application_id` (`leave_application_id`),
                KEY `uploaded_by` (`uploaded_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating documents table");
        
        // Create audit_logs table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `action` varchar(100) NOT NULL,
                `entity_type` varchar(50) NOT NULL,
                `entity_id` int(11) DEFAULT NULL,
                `details` text,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `entity_type` (`entity_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating audit_logs table");
        
        // Create system_settings table
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS `system_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text NOT NULL,
                `description` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", "Creating system_settings table");
        
        // Create academic_calendar table
        $this->executeSQL("
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
        ", "Creating academic_calendar table");
    }
    
    public function insertDefaultData() {
        $this->output("Inserting default data...", 'info');
        
        // Insert departments
        $this->executeSQL("
            INSERT IGNORE INTO `departments` (`name`, `code`, `description`) VALUES
            ('Computer Science', 'CSE', 'Department of Computer Science and Engineering'),
            ('Mathematics', 'MATH', 'Department of Mathematics'),
            ('Physics', 'PHY', 'Department of Physics'),
            ('Administration', 'ADMIN', 'Administrative Department'),
            ('Human Resources', 'HR', 'Human Resources Department')
        ", "Inserting default departments");
        
        // Insert leave types
        $this->executeSQL("
            INSERT IGNORE INTO `leave_types` (`name`, `description`, `max_days`, `default_days`, `applicable_to`, `requires_attachment`, `is_paid`, `accrual_rate`, `carry_forward`, `max_carry_forward_days`, `color`) VALUES
            ('Annual Leave', 'Annual vacation leave', 21, 21, 'staff,head_of_department,director,admin', 0, 1, 1.75, 1, 5, '#28a745'),
            ('Sick Leave', 'Medical leave for illness', 10, 10, 'staff,head_of_department,director,admin', 1, 1, 0.83, 0, 0, '#dc3545'),
            ('Personal Leave', 'Personal time off', 5, 5, 'staff,head_of_department,director,admin', 0, 1, 0.42, 0, 0, '#ffc107'),
            ('Emergency Leave', 'Emergency situations', 3, 3, 'staff,head_of_department,director,admin', 0, 1, 0.25, 0, 0, '#fd7e14'),
            ('Maternity Leave', 'Leave for childbirth and childcare', 180, 180, 'staff,head_of_department,director,admin', 1, 1, NULL, 0, 0, '#e83e8c'),
            ('Paternity Leave', 'Leave for new fathers', 15, 15, 'staff,head_of_department,director,admin', 0, 1, NULL, 0, 0, '#17a2b8'),
            ('Bereavement Leave', 'Leave for family emergencies or deaths', 7, 7, 'staff,head_of_department,director,admin', 0, 1, NULL, 0, 0, '#6c757d')
        ", "Inserting default leave types");
        
        // Insert system settings
        $this->executeSQL("
            INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
            ('system_name', 'ELMS - Employee Leave Management System', 'Name of the system'),
            ('college_name', 'Sample College', 'Name of the college'),
            ('fiscal_year_start', '04-01', 'Start date of fiscal year (MM-DD)'),
            ('fiscal_year_end', '03-31', 'End date of fiscal year (MM-DD)'),
            ('leave_approval_levels', 'head_of_department,director,admin', 'Approval levels in sequence'),
            ('enable_email_notifications', '1', 'Enable email notifications'),
            ('enable_sms_notifications', '0', 'Enable SMS notifications'),
            ('max_attachment_size', '5', 'Maximum attachment size in MB'),
            ('allowed_attachment_types', 'pdf,doc,docx,jpg,jpeg,png', 'Allowed attachment file types'),
            ('auto_approve_after_days', '7', 'Auto-approve leave requests if not actioned within days'),
            ('approval_flow_description', 'Staff applications go to Head of Department first, then to Director for final approval', 'Description of the approval workflow')
        ", "Inserting system settings");
        
        // Insert sample academic calendar events
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        $this->executeSQL("
            INSERT IGNORE INTO `academic_calendar` (`event_name`, `start_date`, `end_date`, `description`, `event_type`) VALUES
            ('Fall Semester', '$currentYear-09-01', '$currentYear-12-15', 'Fall academic semester', 'semester'),
            ('Spring Semester', '$nextYear-01-15', '$nextYear-05-15', 'Spring academic semester', 'semester'),
            ('Winter Break', '$currentYear-12-16', '$nextYear-01-14', 'Winter holiday break', 'break'),
            ('Summer Break', '$nextYear-05-16', '$nextYear-08-31', 'Summer vacation break', 'break'),
            ('Final Exams - Fall', '$currentYear-12-01', '$currentYear-12-15', 'Fall semester final examinations', 'exam'),
            ('Final Exams - Spring', '$nextYear-05-01', '$nextYear-05-15', 'Spring semester final examinations', 'exam')
        ", "Inserting sample academic calendar events");
    }
    
    public function createTestUsers() {
        $this->output("Creating test users...", 'info');
        
        // Default password hash for 'password123'
        $defaultPassword = '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q';
        
        $this->executeSQL("
            INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
            ('ADM001', 'System', 'Administrator', 'admin@college.edu', '$defaultPassword', 'admin', 4, 'System Administrator', '+1-555-0400', 'active'),
            ('DIR001', 'Dr. Alexandra', 'Thompson', 'director@college.edu', '$defaultPassword', 'director', 4, 'Director of Academic Affairs', '+1-555-0300', 'active'),
            ('HOD001', 'Dr. Richard', 'Parker', 'hod@college.edu', '$defaultPassword', 'head_of_department', 1, 'Head of Computer Science', '+1-555-0500', 'active'),
            ('STF001', 'Alice', 'Johnson', 'staff@college.edu', '$defaultPassword', 'staff', 1, 'Assistant Professor', '+1-555-0600', 'active'),
            ('STF002', 'Bob', 'Smith', 'bob@college.edu', '$defaultPassword', 'staff', 2, 'Associate Professor', '+1-555-0700', 'active')
        ", "Creating test users");
        
        // Update department heads
        $this->executeSQL("
            UPDATE departments SET head_id = (SELECT id FROM users WHERE employee_id = 'HOD001') WHERE code = 'CSE'
        ", "Setting department head for Computer Science");
    }
    
    public function createLeaveBalances() {
        $this->output("Creating leave balances for users...", 'info');
        
        $this->executeSQL("
            INSERT IGNORE INTO `leave_balances` (`user_id`, `leave_type_id`, `year`, `total_days`, `used_days`) 
            SELECT 
                u.id,
                lt.id,
                YEAR(CURDATE()),
                lt.default_days,
                0
            FROM users u
            CROSS JOIN leave_types lt
            WHERE u.role IN ('staff', 'head_of_department', 'director')
            AND FIND_IN_SET(u.role, lt.applicable_to) > 0
        ", "Creating leave balances for all users");
    }
    
    public function verifySetup() {
        $this->output("Verifying database setup...", 'info');
        
        try {
            // Check users
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM users");
            $user_count = $stmt->fetch()['count'];
            $this->output("Users in database: $user_count", 'success');
            
            // Check departments
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM departments");
            $dept_count = $stmt->fetch()['count'];
            $this->output("Departments in database: $dept_count", 'success');
            
            // Check leave types
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM leave_types");
            $leave_count = $stmt->fetch()['count'];
            $this->output("Leave types in database: $leave_count", 'success');
            
            // Check leave balances
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM leave_balances");
            $balance_count = $stmt->fetch()['count'];
            $this->output("Leave balances created: $balance_count", 'success');
            
            return true;
            
        } catch (Exception $e) {
            $this->output("Error during verification: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function runSetup() {
        if (!$this->connect()) {
            return false;
        }
        
        $this->createTables();
        $this->insertDefaultData();
        $this->createTestUsers();
        $this->createLeaveBalances();
        
        return $this->verifySetup();
    }
}

// Web interface execution
$setup = new InfinityFreeSetup(true);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    $success = $setup->runSetup();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS Database Setup - InfinityFree</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .setup-container { max-width: 800px; margin: 0 auto; }
        .setup-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .setup-header { text-align: center; padding: 20px 0; }
        .setup-logo { font-size: 2.5rem; color: #0d6efd; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="card setup-card">
            <div class="card-body">
                <div class="setup-header">
                    <div class="setup-logo"><i class="fas fa-database"></i></div>
                    <h2>ELMS Database Setup</h2>
                    <p class="text-muted">InfinityFree Compatible Version</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Setup Complete!</h4>
                        <p>The database has been configured successfully. You can now use the system.</p>
                        <hr>
                        <p class="mb-0">Default login credentials:</p>
                        <ul>
                            <li><strong>Admin:</strong> admin@college.edu / password123</li>
                            <li><strong>Director:</strong> director@college.edu / password123</li>
                            <li><strong>HOD:</strong> hod@college.edu / password123</li>
                            <li><strong>Staff:</strong> staff@college.edu / password123</li>
                        </ul>
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-primary">Go to Login Page</a>
                        </div>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Setup Failed!</h4>
                        <p>Please check the error messages above and verify your database credentials.</p>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This will create all necessary database tables and insert default data.
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Database Configuration:</strong><br>
                            Host: <?php echo DB_SERVER; ?><br>
                            Database: <?php echo DB_NAME; ?><br>
                            User: <?php echo DB_USERNAME; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="setup" class="btn btn-primary btn-lg">
                                <i class="fas fa-play"></i> Setup Database
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>