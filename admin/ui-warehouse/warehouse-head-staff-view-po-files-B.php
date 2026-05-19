<?php
// warehouse_head_staff_view_po_files_B.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

$orderSql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

$attachmentsSql = "SELECT pa.*, 
                   requester.fullname as requested_by_name,
                   approver.fullname as approved_by_name,
                   superadmin.fullname as superadmin_name
                   FROM po_attachments pa
                   LEFT JOIN nobleaccount requester ON pa.approval_requested_by = requester.id
                   LEFT JOIN nobleaccount approver ON pa.approved_by = approver.id
                   LEFT JOIN nobleaccount superadmin ON pa.superadmin_approved_by = superadmin.id
                   WHERE pa.order_id = ? 
                   ORDER BY pa.supplier_name, pa.uploaded_at DESC";
$attachmentsStmt = $conn->prepare($attachmentsSql);
$attachmentsStmt->bind_param("i", $order_id);
$attachmentsStmt->execute();
$attachments = $attachmentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attachmentsStmt->close();

$attachmentsBySupplier = [];
foreach ($attachments as $attachment) {
    $supplier = $attachment['supplier_name'];
    if (!isset($attachmentsBySupplier[$supplier])) {
        $attachmentsBySupplier[$supplier] = [];
    }
    $attachmentsBySupplier[$supplier][] = $attachment;
}

// Handle file download
if (isset($_GET['download']) && isset($_GET['file_id'])) {
    $file_id = (int) $_GET['file_id'];
    $downloadSql = "SELECT * FROM po_attachments WHERE id = ? AND order_id = ? LIMIT 1";
    $downloadStmt = $conn->prepare($downloadSql);
    $downloadStmt->bind_param("ii", $file_id, $order_id);
    $downloadStmt->execute();
    $file = $downloadStmt->get_result()->fetch_assoc();
    $downloadStmt->close();

    if ($file && $file['superadmin_approval_status'] == 'approved' && $file['approval_status'] == 'approved') {
        $downloadUrl = BASE_URL . "/warehousestaffgeneratepoexcel?" . http_build_query([
            'order_id' => $order_id,
            'supplier_key' => $file['supplier_name'],
            'editing_po_id' => $file_id,
            'download_approved' => 1,
            'payment_terms' => $file['payment_terms'],
            'delivery_details' => $file['delivery_details'],
            'conditions' => $file['conditions'],
            'additional_notes' => $file['additional_notes'],
            'prepared_by' => $file['prepared_by']
        ]);
        header("Location: $downloadUrl");
        exit();
    } elseif ($file) {
        $error_message = "File must be fully approved before downloading.";
    } else {
        $error_message = "File not found or has been deleted.";
    }
}

