<?php
include '../../connection/connect.php';

// Success and error messages
$success_message = '';
$error_message = '';

// Handle delete request
if (isset($_GET['delete_image']) && isset($_GET['id'])) {
    $variant_id = (int)$_GET['id'];
    $image_field = $_GET['delete_image'];

    $allowed_fields = ['imagedescription', 'imagedescriptiontwo', 'imagedescriptiontree', 'imagedescriptionfour'];

    if (in_array($image_field, $allowed_fields)) {
        $stmt = $conn->prepare("UPDATE product_variants SET $image_field = NULL WHERE id = ?");
        $stmt->bind_param("i", $variant_id);
        if ($stmt->execute()) {
            header("Location: ?id=$variant_id&success=1");
            exit;
        } else {
            header("Location: ?id=$variant_id&error=1");
            exit;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)$_POST['id'];
    $descriptionpic = trim($_POST['descriptionpic']);

    try {
        $sql = "UPDATE product_variants SET descriptionpic = ?";
        $types = "s";
        $params = [$descriptionpic];

        $image_fields = ['imagedescription', 'imagedescriptiontwo', 'imagedescriptiontree', 'imagedescriptionfour'];

        foreach ($image_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($file_info, $_FILES[$field]['tmp_name']);
                finfo_close($file_info);

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception("Invalid file type for $field. Only JPEG, PNG, GIF, and WebP are allowed.");
                }

                if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File size too large for $field. Maximum 5MB allowed.");
                }

                $image_data = file_get_contents($_FILES[$field]['tmp_name']);

                $sql .= ", $field = ?";
                $types .= "s";
                $params[] = $image_data;
            }
        }

        $sql .= " WHERE id = ?";
        $types .= "i";
        $params[] = $variant_id;

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $success_message = "Variant updated successfully!";
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get variant data for display
$variant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($variant_id <= 0) {
    echo "Invalid variant ID.";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM product_variants WHERE id = ?");
if (!$stmt) {
    echo "Database error: " . $conn->error;
    exit;
}
$stmt->bind_param("i", $variant_id);
$stmt->execute();
$result = $stmt->get_result();
$variant = $result->fetch_assoc();
$stmt->close();

if (!$variant) {
    echo "Variant not found.";
    exit;
}

// Check for URL feedback
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Variant updated successfully!";
}
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $error_message = "An error occurred while updating the variant.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Variant - Product Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-8">
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit Variant Descriptions</h1>
        <p class="text-gray-600">Update the textual description and images for this product variant.</p>
    </div>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="id" value="<?= $variant_id ?>">

            <div>
                <label for="descriptionpic" class="block text-sm font-medium text-gray-700 mb-2">
                    Textual Description About Images
                </label>
                <textarea
                    name="descriptionpic"
                    id="descriptionpic"
                    rows="4"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                ><?= htmlspecialchars($variant['descriptionpic']) ?></textarea>
            </div>

            <?php
            $image_fields = [
                'imagedescription' => 'Description Image 1',
                'imagedescriptiontwo' => 'Description Image 2',
                'imagedescriptiontree' => 'Description Image 3',
                'imagedescriptionfour' => 'Description Image 4'
            ];

            foreach ($image_fields as $field => $label): ?>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700"><?= $label ?></label>

                    <?php if (!empty($variant[$field])): ?>
                        <div class="relative mb-2">
                            <img src="data:image/jpeg;base64,<?= base64_encode($variant[$field]) ?>"
                                 alt="<?= $label ?>"
                                 class="w-full max-w-sm rounded shadow border border-gray-200">
                            <a href="?id=<?= $variant_id ?>&delete_image=<?= $field ?>"
                               onclick="return confirm('Are you sure you want to delete this image?')"
                               class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1 rounded">
                                Delete
                            </a>
                        </div>
                    <?php endif; ?>

                    <input type="file"
                           name="<?= $field ?>"
                           accept="image/*"
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer bg-gray-50 hover:bg-gray-100">
                    <p class="text-xs text-gray-500">Supported formats: JPEG, PNG, GIF, WebP. Max size: 5MB.</p>
                </div>
            <?php endforeach; ?>

            <div class="pt-4">
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-md">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

  <div class="mt-6 text-center">
    <a href="qrcodeitem.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
        ← Back to Variants
    </a>
</div>

</div>
</body>
</html>
