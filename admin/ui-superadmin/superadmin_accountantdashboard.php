<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$loginValue = $_SESSION['noble_user'];
$current_user = null;
$stmt = $conn->prepare("SELECT id, fullname, email FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('ss', $loginValue, $loginValue);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $current_user = $row;
    $stmt->close();
}
if (!$current_user) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// Stats
$pending_payments = $verified_payments = $rejected_payments = 0;
$total_revenue = $total_pending_amount = 0;

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='pending'");
if ($r)
    $pending_payments = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='verified'");
if ($r)
    $verified_payments = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='rejected'");
if ($r)
    $rejected_payments = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COALESCE(SUM(final_total),0) as t FROM orders WHERE payment_status='verified'");
if ($r)
    $total_revenue = $r->fetch_assoc()['t'] ?? 0;

$r = $conn->query("SELECT COALESCE(SUM(total),0) as t FROM orders WHERE payment_status='pending'");
if ($r)
    $total_pending_amount = $r->fetch_assoc()['t'] ?? 0;

$paymongo_count = $qr_count = $bank_count = $paypal_count = 0;
$r = $conn->query("SELECT mode_payment, COUNT(*) as count FROM orders GROUP BY mode_payment");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        match ($row['mode_payment']) {
            'PayMongo' => $paymongo_count = $row['count'],
            'QR Payment' => $qr_count = $row['count'],
            'Bank Transfer' => $bank_count = $row['count'],
            'PayPal' => $paypal_count = $row['count'],
            default => null,
        };
    }
}

$filter = $_GET['filter'] ?? 'all';
$payment_method = $_GET['payment_method'] ?? 'all';

$where = match ($filter) {
    'pending' => "WHERE o.payment_status = 'pending'",
    'verified' => "WHERE o.payment_status = 'verified'",
    'rejected' => "WHERE o.payment_status = 'rejected'",
    default => "",
};
if ($payment_method !== 'all') {
    $pm = $conn->real_escape_string($payment_method);
    $where = $where === "" ? "WHERE o.mode_payment = '$pm'" : "$where AND o.mode_payment = '$pm'";
}

