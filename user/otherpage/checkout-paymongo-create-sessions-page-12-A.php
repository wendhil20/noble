<?php
// paymongo-create-sessions.php - FIXED version with correct parameter binding
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

try {
    require_once '../../connection/connect.php';
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'User not logged in']);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['amount'])) {
        throw new Exception('Invalid request data - missing amount');
    }

    $amount = floatval($input['amount']);
    if ($amount <= 0) {
        throw new Exception('Invalid amount: ' . $amount);
    }

    $user_id = $_SESSION['user_id'];
    $delivery_fee = floatval($input['delivery_fee'] ?? 0);
    $order_details = $input['order_details'] ?? [];
    
    // Extract order details safely
    $customer_name = $order_details['customer_name'] ?? '';
    $email = $order_details['email'] ?? '';
    $mobile = $order_details['mobile'] ?? '';
    $address = $order_details['address'] ?? '';
    $zipcode = $order_details['zipcode'] ?? '';
    $billing_address_id = !empty($order_details['billing_address_id']) ? intval($order_details['billing_address_id']) : null;
    $latitude = !empty($order_details['latitude']) ? floatval($order_details['latitude']) : null;
    $longitude = !empty($order_details['longitude']) ? floatval($order_details['longitude']) : null;
    $delivery_distance = floatval($order_details['delivery_distance'] ?? 0);
    $delivery_type = $order_details['delivery_type'] ?? 'delivery';
$assigned_vehicle_id = !empty($order_details['assigned_vehicle_id']) ? intval($order_details['assigned_vehicle_id']) : null;
$assigned_vehicle_type = $order_details['assigned_vehicle_type'] ?? null;
$total_cubic_meters = !empty($order_details['total_cubic_meters']) ? floatval($order_details['total_cubic_meters']) : 0;
$total_weight_kg = !empty($order_details['total_weight_kg']) ? floatval($order_details['total_weight_kg']) : 0;
$total_width = !empty($order_details['total_width']) ? floatval($order_details['total_width']) : 0;
$total_height = !empty($order_details['total_height']) ? floatval($order_details['total_height']) : 0;
$total_length = !empty($order_details['total_length']) ? floatval($order_details['total_length']) : 0;

error_log("PayMongo Vehicle Data: Vehicle ID=$assigned_vehicle_id, Type=$assigned_vehicle_type, Volume={$total_cubic_meters}m³, Weight={$total_weight_kg}kg");

    // Validate required fields
    if (empty($customer_name) || empty($email) || empty($mobile) || empty($address) || empty($zipcode)) {
        throw new Exception('Missing required customer information');
    }

    // Generate reference number
    $reference_no = 'NH' . mt_rand(9800000, 9899999);
    
    // Debug log BEFORE insert
    error_log("=== DEBUG START ===");
    error_log("Generated reference_no: " . $reference_no);

    // Calculate breakdown
    $subtotal = $amount - $delivery_fee;
    $vat_amount = $subtotal * 0.12;
    $items_without_vat = $subtotal - $vat_amount;

    // ✅ SIMPLIFIED INSERT - Test with minimal fields first
    $test_stmt = $conn->prepare("INSERT INTO orders (
    user_id, customer_name, email, mobile, address, zipcode,
    subtotal, delivery_fee, total, mode_payment, payment_status, reference_no,
    delivery_type, assigned_vehicle_id, assigned_vehicle_type,
    total_cubic_meters, total_weight_kg, total_width, total_height, total_length
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$test_stmt) {
        throw new Exception('Failed to prepare order statement: ' . $conn->error);
    }

    $payment_method = 'PayMongo';
    $payment_status = 'pending';
    
    // Create variables
    $user_id_var = (int)$user_id;
    $customer_name_var = (string)$customer_name;
    $email_var = (string)$email;
    $mobile_var = (string)$mobile;
    $address_var = (string)$address;
    $zipcode_var = (string)$zipcode;
    $items_without_vat_var = (float)$items_without_vat;
    $delivery_fee_var = (float)$delivery_fee;
    $amount_var = (float)$amount;
    $payment_method_var = (string)$payment_method;
    $payment_status_var = (string)$payment_status;
    $reference_no_var = (string)$reference_no;
    
    error_log("Binding reference_no_var: '" . $reference_no_var . "' (length: " . strlen($reference_no_var) . ")");
    
    // ✅ Create variables for ALL fields including vehicle data
