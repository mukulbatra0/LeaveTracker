-- Add profile_picture column to users table
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER email;

-- Add other potentially missing columns for profile functionality
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER profile_picture;
ALTER TABLE users ADD COLUMN address TEXT NULL AFTER phone;
ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(255) NULL AFTER address;

-- Update the users table comment
ALTER TABLE users COMMENT = 'Users table with profile information';