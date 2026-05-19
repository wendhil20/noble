<?php
// update_tracking_status.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
include ROOT_PATH . '/admin/ui-warehouse/audit_trail_helper.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

$user_id = $_SESSION['noble_id'] ?? 0;

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User ID not found in session']);
    exit();
}

// ✅ REPLACEMENT HANDLER — BAGO ang validation ng item_id
$replacement_id = isset($data['replacement_id']) ? intval($data['replacement_id']) : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($replacement_id > 0 && !empty($status)) {
    $stmt = $conn->prepare("UPDATE replacement_requests SET status = ?, received_by = ?, received_at = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $status, $user_id, $replacement_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Status updated to: ' . $status]);
    exit();
}

// ✅ REGULAR ITEM HANDLER
$item_id = isset($data['item_id']) ? intval($data['item_id']) : 0;
$tracking_status = isset($data['tracking_status']) ? trim($data['tracking_status']) : '';
$mark_as_received = isset($data['mark_as_received']) ? (bool)$data['mark_as_received'] : false;

if ($item_id <= 0 || empty($tracking_status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item ID or tracking status']);
    exit();
}

try {
    $conn->begin_transaction();

    $oldStatusStmt = $conn->prepare("SELECT tracking_status, order_id, po_number, received_status FROM order_items WHERE id = ?");
    $oldStatusStmt->bind_param("i", $item_id);
    $oldStatusStmt->execute();
    $oldStatusResult = $oldStatusStmt->get_result();
    $oldData = $oldStatusResult->fetch_assoc();
    $oldStatus = $oldData['tracking_status'] ?? 'Unknown';
    $order_id = $oldData['order_id'] ?? null;
    $po_number = $oldData['po_number'] ?? null;
    $old_received_status = $oldData['received_status'] ?? 'pending';
    $oldStatusStmt->close();

    if (!$order_id) {
        throw new Exception('Order ID not found for this item');
    }

    $updateSql = "UPDATE order_items SET tracking_status = ?";
    $params = [$tracking_status];
    $types = "s";

    if ($mark_as_received && $tracking_status === 'In Warehouse') {
        $updateSql .= ", received_status = 'received', received_by = ?, received_at = NOW()";
        $params[] = $user_id;
        $types .= "i";
    }

    $updateSql .= " WHERE id = ?";
    $params[] = $item_id;
    $types .= "i";

    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update tracking status: ' . $stmt->error);
    }
    $stmt->close();

    $auditDescription = "Updated tracking status from '$oldStatus' to '$tracking_status'";
    if ($mark_as_received && $tracking_status === 'In Warehouse') {
        $auditDescription .= " and marked as received";
    }

    logAuditTrail(
        $conn,
        'UPDATE_TRACKING_STATUS',
        'order_items',
        $item_id,
        $order_id,
        $item_id,
        $oldStatus,
        $tracking_status,
        $auditDescription
    );

    $response_data = [
        'success' => true,
        'message' => 'Tracking status updated successfully',
        'item_id' => $item_id,
        'tracking_status' => $tracking_status
    ];

    if ($mark_as_received && $tracking_status === 'In Warehouse' && $po_number) {

        if ($old_received_status !== 'received') {
            logAuditTrail(
                $conn,
                'MARK_ITEM_RECEIVED',
                'order_items',
                $item_id,
                $order_id,
                $item_id,
                $old_received_status,
                'received',
                "Item marked as received in warehouse by user ID: $user_id"
            );
        }

        $checkStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN received_status = 'received' THEN 1 ELSE 0 END) as received_count
            FROM order_items 
            WHERE po_number = ?
        ");
        $checkStmt->bind_param("s", $po_number);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        $all_received = ($checkResult['total'] == $checkResult['received_count'] && $checkResult['total'] > 0);

        $response_data['po_number'] = $po_number;
        $response_data['all_items_received'] = $all_received;
        $response_data['received_count'] = $checkResult['received_count'];
        $response_data['total_count'] = $checkResult['total'];

        if ($all_received) {
            $response_data['message'] .= ". All items for P.O. $po_number have been received.";
            $response_data['can_mark_complete'] = true;
        } else {
            $response_data['message'] .= ". {$checkResult['received_count']} of {$checkResult['total']} items received for P.O. $po_number.";
            $response_data['can_mark_complete'] = false;
        }
    }

    $conn->commit();
    echo json_encode($response_data);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in update_tracking_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>