<?php
// admin/qrcodeperproduct/variant_edit.php
// THIS FILE: Shows form for editing product images

session_name("nobleadmin");
include '../../connection/connect.php';
require '../../vendor/autoload.php';
session_start();
require_once '../role/roleaccount.php'; 
require_role(['productspecialist','superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// GET PRODUCT ID FROM URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

error_log("variant_edit.php - Product ID: $product_id");

if ($product_id <= 0) {
    die("<div class='text-center p-6 bg-white rounded shadow'><p class='text-red-600'>Invalid Product ID</p></div>");
}

// ➤ FETCH PRODUCT
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("<div class='text-center p-6 bg-white rounded shadow'><p class='text-red-600'>Product not found</p></div>");
}

// PARSE EXISTING IMAGES
$productImages = [];
if ($product['product_images']) {
    $productImages = json_decode($product['product_images'], true) ?: [];
}

// GET SUCCESS/ERROR MESSAGES
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
if (isset($_SESSION['success_message'])) unset($_SESSION['success_message']);
if (isset($_SESSION['error_message'])) unset($_SESSION['error_message']);

// HANDLE IMAGE DELETION
if (isset($_GET['delete_image_index'])) {
    $deleteIndex = (int)$_GET['delete_image_index'];
    if (isset($productImages[$deleteIndex])) {
        unset($productImages[$deleteIndex]);
        $productImages = array_values($productImages); // Re-index
        $conn->query("UPDATE products SET product_images = '" . json_encode($productImages) . "' WHERE id = $product_id");
        $_SESSION['success_message'] = "Image deleted successfully";
        header("Location: variant_edit.php?id=$product_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Product - <?= htmlspecialchars($product['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include '../navbar/top.php'; ?>

<div class="max-w-5xl mx-auto mt-8 p-6 bg-white shadow-xl rounded-2xl">
  
  <!-- Header -->
  <div class="border-b border-gray-200 pb-6 mb-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-2">
      <i class="fas fa-image text-orange-500 mr-3"></i>Edit Product Images & Description
    </h1>
    <p class="text-gray-600">
      <strong><?= htmlspecialchars($product['product_name'] ?? '') ?></strong> 
      <span class="text-gray-400 ml-2">(ID: <?= $product_id ?>)</span>
    </p>
  </div>

  <!-- Messages -->
  <?php if ($success): ?>
    <div class="mb-6 p-4 rounded-lg bg-green-50 text-green-800 border-l-4 border-green-500 flex items-start gap-3">
      <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-800 border-l-4 border-red-500 flex items-start gap-3">
      <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Form - POSTS TO product_images_handler.php -->
  <form method="POST" enctype="multipart/form-data" action="product_images_handler.php" class="space-y-8">
    
    <!-- CRITICAL: Send product_id, not just 'id' -->
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <!-- Description Section -->
    <div class="bg-gray-50 p-6 rounded-xl">
      <label class="block font-semibold text-gray-700 mb-3">
        <i class="fas fa-align-left text-orange-500 mr-2"></i>Product Description
      </label>
      <textarea name="descriptionpic" 
                class="w-full border-2 border-gray-300 rounded-lg p-4 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" 
                rows="5" 
                placeholder="Enter product description..."><?php echo isset($product['descriptionpic']) ? htmlspecialchars($product['descriptionpic']) : ''; ?></textarea>
      <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Max 500 characters</p>
    </div>

    <!-- Current Images -->
    <?php if (!empty($productImages)): ?>
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl border-2 border-blue-200">
      <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-photo-film text-blue-600"></i>
        Current Images (<?= count($productImages) ?>)
      </h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($productImages as $index => $imagePath): ?>
          <?php if (file_exists("../../$imagePath")): ?>
          <div class="relative group">
            <div class="relative h-32 bg-gray-200 rounded-lg overflow-hidden shadow-md">
              <img src="../../<?= htmlspecialchars($imagePath) ?>" 
                   class="w-full h-full object-cover" 
                   alt="Product Image <?= $index + 1 ?>">
              <div class="absolute top-2 left-2 bg-black/60 text-white px-2 py-1 rounded text-xs font-semibold">
                <?= $index + 1 ?>
              </div>
            </div>
            <a href="?id=<?= $product_id ?>&delete_image_index=<?= $index ?>" 
               onclick="return confirm('Delete this image?')" 
               class="absolute -top-3 -right-3 bg-red-600 hover:bg-red-700 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition">
              <i class="fas fa-trash-alt text-sm"></i>
            </a>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upload New Images -->
    <div class="bg-gray-50 p-6 rounded-xl">
      <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-cloud-upload-alt text-orange-500"></i>
        Add New Images (up to 4)
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-orange-400 transition">
          <label class="block text-sm font-medium text-gray-700 mb-2">Image <?= $i ?></label>
          <input type="file" 
                 name="image<?= $i ?>" 
                 accept="image/jpeg,image/png,image/gif,image/webp" 
                 class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-500 file:text-white" />
          <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF, WebP (Max 5MB)</p>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Buttons -->
    <div class="flex gap-3 pt-6 border-t border-gray-200">
      <button type="submit" class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2">
        <i class="fas fa-save"></i>Save Changes
      </button>
      <a href="qrcodeitem.php" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 rounded-lg transition text-center">
        <i class="fas fa-arrow-left"></i> Back
      </a>
    </div>
  </form>

  <!-- Stats -->
  <div class="mt-8 grid grid-cols-3 gap-4 pt-6 border-t border-gray-200">
    <div class="bg-blue-50 p-4 rounded-lg text-center">
      <div class="text-2xl font-bold text-blue-600"><?= count($productImages) ?></div>
      <div class="text-sm text-gray-600">Current Images</div>
    </div>
    <div class="bg-green-50 p-4 rounded-lg text-center">
      <div class="text-2xl font-bold text-green-600"><?= 4 - count($productImages) ?></div>
      <div class="text-sm text-gray-600">Slots Available</div>
    </div>
    <div class="bg-purple-50 p-4 rounded-lg text-center">
      <div class="text-2xl font-bold text-purple-600">5 MB</div>
      <div class="text-sm text-gray-600">Max Per File</div>
    </div>
  </div>

</div>

</body>
</html>