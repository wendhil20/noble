<?php
include '../connection/connect.php';

$search = trim($_GET['search'] ?? '');

if (strlen($search) < 2) {
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("SELECT id, product_name FROM products WHERE product_name LIKE ? LIMIT 10");
$param = "%" . $search . "%";
$stmt->bind_param("s", $param);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
  $results[] = $row;
}

echo json_encode($results);
?>
