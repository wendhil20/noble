<?php
// checkout-step4.php - Payment Method & Final Order Review
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Check if all previous steps are completed
if (!isset($_SESSION['checkout_step1']) || !$_SESSION['checkout_step1']['completed']) {
    header('Location: index-checkout-page-12.php');
    exit;
}

if (!isset($_SESSION['checkout_step2']) || !$_SESSION['checkout_step2']['completed']) {
    header('Location: index-checkout-page-12-2.php');
    exit;
}

if (!isset($_SESSION['checkout_step3']) || !$_SESSION['checkout_step3']['completed']) {
    header('Location: index-checkout-page-12-3.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get data from previous steps
$customer_data = $_SESSION['checkout_step1'];
$address_data = $_SESSION['checkout_step2'];
$delivery_data = $_SESSION['checkout_step3'];

// PayPal configuration
$paypal_config = [
    'mode' => 'sandbox',
    'client_id' => 'AT1LmhSbRH3yOGHNRFYZb_WhRkFIUlsdUEIQcNNr_0BXnb6LapA61CTycE7xq0c5W6XrHMpetIfpP-Kd',
    'client_secret' => 'EHkB3XnpMB-mjaw8VeOmWR9dmDoDoIZwLwBoEvWdabiGfgd2kTb6VYfOq4WvuJVEUfVaOmm3rBMfS-QT',
    'currency' => 'PHP'
];

// PayMongo configuration
$paymongo_config = [
    'mode' => 'test',
    'secret_key' => 'sk_test_AJdRkkXWfGW9W5DHV6UNNECZ',
    'public_key' => 'pk_test_r4XYBug7KMzvamWvUzjAGAyC',
    'currency' => 'PHP',
    'success_url' => 'http://localhost/noble/user/otherpage/checkout-paymongo-succes-page-12-A.php',
    'cancel_url' => 'http://localhost/noble/user/otherpage/index-checkout-page-12-3.php'
];

// Fetch cart items - CORRECTED to use color_name from user_cart_items
$cart_items = [];
$total_price = 0;

$stmt = $conn->prepare("
    SELECT uci.*,
           COALESCE(pv.origin, '') as origin,
           pv.width, pv.height, pv.length, pv.dimension_unit,
           pv.weight, pv.weight_unit,
           pv.lead_count, pv.lead_interval, pv.lead_gap,
           uci.color_name as variant_color,
           p.product_name
    FROM user_cart_items uci
    LEFT JOIN product_variants pv ON uci.variant_id = pv.id
    LEFT JOIN products p ON uci.product_id = p.id
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

// Fetch QR payment methods
$qr_payment_methods = [];
$stmt = $conn->prepare("SELECT * FROM payment_qr_codes WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $qr_payment_methods[] = $row;
}
$stmt->close();

// Calculate tiered discount
function getCartTieredDiscount($conn, $cart_items)
{
    $result = [
        'has_discount' => false,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'free_shipping' => false,
        'tier_details' => []
    ];

    $product_totals = [];
    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $item_total = $item['price'] * $item['quantity'];

        if (!isset($product_totals[$product_id])) {
            $product_totals[$product_id] = [
                'total' => 0,
                'name' => $item['product_name']
            ];
        }
        $product_totals[$product_id]['total'] += $item_total;
    }

    foreach ($product_totals as $product_id => $data) {
        $product_total = $data['total'];

        $stmt = $conn->prepare("
            SELECT * FROM product_tiers 
            WHERE product_id = ? AND min_amount <= ?
            ORDER BY min_amount DESC 
            LIMIT 1
        ");

        $stmt->bind_param("id", $product_id, $product_total);
        $stmt->execute();
        $tier_result = $stmt->get_result();

        if ($tier_result->num_rows > 0) {
            $tier = $tier_result->fetch_assoc();
            $discount_percent = floatval($tier['discount_percent']);

            if ($discount_percent > 0) {
                $discount_amt = $product_total * ($discount_percent / 100);

                $result['has_discount'] = true;
                $result['discount_amount'] += $discount_amt;

                if ($discount_percent > $result['discount_percent']) {
                    $result['discount_percent'] = $discount_percent;
                }

                if ($tier['free_shipping'] == 1) {
                    $result['free_shipping'] = true;
                }

                $result['tier_details'][] = [
                    'product_name' => $data['name'],
                    'product_total' => $product_total,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amt,
                    'min_amount' => $tier['min_amount'],
                    'free_shipping' => $tier['free_shipping']
                ];
            }
        }
        $stmt->close();
    }

    return $result;
}

$tiered_discount = getCartTieredDiscount($conn, $cart_items);
$items_subtotal = $total_price;
$discount_amount = $tiered_discount['discount_amount'];
$subtotal_after_discount = $items_subtotal - $discount_amount;

// Apply free shipping if eligible
$delivery_fee = $delivery_data['delivery_fee'];
if ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery') {
    $delivery_fee = 0.00;
}

// Calculate final totals - VAT on items only (not delivery)
$vat_amount = $subtotal_after_discount * 0.12;  // VAT based on items subtotal only
$subtotal_with_vat = $subtotal_after_discount + $vat_amount;
$grand_total = $subtotal_with_vat + $delivery_fee;  // Add delivery after VAT

function generateReferenceNumber()
{
    return 'NH' . mt_rand(9800000, 9899999);
}

function calculateLeadTimeRange($leadCount, $leadInterval, $leadGap)
{
    if (empty($leadCount) || empty($leadInterval)) {
        return null;
    }

    $today = new DateTime();
    $startDate = clone $today;
    $endDate = clone $today;

    switch ($leadInterval) {
        case 'day':
            $startDate->modify("+{$leadCount} days");
            break;
        case 'week':
            $daysToAdd = $leadCount * 7;
            $startDate->modify("+{$daysToAdd} days");
            break;
        case 'month':
            $startDate->modify("+{$leadCount} months");
            break;
        case 'year':
            $startDate->modify("+{$leadCount} years");
            break;
    }

    $endDate = clone $startDate;
    if ($leadGap > 0) {
        $endDate->modify("+{$leadGap} days");
    }

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'display' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y')
    ];
}

// PayPal functions
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
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function createPayPalOrder($amount, $order_id, $config)
{
    $url = $config['mode'] === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders';

    $access_token = getPayPalAccessToken($config);
    if (!$access_token) {
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
            'cancel_url' => 'http://localhost/noble/user/otherpage/index-checkout-page-12-3.php',
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
    curl_close($ch);

    if ($http_code === 201) {
        return json_decode($response, true);
    }

    return false;
}

// Handle form submission
$error = null;
$order_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = trim($_POST['payment_method'] ?? '');
    $bank_type = null;
    $reference_number = null;
    $screenshot_filename = null;

    // Validation
    $validation_errors = [];

    if (empty($payment_method)) {
        $validation_errors[] = "Payment method is required";
    }

    // Handle Bank Transfer
    if ($payment_method === 'Bank Transfer') {
        $bank_type = trim($_POST['bank_type'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');

        if (empty($bank_type)) {
            $validation_errors[] = "Please select a bank";
        }

        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/payment_screenshots/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $screenshot_filename = 'payment_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_extension;
                if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $upload_dir . $screenshot_filename)) {
                    $validation_errors[] = "Failed to upload screenshot";
                }
            } else {
                $validation_errors[] = "Invalid file format";
            }
        } else {
            $validation_errors[] = "Payment screenshot is required for bank transfer";
        }
    }

    // Handle QR Payment
    if ($payment_method === 'QR Payment') {
        $qr_method_id = intval($_POST['selected_qr_method'] ?? 0);
        $reference_number = trim($_POST['qr_reference_number'] ?? '');

        if (empty($qr_method_id)) {
            $validation_errors[] = "Please select a QR payment method";
        }

        if (isset($_FILES['qr_payment_screenshot']) && $_FILES['qr_payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/payment_screenshots/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['qr_payment_screenshot']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $screenshot_filename = 'qr_payment_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_extension;
                if (!move_uploaded_file($_FILES['qr_payment_screenshot']['tmp_name'], $upload_dir . $screenshot_filename)) {
                    $validation_errors[] = "Failed to upload screenshot";
                }
            } else {
                $validation_errors[] = "Invalid file format";
            }
        } else {
            $validation_errors[] = "Payment screenshot is required for QR payment";
        }

        $bank_type = 'QR_' . $qr_method_id;
    }

    // Handle PayPal
    if ($payment_method === 'PayPal') {
        try {
            $reference_no = generateReferenceNumber();
            $paypal_order = createPayPalOrder($grand_total, $reference_no, $paypal_config);

            if ($paypal_order && isset($paypal_order['links'])) {
                $payment_status = 'pending_paypal';
                $paypal_order_id = $paypal_order['id'];

                // Insert order
                $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, payment_status, paypal_order_id, assigned_vehicle_id, assigned_vehicle_type, total_cubic_meters, total_weight_kg, total_width, total_height, total_length, delivery_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssssssdsiddidddssisddddds",
                    $customer_data['customer_name'],
                    $customer_data['email'],
                    $address_data['mobile'],
                    $address_data['address'],
                    $address_data['zipcode'],
                    $payment_method,
                    $grand_total,
                    $reference_no,
                    $address_data['billing_address_id'],
                    $address_data['latitude'],
                    $address_data['longitude'],
                    $user_id,
                    $delivery_data['delivery_distance'],
                    $delivery_fee,
                    $subtotal_after_discount,
                    $payment_status,
                    $paypal_order_id,
                    $delivery_data['assigned_vehicle_id'],
                    $delivery_data['assigned_vehicle_type'],
                    $delivery_data['total_cubic_meters'],
                    $delivery_data['total_weight_kg'],
                    $delivery_data['total_width'],
                    $delivery_data['total_height'],
                    $delivery_data['total_length'],
                    $delivery_data['delivery_type']
                );

                if ($stmt->execute()) {
                    $order_id = $stmt->insert_id;
                    $_SESSION['pending_paypal_order'] = $order_id;

                    // Insert order items
                    foreach ($cart_items as $item) {
                        $subtotal_item = $item['price'] * $item['quantity'];
                        $leadTimeRange = calculateLeadTimeRange(
                            $item['lead_count'] ?? null,
                            $item['lead_interval'] ?? null,
                            $item['lead_gap'] ?? null
                        );

                        $lt_from = $leadTimeRange ? $leadTimeRange['start_date']->format('Y-m-d') : null;
                        $lt_to = $leadTimeRange ? $leadTimeRange['end_date']->format('Y-m-d') : null;

                        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin, lt_from, lt_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $stmt2->bind_param(
                            "iisssssdiisssss",
                            $order_id,
                            $item['product_id'],
                            $item['product_name'] ?? $item['variant_name'],
                            $item['codename'] ?? '',
                            $item['type_name'] ?? '',
                            $item['variant_color'] ?? '',
                            $item['size'] ?? '',
                            $item['price'],
                            $item['quantity'],
                            $subtotal_item,
                            $item['descrip6'] ?? '',
                            $item['descrip7'] ?? '',
                            $item['origin'] ?? '',
                            $lt_from,
                            $lt_to
                        );

                        $stmt2->execute();
                        $stmt2->close();
                    }

                    // Get approval URL
                    $approval_url = null;
                    foreach ($paypal_order['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            $approval_url = $link['href'];
                            break;
                        }
                    }

                    if ($approval_url) {
                        header('Location: ' . $approval_url);
                        exit;
                    }
                }
                $stmt->close();
            } else {
                throw new Exception("Failed to create PayPal order");
            }
        } catch (Exception $e) {
            $error = "PayPal payment error: " . $e->getMessage();
        }
    }

    // Handle PayMongo
