<?php
// view_po_files.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: order_list.php");
    exit();
}

// Get order details
$orderSql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: order_list.php");
    exit();
}

// Get P.O. attachments
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

// Handle mark as ordered
if (isset($_POST['mark_as_ordered'])) {
    $file_ids = isset($_POST['file_ids']) ? $_POST['file_ids'] : [];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    if (!empty($file_ids)) {
        $file_ids_str = implode(',', array_map('intval', $file_ids));
        
        $markSql = "UPDATE po_attachments 
                    SET marked_as_ordered = 1,
                        marked_as_ordered_at = NOW(),
                        marked_as_ordered_by = ?
                    WHERE id IN ($file_ids_str) 
                    AND order_id = ? 
                    AND approval_status = 'approved'";
        $markStmt = $conn->prepare($markSql);
        $markStmt->bind_param("ii", $current_user_id, $order_id);
        
        if ($markStmt->execute()) {
            $success_message = "P.O. file(s) marked as ordered successfully.";
            header("Location: view_po_files.php?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to mark files as ordered.";
        }
        $markStmt->close();
    }
}

// Handle approval request
if (isset($_POST['request_approval'])) {
    $file_id = (int)$_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    $requestSql = "UPDATE po_attachments 
                   SET approval_status = 'pending', 
                       approval_requested_at = NOW(),
                       approval_requested_by = ?
                   WHERE id = ? AND order_id = ?";
    $requestStmt = $conn->prepare($requestSql);
    $requestStmt->bind_param("iii", $current_user_id, $file_id, $order_id);
    
    if ($requestStmt->execute()) {
        $success_message = "Approval request submitted successfully.";
        header("Location: view_po_files.php?order_id=" . $order_id);
        exit();
    } else {
        $error_message = "Failed to submit approval request.";
    }
    $requestStmt->close();
}

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $file_id = (int)$_POST['file_id'];
    $deleteSql = "SELECT * FROM po_attachments WHERE id = ? AND order_id = ? LIMIT 1";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("ii", $file_id, $order_id);
    $deleteStmt->execute();
    $fileToDelete = $deleteStmt->get_result()->fetch_assoc();
    $deleteStmt->close();
    
    if ($fileToDelete) {
        // Delete from database
        $deleteDbSql = "DELETE FROM po_attachments WHERE id = ?";
        $deleteDbStmt = $conn->prepare($deleteDbSql);
        $deleteDbStmt->bind_param("i", $file_id);
        if ($deleteDbStmt->execute()) {
            // Delete physical file
            if (file_exists($fileToDelete['file_path'])) {
                unlink($fileToDelete['file_path']);
            }
            $success_message = "File deleted successfully.";
            // Refresh the page to update the list
            header("Location: view_po_files.php?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to delete file from database.";
        }
        $deleteDbStmt->close();
    } else {
        $error_message = "File not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View P.O. Files - Order #<?php echo $order_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <a href="order_list.php" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-file-excel text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">P.O. Files</h1>
                        <p class="text-gray-600 mt-1">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-blue-50 px-4 py-2 rounded-lg mb-2">
                        <span class="text-blue-700 font-medium">Status: <?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                    </div>
                    <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <span class="text-blue-700 font-medium"><?php echo count($attachments); ?> P.O. Files</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
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
            <h2 class="text-xl font-bold text-gray-900 mb-4">Order Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                    <p class="text-lg font-bold text-primary-600">₱<?php echo number_format((float)$order['total'], 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Statistics and Filter Section -->
        <?php
        // Count approved files ready to order
        $approvedCount = 0;
        $pendingCount = 0;
        $orderedCount = 0;
        $totalFiles = count($attachments);
        
        foreach ($attachments as $att) {
            if ($att['approval_status'] == 'approved' && $att['marked_as_ordered'] == 0) {
                $approvedCount++;
            } elseif ($att['approval_status'] == 'pending') {
                $pendingCount++;
            } elseif ($att['marked_as_ordered'] == 1) {
                $orderedCount++;
            }
        }
        
        $allApproved = ($approvedCount > 0 && $pendingCount == 0);
        ?>
        
        <?php if ($totalFiles > 0): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center space-x-4">
                    <h3 class="text-lg font-bold text-gray-900">P.O. Status Overview</h3>
                </div>
                
                <div class="flex items-center space-x-3">
                    <?php if ($approvedCount > 0): ?>
                        <div class="bg-green-50 border border-green-200 px-4 py-2 rounded-lg">
                            <span class="text-green-700 font-medium">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?php echo $approvedCount; ?> Ready to Order
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pendingCount > 0): ?>
                        <div class="bg-yellow-50 border border-yellow-200 px-4 py-2 rounded-lg">
                            <span class="text-yellow-700 font-medium">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $pendingCount; ?> Pending Approval
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($orderedCount > 0): ?>
                        <div class="bg-blue-50 border border-blue-200 px-4 py-2 rounded-lg">
                            <span class="text-blue-700 font-medium">
                                <i class="fas fa-shipping-fast mr-1"></i>
                                <?php echo $orderedCount; ?> Already Ordered
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filter Dropdown -->
                    <select id="statusFilter" onchange="filterFiles()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Files</option>
                        <option value="ready">Ready to Order</option>
                        <option value="pending">Pending Approval</option>
                        <option value="ordered">Already Ordered</option>
                    </select>
                </div>
            </div>
            
            <?php if ($allApproved): ?>
                <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                        <div>
                            <p class="font-semibold text-green-900">All P.O. Files Approved!</p>
                            <p class="text-sm text-green-700">You can now mark this order as sent to suppliers.</p>
                        </div>
                    </div>
                    <button onclick="markAllAsOrdered()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2 font-medium">
                        <i class="fas fa-paper-plane"></i>
                        <span>Mark All as Ordered</span>
                    </button>
                </div>
            <?php endif; ?>
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
                                    <div class="file-card flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200"
                                         data-status="<?php echo $file['approval_status']; ?>"
                                         data-ordered="<?php echo $file['marked_as_ordered']; ?>">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-green-100 p-2 rounded-lg">
                                                <i class="fas fa-file-excel text-green-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    Uploaded: <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                                                </p>
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
    
    <!-- Approval Status Badge -->
    <?php if ($file['approval_status'] == 'pending'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
            <i class="fas fa-clock mr-1"></i>
            Pending Approval
        </span>
    <?php elseif ($file['approval_status'] == 'approved'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
            <i class="fas fa-check-circle mr-1"></i>
            Approved
        </span>
    <?php elseif ($file['approval_status'] == 'rejected'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
            <i class="fas fa-times-circle mr-1"></i>
            Rejected
        </span>
    <?php endif; ?>
    
    <a href="?order_id=<?php echo $order_id; ?>&download=1&file_id=<?php echo $file['id']; ?>" 
       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-1 text-sm">
        <i class="fas fa-download"></i>
        <span>Download</span>
    </a>
    
    <?php if ($file['marked_as_ordered'] == 0): ?>
        <?php if ($file['approval_status'] != 'pending' && $file['approval_requested_at'] == null): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                <button type="submit" name="request_approval"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-1 text-sm">
                    <i class="fas fa-check"></i>
                    <span>Request Approval</span>
                </button>
            </form>
        <?php endif; ?>
        
        <?php if ($file['approval_status'] == 'approved'): ?>
            <button onclick="markSingleAsOrdered(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-1 text-sm">
                <i class="fas fa-paper-plane"></i>
                <span>Mark as Ordered</span>
            </button>
        <?php endif; ?>
    <?php endif; ?>
    
    <button onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-1 text-sm">
        <i class="fas fa-trash"></i>
        <span>Delete</span>
    </button>
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
                <div class="text-gray-500">
                    <i class="fas fa-file-excel text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium mb-2">No P.O. Files Found</h3>
                    <p class="text-sm mb-4">No purchase order files have been uploaded for this order yet.</p>
                    <a href="order_list.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Orders
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-red-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-trash text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Delete P.O. File</h3>
                    </div>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete <strong id="fileName"></strong>? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-3">
                        <button onclick="closeDeleteModal()" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                            Cancel
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="file_id" id="deleteFileId">
                            <button type="submit" name="delete_file" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                                <i class="fas fa-trash mr-2"></i>Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark as Ordered Modal (Single File) -->
    <div id="markOrderedModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-purple-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-paper-plane text-purple-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Mark as Ordered</h3>
                        </div>
                        <p class="text-gray-600 mb-6">
                            Are you sure you want to mark <strong id="orderedFileName"></strong> as ordered? 
                            This means you have already sent this P.O. to the supplier.
                        </p>
                        <input type="hidden" name="file_ids[]" id="orderedFileId">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeMarkOrderedModal()" 
                                    class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                Cancel
                            </button>
                            <button type="submit" name="mark_as_ordered" 
                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Mark as Ordered
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark All as Ordered Modal -->
    <div id="markAllOrderedModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-green-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-paper-plane text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Mark All as Ordered</h3>
                        </div>
                        <p class="text-gray-600 mb-4">
                            Are you sure you want to mark <strong>all approved P.O. files</strong> as ordered?
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                This will mark <strong><?php echo $approvedCount; ?> file(s)</strong> as sent to suppliers.
                            </p>
                        </div>
                        <div id="markAllFileIds"></div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeMarkAllOrderedModal()" 
                                    class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                Cancel
                            </button>
                            <button type="submit" name="mark_as_ordered" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Mark All as Ordered
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(fileId, fileName) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('fileName').textContent = fileName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function markSingleAsOrdered(fileId, fileName) {
            document.getElementById('orderedFileId').value = fileId;
            document.getElementById('orderedFileName').textContent = fileName;
            document.getElementById('markOrderedModal').classList.remove('hidden');
        }

        function closeMarkOrderedModal() {
            document.getElementById('markOrderedModal').classList.add('hidden');
        }

        function markAllAsOrdered() {
            // Get all approved file IDs
            const approvedFiles = <?php 
                $approvedFileIds = [];
                foreach ($attachments as $att) {
                    if ($att['approval_status'] == 'approved' && $att['marked_as_ordered'] == 0) {
                        $approvedFileIds[] = $att['id'];
                    }
                }
                echo json_encode($approvedFileIds);
            ?>;
            
            // Add hidden inputs for all file IDs
            const container = document.getElementById('markAllFileIds');
            container.innerHTML = '';
            approvedFiles.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_ids[]';
                input.value = id;
                container.appendChild(input);
            });
            
            document.getElementById('markAllOrderedModal').classList.remove('hidden');
        }

        function closeMarkAllOrderedModal() {
            document.getElementById('markAllOrderedModal').classList.add('hidden');
        }

        // Filter files by status
        function filterFiles() {
            const filter = document.getElementById('statusFilter').value;
            const fileCards = document.querySelectorAll('.file-card');
            
            fileCards.forEach(card => {
                const status = card.getAttribute('data-status');
                const ordered = card.getAttribute('data-ordered');
                
                let show = false;
                
                if (filter === 'all') {
                    show = true;
                } else if (filter === 'ready') {
                    show = (status === 'approved' && ordered === '0');
                } else if (filter === 'pending') {
                    show = (status === 'pending');
                } else if (filter === 'ordered') {
                    show = (ordered === '1');
                }
                
                if (show) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Close modals when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        document.getElementById('markOrderedModal').addEventListener('click', function(e) {
            if (e.target === this) closeMarkOrderedModal();
        });

        document.getElementById('markAllOrderedModal').addEventListener('click', function(e) {
            if (e.target === this) closeMarkAllOrderedModal();
        });
    </script>
</body>
</html>