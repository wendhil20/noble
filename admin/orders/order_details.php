<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

// Set noble_name and noble_lvl from DB if not already set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
  $email = $_SESSION['noble_user'];
  $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->bind_result($name, $lvl);
  if ($stmt->fetch()) {
    $_SESSION['noble_name'] = $name;
    $_SESSION['noble_lvl'] = $lvl;
  } else {
    $_SESSION['noble_name'] = "Unknown User";
    $_SESSION['noble_lvl'] = "guest";
  }
  $stmt->close();
}

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
  session_unset();
  session_destroy();
  header("Location: ../../loginpage/index.php?timeout=true");
  exit();
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
  header("Location: index.php");
  exit();
}

include '../navbar/top.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Order Details - #<?php echo $order_id; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }
      .print-area, .print-area * {
        visibility: visible;
      }
      .print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>

<body class="bg-gray-100 font-sans">

  <div class="container mx-auto p-6">
    <!-- Order Details Header -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
      <div class="p-6">
        <div class="flex justify-between items-center">
          <div class="flex items-center space-x-4">
            <button onclick="window.history.back()" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-lg transition-colors duration-200">
              <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
              </svg>
            </button>
            <div>
              <h1 class="text-2xl font-bold text-gray-900">Order Details</h1>
              <p class="text-gray-600 mt-1" id="orderTitle">Loading order information...</p>
            </div>
          </div>
          <div class="flex items-center space-x-3 no-print">
            <button onclick="window.print()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
              </svg>
              <span>Print</span>
            </button>
            <button onclick="loadOrderDetails()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
              </svg>
              <span>Refresh</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="mb-6 no-print"></div>

    <!-- Main Content -->
    <div class="bg-white rounded-lg shadow-sm">
      <!-- Loading State -->
      <div id="loadingState" class="hidden p-8">
        <div class="flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
          <span class="ml-3 text-gray-600">Loading order details...</span>
        </div>
      </div>

      <!-- Order Details Container -->
      <div id="orderDetailsContainer"></div>

      <!-- Error State -->
      <div id="errorState" class="hidden p-8 text-center">
        <div class="text-red-400 mb-4">
          <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">Order Not Found</h3>
        <p class="text-gray-600 mb-4">The requested order could not be found or you don't have permission to view it.</p>
        <button onclick="window.history.back()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
          Go Back
        </button>
      </div>
    </div>
  </div>

  <!-- Hidden Print Area -->
  <div id="printArea" class="print-area hidden">
    <!-- Print content will be injected here -->
  </div>

  <script>
    const orderId = <?php echo $order_id; ?>;
    let orderData = null;

    // Calculate order total with proper precision
    function calculateOrderTotal(order) {
      const baseTotal = parseFloat(order.total) || 0;
      const discountPercent = parseFloat(order.discount) || 0;
      const shippingFee = parseFloat(order.shipping_fee) || 0;
      const deliveryFee = parseFloat(order.delivery_fee) || 0;

      const discountAmount = (baseTotal * discountPercent) / 100;
      const subtotalAfterDiscount = baseTotal - discountAmount;
      const vatAmount = subtotalAfterDiscount * 0.12;
      const finalTotal = subtotalAfterDiscount + vatAmount + shippingFee + deliveryFee;

      return {
        baseTotal: baseTotal.toFixed(2),
        discountAmount: discountAmount.toFixed(2),
        subtotalAfterDiscount: subtotalAfterDiscount.toFixed(2),
        vatAmount: vatAmount.toFixed(2),
        shippingFee: shippingFee.toFixed(2),
        deliveryFee: deliveryFee.toFixed(2),
        finalTotal: finalTotal.toFixed(2)
      };
    }

    // Show alert message
    function showAlert(message, type = 'info') {
      const alertContainer = document.getElementById('alertContainer');
      const icons = {
        success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
        error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
        info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
      };

      const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
      };

      alertContainer.innerHTML = `
        <div class="border-l-4 ${colors[type]} p-4 rounded-lg">
          <div class="flex items-center">
            <div class="flex-shrink-0">${icons[type]}</div>
            <div class="ml-3">
              <p class="font-medium">${message}</p>
            </div>
          </div>
        </div>`;

      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 5000);
    }

    // Load order details
    function loadOrderDetails() {
      const loadingState = document.getElementById('loadingState');
      const orderDetailsContainer = document.getElementById('orderDetailsContainer');
      const errorState = document.getElementById('errorState');

      loadingState.classList.remove('hidden');
      orderDetailsContainer.innerHTML = '';
      errorState.classList.add('hidden');

      fetch(`fetch_order_details.php?order_id=${orderId}`)
        .then(res => {
          if (!res.ok) throw new Error('Failed to fetch order details');
          return res.json();
        })
        .then(data => {
          if (data.success && data.order) {
            orderData = data.order;
            renderOrderDetails(data.order);
            document.getElementById('orderTitle').textContent = `Order #${data.order.id} - ${data.order.customer_name}`;
          } else {
            throw new Error(data.message || 'Order not found');
          }
        })
        .catch(error => {
          console.error('Error loading order details:', error);
          errorState.classList.remove('hidden');
          showAlert('Failed to load order details. Please try again.', 'error');
        })
        .finally(() => {
          loadingState.classList.add('hidden');
        });
    }

    // Render order details
    function renderOrderDetails(order) {
      const container = document.getElementById('orderDetailsContainer');
      const totals = calculateOrderTotal(order);
      const status = (order.status || 'pending').toLowerCase();

      // Status configuration
      const statusConfig = {
        ongoing: {
          class: 'bg-green-100 text-green-800 border-green-200',
          icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
          text: 'Ongoing'
        },
        pending: {
          class: 'bg-yellow-100 text-yellow-800 border-yellow-200',
          icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
          text: 'Pending'
        },
        rejected: {
          class: 'bg-red-100 text-red-800 border-red-200',
          icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
          text: 'Rejected'
        }
      };

      const statusBadge = `
        <div class="flex items-center space-x-2 ${statusConfig[status]?.class || statusConfig.pending.class} px-4 py-2 rounded-full border font-medium">
          ${statusConfig[status]?.icon || statusConfig.pending.icon}
          <span>${statusConfig[status]?.text || 'Pending'}</span>
        </div>`;

      // Generate items HTML
      const itemsHtml = order.items.map(item => `
        <div class="flex justify-between items-start py-4 border-b border-gray-100 last:border-b-0">
          <div class="flex-1">
            <div class="flex items-start space-x-4">
              <div class="bg-orange-50 p-3 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-gray-900">${item.product_name}</h4>
                <div class="mt-2 space-y-1">
                  <p class="text-sm text-gray-600">
                    <span class="font-medium">Size:</span> ${item.size} • 
                    <span class="font-medium">Color:</span> ${item.variant_color}
                  </p>
                  <p class="text-sm text-gray-600">
                    <span class="font-medium">Code:</span> ${item.codename}
                  </p>
                  ${item.descrip6 || item.descrip7 ? `
                    <p class="text-sm text-gray-500">
                      <span class="font-medium">Details:</span> ${item.descrip6 || ''} ${item.descrip7 || ''}
                    </p>
                  ` : ''}
                </div>
              </div>
            </div>
          </div>
          <div class="text-right ml-4 flex-shrink-0">
            <div class="text-lg font-semibold text-gray-900 mb-1">₱${item.subtotal}</div>
            <div class="text-sm text-gray-600">
              ₱${item.price} × ${item.quantity} pcs
            </div>
          </div>
        </div>`).join('');

      const discountPercent = parseFloat(order.discount) || 0;
      const shipping_fee = parseFloat(order.shipping_fee) || 0;
      const delivery_fee = parseFloat(order.delivery_fee) || 0;

      const isDisabled = status === 'ongoing' || status === 'rejected';
      const disabledAttr = isDisabled ? 'disabled' : '';
      const disabledClass = isDisabled ? 'bg-gray-100 cursor-not-allowed text-gray-500' : 'bg-white hover:bg-gray-50';

      // Action buttons
      let actionButtons = '';
      if (status === 'pending') {
        actionButtons = `
          <div class="flex space-x-3 no-print">
            <button onclick="confirmOrder(${order.id})" 
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2" 
                    id="confirm-btn-${order.id}">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>Confirm Order</span>
            </button>
            <button onclick="rejectOrder(${order.id})" 
                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2" 
                    id="reject-btn-${order.id}">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              <span>Reject Order</span>
            </button>
          </div>`;
      }

      container.innerHTML = `
        <!-- Order Header -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 mb-2">Order #${order.id}</h2>
              <p class="text-gray-600">Order Date: ${order.created_at}</p>
            </div>
            ${statusBadge}
          </div>
        </div>

        <!-- Customer & Delivery Information -->
        <div class="p-6 border-b border-gray-200">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Customer Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Customer Information
              </h3>
              <div class="space-y-3">
                <div>
                  <label class="text-sm font-medium text-gray-500">Full Name</label>
                  <p class="text-gray-900 font-medium">${order.customer_name}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-500">Email Address</label>
                  <p class="text-gray-900">${order.email}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-500">Mobile Number</label>
                  <p class="text-gray-900">${order.mobile}</p>
                </div>
              </div>
            </div>

            <!-- Delivery Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Delivery Information
              </h3>
              <div class="space-y-3">
                <div>
                  <label class="text-sm font-medium text-gray-500">Delivery Address</label>
                  <p class="text-gray-900">${order.address}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-500">ZIP Code</label>
                  <p class="text-gray-900">${order.zipcode}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Items -->
        <div class="p-6 border-b border-gray-200">
          <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
            </svg>
            Order Items (${order.items.length} items)
          </h3>
          <div class="bg-gray-50 rounded-lg p-4">
            ${itemsHtml}
          </div>
        </div>

        <!-- Fees and Calculations -->
        <div class="p-6 border-b border-gray-200">
          <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
            Fees & Calculations
          </h3>
          
          <!-- Fee Input Fields -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Discount (%)
              </label>
              <input type="number" value="${discountPercent}" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors duration-200 ${disabledClass}" 
                     onchange="saveFee(${order.id}, 'discount', this.value)" ${disabledAttr}>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Shipping Fee (₱)
              </label>
              <input type="number" value="${shipping_fee}" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors duration-200 ${disabledClass}" 
                     onchange="saveFee(${order.id}, 'shipping_fee', this.value)" ${disabledAttr}>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Delivery Fee (₱)
              </label>
              <input type="number" value="${delivery_fee}" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors duration-200 ${disabledClass}" 
                     onchange="saveFee(${order.id}, 'delivery_fee', this.value)" ${disabledAttr}>
            </div>
          </div>

          <!-- Calculation Breakdown -->
          <div class="bg-orange-50 rounded-lg p-6">
            <h4 class="font-semibold text-gray-900 mb-4">Order Summary</h4>
            <div class="space-y-3">
              <div class="flex justify-between text-gray-700">
                <span>Base Total:</span>
                <span class="font-medium">₱${totals.baseTotal}</span>
              </div>
              <div class="flex justify-between text-red-600">
                <span>Discount (${discountPercent}%):</span>
                <span class="font-medium">-₱${totals.discountAmount}</span>
              </div>
              <div class="flex justify-between text-gray-700 pt-2 border-t border-orange-200">
                <span>Subtotal after discount:</span>
                <span class="font-medium">₱${totals.subtotalAfterDiscount}</span>
              </div>
              <div class="flex justify-between text-gray-700">
                <span>VAT (12%):</span>
                <span class="font-medium">₱${totals.vatAmount}</span>
              </div>
              <div class="flex justify-between text-gray-700">
                <span>Shipping Fee:</span>
                <span class="font-medium">₱${totals.shippingFee}</span>
              </div>
              <div class="flex justify-between text-gray-700">
                <span>Delivery Fee:</span>
                <span class="font-medium">₱${totals.deliveryFee}</span>
              </div>
              <div class="flex justify-between text-xl font-bold text-orange-700 pt-3 border-t-2 border-orange-300">
                <span>Final Total:</span>
                <span>₱${totals.finalTotal}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="p-6">
          <div class="flex justify-between items-center">
            <a href="export_excel.php?order_id=${order.id}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2 no-print" 
               target="_blank">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <span>Export to Excel</span>
            </a>
            ${actionButtons}
          </div>
        </div>`;
    }

    // Save fee function
    function saveFee(orderId, field, value) {
      const inputElement = document.querySelector(`input[onchange*="saveFee(${orderId}, '${field}'"]`);
      if (inputElement) {
        inputElement.disabled = true;
        inputElement.classList.add('opacity-50');
      }

      const requestData = {
        order_id: parseInt(orderId),
        field: field,
        value: parseFloat(value) || 0
      };

      fetch('update_order_fees.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(requestData)
        })
        .then(res => {
          if (!res.ok) throw new Error('Failed to update');
          return res.json();
        })
        .then(data => {
          if (data.success) {
            showAlert('Fee updated successfully!', 'success');
            loadOrderDetails(); // Reload to show updated calculations
          } else {
            throw new Error(data.error || 'Unknown error');
          }
        })
        .catch(error => {
          showAlert(`Failed to update: ${error.message}`, 'error');
        })
        .finally(() => {
          if (inputElement) {
            inputElement.disabled = false;
            inputElement.classList.remove('opacity-50');
          }
        });
    }

    // Confirm order function
    function confirmOrder(orderId) {
      const btn = document.getElementById(`confirm-btn-${orderId}`);
      if (!btn) return;

      btn.disabled = true;
      btn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Processing...';

      const rejectBtn = document.getElementById(`reject-btn-${orderId}`);
      if (rejectBtn) rejectBtn.disabled = true;

      fetch('update_order_status.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            order_id: parseInt(orderId),
            action: 'confirm'
          })
        })
        .then(response => {
          if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
          return response.json();
        })
        .then(data => {
          if (data.success) {
            showAlert('Order confirmed successfully!', 'success');
            loadOrderDetails(); // Reload to show updated status
          } else {
            throw new Error(data.message || data.error || 'Unknown error occurred');
          }
        })
        .catch(error => {
          console.error('Error confirming order:', error);
          showAlert(`Failed to confirm order: ${error.message}`, 'error');
          
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Confirm Order</span>';
          }
          if (rejectBtn) rejectBtn.disabled = false;
        });
    }

    // Reject order function
    function rejectOrder(orderId) {
      const btn = document.getElementById(`reject-btn-${orderId}`);
      if (!btn) return;

      btn.disabled = true;
      btn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Processing...';

      const confirmBtn = document.getElementById(`confirm-btn-${orderId}`);
      if (confirmBtn) confirmBtn.disabled = true;

      fetch('update_order_status.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            order_id: parseInt(orderId),
            action: 'reject'
          })
        })
        .then(response => {
          if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
          return response.text().then(text => {
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('Server response is not valid JSON:', text);
              throw new Error('Server returned invalid response.');
            }
          });
        })
        .then(data => {
          if (data.success) {
            showAlert('Order rejected successfully!', 'success');
            loadOrderDetails(); // Reload to show updated status
          } else {
            throw new Error(data.message || data.error || 'Unknown error occurred');
          }
        })
        .catch(error => {
          console.error('Error rejecting order:', error);
          showAlert(`Failed to reject order: ${error.message}`, 'error');
          
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><span>Reject Order</span>';
          }
          if (confirmBtn) confirmBtn.disabled = false;
        });
    }

    // Print Order function  
    function printOrder(orderId) {
      const printContent = createPrintContent(orderId);
      document.getElementById('printArea').innerHTML = printContent;
      window.print();
    }

    function createPrintContent(orderId) {
      return `
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
          <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #ea580c; margin-bottom: 10px;">NobleHome</h1>
            <h2 style="color: #333; margin-bottom: 20px;">Order Confirmation</h2>
          </div>
          
          <div style="border: 2px solid #10b981; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: #10b981; margin-bottom: 15px;">Order #${orderId}</h3>
            <p style="margin-bottom: 10px;"><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
            <p style="margin-bottom: 10px;"><strong>Status:</strong> <span style="color: #10b981;">Confirmed</span></p>
          </div>
          
          <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
            <p style="color: #666; font-size: 14px;">Thank you for choosing NobleHome!</p>
            <p style="color: #666; font-size: 14px;">For questions, contact us at: wendhil10@gmail.com</p>
          </div>
        </div>
      `;
    }

    // Download Excel function
    function downloadExcel(orderId) {
      window.open('export_excel.php?order_id=' + orderId, '_blank');
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', loadOrderDetails);
  </script>
</body>

</html>