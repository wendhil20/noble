<?php
// paymongo-create-sessions.php - FIXED: Order inserted ONLY after payment confirmation
// Flow: 1) Store order details in session → 2) Create PayMongo session → 3) User pays
//       4) Success page receives webhook/redirect → 5) THEN insert order to DB

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

require_once '../../.env.php';

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
    $isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    $basePath = $isLocalhost ? '/noble/user/otherpage' : '/user/otherpage';

    $success_url = $protocol . $host . $basePath . '/checkout-paymongo-success-page-12-A.php';
    $cancel_url  = $protocol . $host . $basePath . '/index-checkout-page-12.php';

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['amount'])) {
        throw new Exception('Invalid request data - missing amount');
    }

    $amount = floatval($input['amount']);
    if ($amount <= 0) {
        throw new Exception('Invalid amount: ' . $amount);
    }

    $user_id      = intval($_SESSION['user_id']);
    $delivery_fee = floatval($input['delivery_fee'] ?? 0);
    $order_details = $input['order_details'] ?? [];

    // ✅ SALES COMMISSION TRACKING (NO DISCOUNT)
    $sales_commission_rate   = 0.00;
    $sales_commission_amount = 0.00;
    $sales_user_id           = null;
    $sales_referral_code     = null;

    if (!empty($user_id)) {
        $user_check = $conn->prepare("SELECT referred_by_code FROM users WHERE id = ? LIMIT 1");
        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_result = $user_check->get_result();

        if ($user_result->num_rows > 0) {
            $user_data      = $user_result->fetch_assoc();
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
                    $sales_data          = $sales_result->fetch_assoc();
                    $sales_user_id       = $sales_data['user_id'];
                    $sales_referral_code = $sales_data['referral_code'];
                    $sales_commission_rate = floatval($sales_data['commission_rate']);

                    $cart_stmt = $conn->prepare("
                        SELECT SUM(price * quantity) as original_subtotal
                        FROM user_cart_items 
                        WHERE user_id = ?
                    ");
                    $cart_stmt->bind_param("i", $user_id);
                    $cart_stmt->execute();
                    $cart_result       = $cart_stmt->get_result();
                    $cart_row          = $cart_result->fetch_assoc();
                    $original_subtotal = floatval($cart_row['original_subtotal'] ?? 0);
                    $cart_stmt->close();

                    $sales_commission_amount = $original_subtotal * ($sales_commission_rate / 100);
                }
                $sales_check->close();
            }
        }
        $user_check->close();
    }

    // ✅ FETCH CART ITEMS (for session storage only, NOT for DB insert yet)
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

    if (!$cart_stmt) {
        throw new Exception('Failed to prepare cart statement: ' . $conn->error);
    }

    $cart_stmt->bind_param("i", $user_id);
    if (!$cart_stmt->execute()) {
        throw new Exception('Failed to get cart items: ' . $cart_stmt->error);
    }

    $cart_result = $cart_stmt->get_result();
    $cart_items  = [];
    while ($row = $cart_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    $cart_stmt->close();

    if (empty($cart_items)) {
        throw new Exception('No items found in cart');
    }

    // ✅ EXTRACT ORDER DETAILS
    $customer_name    = trim($order_details['customer_name']    ?? $_SESSION['checkout_step1']['customer_name'] ?? '');
    $email            = trim($order_details['email']            ?? $_SESSION['checkout_step1']['email']         ?? '');
    $mobile           = trim($order_details['mobile']           ?? $_SESSION['checkout_step2']['mobile']        ?? '');
    $address          = trim($order_details['address']          ?? $_SESSION['checkout_step2']['address']       ?? '');
    $zipcode          = trim($order_details['zipcode']          ?? $_SESSION['checkout_step2']['zipcode']       ?? '');
    $billing_address_id  = !empty($order_details['billing_address_id'])  ? intval($order_details['billing_address_id'])  : ($_SESSION['checkout_step2']['billing_address_id'] ?? null);
    $latitude         = !empty($order_details['latitude'])  ? floatval($order_details['latitude'])  : ($_SESSION['checkout_step2']['latitude']  ?? null);
    $longitude        = !empty($order_details['longitude']) ? floatval($order_details['longitude']) : ($_SESSION['checkout_step2']['longitude'] ?? null);
    $delivery_distance = floatval($order_details['delivery_distance'] ?? ($_SESSION['checkout_step3']['delivery_distance'] ?? 0));
    $delivery_type    = $order_details['delivery_type'] ?? ($_SESSION['checkout_step3']['delivery_type'] ?? 'delivery');

    $assigned_vehicle_id_input = !empty($order_details['assigned_vehicle_id'])
        ? intval($order_details['assigned_vehicle_id'])
        : (!empty($_SESSION['checkout_step3']['assigned_vehicle_id']) ? intval($_SESSION['checkout_step3']['assigned_vehicle_id']) : null);

    $assigned_vehicle_id   = null;
    $assigned_vehicle_type = $order_details['assigned_vehicle_type'] ?? ($_SESSION['checkout_step3']['assigned_vehicle_type'] ?? null);

    $total_cubic_meters = floatval($order_details['total_cubic_meters'] ?? ($_SESSION['checkout_step3']['total_cubic_meters'] ?? 0));
    $total_weight_kg    = floatval($order_details['total_weight_kg']    ?? ($_SESSION['checkout_step3']['total_weight_kg']    ?? 0));
    $total_width        = floatval($order_details['total_width']        ?? ($_SESSION['checkout_step3']['total_width']        ?? 0));
    $total_height       = floatval($order_details['total_height']       ?? ($_SESSION['checkout_step3']['total_height']       ?? 0));
    $total_length       = floatval($order_details['total_length']       ?? ($_SESSION['checkout_step3']['total_length']       ?? 0));

    // Validate vehicle
    if ($delivery_type === 'delivery' && !is_null($assigned_vehicle_id_input) && $assigned_vehicle_id_input > 0) {
        $vehicle_check = $conn->query("
            SELECT id, vehicle_type FROM transportify_vehicle_list 
            WHERE id = " . intval($assigned_vehicle_id_input) . " LIMIT 1
        ");
        if ($vehicle_check && $vehicle_check->num_rows > 0) {
            $vehicle_row         = $vehicle_check->fetch_assoc();
            $assigned_vehicle_id = $vehicle_row['id'];
        }
    }

    // ✅ CALCULATE BREAKDOWN
    $items_with_vat    = $amount - $delivery_fee;
    $subtotal          = $items_with_vat / 1.12;
    $vat_amount        = $subtotal * 0.12;
    $items_without_vat = $subtotal;
    $reference_no      = 'NH' . mt_rand(9800000, 9899999);
    $discount_amount   = 0.00;

    // ✅ STORE EVERYTHING IN SESSION - DO NOT INSERT TO DB YET
    $_SESSION['pending_paymongo_order'] = [
        'reference_no'           => $reference_no,
        'user_id'                => $user_id,
        'amount'                 => $amount,
        'delivery_fee'           => $delivery_fee,
        'subtotal'               => $items_without_vat,
        'vat_amount'             => $vat_amount,
        'discount_amount'        => $discount_amount,
        'customer_name'          => $customer_name,
        'email'                  => $email,
        'mobile'                 => $mobile,
        'address'                => $address,
        'zipcode'                => $zipcode,
        'billing_address_id'     => $billing_address_id,
        'latitude'               => $latitude,
        'longitude'              => $longitude,
        'delivery_distance'      => $delivery_distance,
        'delivery_type'          => $delivery_type,
        'assigned_vehicle_id'    => $assigned_vehicle_id,
        'assigned_vehicle_type'  => $assigned_vehicle_type,
        'total_cubic_meters'     => $total_cubic_meters,
        'total_weight_kg'        => $total_weight_kg,
        'total_width'            => $total_width,
        'total_height'           => $total_height,
        'total_length'           => $total_length,
        'sales_referral_code'    => $sales_referral_code,
        'sales_commission_rate'  => $sales_commission_rate,
        'sales_commission_amount'=> $sales_commission_amount,
        'sales_user_id'          => $sales_user_id,
        'cart_items'             => $cart_items,  // ✅ cart snapshot saved in session
    ];

    error_log("=== PAYMONGO SESSION CREATION (NO DB INSERT YET) ===");
    error_log("Reference: $reference_no | Amount: ₱" . number_format($amount, 2));
    error_log("Cart items stored in session: " . count($cart_items));

    // ✅ CREATE PAYMONGO CHECKOUT SESSION
    $amount_in_centavos = intval($amount * 100);
    $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');

    $checkout_data = [
        "data" => [
            "attributes" => [
                "amount"   => $amount_in_centavos,
                "currency" => "PHP",
                "line_items" => [[
                    "name"        => "Noble Home Order #" . $reference_no,
                    "quantity"    => 1,
                    "amount"      => $amount_in_centavos,
                    "currency"    => "PHP",
                    "description" => "Items: ₱" . number_format($items_without_vat, 2) .
                                     " + VAT: ₱" . number_format($vat_amount, 2) .
                                     " + Delivery: ₱" . number_format($delivery_fee, 2)
                ]],
                "payment_method_types" => ["gcash", "paymaya", "card", "grab_pay"],
                "success_url" => $success_url . "?ref=" . $reference_no,
                "cancel_url"  => $cancel_url  . "?payment_cancelled=1&ref=" . $reference_no,
                "description" => "Noble Home Construction - Order #" . $reference_no,
                "metadata"    => [
                    "user_id"       => strval($user_id),
                    "reference_no"  => $reference_no,
                    "customer_name" => $customer_name,
                    "customer_email"=> $email
                    // NOTE: no order_id here yet — order doesn't exist until payment is confirmed
                ]
            ]
        ]
    ];

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

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        unset($_SESSION['pending_paymongo_order']);
        throw new Exception("PayMongo connection error: $curl_error");
    }

    if ($http_code !== 200) {
        unset($_SESSION['pending_paymongo_order']);
        error_log("PayMongo API error HTTP $http_code: " . substr($response, 0, 500));
        throw new Exception("PayMongo API error HTTP $http_code");
    }

    $paymongo_response = json_decode($response, true);
    if (!$paymongo_response || !isset($paymongo_response['data']['id'])) {
        unset($_SESSION['pending_paymongo_order']);
        throw new Exception("Invalid PayMongo response");
    }

    // ✅ SAVE SESSION ID IN SESSION (for verification on success page)
    $session_id = $paymongo_response['data']['id'];
    $_SESSION['pending_paymongo_order']['session_id'] = $session_id;

    error_log("✓ PayMongo session created: $session_id");
    error_log("✓ Order will be inserted ONLY after payment is confirmed on success page");
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