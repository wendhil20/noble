<?php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../../connection/connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$account_id = isset($input['account_id']) ? (int)$input['account_id'] : 0;
$action = isset($input['action']) ? trim($input['action']) : '';

// Validate inputs
if ($account_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit();
}

if (!in_array($action, ['verify', 'activate', 'deactivate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Check if account exists
    $check_sql = "SELECT id, email, fullname, status, verified FROM nobleaccount WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $account_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception('Account not found');
    }
    
    $account = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    $success_message = '';
    $sql = '';
    $params = [];
    $param_types = '';
    
    switch ($action) {
        case 'verify':
            if ($account['verified'] == 1) {
                throw new Exception('Account is already verified');
            }
            $sql = "UPDATE nobleaccount SET verified = 1 WHERE id = ?";
            $params = [$account_id];
            $param_types = 'i';
            $success_message = 'Account verified successfully';
            break;
            
        case 'activate':
            if ($account['status'] === 'active') {
                throw new Exception('Account is already active');
            }
            $sql = "UPDATE nobleaccount SET status = 'active', failed_attempts = 0, locked_until = NULL WHERE id = ?";
            $params = [$account_id];
            $param_types = 'i';
            $success_message = 'Account activated successfully';
            break;
            
        case 'deactivate':
            if ($account['status'] === 'inactive') {
                throw new Exception('Account is already inactive');
            }
            $sql = "UPDATE nobleaccount SET status = 'inactive' WHERE id = ?";
            $params = [$account_id];
            $param_types = 'i';
            $success_message = 'Account deactivated successfully';
            break;
    }
    
    // Execute the update query
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update account: ' . mysqli_stmt_error($stmt));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected_rows === 0) {
        throw new Exception('No changes were made to the account');
    }
    
    // Log the action (optional - you can create an activity log table)
    $log_sql = "INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, created_at) 
                VALUES (?, ?, 'nobleaccount', ?, ?, NOW())";
    $log_stmt = mysqli_prepare($conn, $log_sql);
    
    if ($log_stmt) {
        $admin_user = $_SESSION['noble_user']['email'] ?? 'unknown';
        $log_action = "noble_account_" . $action;
        $log_details = json_encode([
            'account_email' => $account['email'],
            'account_name' => $account['fullname'],
            'previous_status' => $account['status'],
            'previous_verified' => $account['verified']
        ]);
        
        mysqli_stmt_bind_param($log_stmt, "ssis", $admin_user, $log_action, $account_id, $log_details);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => $success_message,
        'account_id' => $account_id,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    error_log("Noble Account Management Error: " . $e->getMessage() . " - Account ID: $account_id, Action: $action");
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
    
} finally {
    // Clean up
    if (isset($stmt) && $stmt) {
        mysqli_stmt_close($stmt);
    }
    if (isset($check_stmt) && $check_stmt) {
        mysqli_stmt_close($check_stmt);
    }
    if (isset($log_stmt) && $log_stmt) {
        mysqli_stmt_close($log_stmt);
    }
    
    mysqli_close($conn);
}
?>