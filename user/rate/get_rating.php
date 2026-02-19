<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id) {
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM product_ratings WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['avg_rating' => round($result['avg_rating'] ?? 0, 1)]);
    $stmt->close();
} else {
    echo json_encode(['avg_rating' => 0]);
}

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['product_ratings'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}