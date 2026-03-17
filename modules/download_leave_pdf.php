<?php
ob_start();

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';

$leave_id = $_GET['id'] ?? null;
if (!$leave_id) {
    die('Invalid leave application ID');
}

// Get leave application details
$sql = "SELECT la.*, lt.name as leave_type_name, 
        u.first_name, u.last_name, u.email, u.employee_id, u.phone, u.role,
        d.name as department_name
        FROM leave_applications la 
        JOIN leave_types lt ON la.leave_type_id = lt.id 
        JOIN users u ON la.user_id = u.id 
        JOIN departments d ON u.department_id = d.id
        WHERE la.id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
$stmt->execute();
$leave = $stmt->fetch();

if (!$leave) {
    die('Leave application not found');
}

// Check permissions
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Verify user has permission to download this application
$can_download = false;

// Staff can download their own applications
if ($leave['user_id'] == $user_id) {
    $can_download = true;
}

// Admin, Director can download all applications
if (in_array($role, ['admin', 'director'])) {
    $can_download = true;
}

// HOD can download applications from their department
if ($role == 'head_of_department') {
    $hod_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
    $hod_dept_stmt = $conn->prepare($hod_dept_sql);
    $hod_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $hod_dept_stmt->execute();
    $hod_dept = $hod_dept_stmt->fetchColumn();
    
    // Get applicant's department
    $app_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
    $app_dept_stmt = $conn->prepare($app_dept_sql);
    $app_dept_stmt->bindParam(':user_id', $leave['user_id'], PDO::PARAM_INT);
    $app_dept_stmt->execute();
    $app_dept = $app_dept_stmt->fetchColumn();
    
    if ($hod_dept == $app_dept) {
        $can_download = true;
    }
}

if (!$can_download) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    die('You don\'t have permission to download this leave application.');
}

// Get approval history
$approval_sql = "SELECT lap.*, u.first_name, u.last_name, lap.approver_level
                FROM leave_approvals lap
                JOIN users u ON lap.approver_id = u.id
                WHERE lap.leave_application_id = :application_id
                ORDER BY lap.created_at ASC";
$approval_stmt = $conn->prepare($approval_sql);
$approval_stmt->bindParam(':application_id', $leave_id, PDO::PARAM_INT);
$approval_stmt->execute();
$approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);

// Require TCPDF library
try {
    require '../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    die('Error: TCPDF library not found. Please run: composer require tecnickcom/tcpdf');
}

// Check if FPDI is available for PDF merging
$fpdi_available = class_exists('setasign\Fpdi\Tcpdf\Fpdi');

