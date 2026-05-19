<?php
// warehouse_staff_reset_po_number_A-B2.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse', 'superadmin', 'sales']);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_ids']) || !is_array($input['item_ids']) || empty($input['item_ids'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid item IDs']);
    exit();
}

$itemIds = array_map('intval', $input['item_ids']);
$placeholders = implode(',', array_fill(0, count($itemIds), '?'));

try {
    $stmt = $conn->prepare("UPDATE order_items SET po_number = NULL WHERE id IN ($placeholders)");
    
    $types = str_repeat('i', count($itemIds));
    $bind_names = [$types];
    foreach ($itemIds as $i => $id) {
        $bind_name = 'bind' . $i;
        $$bind_name = $id;
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "P.O. number reset for $affected item(s)",
            'affected_rows' => $affected
        ]);
    } else {
        throw new Exception('Failed to execute update query');
    }
    
} catch (Exception $e) {
    error_log("Error resetting P.O. number: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>