<?php
session_name("nobleadmin");
session_start();
include '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin


// === IMAGE UPLOAD & CONVERT TO WEBP ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploadDir = "../../uploads/";
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
        $originalName = $_FILES['images']['name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $newName = uniqid() . '.webp';
        $destination = $uploadDir . $newName;

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($tmpName); break;
            case 'png':
                $image = imagecreatefrompng($tmpName); break;
            case 'webp':
                move_uploaded_file($tmpName, $destination);
                $conn->query("INSERT INTO discount_images (filename) VALUES ('$newName')");
                continue 2;
            default: continue 2;
        }

        imagewebp($image, $destination, 80);
        imagedestroy($image);

        $conn->query("INSERT INTO discount_images (filename) VALUES ('$newName')");
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// === DELETE IMAGE ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $res = $conn->query("SELECT filename FROM discount_images WHERE id=$id");
    if ($row = $res->fetch_assoc()) {
        unlink("../../uploads/" . $row['filename']);
        $conn->query("DELETE FROM discount_images WHERE id=$id");
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// === UPDATE PLACEHOLDER ===
if (isset($_POST['update_id']) && isset($_FILES['update_image'])) {
    $id = (int)$_POST['update_id'];
    $tmpName = $_FILES['update_image']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['update_image']['name'], PATHINFO_EXTENSION));
    $newName = uniqid() . '.webp';
    $destination = "../../uploads/" . $newName;

    switch ($ext) {
        case 'jpg': case 'jpeg':
            $image = imagecreatefromjpeg($tmpName); break;
        case 'png':
            $image = imagecreatefrompng($tmpName); break;
        case 'webp':
            move_uploaded_file($tmpName, $destination);
            break;
        default:
            die("Unsupported file type.");
    }

    if ($ext !== 'webp') {
        imagewebp($image, $destination, 80);
        imagedestroy($image);
    }

    // Delete old image
    $res = $conn->query("SELECT filename FROM discount_images WHERE id=$id");
    if ($row = $res->fetch_assoc()) {
        unlink("uploads/" . $row['filename']);
    }

    $conn->query("UPDATE discount_images SET filename='$newName' WHERE id=$id");
    header("Location: ".$_SERVER['PHP_SELF']);
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
      🏷️ Main Discount Area
    </h1>

    <!-- Upload Form -->
    <form action="" method="post" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 mb-10 border">
      <label class="block text-sm font-medium text-gray-700 mb-2">Upload Discount Images</label>
      <input type="file" name="images[]" multiple required class="mb-4 w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded shadow">
        Upload Images
      </button>
    </form>

    <!-- Uploaded Discounts -->
    <h2 class="text-2xl font-semibold text-gray-800 mb-4">📁 Uploaded Discounts</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php
      $result = $conn->query("SELECT * FROM discount_images ORDER BY uploaded_at DESC");
      while ($row = $result->fetch_assoc()):
      ?>
      <div class="bg-white rounded-lg shadow hover:shadow-md p-4 relative border border-gray-100">
        <img src="../../uploads/<?= $row['filename'] ?>" alt="discount" class="w-full h-40 object-contain mb-4 rounded-md border" />

        <!-- Update Form -->
        <form action="" method="post" enctype="multipart/form-data" class="flex flex-col space-y-2 mb-2">
          <input type="file" name="update_image" required class="block w-full text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:border-0 file:rounded file:bg-yellow-100 file:text-yellow-800 hover:file:bg-yellow-200" />
          <input type="hidden" name="update_id" value="<?= $row['id'] ?>" />
          <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded">
             Update
          </button>
        </form>

        <!-- Delete Button -->
        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this image?')" class="block text-center bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1 rounded">
           Delete
        </a>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>

