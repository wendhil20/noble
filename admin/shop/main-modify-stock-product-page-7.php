<?php
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

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId == 0) {
  header("Location: main-adminshop-page-1");
  exit();
}

// Fetch product details
$productQuery = $conn->prepare("SELECT id, product_name, codename, quantity FROM products WHERE id = ?");
$productQuery->bind_param("i", $productId);
$productQuery->execute();
$productResult = $productQuery->get_result();
$product = $productResult->fetch_assoc();

if (!$product) {
  header("Location: main-adminshop-page-1");
  exit();
}
$productQuery->close();

// Handle stock update for colors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_color_stock'])) {
  $colorId = (int)$_POST['color_id'];
  $newStock = (int)$_POST['stock'];
  
  $updateColor = $conn->prepare("UPDATE product_colors SET stock = ? WHERE id = ? AND product_id = ?");
  $updateColor->bind_param("iii", $newStock, $colorId, $productId);
  if ($updateColor->execute()) {
    $_SESSION['success_msg'] = "Color stock updated successfully!";
  } else {
    $_SESSION['error_msg'] = "Error updating color stock!";
  }
  $updateColor->close();
  header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $productId);
  exit();
}

// Handle stock update for variants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variant_stock'])) {
  $variantId = (int)$_POST['variant_id'];
  $newStock = (int)$_POST['stock'];
  
  $updateVariant = $conn->prepare("UPDATE product_variants SET stock = ? WHERE id = ?");
  $updateVariant->bind_param("ii", $newStock, $variantId);
  if ($updateVariant->execute()) {
    $_SESSION['success_msg'] = "Variant stock updated successfully!";
  } else {
    $_SESSION['error_msg'] = "Error updating variant stock!";
  }
  $updateVariant->close();
  header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $productId);
  exit();
}

// Fetch colors for this product
$colorsQuery = $conn->prepare("SELECT id, color_name, image, stock FROM product_colors WHERE product_id = ? ORDER BY color_name");
$colorsQuery->bind_param("i", $productId);
$colorsQuery->execute();
$colorsResult = $colorsQuery->get_result();
$colors = [];
while ($row = $colorsResult->fetch_assoc()) {
  $colors[] = $row;
}
$colorsQuery->close();

// Fetch types (sizes)
$typesQuery = $conn->prepare("SELECT id, type_name FROM product_types WHERE product_id = ? ORDER BY type_name");
$typesQuery->bind_param("i", $productId);
$typesQuery->execute();
$typesResult = $typesQuery->get_result();
$types = [];
while ($row = $typesResult->fetch_assoc()) {
  $types[] = $row;
}
$typesQuery->close();

