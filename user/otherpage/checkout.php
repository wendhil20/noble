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

// ✅ Function to detect zone by postal code
function detectZoneByPostalCode($postal_code, $conn) {
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
function calculateZoneBasedDelivery($cart_items, $distance_km, $selected_zone, $postal_code = null) {
    // Check if NCR (free delivery)
    if ($selected_zone['zone_code'] === 'NCR' || $selected_zone['is_free_delivery']) {
        return [
            'total_delivery_cost' => 0.00,
            'delivery_fee_per_item' => 0.00,
            'zone_info' => $selected_zone,
            'is_free' => true
        ];
    }
    
    // Calculate total items quantity
    $total_quantity = 0;
    foreach ($cart_items as $item) {
        $total_quantity += (int)$item['quantity'];
    }
    
    // Calculate zone-based fee
    $base_fee = (float)$selected_zone['base_fee'];
    $included_km = (float)$selected_zone['included_km'];
    $per_km_rate = (float)$selected_zone['per_km_rate'];
    
    $total_delivery_fee = $base_fee;
    
    if ($distance_km > $included_km) {
        $extra_km = $distance_km - $included_km;
        $total_delivery_fee += ($extra_km * $per_km_rate);
    }
    
    // Calculate per-item delivery cost
    $delivery_fee_per_item = $total_quantity > 0 ? $total_delivery_fee / $total_quantity : 0;
    
    return [
        'total_delivery_cost' => $total_delivery_fee,
        'delivery_fee_per_item' => $delivery_fee_per_item,
        'zone_info' => $selected_zone,
        'is_free' => false,
        'total_quantity' => $total_quantity
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

            // ✅ Save order (UPDATED to include delivery info and bank transfer data)
            $payment_status = ($payment_method === 'Bank Transfer') ? 'pending' : 'verified';
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id, delivery_distance, delivery_fee, subtotal, bank_type, payment_screenshot, reference_number, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdsiddidddssss", $name, $email, $mobile, $address, $zipcode, $payment_method, $grand_total, $reference_no, $billing_address_id, $latitude, $longitude, $user_id, $delivery_distance, $delivery_fee, $subtotal, $bank_type, $screenshot_filename, $reference_number, $payment_status);

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $stmt->insert_id;
            $stmt->close();


            // ✅ Calculate zone-based delivery
$delivery_result = calculateZoneBasedDelivery($cart_items, $delivery_distance, $selected_zone, $zipcode);
$delivery_fee = $delivery_result['total_delivery_cost'];
$delivery_fee_per_item = $delivery_result['delivery_fee_per_item'];

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

                
                // ✅ Calculate zone-based delivery
$delivery_result = calculateZoneBasedDelivery($cart_items, $delivery_distance, $selected_zone, $zipcode);
$delivery_fee = $delivery_result['total_delivery_cost'];
$delivery_fee_per_item = $delivery_result['delivery_fee_per_item'];

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
        <h2 class="text-3xl font-bold text-orange-700 mb-8">Checkout Process</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center step-indicator" data-step="1">
                    <div class="step-circle active">1</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Customer Info</div>
                        <div class="text-gray-500">Basic details</div>
                    </div>
                </div>
                <div class="flex-1 h-px bg-gray-300 mx-4"></div>
                <div class="flex items-center step-indicator" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Delivery Address</div>
                        <div class="text-gray-500">Where to deliver</div>
                    </div>
                </div>
                <div class="flex-1 h-px bg-gray-300 mx-4"></div>
                <div class="flex items-center step-indicator" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="ml-3 text-sm">
                        <div class="font-medium step-title">Delivery Fee</div>
                        <div class="text-gray-500">Calculate costs</div>
                    </div>
                </div>
                <div class="flex-1 h-px bg-gray-300 mx-4"></div>
                <div class="flex items-center step-indicator" data-step="4">
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
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                    <div class="flex items-center">
                        <div class="text-blue-600 mr-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-blue-800">Step 1: Customer Information</h3>
                            <p class="text-blue-700 text-sm">Verify your basic details</p>
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
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                    <div class="flex items-center">
                        <div class="text-green-600 mr-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-green-800">Step 2: Delivery Address</h3>
                            <p class="text-green-700 text-sm">Choose where to deliver your order</p>
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
    <select name="zone_id" id="zoneSelect" required 
            class="w-full border border-gray-300 px-4 py-3 rounded-lg bg-gray-100 cursor-not-allowed pointer-events-none" 
            disabled
            readonly
            tabindex="-1"
            onchange="selectDeliveryZone(this)">
        <option value="">Auto-detecting delivery zone...</option>
        <?php foreach ($delivery_zones as $zone): ?>
            <option value="<?= $zone['id'] ?>" 
                data-zone-name="<?= htmlspecialchars($zone['zone_name']) ?>"
                data-zone-code="<?= htmlspecialchars($zone['zone_code']) ?>"
                data-base-fee="<?= $zone['base_fee'] ?>"
                data-included-km="<?= $zone['included_km'] ?>"
                data-per-km-rate="<?= $zone['per_km_rate'] ?>"
                data-is-free="<?= $zone['is_free_delivery'] ?>">
                <?= htmlspecialchars($zone['zone_name']) ?>
                <?php if ($zone['is_free_delivery']): ?>
                    - FREE DELIVERY
                <?php else: ?>
                    - Base: ₱<?= number_format($zone['base_fee'], 2) ?>
                <?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
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
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex items-center">
                        <div class="text-yellow-600 mr-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-yellow-800">Step 3: Calculate Delivery Fee</h3>
                            <p class="text-yellow-700 text-sm">Determine delivery costs based on distance</p>
                        </div>
                    </div>
                </div>

                <?php if ($delivery_settings && $has_billing_addresses): ?>
                    <div class="bg-white border-2 border-yellow-300 rounded-lg p-6">
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
                <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-6">
                    <div class="flex items-center">
                        <div class="text-purple-600 mr-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
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
                        </div>

                        <!-- Bank Transfer Fields -->
                        <div id="bankTransferFields" class="hidden mt-6 p-4 bg-blue-50 rounded-lg">
                            <input type="hidden" name="bank_type" id="selectedBank">
                            <input type="hidden" name="reference_number" id="referenceNumber">
                            <div id="bankSelectionArea">
                                <!-- Bank selection will be populated by JavaScript -->
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
                                <div class="p-4" id="cartItem<?= $index ?>">
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
                        <button type="submit" id="placeOrderBtn" class="bg-green-600 text-white px-12 py-3 rounded-lg hover:bg-green-700 transition font-bold text-lg" disabled>
                            Place Order
                        </button>
                    </div>
                </div>

              
            </div>
        </form>
    </div>

        <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

                <!-- Enhanced Branding Section -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <!-- Logo with glow and pulse -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Text Branding -->
                        <div>
                            <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

                        </div>
                    </div>


                    <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
                        Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
                    </p>

                    <!-- Contact Info -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">0968 591 6536</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Quick Links
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <nav class="space-y-3">
                        <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
                        <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
                        <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
                    </nav>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Our Services
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <ul class="space-y-3 text-gray-300">
                        <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

            <!-- Bottom Section -->
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                <!-- Copyright -->
                <div class="text-center lg:text-left">
                    <p class="text-gray-400 text-sm">
                        © 2025 Noble Home Construction. All rights reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Licensed & Insured | PCAB License No. 12345
                    </p>
                </div>

                <!-- Enhanced Social Media -->
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm mr-2">Follow us:</span>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                </div>

                <!-- Back to Top Button -->
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Background Pattern -->
        <div class="absolute bottom-0 right-0 opacity-5">
            <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
                <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
                <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
                <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
            </svg>
        </div>
    </footer>


    <script>
   // Global variables
let deliverySettings = <?= $delivery_settings ? json_encode($delivery_settings) : 'null' ?>;
let deliveryZones = <?= json_encode($delivery_zones) ?>;
let selectedZone = null;
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

// Step management variables
let currentStep = 1;
const totalSteps = 4;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all event listeners
    initializeStepNavigation();
    initializeAddressSelection();
    initializeDistanceCalculation();
    initializeMapModal();
    initializeCheckoutForm();
    
    // Show first step by default
    showStep(1);
});

// Step Navigation Functions
function initializeStepNavigation() {
    // Add CSS for step indicators
    if (!document.getElementById('stepStyles')) {
        const style = document.createElement('style');
        style.id = 'stepStyles';
        style.textContent = `
            .step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                transition: all 0.3s ease;
            }
            
            .step-circle:not(.active):not(.completed) {
                background-color: #e5e7eb;
                color: #6b7280;
                border: 2px solid #d1d5db;
            }
            
            .step-circle.active {
                background-color: #f97316;
                color: white;
                border: 2px solid #ea580c;
                box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.2);
            }
            
            .step-circle.completed {
                background-color: #10b981;
                color: white;
                border: 2px solid #059669;
            }
            
            .step-indicator.active .step-title {
                color: #f97316;
                font-weight: 600;
            }
            
            .step-indicator.completed .step-title {
                color: #10b981;
                font-weight: 500;
            }
        `;
        document.head.appendChild(style);
    }
}

function goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > totalSteps) return;
    
    // Validate current step before moving
    if (!validateStep(currentStep) && stepNumber > currentStep) {
        return;
    }
    
    currentStep = stepNumber;
    showStep(stepNumber);
    updateStepIndicators();
    
    // Scroll to top of form
    document.querySelector('.bg-white.p-6').scrollIntoView({ behavior: 'smooth' });
}

function showStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(step => {
        step.classList.add('hidden');
    });
    
    // Show current step
    const currentStepElement = document.getElementById(`step${stepNumber}`);
    if (currentStepElement) {
        currentStepElement.classList.remove('hidden');
    }
}

function updateStepIndicators() {
    document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
        const stepNumber = index + 1;
        const circle = indicator.querySelector('.step-circle');
        
        // Reset classes
        indicator.classList.remove('active', 'completed');
        circle.classList.remove('active', 'completed');
        
        if (stepNumber < currentStep) {
            // Completed steps
            indicator.classList.add('completed');
            circle.classList.add('completed');
            circle.innerHTML = `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>`;
        } else if (stepNumber === currentStep) {
            // Current active step
            indicator.classList.add('active');
            circle.classList.add('active');
            circle.textContent = stepNumber;
        } else {
            // Future steps
            circle.textContent = stepNumber;
        }
    });
}

function validateStep(stepNumber) {
    switch(stepNumber) {
        case 1:
            // Customer info is readonly, always valid
            return true;
            
        case 2:
            // Check if address is selected
            const selectedRadio = document.querySelector('input[name="billing_address_id"]:checked');
            if (!selectedRadio) {
                showNotification('Please select a delivery address to continue.', 'error');
                return false;
            }
            return true;
            
        case 3:
            // FIXED: Check delivery calculation based on zone type
            if (!selectedZone) {
                showNotification('Please select a delivery zone to continue.', 'error');
                return false;
            }
            
            // For free delivery zones (NCR), no distance calculation needed
            if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                // Auto-calculate free delivery if not already done
                const deliveryFeeInput = document.getElementById('deliveryFee');
                const deliveryDistanceInput = document.getElementById('deliveryDistance');
                
                if (!deliveryFeeInput.value || parseFloat(deliveryFeeInput.value) !== 0) {
                    // Set free delivery values
                    deliveryFeeInput.value = '0.00';
                    deliveryDistanceInput.value = '0.00';
                    updateTotalsDisplay(0);
                    
                    // Update delivery display for free zones
                    const distanceResultElement = document.getElementById('distanceResult');
                    if (distanceResultElement) {
                        distanceResultElement.innerHTML = `
                            <div class="bg-green-100 border border-green-300 rounded p-3">
                                <div class="font-medium text-green-800">FREE DELIVERY!</div>
                                <div class="font-medium text-green-800">Zone: ${selectedZone.zone_name}</div>
                                <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
                            </div>
                        `;
                    }
                    
                    // Enable continue to payment button
                    const continueToPaymentBtn = document.getElementById('continueToPayment');
                    if (continueToPaymentBtn) {
                        continueToPaymentBtn.disabled = false;
                        continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                    }
                }
                return true;
            } else {
                // For paid delivery zones, check if distance is calculated
                const deliveryDistance = parseFloat(document.getElementById('deliveryDistance')?.value || '0');
                if (deliveryDistance <= 0) {
                    showNotification('Please calculate delivery distance and fee to continue.', 'error');
                    return false;
                }
                return true;
            }
            
        case 4:
            // Check payment method selection
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                showNotification('Please select a payment method to continue.', 'error');
                return false;
            }
            
            if (paymentMethod.value === 'Bank Transfer') {
                const selectedBank = document.getElementById('selectedBank').value;
                const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
                
                if (!selectedBank) {
                    showNotification('Please select a bank for transfer.', 'error');
                    return false;
                }
                
                if (!paymentScreenshot || !paymentScreenshot.files[0]) {
                    showNotification('Please upload a payment screenshot.', 'error');
                    return false;
                }
            }
            return true;
            
        default:
            return true;
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotification = document.getElementById('stepNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const colors = {
        'error': 'bg-red-100 border-red-400 text-red-700',
        'success': 'bg-green-100 border-green-400 text-green-700',
        'info': 'bg-blue-100 border-blue-400 text-blue-700',
        'warning': 'bg-yellow-100 border-yellow-400 text-yellow-700'
    };
    
    const notification = document.createElement('div');
    notification.id = 'stepNotification';
    notification.className = `fixed top-4 right-4 ${colors[type]} px-6 py-4 rounded-lg border shadow-lg z-50 max-w-md`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-1">${message}</div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function initializeAddressSelection() {
    const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
    const continueToDeliveryBtn = document.getElementById('continueToDelivery');
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const showMapBtn = document.getElementById('showMapModal');

    const mobileInput = document.getElementById('mobileInput');
    const addressInput = document.getElementById('addressInput');
    const zipcodeInput = document.getElementById('zipcodeInput');

    // Check if user has addresses
    const hasAddresses = <?= $has_billing_addresses ? 'true' : 'false' ?>;

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

                // AUTO-DETECT AND SELECT DELIVERY ZONE BASED ON POSTAL CODE
                if (this.dataset.postalCode) {
                    autoDetectAndSelectZone(this.dataset.postalCode);
                }

                // Enable continue button and other controls
                if (continueToDeliveryBtn) {
                    continueToDeliveryBtn.disabled = false;
                    continueToDeliveryBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToDeliveryBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                if (calculateDistanceBtn) {
                    calculateDistanceBtn.disabled = false;
                    calculateDistanceBtn.classList.remove('bg-gray-400');
                    calculateDistanceBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }
                
                if (showMapBtn) {
                    showMapBtn.disabled = false;
                    showMapBtn.classList.remove('bg-gray-400');
                    showMapBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                }

                // Show success notification
                showNotification('Address selected successfully! Delivery zone auto-detected.', 'success');
            }
        });
    });
}

