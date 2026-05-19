<?php
// orders_unified.php - Unified Orders + Replacement Orders (single fetch)
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
include ROOT_PATH . "/admin/navbar/top.php";
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: " . BASE_URL . "/main");
  exit();
}

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

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders</title>
</head>
<style>
  /* ── Scrollbar ── */
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }

  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }

  /* ── Order accordion ── */
  .order-item {
    transition: all 0.3s ease;
  }

  .order-details {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.45s ease-out;
  }

  .order-item.expanded .order-details {
    max-height: 5000px;
  }

  .expand-icon {
    transition: transform 0.3s ease;
  }

  .order-item.expanded .expand-icon {
    transform: rotate(180deg);
  }

  .order-header {
    cursor: pointer;
    transition: background 0.2s ease;
  }

  .order-header:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  }

  .order-item.expanded .order-header {
    border-bottom: 1px solid #e5e7eb;
  }

  /* ── Tab active ── */
  .tab-btn.active {
    background: #000;
    color: #fff;
    border-color: #000;
  }

  .tab-btn {
    transition: all 0.2s ease;
  }

  /* ── Filter btn active ── */
  .filter-btn.active,
  .replacement-filter-btn.active {
    background: #fed7aa !important;
    color: #c2410c !important;
    border-color: #fb923c !important;
  }
</style>

