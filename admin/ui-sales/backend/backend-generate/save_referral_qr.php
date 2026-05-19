<?php
// save_referral_qr.php

include ROOT_PATH . '/connection/connect.php';
require_once ROOT_PATH . '/admin/authentication/index-admin-role.php';
require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['noble_id'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!isset($input['qr_data'])) {
    echo json_encode(['success' => false, 'error' => 'Missing QR data']);
    exit();
}

$qr_data = trim($input['qr_data']);

try {
    // Update the active referral code with QR path
    $stmt = $conn->prepare("UPDATE referral_codes SET qr_code_path = ? WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("si", $qr_data, $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'QR code saved successfully']);
    } else {
        throw new Exception('Failed to save QR code');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>