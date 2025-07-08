<?php
session_start();
include '../../connection/connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

// Fixed order confirmation section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['confirm_order'], $_POST['confirm_order_id'])) {
    $orderId = (int) $_POST['confirm_order_id'];
    if ($orderId <= 0) die("❌ Invalid order ID.");

    $discount_percent = floatval($_POST['discount'] ?? 0);
    $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);

    $order_result = $conn->query("SELECT * FROM orders WHERE id = $orderId");
    if ($order_result && $order_result->num_rows > 0) {
      $order_data = $order_result->fetch_assoc();
      $total = floatval($order_data['total']);
      $email = $order_data['email'];
      $name = $order_data['customer_name'];
      $discount_amount = ($total * $discount_percent) / 100;
      $grand_total = ($total - $discount_amount) + $shipping_fee;

      // ✅ FIX: Update the order status to 'Ongoing'
      $update_result = $conn->query("UPDATE orders SET 
        status='Ongoing', 
        discount=$discount_percent, 
        shipping_fee=$shipping_fee, 
        final_total=$grand_total 
        WHERE id=$orderId");

      if ($update_result) {
        $client_check = $conn->query("SELECT id, reference_no FROM client_info WHERE email = '$email'");
        if ($client_check->num_rows == 0) {
          $today = date('Ymd');
          $count_result = $conn->query("SELECT COUNT(*) AS total FROM client_info WHERE DATE(created_at) = CURDATE()");
          $count_row = $count_result->fetch_assoc();
          $reference_no = 'NOBLE-' . $today . '-' . str_pad($count_row['total'] + 1, 4, '0', STR_PAD_LEFT);

          $client_name = $conn->real_escape_string($order_data['customer_name']);
          $client_email = $conn->real_escape_string($order_data['email']);
          $client_address = $conn->real_escape_string($order_data['address']);
          $client_contact = $conn->real_escape_string($order_data['mobile']);
          $client_zip = $conn->real_escape_string($order_data['zipcode']);
          $created_at = date('Y-m-d H:i:s');

          $conn->query("INSERT INTO client_info 
            (name, address, email, contact, country, client_type, sex, status, created_at, reference_no) 
            VALUES 
            ('$client_name', '$client_address', '$client_email', '$client_contact', '$client_zip', 'Customer', 'N/A', 'Ongoing', '$created_at', '$reference_no')");
        } else {
          $client = $client_check->fetch_assoc();
          $reference_no = $client['reference_no'];
        }

        // ✅ Send Email
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
          $mail->Subject = "Order #$orderId Ongoing – Upload Payment";
          $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
              <h2 style='color: #10b981;'>🧾 Order #$orderId Ongoing</h2>
              <p>Hi <strong>$name</strong>,</p>
              <p>Your order has been confirmed and is now ongoing. Please proceed with the payment:</p>
              <ul>
                <li><strong>Subtotal:</strong> ₱" . number_format($total, 2) . "</li>
                <li><strong>Discount:</strong> $discount_percent% (₱" . number_format($discount_amount, 2) . ")</li>
                <li><strong>Shipping Fee:</strong> ₱" . number_format($shipping_fee, 2) . "</li>
                <li><strong>Total:</strong> <span style='color:green;'>₱" . number_format($grand_total, 2) . "</span></li>
              </ul>
              <p>
                <a href='http://localhost/noble/admin/orders/billing.php?order_id=$orderId' target='_blank'
                  style='background:#10b981;color:white;padding:10px 18px;border-radius:6px;text-decoration:none;'>View Billing</a>
              </p>
              <p>Thank you for choosing <strong style='color:#ea580c;'>NobleHome</strong>!</p>
            </div>";
          $mail->send();
        } catch (Exception $e) {
          error_log("Mailer Error: {$mail->ErrorInfo}");
        }

        // ✅ FIX: Redirect to refresh the page and show updated data
        echo "<script>
          alert('Order #$orderId has been confirmed successfully!');
          window.location.href='orders.php?tab=ongoing';
        </script>";
        exit;
      } else {
        echo "<script>
          alert('Error updating order status. Please try again.');
          window.location.href='orders.php?tab=pending';
        </script>";
        exit;
      }
    }
  }

  if (isset($_POST['reject_order'], $_POST['reject_order_id'])) {
    $orderId = (int) $_POST['reject_order_id'];
    if ($orderId > 0) {
      $order_result = $conn->query("SELECT * FROM orders WHERE id = $orderId");
      if ($order_result && $order_result->num_rows > 0) {
        $order_data = $order_result->fetch_assoc();
        $email = $order_data['email'];
        $name = $order_data['customer_name'];

        // ✅ Update order status first (instant)
        $conn->query("UPDATE orders SET status='Rejected' WHERE id=$orderId");

        // ✉️ Send rejection email with timeout
        $mail = new PHPMailer(true);
        try {
          $mail->isSMTP();
          $mail->Host = 'smtp.gmail.com';
          $mail->SMTPAuth = true;
          $mail->Username = 'wendhil10@gmail.com';
          $mail->Password = 'tnjqjsuopqlwzoug';
          $mail->SMTPSecure = 'tls';
          $mail->Port = 587;
          $mail->Timeout = 5; // 5 second timeout only
          $mail->SMTPOptions = array(
            'ssl' => array(
              'verify_peer' => false,
              'verify_peer_name' => false,
              'allow_self_signed' => true
            )
          );

          $mail->setFrom('no-reply@yourdomain.com', 'NobleHome Orders');
          $mail->addAddress($email, $name);
          $mail->isHTML(true);
          $mail->Subject = "Order #$orderId Rejected";

          $mail->Body = "
          <div style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2 style='color: #dc2626;'> Order #$orderId Rejected</h2>
            <p>Dear <strong>$name</strong>,</p>
            <p>We regret to inform you that your recent order (#$orderId) has been rejected. This may be due to unavailability of items or other reasons.</p>
            <p>If you believe this is a mistake or wish to reorder, please contact us or try placing a new order.</p>
            <p>Thank you for understanding.<br><strong style='color:#ea580c;'>NobleHome</strong> Team</p>
          </div>";

          $mail->send();
        } catch (Exception $e) {
          error_log("Rejection Mailer Error: {$mail->ErrorInfo}");
          // Don't let email failure stop the process
        }

        echo "<script>
          alert('Order #$orderId has been rejected successfully!');
          window.location.href='orders.php?tab=rejected';
        </script>";
        exit;
      }
    }
  }
}

