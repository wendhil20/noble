<?php
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

function generateReferenceNumber() {
    return 'NH' . mt_rand(9800000, 9899999); // Customize range if needed
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

    $reference_no = generateReferenceNumber();

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

            // ✅ Save order (UPDATED to include billing_address_id, latitude, longitude)
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdsiddi", $name, $email, $mobile, $address, $zipcode, $payment_method, $total_price, $reference_no, $billing_address_id, $latitude, $longitude, $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $stmt->insert_id;
            $stmt->close();

            // ✅ Save each item - FIXED to handle missing product_name column
$stmt = $conn->prepare("INSERT INTO order_items (
    order_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($cart_items as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $product_name = $item['product_name'] ?? $item['variant_name'];
                $codename = $item['codename'] ?? '';
                $type_name = $item['type_name'] ?? '';
                $variant_color = $item['color_name'] ?: ($item['variant_name'] ?? '');
                $size = $item['size'] ?? '';
                $price = $item['price'];
                $quantity = $item['quantity'];

                // ✅ SIMPLIFIED: Get descrip6 and descrip7 directly from cart item
                $desc6 = $item['descrip6'] ?? '';
                $desc7 = $item['descrip7'] ?? '';
                $origin = $item['origin'] ?? '';  // ADD THIS LINE

                $stmt->bind_param(
    "isssssiiisss",
    $order_id,
    $product_name,
    $codename,
    $type_name,
    $variant_color,
    $size,
    $price,
    $quantity,
    $subtotal,
    $desc6,
    $desc7,
    $origin  // Use the variable instead
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

        <form method="POST" class="space-y-4">

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
                                       data-postal-code="<?= htmlspecialchars($addr['postal_code']) ?>" />
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

            <!-- Payment Method Section -->
            <div class="mt-6">
                <label class="block font-medium mb-3">Payment Method</label>
                <div class="space-y-3">
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="payment_method" value="Cash on Delivery" required class="mr-3" />
                        <div class="flex items-center">
                            <div class="text-green-600 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path>
                                    <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium">Cash on Delivery</div>
                                <div class="text-sm text-gray-600">Pay when you receive your order</div>
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="payment_method" value="GCash" required class="mr-3" />
                        <div class="flex items-center">
                            <div class="text-blue-600 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium">GCash</div>
                                <div class="text-sm text-gray-600">Pay via GCash mobile wallet</div>
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="payment_method" value="Bank Transfer" required class="mr-3" />
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

                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="payment_method" value="PayPal" required class="mr-3" />
                        <div class="flex items-center">
                            <div class="text-blue-800 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.736 6.979C9.208 6.193 9.696 6 10.4 6c.8 0 1.6.4 1.6 1.6 0 1.2-.8 2-2 2H8.8l-.064-.021zM7.2 11.2c0-.64.16-1.2.64-1.6.48-.4 1.12-.6 1.76-.6.96 0 1.6.48 1.6 1.44 0 .8-.48 1.44-1.28 1.44H8.4c-.64 0-1.2-.32-1.2-.68z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium">PayPal</div>
                                <div class="text-sm text-gray-600">Pay securely with PayPal</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Order Summary -->
            <h3 class="text-xl font-semibold mt-6 mb-4">Order Summary</h3>
            <div class="border rounded-lg overflow-hidden bg-white">
                <?php foreach ($cart_items as $index => $item): ?>
                    <div class="<?= $index > 0 ? 'border-t' : '' ?> p-4">
                        <div class="flex justify-between items-start gap-4">
                            <!-- Left side: Product details -->
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 mb-2 break-words">
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
                                        <div><span class="font-medium text-blue-600">Origin:</span> <span class="text-blue-700"><?= htmlspecialchars($item['origin']) ?></span></div>
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
                            </div>

                            <!-- Right side: Pricing -->
                            <div class="text-right flex-shrink-0 min-w-[140px]">
                                <div class="text-sm text-gray-600 mb-1">
                                    ₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?>
                                </div>
                                <div class="text-lg font-bold text-green-600">
                                    ₱<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Total section -->
                <div class="border-t bg-gray-50 p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900">Total:</span>
                        <span class="text-2xl font-bold text-green-700">₱<?= number_format($total_price, 2) ?></span>
                    </div>
                </div>
            </div>

            <div class="text-sm text-gray-600 text-center mt-4">
                By placing your order, you agree to our
                <a href="terms.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Terms</a>
                and
                <a href="privacy_policy.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Privacy Policy</a>.
            </div>

            <button type="submit" class="<?= !$has_billing_addresses ? 'bg-gray-400 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-700' ?> text-white px-6 py-2 rounded mt-6" <?= !$has_billing_addresses ? 'disabled' : '' ?> id="placeOrderBtn">
                <?= !$has_billing_addresses ? 'Set up address to continue' : 'Place Order' ?>
            </button>

            <div class="mt-4">
                <a href="cart_view.php" class="inline-flex items-center gap-2 text-sm text-orange-700 font-medium px-4 py-2 rounded-lg bg-orange-100 hover:bg-orange-200 transition">
                    ← Back to Cart
                </a>
            </div>
        </form>
    </div>

    <!-- Success Modal -->
    <?php if ($order_success): ?>
        <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-8 max-w-md mx-4 text-center">
                <div class="mb-4">
                    <svg class="mx-auto h-16 w-16 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Thank You!</h3>
                <p class="text-gray-600 mb-6">
                    Your request has been submitted successfully. We will review your order and contact you within 24 hours.
                    Please note that the final total includes a 12% VAT and applicable shipping fees, which will be reflected in your order summary.
                </p>

                <button onclick="closeModal()" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 transition">
                    Continue Shopping
                </button>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            function closeModal() {
                document.getElementById('successModal').style.display = 'none';
                window.location.href = 'cart_view.php';
            }

            // Auto close modal after 5 seconds
            setTimeout(function() {
                closeModal();
            }, 5000);
        </script>
    <?php endif; ?>

    <script>
        // ✅ Updated Billing Address Selector JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleBillingSelector');
            const selector = document.getElementById('billingAddressSelector');
            const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            
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
                        toggleBtn.textContent = selector.classList.contains('hidden') 
                            ? 'Show Address Selector' 
                            : 'Hide Address Selector';
                    });
                }
            }

            // Handle billing address selection
            billingRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Clean and format mobile number
                        let phone = this.dataset.phone;
                        // Remove spaces, dashes, parentheses, plus signs
                        phone = phone.replace(/[\s\-\(\)\+]/g, '');
                        // Convert +63 format to 09 format
                        if (phone.match(/^63([0-9]{10})$/)) {
                            phone = '0' + phone.substring(2);
                        }
                        
                        // Populate the fields and enable them
                        mobileInput.value = phone;
                        addressInput.value = this.dataset.address;
                        zipcodeInput.value = this.dataset.postalCode;
                        
                        // Enable fields for form submission but keep them visually disabled
                        mobileInput.disabled = false;
                        addressInput.disabled = false;
                        zipcodeInput.disabled = false;
                        
                        // Make them readonly instead of disabled so they submit but can't be edited
                        mobileInput.readOnly = true;
                        addressInput.readOnly = true;
                        zipcodeInput.readOnly = true;
                        
                        // Enable the place order button when an address is selected
                        if (placeOrderBtn) {
                            placeOrderBtn.disabled = false;
                            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                            placeOrderBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                            placeOrderBtn.textContent = 'Place Order';
                        }
                    }
                });
            });

            // Form submission handler - no longer needed since fields are enabled
            const checkoutForm = document.querySelector('form');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    // Double-check that required fields have values before submission
                    if (hasAddresses) {
                        if (!mobileInput.value.trim() || !addressInput.value.trim() || !zipcodeInput.value.trim()) {
                            e.preventDefault();
                            alert('Please select an address before placing your order.');
                            return false;
                        }
                    }
                });
            }
        });
    </script>

</body>

</html>