if ($payment_method === 'PayMongo') {
    // ✅ Extract vehicle data from POST or Session
    $assigned_vehicle_id = isset($_POST['assigned_vehicle_id']) ? intval($_POST['assigned_vehicle_id']) : NULL;
    $assigned_vehicle_type = isset($_POST['assigned_vehicle_type']) ? trim($_POST['assigned_vehicle_type']) : NULL;
    $total_cubic_meters = isset($_POST['total_cubic_meters']) ? floatval($_POST['total_cubic_meters']) : NULL;
    $total_weight_kg = isset($_POST['total_weight_kg']) ? floatval($_POST['total_weight_kg']) : NULL;
    $total_width = isset($_POST['total_width']) ? floatval($_POST['total_width']) : NULL;
    $total_height = isset($_POST['total_height']) ? floatval($_POST['total_height']) : NULL;
    $total_length = isset($_POST['total_length']) ? floatval($_POST['total_length']) : NULL;

    // Fallback to session data if POST is empty
    if ($assigned_vehicle_id === NULL && isset($_SESSION['checkout_step3'])) {
        $step3 = $_SESSION['checkout_step3'];
        $assigned_vehicle_id = isset($step3['assigned_vehicle_id']) ? intval($step3['assigned_vehicle_id']) : NULL;
        $assigned_vehicle_type = isset($step3['assigned_vehicle_type']) ? $step3['assigned_vehicle_type'] : NULL;
        $total_cubic_meters = isset($step3['total_cubic_meters']) ? floatval($step3['total_cubic_meters']) : NULL;
        $total_weight_kg = isset($step3['total_weight_kg']) ? floatval($step3['total_weight_kg']) : NULL;
        $total_width = isset($step3['total_width']) ? floatval($step3['total_width']) : NULL;
        $total_height = isset($step3['total_height']) ? floatval($step3['total_height']) : NULL;
        $total_length = isset($step3['total_length']) ? floatval($step3['total_length']) : NULL;
    }

    // Get delivery distance from POST or session
    $delivery_distance = 0.00;
    if (isset($delivery_data['delivery_distance'])) {
        $delivery_distance = $delivery_data['delivery_distance'];
    } elseif (isset($_POST['delivery_distance'])) {
        $delivery_distance = floatval($_POST['delivery_distance']);
    }

    $_SESSION['paymongo_order_data'] = [
        'customer_name' => $customer_data['customer_name'],
        'email' => $customer_data['email'],
        'mobile' => $address_data['mobile'],
        'address' => $address_data['address'],
        'zipcode' => $address_data['zipcode'],
        'billing_address_id' => $address_data['billing_address_id'],
        'latitude' => $address_data['latitude'],
        'longitude' => $address_data['longitude'],
        'delivery_fee' => $delivery_fee,
        'delivery_distance' => $delivery_distance,
        'subtotal' => $subtotal_after_discount,
        'vat_amount' => $vat_amount,
        'grand_total' => $grand_total,
        'user_id' => $user_id,
        'cart_items' => $cart_items,
        'delivery_type' => $delivery_data['delivery_type'],
        'assigned_vehicle_id' => $assigned_vehicle_id,
        'assigned_vehicle_type' => $assigned_vehicle_type,
        'total_cubic_meters' => $total_cubic_meters,
        'total_weight_kg' => $total_weight_kg,
        'total_width' => $total_width,
        'total_height' => $total_height,
        'total_length' => $total_length
    ];

?>
        <script>
    // Get all vehicle data from hidden fields
    const vehicleData = {
        amount: <?= $grand_total ?>,
        delivery_fee: <?= $delivery_fee ?>,
        order_details: {
            customer_name: '<?= addslashes($customer_data['customer_name']) ?>',
            email: '<?= addslashes($customer_data['email']) ?>',
            mobile: '<?= addslashes($address_data['mobile']) ?>',
            address: '<?= addslashes($address_data['address']) ?>',
            zipcode: '<?= addslashes($address_data['zipcode']) ?>',
            billing_address_id: <?= intval($address_data['billing_address_id'] ?? 0) ?>,
            latitude: <?= floatval($address_data['latitude'] ?? 0) ?>,
            longitude: <?= floatval($address_data['longitude'] ?? 0) ?>,
            delivery_distance: <?= floatval($delivery_data['delivery_distance'] ?? 0) ?>,
            delivery_type: '<?= $delivery_data['delivery_type'] ?>',
            assigned_vehicle_id: <?= intval($delivery_data['assigned_vehicle_id'] ?? 0) ?>,
            assigned_vehicle_type: '<?= addslashes($delivery_data['assigned_vehicle_type'] ?? '') ?>',
            total_cubic_meters: <?= floatval($delivery_data['total_cubic_meters'] ?? 0) ?>,
            total_weight_kg: <?= floatval($delivery_data['total_weight_kg'] ?? 0) ?>,
            total_width: <?= floatval($delivery_data['total_width'] ?? 0) ?>,
            total_height: <?= floatval($delivery_data['total_height'] ?? 0) ?>,
            total_length: <?= floatval($delivery_data['total_length'] ?? 0) ?>
        }
    };

    console.log('Sending PayMongo data:', vehicleData);

    fetch('checkout-paymongo-create-sessions-page-12-A.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(vehicleData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                window.location.href = data.data.attributes.checkout_url;
            } else {
                alert('PayMongo session creation failed.');
                console.error('PayMongo error:', data);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error connecting to payment gateway.');
        });
