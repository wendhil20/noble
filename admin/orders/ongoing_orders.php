<?php
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

// Handle order completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'], $_POST['complete_order_id'])) {
    $orderId = (int) $_POST['complete_order_id'];
    if ($orderId > 0) {
        $order_result = $conn->query("SELECT * FROM orders WHERE id = $orderId");
        if ($order_result && $order_result->num_rows > 0) {
            $order_data = $order_result->fetch_assoc();
            $email = $order_data['email'];
            $name = $order_data['customer_name'];
            $finalTotal = floatval($order_data['final_total']);

            // Update order status to completed
            $conn->query("UPDATE orders SET status='Completed', completed_at=NOW() WHERE id=$orderId");
            
            // Update client status
            $conn->query("UPDATE client_info SET status='Completed' WHERE email='$email'");

            // Send completion email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noblehomeconst.ph@gmail.com';
                $mail->Password = 'icup vicc amrv xbxh';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('no-reply@yourdomain.com', 'NobleHome Orders');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = "Order #$orderId Completed - Thank You!";

                $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2 style='color: #10b981;'>🎉 Order #$orderId Completed!</h2>
                    <p>Dear <strong>$name</strong>,</p>
                    <p>Great news! Your order has been successfully completed and is ready for pickup/delivery.</p>
                    <div style='background-color: #f0fdf4; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #10b981; margin-bottom: 10px;'>Order Summary:</h3>
                        <p><strong>Order ID:</strong> #$orderId</p>
                        <p><strong>Total Amount:</strong> ₱" . number_format($finalTotal, 2) . "</p>
                        <p><strong>Status:</strong> <span style='color: #10b981;'>Completed</span></p>
                    </div>
                    <p>Thank you for choosing <strong style='color:#ea580c;'>NobleHome</strong>! We hope you enjoy your purchase.</p>
                    <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
                    <p>Best regards,<br><strong style='color:#ea580c;'>NobleHome</strong> Team</p>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                error_log("Completion Mailer Error: {$mail->ErrorInfo}");
            }

            echo "<script>
              alert('Order #$orderId has been completed successfully!');
              window.location.href='orders.php?tab=ongoing';
            </script>";
            exit;
        }
    }
}

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'], $_POST['payment_order_id'])) {
    $orderId = (int) $_POST['payment_order_id'];
    if ($orderId > 0) {
        $order_result = $conn->query("SELECT * FROM orders WHERE id = $orderId");
        if ($order_result && $order_result->num_rows > 0) {
            $order_data = $order_result->fetch_assoc();
            $email = $order_data['email'];
            $name = $order_data['customer_name'];

            // Update payment status
            $conn->query("UPDATE orders SET payment_status='Confirmed', payment_confirmed_at=NOW() WHERE id=$orderId");

            // Send payment confirmation email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'wendhil10@gmail.com';
                $mail->Password = 'tnjqjsuopqlwzoug';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('no-reply@yourdomain.com', 'NobleHome Orders');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = "Payment Confirmed - Order #$orderId";

                $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2 style='color: #10b981;'>✅ Payment Confirmed</h2>
                    <p>Dear <strong>$name</strong>,</p>
                    <p>We have successfully received and confirmed your payment for Order #$orderId.</p>
                    <div style='background-color: #f0fdf4; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p><strong>Payment Status:</strong> <span style='color: #10b981;'>Confirmed</span></p>
                        <p><strong>Next Step:</strong> Your order is now being processed for completion.</p>
                    </div>
                    <p>Thank you for your prompt payment. We'll notify you once your order is ready.</p>
                    <p>Best regards,<br><strong style='color:#ea580c;'>NobleHome</strong> Team</p>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                error_log("Payment Confirmation Mailer Error: {$mail->ErrorInfo}");
            }

            echo "<script>
              alert('Payment confirmed successfully for Order #$orderId!');
              window.location.href='orders.php?tab=ongoing';
            </script>";
            exit;
        }
    }
}

