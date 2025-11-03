<?php
/**
 * Script to check and create leave_balances table if needed
 */

// Include database connection
require_once 'db.php';

try {
    echo "Checking leave_balances table...\n";
    
    // Check if table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'leave_balances'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "❌ leave_balances table doesn't exist. Creating it...\n";
        
        $create_table_sql = "CREATE TABLE leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            year YEAR NOT NULL,
            total_days DECIMAL(5,2) DEFAULT 0,
            used_days DECIMAL(5,2) DEFAULT 0,
            remaining_days DECIMAL(5,2) GENERATED ALWAYS AS (total_days - used_days) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_leave_year (user_id, leave_type_id, year)
        )";
        
        $conn->exec($create_table_sql);
        echo "✅ leave_balances table created successfully.\n";
    } else {
        echo "✅ leave_balances table already exists.\n";
        
        // Check table structure
        $stmt = $conn->prepare("DESCRIBE leave_balances");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current table structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    echo "\n✅ leave_balances table check completed!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>