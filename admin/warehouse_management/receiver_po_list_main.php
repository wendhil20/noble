<?php
// receiver_po_list.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse']);
require_subrole(['warehouse_receiver']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$receiver_id = $_SESSION['noble_id'] ?? 0;
$receiver_name = $_SESSION['noble_user']['fullname'] ?? '';

// Get assigned P.O.s for this receiver (regular assignments)
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

// Get replacement assignments for this receiver
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

// Count active/completed for each type
$activeCount = 0;
$completedCount = 0;
$activeReplacementCount = 0;
$completedReplacementCount = 0;

foreach ($assignments as $assignment) {
    if ($assignment['status'] == 'active') $activeCount++;
    else $completedCount++;
}

foreach ($replacementAssignments as $ra) {
    if ($ra['received_status'] == 'pending') $activeReplacementCount++;
    else $completedReplacementCount++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My P.O. Assignments</title>
    
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-red-400 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-clipboard-list text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">My P.O. Assignments</h1>
                        <p class="text-gray-600 mt-1">Receiver: <?php echo htmlspecialchars($receiver_name); ?></p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <div class="bg-purple-50 border border-purple-200 px-4 py-2 rounded-lg">
                        <span class="text-purple-700 font-medium">
                            <i class="fas fa-tasks mr-1"></i>
                            <?php echo $activeCount + $activeReplacementCount; ?> Active
                        </span>
                    </div>
                    <div class="bg-green-50 border border-green-200 px-4 py-2 rounded-lg">
                        <span class="text-green-700 font-medium">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo $completedCount + $completedReplacementCount; ?> Completed
                        </span>
                    </div>
                    <div class="bg-red-50 border border-red-200 px-4 py-2 rounded-lg">
                        <span class="text-red-700 font-medium">
                            <i class="fas fa-sync-alt mr-1"></i>
                            <?php echo count($replacementAssignments); ?> Replacements
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php 
        $totalAll = count($assignments) + count($replacementAssignments);
        $totalActive = $activeCount + $activeReplacementCount;
        $totalCompleted = $completedCount + $completedReplacementCount;

        if ($totalAll === 0): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No P.O. Assignments</h3>
                <p class="text-sm text-gray-500">You don't have any P.O. assignments yet.</p>
            </div>
        <?php else: ?>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap gap-2">
                    <button onclick="filterCards('all')" 
                            class="filter-btn active px-4 py-2 rounded-lg transition-colors duration-200 bg-primary-100 text-primary-700 font-medium">
                        All (<?php echo $totalAll; ?>)
                    </button>
                    <button onclick="filterCards('active')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200 text-gray-600 hover:bg-gray-100">
                        Active (<?php echo $totalActive; ?>)
                    </button>
                    <button onclick="filterCards('completed')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200 text-gray-600 hover:bg-gray-100">
                        Completed (<?php echo $totalCompleted; ?>)
                    </button>
                    <button onclick="filterCards('regular')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200 text-gray-600 hover:bg-gray-100">
                        <i class="fas fa-file-invoice mr-1"></i>Regular P.O. (<?php echo count($assignments); ?>)
                    </button>
                    <button onclick="filterCards('replacement')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200 text-gray-600 hover:bg-gray-100">
                        <i class="fas fa-sync-alt mr-1"></i>Replacements (<?php echo count($replacementAssignments); ?>)
                    </button>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- REGULAR P.O. ASSIGNMENTS -->
                <?php foreach ($assignments as $assignment): 
                    $isActive = $assignment['status'] == 'active';
                    $progress = 0;
                    if ($assignment['total_items'] > 0) {
                        $receivedItems = $assignment['total_items'] - $assignment['pending_items'];
                        $progress = ($receivedItems / $assignment['total_items']) * 100;
                    }
                ?>
                    <div class="assignment-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-200"
                         data-type="regular"
                         data-status="<?php echo $isActive ? 'active' : 'completed'; ?>">

                        <!-- Header -->
                        <div class="<?php echo $isActive ? 'bg-gradient-to-r from-red-500 to-red-600' : 'bg-gradient-to-r from-green-500 to-green-600'; ?> p-4">
                            <div class="flex items-center justify-between text-white">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-file-invoice"></i>
                                    <span class="font-semibold"><?php echo $isActive ? 'Active' : 'Completed'; ?></span>
                                </div>
                                <?php if (!$isActive && $assignment['completed_at']): ?>
                                    <span class="text-xs opacity-90"><?php echo date('M j, Y', strtotime($assignment['completed_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="mb-4">
                                <div class="text-xs text-gray-500 mb-1">P.O. Number</div>
                                <div class="font-mono font-bold text-lg text-gray-900"><?php echo htmlspecialchars($assignment['po_number']); ?></div>
                            </div>

                            <div class="space-y-2 text-sm mb-4">
                                <div class="flex items-start">
                                    <i class="fas fa-building w-5 text-gray-400 mr-2 mt-0.5"></i>
                                    <div>
                                        <div class="text-xs text-gray-500">Supplier</div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['supplier_name']); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-user w-5 text-gray-400 mr-2 mt-0.5"></i>
                                    <div>
                                        <div class="text-xs text-gray-500">Customer</div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['customer_name']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo ($assignment['total_items'] - $assignment['pending_items']); ?>/<?php echo $assignment['total_items']; ?> items</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $progress >= 100 ? 'bg-green-500' : 'bg-purple-500'; ?> h-2 rounded-full transition-all duration-300"
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 mb-4 pb-4 border-b border-gray-200">
                                <span>Assigned by <?php echo htmlspecialchars($assignment['assigned_by_name']); ?></span>
                                <div class="mt-1"><?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?></div>
                            </div>

                            <a href="receiver_view_po_items_A.php?po_number=<?php echo urlencode($assignment['po_number']); ?>&assignment_id=<?php echo $assignment['id']; ?>"
                               class="block w-full bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center font-medium">
                                <i class="fas fa-eye mr-2"></i>View Items & Receive
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- REPLACEMENT ASSIGNMENTS -->
                <?php foreach ($replacementAssignments as $ra):
                    $raIsActive = $ra['received_status'] == 'pending';
                    $raProgress = $raIsActive ? 0 : 100;
                ?>
                    <div class="assignment-card bg-white rounded-xl shadow-sm border-2 border-red-200 overflow-hidden hover:shadow-lg transition-shadow duration-200"
                         data-type="replacement"
                         data-status="<?php echo $raIsActive ? 'active' : 'completed'; ?>">

                        <!-- Header — always red-toned to distinguish replacements -->
                        <div class="<?php echo $raIsActive ? 'bg-gradient-to-r from-red-500 to-rose-500' : 'bg-gradient-to-r from-green-500 to-green-600'; ?> p-4">
                            <div class="flex items-center justify-between text-white">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-sync-alt"></i>
                                    <span class="font-semibold">
                                        Replacement — <?php echo $raIsActive ? 'Pending' : 'Received'; ?>
                                    </span>
                                </div>
                                <?php if (!$raIsActive): ?>
                                    <span class="text-xs opacity-90"><?php echo date('M j, Y', strtotime($ra['assigned_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-5">
                            <!-- Replacement badge -->
                            <div class="mb-3">
                                <span class="inline-flex items-center bg-red-100 text-red-800 text-xs font-bold px-3 py-1 rounded-full">
                                    <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT ITEM
                                </span>
                            </div>

                            <div class="mb-4">
                                <div class="text-xs text-gray-500 mb-1">P.O. Number</div>
                                <div class="font-mono font-bold text-lg text-gray-900"><?php echo htmlspecialchars($ra['po_number']); ?></div>
                            </div>

                            <div class="space-y-2 text-sm mb-4">
                                <div class="flex items-start">
                                    <i class="fas fa-building w-5 text-gray-400 mr-2 mt-0.5"></i>
                                    <div>
                                        <div class="text-xs text-gray-500">Supplier</div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($ra['supplier_name']); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-user w-5 text-gray-400 mr-2 mt-0.5"></i>
                                    <div>
                                        <div class="text-xs text-gray-500">Customer</div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($ra['customer_name']); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-hashtag w-5 text-gray-400 mr-2 mt-0.5"></i>
                                    <div>
                                        <div class="text-xs text-gray-500">Order</div>
                                        <div class="font-medium text-gray-900">#<?php echo $ra['order_id']; ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $raIsActive ? '0' : '1'; ?>/1 item</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $raIsActive ? 'bg-red-400' : 'bg-green-500'; ?> h-2 rounded-full transition-all duration-300"
                                         style="width: <?php echo $raProgress; ?>%"></div>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 mb-4 pb-4 border-b border-gray-200">
                                <span>Assigned: <?php echo date('M j, Y g:i A', strtotime($ra['assigned_at'])); ?></span>
                            </div>

                            <a href="receiver_view_po_items_A.php?po_number=<?php echo urlencode($ra['po_number']); ?>&replacement_id=<?php echo $ra['id']; ?>"
                               class="block w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center font-medium">
                                <i class="fas fa-eye mr-2"></i>View & Receive Replacement
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterCards(filter) {
            const cards = document.querySelectorAll('.assignment-card');
            const buttons = document.querySelectorAll('.filter-btn');

            // Reset all buttons
            buttons.forEach(btn => {
                btn.classList.remove('bg-primary-100', 'text-primary-700', 'font-medium', 'active');
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
            });

            // Highlight clicked button
            event.target.classList.remove('text-gray-600', 'hover:bg-gray-100');
            event.target.classList.add('bg-primary-100', 'text-primary-700', 'font-medium', 'active');

            // Show/hide cards
            cards.forEach(card => {
                const type   = card.getAttribute('data-type');
                const status = card.getAttribute('data-status');

                let show = false;
                if (filter === 'all')         show = true;
                else if (filter === 'active')      show = (status === 'active');
                else if (filter === 'completed')   show = (status === 'completed');
                else if (filter === 'regular')     show = (type === 'regular');
                else if (filter === 'replacement') show = (type === 'replacement');

                card.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>