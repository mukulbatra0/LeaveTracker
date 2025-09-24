-- Mock Data for ELMS Database
-- Run this script after creating the database structure

USE `elms_db`;

-- Insert Departments (using INSERT IGNORE to avoid duplicates)
INSERT IGNORE INTO `departments` (`name`, `code`, `description`) VALUES
('Computer Science', 'CSE', 'Department of Computer Science and Engineering'),
('Mathematics', 'MATH', 'Department of Mathematics'),
('Physics', 'PHY', 'Department of Physics'),
('Chemistry', 'CHEM', 'Department of Chemistry'),
('English', 'ENG', 'Department of English Literature'),
('Business Administration', 'BBA', 'Department of Business Administration'),
('Human Resources', 'HRD', 'Human Resources Department'),
('Administration', 'ADMIN', 'Administrative Department');

-- Insert Users (Password: password123 - hashed)
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`) VALUES
-- HR Admin
('HR001', 'Sarah', 'Johnson', 'sarah.johnson@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'hr_admin', 7, 'HR Manager', '+1-555-0101'),

-- Principal
('PRIN001', 'Dr. Michael', 'Anderson', 'principal@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'principal', 8, 'Principal', '+1-555-0102'),

-- Deans
('DEAN001', 'Dr. Emily', 'Davis', 'emily.davis@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'dean', 1, 'Dean of Engineering', '+1-555-0103'),
('DEAN002', 'Dr. Robert', 'Wilson', 'robert.wilson@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'dean', 6, 'Dean of Business', '+1-555-0104'),

-- Department Heads
('DH001', 'Dr. James', 'Smith', 'james.smith@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'department_head', 1, 'Head of Computer Science', '+1-555-0105'),
('DH002', 'Dr. Lisa', 'Brown', 'lisa.brown@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'department_head', 2, 'Head of Mathematics', '+1-555-0106'),
('DH003', 'Dr. David', 'Miller', 'david.miller@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'department_head', 3, 'Head of Physics', '+1-555-0107'),
('DH004', 'Dr. Jennifer', 'Garcia', 'jennifer.garcia@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'department_head', 4, 'Head of Chemistry', '+1-555-0108'),

-- Staff Members
('CS001', 'John', 'Doe', 'john.doe@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Assistant Professor', '+1-555-0201'),
('CS002', 'Jane', 'Williams', 'jane.williams@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Associate Professor', '+1-555-0202'),
('MATH001', 'Mark', 'Taylor', 'mark.taylor@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 2, 'Assistant Professor', '+1-555-0203'),
('PHY001', 'Anna', 'Martinez', 'anna.martinez@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 3, 'Lecturer', '+1-555-0204'),
('CHEM001', 'Peter', 'Jones', 'peter.jones@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 4, 'Assistant Professor', '+1-555-0205'),
('ENG001', 'Maria', 'Rodriguez', 'maria.rodriguez@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 5, 'Associate Professor', '+1-555-0206'),
('BBA001', 'Thomas', 'Lee', 'thomas.lee@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 6, 'Assistant Professor', '+1-555-0207'),
('CS003', 'Susan', 'White', 'susan.white@college.edu', '$2y$10$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Lecturer', '+1-555-0208');

-- Update departments with head and dean IDs
UPDATE `departments` SET `head_id` = 5, `dean_id` = 3 WHERE `id` = 1; -- CS
UPDATE `departments` SET `head_id` = 6, `dean_id` = 3 WHERE `id` = 2; -- Math
UPDATE `departments` SET `head_id` = 7, `dean_id` = 3 WHERE `id` = 3; -- Physics
UPDATE `departments` SET `head_id` = 8, `dean_id` = 3 WHERE `id` = 4; -- Chemistry
UPDATE `departments` SET `head_id` = NULL, `dean_id` = 4 WHERE `id` = 5; -- English
UPDATE `departments` SET `head_id` = NULL, `dean_id` = 4 WHERE `id` = 6; -- BBA

-- Insert Leave Balances for current year
INSERT IGNORE INTO `leave_balances` (`user_id`, `leave_type_id`, `balance`, `used`, `year`) VALUES
-- John Doe (CS001)
(9, 1, 12.00, 2.00, 2024), -- Casual Leave
(9, 2, 30.00, 5.00, 2024), -- Medical Leave
(9, 3, 30.00, 8.00, 2024), -- Earned Leave

-- Jane Williams (CS002)
(10, 1, 12.00, 1.00, 2024),
(10, 2, 30.00, 0.00, 2024),
(10, 3, 30.00, 12.00, 2024),

-- Mark Taylor (MATH001)
(11, 1, 12.00, 3.00, 2024),
(11, 2, 30.00, 7.00, 2024),
(11, 3, 30.00, 5.00, 2024),

-- Anna Martinez (PHY001)
(12, 1, 12.00, 0.00, 2024),
(12, 2, 30.00, 2.00, 2024),
(12, 3, 30.00, 10.00, 2024),

-- Peter Jones (CHEM001)
(13, 1, 12.00, 4.00, 2024),
(13, 2, 30.00, 3.00, 2024),
(13, 3, 30.00, 6.00, 2024),

-- Maria Rodriguez (ENG001)
(14, 1, 12.00, 2.00, 2024),
(14, 2, 30.00, 1.00, 2024),
(14, 3, 30.00, 15.00, 2024),

-- Thomas Lee (BBA001)
(15, 1, 12.00, 1.00, 2024),
(15, 2, 30.00, 0.00, 2024),
(15, 3, 30.00, 4.00, 2024),

-- Susan White (CS003)
(16, 1, 12.00, 3.00, 2024),
(16, 2, 30.00, 8.00, 2024),
(16, 3, 30.00, 7.00, 2024);

-- Insert Leave Applications
INSERT IGNORE INTO `leave_applications` (`user_id`, `leave_type_id`, `start_date`, `end_date`, `days`, `reason`, `status`, `created_at`) VALUES
-- Recent applications
(9, 1, '2024-01-15', '2024-01-16', 2.00, 'Personal work', 'approved', '2024-01-10 09:00:00'),
(9, 2, '2024-02-20', '2024-02-24', 5.00, 'Medical checkup and treatment', 'approved', '2024-02-15 10:30:00'),
(10, 1, '2024-01-22', '2024-01-22', 1.00, 'Family function', 'approved', '2024-01-18 14:20:00'),
(11, 1, '2024-02-05', '2024-02-07', 3.00, 'Personal emergency', 'approved', '2024-02-01 11:15:00'),
(12, 2, '2024-01-30', '2024-01-31', 2.00, 'Doctor appointment', 'approved', '2024-01-25 16:45:00'),
(13, 1, '2024-02-12', '2024-02-15', 4.00, 'Wedding ceremony', 'approved', '2024-02-08 08:30:00'),

-- Pending applications
(14, 3, '2024-03-15', '2024-03-22', 8.00, 'Vacation with family', 'pending', '2024-03-10 09:15:00'),
(15, 1, '2024-03-20', '2024-03-20', 1.00, 'Personal work', 'pending', '2024-03-18 13:45:00'),
(16, 2, '2024-03-25', '2024-03-29', 5.00, 'Medical treatment', 'pending', '2024-03-22 10:20:00'),

-- Future applications
(9, 3, '2024-04-10', '2024-04-17', 8.00, 'Annual vacation', 'pending', '2024-03-25 15:30:00'),
(11, 2, '2024-04-05', '2024-04-11', 7.00, 'Surgery and recovery', 'pending', '2024-03-28 11:00:00');

-- Insert Leave Approvals
INSERT IGNORE INTO `leave_approvals` (`leave_application_id`, `approver_id`, `approver_level`, `status`, `comments`, `created_at`) VALUES
-- Approved applications
(1, 5, 'department_head', 'approved', 'Approved for personal work', '2024-01-11 10:00:00'),
(2, 5, 'department_head', 'approved', 'Medical leave approved', '2024-02-16 09:30:00'),
(3, 5, 'department_head', 'approved', 'Family function approved', '2024-01-19 11:00:00'),
(4, 6, 'department_head', 'approved', 'Emergency leave approved', '2024-02-02 08:45:00'),
(5, 7, 'department_head', 'approved', 'Medical appointment approved', '2024-01-26 14:20:00'),
(6, 8, 'department_head', 'approved', 'Wedding leave approved', '2024-02-09 10:15:00'),

-- Pending approvals
(7, 5, 'department_head', 'pending', NULL, '2024-03-11 09:30:00'),
(8, 4, 'dean', 'pending', NULL, '2024-03-19 14:00:00'),
(9, 8, 'department_head', 'pending', NULL, '2024-03-23 11:30:00');

-- Insert Holidays
INSERT IGNORE INTO `holidays` (`name`, `date`, `description`, `type`) VALUES
('New Year Day', '2024-01-01', 'New Year celebration', 'national'),
('Republic Day', '2024-01-26', 'Republic Day of India', 'national'),
('Holi', '2024-03-25', 'Festival of Colors', 'national'),
('Good Friday', '2024-03-29', 'Christian holiday', 'national'),
('Independence Day', '2024-08-15', 'Independence Day of India', 'national'),
('Gandhi Jayanti', '2024-10-02', 'Birthday of Mahatma Gandhi', 'national'),
('Diwali', '2024-11-01', 'Festival of Lights', 'national'),
('Christmas', '2024-12-25', 'Christian holiday', 'national'),
('College Foundation Day', '2024-09-15', 'Anniversary of college establishment', 'institutional'),
('Annual Sports Day', '2024-02-14', 'College sports event', 'institutional');

-- Insert Academic Calendar Events
INSERT IGNORE INTO `academic_calendar` (`event_name`, `start_date`, `end_date`, `event_type`, `description`) VALUES
('Spring Semester 2024', '2024-01-15', '2024-05-15', 'semester', 'Spring academic semester'),
('Mid-term Examinations', '2024-03-01', '2024-03-15', 'exam', 'Mid-semester examinations'),
('Final Examinations', '2024-05-01', '2024-05-15', 'exam', 'End semester examinations'),
('Summer Break', '2024-05-16', '2024-06-30', 'restricted_leave_period', 'Summer vacation period - restricted leave'),
('Fall Semester 2024', '2024-07-01', '2024-11-30', 'semester', 'Fall academic semester'),
('Faculty Development Program', '2024-06-01', '2024-06-07', 'staff_development', 'Professional development workshop'),
('Research Conference', '2024-09-20', '2024-09-22', 'staff_development', 'Annual research conference'),
('Winter Break', '2024-12-20', '2025-01-05', 'restricted_leave_period', 'Winter vacation period');

-- Insert Notifications
INSERT IGNORE INTO `notifications` (`user_id`, `title`, `message`, `related_to`, `related_id`, `is_read`, `created_at`) VALUES
-- For John Doe
(9, 'Leave Application Approved', 'Your casual leave application for Jan 15-16 has been approved by Dr. James Smith', 'leave_application', 1, 1, '2024-01-11 10:30:00'),
(9, 'Leave Application Approved', 'Your medical leave application for Feb 20-24 has been approved', 'leave_application', 2, 1, '2024-02-16 10:00:00'),
(9, 'Leave Balance Updated', 'Your leave balance has been updated for the new fiscal year', 'leave_balance', NULL, 0, '2024-03-20 09:00:00'),

-- For Jane Williams
(10, 'Leave Application Approved', 'Your casual leave application has been approved', 'leave_application', 3, 1, '2024-01-19 11:30:00'),
(10, 'Upcoming Holiday', 'Reminder: Holi holiday on March 25, 2024', 'holiday', NULL, 0, '2024-03-20 08:00:00'),

-- For Department Heads
(5, 'New Leave Application', 'Maria Rodriguez has submitted a leave application for your approval', 'leave_application', 7, 0, '2024-03-11 09:30:00'),
(8, 'New Leave Application', 'Susan White has submitted a medical leave application', 'leave_application', 9, 0, '2024-03-23 11:30:00'),

-- For HR Admin
(1, 'System Backup Completed', 'Daily system backup completed successfully', 'system', NULL, 1, '2024-03-25 02:00:00'),
(1, 'Monthly Report Ready', 'Monthly leave report is ready for review', 'report', NULL, 0, '2024-03-25 09:00:00');

-- Insert Audit Logs
INSERT IGNORE INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 'LOGIN', 'user', 1, 'User logged in successfully', '192.168.1.100', '2024-03-25 08:00:00'),
(9, 'CREATE', 'leave_application', 1, 'Created casual leave application', '192.168.1.101', '2024-01-10 09:00:00'),
(5, 'UPDATE', 'leave_application', 1, 'Approved leave application', '192.168.1.102', '2024-01-11 10:00:00'),
(9, 'CREATE', 'leave_application', 10, 'Created earned leave application', '192.168.1.101', '2024-03-25 15:30:00'),
(1, 'CREATE', 'user', 16, 'Created new user account for Susan White', '192.168.1.100', '2024-03-20 14:00:00'),
(1, 'UPDATE', 'leave_type', 1, 'Updated casual leave maximum days', '192.168.1.100', '2024-03-22 11:30:00');

-- Insert sample documents (metadata only)
INSERT IGNORE INTO `documents` (`leave_application_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_by`, `created_at`) VALUES
(2, 'medical_certificate.pdf', '/uploads/documents/medical_certificate_20240220.pdf', 'application/pdf', 245760, 9, '2024-02-15 10:45:00'),
(9, 'medical_report.pdf', '/uploads/documents/medical_report_20240325.pdf', 'application/pdf', 189440, 16, '2024-03-22 10:30:00'),
(11, 'surgery_recommendation.pdf', '/uploads/documents/surgery_rec_20240405.pdf', 'application/pdf', 312580, 11, '2024-03-28 11:15:00');

-- Update settings with more realistic values
UPDATE `settings` SET `setting_value` = 'ABC College of Engineering' WHERE `setting_key` = 'college_name';
UPDATE `settings` SET `setting_value` = '1' WHERE `setting_key` = 'enable_email_notifications';
UPDATE `settings` SET `setting_value` = '10' WHERE `setting_key` = 'max_attachment_size';

COMMIT;