<?php
include '../../connection/connect.php';

// Set charset to UTF-8 para di magka-problem sa special characters
$conn->set_charset("utf8");

$search = trim($_GET['search'] ?? '');

// Allow kahit 1 character lang para tumugma agad
if (strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

// Prepare statement para safe sa SQL injection
$stmt = $conn->prepare("SELECT id, product_name, main_image FROM products WHERE product_name LIKE ? LIMIT 10");

$param = "%" . $search . "%";
$stmt->bind_param("s", $param);

if (!$stmt->execute()) {
    // Error sa query execution, return empty array or message
    echo json_encode([]);
    exit;
}

$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    // Optional: adjust image path here
    if (!empty($row['main_image'])) {
        // Check if image path is already a full URL
        if (filter_var($row['main_image'], FILTER_VALIDATE_URL)) {
            $imgPath = $row['main_image'];
        } else {
            // Relative path: adjust base path as needed
            $imgPath = '../../' . ltrim($row['main_image'], '/');
        }
    } else {
        $imgPath = ''; // or default placeholder image path
    }
    $row['main_image'] = $imgPath;

    $results[] = $row;
}

echo json_encode($results);
