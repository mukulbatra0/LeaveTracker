<?php
/**
 * API Endpoint: Check for overlapping leave applications
 * 
 * This endpoint checks if a user has any existing leave applications
 * that overlap with the requested dates.
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['start_date']) || !isset($data['end_date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];
$start_date = $data['start_date'];
$end_date = $data['end_date'];
$is_half_day = isset($data['is_half_day']) ? $data['is_half_day'] : false;
$half_day_period = isset($data['half_day_period']) ? $data['half_day_period'] : null;

try {
    // Check for overlapping leave applications
    $overlap_sql = "SELECT la.id, la.start_date, la.end_date, la.status, la.days, 
                           lt.name as leave_type_name,
                           la.is_half_day, la.half_day_period
                    FROM leave_applications la
                    JOIN leave_types lt ON la.leave_type_id = lt.id
                    WHERE la.user_id = :user_id 
                    AND la.status NOT IN ('rejected', 'cancelled')
                    AND (
                        (la.start_date <= :end_date AND la.end_date >= :start_date)
                    )
                    ORDER BY la.start_date ASC";
    
    $overlap_stmt = $conn->prepare($overlap_sql);
    $overlap_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $overlap_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $overlap_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $overlap_stmt->execute();
    
    $overlapping_leaves = [];
    
    while ($leave = $overlap_stmt->fetch()) {
        // Special case: Same day with different half-day periods is allowed
        if ($is_half_day && $leave['is_half_day'] && 
            $start_date == $end_date && 
            $leave['start_date'] == $leave['end_date'] &&
            $start_date == $leave['start_date'] &&
            $half_day_period && $half_day_period != $leave['half_day_period']) {
            // Different half-day periods on same day - this is allowed, skip it
            continue;
        }
        
        // Real overlap found, add to list
        $overlapping_leaves[] = [
            'id' => $leave['id'],
            'leave_type' => $leave['leave_type_name'],
            'start_date' => date('d/m/Y', strtotime($leave['start_date'])),
            'end_date' => date('d/m/Y', strtotime($leave['end_date'])),
            'days' => $leave['days'],
            'status' => ucfirst($leave['status']),
            'is_half_day' => $leave['is_half_day'],
            'half_day_period' => $leave['half_day_period'] ? ($leave['half_day_period'] == 'first_half' ? 'First Half' : 'Second Half') : null
        ];
    }
    
    if (count($overlapping_leaves) > 0) {
        $response = [
            'has_overlap' => true,
            'overlapping_leaves' => $overlapping_leaves
        ];
    } else {
        $response = [
            'has_overlap' => false,
            'overlapping_leaves' => []
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
