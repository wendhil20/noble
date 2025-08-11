<?php
// approve_verification.php
session_start();
header('Content-Type: application/json');

require_once '../../connection/connect.php'; // $conn (mysqli)

$tables = ['notification'];

foreach ($tables as $table) {
    // Get the current highest ID that exists
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    // Reset AUTO_INCREMENT to max_id + 1
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$detail_id = isset($input['detail_id']) ? (int)$input['detail_id'] : 0;

if ($detail_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid detail_id']);
    exit;
}

// 1 Kunin muna ang `user_id` mula sa `user_details`
$sql_user = "SELECT user_id FROM user_details WHERE detail_id = $detail_id";
$res_user = mysqli_query($conn, $sql_user);
if (!$res_user || mysqli_num_rows($res_user) == 0) {
    echo json_encode(['success' => false, 'message' => 'User not found for this detail_id']);
    exit;
}
$user_row = mysqli_fetch_assoc($res_user);
$user_id = (int)$user_row['user_id'];

// 2 Update verification status
$sql = "UPDATE user_details SET is_verified = 1 WHERE detail_id = $detail_id";
if (mysqli_query($conn, $sql)) {
    if (mysqli_affected_rows($conn) > 0) {

        // 3 Insert notification sa notifications table
        $admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; // actor_id = admin na nag-approve
        $type = "verification";
        $message = "Your account has been verified successfully!";

        $stmt_notif = $conn->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_notif->bind_param("iiss", $user_id, $admin_id, $type, $message);
        $stmt_notif->execute();
        $stmt_notif->close();

        echo json_encode(['success' => true, 'message' => 'User verification approved and notification sent']);

    } else {
        echo json_encode(['success' => false, 'message' => 'No record updated (maybe already verified)']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
