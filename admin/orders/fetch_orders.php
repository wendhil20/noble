<?php
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

// 2. Lookup this user’s emp_id
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

// 3. Fetch only orders assigned to this emp_id
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
        COALESCE(status, 'pending')    AS status,
        COALESCE(discount, 0)         AS discount,
        COALESCE(shipping_fee, 0)     AS shipping_fee,
        COALESCE(delivery_fee, 0)     AS delivery_fee
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

    // 4. For each order, fetch its items
    $itemStmt = $conn->prepare("
        SELECT 
            product_name,
            size,
            variant_color,
            codename,
            descrip6,
            descrip7,
            price,
            quantity,
            subtotal
        FROM order_items
        WHERE order_id = ?
    ");
    $itemStmt->bind_param("i", $order['id']);
    $itemStmt->execute();
    $itemsRes = $itemStmt->get_result();

    $items = [];
    while ($it = $itemsRes->fetch_assoc()) {
        // ensure numeric formatting matches JS expectations
        $it['price']    = number_format((float)$it['price'], 2, '.', '');
        $it['subtotal'] = number_format((float)$it['subtotal'], 2, '.', '');
        $items[] = $it;
    }
    $itemStmt->close();

    // 5. Attach as `items` (not `products`)
    $order['items'] = $items;

    $orders[] = $order;
}

// 6. Return a JSON **array** — front-end does `orders.filter(...)`, so passing an array is critical
echo json_encode($orders);
