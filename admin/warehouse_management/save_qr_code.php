<?php
// save_qr_code.php
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

if (!isset($input['item_id']) || !isset($input['qr_code'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$itemId = intval($input['item_id']);
$qrCode = trim($input['qr_code']);

if (empty($qrCode)) {
    echo json_encode(['success' => false, 'error' => 'QR code cannot be empty']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE order_items SET qr_code = ? WHERE id = ?");
    $stmt->bind_param("si", $qrCode, $itemId);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affectedRows > 0) {
            // Log the action
            $user_info = is_array($_SESSION['noble_user']) ? 
                ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
                'Unknown';
            error_log("QR Code saved - Item ID: $itemId, QR: $qrCode, By: $user_info");
            
            echo json_encode([
                'success' => true,
                'message' => 'QR code saved successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No rows were updated. Item may not exist.'
            ]);
        }
    } else {
        throw new Exception('Failed to execute update query: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error saving QR code: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>