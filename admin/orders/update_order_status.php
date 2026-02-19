<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

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

       // Function to parse your items string and convert to table format
function parseItemsToTable($itemsString) {
    $html = '';
    
    // Clean up the string and split by common patterns
    $itemsString = trim($itemsString);
    
    // Split by product codes or "Qty:" patterns
    $lines = preg_split('/(?=SYCJ-|(?=Round Tea)|(?=Small Coffee)|(?=Mercedes))/i', $itemsString);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Extract quantity and price using regex
        if (preg_match('/Qty:\s*(\d+).*?₱([\d,]+\.?\d*)/s', $line, $matches)) {
            $quantity = intval($matches[1]);
            $totalPrice = floatval(str_replace(',', '', $matches[2]));
            $unitPrice = $totalPrice / $quantity;
            
            // Extract item code (first part before any parentheses or descriptions)
            preg_match('/^([^(]+)/', $line, $codeMatch);
            $code = trim($codeMatch[1] ?? '');
            
            // Extract description (between parentheses or after code)
            $description = '';
            if (preg_match('/\(([^)]+)\)/', $line, $descMatch)) {
                $description = trim($descMatch[1]);
            } else {
                // If no parentheses, extract everything between code and "Qty:"
                $description = preg_replace('/^[^-]*-?\s*([^Q]*?)Qty:.*$/s', '$1', $line);
                $description = trim($description);
            }
            
            $html .= "
                <tr>
                  <td>
                    <div class='item-code'>{$code}</div>
                    <div class='item-description'>{$description}</div>
                  </td>
                  <td class='item-quantity'>{$quantity}</td>
                  <td class='item-price'>₱" . number_format($unitPrice, 2) . "</td>
                  <td class='item-price'>₱" . number_format($totalPrice, 2) . "</td>
                </tr>
            ";
        }
    }
    
    return $html;
}

