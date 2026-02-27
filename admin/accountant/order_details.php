<?php
//order_details.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    die("Invalid order ID");
}

// Get order details with sales referral information
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

if (!$order) {
    die("Order not found");
}

// Calculate the delivery date range from all order items
$delivery_range_query = "
    SELECT MIN(lt_from) as earliest_delivery_date,
           MAX(lt_to) as latest_delivery_date
    FROM order_items
    WHERE order_id = ? AND lt_from IS NOT NULL AND lt_to IS NOT NULL
";
$stmt_lt = $conn->prepare($delivery_range_query);
$stmt_lt->bind_param("i", $order_id);
$stmt_lt->execute();
$lt_result = $stmt_lt->get_result()->fetch_assoc();
$earliest_delivery_date = $lt_result['earliest_delivery_date'];
$latest_delivery_date = $lt_result['latest_delivery_date'];

// Get order items with tracking status and original pricing
$items_query = "
    SELECT oi.*, 
           pv.original_price,
           pv.price as current_variant_price,
           pv.discount as variant_discount,
           pv.percent as markup_percent,
           oi.lt_from,
           oi.lt_to
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Fetch QR PH label if applicable
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

// Status color mappings
$status_colors = [
    'Pending'    => 'bg-yellow-100 text-yellow-800',
    'Confirmed'  => 'bg-blue-100 text-blue-800',
    'Processing' => 'bg-purple-100 text-purple-800',
    'Shipped'    => 'bg-indigo-100 text-indigo-800',
    'Delivered'  => 'bg-green-100 text-green-800',
    'Cancelled'  => 'bg-red-100 text-red-800'
];

$payment_status_colors = [
    'pending'  => 'bg-yellow-100 text-yellow-800',
    'paid'     => 'bg-blue-100 text-blue-800',
    'verified' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800'
];

