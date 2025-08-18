<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['supplier', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

$_SESSION['last_activity'] = time();

// FIXED: Get supplier_id the same way as in upload script
$user_identifier = $_SESSION['noble_user'] ?? null;
if (!$user_identifier) die("User not found in session.");

// Check if it's email or ID and get supplier_id
$is_email = filter_var($user_identifier, FILTER_VALIDATE_EMAIL);
$query = $is_email ?
    "SELECT supplier_id FROM nobleaccount WHERE email = ?" :
    "SELECT supplier_id FROM nobleaccount WHERE id = ?";
    
$stmt = $conn->prepare($query);
$stmt->bind_param($is_email ? "s" : "i", $user_identifier);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$supplier_id = $data['supplier_id'] ?? null;

if (!$supplier_id) die("Supplier ID not found for user.");

// Now get products with the correct supplier_id
$products = [];
$stmt = $conn->prepare("SELECT id, item_code, product_name FROM supplier_products WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Upload Product</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
  <?php include '../navbar/top.php'; ?>

<section class="p-4">
  <div class="flex flex-wrap gap-3 justify-start items-end">

    <!-- Order Button -->
    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow text-sm">
      Order list
    </button>

     <!-- Order Button -->
    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow text-sm">
     Purchase Order
    </button>

  </div>
</section> 

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-1 px-4">
    <!-- LEFT: Upload Product Form -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold text-orange-600 mb-6">Upload New Product</h2>

      <form id="productForm" method="POST" action="upload_product.php" enctype="multipart/form-data" class="space-y-6">
        <!-- Main Info -->
        <div>
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Main Product Info</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="item_code" placeholder="Item Code" required class="border rounded px-3 py-2 w-full">
            <input type="text" name="product_name" placeholder="Product Name" required class="border rounded px-3 py-2 w-full">
          </div>
          <textarea name="description" placeholder="Description" required class="border rounded px-3 py-2 w-full mt-4 h-24 resize-none"></textarea>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <input type="text" name="category" placeholder="Category (e.g. Cement, Wood)" class="border rounded px-3 py-2 w-full">
            <input type="text" name="main_unit" placeholder="Unit (e.g. kg, pcs, bags)" class="border rounded px-3 py-2 w-full">
          </div>
          <textarea name="main_specification" placeholder="Main Specification" class="border rounded px-3 py-2 w-full mt-4 h-20 resize-none"></textarea>
          <label class="block mt-4 text-sm font-medium">Main Image (JPG/PNG only, Max 15MB):</label>
          <input type="file" name="main_image" accept="image/jpeg,image/png" class="block mt-1" required>
        </div>

        <!-- Variants -->
        <div>
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Variants</h3>
          <div id="variants" class="space-y-4">
            <div class="variant grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
              <input type="text" name="size[]" placeholder="Size" required class="border rounded px-2 py-1">
              <input type="text" name="color[]" placeholder="Color" required class="border rounded px-2 py-1">
              <input type="number" name="stock[]" placeholder="Stock" required class="border rounded px-2 py-1">
              <input type="number" name="price[]" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
              <input type="file" name="variant_image[]" accept="image/jpeg,image/png" class="text-xs">
              <div class="flex justify-center">
                <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-xl font-bold"></button>
              </div>
            </div>
          </div>
          <button type="button" onclick="addVariant()" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded hover:bg-blue-600">
            + Add Variant
          </button>
        </div>

        <div class="pt-6">
          <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded">
            Upload Product
          </button>
        </div>
      </form>
    </div>

    <!-- RIGHT: Manage Products -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-y-auto max-h-[90vh]">
      <h2 class="text-2xl font-bold text-green-700 mb-6">Your Products</h2>
      
    
      <?php if (count($products) === 0): ?>
        <p class="text-gray-500">No products yet.</p>
      <?php else: ?>
        <ul class="space-y-4">
          <?php foreach ($products as $prod): ?>
            <li class="flex justify-between items-center border-b pb-2">
              <div>
                <p class="font-semibold"><?= htmlspecialchars($prod['product_name']) ?></p>
                <p class="text-sm text-gray-500">Code: <?= htmlspecialchars($prod['item_code']) ?></p>
              </div>
              <div class="flex space-x-2">
                <a href="supplier_edit_product.php?id=<?= $prod['id'] ?>" class="text-blue-600 hover:underline text-sm"> Edit</a>
                <a href="supplier_delete_product.php?id=<?= $prod['id'] ?>" onclick="return confirm('Delete this product?')" class="text-red-600 hover:underline text-sm"> Delete</a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function addVariant() {
      const div = document.createElement('div');
      div.className = 'variant grid grid-cols-1 md:grid-cols-6 gap-3 items-center mt-2';
      div.innerHTML = `
        <input type="text" name="size[]" placeholder="Size" required class="border rounded px-2 py-1">
        <input type="text" name="color[]" placeholder="Color" required class="border rounded px-2 py-1">
        <input type="number" name="stock[]" placeholder="Stock" required class="border rounded px-2 py-1">
        <input type="number" name="price[]" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
        <input type="file" name="variant_image[]" accept="image/jpeg,image/png" class="text-xs">
        <div class="flex justify-center">
          <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-xl font-bold"></button>
        </div>
      `;
      document.getElementById('variants').appendChild(div);
    }

    function removeVariant(btn) {
      const variantRow = btn.closest('.variant');
      if (variantRow && document.querySelectorAll('.variant').length > 1) {
        variantRow.remove();
      } else {
        alert('At least one variant is required.');
      }
    }
  </script>
</body>
</html>