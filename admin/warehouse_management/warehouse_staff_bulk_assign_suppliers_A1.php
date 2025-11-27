<?php
//warehouse_staff_bulk_assign_suppliers_A1.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id parameter']);
    exit;
}

$order_id = (int)$input['order_id'];
$type = isset($input['type']) ? $input['type'] : 'primary_only';

try {
    $conn->begin_transaction();

    // Get all unassigned order items for this order
    $stmt = $conn->prepare("
        SELECT 
            oi.id as item_id,
            oi.product_id,
            oi.product_name,
            oi.supplier_id,
            oi.manual_supplier_name
        FROM order_items oi 
        WHERE oi.order_id = ? 
            AND (oi.supplier_id IS NULL OR (oi.supplier_id = 0 AND (oi.manual_supplier_name IS NULL OR oi.manual_supplier_name = '')))
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $unassignedItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($unassignedItems)) {
        echo json_encode([
            'success' => true,
            'message' => 'No unassigned items found',
            'assigned_count' => 0,
            'assigned_items' => []
        ]);
        exit;
    }

    $assignedCount = 0;
    $assignedItems = [];
    $errors = [];

    foreach ($unassignedItems as $item) {
        if (!$item['product_id']) {
            $errors[] = "Item {$item['item_id']}: No product ID found";
            continue;
        }

        // Find primary supplier for this product
        $supplierStmt = $conn->prepare("
            SELECT 
                slp.supplier_id,
                sl.business_name
            FROM supp_link_products slp
            INNER JOIN supplier_list sl ON slp.supplier_id = sl.id
            WHERE slp.product_id = ? 
                AND slp.supplier_type = 'primary'
                AND slp.status = 'active' 
                AND sl.status = 'active'
            LIMIT 1
        ");
        $supplierStmt->bind_param("i", $item['product_id']);
        $supplierStmt->execute();
        $primarySupplier = $supplierStmt->get_result()->fetch_assoc();
        $supplierStmt->close();

        if (!$primarySupplier) {
            $errors[] = "Item {$item['item_id']}: No active primary supplier found for product ID {$item['product_id']}";
            continue;
        }

        // Assign the primary supplier
        $updateStmt = $conn->prepare("
            UPDATE order_items 
            SET supplier_id = ?, manual_supplier_name = NULL 
            WHERE id = ?
        ");
        $updateStmt->bind_param("ii", $primarySupplier['supplier_id'], $item['item_id']);
        
        if ($updateStmt->execute()) {
            $assignedCount++;
            $assignedItems[] = $item['item_id'];
        } else {
            $errors[] = "Item {$item['item_id']}: Failed to update database";
        }
        $updateStmt->close();
    }

    $conn->commit();

    $response = [
        'success' => true,
        'message' => "Successfully assigned {$assignedCount} items to their primary suppliers",
        'assigned_count' => $assignedCount,
        'assigned_items' => $assignedItems,
        'total_processed' => count($unassignedItems)
    ];

    if (!empty($errors)) {
        $response['warnings'] = $errors;
        $response['message'] .= ". Some items could not be assigned - see warnings.";
    }

    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
