<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin']);

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
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

$user_id   = $_SESSION['noble_id'];
$fullname  = $_SESSION['noble_name'];
$message   = "";
$error     = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $po_id  = intval($_POST['po_id']);
    $action = trim($_POST['action']);
    $notes  = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("SELECT status FROM purchase_orders WHERE id = ?");
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $stmt->bind_result($old_status);
    $stmt->fetch();
    $stmt->close();

    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $conn->prepare("UPDATE purchase_orders SET status=?, approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sii", $new_status, $user_id, $po_id);
    if ($stmt->execute()) {
        $log = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?,?,?,?,?,NOW())");
        $log->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
        $log->execute();
        $log->close();
        $message = $action === 'approve' ? "Purchase Order approved successfully!" : "Purchase Order rejected.";
    } else {
        $error = "Action failed. Please try again.";
    }
    $stmt->close();
}

$purchase_orders = [];
$stmt = $conn->prepare("
    SELECT po.id, po.po_number, po.po_date, po.ship_to, po.target_delivery_date,
           po.payment_terms, po.attachment_path, po.client_po_path, po.status, po.created_at,
           po.approved_by, po.approved_at,
           c.company_name, c.company_address, c.logo_path,
           n.fullname as created_by, n2.fullname as approved_by_name
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.approved_by = n2.id
    ORDER BY CASE po.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 END, po.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $purchase_orders[] = $row;
$stmt->close();

$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($purchase_orders as $po) {
    if (isset($status_counts[$po['status']])) $status_counts[$po['status']]++;
}
$total = count($purchase_orders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Approvals — Noble Admin</title>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<div class="max-w-screen-xl mx-auto px-6 py-8">

    <!-- Page Header -->
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="w-9 h-9 rounded-lg bg-violet-600 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clipboard-check text-white text-sm"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Purchase Order Approvals</h1>
            </div>
            <p class="text-sm text-gray-500 ml-12">Review and approve or reject purchase orders submitted by sales.</p>
        </div>
        <div class="hidden sm:flex items-center gap-3">
            <div class="text-right">
                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($fullname); ?></p>
                <p class="text-xs text-gray-400">Operational Manager</p>
            </div>
            <div class="w-9 h-9 bg-violet-600 rounded-full flex items-center justify-center">
                <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($fullname, 0, 1)); ?></span>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($message): ?>
        <div class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">
            <i class="fas fa-check-circle text-emerald-500"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-amber-500 uppercase tracking-wider mb-1 font-medium">Pending</p>
            <p class="text-2xl font-bold text-amber-600"><?php echo $status_counts['pending']; ?></p>
            <p class="text-xs text-gray-400 mt-1">Awaiting review</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-emerald-500 uppercase tracking-wider mb-1 font-medium">Approved</p>
            <p class="text-2xl font-bold text-emerald-600"><?php echo $status_counts['approved']; ?></p>
            <p class="text-xs text-gray-400 mt-1">Ready for processing</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-red-400 uppercase tracking-wider mb-1 font-medium">Rejected</p>
            <p class="text-2xl font-bold text-red-600"><?php echo $status_counts['rejected']; ?></p>
            <p class="text-xs text-gray-400 mt-1">Not approved</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">All Purchase Orders</h2>
                <p class="text-xs text-gray-400 mt-0.5"><?php echo $total; ?> record<?php echo $total !== 1 ? 's' : ''; ?> total</p>
            </div>
        </div>

        <?php if (!empty($purchase_orders)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">PO #</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">PO Date</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Delivery</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment Terms</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Quotation</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Client PO</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Created By</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($purchase_orders as $po):
                        $statusMap = [
                            'pending'  => 'bg-amber-100 text-amber-700',
                            'approved' => 'bg-emerald-100 text-emerald-700',
                            'rejected' => 'bg-red-100 text-red-700',
                        ];
                        $statusClass = $statusMap[$po['status']] ?? 'bg-gray-100 text-gray-600';
                        $isPending = $po['status'] === 'pending';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-mono font-bold text-violet-700 text-xs"><?php echo htmlspecialchars($po['po_number']); ?></span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($po['logo_path']) && file_exists($po['logo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($po['logo_path']); ?>"
                                         alt="" class="h-7 w-7 object-contain rounded border border-gray-100 flex-shrink-0">
                                <?php else: ?>
                                    <div class="h-7 w-7 bg-blue-100 rounded flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-building text-blue-500 text-xs"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars(substr($po['company_name'], 0, 22)); ?></span>
                            </div>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap text-gray-600">
                            <?php echo date('M j, Y', strtotime($po['po_date'])); ?>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap text-gray-600">
                            <?php echo date('M j, Y', strtotime($po['target_delivery_date'])); ?>
                        </td>
                        <td class="px-5 py-4 text-gray-600">
                            <?php echo htmlspecialchars($po['payment_terms']); ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                <?php echo ucfirst($po['status']); ?>
                            </span>
                            <?php if (!$isPending && !empty($po['approved_by_name'])): ?>
                                <p class="text-xs text-gray-400 mt-1">by <?php echo htmlspecialchars($po['approved_by_name']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-xs font-medium">
                                    <i class="fas fa-file-pdf text-xs"></i>View
                                </a>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition text-xs font-medium">
                                    <i class="fas fa-file-alt text-xs"></i>View
                                </a>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-gray-500 text-xs">
                            <?php echo htmlspecialchars($po['created_by'] ?? '—'); ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($isPending): ?>
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="openApproveModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition" title="Approve">
                                        <i class="fas fa-check text-sm"></i>
                                    </button>
                                    <button onclick="openRejectModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Reject">
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs italic"><?php echo ucfirst($po['status']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="py-20 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clipboard-list text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-base font-semibold text-gray-700 mb-1">No purchase orders yet</h3>
            <p class="text-sm text-gray-400">Purchase orders will appear here once submitted.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
        <form method="POST">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Approve Purchase Order</h3>
                        <p class="text-xs text-gray-400">PO # <span id="approvePoNumber" class="font-semibold text-gray-600"></span></p>
                    </div>
                </div>
                <input type="hidden" name="po_id" id="approvePoId">
                <input type="hidden" name="action" value="approve">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea name="notes" rows="3" placeholder="Add any notes about this approval..."
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-gray-50 resize-none mb-4"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('approveModal')"
                        class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Cancel</button>
                    <button type="submit"
                        class="px-5 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition font-medium">
                        <i class="fas fa-check mr-1.5"></i>Approve
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
        <form method="POST">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Reject Purchase Order</h3>
                        <p class="text-xs text-gray-400">PO # <span id="rejectPoNumber" class="font-semibold text-gray-600"></span></p>
                    </div>
                </div>
                <input type="hidden" name="po_id" id="rejectPoId">
                <input type="hidden" name="action" value="reject">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Reason for rejection <span class="text-red-400">*</span></label>
                <textarea name="notes" rows="3" required placeholder="Please explain why this PO is being rejected..."
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 bg-gray-50 resize-none mb-4"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('rejectModal')"
                        class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Cancel</button>
                    <button type="submit"
                        class="px-5 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition font-medium">
                        <i class="fas fa-times mr-1.5"></i>Reject
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function openApproveModal(id, num) {
        document.getElementById('approvePoId').value = id;
        document.getElementById('approvePoNumber').textContent = num;
        document.getElementById('approveModal').classList.remove('hidden');
    }
    function openRejectModal(id, num) {
        document.getElementById('rejectPoId').value = id;
        document.getElementById('rejectPoNumber').textContent = num;
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }
    document.querySelectorAll('[id$="Modal"]').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });
</script>

</body>
</html>