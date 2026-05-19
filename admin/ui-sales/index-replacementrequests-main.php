<?php
//index-replacementrequests-main.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['sales', 'superadmin']);

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
  header("Location: " . BASE_URL . "/main");
  exit();
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if ($order_id <= 0) {
  header("Location: " . BASE_URL . "/ordermain");
  exit();
}

$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT id, customer_name FROM orders WHERE id = ? AND emp_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $emp_id);
$stmt->execute();
$stmt->bind_result($verified_order_id, $customer_name);
if (!$stmt->fetch()) {
  header("Location: orders.php");
  exit();
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Replacement Requests — Order #<?php echo $order_id; ?></title>
  <style>
    /* Lightbox — position:fixed can't be Tailwind-only in all contexts */
    .lightbox-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.88);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 16px;
    }

    .lightbox-overlay.open {
      display: flex;
    }

    /* chevron arrow for select */
    select {
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .spinner {
      animation: spin 0.7s linear infinite;
    }

    .img-thumb {
      transition: transform 0.15s;
      cursor: pointer;
    }

    .img-thumb:hover {
      transform: scale(1.05);
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen text-gray-900 ">

  <?php include ROOT_PATH . '/admin/navbar/top.php' ?>

  <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 pb-24">

    <!-- ── Page header ── -->
    <div
      class="bg-white border border-gray-200 rounded-xl px-5 py-4 mb-4 flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/ordermain"
          class="w-9 h-9 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors">
          <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center flex-shrink-0">
          <i class="fa-solid fa-retweet text-white text-sm"></i>
        </div>
        <div>
          <h1 class="text-base font-semibold text-gray-900 leading-snug">Replacement requests</h1>
          <p class="text-xs text-gray-500 mt-0.5">
            Order #<?php echo $order_id; ?> &mdash; <?php echo htmlspecialchars($customer_name); ?>
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span id="requestCount" class="text-xs text-gray-400 hidden sm:block">Loading…</span>
        <button onclick="loadRequests()"
          class="flex items-center gap-2 text-sm px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700 transition-colors">
          <i class="fas fa-rotate-right text-xs"></i>
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- ── Alert ── -->
    <div id="alertContainer" class="mb-4"></div>


    <!-- ── Loading ── -->
    <div id="loadingState" class="hidden bg-white border border-gray-200 rounded-xl p-12">
      <div class="flex items-center justify-center gap-3 text-gray-500 text-sm">
        <div class="spinner w-5 h-5 border-2 border-gray-200 border-t-gray-700 rounded-full"></div>
        Loading replacement requests…
      </div>
    </div>

    <!-- ── Requests ── -->
    <div id="requestsContainer" class="space-y-3"></div>

    <!-- ── Empty state ── -->
    <div id="emptyState" class="hidden bg-white border border-gray-200 rounded-xl p-16 text-center">
      <i class="fas fa-arrows-rotate text-4xl text-gray-200 block mb-4"></i>
      <p class="text-sm font-medium text-gray-600 mb-1">No replacement requests found</p>
      <p class="text-xs text-gray-400">Try adjusting your filter or search term.</p>
    </div>
  </div>

  <!-- ── Lightbox ── -->
  <div id="lightboxOverlay" class="lightbox-overlay" onclick="closeLightbox()">
    <button onclick="closeLightbox()"
      class="absolute top-5 right-6 text-white text-3xl leading-none bg-transparent border-none cursor-pointer opacity-60 hover:opacity-100 transition-opacity">
      &times;
    </button>
    <img id="lightboxImg" src="" alt="" class="max-w-[88vw] max-h-[78vh] object-contain rounded-xl" />
    <p id="lightboxCaption" class="text-sm text-white/60"></p>
  </div>

  <script>
    let allRequests = [];
    let currentFilter = 'all';
    const orderId = <?php echo $order_id; ?>;
    const BASE_URL = '<?= BASE_URL ?>';

    // ── Configs 
    const STATUS_CFG = {
  pending:    { badge: 'bg-yellow-50 border-yellow-200 text-yellow-800', icon: 'fas fa-clock',        label: 'Pending' },
  approved:   { badge: 'bg-green-50 border-green-200 text-green-800',    icon: 'fas fa-check-circle', label: 'Approved' },
  rejected:   { badge: 'bg-red-50 border-red-200 text-red-800',          icon: 'fas fa-times-circle', label: 'Rejected' },
  processing: { badge: 'bg-blue-50 border-blue-200 text-blue-800',       icon: 'fas fa-cog',          label: 'Processing' },
  completed:  { badge: 'bg-purple-50 border-purple-200 text-purple-800', icon: 'fas fa-check-double', label: 'Completed' },
  cancelled:  { badge: 'bg-gray-100 border-gray-300 text-gray-500',      icon: 'fas fa-ban',          label: 'Cancelled' },
};

    const REASON_CFG = {
      defective: { icon: 'fas fa-screwdriver-wrench', label: 'Defective product' },
      damaged: { icon: 'fas fa-exclamation-triangle', label: 'Damaged in transit' },
      wrong_item: { icon: 'fas fa-arrows-rotate', label: 'Wrong item received' },
      wrong_size: { icon: 'fas fa-ruler', label: 'Wrong size' },
      not_as_described: { icon: 'fas fa-info-circle', label: 'Not as described' },
      other: { icon: 'fas fa-question-circle', label: 'Other reason' },
    };

    // ── Alert 
    function showAlert(msg, type = 'info') {
      const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800',
      };
      const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle',
      };
      document.getElementById('alertContainer').innerHTML = `
        <div class="flex items-center gap-2.5 text-sm px-4 py-3 border rounded-xl ${colors[type]}">
          <i class="${icons[type]}"></i> ${msg}
        </div>`;
      setTimeout(() => document.getElementById('alertContainer').innerHTML = '', 5000);
    }

    // ── Fetch 
    function loadRequests() {
      document.getElementById('loadingState').classList.remove('hidden');
      document.getElementById('requestsContainer').innerHTML = '';
      document.getElementById('emptyState').classList.add('hidden');

      fetch(BASE_URL + '/fetchrequests?order_id=' + orderId)
        .then(r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(data => {
          allRequests = data;
          updateStats();
          renderRequests();
        })
        .catch(() => showAlert('Failed to load replacement requests. Please try again.', 'error'))
        .finally(() => document.getElementById('loadingState').classList.add('hidden'));
    }

    // ── Stats 

    function updateStats() {
      const c = s => allRequests.filter(r => r.status === s).length;
      document.getElementById('requestCount').textContent =
        `${allRequests.length} total · ${c('pending')} pending · ${c('approved')} approved · ${c('rejected')} rejected`;
    }

    // ── Filter 
    function filterRequests(status) {
      currentFilter = status;
      renderRequests();
    }

    // ── Render 
    function renderRequests() {
      const container = document.getElementById('requestsContainer');
      const emptyState = document.getElementById('emptyState');
      const kw = (document.getElementById('searchInput')?.value || '').toLowerCase();

      let list = allRequests;
      if (currentFilter !== 'all') list = list.filter(r => r.status === currentFilter);
      if (kw) list = list.filter(r =>
        [r.product_name, r.codename, r.reason, r.details].some(v => (v || '').toLowerCase().includes(kw))
      );

      if (!list.length) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }
      emptyState.classList.add('hidden');
      container.innerHTML = list.map(buildCard).join('');
    }

    // ── Build card HTML 
    function buildCard(r) {
      const s = STATUS_CFG[r.status] || STATUS_CFG.cancelled;
      const rs = REASON_CFG[r.reason] || REASON_CFG.other;
      const qty = r.replacement_quantity || 1;

      const adminNoteHTML = r.admin_notes ? `
        <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2.5">
          <p class="text-xs font-medium text-yellow-700 flex items-center gap-1.5 mb-1">
            <i class="fas fa-sticky-note text-yellow-500 text-xs"></i> Admin note
          </p>
          <p class="text-xs text-yellow-800">${escHtml(r.admin_notes)}</p>
        </div>` : '';

      const actionBtnsHTML = r.status === 'pending' ? `
        <div class="flex items-center gap-2">
          <button onclick="updateStatus(${r.id}, 'approved')"
            class="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-green-50 border border-green-200 text-green-800 rounded-lg hover:bg-green-100 transition-colors">
            <i class="fas fa-check text-xs"></i> Approve
          </button>
          <button onclick="updateStatus(${r.id}, 'rejected')"
            class="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-red-50 border border-red-200 text-red-800 rounded-lg hover:bg-red-100 transition-colors">
            <i class="fas fa-times text-xs"></i> Reject
          </button>
        </div>` : '';

      const imageSlots = [
        { label: 'Overview', file: r.defect_image_overview },
        { label: 'Close-up', file: r.defect_image_closeup },
        { label: 'Detail', file: r.defect_image_detail },
      ].map(({ label, file }) => {
        const src = BASE_URL + '/uploads/defect_proof/' + file;
        return `
          <div class="flex flex-col items-center gap-1.5">
            <p class="text-xs text-gray-400">${label}</p>
            <img src="${src}" alt="${label}"
              class="img-thumb w-full h-24 object-cover rounded-lg border border-gray-200"
              onclick="openLightbox('${src}', '${label}')" />
          </div>`;
      }).join('');

      return `
      <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-gray-300 transition-colors">

        <!-- Header -->
        <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3 border-b border-gray-100">
          <div>
            <div class="flex items-center gap-2.5 flex-wrap">
              <span class="text-sm font-semibold text-gray-900 ">Request #${r.id}</span>
              <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium ${s.badge}">
                <i class="${s.icon} text-xs"></i> ${s.label}
              </span>
            </div>
            <p class="text-xs text-gray-400 mt-1">
              Created ${escHtml(r.created_at)}
              ${r.updated_at && r.updated_at !== r.created_at
          ? ` &middot; Updated ${escHtml(r.updated_at)}`
          : ''}
            </p>
          </div>
          ${actionBtnsHTML}
        </div>

        <!-- Body -->
        <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-5 border-b border-gray-100">

          <div>
            <p class="text-xs text-black font-semibold uppercase tracking-widest mb-2">Product</p>
            <p class="text-sm font-semibold text-gray-900">${escHtml(r.product_name)}</p>
            <p class="text-xs text-gray-500 mt-1">Size ${escHtml(r.size)} &middot; ${escHtml(r.variant_color)}</p>
            <p class="text-xs text-gray-400 mt-0.5 uppercase">${escHtml(r.codename)}</p>
          </div>

          <div>
            <p class="text-xs text-red-500 font-semibold uppercase tracking-widest mb-2">Reason</p>
            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 bg-gray-50 border border-gray-200 text-gray-600 rounded-lg">
              <i class="${rs.icon} text-xs"></i> ${rs.label}
            </span>
            ${r.details ? `<p class="text-xs text-gray-500 italic mt-2 leading-relaxed">"${escHtml(r.details)}"</p>` : ''}
            ${adminNoteHTML}
          </div>

          <div>
            <p class="text-xs text-green-500 font-semibold uppercase tracking-widest mb-2">Quantity requested</p>
            <p class="text-3xl font-light text-gray-900 ">${qty}</p>
            <p class="text-xs text-gray-400 mt-1">unit${qty > 1 ? 's' : ''}</p>
          </div>

        </div>

        <!-- Images -->
        <div class="px-5 py-4">
          <p class="text-xs text-red-500 font-semibold uppercase tracking-widest mb-3">Defect images</p>
          <div class="grid grid-cols-3 gap-3 mb-3">
            ${imageSlots}
          </div>
          <p class="text-xs text-green-600 ">
            <i class="fas fa-user mr-1 text-black "></i>${escHtml(r.user_email || '—')}
          </p>
        </div>

      </div>`;
    }

    // ── Helpers ───────────────────────────────────
    function escHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // ── Lightbox ──────────────────────────────────
    function openLightbox(src, caption) {
      document.getElementById('lightboxImg').src = src;
      document.getElementById('lightboxCaption').textContent = caption;
      document.getElementById('lightboxOverlay').classList.add('open');
    }
    function closeLightbox() {
      document.getElementById('lightboxOverlay').classList.remove('open');
    }

    // ── Status update ─────────────────────────────
    function updateStatus(requestId, status) {
      const label = status === 'approved' ? 'Approve' : 'Reject';
      if (!confirm(`${label} this replacement request?`)) return;

      fetch(BASE_URL + '/updatereplacement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: requestId, status, admin_notes: '' }),
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showAlert(`Replacement request ${status} successfully.`, 'success');
            loadRequests();
          } else {
            showAlert(data.message || 'Failed to update request.', 'error');
          }
        })
        .catch(() => showAlert('Failed to update request.', 'error'));
    }

    // ── Init 
    document.addEventListener('DOMContentLoaded', () => {
      loadRequests();
    });
  </script>
</body>

</html>