</script>
<?php
        exit;
    }

    // Process Bank Transfer / QR Payment
    if (empty($validation_errors) && ($payment_method === 'Bank Transfer' || $payment_method === 'QR Payment')) {
        try {
            $reference_no = generateReferenceNumber();
            $payment_status = 'pending';

            // ✅ Extract vehicle data from hidden form fields OR session
$assigned_vehicle_id = isset($_POST['assigned_vehicle_id']) ? intval($_POST['assigned_vehicle_id']) : NULL;
$assigned_vehicle_type = isset($_POST['assigned_vehicle_type']) ? trim($_POST['assigned_vehicle_type']) : NULL;
$total_cubic_meters = isset($_POST['total_cubic_meters']) ? floatval($_POST['total_cubic_meters']) : NULL;
$total_weight_kg = isset($_POST['total_weight_kg']) ? floatval($_POST['total_weight_kg']) : NULL;
$total_width = isset($_POST['total_width']) ? floatval($_POST['total_width']) : NULL;
$total_height = isset($_POST['total_height']) ? floatval($_POST['total_height']) : NULL;
$total_length = isset($_POST['total_length']) ? floatval($_POST['total_length']) : NULL;

// If not in POST, try to get from session (Step 3 data)
if ($assigned_vehicle_id === NULL && isset($_SESSION['checkout_step3'])) {
    $step3 = $_SESSION['checkout_step3'];
    $assigned_vehicle_id = isset($step3['assigned_vehicle_id']) ? intval($step3['assigned_vehicle_id']) : NULL;
    $assigned_vehicle_type = isset($step3['assigned_vehicle_type']) ? $step3['assigned_vehicle_type'] : NULL;
    $total_cubic_meters = isset($step3['total_cubic_meters']) ? floatval($step3['total_cubic_meters']) : NULL;
    $total_weight_kg = isset($step3['total_weight_kg']) ? floatval($step3['total_weight_kg']) : NULL;
    $total_width = isset($step3['total_width']) ? floatval($step3['total_width']) : NULL;
    $total_height = isset($step3['total_height']) ? floatval($step3['total_height']) : NULL;
    $total_length = isset($step3['total_length']) ? floatval($step3['total_length']) : NULL;
}

            // ✅ Extract all values as variables
            $customer_name = $customer_data['customer_name'];
            $customer_email = $customer_data['email'];
            $customer_mobile = $address_data['mobile'];
            $customer_address = $address_data['address'];
            $customer_zipcode = $address_data['zipcode'];
            $billing_address_id = $address_data['billing_address_id'];
            $customer_latitude = $address_data['latitude'];
            $customer_longitude = $address_data['longitude'];
            $delivery_type_value = $delivery_data['delivery_type'];

            // Get delivery distance
            if (isset($delivery_data['delivery_distance'])) {
                $delivery_distance = $delivery_data['delivery_distance'];
            } else {
                $delivery_distance = 0.00;
            }

            // ✅ RE-CALCULATE VAT for Bank/QR (VAT on items only, not delivery)
$vat_amount = $subtotal_after_discount * 0.12;

// Use conditional INSERT based on delivery_type
if ($delivery_type_value === 'pickup') {
    // For pickup orders, exclude vehicle fields but INCLUDE vat_amount
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, vat_amount, bank_type, payment_screenshot, reference_number, payment_status, delivery_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssssdsiddiddddsssss",
        $customer_name,
        $customer_email,
        $customer_mobile,
        $customer_address,
        $customer_zipcode,
        $payment_method,
        $grand_total,
        $reference_no,
        $billing_address_id,
        $customer_latitude,
        $customer_longitude,
        $user_id,
        $delivery_distance,
        $delivery_fee,
        $subtotal_after_discount,
        $vat_amount,  // ← NEW: VAT amount
        $bank_type,
        $screenshot_filename,
        $reference_number,
        $payment_status,
        $delivery_type_value
    );
} else {
    // For delivery orders, include vehicle fields AND vat_amount
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, vat_amount, bank_type, payment_screenshot, reference_number, payment_status, assigned_vehicle_id, assigned_vehicle_type, total_cubic_meters, total_weight_kg, total_width, total_height, total_length, delivery_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssssdsiddiddddssssisddddds",
        $customer_name,
        $customer_email,
        $customer_mobile,
        $customer_address,
        $customer_zipcode,
        $payment_method,
        $grand_total,
        $reference_no,
        $billing_address_id,
        $customer_latitude,
        $customer_longitude,
        $user_id,
        $delivery_distance,
        $delivery_fee,
        $subtotal_after_discount,
        $vat_amount,  // ← NEW: VAT amount
        $bank_type,
        $screenshot_filename,
        $reference_number,
        $payment_status,
        $assigned_vehicle_id,
        $assigned_vehicle_type,
        $total_cubic_meters,
        $total_weight_kg,
        $total_width,
        $total_height,
        $total_length,
        $delivery_type_value
    );
}

            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;

                // Insert order items
                foreach ($cart_items as $item) {
                    $subtotal_item = $item['price'] * $item['quantity'];
                    $leadTimeRange = calculateLeadTimeRange(
                        $item['lead_count'] ?? null,
                        $item['lead_interval'] ?? null,
                        $item['lead_gap'] ?? null
                    );

                    $lt_from = $leadTimeRange ? $leadTimeRange['start_date']->format('Y-m-d') : null;
                    $lt_to = $leadTimeRange ? $leadTimeRange['end_date']->format('Y-m-d') : null;

                    // ✅ Extract item values as variables
                    $item_product_id = $item['product_id'];
                    $item_product_name = $item['product_name'] ?? $item['variant_name'];
                    $item_codename = $item['codename'] ?? '';
                    $item_type_name = $item['type_name'] ?? '';
                    $item_variant_color = $item['variant_color'] ?? '';
                    $item_size = $item['size'] ?? '';
                    $item_price = $item['price'];
                    $item_quantity = $item['quantity'];
                    $item_descrip6 = $item['descrip6'] ?? '';
                    $item_descrip7 = $item['descrip7'] ?? '';
                    $item_origin = $item['origin'] ?? '';

                    // ✅ Extract variant_color separately to ensure it's available
                    $item_variant_color_value = isset($item['variant_color']) && !empty($item['variant_color'])
                        ? $item['variant_color']
                        : '';

                    $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin, lt_from, lt_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt2->bind_param(
                        "iisssssdiisssss",
                        $order_id,
                        $item_product_id,
                        $item_product_name,
                        $item_codename,
                        $item_type_name,
                        $item_variant_color_value,
                        $item_size,
                        $item_price,
                        $item_quantity,
                        $subtotal_item,
                        $item_descrip6,
                        $item_descrip7,
                        $item_origin,
                        $lt_from,
                        $lt_to
                    );

                    $stmt2->execute();
                    $stmt2->close();
                }

                // Clear cart
                $stmt3 = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                $stmt3->bind_param("i", $user_id);
                $stmt3->execute();
                $stmt3->close();

                // Clear session data
                unset($_SESSION['checkout_step1']);
                unset($_SESSION['checkout_step2']);
                unset($_SESSION['checkout_step3']);

                // Redirect to success page
                header('Location: checkout-order_receipt-page-12-A.php?order_id=' . $order_id);
                exit;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = "Order processing error: " . $e->getMessage();
        }
    } else {
        $error = !empty($validation_errors) ? implode(', ', $validation_errors) : "Please complete all required fields";
    }
}