// Fetch variants (combinations of color + size)
$variantsQuery = $conn->prepare("
  SELECT pv.id, pv.type_id, pv.color, pv.size, pv.stock, pv.image
  FROM product_variants pv
  WHERE pv.product_id = ?
  ORDER BY pv.color, pv.size
");
$variantsQuery->bind_param("i", $productId);
$variantsQuery->execute();
$variantsResult = $variantsQuery->get_result();
$variants = [];
while ($row = $variantsResult->fetch_assoc()) {
  $variants[] = $row;
}
$variantsQuery->close();

// Calculate total stock
$totalStock = 0;
foreach ($variants as $variant) {
  $totalStock += $variant['stock'];
}

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
    .tab-button {
      transition: all 0.3s ease;
    }
    .tab-button.active {
      border-bottom: 3px solid #ea580c;
      color: #ea580c;
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
          Manage Stock
        </h1>
        <p class="text-gray-600">Product: <span class="font-semibold text-gray-900"><?= htmlspecialchars($product['product_name']) ?></span></p>
        <p class="text-gray-600">Code: <span class="font-semibold text-gray-900"><?= htmlspecialchars($product['codename']) ?></span></p>
        <div class="mt-4 pt-4 border-t border-gray-200">
          <p class="text-sm text-gray-700">
            <i class="fas fa-cubes text-orange-600 mr-2"></i>
            Total Stock: <span class="text-2xl font-bold text-orange-600"><?= $totalStock ?></span> units
          </p>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_msg'])): ?>
      <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg flex items-center gap-3 animate-pulse">
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

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-t-xl shadow-sm border border-b-0 border-gray-100 px-6">
      <div class="flex gap-8 flex-wrap">
        <button class="tab-button active px-4 py-4 font-semibold text-gray-700 hover:text-orange-600" data-tab="colors-tab">
          <i class="fas fa-palette mr-2"></i>
          Colors (<?= count($colors) ?>)
        </button>
        <button class="tab-button px-4 py-4 font-semibold text-gray-700 hover:text-orange-600" data-tab="variants-tab">
          <i class="fas fa-th mr-2"></i>
          All Variants (<?= count($variants) ?>)
        </button>
      </div>
    </div>

    <!-- TAB 1: COLORS STOCK -->
    <div id="colors-tab" class="tab-content bg-white rounded-b-xl shadow-sm border border-gray-100 p-6 mb-6">
      <?php if (count($colors) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($colors as $color): ?>
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-6 border border-gray-200 hover:shadow-lg transition">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($color['color_name']) ?></h3>
                  <?php if (!empty($color['image'])): ?>
                    <img src="../../<?= htmlspecialchars($color['image']) ?>" 
                         alt="<?= htmlspecialchars($color['color_name']) ?>" 
                         class="mt-2 h-24 w-24 object-contain rounded-lg border border-gray-300" />
                  <?php endif; ?>
                </div>
              </div>
              
              <form method="POST" class="flex items-end gap-3 mt-4">
                <div class="flex-1">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Current Stock</label>
                  <input type="number" name="stock" value="<?= $color['stock'] ?>" min="0" 
                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
                <input type="hidden" name="color_id" value="<?= $color['id'] ?>">
                <button type="submit" name="update_color_stock" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition">
                  <i class="fas fa-save"></i>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <i class="fas fa-palette text-gray-300 text-4xl mb-3 block"></i>
          <p class="text-gray-600">No colors added for this product</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- TAB 2: ALL VARIANTS (COLOR + SIZE COMBINATIONS) -->
    <div id="variants-tab" class="tab-content bg-white rounded-b-xl shadow-sm border border-gray-100 p-6 mb-6 hidden">
      <?php if (count($variants) > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-100 border border-gray-200">
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Image</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Color</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Size</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Stock</th>
                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-900">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($variants as $variant): ?>
                <tr class="border border-gray-200 hover:bg-gray-50 transition">
                  <td class="px-6 py-4">
                    <?php if (!empty($variant['image'])): ?>
                      <img src="../../<?= htmlspecialchars($variant['image']) ?>" 
                           alt="variant" 
                           class="h-12 w-12 object-cover rounded-lg border border-gray-300" />
                    <?php else: ?>
                      <div class="h-12 w-12 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-image text-gray-400"></i>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($variant['color'] ?? 'N/A') ?></td>
                  <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($variant['size'] ?? 'N/A') ?></td>
                  <td class="px-6 py-4">
                    <span class="inline-block bg-orange-100 text-orange-800 px-4 py-2 rounded-lg font-bold text-lg">
                      <?= $variant['stock'] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button type="button" class="text-blue-600 hover:text-blue-700 font-medium" onclick="openVariantModal(<?= $variant['id'] ?>, '<?= htmlspecialchars($variant['color']) ?> - <?= htmlspecialchars($variant['size']) ?>', '<?= htmlspecialchars($variant['color']) ?>', '<?= htmlspecialchars($variant['size']) ?>', <?= $variant['stock'] ?>)">
                      <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <i class="fas fa-th text-gray-300 text-4xl mb-3 block"></i>
          <p class="text-gray-600">No variants found</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- MODAL FOR EDITING VARIANT STOCK -->
  <div id="variantModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold text-gray-900">Edit Variant Stock</h3>
        <button type="button" onclick="closeVariantModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form method="POST" id="variantForm" class="space-y-4">
        <div class="bg-gray-50 p-4 rounded-lg space-y-2">
          <p class="text-sm text-gray-600">Variant: <span id="modalVariantName" class="font-semibold text-gray-900"></span></p>
          <p class="text-sm text-gray-600">Color: <span id="modalColor" class="font-semibold"></span></p>
          <p class="text-sm text-gray-600">Size: <span id="modalSize" class="font-semibold"></span></p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">New Stock Quantity</label>
          <input type="number" id="modalStock" name="stock" min="0" required
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-lg" />
        </div>
        
        <input type="hidden" id="modalVariantId" name="variant_id">
        
        <div class="flex gap-3 mt-6">
          <button type="button" onclick="closeVariantModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
            Cancel
          </button>
          <button type="submit" name="update_variant_stock" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition">
            <i class="fas fa-save mr-2"></i>Save
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Tab switching
    document.querySelectorAll('.tab-button').forEach(button => {
      button.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
          tab.classList.add('hidden');
        });
        
        // Show selected tab
        document.getElementById(tabId).classList.remove('hidden');
        
        // Update active button
        document.querySelectorAll('.tab-button').forEach(btn => {
          btn.classList.remove('active');
        });
        this.classList.add('active');
      });
    });

    // Modal functions
    function openVariantModal(variantId, variantName, colorName, sizeName, currentStock) {
      document.getElementById('modalVariantId').value = variantId;
      document.getElementById('modalVariantName').textContent = variantName;
      document.getElementById('modalColor').textContent = colorName;
      document.getElementById('modalSize').textContent = sizeName;
      document.getElementById('modalStock').value = currentStock;
      document.getElementById('variantModal').classList.remove('hidden');
      document.getElementById('modalStock').focus();
    }

    function closeVariantModal() {
      document.getElementById('variantModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('variantModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeVariantModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeVariantModal();
      }
    });
  </script>

</body>

</html>