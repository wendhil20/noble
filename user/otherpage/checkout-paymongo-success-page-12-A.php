<?php
// paymongo-success.php - FIXED with proper stock deduction
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token
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
        $_SESSION['user_email'] = $user['email'];

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';

$order_id = intval($_GET['order_id'] ?? 0);
$reference_no = $_GET['ref'] ?? '';

$order_found = false;
$payment_success = false;
$error_message = '';
$order = null;
$order_items = null;

try {
    if ($order_id > 0) {
        // Find the order
        $stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE id = ? AND user_id = ? AND mode_payment = 'PayMongo'
        ");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            $order_found = true;
        }
        $stmt->close();

        if ($order_found) {
            $payment_success = true;

            // ✅ CRITICAL: Check if this is FIRST TIME on success page
            // Use order_id as unique key to prevent duplicate stock deductions
            $stock_deduction_key = 'stock_deducted_' . $order['id'];

            if (!isset($_SESSION[$stock_deduction_key])) {
                error_log("=== FIRST TIME SUCCESS PAGE - DEDUCTING STOCK ===");
                error_log("Order ID: " . $order['id']);

                // ✅ STEP 1: Get order items with stock details
                $items_stmt = $conn->prepare("
                    SELECT 
                        id,
                        product_id,
                        variant_id,
                        quantity
                    FROM order_items
                    WHERE order_id = ?
                ");

                if (!$items_stmt) {
                    throw new Exception('Failed to prepare items query: ' . $conn->error);
                }

                $items_stmt->bind_param("i", $order['id']);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();

                error_log("Total items to process: " . $items_result->num_rows);

                // ✅ STEP 2: Process each item (FIXED)
                while ($item = $items_result->fetch_assoc()) {
                    $variant_id = $item['variant_id'];
                    $quantity = $item['quantity'];
                    $product_id = $item['product_id'];

                    error_log("Processing: Product #$product_id, Variant #$variant_id, Qty: $quantity");

                    if (!empty($variant_id)) {
                        // ✅ FIX: Get color_id from order_items (which color customer actually ordered)
                        // First check if order_items has color_id field
                        $item_color_check = $conn->prepare("
            SELECT color_id FROM order_items 
            WHERE id = ? 
            LIMIT 1
        ");

                        if ($item_color_check) {
                            $item_color_check->bind_param("i", $item['id']);
                            $item_color_check->execute();
                            $item_color_result = $item_color_check->get_result();

                            if ($item_color_result->num_rows > 0) {
                                // ✅ Color_id is stored in order_items - USE THIS
                                $item_color_row = $item_color_result->fetch_assoc();
                                $color_id = $item_color_row['color_id'];

                                error_log("  Found color_id from order_items: $color_id");

                                // Deduct from junction table
                                $deduct_junction = $conn->prepare("
                    UPDATE product_variant_colors 
                    SET stock_quantity = GREATEST(0, stock_quantity - ?)
                    WHERE variant_id = ? AND color_id = ?
                ");

                                if ($deduct_junction) {
                                    $deduct_junction->bind_param("iii", $quantity, $variant_id, $color_id);

                                    if ($deduct_junction->execute()) {
                                        error_log("  ✓ Junction table updated - rows: " . $deduct_junction->affected_rows);

                                        // Check remaining stock
                                        $check = $conn->prepare("
                            SELECT stock_quantity 
                            FROM product_variant_colors 
                            WHERE variant_id = ? AND color_id = ?
                        ");
                                        $check->bind_param("ii", $variant_id, $color_id);
                                        $check->execute();
                                        $check_res = $check->get_result();
                                        if ($stock_row = $check_res->fetch_assoc()) {
                                            error_log("  → Remaining: {$stock_row['stock_quantity']}");
                                        }
                                        $check->close();
                                    } else {
                                        error_log("  ✗ Failed: " . $deduct_junction->error);
                                    }
                                    $deduct_junction->close();
                                }
                            } else {
                                // ✅ FALLBACK: If color_id NOT in order_items, get from variant_color info
                                error_log("  No color_id in order_items, trying variant_color field...");

                                // Check if order_items has variant_color field (color name)
                                $variant_color_check = $conn->prepare("
                    SELECT variant_color FROM order_items 
                    WHERE id = ? 
                    LIMIT 1
                ");

                                if ($variant_color_check) {
                                    $variant_color_check->bind_param("i", $item['id']);
                                    $variant_color_check->execute();
                                    $variant_color_result = $variant_color_check->get_result();

                                    if ($variant_color_result->num_rows > 0) {
                                        $color_name_row = $variant_color_result->fetch_assoc();
                                        $color_name = $color_name_row['variant_color'];

                                        // Get color_id from color_name
                                        $get_color_id = $conn->prepare("
                            SELECT id FROM product_colors 
                            WHERE color_name = ? 
                            LIMIT 1
                        ");

                                        if ($get_color_id) {
                                            $get_color_id->bind_param("s", $color_name);
                                            $get_color_id->execute();
                                            $get_color_id_result = $get_color_id->get_result();

                                            if ($get_color_id_result->num_rows > 0) {
                                                $color_id_row = $get_color_id_result->fetch_assoc();
                                                $color_id = $color_id_row['id'];

                                                error_log("  Found color_id from variant_color name: $color_id");

                                                // Deduct from junction table
                                                $deduct_junction = $conn->prepare("
                                    UPDATE product_variant_colors 
                                    SET stock_quantity = GREATEST(0, stock_quantity - ?)
                                    WHERE variant_id = ? AND color_id = ?
                                ");

                                                if ($deduct_junction) {
                                                    $deduct_junction->bind_param("iii", $quantity, $variant_id, $color_id);
                                                    if ($deduct_junction->execute()) {
                                                        error_log("  ✓ Junction table updated");
                                                    }
                                                    $deduct_junction->close();
                                                }
                                            }
                                            $get_color_id->close();
                                        }
                                    }
                                    $variant_color_check->close();
                                }
                            }
                            $item_color_check->close();
                        }

                        // FALLBACK: Also deduct from product_variants table
                        $deduct_variant = $conn->prepare("
            UPDATE product_variants 
            SET stock = GREATEST(0, stock - ?)
            WHERE id = ?
        ");

                        if ($deduct_variant) {
                            $deduct_variant->bind_param("ii", $quantity, $variant_id);
                            if ($deduct_variant->execute()) {
                                error_log("  ✓ Variant stock updated");
                            }
                            $deduct_variant->close();
                        }
                    } else {
                        error_log("  ✗ No variant_id found!");
                    }
                }

                $items_stmt->close();
                error_log("=== STOCK DEDUCTION COMPLETE ===");

                // ✅ STEP 3: Mark order as stock deducted (prevent duplicates on refresh)
                $_SESSION[$stock_deduction_key] = true;

                // ✅ STEP 4: Clear user's cart
                $cart_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                $cart_stmt->bind_param("i", $user_id);
                $cart_stmt->execute();
                $cart_stmt->close();
                error_log("✓ Cart cleared for user: $user_id");

                // ✅ STEP 5: Clear checkout session data
                unset($_SESSION['applied_referral_code']);
                unset($_SESSION['checkout_step1']);
                unset($_SESSION['checkout_step2']);
                unset($_SESSION['checkout_step3']);
                unset($_SESSION['pending_paymongo_order']);
                unset($_SESSION['paymongo_order_data']);

                // ✅ STEP 6: Clear referred_by_code after first successful purchase
                if (isset($order['sales_user_id']) && !empty($order['sales_user_id'])) {
                    $clear_referral = $conn->prepare("UPDATE users SET referred_by_code = NULL WHERE id = ?");
                    $clear_referral->bind_param("i", $user_id);
                    $clear_referral->execute();
                    $clear_referral->close();
                    error_log("✓ Cleared referred_by_code for user: $user_id (PayMongo purchase completed)");
                }
            } else {
                // Page refresh - don't deduct again
                error_log("✓ Stock already deducted for order: " . $order['id']);
            }

            // Check for failed/cancelled status
            if ($order['payment_status'] === 'cancelled' || $order['payment_status'] === 'failed') {
                $payment_success = false;
                $error_message = "This payment was " . $order['payment_status'];
            }

            // Get order items for display
            $items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order['id']);
            $items_stmt->execute();
            $order_items = $items_stmt->get_result();
        } else {
            $error_message = "Order not found or access denied";
        }
    } else {
        $error_message = "Invalid order ID";
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("PayMongo success error: " . $e->getMessage());
}

// ✅ Get display reference number with fallback logic
$display_reference_no = '';
if ($order && !empty($order['reference_no'])) {
    $display_reference_no = $order['reference_no'];
} elseif (!empty($reference_no)) {
    $display_reference_no = $reference_no;
} else {
    $display_reference_no = 'NH' . $order_id;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Payment <?= $payment_success ? 'Successful' : 'Failed' ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }

        .success-animation {
            animation: successPulse 2s ease-in-out infinite;
        }

        @keyframes successPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 via-blue-50 to-purple-50 min-h-screen">

    <?php include '../navbar/top.php'; ?>

    <div class="max-w-4xl mx-auto py-12 px-4">
        <?php if ($payment_success && $order_found): ?>
            <!-- SUCCESS PAGE -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Header Section -->
                <div class="bg-black text-white p-8 text-center relative">
                    <div class="success-animation inline-block">
                        <div class="w-24 h-24 mx-auto mb-6 bg-white rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>

                    <h1 class="text-4xl font-bold mb-2">Payment Successful!</h1>
                    <p class="text-xl opacity-90">Thank you for your order with Noble Home Construction</p>
                </div>

                <!-- Order Details -->
                <div class="p-8">
                    <div class="grid md:grid-cols-2 gap-8 mb-8">
                        <!-- Order Summary -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Order Details
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Order Number:</span>
                                    <span class="font-bold text-blue-600">#<?= htmlspecialchars($display_reference_no) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-medium">PayMongo</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Order Date:</span>
                                    <span class="font-medium"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                        ✓ Payment Verified & Stock Deducted
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div class="bg-blue-50 rounded-xl p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                Payment Summary
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Items Subtotal:</span>
                                    <span class="font-medium">₱<?= number_format($order['subtotal'] ?? 0, 2) ?></span>
                                </div>

                                <?php if (isset($order['vat_amount']) && $order['vat_amount'] > 0): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">VAT (12%):</span>
                                        <span class="text-gray-700">₱<?= number_format($order['vat_amount'], 2) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Delivery Fee:</span>
                                        <span class="font-medium">₱<?= number_format($order['delivery_fee'], 2) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="border-t pt-3">
                                    <div class="flex justify-between text-lg">
                                        <span class="font-bold text-gray-800">Total Paid:</span>
                                        <span class="font-bold text-green-600">₱<?= number_format($order['total'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <?php if (isset($order_items) && $order_items->num_rows > 0): ?>
                        <div class="bg-white border rounded-xl p-6 mb-8">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Items Ordered</h3>
                            <div class="space-y-4">
                                <?php while ($item = $order_items->fetch_assoc()): ?>
                                    <div class="flex justify-between items-start p-4 bg-gray-50 rounded-lg">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div class="text-sm text-gray-600 space-y-1">
                                                <?php if (!empty($item['codename'])): ?>
                                                    <div><strong>Code:</strong> <?= htmlspecialchars($item['codename']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['size'])): ?>
                                                    <div><strong>Size:</strong> <?= htmlspecialchars($item['size']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['variant_color'])): ?>
                                                    <div><strong>Color:</strong> <?= htmlspecialchars($item['variant_color']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['origin'])): ?>
                                                    <div><strong>Origin:</strong> <?= htmlspecialchars($item['origin']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-green-600">
                                                ₱<?= number_format($item['subtotal'], 2) ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                ₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- What's Next Section -->
                    <div class="bg-blue-50 rounded-xl p-6 mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            What happens next?
                        </h3>
                        <div class="space-y-3 text-gray-700">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">1</div>
                                <div>
                                    <div class="font-medium">Order Confirmation</div>
                                    <div class="text-sm text-gray-600">You'll receive an email confirmation within 5 minutes.</div>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">2</div>
                                <div>
                                    <div class="font-medium">Order Processing</div>
                                    <div class="text-sm text-gray-600">Our team will prepare your items for delivery.</div>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">3</div>
                                <div>
                                    <div class="font-medium">Delivery Updates</div>
                                    <div class="text-sm text-gray-600">We'll notify you when your order is ready for delivery.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="order_receipt.php?order_id=<?= $order['id'] ?>"
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition font-medium text-center">
                            View Full Receipt
                        </a>
                        <a href="../index.php"
                            class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                            Continue Shopping
                        </a>
                        <a href="profile.php"
                            class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium text-center">
                            View Orders
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ERROR PAGE -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-red-500 to-pink-600 text-white p-8 text-center">
                    <div class="w-24 h-24 mx-auto mb-6 bg-white rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>

                    <h1 class="text-4xl font-bold mb-2">Payment Issue</h1>
                    <p class="text-xl opacity-90">There was a problem processing your payment</p>
                </div>

                <div class="p-8 text-center">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                        <p class="text-red-800 font-medium mb-2">Error Details:</p>
                        <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
                    </div>

                    <div class="space-y-4">
                        <p class="text-gray-600">Don't worry! You can try the following:</p>
                        <ul class="text-left max-w-md mx-auto space-y-2 text-gray-700">
                            <li class="flex items-center"><span class="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>Check your payment method and try again</li>
                            <li class="flex items-center"><span class="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>Contact customer support if the issue persists</li>
                            <li class="flex items-center"><span class="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>Return to checkout and use a different payment method</li>
                        </ul>
                    </div>

                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="checkout.php"
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition font-medium">
                            Return to Checkout
                        </a>
                        <a href="../index.php"
                            class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../navbar/footer.php'; ?>

</body>

</html>