<?php
/**
 * LeaveApplicationPDF - Generates PDF for a leave application
 * Reusable class that can generate PDF as string (for email attachment) or output to browser
 */

// Load Composer autoloader
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

class LeaveApplicationPDF {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Generate PDF for a leave application and return as string
     * @param int $leave_id The leave application ID
     * @return string|false PDF content as string, or false on failure
     */
    public function generatePDFString($leave_id) {
        try {
            $pdf = $this->createPDF($leave_id);
            if ($pdf === false) {
                return false;
            }
            return $pdf->Output('', 'S'); // 'S' returns PDF as string
        } catch (\Exception $e) {
            error_log("LeaveApplicationPDF Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate PDF and save to a temporary file
     * @param int $leave_id The leave application ID
     * @return string|false Path to temp file, or false on failure
     */
    public function generatePDFToFile($leave_id) {
        try {
            $pdf_string = $this->generatePDFString($leave_id);
            if ($pdf_string === false) {
                return false;
            }
            
            $temp_file = tempnam(sys_get_temp_dir(), 'leave_pdf_');
            $temp_file .= '.pdf';
            file_put_contents($temp_file, $pdf_string);
            return $temp_file;
        } catch (\Exception $e) {
            error_log("LeaveApplicationPDF temp file Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the filename for the PDF
     * @param int $leave_id The leave application ID
     * @return string The filename
     */
    public function getFilename($leave_id) {
        try {
            $sql = "SELECT u.employee_id, u.first_name, u.last_name 
                    FROM leave_applications la 
                    JOIN users u ON la.user_id = u.id 
                    WHERE la.id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user) {
                return 'Leave_Application_' . ($user['employee_id'] ?? $leave_id) . '_' . date('Ymd') . '.pdf';
            }
        } catch (\Exception $e) {
            error_log("LeaveApplicationPDF getFilename Error: " . $e->getMessage());
        }
        
        return 'Leave_Application_' . $leave_id . '_' . date('Ymd') . '.pdf';
    }
    
    /**
     * Create the PDF object with all content
     * @param int $leave_id The leave application ID
     * @return object|false TCPDF object or false on failure
     */
    private function createPDF($leave_id) {
        // Get leave application details
        $sql = "SELECT la.*, lt.name as leave_type_name, 
                u.first_name, u.last_name, u.email, u.employee_id, u.phone, u.role,
                d.name as department_name
                FROM leave_applications la 
                JOIN leave_types lt ON la.leave_type_id = lt.id 
                JOIN users u ON la.user_id = u.id 
                JOIN departments d ON u.department_id = d.id
                WHERE la.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
        $stmt->execute();
        $leave = $stmt->fetch();
        
        if (!$leave) {
            error_log("LeaveApplicationPDF: Leave application not found for ID: $leave_id");
            return false;
        }
        
        // Get approval history
        $approval_sql = "SELECT lap.*, u.first_name, u.last_name, lap.approver_level
                        FROM leave_approvals lap
                        JOIN users u ON lap.approver_id = u.id
                        WHERE lap.leave_application_id = :application_id
                        ORDER BY lap.created_at ASC";
        $approval_stmt = $this->conn->prepare($approval_sql);
        $approval_stmt->bindParam(':application_id', $leave_id, PDO::PARAM_INT);
        $approval_stmt->execute();
        $approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if required libraries exist
        if (!class_exists('TCPDF')) {
            error_log("LeaveApplicationPDF: TCPDF not found");
            return false;
        }
        
        // Create PDF using simple TCPDF (no FPDI needed for email attachments)
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        
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
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Build the HTML content
        $html = $this->buildHTML($leave, $approvals);
        
        // Write the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // If there's an image attachment, include it
        if (!empty($leave['attachment'])) {
            $attachment_path = __DIR__ . '/../uploads/' . $leave['attachment'];
            
            if (file_exists($attachment_path)) {
                $file_extension = strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION));
                
                // Handle image attachments
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                    $pdf->Ln(5);
                    
                    // Get image dimensions and fit to page
                    $img_size = @getimagesize($attachment_path);
                    if ($img_size) {
                        list($width, $height) = $img_size;
                        $max_width = 180;
                        $max_height = 250;
                        
                        $ratio = min($max_width / $width, $max_height / $height);
                        $new_width = $width * $ratio;
                        $new_height = $height * $ratio;
                        
                        $pdf->Image($attachment_path, 15, $pdf->GetY(), $new_width, $new_height);
                    }
                } else {
                    // For non-image files, add a note
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->Cell(0, 10, 'Attached Document', 0, 1, 'C');
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->Ln(5);
                    $pdf->Cell(0, 10, 'Filename: ' . $leave['attachment'], 0, 1, 'L');
                    $pdf->Cell(0, 10, 'File Type: ' . strtoupper($file_extension), 0, 1, 'L');
                    $pdf->Ln(5);
                    $pdf->MultiCell(0, 10, 'Note: This file is attached separately. Please download from the ELMS system.', 0, 'L');
                }
            }
        }
        
        return $pdf;
    }
    
    /**
     * Build HTML content for the leave application form
     */
    private function buildHTML($leave, $approvals) {
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
            <h3>The Technological Institute of Textile &amp; Sciences, Bhiwani-127021</h3>
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
        
        return $html;
    }
}
?>
