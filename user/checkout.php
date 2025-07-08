<?php
session_start();
include '../connection/connect.php';

$tables = ['products'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}


if (!isset($_SESSION['google_logged_in'])) {
    // Redirect if not logged in
    header('Location: google-callback.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;
$userPic = $_SESSION['user_picture'] ?? null; // Only if you saved this in callback

// ✅ Restore session from remember_token if needed
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['customer_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $address = $_POST['address'];
    $zipcode = $_POST['zipcode'];
    $payment_method = $_POST['payment_method']; // New payment method field

    if ($name && $email && $mobile && $address && $zipcode && $payment_method && !empty($cart_items)) {
        // ✅ Reset auto-increment if needed
        $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE order_items AUTO_INCREMENT = 1");

        // ✅ Save order (UPDATED to include payment_method)
      $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssd", $name, $email, $mobile, $address, $zipcode, $payment_method, $total_price);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // ✅ Save each item (UPDATED to include size)
        // ✅ Updated Save each item with descrip6 and descrip7
$stmt = $conn->prepare("INSERT INTO order_items (
    order_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($cart_items as $item) {
    $subtotal = $item['price'] * $item['quantity'];

    $product_name = $item['product_name'] ?? $item['variant_name'];
    $codename = $item['codename'];
    $type_name = $item['type_name'];
    $variant_color = $item['color_name'] ?: $item['variant_name'];
    $size = $item['size'] ?? '';
    $price = $item['price'];
    $quantity = $item['quantity'];

    // 🔍 Fetch descrip6 and descrip7 from product_variants
    $desc_stmt = $conn->prepare("SELECT descrip6, descrip7 FROM product_variants WHERE namevariant = ? LIMIT 1");
    $desc_stmt->bind_param("s", $codename);
    $desc_stmt->execute();
    $desc_result = $desc_stmt->get_result();
    $desc_data = $desc_result->fetch_assoc();
    $desc6 = $desc_data['descrip6'] ?? '';
    $desc7 = $desc_data['descrip7'] ?? '';
    $desc_stmt->close();

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
    $stmt->execute();
}
$stmt->close();


        // ✅ Clear cart
        $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Set success flag to show modal
        $order_success = true;
        $_SESSION['checkout_notice'] = 'Order placed successfully!';
    } else {
        $error = "Please complete all fields and ensure your cart is not empty.";
    }
}

// Helper function to format item details
function formatItemDetails($item)
{
    $details = [];

    // Always show type
    if (!empty($item['type_name'])) {
        $details[] = htmlspecialchars($item['type_name']);
    }

    // Only show size if it exists and is not empty
    if (!empty($item['size']) && trim($item['size']) !== '') {
        $details[] = htmlspecialchars($item['size']);
    }

    // Show color if available
    if (!empty($item['color_name'])) {
        $details[] = htmlspecialchars($item['color_name']);
    } elseif (empty($item['color_name']) && !empty($item['variant_name'])) {
        // Fallback to variant name if no color
        $details[] = htmlspecialchars($item['variant_name']);
    }

    return implode(' / ', $details);
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

        <?php if (isset($error)): ?>
            <p class="text-red-600 mb-4"><?= $error ?></p>
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

            <div><label class="block font-medium">Mobile Number</label>
                <input type="tel" name="mobile" pattern="[0-9]{11}" required class="w-full border px-4 py-2 rounded" placeholder="e.g. 09171234567" />
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
        // Fetch descrip6 and descrip7 for this item
        $codename = $item['codename'];
        $desc_stmt = $conn->prepare("SELECT descrip6, descrip7 FROM product_variants WHERE namevariant = ? LIMIT 1");
        $desc_stmt->bind_param("s", $codename);
        $desc_stmt->execute();
        $desc_result = $desc_stmt->get_result();
        $desc_data = $desc_result->fetch_assoc();
        $desc6 = $desc_data['descrip6'] ?? '';
        $desc7 = $desc_data['descrip7'] ?? '';
        $desc_stmt->close();
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
                        <div class="text-xs text-gray-500 mt-1 italic">
                            <?= htmlspecialchars($desc6) ?><br>
                            <?= htmlspecialchars($desc7) ?>
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
                </p>
                <button onclick="closeModal()" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 transition">
                    Continue Shopping
                </button>
            </div>
        </div>

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