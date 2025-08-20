<?php
//fetch_orders.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

// 1. Make sure the user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// 2. Lookup this user's emp_id
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if (!$emp_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Employee record not found']);
    exit;
}

// 3. Fetch all orders assigned to this emp_id including payment_status
$stmt = $conn->prepare("
    SELECT 
        id,
        customer_name,
        email,
        mobile,
        address,
        zipcode,
        total,
        created_at,
        COALESCE(status, 'pending')         AS status,
        COALESCE(payment_status, 'pending') AS payment_status,
        COALESCE(delivery_fee, 0)           AS delivery_fee
    FROM orders
    WHERE emp_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    // format the date exactly as your front-end expects
    $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));

    // 4. For each order, fetch its items WITH SUPPLIER INFO (hybrid supplier system)
    $itemStmt = $conn->prepare("
        SELECT 
            oi.product_name,
            oi.size,
            oi.variant_color,
            oi.codename,
            oi.descrip6,
            oi.descrip7,
            oi.price,
            oi.quantity,
            oi.subtotal,
            oi.origin,
            oi.product_id,
            oi.supplier_id,
            oi.manual_supplier_name,
            oi.delivery_fee_per_item,
            oi.item_total_delivery,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.business_name
                WHEN oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '' THEN oi.manual_supplier_name
                ELSE 'Not Assigned'
            END AS supplier_name,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.primary_contact_name
                ELSE NULL
            END AS primary_contact_name,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.email_address
                ELSE NULL
            END AS supplier_email,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.phone_number
                ELSE NULL
            END AS supplier_phone,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.business_type
                ELSE NULL
            END AS business_type,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.country_region
                ELSE NULL
            END AS country_region,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 'database'
                WHEN oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '' THEN 'manual'
                ELSE 'none'
            END AS supplier_source
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND sl.status = 'active' AND oi.supplier_id > 0
        WHERE oi.order_id = ?
    ");
    $itemStmt->bind_param("i", $order['id']);
    $itemStmt->execute();
    $itemsRes = $itemStmt->get_result();

    $items = [];
    while ($it = $itemsRes->fetch_assoc()) {
        // ensure numeric formatting matches JS expectations
        $it['price']    = number_format((float)$it['price'], 2, '.', '');
        $it['subtotal'] = number_format((float)$it['subtotal'], 2, '.', '');
        $it['delivery_fee_per_item'] = number_format((float)$it['delivery_fee_per_item'], 2, '.', '');
        $it['item_total_delivery'] = number_format((float)$it['item_total_delivery'], 2, '.', '');
        
        // Add additional supplier information for display (only available for database suppliers)
        $it['supplier_contact'] = $it['primary_contact_name'] ?? '';
        $it['supplier_email'] = $it['supplier_email'] ?? '';
        $it['supplier_phone'] = $it['supplier_phone'] ?? '';
        $it['supplier_type'] = $it['business_type'] ?? '';
        $it['supplier_location'] = $it['country_region'] ?? '';
        
        // Add supplier source information for frontend logic
        $it['is_manual_supplier'] = ($it['supplier_source'] === 'manual');
        $it['is_database_supplier'] = ($it['supplier_source'] === 'database');
        $it['has_supplier'] = ($it['supplier_source'] !== 'none');
        
        $items[] = $it;
    }
    $itemStmt->close();

    // 5. Attach as `items` (not `products`)
    $order['items'] = $items;

    $orders[] = $order;
}

// 6. Return a JSON **array** — front-end does `orders.filter(...)`, so passing an array is critical
echo json_encode($orders);
?>