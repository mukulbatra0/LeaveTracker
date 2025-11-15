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
    // Add new holiday
    if (isset($_POST['add_holiday'])) {
        $name = trim($_POST['name']);
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Holiday name is required";
        }
        
        if (empty($date)) {
            $errors[] = "Holiday date is required";
        } else {
            // Check if date is valid
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
                $errors[] = "Invalid date format";
            } else {
                // Check if holiday already exists on this date
                $check_date_sql = "SELECT COUNT(*) FROM holidays WHERE date = :date";
                $check_date_stmt = $conn->prepare($check_date_sql);
                $check_date_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $check_date_stmt->execute();
                
                if ($check_date_stmt->fetchColumn() > 0) {
                    $errors[] = "A holiday already exists on this date";
                }
            }
        }
        
        // If no errors, insert new holiday
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $insert_sql = "INSERT INTO holidays (name, date, description, created_at) 
                              VALUES (:name, :date, :description, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insert_stmt->execute();
                
                // Add audit log
                $action = "Created new holiday: $name on $date";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Holiday added successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: holidays.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Edit holiday
    if (isset($_POST['edit_holiday'])) {
        $edit_holiday_id = $_POST['edit_holiday_id'];
        $name = trim($_POST['name']);
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Holiday name is required";
        }
        
        if (empty($date)) {
            $errors[] = "Holiday date is required";
        } else {
            // Check if date is valid
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
                $errors[] = "Invalid date format";
            } else {
                // Check if holiday already exists on this date (excluding current holiday)
                $check_date_sql = "SELECT COUNT(*) FROM holidays WHERE date = :date AND id != :holiday_id";
                $check_date_stmt = $conn->prepare($check_date_sql);
                $check_date_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $check_date_stmt->bindParam(':holiday_id', $edit_holiday_id, PDO::PARAM_INT);
                $check_date_stmt->execute();
                
                if ($check_date_stmt->fetchColumn() > 0) {
                    $errors[] = "A holiday already exists on this date";
                }
            }
        }
        
        // If no errors, update holiday
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $update_sql = "UPDATE holidays SET 
                              name = :name, 
                              date = :date, 
                              description = :description, 
                              updated_at = NOW() 
                              WHERE id = :holiday_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $update_stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $update_stmt->bindParam(':holiday_id', $edit_holiday_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Add audit log
                $action = "Updated holiday ID $edit_holiday_id: $name on $date";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Holiday updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: holidays.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Delete holiday
    if (isset($_POST['delete_holiday'])) {
        $delete_holiday_id = $_POST['delete_holiday_id'];
        
        try {
            $conn->beginTransaction();
            
            // Get holiday details for audit log
            $holiday_sql = "SELECT name, date FROM holidays WHERE id = :holiday_id";
            $holiday_stmt = $conn->prepare($holiday_sql);
            $holiday_stmt->bindParam(':holiday_id', $delete_holiday_id, PDO::PARAM_INT);
            $holiday_stmt->execute();
            $holiday = $holiday_stmt->fetch();
            
            // Delete holiday
            $delete_sql = "DELETE FROM holidays WHERE id = :holiday_id";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bindParam(':holiday_id', $delete_holiday_id, PDO::PARAM_INT);
            $delete_stmt->execute();
            
            // Add audit log
            $action = "Deleted holiday: {$holiday['name']} on {$holiday['date']}";
            $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $audit_stmt->execute();
            
            $conn->commit();
            
            $_SESSION['alert'] = "Holiday deleted successfully.";
            $_SESSION['alert_type'] = "success";
            header("Location: holidays.php");
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get all holidays with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

$where_conditions = [];
$bind_params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $bind_params[] = "%$search%";
    $bind_params[] = "%$search%";
}

if (!empty($year)) {
    $where_conditions[] = "YEAR(date) = ?";
    $bind_params[] = $year;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

$holidays_sql = "SELECT * FROM holidays $where_sql ORDER BY date ASC LIMIT $limit OFFSET $offset";
$holidays_stmt = $conn->prepare($holidays_sql);

// Bind parameters using positional binding
for ($i = 0; $i < count($bind_params); $i++) {
    $holidays_stmt->bindValue($i + 1, $bind_params[$i]);
}

$holidays_stmt->execute();
$holidays = $holidays_stmt->fetchAll();

// Get total holidays count for pagination
$count_sql = "SELECT COUNT(*) FROM holidays $where_sql";
$count_stmt = $conn->prepare($count_sql);

// Bind parameters using positional binding
for ($i = 0; $i < count($bind_params); $i++) {
    $count_stmt->bindValue($i + 1, $bind_params[$i]);
}

$count_stmt->execute();
$total_holidays = $count_stmt->fetchColumn();
$total_pages = ceil($total_holidays / $limit);

// Get available years for filter
$years_sql = "SELECT DISTINCT YEAR(date) as year FROM holidays ORDER BY year DESC";
$years_stmt = $conn->prepare($years_sql);
$years_stmt->execute();
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

// If no years found, add current year
if (empty($available_years)) {
    $available_years = [date('Y')];
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Holiday Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Holidays</li>
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
                <i class="fas fa-calendar me-1"></i>
                Holidays
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                <i class="fas fa-plus"></i> Add Holiday
            </button>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search holiday name or description" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="holidays.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Holidays Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($holidays)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No holidays found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($holidays as $holiday): ?>
                                <tr>
                                    <td><?php echo $holiday['id']; ?></td>
                                    <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                                    <td>
                                        <?php 
                                        $date = new DateTime($holiday['date']);
                                        echo $date->format('M d, Y') . ' (' . $date->format('l') . ')';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($holiday['description'] ?? 'N/A'); ?></td>

                                    <td><?php echo date('M d, Y', strtotime($holiday['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-holiday" 
                                                data-bs-toggle="modal" data-bs-target="#editHolidayModal"
                                                data-id="<?php echo $holiday['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                                data-date="<?php echo $holiday['date']; ?>"
                                                data-description="<?php echo htmlspecialchars($holiday['description'] ?? ''); ?>"

                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-holiday" 
                                                data-bs-toggle="modal" data-bs-target="#deleteHolidayModal"
                                                data-id="<?php echo $holiday['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($holiday['name']); ?>">
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $year; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $year; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $year; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Holiday Modal -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHolidayModalLabel">Add New Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_holiday" value="1">
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
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                <h5 class="modal-title" id="editHolidayModalLabel">Edit Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_holiday" value="1">
                <input type="hidden" name="edit_holiday_id" id="edit_holiday_id">
                <div class="modal-body">
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
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
                <h5 class="modal-title" id="deleteHolidayModalLabel">Delete Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="delete_holiday" value="1">
                <input type="hidden" name="delete_holiday_id" id="delete_holiday_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the holiday: <strong id="delete_holiday_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_holiday" class="btn btn-danger">Delete Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Holiday Modal
        const editButtons = document.querySelectorAll('.edit-holiday');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const date = this.getAttribute('data-date');
                const description = this.getAttribute('data-description');
                
                document.getElementById('edit_holiday_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_date').value = date;
                document.getElementById('edit_description').value = description;
            });
        });
        
        // Delete Holiday Modal
        const deleteButtons = document.querySelectorAll('.delete-holiday');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_holiday_id').value = id;
                document.getElementById('delete_holiday_name').textContent = name;
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>