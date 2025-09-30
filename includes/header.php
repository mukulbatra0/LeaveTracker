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
    <!-- Custom CSS -->
    <?php
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $currentDir = dirname($currentScript);
    $levels = substr_count(trim($currentDir, '/'), '/');
    $basePath = str_repeat('../', $levels);
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/style.css">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $basePath; ?>images/favicon.ico" type="image/x-icon">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
                <i class="fas fa-calendar-check me-2"></i>LeaveTracker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if(isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    
                    <?php if($_SESSION['role'] == 'staff' || $_SESSION['role'] == 'department_head' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>modules/apply_leave.php"><i class="fas fa-file-alt me-1"></i> Apply Leave</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>modules/my_leaves.php"><i class="fas fa-history me-1"></i> My Leaves</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>modules/leave_calendar.php"><i class="fas fa-calendar-alt me-1"></i> Leave Calendar</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'department_head' || $_SESSION['role'] == 'dean' || $_SESSION['role'] == 'principal' || $_SESSION['role'] == 'hr_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>modules/leave_approvals.php"><i class="fas fa-tasks me-1"></i> Pending Approvals</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'hr_admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs me-1"></i> Administration
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/users.php">Manage Users</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/departments.php">Manage Departments</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/leave_types.php">Manage Leave Types</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/holidays.php">Manage Holidays</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/academic_calendar.php">Academic Calendar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>reports/leave_report.php">Reports</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/system_config.php">System Settings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell me-1"></i>
                            <span class="badge bg-danger notification-badge" id="notification-count">0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" id="notification-list">
                            <li class="dropdown-item text-center">No new notifications</li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>modules/profile.php"><i class="fas fa-user me-1"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>modules/change_password.php"><i class="fas fa-key me-1"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a></li>
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