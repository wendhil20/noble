<?php
// manage-size-color-stock.php (FIXED - Per Size + Color Stock)

session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId == 0) {
  header("Location: main-adminshop-page-1");
  exit();
}

// Fetch product details
$productQuery = $conn->prepare("SELECT id, product_name, codename FROM products WHERE id = ?");
$productQuery->bind_param("i", $productId);
$productQuery->execute();
$productResult = $productQuery->get_result();
$product = $productResult->fetch_assoc();

if (!$product) {
  header("Location: main-adminshop-page-1");
  exit();
}
$productQuery->close();

// ============================================================
// HANDLE STOCK UPDATE (Per Size + Color)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
  $variantId = (int)$_POST['variant_id'];
  $newStock = (int)$_POST['new_stock'];
  
  $updateStmt = $conn->prepare("UPDATE product_variants SET stock = ? WHERE id = ? AND product_id = ?");
  $updateStmt->bind_param("iii", $newStock, $variantId, $productId);
  
  if ($updateStmt->execute()) {
    $_SESSION['success_msg'] = "Stock updated successfully!";
  } else {
    $_SESSION['error_msg'] = "Error updating stock!";
  }
  $updateStmt->close();
  
  header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $productId);
  exit();
}

// ============================================================
// FETCH STOCK DATA: Size + Color + Stock from product_variants
// ============================================================