// Main email template
$mail->Body = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8' />
  <meta name='viewport' content='width=device-width, initial-scale=1.0' />
  <title>Order Confirmation</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #f8fafc;
      min-height: 100vh;
      padding: 40px 20px;
      color: #374151;
      line-height: 1.6;
    }
    
    .email-container {
      max-width: 650px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border: 1px solid #e5e7eb;
    }
    
    /* Header */
    .header {
      background: #ffffff;
      text-align: center;
      padding: 40px 30px 30px;
      border-bottom: 2px solid #1f2937;
    }
    
    .company-logo {
      max-width: 180px;
      max-height: 70px;
      width: auto;
      height: auto;
      margin-bottom: 25px;
    }
    
    .header h1 {
      color: #1f2937;
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 10px;
      letter-spacing: -0.025em;
    }
    
    .order-number {
      color: #6b7280;
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 15px;
    }
    
    .status-badge {
      display: inline-block;
      background: #10b981;
      color: white;
      padding: 6px 16px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    /* Content */
    .content {
      padding: 40px 30px;
    }
    
    .greeting {
      font-size: 16px;
      font-weight: 500;
      color: #1f2937;
      margin-bottom: 20px;
    }
    
    .intro-text {
      font-size: 15px;
      margin-bottom: 35px;
      color: #4b5563;
    }
    
    /* Sections */
    .section {
      margin-bottom: 30px;
      background: #f9fafb;
      border-radius: 6px;
      padding: 25px;
      border: 1px solid #e5e7eb;
    }
    
    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #d1d5db;
      padding-bottom: 8px;
    }
    
    /* Detail Rows */
    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #f3f4f6;
    }
    
    .detail-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    
    .detail-label {
      font-weight: 500;
      color: #4b5563;
      flex: 1;
    }
    
    .detail-value {
      font-weight: 500;
      color: #1f2937;
      text-align: right;
      flex: 1;
    }
    
    /* Items Table */
    .items-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 6px;
      overflow: hidden;
      border: 1px solid #e5e7eb;
    }
    
    .items-table thead {
      background: #f9fafb;
    }
    
    .items-table th {
      padding: 12px 15px;
      text-align: left;
      font-weight: 600;
      color: #374151;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .items-table th:last-child,
    .items-table td:last-child {
      text-align: right;
    }
    
    .items-table td {
      padding: 15px;
      border-bottom: 1px solid #f3f4f6;
      color: #374151;
      vertical-align: top;
    }
    
    .items-table tr:last-child td {
      border-bottom: none;
    }
    
    .items-table tbody tr:hover {
      background: #fafbfc;
    }
    
    .item-code {
      font-weight: 600;
      color: #1f2937;
      font-size: 14px;
    }
    
    .item-description {
      color: #6b7280;
      font-size: 13px;
      margin-top: 4px;
    }
    
    .item-price {
      font-weight: 600;
      color: #059669;
      font-size: 15px;
    }
    
    .item-quantity {
      font-weight: 500;
      color: #374151;
    }
    
    /* Total Section */
    .total-section {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
    }
    
    .final-total {
      background: rgba(224, 175, 13, 1);
      color: white;
      padding: 18px 20px;
      border-radius: 4px;
      margin-top: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 18px;
      font-weight: 600;
    }
    
    .discount-row .detail-value {
      color: #dc2626;
      font-weight: 600;
    }
    
    /* Contact Information */
    .contact-section {
      background: #fafafa;
      border: 1px solid #e5e7eb;
      padding: 25px;
      margin-top: 30px;
      border-radius: 6px;
    }
    
    .contact-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .contact-info {
      font-size: 14px;
      color: #6b7280;
      line-height: 1.5;
    }
    
    /* Footer */
    .footer {
      background: #1f2937;
      color: #d1d5db;
      text-align: center;
      padding: 30px;
    }
    
    .footer-main {
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 8px;
    }
    
    .footer-sub {
      font-size: 13px;
      opacity: 0.8;
      line-height: 1.4;
    }
    
    /* Responsive */
    @media (max-width: 600px) {
      body {
        padding: 20px 10px;
      }
      
      .email-container {
        border-radius: 6px;
      }
      
      .header {
        padding: 30px 20px 25px;
      }
      
      .company-logo {
        max-width: 150px;
        max-height: 60px;
      }
      
      .header h1 {
        font-size: 24px;
      }
      
      .content {
        padding: 30px 20px;
      }
      
      .section {
        padding: 20px;
      }
      
      .contact-section {
        padding: 20px;
      }
      
      .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      
      .detail-value {
        text-align: left;
      }
      
      .final-total {
        flex-direction: column;
        gap: 8px;
        text-align: center;
      }
      
      .items-table {
        font-size: 13px;
      }
      
      .items-table th,
      .items-table td {
        padding: 10px;
      }
      
      .items-table th {
        font-size: 11px;
      }
    }
  </style>
</head>
<body>
  <div class='email-container'>
    
    <!-- Header -->
    <div class='header'>
      <img src='noblehomedepot.com/admin/img/logo/logo.png' alt='Noble Home Depot' class='company-logo' />
      <h1>Order Confirmation</h1>
      <p class='order-number'>Order #{$order['id']}</p>
      <span class='status-badge'>Confirmed</span>
    </div>
    
    <!-- Content -->
    <div class='content'>
      <p class='greeting'>Dear {$order['customer_name']},</p>
      <p class='intro-text'>
        Thank you for your order. We are pleased to confirm that your order has been received and is currently being processed. 
        You will receive updates on your order status via email and SMS.
      </p>
      
      <!-- Order Information -->
<section class='section'>
  <h3 class='section-title'>Order Information</h3>
  <div class='detail-row'>
    <span class='detail-label'>Order Number:</span>
    <span class='detail-value'>#{$order['id']}</span>
  </div>
  <div class='detail-row'>
    <span class='detail-label'>Order Date:</span>
    <span class='detail-value'>" . date('F j, Y g:i A', strtotime($order['created_at'])) . "</span>
  </div>
  <div class='detail-row'>
    <span class='detail-label'>Status:</span>
    <span class='detail-value'>Confirmed</span>
  </div>
</section>

      <!-- Delivery Information -->
      <section class='section'>
        <h3 class='section-title'>Delivery Information</h3>
        <div class='detail-row'>
          <span class='detail-label'>Delivery Address:</span>
          <span class='detail-value'>{$order['address']}, {$order['zipcode']}</span>
        </div>
        <div class='detail-row'>
          <span class='detail-label'>Contact Number:</span>
          <span class='detail-value'>{$order['mobile']}</span>
        </div>
      </section>
      
      <!-- Items Ordered -->
      <section class='section'>
        <h4 class='section-title'>Items Ordered</h4>
        <table class='items-table'>
          <thead>
            <tr>
              <th>Item</th>
              <th>Quantity</th>
              <th>Unit Price</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            " . parseItemsToTable($order['items_list']) . "
          </tbody>
        </table>
      </section>
      
      <!-- Order Summary -->
      <section class='section total-section'>
        <h4 class='section-title'>Order Summary</h4>
        <div class='detail-row'>
          <span class='detail-label'>Subtotal:</span>
          <span class='detail-value'>₱" . number_format($order['total'], 2) . "</span>
        </div>
        <div class='detail-row'>
          <span class='detail-label'>VAT (12%):</span>
          <span class='detail-value'>₱" . number_format($vat, 2) . "</span>
        </div>";

if ($discount > 0) {
    $mail->Body .= "
        <div class='detail-row discount-row'>
          <span class='detail-label'>Discount:</span>
          <span class='detail-value'>-₱" . number_format($discount, 2) . "</span>
        </div>";
}

if ($shipping_fee > 0) {
    $mail->Body .= "
        <div class='detail-row'>
          <span class='detail-label'>Shipping Fee:</span>
          <span class='detail-value'>₱" . number_format($shipping_fee, 2) . "</span>
        </div>";
}

if ($delivery_fee > 0) {
    $mail->Body .= "
        <div class='detail-row'>
          <span class='detail-label'>Delivery Fee:</span>
          <span class='detail-value'>₱" . number_format($delivery_fee, 2) . "</span>
        </div>";
}

$mail->Body .= "
        <div class='final-total'>
          <span>TOTAL AMOUNT:</span>
          <span>₱" . number_format($final_total, 2) . "</span>
        </div>
      </section>

      <!-- Contact Information -->
      <div class='contact-section'>
        <h4 class='contact-title'>Customer Service</h4>
        <div class='contact-info'>
          For inquiries regarding your order, please contact our customer service team.<br>
          We are available Monday to Friday, 8:00 AM to 6:00 PM.<br><br>
          <strong>Email:</strong> noblehomeconst.ph@gmail.com<br>
          <strong>Phone:</strong> (02) 8123-4567
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <footer class='footer'>
      <p class='footer-main'>Noble Home Depot</p>
      <p class='footer-sub'>
        This is an automated message. Please do not reply to this email.<br>
        © 2025 Noble Home Depot. All rights reserved.
      </p>
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
