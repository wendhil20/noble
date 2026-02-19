<?php
// checkout-qrph-create.php - WORKING VERSION (official PayMongo endpoint)
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

    error_log("QRPh: Creating QR for Order #$order_id, Amount: ₱$amount");

    $paymongo_secret_key = 'sk_live_EZ28DgdAquZ2YhHkBX4rxHC3';

    // ✅ OFFICIAL PAYMONGO ENDPOINT
    $ch = curl_init('https://api.paymongo.com/v1/qrph/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret_key . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"data":{"attributes":{"kind":"instore"}}}');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("QRPh Response ($http_code): " . $response);

    if ($http_code !== 200 && $http_code !== 201) {
        throw new Exception('PayMongo API failed: HTTP ' . $http_code . ' - ' . $response);
    }

    $qr_data = json_decode($response, true);
    $qr_id   = $qr_data['data']['id'] ?? null;
    $attrs   = $qr_data['data']['attributes'] ?? [];

    if (!$qr_id) {
        throw new Exception('No QR ID in response');
    }

    error_log("✓ QR generated: $qr_id");
    error_log("Attribute keys: " . implode(', ', array_keys($attrs)));
    error_log("Full response: " . json_encode($qr_data, JSON_PRETTY_PRINT));

    // Find QR image URL
    $qr_image_url = $attrs['qr_image']
        ?? $attrs['qr_code']
        ?? $attrs['image_url']
        ?? $attrs['url']
        ?? $attrs['source']['url']
        ?? null;

    if (!$qr_image_url) {
        throw new Exception('No QR image URL found. Keys available: ' . implode(', ', array_keys($attrs)));
    }

    // ✅ Save to DB - link code_xxx (qr_code_id) to order_id
    // Webhook will match via provider.code_id which equals this qr_id
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
    $stmt->bind_param('issd', $order_id, $qr_id, $qr_image_url, $amount);
    if (!$stmt->execute()) throw new Exception('DB execute failed: ' . $stmt->error);
    $stmt->close();

    error_log("✓ Saved: qr_id=$qr_id linked to order_id=$order_id");

    echo json_encode([
        'success' => true,
        'data'    => [
            'id'         => $qr_id,
            'attributes' => [
                'qr_image' => $qr_image_url,
                'amount'   => $amount,
                'order_id' => $order_id,
                'status'   => 'active'
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log('❌ QRPh Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}
?>