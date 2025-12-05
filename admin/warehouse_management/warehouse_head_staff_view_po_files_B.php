<?php
// warehouse_head_staff_view_po_files_B.php
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
    header("Location: warehouse_staff_management_main.php");
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
    header("Location: warehouse_staff_management_main.php");
    exit();
}

// Get P.O. attachments
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
            header("Location: warehouse_head_staff_view_po_files_B.php?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to mark files as ordered.";
        }
        $markStmt->close();
    }
}

// Handle P.O. status update
if (isset($_POST['update_po_status'])) {
    $file_id = (int)$_POST['file_id'];
    $new_status = $_POST['new_status'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    // Validate status
    $allowed_statuses = ['supplier_confirmed', 'out_for_delivery', 'currently_receiving'];
    
    if (in_array($new_status, $allowed_statuses)) {
        // Prepare update based on status
        if ($new_status == 'supplier_confirmed') {
            $expected_date = isset($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : NULL;
            
            $updateSql = "UPDATE po_attachments 
                         SET po_status = ?,
                             supplier_confirmed_at = NOW(),
                             expected_delivery_date = ?,
                             status_updated_by = ?
                         WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssiii", $new_status, $expected_date, $current_user_id, $file_id, $order_id);
            
        } elseif ($new_status == 'out_for_delivery') {
            $updateSql = "UPDATE po_attachments 
                         SET po_status = ?,
                             out_for_delivery_at = NOW(),
                             status_updated_by = ?
                         WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("siii", $new_status, $current_user_id, $file_id, $order_id);
            
        } elseif ($new_status == 'currently_receiving') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $po_number = isset($_POST['po_number']) ? trim($_POST['po_number']) : '';
    
    if ($receiver_id <= 0) {
        $error_message = "Please select a receiver.";
    } elseif (empty($po_number)) {
        $error_message = "P.O. number is required.";
    } else {
        // Validate P.O. number exists in order_items
        $validatePoSql = "SELECT COUNT(*) as count FROM order_items WHERE po_number = ? AND order_id = ?";
        $validatePoStmt = $conn->prepare($validatePoSql);
        $validatePoStmt->bind_param("si", $po_number, $order_id);
        $validatePoStmt->execute();
        $validateResult = $validatePoStmt->get_result()->fetch_assoc();
        $validatePoStmt->close();
        
        if ($validateResult['count'] == 0) {
            $error_message = "Invalid P.O. number for this order.";
        } else {
            // Update P.O. status
            $updateSql = "UPDATE po_attachments 
                         SET po_status = ?,
                             currently_receiving_at = NOW(),
                             status_updated_by = ?
                         WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("siii", $new_status, $current_user_id, $file_id, $order_id);
            
            if ($updateStmt->execute()) {
                // Create receiver assignment using the validated P.O. number
                $assignSql = "INSERT INTO po_receiver_assignments 
                             (po_attachment_id, po_number, order_id, receiver_id, assigned_by, assigned_at, status)
                             VALUES (?, ?, ?, ?, ?, NOW(), 'active')";
                $assignStmt = $conn->prepare($assignSql);
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
        
        if ($updateStmt->execute()) {
            $success_message = "P.O. status updated successfully.";
            header("Location: warehouse_head_staff_view_po_files_B.php?order_id=" . $order_id);
            exit();
        } else {
            $error_message = "Failed to update P.O. status.";
        }
        $updateStmt->close();
    } else {
        $error_message = "Invalid status.";
    }
}

// Handle approval request (now goes to superadmin first)
if (isset($_POST['request_approval'])) {
    $file_id = (int)$_POST['file_id'];
    $current_user_id = $_SESSION['noble_id'] ?? 0;
    
    $requestSql = "UPDATE po_attachments 
                   SET superadmin_approval_status = 'pending', 
                       approval_requested_at = NOW(),
                       approval_requested_by = ?
                   WHERE id = ? AND order_id = ?";
    $requestStmt = $conn->prepare($requestSql);
    $requestStmt->bind_param("iii", $current_user_id, $file_id, $order_id);
    
    if ($requestStmt->execute()) {
        $success_message = "Approval request sent to Superadmin successfully.";
        header("Location: warehouse_head_staff_view_po_files_B.php?order_id=" . $order_id);
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
            header("Location: warehouse_head_staff_view_po_files_B.php?order_id=" . $order_id);
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
                    <a href="warehouse_staff_management_main.php" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
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
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-visible">
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
                        <div class="p-6 overflow-visible">
    <div class="space-y-4 overflow-visible">
                                <?php foreach ($supplierFiles as $file): ?>
                                    <div class="file-card flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200 relative"
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
    <!-- P.O. Status Badges (keep these visible) -->
    <?php if ($file['all_items_received'] == 1): ?>
    <span class="inline-flex items-center px-3 py-1 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full border-2 border-emerald-300">
        <i class="fas fa-check-double mr-1"></i>
        All Items Received (<?php echo date('M j, Y', strtotime($file['all_items_received_at'])); ?>)
    </span>
<?php elseif ($file['marked_as_ordered'] == 1): ?>
    <?php if ($file['po_status'] == 'currently_receiving'): ?>
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

<!-- Approval Status Badges (Two-Step) -->
<?php if ($file['superadmin_approval_status'] == 'pending'): ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
        <i class="fas fa-hourglass-half mr-1"></i>
        Pending Superadmin
    </span>
<?php elseif ($file['superadmin_approval_status'] == 'rejected'): ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
        <i class="fas fa-ban mr-1"></i>
        Rejected by Superadmin
    </span>
<?php elseif ($file['superadmin_approval_status'] == 'approved' && $file['approval_status'] == 'pending'): ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
        <i class="fas fa-clock mr-1"></i>
        Pending Document Controller
    </span>
<?php elseif ($file['approval_status'] == 'approved'): ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
        <i class="fas fa-check-double mr-1"></i>
        Fully Approved
    </span>
<?php elseif ($file['approval_status'] == 'rejected'): ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
        <i class="fas fa-times-circle mr-1"></i>
        Rejected by Document Controller
    </span>
<?php endif; ?>

    <!-- 3-Dot Dropdown Menu -->
    <div class="relative inline-block text-left">
        <button type="button" 
                onclick="toggleDropdown(<?php echo $file['id']; ?>)"
                class="bg-gray-200 hover:bg-gray-300 text-gray-700 p-2 rounded-lg transition-colors duration-200">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        
        <!-- Dropdown Menu -->
        <div id="dropdown-<?php echo $file['id']; ?>" 
     class="hidden absolute right-0 bottom-full mb-2 w-56 rounded-lg shadow-xl bg-white ring-1 ring-black ring-opacity-5 z-[9999]">
            <div class="py-1">
                <!-- Download -->
                <a href="?order_id=<?php echo $order_id; ?>&download=1&file_id=<?php echo $file['id']; ?>" 
                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-download w-5 mr-2"></i>
                    Download
                </a>
                
                <?php if ($file['marked_as_ordered'] == 0): ?>
    <!-- Request Approval -->
    <?php if ($file['superadmin_approval_status'] != 'pending' && $file['superadmin_approval_status'] != 'approved' && $file['approval_requested_at'] == null): ?>
        <button onclick="submitAction('request_approval', <?php echo $file['id']; ?>)"
                class="w-full text-left flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-paper-plane w-5 mr-2"></i>
            Request Approval
        </button>
    <?php endif; ?>
                    
                    <!-- Mark as Ordered -->
                    <?php if ($file['approval_status'] == 'approved'): ?>
                        <button onclick="markSingleAsOrdered(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-purple-700 hover:bg-purple-50">
                            <i class="fas fa-paper-plane w-5 mr-2"></i>
                            Mark as Ordered
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- P.O. Status Updates -->
                    <?php if ($file['po_status'] == 'currently_receiving'): ?>
                        <div class="px-4 py-2 text-xs text-gray-500">
                            <i class="fas fa-check-double mr-1"></i>
                            Process Complete
                        </div>
                    <?php elseif ($file['po_status'] == 'out_for_delivery'): ?>
                        <button onclick="updatePoStatus(<?php echo $file['id']; ?>, 'currently_receiving', '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-purple-700 hover:bg-purple-50">
                            <i class="fas fa-inbox w-5 mr-2"></i>
                            Mark as Currently Receiving
                        </button>
                        <button onclick="updatePoStatus(<?php echo $file['id']; ?>, 'supplier_confirmed', '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>', true)"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50">
                            <i class="fas fa-undo w-5 mr-2"></i>
                            Back to Confirmed
                        </button>
                    <?php elseif ($file['po_status'] == 'supplier_confirmed'): ?>
                        <button onclick="updatePoStatus(<?php echo $file['id']; ?>, 'out_for_delivery', '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-orange-700 hover:bg-orange-50">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            Mark as Out for Delivery
                        </button>
                        <button onclick="updatePoStatus(<?php echo $file['id']; ?>, 'supplier_confirmed', '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>', true)"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                            <i class="fas fa-edit w-5 mr-2"></i>
                            Update Expected Date
                        </button>
                    <?php else: ?>
                        <button onclick="updatePoStatus(<?php echo $file['id']; ?>, 'supplier_confirmed', '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>', true)"
                                class="w-full text-left flex items-center px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                            <i class="fas fa-check-circle w-5 mr-2"></i>
                            Mark as Supplier Confirmed
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Delete (always available) -->
                <hr class="my-1">
                <button onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                        class="w-full text-left flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                    <i class="fas fa-trash w-5 mr-2"></i>
                    Delete
                </button>
            </div>
        </div>
    </div>
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
                    <a href="warehouse_staff_management_main.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
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

    <!-- P.O. Status Update Modal -->
<div id="updateStatusModal" class="fixed inset-0 z-50 hidden overflow-auto bg-black bg-opacity-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form method="POST">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-sync-alt text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Update P.O. Status</h3>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Update status for: <strong id="statusFileName"></strong>
                    </p>
                    
                    <input type="hidden" name="file_id" id="statusFileId">
                    <input type="hidden" name="new_status" id="newStatusValue">
                    
                    <!-- P.O. Number Input Field (shown only for currently_receiving) -->
                    <div id="poNumberField" class="mb-4 hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1"></i>
                            P.O. Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="po_number" 
                               id="poNumberInput"
                               placeholder="Enter P.O. Number (e.g., NH10202025922331)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                               readonly>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            This P.O. number will be assigned to the receiver
                        </p>
                    </div>
                    
                    <!-- Expected Delivery Date Field (shown only for supplier_confirmed) -->
                    <div id="expectedDateField" class="mb-4 hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1"></i>
                            Expected Delivery Date
                        </label>
                        <input type="date" 
                               name="expected_delivery_date" 
                               id="expectedDeliveryDate"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Receiver Selection Field (shown only for currently_receiving) -->
                    <div id="receiverSelectField" class="mb-4 hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-check mr-1"></i>
                            Assign to Receiver <span class="text-red-500">*</span>
                        </label>
                        <select name="receiver_id" 
                                id="receiverSelect"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Select Receiver --</option>
                            <?php
                            // Get available receivers with their current workload
                            $receiversSql = "SELECT 
                                                na.id, 
                                                na.fullname,
                                                COUNT(pra.id) as active_workload
                                            FROM nobleaccount na
                                            LEFT JOIN po_receiver_assignments pra ON na.id = pra.receiver_id AND pra.status = 'active'
                                            WHERE na.subrole = 'warehouse_receiver' AND na.status = 'active'
                                            GROUP BY na.id, na.fullname
                                            ORDER BY active_workload ASC, na.fullname ASC";
                            $receiversStmt = $conn->query($receiversSql);
                            
                            if ($receiversStmt) {
                                while ($receiver = $receiversStmt->fetch_assoc()) {
                                    $workloadBadge = $receiver['active_workload'] > 0 ? " ({$receiver['active_workload']} active)" : " (Available)";
                                    echo '<option value="' . $receiver['id'] . '">' . htmlspecialchars($receiver['fullname']) . $workloadBadge . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Receivers with fewer active P.O.s are shown first
                        </p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-800" id="statusUpdateMessage">
                            <!-- Dynamic message will appear here -->
                        </p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeUpdateStatusModal()" 
                                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" name="update_po_status" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-check mr-2"></i>
                            <span id="updateStatusButtonText">Update Status</span>
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

        // Update P.O. Status
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
    const updateMessage = document.getElementById('statusUpdateMessage');
    const buttonText = document.getElementById('updateStatusButtonText');
    
    // Reset all fields
    expectedDateField.classList.add('hidden');
    expectedDateInput.removeAttribute('required');
    expectedDateInput.value = '';
    poNumberField.classList.add('hidden');
    poNumberInput.removeAttribute('required');
    poNumberInput.value = '';
    receiverField.classList.add('hidden');
    receiverSelect.removeAttribute('required');
    receiverSelect.value = '';
    
    // Show/hide fields and update message based on status
    if (newStatus === 'supplier_confirmed') {
        expectedDateField.classList.remove('hidden');
        expectedDateInput.setAttribute('required', 'required');
        updateMessage.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Supplier has confirmed the order. Please provide the expected delivery date.';
        buttonText.textContent = needsDate ? 'Update Expected Date' : 'Confirm with Date';
        
    } else if (newStatus === 'out_for_delivery') {
        updateMessage.innerHTML = '<i class="fas fa-truck mr-1"></i>Mark this P.O. as out for delivery.';
        buttonText.textContent = 'Mark Out for Delivery';
        
    } else if (newStatus === 'currently_receiving') {
        // Show P.O. number field (read-only, extracted from file name)
        poNumberField.classList.remove('hidden');
        poNumberInput.setAttribute('required', 'required');
        
        // Extract P.O. number from the file name
        // Assuming format: PO_NH11242025152028_Wendhil_business_warehouse_staff_(1).xlsx
        const poMatch = fileName.match(/PO_([A-Z0-9]+)_/i);
        if (poMatch && poMatch[1]) {
            poNumberInput.value = poMatch[1];
        } else {
            // If can't extract, allow manual input
            poNumberInput.readOnly = false;
            poNumberInput.placeholder = 'Enter P.O. Number';
        }
        
        // Show receiver selection
        receiverField.classList.remove('hidden');
        receiverSelect.setAttribute('required', 'required');
        
        updateMessage.innerHTML = '<i class="fas fa-inbox mr-1"></i>Assign this P.O. to a warehouse receiver for processing. The P.O. number will be used to track items.';
        buttonText.textContent = 'Assign to Receiver';
    }
    
    document.getElementById('updateStatusModal').classList.remove('hidden');
}

function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').classList.add('hidden');
    document.getElementById('expectedDeliveryDate').value = '';
}

// Close modal when clicking outside
document.getElementById('updateStatusModal').addEventListener('click', function(e) {
    if (e.target === this) closeUpdateStatusModal();
});

// Toggle dropdown menu
function toggleDropdown(fileId) {
    const dropdown = document.getElementById('dropdown-' + fileId);
    const allDropdowns = document.querySelectorAll('[id^="dropdown-"]');
    
    // Close all other dropdowns
    allDropdowns.forEach(d => {
        if (d.id !== 'dropdown-' + fileId) {
            d.classList.add('hidden');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('button') || !event.target.closest('[onclick^="toggleDropdown"]')) {
        const allDropdowns = document.querySelectorAll('[id^="dropdown-"]');
        allDropdowns.forEach(d => d.classList.add('hidden'));
    }
});

// Submit form actions (for request approval)
function submitAction(action, fileId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="${action}" value="1">
        <input type="hidden" name="file_id" value="${fileId}">
    `;
    document.body.appendChild(form);
    form.submit();
}
    </script>
</body>
</html>