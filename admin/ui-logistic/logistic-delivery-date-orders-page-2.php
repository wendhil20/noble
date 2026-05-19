<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// ─── Date Validation ──────────────────────────────────────────────────────────
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

// ─── Fetch Orders for Selected Date ──────────────────────────────────────────
$ordersSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_status,
    ds.item_type,
    o.customer_name,
    o.delivery_type,
    o.email,
    o.mobile,
    o.address,
    o.status as order_status,
    o.final_total,
    o.delivery_fee,
    o.total_weight_kg,
    o.total_cubic_meters,
    o.assigned_vehicle_id,
    o.assigned_vehicle_type,
    tv.vehicle_type,
    tv.courier_name,
    db.id as booking_id,
    db.tracking_number,
    db.booking_status,
    db.courier_name as booking_courier_name,
    db.booking_reference,
    CASE 
        WHEN db.id IS NOT NULL THEN 'booked'
        WHEN ds.delivery_status = 'scheduled' THEN 'ready_for_booking'
        ELSE ds.delivery_status
    END as computed_status,
    (SELECT MIN(lt_from) FROM order_items WHERE order_id = o.id AND lt_from IS NOT NULL) as earliest_delivery,
    (SELECT MAX(lt_to)   FROM order_items WHERE order_id = o.id AND lt_to   IS NOT NULL) as latest_delivery
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON o.assigned_vehicle_id = tv.id
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date = ?
ORDER BY ds.delivery_time ASC, ds.order_id ASC";

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$stmt = $conn->prepare($ordersSql);
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    // Item counts
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) as total_items, IFNULL(SUM(quantity), 0) as total_quantity
         FROM order_items WHERE order_id = ?"
    );
    $countStmt->bind_param("i", $row['order_id']);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $row['total_items'] = $counts['total_items'] ?? 0;
    $row['total_quantity'] = $counts['total_quantity'] ?? 0;

    // Replacement data
    if ($row['item_type'] === 'replacement') {
        $repStmt = $conn->prepare(
            "SELECT reason, details, replacement_quantity, status
             FROM replacement_requests WHERE delivery_schedule_id = ? LIMIT 1"
        );
        $repStmt->bind_param("i", $row['delivery_id']);
        $repStmt->execute();
        $rep = $repStmt->get_result()->fetch_assoc();
        $repStmt->close();

        $row['replacement_reason'] = $rep['reason'] ?? null;
        $row['replacement_details'] = $rep['details'] ?? null;
        $row['replacement_quantity'] = $rep['replacement_quantity'] ?? null;
        $row['replacement_status'] = $rep['status'] ?? null;
    } else {
        $row['replacement_reason'] = $row['replacement_details'] =
            $row['replacement_quantity'] = $row['replacement_status'] = null;
    }

    // Search filter
    if (
        $searchQuery === '' ||
        strpos((string) $row['order_id'], $searchQuery) !== false ||
        stripos($row['customer_name'], $searchQuery) !== false ||
        stripos($row['tracking_number'] ?? '', $searchQuery) !== false
    ) {
        $orders[] = $row;
    }
}

$stmt->close();

