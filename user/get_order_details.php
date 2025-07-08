<?php
session_start();
include '../connection/connect.php';

if (!isset($_SESSION['google_logged_in'])) {
    echo '<p class="text-red-600">Please log in to view order details.</p>';
    exit;
}

$order_id = $_GET['order_id'] ?? 0;
$user_email = $_SESSION['user_email'] ?? '';

if (!$order_id) {
    echo '<p class="text-red-600">Invalid order ID.</p>';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND email = ?");
$stmt->bind_param("is", $order_id, $user_email);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    echo '<p class="text-red-600">Order not found or you don\'t have permission to view this order.</p>';
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$progress_width = match($order['status']) {
    'Pending' => 20,
    'Ongoing' => 40,
    'Arrival' => 60,
    'Departure' => 80,
    'Complete' => 100,
    default => 0,
};

$countdown_status = 'no_countdown';
$seconds_remaining = 0;
if (in_array($order['status'], ['Ongoing', 'Departure']) && !empty($order['estimated_arrival_date'])) {
    $seconds_remaining = strtotime($order['estimated_arrival_date']) - time();
    $countdown_status = $seconds_remaining > 0 ? 'active' : 'expired';
}
?>

<!-- HTML STARTS -->
<div class="space-y-4">
    <!-- ORDER INFO -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-800 mb-2">Order Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p><span class="font-medium">Order ID:</span> #<?= $order['id'] ?></p>
                <p><span class="font-medium">Date:</span> <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                <p><span class="font-medium">Status:</span>
                    <span class="px-2 py-1 text-xs rounded-full <?=
                        match($order['status']) {
                            'Pending' => 'bg-orange-100 text-orange-800',
                            'Ongoing' => 'bg-blue-100 text-blue-800',
                            'Arrival' => 'bg-purple-100 text-purple-800',
                            'Departure' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-green-100 text-green-800'
                        }
                    ?>"> <?= $order['status'] ?> </span>
                </p>
                <p><span class="font-medium">Payment Mode:</span> <?= htmlspecialchars($order['mode_payment'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p><span class="font-medium">Customer:</span> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p><span class="font-medium">Email:</span> <?= htmlspecialchars($order['email']) ?></p>
                <p><span class="font-medium">Mobile:</span> <?= htmlspecialchars($order['mobile']) ?></p>
            </div>
        </div>
    </div>

    <!-- SHIPPING -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-800 mb-2">Shipping Information</h4>
        <div class="text-sm">
            <p><span class="font-medium">Address:</span> <?= htmlspecialchars($order['address']) ?></p>
            <p><span class="font-medium">ZIP Code:</span> <?= htmlspecialchars($order['zipcode']) ?></p>
        </div>
    </div>

    <!-- ORDER ITEMS -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-800 mb-2">Order Items</h4>
        <div class="space-y-2">
            <?php foreach ($items as $item): ?>
                <div class="bg-white p-3 rounded border flex justify-between items-center">
                    <div>
                        <p class="font-medium text-sm"><?= htmlspecialchars($item['product_name']) ?></p>
                        <p class="text-xs text-gray-600">
                            <?= htmlspecialchars($item['type_name']) ?>
                            <?php if ($item['variant_color']): ?> • <?= htmlspecialchars($item['variant_color']) ?><?php endif; ?>
                            <?php if ($item['size']): ?> • Size: <?= htmlspecialchars($item['size']) ?><?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-500">Code: <?= htmlspecialchars($item['codename']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">₱<?= number_format($item['price'], 2) ?></p>
                        <p class="text-xs text-gray-600">Qty: <?= $item['quantity'] ?></p>
                        <p class="text-xs text-gray-600">Subtotal: ₱<?= number_format($item['subtotal'], 2) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TOTALS -->
    <div class="bg-orange-50 p-4 rounded-lg space-y-2 text-sm">
        <div class="flex justify-between">
            <span class="text-gray-700 font-medium">Subtotal:</span>
            <span class="text-gray-800">₱<?= number_format($order['total'], 2) ?></span>
        </div>
    
        <?php if (!empty($order['shipping_fee'])): ?>
            <div class="flex justify-between">
                <span class="text-gray-700 font-medium">Shipping Fee:</span>
                <span class="text-gray-800">₱<?= number_format($order['shipping_fee'], 2) ?></span>
            </div>
        <?php endif; ?>
        <div class="flex justify-between border-t pt-2">
            <span class="text-gray-800 font-semibold">Final Total:</span>
            <span class="text-xl font-bold text-orange-600">₱<?= number_format($order['final_total'], 2) ?></span>
        </div>
    </div>
</div>



    <!-- Order Status with Countdown -->
    <div class="bg-blue-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-800 mb-3">Order Status & Tracking</h4>

        <!-- Status Message -->
        <div class="mb-4">
            <p class="text-sm text-gray-600">
                <?php if ($order['status'] === 'Pending'): ?>
                    <span class="text-orange-600 font-medium">📋 Your order is being reviewed. We will contact you within 24 hours.</span>
                <?php elseif ($order['status'] === 'Ongoing'): ?>
                    <span class="text-blue-500 font-medium">🔄 Your order is currently being processed and prepared for shipment.</span>
                <?php elseif ($order['status'] === 'Arrival'): ?>
                    <span class="text-purple-600 font-medium">📦 Your item has arrived in Philippines and is being prepared for dispatch.</span>
                <?php elseif ($order['status'] === 'Departure'): ?>
                    <span class="text-yellow-600 font-medium">🚚 Your order has been dispatched and is on the way to you.</span>
                <?php elseif ($order['status'] === 'Complete'): ?>
                    <span class="text-green-600 font-medium">✅ Order has been completed and delivered successfully.</span>
                <?php else: ?>
                    <span class="text-gray-600">❓ Unknown order status.</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Progress Bar for All Orders -->
        <div class="mb-4">
            <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span class="<?php echo $order['status'] === 'Pending' ? 'text-orange-600 font-medium' : ''; ?>">Pending</span>
                <span class="<?php echo $order['status'] === 'Ongoing' ? 'text-blue-600 font-medium' : ''; ?>">Processing</span>
                <span class="<?php echo $order['status'] === 'Arrival' ? 'text-purple-600 font-medium' : ''; ?>">Arriving</span>
                <span class="<?php echo $order['status'] === 'Departure' ? 'text-yellow-600 font-medium' : ''; ?>">Departure</span>
                <span class="<?php echo $order['status'] === 'Complete' ? 'text-green-600 font-medium' : ''; ?>">Complete</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="<?php
                            echo $order['status'] === 'Pending' ? 'bg-orange-500' : ($order['status'] === 'Ongoing' ? 'bg-blue-500' : ($order['status'] === 'Arrival' ? 'bg-purple-500' : ($order['status'] === 'Departure' ? 'bg-yellow-500' : 'bg-green-500')));
                            ?> h-2 rounded-full transition-all duration-300"
                    style="width: <?php echo $progress_width; ?>%"></div>
            </div>
        </div>

        <!-- Countdown Timer for Ongoing and Departure Orders -->
        <?php if (($order['status'] === 'Ongoing' || $order['status'] === 'Departure') && !empty($order['estimated_arrival_date'])): ?>
            <div class="border-t pt-4">
                <div class="text-center">
                    <?php if ($countdown_status === 'expired'): ?>
                        <div class="bg-green-100 border border-green-400 rounded-lg p-4">
                            <div class="text-green-800 font-bold text-lg mb-2">
                                <?php if ($order['status'] === 'Departure'): ?>
                                    🎉 Your order should have arrived by now!
                                <?php else: ?>
                                    🎉 Your order has arrived in the Philippines!
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-green-700">
                                <?php if ($order['status'] === 'Departure'): ?>
                                    Please check with our support team for the latest update on your delivery.
                                <?php else: ?>
                                    Please wait for further instructions for pickup/delivery.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">
                                <?php if ($order['status'] === 'Departure'): ?>
                                    Estimated Delivery Time:
                                <?php else: ?>
                                    Estimated Arrival in Philippines:
                                <?php endif; ?>
                            </p>
                            <div id="modal-countdown-<?php echo $order['id']; ?>"
                                class="text-2xl font-bold <?php echo $order['status'] === 'Departure' ? 'text-yellow-600' : 'text-blue-600'; ?> mb-2"
                                data-end-time="<?php echo strtotime($order['estimated_arrival_date']) * 1000; ?>"
                                data-order-id="<?php echo $order['id']; ?>"
                                data-status="<?php echo $order['status']; ?>"
                                style="font-family: 'Courier New', monospace; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);">
                                Loading countdown...
                            </div>
                            <p class="text-xs text-gray-500 mb-3">
                                Target: <?php echo date('M d, Y H:i', strtotime($order['estimated_arrival_date'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Estimated Arrival Date (for other statuses) -->
        <?php if (!in_array($order['status'], ['Ongoing', 'Departure']) && !empty($order['estimated_arrival_date'])): ?>
            <div class="border-t pt-4">
                <p class="text-sm text-gray-600">
                    <span class="font-medium">
                        <?php if ($order['status'] === 'Complete'): ?>
                            Delivered on:
                        <?php else: ?>
                            Estimated Arrival Date:
                        <?php endif; ?>
                    </span>
                    <?php echo date('M d, Y H:i', strtotime($order['estimated_arrival_date'])); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Initialize countdown for modal
    document.addEventListener('DOMContentLoaded', function() {
        const modalCountdown = document.getElementById('modal-countdown-<?php echo $order['id']; ?>');

        if (modalCountdown) {
            const endTime = parseInt(modalCountdown.getAttribute('data-end-time'));
            const orderId = modalCountdown.getAttribute('data-order-id');
            const orderStatus = modalCountdown.getAttribute('data-status');

            function updateModalCountdown() {
                const now = new Date().getTime();
                const timeLeft = endTime - now;

                if (timeLeft > 0) {
                    const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                    let countdownText = '';
                    if (days > 0) {
                        countdownText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                    } else if (hours > 0) {
                        countdownText = `${hours}h ${minutes}m ${seconds}s`;
                    } else if (minutes > 0) {
                        countdownText = `${minutes}m ${seconds}s`;
                    } else {
                        countdownText = `${seconds}s`;
                    }

                    modalCountdown.textContent = countdownText;
                    modalCountdown.className = `text-2xl font-bold ${orderStatus === 'Departure' ? 'text-yellow-600' : 'text-blue-600'} mb-2`;
                    modalCountdown.style.animation = 'pulse 2s infinite';
                } else {
                    if (orderStatus === 'Departure') {
                        modalCountdown.textContent = '🎉 Should have arrived!';
                    } else {
                        modalCountdown.textContent = '🎉 Order has arrived!';
                    }
                    modalCountdown.className = 'text-2xl font-bold text-green-600 mb-2';
                    modalCountdown.style.animation = 'none';

                    // Show arrival notification
                    if (window.parent && window.parent.showNotification) {
                        const message = orderStatus === 'Departure' ?
                            `Order #${orderId} should have been delivered by now!` :
                            `Order #${orderId} has arrived in the Philippines!`;
                        window.parent.showNotification(message);
                    }

                    // Stop the countdown
                    clearInterval(countdownInterval);
                }
            }

            // Initial update
            updateModalCountdown();

            // Update every second
            const countdownInterval = setInterval(updateModalCountdown, 1000);

            // Clean up interval when modal is closed
            window.addEventListener('beforeunload', function() {
                clearInterval(countdownInterval);
            });
        }
    });
</script>

<style>
    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }
    }

    .countdown-timer {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }
</style>