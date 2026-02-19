<?php
// checkout-qrph-create-order.php - Based on working paymongo-create-sessions.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_name("nobleuser");
session_start();

header('Content-Type: application/json');
ob_start();

try {
    require_once '../../connection/connect.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    $input = json_decode(file_get_contents("php://input"), true);
    error_log("QRPh Create Order Input: " . json_encode($input));

    if (!$input) throw new Exception('Invalid JSON input');

    $amount       = floatval($input['amount'] ?? 0);
    $delivery_fee = floatval($input['delivery_fee'] ?? 0);
    $qr_code_id   = trim($input['qr_code_id'] ?? '');

    if ($amount <= 0) throw new Exception('Invalid amount: ' . $amount);

    $user_id = intval($_SESSION['user_id']);

    // Get data from session (same as PayMongo)
    $customer_name      = trim($_SESSION['checkout_step1']['customer_name'] ?? '');
    $email              = trim($_SESSION['checkout_step1']['email'] ?? '');
    $mobile             = trim($_SESSION['checkout_step2']['mobile'] ?? '');
    $address            = trim($_SESSION['checkout_step2']['address'] ?? '');
    $zipcode            = trim($_SESSION['checkout_step2']['zipcode'] ?? '');
    $billing_address_id = !empty($_SESSION['checkout_step2']['billing_address_id']) ? intval($_SESSION['checkout_step2']['billing_address_id']) : null;
    $latitude           = !empty($_SESSION['checkout_step2']['latitude']) ? floatval($_SESSION['checkout_step2']['latitude']) : null;
    $longitude          = !empty($_SESSION['checkout_step2']['longitude']) ? floatval($_SESSION['checkout_step2']['longitude']) : null;
    $delivery_distance  = floatval($_SESSION['checkout_step3']['delivery_distance'] ?? 0);
    $delivery_type      = $_SESSION['checkout_step3']['delivery_type'] ?? 'delivery';
    $assigned_vehicle_type = $_SESSION['checkout_step3']['assigned_vehicle_type'] ?? null;

    $total_cubic_meters = floatval($_SESSION['checkout_step3']['total_cubic_meters'] ?? 0);
    $total_weight_kg    = floatval($_SESSION['checkout_step3']['total_weight_kg'] ?? 0);
    $total_width        = floatval($_SESSION['checkout_step3']['total_width'] ?? 0);
    $total_height       = floatval($_SESSION['checkout_step3']['total_height'] ?? 0);
    $total_length       = floatval($_SESSION['checkout_step3']['total_length'] ?? 0);

    // Validate vehicle (same as PayMongo)
    $assigned_vehicle_id = null;
    $assigned_vehicle_id_input = !empty($_SESSION['checkout_step3']['assigned_vehicle_id']) ? intval($_SESSION['checkout_step3']['assigned_vehicle_id']) : null;

    if ($delivery_type === 'delivery' && !is_null($assigned_vehicle_id_input) && $assigned_vehicle_id_input > 0) {
        $vehicle_check = $conn->query("SELECT id FROM transportify_vehicle_list WHERE id = " . intval($assigned_vehicle_id_input) . " LIMIT 1");
        if ($vehicle_check && $vehicle_check->num_rows > 0) {
            $assigned_vehicle_id = $assigned_vehicle_id_input;
        }
    }

    // Sales commission (same as PayMongo)
    $sales_commission_rate   = 0.00;
    $sales_commission_amount = 0.00;
    $sales_user_id           = null;
    $sales_referral_code     = null;

    $user_check = $conn->prepare("SELECT referred_by_code FROM users WHERE id = ? LIMIT 1");
    $user_check->bind_param("i", $user_id);
    $user_check->execute();
    $user_data = $user_check->get_result()->fetch_assoc();
    $user_check->close();

    if (!empty($user_data['referred_by_code'])) {
        $potential_code = $user_data['referred_by_code'];
        $sales_check = $conn->prepare("
            SELECT rc.user_id, rc.referral_code, na.commission_rate
            FROM referral_codes rc
            INNER JOIN nobleaccount na ON rc.user_id = na.id
            WHERE rc.referral_code = ? AND rc.is_active = 1 AND na.lvl = 'sales' AND na.commission_rate > 0
            LIMIT 1
        ");
        $sales_check->bind_param("s", $potential_code);
        $sales_check->execute();
        $sales_data = $sales_check->get_result()->fetch_assoc();
        $sales_check->close();

        if ($sales_data) {
            $sales_user_id        = $sales_data['user_id'];
            $sales_referral_code  = $sales_data['referral_code'];
            $sales_commission_rate = floatval($sales_data['commission_rate']);

            $cart_stmt = $conn->prepare("SELECT SUM(price * quantity) as original_subtotal FROM user_cart_items WHERE user_id = ?");
            $cart_stmt->bind_param("i", $user_id);
            $cart_stmt->execute();
            $cart_row = $cart_stmt->get_result()->fetch_assoc();
            $cart_stmt->close();
            $sales_commission_amount = floatval($cart_row['original_subtotal'] ?? 0) * ($sales_commission_rate / 100);
        }
    }

    // Calculate amounts (same as PayMongo)
    $items_with_vat   = $amount - $delivery_fee;
    $subtotal         = $items_with_vat / 1.12;
    $vat_amount       = $subtotal * 0.12;
    $discount_amount  = 0.00;
    $reference_no     = 'NH' . mt_rand(9800000, 9899999);
    $payment_method   = 'QR Ph';
    $payment_status   = 'pending';
    $order_status     = 'Pending';

    // INSERT ORDER - EXACT SAME COLUMNS AS PAYMONGO
    $insert_sql = "INSERT INTO orders (
        user_id, customer_name, email, mobile, address, zipcode,
        subtotal, delivery_fee, total, vat_amount, discount,
        mode_payment, payment_status, reference_no, status,
        delivery_type, assigned_vehicle_id, assigned_vehicle_type,
        total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
        latitude, longitude, billing_address_id, delivery_distance,
        sales_referral_code, sales_commission_rate, sales_commission_amount, sales_user_id,
        paymongo_session_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param(
        "isssssdddddsssssissddddddidsdids",
        $user_id,
        $customer_name,
        $email,
        $mobile,
        $address,
        $zipcode,
        $subtotal,
        $delivery_fee,
        $amount,
        $vat_amount,
        $discount_amount,
        $payment_method,
        $payment_status,
        $reference_no,
        $order_status,
        $delivery_type,
        $assigned_vehicle_id,
        $assigned_vehicle_type,
        $total_cubic_meters,
        $total_weight_kg,
        $total_width,
        $total_height,
        $total_length,
        $latitude,
        $longitude,
        $billing_address_id,
        $delivery_distance,
        $sales_referral_code,
        $sales_commission_rate,
        $sales_commission_amount,
        $sales_user_id,
        $qr_code_id
    );

    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

    $order_id = $conn->insert_id;
    $stmt->close();
    error_log("QRPh Order created: ID=$order_id Ref=$reference_no");

    // GET CART ITEMS - EXACT SAME QUERY AS PAYMONGO
    $cart_stmt = $conn->prepare("
        SELECT 
            uci.id, uci.user_id, uci.product_id, uci.variant_id, uci.color_id,
            uci.quantity, uci.price, uci.type_name, uci.variant_name, uci.color_name,
            uci.size, uci.codename, uci.descrip6, uci.descrip7,
            COALESCE(p.product_name, uci.variant_name, '') as product_name,
            COALESCE(pv.origin, '') as origin
        FROM user_cart_items uci
        LEFT JOIN products p ON uci.product_id = p.id
        LEFT JOIN product_variants pv ON uci.variant_id = pv.id
        WHERE uci.user_id = ?
    ");
    if (!$cart_stmt) throw new Exception('Cart prepare failed: ' . $conn->error);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_items = $cart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cart_stmt->close();

    if (empty($cart_items)) {
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('No items in cart');
    }

    // INSERT ORDER ITEMS - EXACT SAME AS PAYMONGO
    $item_stmt = $conn->prepare("INSERT INTO order_items (
        order_id, product_id, variant_id, color_id, product_name, codename, type_name,
        variant_color, size, price, quantity, subtotal,
        descrip6, descrip7, origin
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$item_stmt) throw new Exception('Item prepare failed: ' . $conn->error);

    foreach ($cart_items as $item) {
        $item_subtotal = floatval($item['price']) * intval($item['quantity']);
        $variant_id    = !empty($item['variant_id']) ? intval($item['variant_id']) : null;
        $color_id      = !empty($item['color_id']) ? intval($item['color_id']) : null;
        $product_id    = intval($item['product_id']);
        $product_name  = !empty($item['product_name']) ? $item['product_name'] : 'Product';
        $color         = $item['color_name'] ?? '';
        $codename      = $item['codename'] ?? '';
        $type_name     = $item['type_name'] ?? '';
        $size          = $item['size'] ?? '';
        $descrip6      = $item['descrip6'] ?? '';
        $descrip7      = $item['descrip7'] ?? '';
        $origin        = $item['origin'] ?? '';

        $item_stmt->bind_param(
            "iiiisssssdsisss",
            $order_id, $product_id, $variant_id, $color_id, $product_name,
            $codename, $type_name, $color, $size,
            $item['price'], $item['quantity'], $item_subtotal,
            $descrip6, $descrip7, $origin
        );

        if (!$item_stmt->execute()) {
            error_log("Item insert failed: " . $item_stmt->error);
        } else {
            error_log("Item inserted: {$product_name}");
        }
    }
    $item_stmt->close();

    // Store in session
    $_SESSION['qrph_pending_order'] = [
        'order_id'   => $order_id,
        'qr_code_id' => $qr_code_id,
        'amount'     => $amount
    ];

    ob_end_clean();
    echo json_encode([
        'success'      => true,
        'order_id'     => $order_id,
        'reference_no' => $reference_no
    ]);

} catch (Exception $e) {
    ob_end_clean();
    error_log("QRPh Create Order ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}