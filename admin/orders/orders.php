<?php
//orders.php - Content Fragment (no HTML wrapper)
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


?>

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

<!-- Header Section -->
<div class=" border border-gray-200 mb-6 font-roboto">
  <div class="p-4 md:p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
      <div class="flex items-center space-x-4">
        <div class="bg-black p-3 rounded-lg">
          <i class="fas fa-shopping-cart text-white text-2xl"></i>
        </div>
        <div>
          <h2 class="text-2xl md:text-3xl text-gray-900">Pending Orders</h2>
          <p class="text-gray-600 text-sm mt-1">Review and manage pending customer orders</p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <div class=" px-4 py-2">
          <span class="text-black text-xs" id="orderCount">Loading...</span>
        </div>
        <button onclick="loadOrders()" class="bg-black hover:bg-orange-700 text-white px-4 py-2 rounded transition-colors duration-200 flex items-center space-x-2 shadow-sm">
          <i class="fas fa-sync-alt"></i>
          <span>Refresh</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" class="mb-6"></div>

<!-- Loading State -->
<div id="loadingState" class="hidden">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
    <div class="flex items-center justify-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
      <span class="ml-3 text-gray-600">Loading orders...</span>
    </div>
  </div>
</div>

<!-- Filter Section -->
<div class="bg-white  p-4 md:p-6 mb-6">
  <div class="space-y-4">
    <!-- Status Filter -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center space-x-2 min-w-max">
        <i class="fas fa-filter text-gray-400"></i>
        <span class="text-gray-700 font-medium text-sm">Status:</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <button onclick="filterOrders('all')" class="filter-btn bg-gray-100 text-gray-700 px-3 md:px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors duration-200 active text-sm" data-filter="all">All</button>
        <button onclick="filterOrders('pending')" class="filter-btn bg-yellow-100 text-yellow-700 px-3 md:px-4 py-2 rounded-lg hover:bg-yellow-200 transition-colors duration-200 text-sm" data-filter="pending">Pending</button>
        <button onclick="filterOrders('ongoing')" class="filter-btn bg-green-100 text-green-700 px-3 md:px-4 py-2 rounded-lg hover:bg-green-200 transition-colors duration-200 text-sm" data-filter="ongoing">Ongoing</button>
        <button onclick="filterOrders('verified')" class="filter-btn bg-blue-100 text-blue-700 px-3 md:px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors duration-200 text-sm" data-filter="verified">Verified</button>
        <button onclick="filterOrders('rejected')" class="filter-btn bg-red-100 text-red-700 px-3 md:px-4 py-2 rounded-lg hover:bg-red-200 transition-colors duration-200 text-sm" data-filter="rejected">Rejected</button>
      </div>
    </div>

    <!-- Replacement Filter -->
    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200">
      <div class="flex items-center space-x-2 min-w-max">
        <i class="fas fa-exchange-alt text-gray-400"></i>
        <span class="text-gray-700 font-medium text-sm">Replacements:</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <button onclick="filterByReplacement('all')" class="replacement-filter-btn bg-gray-100 text-gray-700 px-3 md:px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors duration-200 active text-sm" data-replacement-filter="all">
          <i class="fas fa-list mr-1"></i>All
        </button>
        <button onclick="filterByReplacement('with_replacements')" class="replacement-filter-btn bg-orange-100 text-orange-700 px-3 md:px-4 py-2 rounded-lg hover:bg-orange-200 transition-colors duration-200 text-sm" data-replacement-filter="with_replacements">
          <i class="fas fa-exclamation-triangle mr-1"></i>Has Issues
        </button>
        <button onclick="filterByReplacement('no_replacements')" class="replacement-filter-btn bg-green-100 text-green-700 px-3 md:px-4 py-2 rounded-lg hover:bg-green-200 transition-colors duration-200 text-sm" data-replacement-filter="no_replacements">
          <i class="fas fa-check-circle mr-1"></i>No Issues
        </button>
      </div>
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
      class="w-full pl-10 pr-4 py-3 border border-gray-300  focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white shadow-sm" />
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

