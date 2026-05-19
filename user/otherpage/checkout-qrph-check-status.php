<?php
// checkout-qrph-check-status.php - Checks if QRPh payment received via PayMongo API
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

try {
    $qr_id = $_GET['qr_id'] ?? '';
    $order_id = intval($_GET['order_id'] ?? 0);
    
    if (empty($qr_id) || $order_id <= 0) {
        throw new Exception('Invalid parameters');
    }

    // ✅ FIRST: Check local database for QR code
    $stmt = $conn->prepare("
        SELECT id, order_id, status, paid_at, paymongo_intent_id
        FROM qrph_codes 
        WHERE qr_code_id = ? AND order_id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Database error');
    }
    
    $stmt->bind_param('si', $qr_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("⚠️ QR code not found: $qr_id");
        echo json_encode([
            'qr_id' => $qr_id,
            'order_id' => $order_id,
            'paid' => false,
            'status' => 'not_found',
            'message' => 'QR code not found'
        ]);
        exit;
    }
    
    $qr_record = $result->fetch_assoc();
    $stmt->close();
    
    // ✅ Check if already marked as paid
    $is_paid = ($qr_record['status'] === 'paid' && !empty($qr_record['paid_at']));
    
    if ($is_paid) {
        error_log("✓ Order #$order_id already marked as PAID (via webhook)");
        echo json_encode([
            'qr_id' => $qr_id,
            'order_id' => $order_id,
            'paid' => true,
            'status' => 'paid',
            'paid_at' => $qr_record['paid_at']
        ]);
        exit;
    }

    // ✅ NOTE: For QRPh, payment confirmation comes via webhook
    // No direct API check available - rely on webhook + polling
    // This is by design from PayMongo
    
    error_log("Waiting for webhook to mark payment as received");
    error_log("QR Code ID: $qr_id, Status: " . $qr_record['status']);
    
    // ✅ Still pending
    echo json_encode([
        'qr_id' => $qr_id,
        'order_id' => $order_id,
        'paid' => false,
        'status' => $qr_record['status'] ?? 'pending',
        'message' => 'Waiting for payment...'
    ]);

} catch (Exception $e) {
    error_log('QRPh Check Status Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'paid' => false
    ]);
}