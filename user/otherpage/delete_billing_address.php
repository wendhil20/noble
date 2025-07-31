<?php
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['address_id']) || !is_numeric($input['address_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    exit;
}

$address_id = (int)$input['address_id'];
$user_id = $_SESSION['user_id'];

try {
    // First, verify that the address belongs to the current user
    $stmt = $conn->prepare("SELECT id FROM billing_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Address not found or access denied']);
        exit;
    }
    $stmt->close();
    
    // Delete the address
    $stmt = $conn->prepare("DELETE FROM billing_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No address was deleted']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Delete billing address error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the address']);
}

$conn->close();
?>