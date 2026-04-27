<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = $_POST['address_id'] ?? '';

if (empty($address_id) || !is_numeric($address_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Set all addresses to inactive for this user
    $stmt = $conn->prepare("UPDATE billing_addresses SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Set selected address as active
    $stmt = $conn->prepare("UPDATE billing_addresses SET is_active = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Active address updated']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>