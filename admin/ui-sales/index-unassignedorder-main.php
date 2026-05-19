<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: " . BASE_URL . "/main");
  exit();
}

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
  $email = $_SESSION['noble_user'];
  $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->bind_result($id, $name, $lvl);
  if ($stmt->fetch()) {
    $_SESSION['noble_id'] = $id;
    $_SESSION['noble_name'] = $name;
    $_SESSION['noble_lvl'] = $lvl;
  } else {
    $_SESSION['noble_id'] = 0;
    $_SESSION['noble_name'] = "Unknown User";
    $_SESSION['noble_lvl'] = "guest";
  }
  $stmt->close();
}

$sql = "
  SELECT 
    o.id, o.customer_name, o.email, o.mobile, o.total, o.address, 
    o.emp_id, a.fullname AS accepted_by,
    COUNT(rr.id) AS replacement_count
  FROM orders o
  LEFT JOIN nobleaccount a ON o.emp_id = a.id
  LEFT JOIN replacement_requests rr ON rr.order_id = o.id
  GROUP BY o.id, o.customer_name, o.email, o.mobile, o.total, o.address, o.emp_id, a.fullname
  ORDER BY 
    CASE 
        WHEN (o.emp_id IS NULL OR o.emp_id = '' OR o.emp_id = 0) THEN 0 
        ELSE 1 
    END ASC,
    o.id DESC
";
$result = $conn->query($sql);
$total_orders = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Orders</title>
</head>

