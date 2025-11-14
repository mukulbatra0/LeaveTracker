# LeaveTracker - Employee Leave Management System

A comprehensive web-based leave management system built with PHP and MySQL, designed for educational institutions and organizations with hierarchical approval workflows.

## 🚀 Features

### Core Functionality
- **User Authentication** - Secure login/logout with password hashing
- **Role-Based Access Control** - Four distinct user roles with specific permissions
- **Leave Application Management** - Apply, track, and manage leave requests
- **Approval Workflow** - Two-level approval process (Head of Department → Director)
- **Leave Balance Tracking** - Automatic calculation and tracking of leave balances
- **Email Notifications** - Automated email alerts for status changes
- **Academic Calendar** - Manage academic events, semesters, and exam periods
- **Audit Logging** - Complete audit trail of all system activities

### User Roles & Permissions

#### 1. **Staff**
- Apply for various types of leave
- View personal leave balances and history
- Track application status in real-time
- Receive notifications for status updates

#### 2. **Head of Department**
- First-level approval for department staff applications
- View department-wide leave statistics
- Manage department staff leave requests
- Apply for personal leave

#### 3. **Director**
- Final approval authority for all leave applications
- Institution-wide leave oversight
- View comprehensive reports and analytics
- Apply for personal leave

#### 4. **Admin**
- Complete system administration
- User and department management
- Leave type and holiday configuration
- Academic calendar management
- System settings and maintenance
- Emergency approval override capabilities

## 🏗️ System Architecture

### Technology Stack
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Email**: PHPMailer
- **PDF Generation**: TCPDF
- **Excel Export**: PhpSpreadsheet

### Database Structure
- **users** - User accounts and profile information
- **departments** - Organizational structure
- **leave_types** - Configurable leave categories
- **leave_applications** - Leave requests and details
- **leave_approvals** - Approval workflow tracking
- **leave_balances** - User leave balance management
- **notifications** - System notifications
- **holidays** - Holiday calendar
- **academic_calendar** - Academic events and semesters
- **audit_logs** - System activity logging

## 📋 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependencies)

