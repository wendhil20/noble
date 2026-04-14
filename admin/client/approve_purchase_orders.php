<?php
// approve_purchase_orders.php - Operational Manager Purchase Order Approval
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
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

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$message = "";
$error = "";

// Handle approve/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $po_id = intval($_POST['po_id']);
    $action = trim($_POST['action']);
    $notes = trim($_POST['notes'] ?? '');
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM purchase_orders WHERE id = ?");
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $stmt->bind_result($old_status);
    $stmt->fetch();
    $stmt->close();
    
    if ($action === 'approve') {
        $new_status = 'approved';
        // Update PO status to approved
        $stmt = $conn->prepare("UPDATE purchase_orders SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $user_id, $po_id);
        
        if ($stmt->execute()) {
            // Log the approval
            $log_stmt = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Purchase Order approved successfully!";
        } else {
            $error = "Failed to approve purchase order. Please try again.";
        }
        $stmt->close();
        
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
        // Update PO status to rejected
        $stmt = $conn->prepare("UPDATE purchase_orders SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $user_id, $po_id);
        
        if ($stmt->execute()) {
            // Log the rejection
            $log_stmt = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Purchase Order rejected.";
        } else {
            $error = "Failed to reject purchase order. Please try again.";
        }
        $stmt->close();
    }
}

// Fetch all purchase orders with company and sales info
$purchase_orders = [];
$stmt = $conn->prepare("
    SELECT 
        po.id, 
        po.po_number, 
        po.po_date, 
        po.ship_to, 
        po.target_delivery_date, 
        po.payment_terms, 
        po.attachment_path, 
        po.client_po_path, 
        po.status, 
        po.created_at,
        po.approved_by,
        po.approved_at,
        c.company_name,
        c.company_address,
        c.logo_path,
        n.fullname as created_by,
        n2.fullname as approved_by_name
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.approved_by = n2.id
    ORDER BY 
        CASE po.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        po.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchase_orders[] = $row;
}
$stmt->close();

// Count by status
$status_counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];
foreach ($purchase_orders as $po) {
    if (isset($status_counts[$po['status']])) {
        $status_counts[$po['status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Purchase Orders - Noble Admin</title>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="bg-purple-500 p-3 rounded-lg">
                            <i class="fas fa-clipboard-check text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Purchase Order Approvals</h1>
                            <p class="text-gray-600 mt-1">Review and approve/reject purchase orders</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-medium text-gray-900">
                                <i class="fas fa-user text-primary-600 mr-1"></i>
                                <?php echo htmlspecialchars($fullname); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-shield-alt mr-1"></i>
                                Operational Manager
                            </div>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm sm:text-lg">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 mb-6 flex items-center animate-pulse">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium mb-1">Pending Approval</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $status_counts['pending']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Awaiting your review</p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-4">
                        <i class="fas fa-clock text-yellow-600 text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium mb-1">Approved</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Ready for processing</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-4">
                        <i class="fas fa-check-circle text-green-600 text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium mb-1">Rejected</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $status_counts['rejected']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Not approved</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-4">
                        <i class="fas fa-times-circle text-red-600 text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-purple-600 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-list-alt mr-2 sm:mr-3"></i>
                    All Purchase Orders (<?php echo count($purchase_orders); ?>)
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <?php if (!empty($purchase_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">PO #</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Company</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">PO Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Delivery</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Payment Terms</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Quotation</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Client PO</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Created By</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($purchase_orders as $po): ?>
                                    <tr class="hover:bg-purple-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-mono font-bold text-purple-700">
                                                <?php echo htmlspecialchars($po['po_number']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center space-x-2">
                                                <?php if (!empty($po['logo_path']) && file_exists($po['logo_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($po['logo_path']); ?>" 
                                                        alt="<?php echo htmlspecialchars($po['company_name']); ?>"
                                                        class="h-8 w-8 object-contain rounded border">
                                                <?php else: ?>
                                                    <div class="h-8 w-8 bg-blue-100 rounded flex items-center justify-center">
                                                        <i class="fas fa-building text-blue-600 text-xs"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars(substr($po['company_name'], 0, 20)); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo date('M j, Y', strtotime($po['po_date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo date('M j, Y', strtotime($po['target_delivery_date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo htmlspecialchars($po['payment_terms']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                                                'approved' => 'bg-green-100 text-green-700 border-green-300',
                                                'rejected' => 'bg-red-100 text-red-700 border-red-300'
                                            ];
                                            $status_class = $status_colors[$po['status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 <?php echo $status_class; ?> border rounded-full text-xs font-bold uppercase">
                                                <?php echo ucfirst($po['status']); ?>
                                            </span>
                                            <?php if ($po['status'] === 'approved' || $po['status'] === 'rejected'): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    by <?php echo htmlspecialchars($po['approved_by_name'] ?? 'Unknown'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank"
                                                    class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-xs font-medium">
                                                    <i class="fas fa-file-pdf mr-1"></i>
                                                    View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank"
                                                    class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors text-xs font-medium">
                                                    <i class="fas fa-file-alt mr-1"></i>
                                                    View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">Not uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 text-xs">
                                            <?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($po['status'] === 'pending'): ?>
                                                <div class="flex gap-2 justify-center">
                                                    <button onclick="openApproveModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number']); ?>')"
                                                        class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors text-xs font-bold">
                                                        <i class="fas fa-check mr-1"></i>
                                                        Approve
                                                    </button>
                                                    <button onclick="openRejectModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number']); ?>')"
                                                        class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-xs font-bold">
                                                        <i class="fas fa-times mr-1"></i>
                                                        Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic">
                                                    <?php echo ucfirst($po['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-clipboard-list text-5xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium">No purchase orders yet</p>
                        <p class="text-sm">Purchase orders will appear here for approval</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-xl shadow-2xl">
            <div class="bg-green-600 px-6 py-4 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Approve Purchase Order
                    </h3>
                    <button type="button" onclick="closeApproveModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full w-8 h-8 flex items-center justify-center transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-green-100 text-sm mt-1">PO #: <span id="approvePoNumber" class="font-bold"></span></p>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="po_id" id="approvePoId">
                <input type="hidden" name="action" value="approve">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment mr-1 text-green-600"></i>Approval Notes (Optional)
                    </label>
                    <textarea name="notes" rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        placeholder="Add any notes about this approval..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeApproveModal()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-lg">
                        <i class="fas fa-check mr-2"></i>Approve PO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-xl shadow-2xl">
            <div class="bg-red-600 px-6 py-4 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-times-circle mr-2"></i>
                        Reject Purchase Order
                    </h3>
                    <button type="button" onclick="closeRejectModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full w-8 h-8 flex items-center justify-center transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-red-100 text-sm mt-1">PO #: <span id="rejectPoNumber" class="font-bold"></span></p>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="po_id" id="rejectPoId">
                <input type="hidden" name="action" value="reject">
                
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <p class="text-sm text-red-800 font-medium">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Are you sure you want to reject this purchase order?
                    </p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment mr-1 text-red-600"></i>Reason for Rejection (Required)
                    </label>
                    <textarea name="notes" rows="3" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        placeholder="Please explain why this PO is being rejected..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeRejectModal()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-lg">
                        <i class="fas fa-times mr-2"></i>Reject PO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Approve Modal Functions
        function openApproveModal(poId, poNumber) {
            document.getElementById('approvePoId').value = poId;
            document.getElementById('approvePoNumber').textContent = poNumber;
            document.getElementById('approveModal').classList.remove('hidden');
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.add('hidden');
        }

        // Reject Modal Functions
        function openRejectModal(poId, poNumber) {
            document.getElementById('rejectPoId').value = poId;
            document.getElementById('rejectPoNumber').textContent = poNumber;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApproveModal();
            }
        });

        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>