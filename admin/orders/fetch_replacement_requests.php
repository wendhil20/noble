<?php
//fetch_replacement_requests.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

// Make sure user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get order ID from URL parameter
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Verify the order belongs to the current employee
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

// Verify order ownership
$stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND emp_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $emp_id);
$stmt->execute();
$stmt->bind_result($verified_order_id);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Order not found or access denied']);
    exit;
}
$stmt->close();

// Fetch replacement requests for this order with product details
$stmt = $conn->prepare("
    SELECT 
        rr.id,
        rr.order_id,
        rr.order_item_id,
        rr.user_email,
        rr.reason,
        rr.details,
        rr.replacement_quantity,
        rr.defect_image_overview,
        rr.defect_image_closeup,
        rr.defect_image_detail,
        rr.status,
        rr.admin_notes,
        rr.created_at,
        rr.updated_at,
        oi.product_name,
        oi.codename,
        oi.size,
        oi.variant_color,
        oi.price,
        oi.quantity as original_quantity,
        oi.subtotal
    FROM replacement_requests rr
    JOIN order_items oi ON rr.order_item_id = oi.id
    WHERE rr.order_id = ?
    ORDER BY rr.created_at DESC
");

$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    // Format dates
    $row['created_at'] = date('M j, Y g:i A', strtotime($row['created_at']));
    $row['updated_at'] = date('M j, Y g:i A', strtotime($row['updated_at']));
    
    // Format price
    $row['price'] = number_format((float)$row['price'], 2, '.', '');
    $row['subtotal'] = number_format((float)$row['subtotal'], 2, '.', '');
    
    $requests[] = $row;
}

$stmt->close();

// Return JSON array
echo json_encode($requests);
?>