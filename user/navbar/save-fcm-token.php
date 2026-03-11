<?php
// save-fcm-token.php
// I-upload sa: user/navbar/save-fcm-token.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../connection/connect.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
$userId = intval($input['userId'] ?? $_SESSION['user_id'] ?? 0);
$token  = trim($input['token'] ?? '');

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Missing userId or token']);
    exit;
}

// Upsert — insert or update if token already exists for this user
$stmt = $conn->prepare("
    INSERT INTO fcm_tokens (user_id, token, created_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()
");

if (!$stmt) {
    // Try without unique constraint — just replace
    $stmt = $conn->prepare("
        DELETE FROM fcm_tokens WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO fcm_tokens (user_id, token, created_at)
        VALUES (?, ?, NOW())
    ");
}

$stmt->bind_param("is", $userId, $token);
$success = $stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Token saved!' : $conn->error
]);