// Enhanced auto-detect and select zone function
function autoDetectAndSelectZone(postalCode) {
    const zoneSelect = document.getElementById('zoneSelect');
    if (!zoneSelect || !postalCode) return;
    
    const postal_num = parseInt(postalCode);
    let targetZoneCode = 'LUZON'; // default fallback
    let detectedRegion = 'LUZON';
    
    // Enhanced postal code zone detection
    if (postal_num >= 1000 && postal_num <= 1800) {
        targetZoneCode = 'NCR';
        detectedRegion = 'NCR (Metro Manila)';
    } else if (postal_num >= 2000 && postal_num <= 3999) {
        targetZoneCode = 'LUZON';
        detectedRegion = 'LUZON';
    } else if (postal_num >= 4000 && postal_num <= 6999) {
        targetZoneCode = 'VISAYAS';
        detectedRegion = 'VISAYAS';
    } else if (postal_num >= 7000 && postal_num <= 9999) {
        targetZoneCode = 'MINDANAO';
        detectedRegion = 'MINDANAO';
    }
    
    // Find and select the matching zone option
    let zoneFound = false;
for (let i = 0; i < zoneSelect.options.length; i++) {
    const option = zoneSelect.options[i];
    if (option.dataset.zoneCode === targetZoneCode) {
        // Enable the select and update styling before setting the value
        zoneSelect.disabled = false;
zoneSelect.removeAttribute('readonly');
zoneSelect.removeAttribute('tabindex');
zoneSelect.classList.remove('bg-gray-100', 'cursor-not-allowed', 'pointer-events-none');
zoneSelect.classList.add('bg-white');
        
        zoneSelect.selectedIndex = i;
        selectDeliveryZone(zoneSelect);
        zoneFound = true;
            
            // Show appropriate notification with delivery cost info
            let message = `${detectedRegion} zone auto-selected based on postal code ${postalCode}`;
            let messageType = 'info';
            
            if (targetZoneCode === 'NCR') {
                message += ' - FREE DELIVERY!';
                messageType = 'success';
                
                // ADDED: Auto-setup free delivery for NCR
                setupFreeDelivery();
                
            } else {
                message += ` - Base delivery: ₱${parseFloat(option.dataset.baseFee).toFixed(2)}`;
                messageType = 'info';
            }
            
            showNotification(message, messageType);
            break;
        }
    }
    
    if (!zoneFound) {
        console.warn('No matching zone found for postal code:', postalCode);
        showNotification(`Postal code ${postalCode} detected as ${detectedRegion}, but no matching delivery zone found. Please select manually.`, 'warning');
    }
}

