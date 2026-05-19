<?php
// warehouse_staff_assign_replacement_receiver_C3.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'warehouse']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$replacement_id = (int)($input['replacement_id'] ?? 0);
$receiver_id    = (int)($input['receiver_id'] ?? 0);
$po_number      = trim($input['po_number'] ?? '');

if (!$replacement_id || !$receiver_id || empty($po_number)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Validate receiver exists and has correct subrole
$checkSql = "SELECT id FROM nobleaccount WHERE id = ? AND subrole = 'warehouse_receiver' AND status = 'active' LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $receiver_id);
$checkStmt->execute();
if (!$checkStmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
    exit();
}
$checkStmt->close();

// Update replacement_requests with receiver_id AND set status to processing
$updateSql = "UPDATE replacement_requests SET receiver_id = ?, status = 'processing' WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("ii", $receiver_id, $replacement_id);

if ($updateStmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}
$updateStmt->close();
?>