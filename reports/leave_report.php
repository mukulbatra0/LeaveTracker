<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['hr_admin', 'principal', 'dean'])) {
    header("Location: ../login.php");
    exit;
}

include_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Leave Reports</h1>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Generate Leave Report</h5>
            <form method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? date('Y-m-t'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($_GET['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($_GET['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['start_date']) || isset($_GET['end_date'])): ?>
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title">Leave Applications Report</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT la.*, u.first_name, u.last_name, lt.name as leave_type_name 
                                FROM leave_applications la 
                                JOIN users u ON la.user_id = u.id 
                                JOIN leave_types lt ON la.leave_type_id = lt.id 
                                WHERE 1=1";
                        
                        $params = [];
                        
                        if (!empty($_GET['start_date'])) {
                            $sql .= " AND la.start_date >= :start_date";
                            $params[':start_date'] = $_GET['start_date'];
                        }
                        
                        if (!empty($_GET['end_date'])) {
                            $sql .= " AND la.end_date <= :end_date";
                            $params[':end_date'] = $_GET['end_date'];
                        }
                        
                        if (!empty($_GET['status'])) {
                            $sql .= " AND la.status = :status";
                            $params[':status'] = $_GET['status'];
                        }
                        
                        $sql .= " ORDER BY la.created_at DESC";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        $applications = $stmt->fetchAll();
                        
                        foreach ($applications as $app):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['leave_type_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['end_date'])); ?></td>
                            <td><?php echo $app['days']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $app['status'] == 'approved' ? 'success' : 
                                        ($app['status'] == 'rejected' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>