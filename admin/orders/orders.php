<?php
include '../../connection/connect.php';
require_role(['sales', 'superadmin']);

// ✅ Set noble_name and noble_lvl from DB if not already set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
  $email = $_SESSION['noble_user'];
  $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->bind_result($name, $lvl);
  if ($stmt->fetch()) {
    $_SESSION['noble_name'] = $name;
    $_SESSION['noble_lvl'] = $lvl; // ← ✅ Store the user's role
  } else {
    $_SESSION['noble_name'] = "Unknown User";
    $_SESSION['noble_lvl'] = "guest"; // fallback role
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Admin Orders - Collapsible List</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#fff7ed',
              100: '#ffedd5',
              200: '#fed7aa',
              300: '#fdba74',
              400: '#fb923c',
              500: '#f97316',
              600: '#ea580c',
              700: '#c2410c',
              800: '#9a3412',
              900: '#7c2d12',
            }
          }
        }
      }
    }
  </script>
  <style>
    .scrollbar-hide::-webkit-scrollbar {
      display: none;
    }

    .scrollbar-hide {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    .order-item {
      transition: all 0.3s ease;
    }

    .order-details {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease-out;
    }

    .order-item.expanded .order-details {
      max-height: 2000px;
    }

    .expand-icon {
      transition: transform 0.3s ease;
    }

    .order-item.expanded .expand-icon {
      transform: rotate(180deg);
    }

    .order-header {
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .order-header:hover {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .order-item.expanded .order-header {
      border-bottom: 1px solid #e5e7eb;
    }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
  <!-- Header Section -->
  <div class="bg-white shadow-lg border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-6">
        <div class="flex items-center space-x-4">
          <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-xl shadow-lg">
            <i class="fas fa-shopping-cart text-white text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Order Management</h1>
            <p class="text-gray-600 mt-1">Manage and track all customer orders</p>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <div class="bg-primary-50 px-4 py-2 rounded-lg">
            <span class="text-primary-700 font-medium" id="orderCount">Loading...</span>
          </div>
          <button onclick="loadOrders()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <i class="fas fa-sync-alt"></i>
            <span>Refresh</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Alert Container -->
    <div id="alertContainer" class="mb-6"></div>

    <!-- Loading State -->
    <div id="loadingState" class="hidden">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
          <span class="ml-3 text-gray-600">Loading orders...</span>
        </div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center space-x-2">
          <i class="fas fa-filter text-gray-400"></i>
          <span class="text-gray-700 font-medium">Filter by Status:</span>
        </div>
        <div class="flex space-x-2">
          <button onclick="filterOrders('all')" class="filter-btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors duration-200 active" data-filter="all">All</button>
          <button onclick="filterOrders('pending')" class="filter-btn bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg hover:bg-yellow-200 transition-colors duration-200" data-filter="pending">Pending</button>
          <button onclick="filterOrders('ongoing')" class="filter-btn bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors duration-200" data-filter="ongoing">Ongoing</button>
        </div>
      </div>
    </div>

    <!-- Search Box -->
    <div class="mb-6">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="fas fa-search text-gray-400"></i>
        </div>
        <input
          type="text"
          id="orderSearch"
          placeholder="Search orders by ID, customer name, email..."
          class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white shadow-sm" />
      </div>
    </div>

    <!-- Orders Container -->
    <div id="ordersContainer" class="space-y-4"></div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
      <div class="text-gray-400 mb-4">
        <i class="fas fa-shopping-cart text-6xl"></i>
      </div>
      <h3 class="text-xl font-semibold text-gray-900 mb-2">No Orders Found</h3>
      <p class="text-gray-600">There are no orders to display at the moment.</p>
    </div>
  </div>

  <script>
    let allOrders = [];
    let currentFilter = 'all';

    // Toggle order details
    function toggleOrderDetails(orderId) {
      const orderItem = document.getElementById(`order-${orderId}`);
      if (orderItem) {
        orderItem.classList.toggle('expanded');
      }
    }

    // Search functionality
    document.getElementById("orderSearch").addEventListener("input", function() {
      const keyword = this.value.toLowerCase();
      const orders = document.querySelectorAll(".order-item");

      orders.forEach(order => {
        const text = order.textContent.toLowerCase();
        order.style.display = text.includes(keyword) ? "" : "none";
      });
    });

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
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle'
      };

      const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
      };

      alertContainer.innerHTML = `
        <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-sm animate-pulse">
          <div class="flex items-center">
            <i class="${icons[type]} text-xl mr-3"></i>
            <div>
              <p class="font-medium">${message}</p>
            </div>
          </div>
        </div>`;

      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 5000);
    }

    // Filter orders - only show pending and ongoing
    function filterOrders(status) {
      currentFilter = status;

      document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-primary-100', 'text-primary-700', 'border-primary-200');
        if (btn.dataset.filter === status) {
          btn.classList.add('active', 'bg-primary-100', 'text-primary-700', 'border-primary-200');
        }
      });

      renderOrders();
    }

    // Load orders - filter out rejected orders
    function loadOrders() {
      const loadingState = document.getElementById('loadingState');
      const ordersContainer = document.getElementById('ordersContainer');

      loadingState.classList.remove('hidden');
      ordersContainer.innerHTML = '';

      fetch('fetch_orders.php')
        .then(res => {
          if (!res.ok) throw new Error('Failed to fetch orders');
          return res.json();
        })
        .then(orders => {
          // Filter out rejected orders - only show pending and ongoing
          allOrders = orders.filter(order => {
            const status = (order.status || 'pending').toLowerCase();
            return status === 'pending' || status === 'ongoing';
          });

          updateOrderCount();
          renderOrders();
        })
        .catch(error => {
          console.error('Error loading orders:', error);
          showAlert('Failed to load orders. Please try again.', 'error');
        })
        .finally(() => {
          loadingState.classList.add('hidden');
        });
    }

    // Update order count - only pending and ongoing
    function updateOrderCount() {
      const orderCount = document.getElementById('orderCount');
      const total = allOrders.length;
      const pending = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'pending').length;
      const ongoing = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'ongoing').length;

      orderCount.innerHTML = `${total} Total Orders • ${pending} Pending • ${ongoing} Ongoing`;
    }

    // Render orders in collapsible list style
    function renderOrders() {
      const container = document.getElementById('ordersContainer');
      const emptyState = document.getElementById('emptyState');

      let filteredOrders = allOrders;
      if (currentFilter !== 'all') {
        filteredOrders = allOrders.filter(order =>
          (order.status || 'pending').toLowerCase() === currentFilter
        );
      }

      if (filteredOrders.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }

      emptyState.classList.add('hidden');
      container.innerHTML = '';

      filteredOrders.forEach(order => {
        const totals = calculateOrderTotal(order);
        const status = (order.status || 'pending').toLowerCase();

        // Status configuration
        const statusConfig = {
          ongoing: {
            class: 'bg-green-100 text-green-800 border-green-200',
            icon: 'fas fa-truck',
            text: 'Ongoing'
          },
          pending: {
            class: 'bg-yellow-100 text-yellow-800 border-yellow-200',
            icon: 'fas fa-clock',
            text: 'Pending'
          }
        };

        const statusBadge = `
          <div class="flex items-center space-x-2 ${statusConfig[status].class} px-3 py-1 rounded-full border text-sm font-medium">
            <i class="${statusConfig[status].icon}"></i>
            <span>${statusConfig[status].text}</span>
          </div>`;

        // Generate items HTML for expanded view
        const itemsHtml = order.items.map(item => `
          <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
            <div class="flex-1">
              <div class="flex items-center space-x-3">
                <div class="bg-primary-50 p-2 rounded-lg">
                  <i class="fas fa-box text-primary-600"></i>
                </div>
                <div>
                  <h4 class="font-semibold text-gray-900">${item.product_name}</h4>
                  <p class="text-sm text-gray-600">
                    <span class="font-medium">Size:</span> ${item.size}, 
                    <span class="font-medium">Color:</span> ${item.variant_color}
                  </p>
                  <p class="text-xs text-gray-500">
                    <span class="font-medium">Code:</span> ${item.codename} • 
                    <span class="font-medium">Details:</span> ${item.descrip6 || ''} ${item.descrip7 || ''}
                  </p>
                </div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-sm text-gray-600">
                <span class="font-medium">Price:</span> ₱${item.price} × 
                <span class="font-medium">Qty:</span> ${item.quantity}
              </div>
              <div class="font-semibold text-gray-900">
                <span class="text-sm font-medium text-gray-600">Subtotal:</span> ₱${item.subtotal}
              </div>
            </div>
          </div>`).join('');

        const discountPercent = parseFloat(order.discount) || 0;
        const shipping_fee = parseFloat(order.shipping_fee) || 0;
        const delivery_fee = parseFloat(order.delivery_fee) || 0;

        const isDisabled = status === 'ongoing';
        const disabledAttr = isDisabled ? 'disabled' : '';
        const disabledClass = isDisabled ? 'bg-gray-100 cursor-not-allowed text-gray-500' : 'bg-white hover:bg-gray-50';

        // Action buttons only for pending orders
        let actionButtons = '';
        if (status === 'pending') {
          actionButtons = `
            <button onclick="confirmOrder(${order.id})" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                    id="confirm-btn-${order.id}">
              <i class="fas fa-check"></i>
              <span>Confirm</span>
            </button>
            <button onclick="rejectOrder(${order.id})" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                    id="reject-btn-${order.id}">
              <i class="fas fa-times"></i>
              <span>Reject</span>
            </button>`;
        }

        // Create collapsible order item
        container.innerHTML += `
          <div class="order-item bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200" id="order-${order.id}">
            <!-- Collapsible Header -->
            <div class="order-header p-4" onclick="toggleOrderDetails(${order.id})">
              <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                  <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg">
                    <i class="fas fa-receipt text-white"></i>
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-gray-900">Order #${order.id}</h3>
                    <p class="text-sm text-gray-600">${order.customer_name} • ${order.created_at}</p>
                    <p class="text-sm font-semibold text-primary-700">₱${totals.finalTotal}</p>
                  </div>
                </div>
                <div class="flex items-center space-x-3">
                  ${statusBadge}
                  <i class="fas fa-chevron-down expand-icon text-gray-400"></i>
                </div>
              </div>
            </div>

            <!-- Collapsible Details -->
            <div class="order-details">
              <div class="px-4 pb-4">
                <!-- Customer Information -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <h4 class="font-semibold text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-user text-primary-600 mr-2"></i>
                        Customer Information
                      </h4>
                      <p class="text-gray-700">${order.customer_name}</p>
                      <p class="text-gray-600 text-sm">${order.email}</p>
                      <p class="text-gray-600 text-sm">${order.mobile}</p>
                    </div>
                    <div>
                      <h4 class="font-semibold text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-map-marker-alt text-primary-600 mr-2"></i>
                        Delivery Address
                      </h4>
                      <p class="text-gray-700">${order.address}</p>
                      <p class="text-gray-600 text-sm">ZIP: ${order.zipcode}</p>
                    </div>
                  </div>
                </div>

                <!-- Order Items -->
                <div class="mb-4">
                  <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-shopping-bag text-primary-600 mr-2"></i>
                    Order Items
                  </h4>
                  <div class="max-h-60 overflow-y-auto scrollbar-hide space-y-1 pr-1 bg-gray-50 rounded-lg p-3">
                    ${itemsHtml}
                  </div>
                </div>

                <!-- Fees and Totals -->
                <div class="mb-4">
                  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-percent text-primary-600 mr-1"></i>
                        Discount (%)
                      </label>
                      <input type="number" value="${discountPercent}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200 ${disabledClass}" 
                             onchange="saveFee(${order.id}, 'discount', this.value)" ${disabledAttr}>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-truck text-primary-600 mr-1"></i>
                        Shipping Fee (₱)
                      </label>
                      <input type="number" value="${shipping_fee}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200 ${disabledClass}" 
                             onchange="saveFee(${order.id}, 'shipping_fee', this.value)" ${disabledAttr}>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-shipping-fast text-primary-600 mr-1"></i>
                        Delivery Fee (₱)
                      </label>
                      <input type="number" value="${delivery_fee}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200 ${disabledClass}" 
                             onchange="saveFee(${order.id}, 'delivery_fee', this.value)" ${disabledAttr}>
                    </div>
                    <div class="bg-primary-50 p-4 rounded-lg">
                      <div class="text-center">
                        <div class="text-sm text-primary-600 mb-1">Final Total</div>
                        <div class="text-xl font-bold text-primary-700">₱${totals.finalTotal}</div>
                        <div class="text-xs text-gray-500">(incl. VAT ₱${totals.vatAmount})</div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Calculation Breakdown -->
                  <div class="bg-gray-50 rounded-lg p-4 text-sm">
                    <h5 class="font-semibold text-gray-900 mb-2">Calculation Breakdown:</h5>
                    <div class="space-y-1 text-gray-600">
                      <div class="flex justify-between">
                        <span>Base Total:</span>
                        <span>₱${totals.baseTotal}</span>
                      </div>
                      <div class="flex justify-between text-red-600">
                        <span>Discount (${discountPercent}%):</span>
                        <span>-₱${totals.discountAmount}</span>
                      </div>
                      <div class="flex justify-between border-t pt-1">
                        <span>Subtotal after discount:</span>
                        <span>₱${totals.subtotalAfterDiscount}</span>
                      </div>
                      <div class="flex justify-between">
                        <span>VAT (12%):</span>
                        <span>₱${totals.vatAmount}</span>
                      </div>
                      <div class="flex justify-between">
                        <span>Shipping Fee:</span>
                        <span>₱${totals.shippingFee}</span>
                      </div>
                      <div class="flex justify-between">
                        <span>Delivery Fee:</span>
                        <span>₱${totals.deliveryFee}</span>
                      </div>
                      <div class="flex justify-between font-semibold text-gray-900 border-t pt-1">
                        <span>Final Total:</span>
                        <span>₱${totals.finalTotal}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                  <a href="export_excel.php?order_id=${order.id}" 
                     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                     target="_blank">
                    <i class="fas fa-file-excel"></i>
                    <span>Export Excel</span>
                  </a>
                  <div class="flex space-x-2">
                    ${actionButtons}
                  </div>
                </div>
              </div>
            </div>
          </div>`;
      });
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
            loadOrders();
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

      if (!btn) {
        showAlert('Button not found. Please refresh the page.', 'error');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

      const rejectBtn = document.getElementById(`reject-btn-${orderId}`);
      if (rejectBtn) {
        rejectBtn.disabled = true;
      }

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
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            showAlert('Order confirmed successfully!', 'success');
            loadOrders();
          } else {
            throw new Error(data.message || data.error || 'Unknown error occurred');
          }
        })
        .catch(error => {
          console.error('Error confirming order:', error);
          showAlert(`Failed to confirm order: ${error.message}`, 'error');

          const currentBtn = document.getElementById(`confirm-btn-${orderId}`);
          const currentRejectBtn = document.getElementById(`reject-btn-${orderId}`);

          if (currentBtn) {
            currentBtn.disabled = false;
            currentBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm';
          }

          if (currentRejectBtn) {
            currentRejectBtn.disabled = false;
          }
        });
    }

    // Reject order function
    function rejectOrder(orderId) {
      const btn = document.getElementById(`reject-btn-${orderId}`);

      if (!btn) {
        showAlert('Button not found. Please refresh the page.', 'error');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

      const confirmBtn = document.getElementById(`confirm-btn-${orderId}`);
      if (confirmBtn) {
        confirmBtn.disabled = true;
      }

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
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.text().then(text => {
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('Server response is not valid JSON:', text);
              throw new Error('Server returned invalid response. Check console for details.');
            }
          });
        })
        .then(data => {
          if (data.success) {
            showAlert('Order rejected successfully!', 'success');
            loadOrders();
          } else {
            throw new Error(data.message || data.error || 'Unknown error occurred');
          }
        })
        .catch(error => {
          console.error('Error rejecting order:', error);
          showAlert(`Failed to reject order: ${error.message}`, 'error');

          const currentBtn = document.getElementById(`reject-btn-${orderId}`);
          const currentConfirmBtn = document.getElementById(`confirm-btn-${orderId}`);

          if (currentBtn) {
            currentBtn.disabled = false;
            currentBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Reject';
          }

          if (currentConfirmBtn) {
            currentConfirmBtn.disabled = false;
          }
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', loadOrders);
  </script>
</body>

</html>