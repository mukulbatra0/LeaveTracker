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

// Check if document upload is enabled
try {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_document_upload'");
    $stmt->execute();
    $enable_document_upload = $stmt->fetchColumn();
    
    if ($enable_document_upload !== '1') {
        $_SESSION['alert'] = "Document upload feature is currently disabled.";
        $_SESSION['alert_type'] = "warning";
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    // Default to enabled if setting not found
    $enable_document_upload = '1';
}

// Get system settings for file uploads
try {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_file_size'");
    $stmt->execute();
    $max_file_size = ($stmt->fetchColumn() ?: 5) * 1024 * 1024; // Convert MB to bytes
    
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allowed_file_types'");
    $stmt->execute();
    $allowed_types_str = $stmt->fetchColumn() ?: 'pdf,doc,docx,jpg,jpeg,png';
    $allowed_types = explode(',', $allowed_types_str);
} catch (PDOException $e) {
    // Default values if settings not found
    $max_file_size = 5 * 1024 * 1024; // 5MB
    $allowed_types_str = 'pdf,doc,docx,jpg,jpeg,png';
    $allowed_types = explode(',', $allowed_types_str);
}

// Initialize variables
$errors = [];
$success = false;
$documents = [];

// Handle document download
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $document_id = $_GET['download'];
    
    try {
        // Get document information
        $stmt = $conn->prepare("SELECT d.*, la.user_id as leave_user_id 
                              FROM documents d 
                              LEFT JOIN leave_applications la ON d.leave_application_id = la.id 
                              WHERE d.id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            $_SESSION['alert'] = "Document not found.";
            $_SESSION['alert_type'] = "danger";
            header("Location: ../modules/documents.php");
            exit;
        }
        
        // Check if user has permission to download
        $can_download = false;
        
        // Document owner can download
        if ($document['leave_user_id'] == $user_id) {
            $can_download = true;
        }
        
        // HR admin can download any document
        if ($role == 'hr_admin') {
            $can_download = true;
        }
        
        // Department head, dean, principal can download documents for their approval chain
        if (in_array($role, ['department_head', 'dean', 'principal'])) {
            if ($document['leave_application_id']) {
                // Check if user is in approval chain
                $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_approvals 
                                      WHERE leave_application_id = ? AND approver_id = ?");
                $stmt->execute([$document['leave_application_id'], $user_id]);
                $is_approver = $stmt->fetchColumn() > 0;
                
                if ($is_approver) {
                    $can_download = true;
                }
            }
        }
        
        if (!$can_download) {
            $_SESSION['alert'] = "You don't have permission to download this document.";
            $_SESSION['alert_type'] = "danger";
            header("Location: ../modules/documents.php");
            exit;
        }
        
        // File path
        $file_path = '../' . $document['file_path'];
        
        if (!file_exists($file_path)) {
            $_SESSION['alert'] = "File not found on server.";
            $_SESSION['alert_type'] = "danger";
            header("Location: ../modules/documents.php");
            exit;
        }
        
        // Log the download
        $action = "Downloaded document: " . $document['file_name'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
        
        // Set headers and output file
        header('Content-Description: File Transfer');
        header('Content-Type: ' . mime_content_type($file_path));
        header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: ../modules/documents.php");
        exit;
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $document_id = $_GET['delete'];
    
    try {
        // Get document information
        $stmt = $conn->prepare("SELECT d.*, la.user_id as leave_user_id, la.status as leave_status 
                              FROM documents d 
                              LEFT JOIN leave_applications la ON d.leave_application_id = la.id 
                              WHERE d.id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            $_SESSION['alert'] = "Document not found.";
            $_SESSION['alert_type'] = "danger";
            header("Location: ../modules/documents.php");
            exit;
        }
        
        // Check if user has permission to delete
        $can_delete = false;
        
        // Document owner can delete if leave is pending or document is not attached to leave
        if ($document['leave_user_id'] == $user_id && (!$document['leave_application_id'] || $document['leave_status'] == 'pending')) {
            $can_delete = true;
        }
        
        // HR admin can delete any document
        if ($role == 'hr_admin') {
            $can_delete = true;
        }
        
        if (!$can_delete) {
            $_SESSION['alert'] = "You don't have permission to delete this document.";
            $_SESSION['alert_type'] = "danger";
            header("Location: ../modules/documents.php");
            exit;
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        
        // Delete file from server
        $file_path = '../' . $document['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Log the action
        $action = "Deleted document: " . $document['file_name'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
        
        $conn->commit();
        
        $_SESSION['alert'] = "Document deleted successfully.";
        $_SESSION['alert_type'] = "success";
        header("Location: ../modules/documents.php");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: ../modules/documents.php");
        exit;
    }
}

// Process document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    // Check if file was uploaded without errors
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $file = $_FILES['document'];
        $file_name = $file['name'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $document_type = $_POST['document_type'];
        $description = trim($_POST['description']);
        
        // Validate file size
        if ($file_size > $max_file_size) {
            $errors[] = "File size exceeds the maximum limit of " . ($max_file_size / 1024 / 1024) . "MB.";
        }
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Allowed types: " . $allowed_types_str;
        }
        
        if (empty($document_type)) {
            $errors[] = "Document type is required.";
        }
        
        if (empty($errors)) {
            try {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../uploads/documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = 'doc_' . $user_id . '_' . time() . '.' . $file_type;
                $upload_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Begin transaction
                    $conn->beginTransaction();
                    
                    // Insert document record
                    $file_path = 'uploads/documents/' . $new_filename;
                    $stmt = $conn->prepare("INSERT INTO documents (user_id, file_name, file_path, file_type, file_size, document_type, description, uploaded_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$user_id, $file_name, $file_path, $file_type, $file_size, $document_type, $description]);
                    
                    // Log the action
                    $action = "Uploaded document: " . $file_name;
                    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
                    
                    $conn->commit();
                    
                    $_SESSION['alert'] = "Document uploaded successfully.";
                    $_SESSION['alert_type'] = "success";
                    $success = true;
                } else {
                    $errors[] = "Failed to upload file. Please try again.";
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Please select a file to upload.";
    }
}

// Get user's documents
try {
    // Get pagination settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'pagination_limit'");
    $stmt->execute();
    $pagination_limit = $stmt->fetchColumn() ?: 10;
    
    // Calculate pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $pagination_limit;
    
    // Get total count
    if ($role == 'hr_admin') {
        // HR admin can see all documents
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents");
        $stmt->execute();
    } else {
        // Other users can only see their own documents
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    $total_documents = $stmt->fetchColumn();
    $total_pages = ceil($total_documents / $pagination_limit);
    
    // Get documents with pagination
    if ($role == 'hr_admin') {
        $stmt = $conn->prepare("SELECT d.*, u.first_name, u.last_name, u.employee_id, 
                                la.id as leave_application_id, la.status as leave_status 
                              FROM documents d 
                              LEFT JOIN users u ON d.user_id = u.id 
                              LEFT JOIN leave_applications la ON d.leave_application_id = la.id 
                              ORDER BY d.uploaded_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->bindParam(1, $pagination_limit, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("SELECT d.*, u.first_name, u.last_name, u.employee_id, 
                                la.id as leave_application_id, la.status as leave_status 
                              FROM documents d 
                              LEFT JOIN users u ON d.user_id = u.id 
                              LEFT JOIN leave_applications la ON d.leave_application_id = la.id 
                              WHERE d.user_id = ? 
                              ORDER BY d.uploaded_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $pagination_limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving documents: " . $e->getMessage();
    $documents = [];
    $total_pages = 1;
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Document Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Document Management</li>
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
        <!-- Upload Document Form -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-upload me-1"></i>
                    Upload Document
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="document" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="document" name="document" required>
                            <div class="form-text">
                                Max file size: <?php echo $max_file_size / 1024 / 1024; ?>MB<br>
                                Allowed file types: <?php echo $allowed_types_str; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <option value="medical_certificate">Medical Certificate</option>
                                <option value="conference_invitation">Conference Invitation</option>
                                <option value="travel_document">Travel Document</option>
                                <option value="official_letter">Official Letter</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" name="upload_document" class="btn btn-primary">Upload Document</button>
                    </form>
                </div>
            </div>
            
            <!-- Document Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Document Statistics
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Get document type statistics
                        if ($role == 'hr_admin') {
                            $stmt = $conn->prepare("SELECT document_type, COUNT(*) as count 
                                                  FROM documents 
                                                  GROUP BY document_type 
                                                  ORDER BY count DESC");
                            $stmt->execute();
                        } else {
                            $stmt = $conn->prepare("SELECT document_type, COUNT(*) as count 
                                                  FROM documents 
                                                  WHERE user_id = ? 
                                                  GROUP BY document_type 
                                                  ORDER BY count DESC");
                            $stmt->execute([$user_id]);
                        }
                        $doc_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($doc_stats)) {
                            echo "<h5>Document Types</h5>";
                            echo "<div class='list-group'>";
                            foreach ($doc_stats as $stat) {
                                $type_label = ucwords(str_replace('_', ' ', $stat['document_type']));
                                echo "<div class='list-group-item d-flex justify-content-between align-items-center'>";
                                echo "<span>{$type_label}</span>";
                                echo "<span class='badge bg-primary rounded-pill'>{$stat['count']}</span>";
                                echo "</div>";
                            }
                            echo "</div>";
                        } else {
                            echo "<p class='text-center'>No documents uploaded yet</p>";
                        }
                        
                        // Get total storage used
                        if ($role == 'hr_admin') {
                            $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM documents");
                            $stmt->execute();
                        } else {
                            $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM documents WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                        }
                        $total_size = $stmt->fetchColumn();
                        
                        if ($total_size) {
                            $size_mb = round($total_size / 1024 / 1024, 2);
                            echo "<div class='mt-4'>";
                            echo "<h5>Storage Used</h5>";
                            echo "<p class='lead text-center'>{$size_mb} MB</p>";
                            echo "</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<p class='text-danger'>Error retrieving statistics</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Document List -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-file-alt me-1"></i>
                    My Documents
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                            <p class="lead">No documents uploaded yet</p>
                            <p>Upload your first document using the form on the left</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <?php if ($role == 'hr_admin'): ?>
                                            <th>Employee</th>
                                        <?php endif; ?>
                                        <th>Document Name</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <?php if ($role == 'hr_admin'): ?>
                                                <td><?php echo htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '') . ' (' . ($doc['employee_id'] ?? '') . ')'); ?></td>
                                            <?php endif; ?>
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
                                                <?php if (!empty($doc['description'])): ?>
                                                    <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($doc['description']); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                            <td><?php echo round($doc['file_size'] / 1024, 2); ?> KB</td>
                                            <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                            <td>
                                                <?php if ($doc['leave_application_id']): ?>
                                                    <span class="badge bg-info">Attached to Leave #<?php echo $doc['leave_application_id']; ?></span>
                                                    <?php if ($doc['leave_status']): ?>
                                                        <span class="badge bg-<?php echo $doc['leave_status'] == 'approved' ? 'success' : ($doc['leave_status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucfirst($doc['leave_status']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Attached</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?download=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php 
                                                // Show delete button if:
                                                // 1. User is the document owner and leave is pending or not attached
                                                // 2. User is HR admin
                                                $can_delete = ($doc['user_id'] == $user_id && (!$doc['leave_application_id'] || $doc['leave_status'] == 'pending')) || $role == 'hr_admin';
                                                if ($can_delete):
                                                ?>
                                                    <a href="?delete=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this document?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Document pagination" class="mt-4">
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
    </div>
</div>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php include '../includes/footer.php'; ?>