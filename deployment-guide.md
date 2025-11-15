# ELMS Deployment Guide for InfinityFree

## Pre-deployment Checklist

### 1. InfinityFree Account Setup
- Sign up at infinityfree.net
- Create a new hosting account
- Note down your database credentials from the control panel

### 2. Database Configuration
- Login to your InfinityFree control panel
- Go to "MySQL Databases"
- Create a new database (name will be prefixed with your username)
- Note the database host, username, password, and database name

### 3. File Upload Methods
Choose one of these methods:

#### Option A: File Manager (Recommended for beginners)
1. Login to your InfinityFree control panel
2. Open "File Manager"
3. Navigate to `htdocs` folder
4. Upload all project files here

#### Option B: FTP Client
1. Download FileZilla or similar FTP client
2. Use FTP credentials from your control panel
3. Upload files to `/htdocs/` directory

## Deployment Steps

### Step 1: Update Environment Configuration
1. Rename `.env.production` to `.env`
2. Update database credentials with your InfinityFree details:
   ```
   DB_HOST=sql200.infinityfree.com (or your assigned server)
   DB_USER=if0_yourusername_dbuser
   DB_PASS=your_database_password
   DB_NAME=if0_yourusername_elms
   APP_DEBUG=false
   ```

### Step 2: Upload Files
Upload all project files to the `htdocs` folder:
- All PHP files
- css/, js/, images/ folders
- vendor/ folder (if using Composer)
- All other project directories

### Step 3: Set Up Database
1. Visit: `https://yourdomain.infinityfreeapp.com/database_setup.php`
2. Click "Setup Database" button
3. Wait for completion message

### Step 4: Test the Application
1. Visit your website: `https://yourdomain.infinityfreeapp.com`
2. Try logging in with test credentials:
   - Admin: admin@college.edu / password123
   - Staff: staff@college.edu / password123

## Important Notes

### File Permissions
- InfinityFree automatically sets correct permissions
- Ensure `uploads/` folder is writable (usually automatic)

### PHP Version
- InfinityFree supports PHP 8.x
- Your project is compatible

### Database Limitations
- InfinityFree provides MySQL databases
- No special configuration needed

### SSL Certificate
- InfinityFree provides free SSL certificates
- Enable in control panel under "SSL Certificates"

## Troubleshooting

### Common Issues:
1. **Database Connection Error**: Double-check credentials in `.env`
2. **File Upload Issues**: Ensure files are in `htdocs` folder
3. **Permission Errors**: Contact InfinityFree support if needed

### Support Resources:
- InfinityFree Community Forum
- Knowledge Base in control panel
- Support ticket system

## Security Recommendations

1. Change default passwords immediately after setup
2. Enable SSL certificate
3. Set APP_DEBUG=false in production
4. Regularly backup your database

## Post-Deployment Tasks

1. Update system settings in admin panel
2. Configure email settings (if needed)
3. Add real user accounts
4. Test all functionality
5. Set up regular backups