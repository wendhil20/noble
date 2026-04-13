<?php
// checkout-step3.php - Delivery Fee Calculation & Options
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Check if previous steps are completed
if (!isset($_SESSION['checkout_step1']) || !$_SESSION['checkout_step1']['completed']) {
    header('Location: index-checkout-page-12.php');
    exit;
}

if (!isset($_SESSION['checkout_step2']) || !$_SESSION['checkout_step2']['completed']) {
    header('Location: index-checkout-page-12-2.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch cart items with variant details
$cart_items = [];
$total_price = 0;
$stmt = $conn->prepare("
    SELECT uci.*, 
           COALESCE(pv.origin, '') as origin,
           pv.width, pv.height, pv.length, pv.dimension_unit,
           pv.weight, pv.weight_unit,
           pv.lead_count, pv.lead_interval, pv.lead_gap,
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

if (empty($cart_items)) {
    header('Location: ../cart.php');
    exit;
}

// Fetch transportify vehicles
$transportify_vehicles = [];
$couriers_list = [];
$stmt = $conn->prepare("SELECT * FROM transportify_vehicle_list ORDER BY courier_name ASC, base_fare ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transportify_vehicles[] = $row;
    $courier_name = $row['courier_name'];
    if (!isset($couriers_list[$courier_name])) {
        $couriers_list[$courier_name] = [];
    }
    $couriers_list[$courier_name][] = $row;
}
$stmt->close();
$unique_couriers = array_keys($couriers_list);

// Fetch delivery settings
$delivery_settings = null;
$stmt = $conn->prepare("SELECT * FROM delivery_settings ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $delivery_settings = $result->fetch_assoc();
}
$stmt->close();

// Helper functions
function calculateCubicMeters($width, $height, $length, $unit, $quantity = 1)
{
    // Return 0 if no dimensions provided
    if (empty($width) || empty($height) || empty($length)) {
        return 0;
    }

    // Conversion factors to meters (matching database ENUM)
    $meters = [
        'mm' => 0.001,      // millimeters to meters
        'cm' => 0.01,       // centimeters to meters
        'inches' => 0.0254, // inches to meters
        'm' => 1            // meters (no conversion)
    ];

    // Get multiplier, default to 0 if unit not found (will result in 0 volume)
    $multiplier = $meters[strtolower($unit)] ?? 0;

    // If multiplier is 0 (invalid unit), return 0
    if ($multiplier === 0) {
        return 0;
    }

    $widthM = ($width * $multiplier);
    $heightM = ($height * $multiplier);
    $lengthM = ($length * $multiplier);

    return ($widthM * $heightM * $lengthM) * $quantity;
}

function convertToKilograms($weight, $unit, $quantity = 1)
{
    // Return 0 if no weight provided
    if (empty($weight)) {
        return 0;
    }

    // Conversion factors to kilograms (matching database ENUM)
    $kgConversion = [
        'g' => 0.001,      // grams to kg
        'kg' => 1,         // kg (no conversion)
        'lbs' => 0.453592, // pounds to kg
        'oz' => 0.0283495  // ounces to kg
    ];

    // Get multiplier, default to 0 if unit not found
    $multiplier = $kgConversion[strtolower($unit)] ?? 0;

    // If multiplier is 0 (invalid unit), return 0
    if ($multiplier === 0) {
        return 0;
    }

    return ($weight * $multiplier) * $quantity;
}

function assignTransportifyVehicle($cart_items, $transportify_vehicles)
{
    $totalCubicMeters = 0;
    $totalWeightKg = 0;
    $itemVehicleData = [];

    foreach ($cart_items as $item) {
        // Get dimensions - use 0 if not provided (no fallback sizes)
        $width = floatval($item['width'] ?? 0);
        $height = floatval($item['height'] ?? 0);
        $length = floatval($item['length'] ?? 0);

        // Get units - use database defaults if not provided
        $dimensionUnit = $item['dimension_unit'] ?? 'cm';
        $weight = floatval($item['weight'] ?? 0);
        $weightUnit = $item['weight_unit'] ?? 'kg';
        $quantity = intval($item['quantity'] ?? 1);

        // Calculate cubic meters and weight (will be 0 if no data)
        $itemCubicM = calculateCubicMeters($width, $height, $length, $dimensionUnit, $quantity);
        $itemWeightKg = convertToKilograms($weight, $weightUnit, $quantity);

        $totalCubicMeters += $itemCubicM;
        $totalWeightKg += $itemWeightKg;

        // Debug log for items with no dimensions
        if ($itemCubicM === 0 && $itemWeightKg === 0) {
            error_log("⚠️ Item has no dimensions/weight: " . ($item['variant_name'] ?? $item['product_name']));
        }

        $itemVehicleData[] = [
            'item_id' => $item['id'] ?? $item['variant_id'],
            'variant_name' => $item['variant_name'],
            'quantity' => $quantity,
            'cubic_meters' => $itemCubicM,
            'weight_kg' => $itemWeightKg
        ];
    }

    $assignedVehicle = null;
    foreach ($transportify_vehicles as $vehicle) {
        $maxCubicM = floatval($vehicle['max_cubic_meter'] ?? 0);
        $maxWeightKg = floatval($vehicle['max_weight_capacity'] ?? 0);
        if ($totalCubicMeters <= $maxCubicM && $totalWeightKg <= $maxWeightKg) {
            $assignedVehicle = $vehicle;
            break;
        }
    }

    if (!$assignedVehicle) {
        $assignedVehicle = end($transportify_vehicles);
    }

    return [
        'vehicle' => $assignedVehicle,
        'total_cubic_meters' => $totalCubicMeters,
        'total_weight_kg' => $totalWeightKg,
        'item_vehicle_data' => $itemVehicleData
    ];
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_type = trim($_POST['delivery_type'] ?? 'delivery');
    $courier_name = trim($_POST['courier_selection'] ?? '');
    $delivery_distance = floatval($_POST['delivery_distance'] ?? 0);
    $delivery_fee = floatval($_POST['delivery_fee'] ?? 0);

    // Get vehicle assignment data
    $assigned_vehicle_id = intval($_POST['assigned_vehicle_id'] ?? 0);
    $assigned_vehicle_type = trim($_POST['assigned_vehicle_type'] ?? '');
    $total_cubic_meters = floatval($_POST['total_cubic_meters'] ?? 0);
    $total_weight_kg = floatval($_POST['total_weight_kg'] ?? 0);
    $total_width = floatval($_POST['total_width'] ?? 0);
    $total_height = floatval($_POST['total_height'] ?? 0);
    $total_length = floatval($_POST['total_length'] ?? 0);

    if ($delivery_type === 'pickup') {
        $delivery_fee = 0.00;
        $delivery_distance = 0.00;
        $assigned_vehicle_id = null;
        $assigned_vehicle_type = null;
    }

    $_SESSION['checkout_step3'] = [
        'delivery_type' => $delivery_type,
        'courier_name' => $courier_name,
        'delivery_distance' => $delivery_distance,
        'delivery_fee' => $delivery_fee,
        'assigned_vehicle_id' => $assigned_vehicle_id,
        'assigned_vehicle_type' => $assigned_vehicle_type,
        'total_cubic_meters' => $total_cubic_meters,
        'total_weight_kg' => $total_weight_kg,
        'total_width' => $total_width,
        'total_height' => $total_height,
        'total_length' => $total_length,
        'completed' => true
    ];

    header('Location: index-checkout-page-12-4.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Step 3: Delivery Options - Noble Home</title>
</head>

<body class="bg-gray-100 font-sans">
    <?php include '../navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow mt-3 max-w-7xl mx-auto">
        <h2 class="text-3xl  text-orange-700 mb-8">Checkout Process</h2>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <!-- Step 1 - Completed -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-green-600">Delivery Address</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-green-500 mx-4"></div>

                <!-- Step 3 - Active -->
                <div class="flex items-center flex-1">
                    <div
                        class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                        3</div>
                    <div class="ml-3">
                        <div class="font-medium text-orange-600">Delivery Options</div>
                        <div class="text-xs text-gray-500">Current step</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 4 -->
                <div class="flex items-center flex-1">
                    <div
                        class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">
                        4</div>
                    <div class="ml-3">
                        <div class="font-medium text-gray-400">Payment</div>
                        <div class="text-xs text-gray-400">Complete order</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="deliveryForm" class="space-y-6">
            <div class="bg-green-50 p-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                        </path>
                    </svg>
                    <div>
                        <h3 class="text-lg  text-green-800">Step 3: Delivery Options</h3>
                        <p class="text-green-700 text-sm">Choose how you want to receive your order</p>
                    </div>
                </div>
            </div>

            <!-- Delivery Type Selection -->
            <div class="bg-white border rounded-lg p-6">
                <h4 class=" text-gray-800 mb-4">Select Delivery Method *</h4>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Delivery Option -->
                    <label
                        class="flex items-start p-4 cursor-pointer hover:bg-orange-50 hover:border-orange-300 transition delivery-type-option">
                        <input type="radio" name="delivery_type" value="delivery" class="mt-2 mr-4" required />
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <svg class="w-6 h-6 text-orange-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                                    </path>
                                </svg>
                                <div class=" text-lg">Delivery</div>
                            </div>
                            <div class="text-sm text-gray-600">
                                We'll deliver to your address using our courier service
                            </div>
                            <div class="mt-2 text-xs text-orange-600 font-medium">
                                Delivery fee applies based on distance
                            </div>
                        </div>
                    </label>

                    <!-- Pickup Option -->
                    <label
                        class="flex items-start p-4  cursor-pointer hover:bg-green-50 hover:border-green-300 transition delivery-type-option">
                        <input type="radio" name="delivery_type" value="pickup" class="mt-2 mr-4" required />
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                                <div class=" text-lg">Pick-up</div>
                            </div>
                            <div class="text-sm text-gray-600">
                                You'll pick up your order from our store
                            </div>
                            <div class="mt-2 text-xs text-green-600 font-medium">
                                FREE - No delivery fee
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Delivery Calculation Section -->
            <div id="deliveryCalculationSection" class="bg-white rounded-lg p-6 border">
                <h4 class=" text-gray-800 mb-4">Delivery </h4>
                <!-- Courier Selection - HIDDEN (Auto-selected) -->
                <div class="p-6 mb-6 hidden">
                    <label for="courierSelection" class="block text-sm font-semibold text-gray-700 mb-3">
                        <span
                            class="bg-orange-600 text-white rounded-full w-6 h-6 inline-flex items-center justify-center text-xs mr-2">1</span>
                        Select Your Courier *
                    </label>

                    <select id="courierSelection" name="courier_selection"
                        class="w-full px-4 py-3 border-2 border-gray-300 bg-white rounded" required>
                        <?php foreach ($unique_couriers as $index => $courier): ?>
                            <option value="<?= htmlspecialchars($courier) ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($courier) ?> • <?= count($couriers_list[$courier]) ?> vehicle(s)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Store Information -->
                <?php if ($delivery_settings): ?>
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h5 class="font-bold text-gray-700 mb-2">Store Location</h5>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($delivery_settings['location_name']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Vehicle Assignment Display -->
                <div id="assignedVehicleDetails" class="hidden  p-4 mb-4">
                    <h5 class=" text-green-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Assigned Delivery Vehicle
                    </h5>
                    <div id="vehicleDetailsContent" class="text-sm"></div>
                </div>

                <!-- Distance Calculation Button - Hidden (Auto-triggered) -->
                <button type="button" id="calculateDistance"
                    class="hidden w-full bg-orange-600 text-white px-4 py-3 rounded-lg hover:bg-orange-700 transition font-medium disabled:bg-gray-400  items-center justify-center gap-2"
                    disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                        </path>
                    </svg>
                    Calculate Distance & Fee
                </button>

                <div id="distanceResult" class="mt-4 text-sm"></div>

                <!-- Hidden inputs for form submission -->
                <input type="hidden" name="delivery_distance" id="deliveryDistance" value="0">
                <input type="hidden" name="delivery_fee" id="deliveryFee" value="0">
                <input type="hidden" name="assigned_vehicle_id" id="assignedVehicleId" value="0">
                <input type="hidden" name="assigned_vehicle_type" id="assignedVehicleType" value="">
                <input type="hidden" name="total_cubic_meters" id="totalCubicMeters" value="0">
                <input type="hidden" name="total_weight_kg" id="totalWeightKg" value="0">
                <input type="hidden" name="total_width" id="totalWidth" value="0">
                <input type="hidden" name="total_height" id="totalHeight" value="0">
                <input type="hidden" name="total_length" id="totalLength" value="0">
            </div>

            <!-- Pickup Information Section -->
            <div id="pickupInformationSection" class="hidden bg-green-50 border border-green-200 rounded-lg p-6">
                <h4 class="font-bold text-green-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Store Pick-up Location
                </h4>
                <?php if ($delivery_settings): ?>
                    <div class="space-y-3">
                        <div class="bg-white rounded-lg p-4">
                            <div class="font-medium text-gray-700 mb-2">Store Address:</div>
                            <div class="text-gray-600"><?= htmlspecialchars($delivery_settings['location_name']) ?></div>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <div class="font-medium text-gray-700 mb-2">Pick-up Hours:</div>
                            <div class="text-gray-600">Monday - Saturday: 9:00 AM - 6:00 PM</div>
                            <div class="text-gray-600">Sunday: Closed</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cart Summary -->
            <div class="bg-white border rounded-lg p-6">
                <h4 class="font-bold text-gray-800 mb-4">Your Cart Items</h4>
                <div class="space-y-3 max-h-80 overflow-y-auto">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="border-b pb-3">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-gray-800"><?= htmlspecialchars($item['variant_name']) ?>
                                    </h5>
                                    <div class="text-xs text-gray-600 mt-1">
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
                                    // Hidden to avoid confusion - customer can see expected delivery in next step
                                    ?>
                                </div>
                                <div class="text-right ml-4">
                                    <div class="font-bold text-green-600">
                                        ₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                                    <div class="text-xs text-gray-500">Qty: <?= $item['quantity'] ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <div class="flex justify-between font-bold text-lg">
                        <span>Items Subtotal:</span>
                        <span class="text-orange-600">₱<?= number_format($total_price, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="flex justify-between items-center pt-4">
                <a href="index-checkout-page-12-2.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                        </path>
                    </svg>
                    Back to Address
                </a>

                <button type="submit" id="continueToPayment"
                    class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium flex items-center disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled>
                    Continue to Payment
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50  items-center justify-center">
        <div class="bg-white rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Delivery Route Map</h3>
                <button type="button" id="closeMapModal" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div id="map" class="w-full h-96"></div>
            <div class="p-4 bg-gray-50">
                <div id="mapRouteInfo" class="text-sm text-gray-600"></div>
            </div>
        </div>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script>
        console.log('🚀 Loading Step 3 configuration...');

        // Pass PHP data to JavaScript
        window.checkoutConfig = {
            deliverySettings: <?= $delivery_settings ? json_encode($delivery_settings) : 'null' ?>,
            transportifyVehicles: <?= json_encode($transportify_vehicles) ?>,
            couriersGrouped: <?= json_encode($couriers_list) ?>,
            courierNames: <?= json_encode($unique_couriers) ?>,
            totalPrice: <?= (float) $total_price ?>,
            cartItems: <?= json_encode($cart_items) ?>,
            customerAddress: {
                latitude: <?= floatval($_SESSION['checkout_step2']['latitude'] ?? 0) ?>,
                longitude: <?= floatval($_SESSION['checkout_step2']['longitude'] ?? 0) ?>,
                address: '<?= addslashes($_SESSION['checkout_step2']['address'] ?? '') ?>'
            }
        };

        // Global variables
        window.transportifyVehicles = <?= json_encode($transportify_vehicles) ?>;
        window.cartItemsData = <?= json_encode($cart_items) ?>;
        window.deliverySettings = <?= $delivery_settings ? json_encode($delivery_settings) : 'null' ?>;

        // ✅ CRITICAL: Initialize selectedAddress with NUMBER conversion
        window.selectedAddress = {
            latitude: <?= floatval($_SESSION['checkout_step2']['latitude'] ?? 0) ?>,
            longitude: <?= floatval($_SESSION['checkout_step2']['longitude'] ?? 0) ?>,
            address: '<?= addslashes($_SESSION['checkout_step2']['address'] ?? '') ?>',
            zipcode: '<?= $_SESSION['checkout_step2']['zipcode'] ?? '' ?>',
            mobile: '<?= $_SESSION['checkout_step2']['mobile'] ?? '' ?>'
        };

        console.log('✓ Step 3 Data Loaded:', {
            config: window.checkoutConfig,
            selectedAddress: window.selectedAddress,
            vehicles: window.transportifyVehicles.length,
            items: window.cartItemsData.length
        });

        // ✅ VALIDATE AND CLEAN ALERTS
        (function () {
            const addr = window.selectedAddress;

            // Validate coordinates
            if (!addr.latitude || !addr.longitude || addr.latitude === 0 || addr.longitude === 0) {
                console.error('❌ Invalid address coordinates!');

                // Show error with fix button
                const errorAlert = document.createElement('div');
                errorAlert.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-red-600 text-white px-8 py-4 rounded-lg shadow-2xl z-[9999] max-w-2xl';
                errorAlert.innerHTML = `
            <div class="flex items-center gap-4">
                <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div class="flex-1">
                    <div class="font-bold text-lg">Invalid Delivery Address</div>
                    <div class="text-sm mt-1">Please return to Step 2 and select a valid address.</div>
                </div>
                <a href="index-checkout-page-12-2.php" 
                    class="bg-white text-red-600 px-6 py-3 rounded-lg font-bold hover:bg-red-50 transition">
                    Fix Address
                </a>
            </div>
        `;
                document.body.appendChild(errorAlert);

                // Disable form
                const form = document.getElementById('deliveryForm');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        alert('Please fix your address first.');
                    });
                }

                return;
            }

            console.log('✅ Address is valid!');

            // ✅ AGGRESSIVE STALE ALERT REMOVAL
            function removeStaleAlerts() {
                const selectors = [
                    '[role="alert"]',
                    '[class*="alert"]',
                    '[class*="notification"]',
                    '[class*="banner"]',
                    '[class*="warning"]',
                    'div[style*="background"]'
                ];

                let removed = 0;
                selectors.forEach(selector => {
                    document.querySelectorAll(selector).forEach(element => {
                        const text = element.textContent.toLowerCase();

                        if ((text.includes('select') || text.includes('please') || text.includes('choose')) &&
                            (text.includes('address') || text.includes('delivery'))) {
                            console.log('🗑️ Removing alert:', element.textContent.substring(0, 60));
                            element.remove();
                            removed++;
                        }
                    });
                });

                if (removed > 0) {
                    console.log(`✅ Removed ${removed} stale alert(s)`);
                }
            }

            // Run immediately and repeatedly
            removeStaleAlerts();
            setTimeout(removeStaleAlerts, 100);
            setTimeout(removeStaleAlerts, 500);
            setTimeout(removeStaleAlerts, 1000);
            setTimeout(removeStaleAlerts, 2000);

            // Monitor for new alerts
            const observer = new MutationObserver(() => {
                setTimeout(removeStaleAlerts, 50);
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            console.log('✅ Alert cleanup system active');
        })();
    </script>

    <!-- Core Scripts (Order Matters!) -->
    <script
        src="js/index-checkout-main-page-12-3.obfuscated.obfuscated.js?v=<?= filemtime('js/index-checkout-main-page-12-3.obfuscated.js') ?>"></script>
    <script
        src="js/index-checkout-stepNavigation-page-12-3.obfuscated.js?v=<?= filemtime('js/index-stepNavigation-page-12-3.obfuscated.js') ?>"></script>
    <script
        src="js/index-checkout-distanceCalculation-page-12-3.obfuscated.js?v=<?= filemtime('js/index-checkout-distanceCalculation-page-12-3.obfuscated.js') ?>"></script>
    <script
        src="js/index-checkout-mapModal-page-12-3.obfuscated.js?v=<?= filemtime('js/index-checkout-mapModal-page-12-3.obfuscated.js') ?>"></script>

    <!-- Initialize Step 3 -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('🚀 Initializing Step 3...');

            // ✅ OVERRIDE showNotification to block address alerts
            const originalShowNotification = window.showNotification;
            window.showNotification = function (message, type, duration) {
                const msg = String(message).toLowerCase();

                // Block "select address" alerts if we have valid address
                if ((msg.includes('select') || msg.includes('please')) &&
                    msg.includes('address') &&
                    window.selectedAddress &&
                    window.selectedAddress.latitude &&
                    window.selectedAddress.longitude) {
                    console.log('🚫 Blocked notification:', message);
                    return; // Don't show the alert
                }

                // Allow other notifications
                if (originalShowNotification && typeof originalShowNotification === 'function') {
                    return originalShowNotification(message, type, duration);
                } else {
                    // Fallback if showNotification doesn't exist yet
                    console.log(`${type}: ${message}`);
                }
            };

            console.log('✅ Notification blocker installed');

            // Initialize delivery type selection
            if (typeof initializeDeliveryTypeSelection === 'function') {
                initializeDeliveryTypeSelection();
            } else {
                console.warn('⚠️ initializeDeliveryTypeSelection not found');
            }

            // Initialize distance calculation
            if (typeof initializeDistanceCalculation === 'function') {
                initializeDistanceCalculation();
            } else {
                console.warn('⚠️ initializeDistanceCalculation not found');
            }

            // Initialize map modal
            if (typeof initializeMapModal === 'function') {
                initializeMapModal();
            } else {
                console.warn('⚠️ initializeMapModal not found');
            }

            // ✅ AUTO-TRIGGER CALCULATION after everything loads
            setTimeout(() => {
                const courierSelect = document.getElementById('courierSelection');
                if (courierSelect && courierSelect.value) {
                    console.log('✅ Courier already selected:', courierSelect.value);

                    // Trigger calculation automatically
                    if (typeof autoCalculateDelivery === 'function') {
                        console.log('🔄 Auto-triggering delivery calculation...');
                        autoCalculateDelivery();
                    }
                } else {
                    console.log('⏭️ No courier selected yet - waiting for user');
                }
            }, 1500); // Wait 1.5 seconds for everything to load

            console.log('✅ Step 3 fully initialized');
        });
    </script>

</body>

</html>