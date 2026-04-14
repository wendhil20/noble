<?php
// view_purchase_order.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

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
$po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : 0;

if ($po_id <= 0) {
    header("Location: view_companies.php");
    exit();
}

// Get PO details with e-signatures
$stmt = $conn->prepare("
    SELECT po.*, c.company_name, c.company_address, c.logo_path,
           n.fullname as created_by,
           n2.fullname as ops_approved_by, n2.e_signature as ops_signature,
           n3.fullname as acc_approved_by, n3.e_signature as acc_signature,
           n4.fullname as doc_approved_by, n4.e_signature as doc_signature
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.approved_by = n2.id
    LEFT JOIN nobleaccount n3 ON po.accounting_approved_by = n3.id
    LEFT JOIN nobleaccount n4 ON po.document_controller_approved_by = n4.id
    WHERE po.id = ? AND po.sales_user_id = ?
");
$stmt->bind_param("ii", $po_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$po = $result->fetch_assoc();
$stmt->close();

if (!$po) {
    header("Location: view_companies.php");
    exit();
}

// Get PO items
$items_stmt = $conn->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id ASC");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Calculate totals
$subtotal = array_sum(array_column($items, 'subtotal'));
$vat = $subtotal * 0.12;
$general_req = $subtotal * 0.10;
$total = $subtotal + $vat + $general_req;

// Get rejection notes
function getRejectionNotes($conn, $po_id, $status_type) {
    $query = "SELECT notes FROM po_status_logs WHERE po_id = ? AND new_status = 'rejected' ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['notes'] ?? '';
}

$ops_rejection = ($po['status'] === 'rejected') ? getRejectionNotes($conn, $po_id, 'ops') : '';
$acc_rejection = ($po['accounting_status'] === 'rejected') ? getRejectionNotes($conn, $po_id, 'acc') : '';
$doc_rejection = ($po['document_controller_status'] === 'rejected') ? getRejectionNotes($conn, $po_id, 'doc') : '';

// Check if all three departments have approved
$all_approved = ($po['status'] === 'approved' && 
                 $po['accounting_status'] === 'approved' && 
                 ($po['document_controller_status'] ?? 'pending') === 'approved');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO #<?php echo htmlspecialchars($po['po_number']); ?></title>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="purchase_orders.php?company_id=<?php echo $po['company_id']; ?>" 
                       class="bg-gray-200 hover:bg-gray-300 p-2 rounded-lg">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            PO #<?php echo htmlspecialchars($po['po_number']); ?>
                        </h1>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($po['company_name']); ?></p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <?php if ($po['status'] === 'rejected' || $po['accounting_status'] === 'rejected' || $po['document_controller_status'] === 'rejected'): ?>
                        <a href="edit_purchase_order.php?po_id=<?php echo $po_id; ?>" 
                           class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-edit mr-2"></i>Edit & Resubmit
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($all_approved): ?>
                        <button onclick="updatePDFWithSignatures()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-file-pdf mr-2"></i>Update PDF with Signatures
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <?php
            $statuses = [
                ['label' => 'Operations', 'status' => $po['status'], 'approved_by' => $po['ops_approved_by'], 'approved_at' => $po['approved_at'], 'rejection' => $ops_rejection],
                ['label' => 'Accounting', 'status' => $po['accounting_status'], 'approved_by' => $po['acc_approved_by'], 'approved_at' => $po['accounting_approved_at'], 'rejection' => $acc_rejection],
                ['label' => 'Document Controller', 'status' => $po['document_controller_status'] ?? 'pending', 'approved_by' => $po['doc_approved_by'], 'approved_at' => $po['document_controller_approved_at'], 'rejection' => $doc_rejection]
            ];

            $colors = [
                'pending' => 'border-yellow-500 bg-yellow-50',
                'approved' => 'border-green-500 bg-green-50',
                'rejected' => 'border-red-500 bg-red-50'
            ];

            foreach ($statuses as $s):
                $color = $colors[$s['status']] ?? 'border-gray-500 bg-gray-50';
            ?>
                <div class="bg-white rounded-lg shadow-md border-l-4 <?php echo $color; ?> p-4">
                    <h3 class="font-bold text-gray-700 mb-2"><?php echo $s['label']; ?></h3>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold
                        <?php echo $s['status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                                  ($s['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                        <?php echo ucfirst($s['status']); ?>
                    </span>
                    
                    <?php if ($s['status'] !== 'pending'): ?>
                        <div class="mt-2 text-xs text-gray-600">
                            <div><strong>By:</strong> <?php echo htmlspecialchars($s['approved_by'] ?? 'Unknown'); ?></div>
                            <div><strong>Date:</strong> <?php echo $s['approved_at'] ? date('M j, Y g:i A', strtotime($s['approved_at'])) : 'N/A'; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($s['rejection'])): ?>
                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($s['rejection']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PO Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-file-invoice mr-2"></i>Purchase Order Details
                </h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">PO Date</label>
                    <p class="text-sm text-gray-900"><?php echo date('F d, Y', strtotime($po['po_date'])); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">Ship To</label>
                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($po['ship_to']); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">Target Delivery</label>
                    <p class="text-sm text-gray-900"><?php echo date('F d, Y', strtotime($po['target_delivery_date'])); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">Payment Terms</label>
                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($po['payment_terms']); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">Created By</label>
                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase">Created Date</label>
                    <p class="text-sm text-gray-900"><?php echo date('F d, Y H:i', strtotime($po['created_at'])); ?></p>
                </div>
                <div class="md:col-span-3">
                    <label class="text-xs font-bold text-gray-600 uppercase">Project Scope</label>
                    <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($po['project_scope'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <?php if (!empty($po['attachment_path']) || !empty($po['client_po_path'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="font-bold text-gray-700 mb-3">
                <i class="fas fa-paperclip mr-2"></i>Documents
            </h3>
            <div class="flex gap-3">
                <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                    <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank" 
                       class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                        <i class="fas fa-file-pdf mr-2"></i>Quotation PDF
                    </a>
                <?php endif; ?>
                <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                    <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank" 
                       class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                        <i class="fas fa-file-alt mr-2"></i>Client PO
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Items Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-boxes mr-2"></i>Order Items (<?php echo count($items); ?>)
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold">#</th>
                            <th class="px-4 py-3 text-left text-xs font-bold">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-bold">Color</th>
                            <th class="px-4 py-3 text-left text-xs font-bold">Size</th>
                            <th class="px-4 py-3 text-center text-xs font-bold">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-bold">Unit Price</th>
                            <th class="px-4 py-3 text-right text-xs font-bold">Subtotal</th>
                            <th class="px-4 py-3 text-center text-xs font-bold">Type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php $num = 1; foreach ($items as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3"><?php echo $num++; ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($item['color_name']); ?></td>
                                <td class="px-4 py-3 font-bold"><?php echo htmlspecialchars($item['size']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 bg-gray-100 rounded-full font-bold"><?php echo $item['quantity']; ?></span>
                                </td>
                                <td class="px-4 py-3 text-right text-blue-600 font-bold">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="px-4 py-3 text-right text-blue-700 font-bold">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold
                                        <?php echo $item['is_custom_size'] ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'; ?>">
                                        <?php echo $item['is_custom_size'] ? 'Custom' : 'Standard'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Totals -->
            <div class="p-6 bg-gray-50">
                <div class="max-w-md ml-auto space-y-2">
                    <div class="flex justify-between py-2 px-4 bg-white rounded">
                        <span class="font-medium">Subtotal:</span>
                        <span class="font-bold">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2 px-4 bg-white rounded">
                        <span class="font-medium">VAT (12%):</span>
                        <span class="font-bold">₱<?php echo number_format($vat, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2 px-4 bg-white rounded">
                        <span class="font-medium">General Req (10%):</span>
                        <span class="font-bold">₱<?php echo number_format($general_req, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-3 px-4 bg-blue-600 text-white rounded-lg">
                        <span class="font-bold text-lg">Total:</span>
                        <span class="font-bold text-lg">₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Signatures Preview Section - Only show when all approved -->
        <?php if ($all_approved): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-check-circle mr-2"></i>Approved By
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Operations Approval -->
                    <div class="text-center">
                        <div class="mb-3">
                            <?php if (!empty($po['ops_signature'])): ?>
                                <img src="data:image/png;base64,<?php echo base64_encode($po['ops_signature']); ?>" 
                                     alt="Operations Signature" 
                                     class="h-20 mx-auto object-contain border-b-2 border-gray-300">
                            <?php else: ?>
                                <div class="h-20 flex items-center justify-center border-b-2 border-gray-300">
                                    <span class="text-gray-400 text-sm italic">No signature</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($po['ops_approved_by']); ?></p>
                        <p class="text-xs text-gray-600 uppercase font-semibold">Operations</p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $po['approved_at'] ? date('M j, Y g:i A', strtotime($po['approved_at'])) : 'N/A'; ?>
                        </p>
                    </div>

                    <!-- Accounting Approval -->
                    <div class="text-center">
                        <div class="mb-3">
                            <?php if (!empty($po['acc_signature'])): ?>
                                <img src="data:image/png;base64,<?php echo base64_encode($po['acc_signature']); ?>" 
                                     alt="Accounting Signature" 
                                     class="h-20 mx-auto object-contain border-b-2 border-gray-300">
                            <?php else: ?>
                                <div class="h-20 flex items-center justify-center border-b-2 border-gray-300">
                                    <span class="text-gray-400 text-sm italic">No signature</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($po['acc_approved_by']); ?></p>
                        <p class="text-xs text-gray-600 uppercase font-semibold">Accounting</p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $po['accounting_approved_at'] ? date('M j, Y g:i A', strtotime($po['accounting_approved_at'])) : 'N/A'; ?>
                        </p>
                    </div>

                    <!-- Document Controller Approval -->
                    <div class="text-center">
                        <div class="mb-3">
                            <?php if (!empty($po['doc_signature'])): ?>
                                <img src="data:image/png;base64,<?php echo base64_encode($po['doc_signature']); ?>" 
                                     alt="Document Controller Signature" 
                                     class="h-20 mx-auto object-contain border-b-2 border-gray-300">
                            <?php else: ?>
                                <div class="h-20 flex items-center justify-center border-b-2 border-gray-300">
                                    <span class="text-gray-400 text-sm italic">No signature</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($po['doc_approved_by']); ?></p>
                        <p class="text-xs text-gray-600 uppercase font-semibold">Document Controller</p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $po['document_controller_approved_at'] ? date('M j, Y g:i A', strtotime($po['document_controller_approved_at'])) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-4"></div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Updating PDF...</h3>
                <p class="text-gray-600 text-sm">Please wait while we add the approval signatures to your PDF document.</p>
            </div>
        </div>
    </div>

    <script>
        function updatePDFWithSignatures() {
            if (!confirm('This will regenerate the PDF with approval signatures. Continue?')) {
                return;
            }

            // Show loading modal
            document.getElementById('loadingModal').classList.remove('hidden');

            // Send AJAX request to regenerate PDF
            fetch('regenerate_po_pdf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_id: <?php echo $po_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingModal').classList.add('hidden');
                
                if (data.success) {
                    alert('PDF updated successfully with approval signatures!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update PDF'));
                }
            })
            .catch(error => {
                document.getElementById('loadingModal').classList.add('hidden');
                alert('Error updating PDF: ' + error.message);
            });
        }
    </script>
</body>
</html>