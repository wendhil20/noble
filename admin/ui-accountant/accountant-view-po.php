<?php
// accountant_view_po.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);
require_subrole(['document_controller']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "/accountantorderview");
    exit();
}

// Get order details
$orderSql = "SELECT o.*, we.fullname as warehouse_employee_name 
             FROM orders o 
             LEFT JOIN nobleaccount we ON o.warehouse_employee_id = we.id 
             WHERE o.id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: " . BASE_URL . "/accountantorderview");
    exit();
}

// Get P.O. attachments grouped by supplier (only superadmin-approved files)
$attachmentsSql = "SELECT pa.*, 
                   requester.fullname as requested_by_name,
                   approver.fullname as approved_by_name,
                   superadmin.fullname as superadmin_name
                   FROM po_attachments pa
                   LEFT JOIN nobleaccount requester ON pa.approval_requested_by = requester.id
                   LEFT JOIN nobleaccount approver ON pa.approved_by = approver.id
                   LEFT JOIN nobleaccount superadmin ON pa.superadmin_approved_by = superadmin.id
                   WHERE pa.order_id = ? 
                   AND pa.superadmin_approval_status = 'approved'
                   ORDER BY pa.supplier_name, pa.uploaded_at DESC";
$attachmentsStmt = $conn->prepare($attachmentsSql);
$attachmentsStmt->bind_param("i", $order_id);
$attachmentsStmt->execute();
$attachments = $attachmentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attachmentsStmt->close();

// Group attachments by supplier
$attachmentsBySupplier = [];
foreach ($attachments as $attachment) {
    $supplier = $attachment['supplier_name'];
    if (!isset($attachmentsBySupplier[$supplier])) {
        $attachmentsBySupplier[$supplier] = [];
    }
    $attachmentsBySupplier[$supplier][] = $attachment;
}

// Get order items
$itemsSql = "SELECT oi.*, 
                     CASE 
                        WHEN oi.supplier_id > 0 THEN s.business_name 
                        ELSE oi.manual_supplier_name 
                     END as supplier_name
              FROM order_items oi
              LEFT JOIN supplier_list s ON oi.supplier_id = s.id
              WHERE oi.order_id = ?
              ORDER BY oi.id";
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Handle file download
if (isset($_GET['download']) && isset($_GET['file_id'])) {
    $file_id = (int) $_GET['file_id'];
    $downloadSql = "SELECT * FROM po_attachments WHERE id = ? AND order_id = ? LIMIT 1";
    $downloadStmt = $conn->prepare($downloadSql);
    $downloadStmt->bind_param("ii", $file_id, $order_id);
    $downloadStmt->execute();
    $file = $downloadStmt->get_result()->fetch_assoc();
    $downloadStmt->close();

    if ($file && file_exists($file['file_path'])) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
        header('Content-Length: ' . filesize($file['file_path']));
        readfile($file['file_path']);
        exit();
    } else {
        $error_message = "File not found or has been deleted.";
    }
}

// Handle approval
if (isset($_POST['approve_file'])) {
    $file_id = (int) $_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    $approveSql = "UPDATE po_attachments SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND order_id = ?";
    $approveStmt = $conn->prepare($approveSql);
    $approveStmt->bind_param("iii", $current_user_id, $file_id, $order_id);
    if ($approveStmt->execute()) {
        $success_message = "File approved successfully.";
        header("Location: " . BASE_URL . "/accountantviewpo?order_id=" . $order_id);
        exit();
    } else {
        $error_message = "Failed to approve file.";
    }
    $approveStmt->close();
}