// ADDED: Function to setup free delivery automatically
function setupFreeDelivery() {
    if (!selectedZone || (!selectedZone.is_free_delivery && selectedZone.zone_code !== 'NCR')) {
        return;
    }
    
    // Set delivery values to zero
    const deliveryFeeInput = document.getElementById('deliveryFee');
    const deliveryDistanceInput = document.getElementById('deliveryDistance');
    
    if (deliveryFeeInput) deliveryFeeInput.value = '0.00';
    if (deliveryDistanceInput) deliveryDistanceInput.value = '0.00';
    
    // Update totals display
    updateTotalsDisplay(0);
    
    // Update delivery display
    const distanceResultElement = document.getElementById('distanceResult');
    if (distanceResultElement) {
        distanceResultElement.innerHTML = `
            <div class="bg-green-100 border border-green-300 rounded p-3">
                <div class="font-medium text-green-800">FREE DELIVERY!</div>
                <div class="font-medium text-green-800">Zone: ${selectedZone.zone_name}</div>
                <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
            </div>
        `;
    }
    
    // Enable continue to payment button
    const continueToPaymentBtn = document.getElementById('continueToPayment');
    if (continueToPaymentBtn) {
        continueToPaymentBtn.disabled = false;
        continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
    }
    
    // Update individual item delivery displays to show free
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');
        
        if (deliveryPerItemElement) {
            deliveryPerItemElement.textContent = '₱0.00';
        }
        if (totalDeliveryForItemElement) {
            totalDeliveryForItemElement.textContent = '₱0.00';
        }
    });
}

