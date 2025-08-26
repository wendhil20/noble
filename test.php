<?php
// products.php
header('Content-Type: application/json');
include 'connection/connect.php';

$sql = "SELECT id, product_name, description, descrip6, descrip7 FROM products";
$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);

?>