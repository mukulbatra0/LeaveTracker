<?php
session_start();

// Check if user is logged in (any role can view)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if the PDF exists
$pdf_path = __DIR__ . '/../uploads/academic_calendar.pdf';
$pdf_exists = file_exists($pdf_path);

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Academic Calendar</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Academic Calendar</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-calendar-alt me-1"></i>
                Academic Calendar PDF
            </div>
        </div>
        <div class="card-body text-center py-5">
            <?php if ($pdf_exists): ?>
                <i class="fas fa-file-pdf fa-5x text-danger mb-4"></i>
                <h4 class="mb-3">Academic Calendar is available</h4>
                <p class="text-muted mb-4">Click the button below to open the Academic Calendar PDF in a new tab.</p>
                <a href="../uploads/academic_calendar.pdf" target="_blank" class="btn btn-danger btn-lg">
                    <i class="fas fa-file-pdf me-2"></i> View Academic Calendar PDF
                </a>
            <?php else: ?>
                <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                <h4 class="mb-3 text-muted">No PDF Available</h4>
                <p class="text-muted">The Academic Calendar PDF has not been uploaded yet. Please contact the administrator.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
