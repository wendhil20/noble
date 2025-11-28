<?php
// approve_purchase_orders_accountant.php - Accountant PO Approval
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'accountant']);

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
$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $po_id = intval($_POST['po_id']);
    $action = trim($_POST['action']);
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $conn->prepare("SELECT accounting_status FROM purchase_orders WHERE id = ?");
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $stmt->bind_result($old_status);
    $stmt->fetch();
    $stmt->close();
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE purchase_orders SET accounting_status = ?, accounting_approved_by = ?, accounting_approved_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("sii", $new_status, $user_id, $po_id);
    
    if ($stmt->execute()) {
        $log_stmt = $conn->prepare("INSERT INTO po_status_logs (po_id, admin_id, old_status, new_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $log_stmt->bind_param("iisss", $po_id, $user_id, $old_status, $new_status, $notes);
        $log_stmt->execute();
        $log_stmt->close();
        
        $message = "Purchase Order accounting status updated to " . ucfirst($new_status) . "!";
    } else {
        $error = "Failed to update PO. Please try again.";
    }
    $stmt->close();
}

$purchase_orders = [];
$stmt = $conn->prepare("
    SELECT 
        po.id, 
        po.po_number, 
        po.po_date, 
        po.payment_terms, 
        po.attachment_path,
        po.client_po_path,
        po.accounting_status,
        po.accounting_approved_by,
        po.accounting_approved_at,
        c.company_name,
        n.fullname as created_by,
        n2.fullname as approved_by_name
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.accounting_approved_by = n2.id
    WHERE po.status = 'approved'
    ORDER BY 
        CASE po.accounting_status
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

$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($purchase_orders as $po) {
    if (isset($status_counts[$po['accounting_status']])) {
        $status_counts[$po['accounting_status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Approval - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-lg">
                        <i class="fas fa-calculator text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Accounting Approval</h1>
                        <p class="text-gray-600 text-sm">Review operational approved POs</p>
                    </div>
                </div>
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($fullname); ?></div>
                    <div class="text-xs text-gray-500">Accountant</div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-yellow-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600 mb-1 font-medium">Pending Review</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $status_counts['pending']; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-600 text-3xl opacity-50"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600 mb-1 font-medium">Approved</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-600 text-3xl opacity-50"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600 mb-1 font-medium">Rejected</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $status_counts['rejected']; ?></p>
                    </div>
                    <i class="fas fa-times-circle text-red-600 text-3xl opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-3">
                <h2 class="text-xl font-bold text-white">Approved Purchase Orders (<?php echo count($purchase_orders); ?>)</h2>
            </div>
            
            <div class="p-6">
                <?php if (!empty($purchase_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">PO #</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">Company</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">Payment Terms</th>
                                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Quotation</th>
                                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Client PO</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">Created By</th>
                                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Status</th>
                                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($purchase_orders as $po): ?>
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-4 py-2 font-mono font-bold text-blue-700"><?php echo htmlspecialchars($po['po_number']); ?></td>
                                        <td class="px-4 py-2 text-gray-900"><?php echo htmlspecialchars(substr($po['company_name'], 0, 20)); ?></td>
                                        <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($po['payment_terms']); ?></td>
                                        <td class="px-4 py-2 text-center">
                                            <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank" class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">
                                                    <i class="fas fa-file-pdf mr-1"></i>View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs font-medium">
                                                    <i class="fas fa-file-alt mr-1"></i>View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-600"><?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?></td>
                                        <td class="px-4 py-2 text-center">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'approved' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700'
                                            ];
                                            $status_class = $status_colors[$po['accounting_status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="inline-block px-3 py-1 <?php echo $status_class; ?> rounded-full text-xs font-bold">
                                                <?php echo ucfirst($po['accounting_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <?php if ($po['accounting_status'] === 'pending'): ?>
                                                <div class="flex gap-2 justify-center">
                                                    <button onclick="openModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number']); ?>', 'approve')"
                                                        class="px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs font-bold">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                    <button onclick="openModal(<?php echo $po['id']; ?>, '<?php echo htmlspecialchars($po['po_number']); ?>', 'reject')"
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs font-bold">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic">Done</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                        <p class="font-medium">No purchase orders to review</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-lg shadow-2xl">
            <div id="modalHeader" class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-3 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 id="modalTitle" class="text-lg font-bold text-white"></h3>
                    <button onclick="closeModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded w-6 h-6 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-blue-100 text-xs mt-1">PO #: <span id="modalPoNumber" class="font-bold"></span></p>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="po_id" id="modalPoId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Add notes..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" id="submitBtn" class="flex-1 text-white font-bold py-2 px-4 rounded">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(poId, poNumber, action) {
            document.getElementById('modalPoId').value = poId;
            document.getElementById('modalPoNumber').textContent = poNumber;
            document.getElementById('modalAction').value = action;
            
            if (action === 'approve') {
                document.getElementById('modalTitle').textContent = 'Approve for Accounting';
                document.getElementById('submitBtn').className = 'flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check mr-1"></i>Approve';
            } else {
                document.getElementById('modalTitle').textContent = 'Reject for Accounting';
                document.getElementById('submitBtn').className = 'flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-times mr-1"></i>Reject';
                document.querySelector('textarea').required = true;
            }
            
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.querySelector('textarea').value = '';
            document.querySelector('textarea').required = false;
        }

        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>