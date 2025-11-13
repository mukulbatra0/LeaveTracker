<?php
/**
 * Display test users for leave approval workflow testing
 */

require_once 'config/db.php';
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-users me-2"></i>Test Users for Leave Approval Workflow</h4>
                    <p class="mb-0 text-muted">Use these accounts to test the complete leave approval process</p>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $conn->query("
                            SELECT 
                                u.employee_id,
                                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                u.email,
                                u.role,
                                u.position,
                                u.status,
                                d.name as department_name
                            FROM users u
                            LEFT JOIN departments d ON u.department_id = d.id
                            WHERE u.employee_id IN ('DIR001', 'ADM001', 'HOD001', 'STF001')
                            ORDER BY 
                                CASE u.role 
                                    WHEN 'admin' THEN 1
                                    WHEN 'director' THEN 2
                                    WHEN 'head_of_department' THEN 3
                                    WHEN 'staff' THEN 4
                                END
                        ");
                        
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($users) > 0) {
                    ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Default Password:</strong> password123 (for all test accounts)
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['employee_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        $role_class = '';
                                        $role_display = '';
                                        switch($user['role']) {
                                            case 'admin':
                                                $role_class = 'danger';
                                                $role_display = 'Administrator';
                                                break;
                                            case 'director':
                                                $role_class = 'primary';
                                                $role_display = 'Director';
                                                break;
                                            case 'head_of_department':
                                                $role_class = 'warning';
                                                $role_display = 'Head of Department';
                                                break;
                                            case 'staff':
                                                $role_class = 'success';
                                                $role_display = 'Staff';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $role_class; ?>"><?php echo $role_display; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['position']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="login.php?email=<?php echo urlencode($user['email']); ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-sign-in-alt me-1"></i>Quick Login
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php } else { ?>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No test users found!</strong> 
                        <a href="add_test_users.php" class="btn btn-sm btn-warning ms-2">
                            <i class="fas fa-plus me-1"></i>Create Test Users
                        </a>
                    </div>
                    
                    <?php } ?>
                    
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-route me-2"></i>Testing Workflow</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-user fa-2x text-success mb-2"></i>
                                    <h6>Step 1: Staff</h6>
                                    <p class="small">Login as <strong>STF001</strong><br>Submit leave application</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-tie fa-2x text-warning mb-2"></i>
                                    <h6>Step 2: Head of Dept</h6>
                                    <p class="small">Login as <strong>HOD001</strong><br>First level approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                                    <h6>Step 3: Director</h6>
                                    <p class="small">Login as <strong>DIR001</strong><br>Final approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-shield fa-2x text-danger mb-2"></i>
                                    <h6>Admin Override</h6>
                                    <p class="small">Login as <strong>ADM001</strong><br>Emergency approval</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
?>

<?php include_once 'includes/footer.php'; ?>