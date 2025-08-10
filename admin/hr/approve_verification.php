<?php
// approve_verification.php
session_start();
header('Content-Type: application/json');



require_once '../../connection/connect.php'; // $conn (mysqli)

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$detail_id = isset($input['detail_id']) ? (int)$input['detail_id'] : 0;

if ($detail_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid detail_id']);
    exit;
}

// Update verification status
$sql = "UPDATE user_details SET is_verified = 1 WHERE detail_id = $detail_id";
if (mysqli_query($conn, $sql)) {
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true, 'message' => 'User verification approved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No record updated (maybe already verified)']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
