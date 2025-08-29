<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    error_log("Update fees input: " . $input);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!isset($data['order_id'], $data['field'], $data['value'])) {
        throw new Exception('Missing required fields');
    }

    $order_id = (int)$data['order_id'];
    $field = trim($data['field']);
    $value = (float)$data['value'];

    if ($order_id <= 0) throw new Exception('Invalid order ID');

    $allowed_fields = ['discount', 'shipping_fee', 'delivery_fee'];
    if (!in_array($field, $allowed_fields)) {
        throw new Exception('Invalid field: ' . $field);
    }

    if ($value < 0) throw new Exception('Value cannot be negative');
    if (!$conn) throw new Exception('Database connection failed');

    // Step 1: Update the requested field (discount, shipping_fee, or delivery_fee)
    $stmt = $conn->prepare("UPDATE orders SET `$field` = ? WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param("di", $value, $order_id);
    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
    $stmt->close();

    // Step 2: Fetch current order details
    $stmt = $conn->prepare("SELECT total, discount, shipping_fee, delivery_fee FROM orders WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare select failed: ' . $conn->error);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Order not found');
    $order = $result->fetch_assoc();
    $stmt->close();

    // Step 3: Compute final_total only
    $total = (float)($order['total'] ?? 0);
    $discount_percent = (float)($order['discount'] ?? 0);
    $shipping_fee = (float)($order['shipping_fee'] ?? 0);
    $delivery_fee = (float)($order['delivery_fee'] ?? 0);

    $discount_amount = round($total * ($discount_percent / 100), 2);
    $net_total = $total - $discount_amount;
    if ($net_total < 0) $net_total = 0;

    $vat = round($net_total * 0.12, 2);
    $final_total = round($net_total + $vat + $shipping_fee + $delivery_fee, 2);

    // Step 4: Save only final_total
    $stmt = $conn->prepare("UPDATE orders SET final_total = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare update final_total failed: ' . $conn->error);
    $stmt->bind_param("di", $final_total, $order_id);
    if (!$stmt->execute()) throw new Exception('Execute update final_total failed: ' . $stmt->error);
    $stmt->close();

    // Step 5: Respond with JSON
    echo json_encode([
        'success' => true,
        'message' => 'Final total updated successfully.',
        'data' => [
            'order_id' => $order_id,
            'field_updated' => $field,
            'value' => $value,
            'discount_percent' => $discount_percent,
            'discount_amount' => $discount_amount,
            'shipping_fee' => $shipping_fee,
            'delivery_fee' => $delivery_fee,
            'vat' => $vat,
            'final_total' => $final_total
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in update_order_fees.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'input' => $input ?? 'No input',
            'data' => $data ?? 'No data parsed'
        ]
    ]);
}
?>
