<?php
// approve_purchase_orders_accountant.php - Accountant PO Approval
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'accountant']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$_SESSION['last_activity'] = time();

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
    }
    $stmt->close();
}

$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $po_id = intval($_POST['po_id']);
    $action = trim($_POST['action']);
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("SELECT accounting_status FROM purchase_orders WHERE id = ?");
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $stmt->bind_result($old_status);
    $stmt->fetch();
    $stmt->close();

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE purchase_orders SET accounting_status = ?, accounting_approved_by = ?, accounting_approved_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("sii", $new_status, $user_id, $po_id);

    if ($stmt->execute()) {
        $log_stmt = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $log_stmt->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
        $log_stmt->execute();
        $log_stmt->close();
        $message = "Purchase Order successfully " . ($new_status === 'approved' ? 'approved' : 'rejected') . ".";
    } else {
        $error = "Failed to update PO. Please try again.";
    }
    $stmt->close();
}

$purchase_orders = [];
$stmt = $conn->prepare("
    SELECT
        po.id,
        po.po_number,
        po.po_date,
        po.payment_terms,
        po.attachment_path,
        po.client_po_path,
        po.accounting_status,
        po.accounting_approved_by,
        po.accounting_approved_at,
        c.company_name,
        n.fullname  AS created_by,
        n2.fullname AS approved_by_name
    FROM purchase_orders po
    LEFT JOIN companies c     ON po.company_id = c.id
    LEFT JOIN nobleaccount n  ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.accounting_approved_by = n2.id
    WHERE po.status = 'approved'
    ORDER BY
        CASE po.accounting_status
            WHEN 'pending'  THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        po.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchase_orders[] = $row;
}
$stmt->close();

