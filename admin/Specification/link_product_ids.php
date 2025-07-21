<?php
function link_variant_to_product(mysqli $conn, array &$variant): ?int {
    if (!isset($variant['product_id']) || !$variant['product_id']) {
        $name = trim($variant['namevariant']);

        // Extract base name only (before ' - ') for matching
        $baseName = explode(' -', $name)[0];
        $like = $baseName . '%'; // e.g. 'SYCJ-825%'

        // ✅ Remove COLLATE to avoid binary charset issue
        $stmt = $conn->prepare("SELECT id FROM products WHERE product_name LIKE ? LIMIT 1");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $product = $res->fetch_assoc();
        $stmt->close();

        if ($product) {
            $productId = (int)$product['id'];
            $update = $conn->prepare("UPDATE product_variants SET product_id = ? WHERE id = ?");
            $update->bind_param("ii", $productId, $variant['id']);
            if ($update->execute()) {
                $update->close();
                $variant['product_id'] = $productId;
                return $productId;
            }
            $update->close();
        }
    }
    return null;
}

?>
