<?php
//checkout.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// DESCRIBE product_variants;
$tables = ['products'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
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

// ✅ Fetch delivery settings
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
    // ✅ Fetch cart items with origin from product_variants
    $stmt = $conn->prepare("
    SELECT uci.*, COALESCE(pv.origin, '') as origin 
    FROM user_cart_items uci 
    LEFT JOIN product_variants pv ON uci.variant_id = pv.id 
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

// ✅ Function to calculate delivery fee per item based on distance (same as sample code)
function calculateDeliveryFeePerItem($distance_km, $delivery_settings)
{
    if (!$delivery_settings) {
        return 0; // No delivery settings found
    }

    $base_fee = (float) $delivery_settings['base_fee'];
    $per_km_rate = (float) $delivery_settings['per_km_rate'];
    $total_km_for_base = (float) $delivery_settings['total_km_base_fee'];

    if ($distance_km <= $total_km_for_base) {
        return $base_fee;
    } else {
        $extra_km = $distance_km - $total_km_for_base;
        return $base_fee + ($extra_km * $per_km_rate);
    }
}

// ✅ Function to calculate total delivery cost for all items
function calculateTotalDeliveryForOrder($cart_items, $distance_km, $delivery_settings)
{
    $delivery_fee_per_item = calculateDeliveryFeePerItem($distance_km, $delivery_settings);
    $total_delivery_cost = 0;

    foreach ($cart_items as $item) {
        $quantity = (int) $item['quantity'];
        $item_total_delivery = $delivery_fee_per_item * $quantity;
        $total_delivery_cost += $item_total_delivery;
    }

    return $total_delivery_cost;
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

    if (empty($payment_method)) {
        $validation_errors[] = "Payment method is required";
    }

    if (empty($cart_items)) {
        $validation_errors[] = "Your cart is empty";
    }

    if ($delivery_distance <= 0) {
        $validation_errors[] = "Please calculate delivery distance first";
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

            // ✅ Save order (UPDATED to include delivery info and bank transfer data)
            $payment_status = ($payment_method === 'Bank Transfer') ? 'pending' : 'verified';
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, bank_type, payment_screenshot, reference_number, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdsiddidddssss", $name, $email, $mobile, $address, $zipcode, $payment_method, $grand_total, $reference_no, $billing_address_id, $latitude, $longitude, $user_id, $delivery_distance, $delivery_fee, $subtotal, $bank_type, $screenshot_filename, $reference_number, $payment_status);

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $stmt->insert_id;
            $stmt->close();


            // ✅ Calculate delivery fee per item (same logic as sample code)
            $delivery_fee_per_item = calculateDeliveryFeePerItem($delivery_distance, $delivery_settings);

            // ✅ Save each item with individual delivery calculations
            $stmt = $conn->prepare("INSERT INTO order_items (
    order_id, product_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin, delivery_fee_per_item, item_total_delivery
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($cart_items as $item) {
                $subtotal_item = $item['price'] * $item['quantity'];
                $product_name = $item['product_name'] ?? $item['variant_name'];
                $codename = $item['codename'] ?? '';
                $type_name = $item['type_name'] ?? '';
                $variant_color = $item['color_name'] ?: ($item['variant_name'] ?? '');
                $size = $item['size'] ?? '';
                $price = $item['price'];
                $quantity = $item['quantity'];

                // ✅ Make sure delivery fee per item is available in this scope
                // Re-calculate or ensure it's properly set
                if (!isset($delivery_fee_per_item) || $delivery_fee_per_item <= 0) {
                    $delivery_fee_per_item = calculateDeliveryFeePerItem($delivery_distance, $delivery_settings);
                }

                // ✅ Calculate delivery fee per item and total delivery for this item (exactly like sample code)
                $item_total_delivery = $delivery_fee_per_item * $quantity;

                // ✅ SIMPLIFIED: Get descrip6 and descrip7 directly from cart item
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
    <title>Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
</head>

<body class="bg-gray-100 font-sans">
    <?php include '../navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow mt-3">
        <h2 class="text-2xl font-bold text-orange-700 mb-6">Checkout</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" id="checkoutForm" enctype="multipart/form-data">

            <!-- Customer Details -->
            <div><label class="block font-medium">Full Name</label>
                <input type="text" name="customer_name" required class="w-full border px-4 py-2 rounded bg-gray-50"
                    value="<?= htmlspecialchars($userName ?? '') ?>" readonly />
            </div>

            <div><label class="block font-medium">Email</label>
                <input type="email" name="email" required class="w-full border px-4 py-2 rounded bg-gray-50"
                    value="<?= htmlspecialchars($userEmail ?? '') ?>" readonly />
            </div>

            <!-- ✅ UPDATED: Billing Address Selector with Setup Button -->
            <div class="mb-4">
                <div class="flex justify-between items-center mb-3">
                    <label class="block font-medium">Delivery Address</label>
                    <?php if ($has_billing_addresses): ?>
                        <div class="flex gap-2">
                            <button type="button" id="toggleBillingSelector" class="bg-orange-600 text-white px-4 py-2 rounded text-sm hover:bg-orange-700">
                                Select from Saved Addresses
                            </button>
                            <a href="update_billing_add.php" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add New
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="update_billing_add.php" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Set up your address
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($has_billing_addresses): ?>
                    <div class="border rounded-lg p-4 bg-blue-50 border-blue-200 mb-4">
                        <div class="flex items-center gap-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-blue-900">Select Your Delivery Address</h4>
                                <p class="text-sm text-blue-700">Please choose one of your saved addresses below. You cannot manually enter address details.</p>
                            </div>
                        </div>
                    </div>

                    <div id="billingAddressSelector" class="border rounded-lg p-4 bg-gray-50">
                        <div class="space-y-3 max-h-40 overflow-y-auto">
                            <?php foreach ($billing_addresses as $addr): ?>
                                <label class="flex items-start p-3 border rounded cursor-pointer hover:bg-white billing-address-option">
                                    <input type="radio" name="billing_address_id" value="<?= $addr['id'] ?>" class="mt-1 mr-3" required
                                        data-full-name="<?= htmlspecialchars($addr['full_name']) ?>"
                                        data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                                        data-address="<?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?>"
                                        data-postal-code="<?= htmlspecialchars($addr['postal_code']) ?>"
                                        data-latitude="<?= $addr['latitude'] ?>"
                                        data-longitude="<?= $addr['longitude'] ?>" />
                                    <div class="flex-1">
                                        <div class="font-medium"><?= htmlspecialchars($addr['full_name']) ?></div>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($addr['phone']) ?></div>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?></div>
                                        <div class="text-sm text-gray-500">ZIP: <?= htmlspecialchars($addr['postal_code']) ?></div>
                                        <?php if (!empty($addr['notes'])): ?>
                                            <div class="text-xs text-gray-500 italic"><?= htmlspecialchars($addr['notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="border rounded-lg p-6 bg-red-50 border-red-200">
                        <div class="text-center">
                            <svg class="mx-auto w-12 h-12 text-red-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <h4 class="font-bold text-red-900 text-lg mb-2">Address Required</h4>
                            <p class="text-red-700 mb-4">You must set up at least one delivery address before you can place an order. This ensures accurate delivery and better service.</p>
                            <a href="update_billing_add.php" class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Set up your address now
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ✅ Address Fields - Always disabled, populated by address selection -->
            <div>
                <label class="block font-medium">Mobile Number</label>
                <input
                    type="tel"
                    name="mobile"
                    id="mobileInput"
                    pattern="[0-9]{11}"
                    required
                    class="w-full border px-4 py-2 rounded bg-gray-200 cursor-not-allowed"
                    value="<?= htmlspecialchars($userMobile ?? '') ?>"
                    placeholder="<?= !$has_billing_addresses ? 'Please set up your address first' : 'Will be filled when you select an address' ?>"
                    disabled readonly />
            </div>

            <div>
                <label class="block font-medium">Full Address</label>
                <textarea
                    name="address"
                    id="addressInput"
                    rows="3"
                    required
                    class="w-full border px-4 py-2 rounded resize-none bg-gray-200 cursor-not-allowed"
                    placeholder="<?= !$has_billing_addresses ? 'Please set up your address first' : 'Will be filled when you select an address' ?>"
                    disabled readonly></textarea>
            </div>

            <div>
                <label class="block font-medium">ZIP Code</label>
                <input
                    type="text"
                    name="zipcode"
                    id="zipcodeInput"
                    pattern="[0-9]{4}"
                    required
                    class="w-full border px-4 py-2 rounded bg-gray-200 cursor-not-allowed"
                    placeholder="<?= !$has_billing_addresses ? 'Please set up your address first' : 'Will be filled when you select an address' ?>"
                    disabled readonly />
            </div>

            <!-- ✅ NEW: Delivery Distance Calculator -->
            <?php if ($delivery_settings && $has_billing_addresses): ?>
                <div class="border rounded-lg p-4 bg-yellow-50 border-yellow-200">
                    <h3 class="font-bold text-yellow-900 mb-3">Delivery Distance Calculator</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-yellow-700 mb-2">Store Location: <?= htmlspecialchars($delivery_settings['location_name']) ?></p>
                            <div class="flex gap-2">
                                <button type="button" id="calculateDistance" class="bg-yellow-600 text-white px-4 py-2 rounded text-sm hover:bg-yellow-700 disabled:bg-gray-400" disabled>
                                    Calculate Distance
                                </button>
                                <button type="button" id="showMapModal" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 disabled:bg-gray-400 flex items-center gap-2" disabled>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                    </svg>
                                    View Maps
                                </button>
                            </div>
                            <div id="distanceResult" class="mt-3 text-sm text-gray-700"></div>
                        </div>
                        <div>
                            <div class="text-sm space-y-1">
                                <div><strong>Base Fee:</strong> ₱<?= number_format($delivery_settings['base_fee'], 2) ?></div>
                                <div><strong>Per KM Rate:</strong> ₱<?= number_format($delivery_settings['per_km_rate'], 2) ?></div>
                                <div><strong>Base KM:</strong> <?= $delivery_settings['total_km_base_fee'] ?> km</div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs to store calculated values -->
                    <input type="hidden" name="delivery_distance" id="deliveryDistance" value="0">
                    <input type="hidden" name="delivery_fee" id="deliveryFee" value="0">
                </div>
            <?php endif; ?>

            <!-- Payment Method Section -->
            <div class="mt-6">
                <label class="block font-medium mb-3">Payment Method</label>
                <div class="space-y-3">

                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="payment_method" value="Bank Transfer" required class="mr-3" onclick="showBankSelection()" />
                        <div class="flex items-center">
                            <div class="text-purple-600 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm8 0a2 2 0 104 0 2 2 0 00-4 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium">Bank Transfer</div>
                                <div class="text-sm text-gray-600">Transfer to our bank account</div>
                            </div>
                        </div>
                    </label>

                    <!-- Add hidden fields for bank transfer data -->
                    <div id="bankTransferFields" class="hidden mt-4 p-4 bg-blue-50 rounded-lg">
                        <input type="hidden" name="bank_type" id="selectedBank">
                        <input type="hidden" name="reference_number" id="referenceNumber">
                        <div id="bankSelectionArea">
                            <!-- Bank selection will be populated by JavaScript -->
                        </div>
                    </div>

                </div>
            </div>

            <!-- ✅ UPDATED: Order Summary with Per-Item Delivery Fee (Like Sample Code) -->
            <div class="border rounded-lg overflow-hidden bg-white max-h-[500px] flex flex-col">

                <!-- Scrollable cart items -->
                <div class="overflow-y-auto divide-y divide-gray-200 flex-1">
                    <?php foreach ($cart_items as $index => $item): ?>
                        <div class="p-4" id="cartItem<?= $index ?>">
                            <div class="flex justify-between items-start gap-4">
                                <!-- Left side: Product details -->
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-orange-500 mb-2 break-words">
                                        <?= htmlspecialchars($item['variant_name']) ?>
                                    </h4>

                                    <!-- Product details grid -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-600">
                                        <?php if (!empty($item['type_name'])): ?>
                                            <div><span class="font-medium">Type:</span> <?= htmlspecialchars($item['type_name']) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                            <div><span class="font-medium">Size:</span> <?= htmlspecialchars($item['size']) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['color_name'])): ?>
                                            <div><span class="font-medium">Color:</span> <?= htmlspecialchars($item['color_name']) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['codename'])): ?>
                                            <div><span class="font-medium">Code:</span> <?= htmlspecialchars($item['codename']) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['origin'])): ?>
                                            <?php
                                            $is_local = stripos($item['origin'], 'local') !== false;
                                            $origin_label_class = $is_local ? 'text-blue-600' : 'text-red-600';
                                            $origin_value_class = $is_local ? 'text-blue-700' : 'text-red-700';
                                            ?>
                                            <div>
                                                <span class="font-medium <?= $origin_label_class ?>">Origin:</span>
                                                <span class="<?= $origin_value_class ?>"><?= htmlspecialchars($item['origin']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                    </div>

                                    <!-- Descriptions -->
                                    <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                                        <div class="mt-2 text-xs text-gray-500 space-y-1">
                                            <?php if (!empty($item['descrip6'])): ?>
                                                <div class="italic"><?= htmlspecialchars($item['descrip6']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['descrip7'])): ?>
                                                <div class="italic"><?= htmlspecialchars($item['descrip7']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- ✅ NEW: Delivery Fee Details (Like Sample Code) -->
                                    <div class="mt-3 p-2 bg-blue-50 rounded text-xs">
                                        <div class="grid grid-cols-2 gap-2 text-blue-700">
                                            <div><span class="font-medium">Delivery per item:</span> <span class="deliveryPerItem">₱0.00</span></div>
                                            <div><span class="font-medium">Totals delivery:</span> <span class="totalDeliveryForItem">₱0.00</span></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right side: Pricing -->
                                <div class="text-right flex-shrink-0 min-w-[140px]">
                                    <div class="text-sm text-gray-600 mb-1">
                                        ₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?>
                                    </div>
                                    <div class="text-lg font-bold text-green-600">
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

                <!-- Fixed Total section with detailed breakdown (Like Sample Code) -->
                <div class="border-t bg-gray-50 p-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Items Subtotal:</span>
                            <span class="font-medium">₱<?= number_format($total_price, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Total Delivery Cost:</span>
                            <span class="font-medium" id="totalDeliveryCostDisplay">₱0.00</span>
                        </div>
                        <hr class="border-gray-300">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Subtotal (Items + Delivery):</span>
                            <span class="font-medium" id="subtotalBeforeVAT">₱<?= number_format($total_price, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">VAT (12%):</span>
                            <span class="font-medium text-orange-600" id="vatAmount">₱0.00</span>
                        </div>
                        <hr class="border-gray-300">
                        <div class="flex justify-between items-center text-lg font-bold">
                            <span class="text-gray-900">Grand Total (with VAT):</span>
                            <span class="text-green-700" id="grandTotalDisplay">₱<?= number_format($total_price, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

           <div class="text-sm text-gray-600 text-center mt-4">
    By placing your order, you agree to our
    <a href="../rules/terms.php" class="text-blue-600 underline hover:text-blue-800">Terms</a>
    and
    <a href="../rules/policy.php" class="text-blue-600 underline hover:text-blue-800">Privacy Policy</a>.
</div>


            <button type="submit" class="<?= !$has_billing_addresses ? 'bg-gray-400 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-700' ?> text-white px-6 py-2 rounded mt-6" <?= !$has_billing_addresses ? 'disabled' : '' ?> id="placeOrderBtn">
                <?= !$has_billing_addresses ? 'Set up address to continue' : 'Calculate delivery fee to continue' ?>
            </button>

            <div class="mt-4">
                <a href="cart_view.php" class="inline-flex items-center gap-2 text-sm text-orange-700 font-medium px-4 py-2 rounded-lg bg-orange-100 hover:bg-orange-200 transition">
                    ← Back to Cart
                </a>
            </div>
        </form>
    </div>

    <script>
        // Global variables
        let deliverySettings = <?= $delivery_settings ? json_encode($delivery_settings) : 'null' ?>;
        let subtotal = <?= $total_price ?>;

        // Function to calculate VAT and totals (UPDATED - VAT only on items, not delivery)
        function calculateTotalsWithVAT(itemsSubtotal, deliveryCost) {
            const vatAmount = itemsSubtotal * 0.12; // VAT only on items, not delivery
            const grandTotal = itemsSubtotal + vatAmount + deliveryCost; // Add delivery after VAT calculation

            return {
                subtotalWithDelivery: itemsSubtotal + deliveryCost,
                vatAmount: vatAmount,
                grandTotal: grandTotal
            };
        }

        let selectedAddress = null;

        // Global variables for the map modal
        let deliveryMap = null;
        let routingControl = null;
        let storeMarker = null;
        let customerMarker = null;
        let currentRouteData = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all event listeners
            initializeAddressSelection();
            initializeDistanceCalculation();
            initializeMapModal();
            initializeCheckoutForm();
        });

        function initializeAddressSelection() {
            const toggleBtn = document.getElementById('toggleBillingSelector');
            const selector = document.getElementById('billingAddressSelector');
            const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            const calculateDistanceBtn = document.getElementById('calculateDistance');
            const showMapBtn = document.getElementById('showMapModal');

            const mobileInput = document.getElementById('mobileInput');
            const addressInput = document.getElementById('addressInput');
            const zipcodeInput = document.getElementById('zipcodeInput');

            // Check if user has addresses
            const hasAddresses = <?= $has_billing_addresses ? 'true' : 'false' ?>;

            // For users with addresses, show selector by default and require selection
            if (hasAddresses) {
                // Initially disable the place order button until an address is selected
                if (placeOrderBtn) {
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    placeOrderBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
                    placeOrderBtn.textContent = 'Please select an address';
                }

                // Toggle functionality for the selector button (optional collapse/expand)
                if (toggleBtn && selector) {
                    toggleBtn.addEventListener('click', function() {
                        selector.classList.toggle('hidden');
                        toggleBtn.textContent = selector.classList.contains('hidden') ?
                            'Show Address Selector' :
                            'Hide Address Selector';
                    });
                }
            }

            // Handle billing address selection
            billingRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        selectedAddress = {
                            latitude: parseFloat(this.dataset.latitude),
                            longitude: parseFloat(this.dataset.longitude),
                            address: this.dataset.address
                        };

                        // Clean and format mobile number
                        let phone = this.dataset.phone;
                        // Remove spaces, dashes, parentheses, plus signs
                        phone = phone.replace(/[\s\-\(\)\+]/g, '');
                        // Convert +63 format to 09 format
                        if (phone.match(/^63([0-9]{10})$/)) {
                            phone = '0' + phone.substring(2);
                        }

                        // Populate the fields and enable them
                        if (mobileInput) mobileInput.value = phone;
                        if (addressInput) addressInput.value = this.dataset.address;
                        if (zipcodeInput) zipcodeInput.value = this.dataset.postalCode;

                        // Enable fields for form submission but keep them visually disabled
                        if (mobileInput) {
                            mobileInput.disabled = false;
                            mobileInput.readOnly = true;
                        }
                        if (addressInput) {
                            addressInput.disabled = false;
                            addressInput.readOnly = true;
                        }
                        if (zipcodeInput) {
                            zipcodeInput.disabled = false;
                            zipcodeInput.readOnly = true;
                        }

                        // Enable calculate distance and map buttons
                        if (calculateDistanceBtn) {
                            calculateDistanceBtn.disabled = false;
                        }
                        if (showMapBtn) {
                            showMapBtn.disabled = false;
                        }

                        // Update place order button text
                        if (placeOrderBtn) {
                            placeOrderBtn.textContent = 'Calculate delivery fee to continue';
                        }
                    }
                });
            });
        }

        // Function to calculate distance using OSRM (same as map routing)
        async function calculateRoutingDistance(storeLatLng, customerLatLng) {
            try {
                const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false&geometries=geojson`;

                const response = await fetch(url);
                const data = await response.json();

                if (data.routes && data.routes.length > 0) {
                    const route = data.routes[0];
                    const distanceKm = route.distance / 1000; // Convert to kilometers
                    const timeMinutes = Math.round(route.duration / 60); // Convert to minutes

                    return {
                        distance: distanceKm,
                        time: timeMinutes,
                        success: true
                    };
                } else {
                    throw new Error('No routes found');
                }
            } catch (error) {
                console.error('Routing API error:', error);
                // Fallback to Haversine distance
                const distance = calculateHaversineDistance(
                    storeLatLng.lat, storeLatLng.lng,
                    customerLatLng.lat, customerLatLng.lng
                );
                return {
                    distance: distance,
                    time: Math.round(distance * 2), // Rough estimate: 2 minutes per km
                    success: false,
                    fallback: true
                };
            }
        }

        function initializeDistanceCalculation() {
            const calculateDistanceBtn = document.getElementById('calculateDistance');

            if (calculateDistanceBtn) {
                calculateDistanceBtn.addEventListener('click', async function() {
                    if (!selectedAddress || !deliverySettings) {
                        alert('Please select an address and ensure delivery settings are available.');
                        return;
                    }

                    // Show loading
                    calculateDistanceBtn.textContent = 'Calculating...';
                    calculateDistanceBtn.disabled = true;

                    // Calculate distance using routing API (same as map)
                    const storeLatLng = {
                        lat: parseFloat(deliverySettings.latitude),
                        lng: parseFloat(deliverySettings.longitude)
                    };
                    const customerLatLng = {
                        lat: selectedAddress.latitude,
                        lng: selectedAddress.longitude
                    };

                    const routeData = await calculateRoutingDistance(storeLatLng, customerLatLng);
                    const distance = routeData.distance;

                    // Calculate delivery fee per item
                    const baseFee = parseFloat(deliverySettings.base_fee);
                    const perKmRate = parseFloat(deliverySettings.per_km_rate);
                    const baseKm = parseFloat(deliverySettings.total_km_base_fee);

                    let deliveryFeePerItem;
                    if (distance <= baseKm) {
                        deliveryFeePerItem = baseFee;
                    } else {
                        const extraKm = distance - baseKm;
                        deliveryFeePerItem = baseFee + (extraKm * perKmRate);
                    }

                    // Calculate total delivery cost for all items (delivery fee per item × quantity for each item)
                    let totalDeliveryCost = 0;
                    const cartItems = document.querySelectorAll('[id^="cartItem"]');

                    cartItems.forEach((cartItem, index) => {
                        const quantityElement = cartItem.querySelector('.itemQuantity');
                        const quantity = parseInt(quantityElement.textContent);

                        // Calculate item total delivery (delivery fee per item × quantity)
                        const itemTotalDelivery = deliveryFeePerItem * quantity;
                        totalDeliveryCost += itemTotalDelivery;

                        // Update UI for each item
                        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
                        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');

                        if (deliveryPerItemElement) {
                            deliveryPerItemElement.textContent = `₱${deliveryFeePerItem.toFixed(2)}`;
                        }
                        if (totalDeliveryForItemElement) {
                            totalDeliveryForItemElement.textContent = `₱${itemTotalDelivery.toFixed(2)}`;
                        }
                    });

                    // Update distance result
                    const distanceResultElement = document.getElementById('distanceResult');
                    if (distanceResultElement) {
                        const fallbackNote = routeData.fallback ? '<div class="text-xs text-orange-600 mt-1">* Distance calculated using straight-line method</div>' : '';
                        distanceResultElement.innerHTML = `
                        <div class="bg-green-100 border border-green-300 rounded p-3">
                            <div class="font-medium text-green-800">Distance: ${distance.toFixed(2)} km</div>
                            <div class="font-medium text-green-800">Est. Time: ${routeData.time} minutes</div>
                            <div class="font-medium text-green-800">Delivery Fee per Item: ₱${deliveryFeePerItem.toFixed(2)}</div>
                            <div class="font-medium text-green-800">Total Delivery Cost: ₱${totalDeliveryCost.toFixed(2)}</div>
                            ${fallbackNote}
                        </div>
                    `;
                    }

                    // Update hidden fields
                    const deliveryDistanceInput = document.getElementById('deliveryDistance');
                    const deliveryFeeInput = document.getElementById('deliveryFee');

                    if (deliveryDistanceInput) deliveryDistanceInput.value = distance.toFixed(2);
                    if (deliveryFeeInput) deliveryFeeInput.value = totalDeliveryCost.toFixed(2);

                    // Update totals display with corrected VAT calculation
                    const totals = calculateTotalsWithVAT(subtotal, totalDeliveryCost);

                    const totalDeliveryCostDisplay = document.getElementById('totalDeliveryCostDisplay');
                    const subtotalBeforeVATDisplay = document.getElementById('subtotalBeforeVAT');
                    const vatAmountDisplay = document.getElementById('vatAmount');
                    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

                    if (totalDeliveryCostDisplay) {
                        totalDeliveryCostDisplay.textContent = `₱${totalDeliveryCost.toFixed(2)}`;
                    }
                    if (subtotalBeforeVATDisplay) {
                        // Show items subtotal only (before VAT, before delivery)
                        subtotalBeforeVATDisplay.textContent = `₱${subtotal.toFixed(2)}`;
                    }
                    if (vatAmountDisplay) {
                        vatAmountDisplay.textContent = `₱${totals.vatAmount.toFixed(2)}`;
                    }
                    if (grandTotalDisplay) {
                        grandTotalDisplay.textContent = `₱${totals.grandTotal.toFixed(2)}`;
                    }

                    // Reset button
                    calculateDistanceBtn.textContent = 'Recalculate Distance';
                    calculateDistanceBtn.disabled = false;

                    // ✅ ENABLE PLACE ORDER BUTTON AFTER DELIVERY CALCULATION
                    const placeOrderBtn = document.getElementById('placeOrderBtn');
                    if (placeOrderBtn) {
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        placeOrderBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                        placeOrderBtn.textContent = 'Place Order';
                    }

                    // Also update the updateBankPaymentAmount function to be called after delivery calculation
                    if (typeof updateBankPaymentAmount === 'function') {
                        updateBankPaymentAmount();
                    }
                });
            }
        }

        function initializeMapModal() {
            const showMapBtn = document.getElementById('showMapModal');

            if (showMapBtn) {
                showMapBtn.addEventListener('click', function() {
                    if (!selectedAddress || !deliverySettings) {
                        alert('Please select an address first.');
                        return;
                    }

                    // Prepare store data
                    const storeData = {
                        name: deliverySettings.location_name,
                        latitude: parseFloat(deliverySettings.latitude),
                        longitude: parseFloat(deliverySettings.longitude)
                    };

                    // Prepare customer data
                    const customerData = {
                        address: selectedAddress.address,
                        latitude: selectedAddress.latitude,
                        longitude: selectedAddress.longitude
                    };

                    // Create and show the map modal
                    createMapModal(storeData, customerData, deliverySettings);
                });
            }
        }

        function initializeCheckoutForm() {
            const checkoutForm = document.querySelector('#checkoutForm');
            const placeOrderBtn = document.getElementById('placeOrderBtn');

            if (checkoutForm && placeOrderBtn) {
                checkoutForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent normal form submission

                    // Check if delivery fee is calculated
                    const deliveryDistanceInput = document.getElementById('deliveryDistance');
                    const deliveryDistance = deliveryDistanceInput ? parseFloat(deliveryDistanceInput.value) : 0;

                    if (deliveryDistance <= 0) {
                        alert('Please calculate delivery distance first before placing your order.');
                        return;
                    }

                    // Show loading state
                    const originalText = placeOrderBtn.textContent;
                    placeOrderBtn.textContent = 'Processing...';
                    placeOrderBtn.disabled = true;

                    // Create FormData object
                    const formData = new FormData(checkoutForm);

                    // Send AJAX request with proper headers
                    fetch('', { // Empty string means current page
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest' // This identifies it as an AJAX request
                            },
                            body: formData
                        })
                        .then(response => {
                            // Check if response is JSON or HTML
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json();
                            } else {
                                return response.text();
                            }
                        })
                        .then(data => {
                            if (typeof data === 'object' && data.success) {
                                // JSON response - success
                                alert('Order placed successfully! You will be redirected to the order receipt.');
                                window.location.href = data.redirect_url;
                            } else if (typeof data === 'string') {
                                // HTML response - check for errors
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(data, 'text/html');
                                const errorDiv = doc.querySelector('.bg-red-100');

                                if (errorDiv) {
                                    const errorText = errorDiv.textContent.replace('Error:', '').trim();
                                    alert('Error: ' + errorText);
                                } else {
                                    alert('An unexpected error occurred. Please try again.');
                                }

                                placeOrderBtn.textContent = originalText;
                                placeOrderBtn.disabled = false;
                            } else {
                                // Unexpected response format
                                alert('An unexpected error occurred. Please try again.');
                                placeOrderBtn.textContent = originalText;
                                placeOrderBtn.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                            placeOrderBtn.textContent = originalText;
                            placeOrderBtn.disabled = false;
                        });
                });
            }
        }

        // Haversine formula to calculate distance between two coordinates
        function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in kilometers

            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;

            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            const distance = R * c; // Distance in kilometers
            return distance;
        }

        // Function to create and display the map modal with fixed layout
        function createMapModal(storeData, customerData, deliverySettings) {
            // Remove existing modal if any
            const existingModal = document.getElementById('deliveryMapModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Create modal HTML with improved layout
            const modalHTML = `
            <div id="deliveryMapModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" style="backdrop-filter: blur(4px);">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-orange-600 to-orange-700 text-white p-6 flex-shrink-0">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-2xl font-bold flex items-center gap-3">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Delivery Route Map
                                </h2>
                                <p class="text-orange-100 mt-1">View your delivery route and distance calculation</p>
                            </div>
                            <button onclick="closeDeliveryMapModal()" class="text-orange-200 hover:text-white transition-colors p-2 rounded-lg hover:bg-orange-800">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Content - Scrollable -->
                    <div class="flex-1 overflow-y-auto">
                        <!-- Route Information Panel -->
                        <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                            <div class="grid md:grid-cols-3 gap-6">
                                <!-- Store Location -->
                                <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-2-13v6h0m-8-6v6h0M5 21h14"></path>
                                            </svg>
                                        </div>
                                        <h3 class="font-bold text-gray-900">Store Location</h3>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="text-sm text-gray-600" id="storeLocationName">${storeData.name}</div>
                                        <div class="text-xs text-gray-500" id="storeCoordinates">${storeData.latitude}, ${storeData.longitude}</div>
                                    </div>
                                </div>

                                <!-- Delivery Address -->
                                <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1v4a1 1 0 001 1m-6 0h6"></path>
                                            </svg>
                                        </div>
                                        <h3 class="font-bold text-gray-900">Your Address</h3>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="text-sm text-gray-600" id="customerAddress">${customerData.address}</div>
                                        <div class="text-xs text-gray-500" id="customerCoordinates">${customerData.latitude}, ${customerData.longitude}</div>
                                    </div>
                                </div>

                                <!-- Route Stats -->
                                <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2V7a2 2 0 012-2h2a2 2 0 002 2v2a2 2 0 002 2h2a2 2 0 012-2V7a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 00-2 2h-2a2 2 0 00-2 2v6"></path>
                                            </svg>
                                        </div>
                                        <h3 class="font-bold text-gray-900">Route Statistics</h3>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">Distance:</span>
                                            <span class="font-bold text-blue-600" id="routeDistance">Calculating...</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">Est. Travel Time:</span>
                                            <span class="font-medium text-gray-800" id="routeTime">Calculating...</span>
                                        </div>
                                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                            <span class="text-sm font-medium text-gray-700">Total Delivery Fee:</span>
                                            <span class="font-bold text-green-600" id="routeDeliveryFee">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Container -->
                        <div class="p-6">
                            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 border-b">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                            </svg>
                                            Interactive Route Map
                                        </h3>
                                        <div class="flex items-center gap-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                                <span class="text-gray-600">Store</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                                <span class="text-gray-600">Your Address</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-1 bg-blue-500"></div>
                                                <span class="text-gray-600">Route</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="deliveryMap" class="w-full h-96 relative">
                                    <div class="absolute inset-0 flex items-center justify-center bg-gray-100">
                                        <div class="text-center">
                                            <div class="animate-spin rounded-full h-12 w-12 border-4 border-orange-500 border-t-transparent mx-auto mb-4"></div>
                                            <p class="text-gray-600">Loading map...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Fee Breakdown -->
                        <div class="p-6 pt-0">
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl p-6 border border-orange-200">
                                <h3 class="font-bold text-orange-900 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                    Delivery Fee Calculation
                                </h3>
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-700">Base Fee:</span>
                                            <span class="font-medium" id="baseFeeDisplay">₱${parseFloat(deliverySettings.base_fee).toFixed(2)}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-700">Base Distance Covered:</span>
                                            <span class="font-medium" id="baseDistanceDisplay">${deliverySettings.total_km_base_fee} km</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-700">Additional Distance:</span>
                                            <span class="font-medium" id="additionalDistanceDisplay">0 km</span>
                                        </div>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-700">Per KM Rate:</span>
                                            <span class="font-medium" id="perKmRateDisplay">₱${parseFloat(deliverySettings.per_km_rate).toFixed(2)}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-700">Additional Distance Fee:</span>
                                            <span class="font-medium" id="additionalFeeDisplay">₱0.00</span>
                                        </div>
                                        <hr class="border-orange-300">
                                        <div class="flex justify-between items-center text-lg font-bold">
                                            <span class="text-orange-900">Total Delivery Fee:</span>
                                            <span class="text-orange-700" id="totalPerItemDisplay">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer - Fixed at bottom -->
                    <div class="border-t bg-gray-50 p-6 flex gap-3 justify-end flex-shrink-0">
                        <button onclick="closeDeliveryMapModal()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            Close Map
                        </button>
                    </div>
                </div>
            </div>
        `;

            // Add modal to the page
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Prevent body scrolling
            document.body.style.overflow = 'hidden';

            // Initialize the map after modal is added
            setTimeout(() => {
                initializeDeliveryMap(storeData, customerData, deliverySettings);
            }, 100);
        }

        // Function to close the delivery map modal
        function closeDeliveryMapModal() {
            const modal = document.getElementById('deliveryMapModal');
            if (modal) {
                modal.remove();
            }

            // Restore body scrolling
            document.body.style.overflow = '';

            // Clean up map
            if (deliveryMap) {
                deliveryMap.remove();
                deliveryMap = null;
                routingControl = null;
                storeMarker = null;
                customerMarker = null;
            }
        }

        // Function to initialize the delivery map with proper routing
        function initializeDeliveryMap(storeData, customerData, deliverySettings) {
            // Initialize map
            const centerLat = (parseFloat(storeData.latitude) + parseFloat(customerData.latitude)) / 2;
            const centerLng = (parseFloat(storeData.longitude) + parseFloat(customerData.longitude)) / 2;

            deliveryMap = L.map('deliveryMap').setView([centerLat, centerLng], 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(deliveryMap);

            // Custom markers
            const storeIcon = L.divIcon({
                html: `<div class="bg-green-500 w-8 h-8 rounded-full flex items-center justify-center border-2 border-white shadow-lg">
                     <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                       <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-2-13v6h0m-8-6v6h0M5 21h14"/>
                     </svg>
                   </div>`,
                className: '',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            const customerIcon = L.divIcon({
                html: `<div class="bg-red-500 w-8 h-8 rounded-full flex items-center justify-center border-2 border-white shadow-lg">
                     <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                       <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                     </svg>
                   </div>`,
                className: '',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            // Add markers
            storeMarker = L.marker([storeData.latitude, storeData.longitude], {
                    icon: storeIcon
                })
                .addTo(deliveryMap)
                .bindPopup(`<b>Store Location</b><br>${storeData.name}`);

            customerMarker = L.marker([customerData.latitude, customerData.longitude], {
                    icon: customerIcon
                })
                .addTo(deliveryMap)
                .bindPopup(`<b>Delivery Address</b><br>${customerData.address}`);

            // Load routing and calculate route
            loadRoutingAndCalculate();

            function loadRoutingAndCalculate() {
                // Check if Leaflet Routing Machine is already loaded
                if (typeof L.Routing !== 'undefined') {
                    addRoutingControl();
                } else {
                    // Load the routing library
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
                    script.onload = function() {
                        addRoutingControl();
                    };
                    script.onerror = function() {
                        console.error('Failed to load routing library, using fallback calculation');
                        calculateFallbackRoute();
                    };
                    document.head.appendChild(script);
                }
            }

            function addRoutingControl() {
                try {
                    // Add routing control
                    routingControl = L.Routing.control({
                        waypoints: [
                            L.latLng(storeData.latitude, storeData.longitude),
                            L.latLng(customerData.latitude, customerData.longitude)
                        ],
                        routeWhileDragging: false,
                        addWaypoints: false,
                        createMarker: function() {
                            return null;
                        }, // Don't create default markers
                        lineOptions: {
                            styles: [{
                                color: '#3B82F6',
                                weight: 4,
                                opacity: 0.8
                            }]
                        },
                        show: false, // Hide the routing instructions panel
                        router: L.Routing.osrmv1({
                            serviceUrl: 'https://router.project-osrm.org/route/v1',
                            profile: 'driving'
                        })
                    }).on('routesfound', function(e) {
                        const route = e.routes[0];
                        const distanceKm = (route.summary.totalDistance / 1000);
                        const timeMinutes = Math.round(route.summary.totalTime / 60);

                        // Store current route data
                        currentRouteData = {
                            distance: distanceKm,
                            time: timeMinutes,
                            success: true
                        };

                        updateRouteDisplay(currentRouteData, deliverySettings);

                        // Fit map to show the full route with padding
                        const bounds = L.latLngBounds([
                            [storeData.latitude, storeData.longitude],
                            [customerData.latitude, customerData.longitude]
                        ]);
                        deliveryMap.fitBounds(bounds, {
                            padding: [50, 50]
                        });

                    }).on('routingerror', function(e) {
                        console.error('Routing error:', e);
                        calculateFallbackRoute();
                    }).addTo(deliveryMap);

                } catch (error) {
                    console.error('Error creating routing control:', error);
                    calculateFallbackRoute();
                }
            }

            function calculateFallbackRoute() {
                // Use Haversine distance as fallback
                const distance = calculateHaversineDistance(
                    storeData.latitude, storeData.longitude,
                    customerData.latitude, customerData.longitude
                );

                currentRouteData = {
                    distance: distance,
                    time: Math.round(distance * 2), // Rough estimate: 2 minutes per km
                    success: false,
                    fallback: true
                };

                updateRouteDisplay(currentRouteData, deliverySettings);

                // Draw a simple straight line
                const straightLine = L.polyline([
                    [storeData.latitude, storeData.longitude],
                    [customerData.latitude, customerData.longitude]
                ], {
                    color: '#EF4444',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '10, 10'
                }).addTo(deliveryMap);

                // Fit map to show both points
                const bounds = L.latLngBounds([
                    [storeData.latitude, storeData.longitude],
                    [customerData.latitude, customerData.longitude]
                ]);
                deliveryMap.fitBounds(bounds, {
                    padding: [50, 50]
                });
            }

            // Ensure map renders properly
            setTimeout(() => {
                deliveryMap.invalidateSize();
            }, 200);
        }

        // Updated function to update route display in modal
        function updateRouteDisplay(routeData, deliverySettings) {
            // Update route statistics
            const routeDistanceElement = document.getElementById('routeDistance');
            const routeTimeElement = document.getElementById('routeTime');

            if (routeDistanceElement) {
                const distanceText = `${routeData.distance.toFixed(2)} km`;
                const fallbackNote = routeData.fallback ? ' (straight-line)' : '';
                routeDistanceElement.textContent = distanceText + fallbackNote;
            }
            if (routeTimeElement) routeTimeElement.textContent = `${routeData.time} minutes`;

            // Calculate delivery fee per item
            const baseFee = parseFloat(deliverySettings.base_fee);
            const perKmRate = parseFloat(deliverySettings.per_km_rate);
            const baseKm = parseFloat(deliverySettings.total_km_base_fee);

            let deliveryFeePerItem;
            let additionalDistance = 0;
            let additionalFee = 0;

            if (routeData.distance <= baseKm) {
                deliveryFeePerItem = baseFee;
            } else {
                additionalDistance = routeData.distance - baseKm;
                additionalFee = additionalDistance * perKmRate;
                deliveryFeePerItem = baseFee + additionalFee;
            }

            // Get total quantity for total delivery calculation
            let totalQuantity = 0;
            const cartItems = document.querySelectorAll('[id^="cartItem"]');
            cartItems.forEach((cartItem) => {
                const quantityElement = cartItem.querySelector('.itemQuantity');
                const quantity = parseInt(quantityElement.textContent);
                totalQuantity += quantity;
            });

            const totalDeliveryFee = deliveryFeePerItem * totalQuantity;

            // Update fee breakdown display
            const additionalDistanceDisplay = document.getElementById('additionalDistanceDisplay');
            const additionalFeeDisplay = document.getElementById('additionalFeeDisplay');
            const totalPerItemDisplay = document.getElementById('totalPerItemDisplay');
            const routeDeliveryFee = document.getElementById('routeDeliveryFee');

            if (additionalDistanceDisplay) additionalDistanceDisplay.textContent = `${additionalDistance.toFixed(2)} km`;
            if (additionalFeeDisplay) additionalFeeDisplay.textContent = `₱${additionalFee.toFixed(2)}`;
            if (totalPerItemDisplay) totalPerItemDisplay.textContent = `₱${totalDeliveryFee.toFixed(2)}`;
            if (routeDeliveryFee) routeDeliveryFee.textContent = `₱${totalDeliveryFee.toFixed(2)}`;

            // Store the calculated fee
            currentRouteData.deliveryFeePerItem = deliveryFeePerItem;
            currentRouteData.totalDeliveryFee = totalDeliveryFee;
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'deliveryMapModal') {
                closeDeliveryMapModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('deliveryMapModal');
                if (modal) {
                    closeDeliveryMapModal();
                }
            }
        });

        // Make closeDeliveryMapModal globally available for onclick handlers
        window.closeDeliveryMapModal = closeDeliveryMapModal;
    </script>

    <script>
        // New Bank Selection Functions for Checkout Form
        function showBankSelection() {
            document.getElementById('bankTransferFields').classList.remove('hidden');
            document.getElementById('bankSelectionArea').innerHTML = `
        <h4 class="font-semibold mb-3">Choose Your Bank</h4>
        <div class="grid grid-cols-2 gap-3 mb-4">
            <button type="button" onclick="selectBankOption('bdo')" 
                    class="p-3 border-2 border-blue-200 rounded-lg hover:border-blue-400 transition-colors">
                <div class="font-semibold">BDO</div>
                <div class="text-sm text-gray-600">Banco de Oro</div>
            </button>
            <button type="button" onclick="selectBankOption('aub')" 
                    class="p-3 border-2 border-red-200 rounded-lg hover:border-red-400 transition-colors">
                <div class="font-semibold">AUB</div>
                <div class="text-sm text-gray-600">Asia United Bank</div>
            </button>
        </div>
        <div id="selectedBankInfo" class="hidden"></div>
    `;
        }

        function selectBankOption(bankType) {
            const bankInfo = {
                bdo: {
                    name: 'BDO',
                    accountName: 'Noblehome Construction Corp.',
                    accountNumber: '013-238-001-657'
                },
                aub: {
                    name: 'AUB',
                    accountName: 'Noblehome Construction Corp.',
                    accountNumber: '151-010-002-868'
                }
            };

            document.getElementById('selectedBank').value = bankType;
            const bank = bankInfo[bankType];

            document.getElementById('selectedBankInfo').classList.remove('hidden');
            document.getElementById('selectedBankInfo').innerHTML = `
        <div class="p-4 bg-white rounded-lg border">
            <h5 class="font-semibold mb-2">Selected Bank: ${bank.name}</h5>
            <div class="space-y-2 text-sm mb-4">
    <div><strong>Account Name:</strong> ${bank.accountName}</div>
    <div><strong>Account Number:</strong> <span class="font-mono">${bank.accountNumber}</span></div>
</div>

<div class="mt-3 p-3 bg-yellow-50 rounded border border-yellow-200">
    <div class="text-sm font-medium text-yellow-800 mb-2">Payment Amount (with VAT):</div>
    <div class="space-y-1 text-sm text-yellow-700">
        <div class="flex justify-between">
            <span>Items Subtotal:</span>
            <span id="bankSubtotal">₱0.00</span>
        </div>
        <div class="flex justify-between">
            <span>VAT (12% on items):</span>
            <span id="bankVAT">₱0.00</span>
        </div>
        <div class="flex justify-between">
            <span>Delivery Fee:</span>
            <span id="bankDelivery">₱0.00</span>
        </div>
        <div class="flex justify-between font-bold text-yellow-900">
            <span>Total to Transfer:</span>
            <span id="bankTotal">₱0.00</span>
        </div>
    </div>
</div>
            
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Upload Payment Screenshot *</label>
                    <input type="file" name="payment_screenshot" accept="image/*" required
                           onchange="previewPaymentScreenshot(this)" capture="environment"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                    <div id="paymentScreenshotPreview"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Reference Number (Optional)</label>
                    <input type="text" onchange="document.getElementById('referenceNumber').value = this.value"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter reference number">
                </div>
            </div>
        </div>
    `;
            setTimeout(updateBankPaymentAmount, 100);
        }

        function previewPaymentScreenshot(input) {
            const preview = document.getElementById('paymentScreenshotPreview');
            preview.innerHTML = '';

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                <div class="mt-2">
                    <img src="${e.target.result}" class="max-w-full h-32 object-cover rounded border">
                    <p class="text-xs text-gray-600 mt-1">Screenshot Preview</p>
                </div>
            `;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Updated function to show correct VAT calculation in bank payment
        function updateBankPaymentAmount() {
            // Get current totals from the main display
            const grandTotalText = document.getElementById('grandTotalDisplay')?.textContent || '₱0.00';
            const vatAmountText = document.getElementById('vatAmount')?.textContent || '₱0.00';
            const deliveryText = document.getElementById('totalDeliveryCostDisplay')?.textContent || '₱0.00';
            
            // Items subtotal (without delivery, without VAT)
            const itemsSubtotal = subtotal.toFixed(2);

            // Update bank payment display
            const bankSubtotal = document.getElementById('bankSubtotal');
            const bankVAT = document.getElementById('bankVAT');
            const bankDelivery = document.getElementById('bankDelivery');
            const bankTotal = document.getElementById('bankTotal');

            if (bankSubtotal) bankSubtotal.textContent = `₱${itemsSubtotal}`;
            if (bankVAT) bankVAT.textContent = vatAmountText;
            if (bankDelivery) bankDelivery.textContent = deliveryText;
            if (bankTotal) bankTotal.textContent = grandTotalText;
        }
    </script>
</body>

</html>