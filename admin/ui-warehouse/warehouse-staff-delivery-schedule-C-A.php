<?php
// warehouse_staff_delivery_schedule_C-A.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// ─── URL Parameters ─────────────────────────────────────────────────────────
$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$schedule_all = isset($_GET['schedule_all']) && $_GET['schedule_all'] === 'true';
$schedule_replacements = isset($_GET['schedule_replacements']) && $_GET['schedule_replacements'] === 'true';

if ($order_id <= 0 || (!$schedule_all && !$schedule_replacements)) {
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

// ─── Fetch Order ─────────────────────────────────────────────────────────────
$orderStmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

// ─── Fetch Items ──────────────────────────────────────────────────────────────
if ($schedule_replacements) {
    $itemsSql = "
        SELECT oi.id, oi.product_name,
               rr.replacement_quantity AS quantity,
               oi.price, oi.origin,
               rr.status AS tracking_status,
               'replacement' AS item_type,
               rr.reason AS replacement_reason,
               oi.lt_from, oi.lt_to
        FROM replacement_requests rr
        JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.order_id = ? AND rr.status = 'In Warehouse'
        ORDER BY product_name
    ";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $order_id);
} else {
    $itemsSql = "
        SELECT oi.id, oi.product_name, oi.quantity, oi.price, oi.origin,
               oi.tracking_status, 'original' AS item_type,
               NULL AS replacement_reason, oi.lt_from, oi.lt_to
        FROM order_items oi
        WHERE oi.order_id = ? AND oi.tracking_status = 'In Warehouse'

        UNION ALL

        SELECT oi.id, oi.product_name,
               rr.replacement_quantity AS quantity,
               oi.price, oi.origin,
               rr.status AS tracking_status,
               'replacement' AS item_type,
               rr.reason AS replacement_reason,
               oi.lt_from, oi.lt_to
        FROM replacement_requests rr
        JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.order_id = ? AND rr.status = 'In Warehouse'

        ORDER BY item_type, product_name
    ";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("ii", $order_id, $order_id);
}
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

if (empty($items)) {
    header("Location: warehouse_staff_order_tracking_C.php?order_id=$order_id");
    exit();
}

// ─── Lead Time Range ─────────────────────────────────────────────────────────
$latestLtFrom = null;
$latestLtTo = null;

foreach ($items as $item) {
    if (!empty($item['lt_from']) && !empty($item['lt_to'])) {
        $from = new DateTime($item['lt_from']);
        $to = new DateTime($item['lt_to']);
        if ($latestLtFrom === null || $from > $latestLtFrom)
            $latestLtFrom = $from;
        if ($latestLtTo === null || $to > $latestLtTo)
            $latestLtTo = $to;
    }
}

$expectedDelivery = ($latestLtFrom && $latestLtTo)
    ? $latestLtFrom->format('M j, Y') . ' – ' . $latestLtTo->format('M j, Y')
    : null;

// ─── Summary Counts ───────────────────────────────────────────────────────────
$totalItems = count($items);
$totalQuantity = array_sum(array_column($items, 'quantity'));
$hasReplacements = !empty(array_filter($items, fn($i) => $i['item_type'] === 'replacement'));

// ─── Handle Form Submission ───────────────────────────────────────────────────
$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_delivery'])) {
    $delivery_date = $_POST['delivery_date'];
    $delivery_time = $_POST['delivery_time'];
    $delivery_notes = $_POST['delivery_notes'] ?? '';
    $item_type_for_schedule = $schedule_replacements ? 'replacement' : 'original';

    try {
        $conn->begin_transaction();

        // Insert one delivery_schedules row for the whole order
        $scheduleStmt = $conn->prepare(
            "INSERT INTO delivery_schedules
             (order_id, delivery_date, delivery_time, delivery_notes, item_type, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $created_by = $_SESSION['noble_user'];
        $scheduleStmt->bind_param(
            "isssss",
            $order_id,
            $delivery_date,
            $delivery_time,
            $delivery_notes,
            $item_type_for_schedule,
            $created_by
        );
        $scheduleStmt->execute();
        $delivery_schedule_id = $conn->insert_id;
        $scheduleStmt->close();

        $affectedOriginal = 0;
        $affectedReplacement = 0;

        if ($schedule_replacements) {
            $upd = $conn->prepare(
                "UPDATE replacement_requests
                 SET status = 'scheduled', delivery_schedule_id = ?
                 WHERE order_id = ? AND status = 'In Warehouse'"
            );
            $upd->bind_param("ii", $delivery_schedule_id, $order_id);
            $upd->execute();
            $affectedReplacement = $upd->affected_rows;
            $upd->close();
        } else {
            $upd = $conn->prepare(
                "UPDATE order_items
                 SET tracking_status = 'scheduled'
                 WHERE order_id = ? AND tracking_status = 'In Warehouse'"
            );
            $upd->bind_param("i", $order_id);
            $upd->execute();
            $affectedOriginal = $upd->affected_rows;
            $upd->close();
        }

        $conn->commit();

        $totalUpdated = $affectedOriginal + $affectedReplacement;
        $success_message = "Delivery scheduled! {$totalUpdated} item(s) marked as 'Scheduled'.";

        echo "<script>
            setTimeout(() => {
                window.location.href = '" . BASE_URL . "/warehousestafftrackorder?order_id=$order_id';
            }, 2000);
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to schedule delivery: " . $e->getMessage();
    }
}

// ─── Calendar Data (next 60 days) ────────────────────────────────────────────
$calStmt = $conn->prepare(
    "SELECT DATE(delivery_date) AS date, COUNT(*) AS count
     FROM delivery_schedules
     WHERE delivery_date >= CURDATE()
       AND delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     GROUP BY DATE(delivery_date)"
);
$calStmt->execute();
$calendarRaw = $calStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$calStmt->close();

$deliveryCountsByDate = array_column($calendarRaw, 'count', 'date');

// ─── Scheduled Deliveries (next 30 days) ─────────────────────────────────────
$schStmt = $conn->prepare(
    "SELECT ds.*,
            o.customer_name, o.address, o.id AS order_id,
            COUNT(DISTINCT oi.id)          AS original_count,
            SUM(oi.quantity)               AS original_quantity,
            COUNT(DISTINCT rr.id)          AS replacement_count,
            SUM(rr.replacement_quantity)   AS replacement_quantity
     FROM delivery_schedules ds
     JOIN orders o       ON ds.order_id = o.id
     LEFT JOIN order_items oi
           ON oi.order_id = ds.order_id
          AND oi.tracking_status IN ('scheduled','ready_for_pickup','out_for_delivery')
     LEFT JOIN replacement_requests rr ON rr.delivery_schedule_id = ds.id
     WHERE ds.delivery_date >= CURDATE()
       AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND ds.item_type IN ('original','replacement')
     GROUP BY ds.id, ds.order_id, ds.delivery_date, ds.delivery_time,
              ds.delivery_notes, o.customer_name, o.address, ds.item_type
     ORDER BY ds.delivery_date, ds.delivery_time"
);
$schStmt->execute();
$schedules = $schStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$schStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Delivery – Order #<?= $order_id ?></title>
</head>

<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- ═══════════════════════════════════════════════════════ PAGE HEADER -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5">
            <div class="flex flex-wrap items-center justify-between gap-4">

                <!-- Left: Back + Title -->
                <div class="flex items-center gap-3">
                    <a href="warehouse_staff_order_tracking_C.php?order_id=<?= $order_id ?>"
                        class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-calendar-check text-green-600"></i>
                            <?= $schedule_replacements ? 'Schedule Replacement Delivery' : 'Schedule Order Delivery' ?>
                        </h1>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Order #<?= $order_id ?> &nbsp;·&nbsp;
                            <?= htmlspecialchars($order['customer_name']) ?>
                            <?php if ($hasReplacements): ?>
                                <span
                                    class="ml-2 inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <i class="fas fa-sync-alt text-[10px]"></i> Has Replacements
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Right: Quick Stats -->
                <div class="flex flex-wrap gap-2 text-sm">
                    <span
                        class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-800 border border-blue-200 px-3 py-1.5 rounded-lg font-medium">
                        <i class="fas fa-boxes text-blue-500"></i>
                        <?= $totalItems ?> Items
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 bg-green-50 text-green-800 border border-green-200 px-3 py-1.5 rounded-lg font-medium">
                        <i class="fas fa-cubes text-green-500"></i>
                        <?= $totalQuantity ?> Qty
                    </span>
                    <?php if ($expectedDelivery): ?>
                        <span
                            class="inline-flex items-center gap-1.5 bg-orange-50 text-orange-800 border border-orange-200 px-3 py-1.5 rounded-lg font-medium">
                            <i class="fas fa-clock text-orange-500"></i>
                            ETA: <?= $expectedDelivery ?>
                        </span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ ALERTS -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <?php if ($success_message): ?>
            <div class="flex items-center gap-3 bg-green-50 border border-green-300 text-green-800 rounded-lg px-4 py-3">
                <i class="fas fa-check-circle text-green-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="flex items-center gap-3 bg-red-50 border border-red-300 text-red-800 rounded-lg px-4 py-3">
                <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════ MAIN GRID -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- ── COL 1: Items + Form ───────────────────────────────────── -->
        <div class="xl:col-span-1 flex flex-col gap-6">

            <!-- Items List -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-boxes text-green-600"></i>
                    <h2 class="font-semibold text-gray-800">Items to Schedule
                        <span class="ml-1 text-gray-400 font-normal">(<?= $totalItems ?>)</span>
                    </h2>
                </div>

                <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    <?php foreach ($items as $item): ?>
                        <?php $isReplacement = $item['item_type'] === 'replacement'; ?>
                        <div
                            class="px-5 py-3 <?= $isReplacement ? 'bg-red-50 border-l-4 border-red-400' : 'hover:bg-gray-50' ?>">

                            <?php if ($isReplacement): ?>
                                <div class="flex items-center gap-1 mb-1">
                                    <span
                                        class="text-[10px] font-bold tracking-wide bg-red-500 text-white px-1.5 py-0.5 rounded uppercase">
                                        Replacement
                                    </span>
                                    <?php if ($item['replacement_reason']): ?>
                                        <span class="text-xs text-red-600">–
                                            <?= htmlspecialchars(ucfirst($item['replacement_reason'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-gray-800 leading-tight">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </span>
                                <div class="text-right ml-3 shrink-0">
                                    <span class="text-xs text-gray-500">Qty:
                                        <strong><?= $item['quantity'] ?></strong></span><br>
                                    <span
                                        class="text-xs text-gray-400">₱<?= number_format((float) $item['price'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Footer -->
                <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 text-sm space-y-1.5">
                    <div class="flex justify-between text-gray-600">
                        <span>Total Items</span>
                        <span class="font-semibold text-gray-900"><?= $totalItems ?></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Total Quantity</span>
                        <span class="font-semibold text-gray-900"><?= $totalQuantity ?></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Customer</span>
                        <span
                            class="font-semibold text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></span>
                    </div>
                    <?php if ($expectedDelivery): ?>
                        <div
                            class="mt-2 pt-2 border-t border-gray-200 flex gap-2 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
                            <i class="fas fa-clock text-orange-500 mt-0.5 shrink-0"></i>
                            <div>
                                <div class="text-xs text-orange-700 font-semibold">Estimated Delivery Window</div>
                                <div class="text-xs text-orange-800"><?= $expectedDelivery ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="mt-2 pt-2 border-t border-gray-200">
                        <div class="text-xs text-gray-500 mb-1">Delivery Address</div>
                        <div class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($order['address']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Schedule Form -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-calendar-check text-green-600"></i>
                    <h2 class="font-semibold text-gray-800">Set Delivery Schedule</h2>
                </div>

                <form method="POST" class="px-5 py-5 space-y-4">

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-calendar mr-1 text-gray-400"></i> Delivery Date
                        </label>
                        <input type="date" name="delivery_date" id="delivery_date" required min="<?= date('Y-m-d') ?>"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-400 mt-1">Or click a date on the calendar →</p>
                    </div>

                    <!-- Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-clock mr-1 text-gray-400"></i> Time Slot
                        </label>
                        <select name="delivery_time" id="delivery_time" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                   focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">-- Select a time slot --</option>
                            <option value="08:00">8:00 AM – 9:00 AM</option>
                            <option value="09:00">9:00 AM – 10:00 AM</option>
                            <option value="10:00">10:00 AM – 11:00 AM</option>
                            <option value="11:00">11:00 AM – 12:00 PM</option>
                            <option value="13:00">1:00 PM – 2:00 PM</option>
                            <option value="14:00">2:00 PM – 3:00 PM</option>
                            <option value="15:00">3:00 PM – 4:00 PM</option>
                            <option value="16:00">4:00 PM – 5:00 PM</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-sticky-note mr-1 text-gray-400"></i> Notes
                            <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <textarea name="delivery_notes" rows="3" placeholder="Special instructions, contact info, etc."
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm resize-none
                                     focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>

                    <!-- Conflict Warning -->
                    <div id="conflict-warning"
                        class="hidden flex items-center gap-2 bg-yellow-50 border border-yellow-300 text-yellow-800 text-xs rounded-lg px-3 py-2">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        <span id="conflict-text"></span>
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="schedule_delivery" class="w-full flex items-center justify-center gap-2
                               bg-green-600 hover:bg-green-700 active:bg-green-800
                               text-white font-semibold text-sm py-3 px-4 rounded-lg
                               transition-colors duration-150 shadow-sm">
                        <i class="fas fa-calendar-check"></i>
                        Schedule <?= $totalItems ?> Item<?= $totalItems > 1 ? 's' : '' ?> for Delivery
                    </button>

                </form>
            </div>
        </div>

        <!-- ── COL 2: Calendar ───────────────────────────────────────── -->
        <div class="xl:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-calendar text-purple-600"></i>
                    <h2 class="font-semibold text-gray-800">Delivery Calendar</h2>
                </div>

                <div class="px-5 py-4">
                    <!-- Month Navigation -->
                    <div class="flex items-center justify-between mb-4">
                        <button id="prevMonth" type="button"
                            class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </button>
                        <h3 id="currentMonth" class="font-semibold text-gray-900 text-sm"></h3>
                        <button id="nextMonth" type="button"
                            class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </button>
                    </div>

                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 gap-1 mb-1">
                        <?php foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $d): ?>
                            <div class="text-center text-[11px] font-semibold text-gray-400 py-1"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Calendar Days (JS renders this) -->
                    <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>

                    <!-- Legend -->
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-3 text-xs text-gray-500">
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded bg-blue-600"></div>
                            <span>Selected</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded bg-orange-100 border border-orange-400"></div>
                            <span>Has deliveries</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded bg-red-100 border border-red-400"></div>
                            <span>Busy (5+)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── COL 3: Scheduled Orders List ─────────────────────────── -->
        <div class="xl:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-list-ul text-purple-600"></i>
                        <h2 class="font-semibold text-gray-800">Scheduled Orders</h2>
                    </div>
                    <span id="items-count"
                        class="bg-purple-100 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                        <?= count($schedules) ?> orders
                    </span>
                </div>

                <div id="scheduled-items-container" class="max-h-[640px] overflow-y-auto divide-y divide-gray-100">
                    <!-- Rendered by JS -->
                </div>
            </div>
        </div>

    </div><!-- /main grid -->

    <!-- ═══════════════════════════════════════════════════════ SCRIPTS -->
    <script>
        // ── Data from PHP ────────────────────────────────────────────────────────
        const deliveryCounts = <?= json_encode($deliveryCountsByDate) ?>;
        const schedules = <?= json_encode($schedules) ?>;

        // ── Calendar State ───────────────────────────────────────────────────────
        let currentDate = new Date();
        let selectedDate = null;

        // ── Calendar Rendering ───────────────────────────────────────────────────
        function generateCalendar(year, month) {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';

            // Empty cells before first day
            for (let i = 0; i < firstDay; i++) {
                grid.appendChild(Object.assign(document.createElement('div'), { className: 'p-2' }));
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                date.setHours(0, 0, 0, 0);
                const ds = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                const el = document.createElement('div');
                el.dataset.date = ds;

                const count = deliveryCounts[ds] || 0;
                const isPast = date < today;

                // Base classes
                el.className = 'relative p-2 text-center text-sm rounded-lg border border-transparent cursor-pointer transition-all duration-150 select-none';

                if (isPast) {
                    el.className += ' text-gray-300 cursor-not-allowed bg-gray-50';
                } else if (count >= 5) {
                    el.className += ' bg-red-50 border-red-300 text-red-700 hover:bg-red-100';
                } else if (count > 0) {
                    el.className += ' bg-orange-50 border-orange-300 text-orange-700 hover:bg-orange-100';
                } else {
                    el.className += ' text-gray-700 hover:bg-blue-50 hover:border-blue-300';
                }

                el.textContent = day;

                // Delivery count badge
                if (count > 0) {
                    const badge = document.createElement('span');
                    badge.textContent = count;
                    badge.className = 'absolute -top-1 -right-1 text-[9px] font-bold w-4 h-4 flex items-center justify-center rounded-full '
                        + (count >= 5 ? 'bg-red-400 text-white' : 'bg-orange-400 text-white');
                    el.appendChild(badge);
                }

                if (!isPast) {
                    el.addEventListener('click', () => selectDate(ds, el));
                }

                grid.appendChild(el);
            }
        }

        function selectDate(ds, el) {
            // Deselect previous
            document.querySelectorAll('#calendar-grid [data-date]').forEach(d => {
                d.classList.remove('!bg-blue-600', '!text-white', '!border-blue-600');
            });
            el.classList.add('!bg-blue-600', '!text-white', '!border-blue-600');

            selectedDate = ds;
            document.getElementById('delivery_date').value = ds;
            updateScheduledList(ds);
            checkConflict();
        }

        function updateCalendarHeader() {
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            document.getElementById('currentMonth').textContent =
                `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        }

        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
        });
        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
        });

        // ── Scheduled List Rendering ─────────────────────────────────────────────
        function updateScheduledList(filterDate = null) {
            const container = document.getElementById('scheduled-items-container');
            const countEl = document.getElementById('items-count');
            const list = filterDate
                ? schedules.filter(s => s.delivery_date === filterDate)
                : schedules;

            countEl.textContent = filterDate
                ? `${list.length} order${list.length !== 1 ? 's' : ''} on ${fmtDate(filterDate)}`
                : `${schedules.length} orders`;

            if (list.length === 0) {
                container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <i class="fas fa-calendar-times text-4xl mb-3"></i>
                    <p class="text-sm font-medium text-gray-500">No deliveries ${filterDate ? 'on this date' : 'scheduled'}</p>
                    ${filterDate ? `<button onclick="updateScheduledList()" class="mt-3 text-xs text-blue-600 hover:underline">View all</button>` : ''}
                </div>`;
                return;
            }

            // Group by date
            const grouped = {};
            list.forEach(s => {
                if (!grouped[s.delivery_date]) grouped[s.delivery_date] = [];
                grouped[s.delivery_date].push(s);
            });

            let html = '';
            for (const [date, daySchedules] of Object.entries(grouped)) {
                html += `
                <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <i class="fas fa-calendar-day text-blue-500 text-xs"></i>
                        ${fmtDate(date)}
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                            ${daySchedules.length} order${daySchedules.length !== 1 ? 's' : ''}
                        </span>
                        ${filterDate ? `<button onclick="updateScheduledList()" class="text-xs text-blue-500 hover:underline">All</button>` : ''}
                    </div>
                </div>`;

                daySchedules.forEach(s => { html += renderScheduleCard(s); });
            }

            container.innerHTML = html;
        }

        function renderScheduleCard(s) {
            const origQty = parseInt(s.original_quantity) || 0;
            const replQty = parseInt(s.replacement_quantity) || 0;
            const replCnt = parseInt(s.replacement_count) || 0;
            const totalQty = origQty + replQty;

            return `
        <div class="px-4 py-3 hover:bg-gray-50 transition">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap mb-1">
                        <span class="font-semibold text-gray-900 text-sm">Order #${s.order_id}</span>
                        ${replCnt > 0 ? `<span class="text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-medium">+${replCnt} replacement</span>` : ''}
                    </div>
                    <div class="text-xs text-green-600 font-medium mb-1.5">
                        <i class="fas fa-clock mr-1"></i>${fmtTime(s.delivery_time)}
                    </div>
                    <div class="text-xs text-gray-600 truncate">
                        <i class="fas fa-user mr-1 text-gray-400"></i>${esc(s.customer_name)}
                    </div>
                    <div class="text-xs text-gray-500 truncate">
                        <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>${esc(s.address)}
                    </div>
                    ${s.delivery_notes ? `
                        <div class="mt-1.5 text-xs text-gray-500 bg-yellow-50 border border-yellow-200 rounded px-2 py-1 flex gap-1">
                            <i class="fas fa-sticky-note text-yellow-500 shrink-0 mt-0.5"></i>
                            <span>${esc(s.delivery_notes)}</span>
                        </div>` : ''}
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-bold text-gray-900">${totalQty} <span class="text-xs font-normal text-gray-400">pcs</span></div>
                    ${replQty > 0 ? `<div class="text-xs text-red-500">+${replQty} repl.</div>` : ''}
                </div>
            </div>
        </div>`;
        }

        // ── Conflict Detection ────────────────────────────────────────────────────
        function checkConflict() {
            const date = document.getElementById('delivery_date').value;
            const time = document.getElementById('delivery_time').value;
            const warning = document.getElementById('conflict-warning');
            const text = document.getElementById('conflict-text');

            if (!date || !time) { warning.classList.add('hidden'); return; }

            const conflicts = schedules.filter(s => s.delivery_date === date && s.delivery_time === time);
            if (conflicts.length > 0) {
                text.textContent = `${conflicts.length} order${conflicts.length > 1 ? 's' : ''} already scheduled at this time slot.`;
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        }

        // ── Helpers ───────────────────────────────────────────────────────────────
        function fmtDate(ds) {
            return new Date(ds + 'T00:00:00').toLocaleDateString('en-US',
                { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        }

        function fmtTime(t) {
            return new Date('2000-01-01 ' + t).toLocaleTimeString('en-US',
                { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        // ── Event Listeners ───────────────────────────────────────────────────────
        document.getElementById('delivery_date').addEventListener('change', function () {
            const ds = this.value;
            // Sync calendar highlight
            document.querySelectorAll('#calendar-grid [data-date]').forEach(el => {
                el.classList.remove('!bg-blue-600', '!text-white', '!border-blue-600');
            });
            const el = document.querySelector(`#calendar-grid [data-date="${ds}"]`);
            if (el) el.classList.add('!bg-blue-600', '!text-white', '!border-blue-600');
            updateScheduledList(ds);
            checkConflict();
        });

        document.getElementById('delivery_time').addEventListener('change', checkConflict);

        // ── Init ─────────────────────────────────────────────────────────────────
        updateCalendarHeader();
        generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
        updateScheduledList();
    </script>

</body>

</html>