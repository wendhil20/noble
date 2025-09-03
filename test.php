<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8' />
  <meta name='viewport' content='width=device-width, initial-scale=1.0' />
  <title>Order Confirmation</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }
    
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    }
    
    /* Logo Section */
    .logo-section {
      background: #ffffff;
      text-align: center;
      padding: 30px;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .company-logo {
      max-width: 200px;
      max-height: 80px;
      width: auto;
      height: auto;
    }
    
    /* Header */
    .header {
      background: linear-gradient(135deg, #ffa500ff 0%, #ffa500ff 50%, #ffa500ff 100%);
      text-align: center;
      padding: 40px 30px;
      position: relative;
      overflow: hidden;
    }
    
    .header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      animation: shimmer 3s ease-in-out infinite;
    }
    
    @keyframes shimmer {
      0%, 100% { transform: translateX(-100px) translateY(-100px); }
      50% { transform: translateX(100px) translateY(100px); }
    }
    
    .check-icon {
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 40px;
      color: white;
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.3);
      position: relative;
      z-index: 1;
    }
    
    .header h1 {
      color: white;
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 15px;
      position: relative;
      z-index: 1;
    }
    
    .status-badge {
      display: inline-block;
      background: rgba(16, 185, 129, 0.9);
      color: white;
      padding: 8px 20px;
      border-radius: 25px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      position: relative;
      z-index: 1;
      backdrop-filter: blur(10px);
    }
    
    /* Content */
    .content {
      padding: 40px 30px;
      color: #374151;
      line-height: 1.6;
    }
    
    .greeting {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 15px;
    }
    
    .intro-text {
      font-size: 16px;
      margin-bottom: 30px;
      color: #6b7280;
    }
    
    /* Sections */
    .section {
      margin-bottom: 35px;
      background: #f8fafc;
      border-radius: 15px;
      padding: 25px;
      border-left: 5px solid #ff6b6b;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Detail Rows */
    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .detail-row:last-child {
      border-bottom: none;
    }
    
    .detail-label {
      font-weight: 500;
      color: #6b7280;
      flex: 1;
    }
    
    .detail-value {
      font-weight: 500;
      color: #1f2937;
      text-align: right;
      flex: 1;
    }
    
    /* Items Container */
    .items-container {
      background: #1f2937;
      color: #e5e7eb;
      border-radius: 10px;
      padding: 20px;
      font-family: 'Monaco', 'Menlo', monospace;
      font-size: 14px;
      white-space: pre-wrap;
      overflow-x: auto;
      border: 1px solid #374151;
    }
    
    /* Total Section */
    .total-section {
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      border-left-color: #10b981;
    }
    
    .final-total {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
      margin-top: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 20px;
      font-weight: 700;
      box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
    }
    
    .discount-row .detail-value {
      color: #dc2626;
      font-weight: 600;
    }
    
    /* Footer */
    .footer {
      background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
      color: white;
      text-align: center;
      padding: 40px 30px;
      position: relative;
    }
    
    .footer-main {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    
    .footer-sub {
      font-size: 14px;
      opacity: 0.8;
      line-height: 1.5;
    }
    
    /* Responsive */
    @media (max-width: 600px) {
      body {
        padding: 10px;
      }
      
      .email-container {
        border-radius: 15px;
      }
      
      .logo-section {
        padding: 20px;
      }
      
      .company-logo {
        max-width: 150px;
        max-height: 60px;
      }
      
      .header {
        padding: 30px 20px;
      }
      
      .header h1 {
        font-size: 26px;
      }
      
      .content {
        padding: 30px 20px;
      }
      
      .section {
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
        gap: 10px;
        text-align: center;
      }
      
      .footer-main {
        flex-direction: column;
        gap: 5px;
      }
    }
  </style>
</head>
<body>
  <div class='email-container'>
    
    <!-- Logo Section -->
    <div class='logo-section'>
      <img src='noblehomedepot.com/admin/img/logo/logo.png' alt='Company Logo' class='company-logo' />
    </div>
    
    <!-- Header -->
    <div class='header'>
      <div class='check-icon'>✓</div>
      <h1>Order Confirmed!</h1>
      <span class='status-badge'>Confirmed</span>
    </div>
    
    <!-- Content -->
    <div class='content'>
      <p class='greeting'>Dear {$order['customer_name']},</p>
      <p class='intro-text'>Fantastic news! Your order has been confirmed and is now being processed. We'll keep you updated every step of the way.</p>
      
      <!-- Order Details -->
      <section class='section'>
        <h3 class='section-title'>
          <span>📋</span>
          <span>Order Details</span>
        </h3>
        <div class='detail-row'>
          <span class='detail-label'>Order ID:</span>
          <span class='detail-value'>#{$order['id']}</span>
        </div>
        <div class='detail-row'>
          <span class='detail-label'>Order Date:</span>
          <span class='detail-value'>{$order['created_at']}</span>
        </div>
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
        <h4 class='section-title'>
          <span>📦</span>
          <span>Items Ordered</span>
        </h4>
        <div class='items-container'>{$order['items_list']}</div>
      </section>
      
      <!-- Order Summary -->
      <section class='section total-section'>
        <h4 class='section-title'>
          <span>💰</span>
          <span>Order Summary</span>
        </h4>
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
          <span>FINAL TOTAL:</span>
          <span>₱" . number_format($final_total, 2) . "</span>
        </div>
      </section>
    </div>
    
    <!-- Footer -->
    <footer class='footer'>
      <p class='footer-main'>
        <span>🙏</span>
        <span>Thank you for choosing us!</span>
      </p>
      <p class='footer-sub'>We appreciate your business and look forward to serving you again.</p>
    </footer>
  </div>
</body>
</html>