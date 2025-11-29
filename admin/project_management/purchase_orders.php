<?php
// purchase_orders.php - Purchase Order Management for Clients
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

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

// Get company_id from URL
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id <= 0) {
    header("Location: view_companies.php");
    exit();
}

// Get company details
$stmt = $conn->prepare("SELECT company_name, company_address, logo_path FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stmt->bind_result($company_name, $company_address, $logo_path);
if (!$stmt->fetch()) {
    header("Location: view_companies.php");
    exit();
}
$stmt->close();

$message = "";
$error = "";

// Check for success message from add page
if (isset($_SESSION['po_success'])) {
    $message = $_SESSION['po_success'];
    unset($_SESSION['po_success']);
}

// Fetch all purchase orders for this company
$purchase_orders = [];
$stmt = $conn->prepare("SELECT po.id, po.po_number, po.po_date, po.ship_to, po.target_delivery_date, po.payment_terms, po.attachment_path, po.client_po_path, po.status, po.accounting_status, po.created_at, po.approved_by, po.approved_at, po.accounting_approved_by, po.accounting_approved_at, n.fullname as created_by, n2.fullname as approved_by_name, n3.fullname as accounting_approved_by_name FROM purchase_orders po LEFT JOIN nobleaccount n ON po.sales_user_id = n.id LEFT JOIN nobleaccount n2 ON po.approved_by = n2.id LEFT JOIN nobleaccount n3 ON po.accounting_approved_by = n3.id WHERE po.company_id = ? AND po.sales_user_id = ? ORDER BY po.created_at DESC");
$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchase_orders[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - <?php echo htmlspecialchars($company_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
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
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <!-- Back Button -->
                        <a href="view_companies.php" class="bg-gray-200 hover:bg-gray-300 p-2 rounded-lg transition">
                            <i class="fas fa-arrow-left text-gray-700"></i>
                        </a>
                        
                        <!-- Company Logo -->
                        <?php if (!empty($logo_path) && file_exists($logo_path)): ?>
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                                alt="<?php echo htmlspecialchars($company_name); ?>"
                                class="h-12 w-12 object-contain rounded-lg border border-gray-200">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-blue-600 text-xl"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">
                                <?php echo htmlspecialchars($company_name); ?>
                            </h1>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo htmlspecialchars($company_address); ?>
                            </p>
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
                                <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                            </div>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
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

        <?php
// Check if there are any rejected POs (both operational and accounting)
$rejected_count = 0;
foreach ($purchase_orders as $po) {
    if ($po['status'] === 'rejected' || $po['accounting_status'] === 'rejected') {
        $rejected_count++;
    }
}
?>
        <?php if ($rejected_count > 0): ?>
            <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-orange-600 text-xl mr-3 mt-1"></i>
                    <div class="flex-1">
                        <h3 class="text-orange-800 font-bold text-sm mb-1">
                            <?php echo $rejected_count; ?> Purchase Order<?php echo $rejected_count > 1 ? 's' : ''; ?> Rejected
                        </h3>
                        <p class="text-orange-700 text-xs">
                            You have rejected purchase orders that need attention. Please review the rejection reasons and click "Edit & Resubmit" to update and resubmit them.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?php
// Count operational statuses
$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'processing' => 0];
$accounting_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

foreach ($purchase_orders as $po) {
    if (isset($status_counts[$po['status']])) {
        $status_counts[$po['status']]++;
    }
    if (isset($accounting_counts[$po['accounting_status']])) {
        $accounting_counts[$po['accounting_status']]++;
    }
}
?>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600 font-medium">Pending</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $status_counts['pending']; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600 font-medium">Approved</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600 font-medium">Rejected</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $status_counts['rejected']; ?></p>
                    </div>
                    <i class="fas fa-times-circle text-red-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600 font-medium">Total</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo count($purchase_orders); ?></p>
                    </div>
                    <i class="fas fa-file-invoice text-blue-500 text-2xl"></i>
                </div>
            </div>

            <!-- Accounting Status Cards -->
            <div class="col-span-2 md:col-span-4 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-lg shadow-md p-4 border border-indigo-200">
                <p class="text-xs font-bold text-indigo-800 mb-3 flex items-center">
                    <i class="fas fa-calculator mr-2"></i>ACCOUNTING STATUS
                </p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white rounded p-3 border-l-2 border-yellow-500">
                        <p class="text-xs text-gray-600">Pending</p>
                        <p class="text-xl font-bold text-yellow-600"><?php echo $accounting_counts['pending']; ?></p>
                    </div>
                    <div class="bg-white rounded p-3 border-l-2 border-green-500">
                        <p class="text-xs text-gray-600">Approved</p>
                        <p class="text-xl font-bold text-green-600"><?php echo $accounting_counts['approved']; ?></p>
                    </div>
                    <div class="bg-white rounded p-3 border-l-2 border-red-500">
                        <p class="text-xs text-gray-600">Rejected</p>
                        <p class="text-xl font-bold text-red-600"><?php echo $accounting_counts['rejected']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
                <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-2 sm:mr-3"></i>
                    Purchase Orders (<?php echo count($purchase_orders); ?>)
                </h2>
                <a href="add_purchase_order.php?company_id=<?php echo $company_id; ?>"
                    class="bg-white hover:bg-gray-100 text-blue-600 px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 font-medium shadow-md">
                    <i class="fas fa-plus"></i>
                    <span class="hidden sm:inline">Add Purchase Order</span>
                    <span class="sm:hidden">Add PO</span>
                </a>
            </div>
            
            <div class="p-4 sm:p-6">
                <?php if (!empty($purchase_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">PO #</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">PO Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Ship To</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Delivery</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Payment Terms</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Ops Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Accounting Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Our Quotation</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Client's PO</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Ops Approval</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Created By</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($purchase_orders as $po): ?>
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-mono font-bold text-blue-700">
                                                <?php echo htmlspecialchars($po['po_number']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo date('M j, Y', strtotime($po['po_date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 text-xs">
                                            <?php echo htmlspecialchars(substr($po['ship_to'], 0, 30)) . (strlen($po['ship_to']) > 30 ? '...' : ''); ?>
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
        'pending' => 'bg-yellow-100 text-yellow-700',
        'approved' => 'bg-green-100 text-green-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'shipped' => 'bg-purple-100 text-purple-700',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-700',
        'rejected' => 'bg-red-100 text-red-700'
    ];
    $status_class = $status_colors[$po['status']] ?? 'bg-gray-100 text-gray-700';
    ?>
    <span class="inline-flex items-center px-2 py-1 <?php echo $status_class; ?> rounded-full text-xs font-semibold">
        <?php echo ucfirst($po['status']); ?>
    </span>
    
    <?php if ($po['status'] === 'rejected'): ?>
        <?php
        // Fetch operational rejection reason from logs
        $ops_rejection_notes = '';
        $ops_log_query = "SELECT notes FROM po_status_logs WHERE po_id = ? AND new_status = 'rejected' AND (old_status = 'pending' OR old_status = 'approved') ORDER BY created_at DESC LIMIT 1";
        $ops_log_stmt = $conn->prepare($ops_log_query);
        
        if ($ops_log_stmt) {
            $ops_log_stmt->bind_param("i", $po['id']);
            $ops_log_stmt->execute();
            $ops_log_result = $ops_log_stmt->get_result();
            
            if ($ops_log_row = $ops_log_result->fetch_assoc()) {
                $ops_rejection_notes = $ops_log_row['notes'];
            }
            $ops_log_stmt->close();
        }
        ?>
        
        <?php if (!empty($ops_rejection_notes)): ?>
            <div class="mt-2 text-xs text-red-700 bg-red-50 p-2 rounded border border-red-200 text-left">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Ops Rejection:</strong> <?php echo htmlspecialchars(substr($ops_rejection_notes, 0, 100)); ?>
                <?php if (strlen($ops_rejection_notes) > 100): ?>...<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mt-2 text-xs text-gray-500 italic">
                No rejection reason provided
            </div>
        <?php endif; ?>
    <?php endif; ?>
</td>
                                        <td class="px-4 py-3 text-center">
    <?php
    $accounting_status_colors = [
        'pending' => 'bg-yellow-100 text-yellow-700',
        'approved' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700'
    ];
    $accounting_class = $accounting_status_colors[$po['accounting_status'] ?? 'pending'] ?? 'bg-gray-100 text-gray-700';
    ?>
    <span class="inline-flex items-center px-2 py-1 <?php echo $accounting_class; ?> rounded-full text-xs font-semibold">
        <?php echo ucfirst($po['accounting_status'] ?? 'pending'); ?>
    </span>
    
    <?php if ($po['accounting_status'] === 'rejected'): ?>
        <?php
        // Fetch accounting rejection reason from logs
        $acc_rejection_notes = '';
        $acc_log_query = "SELECT notes FROM po_status_logs WHERE po_id = ? AND new_status = 'rejected' AND old_status != 'rejected' ORDER BY created_at DESC LIMIT 1";
        $acc_log_stmt = $conn->prepare($acc_log_query);
        
        if ($acc_log_stmt) {
            $acc_log_stmt->bind_param("i", $po['id']);
            $acc_log_stmt->execute();
            $acc_log_result = $acc_log_stmt->get_result();
            
            if ($acc_log_row = $acc_log_result->fetch_assoc()) {
                $acc_rejection_notes = $acc_log_row['notes'];
            }
            $acc_log_stmt->close();
        }
        ?>
        
        <?php if (!empty($acc_rejection_notes)): ?>
            <div class="mt-2 text-xs text-red-700 bg-red-50 p-2 rounded border border-red-200 text-left">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Reason:</strong> <?php echo htmlspecialchars(substr($acc_rejection_notes, 0, 100)); ?>
                <?php if (strlen($acc_rejection_notes) > 100): ?>...<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mt-2 text-xs text-gray-500 italic">
                No rejection reason provided
            </div>
        <?php endif; ?>
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
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($po['status'] === 'approved' || $po['status'] === 'rejected'): ?>
                                                <div class="text-xs">
                                                    <div class="font-semibold text-gray-700">
                                                        <?php echo htmlspecialchars($po['approved_by_name'] ?? 'Unknown'); ?>
                                                    </div>
                                                    <div class="text-gray-500 mt-1">
                                                        <?php echo $po['approved_at'] ? date('M j, Y g:i A', strtotime($po['approved_at'])) : 'N/A'; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic">Pending review</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 text-xs">
    <?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?>
</td>
<td class="px-4 py-3 text-center">
    <?php if ($po['status'] === 'rejected' || $po['accounting_status'] === 'rejected'): ?>
        <a href="edit_purchase_order.php?po_id=<?php echo $po['id']; ?>" 
           class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition-colors text-xs font-bold">
            <i class="fas fa-edit mr-1"></i>
            Edit & Resubmit
        </a>
    <?php else: ?>
        <span class="text-gray-400 text-xs">-</span>
    <?php endif; ?>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-file-invoice text-5xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium">No purchase orders yet</p>
                        <p class="text-sm">Add your first purchase order using the form above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>