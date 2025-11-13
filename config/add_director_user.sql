-- Add a Director user for testing leave approval workflow
-- Password: password123 (hashed)

USE `elms_db`;

-- Insert a Director user
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
('DIR001', 'Dr. Alexandra', 'Thompson', 'director@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'director', 8, 'Director of Academic Affairs', '+1-555-0300', 'active');

-- Also add an admin user if not exists
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
('ADM001', 'System', 'Administrator', 'admin@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'admin', 8, 'System Administrator', '+1-555-0400', 'active');

-- Ensure we have a head of department user as well
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
('HOD001', 'Dr. Richard', 'Parker', 'hod@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'head_of_department', 1, 'Head of Computer Science', '+1-555-0500', 'active');

-- Add a staff user for testing
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
('STF001', 'Alice', 'Johnson', 'staff@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Assistant Professor', '+1-555-0600', 'active');

-- Update department head if needed
UPDATE `departments` SET `head_id` = (SELECT id FROM users WHERE employee_id = 'HOD001' LIMIT 1) WHERE `code` = 'CSE' OR `name` = 'Computer Science';

-- Add some leave balances for the staff user
INSERT IGNORE INTO `leave_balances` (`user_id`, `leave_type_id`, `year`, `total_days`, `used_days`) 
SELECT 
    u.id,
    lt.id,
    YEAR(CURDATE()),
    lt.max_days,
    0
FROM users u
CROSS JOIN leave_types lt
WHERE u.employee_id = 'STF001'
AND u.role = 'staff';

-- Display the created users
SELECT 
    employee_id,
    CONCAT(first_name, ' ', last_name) as full_name,
    email,
    role,
    position,
    status
FROM users 
WHERE employee_id IN ('DIR001', 'ADM001', 'HOD001', 'STF001')
ORDER BY 
    CASE role 
        WHEN 'admin' THEN 1
        WHEN 'director' THEN 2
        WHEN 'head_of_department' THEN 3
        WHEN 'staff' THEN 4
    END;