<?php
// client/track_order.php
session_start();
require_once '../../connection/connect.php';

$message = '';
$orderData = null;

// Handle order lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_order'])) {
    $email = trim($_POST['email']);
    $orderId = (int)$_POST['order_id'];
    
    if (empty($email) || $orderId <= 0) {
        $message = 'Please provide both email and order ID.';
    } else {
        $orderData = getClientOrderData($email, $orderId);
        if (!$orderData) {
            $message = 'No order found with the provided email and order ID.';
        }
    }
}

function getClientOrderData($email, $orderId) {
    global $conn;
    
    try {
        // Get the specific order
        $stmt = $conn->prepare("
            SELECT id, customer_name, email, mobile, total, status, created_at, estimated_arrival_date 
            FROM orders 
            WHERE id = ? AND email = ?
        ");
        $stmt->bind_param('is', $orderId, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $mainOrder = $result->fetch_assoc();
        
        // Get order items
        $itemsStmt = $conn->prepare("
            SELECT product_name, quantity, price
            FROM order_items
            WHERE order_id = ?
        ");
        $itemsStmt->bind_param('i', $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        $orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
        
        // Get status history
        $historyStmt = $conn->prepare("
            SELECT status, changed_at, changed_by
            FROM order_status_history
            WHERE order_id = ?
            ORDER BY changed_at DESC
        ");
        $historyStmt->bind_param('i', $orderId);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        $statusHistory = $historyResult->fetch_all(MYSQLI_ASSOC);
        
        return [
            'main_order' => $mainOrder,
            'order_items' => $orderItems,
            'status_history' => $statusHistory
        ];
        
    } catch (Exception $e) {
        error_log("Error in getClientOrderData: " . $e->getMessage());
        return null;
    }
}

function getStatusMessage($status) {
    switch (strtolower($status)) {
        case 'pending':
            return [
                'message' => 'Your order is being processed and will be shipped soon.',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-50',
                'icon' => '📋'
            ];
        case 'ongoing':
            return [
                'message' => 'Your order is currently being shipped to the Philippines.',
                'color' => 'text-blue-600',
                'bg' => 'bg-blue-50',
                'icon' => '🚢'
            ];
        case 'arrival':
            return [
                'message' => 'Your order has arrived in the Philippines and will be delivered soon.',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-50',
                'icon' => '🎉'
            ];
        case 'departure':
            return [
                'message' => 'Your order is out for delivery.',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-50',
                'icon' => '🚚'
            ];
        case 'complete':
            return [
                'message' => 'Your order has been successfully delivered!',
                'color' => 'text-green-600',
                'bg' => 'bg-green-50',
                'icon' => '✅'
            ];
        case 'rejected':
            return [
                'message' => 'Your order has been cancelled. Please contact customer service.',
                'color' => 'text-red-600',
                'bg' => 'bg-red-50',
                'icon' => '❌'
            ];
        default:
            return [
                'message' => 'Order status unknown.',
                'color' => 'text-gray-600',
                'bg' => 'bg-gray-50',
                'icon' => '❓'
            ];
    }
}

function getTimeRemaining($estimatedArrival) {
    if (!$estimatedArrival) return null;
    
    $now = new DateTime();
    $arrival = new DateTime($estimatedArrival);
    $diff = $now->diff($arrival);
    
    if ($arrival <= $now) {
        return "Package should arrive soon!";
    }
    
    $days = $diff->days;
    $hours = $diff->h;
    $minutes = $diff->i;
    
    if ($days > 0) {
        return "{$days} days, {$hours} hours remaining";
    } elseif ($hours > 0) {
        return "{$hours} hours, {$minutes} minutes remaining";
    } else {
        return "{$minutes} minutes remaining";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .progress-bar {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            transition: width 0.3s ease;
        }
        .status-step {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
        }
        .status-step.active {
            background: #3b82f6;
            color: white;
        }
        .status-step.completed {
            background: #10b981;
            color: white;
        }
        .status-step.pending {
            background: #f3f4f6;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }
        .refresh-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        .refresh-button:hover {
            background: #2563eb;
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Track Your Order</h1>
                <p class="text-gray-600">Enter your email and order ID to track your shipment</p>
            </div>

            <!-- Order Lookup Form -->
            <?php if (!$orderData): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Find Your Order</h2>
                    
                    <?php if (!empty($message)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address:</label>
                            <input type="email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter your email">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order ID:</label>
                            <input type="number" name="order_id" required min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter order ID">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="lookup_order" value="1"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                                Track Order
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Order Details -->
            <?php if ($orderData): ?>
                <div class="space-y-6">
                    <!-- Order Status Progress -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Order Status</h2>
                        
                        <div class="flex items-center justify-between mb-8">
                            <?php
                            $steps = ['Pending', 'Ongoing', 'Arrival', 'Departure', 'Complete'];
                            $currentStatus = $orderData['main_order']['status'];
                            $currentStep = array_search($currentStatus, $steps);
                            if ($currentStep === false) $currentStep = 0;
                            ?>
                            
                            <?php foreach ($steps as $index => $step): ?>
                                <div class="flex flex-col items-center">
                                    <div class="status-step <?php 
                                        if ($index < $currentStep) echo 'completed';
                                        elseif ($index == $currentStep) echo 'active';
                                        else echo 'pending';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <span class="text-xs text-gray-600 mt-2"><?php echo $step; ?></span>
                                </div>
                                <?php if ($index < count($steps) - 1): ?>
                                    <div class="flex-1 mx-2">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo ($index < $currentStep) ? '100%' : '0%'; ?>"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <?php
                        $statusInfo = getStatusMessage($orderData['main_order']['status']);
                        ?>
                        <div class="<?php echo $statusInfo['bg']; ?> border-l-4 border-blue-500 p-4 rounded">
                            <div class="flex items-center">
                                <span class="text-2xl mr-3"><?php echo $statusInfo['icon']; ?></span>
                                <p class="<?php echo $statusInfo['color']; ?> font-semibold text-lg">
                                    <?php echo $statusInfo['message']; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Estimated Arrival Info -->
                        <?php if ($orderData['main_order']['estimated_arrival_date'] && in_array($currentStatus, ['Ongoing', 'Arrival'])): ?>
                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h3 class="font-semibold text-blue-800 mb-2">Estimated Arrival</h3>
                                <p class="text-blue-700">
                                    <?php echo date('F d, Y \a\t h:i A', strtotime($orderData['main_order']['estimated_arrival_date'])); ?>
                                </p>
                                <?php 
                                $timeRemaining = getTimeRemaining($orderData['main_order']['estimated_arrival_date']);
                                if ($timeRemaining):
                                ?>
                                    <p class="text-sm text-blue-600 mt-1"><?php echo $timeRemaining; ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Order Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-2">Customer Details</h3>
                                <p class="text-sm text-gray-600">Name: <?php echo htmlspecialchars($orderData['main_order']['customer_name']); ?></p>
                                <p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($orderData['main_order']['email']); ?></p>
                                <p class="text-sm text-gray-600">Mobile: <?php echo htmlspecialchars($orderData['main_order']['mobile']); ?></p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-2">Order Details</h3>
                                <p class="text-sm text-gray-600">Order ID: #<?php echo $orderData['main_order']['id']; ?></p>
                                <p class="text-sm text-gray-600">Order Date: <?php echo date('F d, Y', strtotime($orderData['main_order']['created_at'])); ?></p>
                                <p class="text-sm text-gray-600">Total: ₱<?php echo number_format($orderData['main_order']['total'], 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Items</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Product</th>
                                        <th class="text-center py-3 px-4 font-semibold text-gray-700">Quantity</th>
                                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderData['order_items'] as $item): ?>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td class="text-center py-3 px-4"><?php echo $item['quantity']; ?></td>
                                            <td class="text-right py-3 px-4">₱<?php echo number_format($item['price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Status History -->
                    <?php if (!empty($orderData['status_history'])): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Status History</h2>
                            <div class="space-y-3">
                                <?php foreach ($orderData['status_history'] as $history): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <span class="font-medium text-gray-800"><?php echo ucfirst($history['status']); ?></span>
                                            <span class="text-sm text-gray-500 ml-2">
                                                by <?php echo htmlspecialchars($history['changed_by']); ?>
                                            </span>
                                        </div>
                                        <span class="text-sm text-gray-500">
                                            <?php echo date('M d, Y h:i A', strtotime($history['changed_at'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Contact Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Need Help?</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-blue-800 mb-2">📞 Customer Support</h3>
                                <p class="text-sm text-blue-700">Call us: +63 123 456 7890</p>
                                <p class="text-sm text-blue-700">Hours: 9AM - 6PM (Mon-Fri)</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-green-800 mb-2">📧 Email Support</h3>
                                <p class="text-sm text-green-700">support@yourstore.com</p>
                                <p class="text-sm text-green-700">Response within 24 hours</p>
                            </div>
                        </div>
                    </div>

                    <!-- Track Another Order -->
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <form method="POST">
                            <button type="submit" name="track_another" value="1"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                                Track Another Order
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Refresh Button -->
                <button onclick="location.reload()" class="refresh-button" title="Refresh page">
                    🔄
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Optional: Add a subtle notification when page loads if there's order data
        <?php if ($orderData): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // You can add any client-side enhancements here
                console.log('Order tracking loaded for Order #<?php echo $orderData["main_order"]["id"]; ?>');
            });
        <?php endif; ?>
    </script>
</body>
</html>