// Function to calculate distance using OSRM (same as map routing)
async function calculateRoutingDistance(storeLatLng, customerLatLng) {
    try {
        // First try OSRM routing
        const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false&geometries=geojson`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        const response = await fetch(url, {
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
            }
        });
        
        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`OSRM API error: ${response.status}`);
        }
        
        const data = await response.json();

        if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            const distanceKm = route.distance / 1000;
            const timeMinutes = Math.round(route.duration / 60);

            return {
                distance: distanceKm,
                time: timeMinutes,
                success: true,
                fallback: false
            };
        } else {
            throw new Error('No routes found in response');
        }
    } catch (error) {
        console.warn('OSRM routing failed, using fallback calculation:', error.message);
        
        // Validate coordinates before fallback calculation
        if (isNaN(storeLatLng.lat) || isNaN(storeLatLng.lng) || isNaN(customerLatLng.lat) || isNaN(customerLatLng.lng)) {
            throw new Error('Invalid coordinates provided for distance calculation');
        }
        
        // Fallback to Haversine distance
        const distance = calculateHaversineDistance(
            storeLatLng.lat, storeLatLng.lng,
            customerLatLng.lat, customerLatLng.lng
        );
        
        return {
            distance: distance,
            time: Math.round(distance * 3), // More realistic time estimate
            success: true,
            fallback: true
        };
    }
}

function initializeDistanceCalculation() {
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const continueToPaymentBtn = document.getElementById('continueToPayment');

    if (calculateDistanceBtn) {
        calculateDistanceBtn.addEventListener('click', async function() {
            if (!selectedAddress || !selectedZone) {
                showNotification('Please select an address and delivery zone.', 'error');
                return;
            }

            // ADDED: Handle free delivery zones without distance calculation
            if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                setupFreeDelivery();
                showNotification('Free delivery confirmed!', 'success');
                return;
            }

            // Show loading
            const originalText = calculateDistanceBtn.textContent;
            calculateDistanceBtn.textContent = 'Calculating...';
            calculateDistanceBtn.disabled = true;

            try {
                // Validate required data
                if (!selectedAddress) {
                    throw new Error('No delivery address selected');
                }
                if (!selectedZone) {
                    throw new Error('No delivery zone selected');
                }
                if (!deliverySettings) {
                    throw new Error('Delivery settings not loaded');
                }

                let distance = 0;
                let routeData = { distance: 0, time: 0, fallback: false };

                // Only calculate distance for paid delivery zones
                const storeLatLng = {
                    lat: parseFloat(deliverySettings.latitude),
                    lng: parseFloat(deliverySettings.longitude)
                };
                const customerLatLng = {
                    lat: selectedAddress.latitude,
                    lng: selectedAddress.longitude
                };

                if (isNaN(storeLatLng.lat) || isNaN(storeLatLng.lng)) {
                    throw new Error('Invalid store coordinates');
                }
                if (isNaN(customerLatLng.lat) || isNaN(customerLatLng.lng)) {
                    throw new Error('Invalid customer address coordinates');
                }

                routeData = await calculateRoutingDistance(storeLatLng, customerLatLng);
                distance = routeData.distance || 0;

                // Calculate zone-based delivery
                const deliveryResult = calculateZoneBasedDeliveryJS(distance, selectedZone);
                
                if (!deliveryResult) {
                    throw new Error('Failed to calculate delivery costs');
                }
                
                // Update UI
                updateDeliveryDisplay(deliveryResult, routeData, distance);
                
                // Update hidden fields
                document.getElementById('deliveryDistance').value = distance.toFixed(2);
                document.getElementById('deliveryFee').value = deliveryResult.totalDeliveryCost.toFixed(2);
                
                // Update totals
                updateTotalsDisplay(deliveryResult.totalDeliveryCost);
                
                // Enable continue button
                if (continueToPaymentBtn) {
                    continueToPaymentBtn.disabled = false;
                    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                const successMessage = `Delivery fee calculated: ₱${deliveryResult.totalDeliveryCost.toFixed(2)}`;
                showNotification(successMessage, 'success');

            } catch (error) {
                console.error('Delivery calculation error:', error);
                
                let errorMessage = 'Error calculating delivery fee. ';
                if (error.message.includes('coordinates')) {
                    errorMessage += 'Invalid location data.';
                } else if (error.message.includes('zone')) {
                    errorMessage += 'Please select a delivery zone.';
                } else if (error.message.includes('address')) {
                    errorMessage += 'Please select a delivery address.';
                } else {
                    errorMessage += 'Please try again or contact support.';
                }
                
                showNotification(errorMessage, 'error');
            } finally {
                calculateDistanceBtn.textContent = 'Recalculate Distance';
                calculateDistanceBtn.disabled = false;
            }
        });
    }
}

// JavaScript zone-based calculation
function calculateZoneBasedDeliveryJS(distance, zone) {
    if (zone.zone_code === 'NCR' || zone.is_free_delivery == 1) {
        return {
            totalDeliveryCost: 0,
            deliveryFeePerItem: 0,
            isFree: true
        };
    }
    
    // Calculate total items quantity
    let totalQuantity = 0;
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const quantityElement = cartItem.querySelector('.itemQuantity');
        totalQuantity += parseInt(quantityElement.textContent);
    });
    
    // Calculate zone fee
    const baseFee = parseFloat(zone.base_fee);
    const includedKm = parseFloat(zone.included_km);
    const perKmRate = parseFloat(zone.per_km_rate);
    
    let totalDeliveryFee = baseFee;
    if (distance > includedKm) {
        const extraKm = distance - includedKm;
        totalDeliveryFee += (extraKm * perKmRate);
    }
    
    const deliveryFeePerItem = totalQuantity > 0 ? totalDeliveryFee / totalQuantity : 0;
    
    return {
        totalDeliveryCost: totalDeliveryFee,
        deliveryFeePerItem: deliveryFeePerItem,
        isFree: false,
        totalQuantity: totalQuantity
    };
}

function updateDeliveryDisplay(deliveryResult, routeData, distance) {
    // Update distance result
    const distanceResultElement = document.getElementById('distanceResult');
    if (distanceResultElement) {
        if (deliveryResult.isFree) {
            distanceResultElement.innerHTML = `
                <div class="bg-green-100 border border-green-300 rounded p-3">
                    <div class="font-medium text-green-800">FREE DELIVERY!</div>
                    <div class="font-medium text-green-800">Zone: ${selectedZone.zone_name}</div>
                    <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
                </div>
            `;
        } else {
            distanceResultElement.innerHTML = `
                <div class="bg-blue-100 border border-blue-300 rounded p-3">
                    <div class="font-medium text-blue-800">Zone: ${selectedZone.zone_name}</div>
                    <div class="font-medium text-blue-800">Distance: ${distance.toFixed(2)} km</div>
                    <div class="font-medium text-blue-800">Est. Time: ${routeData.time} minutes</div>
                    <div class="font-medium text-blue-800">Total Delivery: ₱${deliveryResult.totalDeliveryCost.toFixed(2)}</div>
                    <div class="font-medium text-blue-800">Per Item: ₱${deliveryResult.deliveryFeePerItem.toFixed(2)}</div>
                </div>
            `;
        }
    }
    
    // Update individual item delivery displays
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const quantityElement = cartItem.querySelector('.itemQuantity');
        const quantity = parseInt(quantityElement.textContent);
        
        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');
        
        const itemTotalDelivery = deliveryResult.deliveryFeePerItem * quantity;
        
        if (deliveryPerItemElement) {
            deliveryPerItemElement.textContent = `₱${deliveryResult.deliveryFeePerItem.toFixed(2)}`;
        }
        if (totalDeliveryForItemElement) {
            totalDeliveryForItemElement.textContent = `₱${itemTotalDelivery.toFixed(2)}`;
        }
    });
}

function updateTotalsDisplay(deliveryCost) {
    const subtotal = <?= $total_price ?>;
    const totals = calculateTotalsWithVAT(subtotal, deliveryCost);
    
    // Update displays
    const subtotalBeforeVATElement = document.getElementById('subtotalBeforeVAT');
    const totalDeliveryCostDisplayElement = document.getElementById('totalDeliveryCostDisplay');
    const vatAmountElement = document.getElementById('vatAmount');
    const grandTotalDisplayElement = document.getElementById('grandTotalDisplay');
    
    if (subtotalBeforeVATElement) {
        subtotalBeforeVATElement.textContent = `₱${totals.subtotalWithDelivery.toFixed(2)}`;
    }
    if (totalDeliveryCostDisplayElement) {
        totalDeliveryCostDisplayElement.textContent = `₱${deliveryCost.toFixed(2)}`;
    }
    if (vatAmountElement) {
        vatAmountElement.textContent = `₱${totals.vatAmount.toFixed(2)}`;
    }
    if (grandTotalDisplayElement) {
        grandTotalDisplayElement.textContent = `₱${totals.grandTotal.toFixed(2)}`;
    }
    
    // Update bank transfer amount if visible
    updateBankPaymentAmount();
}

function initializeMapModal() {
    const showMapBtn = document.getElementById('showMapModal');

    if (showMapBtn) {
        showMapBtn.addEventListener('click', function() {
            if (!selectedAddress || !deliverySettings) {
                showNotification('Please select an address first.', 'error');
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

            // Final validation
            if (!validateStep(4)) {
                return;
            }

            // UPDATED: Check if delivery fee is calculated (considering free delivery)
            if (!selectedZone) {
                showNotification('Please select a delivery zone first.', 'error');
                return;
            }

            const deliveryFeeInput = document.getElementById('deliveryFee');
            const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

            if (deliveryFee === null || deliveryFee === undefined) {
                if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                    // Auto-setup free delivery if not already done
                    setupFreeDelivery();
                } else {
                    showNotification('Please calculate delivery distance first before placing your order.', 'error');
                    return;
                }
            }

            // Show loading state
            const originalText = placeOrderBtn.textContent;
            placeOrderBtn.textContent = 'Processing Order...';
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
                        showNotification('Order placed successfully! Redirecting to receipt...', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 2000);
                    } else if (typeof data === 'string') {
                        // HTML response - check for errors
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(data, 'text/html');
                        const errorDiv = doc.querySelector('.bg-red-100');

                        if (errorDiv) {
                            const errorText = errorDiv.textContent.replace('Error:', '').trim();
                            showNotification('Error: ' + errorText, 'error');
                        } else {
                            showNotification('An unexpected error occurred. Please try again.', 'error');
                        }

                        placeOrderBtn.textContent = originalText;
                        placeOrderBtn.disabled = false;
                    } else {
                        // Unexpected response format
                        showNotification('An unexpected error occurred. Please try again.', 'error');
                        placeOrderBtn.textContent = originalText;
                        placeOrderBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('A network error occurred. Please check your connection and try again.', 'error');
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

                            <!-- Route Summary -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-900">Route Info</h3>
                                </div>
                                <div class="space-y-2" id="routeInfoDisplay">
                                    <div class="text-sm text-gray-600">Calculating route...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div class="p-6">
                        <div class="bg-gray-100 rounded-lg overflow-hidden shadow-inner">
                            <div id="deliveryMapContainer" style="height: 500px; width: 100%;"></div>
                        </div>
                        
                        <!-- Map Controls -->
                        <div class="mt-4 flex justify-between items-center">
                            <div class="flex gap-2">
                                <button onclick="centerMapOnStore()" class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-2-13v6h0m-8-6v6h0M5 21h14"></path>
                                    </svg>
                                    Store
                                </button>
                                <button onclick="centerMapOnCustomer()" class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1v4a1 1 0 001 1m-6 0h6"></path>
                                    </svg>
                                    Your Address
                                </button>
                                <button onclick="fitMapToRoute()" class="bg-purple-600 text-white px-3 py-2 rounded text-sm hover:bg-purple-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                                    </svg>
                                    Fit Route
                                </button>
                            </div>
                            <button onclick="closeDeliveryMapModal()" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition font-medium">
                                Close Map
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to page
    document.body.appendChild(document.createElement('div')).innerHTML = modalHTML;
    const modal = document.getElementById('deliveryMapModal');

    // Initialize the map
    setTimeout(() => {
        initializeDeliveryMap(storeData, customerData, deliverySettings);
    }, 100);
}

// Function to initialize the delivery map
async function initializeDeliveryMap(storeData, customerData, deliverySettings) {
    const mapContainer = document.getElementById('deliveryMapContainer');
    
    if (!mapContainer) return;

    // Create the map
    deliveryMap = L.map('deliveryMapContainer').setView([storeData.latitude, storeData.longitude], 13);

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(deliveryMap);

    // Store marker (green)
    storeMarker = L.marker([storeData.latitude, storeData.longitude], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: #10b981; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
                </svg>
            </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        })
    }).addTo(deliveryMap);

    storeMarker.bindPopup(`
        <div class="text-center">
            <div class="font-bold text-green-700">${storeData.name}</div>
            <div class="text-sm text-gray-600 mt-1">Store Location</div>
        </div>
    `);

    // Customer marker (blue)
    customerMarker = L.marker([customerData.latitude, customerData.longitude], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: #3b82f6; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                </svg>
            </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        })
    }).addTo(deliveryMap);

    customerMarker.bindPopup(`
        <div class="text-center">
            <div class="font-bold text-blue-700">Your Address</div>
            <div class="text-sm text-gray-600 mt-1">${customerData.address}</div>
        </div>
    `);

    // Calculate and display route
    try {
        const routeData = await calculateRoutingDistance(
            { lat: storeData.latitude, lng: storeData.longitude },
            { lat: customerData.latitude, lng: customerData.longitude }
        );

        currentRouteData = routeData;

        // Update route info display
        const routeInfoDisplay = document.getElementById('routeInfoDisplay');
        if (routeInfoDisplay) {
            const fallbackNote = routeData.fallback ? '<div class="text-xs text-orange-600 mt-1">* Distance calculated using straight-line method</div>' : '';
            
            routeInfoDisplay.innerHTML = `
                <div class="text-sm">
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Distance:</span>
                        <span class="font-medium text-gray-800">${routeData.distance.toFixed(2)} km</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Est. Time:</span>
                        <span class="font-medium text-gray-800">${routeData.time} minutes</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Route Status:</span>
                        <span class="font-medium ${routeData.fallback ? 'text-orange-600' : 'text-green-600'}">
                            ${routeData.fallback ? 'Estimated' : 'Calculated'}
                        </span>
                    </div>
                    ${fallbackNote}
                </div>
            `;
        }

        // Add route to map if not using fallback
        if (!routeData.fallback && typeof L.Routing !== 'undefined') {
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(storeData.latitude, storeData.longitude),
                    L.latLng(customerData.latitude, customerData.longitude)
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                createMarker: function() { return null; }, // Don't create default markers
                lineOptions: {
                    styles: [{
                        color: '#f97316',
                        weight: 6,
                        opacity: 0.8
                    }]
                },
                show: false // Hide the directions panel
            }).addTo(deliveryMap);
        } else {
            // Draw straight line if routing failed
            const straightLine = L.polyline([
                [storeData.latitude, storeData.longitude],
                [customerData.latitude, customerData.longitude]
            ], {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 5'
            }).addTo(deliveryMap);
        }

        // Fit map to show both markers
        fitMapToRoute();

    } catch (error) {
        console.error('Error displaying route on map:', error);
        
        const routeInfoDisplay = document.getElementById('routeInfoDisplay');
        if (routeInfoDisplay) {
            routeInfoDisplay.innerHTML = `
                <div class="text-sm text-red-600">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Unable to calculate precise route
                    </div>
                    <div class="text-xs mt-1">Distance will be estimated during checkout</div>
                </div>
            `;
        }
        
        // Still fit map to show both markers
        fitMapToRoute();
    }
}

// Map control functions
function centerMapOnStore() {
    if (deliveryMap && storeMarker) {
        deliveryMap.setView(storeMarker.getLatLng(), 15);
        storeMarker.openPopup();
    }
}

function centerMapOnCustomer() {
    if (deliveryMap && customerMarker) {
        deliveryMap.setView(customerMarker.getLatLng(), 15);
        customerMarker.openPopup();
    }
}

function fitMapToRoute() {
    if (deliveryMap && storeMarker && customerMarker) {
        const group = new L.featureGroup([storeMarker, customerMarker]);
        deliveryMap.fitBounds(group.getBounds().pad(0.1));
    }
}

// Function to close the map modal
function closeDeliveryMapModal() {
    const modal = document.getElementById('deliveryMapModal');
    if (modal) {
        modal.remove();
    }
    
    // Clean up map resources
    if (deliveryMap) {
        deliveryMap.remove();
        deliveryMap = null;
    }
    if (routingControl) {
        routingControl = null;
    }
    storeMarker = null;
    customerMarker = null;
    currentRouteData = null;
}

// Bank Transfer Functions
function showBankSelection() {
    const bankTransferFields = document.getElementById('bankTransferFields');
    const bankSelectionArea = document.getElementById('bankSelectionArea');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    if (bankTransferFields) {
        bankTransferFields.classList.remove('hidden');
    }
    
    if (bankSelectionArea) {
        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer</h5>
                
                <div class="grid gap-3">
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="BPI" class="mr-3" onchange="selectBank('BPI')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                BPI
                            </div>
                            <div>
                                <div class="font-medium">Bank of the Philippine Islands</div>
                                <div class="text-sm text-gray-600">BPI</div>
                            </div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="BDO" class="mr-3" onchange="selectBank('BDO')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-800 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                BDO
                            </div>
                            <div>
                                <div class="font-medium">Banco de Oro</div>
                                <div class="text-sm text-gray-600">BDO</div>
                            </div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="Metrobank" class="mr-3" onchange="selectBank('Metrobank')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                MB
                            </div>
                            <div>
                                <div class="font-medium">Metropolitan Bank</div>
                                <div class="text-sm text-gray-600">Metrobank</div>
                            </div>
                        </div>
                    </label>
                </div>
                
                <div id="bankDetailsArea" class="hidden">
                    <!-- Bank details will be populated when a bank is selected -->
                </div>
            </div>
        `;
    }
}

