<?php
// paymongo-create-sessions.php - FINAL DEBUG VERSION WITH STOCK DEDUCTION
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
    
    // ✅ NEW: Get referral discount data from SESSION (saved during checkout)
    $referral_code = isset($_SESSION['applied_referral_code']) ? trim($_SESSION['applied_referral_code']) : null;
    $referral_user_id = null;
    $referral_discount = 0.00;
    
    // If referral code exists, validate and get discount info
    if (!empty($referral_code)) {
        $stmt = $conn->prepare("SELECT user_id, discount_type, discount_value 
                               FROM referral_codes 
                               WHERE referral_code = ? AND is_active = 1 AND discount_enabled = 1 
                               LIMIT 1");
        $stmt->bind_param("s", $referral_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $ref_data = $result->fetch_assoc();
            $referral_user_id = $ref_data['user_id'];
            
            // Calculate discount based on subtotal (before VAT and delivery)
            $subtotal = ($amount - $delivery_fee) / 1.12; // Remove VAT to get base
            
            if ($ref_data['discount_type'] === 'percentage') {
                $referral_discount = $subtotal * ($ref_data['discount_value'] / 100);
            } else {
                $referral_discount = min($ref_data['discount_value'], $subtotal);
            }
        }
        $stmt->close();
    }
    
    error_log("=== PAYMONGO REFERRAL DEBUG ===");
    error_log("Referral Code: " . ($referral_code ?? 'NONE'));
    error_log("Referral Discount: ₱" . number_format($referral_discount, 2));
    error_log("Referral User ID: " . ($referral_user_id ?? 'NONE'));
    error_log("==============================");
    
    // Extract order details with fallbacks to session
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
    
    // ✅ CRITICAL: Vehicle data - prioritize order_details, fallback to session
$assigned_vehicle_id_input = !empty($order_details['assigned_vehicle_id']) 
    ? intval($order_details['assigned_vehicle_id']) 
    : (!empty($_SESSION['checkout_step3']['assigned_vehicle_id']) 
        ? intval($_SESSION['checkout_step3']['assigned_vehicle_id']) 
        : null);

$assigned_vehicle_id = null;  // Will be validated later

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

    // ✅ DEBUG LOG: Print incoming data
    error_log("=== PAYMONGO DEBUG ===");
    error_log("Input Vehicle ID: " . var_export($assigned_vehicle_id_input, true));
    error_log("Input Vehicle Type: " . var_export($assigned_vehicle_type, true));
    error_log("Delivery Type: $delivery_type");
    error_log("Total Cubic Meters: $total_cubic_meters");
    error_log("Total Weight: $total_weight_kg kg");
    
    // ✅ CRITICAL FIX: Only validate if vehicle_id is provided AND delivery type is "delivery"
    if ($delivery_type === 'delivery' && !is_null($assigned_vehicle_id_input) && $assigned_vehicle_id_input > 0) {
        error_log("Validating vehicle ID: $assigned_vehicle_id_input from transportify_vehicle_list");
        
        // Check both possible table names or structures
        $vehicle_check = $conn->query("
            SELECT id, vehicle_type FROM transportify_vehicle_list 
            WHERE id = " . intval($assigned_vehicle_id_input) . " 
            LIMIT 1
        ");
        
        if ($vehicle_check && $vehicle_check->num_rows > 0) {
            $vehicle_row = $vehicle_check->fetch_assoc();
            $assigned_vehicle_id = $vehicle_row['id'];
            error_log("✓ Vehicle found: ID={$vehicle_row['id']}, Type={$vehicle_row['vehicle_type']}");
        } else {
            error_log("✗ Vehicle NOT found - checking if table exists...");
            
            // Check if table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'transportify_vehicle_list'");
            if ($table_check->num_rows > 0) {
                error_log("Table EXISTS but vehicle ID $assigned_vehicle_id_input not found - Setting to NULL");
            } else {
                error_log("Table 'transportify_vehicle_list' NOT FOUND - This may cause foreign key errors!");
            }
            
            $assigned_vehicle_id = null;
        }
    } else {
        error_log("Skipping vehicle validation - Delivery Type: $delivery_type, Vehicle ID: " . var_export($assigned_vehicle_id_input, true));
        $assigned_vehicle_id = null;
    }

    // Calculate breakdown - VAT on items only (not delivery)
// Formula: grand_total = (items * 1.12) + delivery
// Step 1: items_with_vat = grand_total - delivery
$items_with_vat = $amount - $delivery_fee;

// Step 2: items = items_with_vat / 1.12
$subtotal = $items_with_vat / 1.12;

// Step 3: VAT amount (12% of items only)
$vat_amount = $subtotal * 0.12;

// Step 4: Items without VAT (same as subtotal)
$items_without_vat = $subtotal;
$reference_no = 'NH' . mt_rand(9800000, 9899999);
    $payment_method = 'PayMongo';
    $payment_status = 'pending';
    $order_status = 'pending';
    $discount_amount = 0.00;

    error_log("Final Vehicle ID for INSERT: " . var_export($assigned_vehicle_id, true));
    error_log("Reference: $reference_no, Amount: $amount");
    error_log("==================");

    // ✅ INSERT ORDER WITH REFERRAL FIELDS
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
    referral_code,
    referral_user_id,
    referral_discount_amount
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insert_sql);
if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
}

