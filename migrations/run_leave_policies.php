<?php
/**
 * Migration Runner: Add Leave Policy Rules System
 * 
 * This script will:
 * 1. Add gender and employment_type columns to users table
 * 2. Create leave_policy_rules table
 * 3. Seed all leave policies based on institutional rules
 * 4. Update existing leave balances based on new policies
 */

require_once __DIR__ . '/../config/db.php';

echo "<html><head><title>Leave Policy Migration</title>
<style>
body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f4f6f9; }
.card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.success { color: #28a745; } .error { color: #dc3545; } .info { color: #17a2b8; } .warn { color: #ffc107; }
h1 { color: #2c3e50; } h2 { color: #34495e; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.step { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.step:last-child { border-bottom: none; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-error { background: #f8d7da; color: #721c24; }
.badge-skip { background: #fff3cd; color: #856404; }
</style></head><body>";

echo "<h1>🔄 Leave Policy Migration</h1>";
echo "<div class='card'>";
echo "<h2>Step 1: Alter Users Table</h2>";

try {
    // Check if gender column exists
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'gender'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other') NULL DEFAULT NULL AFTER staff_type");
        echo "<div class='step'><span class='badge badge-success'>✓ ADDED</span> <code>gender</code> column to users table</div>";
    } else {
        echo "<div class='step'><span class='badge badge-skip'>⏩ SKIPPED</span> <code>gender</code> column already exists</div>";
    }

    // Check if employment_type column exists
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'employment_type'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN employment_type ENUM('full_time', 'part_time') NOT NULL DEFAULT 'full_time' AFTER gender");
        echo "<div class='step'><span class='badge badge-success'>✓ ADDED</span> <code>employment_type</code> column to users table</div>";
    } else {
        echo "<div class='step'><span class='badge badge-skip'>⏩ SKIPPED</span> <code>employment_type</code> column already exists</div>";
    }
} catch (PDOException $e) {
    echo "<div class='step'><span class='badge badge-error'>✗ ERROR</span> " . $e->getMessage() . "</div>";
}

echo "</div>";

echo "<div class='card'>";
echo "<h2>Step 2: Create leave_policy_rules Table</h2>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS leave_policy_rules (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        leave_type_id INT(11) NOT NULL,
        staff_type VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'teaching, non_teaching, all',
        gender VARCHAR(10) NOT NULL DEFAULT 'all' COMMENT 'male, female, all',
        employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time' COMMENT 'full_time, part_time, all',
        allocated_days DECIMAL(10,2) NOT NULL DEFAULT 0,
        max_accumulation DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Max accumulation limit',
        max_at_once DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Max days sanctioned at one time',
        description TEXT NULL COMMENT 'Policy rule description/notes',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
        UNIQUE KEY unique_policy_rule (leave_type_id, staff_type, gender, employment_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<div class='step'><span class='badge badge-success'>✓ CREATED</span> <code>leave_policy_rules</code> table created successfully</div>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "<div class='step'><span class='badge badge-skip'>⏩ SKIPPED</span> Table already exists</div>";
    } else {
        echo "<div class='step'><span class='badge badge-error'>✗ ERROR</span> " . $e->getMessage() . "</div>";
    }
}

echo "</div>";

echo "<div class='card'>";
echo "<h2>Step 3: Seed Leave Policy Rules</h2>";

