<?php
// update_location.php
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

if (!isset($input['item_id']) || !isset($input['warehouse_location'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$itemId = intval($input['item_id']);
$warehouseLocation = trim($input['warehouse_location']);

if (empty($warehouseLocation)) {
    echo json_encode(['success' => false, 'error' => 'Location cannot be empty']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE order_items SET warehouse_location = ? WHERE id = ?");
    $stmt->bind_param("si", $warehouseLocation, $itemId);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        error_log("Location updated - Item ID: $itemId, Location: $warehouseLocation, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully'
        ]);
    } else {
        throw new Exception('Failed to execute update query');
    }
    
} catch (Exception $e) {
    error_log("Error updating location: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>