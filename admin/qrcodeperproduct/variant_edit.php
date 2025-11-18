<?php
session_name("nobleadmin");
include '../../connection/connect.php';
require '../../vendor/autoload.php';
session_start();
require_once '../role/roleaccount.php'; 
require_role(['productspecialist','superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// ➤ Helper: save image to uploads/ and convert to WebP
function saveImageToFolder($file, $targetDir = '../../uploads/') {
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

    $filename = uniqid('img_', true) . '.webp';
    $targetPath = $targetDir . $filename;
    $relativePath = 'uploads/' . $filename;

    $type = mime_content_type($file['tmp_name']);
    $src = null;

    switch ($type) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $src = imagecreatefrompng($file['tmp_name']);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($file['tmp_name']);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/webp':
            if (move_uploaded_file($file['tmp_name'], $targetPath)) return $relativePath;
            return null;
        default:
            return null;
    }

    if ($src && imagewebp($src, $targetPath, 80)) {
        imagedestroy($src);
        return $relativePath;
    }
    return null;
}

// ➤ SUCCESS / ERROR messages
$success = $error = '';

// ➤ Handle deletion of a specific image
if (isset($_GET['id'], $_GET['delete_image_index'])) {
    $id = (int)$_GET['id'];
    $deleteIndex = (int)$_GET['delete_image_index'];

    $stmt = $conn->prepare("SELECT product_images FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($currentImages); 
    $stmt->fetch();
    $stmt->close();

    if ($currentImages) {
        $imageArray = json_decode($currentImages, true) ?: [];
        
        if (isset($imageArray[$deleteIndex])) {
            $imagePath = $imageArray[$deleteIndex];
            if (file_exists("../../$imagePath")) {
                unlink("../../$imagePath");
            }
            
            unset($imageArray[$deleteIndex]);
            $imageArray = array_values($imageArray);
            
            $updatedImages = empty($imageArray) ? NULL : json_encode($imageArray);
            $stmt = $conn->prepare("UPDATE products SET product_images=? WHERE id=?");
            $stmt->bind_param("si", $updatedImages, $id);
            
            if ($stmt->execute()) {
                $success = '✓ Image deleted successfully.';
            } else {
                $error = '✗ Error deleting image.';
            }
            $stmt->close();
        }
    }
}

// ➤ Handle form submission (upload/update)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)$_POST['id'];
    $descPic = trim($_POST['descriptionpic'] ?? '');
    $uploadErrors = [];

    $stmt = $conn->prepare("SELECT product_images FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($existingImages);
    $stmt->fetch();
    $stmt->close();

    $imageArray = [];
    if ($existingImages) {
        $imageArray = json_decode($existingImages, true) ?: [];
    }

    // Process new uploaded images
    $fields = ['image1', 'image2', 'image3', 'image4'];
    foreach ($fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            // Validate file size (max 5MB)
            if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
                $uploadErrors[] = "$field: File size exceeds 5MB";
                continue;
            }

            $path = saveImageToFolder($_FILES[$field]);
            if ($path) {
                $imageArray[] = $path;
            } else {
                $uploadErrors[] = "$field: Failed to process image";
            }
        }
    }

    if (!empty($uploadErrors)) {
        $error = 'Some uploads failed: ' . implode(', ', $uploadErrors);
    } else {
        $finalImages = empty($imageArray) ? NULL : json_encode($imageArray);

        $stmt = $conn->prepare("UPDATE products SET descriptionpic=?, product_images=? WHERE id=?");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssi", $descPic, $finalImages, $id);
            if ($stmt->execute()) {
                $success = '✓ Product updated successfully. ' . count($imageArray) . ' image(s) stored.';
            } else {
                $error = '✗ Failed to update product: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ➤ Fetch product to display/update
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$product) {
    echo "<p class='text-center p-4'>Product not found.</p>";
    exit;
}

$productImages = [];
if ($product['product_images']) {
    $productImages = json_decode($product['product_images'], true) ?: [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Product Images</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-sans">
<?php include '../navbar/top.php'; ?>

<div class="max-w-5xl mx-auto mt-8 p-6 bg-white shadow-xl rounded-2xl">
  
  <!-- Header -->
  <div class="border-b border-gray-200 pb-6 mb-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-2">
      <i class="fas fa-image text-orange-500 mr-3"></i>Edit Product Images
    </h1>
    <p class="text-gray-600">
      <strong><?= htmlspecialchars($product['product_name'] ?? '') ?></strong> 
      <span class="text-gray-400 ml-2">(ID: <?= $product['id'] ?>)</span>
    </p>
  </div>

  <!-- Alert Messages -->
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

  <form method="POST" enctype="multipart/form-data" class="space-y-8">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- Text Description Section -->
    <div class="bg-gray-50 p-6 rounded-xl">
      <label class="block font-semibold text-gray-700 mb-3">
        <i class="fas fa-align-left text-orange-500 mr-2"></i>Product Description
      </label>
      <textarea name="descriptionpic" 
                class="w-full border-2 border-gray-300 rounded-lg p-4 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent resize-vertical" 
                rows="5" 
                placeholder="Enter product description..."><?= htmlspecialchars($product['descriptionpic'] ?? '') ?></textarea>
      <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Maximum 500 characters</p>
    </div>

    <!-- Current Images Section -->
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
            <div class="relative h-32 bg-gray-200 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-shadow">
              <img src="../../<?= htmlspecialchars($imagePath) ?>" 
                   class="w-full h-full object-cover" 
                   alt="Product Image <?= $index + 1 ?>">
              
              <!-- Image Index Badge -->
              <div class="absolute top-2 left-2 bg-black/60 text-white px-2 py-1 rounded text-xs font-semibold">
                <?= $index + 1 ?>
              </div>
            </div>
            
            <!-- Delete Button -->
            <a href="?id=<?= $id ?>&delete_image_index=<?= $index ?>" 
               onclick="return confirm('Delete this image? This action cannot be undone.')" 
               class="absolute -top-3 -right-3 bg-red-600 hover:bg-red-700 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-lg transition opacity-0 group-hover:opacity-100">
              <i class="fas fa-trash-alt text-sm"></i>
            </a>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- New Images Upload Section -->
    <div class="bg-gray-50 p-6 rounded-xl">
      <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-cloud-upload-alt text-orange-500"></i>
        Add New Images (up to 4)
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-orange-400 hover:bg-orange-50 transition">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Image <?= $i ?>
          </label>
          <input type="file" 
                 name="image<?= $i ?>" 
                 accept="image/jpeg,image/png,image/gif,image/webp" 
                 class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-500 file:text-white hover:file:bg-orange-600 cursor-pointer" />
          <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF, WebP (Max 5MB)</p>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-3 pt-6 border-t border-gray-200">
      <button type="submit" class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold py-3 rounded-lg shadow-lg transition flex items-center justify-center gap-2">
        <i class="fas fa-save"></i>Save Changes
      </button>
      <a href="qrcodeitem.php" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 rounded-lg shadow-lg transition flex items-center justify-center gap-2">
        <i class="fas fa-arrow-left"></i>Back
      </a>
    </div>
  </form>

  <!-- Image Statistics -->
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