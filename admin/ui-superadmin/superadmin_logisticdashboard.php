<?php
//superadmin_logisticdashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get key delivery information with more details
$sql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    o.customer_name,
    o.address,
    o.mobile,
    o.email,
    o.delivery_type,
    o.final_total,
    o.delivery_fee,
    o.total_weight_kg,
    o.total_cubic_meters,
    o.warehouse_employee_id,
    o.sales_user_id,
    o.verified_by,
    db.courier_name,
    db.tracking_number,
    db.booking_status,
    db.booking_reference,
    db.vehicle_id,
    
    -- Handlers
    ws.fullname as warehouse_staff_name,
    accountant.fullname as accountant_name,
    receiver.fullname as receiver_name,
    doc_controller.fullname as doc_controller_name,
    dispatcher.fullname as dispatcher_name,
    
    CASE 
        WHEN db.booking_status IN ('delivered', 'picked_up') THEN 'completed'
        WHEN ds.delivery_date < CURDATE() AND db.booking_status NOT IN ('delivered', 'picked_up') THEN 'overdue'
        WHEN ds.delivery_date = CURDATE() THEN 'today'
        ELSE 'upcoming'
    END as delivery_status,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_quantity
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
LEFT JOIN nobleaccount ws ON o.warehouse_employee_id = ws.id
LEFT JOIN nobleaccount accountant ON o.verified_by = accountant.id
LEFT JOIN (
    SELECT pra1.order_id, pra1.receiver_id
    FROM po_receiver_assignments pra1
    WHERE pra1.id = (
        SELECT pra2.id 
        FROM po_receiver_assignments pra2 
        WHERE pra2.order_id = pra1.order_id 
        ORDER BY pra2.assigned_at DESC 
        LIMIT 1
    )
) latest_pra ON o.id = latest_pra.order_id
LEFT JOIN nobleaccount receiver ON latest_pra.receiver_id = receiver.id
LEFT JOIN (
    SELECT poa1.order_id, poa1.approved_by
    FROM po_attachments poa1
    WHERE poa1.id = (
        SELECT poa2.id 
        FROM po_attachments poa2 
        WHERE poa2.order_id = poa1.order_id 
        AND poa2.approved_by IS NOT NULL
        ORDER BY poa2.approved_at DESC 
        LIMIT 1
    )
) latest_po ON o.id = latest_po.order_id
LEFT JOIN nobleaccount doc_controller ON latest_po.approved_by = doc_controller.id
LEFT JOIN (
    SELECT db1.order_id, db1.dispatcher_id
    FROM delivery_bookings db1
    WHERE db1.id = (
        SELECT db2.id 
        FROM delivery_bookings db2 
        WHERE db2.order_id = db1.order_id 
        ORDER BY db2.created_at DESC 
        LIMIT 1
    )
) latest_booking ON o.id = latest_booking.order_id
LEFT JOIN nobleaccount dispatcher ON latest_booking.dispatcher_id = dispatcher.id
ORDER BY ds.delivery_date DESC, ds.delivery_time ASC";

$result = $conn->prepare($sql);
$result->execute();
$allDeliveries = $result->get_result()->fetch_all(MYSQLI_ASSOC);
$result->close();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$totalDeliveries = count($allDeliveries);
$totalPages = ceil($totalDeliveries / $perPage);
$offset = ($page - 1) * $perPage;
$deliveries = array_slice($allDeliveries, $offset, $perPage);

// Get summary stats
$statsSql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN db.booking_status IN ('delivered', 'picked_up') THEN 1 END) as completed,
    COUNT(CASE WHEN ds.delivery_date < CURDATE() AND db.booking_status NOT IN ('delivered', 'picked_up') THEN 1 END) as overdue,
    COUNT(CASE WHEN ds.delivery_date = CURDATE() AND db.booking_status NOT IN ('delivered', 'picked_up') THEN 1 END) as today
FROM delivery_schedules ds
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id";

