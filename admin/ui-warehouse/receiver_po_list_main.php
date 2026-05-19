<?php
// receiver_po_list.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse']);
require_subrole(['warehouse_receiver']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$receiver_id = $_SESSION['noble_id'] ?? 0;
$receiver_name = $_SESSION['noble_user']['fullname'] ?? '';

// Regular assignments
$assignmentsSql = "SELECT 
                    pra.*,
                    'regular' as assignment_type,
                    pa.original_filename,
                    pa.supplier_name,
                    o.customer_name,
                    o.total as order_total,
                    assigner.fullname as assigned_by_name,
                    (SELECT COUNT(*) FROM order_items oi 
                     WHERE oi.po_number = pra.po_number AND oi.received_status = 'pending') +
                    (SELECT COUNT(*) FROM replacement_requests rr 
                     WHERE rr.po_number = pra.po_number AND rr.received_status = 'pending') as pending_items,
                    (SELECT COUNT(*) FROM order_items oi 
                     WHERE oi.po_number = pra.po_number) +
                    (SELECT COUNT(*) FROM replacement_requests rr 
                     WHERE rr.po_number = pra.po_number) as total_items
                   FROM po_receiver_assignments pra
                   LEFT JOIN po_attachments pa ON pra.po_attachment_id = pa.id
                   LEFT JOIN orders o ON pra.order_id = o.id
                   LEFT JOIN nobleaccount assigner ON pra.assigned_by = assigner.id
                   WHERE pra.receiver_id = ?
                   ORDER BY pra.status ASC, pra.assigned_at DESC";
$assignmentsStmt = $conn->prepare($assignmentsSql);
$assignmentsStmt->bind_param("i", $receiver_id);
$assignmentsStmt->execute();
$assignments = $assignmentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$assignmentsStmt->close();

// Replacement assignments
$replacementAssignmentsSql = "SELECT 
                    rr.id,
                    rr.po_number,
                    rr.order_id,
                    rr.status,
                    rr.created_at as assigned_at,
                    rr.received_status,
                    'replacement' as assignment_type,
                    NULL as original_filename,
                    NULL as completed_at,
                    COALESCE(sl.business_name, oi.manual_supplier_name, 'Unknown Supplier') as supplier_name,
                    o.customer_name,
                    o.total as order_total,
                    assigner.fullname as assigned_by_name,
                    CASE WHEN rr.received_status = 'pending' THEN 1 ELSE 0 END as pending_items,
                    1 as total_items
                   FROM replacement_requests rr
                   LEFT JOIN order_items oi ON rr.order_item_id = oi.id
                   LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
                   LEFT JOIN orders o ON rr.order_id = o.id
                   LEFT JOIN nobleaccount assigner ON rr.receiver_id = assigner.id
                   WHERE rr.receiver_id = ? 
                   AND rr.po_number IS NOT NULL
                   AND rr.po_number != ''
                   ORDER BY rr.created_at DESC";
$replacementStmt = $conn->prepare($replacementAssignmentsSql);
$replacementStmt->bind_param("i", $receiver_id);
$replacementStmt->execute();
$replacementAssignments = $replacementStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$replacementStmt->close();

// Counts
$activeCount = 0;
$completedCount = 0;
$activeReplacementCount = 0;
$completedReplacementCount = 0;

foreach ($assignments as $a) {
    if ($a['status'] == 'active')
        $activeCount++;
    else
        $completedCount++;
}
foreach ($replacementAssignments as $ra) {
    if ($ra['received_status'] == 'pending')
        $activeReplacementCount++;
    else
        $completedReplacementCount++;
}

$totalAll = count($assignments) + count($replacementAssignments);
$totalActive = $activeCount + $activeReplacementCount;
$totalCompleted = $completedCount + $completedReplacementCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My P.O. Assignments</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- ── Header ───────────────────────────────────────────────── -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <!-- Title -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-red-500 flex items-center justify-center shrink-0">
                        <i class="fas fa-clipboard-list text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-semibold text-gray-900 leading-tight">My P.O. Assignments</h1>
                        <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($receiver_name); ?></p>
                    </div>
                </div>

                <!-- Stat Pills -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-50 border border-violet-200 text-xs font-medium text-violet-700">
                        <i class="fas fa-tasks text-[10px]"></i> <?php echo $totalActive; ?> Active
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 border border-green-200 text-xs font-medium text-green-700">
                        <i class="fas fa-check-circle text-[10px]"></i> <?php echo $totalCompleted; ?> Completed
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                        <i class="fas fa-sync-alt text-[10px]"></i> <?php echo count($replacementAssignments); ?>
                        Replacements
                    </span>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Main ─────────────────────────────────────────────────── -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6">

        <?php if ($totalAll === 0): ?>
            <!-- Empty State -->
            <div class="bg-white border border-gray-200 rounded-xl p-12 text-center">
                <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-inbox text-gray-400 text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 mb-1">No P.O. Assignments</h3>
                <p class="text-xs text-gray-500">You don't have any P.O. assignments yet.</p>
            </div>

        <?php else: ?>

            <!-- ── Filter Tabs ───────────────────────────────────── -->
            <div class="bg-white border border-gray-200 rounded-xl p-3 mb-5">
                <div class="flex flex-wrap gap-2">
                    <?php
                    $filters = [
                        'all' => "All ({$totalAll})",
                        'active' => "Active ({$totalActive})",
                        'completed' => "Completed ({$totalCompleted})",
                        'regular' => "Regular P.O. (" . count($assignments) . ")",
                        'replacement' => "Replacements (" . count($replacementAssignments) . ")",
                    ];
                    foreach ($filters as $key => $label):
                        ?>
                        <button onclick="filterCards('<?php echo $key; ?>', this)"
                            class="filter-btn px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                               <?php echo $key === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <?php echo $label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Cards Grid ───────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <!-- Regular P.O. Cards -->
                <?php foreach ($assignments as $assignment):
                    $isActive = $assignment['status'] == 'active';
                    $received = $assignment['total_items'] - $assignment['pending_items'];
                    $progress = $assignment['total_items'] > 0 ? ($received / $assignment['total_items']) * 100 : 0;
                    ?>
                    <div class="assignment-card bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow"
                        data-type="regular" data-status="<?php echo $isActive ? 'active' : 'completed'; ?>">

                        <!-- Card Top Bar -->
                        <div class="flex items-center justify-between px-4 py-2.5
                        <?php echo $isActive ? 'bg-red-600' : 'bg-green-600'; ?>">
                            <div class="flex items-center gap-1.5 text-white text-xs font-semibold">
                                <i class="fas fa-file-invoice text-[10px]"></i>
                                <?php echo $isActive ? 'Active' : 'Completed'; ?>
                            </div>
                            <?php if (!$isActive && $assignment['completed_at']): ?>
                                <span class="text-white/80 text-[10px]">
                                    <?php echo date('M j, Y', strtotime($assignment['completed_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="p-4">

                            <!-- PO Number -->
                            <div class="mb-3">
                                <p class="text-[10px] text-gray-400 mb-0.5">P.O. Number</p>
                                <p class="font-mono text-sm font-bold text-gray-900">
                                    <?php echo htmlspecialchars($assignment['po_number']); ?></p>
                            </div>

                            <!-- Details -->
                            <div class="space-y-2 mb-3">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-building text-gray-300 text-xs mt-0.5 w-3"></i>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Supplier</p>
                                        <p class="text-xs font-medium text-gray-900">
                                            <?php echo htmlspecialchars($assignment['supplier_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-user text-gray-300 text-xs mt-0.5 w-3"></i>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Customer</p>
                                        <p class="text-xs font-medium text-gray-900">
                                            <?php echo htmlspecialchars($assignment['customer_name']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $received; ?>/<?php echo $assignment['total_items']; ?> items</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full transition-all duration-300
                                    <?php echo $progress >= 100 ? 'bg-green-500' : 'bg-violet-500'; ?>"
                                        style="width:<?php echo $progress; ?>%"></div>
                                </div>
                            </div>

                            <!-- Meta -->
                            <div class="text-[10px] text-gray-400 pb-3 mb-3 border-b border-gray-100">
                                <p>Assigned by <strong
                                        class="text-gray-600"><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></strong>
                                </p>
                                <p class="mt-0.5"><?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?></p>
                            </div>

                            <a href="<?= BASE_URL; ?>/receiverviewpoitems?po_number=<?php echo urlencode($assignment['po_number']); ?>&assignment_id=<?php echo $assignment['id']; ?>"
                                class="flex items-center justify-center gap-2 w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors">
                                <i class="fas fa-eye text-[10px]"></i> View Items & Receive
                            </a>

                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Replacement Cards -->
                <?php foreach ($replacementAssignments as $ra):
                    $raIsActive = $ra['received_status'] == 'pending';
                    ?>
                    <div class="assignment-card bg-white border-2 border-red-100 rounded-xl overflow-hidden hover:shadow-md transition-shadow"
                        data-type="replacement" data-status="<?php echo $raIsActive ? 'active' : 'completed'; ?>">

                        <!-- Card Top Bar -->
                        <div class="flex items-center justify-between px-4 py-2.5
                        <?php echo $raIsActive ? 'bg-rose-600' : 'bg-green-600'; ?>">
                            <div class="flex items-center gap-1.5 text-white text-xs font-semibold">
                                <i class="fas fa-sync-alt text-[10px]"></i>
                                Replacement — <?php echo $raIsActive ? 'Pending' : 'Received'; ?>
                            </div>
                            <?php if (!$raIsActive): ?>
                                <span class="text-white/80 text-[10px]">
                                    <?php echo date('M j, Y', strtotime($ra['assigned_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="p-4">

                            <!-- Replacement Badge -->
                            <div class="mb-3">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">
                                    <i class="fas fa-sync-alt text-[8px]"></i> REPLACEMENT ITEM
                                </span>
                            </div>

                            <!-- PO Number -->
                            <div class="mb-3">
                                <p class="text-[10px] text-gray-400 mb-0.5">P.O. Number</p>
                                <p class="font-mono text-sm font-bold text-gray-900">
                                    <?php echo htmlspecialchars($ra['po_number']); ?></p>
                            </div>

                            <!-- Details -->
                            <div class="space-y-2 mb-3">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-building text-gray-300 text-xs mt-0.5 w-3"></i>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Supplier</p>
                                        <p class="text-xs font-medium text-gray-900">
                                            <?php echo htmlspecialchars($ra['supplier_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-user text-gray-300 text-xs mt-0.5 w-3"></i>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Customer</p>
                                        <p class="text-xs font-medium text-gray-900">
                                            <?php echo htmlspecialchars($ra['customer_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-hashtag text-gray-300 text-xs mt-0.5 w-3"></i>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Order</p>
                                        <p class="text-xs font-medium text-gray-900">#<?php echo $ra['order_id']; ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $raIsActive ? '0' : '1'; ?>/1 item</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full transition-all duration-300
                                    <?php echo $raIsActive ? 'bg-red-400' : 'bg-green-500'; ?>"
                                        style="width:<?php echo $raIsActive ? 0 : 100; ?>%"></div>
                                </div>
                            </div>

                            <!-- Meta -->
                            <div class="text-[10px] text-gray-400 pb-3 mb-3 border-b border-gray-100">
                                <p>Assigned: <?php echo date('M j, Y g:i A', strtotime($ra['assigned_at'])); ?></p>
                            </div>

                            <a href="<?= BASE_URL; ?>/receiverviewpoitems?po_number=<?php echo urlencode($ra['po_number']); ?>&replacement_id=<?php echo $ra['id']; ?>"
                                class="flex items-center justify-center gap-2 w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors">
                                <i class="fas fa-eye text-[10px]"></i> View & Receive Replacement
                            </a>

                        </div>
                    </div>
                <?php endforeach; ?>

            </div><!-- end grid -->
        <?php endif; ?>
    </div><!-- end max-w-6xl -->

    <script>
        function filterCards(filter, btn) {
            // Update button styles
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.remove('bg-gray-900', 'text-white');
                b.classList.add('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
            });
            btn.classList.remove('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
            btn.classList.add('bg-gray-900', 'text-white');

            // Show/hide cards
            document.querySelectorAll('.assignment-card').forEach(card => {
                const type = card.dataset.type;
                const status = card.dataset.status;
                let show = filter === 'all'
                    || (filter === 'active' && status === 'active')
                    || (filter === 'completed' && status === 'completed')
                    || (filter === 'regular' && type === 'regular')
                    || (filter === 'replacement' && type === 'replacement');
                card.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>

</html>