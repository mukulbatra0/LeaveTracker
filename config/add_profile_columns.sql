-- Add missing columns to users table for profile functionality

USE `elms_db`;

-- Add address column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'elms_db' 
     AND TABLE_NAME = 'users' 
     AND COLUMN_NAME = 'address') = 0,
    'ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL AFTER phone',
    'SELECT "address column already exists"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add emergency_contact column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'elms_db' 
     AND TABLE_NAME = 'users' 
     AND COLUMN_NAME = 'emergency_contact') = 0,
    'ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL AFTER address',
    'SELECT "emergency_contact column already exists"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;