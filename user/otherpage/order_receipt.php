<?php
// order_receipt.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

// Validate order_id and ensure it belongs to the current user
if (!$order_id || !is_numeric($order_id)) {
    header('Location: dashboard.php');
    exit;
}

// Fetch order details
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Fetch order items
$stmt = $conn->prepare("
    SELECT * FROM order_items 
    WHERE order_id = ? 
    ORDER BY id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Calculate totals
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['subtotal'];
}
$vat_rate = 0.12; // 12% VAT
$vat_amount = $subtotal * $vat_rate;
$total_with_vat = $subtotal + $vat_amount;

// Format date
$order_date = date('F j, Y g:i A', strtotime($order['created_at']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - <?= htmlspecialchars($order['reference_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .print-shadow {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Enhanced Breadcrumb -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    <i class="fas fa-home mr-1"></i>Home
                </a>

                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="order_history" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    Recent History
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-600 font-medium">receipt</span>
                <?php if (!empty($search_keyword)): ?>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                    <span class="text-gray-500">Search: "<?= htmlspecialchars($search_keyword) ?>"</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>


    <div class="max-w-4xl mx-auto px-4">
        <!-- Header with Actions -->

        <!-- Receipt Container -->
        <div class="bg-white shadow-lg print-shadow rounded-lg overflow-hidden mt-4" id="receipt">
            <!-- Receipt Header -->
            <div class="bg-orange-600 text-white p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Order Receipt</h1>
                        <p class="text-orange-100">Thank you for your order!</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold"><?= htmlspecialchars($order['reference_no']) ?></div>
                        <div class="text-orange-100 text-sm"><?= $order_date ?></div>
                    </div>
                </div>
            </div>

            <!-- Customer & Delivery Info -->
            <div class="p-6 grid md:grid-cols-2 gap-8 border-b">
                <!-- Customer Info -->
                <div>
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Customer Information
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">Name:</span> <?= htmlspecialchars($order['customer_name']) ?></div>
                        <div><span class="font-medium">Email:</span> <?= htmlspecialchars($order['email']) ?></div>
                        <div><span class="font-medium">Mobile:</span> <?= htmlspecialchars($order['mobile']) ?></div>
                    </div>
                </div>

                <!-- Delivery Info -->
                <div>
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Delivery Address
                    </h3>
                    <div class="text-sm text-gray-700">
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                        <div class="mt-1"><span class="font-medium">ZIP Code:</span> <?= htmlspecialchars($order['zipcode']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Order Details
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-3 font-medium text-gray-700">Product</th>
                                <th class="text-left p-3 font-medium text-gray-700">Details</th>
                                <th class="text-center p-3 font-medium text-gray-700">Qty</th>
                                <th class="text-right p-3 font-medium text-gray-700">Unit Price</th>
                                <th class="text-right p-3 font-medium text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($order_items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <?php if (!empty($item['codename'])): ?>
                                            <div class="text-xs text-gray-500">Code: <?= htmlspecialchars($item['codename']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-gray-600">
                                        <div class="space-y-1">
                                            <?php if (!empty($item['type_name'])): ?>
                                                <div>Type: <?= htmlspecialchars($item['type_name']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($item['variant_color'])): ?>
                                                <div>Color: <?= htmlspecialchars($item['variant_color']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                                <div>Size: <?= htmlspecialchars($item['size']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($item['origin'])): ?>
                                                <?php
                                                $is_local = stripos($item['origin'], 'local') !== false;
                                                $origin_class = $is_local ? 'text-blue-600' : 'text-red-600';
                                                ?>
                                                <div class="<?= $origin_class ?> font-medium">Origin: <?= htmlspecialchars($item['origin']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                                                <div class="text-xs text-gray-500 italic">
                                                    <?php if (!empty($item['descrip6'])): ?>
                                                        <div><?= htmlspecialchars($item['descrip6']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['descrip7'])): ?>
                                                        <div><?= htmlspecialchars($item['descrip7']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center font-medium"><?= $item['quantity'] ?></td>
                                    <td class="p-3 text-right">₱<?= number_format($item['price'], 2) ?></td>
                                    <td class="p-3 text-right font-medium">₱<?= number_format($item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="p-6 bg-gray-50 border-t">
                <div class="max-w-sm ml-auto space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium">₱<?= number_format($subtotal, 2) ?></span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">VAT (12%):</span>
                        <span class="font-medium">₱<?= number_format($vat_amount, 2) ?></span>
                    </div>

                    <div class="flex justify-between items-center text-sm text-gray-600">
                        <span>Shipping:</span>
                        <span>To be calculated</span>
                    </div>

                    <hr class="border-gray-300">

                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total (excl. shipping):</span>
                        <span class="text-green-700">₱<?= number_format($total_with_vat, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="p-6 border-t">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span class="font-medium">Payment Method:</span>
                    <span class="text-gray-700"><?= htmlspecialchars($order['mode_payment']) ?></span>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="p-6 bg-yellow-50 border-t border-yellow-200">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-yellow-800 mb-2">Important Notes:</h4>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li>• We will review your order and contact you within 24 hours</li>
                            <li>• Final total includes 12% VAT and applicable shipping fees</li>
                            <li>• Please keep this receipt for your records</li>
                            <li>• For questions, please contact us with your reference number: <strong><?= htmlspecialchars($order['reference_no']) ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 text-center text-gray-500 text-sm border-t">
                <p>Thank you for choosing our service!</p>
                <p class="mt-1">Generated on <?= date('F j, Y g:i A') ?></p>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // Simple implementation - you can enhance this with a proper PDF library
            window.print();
        }
    </script>
</body>

</html>