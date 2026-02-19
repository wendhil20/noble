<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_name("nobleadmin");
session_start();

header('Content-Type: application/json');

try {
    // Check if connection file exists
    if (!file_exists('../../connection/connect.php')) {
        throw new Exception('Connection file not found');
    }
    
    include '../../connection/connect.php';

    // Check if connection exists
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection error: ' . ($conn->connect_error ?? 'Unknown'));
    }

    $action = $_GET['action'] ?? '';

    // Get notification count for pending requests
    if ($action === 'get-notification-count') {
        $countQuery = "SELECT COUNT(*) as pending_count FROM custom_quote_requests WHERE status = 'pending'";
        $countResult = $conn->query($countQuery);
        
        if (!$countResult) {
            throw new Exception('Count query failed: ' . $conn->error);
        }
        
        $countRow = $countResult->fetch_assoc();
        echo json_encode([
            'success' => true,
            'pending_count' => intval($countRow['pending_count'])
        ]);
        exit;
    }

    // Get specific request by ID
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('Invalid request ID');
    }

    $query = "
        SELECT 
            ccr.id, 
            ccr.product_id, 
            ccr.full_name, 
            ccr.email, 
            ccr.phone, 
            ccr.custom_type, 
            ccr.specifications, 
            ccr.message, 
            ccr.selected_color, 
            ccr.status, 
            ccr.created_at,
            p.product_name
        FROM custom_quote_requests ccr
        LEFT JOIN products p ON ccr.product_id = p.id
        WHERE ccr.id = ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Get result failed: ' . $conn->error);
    }

    $request = $result->fetch_assoc();
    $stmt->close();

    if (!$request) {
        throw new Exception('Request not found with ID: ' . $id);
    }

    echo json_encode($request);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
?>