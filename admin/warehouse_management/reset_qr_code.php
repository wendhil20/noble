<?php
// reset_qr_code.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing item ID']);
    exit();
}

$itemId = intval($input['item_id']);

try {
    $stmt = $conn->prepare("UPDATE order_items SET qr_code = NULL, warehouse_location = NULL WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        error_log("QR Code reset - Item ID: $itemId, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code and location reset successfully'
        ]);
    } else {
        throw new Exception('Failed to execute update query');
    }
    
} catch (Exception $e) {
    error_log("Error resetting QR code: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>