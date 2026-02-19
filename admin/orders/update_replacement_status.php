<?php
//update_replacement_status.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

// Make sure user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['request_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$request_id = (int)$input['request_id'];
$status = $input['status'];
$admin_notes = isset($input['admin_notes']) ? trim($input['admin_notes']) : '';

// Validate status
$valid_statuses = ['pending', 'approved', 'rejected', 'processing', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Get current user's employee ID
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if (!$emp_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Employee record not found']);
    exit;
}

// Verify the replacement request belongs to an order assigned to this employee
$stmt = $conn->prepare("
    SELECT rr.id, rr.order_id, o.emp_id, rr.status as current_status
    FROM replacement_requests rr
    JOIN orders o ON rr.order_id = o.id
    WHERE rr.id = ? AND o.emp_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $request_id, $emp_id);
$stmt->execute();
$stmt->bind_result($verified_request_id, $order_id, $order_emp_id, $current_status);

if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Replacement request not found or access denied']);
    exit;
}
$stmt->close();

// Check if status change is allowed
if ($current_status === 'completed' || $current_status === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Cannot modify completed or cancelled requests']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Update the replacement request
    $update_stmt = $conn->prepare("
        UPDATE replacement_requests 
        SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $update_stmt->bind_param("ssi", $status, $admin_notes, $request_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update replacement request');
    }
    $update_stmt->close();
    
    // Log the status change (optional - you can create an activity log table)
    // For now, we'll just commit the transaction
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Replacement request status updated successfully',
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating replacement status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update replacement request: ' . $e->getMessage()
    ]);
}
?>