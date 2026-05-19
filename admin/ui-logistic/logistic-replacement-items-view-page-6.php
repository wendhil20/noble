<?php
// replacement_items_view.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);



if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: " . BASE_URL . "/logisticdispatcherdashboard");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$order_id    = isset($_GET['order_id'])    ? intval($_GET['order_id'])    : 0;

if (!$schedule_id || !$order_id) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$scheduleSql = "SELECT ds.*, o.customer_name, o.email, o.mobile, o.address,
                       o.final_total, o.status as order_status,
                       o.delivery_type as order_delivery_type,
                       o.created_at as order_created_at
                FROM delivery_schedules ds
                INNER JOIN orders o ON ds.order_id = o.id
                WHERE ds.id = ? AND ds.order_id = ? AND ds.item_type = 'replacement'";
$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->bind_param("ii", $schedule_id, $order_id);
$scheduleStmt->execute();
$schedule = $scheduleStmt->get_result()->fetch_assoc();
$scheduleStmt->close();

if (!$schedule) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$replacementsSql = "SELECT rr.*, oi.product_name, oi.variant_color, oi.size, oi.price,
                           oi.codename, oi.type_name, oi.warehouse_location,
                    CASE
                        WHEN rr.status = 'pending'          THEN 'Pending'
                        WHEN rr.status = 'approved'         THEN 'Approved'
                        WHEN rr.status = 'ready_for_pickup' THEN 'Ready for Pickup'
                        WHEN rr.status = 'item_is_loaded'   THEN 'Item is Loaded'
                        WHEN rr.status = 'out_for_delivery' THEN 'Out for Delivery'
                        WHEN rr.status = 'delivered'        THEN 'Delivered'
                        WHEN rr.status = 'picked_up'        THEN 'Picked Up'
                        ELSE 'Unknown'
                    END as status_display
                    FROM replacement_requests rr
                    INNER JOIN order_items oi ON rr.order_item_id = oi.id
                    WHERE rr.delivery_schedule_id = ? AND rr.order_id = ?
                    ORDER BY oi.product_name";
$replacementsStmt = $conn->prepare($replacementsSql);
$replacementsStmt->bind_param("ii", $schedule_id, $order_id);
$replacementsStmt->execute();
$replacements = $replacementsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$replacementsStmt->close();

if (empty($replacements)) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$bookingSql = "SELECT * FROM delivery_bookings WHERE delivery_schedule_id = ? LIMIT 1";
$bookingStmt = $conn->prepare($bookingSql);
$bookingStmt->bind_param("i", $schedule_id);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

$totalItems    = count($replacements);
$totalQuantity = array_sum(array_column($replacements, 'replacement_quantity'));
$totalValue    = 0;
foreach ($replacements as $r) {
    $totalValue += $r['price'] * $r['replacement_quantity'];
}

