<?php
//checkout.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

$tables = ['products', 'orders', 'order_items'];

foreach ($tables as $table) {
    // Get the current highest ID that exists
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    // Reset AUTO_INCREMENT to max_id + 1
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// ✅ Restore session from remember_token (email or mobile-based or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // 🔐 Store essential user session info
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        // 👤 Check if it's a Google account (optional)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }

    $stmt->close();
}

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
    // Not logged in — redirect to login or Google auth
    header('Location: ../google-callback.php'); // You may replace with `index.php` if default login
    exit;
}

// PayPal configuration for sandbox
$paypal_config = [
    'mode' => 'sandbox', // Change to 'live' for production
    'client_id' => 'AT1LmhSbRH3yOGHNRFYZb_WhRkFIUlsdUEIQcNNr_0BXnb6LapA61CTycE7xq0c5W6XrHMpetIfpP-Kd',
    'client_secret' => 'EHkB3XnpMB-mjaw8VeOmWR9dmDoDoIZwLwBoEvWdabiGfgd2kTb6VYfOq4WvuJVEUfVaOmm3rBMfS-QT',
    'currency' => 'PHP'
];



$userName = $_SESSION['user_name'] ?? '';
$userEmail = '';
$userMobile = '';  // ✅ Added mobile variable

// Fetch the correct email and mobile from database
if (isset($_SESSION['user_id'])) {
    $user_id_temp = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT email, mobile FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_temp);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $userEmail = $user_data['email'];
        $userMobile = $user_data['mobile'];  // ✅ Get mobile from database
    }
    $stmt->close();
}

$user_id = $_SESSION['user_id'] ?? 0;
$cart_items = [];
$total_price = 0;
$error = null;
$order_success = false;

// ✅ Fetch delivery zones
$delivery_zones = [];
$stmt = $conn->prepare("SELECT * FROM delivery_zones ORDER BY zone_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $delivery_zones[] = $row;
}
$stmt->close();

// ✅ Fetch delivery settings (keep for store location)
$delivery_settings = null;
$stmt = $conn->prepare("SELECT * FROM delivery_settings ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $delivery_settings = $result->fetch_assoc();
}
$stmt->close();

// ✅ Fetch billing addresses for the user
$billing_addresses = [];
$has_billing_addresses = false;
if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM billing_addresses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $billing_addresses[] = $row;
    }
    $has_billing_addresses = count($billing_addresses) > 0;
    $stmt->close();
}

if ($user_id) {
    // ✅ Fetch cart items with origin and delivery size info
    $stmt = $conn->prepare("
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
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total_price += $row['price'] * $row['quantity'];
    }
    $stmt->close();
}

function generateReferenceNumber()
{
    return 'NH' . mt_rand(9800000, 9899999); // Customize range if needed
}

