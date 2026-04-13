<?php
include '../../connection/connect.php';

if (!isset($_GET['id'])) {
  die("Product ID not provided.");
}

$productId = intval($_GET['id']);

// Enhanced query with rating, sold count, and price range
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        v.origin,
        v.discount,
        v.percent,
        v.status,
        COALESCE(MIN(pv.price), 0) as min_size_price,  
        COALESCE(MAX(pv.price), 0) as max_size_price,  
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        COUNT(DISTINCT pc.id) as color_count,
        ROUND(AVG(r.rating), 1) AS avg_rating,
        COUNT(r.rating) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.id = ?
    GROUP BY p.id
");

$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Product not found.");
}

$product = $result->fetch_assoc();

// Calculate rating stars
$avg_rating = (float)($product['avg_rating'] ?? 0);
$rating_count = (int)($product['rating_count'] ?? 0);
$full = floor($avg_rating);
$half = ($avg_rating - $full >= 0.5) ? 1 : 0;
$empty = 5 - $full - $half;

// Calculate price range
$min_price = max($product['min_size_price'], $product['min_color_price']);
$max_price = max($product['max_size_price'], $product['max_color_price']);
if ($min_price == 0 && $max_price == 0) {
  $min_price = $product['price'];
  $max_price = $product['price'];
}

// Total sold
$total_sold = (int)($product['total_sold'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Product Details</title>
</head>

<body class="bg-gray-100 p-6">
  <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-lg">
    
    <!-- Product Header -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold text-orange-600 mb-2"><?= htmlspecialchars($product['product_name']) ?></h1>
      <p class="text-gray-600 text-sm">Category: <span class="font-semibold"><?= htmlspecialchars($product['codename']) ?></span></p>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
      
      <!-- Product Image -->
      <div class="shrink-0 w-full lg:w-80">
        <?php if (!empty($product['main_image'])): ?>
          <img src="../../<?= ($product['main_image']) ?>" class="w-full h-auto object-cover rounded-lg border border-gray-200 shadow-md">
        <?php else: ?>
          <div class="w-full aspect-square bg-gray-200 flex items-center justify-center text-gray-500 rounded-lg">
            <i class="fas fa-image text-5xl"></i>
          </div>
        <?php endif; ?>
      </div>

      <!-- Product Details -->
      <div class="flex-1">
        
        <!-- Rating Section -->
        <div class="mb-6 pb-6 border-b border-gray-200">
          <div class="flex items-center gap-3">
            <div class="flex text-yellow-400 text-lg">
              <?php for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>'; ?>
              <?php if ($half) echo '<i class="fas fa-star-half-alt"></i>'; ?>
              <?php for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>'; ?>
            </div>
            <span class="font-bold text-gray-900"><?= number_format($avg_rating, 1) ?></span>
            <span class="text-gray-500">(<?= $rating_count ?> <?= $rating_count == 1 ? 'rating' : 'ratings' ?>)</span>
          </div>
        </div>

        <!-- Price Section -->
        <div class="mb-6 pb-6 border-b border-gray-200">
          <p class="text-gray-600 text-sm mb-2">Price Range:</p>
          <?php if ($min_price != $max_price): ?>
            <p class="text-3xl font-bold text-gray-900">₱<?= number_format($min_price, 2) ?> - ₱<?= number_format($max_price, 2) ?></p>
          <?php else: ?>
            <p class="text-3xl font-bold text-gray-900">₱<?= number_format($min_price, 2) ?></p>
          <?php endif; ?>
          
          <?php if ($product['discount'] > 0): ?>
            <div class="mt-2 flex items-center gap-2">
              <span class="inline-block bg-red-100 text-red-700 px-3 py-1 rounded font-semibold">
                -<?= $product['discount'] ?>% OFF
              </span>
              <span class="text-gray-500 text-sm">Discount applied</span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Stock & Sales Info -->
        <div class="mb-6 pb-6 border-b border-gray-200 grid grid-cols-3 gap-4">
          <div>
            <p class="text-gray-600 text-sm">Quantity in Stock</p>
            <p class="text-2xl font-bold text-gray-900"><?= $product['quantity'] ?></p>
          </div>
          <div>
            <p class="text-gray-600 text-sm">Total Sold</p>
            <p class="text-2xl font-bold text-green-600"><?= number_format($total_sold) ?></p>
          </div>
          <div>
            <p class="text-gray-600 text-sm">Views</p>
            <p class="text-2xl font-bold text-blue-600"><?= number_format($product['view_count']) ?></p>
          </div>
        </div>

        <!-- Description Section -->
        <div class="mb-6 pb-6 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-900 mb-3">Description</h2>
          <div class="text-gray-700 leading-relaxed">
            <?php if (!empty($product['description'])): ?>
              <?= nl2br(htmlspecialchars($product['description'])) ?>
            <?php else: ?>
              <p class="text-gray-500 italic">No description available</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Additional Info -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-gray-600 text-sm font-semibold">Unit</p>
            <p class="text-gray-900"><?= !empty($product['unit']) ? htmlspecialchars($product['unit']) : 'N/A' ?></p>
          </div>
          <div>
            <p class="text-gray-600 text-sm font-semibold">Specification</p>
            <p class="text-gray-900"><?= !empty($product['specification']) ? htmlspecialchars($product['specification']) : 'N/A' ?></p>
          </div>
          <?php if (!empty($product['descrip6'])): ?>
            <div>
              <p class="text-gray-600 text-sm font-semibold">Material</p>
              <p class="text-gray-900"><?= htmlspecialchars($product['descrip6']) ?></p>
            </div>
          <?php endif; ?>
          <?php if (!empty($product['descrip7'])): ?>
            <div>
              <p class="text-gray-600 text-sm font-semibold">Finish</p>
              <p class="text-gray-900"><?= htmlspecialchars($product['descrip7']) ?></p>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </div>

  </div>
</body>

</html>