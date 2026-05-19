<?php
// add_to_cart.php

include ROOT_PATH . '/connection/connect.php';
header('Content-Type: application/json');

// Reset AUTO_INCREMENT if table is empty
$tables = ['user_cart_items'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

// Restore session if remember_token exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
    }
    $stmt->close();
}

$user_id = $_SESSION['user_id'] ?? null;
$is_guest = !$user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ✅ STEP 1: GET ALL POST DATA FIRST
        $product_id = (int)($_POST['product_id'] ?? 0);
        $selected_type = trim($_POST['selected_type'] ?? '');
        $selected_variant = trim($_POST['selected_variant'] ?? '');
        $selected_color_id = (int)($_POST['selected_color_id'] ?? 0);
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 9999) $quantity = 9999;

        if (!$product_id) throw new Exception('Product ID is required.');

        // Get basic product data + min_order_qty
        $stmt = $conn->prepare("SELECT product_name, codename, descrip6, descrip7, min_order_qty FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) throw new Exception('Product not found.');

        // ✅ GET MIN ORDER QTY (default to 1 if not set)
        $min_order_qty = (int)($product['min_order_qty'] ?? 1);
        if ($min_order_qty < 1) $min_order_qty = 1;

        $type_name = $variant_name = $color_name = $size = '';
        $color_price = 0;
        $variant_price = 0;
        $variant_id_db = null;
        $color_id = null;
        $codename = $product['codename'];
        $unit = $product['descrip6'] ?? '';
        $specification = $product['descrip7'] ?? '';

        // ✅ STEP 2: GET COLOR INFO
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
            }
        }

        // ✅ STEP 3: GET VARIANT INFO
        if (!empty($selected_type) && !empty($selected_variant)) {
            $type_stmt = $conn->prepare("SELECT id FROM product_types WHERE product_id = ? AND type_name = ?");
            $type_stmt->bind_param("is", $product_id, $selected_type);
            $type_stmt->execute();
            $type_data = $type_stmt->get_result()->fetch_assoc();
            $type_stmt->close();

            if ($type_data) {
                $type_id = $type_data['id'];
                $type_name = $selected_type;

                $variant_stmt = $conn->prepare("SELECT id, namevariant, size, price FROM product_variants WHERE type_id = ? AND size = ?");
                $variant_stmt->bind_param("is", $type_id, $selected_variant);
                $variant_stmt->execute();
                $variant_data = $variant_stmt->get_result()->fetch_assoc();
                $variant_stmt->close();

                if ($variant_data) {
                    $variant_id_db   = $variant_data['id'];
                    $variant_name    = $variant_data['namevariant'];
                    $size            = $variant_data['size'];
                    $variant_price   = floatval($variant_data['price']);
                }
            }
        }

        // Fallback by variant_id
        if (!$variant_id_db && $variant_id > 0) {
            $variant_stmt = $conn->prepare("
                SELECT v.id, v.namevariant, v.size, v.price, t.type_name 
                FROM product_variants v 
                JOIN product_types t ON v.type_id = t.id 
                WHERE v.id = ?
            ");
            $variant_stmt->bind_param("i", $variant_id);
            $variant_stmt->execute();
            $variant_data = $variant_stmt->get_result()->fetch_assoc();
            $variant_stmt->close();

            if ($variant_data) {
                $variant_id_db  = $variant_data['id'];
                $variant_name   = $variant_data['namevariant'];
                $size           = $variant_data['size'];
                $type_name      = $variant_data['type_name'];
                $variant_price  = floatval($variant_data['price']);
            }
        }

        // ✅ STEP 4: VALIDATE COLOR + VARIANT SELECTED
        if (!$variant_id_db || !$color_id) {
            throw new Exception('Please select both color and size');
        }

        // ✅ STEP 5: CHECK MINIMUM ORDER QUANTITY
        if ($quantity < $min_order_qty) {
            echo json_encode([
                'success' => false,
                'message' => "Minimum order for this product is {$min_order_qty} pcs. Please add at least {$min_order_qty} piece(s).",
                'min_order_qty' => $min_order_qty
            ]);
            exit;
        }

        // ✅ STEP 6: FETCH AVAILABLE STOCK
        $stock_stmt = $conn->prepare("
            SELECT stock_quantity 
            FROM product_variant_colors 
            WHERE variant_id = ? AND color_id = ?
        ");
        $stock_stmt->bind_param("ii", $variant_id_db, $color_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();

        $available_stock = 0;
        if ($stock_result->num_rows > 0) {
            $stock_row = $stock_result->fetch_assoc();
            $available_stock = intval($stock_row['stock_quantity']);
        }
        $stock_stmt->close();

        // ✅ VALIDATION: Out of stock
        if ($available_stock <= 0) {
            echo json_encode([
                'success' => false,
                'message' => "This item is OUT OF STOCK. No units available."
            ]);
            exit;
        }

        // ✅ VALIDATION: Requested qty exceeds stock
        if ($quantity > $available_stock) {
            echo json_encode([
                'success' => false,
                'message' => "Not enough stock! You requested {$quantity} units but only {$available_stock} available. Max: {$available_stock}"
            ]);
            exit;
        }

        // ✅ STEP 7: CALCULATE PRICE
        $price = round($color_price + $variant_price, 2);
        if ($price <= 0) throw new Exception('Invalid price computation.');

        // ✅ STEP 8: SAVE TO CART (guest or logged in)
        if ($is_guest) {
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }

            $item_key = md5($product_id . '_' . $color_id . '_' . $variant_id_db);

            if (isset($_SESSION['guest_cart'][$item_key])) {
                $new_qty = $_SESSION['guest_cart'][$item_key]['quantity'] + $quantity;

                // ✅ CHECK MIN ORDER QTY ON UPDATE TOO
                if ($new_qty < $min_order_qty) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Minimum order for this product is {$min_order_qty} pcs.",
                        'min_order_qty' => $min_order_qty
                    ]);
                    exit;
                }

                // ✅ CHECK TOTAL DOESN'T EXCEED STOCK
                if ($new_qty > $available_stock) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Cannot add! You already have {$_SESSION['guest_cart'][$item_key]['quantity']} in cart. Adding {$quantity} more would exceed stock limit of {$available_stock}. Max total: {$available_stock}"
                    ]);
                    exit;
                }

                $_SESSION['guest_cart'][$item_key]['quantity'] = $new_qty;
                $action_message = "Added {$quantity} more piece(s) to cart. Total: {$new_qty}";
                $final_quantity = $new_qty;
            } else {
                $_SESSION['guest_cart'][$item_key] = [
                    'product_id'  => $product_id,
                    'product_name'=> $product['product_name'],
                    'color_id'    => $color_id,
                    'color_name'  => $color_name,
                    'variant_id'  => $variant_id_db,
                    'type_name'   => $type_name,
                    'variant_name'=> $variant_name,
                    'size'        => $size,
                    'codename'    => $codename,
                    'descrip6'    => $unit,
                    'descrip7'    => $specification,
                    'price'       => $price,
                    'quantity'    => $quantity
                ];
                $action_message = "Added {$quantity} piece(s) to cart";
                $final_quantity = $quantity;
            }

            echo json_encode([
                'success'    => true,
                'is_guest'   => true,
                'message'    => $action_message,
                'warning'    => 'You are in guest mode. Please login to checkout.',
                'cart_count' => count($_SESSION['guest_cart']),
                'available_stock' => $available_stock,
                'min_order_qty'   => $min_order_qty,
                'item_added' => [
                    'name'          => $product['product_name'],
                    'color'         => $color_name ?: '—',
                    'type'          => $type_name ?: '—',
                    'variant'       => $variant_name ?: '—',
                    'size'          => $size ?: '—',
                    'unit'          => $unit ?: '—',
                    'specification' => $specification ?: '—',
                    'price'         => $price,
                    'quantity'      => $final_quantity,
                    'quantity_added'=> $quantity
                ]
            ]);
            exit;
        }

        // LOGGED IN USER
        $verify_stmt = $conn->prepare("SELECT ud.is_verified FROM user_details ud WHERE ud.user_id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $verify_stmt->close();

        if ($verify_result->num_rows === 0) {
            http_response_code(403);
            echo json_encode([
                'success'    => false,
                'message'    => 'Your account details are incomplete. Please complete your profile verification to add items to cart.',
                'error_code' => 'PROFILE_INCOMPLETE'
            ]);
            exit;
        }

        $user_verification = $verify_result->fetch_assoc();
        if (!$user_verification['is_verified'] || $user_verification['is_verified'] == 0) {
            http_response_code(403);
            echo json_encode([
                'success'    => false,
                'message'    => 'Your account is not verified. Please verify your account to add items to cart.',
                'error_code' => 'ACCOUNT NOT VERIFIED'
            ]);
            exit;
        }

        $check_stmt = $conn->prepare("SELECT id, quantity FROM user_cart_items WHERE user_id = ? AND product_id = ? AND color_id <=> ? AND variant_id <=> ?");
        $check_stmt->bind_param("iiii", $user_id, $product_id, $color_id, $variant_id_db);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;

            // ✅ CHECK MIN ORDER QTY ON UPDATE
            if ($new_qty < $min_order_qty) {
                echo json_encode([
                    'success' => false,
                    'message' => "Minimum order for this product is {$min_order_qty} pcs.",
                    'min_order_qty' => $min_order_qty
                ]);
                exit;
            }

            // ✅ CHECK TOTAL DOESN'T EXCEED STOCK
            if ($new_qty > $available_stock) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot add! You already have {$existing['quantity']} in cart. Adding {$quantity} more would exceed stock limit of {$available_stock}. Max total: {$available_stock}"
                ]);
                exit;
            }

            $update_stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ?, price = ?, added_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("idi", $new_qty, $price, $existing['id']);
            $update_stmt->execute();
            $update_stmt->close();

            $final_quantity = $new_qty;
            $action_message = "Added {$quantity} more piece(s) to cart. Total: {$new_qty}";
        } else {
            $insert_stmt = $conn->prepare("
                INSERT INTO user_cart_items (
                    user_id, product_id, color_id, variant_id, quantity, price,
                    type_name, variant_name, color_name, size, codename, descrip6, descrip7, added_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert_stmt->bind_param(
                "iiiidssssssss",
                $user_id, $product_id, $color_id, $variant_id_db, $quantity, $price,
                $type_name, $variant_name, $color_name, $size, $codename,
                $unit, $specification
            );
            $insert_stmt->execute();
            $insert_stmt->close();

            $final_quantity = $quantity;
            $action_message = "Added {$quantity} piece(s) to cart";
        }

        echo json_encode([
            'success'    => true,
            'is_guest'   => false,
            'message'    => $action_message,
            'cart_count' => getCartCount($conn, $user_id),
            'available_stock' => $available_stock,
            'min_order_qty'   => $min_order_qty,
            'item_added' => [
                'name'          => $product['product_name'],
                'color'         => $color_name ?: '—',
                'type'          => $type_name ?: '—',
                'variant'       => $variant_name ?: '—',
                'size'          => $size ?: '—',
                'unit'          => $unit ?: '—',
                'specification' => $specification ?: '—',
                'price'         => $price,
                'quantity'      => $final_quantity,
                'quantity_added'=> $quantity
            ]
        ]);

    } catch (Exception $e) {
        error_log("Cart Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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