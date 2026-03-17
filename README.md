# LeaveTracker - Employee Leave Management System

A comprehensive web-based leave management system built with PHP and MySQL, designed for educational institutions and organizations with hierarchical approval workflows.

## 🚀 Features

### Core Functionality
- **User Authentication** - Secure login/logout with password hashing, password visibility toggle, and secure session handling.
- **Role-Based Access Control** - Four distinct user roles (Admin, Director, HOD, Staff) with specific permissions.
- **Leave Application Management** - Apply for leaves, upload supporting documents, and track application status.
- **Approval Workflow** - Robust multi-level approval process (Head of Department → Director).
- **Automated Leave Allocation** - Advanced leave mapping based on staff classification (Teaching, Non-Teaching) and gender using configured leave policies.
- **Email Notifications & PDF Export** - Automated email alerts and auto-generated application PDF forms dispatched to users via PHPMailer & TCPDF/FPDI.
- **Academic Calendar** - Support for managing academic events, semesters, exam periods, and restrictive leave times.
- **Data Export** - Export leave reports and analytics in both Excel and PDF formats using PhpSpreadsheet and TCPDF.
- **Mobile Responsive** - Enhanced UI components, mobile-focused tables, and specific JS/CSS integrations for smaller screens.

### User Roles & Permissions

#### 1. **Staff**
- Apply for various types of leave (Casual, Medical, etc.) based on allocated quotas.
- View personal leave balances, usage history, and real-time application status.
- Download historical PDF applications.

#### 2. **Head of Department (HOD)**
- First-level approval authority for department staff applications.
- Access to the department staff directory and their leave histories.
- Has all functionalities of standard Staff to apply for personal leave.

#### 3. **Director**
- Complete institution-wide oversight and dashboard metrics.
- Final approval/rejection authority for applications approved by HODs.
- Detailed reporting metrics and analytical view of overall staff utilization.

#### 4. **Admin**
- Comprehensive system setup including user, department, and policy management.
- Dynamic Configuration: Configure leave types, assign complex leave policies conditionally, and maintain system environment variables.
- Academic Calendar tracking and global announcements.
- Full access to analytical tools and data auditing.

## 🏗️ System Architecture

### Technology Stack
- **Backend**: PHP (7.4+)
- **Database**: MySQL Server
- **Frontend**: HTML5, Vanilla JS, Custom responsive CSS overlays, Bootstrap 5 Components
- **Core Dependencies**:
  - `phpmailer/phpmailer` (Email dispatch mechanisms)
  - `tecnickcom/tcpdf` & `setasign/fpdi` (PDF processing)
  - `phpoffice/phpspreadsheet` (Excel reporting)

### Database Structure Highlights
The application relies on `elms_db` consisting of critical tables mapping core operations:
- **Users & Departments**: Maintains organizational structure and role schemas.
- **Leave Operations**: `leave_types`, `leave_applications`, `leave_approvals`, `leave_balances`.
- **Policy Mapping**: `leave_policies` connecting leave rules to staff characteristics.
- **Academic Support**: Events scheduling and restricted times.

## 📋 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB
- Web server setup (Apache HTTP Server/Nginx)
- Composer (for grabbing package dependencies)

### Step 1: Clone Repository
```bash
git clone <repository-url>
cd LeaveTracker
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Database & Environment Setup
1. Create a MySQL database named `elms_db` (or as per your preference).
2. Copy `.env.example` to `.env`.
3. Update database credentials in `.env`:
```env
DB_HOST=localhost
DB_NAME=elms_db
DB_USER=root
DB_PASS=
```
4. Configure SMTP rules in the `.env` settings to enable seamless background email transmission. Need Gmail App passwords or a provider like Sendgrid/AWS SES if using cloud environments.

### Step 4: Database Initialization
Import your baseline exported database schema directly into `elms_db`. 
> Note: For establishing specific leave allocation features, you can evaluate/run the structural policies component via:
```bash
php migrations/run_leave_policies.php
```

### Step 5: Validating Project Paths
A utility script named `path_checker.php` is available on the application root. You can run it via CLI to ensure that there are no internal missing scripts/links resulting from local setup:
```bash
php path_checker.php
```

## 📁 Repository Structure

```
LeaveTracker/
├── admin/                 # Administration control modules (config, policies, types, users)
├── api/                   # Asynchronous JS fetching endpoints (e.g., notifications)
├── classes/               # Core utility blueprints (EmailNotification.php, LeaveApplicationPDF.php)
├── config/                # Environment configuration implementations and path variables
├── css/                   # Stylesheets capturing global resets and responsive/mobile tweaks
├── dashboards/            # Role-isolated primary interface entry points
├── images/                # Core visual assets
├── includes/              # Shared functional GUI blocks (header, footer, path_helper, security)
├── js/                    # Client-side implementations for responsiveness and dynamic updates
├── migrations/            # Table creation pipelines and ad-hoc patching scripts
├── modules/               # Root procedural modules executing form submissions and approvals
├── reports/               # Read-only metrics processing tools
├── uploads/               # Standardized secure directory for handling application attachments
├── .env.example           # Bootstrapping template for developer environments
├── composer.json          # Dependency catalog mapped for packaging
├── index.php              # Central traffic router
├── login.php              # Authentication processor
├── path_checker.php       # Diagnostic utility looking for broken includes and href targets
└── README.md              # Software blueprint map
```

## 🔄 Workflow Walkthrough

1. **Submission**: Staff fills out application in `modules/apply_leave.php` providing a reason, target dates, and valid PDF/JPEG attachments.
2. **First Level Approval**: The HOD logs in and assesses `dashboards/head_of_department_dashboard.php` to accept or reject the proposal.
3. **Automated Handoff**: If authorized, an internal state transitions the status to Director-Level and dispatches an alert.
4. **Final Consensus**: The Director finalizes the outcome.
5. **Closure & Finalization**: The `process_final_approval.php` generates the final PDF application layout leveraging FPDI/TCPDF, updates the staff members' balance array, and sends out the final result summary directly via mail.

## 🤝 Contributing
1. Fork the repository
2. Create a specific feature branch for UI tweaks or functional modules
3. Make your modifications observing secure prepared SQL clauses
4. Validate internal relationships using `php path_checker.php`
5. Process pull request requests against core.

## 📄 License
This project is licensed under the MIT License - see the LICENSE file for details.