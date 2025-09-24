<?php
// Dashboard Widgets for Enhanced User Experience

function getLeaveBalanceWidget($conn, $user_id) {
    $current_year = date('Y');
    $sql = "SELECT lt.name, lb.balance, lb.used, lt.max_days 
            FROM leave_balances lb 
            JOIN leave_types lt ON lb.leave_type_id = lt.id 
            WHERE lb.user_id = :user_id AND lb.year = :year
            ORDER BY lt.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
    $stmt->execute();
    $balances = $stmt->fetchAll();
    
    $html = '<div class="card mb-4 shadow-professional">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Leave Balance Summary</h5>
                </div>
                <div class="card-body">';
    
    if (count($balances) > 0) {
        $html .= '<div class="row">';
        foreach ($balances as $balance) {
            $percentage = ($balance['used'] / $balance['max_days']) * 100;
            $color_class = $percentage > 75 ? 'danger' : ($percentage > 50 ? 'warning' : 'success');
            
            $html .= '<div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 border-left-' . $color_class . '">
                            <div class="card-body">
                                <h6 class="card-title">' . htmlspecialchars($balance['name']) . '</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Available:</span>
                                    <strong>' . number_format($balance['balance'], 1) . ' days</strong>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-' . $color_class . '" style="width: ' . $percentage . '%"></div>
                                </div>
                                <small class="text-muted">' . number_format($balance['used'], 1) . '/' . $balance['max_days'] . ' days used</small>
                            </div>
                        </div>
                      </div>';
        }
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No leave balances found for the current year.
                  </div>';
    }
    
    $html .= '</div></div>';
    return $html;
}

function getPendingApprovalsWidget($conn, $user_id) {
    $sql = "SELECT COUNT(*) as pending_count 
            FROM leave_approvals lap
            JOIN leave_applications la ON lap.leave_application_id = la.id
            WHERE lap.approver_id = :user_id 
            AND lap.status = 'pending'
            AND la.status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $pending_count = $stmt->fetchColumn();
    
    $html = '<div class="card mb-4 shadow-professional dashboard-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1 fw-bold" style="color: var(--primary-medium); font-size: 0.85rem;">
                                Pending Approvals
                            </div>
                            <div class="h4 mb-0 fw-bold" style="color: var(--primary-dark);">' . $pending_count . '</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x" style="color: var(--primary-light);"></i>
                        </div>
                    </div>';
    
    if ($pending_count > 0) {
        $html .= '<div class="mt-3">
                    <a href="/modules/leave_approvals.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-eye me-1"></i>Review Applications
                    </a>
                  </div>';
    }
    
    $html .= '</div></div>';
    return $html;
}

function getUpcomingLeavesWidget($conn, $user_id) {
    $sql = "SELECT la.*, lt.name as leave_type_name
            FROM leave_applications la
            JOIN leave_types lt ON la.leave_type_id = lt.id
            WHERE la.user_id = :user_id 
            AND la.status = 'approved'
            AND la.start_date >= CURDATE()
            ORDER BY la.start_date ASC
            LIMIT 3";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $upcoming_leaves = $stmt->fetchAll();
    
    $html = '<div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Upcoming Approved Leaves</h6>
                </div>
                <div class="card-body">';
    
    if (count($upcoming_leaves) > 0) {
        foreach ($upcoming_leaves as $leave) {
            $start_date = new DateTime($leave['start_date']);
            $end_date = new DateTime($leave['end_date']);
            $days_until = $start_date->diff(new DateTime())->days;
            
            $html .= '<div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge bg-secondary">' . 
                            htmlspecialchars($leave['leave_type_name']) . '</span>
                            <small class="text-muted d-block">' . 
                            $start_date->format('M d') . ' - ' . $end_date->format('M d, Y') . '</small>
                        </div>
                        <small class="text-primary">' . $days_until . ' days away</small>
                      </div>';
        }
    } else {
        $html .= '<div class="text-center text-muted">
                    <i class="fas fa-calendar-times fa-2x mb-2"></i>
                    <p>No upcoming approved leaves</p>
                  </div>';
    }
    
    $html .= '</div></div>';
    return $html;
}

function getQuickActionsWidget($role) {
    $html = '<div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">';
    
    // Common actions for all users
    $html .= '<a href="../modules/apply_leave.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Apply for Leave
              </a>
              <a href="../modules/my_leaves.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list me-1"></i>My Applications
              </a>';
    
    // Role-specific actions
    if (in_array($role, ['department_head', 'dean', 'principal', 'hr_admin'])) {
        $html .= '<a href="../modules/leave_approvals.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-check-circle me-1"></i>Pending Approvals
                  </a>';
    }
    
    if ($role === 'hr_admin') {
        $html .= '<a href="../modules/reports.php" class="btn btn-success btn-sm">
                    <i class="fas fa-chart-bar me-1"></i>Generate Reports
                  </a>
                  <a href="../admin/users.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-users me-1"></i>Manage Users
                  </a>';
    }
    
    $html .= '</div></div></div>';
    return $html;
}

function getNotificationWidget($conn, $user_id) {
    $sql = "SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = :user_id AND is_read = 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $unread_count = $stmt->fetchColumn();
    
    // Get recent notifications
    $sql = "SELECT title, message, created_at 
            FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 3";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $recent_notifications = $stmt->fetchAll();
    
    $html = '<div class="card mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>';
    
    if ($unread_count > 0) {
        $html .= '<span class="badge bg-danger">' . $unread_count . '</span>';
    }
    
    $html .= '</div><div class="card-body">';
    
    if (count($recent_notifications) > 0) {
        foreach ($recent_notifications as $notification) {
            $time_ago = time_elapsed_string($notification['created_at']);
            $html .= '<div class="notification-item mb-2 pb-2 border-bottom">
                        <h6 class="mb-1">' . htmlspecialchars($notification['title']) . '</h6>
                        <p class="mb-1 small text-muted">' . htmlspecialchars($notification['message']) . '</p>
                        <small class="text-muted">' . $time_ago . '</small>
                      </div>';
        }
        
        $html .= '<div class="text-center mt-3">
                    <a href="../modules/notifications.php" class="btn btn-outline-dark btn-sm">
                        View All Notifications
                    </a>
                  </div>';
    } else {
        $html .= '<div class="text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p>No recent notifications</p>
                  </div>';
    }
    
    $html .= '</div></div>';
    return $html;
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>