// Handle rejection
if (isset($_POST['reject_file'])) {
    $file_id = (int) $_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;
    $approveSql = "UPDATE po_attachments SET approval_status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ? AND order_id = ?";
    $approveStmt = $conn->prepare($approveSql);
    $approveStmt->bind_param("isii", $current_user_id, $rejection_reason, $file_id, $order_id);
    if ($approveStmt->execute()) {
        $success_message = "File rejected successfully.";
        header("Location: " . BASE_URL . "/accountantviewpo?order_id=" . $order_id);
        exit();
    } else {
        $error_message = "Failed to update approval status.";
    }
    $approveStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P.O. Files — Order #<?php echo $order_id; ?></title>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL; ?>/accountantorderview"
               class="p-2 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Purchase Order Files</h1>
                <p class="text-sm text-gray-500">Order #<?php echo $order_id; ?> &middot; <?php echo htmlspecialchars($order['customer_name']); ?></p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
            <i class="fas fa-paperclip text-xs"></i>
            <?php echo count($attachments); ?> File(s)
        </span>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($success_message)): ?>
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-check-circle text-green-500"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Order Details Card -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Order Details</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Customer</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Email</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($order['email']); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Total Amount</p>
                <p class="text-sm font-bold text-noble-orange">₱<?php echo number_format((float)$order['total'], 2); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Warehouse Staff</p>
                <p class="text-sm font-semibold text-gray-900">
                    <?php echo $order['warehouse_employee_name']
                        ? htmlspecialchars($order['warehouse_employee_name'])
                        : '<span class="text-gray-400 font-normal">Not assigned</span>'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Order Items Card -->
    <?php if (!empty($items)): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-list-ul text-purple-500"></i>
                Order Items
            </h2>
            <span class="text-xs text-gray-400"><?php echo count($items); ?> item(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-6 py-3 text-left font-medium">Item</th>
                        <th class="px-6 py-3 text-left font-medium">Qty</th>
                        <th class="px-6 py-3 text-left font-medium">Price</th>
                        <th class="px-6 py-3 text-left font-medium">Supplier</th>
                        <th class="px-6 py-3 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-3 font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="px-6 py-3 text-gray-600"><?php echo $item['quantity']; ?></td>
                        <td class="px-6 py-3 font-semibold text-gray-900">₱<?php echo number_format((float)$item['price'], 2); ?></td>
                        <td class="px-6 py-3 text-gray-600">
                            <?php echo $item['supplier_name'] ? htmlspecialchars($item['supplier_name']) : '<span class="text-gray-400">—</span>'; ?>
                        </td>
                        <td class="px-6 py-3">
                            <?php if ($item['tracking_status']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    <?php echo htmlspecialchars($item['tracking_status']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- P.O. Files by Supplier -->
    <?php if (!empty($attachmentsBySupplier)): ?>
        <div class="space-y-5">
            <?php foreach ($attachmentsBySupplier as $supplier => $supplierFiles): ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Supplier Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-2 text-gray-800 font-semibold">
                        <i class="fas fa-building text-blue-500"></i>
                        <?php echo htmlspecialchars($supplier); ?>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-700 font-medium px-2.5 py-1 rounded-full">
                        <?php echo count($supplierFiles); ?> file(s)
                    </span>
                </div>

                <!-- File Rows -->
                <div class="divide-y divide-gray-100">
                    <?php foreach ($supplierFiles as $file): ?>
                    <?php
                        $fileId        = $file['id'];
                        $fileName      = htmlspecialchars($file['original_filename'], ENT_QUOTES);
                        $supplierEsc   = htmlspecialchars($supplier, ENT_QUOTES);
                        $fileSize      = file_exists($file['file_path']) ? number_format(filesize($file['file_path']) / 1024, 2) : '0';
                        $uploaderName  = htmlspecialchars($file['requested_by_name'] ?? 'Unknown', ENT_QUOTES);
                        $uploadDate    = $file['approval_requested_at'] ? date('M j, Y g:i A', strtotime($file['approval_requested_at'])) : '';
                    ?>
                    <div class="px-5 py-4 flex items-center gap-4">

                        <!-- LEFT: Icon + File info -->
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="shrink-0 w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center">
                                <i class="fas fa-file-pdf text-red-500"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate leading-snug">
                                    <?php echo htmlspecialchars($file['original_filename']); ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Uploaded <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                                    <?php if (file_exists($file['file_path'])): ?>&middot; <?php echo $fileSize; ?> KB<?php endif; ?>
                                </p>
                                <?php if ($file['approval_status'] == 'pending' && $file['requested_by_name']): ?>
                                    <p class="text-xs text-amber-600 mt-0.5">
                                        <i class="fas fa-user mr-1"></i>Requested by <strong><?php echo htmlspecialchars($file['requested_by_name']); ?></strong>
                                        <?php if ($file['approval_requested_at']): ?>on <?php echo date('M j, Y g:i A', strtotime($file['approval_requested_at'])); ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($file['superadmin_name']): ?>
                                    <p class="text-xs text-green-600 mt-0.5">
                                        <i class="fas fa-shield-alt mr-1"></i>SA-approved by <strong><?php echo htmlspecialchars($file['superadmin_name']); ?></strong>
                                        <?php if ($file['superadmin_approved_at']): ?>on <?php echo date('M j, Y g:i A', strtotime($file['superadmin_approved_at'])); ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- RIGHT: Badges + Actions (fixed width, right-aligned) -->
                        <div class="shrink-0 flex flex-col items-end gap-2 text-xs">

                            <!-- Status badges row -->
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <?php if (!empty($file['file_replaced']) && $file['file_replaced'] == 1): ?>
                                    <span class="badge-purple"><i class="fas fa-sync-alt mr-1"></i>Updated <?php echo date('M j, Y', strtotime($file['file_replaced_at'])); ?></span>
                                <?php endif; ?>

                                <?php if ($file['all_items_received'] == 1 && $file['po_status'] == 'received'): ?>
                                    <span class="badge-emerald"><i class="fas fa-check-double mr-1"></i>Fully Received (<?php echo date('M j, Y', strtotime($file['all_items_received_at'])); ?>)</span>
                                <?php elseif ($file['all_items_received'] == 1): ?>
                                    <span class="badge-green"><i class="fas fa-check-circle mr-1"></i>All Items Received (<?php echo date('M j, Y', strtotime($file['all_items_received_at'])); ?>)</span>
                                <?php elseif ($file['marked_as_ordered'] == 1): ?>
                                    <?php if ($file['po_status'] == 'currently_receiving'): ?>
                                        <span class="badge-purple"><i class="fas fa-inbox mr-1"></i>Currently Receiving (<?php echo date('M j, Y', strtotime($file['currently_receiving_at'])); ?>)</span>
                                    <?php elseif ($file['po_status'] == 'out_for_delivery'): ?>
                                        <span class="badge-orange"><i class="fas fa-truck mr-1"></i>Out for Delivery (<?php echo date('M j, Y', strtotime($file['out_for_delivery_at'])); ?>)</span>
                                    <?php elseif ($file['po_status'] == 'supplier_confirmed'): ?>
                                        <span class="badge-green"><i class="fas fa-check-circle mr-1"></i>Supplier Confirmed (<?php echo date('M j, Y', strtotime($file['supplier_confirmed_at'])); ?>)</span>
                                        <?php if ($file['expected_delivery_date']): ?>
                                            <span class="badge-yellow"><i class="fas fa-calendar mr-1"></i>Expected: <?php echo date('M j, Y', strtotime($file['expected_delivery_date'])); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-blue"><i class="fas fa-shipping-fast mr-1"></i>Ordered (<?php echo date('M j, Y', strtotime($file['marked_as_ordered_at'])); ?>)</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($file['approval_status'] == 'approved'): ?>
                                    <span class="badge-green"><i class="fas fa-check-circle mr-1"></i>Approved by <?php echo htmlspecialchars($file['approved_by_name']); ?></span>
                                <?php elseif ($file['approval_status'] == 'rejected'): ?>
                                    <span class="badge-red"><i class="fas fa-times-circle mr-1"></i>Rejected</span>
                                    <?php if ($file['rejection_reason']): ?>
                                        <button type="button"
                                            onclick="showRejectionReason('<?php echo htmlspecialchars($file['rejection_reason'], ENT_QUOTES); ?>')"
                                            class="text-xs text-gray-500 hover:text-gray-700 underline">View Reason</button>
                                    <?php endif; ?>
                                <?php elseif ($file['approval_status'] == 'pending'): ?>
                                    <span class="badge-yellow"><i class="fas fa-clock mr-1"></i>Pending Approval</span>
                                <?php endif; ?>
                            </div>

                            <!-- Action buttons row -->
                            <div class="flex items-center gap-1.5">
                                <?php if ($file['approval_status'] == 'pending'): ?>
                                    <button type="button"
                                        data-file-id="<?php echo $fileId; ?>"
                                        data-file-name="<?php echo $fileName; ?>"
                                        data-supplier="<?php echo $supplierEsc; ?>"
                                        data-file-size="<?php echo $fileSize; ?>"
                                        data-uploader="<?php echo $uploaderName; ?>"
                                        data-upload-date="<?php echo $uploadDate; ?>"
                                        onclick="approveFileHandler(this)"
                                        class="btn-green text-xs">
                                        <i class="fas fa-check mr-1"></i>Approve
                                    </button>
                                    <button type="button"
                                        onclick="rejectFile(<?php echo $file['id']; ?>, '<?php echo $fileName; ?>')"
                                        class="btn-red text-xs">
                                        <i class="fas fa-times mr-1"></i>Reject
                                    </button>
                                <?php endif; ?>
                                <a href="?order_id=<?php echo $order_id; ?>&download=1&file_id=<?php echo $file['id']; ?>"
                                   target="_blank"
                                   class="btn-blue text-xs">
                                    <i class="fas fa-file-pdf mr-1"></i>View PDF
                                </a>
                            </div>

                        </div><!-- end right col -->
                    </div><!-- end file row -->
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
    <!-- Empty State -->
    <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
        <i class="fas fa-file-pdf text-5xl text-gray-200 mb-4"></i>
        <h3 class="text-base font-semibold text-gray-700 mb-1">No P.O. Files Found</h3>
        <p class="text-sm text-gray-400 mb-5">No purchase order files have been approved by Superadmin yet.</p>
        <a href="<?= BASE_URL; ?>/accountantorderview"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-noble-orange text-white text-sm font-medium hover:bg-noble-orange-dark transition">
            <i class="fas fa-arrow-left"></i>Back to Orders
        </a>
    </div>
    <?php endif; ?>

    <!-- Back -->
    <div class="text-center pt-2">
        <a href="<?= BASE_URL; ?>/accountantorderview"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-200 bg-white text-sm text-gray-600 hover:bg-gray-50 transition">
            <i class="fas fa-arrow-left"></i>Back to Orders List
        </a>
    </div>
</div>

<!-- ============================================================
     APPROVE MODAL
     ============================================================ -->
<div id="approveModal" class="modal-backdrop hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <form method="POST">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Approve P.O. File</h3>
                            <p class="text-xs text-gray-400">This action will mark the file as approved.</p>
                        </div>
                    </div>

                    <!-- File Card -->
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 mb-5 flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                            <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p id="approveFileNameDisplay" class="text-sm font-semibold text-gray-900 break-all mb-1"></p>
                            <div class="space-y-0.5 text-xs text-gray-500">
                                <p id="approveFileSupplier"></p>
                                <p id="approveFileSize"></p>
                                <p id="approveFileUploader"></p>
                            </div>
                            <a id="approveFileDownloadLink" href="#" target="_blank"
                               class="inline-flex items-center gap-1 mt-2 text-xs text-blue-600 hover:text-blue-700 font-medium">
                                <i class="fas fa-external-link-alt"></i>Preview PDF
                            </a>
                        </div>
                    </div>

                    <input type="hidden" name="file_id" id="approveFileId">

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeApproveModal()"
                            class="px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="submit" name="approve_file"
                            class="px-4 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 transition font-medium">
                            <i class="fas fa-check mr-1.5"></i>Approve File
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     REJECT MODAL
     ============================================================ -->
<div id="rejectModal" class="modal-backdrop hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <form method="POST">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                            <i class="fas fa-times text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Reject P.O. File</h3>
                            <p class="text-xs text-gray-400">This action will mark the file as rejected.</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-4">
                        Rejecting: <strong id="rejectFileName" class="text-gray-800 break-all"></strong>
                    </p>

                    <input type="hidden" name="file_id" id="rejectFileId">

                    <div class="mb-5">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                            Reason for rejection <span class="font-normal normal-case text-gray-400">(optional)</span>
                        </label>
                        <textarea name="rejection_reason" rows="3"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
                            placeholder="Enter reason..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeRejectModal()"
                            class="px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="submit" name="reject_file"
                            class="px-4 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 transition font-medium">
                            <i class="fas fa-times mr-1.5"></i>Reject File
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Badge helpers */
    [class^="badge-"], [class*=" badge-"] {
        display: inline-flex; align-items: center;
        padding: 3px 10px; font-size: 0.75rem; font-weight: 500; border-radius: 9999px;
    }
    .badge-blue   { background: #dbeafe; color: #1d4ed8; }
    .badge-green  { background: #dcfce7; color: #15803d; }
    .badge-emerald{ background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .badge-yellow { background: #fef9c3; color: #a16207; }
    .badge-orange { background: #ffedd5; color: #c2410c; }
    .badge-purple { background: #f3e8ff; color: #7e22ce; }
    .badge-red    { background: #fee2e2; color: #b91c1c; }

    /* Button helpers */
    [class^="btn-"], [class*=" btn-"] {
        display: inline-flex; align-items: center;
        padding: 5px 12px; border-radius: 8px; font-size: 0.75rem;
        font-weight: 500; border: none; cursor: pointer; text-decoration: none;
        transition: background 0.15s;
    }
    .btn-green { background: #16a34a; color: #fff; }
    .btn-green:hover { background: #15803d; }
    .btn-red   { background: #dc2626; color: #fff; }
    .btn-red:hover { background: #b91c1c; }
    .btn-blue  { background: #2563eb; color: #fff; }
    .btn-blue:hover { background: #1d4ed8; }

    /* Modal */
    .modal-backdrop { position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,0.45); overflow: auto; }
    .modal-backdrop.hidden { display: none; }
</style>

<script>
    function approveFileHandler(btn) {
        approveFile(
            btn.dataset.fileId,
            btn.dataset.fileName,
            btn.dataset.supplier,
            btn.dataset.fileSize,
            btn.dataset.uploader,
            btn.dataset.uploadDate
        );
    }

    function approveFile(fileId, fileName, supplierName, fileSize, uploaderName, uploadDate) {
        document.getElementById('approveFileId').value = fileId;
        document.getElementById('approveFileNameDisplay').textContent = fileName;
        document.getElementById('approveFileSupplier').innerHTML = supplierName
            ? '<i class="fas fa-building mr-1 text-gray-400"></i>Supplier: <span class="font-medium text-gray-700">' + escapeHtml(supplierName) + '</span>' : '';
        document.getElementById('approveFileSize').innerHTML = fileSize
            ? '<i class="fas fa-hdd mr-1 text-gray-400"></i>Size: <span class="font-medium text-gray-700">' + fileSize + ' KB</span>' : '';
        document.getElementById('approveFileUploader').innerHTML = (uploaderName && uploadDate)
            ? '<i class="fas fa-user mr-1 text-gray-400"></i>Requested by <span class="font-medium text-gray-700">' + escapeHtml(uploaderName) + '</span> on ' + escapeHtml(uploadDate) : '';
        document.getElementById('approveFileDownloadLink').href = '?order_id=<?php echo $order_id; ?>&download=1&file_id=' + fileId;
        document.getElementById('approveModal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

    function rejectFile(fileId, fileName) {
        document.getElementById('rejectFileId').value = fileId;
        document.getElementById('rejectFileName').textContent = fileName;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function showRejectionReason(reason) {
        alert('Rejection Reason:\n\n' + reason);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    document.getElementById('approveModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeApproveModal(); });
    document.getElementById('rejectModal').addEventListener('click',  e => { if (e.target === e.currentTarget) closeRejectModal(); });
</script>

</body>
</html>