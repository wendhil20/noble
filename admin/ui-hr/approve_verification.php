<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

header('Content-Type: application/json');

try {

    // Read and validate JSON input
    $input_raw = file_get_contents('php://input');
    if (empty($input_raw)) {
        throw new Exception('Empty request body');
    }

    $input = json_decode($input_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    $detail_id = isset($input['detail_id']) ? (int)$input['detail_id'] : 0;

    if ($detail_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid detail_id']);
        exit;
    }

    // Start transaction
    mysqli_autocommit($conn, false);

    // 1. Get user_id from user_details using prepared statement
    $stmt_user = $conn->prepare("SELECT user_id FROM user_details WHERE detail_id = ?");
    if (!$stmt_user) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_user->bind_param("i", $detail_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows == 0) {
        $stmt_user->close();
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'User not found for this detail_id']);
        exit;
    }
    
    $user_row = $result_user->fetch_assoc();
    $user_id = (int)$user_row['user_id'];
    $stmt_user->close();

    // 2. Update verification status using prepared statement
    $stmt_update = $conn->prepare("UPDATE user_details SET is_verified = 1 WHERE detail_id = ? AND is_verified = 0");
    if (!$stmt_update) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_update->bind_param("i", $detail_id);
    $stmt_update->execute();
    
    if ($stmt_update->affected_rows == 0) {
        $stmt_update->close();
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'No record updated (maybe already verified)']);
        exit;
    }
    $stmt_update->close();

    // 3. Insert notification
    $admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $type = "verification";
    $message = "Your account has been verified successfully!";

    $stmt_notif = $conn->prepare("
        INSERT INTO notifications (user_id, actor_id, type, message, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt_notif) {
        throw new Exception('Prepare notification failed: ' . $conn->error);
    }
    
    $stmt_notif->bind_param("iiss", $user_id, $admin_id, $type, $message);
    
    if (!$stmt_notif->execute()) {
        throw new Exception('Notification insert failed: ' . $stmt_notif->error);
    }
    
    $stmt_notif->close();

    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'User verification approved and notification sent'
    ]);

} catch (Exception $e) {
    // Rollback on any error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    // Log the error
    error_log("Approval Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred. Please try again.'
    ]);
} finally {
    // Close connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>