<?php
// update_tracking_status.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_once 'audit_trail_helper.php'; // ADD THIS LINE
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

$item_id = isset($data['item_id']) ? intval($data['item_id']) : 0;
$tracking_status = isset($data['tracking_status']) ? trim($data['tracking_status']) : '';

if ($item_id <= 0 || empty($tracking_status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item ID or tracking status']);
    exit();
}

// GET OLD VALUE FOR AUDIT TRAIL - ADD THIS BLOCK
$oldStatusStmt = $conn->prepare("SELECT tracking_status, order_id FROM order_items WHERE id = ?");
$oldStatusStmt->bind_param("i", $item_id);
$oldStatusStmt->execute();
$oldStatusResult = $oldStatusStmt->get_result();
$oldData = $oldStatusResult->fetch_assoc();
$oldStatus = $oldData['tracking_status'] ?? 'Unknown';
$order_id = $oldData['order_id'] ?? null;
$oldStatusStmt->close();

// Update tracking status
$stmt = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE id = ?");
$stmt->bind_param("si", $tracking_status, $item_id);

if ($stmt->execute()) {
    // LOG AUDIT TRAIL - ADD THIS BLOCK
    logAuditTrail(
        $conn,
        'UPDATE_TRACKING_STATUS',
        'order_items',
        $item_id,
        $order_id,
        $item_id,
        $oldStatus,
        $tracking_status,
        "Updated tracking status from '$oldStatus' to '$tracking_status'"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Tracking status updated successfully',
        'item_id' => $item_id,
        'tracking_status' => $tracking_status
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>