function selectBank(bankType) {
    const selectedBankInput = document.getElementById('selectedBank');
    const bankDetailsArea = document.getElementById('bankDetailsArea');
    
    if (selectedBankInput) {
        selectedBankInput.value = bankType;
    }
    
    // Bank account details (you should replace these with actual account details)
    const bankDetails = {
        'BPI': {
            name: 'Bank of the Philippine Islands',
            accountName: 'Your Store Name',
            accountNumber: '1234567890',
            color: 'red'
        },
        'BDO': {
            name: 'Banco de Oro',
            accountName: 'Your Store Name',
            accountNumber: '0987654321',
            color: 'blue'
        },
        'Metrobank': {
            name: 'Metropolitan Bank',
            accountName: 'Your Store Name',
            accountNumber: '5678901234',
            color: 'yellow'
        }
    };
    
    const bank = bankDetails[bankType];
    if (bank && bankDetailsArea) {
        bankDetailsArea.classList.remove('hidden');
        bankDetailsArea.innerHTML = `
            <div class="bg-${bank.color}-50 border border-${bank.color}-200 rounded-lg p-4 mt-4">
                <h6 class="font-bold text-${bank.color}-800 mb-3">Transfer Details for ${bank.name}</h6>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Name:</span>
                        <span class="font-medium">${bank.accountName}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Number:</span>
                        <span class="font-medium font-mono">${bank.accountNumber}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount to Transfer:</span>
                        <span class="font-bold text-green-600" id="bankTransferAmount">₱0.00</span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Upload Payment Screenshot</label>
                    <input type="file" name="payment_screenshot" accept="image/*" required 
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                        onchange="validatePaymentScreenshot(this)" />
                    <div class="text-xs text-gray-500 mt-1">
                        Upload a clear screenshot of your bank transfer confirmation
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Reference Number (Optional)</label>
                    <input type="text" name="reference_number_input" 
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                        placeholder="Enter transaction reference number"
                        onchange="updateReferenceNumber(this.value)" />
                    <div class="text-xs text-gray-500 mt-1">
                        Reference number from your bank transfer receipt
                    </div>
                </div>
            </div>
        `;
        
        // Update the transfer amount
        updateBankPaymentAmount();
    }
}

