<?php
include '../../connection/connect.php';

if (!isset($_GET['id'])) {
    die("No product ID provided.");
}

$productId = intval($_GET['id']);

$stmt = $conn->prepare("SELECT qr_code, codename FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Product not found.");
}

$product = $result->fetch_assoc();
$qrData = $product['qr_code'];
$filename = 'qr_' . preg_replace('/[^a-zA-Z0-9]/', '_', $product['codename']) . '.png';

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $qrData;
exit;
?>
