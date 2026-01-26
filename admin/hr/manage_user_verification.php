<?php
// manage_user_verification.php
session_name("nobleadmin");
session_start();

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../role/roleaccount.php';
require_role(['hr', 'superadmin']);

require_once '../../connection/connect.php';

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

$detail_id = (int)($data['detail_id'] ?? 0);
$action = $data['action'] ?? '';
$reason = $data['reason'] ?? null;

if (!$detail_id || !in_array($action, ['approve', 'reject', 'reset'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Get user information for notifications and file paths
    $user_query = "SELECT ud.user_id, ud.government_id_path, ud.id_type, u.name, u.email 
                   FROM user_details ud 
                   JOIN users u ON u.id = ud.user_id 
                   WHERE ud.detail_id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $detail_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user_info = $user_result->fetch_assoc();
    $user_id = (int)$user_info['user_id'];
    $user_name = $user_info['name'];
    $user_email = $user_info['email'];
    $government_id_path = $user_info['government_id_path'];
    $id_type = $user_info['id_type'];
    
    // Get admin information for notifications
    $admin_name = $_SESSION['noble_user']['fullname'] ?? 'Admin';
    $admin_id = $_SESSION['noble_user']['id'] ?? null;
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($action === 'approve') {
        // Update verification status to approved (1)
        $update_query = "UPDATE user_details SET is_verified = 1 WHERE detail_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $detail_id);
        $update_stmt->execute();
        
        // Create success notification for user
        $message = "Great news! Your account verification has been approved by our admin team. You now have full access to all platform features.";
        $notification_query = "INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) VALUES (?, ?, 'verification_approved', ?, 0, NOW())";
        $notification_stmt = $conn->prepare($notification_query);
        $notification_stmt->bind_param("iis", $user_id, $admin_id, $message);
        $notification_stmt->execute();
        
        $response_message = "User verification approved successfully. Notification sent to {$user_name}.";
        
    } elseif ($action === 'reject') {
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
            exit();
        }
        
        // Update verification status to rejected (-1)
        $update_query = "UPDATE user_details SET is_verified = -1 WHERE detail_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $detail_id);
        $update_stmt->execute();
        
        // Create rejection notification for user
        $message = "Unfortunately, your account verification has been rejected. Reason: {$reason}. Please review your information and uploaded documents, then resubmit your verification request.";
        $notification_query = "INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) VALUES (?, ?, 'verification_rejected', ?, 0, NOW())";
        $notification_stmt = $conn->prepare($notification_query);
        $notification_stmt->bind_param("iis", $user_id, $admin_id, $message);
        $notification_stmt->execute();
        
        $response_message = "User verification rejected. Notification with reason sent to {$user_name}.";
        
    } elseif ($action === 'reset') {
        // Variable to track file deletion status
        $file_deletion_message = '';
        
        // Delete government ID file if it exists
        if (!empty($government_id_path)) {
            $file_path = '../../uploads/government_ids/' . $government_id_path;
            
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $file_deletion_message = ' Government ID file has been removed from server.';
                    error_log("Admin Action: {$admin_name} deleted government ID file: {$government_id_path} for user {$user_name} (ID: {$user_id})");
                } else {
                    $file_deletion_message = ' Warning: Could not delete government ID file from server.';
                    error_log("Warning: Failed to delete government ID file: {$file_path} for user {$user_name} (ID: {$user_id})");
                }
            } else {
                $file_deletion_message = ' Government ID file was not found on server (may have been previously removed).';
                error_log("Note: Government ID file not found: {$file_path} for user {$user_name} (ID: {$user_id})");
            }
        }
        
        // Reset verification status to pending (0) and clear government ID data
        $update_query = "UPDATE user_details SET is_verified = 0, government_id_path = NULL, id_type = NULL WHERE detail_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $detail_id);
        $update_stmt->execute();
        
        // Create reset notification for user
        $message = " Your account verification status has been reset to pending. You will need to re-upload your government ID and complete the verification process again. Our admin team will review your new submission.";
        $notification_query = "INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) VALUES (?, ?, 'verification_reset', ?, 0, NOW())";
        $notification_stmt = $conn->prepare($notification_query);
        $notification_stmt->bind_param("iis", $user_id, $admin_id, $message);
        $notification_stmt->execute();
        
        $response_message = "User verification status reset to pending. All government ID data cleared.{$file_deletion_message} Notification sent to {$user_name}.";
    }
    
    // Check if update was successful
    if ($update_stmt->affected_rows === 0) {
        throw new Exception('No rows were updated. User may not exist or status may already be set.');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the action for admin audit trail
    if ($action === 'reset') {
        error_log("Admin Action: {$admin_name} reset user verification for {$user_name} (ID: {$user_id}) - cleared ID type: {$id_type}, file: {$government_id_path}");
    } else {
        error_log("Admin Action: {$admin_name} {$action}ed user verification for {$user_name} (ID: {$user_id})");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $response_message,
        'user_name' => $user_name,
        'action' => $action,
        'file_deleted' => $action === 'reset' && !empty($government_id_path) && strpos($file_deletion_message, 'removed') !== false
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in manage_user_verification.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} finally {
    // Close statements
    if (isset($user_stmt)) $user_stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    if (isset($notification_stmt)) $notification_stmt->close();
}
?>