function updateBankPaymentAmount() {
    const bankTransferAmountElement = document.getElementById('bankTransferAmount');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    
    if (bankTransferAmountElement && grandTotalDisplay) {
        const grandTotal = grandTotalDisplay.textContent;
        bankTransferAmountElement.textContent = grandTotal;
    }
}

function updateReferenceNumber(value) {
    const referenceNumberInput = document.getElementById('referenceNumber');
    if (referenceNumberInput) {
        referenceNumberInput.value = value;
    }
}

function validatePaymentScreenshot(input) {
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = file.size / 1024 / 1024; // Convert to MB
        
        if (fileSize > 5) {
            showNotification('Payment screenshot must be less than 5MB', 'error');
            input.value = '';
            return;
        }
        
        // Check if all required fields are filled
        const selectedBank = document.getElementById('selectedBank').value;
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        const deliveryFeeInput = document.getElementById('deliveryFee');
        const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;
        
        if (selectedBank && paymentMethod && (deliveryFee !== null && deliveryFee >= 0)) {
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        }
        
        showNotification('Payment screenshot uploaded successfully!', 'success');
    }
}

// Additional helper functions for better UX
function previewPaymentScreenshot(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Create preview if needed
            const previewArea = document.getElementById('screenshotPreview');
            if (previewArea) {
                previewArea.innerHTML = `
                    <img src="${e.target.result}" class="max-w-full h-32 object-cover rounded-lg border" alt="Payment Screenshot Preview">
                `;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Function to handle step validation for payment method
function validatePaymentStep() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    if (!paymentMethod) return false;
    
    if (paymentMethod.value === 'Bank Transfer') {
        const selectedBank = document.getElementById('selectedBank').value;
        const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
        
        if (!selectedBank) {
            showNotification('Please select a bank for transfer.', 'error');
            return false;
        }
        
        if (!paymentScreenshot || !paymentScreenshot.files[0]) {
            showNotification('Please upload a payment screenshot.', 'error');
            return false;
        }
        
        // Enable place order button
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }
        
        return true;
    }
    
    return false;
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('deliveryMapModal');
    if (modal && event.target === modal) {
        closeDeliveryMapModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('deliveryMapModal');
        if (modal) {
            closeDeliveryMapModal();
        }
    }
});

