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

// Process mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        
        $_SESSION['alert'] = "Notification marked as read.";
        $_SESSION['alert_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect to remove the GET parameter
    header('Location: ./modules/notifications.php');
    exit;
}

// Process mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['alert'] = "All notifications marked as read.";
        $_SESSION['alert_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect to remove the GET parameter
    header('Location: ./modules/notifications.php');
    exit;
}

// Process delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        
        $_SESSION['alert'] = "Notification deleted.";
        $_SESSION['alert_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect to remove the GET parameter
    header('Location: ./modules/notifications.php');
    exit;
}

// Get notifications for the current user
try {
    // Get pagination settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'pagination_limit'");
    $stmt->execute();
    $pagination_limit = $stmt->fetchColumn() ?: 10;
    
    // Calculate pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $pagination_limit;
    
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notifications = $stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $pagination_limit);
    
    // Get notifications with pagination
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $pagination_limit, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['alert'] = "Error: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    $notifications = [];
    $total_pages = 1;
    $unread_count = 0;
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Notifications</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Notifications</li>
    </ol>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-bell me-1"></i>
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
            </div>
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary">Mark All as Read</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <p class="lead">No notifications to display</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'list-group-item-light'; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h5 class="mb-1">
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-primary me-2">New</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h5>
                                <small class="text-muted">
                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                </small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <?php if (!empty($notification['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                                <div>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">Mark as Read</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Notification pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>