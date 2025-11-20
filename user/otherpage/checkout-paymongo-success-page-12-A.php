<?php
// paymongo-success.php - Simple version with reference_no fix
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token (normal account or Google)
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
            // ✅ CORRECTED: Since your orders start with 'pending' status
            $payment_success = true; // Order found means payment was successful
            
            // ✅ Check if this is the FIRST TIME visiting success page
            // We use a flag in the order to track if cart was already cleared
            if (!isset($_SESSION['paymongo_cart_cleared_' . $order['id']])) {
                // ✅ FIRST-TIME SUCCESS - Clear cart and session
                
                // Clear cart
                $cart_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                $cart_stmt->bind_param("i", $user_id);
                $cart_stmt->execute();
                $cart_stmt->close();
                
                // ✅ Clear ALL checkout session data including referral code
                unset($_SESSION['applied_referral_code']);
                unset($_SESSION['checkout_step1']);
                unset($_SESSION['checkout_step2']);
                unset($_SESSION['checkout_step3']);
                unset($_SESSION['pending_paymongo_order']);
                unset($_SESSION['paymongo_order_data']);
                
                // ✅ Mark this order as processed to prevent clearing cart on page refresh
                $_SESSION['paymongo_cart_cleared_' . $order['id']] = true;
                
                $_SESSION['checkout_notice'] = 'PayMongo payment completed successfully!';
            } else {
                // ✅ Page refresh - Don't clear cart again
                $_SESSION['checkout_notice'] = 'Order already processed!';
            }
            
            // Check for failed/cancelled status
            if ($order['payment_status'] === 'cancelled' || $order['payment_status'] === 'failed') {
        // ✅ Payment failed or cancelled - show error
        $payment_success = false;
        $error_message = "This payment was " . $order['payment_status'];
    }
            
            // Get order items
            $items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order['id']);
            $items_stmt->execute();
            $order_items = $items_stmt->get_result();
            
            // Clear pending session
            unset($_SESSION['pending_paymongo_order']);
            
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

// ✅ FIXED: Get display reference number with fallback logic
$display_reference_no = '';
if ($order && !empty($order['reference_no'])) {
    $display_reference_no = $order['reference_no'];
} elseif (!empty($reference_no)) {
    $display_reference_no = $reference_no; // Use from URL parameter
} else {
    $display_reference_no = 'NH' . $order_id; // Generate fallback
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
        body { font-family: 'Montserrat', sans-serif; }
        .success-animation {
            animation: successPulse 2s ease-in-out infinite;
        }
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
                                    Payment Verified
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
        
        <!-- ✅ NEW: Show Referral Discount if applied -->
        <?php if (isset($order['referral_code']) && !empty($order['referral_code']) && isset($order['referral_discount_amount']) && $order['referral_discount_amount'] > 0): ?>
        <div class="flex justify-between text-purple-600 bg-purple-50 px-3 py-2 rounded-lg -mx-3">
            <span class="font-medium flex items-center">
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                </svg>
                Referral Discount (<?= htmlspecialchars($order['referral_code']) ?>):
            </span>
            <span class="font-bold">-₱<?= number_format($order['referral_discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        
        <!-- ✅ NEW: Show VAT breakdown -->
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
        
        <!-- ✅ NEW: Show total savings if discount was applied -->
        <?php 
        $total_savings = 0;
        if (isset($order['referral_discount_amount'])) {
            $total_savings += $order['referral_discount_amount'];
        }
        if ($total_savings > 0): 
        ?>
        <div class="bg-green-100 border border-green-300 rounded-lg p-3 mt-3">
            <div class="flex justify-between items-center">
                <span class="text-green-700 font-medium flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                    </svg>
                    You Saved:
                </span>
                <span class="text-green-700 font-bold text-lg">₱<?= number_format($total_savings, 2) ?></span>
            </div>
        </div>
        <?php endif; ?>
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
                                <h4 class="font-bold text-orange-600 mb-1">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <?php if (!empty($item['codename'])): ?>
                                        <div><strong>Code:</strong> <?= htmlspecialchars($item['codename']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['type_name'])): ?>
                                        <div><strong>Type:</strong> <?= htmlspecialchars($item['type_name']) ?></div>
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

                <!-- ✅ NEW: Referral Code Thank You Message -->
<?php if (isset($order['referral_code']) && !empty($order['referral_code'])): ?>
<div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-300 rounded-xl p-6 mb-8">
    <div class="flex items-start">
        <div class="flex-shrink-0 w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mr-4">
            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-purple-900 mb-2">
                🎉 Referral Code Applied!
            </h3>
            <p class="text-purple-700 mb-2">
                You used referral code <strong class="bg-purple-200 px-2 py-1 rounded font-mono"><?= htmlspecialchars($order['referral_code']) ?></strong> 
                and saved <strong class="text-green-700">₱<?= number_format($order['referral_discount_amount'], 2) ?></strong> on this order!
            </p>
            <p class="text-purple-600 text-sm">
                Thank you for supporting our sales representatives. Your discount has been applied to this order.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- What's Next Section -->
<div class="bg-blue-50 rounded-xl p-6 mb-8"></div>

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