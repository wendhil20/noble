<?php
session_name("nobleadmin");
include '../../connection/connect.php';
require '../../vendor/autoload.php';
session_start();
require_once '../role/roleaccount.php'; 
require_role(['productspecialist','superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
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
    switch ($type) {
        case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
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

// ➤ Handle deletion of a specific image from the combined field
if (isset($_GET['id'], $_GET['delete_image_index'])) {
    $id = (int)$_GET['id'];
    $deleteIndex = (int)$_GET['delete_image_index'];

    // Get current images
    $stmt = $conn->prepare("SELECT product_images FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($currentImages); 
    $stmt->fetch();
    $stmt->close();

    if ($currentImages) {
        $imageArray = json_decode($currentImages, true) ?: [];
        
        // Check if index exists and delete the file
        if (isset($imageArray[$deleteIndex])) {
            $imagePath = $imageArray[$deleteIndex];
            if (file_exists("../../$imagePath")) {
                unlink("../../$imagePath");
            }
            
            // Remove from array and reindex
            unset($imageArray[$deleteIndex]);
            $imageArray = array_values($imageArray);
            
            // Update database
            $updatedImages = empty($imageArray) ? NULL : json_encode($imageArray);
            $stmt = $conn->prepare("UPDATE products SET product_images=? WHERE id=?");
            $stmt->bind_param("si", $updatedImages, $id);
            
            if ($stmt->execute()) {
                $success = 'Image deleted successfully.';
            } else {
                $error = 'Error deleting image.';
            }
            $stmt->close();
        } else {
            $error = 'Image not found.';
        }
    }
}

// ➤ Handle form submission (upload/update)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)$_POST['id'];
    $descPic = trim($_POST['descriptionpic'] ?? '');

    // Get existing images
    $stmt = $conn->prepare("SELECT product_images FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($existingImages);
    $stmt->fetch();
    $stmt->close();

    // Parse existing images
    $imageArray = [];
    if ($existingImages) {
        $imageArray = json_decode($existingImages, true) ?: [];
    }

    // Process new uploaded images
    $fields = ['image1', 'image2', 'image3', 'image4'];
    foreach ($fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $path = saveImageToFolder($_FILES[$field]);
            if ($path) {
                $imageArray[] = $path; // Add new image to array
            }
        }
    }

    // Convert array to JSON (or NULL if empty)
    $finalImages = empty($imageArray) ? NULL : json_encode($imageArray);

    // Update database
    $stmt = $conn->prepare("UPDATE products SET descriptionpic=?, product_images=? WHERE id=?");
    if (!$stmt) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param("ssi", $descPic, $finalImages, $id);
        if ($stmt->execute()) {
            $success = 'Product updated successfully.';
        } else {
            $error = 'Execute failed: ' . $stmt->error;
        }
        $stmt->close();
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

// Parse images for display
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
</head>
<body class="bg-gray-100 min-h-screen font-sans">
<?php include '../navbar/top.php'; ?>

<div class="max-w-3xl mx-auto mt-10 p-6 bg-white shadow-lg rounded-xl">
  <h1 class="text-3xl font-bold text-gray-800 mb-2 border-b pb-2">
     Edit Product: <span class="text-orange-600"><?= htmlspecialchars($product['product_name'] ?? '') ?></span>
  </h1>
  <p class="text-gray-600 mb-6">
    Product ID: <span class="font-semibold"><?= $product['id'] ?></span>
  </p>

  <?php if ($success): ?>
    <div class="mb-4 p-4 rounded bg-green-100 text-green-800 border border-green-300">
       <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-4 p-4 rounded bg-red-100 text-red-800 border border-red-300">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- Text Description -->
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Description</label>
      <textarea name="descriptionpic" class="w-full border rounded-md p-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-400" rows="4"><?= htmlspecialchars($product['descriptionpic'] ?? '') ?></textarea>
    </div>

    <!-- Current Images Display -->
    <?php if (!empty($productImages)): ?>
    <div>
      <label class="block font-semibold text-gray-700 mb-2">Current Images</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <?php foreach ($productImages as $index => $imagePath): ?>
          <?php if (file_exists("../../$imagePath")): ?>
          <div class="relative group">
            <img src="../../<?= htmlspecialchars($imagePath) ?>" class="w-full h-24 object-cover border rounded-md shadow-sm" alt="Product Image <?= $index + 1 ?>">
            <a href="?id=<?= $id ?>&delete_image_index=<?= $index ?>" 
               onclick="return confirm('Delete this image?')" 
               class="absolute -top-2 -right-2 bg-red-600 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center opacity-90 group-hover:opacity-100 transition">✖</a>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- New Images Upload -->
    <div>
      <label class="block font-semibold text-gray-700 mb-2">Add New Images</label>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Image <?= $i ?></label>
          <input type="file" name="image<?= $i ?>" accept="image/jpeg,image/png,image/gif,image/webp" 
                 class="block w-full text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200" />
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="pt-4">
      <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition">Save Changes</button>
    </div>
  </form>

  <div class="mt-6">
    <a href="qrcodeitem.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to Products</a>
  </div>
</div>

<!-- Display JSON structure for debugging -->
<div class="max-w-3xl mx-auto mt-4 p-4 bg-gray-50 rounded-lg">
  <h3 class="font-semibold text-gray-700 mb-2">Current Image Data Structure:</h3>
  <pre class="text-xs text-gray-600 bg-white p-2 rounded border"><?= htmlspecialchars($product['product_images'] ?? 'No images') ?></pre>
</div>

</body>
</html>