$delivery_type_var = (string)$delivery_type;
$assigned_vehicle_id_var = $assigned_vehicle_id;
$assigned_vehicle_type_var = $assigned_vehicle_type;
$total_cubic_meters_var = (float)$total_cubic_meters;
$total_weight_kg_var = (float)$total_weight_kg;
$total_width_var = (float)$total_width;
$total_height_var = (float)$total_height;
$total_length_var = (float)$total_length;

$bind_success = $test_stmt->bind_param("isssssdddssssisddddd", 
    $user_id_var,                // i - 1
    $customer_name_var,          // s - 2
    $email_var,                  // s - 3
    $mobile_var,                 // s - 4
    $address_var,                // s - 5
    $zipcode_var,                // s - 6
    $items_without_vat_var,      // d - 7
    $delivery_fee_var,           // d - 8
    $amount_var,                 // d - 9
    $payment_method_var,         // s - 10
    $payment_status_var,         // s - 11
    $reference_no_var,           // s - 12
    $delivery_type_var,          // s - 13
    $assigned_vehicle_id_var,    // i - 14
    $assigned_vehicle_type_var,  // s - 15
    $total_cubic_meters_var,     // d - 16
    $total_weight_kg_var,        // d - 17
    $total_width_var,            // d - 18
    $total_height_var,           // d - 19
    $total_length_var            // d - 20
);
    
    if (!$bind_success) {
        error_log("Bind failed: " . $test_stmt->error);
        throw new Exception('Failed to bind parameters: ' . $test_stmt->error);
    }
    
    error_log("Bind successful, executing...");

    if (!$test_stmt->execute()) {
        error_log("Execute failed: " . $test_stmt->error);
        throw new Exception('Failed to create order: ' . $test_stmt->error);
    }

    $order_id = $conn->insert_id;
    error_log("Insert successful, order_id: " . $order_id);
    
    // Verify immediately
    $verify = $conn->query("SELECT reference_no FROM orders WHERE id = $order_id");
    $row = $verify->fetch_assoc();
    error_log("Verified reference_no from DB: '" . ($row['reference_no'] ?? 'NULL') . "'");
    error_log("=== DEBUG END ===");
    
    $test_stmt->close();
    
    // Now update with remaining fields
    if (!is_null($billing_address_id) || !is_null($latitude) || !is_null($longitude) || $delivery_distance > 0) {
        $update_stmt = $conn->prepare("UPDATE orders SET 
            billing_address_id = ?, 
            latitude = ?, 
            longitude = ?, 
            delivery_distance = ?
            WHERE id = ?");
        
        if ($update_stmt) {
            $billing_id_var = $billing_address_id;
            $lat_var = $latitude;
            $lon_var = $longitude;
            $dist_var = (float)$delivery_distance;
            $order_id_var = $order_id;
            
            $update_stmt->bind_param("idddi", $billing_id_var, $lat_var, $lon_var, $dist_var, $order_id_var);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    // ✅ GET CART ITEMS AND ADD TO ORDER_ITEMS
    $cart_stmt = $conn->prepare("
        SELECT uci.*, 
               COALESCE(pv.origin, '') as origin,
               pv.delivery_size_id,
               ds.size_name,
               ds.percentage as delivery_size_percentage
        FROM user_cart_items uci 
        LEFT JOIN product_variants pv ON uci.variant_id = pv.id 
        LEFT JOIN delivery_sizes ds ON pv.delivery_size_id = ds.id
        WHERE uci.user_id = ?
    ");
    
    if (!$cart_stmt) {
        throw new Exception('Failed to prepare cart statement: ' . $conn->error);
    }

    $user_id_cart = $user_id;
    $cart_stmt->bind_param("i", $user_id_cart);
    if (!$cart_stmt->execute()) {
        throw new Exception('Failed to get cart items: ' . $cart_stmt->error);
    }

    $cart_result = $cart_stmt->get_result();
    $cart_items = [];
    while ($row = $cart_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    $cart_stmt->close();

    if (empty($cart_items)) {
        // Delete the order since there are no items
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception('No items found in cart');
    }

    // ✅ Insert order items with correct parameter count
    $order_item_stmt = $conn->prepare("INSERT INTO order_items (
        order_id, product_id, product_name, codename, type_name, 
        variant_color, size, price, quantity, subtotal, 
        descrip6, descrip7, origin, delivery_fee_per_item, item_total_delivery
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$order_item_stmt) {
        throw new Exception('Failed to prepare order items statement: ' . $conn->error);
    }

    foreach ($cart_items as $item) {
        $item_subtotal = floatval($item['price']) * intval($item['quantity']);
        
        // Extract item details safely
        $product_name = $item['variant_name'] ?? $item['product_name'] ?? 'Product';
        $color = $item['color_name'] ?? $item['variant_color'] ?? '';
        $codename = $item['codename'] ?? '';
        $type_name = $item['type_name'] ?? '';
        $size = $item['size'] ?? '';
        $descrip6 = $item['descrip6'] ?? '';
        $descrip7 = $item['descrip7'] ?? '';
        $origin = $item['origin'] ?? '';
        
        // Delivery fees (can be 0 for now)
        $delivery_fee_per_item = 0.00;
        $item_total_delivery = 0.00;

        // Create variables for all bind_param arguments
        $order_id_var = $order_id;
        $product_id_var = intval($item['product_id']);
        $product_name_var = $product_name;
        $codename_var = $codename;
        $type_name_var = $type_name;
        $color_var = $color;
        $size_var = $size;
        $price_var = floatval($item['price']);
        $quantity_var = intval($item['quantity']);
        $item_subtotal_var = $item_subtotal;
        $descrip6_var = $descrip6;
        $descrip7_var = $descrip7;
        $origin_var = $origin;
        $delivery_fee_per_item_var = $delivery_fee_per_item;
        $item_total_delivery_var = $item_total_delivery;

        $order_item_stmt->bind_param("iisssssdidsssdd", 
            $order_id_var,                // i - 1
            $product_id_var,              // i - 2
            $product_name_var,            // s - 3
            $codename_var,                // s - 4
            $type_name_var,               // s - 5
            $color_var,                   // s - 6
            $size_var,                    // s - 7
            $price_var,                   // d - 8
            $quantity_var,                // i - 9
            $item_subtotal_var,           // d - 10
            $descrip6_var,                // s - 11
            $descrip7_var,                // s - 12
            $origin_var,                  // s - 13
            $delivery_fee_per_item_var,   // d - 14
            $item_total_delivery_var      // d - 15
        );
        
        if (!$order_item_stmt->execute()) {
            error_log("Failed to insert order item: " . $order_item_stmt->error);
            // Don't throw exception here, just log the error
        }
    }
    
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

    // Debug logging
    error_log("PayMongo Request Data: " . json_encode($checkout_data));
    error_log("PayMongo Response: " . $response);
    error_log("PayMongo HTTP Code: " . $http_code);

    if ($curl_error) {
        // Delete order if PayMongo fails
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("PayMongo connection failed: $curl_error");
    }

    if ($http_code !== 200) {
        // Delete order if PayMongo fails
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("PayMongo API error: HTTP $http_code - Response: " . substr($response, 0, 500));
    }

    $paymongo_response = json_decode($response, true);
    if (!$paymongo_response || !isset($paymongo_response['data']['id'])) {
        // Delete order if PayMongo response is invalid
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        $conn->query("DELETE FROM orders WHERE id = $order_id");
        throw new Exception("Invalid PayMongo response structure");
    }

    // ✅ UPDATE ORDER WITH PAYMONGO SESSION ID
    $session_id = $paymongo_response['data']['id'];
    $update_stmt = $conn->prepare("UPDATE orders SET paymongo_session_id = ? WHERE id = ?");
    if (!$update_stmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }
    
    // Create variables for bind_param
    $session_id_var = $session_id;
    $order_id_var = $order_id;
    
    $update_stmt->bind_param("si", $session_id_var, $order_id_var);
    if (!$update_stmt->execute()) {
        error_log("Failed to update order with PayMongo session ID: " . $update_stmt->error);
    }
    $update_stmt->close();

    // ✅ STORE ORDER INFO IN SESSION FOR SUCCESS PAGE
    $_SESSION['pending_paymongo_order'] = [
        'order_id' => $order_id,
        'session_id' => $session_id,
        'reference_no' => $reference_no,
        'amount' => $amount
    ];

    // Return PayMongo's response
    echo json_encode($paymongo_response);

} catch (Exception $e) {
    error_log("PayMongo create session error: " . $e->getMessage() . " - Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Error $e) {
    error_log("PayMongo create session fatal error: " . $e->getMessage() . " - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>