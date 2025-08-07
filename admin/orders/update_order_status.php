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
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .header .icon {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .order-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .order-info h3 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 20px;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
            text-align: right;
            flex: 1;
            margin-left: 20px;
        }
        .items-section {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .items-section h4 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .items-list {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            color: #444;
            white-space: pre-wrap;
        }
        .summary-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .summary-section h4 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 10px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #28a745;
            background-color: #e8f5e8;
            padding: 15px;
            margin: 10px -20px -20px -20px;
            border-radius: 0 0 8px 8px;
        }
        .summary-label {
            font-weight: 500;
        }
        .summary-value {
            font-weight: bold;
            color: #333;
        }
        .footer {
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 25px;
        }
        .footer p {
            margin: 0;
            font-size: 16px;
        }
        .status-badge {
            background-color: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-top: 10px;
        }
        @media (max-width: 600px) {
            .container {
                margin: 0;
                box-shadow: none;
            }
            .content {
                padding: 20px;
            }
            .info-row, .summary-row {
                flex-direction: column;
            }
            .info-value {
                text-align: left;
                margin-left: 0;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <div class='icon'>✓</div>
            <h1>Order Confirmed!</h1>
            <div class='status-badge'>Confirmed</div>
        </div>
        
        <div class='content'>
            <div class='greeting'>
                Dear {$order['customer_name']},
            </div>
            
            <div class='message'>
                Great news! Your order has been confirmed and is now being processed. We'll keep you updated on its progress.
            </div>
            
            <div class='order-info'>
                <h3>📋 Order Details</h3>
                <div class='info-row'>
                    <span class='info-label'>Order ID:</span>
                    <span class='info-value'>#{$order['id']}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Order Date:</span>
                    <span class='info-value'>{$order['created_at']}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Delivery Address:</span>
                    <span class='info-value'>{$order['address']}, {$order['zipcode']}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Contact Number:</span>
                    <span class='info-value'>{$order['mobile']}</span>
                </div>
            </div>
            
            <div class='items-section'>
                <h4>📦 Items Ordered</h4>
                <div class='items-list'>{$order['items_list']}</div>
            </div>
            
            <div class='summary-section'>
                <h4>💰 Order Summary</h4>
                <div class='summary-row'>
                    <span class='summary-label'>Subtotal:</span>
                    <span class='summary-value'>₱" . number_format($order['total'], 2) . "</span>
                </div>
                <div class='summary-row'>
                    <span class='summary-label'>VAT (12%):</span>
                    <span class='summary-value'>₱" . number_format($vat, 2) . "</span>
                </div>";

if ($discount > 0) {
    $mail->Body .= "
                <div class='summary-row'>
                    <span class='summary-label'>Discount:</span>
                    <span class='summary-value' style='color: #dc3545;'>-₱" . number_format($discount, 2) . "</span>
                </div>";
}

if ($shipping_fee > 0) {
    $mail->Body .= "
                <div class='summary-row'>
                    <span class='summary-label'>Shipping Fee:</span>
                    <span class='summary-value'>₱" . number_format($shipping_fee, 2) . "</span>
                </div>";
}

if ($delivery_fee > 0) {
    $mail->Body .= "
                <div class='summary-row'>
                    <span class='summary-label'>Delivery Fee:</span>
                    <span class='summary-value'>₱" . number_format($delivery_fee, 2) . "</span>
                </div>";
}

$mail->Body .= "
                <div class='summary-row'>
                    <span class='summary-label'>FINAL TOTAL:</span>
                    <span class='summary-value'>₱" . number_format($final_total, 2) . "</span>
                </div>
            </div>
        </div>
        
        <div class='footer'>
            <p>🙏 Thank you for choosing us!</p>
            <p style='font-size: 14px; margin-top: 10px; opacity: 0.8;'>
                We appreciate your business and look forward to serving you again.
            </p>
        </div>
    </div>
</body>
</html>";

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