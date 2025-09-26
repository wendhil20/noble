<?php
// paymongo-create-sessions.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_name("nobleuser");
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../../connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['amount'])) {
        throw new Exception('Invalid request data - missing amount');
    }

    $amount = floatval($input['amount']);
    if ($amount <= 0) throw new Exception('Invalid amount: ' . $amount);

    $user_id = $_SESSION['user_id'];
    $reference_no = 'NB' . time() . rand(1000, 9999);

    // Get order details from input
    $order_details = $input['order_details'] ?? [];
    $customer_name = $order_details['customer_name'] ?? '';
    $email = $order_details['email'] ?? '';
    $mobile = $order_details['mobile'] ?? '';

    // Calculate breakdown
    $delivery_fee = floatval($input['delivery_fee'] ?? 0);
    $subtotal = $amount - $delivery_fee; // Items subtotal
    $vat_amount = $subtotal * 0.12; // 12% VAT on items only
    $items_without_vat = $subtotal - $vat_amount; // Items before VAT

    // ✅ SAVE ORDER TO DATABASE FIRST
    $stmt = $conn->prepare("INSERT INTO orders (
        user_id, customer_name, email, mobile, 
        subtotal, delivery_fee, vat_amount, total, 
        mode_payment, payment_status, reference_no, 
        created_at, paymongo_session_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PayMongo', 'pending_paymongo', ?, NOW(), '')");

    $stmt->bind_param("isssdddds", 
        $user_id, 
        $customer_name, 
        $email, 
        $mobile, 
        $items_without_vat,
        $delivery_fee,
        $vat_amount,
        $amount, 
        $reference_no
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create order in database');
    }

    $order_id = $conn->insert_id;
    $stmt->close();

    // ✅ ADD CART ITEMS TO ORDER_ITEMS TABLE
    $cart_stmt = $conn->prepare("
        SELECT uci.* 
        FROM user_cart_items uci 
        WHERE uci.user_id = ?
    ");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();

    $order_item_stmt = $conn->prepare("INSERT INTO order_items (
        order_id, product_id, product_name, codename, type_name, 
        variant_color, size, price, quantity, subtotal, 
        descrip6, descrip7, origin
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    while ($item = $cart_result->fetch_assoc()) {
        $item_subtotal = $item['price'] * $item['quantity'];
        
        // Use data directly from user_cart_items table
        $product_name = $item['variant_name'] ?? 'Product';
        $color = $item['color_name'] ?? '';
        $codename = $item['codename'] ?? '';
        $type_name = $item['type_name'] ?? '';
        $size = $item['size'] ?? '';
        $descrip6 = $item['descrip6'] ?? '';
        $descrip7 = $item['descrip7'] ?? '';
        
        // Set origin as empty since user_cart_items doesn't have origin column
        $origin = '';

        $order_item_stmt->bind_param("iisssssdiisss", 
            $order_id,
            $item['product_id'],
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
        
        if (!$order_item_stmt->execute()) {
            error_log("Failed to insert order item: " . $order_item_stmt->error);
        }
    }
    
    $cart_stmt->close();
    $order_item_stmt->close();

    // ✅ CREATE PAYMONGO CHECKOUT SESSION
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
                "payment_method_types" => ["gcash","paymaya","card","grab_pay"],
                "success_url" => "http://localhost/noble/user/otherpage/paymongo-success.php?order_id=" . $order_id . "&ref=" . $reference_no,
                "cancel_url" => "http://localhost/noble/user/otherpage/checkout.php?payment_cancelled=1&order_id=" . $order_id,
                "description" => "Noble Home Construction - Order #" . $reference_no,
                "metadata" => [
                    "user_id" => $user_id,
                    "order_id" => $order_id,
                    "reference_no" => $reference_no
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

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        // If PayMongo fails, delete the order we just created
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("PayMongo API error: $response");
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
    $update_stmt->bind_param("si", $session_id, $order_id);
    $update_stmt->execute();
    $update_stmt->close();

    // ✅ CLEAR USER'S CART
    $clear_cart_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
    $clear_cart_stmt->bind_param("i", $user_id);
    $clear_cart_stmt->execute();
    $clear_cart_stmt->close();

    // ✅ STORE ORDER INFO IN SESSION FOR SUCCESS PAGE
    $_SESSION['pending_paymongo_order'] = [
        'order_id' => $order_id,
        'session_id' => $session_id,
        'reference_no' => $reference_no,
        'amount' => $amount
    ];

    echo $response; // Return PayMongo's response

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
