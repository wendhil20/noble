<?php
// paymongo-success.php
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

$error = null;
$success_order = null;

// Get session ID from URL parameters
$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    $error = "Invalid payment session.";
} else {
    try {
        // Find the order by PayMongo session ID
        $stmt = $conn->prepare("SELECT * FROM orders WHERE paymongo_session_id = ? AND user_id = ?");
        $stmt->bind_param("si", $session_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            
            // Update order status to verified if it's still pending
            if ($order['payment_status'] === 'pending_paymongo') {
                $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'verified', updated_at = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $order['id']);
                
                if ($update_stmt->execute()) {
                    // Clear user's cart
                    $clear_cart_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                    $clear_cart_stmt->bind_param("i", $_SESSION['user_id']);
                    $clear_cart_stmt->execute();
                    $clear_cart_stmt->close();
                    
                    $success_order = $order;
                    $_SESSION['checkout_success'] = true;
                } else {
                    error_log("Failed to update order status for PayMongo payment: " . $update_stmt->error);
                    $error = "Failed to process payment confirmation.";
                }
                $update_stmt->close();
            } else {
                // Order already processed
                $success_order = $order;
            }
        } else {
            $error = "Payment session not found or invalid.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("PayMongo success page error: " . $e->getMessage());
        $error = "An error occurred while processing your payment.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title><?= $success_order ? 'Payment Successful' : 'Payment Error' ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 font-mono">

<?php include '../navbar/top.php'; ?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        
        <?php if ($success_order): ?>
            <!-- Success State -->
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100 mb-6">
                    <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <h2 class="text-3xl font-bold text-gray-900 mb-4">
                    Payment Successful!
                </h2>
                
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <div class="text-left space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order Number:</span>
                            <span class="font-bold text-gray-900"><?= htmlspecialchars($success_order['reference_no']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="font-bold text-green-600">₱<?= number_format($success_order['total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-medium">PayMongo</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Paid
                            </span>
                        </div>
                    </div>
                </div>
                
                <p class="text-gray-600 mb-8">
                    Thank you for your order! Your payment has been successfully processed. 
                    You will receive an order confirmation email shortly.
                </p>
                
                <div class="space-y-4">
                    <a href="order_receipt.php?order_id=<?= $success_order['id'] ?>" 
                       class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-medium inline-block text-center">
                        View Order Details
                    </a>
                    
                    <a href="../products/index.php" 
                       class="w-full bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-medium inline-block text-center">
                        Continue Shopping
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Error State -->
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-red-100 mb-6">
                    <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                
                <h2 class="text-3xl font-bold text-gray-900 mb-4">
                    Payment Error
                </h2>
                
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <div class="text-red-600">
                        <?= htmlspecialchars($error) ?>
                    </div>
                </div>
                
                <p class="text-gray-600 mb-8">
                    We encountered an issue processing your payment. Please try again or contact support if the problem persists.
                </p>
                
                <div class="space-y-4">
                    <a href="checkout.php" 
                       class="w-full bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 transition font-medium inline-block text-center">
                        Try Again
                    </a>
                    
                    <a href="../products/index.php" 
                       class="w-full bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-medium inline-block text-center">
                        Continue Shopping
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../navbar/footer.php'; ?>

</body>
</html>