// Calculate expected delivery date
$latestLeadTimeRange = null;
foreach ($cart_items as $item) {
    $leadTimeRange = calculateLeadTimeRange(
        $item['lead_count'] ?? null,
        $item['lead_interval'] ?? null,
        $item['lead_gap'] ?? null
    );

    if ($leadTimeRange) {
        if (!$latestLeadTimeRange || $leadTimeRange['end_date'] > $latestLeadTimeRange['end_date']) {
            $latestLeadTimeRange = $leadTimeRange;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Step 4: Payment & Review - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap">
    <script src="https://www.paypal.com/sdk/js?client-id=<?= $paypal_config['client_id'] ?>&currency=<?= $paypal_config['currency'] ?>&intent=capture"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 font-sans">
    <?php include '../navbar/top.php'; ?>
    <div class="bg-white p-6 rounded shadow mt-3 max-w-7xl mx-auto">
        <h2 class="text-3xl text-orange-700 mb-8">Checkout Process</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <!-- Step 1 - Completed -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>Retry2Continue
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-green-600">Customer Info</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                </div>
                <div class="flex-1 h-px bg-green-500 mx-4"></div>

                <!-- Step 2 - Completed -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-green-600">Delivery Address</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-green-500 mx-4"></div>

                <!-- Step 3 - Completed -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-green-600">Delivery Options</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-green-500 mx-4"></div>

                <!-- Step 4 - Active -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">4</div>
                    <div class="ml-3">
                        <div class="font-medium text-orange-600">Payment</div>
                        <div class="text-xs text-gray-500">Final step</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="paymentForm" class="space-y-6" enctype="multipart/form-data">

            <!-- ✅ ADD THESE HIDDEN FIELDS HERE -->
    <input type="hidden" name="assigned_vehicle_id" id="assignedVehicleId" value="<?= $delivery_data['assigned_vehicle_id'] ?? 0 ?>">
    <input type="hidden" name="assigned_vehicle_type" id="assignedVehicleType" value="<?= htmlspecialchars($delivery_data['assigned_vehicle_type'] ?? '') ?>">
    <input type="hidden" name="total_cubic_meters" id="totalCubicMeters" value="<?= $delivery_data['total_cubic_meters'] ?? 0 ?>">
    <input type="hidden" name="total_weight_kg" id="totalWeightKg" value="<?= $delivery_data['total_weight_kg'] ?? 0 ?>">
    <input type="hidden" name="total_width" id="totalWidth" value="<?= $delivery_data['total_width'] ?? 0 ?>">
    <input type="hidden" name="total_height" id="totalHeight" value="<?= $delivery_data['total_height'] ?? 0 ?>">
    <input type="hidden" name="total_length" id="totalLength" value="<?= $delivery_data['total_length'] ?? 0 ?>">

            <div class="bg-purple-50 p-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg  text-purple-800">Step 4: Payment & Final Review</h3>
                        <p class="text-purple-700 text-sm">Choose payment method and complete your order</p>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Payment Method Selection -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class=" text-gray-800 mb-4">Select Payment Method *</h4>

                    <div class="space-y-4">
                        <!-- Bank Transfer -->
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

                        <!-- QR Code Payment -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-indigo-300 transition">
                            <input type="radio" name="payment_method" value="QR Payment" required class="mr-4" />
                            <div class="flex items-center">
                                <div class="text-indigo-600 mr-3">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd" />
                                        <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM7 11a1 1 0 100-2H4a1 1 0 100 2h3zM17 13a1 1 0 01-1 1h-2a1 1 0 110-2h2a1 1 0 011 1zM16 17a1 1 0 100-2h-3a1 1 0 100 2h3z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium">QR Code Payment</div>
                                    <div class="text-sm text-gray-600">Scan QR code to pay</div>
                                </div>
                            </div>
                        </label>

                        <!-- PayPal -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-blue-300 transition">
                            <input type="radio" name="payment_method" value="PayPal" required class="mr-4" />
                            <div class="flex items-center">
                                <div class="mr-3">
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

                        <!-- PayMongo -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-green-300 transition">
                            <input type="radio" name="payment_method" value="PayMongo" required class="mr-4" />
                            <div class="flex items-center">
                                <div class="text-green-600 mr-3">
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
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
                        <div id="bankSelectionArea"></div>
                    </div>

                    <!-- QR Payment Fields -->
                    <div id="qrPaymentFields" class="hidden mt-6 p-4 bg-indigo-50 rounded-lg">
                        <input type="hidden" name="selected_qr_method" id="selectedQRMethod">
                        <input type="hidden" name="qr_reference_number" id="qrReferenceNumber">
                        <div id="qrSelectionArea">
                            <h5 class="font-bold text-indigo-800 mb-4">Select Payment Method *</h5>
                            <?php if (!empty($qr_payment_methods)): ?>
                                <div class="grid gap-4 mb-6">
                                    <?php foreach ($qr_payment_methods as $qr): ?>
                                        <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-300 transition qr-method-option">
                                            <input type="radio" name="qr_payment_selection" value="<?= $qr['id'] ?>" class="mt-2 mr-4 qr-method-radio" required
                                                data-method-name="<?= htmlspecialchars($qr['payment_method']) ?>"
                                                data-account-name="<?= htmlspecialchars($qr['account_name']) ?>"
                                                data-account-number="<?= htmlspecialchars($qr['account_number']) ?>"
                                                data-qr-image="../../<?= htmlspecialchars($qr['qr_code_image']) ?>"
                                                data-instructions="<?= htmlspecialchars($qr['instructions']) ?>" />
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-qrcode text-indigo-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-lg"><?= htmlspecialchars($qr['payment_method']) ?></div>
                                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($qr['account_name']) ?></div>
                                                    </div>
                                                </div>
                                                <div class="text-sm text-gray-600 mt-2">
                                                    <i class="fas fa-mobile-alt mr-1"></i>
                                                    <?= htmlspecialchars($qr['account_number']) ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div id="qrDetailsArea" class="hidden"></div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                                    <p class="text-yellow-800 font-medium">No QR payment methods available.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PayPal Fields -->
                    <div id="paypalFields" class="hidden mt-6 p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                                    <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                                    <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                                </svg>
                            </div>
                            <div>
                                <h5 class="font-bold text-blue-800">PayPal Payment</h5>
                                <p class="text-sm text-blue-600">You will be redirected to PayPal to complete payment</p>
                            </div>
                        </div>
                        <div class="bg-blue-100 border border-blue-200 rounded-lg p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Total Amount:</span>
                                <span class="font-bold text-blue-800">₱<?= number_format($grand_total, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- PayMongo Fields -->
                    <div id="paymongoFields" class="hidden mt-6 p-4 bg-green-50 rounded-lg">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="text-green-600">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                </svg>
                            </div>
                            <div>
                                <h5 class="font-bold text-green-800">PayMongo Payment</h5>
                                <p class="text-sm text-green-600">You will be redirected to PayMongo checkout</p>
                            </div>
                        </div>
                        <div class="bg-green-100 border border-green-200 rounded-lg p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Total Amount:</span>
                                <span class="font-bold text-green-800">₱<?= number_format($grand_total, 2) ?></span>
                            </div>
                            <div class="text-xs text-green-600 mt-2">
                                <strong>Available:</strong> GCash, Maya, Credit/Debit Cards, GrabPay
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="bg-white border rounded-lg overflow-hidden">
                    <div class="bg-gray-50 p-4 border-b">
                        <h4 class="font-bold text-gray-800">Order Summary</h4>
                    </div>

                    <!-- Cart Items -->
                    <div class="max-h-80 overflow-y-auto divide-y divide-gray-200">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="p-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h5 class="font-bold text-orange-600 mb-2"><?= htmlspecialchars($item['variant_name']) ?></h5>
                                        <div class="text-xs text-gray-600">
                                            <?php if (!empty($item['type_name'])): ?>
                                                <span class="mr-2">Type: <?= htmlspecialchars($item['type_name']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['size'])): ?>
                                                <span class="mr-2">Size: <?= htmlspecialchars($item['size']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        $leadTimeRange = calculateLeadTimeRange(
                                            $item['lead_count'] ?? null,
                                            $item['lead_interval'] ?? null,
                                            $item['lead_gap'] ?? null
                                        );
                                        if ($leadTimeRange): ?>
                                            <div class="bg-green-50 border border-green-200 rounded p-2 mt-2 text-xs text-green-700">
                                                📅 Receive by: <?= $leadTimeRange['display'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right ml-4">
                                        <div class="font-bold text-green-600">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                                        <div class="text-xs text-gray-500">Qty: <?= $item['quantity'] ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Discount Section -->
                    <?php if ($tiered_discount['has_discount']): ?>
                        <div class="bg-green-50 p-4 border-t">
                            <h5 class="font-bold text-green-800 mb-3">💰 Volume Discount Applied</h5>
                            <?php foreach ($tiered_discount['tier_details'] as $tier): ?>
                                <div class="bg-white rounded p-3 mb-2">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="font-semibold text-sm"><?= htmlspecialchars($tier['product_name']) ?></div>
                                            <div class="text-xs text-green-600">
                                                <span class="bg-green-600 text-white px-2 py-0.5 rounded font-bold">
                                                    <?= number_format($tier['discount_percent'], 1) ?>% OFF
                                                </span>
                                                <?php if ($tier['free_shipping']): ?>
                                                    <span class="ml-2 text-blue-700">🚚 FREE SHIPPING</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-green-600 font-bold">-₱<?= number_format($tier['discount_amount'], 2) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Expected Delivery -->
                    <?php if ($latestLeadTimeRange): ?>
                        <div class="bg-green-50 border-t p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span class="font-bold text-green-800">Expected Delivery:</span>
                            </div>
                            <div class="text-lg font-bold text-green-700">
                                <?= $latestLeadTimeRange['display'] ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Totals -->
                    <div class="bg-gray-50 p-4 border-t">
                        <div class="space-y-2 text-sm">
    <div class="flex justify-between <?= $tiered_discount['has_discount'] ? 'text-gray-500 line-through' : '' ?>">
        <span>Items Subtotal:</span>
        <span>₱<?= number_format($items_subtotal, 2) ?></span>
    </div>

    <?php if ($tiered_discount['has_discount']): ?>
        <div class="flex justify-between text-green-600 font-semibold">
            <span>Volume Discount:</span>
            <span>-₱<?= number_format($discount_amount, 2) ?></span>
        </div>
        <div class="flex justify-between font-medium text-orange-600">
            <span>Subtotal After Discount:</span>
            <span>₱<?= number_format($subtotal_after_discount, 2) ?></span>
        </div>
    <?php endif; ?>

    <div class="flex justify-between">
        <span>VAT (12% on items):</span>
        <span class="font-medium text-orange-600">₱<?= number_format($vat_amount, 2) ?></span>
    </div>

    <div class="border-t pt-2">
        <div class="flex justify-between">
            <span>Subtotal (Items + VAT):</span>
            <span class="font-medium">₱<?= number_format($subtotal_with_vat, 2) ?></span>
        </div>
    </div>

    <div class="flex justify-between">
        <span>Delivery Fee:
            <?php if ($tiered_discount['free_shipping']): ?>
                <span class="text-green-600 font-semibold ml-1">FREE! 🎉</span>
            <?php endif; ?>
        </span>
        <span class="<?= $tiered_discount['free_shipping'] ? 'line-through text-gray-400' : '' ?>">
            ₱<?= number_format($delivery_fee, 2) ?>
        </span>
    </div>

    <div class="border-t pt-2">
        <div class="flex justify-between text-lg font-bold">
            <span>Grand Total:</span>
            <span id="grandTotalDisplay" class="text-green-700">₱<?= number_format($grand_total, 2) ?></span>
        </div>
    </div>

                            <?php if ($tiered_discount['has_discount']): ?>
                                <div class="bg-green-100 border border-green-300 rounded p-3 mt-3">
                                    <div class="font-bold text-green-800 mb-1">🎉 You're Saving Today!</div>
                                    <div class="flex justify-between text-green-700">
                                        <span>Total Savings:</span>
                                        <span class="font-bold">₱<?= number_format($discount_amount + ($tiered_discount['free_shipping'] ? $delivery_data['delivery_fee'] : 0), 2) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms & Submit -->
            <div class="mt-8 text-center">
                <div class="text-sm text-gray-600 mb-6">
                    By placing your order, you agree to our
                    <a href="../rules/terms.php" class="text-blue-600 underline hover:text-blue-800">Terms</a>
                    and
                    <a href="../rules/policy.php" class="text-blue-600 underline hover:text-blue-800">Privacy Policy</a>.
                </div>

                <div class="flex justify-between">
                    <a href="index-checkout-page-12-3.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Delivery
                    </a>

                    <button type="submit" id="placeOrderBtn" class="bg-green-600 text-white px-12 py-3 rounded-lg hover:bg-green-700 transition font-bold text-lg disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Place Order
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script src="js/index-checkout-paymentquickFixPayment-page-12-4.obfuscated.js?v=<?= filemtime('js/index-checkout-paymentquickFixPayment-page-12-4.obfuscated.js') ?>"></script>
    <script src="js/index-bank-qr-payment-module-page-12-4.obfuscated.js?v=<?= filemtime('js/index-bank-qr-payment-module-page-12-4.obfuscated.js')?>"></script>
    <script>
        // Pass data to JavaScript
        window.grandTotal = <?= $grand_total ?>;
        window.deliveryFee = <?= $delivery_fee ?>;
    </script>
</body>

</html>