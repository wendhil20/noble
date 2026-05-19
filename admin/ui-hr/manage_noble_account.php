<?php
// manage_noble_account.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_role(['hr', 'superadmin']);

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

$account_id = (int)($data['account_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$account_id || !in_array($action, ['verify', 'activate', 'deactivate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Get account information
    $account_query = "SELECT * FROM nobleaccount WHERE id = ?";
    $account_stmt = $conn->prepare($account_query);
    $account_stmt->bind_param("i", $account_id);
    $account_stmt->execute();
    $account_result = $account_stmt->get_result();
    
    if ($account_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit();
    }
    
    $account_info = $account_result->fetch_assoc();
    $account_name = $account_info['fullname'];
    $account_email = $account_info['email'];
    
    // Get admin information
    $admin_name = $_SESSION['noble_user']['fullname'] ?? 'Admin';
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($action === 'verify') {
        // Update verification status
        $update_query = "UPDATE nobleaccount SET verified = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $account_id);
        $update_stmt->execute();
        
        $response_message = "Noble account for {$account_name} has been verified successfully.";
        
    } elseif ($action === 'activate') {
        // Update account status to active
        $update_query = "UPDATE nobleaccount SET status = 'active' WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $account_id);
        $update_stmt->execute();
        
        $response_message = "Noble account for {$account_name} has been activated successfully.";
        
    } elseif ($action === 'deactivate') {
        // Update account status to inactive
        $update_query = "UPDATE nobleaccount SET status = 'inactive' WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $account_id);
        $update_stmt->execute();
        
        $response_message = "Noble account for {$account_name} has been deactivated.";
    }
    
    // Check if update was successful
    if ($update_stmt->affected_rows === 0) {
        throw new Exception('No rows were updated. Account may not exist or status may already be set.');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the action for admin audit trail
    error_log("Admin Action: {$admin_name} {$action}d noble account for {$account_name} (ID: {$account_id})");
    
    echo json_encode([
        'success' => true, 
        'message' => $response_message,
        'account_name' => $account_name,
        'action' => $action
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in manage_noble_account.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} finally {
    // Close statements
    if (isset($account_stmt)) $account_stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
}
?>