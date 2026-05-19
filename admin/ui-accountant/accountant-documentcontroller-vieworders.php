<?php
// document_controller_view_orders.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
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

function bindParamsToStmt($stmt, $types, $params)
{
    if ($types === '' || empty($params))
        return;
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

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
        n.fullname  AS created_by,
        n2.fullname AS approved_by_name,
        COUNT(poi.id) as item_count
    FROM purchase_orders po
    LEFT JOIN companies c       ON po.company_id = c.id
    LEFT JOIN nobleaccount n    ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2   ON po.document_controller_approved_by = n2.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
    $whereClause
    GROUP BY po.id
    ORDER BY
        CASE po.document_controller_status
            WHEN 'pending'  THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        po.created_at DESC
";

$purchase_orders = [];
if ($stmt = $conn->prepare($poSql)) {
    if (!empty($params))
        bindParamsToStmt($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res)
        $purchase_orders = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

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
        $status_counts[$row['document_controller_status']] = (int) $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders Review — Document Controller</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-6 py-6 space-y-5">

        <!-- Page Header -->
        <div class="bg-white border-b border-gray-200 rounded-lg ">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-file-contract text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 leading-tight">Purchase Orders Review</h1>
                        <p class="text-sm text-gray-500">Document Controller — Review &amp; approve accounting-cleared
                            POs</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['po_success'])): ?>
            <div
                class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm font-medium">
                <i class="fas fa-check-circle text-green-500 shrink-0"></i>
                <?php echo htmlspecialchars($_SESSION['po_success']);
                unset($_SESSION['po_success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['po_error'])): ?>
            <div
                class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm font-medium">
                <i class="fas fa-exclamation-circle text-red-500 shrink-0"></i>
                <?php echo htmlspecialchars($_SESSION['po_error']);
                unset($_SESSION['po_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Pending Review</p>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $status_counts['pending']; ?></p>
                </div>
                <div class="w-11 h-11 bg-yellow-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-yellow-500 text-lg"></i>
                </div>
            </div>
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Approved</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></p>
                </div>
                <div class="w-11 h-11 bg-green-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
            </div>
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Rejected</p>
                    <p class="text-3xl font-bold text-red-600"><?php echo $status_counts['rejected']; ?></p>
                </div>
                <div class="w-11 h-11 bg-red-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-500 text-lg"></i>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            <!-- Card Header + Search -->
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">Purchase Orders</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Showing only accounting-approved POs</p>
                    </div>
                    <!-- Search -->
                    <form method="GET" class="flex items-center gap-2">
                        <?php if ($status_filter !== ''): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search PO # or company..."
                            class="text-sm px-3 py-2 border border-gray-200 rounded-lg w-56
                               focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition">
                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-lg
                               bg-purple-600 hover:bg-purple-700 text-white transition-colors">
                            <i class="fas fa-search text-xs"></i> Search
                        </button>
                        <?php if ($search_query !== ''): ?>
                            <a href="?<?php echo $status_filter ? 'status=' . urlencode($status_filter) : ''; ?>"
                                class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1.5 px-5 py-3 bg-gray-50 border-b border-gray-100 flex-wrap">
                <a href="?<?php echo $search_query ? 'search=' . urlencode($search_query) : ''; ?>"
                    class="text-xs font-medium px-3 py-1.5 rounded-md border transition-all
                      <?php echo $status_filter === ''
                          ? 'bg-white border-gray-300 text-gray-700 shadow-sm'
                          : 'border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700'; ?>">
                    All
                    <span class="ml-1 bg-gray-100 text-gray-600 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo array_sum($status_counts); ?>
                    </span>
                </a>
                <a href="?status=pending<?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>"
                    class="text-xs font-medium px-3 py-1.5 rounded-md border transition-all
                      <?php echo $status_filter === 'pending'
                          ? 'bg-yellow-50 border-yellow-300 text-yellow-800'
                          : 'border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700'; ?>">
                    <i class="fas fa-clock mr-1 text-yellow-500"></i>Pending
                    <span
                        class="ml-1 bg-yellow-100 text-yellow-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['pending']; ?>
                    </span>
                </a>
                <a href="?status=approved<?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>"
                    class="text-xs font-medium px-3 py-1.5 rounded-md border transition-all
                      <?php echo $status_filter === 'approved'
                          ? 'bg-green-50 border-green-300 text-green-800'
                          : 'border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700'; ?>">
                    <i class="fas fa-check-circle mr-1 text-green-500"></i>Approved
                    <span class="ml-1 bg-green-100 text-green-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['approved']; ?>
                    </span>
                </a>
                <a href="?status=rejected<?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>"
                    class="text-xs font-medium px-3 py-1.5 rounded-md border transition-all
                      <?php echo $status_filter === 'rejected'
                          ? 'bg-red-50 border-red-300 text-red-800'
                          : 'border-transparent text-gray-500 hover:bg-white hover:border-gray-200 hover:text-gray-700'; ?>">
                    <i class="fas fa-times-circle mr-1 text-red-500"></i>Rejected
                    <span class="ml-1 bg-red-100 text-red-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                        <?php echo $status_counts['rejected']; ?>
                    </span>
                </a>

                <!-- Record count pushed to the right -->
                <span class="ml-auto text-xs text-gray-400 bg-gray-100 px-3 py-1 rounded-full font-medium">
                    <?php echo count($purchase_orders); ?> record<?php echo count($purchase_orders) !== 1 ? 's' : ''; ?>
                </span>
            </div>

            <!-- Table -->
            <?php if (!empty($purchase_orders)): ?>
                <div class="overflow-x-auto">
                    <div class="max-h-[520px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                                <tr>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        PO Number</th>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Company</th>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        PO Date</th>
                                    <th
                                        class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Items</th>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Payment Terms</th>
                                    <th
                                        class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Documents</th>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Created By</th>
                                    <th
                                        class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Status</th>
                                    <th
                                        class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($purchase_orders as $po):
                                    $badge_cls = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ][$po['document_controller_status']] ?? 'bg-gray-100 text-gray-700';

                                    $badge_icon = [
                                        'pending' => 'fa-clock',
                                        'approved' => 'fa-check-circle',
                                        'rejected' => 'fa-times-circle',
                                    ][$po['document_controller_status']] ?? 'fa-circle';
                                    ?>
                                    <tr class="hover:bg-purple-50 transition-colors">

                                        <!-- PO Number -->
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <span
                                                class="font-mono text-xs font-semibold text-purple-700 bg-purple-50 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($po['po_number']); ?>
                                            </span>
                                        </td>

                                        <!-- Company -->
                                        <td class="px-5 py-3.5">
                                            <span class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($po['company_name'] ?? '—'); ?>
                                            </span>
                                        </td>

                                        <!-- PO Date -->
                                        <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                            <?php echo date('M d, Y', strtotime($po['po_date'])); ?>
                                        </td>

                                        <!-- Items -->
                                        <td class="px-5 py-3.5 text-center">
                                            <span
                                                class="inline-flex items-center gap-1 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 px-2 py-1 rounded-full">
                                                <i class="fas fa-box text-[10px]"></i>
                                                <?php echo (int) $po['item_count']; ?>
                                            </span>
                                        </td>

                                        <!-- Payment Terms -->
                                        <td class="px-5 py-3.5 text-gray-600 text-xs whitespace-nowrap">
                                            <?php echo htmlspecialchars($po['payment_terms'] ?? '—'); ?>
                                        </td>

                                        <!-- Documents -->
                                        <td class="px-5 py-3.5 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <?php if (!empty($po['attachment_path']) && file_exists($po['attachment_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($po['attachment_path']); ?>"
                                                        target="_blank" title="View Quotation"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2.5 py-1 rounded-md transition-colors">
                                                        <i class="fas fa-file-pdf"></i> QT
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-200 text-xs">—</span>
                                                <?php endif; ?>
                                                <?php if (!empty($po['client_po_path']) && file_exists($po['client_po_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($po['client_po_path']); ?>" target="_blank"
                                                        title="View Client PO"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-2.5 py-1 rounded-md transition-colors">
                                                        <i class="fas fa-file-alt"></i> PO
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Created By -->
                                        <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                            <?php echo htmlspecialchars($po['created_by'] ?? 'Unknown'); ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-5 py-3.5 text-center">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full <?php echo $badge_cls; ?>">
                                                <i class="fas <?php echo $badge_icon; ?> text-[10px]"></i>
                                                <?php echo ucfirst($po['document_controller_status']); ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-5 py-3.5 text-center">
                                            <a href="document_controller_view_po.php?po_id=<?php echo $po['id']; ?>"
                                                class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-md
                                              bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 transition-colors">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="py-14 text-center">
                    <i class="fas fa-inbox text-4xl text-gray-200 mb-3 block"></i>
                    <p class="text-sm font-medium text-gray-500">No purchase orders found</p>
                    <p class="text-xs text-gray-400 mt-1">Try adjusting your filters or search criteria.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>

</html>