// ✅ Function to detect zone by postal code
function detectZoneByPostalCode($postal_code, $conn)
{
    // First try exact postal code match
    $stmt = $conn->prepare("
        SELECT dz.* FROM delivery_zones dz 
        JOIN zone_postal_codes zpc ON dz.id = zpc.zone_id 
        WHERE zpc.postal_code = ?
    ");
    $stmt->bind_param("s", $postal_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $zone = $result->fetch_assoc();
        $stmt->close();
        return $zone;
    }
    $stmt->close();

    // Fallback: Auto-detect based on postal code ranges
    $postal_num = intval($postal_code);

    // NCR postal codes (1000-1800)
    if ($postal_num >= 1000 && $postal_num <= 1800) {
        $zone_code = 'NCR';
    }
    // Luzon postal codes (2000-3999)
    elseif ($postal_num >= 2000 && $postal_num <= 3999) {
        $zone_code = 'LUZON';
    }
    // Visayas postal codes (4000-6999)
    elseif ($postal_num >= 4000 && $postal_num <= 6999) {
        $zone_code = 'VISAYAS';
    }
    // Mindanao postal codes (7000-9999)
    elseif ($postal_num >= 7000 && $postal_num <= 9999) {
        $zone_code = 'MINDANAO';
    }
    // Default to Luzon for unknown codes
    else {
        $zone_code = 'LUZON';
    }

    // Get the detected zone
    $stmt = $conn->prepare("SELECT * FROM delivery_zones WHERE zone_code = ?");
    $stmt->bind_param("s", $zone_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $zone = $result->fetch_assoc();
        $stmt->close();
        return $zone;
    }
    $stmt->close();

    // Final fallback to first available zone
    $stmt = $conn->prepare("SELECT * FROM delivery_zones ORDER BY id LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $zone = $result->fetch_assoc();
    $stmt->close();
    return $zone;
}

// ✅ New zone-based delivery calculation
function calculateZoneBasedDelivery($cart_items, $distance_km, $selected_zone)
{
    // Check if NCR (free delivery)
    if ($selected_zone['zone_code'] === 'NCR' || $selected_zone['is_free_delivery']) {
        // Even for free delivery, calculate individual item allocations as 0
        $item_delivery_details = [];
        foreach ($cart_items as $item) {
            $item_delivery_details[] = [
                'item_id' => $item['id'] ?? $item['variant_id'],
                'quantity' => (int)$item['quantity'],
                'delivery_fee_per_item' => 0.00,
                'item_total_delivery' => 0.00,
                'delivery_size_percentage' => $item['delivery_size_percentage'] ?? 0
            ];
        }

        return [
            'total_delivery_cost' => 0.00,
            'base_delivery_fee' => 0.00,
            'additional_fees' => 0.00,
            'delivery_fee_per_item' => 0.00,
            'zone_info' => $selected_zone,
            'is_free' => true,
            'item_delivery_details' => $item_delivery_details
        ];
    }

    // Calculate base delivery fee (distance-based only)
    $base_fee = (float)$selected_zone['base_fee'];
    $included_km = (float)$selected_zone['included_km'];
    $per_km_rate = (float)$selected_zone['per_km_rate'];

    $base_delivery_fee = $base_fee;

    if ($distance_km > $included_km) {
        $extra_km = $distance_km - $included_km;
        $base_delivery_fee += ($extra_km * $per_km_rate);
    }

    // Debug logging
    error_log("DEBUG - Base delivery fee (distance-based): $base_delivery_fee");

    // Calculate additional percentage fees per item
    $item_delivery_details = [];
    $total_additional_fees = 0;

    foreach ($cart_items as $item) {
        $quantity = (int)$item['quantity'];
        $item_percentage = (float)($item['delivery_size_percentage'] ?? 5.0); // Default 5% if no size

        // Calculate additional fee per item as percentage of base delivery cost
        $additional_fee_per_item = ($base_delivery_fee * $item_percentage) / 100;
        $item_total_additional = $additional_fee_per_item * $quantity;

        $item_delivery_details[] = [
            'item_id' => $item['id'] ?? $item['variant_id'],
            'quantity' => $quantity,
            'delivery_fee_per_item' => $additional_fee_per_item,
            'item_total_delivery' => $item_total_additional,
            'delivery_size_percentage' => $item_percentage
        ];

        $total_additional_fees += $item_total_additional;

        // Debug logging for each item
        error_log("DEBUG - Item: {$item['variant_name']}, Percentage: {$item_percentage}%, Additional per item: $additional_fee_per_item, Total additional: $item_total_additional");
    }

    // Calculate final total delivery cost
    $final_delivery_cost = $base_delivery_fee + $total_additional_fees;

    error_log("DEBUG - Base delivery: $base_delivery_fee, Additional fees: $total_additional_fees, Final total: $final_delivery_cost");

    return [
        'total_delivery_cost' => $final_delivery_cost,
        'base_delivery_fee' => $base_delivery_fee,
        'additional_fees' => $total_additional_fees,
        'zone_info' => $selected_zone,
        'is_free' => false,
        'item_delivery_details' => $item_delivery_details
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $billing_address_id = trim($_POST['billing_address_id'] ?? ''); // New billing address ID
    $latitude = null;
    $longitude = null;
    $delivery_distance = (float) ($_POST['delivery_distance'] ?? 0);
    $delivery_fee = (float) ($_POST['delivery_fee'] ?? 0);

    $reference_no = generateReferenceNumber();

    // ✅ ADD THIS BLOCK FOR BANK TRANSFER HANDLING
    $bank_type = null;
    $reference_number = null;
    $screenshot_filename = null;

    // ADD BACK the PayPal configuration at the top of checkout.php:
    $paypal_config = [
        'mode' => 'sandbox', // Change to 'live' for production
        'client_id' => 'AT1LmhSbRH3yOGHNRFYZb_WhRkFIUlsdUEIQcNNr_0BXnb6LapA61CTycE7xq0c5W6XrHMpetIfpP-Kd',
        'client_secret' => 'EHkB3XnpMB-mjaw8VeOmWR9dmDoDoIZwLwBoEvWdabiGfgd2kTb6VYfOq4WvuJVEUfVaOmm3rBMfS-QT',
        'currency' => 'PHP'
    ];

    // ADD BACK the PayPal functions:
    function getPayPalAccessToken($config)
    {
        $url = $config['mode'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api-m.paypal.com/v1/oauth2/token';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("PayPal cURL Error: " . $error);
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("PayPal JSON decode error: " . json_last_error_msg());
            return null;
        }

        return $data['access_token'] ?? null;
    }

    function createPayPalOrder($amount, $order_id, $config)
    {
        $url = $config['mode'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            : 'https://api-m.paypal.com/v2/checkout/orders';

        $access_token = getPayPalAccessToken($config);
        if (!$access_token) {
            error_log("PayPal: Failed to get access token");
            return false;
        }

        $formatted_amount = number_format((float)$amount, 2, '.', '');

        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string)$order_id,
                'amount' => [
                    'currency_code' => $config['currency'],
                    'value' => $formatted_amount
                ],
                'description' => 'Order from Noble Home - Order #' . $order_id
            ]],
            'application_context' => [
                'return_url' => 'http://localhost/noble/user/otherpage/paypal-success.php',
                'cancel_url' => 'http://localhost/noble/user/otherpage/checkout.php',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'brand_name' => 'Noble Home Construction'
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'Prefer: return=representation'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        error_log("PayPal Order Creation Request: " . json_encode($order_data));
        error_log("PayPal API Response: " . $response);
        error_log("PayPal HTTP Code: " . $http_code);

        if ($error) {
            error_log("PayPal cURL Error: " . $error);
        }

        curl_close($ch);

        if ($http_code === 201) {
            $decoded_response = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded_response;
            } else {
                error_log("PayPal JSON decode error: " . json_last_error_msg());
            }
        }

        error_log("PayPal Order Creation Failed. HTTP Code: " . $http_code);
        return false;
    }

    // ADD BACK the main PayPal processing in the POST section:
    if ($payment_method === 'PayPal') {
        try {
            if (!isset($total_price, $delivery_fee)) {
                throw new Exception("Missing required pricing data");
            }

            $subtotal = (float)$total_price;
            $delivery_fee = (float)$delivery_fee;
            $vat_amount = $subtotal * 0.12;
            $grand_total = $subtotal + $vat_amount + $delivery_fee;

            if ($grand_total <= 0) {
                throw new Exception("Invalid order total: " . $grand_total);
            }

            $reference_no = generateReferenceNumber();
            $paypal_order = createPayPalOrder($grand_total, $reference_no, $paypal_config);

            if ($paypal_order && isset($paypal_order['links'])) {
                $payment_status = 'pending_paypal';
                $paypal_order_id = $paypal_order['id'];

                // Insert order with PayPal data
                $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, payment_status, paypal_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if (!$stmt) {
                    throw new Exception("Database prepare failed: " . $conn->error);
                }

                $stmt->bind_param(
                    "ssssssdsiddiddsss",
                    $name,
                    $email,
                    $mobile,
                    $address,
                    $zipcode,
                    $payment_method,
                    $grand_total,
                    $reference_no,
                    $billing_address_id,
                    $latitude,
                    $longitude,
                    $user_id,
                    $delivery_distance,
                    $delivery_fee,
                    $subtotal,
                    $payment_status,
                    $paypal_order_id
                );

                if ($stmt->execute()) {
                    $order_id = $stmt->insert_id;
                    $_SESSION['pending_paypal_order'] = $order_id;

                    // Add order items (your existing code)
                    $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin, delivery_fee_per_item, item_total_delivery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    if (!$stmt2) {
                        throw new Exception("Database prepare failed for order items: " . $conn->error);
                    }

                    foreach ($cart_items as $item) {
                        $subtotal_item = (float)$item['price'] * (int)$item['quantity'];
                        $product_name = $item['product_name'] ?? $item['variant_name'];
                        $codename = $item['codename'] ?? '';
                        $type_name = $item['type_name'] ?? '';
                        $variant_color = $item['variant_color'] ?? '';
                        $size = $item['size'] ?? '';
                        $desc6 = $item['descrip6'] ?? '';
                        $desc7 = $item['descrip7'] ?? '';
                        $origin = $item['origin'] ?? '';
                        $delivery_fee_per_item = 0;
                        $item_total_delivery = 0;

                        $stmt2->bind_param(
                            "iisssssdiisssdd",
                            $order_id,
                            $item['product_id'],
                            $product_name,
                            $codename,
                            $type_name,
                            $variant_color,
                            $size,
                            $item['price'],
                            $item['quantity'],
                            $subtotal_item,
                            $desc6,
                            $desc7,
                            $origin,
                            $delivery_fee_per_item,
                            $item_total_delivery
                        );

                        if (!$stmt2->execute()) {
                            error_log("Failed to insert order item: " . $stmt2->error);
                        }
                    }

                    // Get approval URL and redirect
                    $approval_url = null;
                    foreach ($paypal_order['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            $approval_url = $link['href'];
                            break;
                        }
                    }

                    if ($approval_url) {
                        if (ob_get_level()) {
                            ob_end_clean();
                        }
                        header('Location: ' . $approval_url);
                        exit;
                    } else {
                        throw new Exception("PayPal approval URL not found");
                    }
                } else {
                    throw new Exception("Failed to insert order: " . $stmt->error);
                }
            } else {
                throw new Exception("Failed to create PayPal order");
            }
        } catch (Exception $e) {
            $error = "PayPal payment error: " . $e->getMessage();
            error_log("PayPal error: " . $e->getMessage());
            error_log("PayPal error trace: " . $e->getTraceAsString());
        }
    }

    
// PayMongo configuration
$paymongo_config = [
    'mode' => 'test', // Change to 'live' for production
    'secret_key' => 'sk_test_AJdRkkXWfGW9W5DHV6UNNECZ', // Replace with your actual secret key
    'public_key' => 'pk_test_r4XYBug7KMzvamWvUzjAGAyC', // Replace with your actual public key
    'currency' => 'PHP',
    'success_url' => 'http://localhost/noble/user/otherpage/paymongo-success.php',
    'cancel_url' => 'http://localhost/noble/user/otherpage/checkout.php'
];

// PayMongo helper functions
function createPayMongoCheckoutSession($amount, $order_id, $config, $line_items = []) {
    $url = 'https://api.paymongo.com/v1/checkout_sessions';
    
    // Convert amount to centavos (PayMongo uses centavos)
    $amount_centavos = (int)($amount * 100);
    
    // Default line item if none provided
    if (empty($line_items)) {
        $line_items = [[
            'name' => 'Order from Noble Home - #' . $order_id,
            'quantity' => 1,
            'amount' => $amount_centavos,
            'currency' => $config['currency']
        ]];
    }
    
    $checkout_data = [
        'data' => [
            'attributes' => [
                'amount' => $amount_centavos,
                'currency' => $config['currency'],
                'line_items' => $line_items,
                'payment_method_types' => ['gcash', 'paymaya', 'card', 'grab_pay'],
                'success_url' => $config['success_url'] . '?order_id=' . $order_id,
                'cancel_url' => $config['cancel_url'],
                'description' => 'Noble Home Construction - Order #' . $order_id,
                'reference_number' => (string)$order_id
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($config['secret_key'] . ':')
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkout_data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Debug logging
    error_log("PayMongo Checkout Session Request: " . json_encode($checkout_data));
    error_log("PayMongo API Response: " . $response);
    error_log("PayMongo HTTP Code: " . $http_code);
    
    if ($error) {
        error_log("PayMongo cURL Error: " . $error);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code === 200) {
        $decoded_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded_response;
        } else {
            error_log("PayMongo JSON decode error: " . json_last_error_msg());
        }
    }
    
    error_log("PayMongo Checkout Session Creation Failed. HTTP Code: " . $http_code);
    return false;
}


if ($_POST['payment_method'] === 'PayMongo') {
    try {
        // ✅ FIXED: Get form data properly with correct names
        $customer_name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['email'] ?? '');  
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
        $billing_address_id = trim($_POST['billing_address_id'] ?? '') ?: null;
        $delivery_fee = floatval($_POST['delivery_fee'] ?? 0);
        $delivery_distance = floatval($_POST['delivery_distance'] ?? 0);
        
        // ✅ VALIDATION: Check required fields
        $validation_errors = [];
        
        if (empty($customer_name)) $validation_errors[] = "Customer name is required";
        if (empty($email)) $validation_errors[] = "Email is required";
        if (empty($mobile)) $validation_errors[] = "Mobile number is required";
        if (empty($address)) $validation_errors[] = "Address is required";
        if (empty($zipcode)) $validation_errors[] = "ZIP code is required";
        
        if (!empty($validation_errors)) {
            throw new Exception('Missing required fields: ' . implode(', ', $validation_errors));
        }
        
        // Calculate amounts
        $subtotal = $total_price; // Items subtotal
        $vat_amount = $subtotal * 0.12; // 12% VAT
        $grand_total = $subtotal + $vat_amount + $delivery_fee;
        
        if ($grand_total <= 0) {
            throw new Exception('Invalid order amount');
        }
        
        // Generate reference number
        $reference_no = 'NB' . time() . rand(1000, 9999);
        
        // Get coordinates from billing address if selected
        $latitude = null;
        $longitude = null;
        if (!empty($billing_address_id) && is_numeric($billing_address_id)) {
            $stmt = $conn->prepare("SELECT latitude, longitude FROM billing_addresses WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $billing_address_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $billing_data = $result->fetch_assoc();
                $latitude = $billing_data['latitude'];
                $longitude = $billing_data['longitude'];
            }
            $stmt->close();
        }
        
        $order_id = $conn->insert_id;
        $stmt->close();
        
        // Add cart items to order_items (existing code is correct)
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
            descrip6, descrip7, origin, delivery_fee_per_item, item_total_delivery
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while ($item = $cart_result->fetch_assoc()) {
            $item_subtotal = $item['price'] * $item['quantity'];
            
            $product_name = $item['variant_name'] ?? 'Product';
            $color = $item['color_name'] ?? '';
            $codename = $item['codename'] ?? '';
            $type_name = $item['type_name'] ?? '';
            $size = $item['size'] ?? '';
            $descrip6 = $item['descrip6'] ?? '';
            $descrip7 = $item['descrip7'] ?? '';
            $origin = '';
            
            $order_item_stmt->bind_param(
                "iisssssdiisssdd",
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
                $origin,
                0, // delivery_fee_per_item
                0  // item_total_delivery
            );
            
            if (!$order_item_stmt->execute()) {
                error_log("Failed to insert order item: " . $order_item_stmt->error);
            }
        }
        
        $cart_stmt->close();
        $order_item_stmt->close();
        
        // Create PayMongo checkout session (existing code)
        $amount_in_centavos = intval($grand_total * 100);
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
                        "description" => "Items: ₱" . number_format($subtotal, 2) .
                            " + VAT: ₱" . number_format($vat_amount, 2) .
                            " + Delivery: ₱" . number_format($delivery_fee, 2)
                    ]],
                    "payment_method_types" => ["gcash", "paymaya", "card", "grab_pay"],
                    "success_url" => "http://localhost/noble/user/otherpage/paymongo-success.php?order_id=" . $order_id . "&ref=" . $reference_no,
                    "cancel_url" => "http://localhost/noble/user/otherpage/checkout.php?payment_cancelled=1&order_id=" . $order_id,
                    "description" => "Noble Home Construction - Order #" . $reference_no,
                    "metadata" => [
                        "user_id" => $user_id,
                        "order_id" => $order_id,
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
            throw new Exception('PayMongo connection failed: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            // Delete the order if PayMongo fails
            $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
            $conn->query("DELETE FROM orders WHERE id = $order_id");
            throw new Exception('PayMongo API error: HTTP ' . $http_code . ' - ' . $response);
        }
        
        $paymongo_data = json_decode($response, true);
        
        if (!$paymongo_data || !isset($paymongo_data['data']['attributes']['checkout_url'])) {
            // Delete the order if response is invalid
            $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
            $conn->query("DELETE FROM orders WHERE id = $order_id");
            throw new Exception('Invalid PayMongo response: ' . $response);
        }
        
        // Update order with PayMongo session ID
        $session_id = $paymongo_data['data']['id'];
        $update_stmt = $conn->prepare("UPDATE orders SET paymongo_session_id = ? WHERE id = ?");
        $update_stmt->bind_param("si", $session_id, $order_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Clear user's cart
        $clear_cart_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
        $clear_cart_stmt->bind_param("i", $user_id);
        $clear_cart_stmt->execute();
        $clear_cart_stmt->close();
        
        // Store order info in session for success page
        $_SESSION['pending_paymongo_order'] = [
            'order_id' => $order_id,
            'session_id' => $session_id,
            'reference_no' => $reference_no,
            'amount' => $grand_total
        ];
        
        // Redirect to PayMongo checkout
        header('Location: ' . $paymongo_data['data']['attributes']['checkout_url']);
        exit;
        
    } catch (Exception $e) {
        $error = "PayMongo payment error: " . $e->getMessage();
        error_log("PayMongo error: " . $e->getMessage());
    }
}

    // Handle Bank Transfer specific data
    if ($payment_method === 'Bank Transfer') {
        $bank_type = trim($_POST['bank_type'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');

        // Handle screenshot upload
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/payment_screenshots/';

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $file_extension = strtolower(pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $screenshot_filename = 'payment_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_extension;

                if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $upload_dir . $screenshot_filename)) {
                    $validation_errors[] = "Failed to upload screenshot";
                }
            } else {
                $validation_errors[] = "Invalid file format. Please upload JPG, PNG, or GIF files only";
            }
        } elseif ($payment_method === 'Bank Transfer') {
            $validation_errors[] = "Payment screenshot is required for bank transfer";
        }

        // Validate bank type
        if (empty($bank_type) || !in_array($bank_type, ['bdo', 'aub'])) {
            $validation_errors[] = "Please select a valid bank";
        }
    }

    // Enhanced validation with specific error messages
    $validation_errors = [];

    if (empty($name)) {
        $validation_errors[] = "Full Name is required";
    }

    if (empty($email)) {
        $validation_errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Invalid email format";
    }

    if (empty($mobile)) {
        $validation_errors[] = "Mobile number is required";
    } else {
        // Clean the mobile number - remove spaces, dashes, parentheses, plus signs
        $cleaned_mobile = preg_replace('/[\s\-\(\)\+]/', '', $mobile);

        // Check if it starts with +63 and convert to 09 format
        if (preg_match('/^63([0-9]{10})$/', $cleaned_mobile, $matches)) {
            $cleaned_mobile = '0' . $matches[1];
        }

        // Validate cleaned mobile number
        if (!preg_match('/^09[0-9]{9}$/', $cleaned_mobile)) {
            $validation_errors[] = "Mobile number must be a valid Philippine mobile number (e.g., 09171234567)";
        } else {
            // Update the mobile variable with cleaned format
            $mobile = $cleaned_mobile;
        }
    }

    if (empty($address)) {
        $validation_errors[] = "Address is required";
    }

    if (empty($zipcode)) {
        $validation_errors[] = "ZIP Code is required";
    } elseif (!preg_match('/^[0-9]{4}$/', $zipcode)) {
        $validation_errors[] = "ZIP Code must be exactly 4 digits";
    }

    // ✅ Zone detection and selection
    $selected_zone = null;
    $zone_id = trim($_POST['zone_id'] ?? '');

    if (!empty($zone_id) && is_numeric($zone_id)) {
        // Manual zone selection
        $stmt = $conn->prepare("SELECT * FROM delivery_zones WHERE id = ?");
        $stmt->bind_param("i", $zone_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $selected_zone = $result->fetch_assoc();
        }
        $stmt->close();
    } else {
        // Auto-detect by postal code
        $selected_zone = detectZoneByPostalCode($zipcode, $conn);
    }

    if (!$selected_zone) {
        $validation_errors[] = "Unable to determine delivery zone. Please select manually.";
    }

    if (empty($payment_method)) {
        $validation_errors[] = "Payment method is required";
    }

    if (empty($cart_items)) {
        $validation_errors[] = "Your cart is empty";
    }

    // ✅ If billing address is selected, fetch coordinates
    if (!empty($billing_address_id) && is_numeric($billing_address_id)) {
        $stmt = $conn->prepare("SELECT latitude, longitude FROM billing_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $billing_address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $billing_data = $result->fetch_assoc();
            $latitude = $billing_data['latitude'];
            $longitude = $billing_data['longitude'];
        }
        $stmt->close();
    }

    // If there are validation errors, show them
    if (!empty($validation_errors)) {
        $error = implode(', ', $validation_errors);
    } else {
        // All validations passed, proceed with order
        try {
            // ✅ Reset auto-increment if needed
            $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE order_items AUTO_INCREMENT = 1");

            // ✅ Calculate total with delivery fee and VAT (VAT only on items)
            $subtotal = $total_price; // Items subtotal only
            $vat_amount = $subtotal * 0.12; // 12% VAT only on items (not on delivery)
            $grand_total = $subtotal + $vat_amount + $delivery_fee; // Items + VAT + Delivery

            // ✅ Save order (FIXED: Use correct number of placeholders - 19 placeholders)
            $payment_status = ($payment_method === 'Bank Transfer') ? 'pending' : 'verified';
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, bank_type, payment_screenshot, reference_number, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // FIXED: Bind exactly 19 parameters (matching the placeholders)
            $stmt->bind_param(
                "ssssssdsiddiddsssss",
                $name,                  // s
                $email,                 // s  
                $mobile,                // s
                $address,               // s
                $zipcode,               // s
                $payment_method,        // s
                $grand_total,           // d
                $reference_no,          // s
                $billing_address_id,    // i
                $latitude,              // d
                $longitude,             // d
                $user_id,               // i
                $delivery_distance,     // d
                $delivery_fee,          // d
                $subtotal,              // d
                $bank_type,             // s
                $screenshot_filename,   // s
                $reference_number,      // s
                $payment_status         // s
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $stmt->insert_id;
            $stmt->close();

            // ✅ Calculate zone-based delivery with size percentages
            $delivery_result = calculateZoneBasedDelivery($cart_items, $delivery_distance, $selected_zone);
            $delivery_fee = $delivery_result['total_delivery_cost'];
            $item_delivery_details = $delivery_result['item_delivery_details'] ?? [];

            // ✅ Save each item with individual delivery calculations
            $stmt = $conn->prepare("INSERT INTO order_items (
    order_id, product_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin, delivery_fee_per_item, item_total_delivery
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($cart_items as $index => $item) {
                $subtotal_item = $item['price'] * $item['quantity'];
                $product_name = $item['product_name'] ?? $item['variant_name'];
                $codename = $item['codename'] ?? '';
                $type_name = $item['type_name'] ?? '';
                $variant_color = $item['color_name'] ?: ($item['variant_name'] ?? '');
                $size = $item['size'] ?? '';
                $price = $item['price'];
                $quantity = $item['quantity'];

                // Get delivery details for this specific item
                $item_delivery_detail = $item_delivery_details[$index] ?? null;

                if ($item_delivery_detail) {
                    $delivery_fee_per_item = $item_delivery_detail['delivery_fee_per_item'];
                    $item_total_delivery = $item_delivery_detail['item_total_delivery'];
                } else {
                    // Fallback calculation
                    $delivery_fee_per_item = 0;
                    $item_total_delivery = 0;
                }

                // ✅ Get descrip6 and descrip7 directly from cart item
                $desc6 = $item['descrip6'] ?? '';
                $desc7 = $item['descrip7'] ?? '';
                $origin = $item['origin'] ?? '';
                $product_id = $item['product_id'] ?? null;

                $stmt->bind_param(
                    "iisssssiiisssdd",
                    $order_id,
                    $product_id,
                    $product_name,
                    $codename,
                    $type_name,
                    $variant_color,
                    $size,
                    $price,
                    $quantity,
                    $subtotal_item,
                    $desc6,
                    $desc7,
                    $origin,
                    $delivery_fee_per_item,
                    $item_total_delivery
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to save order item: " . $stmt->error);
                }
            }
            $stmt->close();

            // ✅ Clear cart
            $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to clear cart: " . $stmt->error);
            }
            $stmt->close();

            // Set success flag to show modal
            $order_success = true;
            $_SESSION['checkout_notice'] = 'Order placed successfully!';

            // Check if it's an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Order placed successfully!',
                    'redirect_url' => 'order_receipt.php?order_id=' . $order_id
                ]);
                exit;
            } else {
                // Regular form submission - redirect normally
                header('Location: order_receipt.php?order_id=' . $order_id);
                exit;
            }
        } catch (Exception $e) {
            $error = "An error occurred while processing your order. Please try again.";
            // Log the actual error for debugging
            error_log("Checkout error: " . $e->getMessage());
        }
    }
}


