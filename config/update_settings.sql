-- Additional settings for enhanced ELMS functionality

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('smtp_host', 'localhost', 'SMTP server host for email notifications'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username for authentication'),
('smtp_password', '', 'SMTP password for authentication'),
('from_email', 'noreply@elms.local', 'From email address for system notifications'),
('from_name', 'ELMS System', 'From name for system notifications'),
('enable_leave_calendar', '1', 'Enable leave calendar view'),
('dashboard_widgets_enabled', '1', 'Enable enhanced dashboard widgets'),
('notification_retention_days', '90', 'Number of days to retain notifications'),
('leave_application_reminder_days', '3', 'Days before leave start to send reminder'),
('auto_delete_cancelled_applications', '30', 'Days after which cancelled applications are auto-deleted'),
('enable_weekend_leave_restriction', '0', 'Restrict leave applications on weekends'),
('minimum_advance_notice_days', '1', 'Minimum days of advance notice required for leave application'),
('maximum_continuous_leave_days', '30', 'Maximum continuous leave days without special approval'),
('enable_leave_balance_alerts', '1', 'Enable alerts when leave balance is low'),
('low_balance_threshold_percentage', '20', 'Percentage threshold for low balance alerts'),
('enable_audit_trail', '1', 'Enable detailed audit trail logging'),
('session_timeout_minutes', '60', 'Session timeout in minutes'),
('password_expiry_days', '90', 'Password expiry period in days'),
('enable_two_factor_auth', '0', 'Enable two-factor authentication'),
('backup_retention_days', '30', 'Number of days to retain database backups')
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
description = VALUES(description);

-- Create system_settings table for better organization
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('text','number','boolean','email','url','json') NOT NULL DEFAULT 'text',
  `description` text,
  `is_editable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert organized settings
INSERT INTO `system_settings` (`category`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_editable`) VALUES
('general', 'system_name', 'ELMS - Employee Leave Management System', 'text', 'Name of the system', 1),
('general', 'college_name', 'Sample College', 'text', 'Name of the college', 1),
('general', 'pagination_limit', '10', 'number', 'Number of records per page', 1),
('email', 'enable_email_notifications', '1', 'boolean', 'Enable email notifications', 1),
('email', 'smtp_host', 'localhost', 'text', 'SMTP server host', 1),
('email', 'smtp_port', '587', 'number', 'SMTP server port', 1),
('email', 'from_email', 'noreply@elms.local', 'email', 'From email address', 1),
('leave', 'fiscal_year_start', '04-01', 'text', 'Start date of fiscal year (MM-DD)', 1),
('leave', 'fiscal_year_end', '03-31', 'text', 'End date of fiscal year (MM-DD)', 1),
('leave', 'minimum_advance_notice_days', '1', 'number', 'Minimum advance notice days', 1),
('leave', 'maximum_continuous_leave_days', '30', 'number', 'Maximum continuous leave days', 1),
('security', 'session_timeout_minutes', '60', 'number', 'Session timeout in minutes', 1),
('security', 'password_expiry_days', '90', 'number', 'Password expiry period', 1),
('security', 'enable_audit_trail', '1', 'boolean', 'Enable audit trail', 1)
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
description = VALUES(description);

-- Add rejection_reason column to leave_applications if not exists
ALTER TABLE `leave_applications` 
ADD COLUMN IF NOT EXISTS `rejection_reason` text AFTER `attachment`;

-- Add color column to leave_types if not exists  
ALTER TABLE `leave_types` 
ADD COLUMN IF NOT EXISTS `color` varchar(7) DEFAULT '#007bff' AFTER `max_carry_forward_days`;

-- Update leave types with colors
UPDATE `leave_types` SET `color` = '#28a745' WHERE `name` = 'Casual Leave';
UPDATE `leave_types` SET `color` = '#dc3545' WHERE `name` = 'Medical Leave';
UPDATE `leave_types` SET `color` = '#17a2b8' WHERE `name` = 'Earned Leave';
UPDATE `leave_types` SET `color` = '#6f42c1' WHERE `name` = 'Conference Leave';
UPDATE `leave_types` SET `color` = '#fd7e14' WHERE `name` = 'Sabbatical Leave';
UPDATE `leave_types` SET `color` = '#20c997' WHERE `name` = 'Invigilation Leave';
UPDATE `leave_types` SET `color` = '#e83e8c' WHERE `name` = 'Maternity Leave';
UPDATE `leave_types` SET `color` = '#6c757d' WHERE `name` = 'Paternity Leave';
UPDATE `leave_types` SET `color` = '#343a40' WHERE `name` = 'Bereavement Leave';
UPDATE `leave_types` SET `color` = '#ffc107' WHERE `name` = 'Unpaid Leave';

-- Create leave_application_history table for better tracking
CREATE TABLE IF NOT EXISTS `leave_application_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_application_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `comments` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leave_application_id` (`leave_application_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `leave_application_history_ibfk_1` FOREIGN KEY (`leave_application_id`) REFERENCES `leave_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_application_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_leave_applications_status` ON `leave_applications` (`status`);
CREATE INDEX IF NOT EXISTS `idx_leave_applications_dates` ON `leave_applications` (`start_date`, `end_date`);
CREATE INDEX IF NOT EXISTS `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_audit_logs_user_date` ON `audit_logs` (`user_id`, `created_at`);