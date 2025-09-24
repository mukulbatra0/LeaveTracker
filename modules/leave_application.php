<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user details
try {
    $stmt = $conn->prepare("SELECT u.*, d.name as department_name 
                          FROM users u 
                          LEFT JOIN departments d ON u.department_id = d.id 
                          WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['alert'] = "User not found.";
        $_SESSION['alert_type'] = "danger";
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['alert'] = "Error: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header("Location: ../index.php");
    exit;
}

// Get system settings
try {
    // Get minimum days before leave application
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_days_before_leave'");
    $stmt->execute();
    $min_days_before_leave = $stmt->fetchColumn() ?: 3;
    
    // Get document upload settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_document_upload'");
    $stmt->execute();
    $enable_document_upload = $stmt->fetchColumn() ?: '1';
} catch (PDOException $e) {
    // Default values if settings not found
    $min_days_before_leave = 3;
    $enable_document_upload = '1';
}

// Initialize variables
$errors = [];
$success = false;
$leave_types = [];
$documents = [];
$holidays = [];
$academic_events = [];
$leave_balances = [];

// Get leave types
try {
    $stmt = $conn->prepare("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving leave types: " . $e->getMessage();
}

// Get user's documents
try {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE user_id = ? AND leave_application_id IS NULL ORDER BY uploaded_at DESC");
    $stmt->execute([$user_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving documents: " . $e->getMessage();
}

// Get holidays for calendar
try {
    $current_year = date('Y');
    $stmt = $conn->prepare("SELECT * FROM holidays WHERE YEAR(date) = ? ORDER BY date");
    $stmt->execute([$current_year]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving holidays: " . $e->getMessage();
}

// Get academic events for calendar
try {
    $current_year = date('Y');
    $stmt = $conn->prepare("SELECT * FROM academic_calendar WHERE 
                          (YEAR(start_date) = ? OR YEAR(end_date) = ?) 
                          ORDER BY start_date");
    $stmt->execute([$current_year, $current_year]);
    $academic_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving academic events: " . $e->getMessage();
}

// Get leave balances
try {
    $stmt = $conn->prepare("SELECT lb.*, lt.name as leave_type_name 
                          FROM leave_balances lb 
                          JOIN leave_types lt ON lb.leave_type_id = lt.id 
                          WHERE lb.user_id = ? AND lt.is_active = 1");
    $stmt->execute([$user_id]);
    $leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving leave balances: " . $e->getMessage();
}

// Process leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $contact_during_leave = trim($_POST['contact_during_leave']);
    $document_ids = isset($_POST['document_ids']) ? $_POST['document_ids'] : [];
    
    // Validate inputs
    if (empty($leave_type_id)) {
        $errors[] = "Leave type is required.";
    }
    
    if (empty($start_date)) {
        $errors[] = "Start date is required.";
    }
    
    if (empty($end_date)) {
        $errors[] = "End date is required.";
    }
    
    if (empty($reason)) {
        $errors[] = "Reason for leave is required.";
    }
    
    // Validate dates
    if (!empty($start_date) && !empty($end_date)) {
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        $current_timestamp = strtotime(date('Y-m-d'));
        
        if ($start_timestamp < $current_timestamp) {
            $errors[] = "Start date cannot be in the past.";
        }
        
        if ($end_timestamp < $start_timestamp) {
            $errors[] = "End date cannot be before start date.";
        }
        
        // Check minimum days before leave
        $days_before_leave = floor(($start_timestamp - $current_timestamp) / (60 * 60 * 24));
        if ($days_before_leave < $min_days_before_leave) {
            $errors[] = "Leave application must be submitted at least {$min_days_before_leave} days before the start date.";
        }
    }
    
    // Check if leave type exists and is active
    if (!empty($leave_type_id)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM leave_types WHERE id = ? AND is_active = 1");
            $stmt->execute([$leave_type_id]);
            $leave_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave_type) {
                $errors[] = "Invalid leave type selected.";
            } else {
                // Check if documents are required
                if ($leave_type['requires_document'] == 1 && empty($document_ids)) {
                    $errors[] = "This leave type requires supporting documents.";
                }
                
                // Check leave balance
                if (!empty($start_date) && !empty($end_date)) {
                    // Calculate number of days
                    $start_date_obj = new DateTime($start_date);
                    $end_date_obj = new DateTime($end_date);
                    $end_date_obj->modify('+1 day'); // Include end date
                    $interval = $start_date_obj->diff($end_date_obj);
                    $num_days = $interval->days;
                    
                    // Exclude weekends and holidays
                    $current_date = clone $start_date_obj;
                    $weekend_days = 0;
                    $holiday_days = 0;
                    $holiday_dates = array_map(function($holiday) {
                        return $holiday['date'];
                    }, $holidays);
                    
                    while ($current_date < $end_date_obj) {
                        $day_of_week = $current_date->format('N');
                        $current_date_str = $current_date->format('Y-m-d');
                        
                        // Check if weekend (6=Saturday, 7=Sunday)
                        if ($day_of_week >= 6) {
                            $weekend_days++;
                        }
                        // Check if holiday
                        elseif (in_array($current_date_str, $holiday_dates)) {
                            $holiday_days++;
                        }
                        
                        $current_date->modify('+1 day');
                    }
                    
                    $working_days = $num_days - $weekend_days - $holiday_days;
                    
                    // Check leave balance
                    $stmt = $conn->prepare("SELECT balance FROM leave_balances 
                                          WHERE user_id = ? AND leave_type_id = ?");
                    $stmt->execute([$user_id, $leave_type_id]);
                    $current_balance = $stmt->fetchColumn();
                    
                    if ($working_days > $current_balance) {
                        $errors[] = "Insufficient leave balance. You have {$current_balance} days available, but requested {$working_days} working days.";
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Error validating leave type: " . $e->getMessage();
        }
    }
    
    // Check for overlapping leave applications
    if (!empty($start_date) && !empty($end_date)) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_applications 
                                  WHERE user_id = ? AND status != 'rejected' AND 
                                  ((start_date BETWEEN ? AND ?) OR 
                                   (end_date BETWEEN ? AND ?) OR 
                                   (start_date <= ? AND end_date >= ?))");
            $stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
            $overlapping_count = $stmt->fetchColumn();
            
            if ($overlapping_count > 0) {
                $errors[] = "You already have an overlapping leave application for the selected dates.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking overlapping leave: " . $e->getMessage();
        }
    }
    
    // Check for academic events that restrict leave
    if (!empty($start_date) && !empty($end_date)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM academic_calendar 
                                  WHERE restrict_leave = 1 AND 
                                  ((start_date BETWEEN ? AND ?) OR 
                                   (end_date BETWEEN ? AND ?) OR 
                                   (start_date <= ? AND end_date >= ?))");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
            $restricted_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($restricted_events)) {
                $event_names = array_map(function($event) {
                    return $event['title'] . ' (' . date('M d', strtotime($event['start_date'])) . ' - ' . date('M d', strtotime($event['end_date'])) . ')';
                }, $restricted_events);
                
                $errors[] = "Leave is restricted during the following academic events: " . implode(", ", $event_names);
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking academic calendar: " . $e->getMessage();
        }
    }
    
    // Process application if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Calculate working days
            $start_date_obj = new DateTime($start_date);
            $end_date_obj = new DateTime($end_date);
            $end_date_obj->modify('+1 day'); // Include end date
            $interval = $start_date_obj->diff($end_date_obj);
            $num_days = $interval->days;
            
            // Exclude weekends and holidays
            $current_date = clone $start_date_obj;
            $weekend_days = 0;
            $holiday_days = 0;
            $holiday_dates = array_map(function($holiday) {
                return $holiday['date'];
            }, $holidays);
            
            while ($current_date < $end_date_obj) {
                $day_of_week = $current_date->format('N');
                $current_date_str = $current_date->format('Y-m-d');
                
                // Check if weekend (6=Saturday, 7=Sunday)
                if ($day_of_week >= 6) {
                    $weekend_days++;
                }
                // Check if holiday
                elseif (in_array($current_date_str, $holiday_dates)) {
                    $holiday_days++;
                }
                
                $current_date->modify('+1 day');
            }
            
            $working_days = $num_days - $weekend_days - $holiday_days;
            
            // Insert leave application
            $stmt = $conn->prepare("INSERT INTO leave_applications 
                                  (user_id, leave_type_id, start_date, end_date, working_days, 
                                   reason, contact_during_leave, status, applied_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $leave_type_id, $start_date, $end_date, $working_days, $reason, $contact_during_leave]);
            $leave_application_id = $conn->lastInsertId();
            
            // Update document associations
            if (!empty($document_ids)) {
                foreach ($document_ids as $doc_id) {
                    $stmt = $conn->prepare("UPDATE documents SET leave_application_id = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$leave_application_id, $doc_id, $user_id]);
                }
            }
            
            // Determine first approver based on department and role
            $approver_id = null;
            $approver_role = null;
            
            // Get department head
            $stmt = $conn->prepare("SELECT head_id FROM departments WHERE id = ?");
            $stmt->execute([$user->department_id]);
            $department_head_id = $stmt->fetchColumn();
            
            if ($department_head_id && $department_head_id != $user_id) {
                $approver_id = $department_head_id;
                $approver_role = 'department_head';
            } else {
                // If user is department head or no department head, go to dean
                $stmt = $conn->prepare("SELECT u.id 
                                      FROM users u 
                                      JOIN departments d ON u.department_id = d.id 
                                      WHERE u.role = 'dean' AND d.faculty_id = 
                                      (SELECT faculty_id FROM departments WHERE id = ?)");
                $stmt->execute([$user->department_id]);
                $dean_id = $stmt->fetchColumn();
                
                if ($dean_id && $dean_id != $user_id) {
                    $approver_id = $dean_id;
                    $approver_role = 'dean';
                } else {
                    // If user is dean or no dean, go to principal
                    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'principal' LIMIT 1");
                    $stmt->execute();
                    $principal_id = $stmt->fetchColumn();
                    
                    if ($principal_id && $principal_id != $user_id) {
                        $approver_id = $principal_id;
                        $approver_role = 'principal';
                    } else {
                        // If user is principal or no principal, go to HR admin
                        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'hr_admin' LIMIT 1");
                        $stmt->execute();
                        $hr_admin_id = $stmt->fetchColumn();
                        
                        if ($hr_admin_id) {
                            $approver_id = $hr_admin_id;
                            $approver_role = 'hr_admin';
                        }
                    }
                }
            }
            
            // Insert approval record
            if ($approver_id) {
                $stmt = $conn->prepare("INSERT INTO leave_approvals 
                                      (leave_application_id, approver_id, approver_role, status, created_at) 
                                      VALUES (?, ?, ?, 'pending', NOW())");
                $stmt->execute([$leave_application_id, $approver_id, $approver_role]);
                
                // Send notification to approver
                $stmt = $conn->prepare("INSERT INTO notifications 
                                      (user_id, type, message, related_id, created_at) 
                                      VALUES (?, 'leave_approval', ?, ?, NOW())");
                $message = "New leave application from {$user['first_name']} {$user['last_name']} requires your approval.";
                $stmt->execute([$approver_id, $message, $leave_application_id]);
            }
            
            // Log the action
            $action = "Applied for leave from {$start_date} to {$end_date}";
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
            
            $conn->commit();
            
            $_SESSION['alert'] = "Leave application submitted successfully.";
            $_SESSION['alert_type'] = "success";
            header("Location: ../modules/leave_history.php");
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Error submitting leave application: " . $e->getMessage();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Apply for Leave</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Apply for Leave</li>
    </ol>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Leave Application Form -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-plus me-1"></i>
                    Leave Application Form
                </div>
                <div class="card-body">
                    <form method="post" id="leaveApplicationForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                    <option value="">Select Leave Type</option>
                                    <?php foreach ($leave_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" data-requires-document="<?php echo $type['requires_document']; ?>">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                            (Balance: 
                                            <?php 
                                            $balance = 0;
                                            foreach ($leave_balances as $lb) {
                                                if ($lb['leave_type_id'] == $type['id']) {
                                                    $balance = $lb['balance'];
                                                    break;
                                                }
                                            }
                                            echo $balance;
                                            ?> days)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="half_day" name="half_day">
                                    <label class="form-check-label" for="half_day">
                                        Half Day Leave
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                                <div class="form-text">Leave must be applied at least <?php echo $min_days_before_leave; ?> days in advance</div>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Leave <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_during_leave" class="form-label">Contact During Leave</label>
                            <input type="text" class="form-control" id="contact_during_leave" name="contact_during_leave" placeholder="Phone number or email">
                        </div>
                        
                        <?php if ($enable_document_upload == '1'): ?>
                            <div class="mb-3" id="documentsSection">
                                <label class="form-label">Supporting Documents</label>
                                <div class="alert alert-info document-required-alert" style="display: none;">
                                    <i class="fas fa-info-circle"></i> This leave type requires supporting documents.
                                </div>
                                
                                <?php if (empty($documents)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> You don't have any documents uploaded. 
                                        <a href="/modules/documents.php" class="alert-link">Upload documents</a> before applying for leave that requires documentation.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th width="5%"></th>
                                                    <th>Document Name</th>
                                                    <th>Type</th>
                                                    <th>Uploaded</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($documents as $doc): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="document_ids[]" value="<?php echo $doc['id']; ?>">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $icon_class = 'fa-file';
                                                            switch ($doc['file_type']) {
                                                                case 'pdf': $icon_class = 'fa-file-pdf'; break;
                                                                case 'doc': case 'docx': $icon_class = 'fa-file-word'; break;
                                                                case 'jpg': case 'jpeg': case 'png': $icon_class = 'fa-file-image'; break;
                                                            }
                                                            ?>
                                                            <i class="fas <?php echo $icon_class; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($doc['file_name']); ?>
                                                        </td>
                                                        <td><?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-text">
                                        <a href="/modules/documents.php" target="_blank">Upload more documents</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div id="leaveCalculation" class="alert alert-info" style="display: none;">
                                <h6>Leave Calculation</h6>
                                <div id="calculationDetails"></div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back();">Cancel</button>
                            <button type="submit" name="apply_leave" class="btn btn-primary">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Leave Information -->
        <div class="col-xl-4">
            <!-- Leave Balances -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-balance-scale me-1"></i>
                    Leave Balances
                </div>
                <div class="card-body">
                    <?php if (empty($leave_balances)): ?>
                        <p class="text-center">No leave balances available</p>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $balance): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo htmlspecialchars($balance['leave_type_name']); ?></span>
                                    <span><strong><?php echo $balance['balance']; ?></strong> days</span>
                                </div>
                                <div class="progress">
                                    <?php 
                                    $percentage = 0;
                                    if ($balance['total_allocated'] > 0) {
                                        $percentage = ($balance['balance'] / $balance['total_allocated']) * 100;
                                    }
                                    $color_class = 'bg-success';
                                    if ($percentage <= 25) {
                                        $color_class = 'bg-danger';
                                    } elseif ($percentage <= 50) {
                                        $color_class = 'bg-warning';
                                    }
                                    ?>
                                    <div class="progress-bar <?php echo $color_class; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $balance['balance']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $balance['total_allocated']; ?>"></div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small>Used: <?php echo $balance['total_allocated'] - $balance['balance']; ?></small>
                                    <small>Total: <?php echo $balance['total_allocated']; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Calendar Events -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar me-1"></i>
                    Calendar Events
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Holidays</h6>
                        <?php if (empty($holidays)): ?>
                            <p class="text-muted">No upcoming holidays</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php 
                                $count = 0;
                                foreach ($holidays as $holiday): 
                                    if (strtotime($holiday['date']) >= strtotime(date('Y-m-d'))) {
                                        $count++;
                                        if ($count > 5) break; // Show only 5 upcoming holidays
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?php echo htmlspecialchars($holiday['name']); ?></span>
                                        <span class="badge bg-primary rounded-pill"><?php echo date('M d', strtotime($holiday['date'])); ?></span>
                                    </li>
                                <?php 
                                    }
                                endforeach; 
                                ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h6>Academic Events</h6>
                        <?php if (empty($academic_events)): ?>
                            <p class="text-muted">No upcoming academic events</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php 
                                $count = 0;
                                foreach ($academic_events as $event): 
                                    if (strtotime($event['end_date']) >= strtotime(date('Y-m-d'))) {
                                        $count++;
                                        if ($count > 5) break; // Show only 5 upcoming events
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <span><?php echo htmlspecialchars($event['title']); ?></span>
                                            <?php if ($event['restrict_leave'] == 1): ?>
                                                <span class="badge bg-danger">Restricted</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo date('M d', strtotime($event['start_date'])); ?> - 
                                            <?php echo date('M d', strtotime($event['end_date'])); ?>
                                        </span>
                                    </li>
                                <?php 
                                    }
                                endforeach; 
                                ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const leaveTypeSelect = document.getElementById('leave_type_id');
        const documentsSection = document.getElementById('documentsSection');
        const documentRequiredAlert = document.querySelector('.document-required-alert');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const halfDayCheckbox = document.getElementById('half_day');
        const leaveCalculation = document.getElementById('leaveCalculation');
        const calculationDetails = document.getElementById('calculationDetails');
        
        // Set minimum date for start date and end date
        const today = new Date();
        const minDaysBeforeLeave = <?php echo $min_days_before_leave; ?>;
        const minDate = new Date(today);
        minDate.setDate(today.getDate() + minDaysBeforeLeave);
        
        const minDateStr = minDate.toISOString().split('T')[0];
        startDateInput.setAttribute('min', minDateStr);
        endDateInput.setAttribute('min', minDateStr);
        
        // Handle leave type change
        if (leaveTypeSelect) {
            leaveTypeSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const requiresDocument = selectedOption.getAttribute('data-requires-document') === '1';
                
                if (documentsSection) {
                    if (requiresDocument) {
                        documentRequiredAlert.style.display = 'block';
                    } else {
                        documentRequiredAlert.style.display = 'none';
                    }
                }
                
                calculateLeaveDays();
            });
        }
        
        // Handle half day checkbox
        if (halfDayCheckbox) {
            halfDayCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    endDateInput.value = startDateInput.value;
                    endDateInput.disabled = true;
                } else {
                    endDateInput.disabled = false;
                }
                
                calculateLeaveDays();
            });
        }
        
        // Handle date changes
        if (startDateInput) {
            startDateInput.addEventListener('change', function() {
                if (halfDayCheckbox.checked) {
                    endDateInput.value = this.value;
                } else if (endDateInput.value && new Date(endDateInput.value) < new Date(this.value)) {
                    endDateInput.value = this.value;
                }
                
                calculateLeaveDays();
            });
        }
        
        if (endDateInput) {
            endDateInput.addEventListener('change', function() {
                calculateLeaveDays();
            });
        }
        
        // Calculate leave days
        function calculateLeaveDays() {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            const isHalfDay = halfDayCheckbox.checked;
            
            if (!startDate || !endDate) {
                leaveCalculation.style.display = 'none';
                return;
            }
            
            // Get holidays and weekends
            const holidays = <?php echo json_encode(array_column($holidays, 'date')); ?>;
            const start = new Date(startDate);
            const end = new Date(endDate);
            end.setDate(end.getDate() + 1); // Include end date
            
            let totalDays = 0;
            let weekendDays = 0;
            let holidayDays = 0;
            
            // Loop through each day
            const currentDate = new Date(start);
            while (currentDate < end) {
                totalDays++;
                
                const dayOfWeek = currentDate.getDay(); // 0 = Sunday, 6 = Saturday
                const dateStr = currentDate.toISOString().split('T')[0];
                
                // Check if weekend
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    weekendDays++;
                }
                // Check if holiday
                else if (holidays.includes(dateStr)) {
                    holidayDays++;
                }
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            let workingDays = totalDays - weekendDays - holidayDays;
            
            if (isHalfDay) {
                workingDays = 0.5;
            }
            
            // Display calculation
            let html = `<p><strong>Total Days:</strong> ${totalDays}</p>`;
            html += `<p><strong>Weekend Days:</strong> ${weekendDays}</p>`;
            html += `<p><strong>Holiday Days:</strong> ${holidayDays}</p>`;
            html += `<p><strong>Working Days:</strong> ${workingDays}</p>`;
            
            calculationDetails.innerHTML = html;
            leaveCalculation.style.display = 'block';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>