$stockQuery = $conn->prepare("
  SELECT 
    id as variant_id,
    size,
    color,
    stock
  FROM product_variants
  WHERE product_id = ?
  ORDER BY size ASC, color ASC
");
$stockQuery->bind_param("i", $productId);
$stockQuery->execute();
$stockResult = $stockQuery->get_result();

// Organize by size -> colors
$sizeColorGroups = [];
$totalStock = 0;

while ($row = $stockResult->fetch_assoc()) {
  $size = $row['size'] ?? 'Unknown Size';
  $color = $row['color'] ?? 'Unknown Color';
  $stock = (int)$row['stock'];
  
  $totalStock += $stock;
  
  if (!isset($sizeColorGroups[$size])) {
    $sizeColorGroups[$size] = [];
  }
  
  $sizeColorGroups[$size][] = [
    'variant_id' => $row['variant_id'],
    'color' => $color,
    'stock' => $stock
  ];
}

$stockResult->free();
$stockQuery->close();

// Convert to JSON for JavaScript
$sizeColorJSON = json_encode($sizeColorGroups);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Stock - <?= htmlspecialchars($product['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .size-section {
      animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
      <a href="main-adminupdateshop-page-2" class="inline-flex items-center gap-2 text-orange-600 hover:text-orange-700 mb-4">
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
      </a>
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
          <i class="fas fa-box-open text-orange-600"></i>
          Manage Size & Color Stock
        </h1>
        <p class="text-gray-600">Product: <span class="font-semibold text-gray-900"><?= htmlspecialchars($product['product_name']) ?></span></p>
        <p class="text-gray-600">Code: <span class="font-semibold text-gray-900"><?= htmlspecialchars($product['codename']) ?></span></p>
        <div class="mt-4 pt-4 border-t border-gray-200">
          <p class="text-sm text-gray-700">
            <i class="fas fa-cubes text-orange-600 mr-2"></i>
            Total Stock (All Variants): <span class="text-2xl font-bold text-orange-600"><?= $totalStock ?></span> units
          </p>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_msg'])): ?>
      <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg flex items-center gap-3">
        <i class="fas fa-check-circle"></i>
        <?= $_SESSION['success_msg'] ?>
      </div>
      <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
      <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg flex items-center gap-3">
        <i class="fas fa-exclamation-circle"></i>
        <?= $_SESSION['error_msg'] ?>
      </div>
      <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- STOCK DISPLAY ORGANIZED BY SIZE & COLOR -->
    <div class="space-y-6">
      <?php if (count($sizeColorGroups) > 0): ?>
        <?php foreach ($sizeColorGroups as $size => $colors): ?>
          <div class="size-section bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Size Header -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4">
              <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-tag"></i>
                <?= htmlspecialchars($size) ?>
                <span class="ml-auto bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                  <?= count($colors) ?> colors
                </span>
              </h3>
            </div>

            <!-- Colors Grid -->
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($colors as $item): ?>
                  <div class="bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <!-- Color Name -->
                    <div class="mb-3">
                      <h4 class="text-lg font-semibold text-gray-900 capitalize">
                        <?= htmlspecialchars($item['color']) ?>
                      </h4>
                      <p class="text-xs text-gray-500">Variant ID: <?= $item['variant_id'] ?></p>
                    </div>

                    <!-- Stock Display & Edit -->
                    <div class="flex items-end gap-3">
                      <div class="flex-1">
                        <p class="text-xs text-gray-600 mb-1">Current Stock</p>
                        <div class="text-3xl font-bold text-orange-600">
                          <?= (int)$item['stock'] ?>
                        </div>
                      </div>
                      <button type="button" 
                              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2"
                              onclick="openStockModal(<?= $item['variant_id'] ?>, '<?= htmlspecialchars($size) ?>', '<?= htmlspecialchars($item['color']) ?>', <?= $item['stock'] ?>)">
                        <i class="fas fa-edit"></i>
                        Edit
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <i class="fas fa-inbox text-gray-300 text-5xl mb-4 block"></i>
          <p class="text-gray-600 text-lg">No size & color stock data found</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- JSON Debug -->
    <div class="mt-8 p-4 bg-gray-900 text-gray-100 rounded-lg font-mono text-xs overflow-x-auto">
      <p class="text-gray-400 mb-2">Stock Data Structure (JSON):</p>
      <pre><?= htmlspecialchars(json_encode(json_decode($sizeColorJSON, true), JSON_PRETTY_PRINT)) ?></pre>
    </div>
  </div>

  <!-- MODAL FOR EDITING STOCK -->
  <div id="stockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-900">Update Stock</h3>
        <button type="button" onclick="closeStockModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form method="POST" id="stockForm" class="space-y-4">
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
          <p class="text-sm text-gray-700 mb-2">
            <strong>Color:</strong> <span id="modalColorName" class="text-orange-700 font-semibold capitalize"></span>
          </p>
          <p class="text-xs text-gray-600 mt-3">
            <i class="fas fa-info-circle mr-1"></i>
            Size: <span id="modalSizeName" class="font-semibold"></span>
          </p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">New Stock Quantity</label>
          <input type="number" id="modalStock" name="new_stock" min="0" required
                 class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent text-lg font-semibold" />
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
          <p class="text-xs text-blue-700">
            <i class="fas fa-info-circle mr-1"></i>
            This updates stock ONLY for this size & color combination
          </p>
        </div>
        
        <input type="hidden" id="modalVariantId" name="variant_id">
        
        <div class="flex gap-3 mt-6 pt-4 border-t border-gray-200">
          <button type="button" onclick="closeStockModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
            Cancel
          </button>
          <button type="submit" name="update_stock" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2">
            <i class="fas fa-save"></i>
            Save Stock
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const stockData = <?= $sizeColorJSON ?>;

    function openStockModal(variantId, sizeName, colorName, currentStock) {
      document.getElementById('modalVariantId').value = variantId;
      document.getElementById('modalSizeName').textContent = sizeName;
      document.getElementById('modalColorName').textContent = colorName;
      document.getElementById('modalStock').value = currentStock;
      document.getElementById('stockModal').classList.remove('hidden');
      setTimeout(() => document.getElementById('modalStock').focus(), 100);
    }

    function closeStockModal() {
      document.getElementById('stockModal').classList.add('hidden');
    }

    document.getElementById('stockModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeStockModal();
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeStockModal();
      }
    });

    console.log('Stock Structure:', stockData);
  </script>

</body>

</html>