<?php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

// 1. Make sure the user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// 2. Get the order_id from the request
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

// 3. Lookup this user's emp_id
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if (!$emp_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Employee record not found']);
    exit;
}

// 4. Fetch the specific order assigned to this emp_id
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
    WHERE id = ? AND emp_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or you do not have permission to view it']);
    exit;
}

$order = $result->fetch_assoc();

// Format the date exactly as your front-end expects
$order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));

// 5. Fetch the order items
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
while ($item = $itemsRes->fetch_assoc()) {
    // Ensure numeric formatting matches JS expectations
    $item['price'] = number_format((float)$item['price'], 2, '.', '');
    $item['subtotal'] = number_format((float)$item['subtotal'], 2, '.', '');
    $items[] = $item;
}
$itemStmt->close();

// 6. Attach items to the order
$order['items'] = $items;

// 7. Return the single order in the format expected by frontend
echo json_encode([
    'success' => true,
    'order' => $order
]);
?>