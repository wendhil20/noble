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
    <title>Checkout - Step by Step</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
</head>

<body class="bg-gray-100 font-sans">
    <?php include '../navbar/top.php'; ?>

    

    <div class="bg-white p-6 rounded shadow mt-3 max-w-6xl mx-auto">
        <h2 class="text-3xl font-bold text-orange-700 mb-8 text-center">Complete Your Order</h2>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center mb-4 overflow-x-auto">
                <div class="flex items-center space-x-2 md:space-x-4 min-w-max px-4">
                    <!-- Step 1 -->
                    <div class="flex items-center">
                        <div id="step1-indicator" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-orange-600 text-white flex items-center justify-center font-bold text-sm md:text-base">1</div>
                        <span class="ml-2 font-medium text-gray-700 text-sm md:text-base hidden sm:inline">Customer Info</span>
                    </div>
                    <div class="w-8 md:w-12 h-1 bg-gray-300" id="progress1"></div>

                    <!-- Step 2 -->
                    <div class="flex items-center">
                        <div id="step2-indicator" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center font-bold text-sm md:text-base">2</div>
                        <span class="ml-2 font-medium text-gray-500 text-sm md:text-base hidden sm:inline">Address</span>
                    </div>
                    <div class="w-8 md:w-12 h-1 bg-gray-300" id="progress2"></div>

                    <!-- Step 3 -->
                    <div class="flex items-center">
                        <div id="step3-indicator" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center font-bold text-sm md:text-base">3</div>
                        <span class="ml-2 font-medium text-gray-500 text-sm md:text-base hidden sm:inline">Delivery</span>
                    </div>
                    <div class="w-8 md:w-12 h-1 bg-gray-300" id="progress3"></div>

                    <!-- Step 4 -->
                    <div class="flex items-center">
                        <div id="step4-indicator" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center font-bold text-sm md:text-base">4</div>
                        <span class="ml-2 font-medium text-gray-500 text-sm md:text-base hidden sm:inline">Payment</span>
                    </div>
                    <div class="w-8 md:w-12 h-1 bg-gray-300" id="progress4"></div>

                    <!-- Step 5 -->
                    <div class="flex items-center">
                        <div id="step5-indicator" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center font-bold text-sm md:text-base">5</div>
                        <span class="ml-2 font-medium text-gray-500 text-sm md:text-base hidden sm:inline">Review</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Display -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

      <form id="checkoutForm" method="POST" action="checkout.php" enctype="multipart/form-data">
        <!-- Step 1: Customer Information -->
        <div id="step1" class="step-container">
            <div class="bg-gradient-to-r from-orange-50 to-orange-100 p-6 rounded-lg border border-orange-200">
                <h3 class="text-xl font-bold text-orange-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Step 1: Customer Information
                </h3>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium mb-2">Full Name</label>
                        <input type="text" name="customer_name" required 
                               class="w-full border px-4 py-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                               value="<?= htmlspecialchars($userName ?? '') ?>" readonly />
                    </div>

                    <div>
                        <label class="block font-medium mb-2">Email Address</label>
                        <input type="email" name="email" required 
                               class="w-full border px-4 py-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                               value="<?= htmlspecialchars($userEmail ?? '') ?>" readonly />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="goToStep(2)" 
                            class="bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 transition">
                        Continue to Address →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Delivery Address -->
        <div id="step2" class="step-container hidden">
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-lg border border-blue-200">
                <h3 class="text-xl font-bold text-blue-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Step 2: Select Delivery Address
                </h3>

                <?php if ($has_billing_addresses): ?>
                    <!-- Address Management Buttons -->
                    <div class="flex justify-between items-center mb-4">
                        <div class="text-sm text-gray-600">Choose from your saved addresses or add a new one</div>
                        <a href="update_billing_add.php" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Address
                        </a>
                    </div>

                    <!-- Address Selection -->
                    <div class="space-y-3 mb-6 max-h-60 overflow-y-auto">
                        <?php foreach ($billing_addresses as $addr): ?>
                            <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-white billing-address-option border-gray-200 hover:border-blue-300 transition">
                                <input type="radio" name="billing_address_id" value="<?= $addr['id'] ?>" class="mt-2 mr-4" required
                                    data-full-name="<?= htmlspecialchars($addr['full_name']) ?>"
                                    data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                                    data-address="<?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?>"
                                    data-postal-code="<?= htmlspecialchars($addr['postal_code']) ?>"
                                    data-latitude="<?= $addr['latitude'] ?>"
                                    data-longitude="<?= $addr['longitude'] ?>" />
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($addr['full_name']) ?></div>
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

                    <!-- Address Preview Fields -->
                    <div class="grid md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block font-medium mb-2">Mobile Number</label>
                            <input type="tel" name="mobile" id="mobileInput" pattern="[0-9]{11}" required
                                   class="w-full border px-4 py-3 rounded-lg bg-gray-100" 
                                   placeholder="Select an address above" disabled readonly />
                        </div>

                        <div>
                            <label class="block font-medium mb-2">Full Address</label>
                            <textarea name="address" id="addressInput" rows="2" required
                                      class="w-full border px-4 py-3 rounded-lg resize-none bg-gray-100"
                                      placeholder="Select an address above" disabled readonly></textarea>
                        </div>

                        <div>
                            <label class="block font-medium mb-2">ZIP Code</label>
                            <input type="text" name="zipcode" id="zipcodeInput" pattern="[0-9]{4}" required
                                   class="w-full border px-4 py-3 rounded-lg bg-gray-100"
                                   placeholder="Select an address above" disabled readonly />
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No Addresses Available -->
                    <div class="border rounded-lg p-6 bg-red-50 border-red-200 mb-6">
                        <div class="text-center">
                            <svg class="mx-auto w-12 h-12 text-red-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <h4 class="font-bold text-red-900 text-lg mb-2">Address Required</h4>
                            <p class="text-red-700 mb-4">You must set up at least one delivery address before you can place an order.</p>
                            <a href="update_billing_add.php" class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Set up your address now
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(1)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="button" id="step2NextBtn" onclick="goToStep(3)" 
                            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition" 
                            <?= !$has_billing_addresses ? 'disabled' : '' ?>>
                        Continue to Delivery →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Delivery Details -->
        <div id="step3" class="step-container hidden">
            <div class="bg-gradient-to-r from-green-50 to-green-100 p-6 rounded-lg border border-green-200">
                <h3 class="text-xl font-bold text-green-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Step 3: Calculate Delivery Fee
                </h3>

                <?php if ($delivery_settings && $has_billing_addresses): ?>
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <!-- Store Information -->
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="font-bold text-gray-900 mb-3">Store Location</h4>
                            <p class="text-sm text-gray-700 mb-2"><?= htmlspecialchars($delivery_settings['location_name']) ?></p>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div><strong>Base Fee:</strong> ₱<?= number_format($delivery_settings['base_fee'], 2) ?></div>
                                <div><strong>Per KM Rate:</strong> ₱<?= number_format($delivery_settings['per_km_rate'], 2) ?></div>
                                <div><strong>Base Distance:</strong> <?= $delivery_settings['total_km_base_fee'] ?> km</div>
                            </div>
                        </div>

                        <!-- Distance Calculator -->
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="font-bold text-gray-900 mb-3">Distance Calculator</h4>
                            <div class="space-y-3">
                                <button type="button" id="calculateDistance" 
                                        class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 disabled:bg-gray-400 transition">
                                    Calculate Distance & Fee
                                </button>
                                <button type="button" id="showMapModal" 
                                        class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 disabled:bg-gray-400 flex items-center justify-center gap-2 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                    </svg>
                                    View Route Map
                                </button>
                            </div>
                            <div id="distanceResult" class="mt-3"></div>
                        </div>
                    </div>

                    <!-- Hidden inputs for calculated values -->
                    <input type="hidden" name="delivery_distance" id="deliveryDistance" value="0">
                    <input type="hidden" name="delivery_fee" id="deliveryFee" value="0">
                <?php endif; ?>

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(2)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="button" id="step3NextBtn" onclick="goToStep(4)" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition" >
                        Continue to Payment →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 4: Payment Method -->
        <div id="step4" class="step-container hidden">
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-6 rounded-lg border border-purple-200">
                <h3 class="text-xl font-bold text-purple-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    Step 4: Choose Payment Method
                </h3>

                <div class="space-y-4 mb-6">
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-white border-gray-200 hover:border-purple-300 transition">
                        <input type="radio" name="payment_method" value="Bank Transfer" required class="mr-4" onclick="showBankSelection()" />
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

                    <!-- Bank Transfer Fields -->
                    <div id="bankTransferFields" class="hidden mt-4 p-4 bg-white rounded-lg border">
                        <input type="hidden" name="bank_type" id="selectedBank">
                        <input type="hidden" name="reference_number" id="referenceNumber">
                        <div id="bankSelectionArea">
                            <!-- Bank selection will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(3)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="button" id="step4NextBtn" onclick="goToStep(5)" 
                            class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition" disabled>
                        Continue to Review →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 5: Review & Submit -->
        <div id="step5" class="step-container hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-lg border border-gray-200">
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    Step 5: Review Your Order
                </h3>

                <!-- Order Summary -->
                <div class="border rounded-lg overflow-hidden bg-white max-h-[400px] flex flex-col mb-6">
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

                                        <!-- Delivery Fee Details -->
                                        <div class="mt-3 p-2 bg-blue-50 rounded text-xs">
                                            <div class="grid grid-cols-2 gap-2 text-blue-700">
                                                <div><span class="font-medium">Delivery per item:</span> <span class="deliveryPerItem">₱0.00</span></div>
                                                <div><span class="font-medium">Total delivery:</span> <span class="totalDeliveryForItem">₱0.00</span></div>
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

                    <!-- Total section -->
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

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(2)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="button" id="step3NextBtn" onclick="goToStep(4)" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition" disabled>
                        Continue to Payment →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 4: Payment Method -->
        <div id="step4" class="step-container hidden">
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-6 rounded-lg border border-purple-200">
                <h3 class="text-xl font-bold text-purple-800 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    Step 4: Choose Payment Method
                </h3>

                <div class="space-y-4 mb-6">
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-white border-gray-200 hover:border-purple-300 transition">
                        <input type="radio" name="payment_method" value="Bank Transfer" required class="mr-4" onclick="showBankSelection()" />
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

                    <!-- Bank Transfer Fields -->
                    <div id="bankTransferFields" class="hidden mt-4 p-4 bg-white rounded-lg border">
                        <input type="hidden" name="bank_type" id="selectedBank">
                        <input type="hidden" name="reference_number" id="referenceNumber">
                        <div id="bankSelectionArea">
                            <!-- Bank selection will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(3)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="button" id="step4NextBtn" onclick="goToStep(5)" 
                            class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition" disabled>
                        Continue to Review →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 5: Review & Submit -->
        <div id="step5" class="step-container hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-lg border border-gray-200">
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 004.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    Step 5: Review Your Order
                </h3>

                <!-- Order Summary Section -->
                <div class="grid lg:grid-cols-2 gap-6 mb-6">
                    <!-- Left: Order Details -->
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="font-bold text-gray-900 mb-3">Customer Information</h4>
                            <div class="space-y-2 text-sm">
                                <div><strong>Name:</strong> <span id="reviewCustomerName"><?= htmlspecialchars($userName ?? '') ?></span></div>
                                <div><strong>Email:</strong> <span id="reviewEmail"><?= htmlspecialchars($userEmail ?? '') ?></span></div>
                                <div><strong>Mobile:</strong> <span id="reviewMobile">-</span></div>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="font-bold text-gray-900 mb-3">Delivery Address</h4>
                            <div class="text-sm text-gray-600" id="reviewAddress">Please select an address in Step 2</div>
                        </div>

                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="font-bold text-gray-900 mb-3">Payment Method</h4>
                            <div class="text-sm text-gray-600" id="reviewPayment">Please select a payment method in Step 4</div>
                        </div>
                    </div>

                    <!-- Right: Order Summary -->
                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-bold text-gray-900 mb-3">Order Summary</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Items Subtotal:</span>
                                <span class="font-medium">₱<?= number_format($total_price, 2) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Total Delivery Cost:</span>
                                <span class="font-medium" id="reviewTotalDelivery">₱0.00</span>
                            </div>
                            <hr class="border-gray-300">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Subtotal (Items + Delivery):</span>
                                <span class="font-medium" id="reviewSubtotalBeforeVAT">₱<?= number_format($total_price, 2) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">VAT (12%):</span>
                                <span class="font-medium text-orange-600" id="reviewVATAmount">₱0.00</span>
                            </div>
                            <hr class="border-gray-300">
                            <div class="flex justify-between items-center text-lg font-bold">
                                <span class="text-gray-900">Grand Total (with VAT):</span>
                                <span class="text-green-700" id="reviewGrandTotal">₱<?= number_format($total_price, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms Agreement -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" id="termsAgreement" class="mt-1" required>
                        <label for="termsAgreement" class="text-sm text-gray-700 cursor-pointer">
                            I agree to the 
                            <a href="../rules/terms.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Terms and Conditions</a>
                            and 
                            <a href="../rules/policy.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(4)" 
                            class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                        ← Back
                    </button>
                    <button type="submit" id="finalSubmitBtn"
                            class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-bold" disabled>
                        🛒 Place Order
                    </button>
                </div>

                <!-- Back to Cart Link -->
                <div class="mt-6 text-center">
                    <a href="cart_view.php" class="inline-flex items-center gap-2 text-sm text-orange-700 font-medium px-4 py-2 rounded-lg bg-orange-100 hover:bg-orange-200 transition">
                        ← Back to Cart
                    </a>
                </div>
            </div>
        </div>

    </form>
    </div>

    <script>
        // Global variables
        let deliverySettings = <?= $delivery_settings ? json_encode($delivery_settings) : 'null' ?>;
        let subtotal = <?= $total_price ?>;
        let selectedAddress = null;
        let currentStep = 1;
        let stepsCompleted = {
            step1: true,  // Customer info is pre-filled
            step2: false, // Address selection
            step3: false, // Delivery calculation
            step4: false, // Payment method
            step5: false  // Final review
        };

        // Global variables for the map modal
        let deliveryMap = null;
        let routingControl = null;
        let storeMarker = null;
        let customerMarker = null;
        let currentRouteData = null;

        document.addEventListener('DOMContentLoaded', function() {
            initializeStepNavigation();
            initializeAddressSelection();
            initializeDistanceCalculation();
            initializeMapModal();
            initializeCheckoutForm();
            updateStepIndicators();
        });

        // Step Navigation Functions
        function goToStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.step-container').forEach(step => {
                step.classList.add('hidden');
            });

            // Show target step
            document.getElementById(`step${stepNumber}`).classList.remove('hidden');
            currentStep = stepNumber;
            updateStepIndicators();

            // Update review section when going to step 5
            if (stepNumber === 5) {
                updateReviewSection();
            }

            // Scroll to top of form
            document.getElementById('checkoutForm').scrollIntoView({ behavior: 'smooth' });
        }

        function updateStepIndicators() {
            // Reset all indicators
            for (let i = 1; i <= 5; i++) {
                const indicator = document.getElementById(`step${i}-indicator`);
                const progress = document.getElementById(`progress${i}`);
                
                if (i < currentStep || stepsCompleted[`step${i}`]) {
                    // Completed steps
                    indicator.className = "w-8 h-8 md:w-10 md:h-10 rounded-full bg-green-600 text-white flex items-center justify-center font-bold text-sm md:text-base";
                    if (progress) progress.className = "w-8 md:w-12 h-1 bg-green-600";
                } else if (i === currentStep) {
                    // Current step
                    indicator.className = "w-8 h-8 md:w-10 md:h-10 rounded-full bg-orange-600 text-white flex items-center justify-center font-bold text-sm md:text-base";
                    if (progress) progress.className = "w-8 md:w-12 h-1 bg-gray-300";
                } else {
                    // Future steps
                    indicator.className = "w-8 h-8 md:w-10 md:h-10 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center font-bold text-sm md:text-base";
                    if (progress) progress.className = "w-8 md:w-12 h-1 bg-gray-300";
                }

                // Update step labels
                const stepLabels = document.querySelectorAll(`#step${i}-indicator`).forEach((_, index) => {
                    const label = document.querySelector(`#step${i}-indicator`).nextElementSibling;
                    if (label) {
                        if (i < currentStep || stepsCompleted[`step${i}`]) {
                            label.className = "ml-2 font-medium text-green-700 text-sm md:text-base hidden sm:inline";
                        } else if (i === currentStep) {
                            label.className = "ml-2 font-medium text-orange-700 text-sm md:text-base hidden sm:inline";
                        } else {
                            label.className = "ml-2 font-medium text-gray-500 text-sm md:text-base hidden sm:inline";
                        }
                    }
                });
            }
        }

        function initializeStepNavigation() {
            // Enable step 2 button initially if has addresses
            const hasAddresses = <?= $has_billing_addresses ? 'true' : 'false' ?>;
            if (!hasAddresses) {
                // Disable step navigation if no addresses
                const step2NextBtn = document.getElementById('step2NextBtn');
                if (step2NextBtn) {
                    step2NextBtn.disabled = true;
                    step2NextBtn.textContent = 'Set up address first';
                }
            }
        }

        function updateReviewSection() {
            // Update customer info
            const customerName = document.querySelector('input[name="customer_name"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const mobile = document.getElementById('mobileInput').value;

            document.getElementById('reviewCustomerName').textContent = customerName;
            document.getElementById('reviewEmail').textContent = email;
            document.getElementById('reviewMobile').textContent = mobile || '-';

            // Update address
            const address = document.getElementById('addressInput').value;
            document.getElementById('reviewAddress').textContent = address || 'Please select an address in Step 2';

            // Update payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const selectedBank = document.getElementById('selectedBank').value;
            let paymentText = 'Please select a payment method in Step 4';
            
            if (paymentMethod) {
                paymentText = paymentMethod.value;
                if (selectedBank) {
                    paymentText += ` (${selectedBank.toUpperCase()})`;
                }
            }
            document.getElementById('reviewPayment').textContent = paymentText;

            // Update totals
            const totalDelivery = document.getElementById('totalDeliveryCostDisplay').textContent;
            const vatAmount = document.getElementById('vatAmount').textContent;
            const grandTotal = document.getElementById('grandTotalDisplay').textContent;

            document.getElementById('reviewTotalDelivery').textContent = totalDelivery;
            document.getElementById('reviewVATAmount').textContent = vatAmount;
            document.getElementById('reviewGrandTotal').textContent = grandTotal;

            // Enable final submit if all steps completed
            updateFinalSubmitButton();
        }

        function updateFinalSubmitButton() {
            const termsChecked = document.getElementById('termsAgreement').checked;
            const allStepsCompleted = stepsCompleted.step2 && stepsCompleted.step3 && stepsCompleted.step4;
            const finalSubmitBtn = document.getElementById('finalSubmitBtn');

            if (finalSubmitBtn) {
                if (allStepsCompleted && termsChecked) {
                    finalSubmitBtn.disabled = false;
                    finalSubmitBtn.className = "bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-bold";
                } else {
                    finalSubmitBtn.disabled = true;
                    finalSubmitBtn.className = "bg-gray-400 text-white px-8 py-3 rounded-lg cursor-not-allowed font-bold";
                }
            }
        }

        // Function to calculate VAT and totals
        function calculateTotalsWithVAT(itemsSubtotal, deliveryCost) {
            const vatAmount = itemsSubtotal * 0.12; // VAT only on items, not delivery
            const grandTotal = itemsSubtotal + vatAmount + deliveryCost; // Add delivery after VAT calculation

            return {
                subtotalWithDelivery: itemsSubtotal + deliveryCost,
                vatAmount: vatAmount,
                grandTotal: grandTotal
            };
        }

        function initializeAddressSelection() {
            const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
            const mobileInput = document.getElementById('mobileInput');
            const addressInput = document.getElementById('addressInput');
            const zipcodeInput = document.getElementById('zipcodeInput');
            const calculateDistanceBtn = document.getElementById('calculateDistance');
            const showMapBtn = document.getElementById('showMapModal');

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
                        phone = phone.replace(/[\s\-\(\)\+]/g, '');
                        if (phone.match(/^63([0-9]{10})$/)) {
                            phone = '0' + phone.substring(2);
                        }

                        // Populate the fields
                        if (mobileInput) {
                            mobileInput.value = phone;
                            mobileInput.disabled = false;
                            mobileInput.readOnly = true;
                        }
                        if (addressInput) {
                            addressInput.value = this.dataset.address;
                            addressInput.disabled = false;
                            addressInput.readOnly = true;
                        }
                        if (zipcodeInput) {
                            zipcodeInput.value = this.dataset.postalCode;
                            zipcodeInput.disabled = false;
                            zipcodeInput.readOnly = true;
                        }

                        // Enable calculate distance and map buttons
                        if (calculateDistanceBtn) calculateDistanceBtn.disabled = false;
                        if (showMapBtn) showMapBtn.disabled = false;

                        // Mark step 2 as completed
                        stepsCompleted.step2 = true;
                        updateStepIndicators();
                    }
                });
            });

            // Add terms agreement listener
            const termsAgreement = document.getElementById('termsAgreement');
            if (termsAgreement) {
                termsAgreement.addEventListener('change', updateFinalSubmitButton);
            }
        }

        // Function to calculate distance using OSRM
        async function calculateRoutingDistance(storeLatLng, customerLatLng) {
            try {
                const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false&geometries=geojson`;

                const response = await fetch(url);
                const data = await response.json();

                if (data.routes && data.routes.length > 0) {
                    const route = data.routes[0];
                    const distanceKm = route.distance / 1000;
                    const timeMinutes = Math.round(route.duration / 60);

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
                const distance = calculateHaversineDistance(
                    storeLatLng.lat, storeLatLng.lng,
                    customerLatLng.lat, customerLatLng.lng
                );
                return {
                    distance: distance,
                    time: Math.round(distance * 2),
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
                        alert('Please select an address first.');
                        return;
                    }

                    // Show loading
                    calculateDistanceBtn.textContent = 'Calculating...';
                    calculateDistanceBtn.disabled = true;

                    // Calculate distance using routing API
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

                    // Calculate total delivery cost
                    let totalDeliveryCost = 0;
                    const cartItems = document.querySelectorAll('[id^="cartItem"]');

                    cartItems.forEach((cartItem, index) => {
                        const quantityElement = cartItem.querySelector('.itemQuantity');
                        const quantity = parseInt(quantityElement.textContent);
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
                    document.getElementById('deliveryDistance').value = distance.toFixed(2);
                    document.getElementById('deliveryFee').value = totalDeliveryCost.toFixed(2);

                    // Update totals display
                    const totals = calculateTotalsWithVAT(subtotal, totalDeliveryCost);

                    document.getElementById('totalDeliveryCostDisplay').textContent = `₱${totalDeliveryCost.toFixed(2)}`;
                    document.getElementById('subtotalBeforeVAT').textContent = `₱${subtotal.toFixed(2)}`;
                    document.getElementById('vatAmount').textContent = `₱${totals.vatAmount.toFixed(2)}`;
                    document.getElementById('grandTotalDisplay').textContent = `₱${totals.grandTotal.toFixed(2)}`;

                    // Reset button and mark step 3 as completed
                    calculateDistanceBtn.textContent = 'Recalculate Distance';
                    calculateDistanceBtn.disabled = false;
                    
                    stepsCompleted.step3 = true;
                    document.getElementById('step3NextBtn').disabled = false;
                    updateStepIndicators();

                    // Update bank payment amount if bank is selected
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

                    const storeData = {
                        name: deliverySettings.location_name,
                        latitude: parseFloat(deliverySettings.latitude),
                        longitude: parseFloat(deliverySettings.longitude)
                    };

                    const customerData = {
                        address: selectedAddress.address,
                        latitude: selectedAddress.latitude,
                        longitude: selectedAddress.longitude
                    };

                    createMapModal(storeData, customerData, deliverySettings);
                });
            }
        }

        function initializeCheckoutForm() {
            const checkoutForm = document.querySelector('#checkoutForm');

            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Check if delivery fee is calculated
                    const deliveryDistance = parseFloat(document.getElementById('deliveryDistance').value);

                    if (deliveryDistance <= 0) {
                        alert('Please calculate delivery distance first in Step 3.');
                        goToStep(3);
                        return;
                    }

                    // Check if payment method is selected
                    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    if (!paymentMethod) {
                        alert('Please select a payment method in Step 4.');
                        goToStep(4);
                        return;
                    }

                    // Show loading state
                    const submitBtn = document.getElementById('finalSubmitBtn');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Processing Order...';
                submitBtn.disabled = true;

                    // Submit the form
                    this.submit();
                });
            }
        }

        // Payment Method Functions
        function showBankSelection() {
            const bankTransferFields = document.getElementById('bankTransferFields');
            const bankSelectionArea = document.getElementById('bankSelectionArea');

            if (bankTransferFields) {
                bankTransferFields.classList.remove('hidden');
            }

            // Create bank selection UI
            if (bankSelectionArea) {
                bankSelectionArea.innerHTML = `
                    <h5 class="font-medium mb-3">Select Bank</h5>
                    <div class="grid md:grid-cols-2 gap-3 mb-4">
                        <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="bank_selection" value="gcash" class="mr-3" onchange="selectBank('gcash')">
                            <div class="text-sm">
                                <div class="font-medium">GCash</div>
                                <div class="text-gray-600">Mobile wallet transfer</div>
                            </div>
                        </label>
                        <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="bank_selection" value="bpi" class="mr-3" onchange="selectBank('bpi')">
                            <div class="text-sm">
                                <div class="font-medium">BPI</div>
                                <div class="text-gray-600">Bank of the Philippine Islands</div>
                            </div>
                        </label>
                        <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="bank_selection" value="bdo" class="mr-3" onchange="selectBank('bdo')">
                            <div class="text-sm">
                                <div class="font-medium">BDO</div>
                                <div class="text-gray-600">Banco de Oro</div>
                            </div>
                        </label>
                        <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="bank_selection" value="metrobank" class="mr-3" onchange="selectBank('metrobank')">
                            <div class="text-sm">
                                <div class="font-medium">Metrobank</div>
                                <div class="text-gray-600">Metropolitan Bank</div>
                            </div>
                        </label>
                    </div>
                    <div id="bankDetails" class="hidden">
                        <!-- Bank details will be populated by selectBank function -->
                    </div>
                `;
            }
        }

        function selectBank(bankType) {
            const selectedBankInput = document.getElementById('selectedBank');
            const bankDetailsDiv = document.getElementById('bankDetails');
            
            if (selectedBankInput) {
                selectedBankInput.value = bankType;
            }

            // Bank account details
            const bankInfo = {
                gcash: {
                    name: 'GCash',
                    accountName: 'Store Owner Name',
                    accountNumber: '09XX-XXX-XXXX',
                    instructions: 'Send payment via GCash and upload screenshot of transaction.'
                },
                bpi: {
                    name: 'BPI',
                    accountName: 'Store Owner Name',
                    accountNumber: 'XXXX-XXXX-XX',
                    instructions: 'Transfer to BPI account and provide reference number.'
                },
                bdo: {
                    name: 'BDO',
                    accountName: 'Store Owner Name',
                    accountNumber: 'XXXX-XXXX-XX',
                    instructions: 'Transfer to BDO account and provide reference number.'
                },
                metrobank: {
                    name: 'Metrobank',
                    accountName: 'Store Owner Name',
                    accountNumber: 'XXXX-XXXX-XX',
                    instructions: 'Transfer to Metrobank account and provide reference number.'
                }
            };

            const bank = bankInfo[bankType];
            if (bank && bankDetailsDiv) {
                const grandTotal = document.getElementById('grandTotalDisplay').textContent;
                
                bankDetailsDiv.innerHTML = `
                    <div class="bg-blue-50 border border-blue-200 rounded p-4">
                        <h6 class="font-bold text-blue-800 mb-3">${bank.name} Transfer Details</h6>
                        <div class="space-y-2 text-sm">
                            <div><strong>Account Name:</strong> ${bank.accountName}</div>
                            <div><strong>Account Number:</strong> ${bank.accountNumber}</div>
                            <div><strong>Amount to Transfer:</strong> <span class="text-lg font-bold text-green-600">${grandTotal}</span></div>
                        </div>
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <div class="text-xs text-yellow-800">${bank.instructions}</div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block font-medium mb-2">Reference Number / Transaction ID</label>
                            <input type="text" id="referenceInput" 
                                   class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-blue-500" 
                                   placeholder="Enter transaction reference number"
                                   onchange="updateReferenceNumber(this.value)">
                        </div>
                    </div>
                `;
                bankDetailsDiv.classList.remove('hidden');
            }

            // Enable step 4 next button
            stepsCompleted.step4 = true;
            document.getElementById('step4NextBtn').disabled = false;
            updateStepIndicators();
        }

        function updateReferenceNumber(value) {
            const referenceNumberInput = document.getElementById('referenceNumber');
            if (referenceNumberInput) {
                referenceNumberInput.value = value;
            }
        }

        function updateBankPaymentAmount() {
            const grandTotal = document.getElementById('grandTotalDisplay').textContent;
            const bankDetailsDiv = document.getElementById('bankDetails');
            
            if (bankDetailsDiv && !bankDetailsDiv.classList.contains('hidden')) {
                const amountSpan = bankDetailsDiv.querySelector('.text-green-600');
                if (amountSpan) {
                    amountSpan.textContent = grandTotal;
                }
            }
        }

        // Map Modal Functions
        function createMapModal(storeData, customerData, deliverySettings) {
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modalOverlay.id = 'mapModal';

            modalOverlay.innerHTML = `
                <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] flex flex-col">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h4 class="text-lg font-bold">Delivery Route</h4>
                        <button onclick="closeMapModal()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 p-4">
                        <div id="deliveryMap" class="w-full h-96 rounded border"></div>
                        <div class="mt-4 grid md:grid-cols-2 gap-4 text-sm">
                            <div class="bg-green-50 p-3 rounded">
                                <h5 class="font-medium text-green-800">Store Location</h5>
                                <div class="text-green-700">${storeData.name}</div>
                            </div>
                            <div class="bg-blue-50 p-3 rounded">
                                <h5 class="font-medium text-blue-800">Delivery Address</h5>
                                <div class="text-blue-700">${customerData.address}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modalOverlay);

            // Initialize map
            setTimeout(() => {
                initializeDeliveryMap(storeData, customerData);
            }, 100);

            // Close modal when clicking overlay
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    closeMapModal();
                }
            });
        }

        function initializeDeliveryMap(storeData, customerData) {
            const mapElement = document.getElementById('deliveryMap');
            if (!mapElement) return;

            // Calculate center point
            const centerLat = (storeData.latitude + customerData.latitude) / 2;
            const centerLng = (storeData.longitude + customerData.longitude) / 2;

            // Initialize map
            deliveryMap = L.map('deliveryMap').setView([centerLat, centerLng], 12);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(deliveryMap);

            // Store marker (green)
            storeMarker = L.marker([storeData.latitude, storeData.longitude])
                .addTo(deliveryMap)
                .bindPopup(`<b>Store Location</b><br>${storeData.name}`)
                .openPopup();

            // Customer marker (red)
            customerMarker = L.marker([customerData.latitude, customerData.longitude])
                .addTo(deliveryMap)
                .bindPopup(`<b>Delivery Address</b><br>${customerData.address}`);

            // Add routing if available
            if (typeof L.Routing !== 'undefined') {
                routingControl = L.Routing.control({
                    waypoints: [
                        L.latLng(storeData.latitude, storeData.longitude),
                        L.latLng(customerData.latitude, customerData.longitude)
                    ],
                    routeWhileDragging: false,
                    addWaypoints: false,
                    createMarker: function() { return null; }, // Use our custom markers
                    lineOptions: {
                        styles: [{ color: '#3B82F6', weight: 4, opacity: 0.7 }]
                    }
                }).addTo(deliveryMap);
            }

            // Fit map to show both points
            const group = new L.featureGroup([storeMarker, customerMarker]);
            deliveryMap.fitBounds(group.getBounds().pad(0.1));
        }

        function closeMapModal() {
            const modal = document.getElementById('mapModal');
            if (modal) {
                if (deliveryMap) {
                    deliveryMap.remove();
                    deliveryMap = null;
                }
                if (routingControl) {
                    routingControl = null;
                }
                modal.remove();
            }
        }

        // Utility Functions
        function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in kilometers
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        // Address Selection Styling
        document.addEventListener('change', function(e) {
            if (e.target.name === 'billing_address_id') {
                // Reset all address options
                document.querySelectorAll('.billing-address-option').forEach(option => {
                    option.classList.remove('border-blue-500', 'bg-blue-50');
                    option.classList.add('border-gray-200');
                });

                // Highlight selected option
                const selectedOption = e.target.closest('.billing-address-option');
                if (selectedOption) {
                    selectedOption.classList.remove('border-gray-200');
                    selectedOption.classList.add('border-blue-500', 'bg-blue-50');
                }
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMapModal();
            }
        });

        // Additional validation for form submission
        function validateEntireForm() {
            const errors = [];

            // Check address selection
            const selectedAddressRadio = document.querySelector('input[name="billing_address_id"]:checked');
            if (!selectedAddressRadio) {
                errors.push('Please select a delivery address');
            }

            // Check delivery calculation
            const deliveryDistance = parseFloat(document.getElementById('deliveryDistance').value || 0);
            if (deliveryDistance <= 0) {
                errors.push('Please calculate delivery distance first');
            }

            // Check payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                errors.push('Please select a payment method');
            } else if (paymentMethod.value === 'Bank Transfer') {
                const referenceNumber = document.getElementById('referenceNumber').value;
                if (!referenceNumber || referenceNumber.trim() === '') {
                    errors.push('Please provide a transaction reference number');
                }
            }

            // Check terms agreement
            const termsChecked = document.getElementById('termsAgreement').checked;
            if (!termsChecked) {
                errors.push('Please agree to the terms and conditions');
            }

            // Check mobile number format
            const mobileInput = document.getElementById('mobileInput').value;
            if (mobileInput && !mobileInput.match(/^09[0-9]{9}$/)) {
                errors.push('Please provide a valid Philippine mobile number (09XXXXXXXXX)');
            }

            return errors;
        }

        // Auto-save functionality (using memory storage only)
        let checkoutData = {};

        function saveCheckoutData() {
            // Save current form data to memory
            checkoutData = {
                selectedAddressId: document.querySelector('input[name="billing_address_id"]:checked')?.value,
                paymentMethod: document.querySelector('input[name="payment_method"]:checked')?.value,
                selectedBank: document.getElementById('selectedBank')?.value,
                referenceNumber: document.getElementById('referenceNumber')?.value,
                deliveryDistance: document.getElementById('deliveryDistance')?.value,
                deliveryFee: document.getElementById('deliveryFee')?.value,
                stepsCompleted: { ...stepsCompleted }
            };
        }

        function loadCheckoutData() {
            // Restore form data from memory
            if (checkoutData.selectedAddressId) {
                const addressRadio = document.querySelector(`input[name="billing_address_id"][value="${checkoutData.selectedAddressId}"]`);
                if (addressRadio) {
                    addressRadio.checked = true;
                    addressRadio.dispatchEvent(new Event('change'));
                }
            }

            if (checkoutData.paymentMethod) {
                const paymentRadio = document.querySelector(`input[name="payment_method"][value="${checkoutData.paymentMethod}"]`);
                if (paymentRadio) {
                    paymentRadio.checked = true;
                    if (checkoutData.paymentMethod === 'Bank Transfer') {
                        showBankSelection();
                        if (checkoutData.selectedBank) {
                            setTimeout(() => {
                                const bankRadio = document.querySelector(`input[name="bank_selection"][value="${checkoutData.selectedBank}"]`);
                                if (bankRadio) {
                                    bankRadio.checked = true;
                                    selectBank(checkoutData.selectedBank);
                                    if (checkoutData.referenceNumber) {
                                        document.getElementById('referenceInput').value = checkoutData.referenceNumber;
                                        updateReferenceNumber(checkoutData.referenceNumber);
                                    }
                                }
                            }, 100);
                        }
                    }
                }
            }

            if (checkoutData.stepsCompleted) {
                stepsCompleted = { ...checkoutData.stepsCompleted };
            }
        }

        // Auto-save on form changes
        document.addEventListener('change', function(e) {
            if (e.target.closest('#checkoutForm')) {
                saveCheckoutData();
            }
        });

        // Address validation
        function validatePhilippineAddress(address) {
            const philippineRegions = [
                'metro manila', 'ncr', 'region i', 'region ii', 'region iii', 'region iv-a', 'calabarzon',
                'region iv-b', 'mimaropa', 'region v', 'bicol', 'region vi', 'western visayas',
                'region vii', 'central visayas', 'region viii', 'eastern visayas',
                'region ix', 'zamboanga peninsula', 'region x', 'northern mindanao',
                'region xi', 'davao', 'region xii', 'soccsksargen', 'region xiii', 'caraga',
                'car', 'cordillera', 'armm', 'barmm'
            ];

            const lowerAddress = address.toLowerCase();
            return philippineRegions.some(region => lowerAddress.includes(region)) ||
                   lowerAddress.includes('philippines') ||
                   lowerAddress.includes('pilipinas');
        }

        // Mobile number formatting
        function formatPhilippineMobile(phone) {
            // Remove all non-digits
            let cleaned = phone.replace(/\D/g, '');
            
            // Handle different formats
            if (cleaned.startsWith('63') && cleaned.length === 12) {
                // +63 format to 09xx
                cleaned = '0' + cleaned.substring(2);
            } else if (cleaned.startsWith('9') && cleaned.length === 10) {
                // 9xx format to 09xx
                cleaned = '0' + cleaned;
            }
            
            // Validate Philippine mobile format (09xxxxxxxxx)
            if (cleaned.match(/^09[0-9]{9}$/)) {
                return cleaned;
            }
            
            return null; // Invalid format
        }

        // Enhanced error handling
        function showError(message, stepNumber = null) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            errorDiv.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(errorDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 5000);

            // Navigate to specific step if provided
            if (stepNumber) {
                setTimeout(() => goToStep(stepNumber), 500);
            }
        }

        function showSuccess(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            successDiv.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(successDiv);

            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.remove();
                }
            }, 3000);
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadCheckoutData();
            
            // Set up periodic auto-save
            setInterval(saveCheckoutData, 30000); // Save every 30 seconds
        });

        // Clean up before page unload
        window.addEventListener('beforeunload', function() {
            saveCheckoutData();
        });

        // Additional validation for form submission
        function validateEntireForm() {
            const errors = [];

            // Check address selection
            if (!document.querySelector('input[name="billing_address_id"]:checked')) {
                errors.push('Please select a delivery address');
            }

            // Check delivery calculation
            const deliveryDistance = parseFloat(document.getElementById('deliveryDistance').value);
            if (deliveryDistance <= 0) {
                errors.push('Please calculate delivery distance');
            }

            // Check payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                errors.push('Please select a payment method');
            } else if (paymentMethod.value === 'Bank Transfer') {
                const referenceNumber = document.getElementById('referenceNumber').value;
                if (!referenceNumber.trim()) {
                    errors.push('Please provide a transaction reference number');
                }
            }

            // Check terms agreement
            if (!document.getElementById('termsAgreement').checked) {
                errors.push('Please agree to the terms and conditions');
            }

            return errors;
        }

        // Loading script for Leaflet Routing Machine
        function loadRoutingMachine() {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
            script.onload = function() {
                console.log('Leaflet Routing Machine loaded successfully');
            };
            script.onerror = function() {
                console.warn('Failed to load Leaflet Routing Machine - using fallback distance calculation');
            };
            document.head.appendChild(script);
        }

        // Load routing machine after page loads
        window.addEventListener('load', loadRoutingMachine);
    </script>

    </body>
</html>