$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($purchase_orders as $po) {
    if (isset($status_counts[$po['accounting_status']])) {
        $status_counts[$po['accounting_status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Approval — Noble Admin</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- Page header -->
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center shrink-0">
                <i class="fas fa-file-invoice-dollar text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-semibold text-gray-900 leading-tight">Accounting Approval</h1>
                <p class="text-xs text-gray-500">Review operationally approved purchase orders</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
            <div
                class="flex items-center gap-2.5 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                <i class="fas fa-check-circle text-green-500 shrink-0"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                class="flex items-center gap-2.5 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                <i class="fas fa-exclamation-circle text-red-500 shrink-0"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Metric cards -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Pending</p>
                    <p class="text-2xl font-semibold text-yellow-600"><?php echo $status_counts['pending']; ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-yellow-500"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Approved</p>
                    <p class="text-2xl font-semibold text-green-600"><?php echo $status_counts['approved']; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Rejected</p>
                    <p class="text-2xl font-semibold text-red-600"><?php echo $status_counts['rejected']; ?></p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-500"></i>
                </div>
            </div>
        </div>

        <!-- Table card -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            <!-- Card header -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Purchase Orders</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Only operationally approved POs are listed here</p>
                </div>
                <span class="text-[11px] text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full font-medium">
                    <?php echo count($purchase_orders); ?> record<?php echo count($purchase_orders) !== 1 ? 's' : ''; ?>
                </span>
            </div>

            <!-- Filter tabs -->
            <div class="flex items-center gap-1.5 px-5 py-2.5 bg-gray-50 border-b border-gray-100">
                <button onclick="filterTable('all', this)"
                    class="tab-btn active text-[11px] font-medium px-3 py-1.5 rounded-md border transition-all bg-white border-gray-300 text-gray-700 shadow-sm">
                    All
                    <span class="ml-1 bg-gray-100 text-gray-600 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo count($purchase_orders); ?>
                    </span>
                </button>
                <button onclick="filterTable('pending', this)"
                    class="tab-btn text-[11px] font-medium px-3 py-1.5 rounded-md border border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700 transition-all">
                    <i class="fas fa-clock mr-1 text-yellow-500 text-[10px]"></i>Pending
                    <span
                        class="ml-1 bg-yellow-100 text-yellow-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['pending']; ?>
                    </span>
                </button>
                <button onclick="filterTable('approved', this)"
                    class="tab-btn text-[11px] font-medium px-3 py-1.5 rounded-md border border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700 transition-all">
                    <i class="fas fa-check-circle mr-1 text-green-500 text-[10px]"></i>Approved
                    <span class="ml-1 bg-green-100 text-green-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['approved']; ?>
                    </span>
                </button>
                <button onclick="filterTable('rejected', this)"
                    class="tab-btn text-[11px] font-medium px-3 py-1.5 rounded-md border border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700 transition-all">
                    <i class="fas fa-times-circle mr-1 text-red-500 text-[10px]"></i>Rejected
                    <span class="ml-1 bg-red-100 text-red-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['rejected']; ?>
                    </span>
                </button>
            </div>

            <!-- Table -->
            <?php if (!empty($purchase_orders)): ?>
                <div class="overflow-x-auto">
                    <div class="max-h-[520px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                                <tr>
                                    <th
                                        class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        PO Number</th>
                                    <th
                                        class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Company</th>
                                    <th
                                        class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Payment Terms</th>
                                    <th
                                        class="px-5 py-3 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Quotation</th>
                                    <th
                                        class="px-5 py-3 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Client PO</th>
                                    <th
                                        class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Created By</th>
                                    <th
                                        class="px-5 py-3 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Status</th>
                                    <th
                                        class="px-5 py-3 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="tableBody">
                                <?php foreach ($purchase_orders as $po):
                                    $badge_cls = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ][$po['accounting_status']] ?? 'bg-gray-100 text-gray-700';

                                    $badge_icon = [
                                        'pending' => 'fa-clock',
                                        'approved' => 'fa-check-circle',
                                        'rejected' => 'fa-times-circle',
                                    ][$po['accounting_status']] ?? 'fa-circle';
                                    ?>
                                    <tr class="po-row hover:bg-gray-50 transition-colors"
                                        data-status="<?php echo htmlspecialchars($po['accounting_status']); ?>">

                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span
                                                class="font-mono text-[11px] font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($po['po_number']); ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-3">
                                            <span class="font-medium text-gray-800 text-xs">
                                                <?php echo htmlspecialchars($po['company_name'] ?? '—'); ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap">
                                            <?php echo htmlspecialchars($po['payment_terms'] ?? '—'); ?>
                                        </td>

                                        <td class="px-5 py-3 text-center">
                                            <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank"
                                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2.5 py-1 rounded-md transition-colors">
                                                    <i class="fas fa-file-pdf text-[10px]"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-300 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-5 py-3 text-center">
                                            <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank"
                                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-2.5 py-1 rounded-md transition-colors">
                                                    <i class="fas fa-file-alt text-[10px]"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-300 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap">
                                            <?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?>
                                        </td>

                                        <td class="px-5 py-3 text-center">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full <?php echo $badge_cls; ?>">
                                                <i class="fas <?php echo $badge_icon; ?> text-[9px]"></i>
                                                <?php echo ucfirst($po['accounting_status']); ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            <?php if ($po['accounting_status'] === 'pending'): ?>
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button
                                                        onclick="openModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>', 'approve')"
                                                        class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1.5 rounded-md bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors">
                                                        <i class="fas fa-check text-[9px]"></i> Approve
                                                    </button>
                                                    <button
                                                        onclick="openModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>', 'reject')"
                                                        class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1.5 rounded-md bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-colors">
                                                        <i class="fas fa-times text-[9px]"></i> Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-[11px] text-gray-300 italic">Done</span>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty filtered state -->
                <div id="emptyFiltered" class="hidden py-12 text-center">
                    <i class="fas fa-inbox text-3xl text-gray-200 mb-2 block"></i>
                    <p class="text-sm text-gray-400">No orders match this filter.</p>
                </div>

            <?php else: ?>
                <div class="py-12 text-center">
                    <i class="fas fa-inbox text-3xl text-gray-200 mb-2 block"></i>
                    <p class="text-sm font-medium text-gray-500">No purchase orders to review</p>
                    <p class="text-xs text-gray-400 mt-1">Operationally approved POs will appear here.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Approval / Rejection Modal -->
    <div id="modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.4); backdrop-filter: blur(3px);">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">

            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between">
                <div>
                    <h3 id="modalTitle" class="text-sm font-semibold text-gray-900"></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        PO: <span id="modalPoNumber" class="font-mono font-semibold text-blue-600"></span>
                    </p>
                </div>
                <button onclick="closeModal()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 w-6 h-6 rounded-full flex items-center justify-center transition-colors ml-3 shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <form method="POST" class="px-5 py-4 space-y-4">
                <input type="hidden" name="po_id" id="modalPoId">
                <input type="hidden" name="action" id="modalAction">

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">
                        Notes
                        <span id="notesRequiredLabel" class="text-red-400 font-normal hidden">(required for
                            rejection)</span>
                    </label>
                    <textarea id="modalNotes" name="notes" rows="3" placeholder="Add notes or remarks..."
                        class="w-full text-xs px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-none text-gray-700 placeholder-gray-300 transition"></textarea>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="button" onclick="closeModal()"
                        class="flex-1 text-xs font-medium px-4 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn"
                        class="flex-1 text-xs font-semibold px-4 py-2 rounded-lg transition-colors">
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        // Modal
        const modal = document.getElementById('modal');
        const modalNotes = document.getElementById('modalNotes');

        function openModal(poId, poNumber, action) {
            document.getElementById('modalPoId').value = poId;
            document.getElementById('modalPoNumber').textContent = poNumber;
            document.getElementById('modalAction').value = action;
            modalNotes.value = '';

            const title = document.getElementById('modalTitle');
            const btn = document.getElementById('modalSubmitBtn');
            const reqLbl = document.getElementById('notesRequiredLabel');

            if (action === 'approve') {
                title.textContent = 'Approve Purchase Order';
                btn.innerHTML = '<i class="fas fa-check mr-1"></i>Approve';
                btn.className = 'flex-1 text-xs font-semibold px-4 py-2 rounded-lg transition-colors bg-green-600 hover:bg-green-700 text-white';
                modalNotes.required = false;
                reqLbl.classList.add('hidden');
            } else {
                title.textContent = 'Reject Purchase Order';
                btn.innerHTML = '<i class="fas fa-times mr-1"></i>Reject';
                btn.className = 'flex-1 text-xs font-semibold px-4 py-2 rounded-lg transition-colors bg-red-600 hover:bg-red-700 text-white';
                modalNotes.required = true;
                reqLbl.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
            setTimeout(() => modalNotes.focus(), 50);
        }

        function closeModal() {
            modal.classList.add('hidden');
            modalNotes.required = false;
            document.getElementById('notesRequiredLabel').classList.add('hidden');
        }

        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        // Tab filter
        const tabActiveMap = {
            all: ['bg-white', 'border-gray-300', 'text-gray-700', 'shadow-sm'],
            pending: ['bg-yellow-50', 'border-yellow-300', 'text-yellow-800'],
            approved: ['bg-green-50', 'border-green-300', 'text-green-800'],
            rejected: ['bg-red-50', 'border-red-300', 'text-red-800'],
        };
        const tabInactive = ['border-transparent', 'text-gray-500'];

        function filterTable(status, btn) {
            document.querySelectorAll('.tab-btn').forEach(t => {
                Object.values(tabActiveMap).flat().forEach(c => t.classList.remove(c));
                t.classList.add(...tabInactive);
            });

            btn.classList.remove(...tabInactive);
            btn.classList.add(...(tabActiveMap[status] || tabActiveMap.all));

            const rows = document.querySelectorAll('.po-row');
            const emptyEl = document.getElementById('emptyFiltered');
            let visible = 0;

            rows.forEach(row => {
                const show = status === 'all' || row.dataset.status === status;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
        }
    </script>

</body>

</html>