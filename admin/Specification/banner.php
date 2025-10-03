<?php
session_name("nobleadmin");
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin
include '../../connection/connect.php';


$absoluteUploadPath = "../../uploads/";
$relativeUploadPath = "../uploads/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Upload Main Area images
    if (isset($_FILES['images'])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
            $originalName = $_FILES['images']['name'][$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $newName = uniqid() . '.webp';
            $absoluteDestination = $absoluteUploadPath . $newName;
            $relativePath = $relativeUploadPath . $newName;

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($tmpName); break;
                case 'png':
                    $image = imagecreatefrompng($tmpName); break;
                case 'webp':
                    move_uploaded_file($tmpName, $absoluteDestination);
                    $conn->query("INSERT INTO discount_images (filename) VALUES ('$relativePath')");
                    continue 2;
                default: continue 2;
            }

            imagewebp($image, $absoluteDestination, 80);
            imagedestroy($image);
            $conn->query("INSERT INTO discount_images (filename) VALUES ('$relativePath')");
        }
    }

    // ✅ Update Main Area image
    if (isset($_POST['update_id']) && isset($_FILES['update_image'])) {
        $id = intval($_POST['update_id']);
        $tmpName = $_FILES['update_image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['update_image']['name'], PATHINFO_EXTENSION));
        $newName = uniqid() . '.webp';
        $absoluteDestination = $absoluteUploadPath . $newName;
        $relativePath = $relativeUploadPath . $newName;

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($tmpName); break;
            case 'png':
                $image = imagecreatefrompng($tmpName); break;
            case 'webp':
                move_uploaded_file($tmpName, $absoluteDestination);
                break;
            default:
                die("Unsupported file type.");
        }

        if ($ext !== 'webp') {
            imagewebp($image, $absoluteDestination, 80);
            imagedestroy($image);
        }

        // Delete old file
        $res = $conn->query("SELECT filename FROM discount_images WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $oldPath = str_replace("../uploads/", "../../uploads/", $row['filename']);
            if (file_exists($oldPath)) unlink($oldPath);
        }

        $conn->query("UPDATE discount_images SET filename='$relativePath' WHERE id=$id");
    }

    // ✅ Delete Main Area image
    if (isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $res = $conn->query("SELECT filename FROM discount_images WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $filePath = str_replace("../uploads/", "../../uploads/", $row['filename']);
            if (file_exists($filePath)) unlink($filePath);
        }
        $conn->query("DELETE FROM discount_images WHERE id=$id");
    }

    // ✅ Upload On Sale Banner images
    if (isset($_FILES['onsale_images'])) {
        foreach ($_FILES['onsale_images']['tmp_name'] as $i => $tmpName) {
            $originalName = $_FILES['onsale_images']['name'][$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $newName = uniqid() . '.webp';
            $absoluteDestination = $absoluteUploadPath . $newName;
            $relativePath = $relativeUploadPath . $newName;

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($tmpName); break;
                case 'png':
                    $image = imagecreatefrompng($tmpName); break;
                case 'webp':
                    move_uploaded_file($tmpName, $absoluteDestination);
                    $conn->query("INSERT INTO onsalebanner (filename) VALUES ('$relativePath')");
                    continue 2;
                default: continue 2;
            }

            imagewebp($image, $absoluteDestination, 80);
            imagedestroy($image);
            $conn->query("INSERT INTO onsalebanner (filename) VALUES ('$relativePath')");
        }
    }

    // ✅ Update On Sale Banner image
    if (isset($_POST['update_onsale_id']) && isset($_FILES['update_onsale_image'])) {
        $id = intval($_POST['update_onsale_id']);
        $tmpName = $_FILES['update_onsale_image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['update_onsale_image']['name'], PATHINFO_EXTENSION));
        $newName = uniqid() . '.webp';
        $absoluteDestination = $absoluteUploadPath . $newName;
        $relativePath = $relativeUploadPath . $newName;

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($tmpName); break;
            case 'png':
                $image = imagecreatefrompng($tmpName); break;
            case 'webp':
                move_uploaded_file($tmpName, $absoluteDestination);
                break;
            default:
                die("Unsupported file type.");
        }

        if ($ext !== 'webp') {
            imagewebp($image, $absoluteDestination, 80);
            imagedestroy($image);
        }

        // Delete old file
        $res = $conn->query("SELECT filename FROM onsalebanner WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $oldPath = str_replace("../uploads/", "../../uploads/", $row['filename']);
            if (file_exists($oldPath)) unlink($oldPath);
        }

        $conn->query("UPDATE onsalebanner SET filename='$relativePath' WHERE id=$id");
    }

    // ✅ Delete On Sale Banner image
    if (isset($_POST['delete_onsale_id'])) {
        $id = intval($_POST['delete_onsale_id']);
        $res = $conn->query("SELECT filename FROM onsalebanner WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $filePath = str_replace("../uploads/", "../../uploads/", $row['filename']);
            if (file_exists($filePath)) unlink($filePath);
        }
        $conn->query("DELETE FROM onsalebanner WHERE id=$id");
    }

    header("Location: banner.php"); // Redirect after processing
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Discount Manager</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

  <?php include '../navbar/top.php'; ?>

  <div class="max-w-7xl mx-auto p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-2">
      🏷️ Main Area 
    </h1>

    <!-- Upload Form -->
    <form action="" method="post" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 mb-10 border">
      <label class="block text-sm font-medium text-gray-700 mb-2">Upload Images</label>
      <input type="file" name="images[]" multiple required class="mb-4 w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded shadow">
        Upload Images
      </button>
    </form>

    <!-- Uploaded Discounts -->
    <h2 class="text-2xl font-semibold text-gray-800 mb-4">📁 Uploaded</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-16">
      <?php
      $result = $conn->query("SELECT * FROM discount_images ORDER BY uploaded_at DESC");
      while ($row = $result->fetch_assoc()):
      ?>
      <div class="bg-white rounded-lg shadow hover:shadow-md p-4 relative border border-gray-100">
        <img src="../../uploads/<?= basename($row['filename']) ?>" alt="discount" class="w-full h-40 object-contain mb-4 rounded-md border" />

        <!-- Update Form -->
        <form action="" method="post" enctype="multipart/form-data" class="flex flex-col space-y-2 mb-2">
          <input type="file" name="update_image" required class="block w-full text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:border-0 file:rounded file:bg-yellow-100 file:text-yellow-800 hover:file:bg-yellow-200" />
          <input type="hidden" name="update_id" value="<?= $row['id'] ?>" />
          <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded">
             Update
          </button>
        </form>

        <!-- Delete Button -->
        <form action="" method="post" onsubmit="return confirm('Delete this image?')">
          <input type="hidden" name="delete_id" value="<?= $row['id'] ?>" />
          <button type="submit" class="w-full text-center bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1 rounded">
             Delete
          </button>
        </form>
      </div>
      <?php endwhile; ?>
    </div>

    <!-- On Sale Banner Section -->
    <hr class="my-8 border-gray-300" />
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-2">
      🔥 On Sale Banner
    </h1>

    <!-- Upload Form for On Sale Banner -->
    <form action="" method="post" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 mb-10 border border-orange-200">
      <label class="block text-sm font-medium text-gray-700 mb-2">Upload On Sale Banner Images</label>
      <input type="file" name="onsale_images[]" multiple required class="mb-4 w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100" />
      <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-medium px-5 py-2 rounded shadow">
        Upload On Sale Banners
      </button>
    </form>

    <!-- Uploaded On Sale Banners -->
    <h2 class="text-2xl font-semibold text-gray-800 mb-4">📁 Uploaded On Sale Banners</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php
      $result_onsale = $conn->query("SELECT * FROM onsalebanner ORDER BY uploaded_at DESC");
      while ($row = $result_onsale->fetch_assoc()):
      ?>
      <div class="bg-white rounded-lg shadow hover:shadow-md p-4 relative border border-orange-100">
        <img src="../../uploads/<?= basename($row['filename']) ?>" alt="onsale banner" class="w-full h-40 object-contain mb-4 rounded-md border" />

        <!-- Update Form -->
        <form action="" method="post" enctype="multipart/form-data" class="flex flex-col space-y-2 mb-2">
          <input type="file" name="update_onsale_image" required class="block w-full text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:border-0 file:rounded file:bg-yellow-100 file:text-yellow-800 hover:file:bg-yellow-200" />
          <input type="hidden" name="update_onsale_id" value="<?= $row['id'] ?>" />
          <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded">
             Update
          </button>
        </form>

        <!-- Delete Button -->
        <form action="" method="post" onsubmit="return confirm('Delete this on sale banner?')">
          <input type="hidden" name="delete_onsale_id" value="<?= $row['id'] ?>" />
          <button type="submit" class="w-full text-center bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1 rounded">
             Delete
          </button>
        </form>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>