$statsResult = $conn->prepare($statsSql);
$statsResult->execute();
$stats = $statsResult->get_result()->fetch_assoc();
$statsResult->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistic Dashboard - Noble Home</title>
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }

        body {
            overflow-x: hidden;
        }

        .delivery-card {
            overflow-x: auto;
        }

        .delivery-card::-webkit-scrollbar {
            height: 4px;
        }

        .delivery-card::-webkit-scrollbar-track {
            background: transparent;
        }

        .delivery-card::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .delivery-card::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Horizontal scroll wrapper */
        .scroll-container {
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .scroll-container::-webkit-scrollbar {
            height: 6px;
        }

        .scroll-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .scroll-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .scroll-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @media (max-width: 768px) {
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }

            .sidebar-overlay.hidden {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../navbar/top.php'; ?>

    <div class="flex min-h-screen bg-gray-100">

        <!-- Mobile Menu Toggle Button -->
        <button onclick="toggleSidebar()" class="lg:hidden fixed bottom-6 right-6 z-30 bg-teal-500 hover:bg-teal-600 text-white p-4 rounded-full shadow-lg transition-colors">
            <i class="fas fa-sliders-h text-lg"></i>
        </button>

        <!-- Sidebar Overlay (Mobile) -->
        <div id="sidebarOverlay" class="sidebar-overlay hidden lg:hidden" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <div id="sidebar" class="w-full sm:w-72  overflow-y-auto fixed lg:static inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out max-h-screen ">
            <div class="p-4 sm:p-6 flex items-center justify-between lg:block sticky top-0 ">
                <h2 class="text-lg font-bold text-gray-900 lg:hidden">Filters</h2>
                <button onclick="closeSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- Status Filter -->
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                <h3 class="text-gray-600 uppercase text-xs font-bold mb-4">Status Filter</h3>
                <div class="space-y-2">
                    <button onclick="filterByStatus('overdue')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-red-50 cursor-pointer transition-colors border border-transparent hover:border-red-200 text-left">
                        <div class="w-3 h-3 bg-red-500 rounded-full flex-shrink-0"></div>
                        <span class="text-gray-700 text-sm font-medium flex-1">Overdue</span>
                        <span class="text-xs font-bold text-red-700"><?php echo $stats['overdue']; ?></span>
                    </button>

                    <button onclick="filterByStatus('today')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-yellow-50 cursor-pointer transition-colors border border-transparent hover:border-yellow-200 text-left">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full flex-shrink-0"></div>
                        <span class="text-gray-700 text-sm font-medium flex-1">Today</span>
                        <span class="text-xs font-bold text-yellow-700"><?php echo $stats['today']; ?></span>
                    </button>

                    <button onclick="filterByStatus('upcoming')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-50 cursor-pointer transition-colors border border-transparent hover:border-blue-200 text-left">
                        <div class="w-3 h-3 bg-blue-500 rounded-full flex-shrink-0"></div>
                        <span class="text-gray-700 text-sm font-medium flex-1">Upcoming</span>
                        <span class="text-xs font-bold text-blue-700"><?php echo ($stats['total'] - $stats['completed'] - $stats['overdue'] - $stats['today']); ?></span>
                    </button>

                    <button onclick="filterByStatus('completed')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-green-50 cursor-pointer transition-colors border border-transparent hover:border-green-200 text-left">
                        <div class="w-3 h-3 bg-green-500 rounded-full flex-shrink-0"></div>
                        <span class="text-gray-700 text-sm font-medium flex-1">Completed</span>
                        <span class="text-xs font-bold text-green-700"><?php echo $stats['completed']; ?></span>
                    </button>

                    <button onclick="filterByStatus('all')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors border border-transparent hover:border-gray-200 text-left mt-2 pt-2 border-t border-gray-200">
                        <div class="w-3 h-3 bg-gray-500 rounded-full flex-shrink-0"></div>
                        <span class="text-gray-700 text-sm font-medium flex-1">All Deliveries</span>
                        <span class="text-xs font-bold text-gray-700"><?php echo $stats['total']; ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto w-full">
            <div class="p-4 sm:p-6 lg:p-8">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Deliveries</h1>
                        <p class="text-gray-600 text-xs sm:text-sm mt-1">Manage and track all deliveries</p>
                    </div>
                    <button onclick="toggleSortMenu()" class="hidden sm:flex px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 items-center justify-center gap-2 transition-colors text-sm">
                        <i class="fas fa-sort"></i> Sort
                    </button>
                </div>

                <!-- Sort Menu -->
                <div id="sortMenu" class="hidden absolute right-4 sm:right-8 top-40 sm:top-48 bg-white border border-gray-300 rounded-lg shadow-lg z-50 w-48">
                    <button onclick="sortDeliveries('newest')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                        <i class="fas fa-arrow-down text-teal-500 mr-2"></i> Newest First
                    </button>
                    <button onclick="sortDeliveries('oldest')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                        <i class="fas fa-arrow-up text-teal-500 mr-2"></i> Oldest First
                    </button>
                    <button onclick="sortDeliveries('highest')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                        <i class="fas fa-arrow-down-9-1 text-teal-500 mr-2"></i> Highest Amount
                    </button>
                    <button onclick="sortDeliveries('lowest')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-arrow-up-1-9 text-teal-500 mr-2"></i> Lowest Amount
                    </button>
                </div>

                <!-- Deliveries List -->
                <div id="deliveriesList" class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto scroll-container pr-2">
                    <?php foreach ($deliveries as $delivery):
                        $statusColor = [
                            'completed' => 'bg-green-100 text-green-800',
                            'overdue' => 'bg-red-100 text-red-800',
                            'today' => 'bg-yellow-100 text-yellow-800',
                            'upcoming' => 'bg-blue-100 text-blue-800'
                        ][$delivery['delivery_status']] ?? 'bg-gray-100 text-gray-800';

                        $statusText = [
                            'completed' => 'Completed',
                            'overdue' => 'Overdue',
                            'today' => 'Today',
                            'upcoming' => 'Upcoming'
                        ][$delivery['delivery_status']] ?? 'Unknown';
                    ?>
                        <div class="delivery-card bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow" data-status="<?php echo $delivery['delivery_status']; ?>" data-amount="<?php echo $delivery['final_total']; ?>" data-date="<?php echo $delivery['delivery_date']; ?>">
                     <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-3">
  <!-- Left: Order Info -->
  <div class="flex-1 min-w-0">
    <h3 class="font-bold text-gray-900 text-sm sm:text-base">
      Order #<?php echo $delivery['order_id']; ?>
    </h3>
    <p class="text-xs text-gray-500">
      <?php echo date('M d, Y · g:i A', strtotime($delivery['delivery_date'] . ' ' . $delivery['delivery_time'])); ?>
    </p>
  </div>

  <!-- Middle: Customer Details -->
  <div class="flex-1 min-w-0">
    <p class="font-medium text-gray-900 text-sm mb-1">
      <?php echo htmlspecialchars($delivery['customer_name']); ?>
    </p>
    <div class="space-y-0.5 text-xs text-gray-600">
      <p class="flex items-center gap-2" title="<?php echo htmlspecialchars($delivery['address']); ?>">
        <i class="fas fa-map-marker-alt w-3 text-gray-400 flex-shrink-0"></i>
        <span class="truncate"><?php echo htmlspecialchars(substr($delivery['address'], 0, 55)); ?></span>
      </p>
      <p class="flex items-center gap-2">
        <i class="fas fa-phone w-3 text-gray-400 flex-shrink-0"></i>
        <span><?php echo $delivery['mobile'] ?? '-'; ?></span>
      </p>
    </div>
  </div>

  <!-- Right: Status Badges -->
  <div class="flex gap-1 flex-shrink-0">
    <span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded whitespace-nowrap">
      <?php echo ucfirst($delivery['delivery_type']); ?>
    </span>
    <span class="<?php echo $statusColor; ?> text-xs font-bold px-2 py-1 rounded whitespace-nowrap">
      <?php echo $statusText; ?>
    </span>
  </div>
</div>

                      

                            <!-- Handler Tags -->
                            <?php if (!empty($delivery['accountant_name']) || !empty($delivery['receiver_name']) || !empty($delivery['warehouse_staff_name']) || !empty($delivery['doc_controller_name']) || !empty($delivery['dispatcher_name'])): ?>
                                <div class="mb-3 pb-3 border-b border-gray-100">
                                    <p class="text-xs text-gray-500 font-semibold mb-2">Verified:</p>
                                    <div class="flex flex-wrap gap-1">
                                        <?php if (!empty($delivery['warehouse_staff_name'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-800">
                                                <i class="fas fa-warehouse flex-shrink-0"></i>
                                                <span class="truncate"><?php echo htmlspecialchars(substr($delivery['warehouse_staff_name'], 0, 10)); ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['receiver_name'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-purple-100 text-purple-800">
                                                <i class="fas fa-boxes flex-shrink-0"></i>
                                                <span class="truncate"><?php echo htmlspecialchars(substr($delivery['receiver_name'], 0, 10)); ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['dispatcher_name'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-orange-100 text-orange-800">
                                                <i class="fas fa-truck flex-shrink-0"></i>
                                                <span class="truncate"><?php echo htmlspecialchars(substr($delivery['dispatcher_name'], 0, 10)); ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['accountant_name'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                                <i class="fas fa-calculator flex-shrink-0"></i>
                                                <span class="truncate"><?php echo htmlspecialchars(substr($delivery['accountant_name'], 0, 10)); ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['doc_controller_name'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-cyan-100 text-cyan-800">
                                                <i class="fas fa-file flex-shrink-0"></i>
                                                <span class="truncate"><?php echo htmlspecialchars(substr($delivery['doc_controller_name'], 0, 10)); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3 pb-3 border-b border-gray-100">
                                <div class="text-center">
                                    <div class="text-gray-500 text-xs mb-1">Items</div>
                                    <div class="font-bold text-gray-900 text-sm"><?php echo $delivery['total_items']; ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-gray-500 text-xs mb-1">Qty</div>
                                    <div class="font-bold text-gray-900 text-sm"><?php echo $delivery['total_quantity'] ?? 0; ?></div>
                                </div>
                                <div class="text-center hidden sm:block">
                                    <div class="text-gray-500 text-xs mb-1">Weight</div>
                                    <div class="font-bold text-gray-900 text-xs"><?php echo number_format($delivery['total_weight_kg'] ?? 0, 1); ?> kg</div>
                                </div>
                                <div class="text-center hidden sm:block">
                                    <div class="text-gray-500 text-xs mb-1">Volume</div>
                                    <div class="font-bold text-gray-900 text-xs"><?php echo number_format($delivery['total_cubic_meters'] ?? 0, 2); ?> m³</div>
                                </div>
                            </div>

                            <!-- Courier & Amount Footer -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="text-xs text-gray-600">
                                    <span class="font-semibold">Courier:</span> <?php echo htmlspecialchars(substr($delivery['courier_name'] ?? 'Pending', 0, 20)); ?>
                                    <?php if ($delivery['tracking_number']): ?>
                                        <br><span class="font-semibold">Track:</span> <span class="font-mono text-xs"><?php echo htmlspecialchars(substr($delivery['tracking_number'], 0, 14)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <div class="text-gray-500 text-xs">Total Amount</div>
                                    <div class="font-bold text-teal-600 text-lg">₱<?php echo number_format($delivery['final_total'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs sm:text-sm">
                    <p class="text-gray-600">
                        Showing <?php echo (($page - 1) * $perPage) + 1; ?>-<?php echo min($page * $perPage, $totalDeliveries); ?> from <?php echo $totalDeliveries; ?> deliveries
                    </p>
                    <div class="flex gap-1 sm:gap-2 flex-wrap justify-center">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="px-2 sm:px-3 py-2 border border-gray-300 rounded hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <button class="px-3 sm:px-4 py-2 bg-teal-500 text-white rounded font-bold">
                                    <?php echo $i; ?>
                                </button>
                            <?php elseif ($i <= 3 || $i >= $totalPages - 1 || abs($i - $page) <= 1): ?>
                                <a href="?page=<?php echo $i; ?>" class="px-3 sm:px-4 py-2 border border-gray-300 rounded hover:bg-gray-50 font-semibold text-gray-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="px-2 sm:px-3 py-2 border border-gray-300 rounded hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allDeliveries = <?php echo json_encode($allDeliveries); ?>;
        let currentFilter = 'all';

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }

        function toggleSortMenu() {
            const menu = document.getElementById('sortMenu');
            menu.classList.toggle('hidden');
        }

        function sortDeliveries(type) {
            let sorted = [...allDeliveries];

            if (type === 'newest') {
                sorted.sort((a, b) => new Date(b.delivery_date) - new Date(a.delivery_date));
            } else if (type === 'oldest') {
                sorted.sort((a, b) => new Date(a.delivery_date) - new Date(b.delivery_date));
            } else if (type === 'highest') {
                sorted.sort((a, b) => parseFloat(b.final_total) - parseFloat(a.final_total));
            } else if (type === 'lowest') {
                sorted.sort((a, b) => parseFloat(a.final_total) - parseFloat(b.final_total));
            }

            updateDeliveriesDisplay(sorted);
            document.getElementById('sortMenu').classList.add('hidden');
        }

        function filterByStatus(status) {
            currentFilter = status;
            closeSidebar();

            if (status === 'all') {
                updateDeliveriesDisplay(allDeliveries);
            } else {
                let filtered = allDeliveries.filter(d => d.delivery_status === status);
                updateDeliveriesDisplay(filtered);
            }
        }

        function updateDeliveriesDisplay(deliveries) {
            const container = document.getElementById('deliveriesList');

            if (deliveries.length === 0) {
                container.innerHTML = '<div class="p-8 text-center"><i class="fas fa-inbox text-gray-300 text-5xl mb-3 block"></i><p class="text-gray-600 text-sm">No deliveries found</p></div>';
                return;
            }

            container.innerHTML = deliveries.slice(0, 10).map(d => {
                const statusColors = {
                    'completed': 'bg-green-100 text-green-800',
                    'overdue': 'bg-red-100 text-red-800',
                    'today': 'bg-yellow-100 text-yellow-800',
                    'upcoming': 'bg-blue-100 text-blue-800'
                };

                const statusTexts = {
                    'completed': 'Completed',
                    'overdue': 'Overdue',
                    'today': 'Today',
                    'upcoming': 'Upcoming'
                };

                const statusColor = statusColors[d.delivery_status] || 'bg-gray-100 text-gray-800';
                const statusText = statusTexts[d.delivery_status] || 'Unknown';
                const deliveryDateTime = new Date(d.delivery_date + ' ' + d.delivery_time);
                const dateString = deliveryDateTime.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                const timeString = deliveryDateTime.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                let handlerTags = '';
                if (d.warehouse_staff_name) handlerTags += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-800"><i class="fas fa-warehouse flex-shrink-0"></i><span class="truncate">${d.warehouse_staff_name.substring(0, 10)}</span></span>`;
                if (d.receiver_name) handlerTags += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-purple-100 text-purple-800"><i class="fas fa-boxes flex-shrink-0"></i><span class="truncate">${d.receiver_name.substring(0, 10)}</span></span>`;
                if (d.dispatcher_name) handlerTags += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-orange-100 text-orange-800"><i class="fas fa-truck flex-shrink-0"></i><span class="truncate">${d.dispatcher_name.substring(0, 10)}</span></span>`;
                if (d.accountant_name) handlerTags += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-blue-100 text-blue-800"><i class="fas fa-calculator flex-shrink-0"></i><span class="truncate">${d.accountant_name.substring(0, 10)}</span></span>`;
                if (d.doc_controller_name) handlerTags += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-cyan-100 text-cyan-800"><i class="fas fa-file flex-shrink-0"></i><span class="truncate">${d.doc_controller_name.substring(0, 10)}</span></span>`;

                const handlerSection = handlerTags ? `<div class="mb-3 pb-3 border-b border-gray-100"><p class="text-xs text-gray-500 font-semibold mb-2">Assigned To:</p><div class="flex flex-wrap gap-1">${handlerTags}</div></div>` : '';

                return `
        <div class="delivery-card bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow" data-status="${d.delivery_status}" data-amount="${d.final_total}" data-date="${d.delivery_date}">
            <div class="flex items-start justify-between mb-3 gap-2">
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-900 text-sm sm:text-base">Order #${d.order_id}</h3>
                    <p class="text-xs text-gray-500">${dateString} · ${timeString}</p>
                </div>
                <div class="flex gap-1 flex-shrink-0">
                    <span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded">${d.delivery_type.charAt(0).toUpperCase() + d.delivery_type.slice(1)}</span>
                    <span class="text-xs font-bold px-2 py-1 rounded ${statusColor}">${statusText}</span>
                </div>
            </div>

            <div class="mb-3 pb-3 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900 mb-1">${d.customer_name}</p>
                <div class="space-y-1 text-xs text-gray-600">
                    <p title="${d.address}"><i class="fas fa-map-marker-alt w-4 text-gray-400"></i>${d.address.substring(0, 55)}</p>
                    <p><i class="fas fa-phone w-4 text-gray-400"></i>${d.mobile || '-'}</p>
                </div>
            </div>

            ${handlerSection}

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3 pb-3 border-b border-gray-100">
                <div class="text-center">
                    <div class="text-gray-500 text-xs mb-1">Items</div>
                    <div class="font-bold text-gray-900 text-sm">${d.total_items}</div>
                </div>
                <div class="text-center">
                    <div class="text-gray-500 text-xs mb-1">Qty</div>
                    <div class="font-bold text-gray-900 text-sm">${d.total_quantity || 0}</div>
                </div>
                <div class="text-center hidden sm:block">
                    <div class="text-gray-500 text-xs mb-1">Weight</div>
                    <div class="font-bold text-gray-900 text-xs">${parseFloat(d.total_weight_kg || 0).toFixed(1)} kg</div>
                </div>
                <div class="text-center hidden sm:block">
                    <div class="text-gray-500 text-xs mb-1">Volume</div>
                    <div class="font-bold text-gray-900 text-xs">${parseFloat(d.total_cubic_meters || 0).toFixed(2)} m³</div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="text-xs text-gray-600">
                    <span class="font-semibold">Courier:</span> ${(d.courier_name || 'Pending').substring(0, 20)}
                    ${d.tracking_number ? `<br><span class="font-semibold">Track:</span> <span class="font-mono text-xs">${d.tracking_number.substring(0, 14)}</span>` : ''}
                </div>
                <div class="text-right">
                    <div class="text-gray-500 text-xs">Total Amount</div>
                    <div class="font-bold text-teal-600 text-lg">₱${parseFloat(d.final_total).toFixed(2)}</div>
                </div>
            </div>
        </div>
        `;
            }).join('');
        }

        // Close sort menu when clicking outside
        document.addEventListener('click', function(e) {
            const sortMenu = document.getElementById('sortMenu');
            const sortBtn = document.querySelector('[onclick="toggleSortMenu()"]');
            if (sortBtn && !sortMenu.contains(e.target) && e.target !== sortBtn && !sortBtn.contains(e.target)) {
                sortMenu.classList.add('hidden');
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.querySelector('[onclick="toggleSidebar()"]');

            if (toggleBtn && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    closeSidebar();
                }
            }
        });
    </script>
</body>

</html>