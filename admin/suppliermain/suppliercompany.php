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
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time();

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

  <div class="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-orange-600 mb-6">Upload New Product</h2>

    <form id="productForm" method="POST" action="upload_product.php" enctype="multipart/form-data" class="space-y-6">
      
      <!-- Main Product Info -->
      <div>
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Main Product Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="text" name="item_code" placeholder="Item Code" required class="border rounded px-3 py-2 w-full">
          <input type="text" name="product_name" placeholder="Product Name" required class="border rounded px-3 py-2 w-full">
        </div>
        <textarea name="description" placeholder="Description" required class="border rounded px-3 py-2 w-full mt-4 h-24 resize-none"></textarea>
        <input type="text" name="category" placeholder="Category (e.g. Cement, Wood)" class="border rounded px-3 py-2 w-full mt-4">
        <label class="block mt-4 text-sm font-medium">Main Image (JPG/PNG only, Max 15MB):</label>
        <input type="file" name="main_image" accept="image/jpeg,image/png" class="block mt-1" required>
      </div>

      <!-- Variant Section -->
      <div>
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Variants</h3>
        <div id="variants" class="space-y-4">
          <div class="variant grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
            <input type="text" name="size[]" placeholder="Size" required class="border rounded px-2 py-1">
            <input type="text" name="color[]" placeholder="Color" required class="border rounded px-2 py-1">
            <input type="number" name="stock[]" placeholder="Stock" required class="border rounded px-2 py-1">
            <input type="number" name="price[]" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
            <input type="file" name="variant_image[]" accept="image/jpeg,image/png">
          </div>
        </div>
        <button type="button" onclick="addVariant()" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded hover:bg-blue-600">
          + Add Variant
        </button>
      </div>

      <!-- Submit -->
      <div class="pt-6">
        <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded">
          Upload Product
        </button>
      </div>

    </form>
  </div>

  <script>
    function addVariant() {
      const div = document.createElement('div');
      div.className = 'variant grid grid-cols-1 md:grid-cols-5 gap-3 items-center mt-2';
      div.innerHTML = `
        <input type="text" name="size[]" placeholder="Size" required class="border rounded px-2 py-1">
        <input type="text" name="color[]" placeholder="Color" required class="border rounded px-2 py-1">
        <input type="number" name="stock[]" placeholder="Stock" required class="border rounded px-2 py-1">
        <input type="number" name="price[]" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
        <input type="file" name="variant_image[]" accept="image/jpeg,image/png">
      `;
      document.getElementById('variants').appendChild(div);
    }
  </script>

</body>
</html>
