<?php
/**
 * Update Leave Types with Actual Data from TITS Leave Rules
 * This script replaces mock data with actual leave types from the official leave rules document
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

class LeaveTypeUpdater {
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
            $this->conn = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            $this->output("Connected to database successfully", 'success');
            return true;
            
        } catch(PDOException $e) {
            $this->output("Database connection failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function clearExistingLeaveTypes() {
        try {
            // Delete existing leave balances first (foreign key constraint)
            $this->conn->exec("DELETE FROM leave_balances");
            $this->output("Cleared existing leave balances", 'success');
            
            // Delete existing leave types
            $this->conn->exec("DELETE FROM leave_types");
            $this->output("Cleared existing leave types", 'success');
            
            return true;
        } catch (PDOException $e) {
            $this->output("Error clearing data: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function insertLeaveTypes() {
        $this->output("Inserting leave types from TITS Leave Rules...", 'info');
        
        // Leave types based on the official TITS Leave Rules document
        $leaveTypes = [
            // Teaching Staff Leave Types (Section 12)
            [
                'name' => 'Casual Leave',
                'description' => 'Non-teaching: 15 days, Teaching: 10 days per calendar year. Cannot be combined with other leaves except special casual and academic leave. Cannot be carried forward.',
                'max_days' => 15,
                'default_days' => 15,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 0,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#28a745',
                'is_active' => 1
            ],
            [
                'name' => 'Special Casual Leave',
                'description' => 'For sterilization operations (6 days) or non-puereperal sterilization (14 days). Cannot be accumulated or combined with other leaves except casual leave.',
                'max_days' => 14,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#17a2b8',
                'is_active' => 1
            ],
            [
                'name' => 'Earned Leave',
                'description' => 'One-eleventh of period spent on duty. Maximum 120 days can be sanctioned at a time. Ceases to earn on full pay when leave due amounts to 180 days.',
                'max_days' => 120,
                'default_days' => 30,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 0,
                'is_paid' => 1,
                'accrual_rate' => 2.5,
                'carry_forward' => 1,
                'max_carry_forward_days' => 180,
                'color' => '#007bff',
                'is_active' => 1
            ],
            [
                'name' => 'Half Pay Leave',
                'description' => 'Must be granted to permanent employee for 20 days per completed year of service upon submission of Medical Certificate issued by MBBS Doctor.',
                'max_days' => 20,
                'default_days' => 20,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => 1.67,
                'carry_forward' => 1,
                'max_carry_forward_days' => NULL,
                'color' => '#ffc107',
                'is_active' => 1
            ],
            [
                'name' => 'Commuted Leave',
                'description' => 'Must be granted only upon submission of Medical Certificate issued by MBBS Doctor. Cannot be granted for less than 3 days. Maximum 180 days during entire service.',
                'max_days' => 180,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#fd7e14',
                'is_active' => 1
            ],
            [
                'name' => 'Maternity Leave',
                'description' => 'Up to two living children may be granted by competent authority on full pay for four months. Not debited to leave account.',
                'max_days' => 120,
                'default_days' => 120,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#e83e8c',
                'is_active' => 1
            ],
            [
                'name' => 'Quarantine Leave',
                'description' => 'For infectious diseases (small pox, cholera, plague, etc). Maximum one month on production of certificate from Chief Medical Officer.',
                'max_days' => 30,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department,director,admin',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#dc3545',
                'is_active' => 1
            ],
            [
                'name' => 'Hospital Leave',
                'description' => 'For medical treatment for injury directly due to risks incurred in official duty. Limited to 3 months in any period of 3 years. Can be on full or half pay.',
                'max_days' => 90,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department,director,admin',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#6610f2',
                'is_active' => 1
            ],
            [
                'name' => 'Leave Not Due',
                'description' => 'Advance leave when employee\'s leave account shows nil/debit balance. Maximum 180 days during entire service. Debited against half pay leave.',
                'max_days' => 180,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#6c757d',
                'is_active' => 1
            ],
            [
                'name' => 'Extraordinary Leave',
                'description' => 'Without pay and allowances. Shall not ordinarily exceed one year at a time. Does not count for increment except for specific cases (illness, higher studies, teaching post).',
                'max_days' => 365,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department,director,admin',
                'requires_attachment' => 1,
                'is_paid' => 0,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#495057',
                'is_active' => 1
            ],
            [
                'name' => 'Academic Leave',
                'description' => 'Not exceeding 10 days in a calendar year for teachers. For examinations, inspections, meetings, conferences, seminars. Cannot be accumulated or combined except with casual leave.',
                'max_days' => 10,
                'default_days' => 10,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 0,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#20c997',
                'is_active' => 1
            ],
            [
                'name' => 'Duty Leave',
                'description' => 'For attending conferences, delivering lectures, working in delegations appointed by Govt/Institute. Maximum 15 days by competent authority, beyond 15 days by TITS MC.',
                'max_days' => 15,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department,director,admin',
                'requires_attachment' => 0,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#0dcaf0',
                'is_active' => 1
            ],
            [
                'name' => 'Study Leave (Without Pay)',
                'description' => 'For staff with 2+ years service for higher studies. Up to 2 years (3 years if course duration is more). No increment admissible during study leave.',
                'max_days' => 730,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 0,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#6f42c1',
                'is_active' => 1
            ],
            [
                'name' => 'Study Leave (With Pay)',
                'description' => 'For confirmed whole-time teacher with 5+ years service. Ordinarily 1 year, can be extended to 2 years. Salary at absolute discretion of Management.',
                'max_days' => 365,
                'default_days' => 0,
                'applicable_to' => 'staff,head_of_department',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#d63384',
                'is_active' => 1
            ],
            [
                'name' => 'Sabbatical Leave',
                'description' => 'For Professors with 3+ years service for study/research. Duration not exceeding 1-2 semesters. Normal increment allowed. Period counts as regular service.',
                'max_days' => 365,
                'default_days' => 0,
                'applicable_to' => 'head_of_department,director',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#198754',
                'is_active' => 1
            ],
            // Non-teaching Staff Additional Leave Types (Section 13)
            [
                'name' => 'Leave for Anti-Rabic Treatment',
                'description' => 'For non-teaching staff. Maximum one month on production of certificate from medical or Public Health Officer. Employee considered on duty.',
                'max_days' => 30,
                'default_days' => 0,
                'applicable_to' => 'staff',
                'requires_attachment' => 1,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#fd7e14',
                'is_active' => 1
            ],
            [
                'name' => 'Compensatory Leave',
                'description' => 'For non-teaching staff not above rank of Assistant. For attending office on Sundays/holidays for not less than half day under written order of director.',
                'max_days' => 30,
                'default_days' => 0,
                'applicable_to' => 'staff',
                'requires_attachment' => 0,
                'is_paid' => 1,
                'accrual_rate' => NULL,
                'carry_forward' => 0,
                'max_carry_forward_days' => 0,
                'color' => '#0d6efd',
                'is_active' => 1
            ]
        ];
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO leave_types (
                    name, description, max_days, default_days, applicable_to, 
                    requires_attachment, is_paid, accrual_rate, carry_forward, 
                    max_carry_forward_days, color, is_active
                ) VALUES (
                    :name, :description, :max_days, :default_days, :applicable_to,
                    :requires_attachment, :is_paid, :accrual_rate, :carry_forward,
                    :max_carry_forward_days, :color, :is_active
                )
            ");
            
            foreach ($leaveTypes as $leaveType) {
                $stmt->execute($leaveType);
                $this->output("Inserted: " . $leaveType['name'], 'success');
            }
            
            return true;
            
        } catch (PDOException $e) {
            $this->output("Error inserting leave types: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function recreateLeaveBalances() {
        $this->output("Recreating leave balances for all users...", 'info');
        
        try {
            $this->conn->exec("
                INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                SELECT 
                    u.id,
                    lt.id,
                    YEAR(CURDATE()),
                    lt.default_days,
                    0
                FROM users u
                CROSS JOIN leave_types lt
                WHERE u.role IN ('staff', 'head_of_department', 'director', 'admin')
                AND FIND_IN_SET(u.role, lt.applicable_to) > 0
            ");
            
            $this->output("Leave balances recreated successfully", 'success');
            return true;
            
        } catch (PDOException $e) {
            $this->output("Error creating leave balances: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function verifyUpdate() {
        $this->output("Verifying update...", 'info');
        
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM leave_types");
            $count = $stmt->fetch()['count'];
            $this->output("Total leave types in database: $count", 'success');
            
            $stmt = $this->conn->query("SELECT name FROM leave_types ORDER BY name");
            $types = $stmt->fetchAll();
            
            $this->output("Leave types:", 'info');
            foreach ($types as $type) {
                $this->output("  - " . $type['name'], 'info');
            }
            
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM leave_balances");
            $balance_count = $stmt->fetch()['count'];
            $this->output("Total leave balances created: $balance_count", 'success');
            
            return true;
            
        } catch (Exception $e) {
            $this->output("Error during verification: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function runUpdate() {
        if (!$this->connect()) {
            return false;
        }
        
        if (!$this->clearExistingLeaveTypes()) {
            return false;
        }
        
        if (!$this->insertLeaveTypes()) {
            return false;
        }
        
        if (!$this->recreateLeaveBalances()) {
            return false;
        }
        
        return $this->verifyUpdate();
    }
}

// Web interface execution
$updater = new LeaveTypeUpdater(true);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $success = $updater->runUpdate();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Leave Types - ELMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .update-container { max-width: 900px; margin: 0 auto; }
        .update-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .update-header { text-align: center; padding: 20px 0; }
        .update-logo { font-size: 2.5rem; color: #0d6efd; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="update-container">
        <div class="card update-card">
            <div class="card-body">
                <div class="update-header">
                    <div class="update-logo"><i class="fas fa-sync-alt"></i></div>
                    <h2>Update Leave Types</h2>
                    <p class="text-muted">Replace mock data with actual TITS Leave Rules</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Update Complete!</h4>
                        <p>Leave types have been successfully updated with actual data from TITS Leave Rules document.</p>
                        <hr>
                        <p class="mb-0">All leave balances have been recreated for existing users.</p>
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-primary">Go to Login Page</a>
                            <a href="admin/leave_types.php" class="btn btn-secondary">View Leave Types</a>
                        </div>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Update Failed!</h4>
                        <p>Please check the error messages above.</p>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This will delete all existing leave types and leave balances, then insert new data based on TITS Leave Rules.
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">Leave Types to be Added</h5>
                            </div>
                            <div class="card-body">
                                <h6>Teaching Staff Leave Types:</h6>
                                <ul>
                                    <li>Casual Leave (10 days for teaching, 15 for non-teaching)</li>
                                    <li>Special Casual Leave</li>
                                    <li>Earned Leave</li>
                                    <li>Half Pay Leave</li>
                                    <li>Commuted Leave</li>
                                    <li>Maternity Leave</li>
                                    <li>Quarantine Leave</li>
                                    <li>Hospital Leave</li>
                                    <li>Leave Not Due</li>
                                    <li>Extraordinary Leave</li>
                                    <li>Academic Leave</li>
                                    <li>Duty Leave</li>
                                    <li>Study Leave (Without Pay)</li>
                                    <li>Study Leave (With Pay)</li>
                                    <li>Sabbatical Leave</li>
                                </ul>
                                
                                <h6>Non-Teaching Staff Additional Leave Types:</h6>
                                <ul>
                                    <li>Leave for Anti-Rabic Treatment</li>
                                    <li>Compensatory Leave</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="update" class="btn btn-primary btn-lg">
                                <i class="fas fa-sync-alt"></i> Update Leave Types
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
