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

// Check if user is an admin or HR admin
if ($role != 'admin' && $role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: /dashboards/admin_dashboard.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new academic event
    if (isset($_POST['add_event'])) {
        $name = trim($_POST['name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $description = trim($_POST['description']);
        $event_type = $_POST['event_type'];
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Event name is required";
        }
        
        if (empty($start_date)) {
            $errors[] = "Start date is required";
        }
        
        if (empty($end_date)) {
            $errors[] = "End date is required";
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            if ($end < $start) {
                $errors[] = "End date cannot be before start date";
            }
            
            // Check for overlapping events of the same type
            $check_overlap_sql = "SELECT COUNT(*) FROM academic_calendar 
                                WHERE event_type = :event_type 
                                AND start_date <= :end_date AND end_date >= :start_date";
            $check_overlap_stmt = $conn->prepare($check_overlap_sql);
            $check_overlap_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
            $check_overlap_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
            $check_overlap_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $check_overlap_stmt->execute();
            
            if ($check_overlap_stmt->fetchColumn() > 0) {
                $errors[] = "There is already an event of type '$event_type' that overlaps with these dates";
            }
        }
        
        // If no errors, insert new event
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO academic_calendar (event_name, start_date, end_date, description, event_type, created_at) 
                              VALUES (:name, :start_date, :end_date, :description, :event_type, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);

                $insert_stmt->execute();
                
                // Add audit log
                $action = "Created new academic event: $name ($start_date to $end_date)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Academic event added successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: academic_calendar.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Edit academic event
    if (isset($_POST['edit_event'])) {
        $edit_event_id = $_POST['edit_event_id'];
        $name = trim($_POST['name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $description = trim($_POST['description']);
        $event_type = $_POST['event_type'];
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Event name is required";
        }
        
        if (empty($start_date)) {
            $errors[] = "Start date is required";
        }
        
        if (empty($end_date)) {
            $errors[] = "End date is required";
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            if ($end < $start) {
                $errors[] = "End date cannot be before start date";
            }
            
            // Check for overlapping events of the same type (excluding current event)
            $check_overlap_sql = "SELECT COUNT(*) FROM academic_calendar 
                                WHERE event_type = :event_type 
                                AND id != :event_id 
                                AND start_date <= :end_date AND end_date >= :start_date";
            $check_overlap_stmt = $conn->prepare($check_overlap_sql);
            $check_overlap_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
            $check_overlap_stmt->bindParam(':event_id', $edit_event_id, PDO::PARAM_INT);
            $check_overlap_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
            $check_overlap_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $check_overlap_stmt->execute();
            
            if ($check_overlap_stmt->fetchColumn() > 0) {
                $errors[] = "There is already an event of type '$event_type' that overlaps with these dates";
            }
        }
        
        // If no errors, update event
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $update_sql = "UPDATE academic_calendar SET 
                              event_name = :name, 
                              start_date = :start_date, 
                              end_date = :end_date, 
                              description = :description, 
                              event_type = :event_type, 
                              updated_at = NOW() 
                              WHERE id = :event_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);

                $update_stmt->bindParam(':event_id', $edit_event_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $action = "Updated academic event ID $edit_event_id: $name ($start_date to $end_date)";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Academic event updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: academic_calendar.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Delete academic event
    if (isset($_POST['delete_event'])) {
        $delete_event_id = $_POST['delete_event_id'];
        
        try {
            $conn->beginTransaction();
            
            // Get event details for audit log
            $event_sql = "SELECT event_name, start_date, end_date FROM academic_calendar WHERE id = :event_id";
            $event_stmt = $conn->prepare($event_sql);
            $event_stmt->bindParam(':event_id', $delete_event_id, PDO::PARAM_INT);
            $event_stmt->execute();
            $event = $event_stmt->fetch();
            
            // Delete event
            $delete_sql = "DELETE FROM academic_calendar WHERE id = :event_id";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bindParam(':event_id', $delete_event_id, PDO::PARAM_INT);
            $delete_stmt->execute();
            
            // Add audit log
            $action = "Deleted academic event: {$event['event_name']} ({$event['start_date']} to {$event['end_date']})";
            $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $audit_stmt->execute();
            
            $conn->commit();
            
            $_SESSION['alert'] = "Academic event deleted successfully.";
            $_SESSION['alert_type'] = "success";
            header("Location: academic_calendar.php");
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get all academic events with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$event_type = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';


// Build WHERE clause and parameters
$where_conditions = [];
$bind_params = [];

if (!empty($search)) {
    $where_conditions[] = "(event_name LIKE ? OR description LIKE ?)";
    $bind_params[] = "%$search%";
    $bind_params[] = "%$search%";
}

if (!empty($event_type)) {
    $where_conditions[] = "event_type = ?";
    $bind_params[] = $event_type;
}

if (!empty($academic_year)) {
    // Academic year format: 2023-2024
    $year_parts = explode('-', $academic_year);
    if (count($year_parts) == 2) {
        $start_year = $year_parts[0];
        $end_year = $year_parts[1];
        
        // Academic year typically runs from August to July
        $academic_start = $start_year . '-08-01';
        $academic_end = $end_year . '-07-31';
        
        $where_conditions[] = "((start_date >= ? AND start_date <= ?) OR 
                              (end_date >= ? AND end_date <= ?) OR 
                              (start_date <= ? AND end_date >= ?))";
        $bind_params[] = $academic_start;
        $bind_params[] = $academic_end;
        $bind_params[] = $academic_start;
        $bind_params[] = $academic_end;
        $bind_params[] = $academic_start;
        $bind_params[] = $academic_end;
    }
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Get events with pagination
$events_sql = "SELECT * FROM academic_calendar $where_sql ORDER BY start_date ASC LIMIT ? OFFSET ?";
$events_stmt = $conn->prepare($events_sql);

// Add limit and offset to parameters
$all_params = array_merge($bind_params, [$limit, $offset]);
$events_stmt->execute($all_params);
$events = $events_stmt->fetchAll();

// Get total events count for pagination
$count_sql = "SELECT COUNT(*) FROM academic_calendar $where_sql";
$count_stmt = $conn->prepare($count_sql);

if (!empty($bind_params)) {
    $count_stmt->execute($bind_params);
} else {
    $count_stmt->execute();
}
$total_events = $count_stmt->fetchColumn();
$total_pages = ceil($total_events / $limit);

// Get available academic years for filter
$current_year = date('Y');
$academic_years = [];
for ($i = $current_year - 2; $i <= $current_year + 2; $i++) {
    $academic_years[] = $i . '-' . ($i + 1);
}

// Get available event types for filter
$event_types_sql = "SELECT DISTINCT event_type FROM academic_calendar ORDER BY event_type ASC";
$event_types_stmt = $conn->prepare($event_types_sql);
$event_types_stmt->execute();
$event_types = $event_types_stmt->fetchAll(PDO::FETCH_COLUMN);

// If no event types found, add default ones
if (empty($event_types)) {
    $event_types = ['semester', 'exam', 'staff_development', 'restricted_leave_period'];
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Academic Calendar Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Academic Calendar</li>
    </ol>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-calendar-alt me-1"></i>
                Academic Calendar
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                <i class="fas fa-plus"></i> Add Event
            </button>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search event name or description" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="event_type">
                            <option value="">All Event Types</option>
                            <?php foreach ($event_types as $type): ?>
                                <?php 
                                $display_name = '';
                                switch ($type) {
                                    case 'semester':
                                        $display_name = 'Semester';
                                        break;
                                    case 'exam':
                                        $display_name = 'Exam Period';
                                        break;
                                    case 'staff_development':
                                        $display_name = 'Staff Development';
                                        break;
                                    case 'restricted_leave_period':
                                        $display_name = 'Restricted Leave Period';
                                        break;
                                    default:
                                        $display_name = ucfirst(str_replace('_', ' ', $type));
                                }
                                ?>
                                <option value="<?php echo $type; ?>" <?php echo $event_type == $type ? 'selected' : ''; ?>>
                                    <?php echo $display_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="academic_year">
                            <option value="">All Academic Years</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="academic_calendar.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Academic Calendar Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Event Type</th>

                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No academic events found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <?php 
                                $start = new DateTime($event['start_date']);
                                $end = new DateTime($event['end_date']);
                                $duration = $start->diff($end)->days + 1; // +1 to include both start and end days
                                
                                // Determine row class based on event type
                                $row_class = '';
                                switch ($event['event_type']) {
                                    case 'exam':
                                        $row_class = 'table-danger';
                                        break;
                                    case 'semester':
                                        $row_class = 'table-primary';
                                        break;
                                    case 'staff_development':
                                        $row_class = 'table-warning';
                                        break;
                                    case 'restricted_leave_period':
                                        $row_class = 'table-info';
                                        break;
                                }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo $event['id']; ?></td>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo $start->format('M d, Y'); ?></td>
                                    <td><?php echo $end->format('M d, Y'); ?></td>
                                    <td><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></td>
                                    <td>
                                        <?php 
                                        $display_type = '';
                                        switch ($event['event_type']) {
                                            case 'semester':
                                                $display_type = 'Semester';
                                                break;
                                            case 'exam':
                                                $display_type = 'Exam Period';
                                                break;
                                            case 'staff_development':
                                                $display_type = 'Staff Development';
                                                break;
                                            case 'restricted_leave_period':
                                                $display_type = 'Restricted Leave Period';
                                                break;
                                            default:
                                                $display_type = ucfirst(str_replace('_', ' ', $event['event_type']));
                                        }
                                        ?>
                                        <span class="badge bg-secondary"><?php echo $display_type; ?></span>
                                    </td>

                                    <td>
                                        <button type="button" class="btn btn-sm btn-info view-event" 
                                                data-bs-toggle="modal" data-bs-target="#viewEventModal"
                                                data-id="<?php echo $event['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($event['event_name']); ?>"
                                                data-start-date="<?php echo $event['start_date']; ?>"
                                                data-end-date="<?php echo $event['end_date']; ?>"
                                                data-description="<?php echo htmlspecialchars($event['description'] ?? ''); ?>"
                                                data-event-type="<?php echo $event['event_type']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-event" 
                                                data-bs-toggle="modal" data-bs-target="#editEventModal"
                                                data-id="<?php echo $event['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($event['event_name']); ?>"
                                                data-start-date="<?php echo $event['start_date']; ?>"
                                                data-end-date="<?php echo $event['end_date']; ?>"
                                                data-description="<?php echo htmlspecialchars($event['description'] ?? ''); ?>"
                                                data-event-type="<?php echo $event['event_type']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-event" 
                                                data-bs-toggle="modal" data-bs-target="#deleteEventModal"
                                                data-id="<?php echo $event['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($event['event_name']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>&academic_year=<?php echo urlencode($academic_year); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>&academic_year=<?php echo urlencode($academic_year); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>&academic_year=<?php echo urlencode($academic_year); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Add New Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_event" value="1">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select Event Type</option>
                                <option value="semester">Semester</option>
                                <option value="exam">Exam Period</option>
                                <option value="staff_development">Staff Development</option>
                                <option value="restricted_leave_period">Restricted Leave Period</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

<!-- View Event Modal -->
<div class="modal fade" id="viewEventModal" tabindex="-1" aria-labelledby="viewEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEventModalLabel">View Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Event Name:</h6>
                        <p id="view_name" class="fw-bold"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Event Type:</h6>
                        <p id="view_event_type"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Start Date:</h6>
                        <p id="view_start_date"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>End Date:</h6>
                        <p id="view_end_date"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Duration:</h6>
                        <p id="view_duration"></p>
                    </div>

                </div>
                <div class="mb-3">
                    <h6>Description:</h6>
                    <p id="view_description"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Edit Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_event" value="1">
                <input type="hidden" name="edit_event_id" id="edit_event_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="edit_name" class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_event_type" name="event_type" required>
                                <option value="">Select Event Type</option>
                                <option value="semester">Semester</option>
                                <option value="exam">Exam Period</option>
                                <option value="staff_development">Staff Development</option>
                                <option value="restricted_leave_period">Restricted Leave Period</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteEventModalLabel">Delete Academic Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="delete_event" value="1">
                <input type="hidden" name="delete_event_id" id="delete_event_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the event: <strong id="delete_event_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_event" class="btn btn-danger">Delete Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // View Event Modal
        const viewButtons = document.querySelectorAll('.view-event');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const startDate = this.getAttribute('data-start-date');
                const endDate = this.getAttribute('data-end-date');
                const description = this.getAttribute('data-description');
                const eventType = this.getAttribute('data-event-type');

                
                // Calculate duration
                const start = new Date(startDate);
                const end = new Date(endDate);
                const durationDays = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
                const duration = durationDays + ' day' + (durationDays > 1 ? 's' : '');
                
                // Format dates for display
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                const formattedStartDate = start.toLocaleDateString('en-US', options);
                const formattedEndDate = end.toLocaleDateString('en-US', options);
                
                document.getElementById('view_name').textContent = name;
                document.getElementById('view_event_type').textContent = eventType;
                document.getElementById('view_start_date').textContent = formattedStartDate;
                document.getElementById('view_end_date').textContent = formattedEndDate;
                document.getElementById('view_duration').textContent = duration;

                document.getElementById('view_description').textContent = description || 'No description provided';
            });
        });
        
        // Edit Event Modal
        const editButtons = document.querySelectorAll('.edit-event');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const startDate = this.getAttribute('data-start-date');
                const endDate = this.getAttribute('data-end-date');
                const description = this.getAttribute('data-description');
                const eventType = this.getAttribute('data-event-type');

                
                document.getElementById('edit_event_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_start_date').value = startDate;
                document.getElementById('edit_end_date').value = endDate;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_event_type').value = eventType;

            });
        });
        
        // Delete Event Modal
        const deleteButtons = document.querySelectorAll('.delete-event');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_event_id').value = id;
                document.getElementById('delete_event_name').textContent = name;
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>