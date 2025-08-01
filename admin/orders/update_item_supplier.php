<?php
// update_item_supplier.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['item_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields'
    ]);
    exit();
}

$item_id = (int)$data['item_id'];
$supplier_id = isset($data['supplier_id']) && $data['supplier_id'] !== '' ? (int)$data['supplier_id'] : null;

// Get current user's emp_id
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if (!$emp_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not found'
    ]);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify that the item belongs to an order assigned to the current user
    $stmt = $conn->prepare("
        SELECT oi.id, oi.order_id, o.emp_id 
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE oi.id = ? AND o.emp_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $item_id, $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        throw new Exception('Order item not found or access denied');
    }
    
    // If supplier_id is provided, verify that the supplier exists
    if ($supplier_id !== null) {
        $stmt = $conn->prepare("
            SELECT id FROM nobleaccount 
            WHERE id = ? AND lvl = 'supplier' 
            LIMIT 1
        ");
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Supplier not found');
        }
        $stmt->close();
    }
    
    // Check if updated_at column exists in order_items table
    $check_column = $conn->query("SHOW COLUMNS FROM order_items LIKE 'updated_at'");
    $has_updated_at = $check_column->num_rows > 0;
    
    // Update the order_items table with the supplier_id
    if ($has_updated_at) {
        $stmt = $conn->prepare("
            UPDATE order_items 
            SET supplier_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE order_items 
            SET supplier_id = ? 
            WHERE id = ?
        ");
    }
    
    $stmt->bind_param("ii", $supplier_id, $item_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update supplier assignment');
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected_rows === 0) {
        throw new Exception('No changes made - supplier may already be assigned');
    }
    
    // Get supplier name for response (if supplier was assigned)
    $supplier_name = null;
    if ($supplier_id) {
        $stmt = $conn->prepare("
            SELECT fullname FROM nobleaccount 
            WHERE id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $stmt->bind_result($supplier_name);
        $stmt->fetch();
        $stmt->close();
    }
    
    // Log the action (optional - you can add this to an audit table if needed)
    $action = $supplier_id ? "Assigned supplier ID $supplier_id ($supplier_name)" : "Removed supplier assignment";
    error_log("User $email $action to order item $item_id in order {$item['order_id']}");
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $supplier_id ? 'Supplier assigned successfully' : 'Supplier assignment removed',
        'data' => [
            'item_id' => $item_id,
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier_name,
            'order_id' => $item['order_id']
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log error
    error_log("Error updating supplier assignment: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>