function formatItemDetails($item)
{
    $details = [];
    if (!empty($item['type_name'])) {
        $details[] = $item['type_name'];
    }
    if (!empty($item['size']) && trim($item['size']) !== '') {
        $details[] = 'Size: ' . $item['size'];
    }
    if (!empty($item['materials'])) {
        $details[] = $item['materials'];
    }
    if (!empty($item['color_name'])) {
        $details[] = 'Color: ' . $item['color_name'];
    } elseif (!empty($item['variant_name'])) {
        $details[] = $item['variant_name'];
    }
    if (!empty($item['codename'])) {
        $details[] = 'Code: ' . $item['codename'];
    }
    // ADD THIS LINE:
    if (!empty($item['origin'])) {
        $details[] = 'Origin: ' . $item['origin'];
    }
    if (!empty($item['descrip6'])) {
        $details[] = ' ' . $item['descrip6'];
    }
    if (!empty($item['descrip7'])) {
        $details[] = ' ' . $item['descrip7'];
    }
    return implode('<br>', array_map('htmlspecialchars', $details));
}

// SIMPLIFIED: Function to get descrip6 and descrip7 for display
function getProductDescription($conn, $codename, $variant_name = '', $variant_id = null)
{
    // Since descrip6 and descrip7 are already in user_cart_items, 
    // this function is not needed for cart items
    // But keeping it for other potential uses
    $desc6 = '';
    $desc7 = '';

    if (!empty($codename)) {
        $desc_stmt = $conn->prepare("SELECT descrip6, descrip7 FROM product_variants WHERE namevariant = ? LIMIT 1");
        $desc_stmt->bind_param("s", $codename);
        $desc_stmt->execute();
        $desc_result = $desc_stmt->get_result();

        if ($desc_result->num_rows > 0) {
            $desc_data = $desc_result->fetch_assoc();
            $desc6 = $desc_data['descrip6'] ?? '';
            $desc7 = $desc_data['descrip7'] ?? '';
        }
        $desc_stmt->close();
    }

    return ['desc6' => $desc6, 'desc7' => $desc7];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <meta name="permissions-policy" content="geolocation=()">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Checkout - Step by Step</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.paypal.com/sdk/js?client-id=AT1LmhSbRH3yOGHNRFYZb_WhRkFIUlsdUEIQcNNr_0BXnb6LapA61CTycE7xq0c5W6XrHMpetIfpP-Kd&currency=PHP&intent=capture&enable-funding=venmo,card&disable-funding=credit,bancontact,eps,giropay,ideal,mybank,p24,sepa,sofort&locale=en_PH"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
</head>

<body class="bg-gray-100 font-mont">

    <script>
        // Initialize global configuration object with PHP data
        window.checkoutConfig = {
            deliverySettings: <?= $delivery_settings ? json_encode($delivery_settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>,
            deliveryZones: <?= json_encode($delivery_zones, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            totalPrice: <?= (float)$total_price ?>,
            hasAddresses: <?= $has_billing_addresses ? 'true' : 'false' ?>
        };
    </script>

    <?php include '../navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow mt-3 max-w-7xl mx-auto">
        <h2 class="text-3xl font-bold text-orange-700 mb-8">Checkout Process</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between sm:mb-6 space-y-6 sm:space-y-0">

                <!-- Step 1 -->
                <div class="flex items-center step-indicator flex-1" data-step="1">
                    <div class="step-circle active">1</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Customer Info</div>
                        <div class="text-gray-500">Basic details</div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden sm:block flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 2 -->
                <div class="flex items-center step-indicator flex-1" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Delivery Address</div>
                        <div class="text-gray-500">Where to deliver</div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden sm:block flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 3 -->
                <div class="flex items-center step-indicator flex-1" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Delivery Fee</div>
                        <div class="text-gray-500">Calculate costs</div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden sm:block flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 4 -->
                <div class="flex items-center step-indicator flex-1" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Payment</div>
                        <div class="text-gray-500">Complete order</div>
                    </div>
                </div>
            </div>
        </div>


        <form method="POST" class="space-y-6" id="checkoutForm" enctype="multipart/form-data">

            <!-- STEP 1: Customer Information -->
            <div class="step-content" id="step1">
                <div class=" p-4 mb-6">
                    <div class="flex items-center">

                        <div>
                            <h3 class="text-lg font-bold text-black">Step 1: Customer Information</h3>
                            <p class="text-black text-sm">Verify your basic details</p>
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-medium mb-2">Full Name</label>
                        <input type="text" name="customer_name" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg bg-gray-50"
                            value="<?= htmlspecialchars($userName ?? '') ?>" readonly />
                    </div>

                    <div>
                        <label class="block font-medium mb-2">Email Address</label>
                        <input type="email" name="email" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg bg-gray-50"
                            value="<?= htmlspecialchars($userEmail ?? '') ?>" readonly />
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="button" class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium" onclick="goToStep(2)">
                        Continue
                    </button>
                </div>
            </div>

            <!-- STEP 2: Delivery Address -->
            <div class="step-content hidden" id="step2">
                <div class=" p-4 mb-6">
                    <div class="flex items-center">

                        <div>
                            <h3 class="text-lg font-bold text-black">Step 2: Delivery Address</h3>
                            <p class="text-black text-sm">Choose where to deliver your order</p>
                        </div>
                    </div>
                </div>

                <?php if ($has_billing_addresses): ?>
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <label class="block font-medium text-lg">Select Delivery Address</label>
                            <a href="update_billing_add.php" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add New Address
                            </a>
                        </div>

                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php foreach ($billing_addresses as $addr): ?>
                                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-orange-300 billing-address-option transition">
                                    <input type="radio" name="billing_address_id" value="<?= $addr['id'] ?>" class="mt-2 mr-4" required
                                        data-full-name="<?= htmlspecialchars($addr['full_name']) ?>"
                                        data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                                        data-address="<?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?>"
                                        data-postal-code="<?= htmlspecialchars($addr['postal_code']) ?>"
                                        data-latitude="<?= $addr['latitude'] ?>"
                                        data-longitude="<?= $addr['longitude'] ?>" />
                                    <div class="flex-1">
                                        <div class="font-bold text-lg text-gray-800"><?= htmlspecialchars($addr['full_name']) ?></div>
                                        <div class="text-orange-600 font-medium"><?= htmlspecialchars($addr['phone']) ?></div>
                                        <div class="text-gray-600 mt-1"><?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?></div>
                                        <div class="text-sm text-gray-500 mt-1">ZIP: <?= htmlspecialchars($addr['postal_code']) ?></div>
                                        <?php if (!empty($addr['notes'])): ?>
                                            <div class="text-sm text-gray-500 italic mt-1"><?= htmlspecialchars($addr['notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Address Details Display -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-4">Selected Address Details</h4>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium mb-2">Mobile Number</label>
                                <input type="tel" name="mobile" id="mobileInput" pattern="[0-9]{11}" required
                                    class="w-full border px-4 py-3 rounded-lg bg-white" readonly />
                            </div>
                            <div>
                                <label class="block font-medium mb-2">ZIP Code</label>
                                <input type="text" name="zipcode" id="zipcodeInput" pattern="[0-9]{4}" required
                                    class="w-full border px-4 py-3 rounded-lg bg-white" readonly />
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block font-medium mb-2">Full Address</label>
                            <textarea name="address" id="addressInput" rows="3" required
                                class="w-full border px-4 py-3 rounded-lg resize-none bg-white" readonly></textarea>
                        </div>
                        <!-- Modified HTML section - make the select disabled/readonly -->
                        <div class="mt-6">
                            <label class="block font-medium mb-2">Delivery Zone</label>

                            <!-- Hidden input to store the selected zone ID for form submission -->
                            <input type="hidden" name="zone_id" id="selectedZoneId" value="">

                            <!-- Display area for the detected zone -->
                            <div id="zoneDisplay" class="w-full border border-gray-300 px-4 py-3 rounded-lg bg-gray-50">
                                <div id="zoneInfo" class="text-gray-500">
                                    Auto-detecting delivery zone...
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 mt-1" id="zoneDescription">
                                Delivery zone will be automatically selected based on your address
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="border-2 border-dashed border-red-300 rounded-lg p-8 text-center bg-red-50">
                        <svg class="mx-auto w-16 h-16 text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <h4 class="font-bold text-red-900 text-xl mb-2">No Delivery Address Found</h4>
                        <p class="text-red-700 mb-6">You need to set up at least one delivery address to continue with your order.</p>
                        <a href="update_billing_add.php" class="inline-flex items-center gap-2 bg-red-600 text-white px-8 py-3 rounded-lg hover:bg-red-700 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Set up your address now
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($has_billing_addresses): ?>
                    <div class="flex justify-between mt-8">
                        <button type="button" class="bg-gray-500 text-white px-8 py-3 rounded-lg hover:bg-gray-600 transition font-medium" onclick="goToStep(1)">
                            Back to Customer Info
                        </button>
                        <button type="button" id="continueToDelivery" class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium" onclick="goToStep(3)" disabled>
                            Continue
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- STEP 3: Delivery Fee Calculation -->
            <div class="step-content hidden" id="step3">
                <div class=" p-4 mb-6">
                    <div class="flex items-center">

                        <div>
                            <h3 class="text-lg font-bold text-black">Step 3: Calculate Delivery Fee</h3>
                            <p class="text-black text-sm">Determine delivery costs based on distance</p>
                        </div>
                    </div>
                </div>

                <?php if ($delivery_settings && $has_billing_addresses): ?>
                    <div class="bg-white rounded-lg p-6">
                        <h4 class="font-bold text-gray-800 mb-4">Delivery Distance Calculator</h4>

                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h5 class="font-bold text-gray-700 mb-3">Store Information</h5>
                                <p class="text-sm text-gray-600 mb-2">
                                    <strong>Location:</strong> <?= htmlspecialchars($delivery_settings['location_name']) ?>
                                </p>
                                <div class="text-sm space-y-1">
                                    <div><strong>Base Fee:</strong> ₱<?= number_format($delivery_settings['base_fee'], 2) ?></div>
                                    <div><strong>Per KM Rate:</strong> ₱<?= number_format($delivery_settings['per_km_rate'], 2) ?></div>
                                    <div><strong>Free Delivery Distance:</strong> <?= $delivery_settings['total_km_base_fee'] ?> km</div>
                                </div>
                            </div>

                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h5 class="font-bold text-gray-700 mb-3">Distance Calculation</h5>
                                <div class="space-y-3">
                                    <button type="button" id="calculateDistance"
                                        class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition font-medium disabled:bg-gray-400"
                                        disabled>
                                        Calculate Distance & Fee
                                    </button>
                                    <button type="button" id="showMapModal"
                                        class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition font-medium disabled:bg-gray-400 flex items-center justify-center gap-2"
                                        disabled>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                        </svg>
                                        View on Map
                                    </button>
                                </div>
                                <div id="distanceResult" class="mt-4 text-sm"></div>
                            </div>
                        </div>

                        <!-- Hidden inputs for calculated values -->
                        <input type="hidden" name="delivery_distance" id="deliveryDistance" value="0">
                        <input type="hidden" name="delivery_fee" id="deliveryFee" value="0">
                    </div>
                <?php endif; ?>

                <div class="flex justify-between mt-8">
                    <button type="button" class="bg-gray-500 text-white px-8 py-3 rounded-lg hover:bg-gray-600 transition font-medium" onclick="goToStep(2)">
                        Back to Address
                    </button>
                    <button type="button" id="continueToPayment" class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium" onclick="goToStep(4)" disabled>
                        Continue
                    </button>
                </div>
            </div>

            <!-- STEP 4: Payment & Order Summary -->
            <div class="step-content hidden" id="step4">
                <div class=" p-4 mb-6">
                    <div class="flex items-center">
                        <div>
                            <h3 class="text-lg font-bold text-purple-800">Step 4: Payment & Final Review</h3>
                            <p class="text-purple-700 text-sm">Choose payment method and complete your order</p>
                        </div>
                    </div>
                </div>

                <div class="grid lg:grid-cols-2 gap-8">
                    <!-- Payment Method -->
                    <div class="bg-white border rounded-lg p-6">
                        <h4 class="font-bold text-gray-800 mb-4">Select Payment Method</h4>

                        <div class="space-y-4">
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-purple-300 transition">
                                <input type="radio" name="payment_method" value="Bank Transfer" required class="mr-4" />

                                <div class="flex items-center">
                                    <div class="text-purple-600 mr-3">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm8 0a2 2 0 104 0 2 2 0 00-4 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">Bank Transfer</div>
                                        <div class="text-sm text-gray-600">Transfer to our bank account</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-blue-300 transition">
                                <input type="radio" name="payment_method" value="PayPal" required class="mr-4" />
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <!-- Official PayPal Logo SVG -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                                            <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                                            <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">PayPal</div>
                                        <div class="text-sm text-gray-600">Pay securely with PayPal</div>
                                    </div>
                                </div>
                            </label>
                            <!-- NEW: PayMongo Option -->
    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-green-300 transition">
        <input type="radio" name="payment_method" value="PayMongo" required class="mr-4" />
        <div class="flex items-center">
            <div class="text-green-600 mr-3">
                <!-- PayMongo-style icon -->
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
            <div>
                <div class="font-medium">PayMongo</div>
                <div class="text-sm text-gray-600">GCash, Maya, Credit Card, GrabPay</div>
            </div>
        </div>
    </label>

                        </div>

                        <!-- Bank Transfer Fields -->
                        <div id="bankTransferFields" class="hidden mt-6 p-4 bg-blue-50 rounded-lg">
                            <input type="hidden" name="bank_type" id="selectedBank">
                            <input type="hidden" name="reference_number" id="referenceNumber">
                            <div id="bankSelectionArea">
                                <!-- Bank selection will be populated by JavaScript -->
                            </div>
                        </div>

                        <div id="paypalFields" class="hidden mt-6 p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="text-blue-600">
                                    <!-- Official PayPal Logo SVG -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                                        <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                                        <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h5 class="font-bold text-blue-800">PayPal Payment</h5>
                                    <p class="text-sm text-blue-600">You will be redirected to PayPal to complete your payment securely.</p>
                                </div>
                            </div>

                            <div class="bg-blue-100 border border-blue-200 rounded-lg p-4">
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Amount:</span>
                                        <span class="font-bold text-blue-800" id="paypalAmount">₱0.00</span>
                                    </div>
                                    <div class="text-xs text-blue-600 mt-2">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li>Safe and secure payment with PayPal</li>
                                            <li>Pay with your PayPal balance, bank account, or credit card</li>
                                            <li>No need to share financial details with us</li>
                                            <li>Instant payment confirmation</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div id="paypal-button-container" class="mt-4"></div>
                        </div>

                        <!-- PayMongo Fields (add this after the existing payment fields) -->
<div id="paymongoFields" class="hidden mt-6 p-4 bg-green-50 rounded-lg">
    <div class="flex items-center gap-3 mb-4">
        <div class="text-green-600">
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        </div>
        <div>
            <h5 class="font-bold text-green-800">PayMongo Payment</h5>
            <p class="text-sm text-green-600">You will be redirected to PayMongo to complete your payment securely.</p>
        </div>
    </div>

    <div class="bg-green-100 border border-green-200 rounded-lg p-4 mb-4">
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total Amount:</span>
                <span class="font-bold text-green-800" id="paymongoAmount">₱0.00</span>
            </div>
            <div class="text-xs text-green-600 mt-2">
                <h6 class="font-semibold mb-2">Available Payment Methods:</h6>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>GCash</strong> - Pay using your GCash wallet</li>
                    <li><strong>Maya (PayMaya)</strong> - Use your Maya account</li>
                    <li><strong>Credit/Debit Cards</strong> - Visa, Mastercard, JCB</li>
                    <li><strong>GrabPay</strong> - Pay with GrabPay wallet</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="bg-white border border-green-200 rounded-lg p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-2">
                    <span class="text-blue-600 font-bold text-xs">GCash</span>
                </div>
                <span class="text-xs text-gray-600">GCash Wallet</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-2">
                    <span class="text-green-600 font-bold text-xs">Maya</span>
                </div>
                <span class="text-xs text-gray-600">Maya Account</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-2">
                    <span class="text-purple-600 font-bold text-xs">CARD</span>
                </div>
                <span class="text-xs text-gray-600">Credit/Debit</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-2">
                    <span class="text-orange-600 font-bold text-xs">Grab</span>
                </div>
                <span class="text-xs text-gray-600">GrabPay</span>
            </div>
        </div>
    </div>

    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded text-xs text-green-700">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
            </svg>
            <span class="font-semibold">Secure Payment Powered by PayMongo</span>
        </div>
        <ul class="mt-2 space-y-1 ml-6">
            <li>✓ Bank-level security and encryption</li>
            <li>✓ Instant payment confirmation</li>
            <li>✓ No need to share card details with us</li>
            <li>✓ PCI DSS compliant payment processing</li>
        </ul>
    </div>
</div>
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-white border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b">
                            <h4 class="font-bold text-gray-800">Order Summary</h4>
                        </div>

                        <!-- Scrollable items -->
                        <div class="max-h-80 overflow-y-auto divide-y divide-gray-200">
                            <?php foreach ($cart_items as $index => $item): ?>
                                <div class="p-4" id="cartItem<?= $index ?>"
                                    data-delivery-size-percentage="<?= htmlspecialchars($item['delivery_size_percentage'] ?? '5.0') ?>"
                                    data-delivery-size-name="<?= htmlspecialchars($item['size_name'] ?? 'default') ?>"
                                    data-item-index="<?= $index ?>">
                                    <!-- Hidden inputs for JavaScript access -->
                                    <input type="hidden" name="item_<?= $index ?>_delivery_size_percentage"
                                        value="<?= htmlspecialchars($item['delivery_size_percentage'] ?? '5.0') ?>"
                                        data-delivery-percentage="true">

                                    <div class="flex justify-between items-start gap-4">
                                        <div class="flex-1">
                                            <h5 class="font-bold text-orange-600 mb-2">
                                                <?= htmlspecialchars($item['variant_name']) ?>
                                            </h5>
                                            <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-2">
                                                <?php if (!empty($item['type_name'])): ?>
                                                    <div><strong>Type:</strong> <?= htmlspecialchars($item['type_name']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                                    <div><strong>Size:</strong> <?= htmlspecialchars($item['size']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['color_name'])): ?>
                                                    <div><strong>Color:</strong> <?= htmlspecialchars($item['color_name']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['origin'])): ?>
                                                    <?php $is_local = stripos($item['origin'], 'local') !== false; ?>
                                                    <div class="<?= $is_local ? 'text-blue-600' : 'text-red-600' ?>">
                                                        <strong>Origin:</strong> <?= htmlspecialchars($item['origin']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <!-- NEW: Display delivery size info -->
                                                <?php if (!empty($item['size_name']) && !empty($item['delivery_size_percentage'])): ?>
                                                    <div class="text-purple-600">
                                                        <strong>Delivery Size:</strong>
                                                        <span class="delivery-size-percentage">
                                                            <?= htmlspecialchars($item['size_name']) ?> (<?= number_format($item['delivery_size_percentage'], 1) ?>%)
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bg-blue-50 p-2 rounded text-xs">
                                                <div class="flex justify-between text-blue-700">
                                                    <span>Delivery per item:</span>
                                                    <span class="deliveryPerItem font-medium">₱0.00</span>
                                                </div>
                                                <div class="flex justify-between text-blue-700">
                                                    <span>Total delivery:</span>
                                                    <span class="totalDeliveryForItem font-medium">₱0.00</span>
                                                </div>
                                                <!-- NEW: Show size-based allocation info when calculated -->
                                                <div class="flex justify-between text-purple-600 mt-1 size-allocation-info" style="display: none;">
                                                    <span>Size allocation:</span>
                                                    <span class="sizeAllocationPercentage font-medium">0%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-600">
                                                ₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?>
                                            </div>
                                            <div class="font-bold text-green-600">
                                                ₱<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Qty: <span class="itemQuantity"><?= $item['quantity'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Total calculation -->
                        <div class="bg-gray-50 p-4 border-t">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Items Subtotal:</span>
                                    <span class="font-medium">₱<?= number_format($total_price, 2) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total Delivery Cost:</span>
                                    <span class="font-medium" id="totalDeliveryCostDisplay">₱0.00</span>
                                </div>
                                <div class="border-t pt-2">
                                    <div class="flex justify-between">
                                        <span>Subtotal (Items + Delivery):</span>
                                        <span class="font-medium" id="subtotalBeforeVAT">₱<?= number_format($total_price, 2) ?></span>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <span>VAT (12%):</span>
                                    <span class="font-medium text-orange-600" id="vatAmount">₱0.00</span>
                                </div>
                                <div class="border-t pt-2">
                                    <div class="flex justify-between text-lg font-bold">
                                        <span>Grand Total (with VAT):</span>
                                        <span class="text-green-700" id="grandTotalDisplay">₱<?= number_format($total_price, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms and Final Actions -->
                <div class="mt-8 text-center">
                    <div class="text-sm text-gray-600 mb-6">
                        By placing your order, you agree to our
                        <a href="../rules/terms.php" class="text-blue-600 underline hover:text-blue-800">Terms</a>
                        and
                        <a href="../rules/policy.php" class="text-blue-600 underline hover:text-blue-800">Privacy Policy</a>.
                    </div>

                    <div class="flex justify-between">
                        <button type="button" class="bg-gray-500 text-white px-8 py-3 rounded-lg hover:bg-gray-600 transition font-medium" onclick="goToStep(3)">
                            Back to Delivery
                        </button>
                        <button type="submit" id="placeOrderBtn" class="bg-green-600 text-white px-12 py-3 rounded-lg hover:bg-green-700 transition font-bold text-lg" style="display: none;" disabled>
                            Place Order
                        </button>
                    </div>
                </div>


            </div>
        </form>
    </div>


    <?php include '../navbar/footer.php'; ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Include all JavaScript modules in the correct order -->
    <script src="js/main.js"></script>
    <script src="js/stepNavigation.js"></script>
    <script src="js/addressZone.js"></script>
    <script src="js/distanceCalculation.js"></script>
    <script src="js/mapModal.js"></script>
    <script src="js/checkoutForm.js"></script>
    <script src="js/paymentquickFixPayment.js"></script>
    <script>
        // Pass cart items data to JavaScript for delivery calculations
        window.cartItemsData = <?= json_encode(array_values($cart_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Debug log to verify data is passed correctly
        console.log('Cart items data loaded:', window.cartItemsData);

        // Verify delivery size data
        window.cartItemsData.forEach((item, index) => {
            console.log(`Item ${index}: ${item.variant_name}, Size: ${item.size_name}, Percentage: ${item.delivery_size_percentage}%`);
        });
    </script>

</body>

</html>