<script>
  let allOrders = [];
  let currentFilter = 'all';
  let currentReplacementFilter = 'all';
  const VAT_RATE = 0.12;

  function toggleOrderDetails(orderId) {
    const orderItem = document.getElementById(`order-${orderId}`);
    if (orderItem) {
      orderItem.classList.toggle('expanded');
    }
  }

  document.getElementById("orderSearch").addEventListener("input", function() {
    const keyword = this.value.toLowerCase();
    const orders = document.querySelectorAll(".order-item");
    orders.forEach(order => {
      const text = order.textContent.toLowerCase();
      order.style.display = text.includes(keyword) ? "" : "none";
    });
  });

  function calculateOrderTotal(order) {
    // ✅ Use values directly from database instead of calculating
    const subtotal = parseFloat(order.subtotal) || 0;
    const vatAmount = parseFloat(order.vat_amount) || 0;
    const deliveryFee = parseFloat(order.delivery_fee) || 0;
    const finalTotal = parseFloat(order.total) || 0;
    
    // Calculate items with VAT (subtotal + vat)
    const itemsWithVAT = subtotal + vatAmount;

    return {
      itemsNetTotal: subtotal.toFixed(2),           // Items without VAT
      itemsWithVAT: itemsWithVAT.toFixed(2),        // Items + VAT
      itemsSubtotal: subtotal.toFixed(2),           // Same as itemsNetTotal
      totalDeliveryFees: deliveryFee.toFixed(2),    // Delivery fee from DB
      vatAmount: vatAmount.toFixed(2),              // VAT from DB
      finalTotal: finalTotal.toFixed(2),            // Grand total from DB
      finalItemsWithVAT: itemsWithVAT.toFixed(2),   // Items + VAT
      finalItemsNetTotal: subtotal.toFixed(2),      // Items only
      finalDeliveryTotal: deliveryFee.toFixed(2),   // Delivery fee
      finalVATAmount: vatAmount.toFixed(2),         // VAT amount
      finalTotalWithoutVAT: (subtotal + deliveryFee).toFixed(2)  // Items + Delivery (no VAT)
    };
  }

  function getVATBreakdownHTML(totals) {
    return `
    <div class="vat-breakdown rounded-lg p-4 text-sm">
      <h5 class="font-semibold text-black mb-4 flex items-center">
        <i class="fas fa-calculator text-black mr-2"></i>
        VAT Calculation Breakdown (12% VAT Rate)
      </h5>
      
      <div class="bg-white rounded-lg p-4 shadow-sm">
        <div class="space-y-2 text-gray-700">
          <div class="flex justify-between items-center py-1 border-b border-orange-100">
            <span class="flex items-center">
              <i class="fas fa-box text-black mr-2 w-4"></i>
              <span class="font-medium">Items Net (Price × Qty):</span>
            </span>
            <span class="font-semibold text-black">₱${totals.itemsNetTotal}</span>
          </div>
          
          <div class="flex justify-between items-center py-1 border-b border-orange-100">
            <span class="flex items-center">
              <i class="fas fa-percent text-black mr-2 w-4"></i>
              <span class="font-medium text-black">VAT on Items (12%):</span>
            </span>
            <span class="font-semibold text-black">+₱${totals.finalVATAmount}</span>
          </div>
          
          <div class="flex justify-between items-center py-1 border-b border-orange-100">
            <span class="flex items-center">
              <i class="fas fa-shopping-bag text-black mr-2 w-4"></i>
              <span class="font-medium">Items Total (Net + VAT):</span>
            </span>
            <span class="font-semibold text-black">₱${totals.finalItemsWithVAT}</span>
          </div>
          
          <div class="flex justify-between items-center py-1 border-b border-orange-100">
            <span class="flex items-center">
              <i class="fas fa-truck text-black mr-2 w-4"></i>
              <span class="font-medium">Delivery Fee:</span>
            </span>
            <span class="font-semibold text-black">+₱${totals.finalDeliveryTotal}</span>
          </div>
          
          <div class="flex justify-between items-center py-2 border-t-2 border-orange-300 mt-2">
            <span class="flex items-center">
              <i class="fas fa-receipt text-black mr-2 w-4"></i>
              <span class="font-bold text-lg text-black">Final Total:</span>
            </span>
            <span class="font-bold text-xl text-black">₱${totals.finalTotal}</span>
          </div>
        </div>
      </div>
      
      <div class="mt-4 rounded-lg p-3">
        <div class="flex items-center justify-between">
          <span class="font-medium text-black flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            VAT Summary:
          </span>
          <div class="text-right">
            <div class="text-black font-bold">
              Total VAT: <span class="text-lg">₱${totals.finalVATAmount}</span>
            </div>
            <div class="text-xs text-black">
              (Items only, delivery is VAT-exempt)
            </div>
          </div>
        </div>
      </div>
    </div>`;
  }

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
          <p class="font-medium">${message}</p>
        </div>
      </div>`;

    setTimeout(() => alertContainer.innerHTML = '', 5000);
  }

  function filterOrders(status) {
    currentFilter = status;
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.classList.remove('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
      if (btn.dataset.filter === status) {
        btn.classList.add('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
      }
    });
    renderOrders();
  }

  function filterByReplacement(replacementStatus) {
    currentReplacementFilter = replacementStatus;
    document.querySelectorAll('.replacement-filter-btn').forEach(btn => {
      btn.classList.remove('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
      if (btn.dataset.replacementFilter === replacementStatus) {
        btn.classList.add('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
      }
    });
    renderOrders();
  }

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
        allOrders = orders;
        updateOrderCount();
        renderOrders();
      })
      .catch(error => {
        console.error('Error loading orders:', error);
        showAlert('Failed to load orders. Please try again.', 'error');
      })
      .finally(() => loadingState.classList.add('hidden'));
  }

  function updateOrderCount() {
    const orderCount = document.getElementById('orderCount');
    const total = allOrders.length;
    const pending = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'pending').length;
    const ongoing = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'ongoing').length;
    const verified = allOrders.filter(o => (o.payment_status || '').toLowerCase() === 'verified').length;
    const rejected = allOrders.filter(o =>
      (o.status || '').toLowerCase() === 'rejected' ||
      (o.payment_status || '').toLowerCase() === 'rejected'
    ).length;
    const withReplacements = allOrders.filter(o => o.has_replacement_requests === true).length;

    orderCount.innerHTML = `${total} Total • ${pending} Pending • ${ongoing} Ongoing • ${verified} Verified • ${rejected} Rejected • ${withReplacements} With Replacements`;
  }

  function renderOrders() {
    const container = document.getElementById('ordersContainer');
    const emptyState = document.getElementById('emptyState');
    let filteredOrders = allOrders;

    if (currentFilter !== 'all') {
      if (currentFilter === 'verified') {
        filteredOrders = filteredOrders.filter(order =>
          (order.payment_status || '').toLowerCase() === 'verified'
        );
      } else if (currentFilter === 'rejected') {
        filteredOrders = filteredOrders.filter(order =>
          (order.status || '').toLowerCase() === 'rejected' ||
          (order.payment_status || '').toLowerCase() === 'rejected'
        );
      } else {
        filteredOrders = filteredOrders.filter(order =>
          (order.status || 'pending').toLowerCase() === currentFilter
        );
      }
    }

    if (currentReplacementFilter !== 'all') {
      if (currentReplacementFilter === 'with_replacements') {
        filteredOrders = filteredOrders.filter(order => order.has_replacement_requests === true);
      } else if (currentReplacementFilter === 'no_replacements') {
        filteredOrders = filteredOrders.filter(order => order.has_replacement_requests !== true);
      }
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
      const paymentStatus = (order.payment_status || '').toLowerCase();

      const statusConfig = {
        ongoing: { class: 'bg-green-100 text-green-800 border-green-200', icon: 'fas fa-truck', text: 'Ongoing' },
        pending: { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: 'fas fa-clock', text: 'Pending' },
        rejected: { class: 'bg-red-100 text-red-800 border-red-200', icon: 'fas fa-times-circle', text: 'Rejected' }
      };

      const paymentStatusConfig = {
        verified: { class: 'bg-blue-100 text-blue-800 border-blue-200', icon: 'fas fa-check-circle', text: 'Payment Verified' },
        pending: { class: 'bg-orange-100 text-orange-800 border-orange-200', icon: 'fas fa-hourglass-half', text: 'Payment Pending' },
        rejected: { class: 'bg-red-100 text-red-800 border-red-200', icon: 'fas fa-ban', text: 'Payment Rejected' }
      };

      const statusBadge = statusConfig[status] ? `
        <div class="flex items-center space-x-2 ${statusConfig[status].class} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
          <i class="${statusConfig[status].icon}"></i>
          <span class="hidden sm:inline">${statusConfig[status].text}</span>
        </div>` : '';

      const paymentBadge = paymentStatusConfig[paymentStatus] ? `
        <div class="flex items-center space-x-2 ${paymentStatusConfig[paymentStatus].class} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
          <i class="${paymentStatusConfig[paymentStatus].icon}"></i>
          <span class="hidden sm:inline">${paymentStatusConfig[paymentStatus].text}</span>
        </div>` : '';

      const itemsHtml = order.items.map((item, itemIndex) => `
        <div class="flex flex-col md:flex-row md:justify-between md:items-start py-3 border-b border-gray-100 last:border-b-0 gap-3">
          <div class="flex-1">
            <div class="flex items-start space-x-3">
              <div class=" p-2 rounded-lg flex-shrink-0">
                <i class="fas fa-box text-black"></i>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-gray-900 break-words">${item.product_name}</h4>
                <p class="text-sm text-gray-600">
                  <span class="font-medium">Size:</span> ${item.size}, 
                  <span class="font-medium">Color:</span> ${item.variant_color}
                </p>
                <p class="text-xs text-gray-500 break-words">
                  <span class="font-medium">Code:</span> ${item.codename}${item.descrip6 || item.descrip7 ? ' • ' + (item.descrip6 || '') + ' ' + (item.descrip7 || '') : ''}
                </p>
                <p class="text-xs text-blue-600 font-medium">
                  <i class="fas fa-map-marker-alt mr-1"></i>
                  ${item.origin || 'Not specified'}
                </p>
                <p class="text-xs ${item.is_manual_supplier ? 'text-purple-600' : 'text-green-600'} font-medium break-words">
                  <i class="fas ${item.is_manual_supplier ? 'fa-edit' : 'fa-user-tie'} mr-1"></i>
                  ${item.supplier_name || 'Not Assigned'}
                  ${item.is_manual_supplier ? ' (Manual)' : item.is_database_supplier ? ' (Database)' : ''}
                </p>
                ${item.delivery_fee_per_item > 0 ? `
                <p class="text-xs text-orange-600 font-medium">
                  <i class="fas fa-truck mr-1"></i>
                  Delivery: ₱${item.delivery_fee_per_item} × ${item.quantity} = ₱${item.item_total_delivery}
                </p>` : ''}
              </div>
            </div>
          </div>
          <div class="text-right md:text-right flex-shrink-0">
            <div class="text-sm text-gray-600 mb-2">
              ₱${item.price} × ${item.quantity}
            </div>
            <div class="font-semibold text-gray-900">
              ₱${item.subtotal}
              <br><span class="text-xs text-blue-600">(VAT inc.)</span>
            </div>
          </div>
        </div>`).join('');

      let actionButtons = '';
      if (paymentStatus === 'verified' && status === 'pending') {
        actionButtons = `
          <button onclick="confirmOrder(${order.id})" 
                  class="bg-green-600 hover:bg-green-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                  id="confirm-btn-${order.id}">
            <i class="fas fa-check"></i>
            <span class="hidden sm:inline">Confirm</span>
          </button>
          <button onclick="rejectOrder(${order.id})" 
                  class="bg-red-600 hover:bg-red-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                  id="reject-btn-${order.id}">
            <i class="fas fa-times"></i>
            <span class="hidden sm:inline">Reject</span>
          </button>`;
      }

      container.innerHTML += `
        <div class="order-item hover:shadow-md transition-all duration-200" id="order-${order.id}">
          <div class="order-header p-3 md:p-4" onclick="toggleOrderDetails(${order.id})">
            <div class="flex justify-between items-center gap-2">
              <div class="flex items-center space-x-2 md:space-x-4 flex-1 min-w-0">
                <div class="bg-black p-2 rounded flex-shrink-0">
                  <i class="fas fa-receipt text-white text-sm md:text-base"></i>
                </div>
                <div class="min-w-0 flex-1">
                  <h3 class="text-base md:text-lg  text-gray-900 truncate">Order #${order.id}</h3>
                  <p class="text-xs md:text-sm text-gray-600 truncate">${order.customer_name}</p>
                  <p class="text-sm md:text-base  text-black">₱${totals.finalTotal}</p>
                </div>
              </div>
              <div class="flex flex-col md:flex-row items-end md:items-center gap-2 flex-shrink-0">
                ${statusBadge}
                ${paymentBadge}
                <i class="fas fa-chevron-down expand-icon text-gray-400"></i>
              </div>
            </div>
          </div>

          <div class="order-details">
            <div class="px-3 md:px-4 pb-3 md:pb-4">
              <div class="bg-gray-50 rounded-lg p-3 md:p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm md:text-base">
                      <i class="fas fa-user text-black mr-2"></i>
                      Customer Info
                    </h4>
                    <p class="text-gray-700 text-sm md:text-base break-words">${order.customer_name}</p>
                    <p class="text-gray-600 text-xs md:text-sm break-words">${order.email}</p>
                    <p class="text-gray-600 text-xs md:text-sm">${order.mobile}</p>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm md:text-base">
                      <i class="fas fa-map-marker-alt text-black mr-2"></i>
                      Delivery Address
                    </h4>
                    <p class="text-gray-700 text-sm md:text-base break-words">${order.address}</p>
                    <p class="text-gray-600 text-xs md:text-sm">ZIP: ${order.zipcode}</p>
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <div class="flex justify-between items-center mb-3">
                  <h4 class="font-semibold text-gray-900 flex items-center text-sm md:text-base">
                    <i class="fas fa-shopping-bag text-black mr-2"></i>
                    Order Items
                  </h4>
                </div>
                <div class="max-h-60 overflow-y-auto scrollbar-hide space-y-1 pr-1 bg-gray-50 rounded-lg p-2 md:p-3">
                  ${itemsHtml}
                </div>
              </div>

              <div class="mb-4">
  ${getVATBreakdownHTML(totals)}
