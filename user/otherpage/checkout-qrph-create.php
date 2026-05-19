<?php
// checkout-qrph-create.php - CREATE CHECKOUT SESSION ONLY (no order yet!)
// Order will be created by webhook ONLY when payment.paid is received
include ROOT_PATH . '/connection/connect.php';
require_once ROOT_PATH . '/.env.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in', 'success' => false]);
    exit;
}

try {
    $data   = json_decode(file_get_contents('php://input'), true);
    $amount = floatval($data['amount'] ?? 0);

    if ($amount <= 0) {
        throw new Exception('Invalid amount: ' . $amount);
    }

    $paymongo_secret_key = getenv('PAYMONGO_SECRET_KEY');
    if (empty($paymongo_secret_key)) {
        throw new Exception('Payment service not configured');
    }

    $amount_in_centavos = intval($amount * 100);
    $user_id            = intval($_SESSION['user_id']);

    // Generate a temp reference we can use to identify this session later
    $temp_ref = 'NH' . mt_rand(9800000, 9899999);

    error_log("QRPh Session Create: user=$user_id amount=₱$amount ref=$temp_ref");

    // ✅ BUILD DYNAMIC URLS (ilagay bago ang $payload)
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host       = $_SERVER['HTTP_HOST'];
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
$base       = $protocol . $host . ($isLocalhost ? '/noble' : '');

$payload = json_encode([
    'data' => [
        'attributes' => [
            'amount'               => $amount_in_centavos,
            'currency'             => 'PHP',
            'payment_method_types' => ['qrph'],
            'line_items'           => [[
                'name'     => 'Noble Home Order ' . $temp_ref,
                'quantity' => 1,
                'amount'   => $amount_in_centavos,
                'currency' => 'PHP',
            ]],
            'success_url' => $base . '/payment2mongo?ref=' . $temp_ref . '&source=qrph',
            'cancel_url'  => $base . '/checkout4',   // checkout4 = step 4 sa router
            'description' => 'Noble Home Order ' . $temp_ref,
            'metadata'    => [
                'user_id'  => strval($user_id),
                'temp_ref' => $temp_ref,
                'source'   => 'qrph_checkout'
            ]
        ]
    ]
]);

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret_key . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("PayMongo Response ($http_code): " . $response);

    if ($http_code !== 200 && $http_code !== 201) {
        throw new Exception('PayMongo API failed: HTTP ' . $http_code . ' - ' . $response);
    }

    $session_data = json_decode($response, true);
    $session_id   = $session_data['data']['id'] ?? null;
    $checkout_url = $session_data['data']['attributes']['checkout_url'] ?? null;

    if (!$session_id || !$checkout_url) {
        throw new Exception('No session ID or checkout URL in response');
    }

    error_log("✓ Checkout Session: $session_id");

    // ✅ Save session to qrph_pending_sessions table (temp storage, no order yet)
    // We store the full checkout session data + user session data for webhook to use later
    $session_snapshot = json_encode([
        'user_id'              => $user_id,
        'amount'               => $amount,
        'delivery_fee'         => floatval($_SESSION['checkout_step3']['delivery_fee'] ?? 0),
        'temp_ref'             => $temp_ref,
        'customer_name'        => $_SESSION['checkout_step1']['customer_name'] ?? '',
        'email'                => $_SESSION['checkout_step1']['email'] ?? '',
        'mobile'               => $_SESSION['checkout_step2']['mobile'] ?? '',
        'address'              => $_SESSION['checkout_step2']['address'] ?? '',
        'zipcode'              => $_SESSION['checkout_step2']['zipcode'] ?? '',
        'billing_address_id'   => $_SESSION['checkout_step2']['billing_address_id'] ?? null,
        'latitude'             => $_SESSION['checkout_step2']['latitude'] ?? null,
        'longitude'            => $_SESSION['checkout_step2']['longitude'] ?? null,
        'delivery_distance'    => $_SESSION['checkout_step3']['delivery_distance'] ?? 0,
        'delivery_type'        => $_SESSION['checkout_step3']['delivery_type'] ?? 'delivery',
        'assigned_vehicle_id'  => $_SESSION['checkout_step3']['assigned_vehicle_id'] ?? null,
        'assigned_vehicle_type'=> $_SESSION['checkout_step3']['assigned_vehicle_type'] ?? null,
        'total_cubic_meters'   => $_SESSION['checkout_step3']['total_cubic_meters'] ?? 0,
        'total_weight_kg'      => $_SESSION['checkout_step3']['total_weight_kg'] ?? 0,
        'total_width'          => $_SESSION['checkout_step3']['total_width'] ?? 0,
        'total_height'         => $_SESSION['checkout_step3']['total_height'] ?? 0,
        'total_length'         => $_SESSION['checkout_step3']['total_length'] ?? 0,
        'cart_snapshot'        => [] // filled below
    ]);

    // Get cart items snapshot NOW so webhook can use them (user's cart might be gone by then)
    $cart_stmt = $conn->prepare("
        SELECT 
            uci.product_id, uci.variant_id, uci.color_id,
            uci.quantity, uci.price, uci.type_name, uci.variant_name, uci.color_name,
            uci.size, uci.codename, uci.descrip6, uci.descrip7,
            COALESCE(p.product_name, uci.variant_name, '') as product_name,
            COALESCE(pv.origin, '') as origin
        FROM user_cart_items uci
        LEFT JOIN products p ON uci.product_id = p.id
        LEFT JOIN product_variants pv ON uci.variant_id = pv.id
        WHERE uci.user_id = ?
    ");
    if ($cart_stmt) {
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_items = $cart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cart_stmt->close();

        if (empty($cart_items)) {
            throw new Exception('No items in cart');
        }
    } else {
        throw new Exception('Failed to fetch cart: ' . $conn->error);
    }

    // Also get sales commission info
    $sales_info = [];
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
            $original_subtotal = 0;
            foreach ($cart_items as $ci) {
                $original_subtotal += floatval($ci['price']) * intval($ci['quantity']);
            }
            $sales_info = [
                'sales_user_id'           => $sales_data['user_id'],
                'sales_referral_code'     => $sales_data['referral_code'],
                'sales_commission_rate'   => floatval($sales_data['commission_rate']),
                'sales_commission_amount' => $original_subtotal * (floatval($sales_data['commission_rate']) / 100),
            ];
        }
    }

    // Build the full snapshot with cart + sales
    $full_snapshot = json_decode($session_snapshot, true);
    $full_snapshot['cart_snapshot'] = $cart_items;
    $full_snapshot['sales_info']    = $sales_info;

    // ✅ Save to qrph_pending_sessions
    $insert_stmt = $conn->prepare("
        INSERT INTO qrph_pending_sessions 
            (session_id, temp_ref, user_id, amount, session_data, status, created_at, expires_at)
        VALUES 
            (?, ?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ON DUPLICATE KEY UPDATE
            temp_ref     = VALUES(temp_ref),
            user_id      = VALUES(user_id),
            amount       = VALUES(amount),
            session_data = VALUES(session_data),
            status       = 'pending',
            created_at   = NOW(),
            expires_at   = DATE_ADD(NOW(), INTERVAL 1 HOUR)
    ");

    if (!$insert_stmt) {
        throw new Exception('DB prepare failed: ' . $conn->error);
    }

    $snapshot_json = json_encode($full_snapshot);
    $insert_stmt->bind_param('ssids', $session_id, $temp_ref, $user_id, $amount, $snapshot_json);

    if (!$insert_stmt->execute()) {
        throw new Exception('DB execute failed: ' . $insert_stmt->error);
    }
    $insert_stmt->close();

    error_log("✓ Saved pending session: session=$session_id ref=$temp_ref user=$user_id");
    error_log("✓ Cart items saved: " . count($cart_items) . " items");

    echo json_encode([
        'success'      => true,
        'checkout_url' => $checkout_url,
        'session_id'   => $session_id,
        'temp_ref'     => $temp_ref,
    ]);

} catch (Exception $e) {
    error_log('❌ QRPh Create Session Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}
?>