$statusCounts = ['pending' => 0, 'approved' => 0, 'ready_for_pickup' => 0, 'item_is_loaded' => 0, 'out_for_delivery' => 0, 'delivered' => 0, 'picked_up' => 0];
foreach ($replacements as $item) {
    if (isset($statusCounts[$item['status']])) $statusCounts[$item['status']]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Replacement Items - Order #<?php echo $order_id; ?> - Noble Home</title>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">

        <!-- Back Link + Header -->
        <div>
            <?php if ($schedule['delivery_date']): ?>
                <a href="<?= BASE_URL ?>/logisticdeliverydateorders?date=<?= $schedule['delivery_date'] ?>"
                   class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-4">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/logistic"
                   class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-4">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-gray-900">Order #<?php echo $order_id; ?></h1>
                        <span class="inline-flex items-center gap-1.5 bg-orange-100 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                            <i class="fas fa-sync-alt"></i> REPLACEMENT
                        </span>
                    </div>
                    <p class="text-gray-500 text-sm mt-0.5">
                        <?= htmlspecialchars($schedule['customer_name']) ?> &middot; Schedule #<?= $schedule_id ?>
                    </p>
                </div>
                <?php if ($booking): ?>
                    <a href="<?= BASE_URL ?>/logisticdeliverytrack?booking_id=<?= $booking['id'] ?>"
                       class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-tasks"></i> Manage Delivery
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Summary Banner -->
        <div class="bg-orange-500 rounded-xl p-5 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="font-semibold text-lg">Replacement Summary</p>
                <p class="text-orange-100 text-sm mt-0.5">
                    <?= date('l, F d, Y', strtotime($schedule['delivery_date'])) ?>
                    at <?= date('g:i A', strtotime($schedule['delivery_time'])) ?>
                </p>
            </div>
            <div class="sm:text-right">
                <p class="text-3xl font-bold">₱<?= number_format($totalValue, 2) ?></p>
                <p class="text-orange-100 text-xs mt-0.5">Total Replacement Value</p>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-4 sm:grid-cols-7 gap-3">
            <?php
            $stats = [
                ['label' => 'Items',      'value' => $totalItems,                                                    'color' => 'text-orange-600', 'border' => 'border-orange-200'],
                ['label' => 'Total Qty',  'value' => $totalQuantity,                                                 'color' => 'text-orange-600', 'border' => 'border-orange-200'],
                ['label' => 'Pending',    'value' => $statusCounts['pending'] + $statusCounts['approved'],            'color' => 'text-gray-500',   'border' => 'border-gray-200'],
                ['label' => 'Ready',      'value' => $statusCounts['ready_for_pickup'],                               'color' => 'text-yellow-600', 'border' => 'border-yellow-200'],
                ['label' => 'Loaded',     'value' => $statusCounts['item_is_loaded'],                                 'color' => 'text-blue-600',   'border' => 'border-blue-200'],
                ['label' => 'In Transit', 'value' => $statusCounts['out_for_delivery'],                               'color' => 'text-purple-600', 'border' => 'border-purple-200'],
                ['label' => 'Completed',  'value' => $statusCounts['delivered'] + $statusCounts['picked_up'],         'color' => 'text-green-600',  'border' => 'border-green-200'],
            ];
            foreach ($stats as $s): ?>
                <div class="bg-white border <?= $s['border'] ?> rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold <?= $s['color'] ?>"><?= $s['value'] ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?= $s['label'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Replacement Items (<?= $totalItems ?>)</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Details</th>
                            <th class="px-5 py-3">Reason</th>
                            <th class="px-5 py-3 text-center">Qty</th>
                            <th class="px-5 py-3 text-right">Price</th>
                            <th class="px-5 py-3 text-right">Subtotal</th>
                            <th class="px-5 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $badgeMap = [
                            'pending'          => 'bg-gray-100 text-gray-600',
                            'approved'         => 'bg-indigo-50 text-indigo-600',
                            'ready_for_pickup' => 'bg-yellow-50 text-yellow-700',
                            'item_is_loaded'   => 'bg-blue-50 text-blue-700',
                            'out_for_delivery' => 'bg-purple-50 text-purple-700',
                            'delivered'        => 'bg-green-50 text-green-700',
                            'picked_up'        => 'bg-green-50 text-green-700',
                        ];
                        $iconMap = [
                            'pending'          => 'fa-clock',
                            'approved'         => 'fa-check',
                            'ready_for_pickup' => 'fa-box',
                            'item_is_loaded'   => 'fa-check-circle',
                            'out_for_delivery' => 'fa-truck',
                            'delivered'        => 'fa-check-double',
                            'picked_up'        => 'fa-check-double',
                        ];
                        foreach ($replacements as $item):
                            $badge = $badgeMap[$item['status']] ?? 'bg-gray-100 text-gray-600';
                            $icon  = $iconMap[$item['status']]  ?? 'fa-question';
                        ?>
                        <tr class="hover:bg-orange-50 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></p>
                                <?php if ($item['codename']): ?>
                                    <p class="text-xs text-gray-400 mt-0.5">Code: <?= htmlspecialchars($item['codename']) ?></p>
                                <?php endif; ?>
                                <span class="inline-block mt-1.5 text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">
                                    Req #<?= $item['id'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-gray-600 space-y-0.5">
                                <?php if ($item['variant_color']): ?><p>Color: <span class="text-gray-800"><?= htmlspecialchars($item['variant_color']) ?></span></p><?php endif; ?>
                                <?php if ($item['size']): ?>        <p>Size: <span class="text-gray-800"><?= htmlspecialchars($item['size']) ?></span></p><?php endif; ?>
                                <?php if ($item['type_name']): ?>   <p>Type: <span class="text-gray-800"><?= htmlspecialchars($item['type_name']) ?></span></p><?php endif; ?>
                                <?php if ($item['warehouse_location']): ?>
                                    <p class="flex items-center gap-1 mt-1">
                                        <i class="fas fa-map-marker-alt text-red-400 text-xs"></i>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs px-1.5 py-0.5 rounded"><?= htmlspecialchars($item['warehouse_location']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-xs font-medium text-red-600 mb-1">Reason</p>
                                <p class="text-gray-800 text-sm capitalize"><?= str_replace('_', ' ', $item['reason']) ?></p>
                                <?php if ($item['details']): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?= nl2br(htmlspecialchars($item['details'])) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center font-semibold text-orange-600"><?= $item['replacement_quantity'] ?></td>
                            <td class="px-5 py-4 text-right text-gray-700">₱<?= number_format($item['price'], 2) ?></td>
                            <td class="px-5 py-4 text-right font-semibold text-gray-900">₱<?= number_format($item['price'] * $item['replacement_quantity'], 2) ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?= $badge ?>">
                                    <i class="fas <?= $icon ?>"></i>
                                    <?= $item['status_display'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200">
                            <td colspan="5" class="px-5 py-4 text-right font-semibold text-gray-700">Replacement Total</td>
                            <td class="px-5 py-4 text-right font-bold text-lg text-orange-600">₱<?= number_format($totalValue, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Customer + Schedule Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Customer -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-user text-green-500"></i> Customer Information
                </h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-400 text-xs mb-0.5">Name</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($schedule['customer_name']) ?></dd>
                    </div>
                    <?php if ($schedule['email']): ?>
                    <div>
                        <dt class="text-gray-400 text-xs mb-0.5">Email</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($schedule['email']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($schedule['mobile']): ?>
                    <div>
                        <dt class="text-gray-400 text-xs mb-0.5">Mobile</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($schedule['mobile']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-gray-400 text-xs mb-0.5">Address</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($schedule['address']) ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Schedule Details -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-sync-alt text-orange-500"></i> Replacement Schedule
                </h3>
                <dl class="space-y-2 text-sm">
                    <?php
                    $rows = [
                        ['Order ID',       '#' . $order_id],
                        ['Schedule ID',    '#' . $schedule_id],
                        ['Order Status',   ucfirst($schedule['order_status'])],
                        ['Order Date',     date('M d, Y g:i A', strtotime($schedule['order_created_at']))],
                        ['Scheduled Date', date('M d, Y', strtotime($schedule['delivery_date']))],
                        ['Scheduled Time', date('g:i A', strtotime($schedule['delivery_time']))],
                        ['Delivery Type',  ucfirst($schedule['order_delivery_type'])],
                        ['Total Items',    $totalItems . ' items (' . $totalQuantity . ' pcs)'],
                    ];
                    foreach ($rows as [$label, $value]): ?>
                    <div class="flex justify-between items-center py-1.5 border-b border-gray-50 last:border-0">
                        <dt class="text-gray-500"><?= $label ?></dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars((string)$value) ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <!-- Booking Info -->
        <?php if ($booking): ?>
        <div class="bg-white rounded-xl border border-orange-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-shipping-fast text-orange-500"></i> Active Replacement Booking
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <?php if ($booking['tracking_number']): ?>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-400 mb-1">Tracking Number</p>
                    <p class="font-mono font-semibold text-gray-900"><?= htmlspecialchars($booking['tracking_number']) ?></p>
                </div>
                <?php endif; ?>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-400 mb-1">Courier</p>
                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($booking['courier_name']) ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-400 mb-1">Booking Status</p>
                    <p class="font-semibold text-gray-900 capitalize"><?= str_replace('_', ' ', $booking['booking_status']) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>