<?php
// document_controller_view_orders.php
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

// Get filters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE conditions
$whereParts = ["po.accounting_status = 'approved'"];
$params = [];
$types = '';

if ($status_filter !== '') {
    $whereParts[] = "po.document_controller_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search_query !== '') {
    $whereParts[] = "(po.po_number LIKE ? OR c.company_name LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereParts);

// Helper function for binding params
function bindParamsToStmt($stmt, $types, $params) {
    if ($types === '' || empty($params)) return;
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// Get purchase orders that are accounting approved
$poSql = "
    SELECT 
        po.id,
        po.po_number,
        po.po_date,
        po.payment_terms,
        po.project_scope,
        po.attachment_path,
        po.client_po_path,
        po.document_controller_status,
        po.document_controller_approved_by,
        po.document_controller_approved_at,
        po.created_at,
        c.company_name,
        c.id as company_id,
        n.fullname as created_by,
        n2.fullname as approved_by_name,
        COUNT(poi.id) as item_count
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.document_controller_approved_by = n2.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
    $whereClause
    GROUP BY po.id
    ORDER BY 
        CASE po.document_controller_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        po.created_at DESC
";

$purchase_orders = [];
if ($stmt = $conn->prepare($poSql)) {
    if (!empty($params)) {
        bindParamsToStmt($stmt, $types, $params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $purchase_orders = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get status counts
$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$countSql = "
    SELECT document_controller_status, COUNT(*) as count 
    FROM purchase_orders 
    WHERE accounting_status = 'approved'
    GROUP BY document_controller_status
";
$res = $conn->query($countSql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status_counts[$row['document_controller_status']] = (int)$row['count'];
    }
}

$totalOrders = array_sum($status_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders Review - Document Controller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
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
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-lg">
                        <i class="fas fa-file-contract text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Purchase Orders Review</h1>
                        <p class="text-gray-600 text-sm">Document Controller - Review & Approve POs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
        
        <?php if (isset($_SESSION['po_success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                <span class="text-green-800 font-medium"><?php echo htmlspecialchars($_SESSION['po_success']); unset($_SESSION['po_success']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['po_error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($_SESSION['po_error']); unset($_SESSION['po_error']); ?></span>
            </div>
        <?php endif; ?>

        

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo $status_filter === '' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <i class="fas fa-list mr-1"></i>
                        All POs
                    </a>
                    
                    <a href="?status=pending<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 <?php echo $status_filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'; ?>">
                        <i class="fas fa-clock"></i>
                        <span>Pending</span>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $status_filter === 'pending' ? 'bg-white/20' : 'bg-yellow-200'; ?>">
                            <?php echo $status_counts['pending']; ?>
                        </span>
                    </a>
                    
                    <a href="?status=approved<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 <?php echo $status_filter === 'approved' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Approved</span>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $status_filter === 'approved' ? 'bg-white/20' : 'bg-green-200'; ?>">
                            <?php echo $status_counts['approved']; ?>
                        </span>
                    </a>
                    
                    <a href="?status=rejected<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 <?php echo $status_filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?>">
                        <i class="fas fa-times-circle"></i>
                        <span>Rejected</span>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $status_filter === 'rejected' ? 'bg-white/20' : 'bg-red-200'; ?>">
                            <?php echo $status_counts['rejected']; ?>
                        </span>
                    </a>
                </div>

                <div class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search PO Number or Company..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-md transition-colors duration-200 flex items-center">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Purchase Orders Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-3">
                <h2 class="text-xl font-bold text-white">Purchase Orders (<?php echo count($purchase_orders); ?>)</h2>
            </div>
            
            <div class="p-6">
                <?php if (!empty($purchase_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">PO #</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Company</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">PO Date</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Items</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Payment Terms</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Documents</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700">Created By</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($purchase_orders as $po): ?>
                                    <tr class="hover:bg-purple-50 transition-colors">
                                        <td class="px-4 py-3 font-mono font-bold text-purple-700"><?php echo htmlspecialchars($po['po_number']); ?></td>
                                        <td class="px-4 py-3 text-gray-900"><?php echo htmlspecialchars(substr($po['company_name'], 0, 25)); ?></td>
                                        <td class="px-4 py-3 text-gray-700 text-xs"><?php echo date('M d, Y', strtotime($po['po_date'])); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                <i class="fas fa-box mr-1"></i><?php echo $po['item_count']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 text-xs"><?php echo htmlspecialchars(substr($po['payment_terms'], 0, 20)); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex gap-1 justify-center">
                                                <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>" target="_blank" 
                                                       class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium" 
                                                       title="View Quotation">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank" 
                                                       class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs font-medium"
                                                       title="View Client PO">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600"><?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'approved' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700'
                                            ];
                                            $status_class = $status_colors[$po['document_controller_status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="inline-block px-3 py-1 <?php echo $status_class; ?> rounded-full text-xs font-bold">
                                                <?php echo ucfirst($po['document_controller_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <a href="document_controller_view_po.php?po_id=<?php echo $po['id']; ?>" 
                                               class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 text-xs font-bold transition-colors">
                                                <i class="fas fa-eye mr-1"></i>View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                        <p class="font-medium text-lg">No purchase orders found</p>
                        <p class="text-sm mt-1">Try adjusting your filters or search criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>