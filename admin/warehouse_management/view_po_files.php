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
$attachmentsSql = "SELECT * FROM po_attachments WHERE order_id = ? ORDER BY supplier_name, uploaded_at DESC";
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
                                            <a href="?order_id=<?php echo $order_id; ?>&download=1&file_id=<?php echo $file['id']; ?>" 
                                               class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-1 text-sm">
                                                <i class="fas fa-download"></i>
                                                <span>Download</span>
                                            </a>
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

    <script>
        function confirmDelete(fileId, fileName) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('fileName').textContent = fileName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>