</div>

              <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                  <a href="export_excel.php?order_id=${order.id}" 
                     class="bg-blue-600 hover:bg-blue-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                     target="_blank">
                    <i class="fas fa-file-excel"></i>
                    <span>Export</span>
                  </a>
                  ${order.has_replacement_requests ? `
                  <a href="replacement_requests.php?order_id=${order.id}" 
                     class="bg-orange-600 hover:bg-orange-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm" 
                     target="_blank">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Replacements (${order.replacement_count})</span>
                  </a>` : ''}
                </div>
                <div class="flex flex-wrap gap-2">
                  ${actionButtons}
                </div>
              </div>
            </div>
          </div>
        </div>`;
    });
  }

  function confirmOrder(orderId) {
    const btn = document.getElementById(`confirm-btn-${orderId}`);
    if (!btn) {
      showAlert('Button not found. Please refresh the page.', 'error');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span class="hidden sm:inline">Processing...</span>';

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
          currentBtn.innerHTML = '<i class="fas fa-check mr-2"></i><span class="hidden sm:inline">Confirm</span>';
        }
        if (currentRejectBtn) currentRejectBtn.disabled = false;
      });
  }

  function rejectOrder(orderId) {
    const btn = document.getElementById(`reject-btn-${orderId}`);
    if (!btn) {
      showAlert('Button not found. Please refresh the page.', 'error');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span class="hidden sm:inline">Processing...</span>';

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
          currentBtn.innerHTML = '<i class="fas fa-times mr-2"></i><span class="hidden sm:inline">Reject</span>';
        }
        if (currentConfirmBtn) currentConfirmBtn.disabled = false;
      });
  }

  document.addEventListener('DOMContentLoaded', loadOrders);
</script>