// Create custom PDF class
if ($fpdi_available) {
    class LeaveFormPDF extends \setasign\Fpdi\Tcpdf\Fpdi {
        public function Header() {
            // No header for leave form
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
} else {
    class LeaveFormPDF extends TCPDF {
        public function Header() {
            // No header for leave form
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
}

try {
    // Create new PDF document
    $pdf = new LeaveFormPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Leave Management System');
    $pdf->SetAuthor($leave['first_name'] . ' ' . $leave['last_name']);
    $pdf->SetTitle('Leave Application - ' . $leave['first_name'] . ' ' . $leave['last_name']);
    $pdf->SetSubject('Leave Application Form');

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Add a page for the leave form
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Build the HTML content for the leave form
    $html = '
    <style>
        .form-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-header h3 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            line-height: 1.4;
        }
        .form-header h4 {
            font-size: 13px;
            margin: 5px 0 0 0;
            font-weight: normal;
        }
        table.form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.form-table td, table.form-table th {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
            line-height: 1.5;
        }
        table.form-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: left;
            width: 40%;
            font-size: 11px;
        }
        table.form-table td {
            font-size: 11px;
            width: 60%;
        }
        .approval-section {
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .approval-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid #333;
        }
        table.approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.approval-table th {
            background-color: #e8e8e8;
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
        }
        table.approval-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 10px;
            text-align: center;
        }
        .signature-section {
            margin-top: 25px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-box {
            border: 1px solid #000;
            padding: 12px;
            vertical-align: top;
            width: 48%;
        }
        .signature-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 8px;
            text-decoration: underline;
        }
        .signature-line {
            margin: 8px 0;
            font-size: 10px;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: black; }
        .badge-info { background-color: #17a2b8; color: white; }
        .text-bold { font-weight: bold; }
    </style>
    
    <div class="form-header">
        <h3>The Technological Institute of Textile & Sciences, Bhiwani-127021</h3>
        <h4>Application form for Comm Leave /CL/ EL/ On Duty/ Duty Leave</h4>
    </div>
    
    <table class="form-table">
        <tr>
            <th>NAME</th>
            <td>' . htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) . '</td>
        </tr>
        <tr>
            <th>DESIGNATION</th>
            <td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $leave['role']))) . '</td>
        </tr>
        <tr>
            <th>DEPARTMENT</th>
            <td>' . htmlspecialchars($leave['department_name']) . '</td>
        </tr>
        <tr>
            <th>TYPE OF LEAVE REQUIRED</th>
            <td class="text-bold">' . htmlspecialchars($leave['leave_type_name']) . '</td>
        </tr>
        <tr>
            <th>FROM</th>
            <td>' . date('d/m/Y', strtotime($leave['start_date'])) . '</td>
        </tr>
        <tr>
            <th>TO</th>
            <td>' . date('d/m/Y', strtotime($leave['end_date'])) . '</td>
        </tr>
        <tr>
            <th>DATE OF APPLICATION</th>
            <td>' . date('d/m/Y', strtotime($leave['created_at'])) . '</td>
        </tr>
        <tr>
            <th>NUMBER OF DAYS</th>
            <td>' . $leave['days'] . ' day(s)';
    
    if ($leave['is_half_day']) {
        $html .= ' <span class="badge badge-info">' . 
                 ($leave['half_day_period'] == 'first_half' ? 'First Half (Morning)' : 'Second Half (Afternoon)') . 
                 '</span>';
    }
    
    $html .= '</td>
        </tr>
        <tr>
            <th>PURPOSE/REASON FOR LEAVE</th>
            <td>' . nl2br(htmlspecialchars($leave['reason'])) . '</td>
        </tr>';
    
    if (!empty($leave['mode_of_transport'])) {
        $html .= '<tr>
            <th>MODE OF TRANSPORT FOR OFFICIAL WORK, IF ANY</th>
            <td>' . nl2br(htmlspecialchars($leave['mode_of_transport'])) . '</td>
        </tr>';
    }
    
    if (!empty($leave['work_adjustment'])) {
        $html .= '<tr>
            <th>CLASS / WORK ADJUSTMENT DURING LEAVE PERIOD</th>
            <td>' . nl2br(htmlspecialchars($leave['work_adjustment'])) . '</td>
        </tr>';
    }
    
    if (!empty($leave['attachment'])) {
        $html .= '<tr>
            <th>ATTACHMENT</th>
            <td>' . htmlspecialchars($leave['attachment']) . '</td>
        </tr>';
    }
    
    $html .= '<tr>
            <th>APPLICATION SIGNATURE</th>
            <td style="padding: 15px 10px;">' . htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) . '</td>
        </tr>
    </table>';
    
    // Add approval history
    if (count($approvals) > 0) {
        $html .= '<div class="approval-section">
        <div class="approval-title">Approval Status</div>
        <table class="approval-table">
            <tr>
                <th style="width: 22%;">Approver Level</th>
                <th style="width: 25%;">Approver Name</th>
                <th style="width: 13%;">Status</th>
                <th style="width: 25%;">Comments</th>
                <th style="width: 15%;">Date</th>
            </tr>';
        
        foreach ($approvals as $approval) {
            $status_class = $approval['status'] == 'approved' ? 'badge-success' : 
                           ($approval['status'] == 'rejected' ? 'badge-danger' : 'badge-warning');
            
            $html .= '<tr>
                <td style="text-align: left;">' . ucwords(str_replace('_', ' ', $approval['approver_level'])) . '</td>
                <td style="text-align: left;">' . htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']) . '</td>
                <td><span class="badge ' . $status_class . '">' . ucfirst($approval['status']) . '</span></td>
                <td style="text-align: left;">' . htmlspecialchars($approval['comments'] ?? '-') . '</td>
                <td>' . ($approval['status'] != 'pending' ? date('d/m/Y H:i', strtotime($approval['updated_at'])) : '-') . '</td>
            </tr>';
        }
        
        $html .= '</table></div>';
    }
    
    // Add signature section
    $html .= '
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-title">HEAD OF DEPARTMENT</div>
                    <div class="signature-line">Signature: _______________________</div>
                    <div class="signature-line">Date: _______________________</div>
                </td>
                <td style="width: 4%; border: none;"></td>
                <td class="signature-box">
                    <div class="signature-title">DIRECTOR</div>
                    <div class="signature-line">Signature: _______________________</div>
                    <div class="signature-line">Date: _______________________</div>
                </td>
            </tr>
        </table>
    </div>';

    // Write the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');

    // If there's an attachment, add it to the PDF
    if (!empty($leave['attachment'])) {
        $attachment_path = '../uploads/' . $leave['attachment'];
        
        if (file_exists($attachment_path)) {
            $file_extension = strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION));
            
            // Handle image attachments
            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                $pdf->Ln(5);
                
                // Get image dimensions and fit to page
                list($width, $height) = getimagesize($attachment_path);
                $max_width = 180;
                $max_height = 250;
                
                $ratio = min($max_width / $width, $max_height / $height);
                $new_width = $width * $ratio;
                $new_height = $height * $ratio;
                
                $pdf->Image($attachment_path, 15, $pdf->GetY(), $new_width, $new_height);
            }
            // For PDF attachments, try to import pages if FPDI is available
            else if ($file_extension == 'pdf' && $fpdi_available) {
                try {
                    // Get the number of pages in the source PDF
                    $pageCount = $pdf->setSourceFile($attachment_path);
                    
                    // Import each page
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $pdf->AddPage();
                        
                        // Add header for first page of attachment
                        if ($pageNo == 1) {
                            $pdf->SetFont('helvetica', 'B', 12);
                            $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                            $pdf->Ln(2);
                        }
                        
                        // Import the page
                        $tplIdx = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($tplIdx);
                        
                        // Calculate scaling to fit page
                        $pageWidth = $pdf->getPageWidth() - 20; // 10mm margins on each side
                        $pageHeight = $pdf->getPageHeight() - 30; // margins
                        
                        $scale = min($pageWidth / $size['width'], $pageHeight / $size['height']);
                        
                        $x = ($pdf->getPageWidth() - ($size['width'] * $scale)) / 2;
                        $y = ($pageNo == 1) ? 30 : 15;
                        
                        $pdf->useTemplate($tplIdx, $x, $y, $size['width'] * $scale, $size['height'] * $scale);
                    }
                } catch (Exception $e) {
                    // If PDF import fails, add a reference page
                    error_log("PDF import error: " . $e->getMessage());
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->Ln(5);
                    $pdf->Cell(0, 10, 'Filename: ' . $leave['attachment'], 0, 1, 'L');
                    $pdf->Cell(0, 10, 'File Type: PDF', 0, 1, 'L');
                    $pdf->Ln(5);
                    $pdf->MultiCell(0, 10, 'Note: The attached PDF document could not be embedded automatically. Please download the attachment separately from the system to view the complete documentation.', 0, 'L');
                    $pdf->Ln(5);
                    $pdf->Cell(0, 10, 'Download from: View Application > Download Attachment', 0, 1, 'L');
                }
            }
            // For PDF without FPDI or other file types, add a reference page
            else {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'Filename: ' . $leave['attachment'], 0, 1, 'L');
                $pdf->Cell(0, 10, 'File Type: ' . strtoupper($file_extension), 0, 1, 'L');
                $pdf->Ln(5);
                
                if ($file_extension == 'pdf') {
                    $pdf->MultiCell(0, 10, 'Note: PDF merging library (FPDI) is not installed. To include PDF attachments in the generated PDF, please run: composer require setasign/fpdi', 0, 'L');
                    $pdf->Ln(5);
                    $pdf->MultiCell(0, 10, 'You can download the attachment separately from the View Application page.', 0, 'L');
                } else {
                    $pdf->MultiCell(0, 10, 'Note: This file type cannot be embedded in the PDF. Please download the attachment separately from the system.', 0, 'L');
                }
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'Download from: View Application > Download Attachment', 0, 1, 'L');
            }
        } else {
            // File not found
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Attachment Error', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'Filename: ' . $leave['attachment'], 0, 1, 'L');
            $pdf->Ln(5);
            $pdf->MultiCell(0, 10, 'Warning: The attachment file could not be found on the server. Please contact the system administrator.', 0, 'L');
        }
    }

    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Output PDF
    $filename = 'Leave_Application_' . $leave['employee_id'] . '_' . date('Ymd') . '.pdf';
    $pdf->Output($filename, 'I');

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    error_log("PDF Generation Error: " . $e->getMessage());
    die('Error generating PDF: ' . $e->getMessage());
}

exit;
?>