// Define all policies
$policies = [
    // ===== TEACHING STAFF =====
    // Casual Leave
    ['Casual Leave', 'teaching', 'male', 'full_time', 10, null, null, 'Casual Leave: 10 days per calendar year for male teaching staff'],
    ['Casual Leave', 'teaching', 'female', 'full_time', 20, null, null, 'Female Employee Casual Leave: 20 days per calendar year for female teaching staff'],
    
    // Academic Leave
    ['Academic Leave', 'teaching', 'all', 'full_time', 10, null, null, 'Academic Leave: 10 days per calendar year for teaching staff'],
    
    // Special Casual Leave
    ['Special Casual Leave', 'teaching', 'male', 'full_time', 6, null, null, 'Special Casual Leave (Sterilization/Vasectomy): 6 working days'],
    ['Special Casual Leave', 'teaching', 'female', 'full_time', 14, null, null, 'Special Casual Leave (Female Non-puerperal Sterilization): 14 days'],
    
    // Earned Leave
    ['Earned Leave', 'teaching', 'all', 'full_time', 30, 180, 120, 'Earned Leave: Accumulation limit of 180 days; max 120 days sanctioned at one time'],
    
    // Half Pay Leave
    ['Half Pay Leave', 'teaching', 'all', 'full_time', 20, null, null, 'Half Pay Leave: 20 days for each completed year of service'],
    
    // Commuted Leave
    ['Commuted Leave', 'teaching', 'all', 'full_time', 180, 180, null, 'Commuted Leave: Maximum of 180 days during entire service'],
    
    // Maternity Leave
    ['Maternity Leave', 'teaching', 'female', 'full_time', 120, null, null, 'Maternity Leave: 4 months for up to two living children'],
    
    // Quarantine Leave
    ['Quarantine Leave', 'teaching', 'all', 'full_time', 30, null, null, 'Quarantine Leave: Maximum of one month'],
    
    // Hospital Leave
    ['Hospital Leave', 'teaching', 'all', 'full_time', 90, 90, null, 'Hospital Leave: Limited to 3 months in any period of 3 years'],
    
    // Leave Not Due
    ['Leave Not Due', 'teaching', 'all', 'full_time', 180, 180, null, 'Leave Not Due: Maximum of 180 days during entire service'],
    
    // Extraordinary Leave
    ['Extraordinary Leave', 'teaching', 'all', 'full_time', 365, 1095, 365, 'Extraordinary Leave: Max 1 year at a time; total limit 3 years'],
    
    // Duty Leave
    ['Duty Leave', 'teaching', 'all', 'full_time', 15, null, null, 'Duty Leave: Up to 15 days (sanctioned by Director-Principal) or longer with Committee approval'],
    
    // Study Leave (Without Pay)
    ['Study Leave (Without Pay)', 'teaching', 'all', 'full_time', 730, 1095, null, 'Study Leave (Without Pay): 2 to 3 years'],
    
    // Study Leave (With Pay)
    ['Study Leave (With Pay)', 'teaching', 'all', 'full_time', 365, 730, null, 'Study Leave (With Pay): Ordinarily 1 year, maximum 2 years'],
    
    // Sabbatical Leave
    ['Sabbatical Leave', 'teaching', 'all', 'full_time', 180, 360, null, 'Sabbatical Leave: 1 or 2 semesters'],

    // ===== NON-TEACHING STAFF =====
    // Casual Leave
    ['Casual Leave', 'non_teaching', 'male', 'full_time', 15, null, null, 'Casual Leave: 15 days per calendar year for male non-teaching staff'],
    ['Casual Leave', 'non_teaching', 'female', 'full_time', 20, null, null, 'Female Employee Casual Leave: 20 days per calendar year for female non-teaching staff'],
    
    // Earned Leave
    ['Earned Leave', 'non_teaching', 'all', 'full_time', 30, 180, null, 'Earned Leave: 1/11th of duty period; accumulation limit 180 days'],
    
    // Maternity Leave
    ['Maternity Leave', 'non_teaching', 'female', 'full_time', 120, null, null, 'Maternity Leave: 4 months for up to two living children'],
    
    // Anti-Rabic Treatment Leave
    ['Leave for Anti-Rabic Treatment', 'non_teaching', 'all', 'full_time', 30, null, null, 'Anti-Rabic Treatment Leave: Maximum of one month'],
    
    // Half Pay Leave
    ['Half Pay Leave', 'non_teaching', 'all', 'full_time', 20, null, null, 'Half Pay Leave: 20 days per completed year of service'],
    
    // Quarantine Leave
    ['Quarantine Leave', 'non_teaching', 'all', 'full_time', 30, null, null, 'Quarantine Leave: Maximum of one month'],
    
    // Hospital Leave
    ['Hospital Leave', 'non_teaching', 'all', 'full_time', 90, 90, null, 'Hospital Leave: Limited to 3 months in any period of 3 years'],
    
    // Duty Leave
    ['Duty Leave', 'non_teaching', 'all', 'full_time', 15, null, null, 'Duty Leave: Up to 15 days'],
    
    // Extraordinary Leave
    ['Extraordinary Leave', 'non_teaching', 'all', 'full_time', 365, 1095, 365, 'Extraordinary Leave: Max 1 year at a time; total 3 years'],
    
    // Leave Not Due
    ['Leave Not Due', 'non_teaching', 'all', 'full_time', 180, 180, null, 'Leave Not Due: Maximum of 180 days during entire service'],
    
    // Commuted Leave
    ['Commuted Leave', 'non_teaching', 'all', 'full_time', 180, 180, null, 'Commuted Leave: Maximum of 180 days during entire service'],
    
    // Special Casual Leave
    ['Special Casual Leave', 'non_teaching', 'male', 'full_time', 6, null, null, 'Special Casual Leave (Sterilization/Vasectomy): 6 working days'],
    ['Special Casual Leave', 'non_teaching', 'female', 'full_time', 14, null, null, 'Special Casual Leave (Female Non-puerperal Sterilization): 14 days'],
    
    // ===== PART-TIME EMPLOYEES =====
    ['Casual Leave', 'teaching', 'all', 'part_time', 10, null, null, 'Part-Time Teachers: 10 days Casual Leave only'],
    ['Casual Leave', 'non_teaching', 'all', 'part_time', 15, null, null, 'Part-Time Non-Teaching: 15 days Casual Leave only'],
];

