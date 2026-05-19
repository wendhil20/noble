<?php
//order_details.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id)
    die("Invalid order ID");

$order_query = "
    SELECT o.*,
           na.fullname as sales_person_name,
           na.email as sales_person_email
    FROM orders o
    LEFT JOIN nobleaccount na ON o.sales_user_id = na.id
    WHERE o.id = ?
";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order)
    die("Order not found");

$stmt_lt = $conn->prepare("SELECT MIN(lt_from) as earliest_delivery_date, MAX(lt_to) as latest_delivery_date FROM order_items WHERE order_id = ? AND lt_from IS NOT NULL AND lt_to IS NOT NULL");
$stmt_lt->bind_param("i", $order_id);
$stmt_lt->execute();
$lt_result = $stmt_lt->get_result()->fetch_assoc();
$earliest_delivery_date = $lt_result['earliest_delivery_date'];
$latest_delivery_date = $lt_result['latest_delivery_date'];

$stmt = $conn->prepare("SELECT oi.*, pv.original_price, pv.price as current_variant_price, pv.discount as variant_discount, pv.percent as markup_percent, oi.lt_from, oi.lt_to FROM order_items oi LEFT JOIN product_variants pv ON oi.variant_id = pv.id WHERE oi.order_id = ? ORDER BY oi.id");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

$qr_method_label = null;
if ($order['mode_payment'] === 'QR Payment' && !empty($order['bank_type']) && strpos($order['bank_type'], 'QR_') === 0) {
    $qr_id = str_replace('QR_', '', $order['bank_type']);
    $qs = $conn->prepare("SELECT payment_method FROM payment_qr_codes WHERE id = ?");
    $qs->bind_param("i", $qr_id);
    $qs->execute();
    $qr_row = $qs->get_result()->fetch_assoc();
    $qs->close();
    $qr_method_label = $qr_row['payment_method'] ?? 'QR PH';
}

// Pre-calculate profit analysis
$total_original_cost = 0;
$total_selling_price = 0;
$total_item_discount = 0;
$total_markup_amount = 0;
$price_after_markup = 0;
$price_after_discount = 0;
$items_for_analysis = [];

while ($item = $items_result->fetch_assoc()) {
    $items_for_analysis[] = $item;
    $original_price = $item['original_price'] ?? $item['price'];
    $quantity = $item['quantity'];
    $actual_subtotal = $item['subtotal'];
    $markup_percent = $item['markup_percent'] ?? 0;
    $discount_percent = $item['variant_discount'] ?? 0;
    $markup_amount = $original_price * ($markup_percent / 100);
    $price_with_markup = $original_price + $markup_amount;
    $discount_amount = $price_with_markup * ($discount_percent / 100);
    $total_original_cost += ($original_price * $quantity);
    $total_markup_amount += ($markup_amount * $quantity);
    $price_after_markup += ($price_with_markup * $quantity);
    $total_item_discount += ($discount_amount * $quantity);
    $total_selling_price += $actual_subtotal;
    $price_after_discount += $actual_subtotal;
}

$gross_profit = $total_selling_price - $total_original_cost;
$profit_margin = $total_selling_price > 0 ? (($gross_profit / $total_selling_price) * 100) : 0;