// ✅ FIX: More specific query for pending orders - only get orders with 'Pending' status
$pendingOrders = $conn->query("SELECT * FROM orders WHERE status = 'Pending' OR status IS NULL OR status = '' ORDER BY created_at DESC");
$confirmedOrders = $conn->query("SELECT * FROM orders WHERE status = 'Ongoing' ORDER BY created_at DESC");
$rejectedOrders = $conn->query("SELECT * FROM orders WHERE status = 'Rejected' ORDER BY created_at DESC");

// Get counts for badges
$pendingCount = $pendingOrders->num_rows;
$ongoingCount = $confirmedOrders->num_rows;
$rejectedCount = $rejectedOrders->num_rows;

// Get active tab from URL parameter
$activeTab = $_GET['tab'] ?? 'pending';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Admin - Orders</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
    }
  </style>
</head>

<body class="bg-gray-100 font-sans">
  <?php include '../navbar/top.php'; ?>

  <div class="container mx-auto p-6">
    <!-- Orders Navigation Tabs -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
      <div class="border-b border-gray-200">
        <nav class="flex space-x-0">
          <button onclick="showTab('pending')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'pending') ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              Pending
              <span class="ml-2 bg-orange-100 text-orange-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $pendingCount; ?>
              </span>
            </span>
          </button>
          
          <button onclick="showTab('ongoing')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'ongoing') ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              Ongoing
              <span class="ml-2 bg-green-100 text-green-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $ongoingCount; ?>
              </span>
            </span>
          </button>
          
          <button onclick="showTab('rejected')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'rejected') ? 'border-red-500 text-red-600 bg-red-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              Rejected
              <span class="ml-2 bg-red-100 text-red-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $rejectedCount; ?>
              </span>
            </span>
          </button>
        </nav>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="bg-white rounded-lg shadow-sm">
      <!-- Pending Orders Tab -->
      <div id="pending-tab" class="tab-content <?php echo ($activeTab === 'pending') ? 'active' : ''; ?> p-6">
        <h2 class="text-2xl font-bold text-orange-700 mb-6 flex items-center">
          <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Pending Orders
        </h2>
        
        <div class="space-y-4">
          <?php if ($pendingCount > 0): 
            $pendingOrders->data_seek(0); // Reset pointer
            while ($order = $pendingOrders->fetch_assoc()): 
              renderOrderCard($order, $conn);
            endwhile;
          else: ?>
            <div class="text-center py-12">
              <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <p class="text-gray-500 text-lg">No pending orders found</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Ongoing Orders Tab -->
      <div id="ongoing-tab" class="tab-content <?php echo ($activeTab === 'ongoing') ? 'active' : ''; ?> p-6">
        <h2 class="text-2xl font-bold text-green-700 mb-6 flex items-center">
          <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Ongoing Orders
        </h2>
        
        <div class="space-y-4">
          <?php if ($ongoingCount > 0): 
            $confirmedOrders->data_seek(0); // Reset pointer
            while ($order = $confirmedOrders->fetch_assoc()): 
              renderOrderCard($order, $conn);
            endwhile;
          else: ?>
            <div class="text-center py-12">
              <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-gray-500 text-lg">No ongoing orders found</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Rejected Orders Tab -->
      <div id="rejected-tab" class="tab-content <?php echo ($activeTab === 'rejected') ? 'active' : ''; ?> p-6">
        <h2 class="text-2xl font-bold text-red-700 mb-6 flex items-center">
          <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Rejected Orders
        </h2>
        
        <div class="space-y-4">
          <?php if ($rejectedCount > 0): 
            $rejectedOrders->data_seek(0); // Reset pointer
            while ($order = $rejectedOrders->fetch_assoc()): 
              renderOrderCard($order, $conn);
            endwhile;
          else: ?>
            <div class="text-center py-12">
              <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-gray-500 text-lg">No rejected orders found</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    function showTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
      });
      
      // Remove active classes from all tab buttons
      document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-orange-500', 'text-orange-600', 'bg-orange-50');
        button.classList.remove('border-green-500', 'text-green-600', 'bg-green-50');
        button.classList.remove('border-red-500', 'text-red-600', 'bg-red-50');
        button.classList.add('border-transparent', 'text-gray-500');
      });
      
      // Show selected tab content
      document.getElementById(tabName + '-tab').classList.add('active');
      
      // Add active class to clicked button
      const activeButton = event.target.closest('.tab-button');
      activeButton.classList.remove('border-transparent', 'text-gray-500');
      
      if (tabName === 'pending') {
        activeButton.classList.add('border-orange-500', 'text-orange-600', 'bg-orange-50');
      } else if (tabName === 'ongoing') {
        activeButton.classList.add('border-green-500', 'text-green-600', 'bg-green-50');
      } else if (tabName === 'rejected') {
        activeButton.classList.add('border-red-500', 'text-red-600', 'bg-red-50');
      }
    }

    // Dynamic total calculation function
    function calculateTotal(orderId, baseTotal) {
      const discountInput = document.getElementById('discount-' + orderId);
      const shippingInput = document.getElementById('shipping-' + orderId);
      const calculatedTotalSpan = document.getElementById('calculated-total-' + orderId);
      
      function updateTotal() {
        const discount = parseFloat(discountInput.value) || 0;
        const shipping = parseFloat(shippingInput.value) || 0;
        
        const discountAmount = (baseTotal * discount) / 100;
        const finalTotal = (baseTotal - discountAmount) + shipping;
        
        calculatedTotalSpan.textContent = '₱' + finalTotal.toLocaleString('en-PH', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      
      discountInput.addEventListener('input', updateTotal);
      shippingInput.addEventListener('input', updateTotal);
    }

    // Confirm reject with loading
    function confirmReject(button) {
      if (confirm('Are you sure you want to reject this order?')) {
        button.innerHTML = '<span class="flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Rejecting...</span>';
        button.disabled = true;
        return true;
      }
      return false;
    }

    // ✅ NEW: Excel download function
    function downloadExcel(orderId) {
      // Open the export in a new window
      window.open('export_excel.php?order_id=' + orderId, '_blank');
    }
  </script>
</body>

</html>


<?php
function renderOrderCard($order, $conn)
{
  $order_id = $order['id'];
  // Updated query to include size
  $items = $conn->query("SELECT *, size FROM order_items WHERE order_id = $order_id");
  
  // OPTION 1: If you added final_total column
  $subtotal = floatval($order['total']); // Original subtotal
  $discount = floatval($order['discount']);
  $shipping = floatval($order['shipping_fee']);
  $final_total = isset($order['final_total']) ? floatval($order['final_total']) : (($subtotal - ($subtotal * $discount / 100)) + $shipping);

  $statusColors = [
    'Pending' => 'bg-orange-100 text-orange-800',
    'Ongoing' => 'bg-green-100 text-green-800',
    'Rejected' => 'bg-red-100 text-red-800',
    'Arrival' => 'bg-blue-100 text-blue-800'
  ];

  echo '<div class="border border-gray-200 rounded-lg p-6 bg-white hover:shadow-md transition-shadow duration-200">';
  echo '<div class="flex justify-between items-start mb-4">';
  echo '<div>';
  echo '<h3 class="font-bold text-xl text-gray-900">Order #' . $order_id . '</h3>';
  echo '<p class="text-sm text-gray-600 mt-1">' . date('F j, Y • h:i A', strtotime($order['created_at'])) . '</p>';
  echo '</div>';
  echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ' . ($statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800') . '">';
  echo $order['status'];
  echo '</span>';
  echo '</div>';

  echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
 echo '<div>';
echo '<h4 class="font-semibold text-gray-900 mb-2">Customer Information</h4>';
echo '<p class="text-sm text-gray-600"><strong>Name:</strong> ' . htmlspecialchars($order['customer_name']) . '</p>';
echo '<p class="text-sm text-gray-600"><strong>Email:</strong> ' . htmlspecialchars($order['email']) . '</p>';
echo '<p class="text-sm text-gray-600"><strong>Mobile:</strong> ' . htmlspecialchars($order['mobile']) . '</p>';
echo '<p class="text-sm text-gray-600"><strong>Address:</strong> ' . htmlspecialchars($order['address']) . ', ' . htmlspecialchars($order['zipcode']) . '</p>';
echo '<p class="text-sm text-gray-600"><strong>Payment Mode:</strong> ' . htmlspecialchars($order['mode_payment'] ?? 'N/A') . '</p>';
echo '</div>';

  
  echo '<div>';
  echo '<h4 class="font-semibold text-gray-900 mb-2">Order Summary</h4>';
  echo '<div class="space-y-1">';
  
  echo '<div class="flex justify-between text-sm">';
  echo '<span class="text-gray-600">Subtotal:</span>';
  echo '<span class="font-medium">₱' . number_format($subtotal, 2) . '</span>';
  echo '</div>';
  echo '<div class="flex justify-between text-sm">';
  echo '<span class="text-gray-600">Discount:</span>';
  echo '<span class="font-medium">' . number_format($discount, 2) . '%</span>';
  echo '</div>';
  echo '<div class="flex justify-between text-sm">';
  echo '<span class="text-gray-600">Shipping:</span>';
  echo '<span class="font-medium">₱' . number_format($shipping, 2) . '</span>';
  echo '</div>';
  echo '<div class="flex justify-between text-base font-bold border-t pt-2">';
  echo '<span class="text-gray-900">Total:</span>';
  echo '<span class="text-orange-600">₱' . number_format($final_total, 2) . '</span>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
  echo '</div>';

  // ✅ FIX: Only show actions for pending orders
  if ($order['status'] === 'Pending' || $order['status'] === '' || $order['status'] === null) {
    echo '<div class="border-t pt-4 mb-4">';
    echo '<h4 class="font-semibold text-gray-900 mb-3">Order Actions</h4>';
    
    // ✅ NEW: Excel Download Button - THIS IS THE BUTTON YOU ASKED FOR!
    echo '<div class="mb-4">';
    echo '<button onclick="downloadExcel(' . $order_id . ')" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-md hover:bg-emerald-700 transition-colors duration-200 shadow-sm">';
    echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
    echo '</svg>';
    echo '📊 Download Excel';
    echo '</button>';
    echo '</div>';
    
    echo '<form method="POST" class="space-y-3">';
    echo '<input type="hidden" name="confirm_order_id" value="' . $order_id . '">';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
    echo '<input type="number" id="discount-' . $order_id . '" name="discount" placeholder="Discount (%)" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />';
    echo '<input type="number" id="shipping-' . $order_id . '" name="shipping_fee" placeholder="Shipping Fee (₱)" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />';
    echo '</div>';
    
    // Add dynamic total display
    echo '<div class="bg-gray-50 p-3 rounded-md">';
    echo '<div class="flex justify-between items-center">';
    echo '<span class="text-sm font-medium text-gray-700">Calculated Total:</span>';
    echo '<span id="calculated-total-' . $order_id . '" class="text-lg font-bold text-orange-600">₱' . number_format($final_total, 2) . '</span>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="flex space-x-3">';
    echo '<button type="submit" name="confirm_order" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors duration-200 text-sm font-medium flex-1">Confirm & Send Email</button>';
    echo '</form>';
    
    echo '<form method="POST" class="inline">';
    echo '<input type="hidden" name="reject_order_id" value="' . $order_id . '">';
    echo '<button type="submit" name="reject_order" onclick="return confirmReject(this)" class="bg-red-600 text-white px-4 py-2 rounded-md hover:red-700 transition-colors duration-200 text-sm font-medium flex-1">Reject Order</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    
    // Initialize dynamic total calculation
    echo '<script>calculateTotal(' . $order_id . ', ' . $subtotal . ');</script>';
  }

  // Order items section
  echo '<div class="border-t pt-4">';
  echo '<h4 class="font-semibold text-gray-900 mb-3">Order Items</h4>';
  echo '<div class="space-y-2">';
  
  if ($items && $items->num_rows > 0) {
    while ($item = $items->fetch_assoc()) {
      echo '<div class="flex justify-between items-center bg-gray-50 p-3 rounded-md">';
      echo '<div class="flex-1">';
      echo '<p class="font-medium text-gray-900">' . htmlspecialchars($item['product_name']) . '</p>';
      echo '<p class="text-sm text-gray-600">Size: ' . htmlspecialchars($item['size'] ?? 'N/A') . ' | Qty: ' . $item['quantity'] . '</p>';
      echo '</div>';
      echo '<div class="text-right">';
      echo '<p class="font-medium text-gray-900">₱' . number_format($item['price'], 2) . '</p>';
      echo '<p class="text-sm text-gray-600">₱' . number_format($item['price'] * $item['quantity'], 2) . ' total</p>';
      echo '</div>';
      echo '</div>';
    }
  } else {
    echo '<p class="text-gray-500 text-center py-4">No items found for this order.</p>';
  }
  
  echo '</div>';
  echo '</div>';
  echo '</div>';
}

// Close the database connection
$conn->close();
?>