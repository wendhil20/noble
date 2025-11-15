<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Allow both authenticated users and guests
$is_guest = !isset($_SESSION['user_id']);

// Get category ID from request
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($category_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit;
}

// Fetch subcategories for the selected category with all fields including image_path
$stmt = $conn->prepare("SELECT id, category_id, subcategory_name, subcategory_slug, image_path FROM product_subcategories WHERE category_id = ? ORDER BY subcategory_name ASC");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = [
        'id' => $row['id'],
        'category_id' => $row['category_id'],
        'subcategory_name' => $row['subcategory_name'],
        'subcategory_slug' => $row['subcategory_slug'],
        'image_path' => $row['image_path']
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'subcategories' => $subcategories,
    'count' => count($subcategories),
    'is_guest' => $is_guest // Optional: para malaman ng frontend kung guest mode
]);
?>