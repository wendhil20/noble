<?php
include '../../connection/connect.php';

if (!isset($_GET['id'])) {
    die("Product ID not provided.");
}

$productId = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Product not found.");
}

$product = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Product Details</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-3xl font-bold text-orange-600 mb-4"><?= htmlspecialchars($product['product_name']) ?></h1>

    <div class="flex flex-col md:flex-row gap-6">
      <div class="flex-shrink-0">
        <?php if (!empty($product['main_image'])): ?>
          <img src="../../<?=($product['main_image']) ?>" class="w-64 h-64 object-cover rounded border">
        <?php else: ?>
          <div class="w-64 h-64 bg-gray-200 flex items-center justify-center text-gray-500">No Image</div>
        <?php endif; ?>
      </div>
      <div>
        <p><strong>Codename:</strong> <?= htmlspecialchars($product['codename']) ?></p>
        <p><strong>Quantity:</strong> <?= $product['quantity'] ?></p>
        <p><strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?></p>
        <p><strong>Description:</strong></p>
        <div class="mt-2 text-gray-700"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
