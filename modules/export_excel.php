<?php
// Enable full error reporting for debugging purposes.
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// --- Prerequisite File Checks ---
// Ensure the database configuration and Composer autoloader files exist before including them.
if (!file_exists('../config/db.php') || !file_exists('../vendor/autoload.php')) {
    die("Error: A required configuration or library file is missing. Please check the file paths.");
}

// Include the database connection configuration and Composer autoloader.
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// --- Main Application Logic with Error Handling ---
try {
    // Check if the database connection variable is set after inclusion.
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new Exception("Database connection failed. Please check your configuration in 'config/db.php'.");
    }

    // Check if the user is logged in. If not, redirect to the login page.
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Get user information from the session.
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Define which roles are allowed to access this report generation page.
    $allowed_roles = ['department_head', 'dean', 'principal', 'hr_admin'];
    if (!in_array($role, $allowed_roles)) {
        // If the user's role is not allowed, set an alert and redirect them.
        $_SESSION['alert'] = "You don't have permission to access this page.";
        $_SESSION['alert_type'] = "danger";
        header('Location: index.php');
        exit;
    }

    // Ensure the script is accessed via a POST request. Redirect if not.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ./modules/reports.php');
        exit;
    }

    // --- Get Report Parameters from POST Request ---
    // Sanitize and retrieve report parameters, providing default values.
    $report_type = $_POST['report_type'] ?? 'leave_utilization';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $department_filter = $_POST['department_id'] ?? 'all';
    $leave_type_filter = $_POST['leave_type_id'] ?? 'all';
    $status_filter = $_POST['status'] ?? 'all';

    // --- Department Head Specific Logic ---
    // If the user is a department head, fetch their department ID once to filter reports.
    $user_department_id = null;
    if ($role == 'department_head') {
        $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_dept = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_dept) {
            $user_department_id = $user_dept['department_id'];
        }
    }

    // --- Spreadsheet Initialization ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties for the generated Excel file.
    $spreadsheet->getProperties()
        ->setCreator($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])
        ->setLastModifiedBy($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])
        ->setTitle('ELMS Report - ' . ucfirst(str_replace('_', ' ', $report_type)))
        ->setSubject('ELMS Report')
        ->setDescription('Generated from ELMS on ' . date('Y-m-d H:i:s'));

    // --- Report Title Setup ---
    $title = 'Unknown Report';
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

    // --- Spreadsheet Header Formatting ---
    $sheet->setCellValue('A1', $title);
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)));
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getFont()->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'Generated on: ' . date('d M Y H:i:s'));
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A3')->getFont()->setSize(10);
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- SQL Query Construction and Execution ---
    $sql = "";
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
    $headers = [];
    $currentRow = 5;

    if ($report_type == 'leave_utilization') {
        $headers = ['Employee', 'Employee ID', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason'];
        $sql = "SELECT la.id, la.start_date, la.end_date, la.days, la.status, la.reason,
                       lt.name as leave_type,
                       u.first_name, u.last_name, u.employee_id,
                       d.name as department_name
                FROM leave_applications la
                JOIN leave_types lt ON la.leave_type_id = lt.id
                JOIN users u ON la.user_id = u.id
                JOIN departments d ON u.department_id = d.id
                WHERE la.start_date >= :start_date AND la.end_date <= :end_date";

        if ($role == 'department_head' && $user_department_id) {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $user_department_id;
        } elseif ($department_filter != 'all') {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $department_filter;
        }

        if ($leave_type_filter != 'all') {
            $sql .= " AND la.leave_type_id = :leave_type_id";
            $params[':leave_type_id'] = $leave_type_filter;
        }

        if ($status_filter != 'all') {
            $sql .= " AND la.status = :status";
            $params[':status'] = $status_filter;
        }

        $sql .= " ORDER BY la.start_date DESC";

    } elseif ($report_type == 'department_summary') {
        $headers = ['Department', 'Total Staff', 'Staff on Leave', 'Total Applications', 'Approved Days', 'Rejected Days', 'Pending Days'];
        $sql = "SELECT d.name as department_name, 
                       COUNT(DISTINCT u.id) as total_staff,
                       COUNT(DISTINCT CASE WHEN la.status = 'approved' THEN la.user_id END) as staff_on_leave,
                       COUNT(la.id) as total_applications,
                       SUM(CASE WHEN la.status = 'approved' THEN la.days ELSE 0 END) as approved_days,
                       SUM(CASE WHEN la.status = 'rejected' THEN la.days ELSE 0 END) as rejected_days,
                       SUM(CASE WHEN la.status = 'pending' THEN la.days ELSE 0 END) as pending_days
                FROM departments d
                LEFT JOIN users u ON d.id = u.department_id
                LEFT JOIN leave_applications la ON u.id = la.user_id AND la.start_date <= :end_date AND la.end_date >= :start_date
                WHERE u.role != 'hr_admin'";

        if ($role == 'department_head' && $user_department_id) {
            $sql .= " AND d.id = :department_id";
            $params[':department_id'] = $user_department_id;
        } elseif ($department_filter != 'all') {
            $sql .= " AND d.id = :department_id";
            $params[':department_id'] = $department_filter;
        }

        $sql .= " GROUP BY d.id ORDER BY d.name ASC";

    } elseif ($report_type == 'monthly_trends') {
        $headers = ['Month', 'Total Applications', 'Unique Applicants', 'Approved Days', 'Rejected Days', 'Pending Days'];
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

        if ($role == 'department_head' && $user_department_id) {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $user_department_id;
        } elseif ($department_filter != 'all') {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $department_filter;
        }

        if ($leave_type_filter != 'all') {
            $sql .= " AND la.leave_type_id = :leave_type_id";
            $params[':leave_type_id'] = $leave_type_filter;
        }

        $sql .= " GROUP BY DATE_FORMAT(la.start_date, '%Y-%m') ORDER BY month ASC";

    } elseif ($report_type == 'leave_balance') {
        $headers = ['Employee', 'Employee ID', 'Department', 'Leave Type', 'Allocated Days', 'Used Days', 'Remaining Days', 'Usage %'];
        $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_id,
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
        
        unset($params[':start_date'], $params[':end_date']);

        if ($role == 'department_head' && $user_department_id) {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $user_department_id;
        } elseif ($department_filter != 'all') {
            $sql .= " AND u.department_id = :department_id";
            $params[':department_id'] = $department_filter;
        }

        if ($leave_type_filter != 'all') {
            $sql .= " AND lb.leave_type_id = :leave_type_id";
            $params[':leave_type_id'] = $leave_type_filter;
        }

        $sql .= " ORDER BY d.name, u.last_name, u.first_name, lt.name";
    }

    // Prepare and execute the final query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Populate Spreadsheet with Data ---

    // Set Headers
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $currentRow, $header);
        $col++;
    }

    // Style the header row
    $headerRange = 'A' . $currentRow . ':' . chr(ord($col) - 2) . $currentRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

    // Add data rows if data exists
    if (!empty($report_data)) {
        $currentRow++;
        foreach ($report_data as $data) {
            $col = 'A';
            if ($report_type == 'leave_utilization') {
                $sheet->setCellValue($col++, $data['first_name'] . ' ' . $data['last_name']);
                $sheet->setCellValue($col++, $data['employee_id']);
                $sheet->setCellValue($col++, $data['department_name']);
                $sheet->setCellValue($col++, $data['leave_type']);
                $sheet->setCellValue($col++, date('d M Y', strtotime($data['start_date'])));
                $sheet->setCellValue($col++, date('d M Y', strtotime($data['end_date'])));
                $sheet->setCellValue($col++, $data['days']);
                $sheet->setCellValue($col++, ucfirst($data['status']));
                $sheet->setCellValue($col++, $data['reason']);
            } elseif ($report_type == 'department_summary') {
                $sheet->setCellValue($col++, $data['department_name']);
                $sheet->setCellValue($col++, $data['total_staff']);
                $sheet->setCellValue($col++, $data['staff_on_leave']);
                $sheet->setCellValue($col++, $data['total_applications']);
                $sheet->setCellValue($col++, $data['approved_days'] ?? 0);
                $sheet->setCellValue($col++, $data['rejected_days'] ?? 0);
                $sheet->setCellValue($col++, $data['pending_days'] ?? 0);
            } elseif ($report_type == 'monthly_trends') {
                $sheet->setCellValue($col++, date('F Y', strtotime($data['month'] . '-01')));
                $sheet->setCellValue($col++, $data['total_applications']);
                $sheet->setCellValue($col++, $data['unique_applicants']);
                $sheet->setCellValue($col++, $data['approved_days'] ?? 0);
                $sheet->setCellValue($col++, $data['rejected_days'] ?? 0);
                $sheet->setCellValue($col++, $data['pending_days'] ?? 0);
            } elseif ($report_type == 'leave_balance') {
                $usage_percent = ($data['allocated_days'] > 0) ? round(($data['used_days'] / $data['allocated_days']) * 100, 1) : 0;
                $sheet->setCellValue($col++, $data['first_name'] . ' ' . $data['last_name']);
                $sheet->setCellValue($col++, $data['employee_id']);
                $sheet->setCellValue($col++, $data['department_name']);
                $sheet->setCellValue($col++, $data['leave_type']);
                $sheet->setCellValue($col++, $data['allocated_days']);
                $sheet->setCellValue($col++, $data['used_days']);
                $sheet->setCellValue($col++, $data['remaining_days']);
                $sheet->setCellValue($col++, $usage_percent . '%');
            }
            $currentRow++;
        }
    } else {
        // If no data is found, add a message to the spreadsheet.
        $currentRow++;
        $sheet->setCellValue('A' . $currentRow, 'No data found for the selected criteria.');
        $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // --- Final Spreadsheet Styling ---
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $dataRange = 'A5:' . $sheet->getHighestDataColumn() . ($sheet->getHighestDataRow());
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // --- File Output ---
    $writer = new Xlsx($spreadsheet);
    $filename = 'ELMS_' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.xlsx';

    // Set HTTP headers to prompt a file download.
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Write the file to the browser output.
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // Catch database-specific errors (e.g., query failed)
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    // Catch general application errors (e.g., file not found, connection failed)
    die("An error occurred: " . $e->getMessage());
}
?>
