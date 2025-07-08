<?php
include '../../connection/connect.php';
session_start();

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

// ➤ Handle deletion of an existing image
if (isset($_GET['id'], $_GET['delete_image'])) {
    $id = (int)$_GET['id'];
    $field = $_GET['delete_image'];
    $allowed = ['imagedescription','imagedescriptiontwo','imagedescriptiontree','imagedescriptionfour'];

    if (in_array($field, $allowed)) {
        $stmt = $conn->prepare("SELECT $field FROM product_variants WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($path); $stmt->fetch();
        $stmt->close();

        if ($path && file_exists("../../$path")) unlink("../../$path");

        $stmt = $conn->prepare("UPDATE product_variants SET $field=NULL WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success = 'Image deleted successfully.';
        else $error = 'Error deleting image.';
        $stmt->close();
    } else {
        $error = 'Invalid image field.';
    }
}

// ➤ Handle form submission (upload/update)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)$_POST['id'];
    $descPic = trim($_POST['descriptionpic'] ?? '');
    $fields = ['imagedescription','imagedescriptiontwo','imagedescriptiontree','imagedescriptionfour'];

    $sql = "UPDATE product_variants SET descriptionpic=?";
    $types = "s"; $params = [$descPic];

    foreach ($fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error']===UPLOAD_ERR_OK) {
            $path = saveImageToFolder($_FILES[$field]);
            if ($path) {
                $sql .= ", $field=?";
                $types .= "s";
                $params[] = $path;
            }
        }
    }
    $sql .= " WHERE id=?";
    $types .= "i";
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    if (!$stmt) $error = "Prepare failed: ".$conn->error;
    else {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) $success = 'Variant updated.';
        else $error = 'Execute failed: '.$stmt->error;
        $stmt->close();
    }
}

// ➤ Fetch variant to display/update
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM product_variants WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$variant = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$variant) {
    echo "<p class='text-center p-4'>Variant not found.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Variant Images</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 py-8">
  <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">

    <h2 class="text-2xl font-semibold mb-4">Edit Variant: <?= htmlspecialchars($variant['namevariant'] ?? '') ?></h2>
    <?php if ($success): ?>
      <div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="mb-4 p-3 bg-red-100 text-red-800 rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="id" value="<?= $id ?>">

      <label class="block"><span class="font-medium">Text Description</span>
        <textarea name="descriptionpic"
                  class="w-full mt-1 border rounded p-2"
                  rows="4"><?= htmlspecialchars($variant['descriptionpic'] ?? '') ?></textarea>
      </label>

      <?php
      $labels = [
        'imagedescription'=>'Image 1', 
        'imagedescriptiontwo'=>'Image 2', 
        'imagedescriptiontree'=>'Image 3', 
        'imagedescriptionfour'=>'Image 4'
      ];
      foreach ($labels as $field=>$label):
      ?>
      <div>
        <span class="font-medium"><?= htmlspecialchars($label) ?></span>
        <?php if (!empty($variant[$field]) && file_exists("../../" . $variant[$field])): ?>
            <div class="relative mt-2 mb-2">
              <img src="../../<?= htmlspecialchars($variant[$field]) ?>"
                   class="w-32 h-32 object-contain rounded border" alt="">
              <a href="?id=<?= $id ?>&delete_image=<?= $field ?>"
                 onclick="return confirm('Delete this image?')"
                 class="absolute top-0 right-0 bg-red-600 text-white px-2 py-1 text-xs rounded">X</a>
            </div>
        <?php endif; ?>

        <input type="file" name="<?= $field ?>" accept="image/jpeg,image/png,image/gif,image/webp" class="mt-1" />
      </div>
      <?php endforeach; ?>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Save Changes
      </button>
    </form>

    <div class="mt-4">
      <a href="qrcodeitem.php" class="text-blue-600">&larr; Back</a>
    </div>
  </div>
</body>
</html>