$status_colors = [
    'Pending' => 'bg-yellow-100 text-yellow-800',
    'Confirmed' => 'bg-blue-100 text-blue-800',
    'Ongoing' => 'bg-purple-100 text-purple-800',
    'Processing' => 'bg-purple-100 text-purple-800',
    'Shipped' => 'bg-indigo-100 text-indigo-800',
    'Delivered' => 'bg-green-100 text-green-800',
    'Cancelled' => 'bg-red-100 text-red-800',
];
$payment_status_colors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-blue-100 text-blue-800',
    'verified' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
];
$tracking_status_colors = [
    'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'in_transit' => 'bg-blue-50 text-blue-700 border-blue-200',
    'delivered' => 'bg-green-50 text-green-700 border-green-200',
    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order['id'] ?> — Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        body {
            background: #f8f9fb;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .section-title i {
            color: #f97316;
        }

        .info-row {
            display: flex;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            align-items: baseline;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            color: #9ca3af;
            width: 120px;
            flex-shrink: 0;
        }

        .info-value {
            font-size: 13px;
            color: #111827;
            font-weight: 500;
        }

        .stat-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .stat-box .val {
            font-size: 18px;
            font-weight: 700;
        }

        .stat-box .lbl {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .profit-box {
            border-radius: 8px;
            padding: 12px 14px;
        }

        .profit-box .val {
            font-size: 20px;
            font-weight: 800;
        }

        .profit-box .lbl {
            font-size: 11px;
            margin-top: 2px;
            opacity: .7;
        }

        table thead th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #9ca3af;
            background: #f9fafb;
            padding: 10px 12px;
            text-align: left;
        }

        table tbody td {
            padding: 12px;
            font-size: 12px;
            vertical-align: top;
            border-bottom: 1px solid #f3f4f6;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .method-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .flow-arrow {
            color: #d1d5db;
            font-size: 10px;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <!-- ── TOP BAR ── -->
    <div
        class="bg-white border-b border-gray-200 px-5 py-3 flex items-center justify-between no-print sticky top-0 z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-500 flex items-center justify-center">
                <i class="fas fa-receipt text-white text-xs"></i>
            </div>
            <div>
                <span class="font-bold text-gray-900 text-base">Order #<?= $order['id'] ?></span>
                <span
                    class="ml-3 text-xs text-gray-400"><?= date('M d, Y · g:i A', strtotime($order['created_at'])) ?></span>
            </div>
        </div>
        <div class="flex gap-2">
          
            <button onclick="window.close()"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-gray-800 hover:bg-gray-900 text-white rounded-lg transition-colors">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-5">

        <!-- ── ROW 1: Customer + Status ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

            <!-- Customer -->
            <div class="section-card">
                <div class="section-title"><i class="fas fa-user"></i> Customer</div>
                <div class="info-row"><span class="info-label">Name</span><span
                        class="info-value"><?= htmlspecialchars($order['customer_name'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Email</span><span
                        class="info-value"><?= htmlspecialchars($order['email'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Mobile</span><span
                        class="info-value"><?= htmlspecialchars($order['mobile'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Address</span><span
                        class="info-value"><?= htmlspecialchars($order['address'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Zipcode</span><span
                        class="info-value"><?= htmlspecialchars($order['zipcode'] ?: 'N/A') ?></span></div>
            </div>

            <!-- Order Status -->
            <div class="section-card">
                <div class="section-title"><i class="fas fa-info-circle"></i> Order Status</div>
                <div class="info-row">
                    <span class="info-label">Order Status</span>
                    <span class="badge <?= $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment</span>
                    <span
                        class="badge <?= $payment_status_colors[$order['payment_status']] ?? 'bg-gray-100 text-gray-700' ?>">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?= date('M d, Y g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <?php if ($order['confirmed_at']): ?>
                    <div class="info-row">
                        <span class="info-label">Confirmed</span>
                        <span class="info-value"><?= date('M d, Y g:i A', strtotime($order['confirmed_at'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($earliest_delivery_date && $latest_delivery_date): ?>
                    <div class="info-row">
                        <span class="info-label">Delivery Range</span>
                        <span class="info-value text-blue-700">
                            <?= date('M d', strtotime($earliest_delivery_date)) ?> –
                            <?= date('M d, Y', strtotime($latest_delivery_date)) ?>
                        </span>
                    </div>
                <?php elseif ($order['estimated_arrival_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Est. Arrival</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($order['estimated_arrival_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── PAYMENT INFORMATION ── -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-credit-card"></i> Payment Information</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-8 gap-y-1">

                <div class="info-row col-span-full" style="border:none;padding-bottom:6px;">
                    <span class="info-label">Method</span>
                    <span>
                        <?php if ($order['mode_payment'] === 'PayMongo'): ?>
                            <span class="method-badge bg-green-100 text-green-800"><i class="fas fa-mobile-alt"></i>
                                PayMongo</span>
                        <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                            <span class="method-badge bg-indigo-100 text-indigo-800"><i class="fas fa-qrcode"></i>
                                <?= htmlspecialchars($qr_method_label ?? 'QR Payment') ?></span>
                        <?php else: ?>
                            <span class="method-badge bg-gray-100 text-gray-700"><i class="fas fa-credit-card"></i>
                                <?= htmlspecialchars($order['mode_payment'] ?: 'N/A') ?></span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($order['reference_number']) || !empty($order['reference_no'])): ?>
                    <div class="info-row">
                        <span class="info-label">Reference No.</span>
                        <code
                            class="info-value font-mono bg-gray-50 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($order['reference_number'] ?: $order['reference_no']) ?></code>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order['paymongo_payment_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">Payment ID</span>
                        <code
                            class="info-value font-mono bg-green-50 text-green-700 px-2 py-0.5 rounded text-xs break-all"><?= htmlspecialchars($order['paymongo_payment_id']) ?></code>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order['paymongo_session_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">Session ID</span>
                        <code
                            class="info-value font-mono bg-gray-50 px-2 py-0.5 rounded text-xs break-all"><?= htmlspecialchars($order['paymongo_session_id']) ?></code>
                    </div>
                <?php endif; ?>

                <?php if ($order['rejection_reason']): ?>
                    <div class="info-row col-span-full">
                        <span class="info-label text-red-500">Rejection</span>
                        <div>
                            <p class="text-red-700 text-xs bg-red-50 px-3 py-2 rounded-lg">
                                <?= htmlspecialchars($order['rejection_reason']) ?></p>
                            <?php if ($order['rejection_date']): ?>
                                <p class="text-xs text-red-400 mt-1">Rejected:
                                    <?= date('M d, Y g:i A', strtotime($order['rejection_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SALES REP (conditional) ── -->
        <?php if ($order['sales_referral_code']): ?>
            <div class="section-card" style="border-left: 4px solid #7c3aed;">
                <div class="section-title"><i class="fas fa-user-tie" style="color:#7c3aed"></i> Sales Representative</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="stat-box">
                        <div class="lbl">Referral Code</div>
                        <div class="val text-purple-700 text-base"><?= htmlspecialchars($order['sales_referral_code']) ?>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="lbl">Sales Rep</div>
                        <div class="val text-base"><?= htmlspecialchars($order['sales_person_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="lbl">Commission Rate</div>
                        <div class="val text-orange-600"><?= number_format($order['sales_commission_rate'] ?? 0, 1) ?>%
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="lbl">Commission Amount</div>
                        <div class="val text-green-600">₱<?= number_format($order['sales_commission_amount'] ?? 0, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── ORDER TOTALS + PROFIT SIDE BY SIDE ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

            <!-- Order Summary -->
            <div class="section-card">
                <div class="section-title"><i class="fas fa-receipt"></i> Order Summary</div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal</span>
                        <span
                            class="font-medium text-gray-900">₱<?= number_format($order['subtotal'] ?: $order['total'], 2) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">VAT (12%)</span>
                        <span
                            class="font-medium text-purple-700">₱<?= number_format($order['vat_amount'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Delivery Fee</span>
                        <span
                            class="font-medium text-gray-900">₱<?= number_format($order['delivery_fee'] ?: ($order['shipping_fee'] ?? 0), 2) ?></span>
                    </div>
                    <div class="flex justify-between text-sm pt-2 border-t border-gray-200 mt-2">
                        <span class="font-bold text-gray-900">Total</span>
                        <span class="font-bold text-orange-600 text-lg">₱<?= number_format($order['total'], 2) ?></span>
                    </div>
                    <?php if (!empty($order['final_total']) && $order['final_total'] != $order['total']): ?>
                        <div class="flex justify-between text-sm">
                            <span class="font-bold text-gray-900">Final Total (Verified)</span>
                            <span
                                class="font-bold text-green-600 text-lg">₱<?= number_format($order['final_total'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profit Analysis -->
            <div class="section-card" style="border-left: 4px solid #22c55e;">
                <div class="section-title"><i class="fas fa-chart-line" style="color:#22c55e"></i> Profit Analysis</div>

                <!-- Flow -->
                <div class="flex items-center gap-1.5 flex-wrap mb-4 text-xs">
                    <div class="bg-gray-100 rounded px-2 py-1">
                        <span class="text-gray-500">Cost</span>
                        <span class="font-bold text-gray-700 ml-1">₱<?= number_format($total_original_cost, 2) ?></span>
                    </div>
                    <i class="fas fa-plus flow-arrow"></i>
                    <div class="bg-blue-50 rounded px-2 py-1">
                        <span class="text-blue-500">Markup</span>
                        <span
                            class="font-bold text-blue-700 ml-1">+₱<?= number_format($total_markup_amount, 2) ?></span>
                    </div>
                    <i class="fas fa-minus flow-arrow"></i>
                    <div class="bg-orange-50 rounded px-2 py-1">
                        <span class="text-orange-500">Discount</span>
                        <span
                            class="font-bold text-orange-600 ml-1">-₱<?= number_format($total_item_discount, 2) ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="profit-box bg-green-50 border border-green-200">
                        <div class="lbl text-green-700">Gross Profit</div>
                        <div class="val text-green-700">₱<?= number_format($gross_profit, 2) ?></div>
                    </div>
                    <div class="profit-box bg-indigo-50 border border-indigo-200">
                        <div class="lbl text-indigo-700">Profit Margin</div>
                        <div class="val text-indigo-700"><?= number_format($profit_margin, 1) ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── ORDER ITEMS TABLE ── -->
        <div class="section-card" style="padding: 0; overflow: hidden;">
            <div class="section-title" style="padding: 16px 20px 0; margin-bottom:0;">
                <i class="fas fa-shopping-cart"></i> Order Items
                <span
                    class="ml-auto text-xs font-normal text-gray-400 normal-case tracking-normal"><?= count($items_for_analysis) ?>
                    item<?= count($items_for_analysis) !== 1 ? 's' : '' ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th style="min-width:160px">Product</th>
                            <th>Details</th>
                            <th>Pricing</th>
                            <th class="text-center">Qty</th>
                            <th>Lead Time</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-right">Profit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items_for_analysis) > 0): ?>
                            <?php foreach ($items_for_analysis as $item):
                                $original_price = $item['original_price'] ?? $item['price'];
                                $quantity = $item['quantity'];
                                $actual_subtotal = $item['subtotal'];
                                $actual_selling_price = $actual_subtotal / $quantity;
                                $item_profit = $actual_subtotal - ($original_price * $quantity);

                                $item_markup_pct = $item['markup_percent'] ?? 0;
                                $item_discount_pct = $item['variant_discount'] ?? 0;
                                $item_markup_amt = $original_price * ($item_markup_pct / 100);
                                $item_price_with_markup = $original_price + $item_markup_amt;
                                $item_discount_amt = $item_price_with_markup * ($item_discount_pct / 100);
                                $item_price_after_disc = $item_price_with_markup - $item_discount_amt;
                                $color_cost = $actual_selling_price - $item_price_after_disc;

                                $tracking_status = $item['tracking_status'] ?: 'pending';
                                $status_display = ucfirst(str_replace('_', ' ', $tracking_status));
                                $status_icon = ['pending' => 'fa-clock', 'in_transit' => 'fa-truck', 'delivered' => 'fa-check-circle', 'cancelled' => 'fa-times-circle'];
                                ?>
                                <tr>
                                    <!-- Product -->
                                    <td>
                                        <div class="font-semibold text-gray-900 text-xs leading-snug">
                                            <?= htmlspecialchars($item['product_name']) ?></div>
                                        <?php if ($item['codename']): ?>
                                            <div class="text-gray-400 mt-0.5 font-mono"><?= htmlspecialchars($item['codename']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Details -->
                                    <td>
                                        <div class="space-y-0.5 text-gray-600">
                                            <?php if ($item['type_name']): ?>
                                                <div><span class="text-gray-400">Type:</span>
                                                    <?= htmlspecialchars($item['type_name']) ?></div><?php endif; ?>
                                            <?php if ($item['variant_color']): ?>
                                                <div><span class="text-gray-400">Color:</span>
                                                    <?= htmlspecialchars($item['variant_color']) ?></div><?php endif; ?>
                                            <?php if ($item['size']): ?>
                                                <div><span class="text-gray-400">Size:</span> <?= htmlspecialchars($item['size']) ?>
                                                </div><?php endif; ?>
                                            <?php if ($item['origin']): ?>
                                                <div><span class="text-gray-400">Origin:</span>
                                                    <?= htmlspecialchars($item['origin']) ?></div><?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Pricing breakdown -->
                                    <td style="min-width:130px">
                                        <div class="text-gray-700">₱<?= number_format($original_price, 2) ?> <span
                                                class="text-gray-400">base</span></div>
                                        <?php if ($item_markup_pct > 0): ?>
                                            <div class="text-blue-600">+<?= number_format($item_markup_pct, 1) ?>% →
                                                ₱<?= number_format($item_price_with_markup, 2) ?></div>
                                        <?php endif; ?>
                                        <?php if ($item_discount_pct > 0): ?>
                                            <div class="text-orange-500">-<?= number_format($item_discount_pct, 1) ?>% →
                                                ₱<?= number_format($item_price_after_disc, 2) ?></div>
                                        <?php endif; ?>
                                        <?php if (abs($color_cost) > 0.01): ?>
                                            <div class="text-pink-500">+₱<?= number_format($color_cost, 2) ?> color</div>
                                        <?php endif; ?>
                                        <div class="font-bold text-green-700 border-t border-gray-100 pt-0.5 mt-0.5">
                                            ₱<?= number_format($actual_selling_price, 2) ?> final</div>
                                    </td>

                                    <!-- Qty -->
                                    <td class="text-center">
                                        <span class="font-semibold text-gray-900"><?= $quantity ?></span>
                                    </td>

                                    <!-- Lead Time -->
                                    <td style="min-width:100px">
                                        <?php if ($item['lt_from'] && $item['lt_to']): ?>
                                            <div class="text-gray-600"><?= date('M d', strtotime($item['lt_from'])) ?></div>
                                            <div class="text-gray-400 text-xs">to</div>
                                            <div class="text-blue-600 font-medium"><?= date('M d, Y', strtotime($item['lt_to'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-300">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Subtotal -->
                                    <td class="text-right">
                                        <span
                                            class="font-semibold text-gray-900">₱<?= number_format($actual_subtotal, 2) ?></span>
                                    </td>

                                    <!-- Profit -->
                                    <td class="text-right">
                                        <?php if ($item_profit > 0): ?>
                                            <span class="font-bold text-green-600">+₱<?= number_format($item_profit, 2) ?></span>
                                        <?php elseif ($item_profit < 0): ?>
                                            <span class="font-bold text-red-600">₱<?= number_format($item_profit, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">₱0.00</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tracking Status -->
                                    <td>
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border <?= $tracking_status_colors[$tracking_status] ?? 'bg-gray-50 text-gray-700 border-gray-200' ?>">
                                            <i class="fas <?= $status_icon[$tracking_status] ?? 'fa-question' ?>"></i>
                                            <?= $status_display ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-10 text-gray-400">No items found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <!-- Totals footer -->
                    <?php if (count($items_for_analysis) > 0): ?>
                        <tfoot>
                            <tr style="background:#f9fafb; border-top: 2px solid #e5e7eb;">
                                <td colspan="5"
                                    class="px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Totals
                                </td>
                                <td class="px-3 py-3 text-right font-bold text-gray-900">
                                    ₱<?= number_format($total_selling_price, 2) ?></td>
                                <td
                                    class="px-3 py-3 text-right font-bold <?= $gross_profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $gross_profit >= 0 ? '+' : '' ?>₱<?= number_format($gross_profit, 2) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div>
</body>

</html>