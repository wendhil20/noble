<?php
// admin/qrcodeperproduct/product_images_handler.php
// THIS FILE: Updates PRODUCTS table only (product images and description)

session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php'; 
require_role(['productspecialist','superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: qrcodeitem.php");
    exit();
}

// GET PRODUCT ID FROM POST
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id <= 0) {
    $_SESSION['error_message'] = "Invalid product ID";
    header("Location: qrcodeitem.php");
    exit();
}

// ➤ VERIFY PRODUCT EXISTS
$verifyStmt = $conn->prepare("SELECT id, product_name FROM products WHERE id = ?");
$verifyStmt->bind_param("i", $product_id);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$verifyResult) {
    $_SESSION['error_message'] = "Product ID $product_id not found";
    error_log("ERROR: Product $product_id not found");
    header("Location: qrcodeitem.php");
    exit();
}

error_log("✓ Processing PRODUCT update for ID: $product_id - " . $verifyResult['product_name']);

// ➤ Helper: Convert image to WebP
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

// ➤ GET CURRENT PRODUCT IMAGES
$fetchStmt = $conn->prepare("SELECT product_images FROM products WHERE id = ?");
$fetchStmt->bind_param("i", $product_id);
$fetchStmt->execute();
$fetchResult = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

$productImages = [];
if ($fetchResult['product_images']) {
    $productImages = json_decode($fetchResult['product_images'], true) ?: [];
}

// ➤ GET DESCRIPTION
$descPic = trim($_POST['descriptionpic'] ?? '');
$uploadErrors = [];
$imageArray = $productImages;

// ➤ PROCESS NEW IMAGES
$fields = ['image1', 'image2', 'image3', 'image4'];
foreach ($fields as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
            $uploadErrors[] = "$field: File exceeds 5MB";
            continue;
        }

        $path = saveImageToFolder($_FILES[$field]);
        if ($path) {
            $imageArray[] = $path;
            error_log("✓ Image uploaded: $path");
        } else {
            $uploadErrors[] = "$field: Failed to process";
        }
    }
}

if (!empty($uploadErrors)) {
    $_SESSION['error_message'] = 'Upload failed: ' . implode(', ', $uploadErrors);
    error_log("Upload errors for product $product_id: " . implode(', ', $uploadErrors));
    header("Location: variant_edit.php?id=$product_id");
    exit();
}

// ➤ UPDATE PRODUCTS TABLE
$finalImages = empty($imageArray) ? NULL : json_encode($imageArray);

$updateStmt = $conn->prepare("UPDATE products SET descriptionpic = ?, product_images = ? WHERE id = ?");
if (!$updateStmt) {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    error_log("Prepare error: " . $conn->error);
    header("Location: variant_edit.php?id=$product_id");
    exit();
}

$updateStmt->bind_param("ssi", $descPic, $finalImages, $product_id);
if ($updateStmt->execute()) {
    $_SESSION['success_message'] = '✓ Product updated! ' . count($imageArray) . ' image(s) stored.';
    error_log("✓ Successfully updated PRODUCT $product_id");
} else {
    $_SESSION['error_message'] = '✗ Failed to update: ' . $updateStmt->error;
    error_log("✗ Update error for product $product_id: " . $updateStmt->error);
}
$updateStmt->close();

// ➤ VERIFY UPDATE
$verifyUpdateStmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
$verifyUpdateStmt->bind_param("i", $product_id);
$verifyUpdateStmt->execute();
$verifyUpdate = $verifyUpdateStmt->get_result()->fetch_assoc();
$verifyUpdateStmt->close();

if (!$verifyUpdate) {
    error_log("CRITICAL: Product $product_id disappeared!");
    $_SESSION['error_message'] = "CRITICAL: Product record lost!";
}

// Redirect back to SAME product
header("Location: variant_edit.php?id=$product_id");
exit();
?>