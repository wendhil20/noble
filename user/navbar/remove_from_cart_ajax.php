<?php
session_start();
require_once '../../connection/connect.php'; // Adjust path as needed

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not logged in'
        ]);
        exit;
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['item_id']) || !is_numeric($input['item_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid item ID'
        ]);
        exit;
    }

    $item_id = intval($input['item_id']);

    // Start transaction
    $conn->autocommit(false);

    try {
        // Verify the item belongs to the current user before deleting
        $verify_stmt = $conn->prepare("SELECT id FROM user_cart_items WHERE id = ? AND user_id = ?");
        $verify_stmt->bind_param("ii", $item_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            $conn->rollback();
            
            echo json_encode([
                'success' => false,
                'message' => 'Item not found or access denied'
            ]);
            exit;
        }
        $verify_stmt->close();

        // Delete the item
        $delete_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $item_id, $user_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to delete item");
        }
        $delete_stmt->close();

        // Get updated cart count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_quantity FROM user_cart_items WHERE user_id = ?");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $total_cart_items = $count_data['total_quantity'];
        $count_stmt->close();

        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);

        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully',
            'total_items' => $total_cart_items
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->autocommit(true);
        throw $e;
    }

} catch (Exception $e) {
    // Log error (in production, don't expose detailed error messages)
    error_log("Cart item removal error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error removing item from cart'
    ]);
}
?>