// Type string for 30 parameters (added 3 for referral)
$types = "isssssdddddsssssissddddddidsid";

$stmt->bind_param(
    $types,
    $user_id,                  // i
    $customer_name,            // s
    $email,                    // s
    $mobile,                   // s
    $address,                  // s
    $zipcode,                  // s
    $items_without_vat,        // d
    $delivery_fee,             // d
    $amount,                   // d
    $vat_amount,               // d
    $discount_amount,          // d
    $payment_method,           // s
    $payment_status,           // s
    $reference_no,             // s
    $order_status,             // s
    $delivery_type,            // s
    $assigned_vehicle_id,      // i (NULL if not validated)
    $assigned_vehicle_type,    // s
    $total_cubic_meters,       // d
    $total_weight_kg,          // d
    $total_width,              // d
    $total_height,             // d
    $total_length,             // d
    $latitude,                 // d
    $longitude,                // d
    $billing_address_id,       // i
    $delivery_distance,        // d
    $referral_code,            // s ← NEW
    $referral_user_id,         // i ← NEW
    $referral_discount         // d ← NEW
);
    
    if (!$stmt->execute()) {
        error_log("INSERT FAILED: " . $stmt->error);
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $order_id = $conn->insert_id;
    error_log("✓ Order created: ID=$order_id");
    $stmt->close();

// ✅ FIXED: paymongo-create-sessions.php - GET CART ITEMS with explicit color_id
// Replace the "GET CART ITEMS" section (around line 85-105)

// ✅ GET CART ITEMS - EXPLICITLY SELECT color_id
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
        COALESCE(pv.origin, '') as origin
    FROM user_cart_items uci 
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
error_log("Fetching cart items for user: $user_id");

while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
    error_log("Item: Product={$row['product_id']}, Variant={$row['variant_id']}, Color={$row['color_id']}, Qty={$row['quantity']}");
}

error_log("Total cart items: " . count($cart_items));
error_log("=== END CART DEBUG ===");

$cart_stmt->close();

