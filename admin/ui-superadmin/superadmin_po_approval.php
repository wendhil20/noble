<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$current_user_id = $_SESSION['noble_id'] ?? 0;

// Handle file download
if (isset($_GET['download']) && isset($_GET['file_id'])) {
    $file_id = (int) $_GET['file_id'];
    $sql = "SELECT * FROM po_attachments WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($file && file_exists($file['file_path'])) {
        while (ob_get_level())
            ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
        header('Content-Length: ' . filesize($file['file_path']));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        readfile($file['file_path']);
        exit();
    } else {
        $_SESSION['error_message'] = "File not found or has been deleted.";
        header("Location: " . BASE_URL . "/superadminpoapproval");
        exit();
    }
}

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

if (isset($_POST['approve_po'])) {
    $file_id = (int) $_POST['file_id'];
    $sql = "UPDATE po_attachments SET superadmin_approval_status='approved', superadmin_approved_by=?, superadmin_approved_at=NOW() WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $current_user_id, $file_id);
    $_SESSION[$stmt->execute() ? 'success_message' : 'error_message'] = $stmt->execute() ? "P.O. file approved successfully." : "Failed to approve P.O. file.";
    $stmt->close();
    header("Location: " . BASE_URL . "/superadminpoapproval");
    exit();
}

if (isset($_POST['reject_po'])) {
    $file_id = (int) $_POST['file_id'];
    $rejection_reason = trim($_POST['rejection_reason']);
    $sql = "UPDATE po_attachments SET superadmin_approval_status='rejected', superadmin_approved_by=?, superadmin_approved_at=NOW(), superadmin_rejection_reason=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $current_user_id, $rejection_reason, $file_id);
    $_SESSION[$stmt->execute() ? 'success_message' : 'error_message'] = $stmt->execute() ? "P.O. file rejected." : "Failed to reject P.O. file.";
    $stmt->close();
    header("Location: " . BASE_URL . "/superadminpoapproval");
    exit();
}

$conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $conditions[] = "pa.superadmin_approval_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = "(pa.supplier_name LIKE ? OR pa.original_filename LIKE ? OR o.id LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

if (empty($conditions))
    $conditions[] = "1=1";

$sql = "SELECT pa.*, o.customer_name, o.email, o.total, o.status as order_status,
               requester.fullname as requested_by_name, sa.fullname as superadmin_name
        FROM po_attachments pa
        INNER JOIN orders o ON pa.order_id = o.id
        LEFT JOIN nobleaccount requester ON pa.approval_requested_by = requester.id
        LEFT JOIN nobleaccount sa ON pa.superadmin_approved_by = sa.id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY CASE pa.superadmin_approval_status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 END, pa.uploaded_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params))
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$poFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$countResult = $conn->query("SELECT superadmin_approval_status, COUNT(*) as count FROM po_attachments GROUP BY superadmin_approval_status");
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $countResult->fetch_assoc()) {
    $statusCounts[$row['superadmin_approval_status']] = (int) $row['count'];
}
$totalCount = array_sum($statusCounts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P.O. Approval — Superadmin</title>
</head>

<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-screen-xl mx-auto px-6 py-8">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-9 h-9 rounded-lg bg-violet-600 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-file-contract text-white text-sm"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">P.O. Approval</h1>
            </div>
            <p class="text-sm text-gray-500 ml-12">Review and manage purchase order files submitted for approval.</p>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div
                class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">
                <i class="fas fa-check-circle text-emerald-500"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div
                class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totalCount; ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-amber-500 uppercase tracking-wider mb-1">Pending</p>
                <p class="text-2xl font-bold text-amber-600"><?php echo $statusCounts['pending']; ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-emerald-500 uppercase tracking-wider mb-1">Approved</p>
                <p class="text-2xl font-bold text-emerald-600"><?php echo $statusCounts['approved']; ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-red-400 uppercase tracking-wider mb-1">Rejected</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $statusCounts['rejected']; ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-52">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Order ID, supplier, or file name..."
                        class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-400 bg-gray-50">
                </div>

                <!-- Status tabs as buttons inside form -->
                <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                    <?php
                    $tabs = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                    foreach ($tabs as $val => $label):
                        $active = $status_filter === $val;
                        ?>
                        <button type="submit" name="status" value="<?php echo $val; ?>"
                            class="px-3 py-1.5 text-xs font-medium rounded-md transition <?php echo $active ? 'bg-white text-violet-700 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-700'; ?>">
                            <?php echo $label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button type="submit"
                    class="px-4 py-2 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition font-medium">
                    Search
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <?php if (!empty($poFiles)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Order</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Supplier</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    File</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Requested By</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($poFiles as $file): ?>
                                <?php
                                $isPending = $file['superadmin_approval_status'] === 'pending';
                                $statusMap = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'approved' => 'bg-emerald-100 text-emerald-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                ];
                                $statusClass = $statusMap[$file['superadmin_approval_status']] ?? 'bg-gray-100 text-gray-600';
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4">
                                        <span class="font-semibold text-gray-800">#<?php echo $file['order_id']; ?></span>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            <?php echo htmlspecialchars($file['customer_name']); ?></p>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700">
                                        <?php echo htmlspecialchars($file['supplier_name']); ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-2">
                                            <i class="fas fa-file-pdf text-red-400 mt-0.5 flex-shrink-0"></i>
                                            <div>
                                                <p class="text-gray-800 font-medium leading-snug">
                                                    <?php echo htmlspecialchars($file['original_filename']); ?></p>
                                                <p class="text-xs text-gray-400">
                                                    <?php echo date('M j, Y · g:i A', strtotime($file['uploaded_at'])); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600">
                                        <?php echo htmlspecialchars($file['requested_by_name'] ?? '—'); ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($file['superadmin_approval_status']); ?>
                                        </span>
                                        <?php if ($file['superadmin_approval_status'] === 'rejected' && !empty($file['superadmin_rejection_reason'])): ?>
                                            <p class="text-xs text-gray-400 mt-1 max-w-[180px] truncate"
                                                title="<?php echo htmlspecialchars($file['superadmin_rejection_reason']); ?>">
                                                <?php echo htmlspecialchars($file['superadmin_rejection_reason']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center gap-3">
                                            <a href="?download=1&file_id=<?php echo $file['id']; ?>" target="_blank"
                                                title="View PDF"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition">
                                                <i class="fas fa-eye text-sm"></i>
                                            </a>
                                            <?php if ($isPending): ?>
                                                <button
                                                    onclick="approveFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                                    title="Approve"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition">
                                                    <i class="fas fa-check text-sm"></i>
                                                </button>
                                                <button
                                                    onclick="rejectFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                                    title="Reject"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition">
                                                    <i class="fas fa-times text-sm"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-300 italic">
                                                    <?php echo $file['superadmin_name'] ?? ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="py-20 text-center">
                    <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-700 mb-1">No P.O. files found</h3>
                    <p class="text-sm text-gray-400">Try a different filter or search term.</p>
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
                        <h3 class="text-base font-semibold text-gray-900">Approve P.O. File</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-5">
                        You're about to approve <strong id="approveFileName" class="text-gray-800 break-all"></strong>.
                        This action will be recorded.
                    </p>
                    <input type="hidden" name="file_id" id="approveFileId">
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('approveModal')"
                            class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                            Cancel
                        </button>
                        <button type="submit" name="approve_po"
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
                        <h3 class="text-base font-semibold text-gray-900">Reject P.O. File</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">
                        Rejecting <strong id="rejectFileName" class="text-gray-800 break-all"></strong>. Please provide
                        a reason.
                    </p>
                    <input type="hidden" name="file_id" id="rejectFileId">
                    <textarea name="rejection_reason" rows="3" required placeholder="Reason for rejection..."
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 bg-gray-50 mb-4 resize-none"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('rejectModal')"
                            class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                            Cancel
                        </button>
                        <button type="submit" name="reject_po"
                            class="px-5 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition font-medium">
                            <i class="fas fa-times mr-1.5"></i>Reject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function approveFile(id, name) {
            document.getElementById('approveFileId').value = id;
            document.getElementById('approveFileName').textContent = name;
            document.getElementById('approveModal').classList.remove('hidden');
        }
        function rejectFile(id, name) {
            document.getElementById('rejectFileId').value = id;
            document.getElementById('rejectFileName').textContent = name;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal.id); });
        });
    </script>

</body>

</html>