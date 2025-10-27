<?php
// get_order_details.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Fetch order details
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
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

// Generate HTML
ob_start();
?>

<div class="space-y-4">
    <!-- Order Info -->
    <div class="bg-gray-50 rounded-lg p-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-700">Reference:</span>
                <div class="font-bold text-lg"><?= htmlspecialchars($order['reference_no']) ?></div>
            </div>
            <div>
                <span class="font-medium text-gray-700">Date:</span>
                <div><?= date('M j, Y - g:i A', strtotime($order['created_at'])) ?></div>
            </div>
            <div>
                <span class="font-medium text-gray-700">Status:</span>
                <div>
                    <?php
                    $status = strtolower($order['status'] ?? 'pending');
                    $badges = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'confirmed' => 'bg-green-100 text-green-800',
                        'shipped' => 'bg-purple-100 text-purple-800',
                        'delivered' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $class ?>"><?= ucfirst($status) ?></span>
                </div>
            </div>
            <div>
                <span class="font-medium text-gray-700">Payment:</span>
                <div><?= htmlspecialchars($order['mode_payment']) ?></div>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div>
        <h4 class="font-medium text-gray-900 mb-3">Order Items</h4>
        <div class="space-y-3 max-h-64 overflow-y-auto">
            <?php foreach ($order_items as $item): ?>
                <div class="border rounded-lg p-3">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <h5 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['product_name']) ?></h5>
                            <div class="text-sm text-gray-600 mt-1">
                                <?php if (!empty($item['type_name'])): ?>
                                    <div>Type: <?= htmlspecialchars($item['type_name']) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['variant_color'])): ?>
                                    <div>Color: <?= htmlspecialchars($item['variant_color']) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                    <div>Size: <?= htmlspecialchars($item['size']) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($item['codename'])): ?>
                                    <div>Code: <?= htmlspecialchars($item['codename']) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($item['origin'])): ?>
                                    <?php 
                                    $is_local = stripos($item['origin'], 'local') !== false;
                                    $origin_class = $is_local ? 'text-blue-600' : 'text-red-600';
                                    ?>
                                    <div class="<?= $origin_class ?> font-medium">Origin: <?= htmlspecialchars($item['origin']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-sm text-gray-600">₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
                            <div class="font-bold text-green-700">₱<?= number_format($item['subtotal'], 2) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Total -->
    <div class="border-t pt-4">
        <div class="flex justify-between items-center text-lg font-bold">
            <span>Total:</span>
            <span class="text-green-700">₱<?= number_format($order['total'], 2) ?></span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-2 pt-4">
        <a href="checkout-order_receipt-page-12-A.php?order_id=<?= $order['id'] ?>" 
           class="flex-1 bg-orange-600 text-white text-center py-2 rounded-lg hover:bg-orange-700 transition">
            View Full Receipt
        </a>
        <button onclick="closeQuickView()" 
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
            Close
        </button>
    </div>
</div>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
?>