<?php
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to pre-order.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $selected_type = trim($_POST['selected_type'] ?? '');
        $selected_variant = trim($_POST['selected_variant'] ?? '');
        $selected_color_id = (int)($_POST['selected_color_id'] ?? 0);
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        $user_id = $_SESSION['user_id'];

        // DEBUG: Log all received POST data
        error_log("=== CART ADD DEBUG START ===");
        error_log("POST Data: " . print_r($_POST, true));
        error_log("product_id: " . $product_id);
        error_log("selected_type: " . $selected_type);
        error_log("selected_variant: " . $selected_variant);
        error_log("selected_color_id: " . $selected_color_id);
        error_log("variant_id: " . $variant_id);

        if (!$product_id) throw new Exception('Product ID is required.');

        $stmt = $conn->prepare("SELECT product_name, codename FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) throw new Exception('Product not found.');

        $type_name = $variant_name = $color_name = $size = '';
        $color_price = 0;
        $variant_price = 0;
        $discount_percent = 0;
        $discount_fixed = 0;
        $variant_id_db = null;
        $color_id = null;
        $codename = $product['codename'];

        // ✅ Get color info
        if ($selected_color_id > 0) {
            $color_stmt = $conn->prepare("SELECT id, color_name, price FROM product_colors WHERE id = ? AND product_id = ?");
            $color_stmt->bind_param("ii", $selected_color_id, $product_id);
            $color_stmt->execute();
            $color_data = $color_stmt->get_result()->fetch_assoc();
            $color_stmt->close();

            if ($color_data) {
                $color_id = $color_data['id'];
                $color_name = $color_data['color_name'];
                $color_price = floatval($color_data['price']);
                
                // DEBUG: Log color data
                error_log("Color Data Found: " . print_r($color_data, true));
            } else {
                error_log("No color data found for color_id: " . $selected_color_id);
            }
        }

        // ✅ Get variant info
        if (!empty($selected_type) && !empty($selected_variant)) {
            $type_stmt = $conn->prepare("SELECT id FROM product_types WHERE product_id = ? AND type_name = ?");
            $type_stmt->bind_param("is", $product_id, $selected_type);
            $type_stmt->execute();
            $type_data = $type_stmt->get_result()->fetch_assoc();
            $type_stmt->close();

            if ($type_data) {
                $type_id = $type_data['id'];
                $type_name = $selected_type;
                error_log("Type ID found: " . $type_id);

                $variant_stmt = $conn->prepare("SELECT id, namevariant, size, price, percent, discount FROM product_variants WHERE type_id = ? AND namevariant = ?");
                $variant_stmt->bind_param("is", $type_id, $selected_variant);
                $variant_stmt->execute();
                $variant_data = $variant_stmt->get_result()->fetch_assoc();
                $variant_stmt->close();

                if ($variant_data) {
                    $variant_id_db = $variant_data['id'];
                    $variant_name = $variant_data['namevariant'];
                    $size = $variant_data['size'];
                    $variant_price = floatval($variant_data['price']);
                    $discount_percent = floatval($variant_data['percent']);
                    $discount_fixed = floatval($variant_data['discount']);
                    
                    // DEBUG: Log variant data
                    error_log("Variant Data Found: " . print_r($variant_data, true));
                } else {
                    error_log("No variant data found for type_id: " . $type_id . ", namevariant: " . $selected_variant);
                }
            } else {
                error_log("No type data found for product_id: " . $product_id . ", type_name: " . $selected_type);
            }
        }

        // ✅ Fallback if variant_id is directly passed
        if (!$variant_id_db && $variant_id > 0) {
            $variant_stmt = $conn->prepare("SELECT v.id, v.namevariant, v.size, v.price, v.percent, v.discount, t.type_name 
                                            FROM product_variants v
                                            JOIN product_types t ON v.type_id = t.id
                                            WHERE v.id = ?");
            $variant_stmt->bind_param("i", $variant_id);
            $variant_stmt->execute();
            $variant_data = $variant_stmt->get_result()->fetch_assoc();
            $variant_stmt->close();

            if ($variant_data) {
                $variant_id_db = $variant_data['id'];
                $variant_name = $variant_data['namevariant'];
                $size = $variant_data['size'];
                $type_name = $variant_data['type_name'];
                $variant_price = floatval($variant_data['price']);
                $discount_percent = floatval($variant_data['percent']);
                $discount_fixed = floatval($variant_data['discount']);
                
                // DEBUG: Log fallback variant data
                error_log("Fallback Variant Data Found: " . print_r($variant_data, true));
            } else {
                error_log("No fallback variant data found for variant_id: " . $variant_id);
            }
        }

        // ✅ DETAILED PRICE CALCULATION DEBUG
        error_log("=== PRICE CALCULATION DEBUG ===");
        error_log("Raw color_price: " . $color_price);
        error_log("Raw variant_price: " . $variant_price);
        error_log("Raw discount_percent: " . $discount_percent);
        error_log("Raw discount_fixed: " . $discount_fixed);

        // Step 1: Calculate base price (color + variant)
        $base_price = $color_price + $variant_price;
        error_log("Base price (color + variant): " . $base_price);
        
        // Step 2: Apply discounts to the base price
        $final_price = $base_price;
        
        // Apply percentage discount first (if any)
        if ($discount_percent > 0) {
            $percentage_discount = $base_price * ($discount_percent / 100);
            $final_price = $base_price - $percentage_discount;
            error_log("Percentage discount applied: " . $percentage_discount . " (". $discount_percent . "%)");
            error_log("Price after percentage discount: " . $final_price);
        }
        
        // Apply fixed discount as percentage (if any)
        if ($discount_fixed > 0) {
            $fixed_discount_amount = $base_price * ($discount_fixed / 100);
            $final_price = $final_price - $fixed_discount_amount;
            error_log("Fixed discount applied as percentage: " . $discount_fixed . "% = ₱" . $fixed_discount_amount);
            error_log("Price after fixed discount: " . $final_price);
        }
        
        // Ensure price is not negative
        $final_price = max($final_price, 0);
        error_log("Final price (after ensuring non-negative): " . $final_price);
        
        // Round to 2 decimal places
        $price = round($final_price, 2);
        error_log("Final rounded price: " . $price);
        error_log("=== PRICE CALCULATION DEBUG END ===");

        if ($price <= 0) throw new Exception('Invalid price computation.');

        // ✅ Check cart for existing item
        $check_stmt = $conn->prepare("
            SELECT id, quantity FROM user_cart_items
            WHERE user_id = ? AND product_id = ? AND color_id <=> ? AND variant_id <=> ?
        ");
        $check_stmt->bind_param("iiii", $user_id, $product_id, $color_id, $variant_id_db);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            $new_qty = $existing['quantity'] + 1;
            $update_stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ?, price = ?, added_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("idi", $new_qty, $price, $existing['id']);
            $update_stmt->execute();
            $update_stmt->close();
            error_log("Updated existing cart item. New quantity: " . $new_qty);
        } else {
            $insert_stmt = $conn->prepare("
                INSERT INTO user_cart_items (
                    user_id, product_id, color_id, variant_id, quantity, price,
                    type_name, variant_name, color_name, size, codename, added_at
                ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert_stmt->bind_param(
                "iiiiisssss",
                $user_id, $product_id, $color_id, $variant_id_db, $price,
                $type_name, $variant_name, $color_name, $size, $codename
            );
            $insert_stmt->execute();
            $insert_stmt->close();
            error_log("Inserted new cart item.");
        }

        error_log("=== CART ADD DEBUG END ===");

        echo json_encode([
            'success' => true,
            'message' => 'Added to cart successfully!',
            'cart_count' => getCartCount($conn, $user_id),
            'item_added' => [
                'name' => $product['product_name'],
                'color' => $color_name ?: '—',
                'type' => $type_name ?: '—',
                'variant' => $variant_name ?: '—',
                'size' => $size ?: '—',
                'price' => $price,
                'quantity' => $existing ? $new_qty : 1
            ],
            'debug_info' => [
                'color_price' => $color_price,
                'variant_price' => $variant_price,
                'base_price' => $base_price,
                'discount_percent' => $discount_percent,
                'discount_fixed' => $discount_fixed,
                'final_price' => $price,
                'color_id' => $color_id,
                'variant_id_db' => $variant_id_db
            ]
        ]);
    } catch (Exception $e) {
        error_log("Cart Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

function getCartCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total FROM user_cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($result['total'] ?? 0);
}
?>