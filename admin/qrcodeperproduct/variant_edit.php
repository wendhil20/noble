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
        if ($stmt->execute()) $success = 'Variant updated successfully.';
        else $error = 'Execute failed: '.$stmt->error;
        $stmt->close();
    }
}

// ➤ Fetch variant with product info to display/update
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("
    SELECT pv.*, p.product_name 
    FROM product_variants pv 
    LEFT JOIN products p ON pv.product_id = p.id 
    WHERE pv.id=?
");
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
<body class="bg-gray-100 min-h-screen font-sans">
<?php include '../navbar/top.php'; ?>

<div class="max-w-3xl mx-auto mt-10 p-6 bg-white shadow-lg rounded-xl">
  <h1 class="text-3xl font-bold text-gray-800 mb-2 border-b pb-2">
     Edit Variant: <span class="text-orange-600"><?= htmlspecialchars($variant['namevariant'] ?? '') ?></span>
  </h1>
  <p class="text-gray-600 mb-6">
    Product: <span class="font-semibold"><?= htmlspecialchars($variant['product_name'] ?? '') ?></span> | 
    Variant ID: <span class="font-semibold"><?= $variant['id'] ?></span>
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
      <label class="block font-semibold text-gray-700 mb-1"> Text Description</label>
      <textarea name="descriptionpic" class="w-full border rounded-md p-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-400" rows="4"><?= htmlspecialchars($variant['descriptionpic'] ?? '') ?></textarea>
    </div>

    <!-- Images Upload -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <?php
      $labels = [
        'imagedescription'     => 'Image 1',
        'imagedescriptiontwo'  => 'Image 2',
        'imagedescriptiontree' => 'Image 3',
        'imagedescriptionfour' => 'Image 4'
      ];
      foreach ($labels as $field => $label):
      ?>
      <div>
        <label class="block font-medium text-gray-700 mb-1"><?= htmlspecialchars($label) ?></label>
        <?php if (!empty($variant[$field]) && file_exists("../../" . $variant[$field])): ?>
          <div class="relative group w-fit mb-2">
            <img src="../../<?= htmlspecialchars($variant[$field]) ?>" class="w-32 h-32 object-contain border rounded-md shadow-sm" alt="">
            <a href="?id=<?= $id ?>&delete_image=<?= $field ?>" onclick="return confirm('Delete this image?')" class="absolute top-0 right-0 bg-red-600 text-white text-xs px-2 py-1 rounded-full opacity-90 group-hover:opacity-100 transition">✖</a>
          </div>
        <?php endif; ?>
        <input type="file" name="<?= $field ?>" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-sm mt-1 text-gray-600 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200" />
      </div>
      <?php endforeach; ?>
    </div>

    <div class="pt-4">
      <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition"> Save Changes</button>
    </div>
  </form>

  <div class="mt-6">
    <a href="qrcodeitem.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to Products</a>
  </div>
</div>
</body>
</html>