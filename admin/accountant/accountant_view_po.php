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

// Get P.O. attachments grouped by supplier
$attachmentsSql = "SELECT pa.*, 
                   requester.fullname as requested_by_name,
                   approver.fullname as approved_by_name
                   FROM po_attachments pa
                   LEFT JOIN nobleaccount requester ON pa.approval_requested_by = requester.id
                   LEFT JOIN nobleaccount approver ON pa.approved_by = approver.id
                   WHERE pa.order_id = ? 
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
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
        header('Content-Length: ' . filesize($file['file_path']));
        readfile($file['file_path']);
        exit();
    } else {
        $error_message = "File not found or has been deleted.";
    }
}

// Handle approval with optional file replacement
if (isset($_POST['approve_with_file'])) {
    $file_id = (int)$_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    // Get original file info
    $getFileSql = "SELECT * FROM po_attachments WHERE id = ? AND order_id = ?";
    $getFileStmt = $conn->prepare($getFileSql);
    $getFileStmt->bind_param("ii", $file_id, $order_id);
    $getFileStmt->execute();
    $originalFile = $getFileStmt->get_result()->fetch_assoc();
    $getFileStmt->close();
    
    if (!$originalFile) {
        $error_message = "File not found.";
    } else {
        $file_path = $originalFile['file_path'];
        $original_filename = $originalFile['original_filename'];
        
        // Check if a new file was uploaded
        if (isset($_FILES['updated_po_file']) && $_FILES['updated_po_file']['error'] === UPLOAD_ERR_OK) {
            $upload_file = $_FILES['updated_po_file'];
            
            // Validate file
            $allowed_extensions = ['xlsx', 'xls'];
            $file_extension = strtolower(pathinfo($upload_file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_extensions)) {
                $upload_dir = '../../uploads/po_files/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate new filename
                $new_filename = 'PO_' . $order_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($upload_file['tmp_name'], $target_path)) {
                    // Delete old physical file
                    if (file_exists($originalFile['file_path'])) {
                        unlink($originalFile['file_path']);
                    }
                    
                    // Update with new file path and filename
                    $file_path = $target_path;
                    $original_filename = $upload_file['name'];
                } else {
                    $error_message = "Failed to upload replacement file.";
                }
            } else {
                $error_message = "Invalid file type. Only Excel files (.xlsx, .xls) are allowed.";
            }
        }
        
        // Approve the file (with or without replacement)
        if (!isset($error_message)) {
            $approveSql = "UPDATE po_attachments 
                           SET approval_status = 'approved', 
                               approved_by = ?,
                               approved_at = NOW(),
                               file_path = ?,
                               original_filename = ?,
                               uploaded_at = NOW()
                           WHERE id = ? AND order_id = ?";
            $approveStmt = $conn->prepare($approveSql);
            $approveStmt->bind_param("issii", $current_user_id, $file_path, $original_filename, $file_id, $order_id);
            
            if ($approveStmt->execute()) {
                if (isset($_FILES['updated_po_file']) && $_FILES['updated_po_file']['error'] === UPLOAD_ERR_OK) {
                    $success_message = "File updated and approved successfully.";
                } else {
                    $success_message = "File approved successfully.";
                }
                header("Location: accountant_view_po.php?order_id=" . $order_id);
                exit();
            } else {
                $error_message = "Failed to approve file.";
            }
            $approveStmt->close();
        }
    }
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>
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
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
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
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 border-b border-gray-200">
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
            <div class="bg-green-100 p-2 rounded-lg">
                <i class="fas fa-file-excel text-green-600 text-xl"></i>
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
            </div>
        </div>
                                        <div class="flex items-center space-x-2">
    <!-- Ordered Badge -->
    <?php if ($file['marked_as_ordered'] == 1): ?>
        <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
            <i class="fas fa-shipping-fast mr-1"></i>
            Ordered on <?php echo date('M j, Y', strtotime($file['marked_as_ordered_at'])); ?>
        </span>
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
       class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
        <i class="fas fa-download mr-2"></i>
        Download
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
                <i class="fas fa-file-excel text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No P.O. Files Found</h3>
                <p class="text-sm text-gray-500 mb-4">No purchase order files have been uploaded for this order yet.</p>
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
            <form method="POST" enctype="multipart/form-data">
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
                            <div class="bg-green-100 p-2 rounded-lg flex-shrink-0">
                                <i class="fas fa-file-excel text-green-600 text-2xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 mb-1" id="approveFileNameDisplay"></h4>
                                <div class="space-y-1 text-sm text-gray-600">
                                    <p id="approveFileSupplier"></p>
                                    <p id="approveFileSize"></p>
                                    <p id="approveFileUploader"></p>
                                </div>
                                <!-- Download button in modal -->
                                <a id="approveFileDownloadLink" href="#" target="_blank"
                                   class="inline-flex items-center mt-3 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-download mr-1"></i>
                                    Download & Review Current File
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Option to Replace File While Approving -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <input type="checkbox" id="replaceWhileApproving" 
                                   onchange="toggleReplaceFileSection()"
                                   class="mt-1 mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <div class="flex-1">
                                <label for="replaceWhileApproving" class="font-medium text-blue-900 cursor-pointer">
                                    <i class="fas fa-upload mr-1"></i>
                                    Upload a corrected/updated file
                                </label>
                                <p class="text-sm text-blue-700 mt-1">
                                    Check this if you want to replace the current file with a corrected version before approving.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- File Upload Section (Hidden by default) -->
                    <div id="replaceFileSection" class="hidden mb-4">
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-excel mr-1"></i>
                                Select Updated P.O. File
                            </label>
                            <input type="file" 
                                   name="updated_po_file" 
                                   id="updatedPoFile"
                                   accept=".xlsx,.xls"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white">
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Only Excel files (.xlsx, .xls) are allowed. This will replace the current file.
                            </p>
                        </div>
                    </div>
                    
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to approve this P.O. file? 
                        <span class="block text-sm text-gray-500 mt-1">
                            <span id="approvalActionText">The file will be approved as-is.</span>
                        </span>
                    </p>
                    
                    <input type="hidden" name="file_id" id="approveFileId">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeApproveModal()" 
                                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" name="approve_with_file" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                            <i class="fas fa-check mr-2"></i>
                            <span id="approveButtonText">Approve File</span>
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
// Toggle the file replacement section in approve modal
function toggleReplaceFileSection() {
    const checkbox = document.getElementById('replaceWhileApproving');
    const section = document.getElementById('replaceFileSection');
    const fileInput = document.getElementById('updatedPoFile');
    const actionText = document.getElementById('approvalActionText');
    const buttonText = document.getElementById('approveButtonText');
    
    if (checkbox.checked) {
        section.classList.remove('hidden');
        fileInput.setAttribute('required', 'required');
        actionText.textContent = 'The file will be replaced with your upload and then approved.';
        buttonText.textContent = 'Upload & Approve';
    } else {
        section.classList.add('hidden');
        fileInput.removeAttribute('required');
        fileInput.value = ''; // Clear the file input
        actionText.textContent = 'The file will be approved as-is.';
        buttonText.textContent = 'Approve File';
    }
}

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
    // Reset the checkbox and hide replacement section
    document.getElementById('replaceWhileApproving').checked = false;
    document.getElementById('replaceFileSection').classList.add('hidden');
    document.getElementById('updatedPoFile').removeAttribute('required');
    document.getElementById('updatedPoFile').value = '';
    document.getElementById('approvalActionText').textContent = 'The file will be approved as-is.';
    document.getElementById('approveButtonText').textContent = 'Approve File';
    
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
    
    // Set download link
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