<body>

  <div class="max-w-7xl mx-auto">
    <div class="border border-gray-200 mb-6 font-roboto">
      <div class="p-4 md:p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
          <div class="flex items-center space-x-4">
            <div class="bg-black p-3 rounded-lg">
              <i class="fas fa-shopping-cart text-white text-2xl"></i>
            </div>
            <div>
              <h2 class="text-2xl md:text-3xl text-gray-900">Orders</h2>
              <p class="text-gray-600 text-sm mt-1">Manage pending orders and replacement requests</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <div class="px-4 py-2">
              <span class="text-black text-xs" id="orderCount">Loading...</span>
            </div>
            <button onclick="loadOrders()"
              class="bg-black hover:bg-orange-700 text-white px-4 py-2 rounded transition-colors duration-200 flex items-center space-x-2 shadow-sm">
              <i class="fas fa-sync-alt"></i>
              <span>Refresh</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert -->
    <div id="alertContainer" class="mb-6"></div>

    <!-- Loading -->
    <div id="loadingState" class="hidden mb-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
          <span class="ml-3 text-gray-600">Loading orders...</span>
        </div>
      </div>
    </div>


    <div class="flex gap-2 mb-6">
      <button class="tab-btn active border px-5 py-2 rounded-lg text-sm font-medium" id="tab-all"
        onclick="switchTab('all')">
        <i class="fas fa-list mr-1"></i> All Orders
        <span class="ml-1 bg-gray-200 text-gray-700 text-xs px-1.5 py-0.5 rounded-full" id="tab-all-count">0</span>
      </button>
      <button class="tab-btn border border-gray-300 text-gray-700 px-5 py-2 rounded-lg text-sm font-medium"
        id="tab-replacements" onclick="switchTab('replacements')">
        <i class="fas fa-exchange-alt mr-1"></i> Replacements
        <span class="ml-1 bg-orange-200 text-orange-800 text-xs px-1.5 py-0.5 rounded-full" id="tab-rep-count">0</span>
      </button>
    </div>


    <div id="panel-all">
      <!-- Status Filter -->
      <div class="bg-white p-4 mb-6 border border-gray-200">
        <div class="flex items-center gap-3 flex-wrap">

          <div class="flex items-center gap-2 flex-1 min-w-[160px]">
            <i class="fas fa-filter text-gray-400 text-sm flex-shrink-0"></i>
            <label class="text-gray-600 text-sm whitespace-nowrap flex-shrink-0">Status</label>
            <select onchange="filterOrders(this.value)"
              class="flex-1 text-sm px-3 py-2 border border-gray-300 bg-gray-50 text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
              id="statusSelect">
              <option value="all">All</option>
              <option value="pending">Pending</option>
              <option value="ongoing">Ongoing</option>
              <option value="verified">Verified</option>
            </select>
          </div>

          <div class="w-px h-8 bg-gray-200 flex-shrink-0 hidden sm:block"></div>

          <div class="flex items-center gap-2 flex-1 min-w-[180px]">
            <i class="fas fa-exchange-alt text-gray-400 text-sm flex-shrink-0"></i>
            <label class="text-gray-600 text-sm whitespace-nowrap flex-shrink-0">Replacements</label>
            <select onchange="filterByReplacement(this.value)"
              class="flex-1 text-sm px-3 py-2 border border-gray-300 bg-gray-50 text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
              id="replacementSelect">
              <option value="all">All</option>
              <option value="with_replacements">Has Issues</option>
              <option value="no_replacements">No Issues</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Search -->
      <div class="mb-6">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
          </div>
          <input type="text" id="orderSearch" placeholder="Search orders by ID, customer name, email..."
            class="w-full pl-10 pr-4 py-3 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white shadow-sm" />
        </div>
      </div>

      <div id="ordersContainer" class="space-y-4"></div>
      <div id="emptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="text-gray-400 mb-4"><i class="fas fa-shopping-cart text-6xl"></i></div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Orders Found</h3>
        <p class="text-gray-600">There are no orders matching your filters.</p>
      </div>
    </div>

    <div id="panel-replacements" class="hidden">
      <!-- Search -->
      <div class="mb-6">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
          </div>
          <input type="text" id="repSearch" placeholder="Search by Order ID, customer name, email..."
            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white shadow-sm" />
        </div>
      </div>

      <div id="repContainer" class="space-y-4"></div>
      <div id="repEmptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="text-gray-400 mb-4"><i class="fas fa-exchange-alt text-6xl"></i></div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Replacement Orders Found</h3>
        <p class="text-gray-600">There are currently no orders with replacement or issue requests.</p>
      </div>
    </div>
  </div>

  <script>
    // ── State 
    let allOrders = [];
    let currentTab = 'all';
    let currentFilter = 'all';
    let currentReplacementFilter = 'all';

    // ── Tab switching 
    function switchTab(tab) {
      currentTab = tab;
      ['all', 'replacements'].forEach(t => {
        document.getElementById(`panel-${t}`).classList.toggle('hidden', t !== tab);
        document.getElementById(`tab-${t}`).classList.toggle('active', t === tab);
      });
    }

    // ── Search (All Orders) 
    document.getElementById("orderSearch").addEventListener("input", function () {
      const kw = this.value.toLowerCase();
      document.querySelectorAll("#ordersContainer .order-item").forEach(el => {
        el.style.display = el.textContent.toLowerCase().includes(kw) ? "" : "none";
      });
    });

    // ── Search (Replacements) 
    document.getElementById("repSearch").addEventListener("input", function () {
      const kw = this.value.toLowerCase();
      document.querySelectorAll("#repContainer .order-item").forEach(el => {
        el.style.display = el.textContent.toLowerCase().includes(kw) ? "" : "none";
      });
    });

    // ── Accordion toggle 
    function toggleOrderDetails(orderId) {
      const el = document.getElementById(`order-${orderId}`);
      if (el) el.classList.toggle('expanded');
    }
    function toggleRepDetails(orderId) {
      const el = document.getElementById(`rep-order-${orderId}`);
      if (el) el.classList.toggle('expanded');
    }

    // ── Alert 
    function showAlert(message, type = 'info') {
      const icons = { success: 'fas fa-check-circle', error: 'fas fa-exclamation-circle', info: 'fas fa-info-circle' };
      const colors = { success: 'bg-green-50 border-green-200 text-green-800', error: 'bg-red-50 border-red-200 text-red-800', info: 'bg-blue-50 border-blue-200 text-blue-800' };
      document.getElementById('alertContainer').innerHTML = `
      <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-sm animate-pulse">
        <div class="flex items-center">
          <i class="${icons[type]} text-xl mr-3"></i>
          <p class="font-medium">${message}</p>
        </div>
      </div>`;
      setTimeout(() => document.getElementById('alertContainer').innerHTML = '', 5000);
    }

    // ── Totals 
    function calculateOrderTotal(order) {
      const subtotal = parseFloat(order.subtotal) || 0;
      const vatAmount = parseFloat(order.vat_amount) || 0;
      const deliveryFee = parseFloat(order.delivery_fee) || 0;
      const finalTotal = parseFloat(order.total) || 0;
      const itemsWithVAT = subtotal + vatAmount;
      return {
        itemsNetTotal: subtotal.toFixed(2),
        itemsWithVAT: itemsWithVAT.toFixed(2),
        totalDeliveryFees: deliveryFee.toFixed(2),
        vatAmount: vatAmount.toFixed(2),
        finalTotal: finalTotal.toFixed(2),
        finalItemsWithVAT: itemsWithVAT.toFixed(2),
        finalItemsNetTotal: subtotal.toFixed(2),
        finalDeliveryTotal: deliveryFee.toFixed(2),
        finalVATAmount: vatAmount.toFixed(2),
      };
    }

    function getVATBreakdownHTML(totals, dark = false) {
      const bg = dark ? 'bg-orange-50 border-orange-200' : 'bg-white border-gray-200';
      const head = dark ? 'text-orange-800' : 'text-black';
      const icon = dark ? 'text-orange-500' : 'text-black';
      return `
    <div class="${bg} border rounded-lg p-4 text-sm">
      <h5 class="font-semibold ${head} mb-3 flex items-center">
        <i class="fas fa-calculator ${icon} mr-2"></i>VAT Breakdown (12%)
      </h5>
      <div class="bg-white rounded-lg p-4 space-y-2 text-gray-700">
        <div class="flex justify-between py-1 border-b border-gray-100">
          <span><i class="fas fa-box ${icon} mr-2 w-4"></i>Items Net:</span>
          <span class="font-semibold">₱${totals.finalItemsNetTotal}</span>
        </div>
        <div class="flex justify-between py-1 border-b border-gray-100">
          <span><i class="fas fa-percent ${icon} mr-2 w-4"></i>VAT (12%):</span>
          <span class="font-semibold">+₱${totals.finalVATAmount}</span>
        </div>
        <div class="flex justify-between py-1 border-b border-gray-100">
          <span><i class="fas fa-shopping-bag ${icon} mr-2 w-4"></i>Items + VAT:</span>
          <span class="font-semibold">₱${totals.finalItemsWithVAT}</span>
        </div>
        <div class="flex justify-between py-1 border-b border-gray-100">
          <span><i class="fas fa-truck ${icon} mr-2 w-4"></i>Delivery Fee:</span>
          <span class="font-semibold">+₱${totals.finalDeliveryTotal}</span>
        </div>
        <div class="flex justify-between pt-2 border-t-2 border-gray-300 font-bold text-base">
          <span><i class="fas fa-receipt ${icon} mr-2 w-4"></i>Grand Total:</span>
          <span class="${dark ? 'text-orange-700' : 'text-black'}">₱${totals.finalTotal}</span>
        </div>
      </div>
    </div>`;
    }

    // ── Items HTML  
    function buildItemsHTML(items, accent = 'black') {
      return items.map(item => `
      <div class="flex flex-col md:flex-row md:justify-between md:items-start py-3 border-b border-gray-100 last:border-b-0 gap-3">
        <div class="flex-1">
          <div class="flex items-start space-x-3">
            <div class="bg-gray-100 p-2 rounded-lg flex-shrink-0">
              <i class="fas fa-box text-${accent}"></i>
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
                <i class="fas fa-map-marker-alt mr-1"></i>${item.origin || 'Not specified'}
              </p>
              <p class="text-xs ${item.is_manual_supplier ? 'text-purple-600' : 'text-green-600'} font-medium break-words">
                <i class="fas ${item.is_manual_supplier ? 'fa-edit' : 'fa-user-tie'} mr-1"></i>
                ${item.supplier_name || 'Not Assigned'}
                ${item.is_manual_supplier ? ' (Manual)' : item.is_database_supplier ? ' (Database)' : ''}
              </p>
              ${item.delivery_fee_per_item > 0 ? `
              <p class="text-xs text-orange-600 font-medium">
                <i class="fas fa-truck mr-1"></i>Delivery: ₱${item.delivery_fee_per_item} × ${item.quantity} = ₱${item.item_total_delivery}
              </p>` : ''}
            </div>
          </div>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="text-sm text-gray-600 mb-1">₱${item.price} × ${item.quantity}</div>
          <div class="font-semibold text-gray-900">
            ₱${item.subtotal}<br><span class="text-xs text-blue-600">(VAT inc.)</span>
          </div>
        </div>
      </div>`).join('');
    }

    // ── Status badge helpers  
    function orderStatusBadge(status) {
      const cfg = {
        ongoing: { cls: 'bg-green-100 text-green-800 border-green-200', icon: 'fas fa-truck', text: 'Ongoing' },
        pending: { cls: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: 'fas fa-clock', text: 'Pending' },
        rejected: { cls: 'bg-red-100 text-red-800 border-red-200', icon: 'fas fa-times-circle', text: 'Rejected' },
      }[status];
      if (!cfg) return '';
      return `<div class="flex items-center space-x-1 ${cfg.cls} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
      <i class="${cfg.icon}"></i><span class="hidden sm:inline">${cfg.text}</span></div>`;
    }

    function paymentStatusBadge(ps) {
      const cfg = {
        verified: { cls: 'bg-blue-100 text-blue-800 border-blue-200', icon: 'fas fa-check-circle', text: 'Payment Verified' },
        pending: { cls: 'bg-orange-100 text-orange-800 border-orange-200', icon: 'fas fa-hourglass-half', text: 'Payment Pending' },
        rejected: { cls: 'bg-red-100 text-red-800 border-red-200', icon: 'fas fa-ban', text: 'Payment Rejected' },
      }[ps];
      if (!cfg) return '';
      return `<div class="flex items-center space-x-1 ${cfg.cls} px-2 md:px-3 py-1 rounded-full border text-xs md:text-sm font-medium">
      <i class="${cfg.icon}"></i><span class="hidden sm:inline">${cfg.text}</span></div>`;
    }

    // ── Replacement request status config ─────────────
    const repStatusConfig = {
      pending: { label: 'Pending', icon: 'fas fa-hourglass-half', badge: 'bg-yellow-100 text-yellow-800 border-yellow-300', dot: 'bg-yellow-400' },
      approved: { label: 'Approved', icon: 'fas fa-check-circle', badge: 'bg-green-100 text-green-800 border-green-300', dot: 'bg-green-500' },
      processing: { label: 'Processing', icon: 'fas fa-cogs', badge: 'bg-blue-100 text-blue-800 border-blue-300', dot: 'bg-blue-500' },
      in_warehouse: { label: 'In Warehouse', icon: 'fas fa-warehouse', badge: 'bg-purple-100 text-purple-800 border-purple-300', dot: 'bg-purple-500' },
      delivered: { label: 'Delivered', icon: 'fas fa-box-open', badge: 'bg-emerald-100 text-emerald-800 border-emerald-300', dot: 'bg-emerald-500' },
      other: { label: 'Other', icon: 'fas fa-question-circle', badge: 'bg-gray-100 text-gray-700 border-gray-300', dot: 'bg-gray-400' },
    };
    function getRepStatusCfg(s) {
      const key = (s || 'pending').toLowerCase().replace(/\s+/g, '_');
      return repStatusConfig[key] || repStatusConfig['other'];
    }

    const reasonLabels = {
      defective: { icon: 'fas fa-bug', label: 'Defective', color: 'text-red-600' },
      damaged: { icon: 'fas fa-exclamation-triangle', label: 'Damaged', color: 'text-orange-600' },
      wrong_item: { icon: 'fas fa-times-circle', label: 'Wrong Item', color: 'text-yellow-600' },
      wrong: { icon: 'fas fa-times-circle', label: 'Wrong', color: 'text-yellow-600' },
    };
    function getReasonCfg(r) {
      return reasonLabels[(r || '').toLowerCase()] || { icon: 'fas fa-info-circle', label: r || 'Unknown', color: 'text-gray-600' };
    }

    function buildReplacementsHTML(order) {
      if (!order.replacements || order.replacements.length === 0) return '';
      const summary = order.replacement_status_summary || {};

      const summaryPills = Object.entries(summary)
        .filter(([, c]) => c > 0)
        .map(([status, count]) => {
          const cfg = repStatusConfig[status] || repStatusConfig['other'];
          return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-semibold ${cfg.badge}">
          <i class="${cfg.icon} text-xs"></i>${cfg.label}: ${count}</span>`;
        }).join('');

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
          ${rep.po_number ? `<span><i class="fas fa-file-invoice mr-1 text-gray-400"></i><span class="font-medium">PO#:</span> ${rep.po_number}</span>` : ''}
          ${rep.warehouse_location ? `<span><i class="fas fa-warehouse mr-1 text-gray-400"></i><span class="font-medium">Warehouse:</span> ${rep.warehouse_location}</span>` : ''}
        </div>` : '';
        const receivedHTML = rep.received_status ? `
        <div class="mt-1 text-xs">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${rep.received_status === 'received' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'} border">
            <i class="fas ${rep.received_status === 'received' ? 'fa-check' : 'fa-clock'}"></i>
            Received: ${rep.received_status}${rep.received_at ? ' · ' + rep.received_at : ''}
          </span>
        </div>` : '';
        return `
        <div class="bg-white border border-gray-200 rounded-lg p-3 md:p-4 shadow-sm">
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
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-semibold ${cfg.badge}">
              <span class="w-2 h-2 rounded-full ${cfg.dot} inline-block"></span>
              <i class="${cfg.icon}"></i>${cfg.label}
            </span>
          </div>
          <div class="flex flex-wrap gap-3 mb-2">
            <span class="flex items-center gap-1 text-xs font-medium ${reasonCfg.color}">
              <i class="${reasonCfg.icon}"></i>${reasonCfg.label}
            </span>
            <span class="flex items-center gap-1 text-xs text-gray-600">
              <i class="fas fa-redo-alt text-gray-400"></i>Qty: <strong>${rep.replacement_quantity || 1}</strong>
            </span>
          </div>
          ${rep.details ? `<p class="text-xs text-gray-600 bg-gray-50 rounded p-2 mb-2 italic">"${rep.details}"</p>` : ''}
          ${warehouseHTML}${receivedHTML}${adminNotesHTML}
          <div class="mt-3 pt-2 border-t border-gray-100 flex flex-wrap gap-3 text-xs text-gray-400">
            ${rep.created_at ? `<span><i class="fas fa-calendar-plus mr-1"></i>Created: ${rep.created_at}</span>` : ''}
            ${rep.updated_at ? `<span><i class="fas fa-calendar-check mr-1"></i>Updated: ${rep.updated_at}</span>` : ''}
          </div>
        </div>`;
      }).join('');

      const deliveryBanner = order.all_replacements_delivered
        ? `<div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-3 py-2 text-sm font-medium mb-3">
           <i class="fas fa-check-double"></i> All replacement requests have been delivered.
         </div>` : '';

      return `
      <div class="mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
          <h4 class="font-semibold text-gray-900 flex items-center text-sm md:text-base">
            <i class="fas fa-exchange-alt text-orange-500 mr-2"></i>
            Replacement Requests
            <span class="ml-2 bg-orange-200 text-orange-800 text-xs font-bold px-2 py-0.5 rounded-full">${order.replacement_count}</span>
          </h4>
          <div class="flex flex-wrap gap-1.5">${summaryPills}</div>
        </div>
        ${deliveryBanner}
        <div class="space-y-3">${repCards}</div>
      </div>`;
    }

    // ── Filters ───────────────────────────────────────
    function filterOrders(status) {
      currentFilter = status;
      const sel = document.getElementById('statusSelect');
      if (sel) sel.value = status;
      renderAllOrders();
    }

    function filterByReplacement(repStatus) {
      currentReplacementFilter = repStatus;
      const sel = document.getElementById('replacementSelect');
      if (sel) sel.value = repStatus;
      renderAllOrders();
    }

    // ── LOAD (single fetch) ───────────────────────────
    function loadOrders() {
      document.getElementById('loadingState').classList.remove('hidden');
      document.getElementById('ordersContainer').innerHTML = '';
      document.getElementById('repContainer').innerHTML = '';

      fetch('<?= BASE_URL ?>/fetchsales')
        .then(res => { if (!res.ok) throw new Error('Failed to fetch'); return res.json(); })
        .then(orders => {
          allOrders = orders;
          updateOrderCount();
          renderAllOrders();
          renderReplacementOrders();
        })
        .catch(err => {
          console.error(err);
          showAlert('Failed to load orders. Please try again.', 'error');
        })
        .finally(() => document.getElementById('loadingState').classList.add('hidden'));
    }

    function updateOrderCount() {
      const total = allOrders.length;
      const pending = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'pending').length;
      const ongoing = allOrders.filter(o => (o.status || 'pending').toLowerCase() === 'ongoing').length;
      const verified = allOrders.filter(o => (o.payment_status || '').toLowerCase() === 'verified').length;
      const rejected = allOrders.filter(o => (o.status || '').toLowerCase() === 'rejected' || (o.payment_status || '').toLowerCase() === 'rejected').length;
      const withRep = allOrders.filter(o => o.has_replacement_requests === true).length;

      document.getElementById('orderCount').textContent =
        `${total} Total • ${pending} Pending • ${ongoing} Ongoing • ${verified} Verified • ${rejected} Rejected • ${withRep} With Replacements`;
      document.getElementById('tab-all-count').textContent = total;
      document.getElementById('tab-rep-count').textContent = withRep;
    }

    // ── RENDER: All Orders ────────────────────────────
    function renderAllOrders() {
      const container = document.getElementById('ordersContainer');
      const emptyState = document.getElementById('emptyState');
      let filtered = allOrders;

      if (currentFilter !== 'all') {
        if (currentFilter === 'verified') {
          filtered = filtered.filter(o => (o.payment_status || '').toLowerCase() === 'verified');
        } else if (currentFilter === 'rejected') {
          filtered = filtered.filter(o =>
            (o.status || '').toLowerCase() === 'rejected' ||
            (o.payment_status || '').toLowerCase() === 'rejected');
        } else {
          filtered = filtered.filter(o => (o.status || 'pending').toLowerCase() === currentFilter);
        }
      }

      if (currentReplacementFilter === 'with_replacements') {
        filtered = filtered.filter(o => o.has_replacement_requests === true);
      } else if (currentReplacementFilter === 'no_replacements') {
        filtered = filtered.filter(o => o.has_replacement_requests !== true);
      }

      if (filtered.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }
      emptyState.classList.add('hidden');
      container.innerHTML = '';

      filtered.forEach(order => {
        const totals = calculateOrderTotal(order);
        const status = (order.status || 'pending').toLowerCase();
        const ps = (order.payment_status || '').toLowerCase();
        const itemsHtml = buildItemsHTML(order.items);
        const replacementsHTML = order.has_replacement_requests ? buildReplacementsHTML(order) : '';

        let actionButtons = '';
        if (ps === 'verified' && status === 'pending') {
          actionButtons = `
          <button onclick="confirmOrder(${order.id})" id="confirm-btn-${order.id}"
            class="bg-green-600 hover:bg-green-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors flex items-center space-x-2 shadow-sm text-sm">
            <i class="fas fa-check"></i><span class="hidden sm:inline">Confirm</span>
          </button>
          <button onclick="rejectOrder(${order.id})" id="reject-btn-${order.id}"
            class="bg-red-600 hover:bg-red-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors flex items-center space-x-2 shadow-sm text-sm">
            <i class="fas fa-times"></i><span class="hidden sm:inline">Reject</span>
          </button>`;
        }

        container.innerHTML += `
        <div class="order-item bg-white border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200" id="order-${order.id}">
          <div class="order-header p-3 md:p-4" onclick="toggleOrderDetails(${order.id})">
            <div class="flex justify-between items-center gap-2">
              <div class="flex items-center space-x-2 md:space-x-4 flex-1 min-w-0">
                <div class="bg-black p-2 rounded flex-shrink-0">
                  <i class="fas fa-receipt text-white text-sm md:text-base"></i>
                </div>
                <div class="min-w-0 flex-1">
                  <h3 class="text-base md:text-lg text-gray-900 truncate">Order #${order.id}</h3>
                  <p class="text-xs md:text-sm text-gray-600 truncate">${order.customer_name}</p>
                  <p class="text-sm md:text-base text-black">₱${totals.finalTotal}</p>
                </div>
              </div>
              <div class="flex flex-col md:flex-row items-end md:items-center gap-2 flex-shrink-0">
                ${orderStatusBadge(status)}
                ${paymentStatusBadge(ps)}
                ${order.has_replacement_requests ? `
                <span class="bg-orange-100 text-orange-700 border border-orange-300 px-2 py-0.5 rounded-full text-xs font-medium">
                  <i class="fas fa-exchange-alt mr-1"></i>${order.replacement_count}
                </span>` : ''}
                <i class="fas fa-chevron-down expand-icon text-gray-400"></i>
              </div>
            </div>
          </div>

          <div class="order-details">
            <div class="px-3 md:px-4 pb-3 md:pb-4">
              <!-- Customer / Address -->
              <div class="bg-gray-50 rounded-lg p-3 md:p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm">
                      <i class="fas fa-user text-black mr-2"></i>Customer Info
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.customer_name}</p>
                    <p class="text-gray-600 text-xs break-words">${order.email}</p>
                    <p class="text-gray-600 text-xs">${order.mobile}</p>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm">
                      <i class="fas fa-map-marker-alt text-black mr-2"></i>Delivery Address
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.address}</p>
                    <p class="text-gray-600 text-xs">ZIP: ${order.zipcode}</p>
                  </div>
                </div>
              </div>

              <!-- Items -->
              <div class="mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center text-sm mb-3">
                  <i class="fas fa-shopping-bag text-black mr-2"></i>Order Items
                </h4>
                <div class="max-h-60 overflow-y-auto scrollbar-hide bg-gray-50 rounded-lg p-2 md:p-3">
                  ${itemsHtml}
                </div>
              </div>

              <!-- Replacements (if any) -->
              ${replacementsHTML}

              <!-- VAT Breakdown -->
              <div class="mb-4">${getVATBreakdownHTML(totals)}</div>

              <!-- Actions -->
              <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                  ${order.has_replacement_requests ? `
                  <a href="<?= BASE_URL ?>/replacementrequests?order_id=${order.id}" target="_blank"
                    class="bg-orange-600 hover:bg-orange-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors flex items-center space-x-2 shadow-sm text-sm">
                    <i class="fas fa-exchange-alt"></i><span>Replacements (${order.replacement_count})</span>
                  </a>` : ''}
                </div>
                <div class="flex flex-wrap gap-2">${actionButtons}</div>
              </div>
            </div>
          </div>
        </div>`;
      });
    }

    // ── RENDER: Replacement Orders ────────────────────
    function renderReplacementOrders() {
      const container = document.getElementById('repContainer');
      const emptyState = document.getElementById('repEmptyState');
      const repOrders = allOrders.filter(o => o.has_replacement_requests === true);

      if (repOrders.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }
      emptyState.classList.add('hidden');
      container.innerHTML = '';

      repOrders.forEach(order => {
        const totals = calculateOrderTotal(order);
        const status = (order.status || 'pending').toLowerCase();
        const ps = (order.payment_status || '').toLowerCase();
        const itemsHtml = buildItemsHTML(order.items, 'orange-600');
        const replacementsHTML = buildReplacementsHTML(order);

        container.innerHTML += `
        <div class="order-item bg-white rounded-xl border-2 border-orange-300 shadow-sm hover:shadow-md transition-all duration-200" id="rep-order-${order.id}">

          <!-- Banner -->
          <div class="bg-orange-50 border-b border-orange-200 px-3 md:px-4 py-2 rounded-t-xl flex items-center justify-between">
            <span class="flex items-center space-x-2 text-orange-700 font-semibold text-sm">
              <i class="fas fa-exclamation-triangle text-orange-500"></i>
              <span>Has Replacement / Issue Request${order.replacement_count > 1 ? 's' : ''}</span>
            </span>
            <span class="bg-orange-200 text-orange-800 text-xs font-bold px-2 py-1 rounded-full">
              ${order.replacement_count} Issue${order.replacement_count > 1 ? 's' : ''}
            </span>
          </div>

          <!-- Header -->
          <div class="order-header p-3 md:p-4" onclick="toggleRepDetails(${order.id})">
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
                ${orderStatusBadge(status)}
                ${paymentStatusBadge(ps)}
                <i class="fas fa-chevron-down expand-icon text-gray-400"></i>
              </div>
            </div>
          </div>

          <!-- Details -->
          <div class="order-details">
            <div class="px-3 md:px-4 pb-3 md:pb-4">
              <!-- Customer / Address -->
              <div class="bg-gray-50 rounded-lg p-3 md:p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm">
                      <i class="fas fa-user text-orange-500 mr-2"></i>Customer Info
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.customer_name}</p>
                    <p class="text-gray-600 text-xs break-words">${order.email}</p>
                    <p class="text-gray-600 text-xs">${order.mobile}</p>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center text-sm">
                      <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>Delivery Address
                    </h4>
                    <p class="text-gray-700 text-sm break-words">${order.address}</p>
                    <p class="text-gray-600 text-xs">ZIP: ${order.zipcode}</p>
                  </div>
                </div>
              </div>

              <!-- Items -->
              <div class="mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center text-sm mb-3">
                  <i class="fas fa-shopping-bag text-orange-500 mr-2"></i>Order Items
                </h4>
                <div class="max-h-60 overflow-y-auto scrollbar-hide bg-gray-50 rounded-lg p-2 md:p-3">
                  ${itemsHtml}
                </div>
              </div>

              <!-- Replacement Requests -->
              ${replacementsHTML}

              <!-- VAT -->
              <div class="mb-4">${getVATBreakdownHTML(totals, true)}</div>

              <!-- Actions -->
              <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                <a href="<?= BASE_URL ?>/replacementrequests?order_id=${order.id}" target="_blank"
                  class="bg-orange-600 hover:bg-orange-700 text-white px-3 md:px-4 py-2 rounded-lg transition-colors flex items-center space-x-2 shadow-sm text-sm">
                  <i class="fas fa-exchange-alt"></i><span>View Replacements (${order.replacement_count})</span>
                </a>
              </div>
            </div>
          </div>
        </div>`;
      });
    }

    // ── Confirm / Reject ──────────────────────────────
    function confirmOrder(orderId) {
      const btn = document.getElementById(`confirm-btn-${orderId}`);
      if (!btn) return;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span class="hidden sm:inline">Processing...</span>';
      const rejectBtn = document.getElementById(`reject-btn-${orderId}`);
      if (rejectBtn) rejectBtn.disabled = true;

      fetch('<?= BASE_URL ?>/updateorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ order_id: parseInt(orderId), action: 'confirm' })
      })
        .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
        .then(data => {
          if (data.success) { showAlert('Order confirmed successfully!', 'success'); loadOrders(); }
          else throw new Error(data.message || data.error || 'Unknown error');
        })
        .catch(err => {
          showAlert(`Failed to confirm order: ${err.message}`, 'error');
          const b = document.getElementById(`confirm-btn-${orderId}`);
          const rb = document.getElementById(`reject-btn-${orderId}`);
          if (b) { b.disabled = false; b.innerHTML = '<i class="fas fa-check mr-2"></i><span class="hidden sm:inline">Confirm</span>'; }
          if (rb) rb.disabled = false;
        });
    }

    document.addEventListener('DOMContentLoaded', loadOrders);
  </script>
</body>

</html>