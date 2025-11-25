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

// Get assigned P.O.s for this receiver
$assignmentsSql = "SELECT 
                    pra.*,
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

// Count active assignments
$activeCount = 0;
$completedCount = 0;
foreach ($assignments as $assignment) {
    if ($assignment['status'] == 'active') {
        $activeCount++;
    } else {
        $completedCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My P.O. Assignments</title>
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
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-xl shadow-lg">
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
                            <?php echo $activeCount; ?> Active
                        </span>
                    </div>
                    <div class="bg-green-50 border border-green-200 px-4 py-2 rounded-lg">
                        <span class="text-green-700 font-medium">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo $completedCount; ?> Completed
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if (empty($assignments)): ?>
            <!-- No Assignments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No P.O. Assignments</h3>
                <p class="text-sm text-gray-500">You don't have any P.O. assignments yet.</p>
            </div>
        <?php else: ?>
            <!-- Filter Tabs -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex space-x-2">
                    <button onclick="filterAssignments('all')" 
                            class="filter-btn active px-4 py-2 rounded-lg transition-colors duration-200">
                        All (<?php echo count($assignments); ?>)
                    </button>
                    <button onclick="filterAssignments('active')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200">
                        Active (<?php echo $activeCount; ?>)
                    </button>
                    <button onclick="filterAssignments('completed')" 
                            class="filter-btn px-4 py-2 rounded-lg transition-colors duration-200">
                        Completed (<?php echo $completedCount; ?>)
                    </button>
                </div>
            </div>

            <!-- P.O. Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-200"
                         data-status="<?php echo $assignment['status']; ?>">
                        
                        <!-- Status Badge Header -->
                        <div class="<?php echo $assignment['status'] == 'active' ? 'bg-gradient-to-r from-purple-500 to-purple-600' : 'bg-gradient-to-r from-green-500 to-green-600'; ?> p-4">
                            <div class="flex items-center justify-between text-white">
                                <span class="font-semibold">
                                    <?php echo $assignment['status'] == 'active' ? 'Active' : 'Completed'; ?>
                                </span>
                                <?php if ($assignment['status'] == 'completed'): ?>
                                    <span class="text-xs">
                                        <?php echo date('M j, Y', strtotime($assignment['completed_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Card Content -->
                        <div class="p-5">
                            <!-- P.O. Number -->
                            <div class="mb-4">
                                <div class="text-xs text-gray-500 mb-1">P.O. Number</div>
                                <div class="font-mono font-bold text-lg text-gray-900">
                                    <?php echo htmlspecialchars($assignment['po_number']); ?>
                                </div>
                            </div>
                            
                            <!-- Supplier & Customer -->
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
                            
                            <!-- Progress Bar -->
                            <?php 
                            $progress = 0;
                            if ($assignment['total_items'] > 0) {
                                $receivedItems = $assignment['total_items'] - $assignment['pending_items'];
                                $progress = ($receivedItems / $assignment['total_items']) * 100;
                            }
                            ?>
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
                            
                            <!-- Assignment Info -->
                            <div class="text-xs text-gray-500 mb-4 pb-4 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <span>Assigned by <?php echo htmlspecialchars($assignment['assigned_by_name']); ?></span>
                                </div>
                                <div class="mt-1">
                                    <?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <a href="view_po_items.php?po_number=<?php echo urlencode($assignment['po_number']); ?>&assignment_id=<?php echo $assignment['id']; ?>" 
                               class="block w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center font-medium">
                                <i class="fas fa-eye mr-2"></i>
                                View Items & Receive
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterAssignments(status) {
            const cards = document.querySelectorAll('.assignment-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update button states
            buttons.forEach(btn => {
                btn.classList.remove('active', 'bg-primary-100', 'text-primary-700', 'font-medium');
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            event.target.classList.remove('text-gray-600', 'hover:bg-gray-100');
            event.target.classList.add('active', 'bg-primary-100', 'text-primary-700', 'font-medium');
            
            // Filter cards
            cards.forEach(card => {
                const cardStatus = card.getAttribute('data-status');
                if (status === 'all' || cardStatus === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Set initial active state
        document.querySelector('.filter-btn.active').classList.add('bg-primary-100', 'text-primary-700', 'font-medium');
    </script>
</body>
</html>