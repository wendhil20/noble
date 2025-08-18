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
    $referer = $_SERVER['HTTP_REFERER'] ?? 'javascript:history.back()';
    header("Location: $referer?error=invalid_id");
    exit();
}

try {
    $conn->autocommit(false);
    
    // Verify product belongs to current supplier and get image paths
    $stmt = $conn->prepare("SELECT image FROM supplier_products WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $product_id, $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if (!$product) {
        $conn->rollback();
        $referer = $_SERVER['HTTP_REFERER'] ?? 'javascript:history.back()';
        header("Location: $referer?error=not_found");
        exit();
    }
    
    // Get variant images before deletion
    $variant_images = [];
    $stmt = $conn->prepare("SELECT image FROM supplier_product_variants WHERE product_id = ? AND image IS NOT NULL AND image != ''");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['image']) {
            $variant_images[] = $row['image'];
        }
    }
    
    // Delete from database (cascade will handle related records)
    // Delete sizes first
    $conn->query("DELETE FROM supplier_variant_sizes WHERE variant_id IN (SELECT id FROM supplier_product_variants WHERE product_id = $product_id)");
    
    // Delete variants
    $conn->query("DELETE FROM supplier_product_variants WHERE product_id = $product_id");
    
    // Delete main product
    $stmt = $conn->prepare("DELETE FROM supplier_products WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $product_id, $supplier_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to delete product or access denied.");
    }
    
    $conn->commit();
    $conn->autocommit(true);
    
    // Delete image files after successful database deletion
    if ($product['image'] && file_exists($product['image'])) {
        unlink($product['image']);
    }
    
    foreach ($variant_images as $img_path) {
        if (file_exists($img_path)) {
            unlink($img_path);
        }
    }
    
    // Redirect with success message
    $referer = $_SERVER['HTTP_REFERER'] ?? 'javascript:history.back()';
    header("Location: $referer?deleted=1");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    
    // Redirect with error message
    $referer = $_SERVER['HTTP_REFERER'] ?? 'javascript:history.back()';
    header("Location: $referer?error=" . urlencode($e->getMessage()));
    exit();
}
?>