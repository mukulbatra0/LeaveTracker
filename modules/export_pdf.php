<?php
// Start output buffering to prevent any accidental output
ob_start();

// Set error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

// Set secure cookie settings before starting session
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');

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

// Check if user has permission to access reports
$allowed_roles = ['admin', 'department_head', 'dean', 'principal', 'hr_admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

// Get report parameters from POST
$report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'leave_utilization';
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
$department_filter = isset($_POST['department_id']) ? $_POST['department_id'] : 'all';
$leave_type_filter = isset($_POST['leave_type_id']) ? $_POST['leave_type_id'] : 'all';
$status_filter = isset($_POST['status']) ? $_POST['status'] : 'all';

// Require the TCPDF library (you need to install it via composer)
// composer require tecnickcom/tcpdf
try {
    require '../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    die('Error: TCPDF library not found. Please run: composer require tecnickcom/tcpdf');
}

// Create a new PDF document
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        // Logo
        $image_file = '../assets/img/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Set font
        $this->SetFont('helvetica', 'B', 16);
        
        // Title
        $title = 'Employee Leave Management System';
        $this->Cell(0, 15, $title, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Line
        $this->Line(10, 25, $this->getPageWidth() - 10, 25);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        // Date
        $this->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

try {
    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Set the report title
$title = '';
switch ($report_type) {
    case 'leave_utilization':
        $title = 'Leave Utilization Report';
        break;
    case 'department_summary':
        $title = 'Department Summary Report';
        break;
    case 'monthly_trends':
        $title = 'Monthly Trends Report';
        break;
    case 'leave_balance':
        $title = 'Leave Balance Report';
        break;
}

$pdf->SetTitle($title);
$pdf->SetSubject('ELMS Report');
$pdf->SetKeywords('ELMS, Report, Leave, Management');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// CSRF token validation removed for compatibility

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 14);

// Report title
$pdf->Cell(0, 10, $title, 0, 1, 'C');

// Report period
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'C');

// Add some space
$pdf->Ln(5);

// Build query based on filters and report type
if ($report_type == 'leave_utilization') {
    // Leave Utilization Report
    $sql = "SELECT la.id, la.start_date, la.end_date, la.days, la.status, la.reason,
                  lt.name as leave_type, lt.color as leave_type_color,
                  u.first_name, u.last_name, u.employee_id, u.email,
                  d.name as department_name
           FROM leave_applications la
           JOIN leave_types lt ON la.leave_type_id = lt.id
           JOIN users u ON la.user_id = u.id
           JOIN departments d ON u.department_id = d.id
           WHERE la.start_date >= :start_date AND la.end_date <= :end_date";
    
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
    
    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }
    
    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND la.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }
    
    // Add status filter if specified
    if ($status_filter != 'all') {
        $sql .= " AND la.status = :status";
        $params[':status'] = $status_filter;
    }
    
    $sql .= " ORDER BY la.start_date DESC";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create the table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(50, 7, 'Employee', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Department', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Leave Type', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Start Date', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'End Date', 1, 0, 'C', 1);
    $pdf->Cell(15, 7, 'Days', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Status', 1, 1, 'C', 1);
    
    // Add data rows
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    foreach ($report_data as $row) {
        $pdf->Cell(50, 6, $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['employee_id'] . ')', 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, $row['department_name'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $row['leave_type'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, date('d M Y', strtotime($row['start_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, date('d M Y', strtotime($row['end_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, $row['days'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, ucfirst($row['status']), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
} elseif ($report_type == 'department_summary') {
    // Department Summary Report
    $sql = "SELECT d.name as department_name, 
                  COUNT(DISTINCT u.id) as total_staff,
                  COUNT(DISTINCT CASE WHEN la.status = 'approved' AND la.start_date <= CURDATE() AND la.end_date >= CURDATE() THEN la.user_id END) as staff_on_leave,
                  COUNT(la.id) as total_applications,
                  SUM(CASE WHEN la.status = 'approved' THEN la.days ELSE 0 END) as approved_days,
                  SUM(CASE WHEN la.status = 'rejected' THEN la.days ELSE 0 END) as rejected_days,
                  SUM(CASE WHEN la.status = 'pending' THEN la.days ELSE 0 END) as pending_days
           FROM departments d
           LEFT JOIN users u ON d.id = u.department_id
           LEFT JOIN leave_applications la ON u.id = la.user_id AND la.start_date >= :start_date AND la.end_date <= :end_date
           WHERE u.role != 'hr_admin'";
    
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
    
    // Department head can only see their department
    if ($role == 'department_head') {
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql .= " AND d.id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    } elseif ($department_filter != 'all') {
        $sql .= " AND d.id = :department_id";
        $params[':department_id'] = $department_filter;
    }
    
    $sql .= " GROUP BY d.id ORDER BY d.name ASC";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create the table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(40, 7, 'Department', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Total Staff', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Staff on Leave', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Applications', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Approved Days', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Rejected Days', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Pending Days', 1, 1, 'C', 1);
    
    // Add data rows
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    foreach ($report_data as $row) {
        $pdf->Cell(40, 6, $row['department_name'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $row['total_staff'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['staff_on_leave'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['total_applications'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['approved_days'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['rejected_days'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['pending_days'], 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
} elseif ($report_type == 'monthly_trends') {
    // Monthly Trends Report
    $sql = "SELECT 
              DATE_FORMAT(la.start_date, '%Y-%m') as month,
              COUNT(la.id) as total_applications,
              SUM(CASE WHEN la.status = 'approved' THEN la.days ELSE 0 END) as approved_days,
              SUM(CASE WHEN la.status = 'rejected' THEN la.days ELSE 0 END) as rejected_days,
              SUM(CASE WHEN la.status = 'pending' THEN la.days ELSE 0 END) as pending_days,
              COUNT(DISTINCT la.user_id) as unique_applicants
           FROM leave_applications la
           JOIN users u ON la.user_id = u.id
           WHERE la.start_date >= :start_date AND la.end_date <= :end_date";
    
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
    
    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }
    
    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND la.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }
    
    $sql .= " GROUP BY DATE_FORMAT(la.start_date, '%Y-%m') ORDER BY month ASC";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create the table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(30, 7, 'Month', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Applications', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Unique Applicants', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Approved Days', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Rejected Days', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Pending Days', 1, 1, 'C', 1);
    
    // Add data rows
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    foreach ($report_data as $row) {
        $pdf->Cell(30, 6, date('F Y', strtotime($row['month'] . '-01')), 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, $row['total_applications'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $row['unique_applicants'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $row['approved_days'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $row['rejected_days'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $row['pending_days'], 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
} elseif ($report_type == 'leave_balance') {
    // Leave Balance Report
    $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_id, u.email,
                  d.name as department_name,
                  lt.name as leave_type,
                  lb.total_days as allocated_days,
                  lb.used_days,
                  (lb.total_days - lb.used_days) as remaining_days
           FROM users u
           JOIN departments d ON u.department_id = d.id
           JOIN leave_balances lb ON u.id = lb.user_id
           JOIN leave_types lt ON lb.leave_type_id = lt.id
           WHERE u.role != 'hr_admin'";
    
    $params = [];
    
    // Add department filter if specified
    if ($department_filter != 'all') {
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $department_filter;
    } else if ($role == 'department_head') {
        // Department heads can only see their department
        $user_dept_sql = "SELECT department_id FROM users WHERE id = :user_id";
        $user_dept_stmt = $conn->prepare($user_dept_sql);
        $user_dept_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_dept_stmt->execute();
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql .= " AND u.department_id = :department_id";
        $params[':department_id'] = $user_dept['department_id'];
    }
    
    // Add leave type filter if specified
    if ($leave_type_filter != 'all') {
        $sql .= " AND lb.leave_type_id = :leave_type_id";
        $params[':leave_type_id'] = $leave_type_filter;
    }
    
    $sql .= " ORDER BY d.name, u.last_name, u.first_name, lt.name";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create the table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(50, 7, 'Employee', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Department', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Leave Type', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Allocated', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Used', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Remaining', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Usage %', 1, 1, 'C', 1);
    
    // Add data rows
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    foreach ($report_data as $row) {
        $usage_percent = ($row['allocated_days'] > 0) ? 
            round(($row['used_days'] / $row['allocated_days']) * 100, 1) : 0;
            
        $pdf->Cell(50, 6, $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['employee_id'] . ')', 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, $row['department_name'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $row['leave_type'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $row['allocated_days'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, $row['used_days'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, $row['remaining_days'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, $usage_percent . '%', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
}

// Add summary information
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'Summary Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

// Add summary based on report type
if ($report_type == 'leave_utilization') {
    $total_applications = count($report_data);
    $total_days = array_sum(array_column($report_data, 'days'));
    $status_counts = array_count_values(array_column($report_data, 'status'));
    
    $pdf->Cell(0, 6, 'Total Applications: ' . $total_applications, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Days: ' . $total_days, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Status Breakdown:', 0, 1, 'L');
    
    foreach ($status_counts as $status => $count) {
        $pdf->Cell(30, 6, '', 0, 0, 'L');
        $pdf->Cell(0, 6, ucfirst($status) . ': ' . $count, 0, 1, 'L');
    }
    
} elseif ($report_type == 'department_summary') {
    $total_departments = count($report_data);
    $total_staff = array_sum(array_column($report_data, 'total_staff'));
    $total_staff_on_leave = array_sum(array_column($report_data, 'staff_on_leave'));
    $total_applications = array_sum(array_column($report_data, 'total_applications'));
    $total_approved_days = array_sum(array_column($report_data, 'approved_days'));
    
    $pdf->Cell(0, 6, 'Total Departments: ' . $total_departments, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Staff: ' . $total_staff, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Staff Currently on Leave: ' . $total_staff_on_leave, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Applications: ' . $total_applications, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Approved Days: ' . $total_approved_days, 0, 1, 'L');
    
} elseif ($report_type == 'monthly_trends') {
    $total_months = count($report_data);
    $total_applications = array_sum(array_column($report_data, 'total_applications'));
    $total_approved_days = array_sum(array_column($report_data, 'approved_days'));
    $total_rejected_days = array_sum(array_column($report_data, 'rejected_days'));
    $total_pending_days = array_sum(array_column($report_data, 'pending_days'));
    
    $pdf->Cell(0, 6, 'Period Covered: ' . $total_months . ' months', 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Applications: ' . $total_applications, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Approved Days: ' . $total_approved_days, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Rejected Days: ' . $total_rejected_days, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Pending Days: ' . $total_pending_days, 0, 1, 'L');
    
} elseif ($report_type == 'leave_balance') {
    $total_employees = count(array_unique(array_column($report_data, 'id')));
    $total_allocated = array_sum(array_column($report_data, 'allocated_days'));
    $total_used = array_sum(array_column($report_data, 'used_days'));
    $total_remaining = array_sum(array_column($report_data, 'remaining_days'));
    $usage_percent = ($total_allocated > 0) ? round(($total_used / $total_allocated) * 100, 1) : 0;
    
    $pdf->Cell(0, 6, 'Total Employees: ' . $total_employees, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Allocated Days: ' . $total_allocated, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Used Days: ' . $total_used, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Remaining Days: ' . $total_remaining, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Overall Usage: ' . $usage_percent . '%', 0, 1, 'L');
}

// Add report filters
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'Report Filters', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

// Date range
$pdf->Cell(0, 6, 'Date Range: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'L');

// Department filter
if ($department_filter != 'all') {
    $dept_sql = "SELECT name FROM departments WHERE id = :id";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bindParam(':id', $department_filter, PDO::PARAM_INT);
    $dept_stmt->execute();
    $dept_name = $dept_stmt->fetchColumn();
    $pdf->Cell(0, 6, 'Department: ' . $dept_name, 0, 1, 'L');
} else {
    $pdf->Cell(0, 6, 'Department: All Departments', 0, 1, 'L');
}

// Leave type filter
if ($leave_type_filter != 'all') {
    $leave_type_sql = "SELECT name FROM leave_types WHERE id = :id";
    $leave_type_stmt = $conn->prepare($leave_type_sql);
    $leave_type_stmt->bindParam(':id', $leave_type_filter, PDO::PARAM_INT);
    $leave_type_stmt->execute();
    $leave_type_name = $leave_type_stmt->fetchColumn();
    $pdf->Cell(0, 6, 'Leave Type: ' . $leave_type_name, 0, 1, 'L');
} else {
    $pdf->Cell(0, 6, 'Leave Type: All Leave Types', 0, 1, 'L');
}

// Status filter
if ($status_filter != 'all') {
    $pdf->Cell(0, 6, 'Status: ' . ucfirst($status_filter), 0, 1, 'L');
} else {
    $pdf->Cell(0, 6, 'Status: All Statuses', 0, 1, 'L');
}

// Clean any output buffer before sending PDF
if (ob_get_length()) {
    ob_end_clean();
}

// Set headers for PDF output
header('Content-Type: application/pdf');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

    // Close and output PDF document
    $pdf->Output('ELMS_' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.pdf', 'I');

} catch (Exception $e) {
    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Log the error
    error_log("PDF Generation Error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: text/html');
    echo '<script>alert("Error generating PDF: ' . addslashes($e->getMessage()) . '"); window.close();</script>';
}

// Ensure no further output
exit;
?>