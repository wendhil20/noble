<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/cartview");
    exit;
}

$user_id = $_SESSION['user_id'];
$key = $_GET['key'] ?? '';

if (!$key) {
    header("Location: " . BASE_URL . "/cartview");
    exit;
}

// ✅ Option 1: If you're passing the row `id` directly (recommended approach)
if (is_numeric($key)) {
    $cart_item_id = (int)$key;

    $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_item_id, $user_id);
    $stmt->execute();
    $stmt->close();

} else {
    // ✅ Option 2: If you're passing a composite key like "123_color_3_type_X_variant_Y"
    // You’ll need to parse that key and extract identifiers

    // Example key format: 5_color_2_type_Metallic_variant_24
    preg_match('/^(\d+)(?:_color_(\d+))?(?:_type_(.+?)_variant_(.+))?(?:_variant_(\d+))?$/', $key, $matches);

    $product_id = (int)($matches[1] ?? 0);
    $color_id = isset($matches[2]) ? (int)$matches[2] : null;
    $variant_id = isset($matches[5]) ? (int)$matches[5] : null;

    if (!$variant_id && isset($matches[4])) {
        // You may need to look up the variant ID by name
        $variant_name = $matches[4];
        $stmt = $conn->prepare("SELECT id FROM product_variants WHERE namevariant = ?");
        $stmt->bind_param("s", $variant_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $variant_row = $res->fetch_assoc();
        $variant_id = $variant_row['id'] ?? null;
        $stmt->close();
    }

    // Proceed to delete matching cart item
    $stmt = $conn->prepare("
        DELETE FROM user_cart_items 
        WHERE user_id = ? AND product_id = ? 
        AND (color_id <=> ?) 
        AND (variant_id <=> ?)
    ");
    $stmt->bind_param("iiii", $user_id, $product_id, $color_id, $variant_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . BASE_URL . "/cartview");
exit;