// ─── Statistics ───────────────────────────────────────────────────────────────
$totalOrders = count($orders);
$deliveryOrders = count(array_filter($orders, fn($o) => $o['delivery_type'] === 'delivery'));
$pickupOrders = count(array_filter($orders, fn($o) => $o['delivery_type'] === 'pickup'));
$completedOrders = count(array_filter($orders, fn($o) => in_array($o['booking_status'], ['delivered', 'picked_up'])));
$pendingOrders = count(array_filter($orders, fn($o) => !in_array($o['booking_status'], ['delivered', 'picked_up'])));
$bookedOrders = count(array_filter($orders, fn($o) => $o['booking_id'] !== null));
$overdueOrders = count(array_filter($orders, fn($o) => $o['delivery_status'] === 'overdue'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries – <?= date('M d, Y', strtotime($selectedDate)) ?></title>
    <style>
        @keyframes slide-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease-out;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 py-6">

        <!-- ═══ HEADER ══════════════════════════════════════════════════════════ -->
        <div class="mb-5">
            <a href="<?= BASE_URL ?>/logistic"
                class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800 mb-3 transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back to Dashboard
            </a>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-calendar-day text-blue-600"></i>
                Deliveries for <?= date('l, F d, Y', strtotime($selectedDate)) ?>
            </h1>
        </div>

        <!-- ═══ SEARCH ═══════════════════════════════════════════════════════════ -->
        <div class="mb-5">
            <form method="GET" action="">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <div class="relative flex items-center">
                    <i class="fas fa-search absolute left-3.5 text-gray-400 text-sm pointer-events-none z-10"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Search by Order ID, Customer, or Tracking Number..." class="w-full pl-10 pr-28 py-2.5 bg-white border border-gray-300 rounded-lg text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                    <div class="absolute right-1.5 flex items-center gap-1">
                        <?php if ($searchQuery): ?>
                            <a href="?date=<?= $selectedDate ?>"
                                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition">
                                <i class="fas fa-times text-xs"></i>
                            </a>
                        <?php endif; ?>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-2 rounded-md transition flex items-center gap-1.5">
                            <i class="fas fa-search text-[10px]"></i> Search
                        </button>
                    </div>
                </div>
                <?php if ($searchQuery): ?>
                    <div class="flex items-center gap-2 mt-2">
                        <span
                            class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full">
                            <i class="fas fa-filter text-blue-400 text-[10px]"></i>
                            "<?= htmlspecialchars($searchQuery) ?>"
                        </span>
                        <span class="text-xs text-gray-400"><?= $totalOrders ?> result(s)</span>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ═══ FILTER TABS ══════════════════════════════════════════════════════ -->
        <div class="mb-5 flex border-b border-gray-200">
            <button onclick="filterOrders('all')" id="filter-all"
                class="filter-btn px-5 py-2.5 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 flex items-center gap-2">
                <i class="fas fa-list text-xs"></i> All
                <span
                    class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= $totalOrders ?></span>
            </button>
            <button onclick="filterOrders('delivery')" id="filter-delivery"
                class="filter-btn px-5 py-2.5 text-sm font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 flex items-center gap-2 transition-colors">
                <i class="fas fa-truck text-xs"></i> Delivery
                <span
                    class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= $deliveryOrders ?></span>
            </button>
            <button onclick="filterOrders('pickup')" id="filter-pickup"
                class="filter-btn px-5 py-2.5 text-sm font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 flex items-center gap-2 transition-colors">
                <i class="fas fa-hand-holding text-xs"></i> Pickup
                <span
                    class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= $pickupOrders ?></span>
            </button>
        </div>

        <!-- ═══ STAT CARDS ═══════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <?php
            $stats = [
                ['id' => 'stat-total', 'icon' => 'fa-shopping-cart', 'color' => 'text-blue-500', 'label' => 'Total', 'val' => $totalOrders],
                ['id' => 'stat-completed', 'icon' => 'fa-check-circle', 'color' => 'text-green-500', 'label' => 'Completed', 'val' => $completedOrders],
                ['id' => 'stat-booked', 'icon' => 'fa-book', 'color' => 'text-purple-500', 'label' => 'Booked', 'val' => $bookedOrders],
                ['id' => 'stat-pending', 'icon' => 'fa-clock', 'color' => 'text-yellow-500', 'label' => 'Pending', 'val' => $pendingOrders],
                ['id' => 'stat-overdue', 'icon' => 'fa-exclamation-triangle', 'color' => 'text-red-500', 'label' => 'Overdue', 'val' => $overdueOrders],
            ];
            foreach ($stats as $s): ?>
                <div
                    class="bg-white rounded-lg border border-gray-200 px-4 py-3 flex items-center justify-between hover:shadow-sm transition-shadow">
                    <div>
                        <p class="text-xs text-gray-500"><?= $s['label'] ?></p>
                        <p id="<?= $s['id'] ?>" class="text-2xl font-bold text-gray-900 leading-tight"><?= $s['val'] ?></p>
                    </div>
                    <i class="fas <?= $s['icon'] ?> <?= $s['color'] ?> text-xl"></i>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══ ORDERS TABLE ══════════════════════════════════════════════════════ -->
        <?php if (empty($orders)): ?>
            <div
                class="flex flex-col items-center justify-center bg-white rounded-xl border border-gray-200 py-20 text-center">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-calendar-times text-gray-400 text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No Deliveries Scheduled</h3>
                <p class="text-sm text-gray-400 mt-1">Nothing scheduled for this date.</p>
            </div>
        <?php else: ?>

            <div id="ordersTable" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Tracking</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Time</th>
                                <th class="px-4 py-3 text-left">ETA</th>
                                <th class="px-4 py-3 text-left">Items</th>
                                <th class="px-4 py-3 text-left">Wt / Vol</th>
                                <th class="px-4 py-3 text-left">Total</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($orders as $order):
                                $hasBooking = $order['booking_id'] !== null;
                                $isCompleted = in_array($order['booking_status'], ['delivered', 'picked_up']);
                                $canBook = !$hasBooking && in_array($order['computed_status'], ['ready_for_booking', 'scheduled']);
                                $isReplacement = $order['item_type'] === 'replacement';

                                // Status badge
                                if ($isCompleted) {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-check-circle"></i> Delivered</span>';
                                } elseif ($order['delivery_status'] === 'overdue') {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-exclamation-triangle"></i> Overdue</span>';
                                } elseif ($hasBooking) {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-shipping-fast"></i> Booked</span>';
                                } else {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-clock"></i> Scheduled</span>';
                                }

                                // Type badge
                                $typeBadge = $order['delivery_type'] === 'pickup'
                                    ? '<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-hand-holding"></i> Pickup</span>'
                                    : '<span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-truck"></i> Delivery</span>';

                                $bookingUrl = $isReplacement
                                    ? BASE_URL . "/logisticreplacementbook?schedule_id={$order['delivery_id']}&order_id={$order['order_id']}"
                                    : BASE_URL . "/logisticdeliverybook?schedule_id={$order['delivery_id']}&order_id={$order['order_id']}";
                                ?>
                                <tr class="order-row hover:bg-gray-50 transition-colors cursor-pointer"
                                    data-delivery-type="<?= $order['delivery_type'] ?>"
                                    onclick="openDetailsModal(<?= htmlspecialchars(json_encode($order)) ?>)">

                                    <!-- Order # -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 bg-blue-50 border border-blue-100 rounded-lg flex items-center justify-center shrink-0">
                                                <i class="fas fa-shopping-cart text-blue-500 text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900">#<?= $order['order_id'] ?></div>
                                                <?php if ($isReplacement): ?>
                                                    <span
                                                        class="text-[10px] bg-orange-50 text-orange-700 border border-orange-200 px-1.5 py-0.5 rounded font-medium">Replacement</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Tracking -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($order['tracking_number']): ?>
                                            <span
                                                class="inline-flex items-center gap-1 font-mono text-xs bg-purple-50 text-purple-800 border border-purple-200 px-2 py-1 rounded-lg">
                                                <i class="fas fa-barcode text-purple-400 text-[10px]"></i>
                                                <?= htmlspecialchars($order['tracking_number']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Not booked</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900 text-sm">
                                            <?= htmlspecialchars($order['customer_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($order['mobile']) ?></div>
                                    </td>

                                    <!-- Type -->
                                    <td class="px-4 py-3 whitespace-nowrap"><?= $typeBadge ?></td>

                                    <!-- Time -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                            class="font-semibold text-gray-800"><?= date('g:i A', strtotime($order['delivery_time'])) ?></span>
                                    </td>

                                    <!-- ETA -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($order['earliest_delivery'] && $order['latest_delivery']): ?>
                                            <div class="border-l-4 border-blue-400 bg-blue-50 rounded-r-lg px-2.5 py-1 text-xs">
                                                <span
                                                    class="text-gray-600"><?= date('M d', strtotime($order['earliest_delivery'])) ?></span>
                                                <span class="text-gray-400 mx-1">–</span>
                                                <span
                                                    class="text-blue-700 font-bold"><?= date('M d, Y', strtotime($order['latest_delivery'])) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Not set</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Items -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900"><?= $order['total_items'] ?> <span
                                                class="font-normal text-gray-400">items</span></div>
                                        <div class="text-xs text-gray-400"><?= $order['total_quantity'] ?> pcs</div>
                                    </td>

                                    <!-- Weight / Volume -->
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 space-y-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-weight text-orange-400 w-3 text-center"></i>
                                            <?= number_format($order['total_weight_kg'] ?? 0, 2) ?> kg
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-cube text-orange-400 w-3 text-center"></i>
                                            <?= number_format($order['total_cubic_meters'] ?? 0, 3) ?> m³
                                        </div>
                                    </td>

                                    <!-- Total -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                            class="font-bold text-green-600">₱<?= number_format($order['final_total'], 2) ?></span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3 whitespace-nowrap"><?= $statusBadge ?></td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1">

                                            <?php if ($canBook): ?>
                                                <a href="<?= $bookingUrl ?>" onclick="event.stopPropagation()"
                                                    title="Book <?= $isReplacement ? 'Replacement' : 'Delivery' ?>"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300 transition">
                                                    <i class="fas fa-calendar-check text-sm"></i>
                                                </a>
                                            <?php elseif ($hasBooking && !$isCompleted): ?>
                                                <a href="<?= BASE_URL ?>/logisticdeliverytrack?booking_id=<?= $order['booking_id'] ?>"
                                                    onclick="event.stopPropagation()" title="Manage Delivery"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-purple-600 hover:bg-purple-50 hover:border-purple-300 transition">
                                                    <i class="fas fa-tasks text-sm"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($isReplacement): ?>
                                                <a href="<?= BASE_URL ?>/logisticreplacementitemsview?schedule_id=<?= $order['delivery_id'] ?>&order_id=<?= $order['order_id'] ?>"
                                                    onclick="event.stopPropagation()" title="View Replacement Items"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-orange-600 hover:bg-orange-50 hover:border-orange-300 transition">
                                                    <i class="fas fa-sync-alt text-sm"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/logisticorderitemsview?order_id=<?= $order['order_id'] ?>"
                                                    onclick="event.stopPropagation()" title="View Items"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-green-600 hover:bg-green-50 hover:border-green-300 transition">
                                                    <i class="fas fa-list text-sm"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$isCompleted): ?>
                                                <button title="Reschedule" onclick="event.stopPropagation();
                                            openRescheduleModal(
                                                <?= $order['delivery_id'] ?>,
                                                <?= $order['order_id'] ?>,
                                                '<?= htmlspecialchars($order['customer_name'], ENT_QUOTES) ?>',
                                                '<?= $order['delivery_date'] ?>',
                                                '<?= date('H:i', strtotime($order['delivery_time'])) ?>'
                                            );"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-orange-600 hover:bg-orange-50 hover:border-orange-300 transition">
                                                    <i class="fas fa-calendar-alt text-sm"></i>
                                                </button>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- No filter results -->
            <div id="noResultsMessage"
                class="hidden flex-col items-center justify-center bg-white rounded-xl border border-gray-200 py-16 text-center mt-4">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-search text-gray-400 text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No Orders Match</h3>
                <p class="text-sm text-gray-400 mt-1">Try a different filter.</p>
            </div>

        <?php endif; ?>
    </div>

    <!-- ═══ ORDER DETAILS MODAL ══════════════════════════════════════════════════ -->
    <div id="detailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background-color:rgba(0,0,0,0.55);">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">

            <!-- Modal Header -->
            <div
                class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10 rounded-t-xl">
                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-600"></i>
                    Order Details
                </h3>
                <button onclick="closeDetailsModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6" id="modalContent"></div>
        </div>
    </div>

    <!-- ═══ RESCHEDULE MODAL ══════════════════════════════════════════════════════ -->
    <div id="rescheduleModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-orange-500"></i>
                    Reschedule Delivery
                </h3>
                <button onclick="closeRescheduleModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="rescheduleForm" class="px-6 py-5 space-y-4">
                <input type="hidden" id="reschedule_delivery_id" name="delivery_id">
                <input type="hidden" id="reschedule_order_id" name="order_id">

                <!-- Order info banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm">
                    <div class="font-semibold text-blue-900">Order #<span id="modal_order_id"></span></div>
                    <div class="text-blue-700 mt-0.5">Customer: <span id="modal_customer_name"></span></div>
                </div>

                <!-- New Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-calendar mr-1 text-gray-400"></i> New Date
                    </label>
                    <input type="date" id="new_delivery_date" name="new_delivery_date" required
                        min="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                              focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>

                <!-- New Time -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-clock mr-1 text-gray-400"></i> New Time
                    </label>
                    <input type="time" id="new_delivery_time" name="new_delivery_time" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                              focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>

                <!-- Reason -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-comment-alt mr-1 text-gray-400"></i>
                        Reason <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="reschedule_reason" name="reschedule_reason" rows="3"
                        placeholder="Why is this being rescheduled?"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm resize-none
                                 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
                </div>

                <!-- Error -->
                <div id="rescheduleError"
                    class="hidden flex items-center gap-2 bg-red-50 border border-red-300 text-red-700 text-sm rounded-lg px-3 py-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span id="rescheduleErrorMessage"></span>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeRescheduleModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm py-2.5 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-semibold text-sm py-2.5 rounded-lg transition">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const allOrders = <?= json_encode($orders) ?>;

        // ── Filter Tabs ───────────────────────────────────────────────────────────
        function filterOrders(type) {
            const rows = document.querySelectorAll('.order-row');
            const noResults = document.getElementById('noResultsMessage');
            const table = document.getElementById('ordersTable');
            let visible = 0;

            // Update tab styles
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('text-blue-600', 'border-blue-600');
                btn.classList.add('text-gray-500', 'border-transparent');
            });
            const activeBtn = document.getElementById(`filter-${type}`);
            activeBtn.classList.remove('text-gray-500', 'border-transparent');
            activeBtn.classList.add('text-blue-600', 'border-blue-600');

            // Show/hide rows
            rows.forEach(row => {
                const show = type === 'all' || row.dataset.deliveryType === type;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            // Toggle empty state
            if (table) {
                table.classList.toggle('hidden', visible === 0);
                noResults.classList.toggle('hidden', visible > 0);
                noResults.classList.toggle('flex', visible === 0);
            }

            updateStatistics(type);
        }

        function updateStatistics(type) {
            const f = type === 'all' ? allOrders : allOrders.filter(o => o.delivery_type === type);

            document.getElementById('stat-total').textContent = f.length;
            document.getElementById('stat-completed').textContent = f.filter(o => ['delivered', 'picked_up'].includes(o.booking_status)).length;
            document.getElementById('stat-booked').textContent = f.filter(o => o.booking_id !== null).length;
            document.getElementById('stat-pending').textContent = f.filter(o => !['delivered', 'picked_up'].includes(o.booking_status)).length;
            document.getElementById('stat-overdue').textContent = f.filter(o => o.delivery_status === 'overdue').length;
        }

        // ── Details Modal ─────────────────────────────────────────────────────────
        function openDetailsModal(order) {
            const hasBooking = order.booking_id !== null;

            const typeBadge = order.delivery_type === 'pickup'
                ? '<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-hand-holding"></i> Pickup</span>'
                : '<span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full text-xs font-semibold"><i class="fas fa-truck"></i> Delivery</span>';

            const bookingBlock = hasBooking ? `
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h4 class="font-semibold text-purple-900 mb-3 flex items-center gap-2 text-sm">
                    <i class="fas fa-shipping-fast text-purple-500"></i> Booking Info
                </h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${order.tracking_number ? `<div><div class="text-xs text-purple-600">Tracking</div><div class="font-mono font-bold text-purple-900 break-all">${order.tracking_number}</div></div>` : ''}
                    ${order.booking_courier_name ? `<div><div class="text-xs text-purple-600">Courier</div><div class="font-bold text-purple-900">${order.booking_courier_name}</div></div>` : ''}
                    ${order.booking_reference ? `<div><div class="text-xs text-purple-600">Reference</div><div class="font-bold text-purple-900">${order.booking_reference}</div></div>` : ''}
                    ${order.booking_status ? `<div><div class="text-xs text-purple-600">Status</div><div class="font-bold text-purple-900 capitalize">${order.booking_status.replace('_', ' ')}</div></div>` : ''}
                </div>
            </div>` : '';

            const etaBlock = (order.earliest_delivery && order.latest_delivery) ? `
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm">
                <div class="text-xs text-blue-600 font-semibold mb-1 flex items-center gap-1">
                    <i class="fas fa-calendar-check"></i> Expected Delivery Window
                </div>
                <div class="font-semibold text-blue-900">
                    ${new Date(order.earliest_delivery).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    <span class="text-gray-400 mx-1">–</span>
                    ${new Date(order.latest_delivery).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </div>
            </div>` : '';

            const replacementBlock = (order.item_type === 'replacement' && order.replacement_reason) ? `
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h4 class="font-semibold text-orange-900 mb-2 flex items-center gap-2 text-sm">
                    <i class="fas fa-exchange-alt text-orange-500"></i> Replacement Info
                </h4>
                <div class="space-y-1 text-sm">
                    <div><span class="font-semibold text-orange-700">Reason:</span> ${order.replacement_reason}</div>
                    ${order.replacement_details ? `<div><span class="font-semibold text-orange-700">Details:</span> ${order.replacement_details}</div>` : ''}
                    ${order.replacement_quantity ? `<div><span class="font-semibold text-orange-700">Qty:</span> ${order.replacement_quantity} pcs</div>` : ''}
                    ${order.replacement_status ? `<div><span class="font-semibold text-orange-700">Status:</span> <span class="capitalize">${order.replacement_status.replace('_', ' ')}</span></div>` : ''}
                </div>
            </div>` : '';

            document.getElementById('modalContent').innerHTML = `
            <div class="space-y-4">

                <!-- Customer -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2 text-sm">
                        <i class="fas fa-user text-blue-500"></i> Customer
                    </h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><div class="text-xs text-gray-500">Name</div><div class="font-semibold text-gray-900">${order.customer_name}</div></div>
                        <div><div class="text-xs text-gray-500">Mobile</div><div class="font-semibold text-gray-900">${order.mobile || 'N/A'}</div></div>
                        <div><div class="text-xs text-gray-500">Type</div><div class="mt-1">${typeBadge}</div></div>
                        <div class="col-span-2"><div class="text-xs text-gray-500">Address</div><div class="font-semibold text-gray-900">${order.address}</div></div>
                    </div>
                </div>

                ${etaBlock}
                ${bookingBlock}

                <!-- Order Details -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2 text-sm">
                        <i class="fas fa-box text-orange-500"></i>
                        ${order.item_type === 'replacement' ? 'Replacement' : 'Order'} Details
                        ${order.item_type === 'replacement' ? '<span class="text-[10px] bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">Replacement Only</span>' : ''}
                    </h4>
                    <div class="grid grid-cols-4 gap-3 text-center text-sm">
                        <div class="bg-white border border-gray-200 rounded-lg p-3">
                            <div class="text-xl font-bold text-blue-600">${order.total_items}</div>
                            <div class="text-xs text-gray-500">Items</div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-3">
                            <div class="text-xl font-bold text-green-600">${order.total_quantity}</div>
                            <div class="text-xs text-gray-500">Quantity</div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-3">
                            <div class="text-xl font-bold text-orange-600">${parseFloat(order.total_weight_kg || 0).toFixed(2)} kg</div>
                            <div class="text-xs text-gray-500">Weight</div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-3">
                            <div class="text-xl font-bold text-orange-600">${parseFloat(order.total_cubic_meters || 0).toFixed(3)} m³</div>
                            <div class="text-xs text-gray-500">Volume</div>
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2 text-sm">
                        <i class="fas fa-receipt text-green-500"></i> Payment
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-semibold">₱${parseFloat(order.final_total - (order.delivery_fee || 0)).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Delivery Fee</span>
                            <span class="font-semibold text-blue-600">₱${parseFloat(order.delivery_fee || 0).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between font-bold text-gray-900 pt-2 border-t border-green-200">
                            <span>Total</span>
                            <span class="text-green-600 text-base">₱${parseFloat(order.final_total).toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                ${order.delivery_notes ? `
                    <div class="flex items-start gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-sm">
                        <i class="fas fa-sticky-note text-yellow-500 mt-0.5 shrink-0"></i>
                        <div><span class="font-semibold text-yellow-800">Notes:</span> ${order.delivery_notes}</div>
                    </div>` : ''}

                ${replacementBlock}
            </div>`;

            document.getElementById('detailsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // ── Reschedule Modal ──────────────────────────────────────────────────────
        function openRescheduleModal(deliveryId, orderId, customerName, currentDate, currentTime) {
            document.getElementById('reschedule_delivery_id').value = deliveryId;
            document.getElementById('reschedule_order_id').value = orderId;
            document.getElementById('modal_order_id').textContent = orderId;
            document.getElementById('modal_customer_name').textContent = customerName;
            document.getElementById('new_delivery_date').value = currentDate;
            document.getElementById('new_delivery_time').value = currentTime;
            document.getElementById('rescheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.add('hidden');
            document.body.style.overflow = '';
            document.getElementById('rescheduleForm').reset();
            document.getElementById('rescheduleError').classList.add('hidden');
        }

        // Click-outside to close
        document.getElementById('detailsModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDetailsModal(); });
        document.getElementById('rescheduleModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeRescheduleModal(); });

        // Reschedule form submit
        document.getElementById('rescheduleForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const origText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Rescheduling...';
            document.getElementById('rescheduleError').classList.add('hidden');

            fetch('<?= BASE_URL ?>/logisticprocessreschedule', { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const toast = document.createElement('div');
                        toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-5 py-3 rounded-lg shadow-lg z-50 animate-slide-in flex items-center gap-3 text-sm';
                        toast.innerHTML = '<i class="fas fa-check-circle text-lg"></i><div><p class="font-semibold">Rescheduled!</p><p>Delivery has been updated.</p></div>';
                        document.body.appendChild(toast);
                        setTimeout(() => { closeRescheduleModal(); location.reload(); }, 1500);
                    } else {
                        document.getElementById('rescheduleErrorMessage').textContent = data.message || 'Failed to reschedule.';
                        document.getElementById('rescheduleError').classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origText;
                    }
                })
                .catch(() => {
                    document.getElementById('rescheduleErrorMessage').textContent = 'An error occurred. Please try again.';
                    document.getElementById('rescheduleError').classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                });
        });
    </script>

</body>

</html>