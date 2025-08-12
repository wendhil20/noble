<?php
// Clean output buffer and prevent any unwanted output
ob_start();
session_name("nobleadmin");
session_start();

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');

// Suppress all PHP errors/warnings from being displayed
error_reporting(0);
ini_set('display_errors', 0);

require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Clean any output buffer before sending response
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../../connection/connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$account_id = isset($input['account_id']) ? (int)$input['account_id'] : 0;
$action = isset($input['action']) ? trim($input['action']) : '';

// Validate inputs
if ($account_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit();
}

if (!in_array($action, ['verify', 'activate', 'deactivate'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    // Check if account exists
    $check_sql = "SELECT id, email, fullname, status, verified FROM nobleaccount WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    
    if (!$check_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($check_stmt, "i", $account_id);
    
    if (!mysqli_stmt_execute($check_stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($check_stmt));
    }
    
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        mysqli_stmt_close($check_stmt);
        throw new Exception('Account not found');
    }
    
    $account = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    $success_message = '';
    $sql = '';
    
    switch ($action) {
        case 'verify':
            if ($account['verified'] == 1) {
                throw new Exception('Account is already verified');
            }
            $sql = "UPDATE nobleaccount SET verified = 1 WHERE id = ?";
            $success_message = 'Account verified successfully';
            break;
            
        case 'activate':
            if ($account['status'] === 'active') {
                throw new Exception('Account is already active');
            }
            // Check if the columns exist before updating them
            $sql = "UPDATE nobleaccount SET status = 'active' WHERE id = ?";
            // Add failed_attempts and locked_until only if columns exist
            $column_check = mysqli_query($conn, "SHOW COLUMNS FROM nobleaccount LIKE 'failed_attempts'");
            if (mysqli_num_rows($column_check) > 0) {
                $sql = "UPDATE nobleaccount SET status = 'active', failed_attempts = 0, locked_until = NULL WHERE id = ?";
            }
            $success_message = 'Account activated successfully';
            break;
            
        case 'deactivate':
            if ($account['status'] === 'inactive') {
                throw new Exception('Account is already inactive');
            }
            $sql = "UPDATE nobleaccount SET status = 'inactive' WHERE id = ?";
            $success_message = 'Account deactivated successfully';
            break;
    }
    
    // Execute the update query
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $account_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to update account: ' . mysqli_stmt_error($stmt));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected_rows === 0) {
        throw new Exception('No changes were made to the account');
    }
    
    // Optional: Log the action (only if table exists)
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_activity_log'");
    if (mysqli_num_rows($table_check) > 0) {
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
    }
    
    // Clean output buffer before sending response
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => $success_message,
        'account_id' => $account_id,
        'action' => $action
    ]);
    exit();
    
} catch (Exception $e) {
    // Log error (write to file instead of error_log to avoid output)
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Noble Account Management Error: " . $e->getMessage() . " - Account ID: $account_id, Action: $action\n", FILE_APPEND);
    
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
    exit();
    
} finally {
    // Clean up any remaining statements
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        mysqli_stmt_close($stmt);
    }
    if (isset($check_stmt) && $check_stmt instanceof mysqli_stmt) {
        mysqli_stmt_close($check_stmt);
    }
    if (isset($log_stmt) && $log_stmt instanceof mysqli_stmt) {
        mysqli_stmt_close($log_stmt);
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_close($conn);
    }
}
?>