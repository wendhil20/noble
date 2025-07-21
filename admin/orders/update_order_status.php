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
    // Get order details for email (MySQLi version)
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

   if ($action === 'confirm') {
    // Update order status to ongoing
    $stmt = $conn->prepare("UPDATE orders SET status = 'Ongoing', confirmed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Update variant_tracking status to 'ongoing'
    $stmt2 = $conn->prepare("UPDATE variant_tracking SET status = 'Ongoing' WHERE order_id = ?");
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $stmt2->close();

    // Send confirmation email
    sendConfirmationEmail($order);

    echo json_encode(['success' => true, 'message' => 'Order confirmed, tracking updated, and email sent']);

} elseif ($action === 'reject') {
    // Update order status to rejected
    $stmt = $conn->prepare("UPDATE orders SET status = 'rejected', rejected_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Update variant_tracking status to 'rejected'
    $stmt2 = $conn->prepare("UPDATE variant_tracking SET status = 'rejected' WHERE order_id = ?");
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $stmt2->close();

    // Send rejection email
    sendRejectionEmail($order);

    echo json_encode(['success' => true, 'message' => 'Order rejected, tracking updated, and email sent']);
}

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function sendConfirmationEmail($order) {
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

        $billing_link = "http://localhost/noble/admin/orders/billing.php?order_id=" . $order['id'];

        $mail->Body = "
        <html><body>
            <h1>Order Confirmed!</h1>
            <p>Dear {$order['customer_name']},</p>
            <p>Great news! Your order has been confirmed and is now being processed.</p>
            <h3>Order Details</h3>
            <p><strong>Order ID:</strong> #{$order['id']}</p>
            <p><strong>Order Date:</strong> {$order['created_at']}</p>
            <p><strong>Delivery Address:</strong> {$order['address']}, {$order['zipcode']}</p>
            <p><strong>Contact:</strong> {$order['mobile']}</p>
            <h4>Items Ordered:</h4>
            <pre>{$order['items_list']}</pre>
            <h4>Order Summary:</h4>
            <p>Subtotal: ₱" . number_format($order['total'], 2) . "</p>
            <p>VAT (12%): ₱" . number_format($vat, 2) . "</p>";

        if ($discount > 0) {
            $mail->Body .= "<p>Discount: -₱" . number_format($discount, 2) . "</p>";
        }
        if ($shipping_fee > 0) {
            $mail->Body .= "<p>Shipping Fee: ₱" . number_format($shipping_fee, 2) . "</p>";
        }
        if ($delivery_fee > 0) {
            $mail->Body .= "<p>Delivery Fee: ₱" . number_format($delivery_fee, 2) . "</p>";
        }

        $mail->Body .= "
            <p><strong>Final Total: ₱" . number_format($final_total, 2) . "</strong></p> 
            <p>Thank you for choosing us!</p>
        </body></html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}


function sendRejectionEmail($order) {
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