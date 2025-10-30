-- Update role structure to simplified hierarchy
-- New roles: admin, head_of_department, director, staff

-- First, update the users table to use new role structure
ALTER TABLE `users` MODIFY COLUMN `role` enum('staff','head_of_department','director','admin') NOT NULL;

-- Update leave_approvals table to match new approval levels
ALTER TABLE `leave_approvals` MODIFY COLUMN `approver_level` enum('head_of_department','director','admin') NOT NULL;

-- Update leave_types applicable_to field
ALTER TABLE `leave_types` MODIFY COLUMN `applicable_to` set('staff','head_of_department','director','admin') NOT NULL;

-- Update existing users to new role structure
UPDATE `users` SET `role` = 'admin' WHERE `role` = 'hr_admin';
UPDATE `users` SET `role` = 'director' WHERE `role` = 'principal';
UPDATE `users` SET `role` = 'head_of_department' WHERE `role` = 'department_head';
UPDATE `users` SET `role` = 'director' WHERE `role` = 'dean';

-- Update leave_types to use new roles
UPDATE `leave_types` SET `applicable_to` = REPLACE(`applicable_to`, 'hr_admin', 'admin');
UPDATE `leave_types` SET `applicable_to` = REPLACE(`applicable_to`, 'principal', 'director');
UPDATE `leave_types` SET `applicable_to` = REPLACE(`applicable_to`, 'department_head', 'head_of_department');
UPDATE `leave_types` SET `applicable_to` = REPLACE(`applicable_to`, 'dean', 'director');

-- Update leave_approvals to use new approval levels
UPDATE `leave_approvals` SET `approver_level` = 'admin' WHERE `approver_level` = 'hr_admin';
UPDATE `leave_approvals` SET `approver_level` = 'director' WHERE `approver_level` = 'principal';
UPDATE `leave_approvals` SET `approver_level` = 'head_of_department' WHERE `approver_level` = 'department_head';
UPDATE `leave_approvals` SET `approver_level` = 'director' WHERE `approver_level` = 'dean';

-- Update settings for new approval flow
UPDATE `settings` SET `setting_value` = 'head_of_department,director' WHERE `setting_key` = 'leave_approval_levels';

-- Add new setting for simplified approval flow
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('approval_flow_description', 'Staff applications go to Head of Department first, then to Director for final approval', 'Description of the approval workflow')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);