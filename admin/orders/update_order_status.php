<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['admin', 'superadmin']);

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php'; // Adjust path if needed

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;
$action = $input['action'] ?? null;

if (!$order_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    // Get order details for email and user_id
    $stmt = $conn->prepare("
        SELECT o.*, 
               GROUP_CONCAT(
                   CONCAT(oi.product_name, ' (', oi.size, ', ', oi.variant_color, ') - Qty: ', oi.quantity, ' - ₱', oi.subtotal)
                   SEPARATOR '\n'
               ) as items_list
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }

    // ... previous code ...

if ($action === 'confirm') {
    // Update order status to ongoing
    $stmtUpdate = $conn->prepare("UPDATE orders SET status = 'Ongoing', confirmed_at = NOW() WHERE id = ?");
    $stmtUpdate->bind_param("i", $order_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // Update variant_tracking status to 'Ongoing'
    $stmtVT = $conn->prepare("UPDATE variant_tracking SET status = 'Ongoing' WHERE order_id = ?");
    $stmtVT->bind_param("i", $order_id);
    $stmtVT->execute();
    $stmtVT->close();

    // Insert notification
    $user_id = $order['user_id'] ?? null;
    if ($user_id) {
        $actor_id = $_SESSION['noble_user']['id'] ?? null;
        $type = 'order_confirmed';
        $reference_no = $order['reference_no'] ?? '';
        $message = "Your order #{$order['id']} (Ref: {$reference_no}) has been confirmed and is now being processed. Please check your email or Spam.";
        $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmtNotif->bind_param("iiss", $user_id, $actor_id, $type, $message);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    // Send confirmation email
    sendConfirmationEmail($order);

    echo json_encode(['success' => true, 'message' => 'Order confirmed, tracking updated, notification created, and email sent']);
} elseif ($action === 'reject') {
    // Update order status to rejected
    $stmtUpdate = $conn->prepare("UPDATE orders SET status = 'rejected', rejected_at = NOW() WHERE id = ?");
    $stmtUpdate->bind_param("i", $order_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // Update variant_tracking status to 'rejected'
    $stmtVT = $conn->prepare("UPDATE variant_tracking SET status = 'rejected' WHERE order_id = ?");
    $stmtVT->bind_param("i", $order_id);
    $stmtVT->execute();
    $stmtVT->close();

    // Insert notification
    $user_id = $order['user_id'] ?? null;
    if ($user_id) {
        $actor_id = $_SESSION['noble_user']['id'] ?? null;
        $type = 'order_rejected';
        $reference_no = $order['reference_no'] ?? '';
        $message = "Your order #{$order['id']} (Ref: {$reference_no}) has been rejected.";
        $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmtNotif->bind_param("iiss", $user_id, $actor_id, $type, $message);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    // Send rejection email
    sendRejectionEmail($order);

    echo json_encode(['success' => true, 'message' => 'Order rejected, tracking updated, notification created, and email sent']);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}


    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function sendConfirmationEmail($order)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wendhil10@gmail.com';
        $mail->Password   = 'tnjqjsuopqlwzoug'; // Use your actual app password or env variable
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your-email@gmail.com', 'Noble Admin');
        $mail->addAddress($order['email'], $order['customer_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Order Confirmation - Order #' . $order['id'];

        $vat = $order['total'] * 0.12;
        $discount = $order['discount'] ?? 0;
        $shipping_fee = $order['shipping_fee'] ?? 0;
        $delivery_fee = $order['delivery_fee'] ?? 0;
        $final_total = $order['total'] + $vat + $shipping_fee + $delivery_fee - $discount;

        $mail->Body = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8' />
  <meta name='viewport' content='width=device-width, initial-scale=1.0' />
  <title>Order Confirmation</title>
</head>
<body class='bg-gray-50 font-sans'>
  <div class='max-w-xl mx-auto bg-white rounded-lg shadow-md overflow-hidden my-8'>
    <div class='bg-gradient-to-r from-orange-500 to-yellow-400 text-white text-center p-8'>
      <div class='inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-25 rounded-full mx-auto mb-4 text-4xl'>✓</div>
      <h1 class='text-3xl font-bold'>Order Confirmed!</h1>
      <span class='inline-block mt-2 bg-green-600 text-white text-xs font-semibold uppercase tracking-wide rounded-full px-4 py-1'>Confirmed</span>
    </div>

    <div class='p-8 text-gray-700'>
      <p class='text-lg font-semibold mb-4'>Dear {$order['customer_name']},</p>
      <p class='mb-6'>Great news! Your order has been confirmed and is now being processed. We'll keep you updated on its progress.</p>

      <section class='mb-8'>
        <h3 class='text-xl font-semibold border-b-2 border-orange-500 pb-2 mb-4'>📋 Order Details</h3>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Order ID:</span><span>#{$order['id']}</span></div>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Order Date:</span><span>{$order['created_at']}</span></div>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Delivery Address:</span><span>{$order['address']}, {$order['zipcode']}</span></div>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Contact Number:</span><span>{$order['mobile']}</span></div>
      </section>

      <section class='mb-8'>
        <h4 class='text-lg font-semibold border-b border-gray-300 pb-1 mb-3'>📦 Items Ordered</h4>
        <pre class='bg-gray-100 rounded-md p-4 text-sm whitespace-pre-wrap font-mono text-gray-700'>{$order['items_list']}</pre>
      </section>

      <section>
        <h4 class='text-lg font-semibold border-b-2 border-orange-500 pb-2 mb-4'>💰 Order Summary</h4>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Subtotal:</span><span>₱" . number_format($order['total'], 2) . "</span></div>
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>VAT (12%):</span><span>₱" . number_format($vat, 2) . "</span></div>";

        if ($discount > 0) {
            $mail->Body .= "
        <div class='flex justify-between py-1'><span class='font-semibold text-red-600'>Discount:</span><span class='text-red-600'>-₱" . number_format($discount, 2) . "</span></div>";
        }

        if ($shipping_fee > 0) {
            $mail->Body .= "
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Shipping Fee:</span><span>₱" . number_format($shipping_fee, 2) . "</span></div>";
        }

        if ($delivery_fee > 0) {
            $mail->Body .= "
        <div class='flex justify-between py-1'><span class='font-semibold text-gray-600'>Delivery Fee:</span><span>₱" . number_format($delivery_fee, 2) . "</span></div>";
        }

        $mail->Body .= "
        <div class='flex justify-between py-3 mt-4 text-green-700 font-bold text-lg border-t-2 border-green-200'>
          <span>FINAL TOTAL:</span>
          <span>₱" . number_format($final_total, 2) . "</span>
        </div>
      </section>
    </div>

    <footer class='bg-gray-800 text-gray-200 text-center p-6'>
      <p class='text-lg'>🙏 Thank you for choosing us!</p>
      <p class='text-sm mt-2 opacity-75'>We appreciate your business and look forward to serving you again.</p>
    </footer>
  </div>
</body>
</html>";


        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}

function sendRejectionEmail($order)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wendhil10@gmail.com';
        $mail->Password   = 'tnjqjsuopqlwzoug';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your-email@gmail.com', 'Noble Admin');
        $mail->addAddress($order['email'], $order['customer_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Order Update - Order #' . $order['id'];

        $mail->Body = "
        <html><body>
            <h1>Order Update</h1>
            <p>Dear {$order['customer_name']},</p>
            <p>We regret to inform you that your order has been rejected.</p>
            <h3>Order Details</h3>
            <p><strong>Order ID:</strong> #{$order['id']}</p>
            <p><strong>Order Date:</strong> {$order['created_at']}</p>
            <p>If you have any questions or want to place a new order, please contact us.</p>
        </body></html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}
