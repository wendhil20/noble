<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

// ⚠️ Require login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
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

$user_id = $_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($product_id && $rating >= 1 && $rating <= 5) {
    // 🔄 Insert or update (1 user = 1 rating per product)
    $stmt = $conn->prepare("INSERT INTO product_ratings (product_id, user_id, rating)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE rating = ?");
    $stmt->bind_param("iiii", $product_id, $user_id, $rating, $rating);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
}
