<?php
// checkout-qrph-create.php - USING CHECKOUT SESSION (fixed amount QRPh)
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

try {
    $data     = json_decode(file_get_contents('php://input'), true);
    $amount   = floatval($data['amount'] ?? 0);
    $order_id = intval($data['order_id'] ?? 0);

    if ($amount <= 0 || $order_id <= 0) {
        throw new Exception('Invalid amount or order ID');
    }

    $amount_in_centavos  = intval($amount * 100);
    $paymongo_secret_key = 'sk_live_EZ28DgdAquZ2YhHkBX4rxHC3';

    error_log("QRPh Checkout Session: Order #$order_id, ₱$amount ({$amount_in_centavos} centavos)");

    // ✅ CREATE CHECKOUT SESSION - QRPh only, fixed amount
    $payload = json_encode([
        'data' => [
            'attributes' => [
                'amount'               => $amount_in_centavos,
                'currency'             => 'PHP',
                'payment_method_types' => ['qrph'],
                'line_items'           => [[
                    'name'     => 'Noble Home Order #' . $order_id,
                    'quantity' => 1,
                    'amount'   => $amount_in_centavos,
                    'currency' => 'PHP',
                ]],
                'success_url' => 'https://noblehomedepot.com/user/otherpage/checkout-order_receipt-page-12-A.php?order_id=' . $order_id,
                'cancel_url'  => 'https://noblehomedepot.com/user/otherpage/index-checkout-page-12.php?payment_cancelled=1&order_id=' . $order_id,
                'description' => 'Noble Home Order #' . $order_id,
                'metadata'    => [
                    'order_id'  => strval($order_id),
                    'source'    => 'qrph_checkout'
                ]
            ]
        ]
    ]);

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret_key . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Checkout Session Response ($http_code): " . $response);

    if ($http_code !== 200 && $http_code !== 201) {
        throw new Exception('PayMongo API failed: HTTP ' . $http_code . ' - ' . $response);
    }

    $session_data    = json_decode($response, true);
    $session_id      = $session_data['data']['id'] ?? null;
    $checkout_url    = $session_data['data']['attributes']['checkout_url'] ?? null;

    if (!$session_id || !$checkout_url) {
        throw new Exception('No session ID or checkout URL in response');
    }

    error_log("✓ Checkout Session created: $session_id");
    error_log("✓ Checkout URL: $checkout_url");

    // Save to DB
    $stmt = $conn->prepare("
        INSERT INTO qrph_codes 
            (order_id, qr_code_id, qr_image_url, amount, status, created_at, expires_at)
        VALUES 
            (?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ON DUPLICATE KEY UPDATE 
            qr_code_id   = VALUES(qr_code_id),
            qr_image_url = VALUES(qr_image_url),
            status       = 'pending',
            created_at   = NOW(),
            expires_at   = DATE_ADD(NOW(), INTERVAL 1 HOUR)
    ");

    if (!$stmt) throw new Exception('DB prepare failed: ' . $conn->error);
    // Store checkout_url as qr_image_url temporarily, session_id as qr_code_id
    $stmt->bind_param('issd', $order_id, $session_id, $checkout_url, $amount);
    if (!$stmt->execute()) throw new Exception('DB execute failed: ' . $stmt->error);
    $stmt->close();

    error_log("✓ Saved: session=$session_id linked to order_id=$order_id");

    // ✅ Return checkout URL — JavaScript will redirect user
    echo json_encode([
        'success'      => true,
        'checkout_url' => $checkout_url,
        'session_id'   => $session_id,
        'order_id'     => $order_id,
    ]);

} catch (Exception $e) {
    error_log('❌ QRPh Checkout Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}
?>