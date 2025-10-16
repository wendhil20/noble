<?php
// paypal-success.php - Handle PayPal payment completion
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
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

// PayPal config
$paypal_config = [
    'mode' => 'sandbox',
    'client_id' => 'AT1LmhSbRH3yOGHNRFYZb_WhRkFIUlsdUEIQcNNr_0BXnb6LapA61CTycE7xq0c5W6XrHMpetIfpP-Kd',
    'client_secret' => 'EHkB3XnpMB-mjaw8VeOmWR9dmDoDoIZwLwBoEvWdabiGfgd2kTb6VYfOq4WvuJVEUfVaOmm3rBMfS-QT',
    'currency' => 'PHP'
];

function getPayPalAccessToken($config) {
    $url = $config['mode'] === 'sandbox' 
        ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api-m.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US',
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("PayPal access token cURL error: " . $error);
        return false;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function capturePayPalPayment($order_id, $config) {
    $url = $config['mode'] === 'sandbox' 
        ? "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$order_id}/capture"
        : "https://api-m.paypal.com/v2/checkout/orders/{$order_id}/capture";
    
    $access_token = getPayPalAccessToken($config);
    if (!$access_token) {
        error_log("Failed to get PayPal access token for capture");
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'Prefer: return=representation'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("PayPal capture cURL error: " . $error);
        return false;
    }
    
    error_log("PayPal capture response: " . $response);
    error_log("PayPal capture HTTP code: " . $http_code);
    
    if ($http_code === 201) {
        return json_decode($response, true);
    }
    
    return false;
}

$error = null;
$success = false;
$order_id = null;
$debug_info = [];

// Get PayPal parameters
$paypal_order_id = $_GET['token'] ?? null;
$payer_id = $_GET['PayerID'] ?? null;

// ✅ DEBUGGING: Log all available data
$debug_info['paypal_order_id'] = $paypal_order_id;
$debug_info['payer_id'] = $payer_id;
$debug_info['session_pending_order'] = $_SESSION['pending_paypal_order'] ?? 'NOT SET';
$debug_info['user_id'] = $user_id;

if (!$paypal_order_id) {
    $error = "Invalid PayPal response. Missing order token.";
} else {
    try {
        // ✅ ENHANCED: Multiple strategies to find the order
        $order_data = null;
        $pending_order_id = null;
        
        // Strategy 1: Use session data (preferred)
        if (isset($_SESSION['pending_paypal_order'])) {
            $pending_order_id = $_SESSION['pending_paypal_order'];
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND paypal_order_id = ? AND user_id = ?");
            $stmt->bind_param("isi", $pending_order_id, $paypal_order_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $order_data = $result->fetch_assoc();
                $debug_info['strategy'] = 'session_match';
            }
            $stmt->close();
        }
        
        // Strategy 2: Find by PayPal Order ID and user (if session strategy failed)
        if (!$order_data) {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE paypal_order_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("si", $paypal_order_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $order_data = $result->fetch_assoc();
                $pending_order_id = $order_data['id'];
                $debug_info['strategy'] = 'paypal_id_match';
            }
            $stmt->close();
        }
        
        // Strategy 3: Find recent pending PayPal order for user (last resort)
        if (!$order_data) {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? AND payment_status = 'pending_paypal' ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $order_data = $result->fetch_assoc();
                $pending_order_id = $order_data['id'];
                $debug_info['strategy'] = 'recent_pending_match';
                
                // Update the PayPal order ID in database if it was missing
                $stmt2 = $conn->prepare("UPDATE orders SET paypal_order_id = ? WHERE id = ?");
                $stmt2->bind_param("si", $paypal_order_id, $pending_order_id);
                $stmt2->execute();
                $stmt2->close();
            }
            $stmt->close();
        }
        
        $debug_info['found_order_id'] = $pending_order_id ?? null;
        $debug_info['found_order_paypal_id'] = $order_data['paypal_order_id'] ?? 'NOT SET';
        
        if (!$order_data || !$pending_order_id) {
            // ✅ DEBUGGING: Show what orders exist for this user
            $stmt = $conn->prepare("SELECT id, paypal_order_id, payment_status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $debug_orders = [];
            while ($row = $result->fetch_assoc()) {
                $debug_orders[] = $row;
            }
            $debug_info['recent_orders'] = $debug_orders;
            $stmt->close();
            
            throw new Exception("No matching PayPal order found. PayPal ID: " . $paypal_order_id . " | Session Order: " . ($pending_order_id ?? 'NONE'));
        }
        
        // ✅ Check if order is already completed
        if ($order_data['payment_status'] === 'pending') {
            $success = true;
            $order_id = $pending_order_id;
            $_SESSION['checkout_notice'] = 'Order already completed!';
        } else {
            // Capture the PayPal payment
            $capture_result = capturePayPalPayment($paypal_order_id, $paypal_config);
            
            if (!$capture_result) {
                throw new Exception("Failed to capture PayPal payment. Please contact support if money was deducted.");
            }
            
            // Check if capture was successful
            $capture_status = $capture_result['status'] ?? '';
            if ($capture_status !== 'COMPLETED') {
                throw new Exception("PayPal payment was not completed. Status: " . $capture_status);
            }
            
            // Get transaction details
            $capture_id = null;
            $payer_email = null;
            $payer_name = null;
            
            if (isset($capture_result['purchase_units'][0]['payments']['captures'][0])) {
                $capture = $capture_result['purchase_units'][0]['payments']['captures'][0];
                $capture_id = $capture['id'];
            }
            
            if (isset($capture_result['payer']['email_address'])) {
                $payer_email = $capture_result['payer']['email_address'];
            }
            
            if (isset($capture_result['payer']['name'])) {
                $payer_name = $capture_result['payer']['name']['given_name'] . ' ' . $capture_result['payer']['name']['surname'];
            }
            
            // ✅ Update order status to completed
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'pending', paypal_capture_id = ?, paypal_payer_email = ?, paypal_payer_name = ? WHERE id = ?");
            $stmt->bind_param("sssi", $capture_id, $payer_email, $payer_name, $pending_order_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update order status: " . $stmt->error);
            }
            $stmt->close();
            
            // ✅ Clear cart
            $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            $success = true;
            $order_id = $pending_order_id;
            $_SESSION['checkout_notice'] = 'PayPal payment completed successfully!';
        }
        
        // ✅ Clear session
        unset($_SESSION['pending_paypal_order']);
        
        // ✅ Log successful transaction
        error_log("PayPal payment completed successfully - Order ID: " . $order_id . ", Strategy: " . $debug_info['strategy']);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("PayPal success handler error: " . $e->getMessage());
        error_log("Debug info: " . json_encode($debug_info));
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment <?= $success ? 'Successful' : 'Failed' ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .pulse-success { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-2xl mx-auto p-4 mt-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="bg-green-50 border-l-4 border-green-400 p-6">
                    <div class="flex items-center">
                        <div class="text-green-600 mr-4 pulse-success">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-green-800 mb-2">Payment Successful!</h1>
                            <p class="text-green-700">Your PayPal payment has been processed successfully.</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-2">Order Details</h3>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div><strong>Order ID:</strong> #<?= htmlspecialchars($order_id) ?></div>
                                <div><strong>Payment Method:</strong> PayPal</div>
                                <div><strong>Status:</strong> <span class="text-green-600 font-medium">Completed</span></div>
                                <div><strong>Transaction ID:</strong> <?= htmlspecialchars($paypal_order_id) ?></div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-800 mb-2">What's Next?</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• You will receive an order confirmation email shortly</li>
                                <li>• Your order will be processed and shipped within 1-3 business days</li>
                                <li>• You can track your order status in your account</li>
                            </ul>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="order_receipt.php?order_id=<?= $order_id ?>" 
                               class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-medium text-center">
                                View Order Receipt
                            </a>
                            <a href="index.php" 
                               class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Error State -->
                <div class="bg-red-50 border-l-4 border-red-400 p-6">
                    <div class="flex items-center">
                        <div class="text-red-600 mr-4">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-red-800 mb-2">Payment Failed</h1>
                            <p class="text-red-700">There was an issue processing your PayPal payment.</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-2">Error Details</h3>
                            <p class="text-sm text-red-600"><?= htmlspecialchars($error) ?></p>
                        </div>
                        
                        <?php if (!empty($debug_info) && isset($_GET['debug'])): ?>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <h4 class="font-medium text-yellow-800 mb-2">Debug Information</h4>
                            <pre class="text-xs text-yellow-700 overflow-auto max-h-48"><?= htmlspecialchars(json_encode($debug_info, JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                        <?php endif; ?>
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-800 mb-2">Need Help?</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• Check your PayPal account to see if payment was processed</li>
                                <li>• Contact our support team if you were charged</li>
                                <li>• Try using a different payment method</li>
                            </ul>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="checkout.php" 
                               class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-medium text-center">
                                Try Again
                            </a>
                            <a href="index.php" 
                               class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-medium text-center">
                                Return to Home
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>