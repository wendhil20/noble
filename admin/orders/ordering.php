<?php
//ordering.php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Set session name/lvl if not set
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

include '../navbar/top.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Replacement Orders</title>
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
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    .order-item { transition: all 0.3s ease; }

    .order-details {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease-out;
    }

    .order-item.expanded .order-details {
      max-height: 5000px;
    }

    .expand-icon { transition: transform 0.3s ease; }
    .order-item.expanded .expand-icon { transform: rotate(180deg); }

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

    /* Replacement status timeline */
    .rep-status-step {
      position: relative;
    }
    .rep-status-step:not(:last-child)::after {
      content: '';
      position: absolute;
      left: 14px;
      top: 28px;
      bottom: -8px;
      width: 2px;
      background: #e5e7eb;
    }
    .rep-status-step.done:not(:last-child)::after {
      background: #f97316;
    }
  </style>
</head>

<body class="bg-gray-50 font-sans">

<div class="container mx-auto p-4 md:p-6">

  <!-- Page Header -->
  <div class="mb-6">
    <div class="flex items-center space-x-4">
      <div class="bg-orange-600 p-3 rounded-lg">
        <i class="fas fa-exchange-alt text-white text-2xl"></i>
      </div>
      <div>
        <h1 class="text-2xl md:text-3xl text-gray-800">Replacement Orders</h1>
        <p class="text-gray-600 text-sm mt-1">Orders with replacement or issue requests</p>
      </div>
    </div>
  </div>

  <!-- Stats + Refresh -->
  <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
    <div class="bg-white border border-orange-200 rounded-lg px-4 py-2">
      <span class="text-orange-700 text-sm font-medium" id="orderCount">Loading...</span>
    </div>
    <button onclick="loadOrders()" class="bg-black hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm w-fit">
      <i class="fas fa-sync-alt"></i>
      <span>Refresh</span>
    </button>
  </div>

  <!-- Alert Container -->
  <div id="alertContainer" class="mb-6"></div>

  <!-- Loading State -->
  <div id="loadingState" class="hidden">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
      <div class="flex items-center justify-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
        <span class="ml-3 text-gray-600">Loading replacement orders...</span>
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
        placeholder="Search by Order ID, customer name, email..."
        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white shadow-sm" />
    </div>
  </div>

  <!-- Orders Container -->
  <div id="ordersContainer" class="space-y-4"></div>

  <!-- Empty State -->
  <div id="emptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
    <div class="text-gray-400 mb-4">
      <i class="fas fa-exchange-alt text-6xl"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-900 mb-2">No Replacement Orders Found</h3>
    <p class="text-gray-600">There are currently no orders with replacement or issue requests.</p>
  </div>

</div>

