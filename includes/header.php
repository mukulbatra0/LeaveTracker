<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeaveTracker - Employee Leave Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS - Using dynamic paths -->
    <?php 
    // Determine the correct path based on current directory
    $base_path = '';
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/modules') !== false || strpos($current_dir, '/admin') !== false || strpos($current_dir, '/reports') !== false) {
        $base_path = '../';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/responsive-override.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/mobile-tables.css">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base_path; ?>images/favicon.ico" type="image/x-icon">
    
    <!-- JavaScript files -->
    <script src="<?php echo $base_path; ?>js/mobile-detector.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_path; ?>js/responsive-helpers.js"></script>
    <script src="<?php echo $base_path; ?>js/mobile-enhancements.js"></script>
    <script src="<?php echo $base_path; ?>js/notifications.js"></script>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $base_path; ?>index.php">
                <i class="fas fa-calendar-check me-2"></i>LeaveTracker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if(isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    
                    <?php if($_SESSION['role'] == 'staff' || $_SESSION['role'] == 'head_of_department' || $_SESSION['role'] == 'director' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>modules/apply_leave.php"><i class="fas fa-file-alt me-1"></i> Apply Leave</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>modules/my_leaves.php"><i class="fas fa-history me-1"></i> My Leaves</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>admin/academic_calendar_view.php"><i class="fas fa-graduation-cap me-1"></i> Academic Calendar</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'head_of_department' || $_SESSION['role'] == 'director' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal' || $_SESSION['role'] == 'hr_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>modules/leave_approvals.php"><i class="fas fa-tasks me-1"></i> Pending Approvals</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'director' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal' || $_SESSION['role'] == 'hr_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>modules/reports.php"><i class="fas fa-chart-line me-1"></i> Reports</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'hr_admin' || $_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs me-1"></i> Administration
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr_admin'): ?>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/director_leave_approvals.php">
                                <i class="fas fa-crown me-1"></i>Director Approvals
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/users.php">Manage Users</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/departments.php">Manage Departments</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/leave_types.php">Manage Leave Types</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/academic_calendar.php">Academic Calendar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>reports/leave_report.php">Reports</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/system_config.php">System Settings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>modules/notifications.php" id="notificationLink">
                            <i class="fas fa-bell me-1"></i>
                            <span class="badge bg-danger notification-badge" id="notification-count" style="display: none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>modules/profile.php"><i class="fas fa-user me-1"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>modules/change_password.php"><i class="fas fa-key me-1"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<br>
<br>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid fade-in">
            <?php if(isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['alert']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
            <?php endif; ?>