### Step 1: Clone Repository
```bash
git clone <repository-url>
cd LeaveTracker
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Database Setup
1. Create a MySQL database named `leavetracker_db`
2. Import the database schema:
```bash
mysql -u username -p leavetracker_db < config/leavetracker_db.sql
```
3. (Optional) Load sample data:
```bash
mysql -u username -p leavetracker_db < config/mock_data.sql
```

### Step 4: Configuration
1. Copy `.env.example` to `.env`
2. Update database credentials in `.env`:
```env
DB_HOST=localhost
DB_NAME=leavetracker_db
DB_USER=your_username
DB_PASS=your_password
```

### Step 5: Initialize Database
Run the initialization script:
```bash
php config/init_db.php
```

### Step 6: Web Server Configuration
Point your web server document root to the project directory.

### Step 7: Default Admin Account
After installation, use these credentials to login as admin:
- **Username**: admin
- **Password**: admin123
- **Important**: Change the default password immediately after first login

## 🔧 Configuration

### Email Settings
Configure email settings in `classes/EmailNotification.php`:
- SMTP server details
- Authentication credentials
- Email templates

### Leave Types
Default leave types included:
- Casual Leave (12 days)
- Medical Leave (30 days)
- Earned Leave (30 days)
- Conference Leave (15 days)
- Maternity/Paternity Leave
- Bereavement Leave

### Academic Calendar
Manage academic events through admin panel:
- **Semesters** - Academic term periods
- **Exam Periods** - Examination schedules
- **Staff Development** - Training and development events
- **Restricted Leave Periods** - Times when leave is limited

### System Settings
Configurable through admin panel:
- Fiscal year dates
- Approval workflow settings
- Email notification preferences
- File upload limits

## 🎯 Usage

### For Staff Users
1. **Login** with your credentials
2. **Apply for Leave**:
   - Select leave type and dates
   - Provide reason and attach documents if required
   - Submit for approval
3. **Track Applications** in your dashboard
4. **View Leave Balance** and usage history

### For Head of Department
1. **Review Applications** from department staff
2. **Approve/Reject** with comments
3. **Monitor Department** leave statistics
4. **Generate Reports** for department activities

### For Directors
1. **Final Approval** of applications approved by HODs
2. **Institution Overview** with comprehensive statistics
3. **Policy Management** and system oversight
4. **Strategic Reporting** and analytics

### For Administrators
1. **User Management** - Create, edit, deactivate users
2. **Department Setup** - Manage organizational structure
3. **System Configuration** - Leave types, holidays, settings
4. **Academic Calendar** - Manage academic events and semesters
5. **Maintenance** - Database cleanup, audit logs

## 🔄 Approval Workflow

### Standard Process
1. **Staff** submits leave application
2. **Head of Department** receives notification
3. **HOD** reviews and approves/rejects
4. If approved, **Director** receives notification
5. **Director** provides final approval/rejection
6. **Staff** receives final decision notification

### Workflow Rules
- Applications must be approved by HOD before reaching Director
- Rejections at any level terminate the workflow
- Email notifications sent at each stage
- Complete audit trail maintained

## 📊 Reports & Analytics

### Available Reports
- **Leave Balance Reports** - Individual and department-wise
- **Usage Statistics** - Leave utilization patterns
- **Approval Analytics** - Processing times and patterns
- **Department Summaries** - Comparative analysis
- **Academic Calendar Reports** - Event schedules and conflicts
- **Audit Reports** - System activity logs

### Export Options
- PDF reports with professional formatting
- Excel spreadsheets for data analysis
- Email delivery of reports

## 🔒 Security Features

### Authentication & Authorization
- Secure password hashing (PHP password_hash)
- Session management with timeout
- Role-based access control
- CSRF protection on forms

### Data Protection
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- File upload validation
- Audit logging for accountability

### Privacy
- Personal data encryption
- Secure file storage
- Access logging
- Data retention policies

## 🛠️ Maintenance

### Regular Tasks
- **Database Backup** - Schedule regular backups
- **Log Rotation** - Manage audit log size
- **User Cleanup** - Remove inactive accounts
- **Balance Updates** - Annual leave balance refresh

### Monitoring
- System performance metrics
- Error logging and alerting
- User activity monitoring
- Database health checks

## 🆘 Troubleshooting

### Common Issues

#### Login Problems
- Check database connection
- Verify user credentials
- Clear browser cache/cookies

#### Email Not Sending
- Verify SMTP settings
- Check firewall/port restrictions
- Test email configuration

#### Permission Errors
- Check file/folder permissions
- Verify web server configuration
- Review .htaccess settings

#### Database Errors
- Check database connectivity
- Verify table structure
- Review error logs

#### Academic Calendar Issues
- Ensure database table `academic_calendar` exists
- Check for proper event type values (semester, exam, staff_development, restricted_leave_period)
- Verify date formats are correct (YYYY-MM-DD)

## 📁 Project Structure

```
LeaveTracker/
├── admin/              # Admin panel modules
│   ├── academic_calendar.php  # Academic calendar management
│   ├── departments.php        # Department management
│   ├── holidays.php          # Holiday management
│   ├── leave_types.php       # Leave type configuration
│   ├── system_config.php     # System settings
│   └── users.php             # User management
├── api/                # API endpoints
├── classes/            # PHP classes and utilities
├── config/             # Configuration files
│   ├── db.php         # Database connection
│   ├── leavetracker_db.sql   # Database schema
│   ├── mock_data.sql  # Sample data
│   └── init_db.php    # Database initialization
├── css/                # Stylesheets
├── dashboards/         # Role-specific dashboards
├── images/             # Static images
├── includes/           # Common includes (header, footer)
├── js/                 # JavaScript files
├── modules/            # Core functionality modules
├── reports/            # Report generation
├── uploads/            # File upload storage
├── vendor/             # Composer dependencies
├── .env.example        # Environment configuration template
├── composer.json       # PHP dependencies
├── index.php           # Main entry point
├── login.php           # Authentication
├── logout.php          # Session termination
└── README.md           # This file
```

## 🔄 Version History

### v2.1.0 (Current)
- Added Academic Calendar management
- Enhanced admin dashboard with calendar link
- Improved database structure
- Fixed PDO parameter binding issues
- Cleaned up redundant files
- Consolidated documentation

### v2.0.0
- Simplified role structure (4 roles)
- Streamlined approval workflow (2 levels)
- Enhanced dashboard interfaces
- Improved notification system
- Better reporting capabilities

### v1.0.0 (Legacy)
- Initial release
- Complex 5-role hierarchy
- Multi-level approval process
- Basic functionality

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- Check the troubleshooting section
- Review system logs
- Contact system administrator

## 🙏 Acknowledgments

- Bootstrap for responsive UI framework
- PHPMailer for email functionality
- TCPDF for PDF generation
- PhpSpreadsheet for Excel export
- Font Awesome for icons

---

**LeaveTracker** - Streamlining leave management for modern organizations.