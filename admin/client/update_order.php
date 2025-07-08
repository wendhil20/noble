<?php
// update_order_status.php - MySQLi version
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../connection/connect.php'; // Your database connection file

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_id']) || !isset($input['status'])) {
        throw new Exception('Missing required parameters');
    }
    
    $orderId = $input['order_id'];
    $newStatus = $input['status'];
    
    // Validate status
    $allowedStatuses = ['Pending', 'Ongoing', 'Arrival', 'Departure', 'Complete'];
    if (!in_array($newStatus, $allowedStatuses)) {
        throw new Exception('Invalid status');
    }
    
    // Get current status first for logging
    $currentStatusStmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $currentStatusStmt->bind_param('i', $orderId);
    $currentStatusStmt->execute();
    $result = $currentStatusStmt->get_result();
    $currentStatus = $result->fetch_column();
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $orderId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log status change if the table exists
        try {
            $logStmt = $conn->prepare("INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, 'system', NOW())");
            $logStmt->bind_param('iss', $orderId, $currentStatus, $newStatus);
            $logStmt->execute();
        } catch (Exception $e) {
            // If logging table doesn't exist, just log to error log
            error_log("Status change log failed: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Order not found or no changes made');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>