<script>
  let allOrders = [];

  // ─── Status Config for Replacement Requests ───────────────────────────────
  const repStatusConfig = {
    pending: {
      label: 'Pending',
      icon: 'fas fa-hourglass-half',
      badge: 'bg-yellow-100 text-yellow-800 border-yellow-300',
      dot: 'bg-yellow-400',
    },
    approved: {
      label: 'Approved',
      icon: 'fas fa-check-circle',
      badge: 'bg-green-100 text-green-800 border-green-300',
      dot: 'bg-green-500',
    },
    processing: {
      label: 'Processing',
      icon: 'fas fa-cogs',
      badge: 'bg-blue-100 text-blue-800 border-blue-300',
      dot: 'bg-blue-500',
    },
    in_warehouse: {
      label: 'In Warehouse',
      icon: 'fas fa-warehouse',
      badge: 'bg-purple-100 text-purple-800 border-purple-300',
      dot: 'bg-purple-500',
    },
    delivered: {
      label: 'Delivered',
      icon: 'fas fa-box-open',
      badge: 'bg-emerald-100 text-emerald-800 border-emerald-300',
      dot: 'bg-emerald-500',
    },
    other: {
      label: 'Other',
      icon: 'fas fa-question-circle',
      badge: 'bg-gray-100 text-gray-700 border-gray-300',
      dot: 'bg-gray-400',
    },
  };

  function getRepStatusCfg(rawStatus) {
    const key = (rawStatus || 'pending').toLowerCase().replace(/\s+/g, '_');
    return repStatusConfig[key] || repStatusConfig['other'];
  }

  // ─── Reason label map ─────────────────────────────────────────────────────
  const reasonLabels = {
    defective:   { icon: 'fas fa-bug',           label: 'Defective',    color: 'text-red-600' },
    damaged:     { icon: 'fas fa-exclamation-triangle', label: 'Damaged', color: 'text-orange-600' },
    wrong_item:  { icon: 'fas fa-times-circle',  label: 'Wrong Item',   color: 'text-yellow-600' },
    wrong:       { icon: 'fas fa-times-circle',  label: 'Wrong',        color: 'text-yellow-600' },
  };

  function getReasonCfg(reason) {
    return reasonLabels[(reason || '').toLowerCase()] || { icon: 'fas fa-info-circle', label: reason || 'Unknown', color: 'text-gray-600' };
  }

  // ─── Build replacement requests HTML ─────────────────────────────────────
  function buildReplacementsHTML(order) {
    if (!order.replacements || order.replacements.length === 0) return '';

    const summary = order.replacement_status_summary || {};
    const allDelivered = order.all_replacements_delivered;

    // Status summary pills
    const summaryPills = Object.entries(summary)
      .filter(([, count]) => count > 0)
      .map(([status, count]) => {
        const cfg = repStatusConfig[status] || repStatusConfig['other'];
        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-semibold ${cfg.badge}">
          <i class="${cfg.icon} text-xs"></i>${cfg.label}: ${count}
        </span>`;
      }).join('');

    // Individual replacement cards
    const repCards = order.replacements.map((rep, idx) => {
      const cfg = getRepStatusCfg(rep.status);
      const reasonCfg = getReasonCfg(rep.reason);



      const adminNotesHTML = rep.admin_notes ? `
        <div class="mt-2 bg-yellow-50 border border-yellow-200 rounded p-2">
          <p class="text-xs text-yellow-800 font-medium"><i class="fas fa-sticky-note mr-1"></i>Admin Notes</p>
          <p class="text-xs text-yellow-700 mt-0.5">${rep.admin_notes}</p>
        </div>` : '';

      const warehouseHTML = (rep.warehouse_location || rep.po_number) ? `
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gray-600">
          ${rep.po_number         ? `<span><i class="fas fa-file-invoice mr-1 text-gray-400"></i><span class="font-medium">PO#:</span> ${rep.po_number}</span>` : ''}
          ${rep.warehouse_location? `<span><i class="fas fa-warehouse mr-1 text-gray-400"></i><span class="font-medium">Warehouse:</span> ${rep.warehouse_location}</span>` : ''}
        </div>` : '';

      const receivedHTML = rep.received_status ? `
        <div class="mt-1 text-xs">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${rep.received_status === 'received' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'} border">
            <i class="fas ${rep.received_status === 'received' ? 'fa-check' : 'fa-clock'}"></i>
            Received: ${rep.received_status}
            ${rep.received_at ? ' · ' + rep.received_at : ''}
          </span>
        </div>` : '';

      return `
        <div class="bg-white border border-gray-200 rounded-lg p-3 md:p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
          <!-- Card Header -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
              <div class="bg-orange-100 rounded-full w-7 h-7 flex items-center justify-center flex-shrink-0">
                <span class="text-orange-700 text-xs font-bold">#${idx + 1}</span>
              </div>
              <div>
                <p class="text-xs text-gray-500">Request ID: <span class="font-medium text-gray-700">${rep.id}</span></p>
                <p class="text-xs text-gray-400">Item ID: ${rep.order_item_id} · By: ${rep.user_email || '—'}</p>
              </div>
            </div>
            <!-- Status Badge -->
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-semibold ${cfg.badge}">
                <span class="w-2 h-2 rounded-full ${cfg.dot} inline-block"></span>
                <i class="${cfg.icon}"></i>
                ${cfg.label}
              </span>
            </div>
          </div>

          <!-- Reason + Qty -->
          <div class="flex flex-wrap gap-3 mb-2">
            <span class="flex items-center gap-1 text-xs font-medium ${reasonCfg.color}">
              <i class="${reasonCfg.icon}"></i>${reasonCfg.label}
            </span>
            <span class="flex items-center gap-1 text-xs text-gray-600">
              <i class="fas fa-redo-alt text-gray-400"></i>
              Qty: <strong>${rep.replacement_quantity || 1}</strong>
            </span>
          </div>

          <!-- Details -->
          ${rep.details ? `<p class="text-xs text-gray-600 bg-gray-50 rounded p-2 mb-2 italic">"${rep.details}"</p>` : ''}

          ${warehouseHTML}
          ${receivedHTML}
          ${adminNotesHTML}

          <!-- Footer timestamps -->
          <div class="mt-3 pt-2 border-t border-gray-100 flex flex-wrap gap-3 text-xs text-gray-400">
            ${rep.created_at ? `<span><i class="fas fa-calendar-plus mr-1"></i>Created: ${rep.created_at}</span>` : ''}
            ${rep.updated_at ? `<span><i class="fas fa-calendar-check mr-1"></i>Updated: ${rep.updated_at}</span>` : ''}
          </div>
        </div>`;
    }).join('');

    const deliveryBanner = allDelivered
      ? `<div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-3 py-2 text-sm font-medium mb-3">
           <i class="fas fa-check-double"></i> All replacement requests have been delivered.
         </div>`
      : '';

    return `
      <!-- ═══════════════════════════════════════════════
           REPLACEMENT REQUESTS SECTION
      ═══════════════════════════════════════════════ -->
      <div class="mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
          <h4 class="font-semibold text-gray-900 flex items-center text-sm md:text-base">
            <i class="fas fa-exchange-alt text-orange-500 mr-2"></i>
            Replacement Requests
            <span class="ml-2 bg-orange-200 text-orange-800 text-xs font-bold px-2 py-0.5 rounded-full">${order.replacement_count}</span>
          </h4>
          <!-- Status Summary Pills -->
          <div class="flex flex-wrap gap-1.5">
            ${summaryPills}
          </div>
        </div>

        ${deliveryBanner}

        <!-- Cards -->
        <div class="space-y-3">
          ${repCards}
        </div>
      </div>`;
  }

  function toggleOrderDetails(orderId) {
    const orderItem = document.getElementById(`order-${orderId}`);
    if (orderItem) orderItem.classList.toggle('expanded');
  }

  document.getElementById("orderSearch").addEventListener("input", function () {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll(".order-item").forEach(order => {
      order.style.display = order.textContent.toLowerCase().includes(keyword) ? "" : "none";
    });
  });

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
      <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-sm">
        <div class="flex items-center">
          <i class="${icons[type]} text-xl mr-3"></i>
          <p class="font-medium">${message}</p>
        </div>
      </div>`;
    setTimeout(() => alertContainer.innerHTML = '', 5000);
  }

  function calculateOrderTotal(order) {
    const subtotal    = parseFloat(order.subtotal)     || 0;
    const vatAmount   = parseFloat(order.vat_amount)   || 0;
    const deliveryFee = parseFloat(order.delivery_fee) || 0;
    const finalTotal  = parseFloat(order.total)        || 0;
    const itemsWithVAT = subtotal + vatAmount;

    return {
      itemsNetTotal:      subtotal.toFixed(2),
      itemsWithVAT:       itemsWithVAT.toFixed(2),
      totalDeliveryFees:  deliveryFee.toFixed(2),
      vatAmount:          vatAmount.toFixed(2),
      finalTotal:         finalTotal.toFixed(2),
      finalItemsWithVAT:  itemsWithVAT.toFixed(2),
      finalItemsNetTotal: subtotal.toFixed(2),
      finalDeliveryTotal: deliveryFee.toFixed(2),
      finalVATAmount:     vatAmount.toFixed(2),
    };
  }

  function getVATBreakdownHTML(totals) {
    return `
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-sm">
      <h5 class="font-semibold text-orange-800 mb-3 flex items-center">
        <i class="fas fa-calculator mr-2"></i>
        VAT Breakdown (12% VAT Rate)
      </h5>
      <div class="bg-white rounded-lg p-4 space-y-2 text-gray-700">
        <div class="flex justify-between items-center py-1 border-b border-orange-100">
          <span><i class="fas fa-box text-orange-500 mr-2 w-4"></i>Items Net:</span>
          <span class="font-semibold">₱${totals.finalItemsNetTotal}</span>
        </div>
        <div class="flex justify-between items-center py-1 border-b border-orange-100">
          <span><i class="fas fa-percent text-orange-500 mr-2 w-4"></i>VAT (12%):</span>
          <span class="font-semibold">+₱${totals.finalVATAmount}</span>
        </div>
        <div class="flex justify-between items-center py-1 border-b border-orange-100">
          <span><i class="fas fa-shopping-bag text-orange-500 mr-2 w-4"></i>Items + VAT:</span>
          <span class="font-semibold">₱${totals.finalItemsWithVAT}</span>
        </div>
        <div class="flex justify-between items-center py-1 border-b border-orange-100">
          <span><i class="fas fa-truck text-orange-500 mr-2 w-4"></i>Delivery Fee:</span>
          <span class="font-semibold">+₱${totals.finalDeliveryTotal}</span>
        </div>
        <div class="flex justify-between items-center pt-2 border-t-2 border-orange-300 font-bold text-base">
          <span><i class="fas fa-receipt text-orange-600 mr-2 w-4"></i>Grand Total:</span>
          <span class="text-orange-700">₱${totals.finalTotal}</span>
        </div>
      </div>
    </div>`;
  }

  function loadOrders() {
    const loadingState    = document.getElementById('loadingState');
    const ordersContainer = document.getElementById('ordersContainer');

    loadingState.classList.remove('hidden');
    ordersContainer.innerHTML = '';

    fetch('fetch_orders.php')
      .then(res => {
        if (!res.ok) throw new Error('Failed to fetch orders');
        return res.json();
      })
      .then(orders => {
        allOrders = orders.filter(order => order.has_replacement_requests === true);
        updateOrderCount();
        renderOrders();
      })
      .catch(error => {
        console.error('Error loading orders:', error);
        showAlert('Failed to load replacement orders. Please try again.', 'error');
      })
      .finally(() => loadingState.classList.add('hidden'));
  }

  function updateOrderCount() {
    const orderCount = document.getElementById('orderCount');
    orderCount.textContent = `${allOrders.length} Replacement Order${allOrders.length !== 1 ? 's' : ''} Found`;
  }

  function renderOrders() {
    const container  = document.getElementById('ordersContainer');
    const emptyState = document.getElementById('emptyState');

    if (allOrders.length === 0) {
      container.innerHTML = '';
      emptyState.classList.remove('hidden');
      return;
    }

    emptyState.classList.add('hidden');
    container.innerHTML = '';

    allOrders.forEach(order => {
      const totals        = calculateOrderTotal(order);
      const status        = (order.status || 'pending').toLowerCase();
      const paymentStatus = (order.payment_status || '').toLowerCase();

      const statusConfig = {
        ongoing:  { class: 'bg-green-100 text-green-800 border-green-200',  icon: 'fas fa-truck',       text: 'Ongoing'  },
        pending:  { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: 'fas fa-clock',      text: 'Pending'  },
        rejected: { class: 'bg-red-100 text-red-800 border-red-200',         icon: 'fas fa-times-circle', text: 'Rejected' }
      };

      const paymentStatusConfig = {
        verified: { class: 'bg-blue-100 text-blue-800 border-blue-200',   icon: 'fas fa-check-circle',  text: 'Payment Verified' },
        pending:  { class: 'bg-orange-100 text-orange-800 border-orange-200', icon: 'fas fa-hourglass-half', text: 'Payment Pending' },
        rejected: { class: 'bg-red-100 text-red-800 border-red-200',       icon: 'fas fa-ban',           text: 'Payment Rejected' }
      };

      const statusBadge = statusConfig[status] ? `
        <div class="flex items-center space-x-1 ${statusConfig[status].class} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
          <i class="${statusConfig[status].icon}"></i>
          <span class="hidden sm:inline">${statusConfig[status].text}</span>
        </div>` : '';

      const paymentBadge = paymentStatusConfig[paymentStatus] ? `
        <div class="flex items-center space-x-1 ${paymentStatusConfig[paymentStatus].class} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
          <i class="${paymentStatusConfig[paymentStatus].icon}"></i>
          <span class="hidden sm:inline">${paymentStatusConfig[paymentStatus].text}</span>
        </div>` : '';

      const itemsHtml = order.items.map(item => `
        <div class="flex flex-col md:flex-row md:justify-between md:items-start py-3 border-b border-gray-100 last:border-b-0 gap-3">
          <div class="flex-1">
            <div class="flex items-start space-x-3">
              <div class="bg-orange-100 p-2 rounded-lg flex-shrink-0">
                <i class="fas fa-box text-orange-600"></i>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-gray-900 break-words">${item.product_name}</h4>
                <p class="text-sm text-gray-600">
                  <span class="font-medium">Size:</span> ${item.size},
                  <span class="font-medium">Color:</span> ${item.variant_color}
                </p>
                <p class="text-xs text-gray-500 break-words">
                  <span class="font-medium">Code:</span> ${item.codename}
                  ${item.descrip6 || item.descrip7 ? ' • ' + (item.descrip6 || '') + ' ' + (item.descrip7 || '') : ''}
                </p>
                <p class="text-xs text-blue-600 font-medium">
                  <i class="fas fa-map-marker-alt mr-1"></i>${item.origin || 'Not specified'}
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
          <div class="text-right flex-shrink-0">
            <div class="text-sm text-gray-600 mb-1">₱${item.price} × ${item.quantity}</div>
            <div class="font-semibold text-gray-900">
              ₱${item.subtotal}
              <br><span class="text-xs text-blue-600">(VAT inc.)</span>
            </div>
          </div>
        </div>`).join('');

      // Build the replacement requests HTML for this order
      const replacementsHTML = buildReplacementsHTML(order);

      container.innerHTML += `
        <div class="order-item bg-white rounded-xl border-2 border-orange-300 shadow-sm hover:shadow-md transition-all duration-200" id="order-${order.id}">

          <!-- Replacement Badge Banner -->
          <div class="bg-orange-50 border-b border-orange-200 px-3 md:px-4 py-2 rounded-t-xl flex items-center justify-between">
            <span class="flex items-center space-x-2 text-orange-700 font-semibold text-sm">
              <i class="fas fa-exclamation-triangle text-orange-500"></i>
              <span>Has Replacement / Issue Request${order.replacement_count > 1 ? 's' : ''}</span>
            </span>
            <span class="bg-orange-200 text-orange-800 text-xs font-bold px-2 py-1 rounded-full">
              ${order.replacement_count} Issue${order.replacement_count > 1 ? 's' : ''}
            </span>
          </div>

          <!-- Order Header (clickable to expand) -->
          <div class="order-header p-3 md:p-4" onclick="toggleOrderDetails(${order.id})">
            <div class="flex justify-between items-center gap-2">
              <div class="flex items-center space-x-2 md:space-x-4 flex-1 min-w-0">
                <div class="bg-black p-2 rounded flex-shrink-0">
                  <i class="fas fa-receipt text-white text-sm md:text-base"></i>
                </div>
                <div class="min-w-0 flex-1">
                  <h3 class="text-base md:text-lg text-gray-900 truncate">Order #${order.id}</h3>
                  <p class="text-xs md:text-sm text-gray-600 truncate">${order.customer_name}</p>
                  <p class="text-sm md:text-base font-semibold text-black">₱${totals.finalTotal}</p>
                </div>
              </div>
              <div class="flex flex-col md:flex-row items-end md:items-center gap-2 flex-shrink-0">
                ${statusBadge}
                ${paymentBadge}
                <i class="fas fa-chevron-down expand-icon text-gray-400"></i>
              </div>
            </div>
          </div>

          <!-- Expandable Details -->
          <div class="order-details">
            <div class="px-3 md:px-4 pb-3 md:pb-4">

              <!-- Customer & Delivery Info -->
              <div class="bg-gray-50 rounded-lg p-3 md:p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm md:text-base">
                      <i class="fas fa-user text-orange-500 mr-2"></i>Customer Info
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.customer_name}</p>
                    <p class="text-gray-600 text-xs break-words">${order.email}</p>
                    <p class="text-gray-600 text-xs">${order.mobile}</p>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm md:text-base">
                      <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>Delivery Address
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.address}</p>
                    <p class="text-gray-600 text-xs">ZIP: ${order.zipcode}</p>
                  </div>
                </div>
              </div>

              <!-- Order Items -->
              <div class="mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center text-sm md:text-base mb-3">
                  <i class="fas fa-shopping-bag text-orange-500 mr-2"></i>Order Items
                </h4>
                <div class="max-h-60 overflow-y-auto scrollbar-hide space-y-1 pr-1 bg-gray-50 rounded-lg p-2 md:p-3">
                  ${itemsHtml}
                </div>
              </div>

              <!-- ✅ REPLACEMENT REQUESTS (with full status) -->
              ${replacementsHTML}

              <!-- VAT Breakdown -->
              <div class="mb-4">
                ${getVATBreakdownHTML(totals)}
              </div>

              <!-- Action Buttons -->
              <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                <a href="export_excel.php?order_id=${order.id}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm"
                   target="_blank">
                  <i class="fas fa-file-excel"></i>
                  <span>Export Excel</span>
                </a>
                <a href="replacement_requests.php?order_id=${order.id}"
                   class="bg-orange-600 hover:bg-orange-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-sm text-sm"
                   target="_blank">
                  <i class="fas fa-exchange-alt"></i>
                  <span>View Replacements (${order.replacement_count})</span>
                </a>
              </div>

            </div>
          </div>
        </div>`;
    });
  }

  document.addEventListener('DOMContentLoaded', loadOrders);
</script>

</body>
</html>