<body class="bg-stone-100 font-sans text-gray-900">
  <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

  <div class="max-w-8xl mx-auto p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold tracking-tight">
        Orders <span class="text-orange-600">.</span>
      </h1>
      <span class="text-xs font-medium bg-white text-gray-500 border border-gray-200 rounded-full px-3 py-1">
        <?php echo $total_orders; ?> order<?php echo $total_orders !== 1 ? 's' : ''; ?>
      </span>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['accepted']) && $_GET['accepted'] == "true"): ?>
      <div
        class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium mb-5 bg-green-50 text-green-800 border border-green-300">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Order accepted successfully.
      </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] == "already_accepted"): ?>
      <div
        class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium mb-5 bg-red-50 text-red-800 border border-red-300">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        This order has already been accepted by another employee.
      </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] == "update_failed"): ?>
      <div
        class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium mb-5 bg-red-50 text-red-800 border border-red-300">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Failed to accept the order. Please try again.
      </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden overflow-x-auto">
      <table class="w-full border-collapse text-sm min-w-[900px]">

        <thead>
          <tr class="bg-gray-200">
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Customer</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Email</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Mobile</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Total</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Address</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Replacement</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Status</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Accepted by</th>
            <th class="px-4 py-3 text-left text-gray-800 text-sm font-bold tracking-widest uppercase whitespace-nowrap">
              Action</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
              $isAccepted = !empty($row['emp_id']) && $row['emp_id'] != '0';
              $hasReplacement = $row['replacement_count'] > 0;
              ?>
              <tr
                class="border-b border-gray-100 last:border-b-0 transition-colors duration-100 hover:bg-gray-50 <?php echo $isAccepted ? 'bg-gray-50' : 'bg-white'; ?>">

                <!-- Customer -->
                <td class="px-4 py-3 align-middle">
                  <div
                    class="font-medium text-sm text-gray-900 whitespace-nowrap overflow-hidden text-ellipsis max-w-[160px]">
                    <?php echo htmlspecialchars($row['customer_name']); ?>
                  </div>
                  <div class="text-xs text-gray-400 mt-0.5 font-mono">#<?php echo $row['id']; ?></div>
                </td>

                <!-- Email -->
                <td class="px-4 py-3 align-middle">
                  <span
                    class="font-mono text-xs text-gray-500 whitespace-nowrap overflow-hidden text-ellipsis max-w-[180px] block">
                    <?php echo htmlspecialchars($row['email']); ?>
                  </span>
                </td>

                <!-- Mobile -->
                <td class="px-4 py-3 align-middle">
                  <span class="font-mono text-xs text-gray-500 whitespace-nowrap">
                    <?php echo htmlspecialchars($row['mobile']); ?>
                  </span>
                </td>

                <!-- Total -->
                <td class="px-4 py-3 align-middle">
                  <span
                    class="inline-flex items-center bg-orange-600 text-white text-xs font-semibold font-mono px-2.5 py-1 rounded-full whitespace-nowrap">
                    ₱<?php echo number_format($row['total'], 2); ?>
                  </span>
                </td>

                <!-- Address -->
                <td class="px-4 py-3 align-middle">
                  <div class="text-xs text-gray-500 max-w-[180px] whitespace-nowrap overflow-hidden text-ellipsis">
                    <?php echo htmlspecialchars($row['address']); ?>
                  </div>
                </td>

                <!-- Replacement — now opens modal -->
                <td class="px-4 py-3 align-middle">
                  <?php if ($hasReplacement): ?>
                    <button
                      onclick="openIssueModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['customer_name'])); ?>')"
                      class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap bg-red-500 text-white border border-orange-800 cursor-pointer transition-colors duration-150 hover:bg-orange-600">
                      <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                      </svg>
                      <?php echo $row['replacement_count']; ?> Issue<?php echo $row['replacement_count'] > 1 ? 's' : ''; ?>
                    </button>
                  <?php else: ?>
                    <span class="text-gray-300 text-sm">—</span>
                  <?php endif; ?>
                </td>

                <!-- Status -->
                <td class="px-4 py-3 align-middle">
                  <?php if ($isAccepted): ?>
                    <span
                      class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full border bg-green-50 text-green-800 border-green-300">
                      <span class="w-1.5 h-1.5 rounded-full bg-green-600 inline-block shrink-0"></span>
                      Accepted
                    </span>
                  <?php else: ?>
                    <span
                      class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full border bg-amber-50 text-amber-800 border-amber-300">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block shrink-0"></span>
                      Pending
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Accepted By -->
                <td class="px-4 py-3 align-middle uppercase font-semibold">
                  <span class="text-xs text-black whitespace-nowrap">
                    <?php echo $isAccepted ? htmlspecialchars($row['accepted_by'] ?? 'Unknown') : '—'; ?>
                  </span>
                </td>

                <!-- Action -->
                <td class="px-4 py-3 align-middle">
                  <?php if (!$isAccepted): ?>
                    <form method="POST" action="<?= BASE_URL ?>/unassignedorderaccept">
                      <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                      <button type="submit"
                        class="inline-flex items-center gap-1.5 text-xs font-medium bg-transparent text-green-800 border border-green-600 rounded-lg px-3 py-1.5 cursor-pointer transition-colors duration-150 hover:bg-green-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        Accept
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-xs text-gray-300 italic">Already taken</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center py-10 text-gray-400 text-sm">No orders found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===== ISSUE MODAL ===== -->
  <div id="issueModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    style="background: rgba(0,0,0,0.45);" onclick="handleModalBackdropClick(event)">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[85vh] flex flex-col overflow-hidden"
      onclick="event.stopPropagation()">

      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-7 h-7 bg-red-100 rounded-lg">
              <svg width="14" height="14" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                  d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              </svg>
            </span>
            <h2 class="font-semibold text-gray-900 text-sm">Replacement / Issues</h2>
          </div>
          <p id="modalSubtitle" class="text-xs text-gray-400 mt-0.5 ml-9"></p>
        </div>
        <button onclick="closeIssueModal()"
          class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div id="modalBody" class="overflow-y-auto flex-1 px-6 py-5 space-y-3">
        <!-- Loading state -->
        <div id="modalLoading" class="flex items-center gap-2 text-xs text-gray-400 py-4">
          <div class="w-4 h-4 border-2 border-gray-200 border-t-orange-500 rounded-full animate-spin shrink-0"></div>
          Loading issue details...
        </div>
        <div id="modalContent"></div>
      </div>
    </div>
  </div>

  <script>
    const fetchedOrders = {};

    const statusConfig = {
      pending: { bg: '#fefce8', color: '#92400e', border: '#fcd34d', icon: '●', label: 'Pending' },
      approved: { bg: '#f0fdf4', color: '#166534', border: '#86efac', icon: '✓', label: 'Approved' },
      processing: { bg: '#eff6ff', color: '#1e40af', border: '#93c5fd', icon: '⚙', label: 'Processing' },
      in_warehouse: { bg: '#f5f3ff', color: '#4c1d95', border: '#c4b5fd', icon: '⬡', label: 'In Warehouse' },
      delivered: { bg: '#f0fdf4', color: '#166534', border: '#86efac', icon: '✓', label: 'Delivered' },
    };

    function getStatusCfg(raw) {
      const key = (raw || 'pending').toLowerCase().replace(/\s+/g, '_');
      return statusConfig[key] || { bg: '#f9fafb', color: '#6b7280', border: '#e5e7eb', icon: '?', label: raw || 'Unknown' };
    }

    function openIssueModal(orderId, customerName) {
      const modal = document.getElementById('issueModal');
      const subtitle = document.getElementById('modalSubtitle');
      const loading = document.getElementById('modalLoading');
      const content = document.getElementById('modalContent');

      subtitle.textContent = 'Order #' + orderId + ' · ' + customerName;
      loading.style.display = 'flex';
      content.innerHTML = '';
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';

      if (fetchedOrders[orderId]) {
        loading.style.display = 'none';
        content.innerHTML = fetchedOrders[orderId];
        return;
      }

      fetch(`<?= BASE_URL ?>/fetchrequests?order_id=${orderId}`)
        .then(r => r.json())
        .then(data => {
          loading.style.display = 'none';

          if (!Array.isArray(data) || data.length === 0) {
            content.innerHTML = `<p class="text-xs text-gray-400 italic py-4 text-center">No replacement details found.</p>`;
            return;
          }

          const html = data.map((rep, idx) => {
            const cfg = getStatusCfg(rep.status);
            const statusPill = `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:3px 10px;border-radius:9999px;background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border};">${cfg.icon} ${cfg.label}</span>`;

            return `
              <div style="background:#fff;border:1px solid #fed7aa;border-radius:12px;padding:14px 16px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;">
                  <div style="display:flex;align-items:flex-start;gap:10px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:#fff7ed;color:#9a3412;border-radius:50%;font-size:10px;font-weight:700;flex-shrink:0;">${idx + 1}</span>
                    <div>
                      <div style="font-size:13.5px;font-weight:600;color:#111827;">${rep.product_name || '—'}</div>
                      <div style="font-size:11px;color:#9ca3af;margin-top:2px;">${rep.codename ? rep.codename + ' · ' : ''}Size: ${rep.size || '—'} · Color: ${rep.variant_color || '—'}</div>
                    </div>
                  </div>
                  ${statusPill}
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:11.5px;color:#6b7280;margin-bottom:8px;">
                  <span>Reason: <strong style="font-weight:600;color:#374151;">${rep.reason || '—'}</strong></span>
                  <span>Qty: <strong style="font-weight:600;color:#374151;">${rep.replacement_quantity || 1}</strong></span>
                  <span>Email: <strong style="font-weight:600;color:#374151;">${rep.user_email || '—'}</strong></span>
                </div>

                ${rep.details ? `<div style="font-size:12px;color:#78350f;background:#fff7ed;border-radius:8px;padding:9px 11px;font-style:italic;margin-bottom:8px;border:1px solid #fed7aa;">"${rep.details}"</div>` : ''}
                ${rep.admin_notes ? `<div style="font-size:11.5px;color:#92400e;background:#fefce8;border-radius:8px;padding:9px 11px;margin-bottom:8px;border:1px solid #fde68a;"><strong>Admin note:</strong> ${rep.admin_notes}</div>` : ''}

                <div style="display:flex;gap:14px;font-size:10.5px;color:#9ca3af;border-top:1px solid #f3f4f6;padding-top:8px;font-family:monospace;">
                  ${rep.created_at ? `<span>Created: ${rep.created_at}</span>` : ''}
                  ${rep.updated_at ? `<span>Updated: ${rep.updated_at}</span>` : ''}
                </div>
              </div>`;
          }).join('');

          fetchedOrders[orderId] = html;
          content.innerHTML = html;
        })
        .catch(() => {
          loading.innerHTML = `<span class="text-red-600 text-xs">Failed to load details. Please try again.</span>`;
        });
    }

    function closeIssueModal() {
      document.getElementById('issueModal').classList.add('hidden');
      document.body.style.overflow = '';
    }

    function handleModalBackdropClick(e) {
      if (e.target === document.getElementById('issueModal')) closeIssueModal();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeIssueModal(); });

    document.querySelectorAll("form").forEach(form => {
      form.addEventListener("submit", function () {
        const btn = this.querySelector("button[type='submit']");
        btn.disabled = true;
        btn.innerHTML = `<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="animate-spin"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Accepting...`;
      });
    });
  </script>

</body>

</html>