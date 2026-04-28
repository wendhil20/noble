<?php
// checkout-paymongo-success-page-12-A.php
// Handles both PayMongo (session-based order creation) and QRPh (webhook creates order)
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// ✅ Restore session from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt  = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture']     = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: "'. BASE_URL . '/google-callback.php');
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_name    = $_SESSION['user_name'] ?? 'Guest';
$order_id     = intval($_GET['order_id'] ?? 0);
$reference_no = $_GET['ref'] ?? '';
$source       = $_GET['source'] ?? '';

$order_found     = false;
$payment_success = false;
$error_message   = '';
$order           = null;
$order_items     = null;

// Determine if this is a QRPh flow:
// QRPh = source=qrph, OR no pending_paymongo_order in session and no order_id in URL
$pending  = $_SESSION['pending_paymongo_order'] ?? null;
$is_qrph  = ($source === 'qrph' || ($order_id === 0 && !empty($reference_no) && empty($pending)));

try {

    // ================================================================
    // PATH A: QRPh — find order via reference_no (webhook creates it)
    // ================================================================
    if ($is_qrph && !empty($reference_no) && $order_id === 0) {

        $max_wait  = 15;
        $waited    = 0;
        $found_row = null;

        while ($waited <= $max_wait) {
            $s = $conn->prepare("SELECT id FROM orders WHERE reference_no = ? AND user_id = ? LIMIT 1");
            $s->bind_param("si", $reference_no, $user_id);
            $s->execute();
            $found_row = $s->get_result()->fetch_assoc();
            $s->close();

            if ($found_row) {
                $order_id = intval($found_row['id']);
                break;
            }

            sleep(2);
            $waited += 2;
        }

        if ($order_id === 0) {
            $show_waiting = true;
        }
    }

    // ================================================================
    // PATH B: PayMongo — create order from session data after payment confirmed
    // ================================================================
    if (!$is_qrph && !empty($reference_no) && $pending && ($pending['reference_no'] === $reference_no)) {

        $session_id = $pending['session_id'] ?? null;

        if (empty($session_id)) {
            throw new Exception("Missing PayMongo session ID.");
        }

        // ── Duplicate guard: check if order already exists ──
        $dup = $conn->prepare("SELECT id FROM orders WHERE reference_no = ? LIMIT 1");
        $dup->bind_param("s", $reference_no);
        $dup->execute();
        $dup_row = $dup->get_result()->fetch_assoc();
        $dup->close();

        if ($dup_row) {
            // Order already inserted (page refresh) — just use it
            $order_id = intval($dup_row['id']);
            unset($_SESSION['pending_paymongo_order']);
            error_log("PayMongo order already exists: ref=$reference_no order_id=$order_id");

        } else {
            // ── Verify payment with PayMongo API ──
            require_once ROOT_PATH . '/.env.php';
            $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');

            $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/" . urlencode($session_id));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic " . base64_encode($secretKey . ":")
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $pm_response = curl_exec($ch);
            $pm_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($pm_http !== 200) {
                error_log("PayMongo verify failed HTTP $pm_http for session $session_id");
                throw new Exception("Payment verification failed. Please contact support with ref: $reference_no");
            }

            $pm_data = json_decode($pm_response, true);

            // ── Check if actually paid ──
            $is_paid  = false;
            $payments = $pm_data['data']['attributes']['payments'] ?? [];
            foreach ($payments as $payment) {
                if (($payment['attributes']['status'] ?? '') === 'paid') {
                    $is_paid = true;
                    break;
                }
            }
            if (!$is_paid && ($pm_data['data']['attributes']['payment_intent']['attributes']['status'] ?? '') === 'succeeded') {
                $is_paid = true;
            }

            if (!$is_paid) {
                error_log("Payment not confirmed for session $session_id | ref $reference_no");
                unset($_SESSION['pending_paymongo_order']);
                header("Location: " . BASE_URL . "/checkout?payment_pending=1&ref=" . urlencode($reference_no));
                exit;
            }

            // ── Payment confirmed — INSERT order now ──
            $amt                     = floatval($pending['amount']);
            $delivery_fee            = floatval($pending['delivery_fee']);
            $items_without_vat       = floatval($pending['subtotal']);
            $vat_amount              = floatval($pending['vat_amount']);
            $discount_amount         = floatval($pending['discount_amount']);
            $customer_name           = $pending['customer_name'];
            $email                   = $pending['email'];
            $mobile                  = $pending['mobile'];
            $address                 = $pending['address'];
            $zipcode                 = $pending['zipcode'];
            $billing_address_id      = $pending['billing_address_id'];
            $latitude                = $pending['latitude'];
            $longitude               = $pending['longitude'];
            $delivery_distance       = floatval($pending['delivery_distance']);
            $delivery_type           = $pending['delivery_type'];
            $assigned_vehicle_id     = $pending['assigned_vehicle_id'];
            $assigned_vehicle_type   = $pending['assigned_vehicle_type'];
            $total_cubic_meters      = floatval($pending['total_cubic_meters']);
            $total_weight_kg         = floatval($pending['total_weight_kg']);
            $total_width             = floatval($pending['total_width']);
            $total_height            = floatval($pending['total_height']);
            $total_length            = floatval($pending['total_length']);
            $sales_referral_code     = $pending['sales_referral_code'];
            $sales_commission_rate   = floatval($pending['sales_commission_rate']);
            $sales_commission_amount = floatval($pending['sales_commission_amount']);
            $sales_user_id           = $pending['sales_user_id'];
            $cart_items              = $pending['cart_items'];
            $payment_method          = 'PayMongo';
            $payment_status_db       = 'paid';
            $order_status            = 'Pending';

            $ins = $conn->prepare("INSERT INTO orders (
                user_id, customer_name, email, mobile, address, zipcode,
                subtotal, delivery_fee, total, vat_amount, discount,
                mode_payment, payment_status, reference_no, status,
                delivery_type, assigned_vehicle_id, assigned_vehicle_type,
                total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
                latitude, longitude, billing_address_id, delivery_distance,
                sales_referral_code, sales_commission_rate, sales_commission_amount, sales_user_id,
                paymongo_session_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$ins) {
                throw new Exception("DB prepare failed: " . $conn->error);
            }

            $ins->bind_param(
                "isssssdddddsssssissddddddidsdds",
                $user_id, $customer_name, $email, $mobile, $address, $zipcode,
                $items_without_vat, $delivery_fee, $amt, $vat_amount, $discount_amount,
                $payment_method, $payment_status_db, $reference_no, $order_status,
                $delivery_type, $assigned_vehicle_id, $assigned_vehicle_type,
                $total_cubic_meters, $total_weight_kg, $total_width, $total_height, $total_length,
                $latitude, $longitude, $billing_address_id, $delivery_distance,
                $sales_referral_code, $sales_commission_rate, $sales_commission_amount, $sales_user_id,
                $session_id
            );

            if (!$ins->execute()) {
                throw new Exception("DB insert failed: " . $ins->error);
            }

            $order_id = $conn->insert_id;
            $ins->close();
            error_log("✓ PayMongo order created: ID=$order_id | ref=$reference_no (payment verified)");

            // ── Insert order items ──
            $item_stmt = $conn->prepare("INSERT INTO order_items (
                order_id, product_id, variant_id, color_id, product_name, codename, type_name,
                variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($item_stmt) {
                foreach ($cart_items as $item) {
                    $item_subtotal = floatval($item['price']) * intval($item['quantity']);
                    $variant_id    = !empty($item['variant_id']) ? intval($item['variant_id']) : null;
                    $color_id      = !empty($item['color_id'])   ? intval($item['color_id'])   : null;
                    $product_id    = intval($item['product_id']);
                    $product_name  = !empty($item['product_name']) ? $item['product_name'] : 'Product';
                    $color         = $item['color_name']  ?? $item['variant_color'] ?? '';
                    $codename      = $item['codename']    ?? '';
                    $type_name     = $item['type_name']   ?? '';
                    $size          = $item['size']        ?? '';
                    $descrip6      = $item['descrip6']    ?? '';
                    $descrip7      = $item['descrip7']    ?? '';
                    $origin        = $item['origin']      ?? '';

                    $item_stmt->bind_param(
                        "iiiisssssdsisss",
                        $order_id, $product_id, $variant_id, $color_id,
                        $product_name, $codename, $type_name, $color, $size,
                        $item['price'], $item['quantity'], $item_subtotal,
                        $descrip6, $descrip7, $origin
                    );
                    if (!$item_stmt->execute()) {
                        error_log("Warning: order item insert failed: " . $item_stmt->error);
                    }
                }
                $item_stmt->close();
                error_log("✓ Order items inserted for order ID=$order_id");
            }

            // ── Clear pending session after successful order creation ──
            unset($_SESSION['pending_paymongo_order']);
        }
    }

    // ================================================================
    // FETCH ORDER for display (both paths arrive here with $order_id)
    // ================================================================
    if ($order_id > 0) {
        $stmt = $conn->prepare("
            SELECT * FROM orders
            WHERE id = ? AND user_id = ? AND mode_payment IN ('PayMongo', 'QR Ph')
        ");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $order       = $result->fetch_assoc();
            $order_found = true;
        }
        $stmt->close();

        if ($order_found) {
            $payment_success     = true;
            $stock_deduction_key = 'stock_deducted_' . $order['id'];
            $is_mode_paymongo    = ($order['mode_payment'] === 'PayMongo');

            // ── Stock deduction (PayMongo only; QRPh webhook handles it) ──
            if ($is_mode_paymongo && !isset($_SESSION[$stock_deduction_key])) {
                error_log("=== STOCK DEDUCTION (PayMongo) order #" . $order['id'] . " ===");

                $items_stmt = $conn->prepare("SELECT id, variant_id, quantity FROM order_items WHERE order_id = ?");
                $items_stmt->bind_param("i", $order['id']);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();

                while ($item = $items_result->fetch_assoc()) {
                    $variant_id = $item['variant_id'];
                    $quantity   = $item['quantity'];

                    if (!empty($variant_id)) {
                        $cc = $conn->prepare("SELECT color_id FROM order_items WHERE id = ? LIMIT 1");
                        $cc->bind_param("i", $item['id']);
                        $cc->execute();
                        $cc_row   = $cc->get_result()->fetch_assoc();
                        $cc->close();
                        $color_id = $cc_row['color_id'] ?? null;

                        if (!empty($color_id)) {
                            $dj = $conn->prepare("
                                UPDATE product_variant_colors
                                SET stock_quantity = GREATEST(0, stock_quantity - ?)
                                WHERE variant_id = ? AND color_id = ?
                            ");
                            $dj->bind_param("iii", $quantity, $variant_id, $color_id);
                            $dj->execute();
                            $dj->close();
                        }

                        $dv = $conn->prepare("UPDATE product_variants SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                        $dv->bind_param("ii", $quantity, $variant_id);
                        $dv->execute();
                        $dv->close();
                    }
                }
                $items_stmt->close();

                $_SESSION[$stock_deduction_key] = true;

                // Clear cart
                $cs = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                $cs->bind_param("i", $user_id);
                $cs->execute();
                $cs->close();

                // Clear checkout session data
                unset($_SESSION['applied_referral_code']);
                unset($_SESSION['checkout_step1']);
                unset($_SESSION['checkout_step2']);
                unset($_SESSION['checkout_step3']);
                unset($_SESSION['pending_paymongo_order']);
                unset($_SESSION['paymongo_order_data']);

                // Clear referral code after purchase
                if (!empty($order['sales_user_id'])) {
                    $cr = $conn->prepare("UPDATE users SET referred_by_code = NULL WHERE id = ?");
                    $cr->bind_param("i", $user_id);
                    $cr->execute();
                    $cr->close();
                }

                error_log("=== STOCK DEDUCTION COMPLETE (PayMongo) order #" . $order['id'] . " ===");

            } elseif ($order['mode_payment'] === 'QR Ph' && !isset($_SESSION[$stock_deduction_key])) {
                // QRPh — webhook already handled stock, just clean up session
                unset($_SESSION['applied_referral_code']);
                unset($_SESSION['checkout_step1']);
                unset($_SESSION['checkout_step2']);
                unset($_SESSION['checkout_step3']);
                unset($_SESSION['pending_paymongo_order']);
                $_SESSION[$stock_deduction_key] = true;
                error_log("QRPh order #" . $order['id'] . " — session cleared (stock done by webhook)");
            }

            if (in_array($order['payment_status'], ['cancelled', 'failed'])) {
                $payment_success = false;
                $error_message   = "This payment was " . $order['payment_status'];
            }

            // Fetch order items for display
            $items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order['id']);
            $items_stmt->execute();
            $order_items = $items_stmt->get_result();

        } else {
            $error_message = "Order not found or access denied";
        }

    } elseif (!isset($show_waiting)) {
        $error_message = "Invalid order ID";
    }

} catch (Exception $e) {
    $error_message = "An error occurred: " . $e->getMessage();
    error_log("Success page error: " . $e->getMessage());
}

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
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <title>Payment <?= isset($show_waiting) ? 'Processing' : ($payment_success ? 'Successful' : 'Failed') ?> - Noble Home</title>
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .success-animation { animation: successPulse 2s ease-in-out infinite; }
        @keyframes successPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
    </style>
</head>
<body class="bg-white min-h-screen">



<div class="max-w-4xl mx-auto py-12 px-4">

<?php if (isset($show_waiting)): ?>
    <!-- ── WAITING PAGE (QRPh webhook still processing) ── -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden text-center p-12">
        <div class="w-24 h-24 mx-auto mb-6 bg-blue-100 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-blue-500 spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-3">Confirming Your Payment...</h1>
        <p class="text-gray-500 mb-2">Your QR Ph payment was received.</p>
        <p class="text-gray-500 mb-8">Please wait while we confirm your order.</p>
        <div class="bg-blue-50 rounded-lg p-4 mb-8 inline-block">
            <span class="text-blue-700 font-medium">Reference: <?= htmlspecialchars($reference_no) ?></span>
        </div>
        <p class="text-sm text-gray-400">Checking again in <span id="secs">5</span> seconds...</p>
    </div>
    <script>
        let secs = 5;
        const el = document.getElementById('secs');
        const interval = setInterval(() => {
            secs--;
            el.textContent = secs;
            if (secs <= 0) { clearInterval(interval); window.location.reload(); }
        }, 1000);
    </script>

<?php elseif ($payment_success && $order_found): ?>
    <!-- ── SUCCESS PAGE ── -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-black text-white p-8 text-center">
            <div class="success-animation inline-block">
                <div class="w-24 h-24 mx-auto mb-6 bg-white rounded-full flex items-center justify-center">
                    <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
            <h1 class="text-4xl font-bold mb-2">Payment Successful!</h1>
            <p class="text-xl opacity-90">Thank you for your order with Noble Home Construction</p>
        </div>

        <div class="p-8">
            <div class="grid md:grid-cols-2 gap-8 mb-8">
                <div class="bg-gray-50 rounded-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
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
                            <span class="font-medium"><?= htmlspecialchars($order['mode_payment']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order Date:</span>
                            <span class="font-medium"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                ✓ Payment Confirmed
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                        Payment Summary
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Items Subtotal:</span>
                            <span class="font-medium">₱<?= number_format($order['subtotal'] ?? 0, 2) ?></span>
                        </div>
                        <?php if (!empty($order['vat_amount']) && $order['vat_amount'] > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">VAT (12%):</span>
                            <span class="text-gray-700">₱<?= number_format($order['vat_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
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
                            <div class="font-bold text-green-600">₱<?= number_format($item['subtotal'], 2) ?></div>
                            <div class="text-sm text-gray-600">₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-blue-50 rounded-xl p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">What happens next?</h3>
                <div class="space-y-3 text-gray-700">
                    <div class="flex items-start">
                        <div class="shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">1</div>
                        <div><div class="font-medium">Order Confirmation</div><div class="text-sm text-gray-600">You'll receive an email confirmation within 5 minutes.</div></div>
                    </div>
                    <div class="flex items-start">
                        <div class="shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">2</div>
                        <div><div class="font-medium">Order Processing</div><div class="text-sm text-gray-600">Our team will prepare your items for delivery.</div></div>
                    </div>
                    <div class="flex items-start">
                        <div class="shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-1">3</div>
                        <div><div class="font-medium">Delivery Updates</div><div class="text-sm text-gray-600">We'll notify you when your order is ready for delivery.</div></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= BASE_URL ?>/shop"
                    class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                    Continue Shopping
                </a>
                <a href="<?= BASE_URL ?>/order"
                    class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium text-center">
                    View Orders
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ── ERROR PAGE ── -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-red-500 text-white p-8 text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-white rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
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
            <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                <a href="index-checkout-page-12.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition font-medium text-center">
                    Return to Checkout
                </a>
                <a href="../index.php" class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>

<?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
</body>
</html>