if (empty($cart_items)) {
    $conn->query("DELETE FROM orders WHERE id = $order_id");
    throw new Exception('No items found in cart');
}

    // ✅ INSERT ORDER ITEMS
    $item_stmt = $conn->prepare("INSERT INTO order_items (
    order_id, product_id, variant_id, product_name, codename, type_name, 
    variant_color, size, price, quantity, subtotal, 
    descrip6, descrip7, origin
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
// ↑ Now 14 placeholders instead of 13

if (!$item_stmt) {
    $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
    $conn->query("DELETE FROM orders WHERE id = $order_id");
    throw new Exception('Failed to prepare items statement: ' . $conn->error);
}

foreach ($cart_items as $item) {
    $item_subtotal = floatval($item['price']) * intval($item['quantity']);
    
    // ✅ Extract variant_id from cart item
    $variant_id = isset($item['variant_id']) && !empty($item['variant_id']) 
        ? intval($item['variant_id']) 
        : null;
    
    $product_id = intval($item['product_id']);
    $product_name = $item['variant_name'] ?? $item['product_name'] ?? 'Product';
    $color = $item['color_name'] ?? $item['variant_color'] ?? '';
    $codename = $item['codename'] ?? '';
    $type_name = $item['type_name'] ?? '';
    $size = $item['size'] ?? '';
    $descrip6 = $item['descrip6'] ?? '';
    $descrip7 = $item['descrip7'] ?? '';
    $origin = $item['origin'] ?? '';

        $item_stmt->bind_param(
        "iiisssssdidsss",  // ← Changed from "iisssssdidsss" - added one 'i'
        $order_id,         // i
        $product_id,       // i
        $variant_id,       // i ← NEW: variant_id (can be NULL)
        $product_name,     // s
        $codename,         // s
        $type_name,        // s
        $color,            // s
        $size,             // s
        $item['price'],    // d
        $item['quantity'], // i
        $item_subtotal,    // d
        $descrip6,         // s
        $descrip7,         // s
        $origin            // s
    );
    
    if (!$item_stmt->execute()) {
        error_log("Warning: Failed to insert item: " . $item_stmt->error);
        // Note: We log but don't throw to allow order to complete with partial items
    }
}

$item_stmt->close();

// ✅✅✅ STOCK DEDUCTION FOR PAYMONGO PAYMENTS ✅✅✅
// This deducts stock IMMEDIATELY when PayMongo session is created

error_log("=== PAYMONGO STOCK DEDUCTION START ===");
error_log("Order ID: " . $order_id);
error_log("Total items to process: " . count($cart_items));

foreach ($cart_items as $item) {
    $item_variant_id = $item['variant_id'] ?? null;
    $item_color_id = $item['color_id'] ?? null;
    $item_quantity = $item['quantity'];

    error_log("Processing: Product #" . $item['product_id'] . ", Variant #$item_variant_id, Color #$item_color_id, Qty: $item_quantity");

    // PRIMARY: Deduct from product_variant_colors (Junction Table)
    if (!empty($item_variant_id) && !empty($item_color_id)) {
        error_log("  → Attempting junction table update...");
        
        $deduct_junction = $conn->prepare("
            UPDATE product_variant_colors 
            SET stock_quantity = stock_quantity - ?
            WHERE variant_id = ? AND color_id = ?
        ");
        
        if (!$deduct_junction) {
            error_log("  ✗ Prepare failed: " . $conn->error);
        } else {
            $deduct_junction->bind_param("iii", $item_quantity, $item_variant_id, $item_color_id);
            
            if (!$deduct_junction->execute()) {
                error_log("  ✗ Execute failed: " . $deduct_junction->error);
            } else {
                error_log("  ✓ Rows affected: " . $deduct_junction->affected_rows);
                
                // Check remaining stock IMMEDIATELY
                $check_stock = $conn->prepare("
                    SELECT stock_quantity 
                    FROM product_variant_colors 
                    WHERE variant_id = ? AND color_id = ?
                ");
                $check_stock->bind_param("ii", $item_variant_id, $item_color_id);
                $check_stock->execute();
                $stock_result = $check_stock->get_result();
                
                if ($stock_row = $stock_result->fetch_assoc()) {
                    error_log("  → New stock: {$stock_row['stock_quantity']} units");
                } else {
                    error_log("  ✗ Record not found after update!");
                }
                $check_stock->close();
            }
            $deduct_junction->close();
        }
    } 
    // FALLBACK: If no color_id, deduct from variant only
    elseif (!empty($item_variant_id)) {
        error_log("  → No color_id, using variant fallback...");
        
        $deduct_variant = $conn->prepare("
            UPDATE product_variants 
            SET stock = stock - ?
            WHERE id = ?
        ");
        
        if (!$deduct_variant) {
            error_log("  ✗ Prepare failed: " . $conn->error);
        } else {
            $deduct_variant->bind_param("ii", $item_quantity, $item_variant_id);
            
            if (!$deduct_variant->execute()) {
                error_log("  ✗ Execute failed: " . $deduct_variant->error);
            } else {
                error_log("  ✓ Variant rows affected: " . $deduct_variant->affected_rows);
            }
            $deduct_variant->close();
        }
    } else {
        error_log("  ✗ No variant_id or color_id found!");
    }
}

error_log("=== PAYMONGO STOCK DEDUCTION END ===");

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
                "payment_method_types" => ["gcash", "paymaya", "card", "grab_pay"],
                "success_url" => "http://localhost/noble/user/otherpage/checkout-paymongo-success-page-12-A.php?order_id=" . $order_id . "&ref=" . $reference_no,
                "cancel_url" => "http://localhost/noble/user/otherpage/index-checkout-page-12.php?payment_cancelled=1&order_id=" . $order_id,
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

// ✅ Clear referral code after order is created (will be removed after payment confirmation)
// Don't clear here - wait for payment success

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
?>