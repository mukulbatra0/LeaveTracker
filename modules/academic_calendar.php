<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is an HR admin
if ($role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new academic event
    if (isset($_POST['add_event'])) {
        $title = trim($_POST['title']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date'] ?? $start_date);
        $event_type = trim($_POST['event_type']);
        $description = trim($_POST['description'] ?? '');
        $is_leave_restricted = isset($_POST['is_leave_restricted']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        
        if (empty($title)) $errors[] = "Event title is required";
        if (empty($start_date)) $errors[] = "Start date is required";
        if (empty($end_date)) $errors[] = "End date is required";
        if (empty($event_type)) $errors[] = "Event type is required";
        
        // Validate date format and logic
        if (!empty($start_date) && !empty($end_date)) {
            $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
            
            if (!$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date) {
                $errors[] = "Invalid start date format. Please use YYYY-MM-DD format.";
            }
            
            if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $end_date) {
                $errors[] = "Invalid end date format. Please use YYYY-MM-DD format.";
            }
            
            if ($start_date_obj && $end_date_obj && $start_date_obj > $end_date_obj) {
                $errors[] = "End date cannot be before start date.";
            }
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert academic event
                $insert_sql = "INSERT INTO academic_calendar (event_name, start_date, end_date, event_type, description, created_at) 
                               VALUES (:title, :start_date, :end_date, :event_type, :description, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_event_id = $conn->lastInsertId();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'academic_event', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_event_id, PDO::PARAM_INT);
                $log_description = "Created new academic event: $title from $start_date to $end_date";
                $log_stmt->bindParam(':description', $log_description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Academic event created successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/academic_calendar.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating academic event: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Edit academic event
    if (isset($_POST['edit_event'])) {
        $edit_event_id = $_POST['edit_event_id'];
        $title = trim($_POST['title']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date'] ?? $start_date);
        $event_type = trim($_POST['event_type']);
        $description = trim($_POST['description'] ?? '');
        $is_leave_restricted = isset($_POST['is_leave_restricted']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        
        if (empty($title)) $errors[] = "Event title is required";
        if (empty($start_date)) $errors[] = "Start date is required";
        if (empty($end_date)) $errors[] = "End date is required";
        if (empty($event_type)) $errors[] = "Event type is required";
        
        // Validate date format and logic
        if (!empty($start_date) && !empty($end_date)) {
            $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
            
            if (!$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date) {
                $errors[] = "Invalid start date format. Please use YYYY-MM-DD format.";
            }
            
            if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $end_date) {
                $errors[] = "Invalid end date format. Please use YYYY-MM-DD format.";
            }
            
            if ($start_date_obj && $end_date_obj && $start_date_obj > $end_date_obj) {
                $errors[] = "End date cannot be before start date.";
            }
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update academic event
                $update_sql = "UPDATE academic_calendar SET 
                              event_name = :title, 
                              start_date = :start_date, 
                              end_date = :end_date, 
                              event_type = :event_type, 
                              description = :description, 
                              updated_at = NOW() 
                              WHERE id = :event_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $update_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':event_id', $edit_event_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'update', 'academic_event', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $edit_event_id, PDO::PARAM_INT);
                $log_description = "Updated academic event: $title from $start_date to $end_date";
                $log_stmt->bindParam(':description', $log_description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Academic event updated successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/academic_calendar.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error updating academic event: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Delete academic event
    if (isset($_POST['delete_event'])) {
        $delete_event_id = $_POST['delete_event_id'];
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get event info for logging
            $event_info_sql = "SELECT event_name, start_date, end_date FROM academic_calendar WHERE id = :event_id";
            $event_info_stmt = $conn->prepare($event_info_sql);
            $event_info_stmt->bindParam(':event_id', $delete_event_id, PDO::PARAM_INT);
            $event_info_stmt->execute();
            $event_info = $event_info_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete academic event
            $delete_event_sql = "DELETE FROM academic_calendar WHERE id = :event_id";
            $delete_event_stmt = $conn->prepare($delete_event_sql);
            $delete_event_stmt->bindParam(':event_id', $delete_event_id, PDO::PARAM_INT);
            $delete_event_stmt->execute();
            
            // Log action
            $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                       VALUES (:user_id, 'delete', 'academic_event', :entity_id, :description, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $log_stmt->bindParam(':entity_id', $delete_event_id, PDO::PARAM_INT);
            $log_description = "Deleted academic event: {$event_info['event_name']} from {$event_info['start_date']} to {$event_info['end_date']}";
            $log_stmt->bindParam(':description', $log_description, PDO::PARAM_STR);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['alert'] = "Academic event deleted successfully!";
            $_SESSION['alert_type'] = "success";
            header('Location: ./modules/academic_calendar.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $_SESSION['alert'] = "Error deleting academic event: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Add new semester
    if (isset($_POST['add_semester'])) {
        $academic_year = trim($_POST['academic_year']);
        $semester_name = trim($_POST['semester_name']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date']);
        
        // Validate inputs
        $errors = [];
        

        if (empty($semester_name)) $errors[] = "Semester name is required";
        if (empty($start_date)) $errors[] = "Start date is required";
        if (empty($end_date)) $errors[] = "End date is required";
        
        // Validate date format and logic
        if (!empty($start_date) && !empty($end_date)) {
            $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
            
            if (!$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date) {
                $errors[] = "Invalid start date format. Please use YYYY-MM-DD format.";
            }
            
            if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $end_date) {
                $errors[] = "Invalid end date format. Please use YYYY-MM-DD format.";
            }
            
            if ($start_date_obj && $end_date_obj && $start_date_obj > $end_date_obj) {
                $errors[] = "End date cannot be before start date.";
            }
        }
        

        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert semester as an academic event
                $insert_sql = "INSERT INTO academic_calendar (event_name, start_date, end_date, event_type, created_at) 
                               VALUES (:title, :start_date, :end_date, 'semester', NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $title = "$semester_name Semester ($academic_year)";
                $insert_stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                $new_semester_id = $conn->lastInsertId();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'semester', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_semester_id, PDO::PARAM_INT);
                $log_description = "Created new semester: $semester_name for academic year $academic_year";
                $log_stmt->bindParam(':description', $log_description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Semester created successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/academic_calendar.php?view=semesters');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating semester: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Get current view (events or semesters)
$current_view = isset($_GET['view']) ? $_GET['view'] : 'events';

// Get current academic year
$current_academic_year = date('Y') . '-' . (date('Y') + 1);
if (date('m') < 6) { // If current month is before June, use previous academic year
    $current_academic_year = (date('Y') - 1) . '-' . date('Y');
}

// Get filter values
$filter_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : $current_academic_year;

$filter_type = isset($_GET['event_type']) ? $_GET['event_type'] : 'all';

// Generate academic years
$academic_years = [];
for ($i = -2; $i <= 2; $i++) {
    $year = date('Y') + $i;
    $academic_years[] = $year . '-' . ($year + 1);
}

// If no academic years in database, add current one to the list
if (empty($academic_years)) {
    $academic_years = [$current_academic_year];
}

// Get all semesters (placeholder - no semester column exists)
$semesters = [];

// Get all event types
$event_types_sql = "SELECT DISTINCT event_type FROM academic_calendar WHERE event_type != 'semester' ORDER BY event_type";
$event_types_stmt = $conn->prepare($event_types_sql);
$event_types_stmt->execute();
$event_types = $event_types_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query based on current view and filters
if ($current_view == 'semesters') {
    $events_sql = "SELECT * FROM academic_calendar WHERE event_type = 'semester'";
} else {
    $events_sql = "SELECT * FROM academic_calendar WHERE event_type != 'semester'";
}

$params = [];





if ($filter_type != 'all' && $current_view == 'events') {
    $events_sql .= " AND event_type = :event_type";
    $params[':event_type'] = $filter_type;
}

$events_sql .= " ORDER BY start_date";
$events_stmt = $conn->prepare($events_sql);

// Bind parameters
foreach ($params as $key => $value) {
    $events_stmt->bindValue($key, $value);
}

$events_stmt->execute();
$events = $events_stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>
                <?php if ($current_view == 'semesters'): ?>
                    <i class="fas fa-university me-2"></i>Academic Semesters
                <?php else: ?>
                    <i class="fas fa-calendar-alt me-2"></i>Academic Calendar
                <?php endif; ?>
            </h2>
            <p class="text-muted">
                <?php if ($current_view == 'semesters'): ?>
                    Manage academic semesters and terms
                <?php else: ?>
                    Manage academic events, exams, and important dates
                <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($current_view == 'semesters'): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
                    <i class="fas fa-plus-circle me-1"></i> Add New Semester
                </button>
                <a href="/modules/academic_calendar.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-calendar-alt me-1"></i> View Events
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus-circle me-1"></i> Add New Event
                </button>
                <a href="/modules/academic_calendar.php?view=semesters" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-university me-1"></i> View Semesters
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="modules/academic_calendar.php" class="row g-3">
                <?php if ($current_view == 'semesters'): ?>
                    <input type="hidden" name="view" value="semesters">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <select class="form-select" id="academic_year" name="academic_year">
                        <option value="all">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($current_view == 'events'): ?>

                    
                    <div class="col-md-4">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select class="form-select" id="event_type" name="event_type">
                            <option value="all">All Types</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filter_type == $type) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <a href="/modules/academic_calendar.php<?php echo ($current_view == 'semesters') ? '?view=semesters' : ''; ?>" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="calendarTable">
                    <thead>
                        <?php if ($current_view == 'semesters'): ?>
                            <tr>
                                <th>Academic Year</th>
                                <th>Semester</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Type</th>
                                <th>Semester</th>
                                <th>Leave Restriction</th>
                                <th>Actions</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if (count($events) == 0): ?>
                            <tr>
                                <td colspan="<?php echo ($current_view == 'semesters') ? '6' : '6'; ?>" class="text-center">
                                    No <?php echo ($current_view == 'semesters') ? 'semesters' : 'events'; ?> found matching your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($events as $event): ?>
                            <?php 
                                $start_date = new DateTime($event['start_date']);
                                $end_date = new DateTime($event['end_date']);
                                $is_past = $end_date < new DateTime('today');
                                $row_class = $is_past ? 'text-muted' : '';
                                
                                // Calculate duration for semesters
                                $duration = $start_date->diff($end_date);
                                $duration_text = '';
                                
                                if ($duration->y > 0) {
                                    $duration_text .= $duration->y . ' year' . ($duration->y > 1 ? 's' : '') . ' ';
                                }
                                
                                if ($duration->m > 0) {
                                    $duration_text .= $duration->m . ' month' . ($duration->m > 1 ? 's' : '') . ' ';
                                }
                                
                                if ($duration->d > 0) {
                                    $duration_text .= $duration->d . ' day' . ($duration->d > 1 ? 's' : '');
                                }
                                
                                // If same day event
                                if ($start_date == $end_date) {
                                    $duration_text = 'Single day';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <?php if ($current_view == 'semesters'): ?>
                                    <td><?php echo date('Y', strtotime($event['start_date'])); ?></td>
                                    <td><span class="text-muted">N/A</span></td>
                                    <td><?php echo $start_date->format('M d, Y'); ?></td>
                                    <td><?php echo $end_date->format('M d, Y'); ?></td>
                                    <td><?php echo $duration_text; ?></td>
                                <?php else: ?>
                                    <td>
                                        <?php if ($start_date->format('Y-m-d') == $end_date->format('Y-m-d')): ?>
                                            <?php echo $start_date->format('M d, Y'); ?>
                                        <?php else: ?>
                                            <?php echo $start_date->format('M d') . ' - ' . $end_date->format('M d, Y'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($event['event_name']); ?>
                                        <?php if (!empty($event['description'])): ?>
                                            <i class="fas fa-info-circle text-info ms-1" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($event['description']); ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $badge_class = '';
                                            switch ($event['event_type']) {
                                                case 'exam':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                                case 'holiday':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'event':
                                                    $badge_class = 'bg-info';
                                                    break;
                                                case 'staff_development':
                                                    $badge_class = 'bg-warning';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($event['event_type']))); ?>
                                        </span>
                                    </td>
                                    <td><span class="text-muted">N/A</span></td>
                                    <td>
                                        <span class="badge bg-success">Allowed</span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-event-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#<?php echo ($current_view == 'semesters') ? 'editSemesterModal' : 'editEventModal'; ?>"
                                            data-id="<?php echo $event['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($event['event_name']); ?>"
                                            data-start="<?php echo htmlspecialchars($event['start_date']); ?>"
                                            data-end="<?php echo htmlspecialchars($event['end_date']); ?>"
                                            data-type="<?php echo htmlspecialchars($event['event_type']); ?>"
                                            data-description="<?php echo htmlspecialchars($event['description'] ?? ''); ?>"
                                            data-restricted="0"
                                            data-year="<?php echo htmlspecialchars($event['academic_year'] ?? ''); ?>"
                                            data-semester="">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-event-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteEventModal"
                                            data-id="<?php echo $event['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($event['event_name']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/academic_calendar.php" method="post">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select Event Type</option>
                                <option value="exam">Exam</option>
                                <option value="holiday">Holiday</option>
                                <option value="event">Event</option>
                                <option value="staff_development">Staff Development</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                            <small class="form-text text-muted">For single-day events, use the same date as start date</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="academic_year" name="academic_year" required>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $current_academic_year) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="<?php echo (date('Y')) . '-' . (date('Y') + 1); ?>">
                                    <?php echo (date('Y')) . '-' . (date('Y') + 1); ?>
                                </option>
                                <option value="<?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>">
                                    <?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">Not Applicable</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>">
                                        <?php echo htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_leave_restricted" name="is_leave_restricted">
                            <label class="form-check-label" for="is_leave_restricted">
                                Restrict Leave Applications During This Period
                            </label>
                            <div class="form-text text-muted">If checked, staff will not be able to apply for leave during this period</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel"><i class="fas fa-edit me-2"></i>Edit Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/academic_calendar.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_event_id" name="edit_event_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_title" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_event_type" name="event_type" required>
                                <option value="exam">Exam</option>
                                <option value="holiday">Holiday</option>
                                <option value="event">Event</option>
                                <option value="staff_development">Staff Development</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_academic_year" name="academic_year" required>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>">
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="<?php echo (date('Y')) . '-' . (date('Y') + 1); ?>">
                                    <?php echo (date('Y')) . '-' . (date('Y') + 1); ?>
                                </option>
                                <option value="<?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>">
                                    <?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_semester" class="form-label">Semester</label>
                            <select class="form-select" id="edit_semester" name="semester">
                                <option value="">Not Applicable</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>">
                                        <?php echo htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_leave_restricted" name="is_leave_restricted">
                            <label class="form-check-label" for="edit_is_leave_restricted">
                                Restrict Leave Applications During This Period
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_event" class="btn btn-primary">Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Semester Modal -->
<div class="modal fade" id="addSemesterModal" tabindex="-1" aria-labelledby="addSemesterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSemesterModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/academic_calendar.php?view=semesters" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="semester_academic_year" name="academic_year" required>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $current_academic_year) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="<?php echo (date('Y')) . '-' . (date('Y') + 1); ?>">
                                <?php echo (date('Y')) . '-' . (date('Y') + 1); ?>
                            </option>
                            <option value="<?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>">
                                <?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester_name" class="form-label">Semester Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="semester_name" name="semester_name" required placeholder="e.g. Fall, Spring, Summer">
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="semester_start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="semester_end_date" name="end_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_semester" class="btn btn-primary">Add Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Semester Modal -->
<div class="modal fade" id="editSemesterModal" tabindex="-1" aria-labelledby="editSemesterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSemesterModalLabel"><i class="fas fa-edit me-2"></i>Edit Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/academic_calendar.php?view=semesters" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_semester_id" name="edit_event_id">
                    
                    <div class="mb-3">
                        <label for="edit_semester_academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_semester_academic_year" name="academic_year" required>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>">
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="<?php echo (date('Y')) . '-' . (date('Y') + 1); ?>">
                                <?php echo (date('Y')) . '-' . (date('Y') + 1); ?>
                            </option>
                            <option value="<?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>">
                                <?php echo (date('Y') + 1) . '-' . (date('Y') + 2); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_semester_name" class="form-label">Semester Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_semester_name" name="semester" required>
                        <input type="hidden" id="edit_semester_title" name="title">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_semester_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_semester_start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_semester_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_semester_end_date" name="end_date" required>
                    </div>
                    
                    <input type="hidden" name="event_type" value="semester">
                    <input type="hidden" name="description" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_event" class="btn btn-primary">Update Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteEventModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Delete <?php echo ($current_view == 'semesters') ? 'Semester' : 'Event'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/academic_calendar.php<?php echo ($current_view == 'semesters') ? '?view=semesters' : ''; ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_event_id" name="delete_event_id">
                    <p>Are you sure you want to delete <strong id="delete_event_title"></strong>?</p>
                    
                    <?php if ($current_view == 'semesters'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Warning:</strong> Deleting a semester will also remove its association with any events. This may affect leave applications and reports.
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-danger"><strong>Note:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_event" class="btn btn-danger">
                        Delete <?php echo ($current_view == 'semesters') ? 'Semester' : 'Event'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load DataTables after jQuery is ready
        if (typeof jQuery !== 'undefined') {
            // Load DataTables CSS and JS dynamically
            $('head').append('<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">');
            
            $.getScript('https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', function() {
                $.getScript('https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js', function() {
                    // Initialize DataTable
                    $('#calendarTable').DataTable({
                        "order": [[0, "asc"]],
                        "pageLength": 25,
                        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                        "columnDefs": [
                            { "orderable": false, "targets": <?php echo ($current_view == 'semesters') ? '5' : '5'; ?> }
                        ]
                    });
                });
            });
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Set end date same as start date by default for new events
        document.getElementById('start_date').addEventListener('change', function() {
            if (document.getElementById('end_date').value === '') {
                document.getElementById('end_date').value = this.value;
            }
        });
        
        // Edit Event Modal
        document.querySelectorAll('.edit-event-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const start = this.getAttribute('data-start');
                const end = this.getAttribute('data-end');
                const type = this.getAttribute('data-type');
                const description = this.getAttribute('data-description');
                const restricted = this.getAttribute('data-restricted') === '1';
                const year = this.getAttribute('data-year');
                const semester = this.getAttribute('data-semester');
                
                <?php if ($current_view == 'semesters'): ?>
                    document.getElementById('edit_semester_id').value = id;
                    document.getElementById('edit_semester_academic_year').value = year;
                    document.getElementById('edit_semester_name').value = semester;
                    document.getElementById('edit_semester_title').value = title;
                    document.getElementById('edit_semester_start_date').value = start;
                    document.getElementById('edit_semester_end_date').value = end;
                <?php else: ?>
                    document.getElementById('edit_event_id').value = id;
                    document.getElementById('edit_title').value = title;
                    document.getElementById('edit_start_date').value = start;
                    document.getElementById('edit_end_date').value = end;
                    document.getElementById('edit_event_type').value = type;
                    document.getElementById('edit_description').value = description;
                    document.getElementById('edit_is_leave_restricted').checked = restricted;
                    document.getElementById('edit_academic_year').value = year;
                    document.getElementById('edit_semester').value = semester;
                <?php endif; ?>
            });
        });
        
        // Delete Event Modal
        document.querySelectorAll('.delete-event-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                
                document.getElementById('delete_event_id').value = id;
                document.getElementById('delete_event_title').textContent = title;
            });
        });
    });
</script>

<?php include_once '../includes/footer.php'; ?>