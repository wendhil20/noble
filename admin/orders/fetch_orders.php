<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['admin', 'superadmin']);

header('Content-Type: application/json');

try {
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Fetch all orders first
    $orders_result = $conn->query("
        SELECT 
            id, customer_name, email, mobile, address, zipcode, total, created_at,
            COALESCE(status, 'pending') as status,
            COALESCE(discount, 0) as discount,
            COALESCE(shipping_fee, 0) as shipping_fee,
            COALESCE(delivery_fee, 0) as delivery_fee,
            COALESCE(vat_amount, 0) as vat_amount,
            COALESCE(final_total, 0) as final_total
        FROM orders
        ORDER BY created_at DESC
    ");
    if (!$orders_result) {
        throw new Exception('Orders query failed: ' . $conn->error);
    }

    $orders = [];
    $order_ids = [];

    while ($order = $orders_result->fetch_assoc()) {
        // Format date
        $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));
        $orders[$order['id']] = $order;
        $order_ids[] = $order['id'];
    }

    if (count($order_ids) > 0) {
        // Prepare a query to fetch all order items for these orders in one go
        $ids_placeholder = implode(',', array_fill(0, count($order_ids), '?'));
        $types = str_repeat('i', count($order_ids));
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id IN ($ids_placeholder)");
        if (!$stmt) {
            throw new Exception('Prepare order_items failed: ' . $conn->error);
        }
        $stmt->bind_param($types, ...$order_ids);
        $stmt->execute();
        $items_result = $stmt->get_result();

        // Group items by order_id
        while ($item = $items_result->fetch_assoc()) {
            $order_id = $item['order_id'];
            $processed_item = [
                'product_name' => $item['product_name'] ?? $item['name'] ?? 'Unknown Product',
                'size' => $item['size'] ?? 'N/A',
                'variant_color' => $item['variant_color'] ?? $item['color'] ?? 'N/A',
                'price' => number_format($item['price'] ?? 0, 2),
                'quantity' => $item['quantity'] ?? 0,
                'subtotal' => number_format($item['subtotal'] ?? (($item['price'] ?? 0) * ($item['quantity'] ?? 0)), 2),
                'codename' => $item['codename'] ?? '',
                'descrip6' => $item['descrip6'] ?? '',
                'descrip7' => $item['descrip7'] ?? ''
            ];
            $orders[$order_id]['items'][] = $processed_item;
        }
        $stmt->close();
    }

    // Convert orders from associative by id to indexed array for JSON
    $orders = array_values($orders);

    echo json_encode($orders);

} catch (Exception $e) {
    error_log("Error in fetch_orders.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
