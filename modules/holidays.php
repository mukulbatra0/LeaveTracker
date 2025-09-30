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
    // Add new holiday
    if (isset($_POST['add_holiday'])) {
        $name = trim($_POST['name']);
        $date = trim($_POST['date']);
        $description = trim($_POST['description'] ?? '');
        $type = trim($_POST['type'] ?? 'public');
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Holiday name is required";
        if (empty($date)) $errors[] = "Holiday date is required";
        
        // Validate date format
        if (!empty($date)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
                $errors[] = "Invalid date format. Please use YYYY-MM-DD format.";
            }
        }
        
        // Check if holiday already exists on the same date
        $check_date_sql = "SELECT id FROM holidays WHERE date = :date";
        $check_date_stmt = $conn->prepare($check_date_sql);
        $check_date_stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $check_date_stmt->execute();
        
        if ($check_date_stmt->rowCount() > 0) {
            $errors[] = "A holiday already exists on this date";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert holiday
                $insert_sql = "INSERT INTO holidays (name, date, description, type, is_recurring, created_at) 
                               VALUES (:name, :date, :description, :type, :is_recurring, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(':type', $type, PDO::PARAM_STR);
                $insert_stmt->bindParam(':is_recurring', $is_recurring, PDO::PARAM_INT);
                $insert_stmt->execute();
                
                $new_holiday_id = $conn->lastInsertId();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'create', 'holiday', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $new_holiday_id, PDO::PARAM_INT);
                $description = "Created new holiday: $name on $date";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Holiday created successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/holidays.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error creating holiday: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Edit holiday
    if (isset($_POST['edit_holiday'])) {
        $edit_holiday_id = $_POST['edit_holiday_id'];
        $name = trim($_POST['name']);
        $date = trim($_POST['date']);
        $description = trim($_POST['description'] ?? '');
        $type = trim($_POST['type'] ?? 'public');
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = "Holiday name is required";
        if (empty($date)) $errors[] = "Holiday date is required";
        
        // Validate date format
        if (!empty($date)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
                $errors[] = "Invalid date format. Please use YYYY-MM-DD format.";
            }
        }
        
        // Check if holiday already exists on the same date (excluding current holiday)
        $check_date_sql = "SELECT id FROM holidays WHERE date = :date AND id != :holiday_id";
        $check_date_stmt = $conn->prepare($check_date_sql);
        $check_date_stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $check_date_stmt->bindParam(':holiday_id', $edit_holiday_id, PDO::PARAM_INT);
        $check_date_stmt->execute();
        
        if ($check_date_stmt->rowCount() > 0) {
            $errors[] = "Another holiday already exists on this date";
        }
        
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update holiday
                $update_sql = "UPDATE holidays SET 
                              name = :name, 
                              date = :date, 
                              description = :description, 
                              type = :type, 
                              is_recurring = :is_recurring, 
                              updated_at = NOW() 
                              WHERE id = :holiday_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':type', $type, PDO::PARAM_STR);
                $update_stmt->bindParam(':is_recurring', $is_recurring, PDO::PARAM_INT);
                $update_stmt->bindParam(':holiday_id', $edit_holiday_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'update', 'holiday', :entity_id, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $edit_holiday_id, PDO::PARAM_INT);
                $description = "Updated holiday: $name on $date";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Holiday updated successfully!";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/holidays.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error updating holiday: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Please fix the following errors: " . implode(", ", $errors);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Delete holiday
    if (isset($_POST['delete_holiday'])) {
        $delete_holiday_id = $_POST['delete_holiday_id'];
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get holiday info for logging
            $holiday_info_sql = "SELECT name, date FROM holidays WHERE id = :holiday_id";
            $holiday_info_stmt = $conn->prepare($holiday_info_sql);
            $holiday_info_stmt->bindParam(':holiday_id', $delete_holiday_id, PDO::PARAM_INT);
            $holiday_info_stmt->execute();
            $holiday_info = $holiday_info_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete holiday
            $delete_holiday_sql = "DELETE FROM holidays WHERE id = :holiday_id";
            $delete_holiday_stmt = $conn->prepare($delete_holiday_sql);
            $delete_holiday_stmt->bindParam(':holiday_id', $delete_holiday_id, PDO::PARAM_INT);
            $delete_holiday_stmt->execute();
            
            // Log action
            $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                       VALUES (:user_id, 'delete', 'holiday', :entity_id, :description, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $log_stmt->bindParam(':entity_id', $delete_holiday_id, PDO::PARAM_INT);
            $description = "Deleted holiday: {$holiday_info['name']} on {$holiday_info['date']}";
            $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['alert'] = "Holiday deleted successfully!";
            $_SESSION['alert_type'] = "success";
            header('Location: ./modules/holidays.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $_SESSION['alert'] = "Error deleting holiday: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Import holidays
    if (isset($_POST['import_holidays'])) {
        $year = intval($_POST['year']);
        
        if ($year < 2000 || $year > 2100) {
            $_SESSION['alert'] = "Please enter a valid year between 2000 and 2100.";
            $_SESSION['alert_type'] = "danger";
        } else {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Get recurring holidays from previous years
                $recurring_sql = "SELECT name, CONCAT(:year, '-', DATE_FORMAT(date, '%m-%d')) as new_date, description, type, is_recurring 
                                FROM holidays 
                                WHERE is_recurring = 1";
                $recurring_stmt = $conn->prepare($recurring_sql);
                $recurring_stmt->bindParam(':year', $year, PDO::PARAM_INT);
                $recurring_stmt->execute();
                $recurring_holidays = $recurring_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $imported_count = 0;
                
                foreach ($recurring_holidays as $holiday) {
                    // Check if holiday already exists on this date
                    $check_sql = "SELECT id FROM holidays WHERE date = :date";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindParam(':date', $holiday['new_date'], PDO::PARAM_STR);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() == 0) {
                        // Insert new holiday for this year
                        $insert_sql = "INSERT INTO holidays (name, date, description, type, is_recurring, created_at) 
                                       VALUES (:name, :date, :description, :type, :is_recurring, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':name', $holiday['name'], PDO::PARAM_STR);
                        $insert_stmt->bindParam(':date', $holiday['new_date'], PDO::PARAM_STR);
                        $insert_stmt->bindParam(':description', $holiday['description'], PDO::PARAM_STR);
                        $insert_stmt->bindParam(':type', $holiday['type'], PDO::PARAM_STR);
                        $insert_stmt->bindParam(':is_recurring', $holiday['is_recurring'], PDO::PARAM_INT);
                        $insert_stmt->execute();
                        
                        $imported_count++;
                    }
                }
                
                // Log action
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at) 
                           VALUES (:user_id, 'import', 'holiday', 0, :description, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $description = "Imported $imported_count recurring holidays for year $year";
                $log_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Successfully imported $imported_count recurring holidays for year $year.";
                $_SESSION['alert_type'] = "success";
                header('Location: ./modules/holidays.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['alert'] = "Error importing holidays: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
}

// Get current year
$current_year = date('Y');

// Get filter values
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';


// Build query based on filters
$holidays_sql = "SELECT * FROM holidays WHERE 1=1";
$params = [];

if ($filter_year > 0) {
    $holidays_sql .= " AND YEAR(date) = :year";
    $params[':year'] = $filter_year;
}

if ($filter_type != 'all') {
    $holidays_sql .= " AND type = :type";
    $params[':type'] = $filter_type;
}



$holidays_sql .= " ORDER BY date";
$holidays_stmt = $conn->prepare($holidays_sql);

// Bind parameters
foreach ($params as $key => $value) {
    $holidays_stmt->bindValue($key, $value);
}

$holidays_stmt->execute();
$holidays = $holidays_stmt->fetchAll();

// Get unique years for filter dropdown
$years_sql = "SELECT DISTINCT YEAR(date) as year FROM holidays ORDER BY year DESC";
$years_stmt = $conn->prepare($years_sql);
$years_stmt->execute();
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);



// Include header
include_once '../includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calendar me-2"></i>Holiday Management</h2>
            <p class="text-muted">Manage institutional holidays and observances</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                <i class="fas fa-plus-circle me-1"></i> Add New Holiday
            </button>
            <button type="button" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#importHolidaysModal">
                <i class="fas fa-file-import me-1"></i> Import Recurring
            </button>
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
            <form method="get" action="modules/holidays.php" class="row g-3">
                <div class="col-md-4">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <option value="0">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="type" class="form-label">Holiday Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="all" <?php echo ($filter_type == 'all') ? 'selected' : ''; ?>>All Types</option>
                        <option value="public" <?php echo ($filter_type == 'public') ? 'selected' : ''; ?>>Public Holiday</option>
                        <option value="restricted" <?php echo ($filter_type == 'restricted') ? 'selected' : ''; ?>>Restricted Holiday</option>
                        <option value="institutional" <?php echo ($filter_type == 'institutional') ? 'selected' : ''; ?>>Institutional Holiday</option>
                        <option value="observance" <?php echo ($filter_type == 'observance') ? 'selected' : ''; ?>>Observance</option>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <a href="./modules/holidays.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="holidaysTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Holiday Name</th>
                            <th>Type</th>

                            <th>Description</th>
                            <th>Recurring</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($holidays) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">No holidays found matching your filters.</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($holidays as $holiday): ?>
                            <?php 
                                $holiday_date = new DateTime($holiday['date']);
                                $is_past = $holiday_date < new DateTime('today');
                                $row_class = $is_past ? 'text-muted' : '';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <?php echo $holiday_date->format('M d, Y'); ?>
                                    <?php 
                                        $day_of_week = $holiday_date->format('l');
                                        echo "<small class='text-muted d-block'>$day_of_week</small>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                                <td>
                                    <?php 
                                        $badge_class = '';
                                        switch ($holiday['type']) {
                                            case 'public':
                                                $badge_class = 'bg-success';
                                                break;
                                            case 'restricted':
                                                $badge_class = 'bg-warning';
                                                break;
                                            case 'institutional':
                                                $badge_class = 'bg-info';
                                                break;
                                            case 'observance':
                                                $badge_class = 'bg-secondary';
                                                break;
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(htmlspecialchars($holiday['type'])); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php 
                                        if (!empty($holiday['description'])) {
                                            echo htmlspecialchars(substr($holiday['description'], 0, 50));
                                            if (strlen($holiday['description']) > 50) {
                                                echo '...';
                                            }
                                        } else {
                                            echo '<span class="text-muted">No description</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($holiday['is_recurring']): ?>
                                        <span class="badge bg-primary">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-holiday-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editHolidayModal"
                                            data-id="<?php echo $holiday['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                            data-date="<?php echo htmlspecialchars($holiday['date']); ?>"
                                            data-description="<?php echo htmlspecialchars($holiday['description'] ?? ''); ?>"
                                            data-type="<?php echo htmlspecialchars($holiday['type']); ?>"
                
                                            data-recurring="<?php echo $holiday['is_recurring']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-holiday-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteHolidayModal"
                                            data-id="<?php echo $holiday['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                            data-date="<?php echo $holiday_date->format('M d, Y'); ?>">
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

<!-- Add Holiday Modal -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHolidayModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/holidays.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Holiday Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="public">Public Holiday</option>
                            <option value="restricted">Restricted Holiday</option>
                            <option value="institutional">Institutional Holiday</option>
                            <option value="observance">Observance</option>
                        </select>
                    </div>
                    

                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring">
                            <label class="form-check-label" for="is_recurring">
                                Recurring Holiday (repeats yearly)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_holiday" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Holiday Modal -->
<div class="modal fade" id="editHolidayModal" tabindex="-1" aria-labelledby="editHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editHolidayModalLabel"><i class="fas fa-edit me-2"></i>Edit Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/holidays.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_holiday_id" name="edit_holiday_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_date" name="date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Holiday Type</label>
                        <select class="form-select" id="edit_type" name="type">
                            <option value="public">Public Holiday</option>
                            <option value="restricted">Restricted Holiday</option>
                            <option value="institutional">Institutional Holiday</option>
                            <option value="observance">Observance</option>
                        </select>
                    </div>
                    

                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_recurring" name="is_recurring">
                            <label class="form-check-label" for="edit_is_recurring">
                                Recurring Holiday (repeats yearly)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_holiday" class="btn btn-primary">Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Holiday Modal -->
<div class="modal fade" id="deleteHolidayModal" tabindex="-1" aria-labelledby="deleteHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteHolidayModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/holidays.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_holiday_id" name="delete_holiday_id">
                    <p>Are you sure you want to delete the holiday <strong id="delete_holiday_name"></strong> on <strong id="delete_holiday_date"></strong>?</p>
                    <p class="text-danger"><strong>Note:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_holiday" class="btn btn-danger">Delete Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Holidays Modal -->
<div class="modal fade" id="importHolidaysModal" tabindex="-1" aria-labelledby="importHolidaysModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importHolidaysModalLabel"><i class="fas fa-file-import me-2"></i>Import Recurring Holidays</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="modules/holidays.php" method="post">
                <div class="modal-body">
                    <p>This will import all recurring holidays for the specified year.</p>
                    
                    <div class="mb-3">
                        <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="import_year" name="year" min="2000" max="2100" value="<?php echo date('Y') + 1; ?>" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Only holidays marked as recurring will be imported. Existing holidays for the selected year will be skipped.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="import_holidays" class="btn btn-primary">Import Holidays</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#holidaysTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "columnDefs": [
                { "orderable": false, "targets": 6 }
            ]
        });
        
        // Edit Holiday Modal
        document.querySelectorAll('.edit-holiday-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const date = this.getAttribute('data-date');
                const description = this.getAttribute('data-description');
                const type = this.getAttribute('data-type');
                const recurring = this.getAttribute('data-recurring') === '1';
                
                document.getElementById('edit_holiday_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_date').value = date;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_is_recurring').checked = recurring;
            });
        });
        
        // Delete Holiday Modal
        document.querySelectorAll('.delete-holiday-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const date = this.getAttribute('data-date');
                
                document.getElementById('delete_holiday_id').value = id;
                document.getElementById('delete_holiday_name').textContent = name;
                document.getElementById('delete_holiday_date').textContent = date;
            });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>