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

// Get order details with referral information
$order_query = "
    SELECT o.*, 
           rc.referral_code as used_referral_code,
           rc.discount_type as referral_discount_type,
           rc.discount_value as referral_discount_value,
           na.fullname as referrer_name,
           na.email as referrer_email
    FROM orders o
    LEFT JOIN referral_codes rc ON o.referral_code = rc.referral_code
    LEFT JOIN nobleaccount na ON rc.user_id = na.id
    WHERE o.id = ?
";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found");
}

// Get order items with tracking status and original pricing
$items_query = "
    SELECT oi.*, 
           pv.original_price,
           pv.price as current_variant_price,
           pv.discount as variant_discount,
           pv.percent as markup_percent
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Status color mappings
$status_colors = [
    'Pending' => 'bg-yellow-100 text-yellow-800',
    'Confirmed' => 'bg-blue-100 text-blue-800',
    'Processing' => 'bg-purple-100 text-purple-800',
    'Shipped' => 'bg-indigo-100 text-indigo-800',
    'Delivered' => 'bg-green-100 text-green-800',
    'Cancelled' => 'bg-red-100 text-red-800'
];

$payment_status_colors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'verified' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800'
];

$tracking_status_colors = [
    'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'in_transit' => 'bg-blue-50 text-blue-700 border-blue-200',
    'delivered' => 'bg-green-50 text-green-700 border-green-200',
    'cancelled' => 'bg-red-50 text-red-700 border-red-200'
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
    <div class="max-w-6xl mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
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
            <div class="bg-white rounded-xl shadow-sm p-6">
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
            <div class="bg-white rounded-xl shadow-sm p-6">
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
                        <p><span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php echo $payment_status_colors[$order['payment_status']]; ?>">
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
                    <?php if ($order['estimated_arrival_date']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Estimated Arrival</label>
                        <p class="text-gray-900"><?php echo date('M d, Y h:i A', strtotime($order['estimated_arrival_date'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Referral Information (if applicable) -->
        <?php if ($order['referral_code']): ?>
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-500 rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-gift text-purple-600 mr-2"></i>Referral Discount Applied
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-600">Referral Code Used</label>
                    <p class="text-lg font-bold text-purple-700"><?php echo htmlspecialchars($order['referral_code']); ?></p>
                </div>
                <?php if ($order['referrer_name']): ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Referred By</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($order['referrer_name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['referrer_email']); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Discount Type</label>
                    <p class="text-gray-900"><?php echo ucfirst($order['referral_discount_type'] ?: 'N/A'); ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Discount Amount</label>
                    <p class="text-lg font-bold text-green-600">
                        <?php 
                        if ($order['referral_discount_amount'] > 0) {
                            echo '₱' . number_format($order['referral_discount_amount'], 2);
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Information -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <i class="fas fa-credit-card text-noble-orange mr-2"></i>Payment Information
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Payment Method -->
        <div>
            <label class="text-sm font-medium text-gray-500">Payment Method</label>
            <p class="text-gray-900 flex items-center">
                <?php 
                $payment_icons = [
                    'Bank Transfer' => 'fa-university text-purple-600',
                    'QR Payment' => 'fa-qrcode text-indigo-600',
                    'PayPal' => 'fab fa-paypal text-blue-600',
                    'PayMongo' => 'fa-mobile-alt text-green-600'
                ];
                $icon_class = $payment_icons[$order['mode_payment']] ?? 'fa-credit-card text-gray-600';
                ?>
                <i class="fas <?php echo $icon_class; ?> mr-2"></i>
                <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
            </p>
        </div>

        <!-- Bank Type (for Bank Transfer & QR Payment) -->
        <?php if (in_array($order['mode_payment'], ['Bank Transfer', 'QR Payment']) && $order['bank_type']): ?>
        <div>
            <label class="text-sm font-medium text-gray-500">Bank Type</label>
            <p class="text-gray-900"><?php echo htmlspecialchars($order['bank_type']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Reference Number (for Bank Transfer & QR Payment) -->
        <?php if (in_array($order['mode_payment'], ['Bank Transfer', 'QR Payment'])): ?>
        <div>
            <label class="text-sm font-medium text-gray-500">Reference Number</label>
            <p class="text-gray-900"><?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?></p>
        </div>
        <?php endif; ?>

        <!-- PayPal Specific Information -->
        <?php if ($order['mode_payment'] === 'PayPal'): ?>
        <div>
            <label class="text-sm font-medium text-gray-500">PayPal Order ID</label>
            <p class="text-gray-900 text-xs break-all"><?php echo htmlspecialchars($order['paypal_order_id'] ?: 'N/A'); ?></p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">PayPal Capture ID</label>
            <p class="text-gray-900 text-xs break-all"><?php echo htmlspecialchars($order['paypal_capture_id'] ?: 'N/A'); ?></p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">PayPal Payer Email</label>
            <p class="text-gray-900"><?php echo htmlspecialchars($order['paypal_payer_email'] ?: 'N/A'); ?></p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">PayPal Payer Name</label>
            <p class="text-gray-900"><?php echo htmlspecialchars($order['paypal_payer_name'] ?: 'N/A'); ?></p>
        </div>
        <?php endif; ?>

        <!-- PayMongo Specific Information -->
        <?php if ($order['mode_payment'] === 'PayMongo'): ?>
        <div class="md:col-span-2">
            <label class="text-sm font-medium text-gray-500">PayMongo Session ID</label>
            <p class="text-gray-900 text-xs break-all"><?php echo htmlspecialchars($order['paymongo_session_id'] ?: 'N/A'); ?></p>
        </div>
        <?php endif; ?>

        <!-- Payment Screenshot (for Bank Transfer & QR Payment) -->
        <?php if ($order['payment_screenshot']): ?>
        <div>
            <label class="text-sm font-medium text-gray-500">Payment Screenshot</label>
            <div class="mt-2">
                <button onclick="viewScreenshot('../../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>')" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm inline-flex items-center">
                    <i class="fas fa-eye mr-1"></i>View Screenshot
                </button>
            </div>
        </div>
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
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <i class="fas fa-calculator text-noble-orange mr-2"></i>Order Summary
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="text-center p-4 bg-gray-50 rounded-lg">
        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($order['subtotal'] ?: $order['total'], 2); ?></p>
        <p class="text-sm text-gray-600">Subtotal</p>
    </div>
    <div class="text-center p-4 bg-gray-50 rounded-lg">
        <p class="text-2xl font-bold text-purple-600">₱<?php echo number_format($order['vat_amount'], 2); ?></p>
        <p class="text-sm text-gray-600">VAT (12%)</p>
    </div>
    <div class="text-center p-4 bg-gray-50 rounded-lg">
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
// Calculate totals using ACTUAL subtotals from database
$total_original_cost = 0;
$total_selling_price = 0;
$total_item_discount = 0;
$total_markup_amount = 0;
$price_after_markup = 0;
$price_after_discount = 0;
$items_for_analysis = [];

// Store items result for reuse
$items_result->data_seek(0); // Reset pointer
while ($item = $items_result->fetch_assoc()) {
    $items_for_analysis[] = $item;
    
    $original_price = $item['original_price'] ?? $item['price'];
    $quantity = $item['quantity'];
    
    // Use ACTUAL subtotal from database (includes color costs, etc.)
    $actual_subtotal = $item['subtotal'];
    $actual_final_price = $actual_subtotal / $quantity;
    
    // Calculate markup and discount for display purposes
    $markup_percent = $item['markup_percent'] ?? 0;
    $discount_percent = $item['variant_discount'] ?? 0;
    
    $markup_amount = $original_price * ($markup_percent / 100);
    $price_with_markup = $original_price + $markup_amount;
    $discount_amount = $price_with_markup * ($discount_percent / 100);
    
    // Add to totals - use ACTUAL subtotal
    $total_original_cost += ($original_price * $quantity);
    $total_markup_amount += ($markup_amount * $quantity);
    $price_after_markup += ($price_with_markup * $quantity);
    $total_item_discount += ($discount_amount * $quantity);
    $total_selling_price += $actual_subtotal; // Use actual subtotal
    $price_after_discount += $actual_subtotal; // Use actual subtotal
}

$gross_profit = $total_selling_price - $total_original_cost;
$profit_margin = $total_selling_price > 0 ? (($gross_profit / $total_selling_price) * 100) : 0;

// Net profit after referral discount
$net_profit = $gross_profit - ($order['referral_discount_amount'] ?? 0);
?>

<div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-xl shadow-sm p-6 mb-6 border-l-4 border-green-500">
    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <i class="fas fa-chart-line text-green-600 mr-2"></i>Profit & Discount Analysis
    </h2>
    
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-xs text-gray-600 mb-1">Supplier Cost</p>
        <p class="text-xl font-bold text-gray-700">₱<?php echo number_format($total_original_cost, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Base price</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-blue-200">
        <p class="text-xs text-gray-600 mb-1">Total Markup</p>
        <p class="text-xl font-bold text-blue-600">+₱<?php echo number_format($total_markup_amount, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Added profit</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-xs text-gray-600 mb-1">After Markup</p>
        <p class="text-xl font-bold text-indigo-600">₱<?php echo number_format($price_after_markup, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Before discount</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-orange-200">
        <p class="text-xs text-gray-600 mb-1">Item Discounts</p>
        <p class="text-xl font-bold text-orange-600">-₱<?php echo number_format($total_item_discount, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Discount given</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm">
        <p class="text-xs text-gray-600 mb-1">After Discount</p>
        <p class="text-xl font-bold text-green-600">₱<?php echo number_format($price_after_discount, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Final selling price</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-purple-200">
        <p class="text-xs text-gray-600 mb-1">Referral Discount</p>
        <p class="text-xl font-bold text-purple-600">-₱<?php echo number_format($order['referral_discount_amount'] ?? 0, 2); ?></p>
        <p class="text-xs text-gray-500 mt-1">Extra discount</p>
    </div>
</div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-green-200">
            <p class="text-xs text-gray-600 mb-1">Gross Profit</p>
            <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($gross_profit, 2); ?></p>
            <p class="text-xs text-gray-500 mt-1">Before referral discount</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-blue-200">
            <p class="text-xs text-gray-600 mb-1">Net Profit</p>
            <p class="text-2xl font-bold text-blue-600">₱<?php echo number_format($net_profit, 2); ?></p>
            <p class="text-xs text-gray-500 mt-1">After all discounts</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-2 border-indigo-200">
            <p class="text-xs text-gray-600 mb-1">Profit Margin</p>
            <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($profit_margin, 2); ?>%</p>
            <p class="text-xs text-gray-500 mt-1">Gross margin percentage</p>
        </div>
    </div>
</div>

        <!-- Order Items with Tracking Status -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-shopping-cart text-noble-orange mr-2"></i>Order Items
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pricing</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
        </tr>
    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
    <?php if (count($items_for_analysis) > 0): ?>
        <?php foreach ($items_for_analysis as $item): 
            $original_price = $item['original_price'] ?? $item['price'];
$quantity = $item['quantity'];
$actual_subtotal = $item['subtotal'];
$actual_selling_price = $actual_subtotal / $quantity;
$item_profit = $actual_subtotal - ($original_price * $quantity);
        ?>
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </div>
                                        <?php if ($item['codename']): ?>
                                        <div class="text-xs text-gray-500">Code: <?php echo htmlspecialchars($item['codename']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
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
                                    <td class="px-4 py-4">
    <?php
    $item_original = $item['original_price'] ?? $item['price'];
    $item_markup_pct = $item['markup_percent'] ?? 0;
    $item_discount_pct = $item['variant_discount'] ?? 0;
    $quantity = $item['quantity'];
    
    $item_markup_amt = $item_original * ($item_markup_pct / 100);
    $item_price_with_markup = $item_original + $item_markup_amt;
    $item_discount_amt = $item_price_with_markup * ($item_discount_pct / 100);
    $item_price_after_discount = $item_price_with_markup - $item_discount_amt;
    
    // Calculate color cost from actual subtotal
    $actual_subtotal = $item['subtotal'];
    $actual_final_price = $actual_subtotal / $quantity;
    $color_cost = $actual_final_price - $item_price_after_discount;
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
        <div class="text-purple-600 font-medium">= ₱<?php echo number_format($item_price_after_discount, 2); ?></div>
        <?php endif; ?>
        
        <?php if (abs($color_cost) > 0.01): // Show if color cost exists ?>
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
                                        $status_display = ucfirst(str_replace('_', ' ', $tracking_status));
                                        $status_icon = [
                                            'pending' => 'fa-clock',
                                            'in_transit' => 'fa-truck',
                                            'delivered' => 'fa-check-circle',
                                            'cancelled' => 'fa-times-circle'
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
        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No items found</td>
    </tr>
<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div id="screenshotModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Payment Screenshot</h3>
                    <button onclick="closeScreenshotModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-4">
                    <img id="screenshotImage" src="" alt="Payment Screenshot" class="max-w-full h-auto mx-auto">
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewScreenshot(path) {
            document.getElementById('screenshotImage').src = path;
            document.getElementById('screenshotModal').classList.remove('hidden');
        }

        function closeScreenshotModal() {
            document.getElementById('screenshotModal').classList.add('hidden');
        }

        document.getElementById('screenshotModal').addEventListener('click', function(e) {
            if (e.target === this) closeScreenshotModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeScreenshotModal();
        });
    </script>
</body>
</html>