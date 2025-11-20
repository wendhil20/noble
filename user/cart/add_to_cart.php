<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

// ===== GUEST MODE: Allow guests to add to cart =====
// No user_id check here - guests can proceed!

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

// ===== GUEST MODE: Check if user is logged in after this point =====
$user_id = $_SESSION['user_id'] ?? null;
$is_guest = !$user_id; // True if guest, False if logged in

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $selected_type = trim($_POST['selected_type'] ?? '');
        $selected_variant = trim($_POST['selected_variant'] ?? '');
        $selected_color_id = (int)($_POST['selected_color_id'] ?? 0);
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        
        // ✅ GET QUANTITY FROM POST (Default to 1 if not provided)
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 9999) $quantity = 9999;

        if (!$product_id) throw new Exception('Product ID is required.');

        // Get basic product data including descrip6 and descrip7
        $stmt = $conn->prepare("SELECT product_name, codename, descrip6, descrip7 FROM products WHERE id = ?");
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
        $color_id = null;  // ✅ Initialize as NULL
        $codename = $product['codename'];
        $unit = $product['descrip6'] ?? '';
        $specification = $product['descrip7'] ?? '';

        // ✅ GET COLOR INFO AND STORE color_id
        if ($selected_color_id > 0) {
            $color_stmt = $conn->prepare("SELECT id, color_name, price FROM product_colors WHERE id = ? AND product_id = ?");
            $color_stmt->bind_param("ii", $selected_color_id, $product_id);
            $color_stmt->execute();
            $color_data = $color_stmt->get_result()->fetch_assoc();
            $color_stmt->close();

            if ($color_data) {
                $color_id = $color_data['id'];  // ✅ STORE THE COLOR ID
                $color_name = $color_data['color_name'];
                $color_price = floatval($color_data['price']);
            }
        }

        // Get variant info via type and variant name
        if (!empty($selected_type) && !empty($selected_variant)) {
            $type_stmt = $conn->prepare("SELECT id FROM product_types WHERE product_id = ? AND type_name = ?");
            $type_stmt->bind_param("is", $product_id, $selected_type);
            $type_stmt->execute();
            $type_data = $type_stmt->get_result()->fetch_assoc();
            $type_stmt->close();

            if ($type_data) {
                $type_id = $type_data['id'];
                $type_name = $selected_type;

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
                }
            }
        }

        // Fallback by variant_id if previous method fails
        if (!$variant_id_db && $variant_id > 0) {
            $variant_stmt = $conn->prepare("SELECT v.id, v.namevariant, v.size, v.price, v.percent, v.discount, t.type_name FROM product_variants v JOIN product_types t ON v.type_id = t.id WHERE v.id = ?");
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
            }
        }

        // Final price calculation
        $discounted_variant_price = $variant_price;
        if ($discount_percent > 0) {
            $discounted_variant_price -= $variant_price * ($discount_percent / 100);
        }
        if ($discount_fixed > 0) {
            $discounted_variant_price -= $variant_price * ($discount_fixed / 100);
        }
        $discounted_variant_price = max($discounted_variant_price, 0);

        $price = round($color_price + $discounted_variant_price, 2);
        if ($price <= 0) throw new Exception('Invalid price computation.');

        // ===== GUEST MODE: If guest, save to temp session instead of database =====
        if ($is_guest) {
            // Initialize guest cart in session
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }

            // ✅ UPDATED: Include color_id in the unique key
            $item_key = md5($product_id . '_' . $color_id . '_' . $variant_id_db);

            // Check if item already exists
            if (isset($_SESSION['guest_cart'][$item_key])) {
                $_SESSION['guest_cart'][$item_key]['quantity'] += $quantity;
                $action_message = "Added $quantity more piece(s) to cart. Total: " . $_SESSION['guest_cart'][$item_key]['quantity'];
                $final_quantity = $_SESSION['guest_cart'][$item_key]['quantity'];
            } else {
                // ✅ UPDATED: Store color_id in guest cart
                $_SESSION['guest_cart'][$item_key] = [
                    'product_id' => $product_id,
                    'product_name' => $product['product_name'],
                    'color_id' => $color_id,  // ✅ STORE COLOR_ID
                    'color_name' => $color_name,
                    'variant_id' => $variant_id_db,
                    'type_name' => $type_name,
                    'variant_name' => $variant_name,
                    'size' => $size,
                    'codename' => $codename,
                    'descrip6' => $unit,
                    'descrip7' => $specification,
                    'price' => $price,
                    'quantity' => $quantity
                ];
                $action_message = "Added $quantity piece(s) to cart";
                $final_quantity = $quantity;
            }

            // Return guest response with warning
            echo json_encode([
                'success' => true,
                'is_guest' => true,
                'message' => $action_message,
                'warning' => 'You are in guest mode. Please login to checkout.',
                'cart_count' => count($_SESSION['guest_cart']),
                'item_added' => [
                    'name' => $product['product_name'],
                    'color' => $color_name ?: '—',
                    'type' => $type_name ?: '—',
                    'variant' => $variant_name ?: '—',
                    'size' => $size ?: '—',
                    'unit' => $unit ?: '—',
                    'specification' => $specification ?: '—',
                    'price' => $price,
                    'quantity' => $final_quantity,
                    'quantity_added' => $quantity
                ]
            ]);
            exit;
        }

        // ===== LOGGED IN USER: Normal database save =====
        
        // Check if user account is verified (only for logged in users)
        $verify_stmt = $conn->prepare("SELECT ud.is_verified FROM user_details ud WHERE ud.user_id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $verify_stmt->close();

        if ($verify_result->num_rows === 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Your account details are incomplete. Please complete your profile verification to add items to cart.',
                'error_code' => 'PROFILE_INCOMPLETE'
            ]);
            exit;
        }

        $user_verification = $verify_result->fetch_assoc();
        if (!$user_verification['is_verified'] || $user_verification['is_verified'] == 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Your account is not verified. Please verify your account to add items to cart.',
                'error_code' => 'ACCOUNT_NOT_VERIFIED'
            ]);
            exit;
        }

        // ✅ UPDATED: Include color_id in the comparison
        $check_stmt = $conn->prepare("SELECT id, quantity FROM user_cart_items WHERE user_id = ? AND product_id = ? AND color_id <=> ? AND variant_id <=> ?");
        $check_stmt->bind_param("iiii", $user_id, $product_id, $color_id, $variant_id_db);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            // ✅ ADD THE REQUESTED QUANTITY to existing quantity
            $new_qty = $existing['quantity'] + $quantity;
            $update_stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ?, price = ?, added_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("idi", $new_qty, $price, $existing['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            $final_quantity = $new_qty;
            $action_message = "Added $quantity more piece(s) to cart. Total: $new_qty";
        } else {
            // ✅ UPDATED: INSERT with color_id
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
            $action_message = "Added $quantity piece(s) to cart";
        }

        echo json_encode([
            'success' => true,
            'is_guest' => false,
            'message' => $action_message,
            'cart_count' => getCartCount($conn, $user_id),
            'item_added' => [
                'name' => $product['product_name'],
                'color' => $color_name ?: '—',
                'type' => $type_name ?: '—',
                'variant' => $variant_name ?: '—',
                'size' => $size ?: '—',
                'unit' => $unit ?: '—',
                'specification' => $specification ?: '—',
                'price' => $price,
                'quantity' => $final_quantity,
                'quantity_added' => $quantity
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

// Helper function to get total cart item count
function getCartCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total FROM user_cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($result['total'] ?? 0);
}
?>