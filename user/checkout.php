<?php
session_name("nobleuser");
session_start();
include '../connection/connect.php';

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
    header('Location: google-callback.php'); // You may replace with `index.php` if default login
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

if ($user_id) {
    // ✅ Fetch cart items
    $stmt = $conn->prepare("SELECT * FROM user_cart_items WHERE user_id = ?");
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
    $payment_method = trim($_POST['payment_method'] ?? ''); // New payment method field


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
    } elseif (!preg_match('/^[0-9]{11}$/', $mobile)) {
        $validation_errors[] = "Mobile number must be exactly 11 digits";
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

    // If there are validation errors, show them
    if (!empty($validation_errors)) {
        $error = implode(', ', $validation_errors);
    } else {
        // All validations passed, proceed with order
        try {
            // ✅ Reset auto-increment if needed
            $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE order_items AUTO_INCREMENT = 1");

            // ✅ Save order (UPDATED to include payment_method)
           $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssds", $name, $email, $mobile, $address, $zipcode, $payment_method, $total_price, $reference_no);


            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $stmt->insert_id;
            $stmt->close();

            // ✅ Save each item - FIXED to handle missing product_name column
            $stmt = $conn->prepare("INSERT INTO order_items (
                order_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

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

                $stmt->bind_param(
                    "isssssiiiss",
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
                    $desc7
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
    <?php include 'navbar/top.php'; ?>

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

            <div>
                <label class="block font-medium">Mobile Number</label>
                <input
                    type="tel"
                    name="mobile"
                    pattern="[0-9]{11}"
                    required
                    class="w-full border px-4 py-2 rounded bg-gray-50 focus:bg-white"
                    value="<?= htmlspecialchars($userMobile ?? '') ?>"
                    placeholder="e.g. 09171234567" />
            </div>


            <div><label class="block font-medium">Full Address</label>
                <textarea name="address" rows="3" required class="w-full border px-4 py-2 rounded resize-none"></textarea>
            </div>

            <div><label class="block font-medium">ZIP Code</label>
                <input type="text" name="zipcode" pattern="[0-9]{4}" required class="w-full border px-4 py-2 rounded" />
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
            <h3 class="text-xl font-semibold mt-6 mb-2">Order Summary</h3>
            <ul class="divide-y text-sm max-h-60 overflow-y-auto border rounded-md p-2 bg-white">
                <?php foreach ($cart_items as $item): ?>
                    <?php
                    // Use the new function to get descrip6 and descrip7
                    $descriptions = getProductDescription($conn, $item['codename'], $item['product_name'] ?? '', $item['variant_name'] ?? '');
                    $desc6 = $descriptions['desc6'];
                    $desc7 = $descriptions['desc7'];
                    ?>
                    <li class="py-2">
                        <div class="flex justify-between items-start">
                            <div class="w-2/3">
                                <div class="font-medium"><?= htmlspecialchars($item['variant_name']) ?></div>
                                <?php $details = formatItemDetails($item); ?>
                                <?php if (!empty($details)): ?>
                                    <div class="text-gray-600 text-xs"><?= $details ?></div>
                                <?php endif; ?>

                                <?php if (!empty($desc6) || !empty($desc7)): ?>
                                    <div class="text-xs text-gray-500 mt-1 italic leading-tight">
                                        <?php if (!empty($desc6)): ?>
                                            <span class="text-gray-600 font-medium"></span> <?= htmlspecialchars($desc6) ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($desc7)): ?>
                                            <span class="text-gray-600 font-medium"></span> <?= htmlspecialchars($desc7) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <div class="text-right w-1/3">
                                <div>₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
                                <div class="text-xs text-gray-500">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="text-right text-xl mt-4 font-bold text-green-700">
                Total: ₱<?= number_format($total_price, 2) ?>
            </div>

            <div class="text-sm text-gray-600 text-center mt-4">
                By placing your order, you agree to our
                <a href="terms.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Terms</a>
                and
                <a href="privacy_policy.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Privacy Policy</a>.
            </div>

            <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 mt-6">
                Place Order
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

</body>

</html>