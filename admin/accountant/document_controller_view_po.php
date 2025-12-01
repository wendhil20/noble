<?php
// document_controller_view_po.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
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

// Get PO ID
$po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : 0;

if ($po_id <= 0) {
    $_SESSION['po_error'] = "Invalid Purchase Order ID.";
    header("Location: document_controller_view_orders.php");
    exit();
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $conn->prepare("SELECT document_controller_status FROM purchase_orders WHERE id = ?");
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $stmt->bind_result($old_status);
    $stmt->fetch();
    $stmt->close();
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE purchase_orders SET document_controller_status = ?, document_controller_approved_by = ?, document_controller_approved_at = NOW(), updated_at = NOW() WHERE id = ? AND accounting_status = 'approved'");
    $stmt->bind_param("sii", $new_status, $user_id, $po_id);
    
    if ($stmt->execute()) {
        $log_stmt = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $log_stmt->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
        $log_stmt->execute();
        $log_stmt->close();
        
        $_SESSION['po_success'] = "Purchase Order " . ucfirst($new_status) . " successfully!";
        header("Location: document_controller_view_orders.php");
        exit();
    } else {
        $_SESSION['po_error'] = "Failed to update PO. Please try again.";
    }
    $stmt->close();
}

// Get PO details
$stmt = $conn->prepare("
    SELECT 
        po.*,
        c.company_name,
        c.company_address,
        n.fullname as created_by,
        n2.fullname as approved_by_name
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.document_controller_approved_by = n2.id
    WHERE po.id = ? AND po.accounting_status = 'approved'
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();
$po_data = $result->fetch_assoc();
$stmt->close();

if (!$po_data) {
    $_SESSION['po_error'] = "Purchase Order not found or not available for review.";
    header("Location: document_controller_view_orders.php");
    exit();
}

// Get PO items
$items_stmt = $conn->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id ASC");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$po_items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Calculate totals
$subtotal = 0;
foreach ($po_items as $item) {
    $subtotal += $item['subtotal'];
}
$vat = $subtotal * 0.12;
$general_req = $subtotal * 0.10;
$total = $subtotal + $vat + $general_req;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review PO #<?php echo htmlspecialchars($po_data['po_number']); ?> - Document Controller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="document_controller_view_orders.php" class="bg-gray-200 hover:bg-gray-300 p-2 rounded-lg transition">
                        <i class="fas fa-arrow-left text-gray-700"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                            Review Purchase Order
                        </h1>
                        <p class="text-sm text-gray-600">
                            PO #<?php echo htmlspecialchars($po_data['po_number']); ?> - <?php echo htmlspecialchars($po_data['company_name']); ?>
                        </p>
                    </div>
                </div>
                <?php
                $status_colors = [
                    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                    'approved' => 'bg-green-100 text-green-700 border-green-300',
                    'rejected' => 'bg-red-100 text-red-700 border-red-300'
                ];
                $status_class = $status_colors[$po_data['document_controller_status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                ?>
                <div class="px-4 py-2 <?php echo $status_class; ?> border-2 rounded-lg font-bold text-sm">
                    <?php echo ucfirst($po_data['document_controller_status']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
        
        <!-- PO Details Card -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-2"></i>
                    Purchase Order Details
                </h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- PO Number -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">PO Number</label>
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($po_data['po_number']); ?></p>
                    </div>
                    
                    <!-- Company -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Company</label>
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($po_data['company_name']); ?></p>
                    </div>
                    
                    <!-- PO Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">PO Date</label>
                        <p class="text-sm text-gray-900"><?php echo date('F d, Y', strtotime($po_data['po_date'])); ?></p>
                    </div>
                    
                    <!-- Ship To -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Ship To</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars(substr($po_data['ship_to'], 0, 50)); ?></p>
                    </div>
                    
                    <!-- Target Delivery -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Target Delivery</label>
                        <p class="text-sm text-gray-900"><?php echo date('F d, Y', strtotime($po_data['target_delivery_date'])); ?></p>
                    </div>
                    
                    <!-- Payment Terms -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Payment Terms</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($po_data['payment_terms']); ?></p>
                    </div>
                    
                    <!-- Created By -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Created By</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($po_data['created_by'] ?? 'Unknown'); ?></p>
                    </div>
                    
                    <!-- Created Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Created Date</label>
                        <p class="text-sm text-gray-900"><?php echo date('F d, Y H:i', strtotime($po_data['created_at'])); ?></p>
                    </div>
                </div>
                
                <!-- Project Scope -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-2">Project Scope</label>
                    <p class="text-sm text-gray-900 leading-relaxed"><?php echo nl2br(htmlspecialchars($po_data['project_scope'])); ?></p>
                </div>
                
                <!-- Documents -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-3">Documents</label>
                    <div class="flex gap-3">
                        <?php if (!empty($po_data['attachment_path']) && file_exists($po_data['attachment_path'])): ?>
                            <a href="<?php echo htmlspecialchars($po_data['attachment_path']); ?>" target="_blank" 
                               class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm font-medium transition-colors">
                                <i class="fas fa-file-pdf mr-2"></i>View Quotation PDF
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($po_data['client_po_path']) && file_exists($po_data['client_po_path'])): ?>
                            <a href="<?php echo htmlspecialchars($po_data['client_po_path']); ?>" target="_blank" 
                               class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm font-medium transition-colors">
                                <i class="fas fa-file-alt mr-2"></i>View Client PO
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PO Items Card -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-boxes mr-2"></i>
                    Order Items (<?php echo count($po_items); ?>)
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (!empty($po_items)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Product Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Color</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Size</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Quantity</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700">Unit Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700">Subtotal</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php $item_num = 1; foreach ($po_items as $item): ?>
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-4 py-3 text-gray-600 font-medium"><?php echo $item_num++; ?></td>
                                        <td class="px-4 py-3 text-gray-900 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($item['color_name']); ?></td>
                                        <td class="px-4 py-3 text-gray-700 font-semibold"><?php echo htmlspecialchars($item['size']); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-bold">
                                                <?php echo $item['quantity']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-blue-600">
                                            ₱<?php echo number_format($item['unit_price'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-blue-700">
                                            ₱<?php echo number_format($item['subtotal'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($item['is_custom_size']): ?>
                                                <span class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-wrench mr-1"></i>Custom
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-check mr-1"></i>Standard
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Totals Section -->
                    <div class="mt-6 flex justify-end">
                        <div class="w-full md:w-96 space-y-2">
                            <div class="flex justify-between py-2 px-4 bg-gray-50 rounded">
                                <span class="text-sm font-medium text-gray-700">Subtotal:</span>
                                <span class="text-sm font-bold text-gray-900">₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between py-2 px-4 bg-gray-50 rounded">
                                <span class="text-sm font-medium text-gray-700">VAT (12%):</span>
                                <span class="text-sm font-bold text-gray-900">₱<?php echo number_format($vat, 2); ?></span>
                            </div>
                            <div class="flex justify-between py-2 px-4 bg-gray-50 rounded">
                                <span class="text-sm font-medium text-gray-700">General Requirements (10%):</span>
                                <span class="text-sm font-bold text-gray-900">₱<?php echo number_format($general_req, 2); ?></span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg">
                                <span class="text-base font-bold text-white">Total Amount:</span>
                                <span class="text-base font-bold text-white">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                        <p class="font-medium">No items found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <?php if ($po_data['document_controller_status'] === 'pending'): ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        Review Actions
                    </h2>
                </div>
                
                <div class="p-6">
                    <div class="flex gap-4">
                        <button onclick="openModal('approve')" 
                                class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center justify-center space-x-2 font-bold">
                            <i class="fas fa-check-circle text-lg"></i>
                            <span>Approve Purchase Order</span>
                        </button>
                        
                        <button onclick="openModal('reject')" 
                                class="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center justify-center space-x-2 font-bold">
                            <i class="fas fa-times-circle text-lg"></i>
                            <span>Reject Purchase Order</span>
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 text-center">
                <i class="fas fa-info-circle text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-600 font-medium">This purchase order has already been reviewed.</p>
                <p class="text-sm text-gray-500 mt-1">Status: <span class="font-bold"><?php echo ucfirst($po_data['document_controller_status']); ?></span></p>
                <?php if ($po_data['document_controller_approved_by']): ?>
                    <p class="text-sm text-gray-500 mt-1">
                        Reviewed by: <span class="font-bold"><?php echo htmlspecialchars($po_data['approved_by_name'] ?? 'Unknown'); ?></span>
                        on <?php echo date('F d, Y H:i', strtotime($po_data['document_controller_approved_at'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approval/Rejection Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-lg shadow-2xl transform transition-all">
            <div id="modalHeader" class="px-6 py-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 id="modalTitle" class="text-xl font-bold text-white"></h3>
                    <button onclick="closeModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full w-8 h-8 flex items-center justify-center transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-white text-opacity-90 text-sm mt-1">PO #: <span class="font-bold"><?php echo htmlspecialchars($po_data['po_number']); ?></span></p>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment mr-1"></i>Notes <span id="notesRequired" class="text-red-500"></span>
                    </label>
                    <textarea name="notes" id="notesTextarea" rows="4" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" 
                              placeholder="Add your notes here..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Provide reasons or comments for this action</p>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>Cancel
                    </button>
                    <button type="submit" id="submitBtn" 
                            class="flex-1 text-white font-bold py-3 px-4 rounded-lg transition-all transform hover:scale-105">
                        <i class="fas fa-check mr-1"></i>Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action) {
            const modal = document.getElementById('modal');
            const modalHeader = document.getElementById('modalHeader');
            const modalTitle = document.getElementById('modalTitle');
            const modalAction = document.getElementById('modalAction');
            const submitBtn = document.getElementById('submitBtn');
            const notesTextarea = document.getElementById('notesTextarea');
            const notesRequired = document.getElementById('notesRequired');
            
            modalAction.value = action;
            
            if (action === 'approve') {
                modalHeader.className = 'px-6 py-4 rounded-t-lg bg-gradient-to-r from-green-500 to-green-600';
                modalTitle.textContent = 'Approve Purchase Order';
                submitBtn.className = 'flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-4 rounded-lg transition-all transform hover:scale-105 shadow-md';
                submitBtn.innerHTML = '<i class="fas fa-check mr-1"></i>Approve';
                notesTextarea.required = false;
                notesRequired.textContent = '';
            } else {
                modalHeader.className = 'px-6 py-4 rounded-t-lg bg-gradient-to-r from-red-500 to-red-600';
                modalTitle.textContent = 'Reject Purchase Order';
                submitBtn.className = 'flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold py-3 px-4 rounded-lg transition-all transform hover:scale-105 shadow-md';
                submitBtn.innerHTML = '<i class="fas fa-times mr-1"></i>Reject';
                notesTextarea.required = true;
                notesRequired.textContent = '*';
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.getElementById('notesTextarea').value = '';
        }

        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>