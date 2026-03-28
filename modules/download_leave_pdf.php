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

    // Prepare data for the form
    $applicant_name = htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']);
    $designation = htmlspecialchars(ucwords(str_replace('_', ' ', $leave['role'])));
    $department = htmlspecialchars($leave['department_name']);
    $leave_type = htmlspecialchars($leave['leave_type_name']);
    $from_date = date('d/m/Y', strtotime($leave['start_date']));
    $to_date = date('d/m/Y', strtotime($leave['end_date']));
    $application_date = date('d/m/Y', strtotime($leave['created_at']));
    $num_days = $leave['days'];
    $half_day_text = '';
    if ($leave['is_half_day']) {
        $half_day_text = ' (' . ($leave['half_day_period'] == 'first_half' ? 'First Half' : 'Second Half') . ')';
    }
    $reason = htmlspecialchars($leave['reason']);
    $transport = !empty($leave['mode_of_transport']) ? htmlspecialchars($leave['mode_of_transport']) : '';
    $work_adj = !empty($leave['work_adjustment']) ? htmlspecialchars($leave['work_adjustment']) : '';
    $visit_address = !empty($leave['visit_address']) ? htmlspecialchars($leave['visit_address']) : '';
    $contact_number = !empty($leave['contact_number']) ? htmlspecialchars($leave['contact_number']) : '';
    $place_mobile = '';
    if ($visit_address && $contact_number) {
        $place_mobile = $visit_address . ', Mobile: ' . $contact_number;
    } elseif ($visit_address) {
        $place_mobile = $visit_address;
    } elseif ($contact_number) {
        $place_mobile = 'Mobile: ' . $contact_number;
    }

    // Build the HTML content matching the official leave application form
    $html = '
    <style>
        body {
            font-family: helvetica, sans-serif;
            font-size: 11px;
            color: #000;
        }
        .header-title {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            line-height: 1.5;
        }
        .header-subtitle {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin-top: 2px;
            line-height: 1.4;
        }
        .field-label {
            font-weight: bold;
            font-size: 11px;
        }
        table.leave-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.leave-table th, table.leave-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            font-size: 11px;
            vertical-align: middle;
        }
        table.leave-table th {
            font-weight: bold;
            text-align: center;
            background-color: #f0f0f0;
        }
        table.leave-table td {
            text-align: center;
            height: 35px;
        }
        .info-value {
            font-weight: normal;
        }
        table.approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.approval-table th {
            background-color: #e0e0e0;
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
        }
        table.approval-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 9px;
            text-align: center;
        }
        .badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: black; }
    </style>

    <!-- ===== HEADER ===== -->
    <div class="header-title">The Technological Institute of Textile &amp; Sciences, Bhiwani-127021</div>
    <div class="header-subtitle">Application form for Comm. Leave / CL / EL / On Duty* / Duty Leave*</div>

    <br/><br/>

    <!-- ===== NAME & DATE ROW ===== -->
    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="width:65%; border:none; padding:4px 0;">
                <span class="field-label">NAME</span>
                <span style="border-bottom:1px solid #000; display:inline;">&nbsp;&nbsp;' . $applicant_name . str_repeat('&nbsp;', 30) . '</span>
            </td>
            <td style="width:35%; border:none; padding:4px 0; text-align:right;">
                <span class="field-label">DATE</span>
                <span style="border-bottom:1px solid #000; display:inline;">&nbsp;&nbsp;' . $application_date . str_repeat('&nbsp;', 10) . '</span>
            </td>
        </tr>
    </table>

    <!-- ===== DESIGNATION & DEPTT ROW ===== -->
    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="width:65%; border:none; padding:4px 0;">
                <span class="field-label">DESIGNATION</span>
                <span style="border-bottom:1px solid #000; display:inline;">&nbsp;&nbsp;' . $designation . str_repeat('&nbsp;', 20) . '</span>
            </td>
            <td style="width:35%; border:none; padding:4px 0; text-align:right;">
                <span class="field-label">DEPTT</span>
                <span style="border-bottom:1px solid #000; display:inline;">&nbsp;&nbsp;' . $department . str_repeat('&nbsp;', 10) . '</span>
            </td>
        </tr>
    </table>

    <br/><br/>

    <!-- ===== LEAVE DETAILS TABLE ===== -->
    <table class="leave-table">
        <tr>
            <th style="width:28%;">TYPE OF LEAVE<br/>REQUIRED</th>
            <th style="width:27%;">FROM</th>
            <th style="width:22%;">TO</th>
            <th style="width:23%;">NUMBER OF DAYS</th>
        </tr>
        <tr>
            <td style="font-weight:bold;">' . $leave_type . '</td>
            <td>' . $from_date . '</td>
            <td>' . $to_date . '</td>
            <td>' . $num_days . ' day(s)' . $half_day_text . '</td>
        </tr>
    </table>

    <br/><br/>

    <!-- ===== ADDITIONAL FIELDS ===== -->
    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="border:none; padding:5px 0; border-bottom:1px solid #000;">
                <span class="field-label">* MODE OF TRANSPORT FOR OFFICIAL WORK, IF ANY :-</span>
                <span class="info-value">&nbsp;&nbsp;' . $transport . '</span>
            </td>
        </tr>
    </table>

    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="border:none; padding:5px 0; border-bottom:1px solid #000;">
                <span class="field-label">CLASS / WORK ADJUSTMENT DURING LEAVE PERIOD, IF ANY :-</span>
                <span class="info-value">&nbsp;&nbsp;' . $work_adj . '</span>
            </td>
        </tr>
    </table>

    <br/><br/>

    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="border:none; padding:5px 0; border-bottom:1px solid #000;">
                <span class="field-label">PURPOSE/REASON FOR LEAVE :-</span>
                <span class="info-value">&nbsp;&nbsp;' . $reason . '</span>
            </td>
        </tr>
    </table>

    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="border:none; padding:5px 0; border-bottom:1px solid #000;">
                <span class="field-label">PLACE / ADDRESS OF VISIT &amp; MOBILE NO. DURING LEAVE PERIOD :-</span>
                <span class="info-value">&nbsp;&nbsp;' . $place_mobile . '</span>
            </td>
        </tr>
    </table>';

    // Attachment info if present
    if (!empty($leave['attachment'])) {
        $html .= '
        <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
            <tr>
                <td style="border:none; padding:5px 0; border-bottom:1px solid #000;">
                    <span class="field-label">ATTACHMENT :-</span>
                    <span class="info-value">&nbsp;&nbsp;' . htmlspecialchars($leave['attachment']) . '</span>
                </td>
            </tr>
        </table>';
    }

    $html .= '<br/><br/><br/>';

    // ===== SIGNATURE SECTION =====
    $html .= '
    <table cellpadding="0" cellspacing="0" style="width:100%; border:none;">
        <tr>
            <td style="width:33%; border:none; padding:5px 0; text-align:left; vertical-align:bottom;">
                <span class="field-label">APPLICANT\'S SIGNATURE</span><br/><br/>
                <span style="border-bottom:1px solid #000;">' . $applicant_name . str_repeat('&nbsp;', 10) . '</span>
            </td>
            <td style="width:34%; border:none; padding:5px 0; text-align:center; vertical-align:bottom;">
                <span class="field-label">HEAD OF DEPTT.</span><br/><br/>
                <span style="border-bottom:1px solid #000;">' . str_repeat('&nbsp;', 30) . '</span>
            </td>
            <td style="width:33%; border:none; padding:5px 0; text-align:right; vertical-align:bottom;">
                <span class="field-label">DIRECTOR</span><br/><br/>
                <span style="border-bottom:1px solid #000;">' . str_repeat('&nbsp;', 30) . '</span>
            </td>
        </tr>
    </table>';

    // ===== APPROVAL HISTORY =====
    if (count($approvals) > 0) {
        $html .= '
        <br/><br/>
        <div style="border-top:2px solid #000; padding-top:8px; margin-top:10px;">
            <div style="font-size:12px; font-weight:bold; margin-bottom:8px; text-align:center;">Approval Status</div>
            <table class="approval-table">
                <tr>
                    <th style="width:22%;">Approver Level</th>
                    <th style="width:25%;">Approver Name</th>
                    <th style="width:13%;">Status</th>
                    <th style="width:25%;">Comments</th>
                    <th style="width:15%;">Date</th>
                </tr>';

        foreach ($approvals as $approval) {
            $status_class = $approval['status'] == 'approved' ? 'badge-success' :
                           ($approval['status'] == 'rejected' ? 'badge-danger' : 'badge-warning');

            $html .= '
                <tr>
                    <td style="text-align:left;">' . ucwords(str_replace('_', ' ', $approval['approver_level'])) . '</td>
                    <td style="text-align:left;">' . htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']) . '</td>
                    <td><span class="badge ' . $status_class . '">' . ucfirst($approval['status']) . '</span></td>
                    <td style="text-align:left;">' . htmlspecialchars($approval['comments'] ?? '-') . '</td>
                    <td>' . ($approval['status'] != 'pending' ? date('d/m/Y H:i', strtotime($approval['updated_at'])) : '-') . '</td>
                </tr>';
        }

        $html .= '
            </table>
        </div>';
    }

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
