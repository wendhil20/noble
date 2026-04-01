<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$updates = $input['updates'] ?? [];

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

$stmt = $conn->prepare("UPDATE products SET min_order_qty = ? WHERE id = ?");
$errors = 0;

foreach ($updates as $u) {
    $id  = (int)($u['id'] ?? 0);
    $qty = max(1, (int)($u['min_order_qty'] ?? 1));

    if ($id <= 0) { $errors++; continue; }

    $stmt->bind_param("ii", $qty, $id);
    if (!$stmt->execute()) $errors++;
}

$stmt->close();

if ($errors === 0) {
    echo json_encode(['success' => true, 'updated' => count($updates)]);
} else {
    echo json_encode(['success' => false, 'message' => "Some updates failed ($errors errors)."]);
}