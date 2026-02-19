<?php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$new_delivery_date = isset($_POST['new_delivery_date']) ? $_POST['new_delivery_date'] : '';
$new_delivery_time = isset($_POST['new_delivery_time']) ? $_POST['new_delivery_time'] : '';
$reschedule_reason = isset($_POST['reschedule_reason']) ? trim($_POST['reschedule_reason']) : '';

// Validate inputs
if (!$delivery_id || !$order_id || !$new_delivery_date || !$new_delivery_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_delivery_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}$/', $new_delivery_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit();
}

// Check if new date is not in the past
$newDateTime = strtotime("$new_delivery_date $new_delivery_time");
if ($newDateTime < time()) {
    echo json_encode(['success' => false, 'message' => 'Cannot reschedule to a past date/time']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Get current delivery schedule info
    $checkSql = "SELECT ds.*, o.customer_name 
                 FROM delivery_schedules ds
                 INNER JOIN orders o ON ds.order_id = o.id
                 WHERE ds.id = ? AND ds.order_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $delivery_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Delivery schedule not found');
    }
    
    $schedule = $result->fetch_assoc();
    $stmt->close();
    
    // Update delivery schedule
    $updateSql = "UPDATE delivery_schedules 
                  SET delivery_date = ?, 
                      delivery_time = ?,
                      delivery_notes = CONCAT(COALESCE(delivery_notes, ''), '\n[Rescheduled on " . date('Y-m-d H:i:s') . " by " . $_SESSION['noble_user'] . "]: ', ?)
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("sssi", $new_delivery_date, $new_delivery_time, $reschedule_reason, $delivery_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update delivery schedule');
    }
    $stmt->close();
    
    // Log the reschedule action (optional - create this table if you want to track changes)
    $logSql = "INSERT INTO delivery_reschedule_log 
               (delivery_schedule_id, order_id, old_date, old_time, new_date, new_time, reason, rescheduled_by, rescheduled_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($logSql);
    if ($stmt) {
        $stmt->bind_param("iissssss", 
            $delivery_id, 
            $order_id, 
            $schedule['delivery_date'], 
            $schedule['delivery_time'], 
            $new_delivery_date, 
            $new_delivery_time, 
            $reschedule_reason, 
            $_SESSION['noble_user']
        );
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Delivery rescheduled successfully',
        'new_date' => date('M d, Y', strtotime($new_delivery_date)),
        'new_time' => date('g:i A', strtotime($new_delivery_time))
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>