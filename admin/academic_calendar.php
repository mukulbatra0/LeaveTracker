<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// Only admin or hr_admin can access this page
if ($role != 'admin' && $role != 'hr_admin') {
    $_SESSION['alert']      = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = 'danger';
    header('Location: ../index.php');
    exit;
}

// PDF file path
$pdf_file_path = __DIR__ . '/../uploads/academic_calendar.pdf';
$pdf_exists    = file_exists($pdf_file_path);

// ── Handle PDF upload ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pdf'])) {
    if (isset($_FILES['calendar_pdf']) && $_FILES['calendar_pdf']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['calendar_pdf'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 10 * 1024 * 1024; // 10 MB

        if ($file_ext !== 'pdf') {
            $_SESSION['alert']      = 'Only PDF files are allowed.';
            $_SESSION['alert_type'] = 'danger';
        } elseif ($file['size'] > $max_size) {
            $_SESSION['alert']      = 'File size must not exceed 10 MB.';
            $_SESSION['alert_type'] = 'danger';
        } else {
            if (move_uploaded_file($file['tmp_name'], $pdf_file_path)) {
                // Audit log
                $action     = 'Uploaded academic calendar PDF: ' . $file['name'];
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
                $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);

                $_SESSION['alert']      = 'Academic Calendar PDF uploaded successfully.';
                $_SESSION['alert_type'] = 'success';
            } else {
                $_SESSION['alert']      = 'Failed to upload PDF. Check server permissions.';
                $_SESSION['alert_type'] = 'danger';
            }
        }
    } else {
        $_SESSION['alert']      = 'Please select a PDF file to upload.';
        $_SESSION['alert_type'] = 'danger';
    }
    header('Location: academic_calendar.php');
    exit;
}

// ── Handle PDF delete ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pdf'])) {
    if ($pdf_exists) {
        unlink($pdf_file_path);

        $action     = 'Deleted academic calendar PDF';
        $audit_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, created_at) VALUES (:uid, :action, NOW())");
        $audit_stmt->execute([':uid' => $user_id, ':action' => $action]);

        $_SESSION['alert']      = 'Academic Calendar PDF deleted successfully.';
        $_SESSION['alert_type'] = 'success';
    }
    header('Location: academic_calendar.php');
    exit;
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Academic Calendar</h1>
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

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-file-pdf me-1"></i>
                Academic Calendar PDF
            </div>
            <div class="d-flex gap-2">
                <?php if ($pdf_exists): ?>
                    <a href="../uploads/academic_calendar.pdf" target="_blank" class="btn btn-danger btn-sm">
                        <i class="fas fa-file-pdf me-1"></i> View PDF
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pdfUploadModal">
                    <i class="fas fa-upload me-1"></i> <?php echo $pdf_exists ? 'Replace PDF' : 'Upload PDF'; ?>
                </button>
                <?php if ($pdf_exists): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete the Academic Calendar PDF?');">
                        <input type="hidden" name="delete_pdf" value="1">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash me-1"></i> Delete PDF
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body text-center py-5">
            <?php if ($pdf_exists): ?>
                <i class="fas fa-file-pdf fa-5x text-danger mb-4"></i>
                <h4 class="mb-2">Academic Calendar PDF is uploaded</h4>
                <p class="text-muted mb-4">
                    Last modified: <?php echo date('d M Y, h:i A', filemtime($pdf_file_path)); ?><br>
                    Size: <?php echo round(filesize($pdf_file_path) / 1024, 1); ?> KB
                </p>
                <a href="../uploads/academic_calendar.pdf" target="_blank" class="btn btn-danger btn-lg">
                    <i class="fas fa-external-link-alt me-2"></i> Open Academic Calendar PDF
                </a>
            <?php else: ?>
                <i class="fas fa-file-upload fa-5x text-muted mb-4"></i>
                <h4 class="mb-2 text-muted">No PDF Uploaded Yet</h4>
                <p class="text-muted mb-4">Upload the Academic Calendar PDF so that all users can view it.</p>
                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#pdfUploadModal">
                    <i class="fas fa-upload me-2"></i> Upload PDF Now
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PDF Upload Modal -->
<div class="modal fade" id="pdfUploadModal" tabindex="-1" aria-labelledby="pdfUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfUploadModalLabel">
                    <i class="fas fa-file-pdf me-2 text-danger"></i>
                    <?php echo $pdf_exists ? 'Replace Academic Calendar PDF' : 'Upload Academic Calendar PDF'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="upload_pdf" value="1">
                <div class="modal-body">
                    <?php if ($pdf_exists): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            A PDF is already uploaded. Uploading a new file will replace it.
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="calendar_pdf" class="form-label">Select PDF File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="calendar_pdf" name="calendar_pdf" accept=".pdf" required>
                        <div class="form-text">Only PDF files are allowed. Maximum size: 10 MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>
                        <?php echo $pdf_exists ? 'Replace PDF' : 'Upload PDF'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
