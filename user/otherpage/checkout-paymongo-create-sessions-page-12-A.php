<?php
// paymongo-create-sessions.php - FIXED: Dynamic URLs + color_id tracking

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_name("nobleuser");
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    require_once '../../connection/connect.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // ✅ BUILD DYNAMIC URLs
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Detect if localhost or production
    $isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    if ($isLocalhost) {
        $basePath = '/noble/user/otherpage';
    } else {
        $basePath = '/user/otherpage';
    }

    $success_url = $protocol . $host . $basePath . '/checkout-paymongo-success-page-12-A.php';
    $cancel_url = $protocol . $host . $basePath . '/index-checkout-page-12.php';

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['amount'])) {
        throw new Exception('Invalid request data - missing amount');
    }

    $amount = floatval($input['amount']);
    if ($amount <= 0) {
        throw new Exception('Invalid amount: ' . $amount);
    }

    $user_id = intval($_SESSION['user_id']);
    $delivery_fee = floatval($input['delivery_fee'] ?? 0);
    $order_details = $input['order_details'] ?? [];

    // ✅ SALES COMMISSION TRACKING (NO DISCOUNT)
    $sales_commission_rate = 0.00;
    $sales_commission_amount = 0.00;
    $sales_user_id = null;
    $sales_referral_code = null;

    // Check logged-in user's referred_by_code
    if (!empty($user_id)) {
        $user_check = $conn->prepare("SELECT referred_by_code FROM users WHERE id = ? LIMIT 1");
        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_result = $user_check->get_result();

        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $potential_code = $user_data['referred_by_code'];

            if (!empty($potential_code)) {
                $sales_check = $conn->prepare("
                    SELECT rc.user_id, rc.referral_code, na.commission_rate
                    FROM referral_codes rc
                    INNER JOIN nobleaccount na ON rc.user_id = na.id
                    WHERE rc.referral_code = ? 
                    AND rc.is_active = 1 
                    AND na.lvl = 'sales'
                    AND na.commission_rate > 0
                    LIMIT 1
                ");

                $sales_check->bind_param("s", $potential_code);
                $sales_check->execute();
                $sales_result = $sales_check->get_result();

                if ($sales_result->num_rows > 0) {
                    $sales_data = $sales_result->fetch_assoc();
                    $sales_user_id = $sales_data['user_id'];
                    $sales_referral_code = $sales_data['referral_code'];
                    $sales_commission_rate = floatval($sales_data['commission_rate']);

                    // Get original cart subtotal
                    $cart_stmt = $conn->prepare("
                        SELECT SUM(price * quantity) as original_subtotal
                        FROM user_cart_items 
                        WHERE user_id = ?
                    ");
                    $cart_stmt->bind_param("i", $user_id);
                    $cart_stmt->execute();
                    $cart_result = $cart_stmt->get_result();
                    $cart_row = $cart_result->fetch_assoc();
                    $original_subtotal = floatval($cart_row['original_subtotal'] ?? 0);
                    $cart_stmt->close();

                    $sales_commission_amount = $original_subtotal * ($sales_commission_rate / 100);

                    error_log("=== PayMongo Sales Commission ===");
                    error_log("Commission Rate: {$sales_commission_rate}%");
                    error_log("Commission Amount: ₱" . number_format($sales_commission_amount, 2));
                }
                $sales_check->close();
            }
        }
        $user_check->close();
    }

    error_log("=== PAYMONGO SESSION CREATION ===");
    error_log("Amount: ₱" . number_format($amount, 2));

    // Extract order details
    $customer_name = trim($order_details['customer_name'] ?? $_SESSION['checkout_step1']['customer_name'] ?? '');
    $email = trim($order_details['email'] ?? $_SESSION['checkout_step1']['email'] ?? '');
    $mobile = trim($order_details['mobile'] ?? $_SESSION['checkout_step2']['mobile'] ?? '');
    $address = trim($order_details['address'] ?? $_SESSION['checkout_step2']['address'] ?? '');
    $zipcode = trim($order_details['zipcode'] ?? $_SESSION['checkout_step2']['zipcode'] ?? '');
    $billing_address_id = !empty($order_details['billing_address_id']) ? intval($order_details['billing_address_id']) : ($_SESSION['checkout_step2']['billing_address_id'] ?? null);
    $latitude = !empty($order_details['latitude']) ? floatval($order_details['latitude']) : ($_SESSION['checkout_step2']['latitude'] ?? null);
    $longitude = !empty($order_details['longitude']) ? floatval($order_details['longitude']) : ($_SESSION['checkout_step2']['longitude'] ?? null);
    $delivery_distance = floatval($order_details['delivery_distance'] ?? ($_SESSION['checkout_step3']['delivery_distance'] ?? 0));
    $delivery_type = $order_details['delivery_type'] ?? ($_SESSION['checkout_step3']['delivery_type'] ?? 'delivery');

    $assigned_vehicle_id_input = !empty($order_details['assigned_vehicle_id'])
        ? intval($order_details['assigned_vehicle_id'])
        : (!empty($_SESSION['checkout_step3']['assigned_vehicle_id'])
            ? intval($_SESSION['checkout_step3']['assigned_vehicle_id'])
            : null);

    $assigned_vehicle_id = null;
    $assigned_vehicle_type = $order_details['assigned_vehicle_type']
        ?? ($_SESSION['checkout_step3']['assigned_vehicle_type'] ?? null);

    $total_cubic_meters = !empty($order_details['total_cubic_meters'])
        ? floatval($order_details['total_cubic_meters'])
        : (!empty($_SESSION['checkout_step3']['total_cubic_meters'])
            ? floatval($_SESSION['checkout_step3']['total_cubic_meters'])
            : 0);

    $total_weight_kg = !empty($order_details['total_weight_kg'])
        ? floatval($order_details['total_weight_kg'])
        : (!empty($_SESSION['checkout_step3']['total_weight_kg'])
            ? floatval($_SESSION['checkout_step3']['total_weight_kg'])
            : 0);

    $total_width = !empty($order_details['total_width'])
        ? floatval($order_details['total_width'])
        : (!empty($_SESSION['checkout_step3']['total_width'])
            ? floatval($_SESSION['checkout_step3']['total_width'])
            : 0);

    $total_height = !empty($order_details['total_height'])
        ? floatval($order_details['total_height'])
        : (!empty($_SESSION['checkout_step3']['total_height'])
            ? floatval($_SESSION['checkout_step3']['total_height'])
            : 0);

    $total_length = !empty($order_details['total_length'])
        ? floatval($order_details['total_length'])
        : (!empty($_SESSION['checkout_step3']['total_length'])
            ? floatval($_SESSION['checkout_step3']['total_length'])
            : 0);

    // Validate vehicle only if needed
    if ($delivery_type === 'delivery' && !is_null($assigned_vehicle_id_input) && $assigned_vehicle_id_input > 0) {
        error_log("Validating vehicle ID: $assigned_vehicle_id_input");

        $vehicle_check = $conn->query("
            SELECT id, vehicle_type FROM transportify_vehicle_list 
            WHERE id = " . intval($assigned_vehicle_id_input) . " 
            LIMIT 1
        ");

        if ($vehicle_check && $vehicle_check->num_rows > 0) {
            $vehicle_row = $vehicle_check->fetch_assoc();
            $assigned_vehicle_id = $vehicle_row['id'];
            error_log("✓ Vehicle found: ID={$vehicle_row['id']}");
        } else {
            error_log("✗ Vehicle NOT found, setting to NULL");
            $assigned_vehicle_id = null;
        }
    }

    // Calculate breakdown
    $items_with_vat = $amount - $delivery_fee;
    $subtotal = $items_with_vat / 1.12;
    $vat_amount = $subtotal * 0.12;
    $items_without_vat = $subtotal;
    $reference_no = 'NH' . mt_rand(9800000, 9899999);
    $payment_method = 'PayMongo';
    $payment_status = 'pending';
    $order_status = 'Pending';
    $discount_amount = 0.00;

    // ✅ INSERT ORDER WITHOUT STOCK DEDUCTION
    $insert_sql = "INSERT INTO orders (
        user_id, 
        customer_name, 
        email, 
        mobile, 
        address, 
        zipcode,
        subtotal, 
        delivery_fee, 
        total, 
        vat_amount,
        discount,
        mode_payment, 
        payment_status, 
        reference_no,
        status,
        delivery_type, 
        assigned_vehicle_id, 
        assigned_vehicle_type,
        total_cubic_meters, 
        total_weight_kg, 
        total_width, 
        total_height, 
        total_length,
        latitude,
        longitude,
        billing_address_id,
        delivery_distance,
        sales_referral_code,
        sales_commission_rate,
        sales_commission_amount,
        sales_user_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $types = "isssssdddddsssssissddddddidsddi";

    $stmt->bind_param(
        $types,
        $user_id,
        $customer_name,
        $email,
        $mobile,
        $address,
        $zipcode,
        $items_without_vat,
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
        $sales_user_id
    );

    if (!$stmt->execute()) {
        error_log("INSERT FAILED: " . $stmt->error);
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $order_id = $conn->insert_id;
    error_log("✓ Order created: ID=$order_id with status: pending");
    $stmt->close();

 // ✅ GET CART ITEMS - FETCH product_name FROM PRODUCTS TABLE (LIKE OLD CODE)
    $cart_stmt = $conn->prepare("
        SELECT 
            uci.id,
            uci.user_id,
            uci.product_id,
            uci.variant_id,
            uci.color_id,
            uci.quantity,
            uci.price,
            uci.type_name,
            uci.variant_name,
            uci.color_name,
            uci.size,
            uci.codename,
            uci.descrip6,
            uci.descrip7,
            COALESCE(p.product_name, uci.variant_name, '') as product_name,
            COALESCE(pv.origin, '') as origin
        FROM user_cart_items uci 
        LEFT JOIN products p ON uci.product_id = p.id
        LEFT JOIN product_variants pv ON uci.variant_id = pv.id 
        WHERE uci.user_id = ?
    ");

    if (!$cart_stmt) {
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('Failed to prepare cart statement: ' . $conn->error);
    }

    $cart_stmt->bind_param("i", $user_id);
    if (!$cart_stmt->execute()) {
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('Failed to get cart items: ' . $cart_stmt->error);
    }

    $cart_result = $cart_stmt->get_result();
    $cart_items = [];

    error_log("=== CART ITEMS DEBUG ===");
    error_log("Total cart items: " . $cart_result->num_rows);

    while ($row = $cart_result->fetch_assoc()) {
        $cart_items[] = $row;
        error_log("Item: Product={$row['product_id']}, ProductName={$row['product_name']}, Variant={$row['variant_id']}, Color={$row['color_id']}, Qty={$row['quantity']}");
    }

    $cart_stmt->close();

    if (empty($cart_items)) {
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('No items found in cart');
    }

    // ✅ INSERT ORDER ITEMS - WITH color_id!
    $item_stmt = $conn->prepare("INSERT INTO order_items (
        order_id, product_id, variant_id, color_id, product_name, codename, type_name, 
        variant_color, size, price, quantity, subtotal, 
        descrip6, descrip7, origin
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$item_stmt) {
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('Failed to prepare items statement: ' . $conn->error);
    }

    foreach ($cart_items as $item) {
        $item_subtotal = floatval($item['price']) * intval($item['quantity']);
        $variant_id = isset($item['variant_id']) && !empty($item['variant_id']) 
            ? intval($item['variant_id']) 
            : null;
        
        $color_id = isset($item['color_id']) && !empty($item['color_id'])
            ? intval($item['color_id'])
            : null;

        $product_id = intval($item['product_id']);
        
        // ✅ Get product_name from COALESCE query result (from products table)
        $product_name = !empty($item['product_name']) 
            ? $item['product_name'] 
            : 'Product';
        
        $color = $item['color_name'] ?? $item['variant_color'] ?? '';
        $codename = $item['codename'] ?? '';
        $type_name = $item['type_name'] ?? '';
        $size = $item['size'] ?? '';
        $descrip6 = $item['descrip6'] ?? '';
        $descrip7 = $item['descrip7'] ?? '';
        $origin = $item['origin'] ?? '';

        $item_stmt->bind_param(
            "iiiisssssdsisss",
            $order_id,
            $product_id,
            $variant_id,
            $color_id,
            $product_name,
            $codename,
            $type_name,
            $color,
            $size,
            $item['price'],
            $item['quantity'],
            $item_subtotal,
            $descrip6,
            $descrip7,
            $origin
        );
        
        if (!$item_stmt->execute()) {
            error_log("Warning: Failed to insert item: " . $item_stmt->error);
        } else {
            error_log("✓ Inserted order item: {$product_name} (Qty: {$item['quantity']})");
        }
    }

    $item_stmt->close();
    
    error_log("✓ Order items created WITHOUT stock deduction (waiting for payment)");

    // ✅ CREATE PAYMONGO CHECKOUT SESSION - WITH DYNAMIC URLs
    $amount_in_centavos = intval($amount * 100);
    $secretKey = "sk_test_AJdRkkXWfGW9W5DHV6UNNECZ";

    $checkout_data = [
        "data" => [
            "attributes" => [
                "amount" => $amount_in_centavos,
                "currency" => "PHP",
                "line_items" => [[
                    "name" => "Noble Home Order #" . $reference_no,
                    "quantity" => 1,
                    "amount" => $amount_in_centavos,
                    "currency" => "PHP",
                    "description" => "Items: ₱" . number_format($items_without_vat, 2) .
                        " + VAT: ₱" . number_format($vat_amount, 2) .
                        " + Delivery: ₱" . number_format($delivery_fee, 2)
                ]],
                "payment_method_types" => ["gcash", "paymaya", "card", "grab_pay"],
                "success_url" => $success_url . "?order_id=" . $order_id . "&ref=" . $reference_no,
                "cancel_url" => $cancel_url . "?payment_cancelled=1&order_id=" . $order_id,
                "description" => "Noble Home Construction - Order #" . $reference_no,
                "metadata" => [
                    "user_id" => strval($user_id),
                    "order_id" => strval($order_id),
                    "reference_no" => $reference_no,
                    "customer_name" => $customer_name,
                    "customer_email" => $email
                ]
            ]
        ]
    ];

    // Make PayMongo API call
    $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode($secretKey . ":")
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkout_data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("PayMongo connection error: $curl_error");
    }

    if ($http_code !== 200) {
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        error_log("PayMongo API error HTTP $http_code: " . substr($response, 0, 500));
        throw new Exception("PayMongo API error HTTP $http_code");
    }

    $paymongo_response = json_decode($response, true);
    if (!$paymongo_response || !isset($paymongo_response['data']['id'])) {
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("Invalid PayMongo response");
    }

    // ✅ UPDATE ORDER WITH PAYMONGO SESSION ID
    $session_id = $paymongo_response['data']['id'];
    $update_stmt = $conn->prepare("UPDATE orders SET paymongo_session_id = ? WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("si", $session_id, $order_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // ✅ STORE IN SESSION
    $_SESSION['pending_paymongo_order'] = [
        'order_id' => $order_id,
        'session_id' => $session_id,
        'reference_no' => $reference_no,
        'amount' => $amount
    ];

    error_log("✓ PayMongo session created - waiting for payment confirmation");
    error_log("=== END ===");

    ob_end_clean();
    echo json_encode($paymongo_response);
} catch (Exception $e) {
    ob_end_clean();
    error_log("PayMongo Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} catch (Error $e) {
    ob_end_clean();
    error_log("PayMongo Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}