$tracking_status_colors = [
    'pending'    => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'in_transit' => 'bg-blue-50 text-blue-700 border-blue-200',
    'delivered'  => 'bg-green-50 text-green-700 border-green-200',
    'cancelled'  => 'bg-red-50 text-red-700 border-red-200'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order['id']; ?> Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-3 py-6">

        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-receipt text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Order #<?php echo $order['id']; ?></h1>
                        <p class="text-gray-600">Complete order information</p>
                    </div>
                </div>
                <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Customer Information -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user text-noble-orange mr-2"></i>Customer Information
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Name</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($order['email'] ?: 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Mobile</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($order['mobile'] ?: 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Address</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($order['address'] ?: 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Zipcode</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($order['zipcode'] ?: 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Order Status & Dates -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-noble-orange mr-2"></i>Order Status
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Order Status</label>
                        <p><span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Payment Status</label>
                        <p><span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php echo $payment_status_colors[$order['payment_status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Order Date</label>
                        <p class="text-gray-900"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <?php if ($order['confirmed_at']): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Confirmed Date</label>
                            <p class="text-gray-900"><?php echo date('M d, Y h:i A', strtotime($order['confirmed_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($earliest_delivery_date && $latest_delivery_date): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Expected Delivery Range</label>
                            <div class="mt-1 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg p-3">
                                <p class="text-gray-900 font-semibold">
                                    <?php echo date('M d, Y', strtotime($earliest_delivery_date)); ?>
                                    <span class="text-gray-500 mx-2">to</span>
                                    <span class="text-blue-700 font-bold"><?php echo date('M d, Y', strtotime($latest_delivery_date)); ?></span>
                                </p>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Based on item lead times</p>
                        </div>
                    <?php elseif ($order['estimated_arrival_date']): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Estimated Arrival</label>
                            <p class="text-gray-900"><?php echo date('M d, Y h:i A', strtotime($order['estimated_arrival_date'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sales Referral Information (if applicable) -->
        <?php if ($order['sales_referral_code']): ?>
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-500 rounded-xl shadow-sm p-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user-tie text-purple-600 mr-2"></i>Sales Representative
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Referral Code Used</label>
                        <p class="text-lg font-bold text-purple-700"><?php echo htmlspecialchars($order['sales_referral_code']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Sales Representative</label>
                        <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($order['sales_person_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Commission Rate</label>
                        <p class="text-lg font-bold text-orange-600"><?php echo number_format($order['sales_commission_rate'] ?? 0, 1); ?>%</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Commission Amount</label>
                        <p class="text-lg font-bold text-green-600">₱<?php echo number_format($order['sales_commission_amount'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Information -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-credit-card text-noble-orange mr-2"></i>Payment Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Payment Method -->
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment Method</label>
                    <div class="mt-1 flex items-center space-x-2">
                        <?php if ($order['mode_payment'] === 'PayMongo'): ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-green-100 text-green-800 font-semibold text-sm">
                                <i class="fas fa-mobile-alt mr-2"></i>PayMongo
                            </span>
                        <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-100 text-indigo-800 font-semibold text-sm">
                                <i class="fas fa-qrcode mr-2"></i>
                                <?php echo htmlspecialchars($qr_method_label ?? 'QR Payment'); ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 font-semibold text-sm">
                                <i class="fas fa-credit-card mr-2"></i>
                                <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['mode_payment'] === 'PayMongo'): ?>
                    <!-- PayMongo: Session ID -->
                    <?php if (!empty($order['paymongo_session_id'])): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Session ID</label>
                            <p class="text-gray-900 text-xs break-all mt-1 bg-gray-50 p-2 rounded-lg border border-gray-200 font-mono">
                                <?php echo htmlspecialchars($order['paymongo_session_id']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- PayMongo: Payment ID -->
                    <?php if (!empty($order['paymongo_payment_id'])): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Payment ID</label>
                            <p class="text-gray-900 text-xs break-all mt-1 bg-green-50 p-2 rounded-lg border border-green-200 font-mono text-green-800">
                                <?php echo htmlspecialchars($order['paymongo_payment_id']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- PayMongo: Reference Number -->
                    <?php if (!empty($order['reference_number']) || !empty($order['reference_no'])): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Reference Number</label>
                            <p class="text-gray-900 font-mono mt-1 bg-gray-50 p-2 rounded-lg border border-gray-200">
                                <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                    <!-- QR PH: Reference Number -->
                    <?php if (!empty($order['reference_number']) || !empty($order['reference_no'])): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Reference Number</label>
                            <p class="text-gray-900 font-mono mt-1 bg-indigo-50 p-2 rounded-lg border border-indigo-200">
                                <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- QR PH: QR Method Name -->
                    <?php if ($qr_method_label): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">QR Provider</label>
                            <p class="text-gray-900 mt-1"><?php echo htmlspecialchars($qr_method_label); ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Rejection Reason (if applicable) -->
                <?php if ($order['rejection_reason']): ?>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium text-red-600">Rejection Reason</label>
                        <p class="text-red-700 bg-red-50 p-3 rounded-lg mt-1"><?php echo htmlspecialchars($order['rejection_reason']); ?></p>
                        <?php if ($order['rejection_date']): ?>
                            <p class="text-sm text-red-600 mt-1">Rejected on: <?php echo date('M d, Y h:i A', strtotime($order['rejection_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-calculator text-noble-orange mr-2"></i>Order Summary
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($order['subtotal'] ?: $order['total'], 2); ?></p>
                    <p class="text-sm text-gray-600">Subtotal</p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-purple-600">₱<?php echo number_format($order['vat_amount'], 2); ?></p>
                    <p class="text-sm text-gray-600">VAT (12%)</p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($order['delivery_fee'] ?: $order['shipping_fee'], 2); ?></p>
                    <p class="text-sm text-gray-600">Delivery Fee</p>
                </div>
                <div class="text-center p-4 bg-noble-orange rounded-lg">
                    <p class="text-2xl font-bold text-white">₱<?php echo number_format($order['total'], 2); ?></p>
                    <p class="text-sm text-orange-100">Total</p>
                </div>
            </div>
        </div>

        <!-- Profit & Discount Analysis -->
        <?php
        $total_original_cost  = 0;
        $total_selling_price  = 0;
        $total_item_discount  = 0;
        $total_markup_amount  = 0;
        $price_after_markup   = 0;
        $price_after_discount = 0;
        $items_for_analysis   = [];

        $items_result->data_seek(0);
        while ($item = $items_result->fetch_assoc()) {
            $items_for_analysis[] = $item;

            $original_price  = $item['original_price'] ?? $item['price'];
            $quantity        = $item['quantity'];
            $actual_subtotal = $item['subtotal'];

            $markup_percent  = $item['markup_percent'] ?? 0;
            $discount_percent = $item['variant_discount'] ?? 0;

            $markup_amount     = $original_price * ($markup_percent / 100);
            $price_with_markup = $original_price + $markup_amount;
            $discount_amount   = $price_with_markup * ($discount_percent / 100);

            $total_original_cost  += ($original_price * $quantity);
            $total_markup_amount  += ($markup_amount * $quantity);
            $price_after_markup   += ($price_with_markup * $quantity);
            $total_item_discount  += ($discount_amount * $quantity);
            $total_selling_price  += $actual_subtotal;
            $price_after_discount += $actual_subtotal;
        }

        $gross_profit   = $total_selling_price - $total_original_cost;
        $profit_margin  = $total_selling_price > 0 ? (($gross_profit / $total_selling_price) * 100) : 0;
        ?>

        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-xl shadow-sm p-4 mb-6 border-l-4 border-green-500">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-line text-green-600 mr-2"></i>Profit & Discount Analysis
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <p class="text-xs text-gray-600 mb-1">Supplier Cost</p>
                    <p class="text-xl font-bold text-gray-700">₱<?php echo number_format($total_original_cost, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Base price</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-blue-200">
                    <p class="text-xs text-gray-600 mb-1">Total Markup</p>
                    <p class="text-xl font-bold text-blue-600">+₱<?php echo number_format($total_markup_amount, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Added profit</p>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <p class="text-xs text-gray-600 mb-1">After Markup</p>
                    <p class="text-xl font-bold text-indigo-600">₱<?php echo number_format($price_after_markup, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Before discount</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-orange-200">
                    <p class="text-xs text-gray-600 mb-1">Item Discounts</p>
                    <p class="text-xl font-bold text-orange-600">-₱<?php echo number_format($total_item_discount, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Discount given</p>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <p class="text-xs text-gray-600 mb-1">After Discount</p>
                    <p class="text-xl font-bold text-green-600">₱<?php echo number_format($price_after_discount, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Final selling price</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-green-200">
                    <p class="text-xs text-gray-600 mb-1">Gross Profit</p>
                    <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($gross_profit, 2); ?></p>
                    <p class="text-xs text-gray-500 mt-1">After all discounts</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-indigo-200">
                    <p class="text-xs text-gray-600 mb-1">Profit Margin</p>
                    <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($profit_margin, 2); ?>%</p>
                    <p class="text-xs text-gray-500 mt-1">Gross margin percentage</p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-shopping-cart text-noble-orange mr-2"></i>Order Items
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pricing</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead Time</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($items_for_analysis) > 0): ?>
                            <?php foreach ($items_for_analysis as $item):
                                $original_price      = $item['original_price'] ?? $item['price'];
                                $quantity            = $item['quantity'];
                                $actual_subtotal     = $item['subtotal'];
                                $actual_selling_price = $actual_subtotal / $quantity;
                                $item_profit         = $actual_subtotal - ($original_price * $quantity);
                            ?>
                                <tr>
                                    <td class="px-2 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </div>
                                        <?php if ($item['codename']): ?>
                                            <div class="text-xs text-gray-500">Code: <?php echo htmlspecialchars($item['codename']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 py-4">
                                        <div class="text-xs space-y-1">
                                            <?php if ($item['type_name']): ?>
                                                <div><span class="font-medium">Type:</span> <?php echo htmlspecialchars($item['type_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['variant_color']): ?>
                                                <div><span class="font-medium">Color:</span> <?php echo htmlspecialchars($item['variant_color']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['size']): ?>
                                                <div><span class="font-medium">Size:</span> <?php echo htmlspecialchars($item['size']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['origin']): ?>
                                                <div><span class="font-medium">Origin:</span> <?php echo htmlspecialchars($item['origin']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-2 py-4">
                                        <?php
                                        $item_original         = $item['original_price'] ?? $item['price'];
                                        $item_markup_pct       = $item['markup_percent'] ?? 0;
                                        $item_discount_pct     = $item['variant_discount'] ?? 0;
                                        $quantity              = $item['quantity'];
                                        $item_markup_amt       = $item_original * ($item_markup_pct / 100);
                                        $item_price_with_markup = $item_original + $item_markup_amt;
                                        $item_discount_amt     = $item_price_with_markup * ($item_discount_pct / 100);
                                        $item_price_after_disc = $item_price_with_markup - $item_discount_amt;
                                        $actual_subtotal       = $item['subtotal'];
                                        $actual_final_price    = $actual_subtotal / $quantity;
                                        $color_cost            = $actual_final_price - $item_price_after_disc;
                                        ?>
                                        <div class="text-xs space-y-1">
                                            <div class="text-gray-600">Base: <span class="font-medium">₱<?php echo number_format($item_original, 2); ?></span></div>
                                            <?php if ($item_markup_pct > 0): ?>
                                                <div class="text-blue-600">
                                                    <span class="font-medium">+<?php echo number_format($item_markup_pct, 1); ?>%</span>
                                                    (₱<?php echo number_format($item_markup_amt, 2); ?>)
                                                </div>
                                                <div class="text-indigo-600 font-semibold">= ₱<?php echo number_format($item_price_with_markup, 2); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item_discount_pct > 0): ?>
                                                <div class="text-orange-600">
                                                    <span class="font-medium">-<?php echo number_format($item_discount_pct, 1); ?>%</span>
                                                    (₱<?php echo number_format($item_discount_amt, 2); ?>)
                                                </div>
                                                <div class="text-purple-600 font-medium">= ₱<?php echo number_format($item_price_after_disc, 2); ?></div>
                                            <?php endif; ?>
                                            <?php if (abs($color_cost) > 0.01): ?>
                                                <div class="text-pink-600">
                                                    <span class="font-medium">Color Cost:</span>
                                                    +₱<?php echo number_format($color_cost, 2); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-green-700 font-bold text-sm pt-1 border-t">
                                                Final: ₱<?php echo number_format($actual_final_price, 2); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 text-center">
                                        <?php echo $item['quantity']; ?>
                                    </td>
                                    <td class="px-2 py-4">
                                        <?php if ($item['lt_from'] && $item['lt_to']): ?>
                                            <div class="text-xs">
                                                <div class="font-medium text-gray-700"><?php echo date('M d, Y', strtotime($item['lt_from'])); ?></div>
                                                <div class="text-gray-500">to</div>
                                                <div class="font-semibold text-blue-600"><?php echo date('M d, Y', strtotime($item['lt_to'])); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                        ₱<?php echo number_format($item['subtotal'], 2); ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm">
                                            <?php if ($item_profit > 0): ?>
                                                <span class="font-bold text-green-600">+₱<?php echo number_format($item_profit, 2); ?></span>
                                            <?php elseif ($item_profit < 0): ?>
                                                <span class="font-bold text-red-600">₱<?php echo number_format($item_profit, 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-500">₱0.00</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php
                                        $tracking_status = $item['tracking_status'] ?: 'pending';
                                        $status_display  = ucfirst(str_replace('_', ' ', $tracking_status));
                                        $status_icon = [
                                            'pending'    => 'fa-clock',
                                            'in_transit' => 'fa-truck',
                                            'delivered'  => 'fa-check-circle',
                                            'cancelled'  => 'fa-times-circle'
                                        ];
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md border <?php echo $tracking_status_colors[$tracking_status] ?? 'bg-gray-50 text-gray-700 border-gray-200'; ?>">
                                            <i class="fas <?php echo $status_icon[$tracking_status] ?? 'fa-question'; ?> mr-1"></i>
                                            <?php echo $status_display; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-2 py-8 text-center text-gray-500">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>

</html>