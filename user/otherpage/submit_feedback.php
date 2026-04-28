<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? null;
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$delivery_rating = isset($_POST['delivery_rating']) ? intval($_POST['delivery_rating']) : null;
$product_quality_rating = isset($_POST['product_quality_rating']) ? intval($_POST['product_quality_rating']) : null;
$feedback_text = isset($_POST['feedback_text']) ? trim($_POST['feedback_text']) : null;

// Validate inputs
if (!$order_id || !$rating || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Verify order belongs to user
$stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND email = ?");
$stmt->bind_param("is", $order_id, $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if feedback already exists
$stmt = $conn->prepare("SELECT id FROM order_feedback WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$existing = $stmt->get_result();

if ($existing->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Feedback already submitted']);
    $stmt->close();
    exit;
}
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Insert feedback
    $stmt = $conn->prepare("
        INSERT INTO order_feedback 
        (order_id, user_id, email, rating, delivery_rating, product_quality_rating, feedback_text) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iisiiis", 
        $order_id, 
        $user_id, 
        $user_email, 
        $rating, 
        $delivery_rating, 
        $product_quality_rating, 
        $feedback_text
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert feedback');
    }
    $stmt->close();
    
    // Update order status to completed
    $stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update order status');
    }
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Feedback submitted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to submit feedback: ' . $e->getMessage()
    ]);
}
?>