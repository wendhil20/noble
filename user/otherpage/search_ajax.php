<?php
include '../../connection/connect.php';

$search = trim($_GET['search'] ?? '');

if (strlen($search) < 2) {
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("SELECT id, product_name, main_image FROM products WHERE product_name LIKE ? LIMIT 10");
$param = "%" . $search . "%";
$stmt->bind_param("s", $param);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
  // Optionally add base path to the image if needed
  $row['main_image'] = '../../' . $row['main_image'];  // <-- adjust path if needed
  $results[] = $row;
}

echo json_encode($results);
?>
