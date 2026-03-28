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
                error_log("LeaveApplicationPDF: generatePDFString returned false for leave ID: $leave_id");
                return false;
            }
            
            $temp_file = tempnam(sys_get_temp_dir(), 'leave_pdf_');
            if ($temp_file === false) {
                error_log("LeaveApplicationPDF: Failed to create temp file");
                return false;
            }
            
            $temp_file .= '.pdf';
            $result = file_put_contents($temp_file, $pdf_string);
            
            if ($result === false) {
                error_log("LeaveApplicationPDF: Failed to write PDF to temp file: $temp_file");
                return false;
            }
            
            error_log("LeaveApplicationPDF: Successfully generated PDF to file: $temp_file (size: " . strlen($pdf_string) . " bytes)");
            return $temp_file;
        } catch (\Exception $e) {
            error_log("LeaveApplicationPDF temp file Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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
        try {
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
                // Try multiple possible paths for the attachment
                $possible_paths = [
                    __DIR__ . '/../uploads/' . $leave['attachment'],
                    __DIR__ . '/../uploads/documents/' . $leave['attachment'],
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $leave['attachment'],
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/documents/' . $leave['attachment']
                ];
                
                $attachment_path = null;
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $attachment_path = $path;
                        break;
                    }
                }
                
                if ($attachment_path && file_exists($attachment_path)) {
                    $file_extension = strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION));
                    
                    // Handle image attachments
                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        try {
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
                        } catch (\Exception $img_e) {
                            error_log("Failed to add image to PDF: " . $img_e->getMessage());
                            // Continue without the image rather than failing the whole PDF
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
                } else {
                    // Log that attachment file was not found
                    error_log("Attachment file not found for leave application ID {$leave_id}: " . $leave['attachment']);
                }
            }
            
            return $pdf;
            
        } catch (\Exception $e) {
            error_log("LeaveApplicationPDF createPDF Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Build HTML content for the leave application form
     */
    private function buildHTML($leave, $approvals) {
        // Prepare data
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
            .field-row {
                width: 100%;
                margin-bottom: 0px;
            }
            .field-label {
                font-weight: bold;
                font-size: 11px;
            }
            .field-value {
                font-size: 11px;
                border-bottom: 1px solid #000;
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
            .info-line {
                font-size: 11px;
                font-weight: bold;
                line-height: 2.2;
                border-bottom: 1px solid #000;
                margin-bottom: 0px;
                padding-bottom: 0px;
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

        return $html;
    }
}
?>