// Handle mark as ordered
if (isset($_POST['mark_as_ordered'])) {
    $file_ids = isset($_POST['file_ids']) ? $_POST['file_ids'] : [];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    if (!empty($file_ids)) {
        $file_ids_str = implode(',', array_map('intval', $file_ids));
        $markSql = "UPDATE po_attachments SET marked_as_ordered = 1, marked_as_ordered_at = NOW(), marked_as_ordered_by = ? WHERE id IN ($file_ids_str) AND order_id = ? AND approval_status = 'approved'";
        $markStmt = $conn->prepare($markSql);
        $markStmt->bind_param("ii", $current_user_id, $order_id);
        if ($markStmt->execute()) {
            header("Location: " . BASE_URL . "/warehouseheadstaffpomanagement?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to mark files as ordered.";
        }
        $markStmt->close();
    }
}

// Handle P.O. status update
if (isset($_POST['update_po_status'])) {
    $file_id = (int) $_POST['file_id'];
    $new_status = $_POST['new_status'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    $allowed_statuses = ['supplier_confirmed', 'out_for_delivery', 'currently_receiving'];

    if (in_array($new_status, $allowed_statuses)) {
        if ($new_status == 'supplier_confirmed') {
            $expected_date = isset($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : NULL;
            $updateSql = "UPDATE po_attachments SET po_status = ?, supplier_confirmed_at = NOW(), expected_delivery_date = ?, status_updated_by = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssiii", $new_status, $expected_date, $current_user_id, $file_id, $order_id);
        } elseif ($new_status == 'out_for_delivery') {
            $updateSql = "UPDATE po_attachments SET po_status = ?, out_for_delivery_at = NOW(), status_updated_by = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("siii", $new_status, $current_user_id, $file_id, $order_id);
        } elseif ($new_status == 'currently_receiving') {
            $receiver_id = isset($_POST['receiver_id']) ? (int) $_POST['receiver_id'] : 0;
            $po_number = isset($_POST['po_number']) ? trim($_POST['po_number']) : '';
            if ($receiver_id <= 0) {
                $error_message = "Please select a receiver.";
            } elseif (empty($po_number)) {
                $error_message = "P.O. number is required.";
            } else {
                $validatePoStmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE po_number = ? AND order_id = ?");
                $validatePoStmt->bind_param("si", $po_number, $order_id);
                $validatePoStmt->execute();
                $validateResult = $validatePoStmt->get_result()->fetch_assoc();
                $validatePoStmt->close();
                if ($validateResult['count'] == 0) {
                    $error_message = "Invalid P.O. number for this order.";
                } else {
                    $updateSql = "UPDATE po_attachments SET po_status = ?, currently_receiving_at = NOW(), status_updated_by = ? WHERE id = ? AND order_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("siii", $new_status, $current_user_id, $file_id, $order_id);
                    if ($updateStmt->execute()) {
                        $assignStmt = $conn->prepare("INSERT INTO po_receiver_assignments (po_attachment_id, po_number, order_id, receiver_id, assigned_by, assigned_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')");
                        $assignStmt->bind_param("isiii", $file_id, $po_number, $order_id, $receiver_id, $current_user_id);
                        if ($assignStmt->execute()) {
                            $success_message = "P.O. assigned to receiver successfully.";
                        } else {
                            $error_message = "Failed to assign P.O. to receiver.";
                        }
                        $assignStmt->close();
                    } else {
                        $error_message = "Failed to update P.O. status.";
                    }
                }
            }
        }

        if (isset($updateStmt) && $updateStmt->execute()) {
            $success_message = "P.O. status updated successfully.";
            header("Location: " . BASE_URL . "/warehouseheadstaffpomanagement?order_id=" . $order_id);
            exit();
        } elseif (!isset($error_message)) {
            $error_message = "Failed to update P.O. status.";
        }
        if (isset($updateStmt))
            $updateStmt->close();
    } else {
        $error_message = "Invalid status.";
    }
}

// Handle approval request
if (isset($_POST['request_approval'])) {
    $file_id = (int) $_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    $requestStmt = $conn->prepare("UPDATE po_attachments SET superadmin_approval_status = 'pending', approval_requested_at = NOW(), approval_requested_by = ? WHERE id = ? AND order_id = ?");
    $requestStmt->bind_param("iii", $current_user_id, $file_id, $order_id);
    if ($requestStmt->execute()) {
        header("Location: " . BASE_URL . "/warehouseheadstaffpomanagement?order_id=" . $order_id);
        exit();
    } else {
        $error_message = "Failed to submit approval request.";
    }
    $requestStmt->close();
}

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $file_id = (int) $_POST['file_id'];
    $deleteStmt = $conn->prepare("SELECT * FROM po_attachments WHERE id = ? AND order_id = ? LIMIT 1");
    $deleteStmt->bind_param("ii", $file_id, $order_id);
    $deleteStmt->execute();
    $fileToDelete = $deleteStmt->get_result()->fetch_assoc();
    $deleteStmt->close();
    if ($fileToDelete) {
        $deleteDbStmt = $conn->prepare("DELETE FROM po_attachments WHERE id = ?");
        $deleteDbStmt->bind_param("i", $file_id);
        if ($deleteDbStmt->execute()) {
            if (file_exists($fileToDelete['file_path']))
                unlink($fileToDelete['file_path']);
            header("Location: " . BASE_URL . "/warehouseheadstaffpomanagement?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to delete file from database.";
        }
        $deleteDbStmt->close();
    } else {
        $error_message = "File not found.";
    }
}

// Stats
$approvedCount = 0;
$pendingCount = 0;
$orderedCount = 0;
$totalFiles = count($attachments);

foreach ($attachments as $att) {
    if ($att['approval_status'] == 'approved' && $att['marked_as_ordered'] == 0)
        $approvedCount++;
    elseif ($att['approval_status'] == 'pending')
        $pendingCount++;
    elseif ($att['marked_as_ordered'] == 1)
        $orderedCount++;
}

$allApproved = ($approvedCount > 0 && $pendingCount == 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>P.O. Files — Order #<?= $order_id ?></title>
</head>

<body class="bg-slate-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- Top Nav -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">

                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/warehousestaff"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors"
                        title="Back to Orders">
                        <i class="fas fa-arrow-left text-xs"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/warehousestaffgeneratepo?order_id=<?= $order_id ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-500 hover:bg-emerald-600 transition-colors"
                        title="Generate New P.O.">
                        <i class="fas fa-file-invoice text-white text-xs"></i>
                    </a>

                    <div class="w-px h-5 bg-slate-200"></div>

                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-500">
                            <i class="fas fa-file-excel text-white text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 leading-none">P.O. Files</p>
                            <p class="text-xs text-slate-400 leading-none mt-0.5">
                                Order #<?= $order_id ?> &mdash; <?= htmlspecialchars($order['customer_name']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <div class="text-right">
                        <p class="text-slate-400">Status</p>
                        <p class="font-semibold text-slate-700"><?= htmlspecialchars(ucfirst($order['status'])) ?></p>
                    </div>
                    <div class="w-px h-5 bg-slate-200"></div>
                    <div class="text-right">
                        <p class="text-slate-400">Files</p>
                        <p class="font-semibold text-slate-700"><?= $totalFiles ?></p>
                    </div>
                </div>

            </div>
        </div>
    </nav>

    <!-- Main -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        <!-- Flash Messages -->
        <?php if (isset($success_message)): ?>
            <div
                class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                <i class="fas fa-check-circle text-emerald-500"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['po_saved']) && $_GET['po_saved'] == '1'): ?>
            <div
                class="flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                <i class="fas fa-info-circle text-blue-500"></i>
                P.O. saved and pending approval. Download will be available after both approvals are complete.
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- ─── ORDER SUMMARY ─────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Order Details</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Customer</p>
                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($order['customer_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Email</p>
                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($order['email']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Total Amount</p>
                    <p class="text-sm font-bold text-emerald-600">₱<?= number_format((float) $order['total'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- ─── STATUS OVERVIEW ───────────────────────────────────────── -->
        <?php if ($totalFiles > 0): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">

                    <!-- Stat Chips -->
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-700 mr-2">P.O. Overview</p>

                        <?php if ($approvedCount > 0): ?>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-200 rounded-full text-xs font-medium text-emerald-700">
                                <i class="fas fa-check-circle"></i> <?= $approvedCount ?> Ready to Order
                            </span>
                        <?php endif; ?>
                        <?php if ($pendingCount > 0): ?>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 border border-amber-200 rounded-full text-xs font-medium text-amber-700">
                                <i class="fas fa-clock"></i> <?= $pendingCount ?> Pending Approval
                            </span>
                        <?php endif; ?>
                        <?php if ($orderedCount > 0): ?>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 border border-blue-200 rounded-full text-xs font-medium text-blue-700">
                                <i class="fas fa-shipping-fast"></i> <?= $orderedCount ?> Ordered
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Filter -->
                    <div class="flex items-center gap-3">
                        <select id="statusFilter" onchange="filterFiles()"
                            class="text-xs px-3 py-1.5 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 text-slate-600">
                            <option value="all">All Files</option>
                            <option value="ready">Ready to Order</option>
                            <option value="pending">Pending Approval</option>
                            <option value="ordered">Already Ordered</option>
                        </select>
                    </div>
                </div>

                <?php if ($allApproved): ?>
                    <div
                        class="mt-4 flex items-center justify-between gap-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                            <div>
                                <p class="text-sm font-semibold text-emerald-900">All P.O. Files Approved!</p>
                                <p class="text-xs text-emerald-700">You can now mark this order as sent to suppliers.</p>
                            </div>
                        </div>
                        <button onclick="markAllAsOrdered()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                            <i class="fas fa-paper-plane text-xs"></i> Mark All as Ordered
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ─── P.O. FILES BY SUPPLIER ────────────────────────────────── -->
        <?php if (!empty($attachmentsBySupplier)): ?>
            <div class="space-y-4">
                <?php foreach ($attachmentsBySupplier as $supplier => $supplierFiles): ?>

                    <div class="bg-white rounded-xl border border-slate-200 overflow-visible">

                        <!-- Supplier Header -->
                        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-blue-50 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-building text-blue-500 text-xs"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($supplier) ?></span>
                            </div>
                            <span class="text-xs text-slate-400"><?= count($supplierFiles) ?> file(s)</span>
                        </div>

                        <!-- Files -->
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($supplierFiles as $file): ?>

                                <?php
                                $isFullyApproved = ($file['superadmin_approval_status'] == 'approved' && $file['approval_status'] == 'approved');
                                $isRejectedSA = ($file['superadmin_approval_status'] == 'rejected');
                                $isRejectedDC = ($file['approval_status'] == 'rejected');
                                $isPendingSA = ($file['superadmin_approval_status'] == 'pending');
                                $isPendingDC = ($file['superadmin_approval_status'] == 'approved' && $file['approval_status'] == 'pending');
                                $isOrdered = ($file['marked_as_ordered'] == 1);
                                $allReceived = ($file['all_items_received'] == 1);
                                ?>

                                <div class="file-card flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50 transition-colors relative"
                                    data-status="<?= $file['approval_status'] ?>" data-ordered="<?= $file['marked_as_ordered'] ?>">

                                    <!-- Left: File Info -->
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div
                                            class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-file-excel text-emerald-600"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate">
                                                <?= htmlspecialchars($file['original_filename']) ?>
                                            </p>
                                            <p class="text-xs text-slate-400 mt-0.5">
                                                <i class="fas fa-calendar mr-1"></i>
                                                Uploaded <?= date('M j, Y g:i A', strtotime($file['uploaded_at'])) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Middle: Status Badges -->
                                    <div class="flex flex-wrap items-center gap-1.5">

                                        <!-- Delivery / Order Status -->
                                        <?php if ($allReceived): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <i class="fas fa-check-double"></i>
                                                All Received &mdash; <?= date('M j, Y', strtotime($file['all_items_received_at'])) ?>
                                            </span>
                                        <?php elseif ($isOrdered): ?>
                                            <?php if ($file['po_status'] == 'currently_receiving'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    <i class="fas fa-inbox"></i> Currently Receiving
                                                </span>
                                            <?php elseif ($file['po_status'] == 'out_for_delivery'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-truck"></i> Out for Delivery
                                                </span>
                                            <?php elseif ($file['po_status'] == 'supplier_confirmed'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                    <i class="fas fa-check-circle"></i> Supplier Confirmed
                                                </span>
                                                <?php if ($file['expected_delivery_date']): ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        <i class="fas fa-calendar"></i>
                                                        <?= date('M j, Y', strtotime($file['expected_delivery_date'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-shipping-fast"></i>
                                                    Ordered &mdash; <?= date('M j, Y', strtotime($file['marked_as_ordered_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Approval Status -->
                                        <?php if ($isPendingSA): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                <i class="fas fa-hourglass-half"></i> Pending Superadmin
                                            </span>
                                        <?php elseif ($isRejectedSA): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-ban"></i> Rejected by Superadmin
                                            </span>
                                        <?php elseif ($isPendingDC): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                <i class="fas fa-clock"></i> Pending Doc Controller
                                            </span>
                                        <?php elseif ($isFullyApproved): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                <i class="fas fa-check-double"></i> Fully Approved
                                            </span>
                                        <?php elseif ($isRejectedDC): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle"></i> Rejected by Doc Controller
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right: 3-Dot Menu -->
                                    <div class="relative flex-shrink-0">
                                        <button type="button" onclick="toggleDropdown(<?= $file['id'] ?>)"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors">
                                            <i class="fas fa-ellipsis-v text-xs"></i>
                                        </button>

                                        <div id="dropdown-<?= $file['id'] ?>"
                                            class="hidden absolute right-0 bottom-full mb-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden">

                                            <!-- Download -->
                                            <?php if ($isFullyApproved): ?>
                                                <a href="?order_id=<?= $order_id ?>&download=1&file_id=<?= $file['id'] ?>"
                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 transition-colors">
                                                    <i class="fas fa-download w-4"></i> Download with Signatures
                                                </a>
                                            <?php else: ?>
                                                <div
                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed">
                                                    <i class="fas fa-lock w-4"></i> Download (Pending Approval)
                                                </div>
                                            <?php endif; ?>

                                            <!-- Edit P.O. -->
                                            <?php if ($isRejectedSA || $isRejectedDC || !$isOrdered): ?>
                                                <a href="warehouse_staff_generate_po_A-B.php?order_id=<?= $order_id ?>&edit_po=<?= $file['id'] ?>"
                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition-colors">
                                                    <i class="fas fa-edit w-4"></i> Edit P.O.
                                                </a>
                                            <?php endif; ?>

                                            <!-- Request Approval -->
                                            <?php if (!$isOrdered && $file['superadmin_approval_status'] != 'pending' && $file['superadmin_approval_status'] != 'approved' && $file['approval_requested_at'] == null): ?>
                                                <button onclick="submitAction('request_approval', <?= $file['id'] ?>)"
                                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                                    <i class="fas fa-paper-plane w-4"></i> Request Approval
                                                </button>
                                            <?php endif; ?>

                                            <!-- Mark as Ordered -->
                                            <?php if (!$isOrdered && $isFullyApproved): ?>
                                                <button
                                                    onclick="markSingleAsOrdered(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>')"
                                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-purple-700 hover:bg-purple-50 transition-colors text-left">
                                                    <i class="fas fa-paper-plane w-4"></i> Mark as Ordered
                                                </button>
                                            <?php endif; ?>

                                            <!-- Status Updates (when ordered) -->
                                            <?php if ($isOrdered): ?>
                                                <?php if ($allReceived): ?>
                                                    <div class="px-4 py-2.5 text-xs text-slate-400">
                                                        <i class="fas fa-check-double mr-1"></i> All Items Received — Complete
                                                    </div>
                                                <?php elseif ($file['po_status'] == 'currently_receiving'): ?>
                                                    <div class="px-4 py-2.5 text-xs text-slate-400">
                                                        <i class="fas fa-hourglass-half mr-1"></i> Receiving in Progress
                                                    </div>
                                                <?php elseif ($file['po_status'] == 'out_for_delivery'): ?>
                                                    <button
                                                        onclick="updatePoStatus(<?= $file['id'] ?>, 'currently_receiving', '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>')"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-purple-700 hover:bg-purple-50 transition-colors text-left">
                                                        <i class="fas fa-inbox w-4"></i> Mark as Currently Receiving
                                                    </button>
                                                    <button
                                                        onclick="updatePoStatus(<?= $file['id'] ?>, 'supplier_confirmed', '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>', true)"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-amber-700 hover:bg-amber-50 transition-colors text-left">
                                                        <i class="fas fa-undo w-4"></i> Back to Confirmed
                                                    </button>
                                                <?php elseif ($file['po_status'] == 'supplier_confirmed'): ?>
                                                    <button
                                                        onclick="updatePoStatus(<?= $file['id'] ?>, 'out_for_delivery', '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>')"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-orange-700 hover:bg-orange-50 transition-colors text-left">
                                                        <i class="fas fa-truck w-4"></i> Mark as Out for Delivery
                                                    </button>
                                                    <button
                                                        onclick="updatePoStatus(<?= $file['id'] ?>, 'supplier_confirmed', '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>', true)"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition-colors text-left">
                                                        <i class="fas fa-edit w-4"></i> Update Expected Date
                                                    </button>
                                                <?php else: ?>
                                                    <button
                                                        onclick="updatePoStatus(<?= $file['id'] ?>, 'supplier_confirmed', '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>', true)"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 transition-colors text-left">
                                                        <i class="fas fa-check-circle w-4"></i> Mark as Supplier Confirmed
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <div class="border-t border-slate-100 mt-1">
                                                <button
                                                    onclick="confirmDelete(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_filename'], ENT_QUOTES) ?>')"
                                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors text-left">
                                                    <i class="fas fa-trash w-4"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-excel text-slate-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-700 mb-1">No P.O. Files Found</h3>
                <p class="text-sm text-slate-400 mb-5">No purchase order files have been uploaded for this order yet.</p>
                <a href="warehouse_staff_management_main.php"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-arrow-left text-xs"></i> Back to Orders
                </a>
            </div>
        <?php endif; ?>

    </div><!-- /main -->


    <!-- ─── MODALS ────────────────────────────────────────────────────── -->

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-trash text-red-600"></i>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800">Delete P.O. File</h3>
                </div>
                <p class="text-sm text-slate-600 mb-6">
                    Are you sure you want to delete <strong id="fileName"></strong>? This cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeDeleteModal()"
                        class="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="file_id" id="deleteFileId">
                        <button type="submit" name="delete_file"
                            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-trash mr-1.5 text-xs"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Single as Ordered Modal -->
    <div id="markOrderedModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-paper-plane text-purple-600"></i>
                        </div>
                        <h3 class="text-base font-semibold text-slate-800">Mark as Ordered</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-6">
                        Confirm that you have sent <strong id="orderedFileName" class="break-all"></strong> to the
                        supplier?
                    </p>
                    <input type="hidden" name="file_ids[]" id="orderedFileId">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeMarkOrderedModal()"
                            class="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="mark_as_ordered"
                            class="px-4 py-2 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-paper-plane mr-1.5 text-xs"></i> Mark as Ordered
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark All as Ordered Modal -->
    <div id="markAllOrderedModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-paper-plane text-emerald-600"></i>
                        </div>
                        <h3 class="text-base font-semibold text-slate-800">Mark All as Ordered</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-3">
                        Mark all approved P.O. files as sent to suppliers?
                    </p>
                    <div
                        class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800 mb-6">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        This will mark <strong><?= $approvedCount ?> file(s)</strong> as ordered.
                    </div>
                    <div id="markAllFileIds"></div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeMarkAllOrderedModal()"
                            class="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="mark_as_ordered"
                            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-paper-plane mr-1.5 text-xs"></i> Mark All as Ordered
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-sync-alt text-blue-600"></i>
                        </div>
                        <h3 class="text-base font-semibold text-slate-800">Update P.O. Status</h3>
                    </div>
                    <p class="text-sm text-slate-500 mb-4 break-all">
                        File: <strong id="statusFileName"></strong>
                    </p>

                    <input type="hidden" name="file_id" id="statusFileId">
                    <input type="hidden" name="new_status" id="newStatusValue">

                    <!-- Expected Date -->
                    <div id="expectedDateField" class="hidden mb-4">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                            <i class="fas fa-calendar mr-1"></i>Expected Delivery Date
                        </label>
                        <input type="date" name="expected_delivery_date" id="expectedDeliveryDate"
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition-shadow">
                    </div>

                    <!-- P.O. Number -->
                    <div id="poNumberField" class="hidden mb-4">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                            <i class="fas fa-barcode mr-1"></i>P.O. Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="po_number" id="poNumberInput" placeholder="e.g., NH10202025922331"
                            readonly
                            class="w-full px-3 py-2 text-sm font-mono border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 bg-slate-50 transition-shadow">
                        <p class="text-xs text-slate-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>This P.O. number will be assigned to the receiver
                        </p>
                    </div>

                    <!-- Receiver Select -->
                    <div id="receiverSelectField" class="hidden mb-4">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                            <i class="fas fa-user-check mr-1"></i>Assign to Receiver <span class="text-red-500">*</span>
                        </label>
                        <select name="receiver_id" id="receiverSelect"
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition-shadow">
                            <option value="">— Select Receiver —</option>
                            <?php
                            $receiversSql = "SELECT na.id, na.fullname, COUNT(pra.id) as active_workload
                                             FROM nobleaccount na
                                             LEFT JOIN po_receiver_assignments pra ON na.id = pra.receiver_id AND pra.status = 'active'
                                             WHERE na.subrole = 'warehouse_receiver' AND na.status = 'active'
                                             GROUP BY na.id, na.fullname
                                             ORDER BY active_workload ASC, na.fullname ASC";
                            $receiversStmt = $conn->query($receiversSql);
                            if ($receiversStmt) {
                                while ($receiver = $receiversStmt->fetch_assoc()) {
                                    $badge = $receiver['active_workload'] > 0 ? " ({$receiver['active_workload']} active)" : " (Available)";
                                    echo '<option value="' . $receiver['id'] . '">' . htmlspecialchars($receiver['fullname']) . $badge . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Receivers with fewer active P.O.s are shown first
                        </p>
                    </div>

                    <!-- Info Banner -->
                    <div
                        class="flex items-start gap-2 px-3 py-2.5 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800 mb-5">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 flex-shrink-0"></i>
                        <span id="statusUpdateMessage"></span>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeUpdateStatusModal()"
                            class="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="update_po_status"
                            class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-circle-check mr-1.5 text-xs"></i>
                            <span id="updateStatusButtonText">Update Status</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        /* ── Dropdown ── */
        function toggleDropdown(fileId) {
            const target = document.getElementById('dropdown-' + fileId);
            document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
                if (d.id !== 'dropdown-' + fileId) d.classList.add('hidden');
            });
            target.classList.toggle('hidden');
        }
        document.addEventListener('click', e => {
            if (!e.target.closest('[onclick^="toggleDropdown"]') && !e.target.closest('[id^="dropdown-"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(d => d.classList.add('hidden'));
            }
        });

        /* ── Filter ── */
        function filterFiles() {
            const filter = document.getElementById('statusFilter').value;
            document.querySelectorAll('.file-card').forEach(card => {
                const status = card.dataset.status;
                const ordered = card.dataset.ordered;
                let show = false;
                if (filter === 'all') show = true;
                if (filter === 'ready') show = (status === 'approved' && ordered === '0');
                if (filter === 'pending') show = (status === 'pending');
                if (filter === 'ordered') show = (ordered === '1');
                card.style.display = show ? 'flex' : 'none';
            });
        }

        /* ── Delete Modal ── */
        function confirmDelete(fileId, fileName) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('fileName').textContent = fileName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }
        document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

        /* ── Mark Single as Ordered ── */
        function markSingleAsOrdered(fileId, fileName) {
            document.getElementById('orderedFileId').value = fileId;
            document.getElementById('orderedFileName').textContent = fileName;
            document.getElementById('markOrderedModal').classList.remove('hidden');
        }
        function closeMarkOrderedModal() { document.getElementById('markOrderedModal').classList.add('hidden'); }
        document.getElementById('markOrderedModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeMarkOrderedModal(); });

        /* ── Mark All as Ordered ── */
        function markAllAsOrdered() {
            const approvedFiles = <?= json_encode(array_values(array_filter(array_map(fn($a) => $a['approval_status'] == 'approved' && $a['marked_as_ordered'] == 0 ? $a['id'] : null, $attachments), fn($v) => $v !== null))) ?>;
            const container = document.getElementById('markAllFileIds');
            container.innerHTML = '';
            approvedFiles.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'file_ids[]'; inp.value = id;
                container.appendChild(inp);
            });
            document.getElementById('markAllOrderedModal').classList.remove('hidden');
        }
        function closeMarkAllOrderedModal() { document.getElementById('markAllOrderedModal').classList.add('hidden'); }
        document.getElementById('markAllOrderedModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeMarkAllOrderedModal(); });

        /* ── Update Status Modal ── */
        function updatePoStatus(fileId, newStatus, fileName, needsDate = false) {
            document.getElementById('statusFileId').value = fileId;
            document.getElementById('newStatusValue').value = newStatus;
            document.getElementById('statusFileName').textContent = fileName;

            const expectedDateField = document.getElementById('expectedDateField');
            const expectedDateInput = document.getElementById('expectedDeliveryDate');
            const poNumberField = document.getElementById('poNumberField');
            const poNumberInput = document.getElementById('poNumberInput');
            const receiverField = document.getElementById('receiverSelectField');
            const receiverSelect = document.getElementById('receiverSelect');
            const msg = document.getElementById('statusUpdateMessage');
            const btn = document.getElementById('updateStatusButtonText');

            // Reset
            [expectedDateField, poNumberField, receiverField].forEach(f => f.classList.add('hidden'));
            [expectedDateInput, poNumberInput, receiverSelect].forEach(f => { f.removeAttribute('required'); f.value = ''; });

            if (newStatus === 'supplier_confirmed') {
                expectedDateField.classList.remove('hidden');
                expectedDateInput.setAttribute('required', 'required');
                msg.textContent = 'Supplier has confirmed the order. Please provide the expected delivery date.';
                btn.textContent = needsDate ? 'Update Expected Date' : 'Confirm with Date';

            } else if (newStatus === 'out_for_delivery') {
                msg.textContent = 'Mark this P.O. as out for delivery.';
                btn.textContent = 'Mark Out for Delivery';

            } else if (newStatus === 'currently_receiving') {
                poNumberField.classList.remove('hidden');
                poNumberInput.setAttribute('required', 'required');
                const match = fileName.match(/PO_([A-Z0-9]+)_/i);
                if (match) {
                    poNumberInput.value = match[1];
                    poNumberInput.readOnly = true;
                } else {
                    poNumberInput.readOnly = false;
                    poNumberInput.placeholder = 'Enter P.O. Number';
                }
                receiverField.classList.remove('hidden');
                receiverSelect.setAttribute('required', 'required');
                msg.textContent = 'Assign this P.O. to a warehouse receiver for processing.';
                btn.textContent = 'Assign to Receiver';
            }

            document.getElementById('updateStatusModal').classList.remove('hidden');
        }
        function closeUpdateStatusModal() {
            document.getElementById('updateStatusModal').classList.add('hidden');
            document.getElementById('expectedDeliveryDate').value = '';
        }
        document.getElementById('updateStatusModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeUpdateStatusModal(); });

        /* ── Submit form actions ── */
        function submitAction(action, fileId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="${action}" value="1"><input type="hidden" name="file_id" value="${fileId}">`;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

</body>

</html>