$inserted = 0;
$updated = 0;
$errors = 0;

$insert_sql = "INSERT INTO leave_policy_rules (leave_type_id, staff_type, gender, employment_type, allocated_days, max_accumulation, max_at_once, description) 
               SELECT id, :staff_type, :gender, :employment_type, :allocated_days, :max_accumulation, :max_at_once, :description
               FROM leave_types WHERE name = :leave_type_name
               ON DUPLICATE KEY UPDATE allocated_days = VALUES(allocated_days), max_accumulation = VALUES(max_accumulation), max_at_once = VALUES(max_at_once), description = VALUES(description)";
$insert_stmt = $conn->prepare($insert_sql);

foreach ($policies as $policy) {
    try {
        $insert_stmt->execute([
            ':leave_type_name' => $policy[0],
            ':staff_type' => $policy[1],
            ':gender' => $policy[2],
            ':employment_type' => $policy[3],
            ':allocated_days' => $policy[4],
            ':max_accumulation' => $policy[5],
            ':max_at_once' => $policy[6],
            ':description' => $policy[7],
        ]);
        
        if ($insert_stmt->rowCount() > 0) {
            $inserted++;
            echo "<div class='step'><span class='badge badge-success'>✓</span> {$policy[0]} — {$policy[1]} / {$policy[2]} / {$policy[3]}: <strong>{$policy[4]} days</strong></div>";
        } else {
            echo "<div class='step'><span class='badge badge-skip'>⏩</span> {$policy[0]} — {$policy[1]} / {$policy[2]} / {$policy[3]}: No matching leave type found</div>";
        }
    } catch (PDOException $e) {
        $errors++;
        echo "<div class='step'><span class='badge badge-error'>✗</span> {$policy[0]}: " . $e->getMessage() . "</div>";
    }
}

echo "<br><strong>Total: {$inserted} rules inserted/updated, {$errors} errors</strong>";
echo "</div>";

// Summary
echo "<div class='card'>";
echo "<h2>Step 4: Summary</h2>";

$count = $conn->query("SELECT COUNT(*) FROM leave_policy_rules")->fetchColumn();
echo "<div class='step'><strong>Total policy rules:</strong> {$count}</div>";

$rules = $conn->query("SELECT lpr.*, lt.name as leave_type_name 
                       FROM leave_policy_rules lpr 
                       JOIN leave_types lt ON lpr.leave_type_id = lt.id 
                       ORDER BY lt.name, lpr.staff_type, lpr.gender")->fetchAll();

echo "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
<tr style='background: #f8f9fa;'>
    <th style='padding: 8px; border: 1px solid #dee2e6; text-align: left;'>Leave Type</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Staff Type</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Gender</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Emp. Type</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Days</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Max Accum.</th>
    <th style='padding: 8px; border: 1px solid #dee2e6;'>Max/Once</th>
</tr>";

foreach ($rules as $rule) {
    echo "<tr>
        <td style='padding: 8px; border: 1px solid #dee2e6;'>{$rule['leave_type_name']}</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center;'>{$rule['staff_type']}</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center;'>{$rule['gender']}</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center;'>{$rule['employment_type']}</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center; font-weight: bold;'>{$rule['allocated_days']}</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center;'>" . ($rule['max_accumulation'] ?? '-') . "</td>
        <td style='padding: 8px; border: 1px solid #dee2e6; text-align: center;'>" . ($rule['max_at_once'] ?? '-') . "</td>
    </tr>";
}
echo "</table>";

echo "</div>";

echo "<div class='card' style='background: #d4edda; border: 1px solid #c3e6cb;'>";
echo "<h2 style='color: #155724;'>✅ Migration Complete</h2>";
echo "<p>The leave policy rules have been set up. Next steps:</p>
<ol>
<li>Update existing users with their <strong>gender</strong> and <strong>employment type</strong> from the <a href='/admin/users.php'>User Management</a> page</li>
<li>View and manage policies from the <a href='/admin/leave_policies.php'>Leave Policies</a> admin page</li>
<li>Leave balances will be automatically calculated based on policies when users apply for leave</li>
</ol>";
echo "</div>";

echo "</body></html>";
?>
