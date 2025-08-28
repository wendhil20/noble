<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['supplier', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$_SESSION['last_activity'] = time();

define('MAX_FILE_SIZE', 15 * 1024 * 1024); // 15MB

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['supplier_products', 'supplier_product_variants', 'supplier_variant_sizes'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

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

// Get logged in supplier
$user_identifier = $_SESSION['noble_user'] ?? null;
if (!$user_identifier) die("User not found in session.");

// Check if it's email or ID and get supplier_id
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

// Get main product data
$item_code = $_POST['item_code'] ?? '';
$product_name = $_POST['product_name'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';

if (!$item_code || !$product_name || !$description) {
    die("Required fields missing.");
}

// Handle main image
$main_image_path = '';
if (!empty($_FILES['main_image']['name'])) {
    $main_image_path = save_as_webp($_FILES['main_image']['tmp_name'], $_FILES['main_image']['name']);
    if (!$main_image_path) die("Invalid main image (JPG/PNG only, max 15MB).");
}

try {
    $conn->autocommit(false);
    
    // Insert main product - FIXED: Now includes unit and specification in supplier_products table
    $stmt = $conn->prepare("INSERT INTO supplier_products
        (supplier_id, item_code, product_name, description, category, image, unit, specification, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->bind_param("isssssss", $supplier_id, $item_code, $product_name, $description, $category, $main_image_path, $unit, $specification);
    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();
    
    // Handle variants - FIXED: Removed unit and specification from variants
    $sizes = $_POST['size'] ?? [];
    $colors = $_POST['color'] ?? [];
    $stocks = $_POST['stock'] ?? [];
    $prices = $_POST['price'] ?? [];
    $variant_imgs = $_FILES['variant_image'] ?? null;
   
    $count = count($colors);
    for ($i = 0; $i < $count; $i++) {
        $size = trim($sizes[$i] ?? '');
        $color = trim($colors[$i] ?? '');
        $stock = intval($stocks[$i] ?? 0);
        $price = floatval($prices[$i] ?? 0);
       
        // Skip if required fields are missing
        if (!$color || $price <= 0 || !$size || $stock <= 0) {
            continue;
        }
        
        // Handle variant image
        $variant_image = '';
        if (!empty($variant_imgs['name'][$i])) {
            $variant_image = save_as_webp($variant_imgs['tmp_name'][$i], $variant_imgs['name'][$i]);
            if (!$variant_image) {
                throw new Exception("Invalid variant image at index $i.");
            }
        }
        
        // Insert variant
        $vstmt = $conn->prepare("INSERT INTO supplier_product_variants (product_id, color, price, image) VALUES (?, ?, ?, ?)");
        $vstmt->bind_param("isds", $product_id, $color, $price, $variant_image);
        $vstmt->execute();
        $variant_id = $vstmt->insert_id;
        $vstmt->close();
        
        // FIXED: Insert size without unit and specification (now stored in main product)
        $sstmt = $conn->prepare("INSERT INTO supplier_variant_sizes (variant_id, size, stock) VALUES (?, ?, ?)");
        $sstmt->bind_param("isi", $variant_id, $size, $stock);
        $sstmt->execute();
        $sstmt->close();
    }
    
    $conn->commit();
    $conn->autocommit(true);
    
    echo "<h2 class='text-green-600 text-xl font-bold text-center mt-10'>Product and variants uploaded successfully!</h2>";
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    echo "<h2 class='text-red-600 text-xl font-bold text-center mt-10'>Upload Failed: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>