// Get ongoing orders
$ongoingOrders = $conn->query("SELECT * FROM orders WHERE status = 'Ongoing' ORDER BY created_at DESC");
?>

<div class="p-6">
    <h2 class="text-2xl font-bold text-green-700 mb-6 flex items-center">
        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Ongoing Orders
    </h2>
    
    <div class="space-y-4">
        <?php if ($ongoingOrders->num_rows > 0): ?>
            <?php while ($order = $ongoingOrders->fetch_assoc()): ?>
                <?php renderOngoingOrderCard($order, $conn); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-gray-500 text-lg">No ongoing orders found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    
function viewBilling(orderId) {
    window.open('billing.php?order_id=' + orderId, '_blank');
}

function confirmPayment(button) {
    if (confirm('Are you sure you want to confirm this payment?')) {
        button.innerHTML = '<span class="flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Confirming...</span>';
        button.disabled = true;
        return true;
    }
    return false;
}

function completeOrder(button) {
    if (confirm('Are you sure you want to mark this order as completed?')) {
        button.innerHTML = '<span class="flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Completing...</span>';
        button.disabled = true;
        return true;
    }
    return false;
}
</script>

<?php
// Function to render ongoing order cards
function renderOngoingOrderCard($order, $conn) {
    $orderId = $order['id'];
    $customerName = htmlspecialchars($order['customer_name']);
    $email = htmlspecialchars($order['email']);
    $mobile = htmlspecialchars($order['mobile']);
    $address = htmlspecialchars($order['address']);
    $zipcode = htmlspecialchars($order['zipcode']);
    $originalTotal = floatval($order['total']);
    $finalTotal = floatval($order['final_total']);
    $discount = floatval($order['discount'] ?? 0);
    $shippingFee = floatval($order['shipping_fee'] ?? 0);
    $deliveryFee = floatval($order['delivery_fee'] ?? 0);
    $vatAmount = floatval($order['vat_amount'] ?? 0);
    $paymentStatus = $order['payment_status'] ?? 'Pending';
    $modePayment = htmlspecialchars($order['mode_payment'] ?? 'N/A');
    $createdAt = date('M j, Y g:i A', strtotime($order['created_at']));
    
    // Get order items
    $items_result = $conn->query("SELECT * FROM order_items WHERE order_id = $orderId");
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    echo '<div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow duration-200">';
    
    // Order Header
    echo '<div class="flex justify-between items-start mb-4">';
    echo '<div>';
    echo '<h3 class="text-xl font-semibold text-gray-900">Order #' . $orderId . '</h3>';
    echo '<p class="text-sm text-gray-600">' . $createdAt . '</p>';
    echo '</div>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ongoing</span>';
    
    // Payment Status Badge
    if ($paymentStatus === 'Confirmed') {
        echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Payment Confirmed</span>';
    } else {
        echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Payment Pending</span>';
    }

    // Mode of Payment badge
    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Mode: ' . $modePayment . '</span>';
    echo '</div>';
    echo '</div>';
    
    // Customer Information
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
    echo '<div>';
    echo '<h4 class="font-medium text-gray-900 mb-2">Customer Information</h4>';
    echo '<div class="text-sm text-gray-600 space-y-1">';
    echo '<p><strong>Name:</strong> ' . $customerName . '</p>';
    echo '<p><strong>Email:</strong> ' . $email . '</p>';
    echo '<p><strong>Mobile:</strong> ' . $mobile . '</p>';
    echo '<p><strong>Address:</strong> ' . $address . '</p>';
    echo '<p><strong>Zipcode:</strong> ' . $zipcode . '</p>';
    echo '<p><strong>Mode of Payment:</strong> ' . $modePayment . '</p>';
    echo '</div>';
    echo '</div>';
    
    // Order Items
    echo '<div>';
    echo '<h4 class="font-medium text-gray-900 mb-2">Order Items</h4>';
    echo '<div class="bg-white border border-gray-200 rounded-md divide-y divide-gray-200 max-h-80 overflow-y-auto">';
    foreach ($items as $item) {
        $itemName = '';
        if (isset($item['item_name'])) {
            $itemName = htmlspecialchars($item['item_name']);
        } elseif (isset($item['product_name'])) {
            $itemName = htmlspecialchars($item['product_name']);
        } elseif (isset($item['name'])) {
            $itemName = htmlspecialchars($item['name']);
        } else {
            $itemName = 'Unknown Item';
        }
        
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $itemTotal = $quantity * $price;
        
        $color = isset($item['variant_color']) && !empty($item['variant_color']) ? htmlspecialchars($item['variant_color']) : null;
        $size = isset($item['size']) && !empty($item['size']) ? htmlspecialchars($item['size']) : null;
        $descrip6 = isset($item['descrip6']) && !empty($item['descrip6']) ? htmlspecialchars($item['descrip6']) : null;
        $descrip7 = isset($item['descrip7']) && !empty($item['descrip7']) ? htmlspecialchars($item['descrip7']) : null;
        
        echo '<div class="p-4 hover:bg-gray-50 transition-colors">';
        echo '<div class="flex justify-between items-start">';
        echo '<div class="flex-1">';
        echo '<h5 class="font-medium text-gray-900 mb-2">' . $itemName . '</h5>';
        
        echo '<div class="text-sm text-gray-600 space-y-1">';
        echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500">Qty:</span><span class="font-medium">' . $quantity . '</span></div>';
        echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500">Price:</span><span>₱' . number_format($price, 2) . '</span></div>';
        
        if ($color) echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500">Color:</span><span>' . $color . '</span></div>';
        if ($size) echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500">Size:</span><span>' . $size . '</span></div>';
        if ($descrip6) echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500"></span><span>' . $descrip6 . '</span></div>';
        if ($descrip7) echo '<div class="flex items-center"><span class="inline-block w-16 text-gray-500"></span><span>' . $descrip7 . '</span></div>';
        
        echo '</div>'; // item details
        echo '</div>'; // left
        echo '<div class="text-right">';
        echo '<p class="text-lg font-semibold text-gray-900">₱' . number_format($itemTotal, 2) . '</p>';
        echo '<p class="text-sm text-gray-500">Subtotal</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Order Summary
    echo '<div class="bg-gray-50 rounded-lg p-4 mb-4">';
    echo '<h4 class="font-medium text-gray-900 mb-3">Order Summary</h4>';
    echo '<div class="space-y-2 text-sm">';
    echo '<div class="flex justify-between"><span>Original Subtotal:</span><span>₱' . number_format($originalTotal, 2) . '</span></div>';
    
    if ($discount > 0) {
        $discountAmount = ($originalTotal * $discount) / 100;
        echo '<div class="flex justify-between text-red-600"><span>Discount (' . $discount . '%):</span><span>-₱' . number_format($discountAmount, 2) . '</span></div>';
    }

    echo '<div class="flex justify-between"><span>VAT (12%):</span><span>₱' . number_format($vatAmount, 2) . '</span></div>';
    if ($shippingFee > 0) echo '<div class="flex justify-between"><span>Shipping Fee:</span><span>₱' . number_format($shippingFee, 2) . '</span></div>';
    if ($deliveryFee > 0) echo '<div class="flex justify-between"><span>Delivery Fee:</span><span>₱' . number_format($deliveryFee, 2) . '</span></div>';

    echo '<div class="flex justify-between font-semibold text-lg border-t pt-2"><span>Final Total:</span><span class="text-green-600">₱' . number_format($finalTotal, 2) . '</span></div>';
    echo '</div>';
    echo '</div>';
    
    // Action Buttons
    echo '<div class="flex flex-wrap gap-3">';
    echo '<button onclick="viewBilling(' . $orderId . ')" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition-colors flex items-center">';
    echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
    echo '</svg>';
    echo 'View Billing';
    echo '</button>';
    echo '</div>'; // actions
    
    echo '</div>'; // card container
}
?>
