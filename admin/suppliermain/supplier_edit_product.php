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

// Get supplier_id
$user_identifier = $_SESSION['noble_user'] ?? null;
if (!$user_identifier) die("User not found in session.");

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

// Get product ID from URL
$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) {
    header("Location: index.php");
    exit();
}

// Verify product belongs to current supplier
$stmt = $conn->prepare("SELECT * FROM supplier_products WHERE id = ? AND supplier_id = ?");
$stmt->bind_param("ii", $product_id, $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    die("Product not found or access denied.");
}

// Get variants with sizes
$variants = [];
$stmt = $conn->prepare("
    SELECT 
        v.id as variant_id, 
        v.color, 
        v.price, 
        v.image as variant_image,
        GROUP_CONCAT(CONCAT(s.id, ':', s.size, ':', s.stock) ORDER BY s.size) as sizes
    FROM supplier_product_variants v
    LEFT JOIN supplier_variant_sizes s ON v.id = s.variant_id
    WHERE v.product_id = ?
    GROUP BY v.id
    ORDER BY v.id
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['sizes_array'] = [];
    if ($row['sizes']) {
        foreach (explode(',', $row['sizes']) as $size_data) {
            list($size_id, $size, $stock) = explode(':', $size_data);
            $row['sizes_array'][] = [
                'id' => $size_id,
                'size' => $size,
                'stock' => $stock
            ];
        }
    }
    $variants[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    define('MAX_FILE_SIZE', 15 * 1024 * 1024);
    
    function save_as_webp($file_tmp, $file_name, $targetDir = '../uploads/') {
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($ext === 'gif') return false;
        if (!getimagesize($file_tmp)) return false;
        if (filesize($file_tmp) > MAX_FILE_SIZE) return false;
        
        $new_filename = uniqid() . '.webp';
        $output_path = $targetDir . $new_filename;
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = imagecreatefromjpeg($file_tmp);
                break;
            case 'png':
                $img = imagecreatefrompng($file_tmp);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                break;
            default:
                return false;
        }
        
        if (!$img) return false;
        
        imagewebp($img, $output_path, 80);
        imagedestroy($img);
        return $output_path;
    }
    
    try {
        $conn->autocommit(false);
        
        // Update main product
        $item_code = $_POST['item_code'] ?? '';
        $product_name = $_POST['product_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $unit = $_POST['unit'] ?? '';
        $specification = $_POST['specification'] ?? '';
        
        // Handle main image update
        $main_image_path = $product['image']; // Keep existing image by default
        if (!empty($_FILES['main_image']['name'])) {
            $new_image_path = save_as_webp($_FILES['main_image']['tmp_name'], $_FILES['main_image']['name']);
            if ($new_image_path) {
                // Delete old image if it exists
                if ($product['image'] && file_exists($product['image'])) {
                    unlink($product['image']);
                }
                $main_image_path = $new_image_path;
            }
        }
        
        $stmt = $conn->prepare("UPDATE supplier_products SET 
            item_code = ?, product_name = ?, description = ?, category = ?, 
            unit = ?, specification = ?, image = ?, updated_at = NOW()
            WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param("sssssssii", $item_code, $product_name, $description, 
            $category, $unit, $specification, $main_image_path, $product_id, $supplier_id);
        $stmt->execute();
        
        // Delete existing variants and sizes
        $conn->query("DELETE FROM supplier_variant_sizes WHERE variant_id IN (SELECT id FROM supplier_product_variants WHERE product_id = $product_id)");
        $conn->query("DELETE FROM supplier_product_variants WHERE product_id = $product_id");
        
        // Add new variants
        $colors = $_POST['color'] ?? [];
        $prices = $_POST['price'] ?? [];
        $sizes = $_POST['size'] ?? [];
        $stocks = $_POST['stock'] ?? [];
        $variant_imgs = $_FILES['variant_image'] ?? null;
        
        for ($i = 0; $i < count($colors); $i++) {
            $color = trim($colors[$i] ?? '');
            $price = floatval($prices[$i] ?? 0);
            $size = trim($sizes[$i] ?? '');
            $stock = intval($stocks[$i] ?? 0);
            
            if (!$color || $price <= 0 || !$size || $stock <= 0) continue;
            
            // Handle variant image
            $variant_image = '';
            if (!empty($variant_imgs['name'][$i])) {
                $variant_image = save_as_webp($variant_imgs['tmp_name'][$i], $variant_imgs['name'][$i]);
            }
            
            // Insert variant
            $vstmt = $conn->prepare("INSERT INTO supplier_product_variants (product_id, color, price, image) VALUES (?, ?, ?, ?)");
            $vstmt->bind_param("isds", $product_id, $color, $price, $variant_image);
            $vstmt->execute();
            $variant_id = $vstmt->insert_id;
            
            // Insert size
            $sstmt = $conn->prepare("INSERT INTO supplier_variant_sizes (variant_id, size, stock) VALUES (?, ?, ?)");
            $sstmt->bind_param("isi", $variant_id, $size, $stock);
            $sstmt->execute();
        }
        
        $conn->commit();
        $conn->autocommit(true);
        
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?updated=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        $error_message = "Update failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-4xl mx-auto mt-10 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-orange-600">Edit Product</h2>
              <a href="suppliercompany.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
  ← Back to Products
</a>

            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Main Product Info -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Main Product Info</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="text" name="item_code" value="<?= htmlspecialchars($product['item_code'] ?? '') ?>" placeholder="Item Code" required class="border rounded px-3 py-2 w-full">
                        <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name'] ?? '') ?>" placeholder="Product Name" required class="border rounded px-3 py-2 w-full">
                    </div>
                    <textarea name="description" placeholder="Description" required class="border rounded px-3 py-2 w-full mt-4 h-24 resize-none"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <input type="text" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" placeholder="Category" class="border rounded px-3 py-2 w-full">
                        <input type="text" name="unit" value="<?= htmlspecialchars($product['unit'] ?? '') ?>" placeholder="Unit" class="border rounded px-3 py-2 w-full">
                    </div>
                    <textarea name="specification" placeholder="Main Specification" class="border rounded px-3 py-2 w-full mt-4 h-20 resize-none"><?= htmlspecialchars($product['specification'] ?? '') ?></textarea>

                    <div class="mt-4">
                        <label class="block text-sm font-medium">Current Image:</label>
                        <?php if ($product['image']): ?>
                            <img src="<?= htmlspecialchars($product['image'] ?? '') ?>" alt="Current Image" class="w-32 h-32 object-cover rounded mt-2">
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">No image</p>
                        <?php endif; ?>
                        <label class="block mt-2 text-sm font-medium">Update Image (JPG/PNG only, Max 15MB):</label>
                        <input type="file" name="main_image" accept="image/jpeg,image/png" class="block mt-1">
                    </div>
                </div>

                <!-- Variants -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Variants</h3>
                    <div id="variants" class="space-y-4">
                        <?php foreach ($variants as $variant): ?>
                            <?php foreach ($variant['sizes_array'] as $size_data): ?>
                                <div class="variant grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
                                    <input type="text" name="size[]" value="<?= htmlspecialchars($size_data['size'] ?? '') ?>" placeholder="Size" required class="border rounded px-2 py-1">
                                    <input type="text" name="color[]" value="<?= htmlspecialchars($variant['color'] ?? '') ?>" placeholder="Color" required class="border rounded px-2 py-1">
                                    <input type="number" name="stock[]" value="<?= htmlspecialchars($size_data['stock'] ?? '0') ?>" placeholder="Stock" required class="border rounded px-2 py-1">
                                    <input type="number" name="price[]" value="<?= htmlspecialchars($variant['price'] ?? '0') ?>" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
                                    <input type="file" name="variant_image[]" accept="image/jpeg,image/png" class="text-xs">
                                    <div class="flex justify-center">
                                        <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-xl font-bold">🗑</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <?php if (empty($variants)): ?>
                            <div class="variant grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
                                <input type="text" name="size[]" placeholder="Size" required class="border rounded px-2 py-1">
                                <input type="text" name="color[]" placeholder="Color" required class="border rounded px-2 py-1">
                                <input type="number" name="stock[]" placeholder="Stock" required class="border rounded px-2 py-1">
                                <input type="number" name="price[]" placeholder="Price" step="0.01" required class="border rounded px-2 py-1">
                                <input type="file" name="variant_image[]" accept="image/jpeg,image/png" class="text-xs">
                                <div class="flex justify-center">
                                    <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-xl font-bold">🗑</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addVariant()" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded hover:bg-blue-600">
                        + Add Variant
                    </button>
                </div>

                <div class="pt-6 flex space-x-4">
                    <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded">
                        Update Product
                    </button>
                    <a href="javascript:history.back()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-center">
                        Cancel
                    </a>
                </div>
            </form>
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
                    <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-xl font-bold">🗑</button>
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