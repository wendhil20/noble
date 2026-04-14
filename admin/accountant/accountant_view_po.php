<?php
// accountant_view_po.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);
require_subrole(['document_controller']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: accountant_view_orders.php");
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
    header("Location: accountant_view_orders.php");
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

// Get order items to show what was ordered
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
    $file_id = (int)$_GET['file_id'];
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
    $file_id = (int)$_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    $approveSql = "UPDATE po_attachments 
                   SET approval_status = 'approved', 
                       approved_by = ?,
                       approved_at = NOW()
                   WHERE id = ? AND order_id = ?";
    $approveStmt = $conn->prepare($approveSql);
    $approveStmt->bind_param("iii", $current_user_id, $file_id, $order_id);
    
    if ($approveStmt->execute()) {
        $success_message = "File approved successfully.";
        header("Location: accountant_view_po.php?order_id=" . $order_id);
        exit();
    } else {
        $error_message = "Failed to approve file.";
    }
    $approveStmt->close();
}

// Handle rejection
if (isset($_POST['reject_file'])) {
    $file_id = (int)$_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;
    
    $approveSql = "UPDATE po_attachments 
                   SET approval_status = 'rejected', 
                       approved_by = ?,
                       approved_at = NOW(),
                       rejection_reason = ?
                   WHERE id = ? AND order_id = ?";
    $approveStmt = $conn->prepare($approveSql);
    $approveStmt->bind_param("isii", $current_user_id, $rejection_reason, $file_id, $order_id);
    
    if ($approveStmt->execute()) {
        $success_message = "File rejected successfully.";
        header("Location: accountant_view_po.php?order_id=" . $order_id);
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
    <title>View P.O. Files - Order #<?php echo $order_id; ?></title>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <a href="accountant_view_orders.php" 
                       class="w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center justify-center transition-colors">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-file-excel text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Purchase Order Files</h1>
                        <p class="text-sm text-gray-600">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-blue-50 px-4 py-2 rounded-lg mb-2">
                        <span class="text-blue-700 font-medium">Status: <?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                    </div>
                    <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <span class="text-blue-700 font-medium">
                            <i class="fas fa-file-excel mr-1"></i>
                            <?php echo count($attachments); ?> P.O. File(s)
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="w-full px-6 py-8">
        
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                    <span class="text-red-800"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Order Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600">Customer</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Email</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($order['email']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Total Amount</label>
                    <p class="text-lg font-bold text-noble-orange">₱<?php echo number_format((float)$order['total'], 2); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Warehouse Staff</label>
                    <p class="text-lg font-medium text-gray-900">
                        <?php echo $order['warehouse_employee_name'] ? htmlspecialchars($order['warehouse_employee_name']) : '<span class="text-gray-400">Not assigned</span>'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Order Items Summary -->
        <?php if (!empty($items)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-shopping-basket text-purple-600 mr-2"></i>
                    Order Items (<?php echo count($items); ?>)
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo $item['quantity']; ?></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">₱<?php echo number_format((float)$item['price'], 2); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo $item['supplier_name'] ? htmlspecialchars($item['supplier_name']) : '<span class="text-gray-400">Not assigned</span>'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if ($item['tracking_status']): ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                <?php echo htmlspecialchars($item['tracking_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No status</span>
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
            <div class="space-y-6">
                <?php foreach ($attachmentsBySupplier as $supplier => $supplierFiles): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-blue-50 px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-building mr-2 text-blue-600"></i>
                                    <?php echo htmlspecialchars($supplier); ?>
                                </h3>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo count($supplierFiles); ?> file(s)
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                               <?php foreach ($supplierFiles as $file): ?>
    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
        <div class="flex items-center space-x-3 flex-1">
            <div class="bg-red-100 p-2 rounded-lg">
    <i class="fas fa-file-pdf text-red-600 text-xl"></i>
</div>
            <div class="flex-1">
                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                <p class="text-sm text-gray-600">
                    <i class="fas fa-calendar mr-1"></i>
                    Uploaded: <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                </p>
                <?php if (file_exists($file['file_path'])): ?>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-hdd mr-1"></i>
                        Size: <?php echo number_format(filesize($file['file_path']) / 1024, 2); ?> KB
                    </p>
                <?php endif; ?>
                <?php if ($file['approval_status'] == 'pending' && $file['requested_by_name']): ?>
    <p class="text-xs text-orange-600 mt-1">
        <i class="fas fa-user mr-1"></i>
        Requested by: <?php echo htmlspecialchars($file['requested_by_name']); ?>
        <?php if ($file['approval_requested_at']): ?>
            on <?php echo date('M j, Y g:i A', strtotime($file['approval_requested_at'])); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($file['superadmin_name']): ?>
    <p class="text-xs text-green-600 mt-1">
        <i class="fas fa-shield-alt mr-1"></i>
        Approved by Superadmin: <?php echo htmlspecialchars($file['superadmin_name']); ?>
        <?php if ($file['superadmin_approved_at']): ?>
            on <?php echo date('M j, Y g:i A', strtotime($file['superadmin_approved_at'])); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>
            </div>
        </div>
                                        <div class="flex items-center space-x-2">
    <!-- File Replaced Badge -->
    <?php if (!empty($file['file_replaced']) && $file['file_replaced'] == 1): ?>
        <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
            <i class="fas fa-sync-alt mr-1"></i>
            File Updated on <?php echo date('M j, Y', strtotime($file['file_replaced_at'])); ?>
        </span>
    <?php endif; ?>
    
    <!-- P.O. Status Badges (Read-Only for Accountant) -->
    <?php if ($file['all_items_received'] == 1 && $file['po_status'] == 'received'): ?>
    <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full border-2 border-emerald-300">
        <i class="fas fa-check-double mr-1"></i>
        Fully Received (<?php echo date('M j, Y', strtotime($file['all_items_received_at'])); ?>)
    </span>
<?php elseif ($file['all_items_received'] == 1): ?>
    <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
        <i class="fas fa-check-circle mr-1"></i>
        All Items Received (<?php echo date('M j, Y', strtotime($file['all_items_received_at'])); ?>)
    </span>
<?php elseif ($file['marked_as_ordered'] == 1): ?>
    <?php if ($file['po_status'] == 'currently_receiving'): ?>
        <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
            <i class="fas fa-inbox mr-1"></i>
            Currently Receiving (<?php echo date('M j, Y', strtotime($file['currently_receiving_at'])); ?>)
        </span>
            <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                <i class="fas fa-inbox mr-1"></i>
                Currently Receiving (<?php echo date('M j, Y', strtotime($file['currently_receiving_at'])); ?>)
            </span>
        <?php elseif ($file['po_status'] == 'out_for_delivery'): ?>
            <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                <i class="fas fa-truck mr-1"></i>
                Out for Delivery (<?php echo date('M j, Y', strtotime($file['out_for_delivery_at'])); ?>)
            </span>
        <?php elseif ($file['po_status'] == 'supplier_confirmed'): ?>
            <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                <i class="fas fa-check-circle mr-1"></i>
                Supplier Confirmed (<?php echo date('M j, Y', strtotime($file['supplier_confirmed_at'])); ?>)
            </span>
            <?php if ($file['expected_delivery_date']): ?>
                <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                    <i class="fas fa-calendar mr-1"></i>
                    Expected: <?php echo date('M j, Y', strtotime($file['expected_delivery_date'])); ?>
                </span>
            <?php endif; ?>
        <?php else: ?>
            <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                <i class="fas fa-shipping-fast mr-1"></i>
                Ordered (<?php echo date('M j, Y', strtotime($file['marked_as_ordered_at'])); ?>)
            </span>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Approval Status -->
    <?php if ($file['approval_status'] == 'pending'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
            <i class="fas fa-clock mr-1"></i>
            Pending Approval
        </span>
        <?php
        // Prepare data for JavaScript
        $fileId = $file['id'];
        $fileName = htmlspecialchars($file['original_filename'], ENT_QUOTES);
        $supplierName = htmlspecialchars($supplier, ENT_QUOTES);
        $fileSize = file_exists($file['file_path']) ? number_format(filesize($file['file_path']) / 1024, 2) : '0';
        $uploaderName = htmlspecialchars($file['requested_by_name'] ?? 'Unknown', ENT_QUOTES);
        $uploadDate = $file['approval_requested_at'] ? date('M j, Y g:i A', strtotime($file['approval_requested_at'])) : '';
        ?>
        <button type="button" 
                data-file-id="<?php echo $fileId; ?>"
                data-file-name="<?php echo $fileName; ?>"
                data-supplier="<?php echo $supplierName; ?>"
                data-file-size="<?php echo $fileSize; ?>"
                data-uploader="<?php echo $uploaderName; ?>"
                data-upload-date="<?php echo $uploadDate; ?>"
                onclick="approveFileHandler(this)"
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
            <i class="fas fa-check mr-1"></i>
            Approve
        </button>
        <button type="button" 
                onclick="rejectFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
            <i class="fas fa-times mr-1"></i>
            Reject
        </button>
    <?php elseif ($file['approval_status'] == 'approved'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
            <i class="fas fa-check-circle mr-1"></i>
            Approved by <?php echo htmlspecialchars($file['approved_by_name']); ?>
        </span>
    <?php elseif ($file['approval_status'] == 'rejected'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
            <i class="fas fa-times-circle mr-1"></i>
            Rejected
        </span>
        <?php if ($file['rejection_reason']): ?>
            <button type="button" 
                    onclick="showRejectionReason('<?php echo htmlspecialchars($file['rejection_reason'], ENT_QUOTES); ?>')"
                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900">
                <i class="fas fa-info-circle mr-1"></i>
                View Reason
            </button>
        <?php endif; ?>
    <?php endif; ?>
    
    <a href="?order_id=<?php echo $order_id; ?>&download=1&file_id=<?php echo $file['id']; ?>" 
   target="_blank"
   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
    <i class="fas fa-file-pdf mr-2"></i>
    View PDF
</a>
</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
    <i class="fas fa-file-pdf text-6xl text-gray-300 mb-4"></i>
<h3 class="text-lg font-medium text-gray-900 mb-2">No P.O. Files Found</h3>
    <p class="text-sm text-gray-500 mb-4">No purchase order files have been approved by Superadmin yet.</p>
                <a href="accountant_view_orders.php" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-noble-orange hover:bg-noble-orange-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Orders
                </a>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="mt-8 text-center">
            <a href="accountant_view_orders.php" 
               class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders List
            </a>
        </div>
    </main>
<!-- Approve Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Approve P.O. File</h3>
                    </div>
                    
                    <!-- File Information Card -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                        <div class="flex items-start space-x-3">
                            <div class="bg-red-100 p-2 rounded-lg shrink-0">
                                <i class="fas fa-file-pdf text-red-600 text-2xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 mb-1" id="approveFileNameDisplay"></h4>
                                <div class="space-y-1 text-sm text-gray-600">
                                    <p id="approveFileSupplier"></p>
                                    <p id="approveFileSize"></p>
                                    <p id="approveFileUploader"></p>
                                </div>
                                <!-- View PDF button in modal -->
                                <a id="approveFileDownloadLink" href="#" target="_blank"
                                   class="inline-flex items-center mt-3 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-file-pdf mr-1"></i>
                                    View PDF Before Approving
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to approve this P.O. file?
                    </p>
                    
                    <input type="hidden" name="file_id" id="approveFileId">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeApproveModal()" 
                                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" name="approve_file" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                            <i class="fas fa-check mr-2"></i>
                            Approve File
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-red-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-times text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Reject P.O. File</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Are you sure you want to reject <strong id="rejectFileName"></strong>?</p>
                    <input type="hidden" name="file_id" id="rejectFileId">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for rejection (optional)</label>
                        <textarea name="rejection_reason" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                  placeholder="Enter reason for rejection..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" name="reject_file" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>Reject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Handle approve button clicks using data attributes
function approveFileHandler(button) {
    const fileId = button.getAttribute('data-file-id');
    const fileName = button.getAttribute('data-file-name');
    const supplierName = button.getAttribute('data-supplier');
    const fileSize = button.getAttribute('data-file-size');
    const uploaderName = button.getAttribute('data-uploader');
    const uploadDate = button.getAttribute('data-upload-date');
    
    approveFile(fileId, fileName, supplierName, fileSize, uploaderName, uploadDate);
}

function approveFile(fileId, fileName, supplierName, fileSize, uploaderName, uploadDate) {
    document.getElementById('approveFileId').value = fileId;
    document.getElementById('approveFileNameDisplay').textContent = fileName;
    
    // Set supplier info
    if (supplierName) {
        document.getElementById('approveFileSupplier').innerHTML = '<i class="fas fa-building mr-1 text-gray-400"></i>Supplier: <span class="font-medium">' + escapeHtml(supplierName) + '</span>';
    }
    
    // Set file size
    if (fileSize) {
        document.getElementById('approveFileSize').innerHTML = '<i class="fas fa-hdd mr-1 text-gray-400"></i>File Size: <span class="font-medium">' + fileSize + ' KB</span>';
    }
    
    // Set uploader info
    if (uploaderName && uploadDate) {
        document.getElementById('approveFileUploader').innerHTML = '<i class="fas fa-user mr-1 text-gray-400"></i>Requested by: <span class="font-medium">' + escapeHtml(uploaderName) + '</span> on ' + escapeHtml(uploadDate);
    }
    
    // Set PDF view link
    document.getElementById('approveFileDownloadLink').href = '?order_id=<?php echo $order_id; ?>&download=1&file_id=' + fileId;
    
    document.getElementById('approveModal').classList.remove('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
}

function rejectFile(fileId, fileName) {
    document.getElementById('rejectFileId').value = fileId;
    document.getElementById('rejectFileName').textContent = fileName;
    document.getElementById('rejectModal').classList.add('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

function showRejectionReason(reason) {
    alert('Rejection Reason:\n\n' + reason);
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Close modals when clicking outside
document.getElementById('approveModal').addEventListener('click', function(e) {
    if (e.target === this) closeApproveModal();
});

document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>
</body>
</html>