$orders_result = $conn->query("
    SELECT o.*, vb.fullname as verified_by_name, rb.fullname as rejected_by_name
    FROM orders o
    LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
    LEFT JOIN nobleaccount rb ON o.rejected_by = rb.id
    $where
    ORDER BY o.created_at DESC LIMIT 100
");

$verified_today = $revenue_today = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='verified' AND DATE(confirmed_at)=CURDATE()");
if ($r)
    $verified_today = $r->fetch_assoc()['c'] ?? 0;
$r = $conn->query("SELECT COALESCE(SUM(final_total),0) as t FROM orders WHERE payment_status='verified' AND DATE(confirmed_at)=CURDATE()");
if ($r)
    $revenue_today = $r->fetch_assoc()['t'] ?? 0;

$total_orders = $pending_payments + $verified_payments + $rejected_payments;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard — Noble Home</title>
</head>

<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-screen-xl mx-auto px-6 py-8">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calculator text-white text-sm"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Accountant Dashboard</h1>
            </div>
            <p class="text-sm text-gray-500 ml-12">Payment overview and analytics for all orders.</p>
        </div>

        <!-- Top Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-amber-500 uppercase tracking-wider mb-1 font-medium">Pending</p>
                <p class="text-2xl font-bold text-amber-600"><?php echo number_format($pending_payments); ?></p>
                <p class="text-xs text-gray-400 mt-1">₱<?php echo number_format($total_pending_amount, 2); ?> at stake
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-emerald-500 uppercase tracking-wider mb-1 font-medium">Verified</p>
                <p class="text-2xl font-bold text-emerald-600"><?php echo number_format($verified_payments); ?></p>
                <p class="text-xs text-gray-400 mt-1">Completed payments</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-orange-500 uppercase tracking-wider mb-1 font-medium">Revenue</p>
                <p class="text-2xl font-bold text-orange-600">₱<?php echo number_format($total_revenue, 2); ?></p>
                <p class="text-xs text-gray-400 mt-1">From verified orders</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <p class="text-xs text-red-400 uppercase tracking-wider mb-1 font-medium">Rejected</p>
                <p class="text-2xl font-bold text-red-600"><?php echo number_format($rejected_payments); ?></p>
                <p class="text-xs text-gray-400 mt-1">Declined payments</p>
            </div>
        </div>

        <!-- Today + Payment Methods -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

            <!-- Today's Summary -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i class="fas fa-calendar-day text-emerald-500 text-sm"></i>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Today's Activity</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-3 border-b border-gray-100">
                        <p class="text-sm text-gray-500">Orders verified</p>
                        <p class="text-xl font-bold text-emerald-600"><?php echo number_format($verified_today); ?></p>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <p class="text-sm text-gray-500">Revenue generated</p>
                        <p class="text-lg font-bold text-emerald-600">₱<?php echo number_format($revenue_today, 2); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i class="fas fa-credit-card text-blue-500 text-sm"></i>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Payment Methods</h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php
                    $methods = [
                        ['label' => 'PayMongo', 'icon' => 'fas fa-mobile-alt', 'count' => $paymongo_count, 'color' => 'emerald'],
                        ['label' => 'QR Payment', 'icon' => 'fas fa-qrcode', 'count' => $qr_count, 'color' => 'indigo'],
                        ['label' => 'Bank Transfer', 'icon' => 'fas fa-university', 'count' => $bank_count, 'color' => 'violet'],
                        ['label' => 'PayPal', 'icon' => 'fab fa-paypal', 'count' => $paypal_count, 'color' => 'blue'],
                    ];
                    $colorMap = [
                        'emerald' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-600', 'badge' => 'bg-emerald-100 text-emerald-700'],
                        'indigo' => ['bg' => 'bg-indigo-50', 'icon' => 'text-indigo-600', 'badge' => 'bg-indigo-100 text-indigo-700'],
                        'violet' => ['bg' => 'bg-violet-50', 'icon' => 'text-violet-600', 'badge' => 'bg-violet-100 text-violet-700'],
                        'blue' => ['bg' => 'bg-blue-50', 'icon' => 'text-blue-600', 'badge' => 'bg-blue-100 text-blue-700'],
                    ];
                    foreach ($methods as $m):
                        $c = $colorMap[$m['color']];
                        ?>
                        <div class="<?php echo $c['bg']; ?> rounded-xl p-4 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <i class="<?php echo $m['icon']; ?> <?php echo $c['icon']; ?> text-base"></i>
                                <span class="text-xs font-bold <?php echo $c['badge']; ?> px-2 py-0.5 rounded-full">
                                    <?php echo number_format($m['count']); ?>
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-gray-700 leading-tight"><?php echo $m['label']; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 mb-6">
            <div class="flex flex-wrap gap-3 items-center">
                <!-- Status tabs -->
                <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                    <?php
                    $tabs = [
                        'all' => "All ({$total_orders})",
                        'pending' => "Pending ({$pending_payments})",
                        'verified' => "Verified ({$verified_payments})",
                        'rejected' => "Rejected ({$rejected_payments})",
                    ];
                    foreach ($tabs as $val => $label):
                        $active = $filter === $val;
                        ?>
                        <a href="?filter=<?php echo $val; ?>&payment_method=<?php echo urlencode($payment_method); ?>"
                            class="px-3 py-1.5 text-xs font-medium rounded-md transition <?php echo $active ? 'bg-white text-orange-600 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-700'; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Payment method filter -->
                <div class="flex items-center gap-2 ml-auto">
                    <label class="text-xs text-gray-500 whitespace-nowrap">
                        <i class="fas fa-filter mr-1 text-orange-400"></i>Method:
                    </label>
                    <select onchange="filterByPaymentMethod(this.value)"
                        class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-gray-700">
                        <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="PayMongo" <?php echo $payment_method === 'PayMongo' ? 'selected' : ''; ?>>PayMongo
                        </option>
                        <option value="QR Payment" <?php echo $payment_method === 'QR Payment' ? 'selected' : ''; ?>>QR
                            Payment</option>
                        <option value="Bank Transfer" <?php echo $payment_method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="PayPal" <?php echo $payment_method === 'PayPal' ? 'selected' : ''; ?>>PayPal
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Payment Orders</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Showing up to 100 records</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Order</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Customer</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Amount</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Method</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Processed By</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while ($order = $orders_result->fetch_assoc()):
                                $status = $order['payment_status'];
                                $statusMap = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'verified' => 'bg-emerald-100 text-emerald-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                ];
                                $iconMap = [
                                    'pending' => 'fas fa-clock',
                                    'verified' => 'fas fa-check-circle',
                                    'rejected' => 'fas fa-times-circle',
                                ];
                                $pmIconMap = [
                                    'PayPal' => ['fab fa-paypal', 'text-blue-500'],
                                    'PayMongo' => ['fas fa-mobile-alt', 'text-emerald-500'],
                                    'QR Payment' => ['fas fa-qrcode', 'text-indigo-500'],
                                    'Bank Transfer' => ['fas fa-university', 'text-violet-500'],
                                ];
                                $pmInfo = $pmIconMap[$order['mode_payment']] ?? ['fas fa-credit-card', 'text-gray-400'];
                                $display_amount = ($status === 'verified' && isset($order['final_total']) && $order['final_total'] !== null)
                                    ? $order['final_total'] : $order['total'];
                                $initials = strtoupper(substr($order['customer_name'] ?: 'U', 0, 1));
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span
                                            class="bg-gray-100 px-2.5 py-1 rounded-md text-xs font-semibold text-gray-700">#<?php echo $order['id']; ?></span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <span class="text-blue-600 text-xs font-bold"><?php echo $initials; ?></span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></p>
                                                <p class="text-xs text-gray-400">
                                                    <?php echo htmlspecialchars(substr($order['email'] ?? '', 0, 25)); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span
                                            class="font-semibold text-gray-900">₱<?php echo number_format((float) $display_amount, 2); ?></span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 text-gray-700 text-xs">
                                            <i class="<?php echo $pmInfo[0]; ?> <?php echo $pmInfo[1]; ?>"></i>
                                            <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $statusMap[$status] ?? 'bg-gray-100 text-gray-600'; ?>">
                                            <i class="<?php echo $iconMap[$status] ?? 'fas fa-question'; ?> text-xs"></i>
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php
                                        if ($status === 'verified' && $order['verified_by_name'])
                                            echo htmlspecialchars($order['verified_by_name']);
                                        elseif ($status === 'rejected' && $order['rejected_by_name'])
                                            echo htmlspecialchars($order['rejected_by_name']);
                                        else
                                            echo '<span class="text-gray-400 italic text-xs">Pending</span>';
                                        ?>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <p class="text-sm text-gray-700">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo date('H:i', strtotime($order['created_at'])); ?></p>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-20 text-center">
                                    <div
                                        class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-receipt text-gray-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-base font-semibold text-gray-700 mb-1">No orders found</h3>
                                    <p class="text-sm text-gray-400">No payment records match the current filters.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function filterByPaymentMethod(method) {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            sessionStorage.setItem('scrollPosition', window.scrollY);
            window.location.href = `?filter=${currentFilter}&payment_method=${encodeURIComponent(method)}`;
        }
        window.addEventListener('load', () => {
            const pos = sessionStorage.getItem('scrollPosition');
            if (pos) { window.scrollTo(0, parseInt(pos)); sessionStorage.removeItem('scrollPosition'); }
        });
    </script>

</body>

</html>