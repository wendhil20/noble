<?php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

// Get order items with tracking information
$itemsSql = "
    SELECT 
        oi.id,
        oi.order_id,
        CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
        CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
        oi.price,
        oi.quantity,
        CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
        CAST(COALESCE(sl.business_name, oi.manual_supplier_name) AS CHAR) COLLATE utf8mb4_unicode_ci as supplier_name,
        CAST(COALESCE(oi.tracking_status, 'processing') AS CHAR) COLLATE utf8mb4_unicode_ci as current_status,
        CAST('original' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
        NULL as replacement_id,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ?
    
    UNION ALL
    
    SELECT 
        oi.id,
        oi.order_id,
        CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
        CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
        oi.price,
        rr.replacement_quantity as quantity,
        CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
        CAST(COALESCE(sl.business_name, oi.manual_supplier_name) AS CHAR) COLLATE utf8mb4_unicode_ci as supplier_name,
        CAST(COALESCE(rr.status, 'approved') AS CHAR) COLLATE utf8mb4_unicode_ci as current_status,
        CAST('replacement' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
        rr.id as replacement_id,
        CAST(rr.reason AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason
    FROM replacement_requests rr
    LEFT JOIN order_items oi ON rr.order_item_id = oi.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE rr.order_id = ? AND rr.status IN ('approved', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered')
    
    ORDER BY origin, supplier_name, product_name, item_type
";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("ii", $order_id, $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Group items by origin and supplier
$groupedItems = [
    'local' => [],
    'international' => []
];

foreach ($items as $item) {
    $origin = $item['origin'] ?? 'local';
    $supplier = $item['supplier_name'] ?? 'Unknown Supplier';

    if (!isset($groupedItems[$origin][$supplier])) {
        $groupedItems[$origin][$supplier] = [
            'original' => [],
            'replacement' => []
        ];
    }

    $itemType = $item['item_type'];
    $groupedItems[$origin][$supplier][$itemType][] = $item;
}

echo json_encode([
    'success' => true,
    'groupedItems' => $groupedItems,
    'order_id' => $order_id
]);
?>