<?php
// checkout-step4.php - Payment Method & Final Order Review (CLEANED - PayMongo & QRPh ONLY)
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

require_once '../../.env.php';


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

// ✅ BUILD DYNAMIC URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Detect if localhost or production
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

// Determine base path for URLs
if ($isLocalhost) {
    $basePath = '/noble/user/otherpage';
} else {
    $basePath = '/user/otherpage';
}

// Dynamic PayMongo URLs
$paymongo_success_url = $protocol . $host . $basePath . '/checkout-paymongo-succes-page-12-A.php';
$paymongo_cancel_url = $protocol . $host . $basePath . '/index-checkout-page-12-3.php';


$paymongo_config = [
    'mode' => 'live',
    'secret_key' => getenv('PAYMONGO_SECRET_KEY'),
    'public_key' => getenv('PAYMONGO_PUBLIC_KEY'),
    'currency' => 'PHP',
    'success_url' => $paymongo_success_url,
    'cancel_url' => $paymongo_cancel_url
];


// Get cart items
$cart_items = [];
$total_price = 0;

$stmt = $conn->prepare("
    SELECT uci.*,
           uci.color_id,
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

// ✅ Get tiered discount
function getCartTieredDiscount($conn, $cart_items)
{
    $result = [
        'has_discount' => false,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'free_shipping' => false,
        'tier_details' => []
    ];

    $product_quantities = [];
    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $item_quantity = $item['quantity'];

        if (!isset($product_quantities[$product_id])) {
            $product_quantities[$product_id] = [
                'total_quantity' => 0,
                'total_amount' => 0,
                'name' => $item['product_name']
            ];
        }
        $product_quantities[$product_id]['total_quantity'] += $item_quantity;
        $product_quantities[$product_id]['total_amount'] += $item['price'] * $item['quantity'];
    }

    foreach ($product_quantities as $product_id => $data) {
        $product_quantity = $data['total_quantity'];
        $product_amount = $data['total_amount'];

        $stmt = $conn->prepare("
            SELECT * FROM product_tiers 
            WHERE product_id = ? AND min_quantity <= ?
            ORDER BY min_quantity DESC 
            LIMIT 1
        ");

        $stmt->bind_param("ii", $product_id, $product_quantity);
        $stmt->execute();
        $tier_result = $stmt->get_result();

        if ($tier_result->num_rows > 0) {
            $tier = $tier_result->fetch_assoc();
            $discount_percent = floatval($tier['discount_percent']);

            if ($discount_percent > 0) {
                $discount_amt = $product_amount * ($discount_percent / 100);

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
                    'product_total' => $product_amount,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amt,
                    'min_quantity' => $tier['min_quantity'],
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
$tiered_discount_amount = $tiered_discount['discount_amount'];

// ✅ Apply tiered discount
$subtotal_after_tiered = $items_subtotal - $tiered_discount_amount;

// ✅ Sales commission tracking
$sales_commission_rate = 0.00;
$sales_commission_amount = 0.00;
$sales_user_id = null;
$sales_referral_code = null;

if (!empty($_SESSION['user_id'])) {
    $user_check = $conn->prepare("SELECT referred_by_code FROM users WHERE id = ? LIMIT 1");
    $user_check->bind_param("i", $_SESSION['user_id']);
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
                $sales_commission_amount = $items_subtotal * ($sales_commission_rate / 100);

                error_log("=== SALES COMMISSION TRACKING ===");
                error_log("Sales User ID: $sales_user_id");
                error_log("Referral Code: $sales_referral_code");
                error_log("Commission Rate: {$sales_commission_rate}%");
                error_log("Commission Amount: ₱" . number_format($sales_commission_amount, 2));
                error_log("=================================");
            }
            $sales_check->close();
        }
    }
    $user_check->close();
}

// ✅ Calculate final amounts
$subtotal_after_discount = $subtotal_after_tiered;
$total_discount_amount = $tiered_discount_amount;

// Apply free shipping if eligible
$delivery_fee = $delivery_data['delivery_fee'];
if ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery') {
    $delivery_fee = 0.00;
}

// Calculate VAT
$vat_amount = $subtotal_after_discount * 0.12;

// Final totals
$subtotal_with_vat = $subtotal_after_discount + $vat_amount;
$grand_total = $subtotal_with_vat + $delivery_fee;

error_log("==================== PRICE CALCULATION ====================");
error_log("Items Subtotal: ₱" . number_format($items_subtotal, 2));
error_log("Tiered Discount: -₱" . number_format($tiered_discount_amount, 2));
error_log("Subtotal After Discount: ₱" . number_format($subtotal_after_discount, 2));
error_log("VAT (12%): ₱" . number_format($vat_amount, 2));
error_log("Delivery Fee: ₱" . number_format($delivery_fee, 2));
error_log(">>> GRAND TOTAL: ₱" . number_format($grand_total, 2) . " <<<");
error_log("============================================================");

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

// ✅ FORM SUBMISSION - PAYPAL & QRPH ONLY
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = trim($_POST['payment_method'] ?? '');

    if (empty($payment_method)) {
        $error = "Payment method is required";
    } elseif ($payment_method === 'PayMongo') {
        // Handle PayMongo - Extract vehicle data
        $assigned_vehicle_id = isset($_POST['assigned_vehicle_id']) ? intval($_POST['assigned_vehicle_id']) : NULL;
        $assigned_vehicle_type = isset($_POST['assigned_vehicle_type']) ? trim($_POST['assigned_vehicle_type']) : NULL;
        $total_cubic_meters = isset($_POST['total_cubic_meters']) ? floatval($_POST['total_cubic_meters']) : NULL;
        $total_weight_kg = isset($_POST['total_weight_kg']) ? floatval($_POST['total_weight_kg']) : NULL;
        $total_width = isset($_POST['total_width']) ? floatval($_POST['total_width']) : NULL;
        $total_height = isset($_POST['total_height']) ? floatval($_POST['total_height']) : NULL;
        $total_length = isset($_POST['total_length']) ? floatval($_POST['total_length']) : NULL;

        // Fallback to session if POST empty
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

        $delivery_distance = $delivery_data['delivery_distance'] ?? 0.00;

        // Store in session and proceed
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
            'items_subtotal' => $items_subtotal,
            'tiered_discount' => $tiered_discount_amount,
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

        // Redirect to PayMongo handler
        echo '<script>
            const vehicleData = {
                amount: ' . $grand_total . ',
                delivery_fee: ' . $delivery_fee . ',
                order_details: {
                    customer_name: "' . addslashes($customer_data['customer_name']) . '",
                    email: "' . addslashes($customer_data['email']) . '",
                    mobile: "' . addslashes($address_data['mobile']) . '",
                    address: "' . addslashes($address_data['address']) . '",
                    zipcode: "' . addslashes($address_data['zipcode']) . '",
                    delivery_type: "' . $delivery_data['delivery_type'] . '"
                }
            };
            fetch("checkout-paymongo-create-sessions-page-12-A.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(vehicleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                    window.location.href = data.data.attributes.checkout_url;
                } else {
                    alert("PayMongo error. Please try again.");
                }
            })
            .catch(error => alert("Error: " + error.message));
        </script>';
        exit;

    } elseif ($payment_method === 'QRPh') {
        // QRPh is handled entirely by JavaScript
        // Server just validates the method here
        // JavaScript will call checkout-qrph-create-order.php
    } else {
        $error = "Invalid payment method";
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
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

            <!-- Hidden fields for vehicle data -->
            <input type="hidden" name="assigned_vehicle_id" id="assignedVehicleId" value="<?= $delivery_data['assigned_vehicle_id'] ?? 0 ?>">
            <input type="hidden" name="assigned_vehicle_type" id="assignedVehicleType" value="<?= htmlspecialchars($delivery_data['assigned_vehicle_type'] ?? '') ?>">
            <input type="hidden" name="total_cubic_meters" id="totalCubicMeters" value="<?= $delivery_data['total_cubic_meters'] ?? 0 ?>">
            <input type="hidden" name="total_weight_kg" id="totalWeightKg" value="<?= $delivery_data['total_weight_kg'] ?? 0 ?>">
            <input type="hidden" name="total_width" id="totalWidth" value="<?= $delivery_data['total_width'] ?? 0 ?>">
            <input type="hidden" name="total_height" id="totalHeight" value="<?= $delivery_data['total_height'] ?? 0 ?>">
            <input type="hidden" name="total_length" id="totalLength" value="<?= $delivery_data['total_length'] ?? 0 ?>">
            <input type="hidden" id="grandTotalDisplay" value="<?= number_format($grand_total, 2, '.', '') ?>">

            <div class="bg-purple-50 p-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg text-purple-800">Step 4: Payment & Final Review</h3>
                        <p class="text-purple-700 text-sm">Choose payment method and complete your order</p>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Payment Method Selection -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class="text-gray-800 mb-4 font-bold">Select Payment Method *</h4>

                    <div class="space-y-4">
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

                        <!-- QR Ph Payment -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-blue-300 transition">
                            <input type="radio" name="payment_method" value="QRPh" required class="mr-4" />
                            <div class="flex items-center">
                                <div class="text-blue-600 mr-3">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium">QR Ph</div>
                                    <div class="text-sm text-gray-600">Scan QR code via any PH bank app (InstaPay/PESONet)</div>
                                </div>
                            </div>
                        </label>
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

                    <!-- QRPh Fields -->
                    <div id="qrphFields" class="hidden mt-6 p-4 bg-blue-50 rounded-lg">
                        
                        <!-- Loading -->
                        <div id="qrphLoading" class="text-center py-6 hidden">
                            <div class="animate-spin w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-3"></div>
                            <p class="text-blue-600 font-medium">Generating QR Code...</p>
                        </div>

                        <!-- QR Display -->
                        <div id="qrphContent" class="hidden text-center">
                            <h5 class="font-bold text-blue-800 mb-1 text-lg">Scan to Pay</h5>
                            <p class="text-sm text-gray-500 mb-4">Gamit ang BPI, BDO, GCash, Maya, o kahit anong bank app</p>
                            
                            <div class="bg-white p-4 rounded-xl inline-block shadow mb-4">
                                <img id="qrphImage" src="" alt="QR Ph Code" class="w-56 h-56 mx-auto">
                            </div>

                            <div class="bg-blue-100 rounded-lg p-3 mb-4">
                                <p class="text-sm text-gray-600">Amount to Pay:</p>
                                <p class="text-2xl font-bold text-blue-800" id="qrphAmountDisplay">₱0.00</p>
                            </div>

                            <!-- Countdown -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                                <p class="text-sm text-yellow-800">⏳ QR expires in: <span id="qrphTimer" class="font-bold">5:00</span></p>
                            </div>

                            <!-- Waiting indicator -->
                            <div class="flex items-center justify-center gap-2 text-gray-500 text-sm">
                                <div class="animate-pulse w-2 h-2 bg-green-500 rounded-full"></div>
                                Waiting for payment...
                            </div>
                        </div>

                        <!-- Success -->
                        <div id="qrphSuccess" class="hidden text-center py-6">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <p class="text-green-700 font-bold text-lg">Payment Received!</p>
                            <p class="text-sm text-gray-500">Redirecting to your receipt...</p>
                        </div>

                        <!-- Error -->
                        <div id="qrphError" class="hidden text-center py-4">
                            <p class="text-red-600 mb-3">Failed to generate QR code.</p>
                            <button onclick="generateQRPh()" 
                                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                                Try Again
                            </button>
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
                            <h5 class="font-bold text-green-800 mb-3">Volume Discount Applied</h5>
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
                            <div class="flex justify-between">
                                <span>Items Subtotal:</span>
                                <span class="<?= ($tiered_discount_amount > 0) ? 'text-gray-500 line-through' : 'font-medium' ?>">
                                    ₱<?= number_format($items_subtotal, 2) ?>
                                </span>
                            </div>

                            <?php if ($tiered_discount_amount > 0): ?>
                                <div class="flex justify-between text-green-600 font-semibold bg-green-50 px-2 py-1 rounded">
                                    <span><i class="fas fa-tag mr-1"></i>Volume Discount:</span>
                                    <span>-₱<?= number_format($tiered_discount_amount, 2) ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>After Volume Discount:</span>
                                    <span class="font-medium">₱<?= number_format($subtotal_after_tiered, 2) ?></span>
                                </div>
                                <div class="flex justify-between font-semibold text-orange-600 border-t border-orange-200 pt-2 mt-2 bg-orange-50 px-2 py-1 rounded">
                                    <span>Subtotal After Discounts:</span>
                                    <span class="text-lg">₱<?= number_format($subtotal_after_discount, 2) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-between border-t pt-2 mt-2">
                                <span>VAT (12%):</span>
                                <span class="font-medium text-orange-600">₱<?= number_format($vat_amount, 2) ?></span>
                            </div>

                            <div class="flex justify-between bg-gray-100 px-2 py-1 rounded">
                                <span class="font-medium">Subtotal (Items + VAT):</span>
                                <span class="font-semibold">₱<?= number_format($subtotal_with_vat, 2) ?></span>
                            </div>

                            <div class="flex justify-between">
                                <span>Delivery Fee:
                                    <?php if ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery'): ?>
                                        <span class="text-green-600 font-semibold ml-1">FREE!</span>
                                    <?php endif; ?>
                                </span>
                                <span class="<?= ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery') ? 'line-through text-gray-400' : 'font-medium' ?>">
                                    ₱<?= number_format($delivery_data['delivery_fee'], 2) ?>
                                </span>
                            </div>

                            <div class="border-t-2 border-gray-400 pt-3 mt-3">
                                <div class="flex justify-between text-xl font-bold">
                                    <span>Grand Total:</span>
                                    <span class="text-green-700">₱<?= number_format($grand_total, 2) ?></span>
                                </div>
                            </div>

                            <?php
                            $total_savings = $tiered_discount_amount;
                            if ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery') {
                                $total_savings += $delivery_data['delivery_fee'];
                            }

                            if ($total_savings > 0):
                            ?>
                                <div class="p-4 mt-4 text-black bg-yellow-50 rounded">
                                    <div class="text-center">
                                        <div class="text-xs uppercase tracking-wider mb-1">Total Savings</div>
                                        <div class="text-3xl font-bold">₱<?= number_format($total_savings, 2) ?></div>
                                        <div class="text-xs mt-2 opacity-90 font-medium">
                                            <?php
                                            $savings_breakdown = [];
                                            if ($tiered_discount_amount > 0) {
                                                $savings_breakdown[] = 'Volume: ₱' . number_format($tiered_discount_amount, 2);
                                            }
                                            if ($tiered_discount['free_shipping'] && $delivery_data['delivery_type'] === 'delivery') {
                                                $savings_breakdown[] = 'Free Shipping: ₱' . number_format($delivery_data['delivery_fee'], 2);
                                            }
                                            echo implode(' + ', $savings_breakdown);
                                            ?>
                                        </div>
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

    <script src="js/index-checkout-paymentquickFixPayment-page-12-4.obfuscated.js?v=<?= filemtime('js/index-checkout-paymentquickFixPayment-page-12-4.obfuscated.js') ?>"></link>
    <script>
        // Pass data to JavaScript
        window.grandTotal = <?= $grand_total ?>;
        window.deliveryFee = <?= $delivery_fee ?>;
        window.subtotalAfterDiscount = <?= $subtotal_after_discount ?>;
        window.vatAmount = <?= $vat_amount ?>;
        window.subtotalWithVat = <?= $subtotal_with_vat ?>;
        window.itemsSubtotal = <?= $items_subtotal ?>;
        window.tieredDiscount = <?= $tiered_discount_amount ?>;
    </script>
</body>

</html>