// Load Leaflet Routing Machine when needed
function loadLeafletRouting() {
    if (typeof L !== 'undefined' && !window.leafletRoutingLoaded) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
        script.onload = function() {
            window.leafletRoutingLoaded = true;
        };
        document.head.appendChild(script);
    }
}

// Initialize routing library on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLeafletRouting();
});

// Zone selection functions
function selectDeliveryZone(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        selectedZone = {
            id: option.value,
            zone_name: option.dataset.zoneName,
            zone_code: option.dataset.zoneCode,
            base_fee: option.dataset.baseFee,
            included_km: option.dataset.includedKm,
            per_km_rate: option.dataset.perKmRate,
            is_free_delivery: option.dataset.isFree === '1'
        };
        
        const description = document.getElementById('zoneDescription');
        if (selectedZone.is_free_delivery) {
            description.innerHTML = `<span class="text-green-600 font-medium">🎉 Free delivery for ${selectedZone.zone_name}!</span>`;
            
            // ADDED: Auto-setup free delivery when zone is selected
            setupFreeDelivery();
            
        } else {
            description.innerHTML = `<span class="text-blue-600">Base fee: ₱${parseFloat(selectedZone.base_fee).toFixed(2)} (includes ${selectedZone.included_km} km), ₱${parseFloat(selectedZone.per_km_rate).toFixed(2)} per additional km</span>`;
        }
    } else {
        selectedZone = null;
        document.getElementById('zoneDescription').textContent = 'Select your delivery zone to calculate shipping costs';
    }
}
</script>
</body>

</html>