<?php
// get_sticker_print_status.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['logistic']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['printed_at' => null]);
    exit();
}

$dispatcher_id = $_SESSION['noble_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    echo json_encode(['printed_at' => null]);
    exit();
}

$sql = "SELECT sticker_printed_at 
        FROM delivery_bookings 
        WHERE id = ? AND dispatcher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $dispatcher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row && $row['sticker_printed_at']) {
    echo json_encode([
        'printed_at'           => $row['sticker_printed_at'],
        'printed_at_formatted' => date('M d, Y h:i A', strtotime($row['sticker_printed_at']))
    ]);
} else {
    echo json_encode(['printed_at' => null]);
}