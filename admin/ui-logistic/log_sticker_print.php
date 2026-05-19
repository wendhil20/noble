<?php
// log_sticker_print.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['logistic']);

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_subrole = $_SESSION['noble_subrole'] ?? '';
if ($user_subrole !== 'dispatcher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($input['booking_id']) ? intval($input['booking_id']) : 0;
$dispatcher_id = $_SESSION['noble_id'];

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Verify this booking belongs to this dispatcher
$checkSql = "SELECT id FROM delivery_bookings WHERE id = ? AND dispatcher_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $booking_id, $dispatcher_id);
$checkStmt->execute();
$exists = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or not assigned to you']);
    exit();
}

// Save print timestamp to DB
// Option A: If you have a sticker_printed_at column in delivery_bookings
$updateSql = "UPDATE delivery_bookings 
              SET sticker_printed_at = NOW(), 
                  sticker_printed_by = ?
              WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("ii", $dispatcher_id, $booking_id);
$success = $updateStmt->execute();
$updateStmt->close();

if ($success) {
    $printed_at = date('Y-m-d H:i:s');
    echo json_encode([
        'success' => true,
        'printed_at' => $printed_at,
        'message' => 'Print timestamp saved'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save print timestamp']);
}