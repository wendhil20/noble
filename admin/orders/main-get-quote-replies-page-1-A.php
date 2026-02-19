<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_name("nobleadmin");
session_start();

header('Content-Type: application/json');

try {
    $request_id = intval($_GET['request_id'] ?? 0);
    
    if (!$request_id) {
        throw new Exception('Invalid request ID');
    }
    
    if (!file_exists('../../connection/connect.php')) {
        throw new Exception('Connection file not found');
    }
    
    include '../../connection/connect.php';
    
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection error');
    }
    
    // Get all replies for this request
    $query = "
        SELECT 
            cqr.id,
            cqr.request_id,
            cqr.admin_id,
            cqr.message,
            cqr.created_at,
            u.full_name as admin_name
        FROM custom_quote_replies cqr
        LEFT JOIN users u ON cqr.admin_id = u.id
        WHERE cqr.request_id = ?
        ORDER BY cqr.created_at ASC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $request_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $replies = [];
    
    while ($row = $result->fetch_assoc()) {
        $replies[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'replies' => $replies
    ]);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
?>