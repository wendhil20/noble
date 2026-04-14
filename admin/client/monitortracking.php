<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin', 'logistic']); // allow only admin and superadmin

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}



// Get all order IDs for dropdown
$orders = $conn->query("SELECT id FROM orders ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Get selected order ID from GET
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Noble Admin</title>
    <style>
          @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .tracking-step {
            position: relative;
        }
        
        .tracking-step::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 3rem;
            width: 2px;
            height: calc(100% - 2rem);
            background: linear-gradient(to bottom, #e5e7eb, #d1d5db);
        }
        
        .tracking-step:last-child::before {
            display: none;
        }
        
        .step-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            position: relative;
            z-index: 10;
        }
        
        .animate-pulse-soft {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        .left-panel {
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
        }
        
        .right-panel {
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
        }

        /* Auto-hide scrollbar */
        .left-panel::-webkit-scrollbar,
        .right-panel::-webkit-scrollbar {
            width: 6px;
        }

        .left-panel::-webkit-scrollbar-track,
        .right-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .left-panel::-webkit-scrollbar-thumb,
        .right-panel::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.3);
            border-radius: 3px;
            transition: opacity 0.3s ease;
        }

        .left-panel::-webkit-scrollbar-thumb:hover,
        .right-panel::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.6);
        }

        .left-panel:not(:hover)::-webkit-scrollbar-thumb,
        .right-panel:not(:hover)::-webkit-scrollbar-thumb {
            opacity: 0;
        }

        .left-panel:hover::-webkit-scrollbar-thumb,
        .right-panel:hover::-webkit-scrollbar-thumb {
            opacity: 1;
        }

        /* Firefox scrollbar */
        .left-panel,
        .right-panel {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.3) transparent;
        }

        /* Hide scrollbar completely on non-hover for Firefox */
        .left-panel:not(:hover),
        .right-panel:not(:hover) {
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-orange-50 min-h-screen">

<?php include '../navbar/top.php'; ?>
    <div class="container px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Tracking System</h1>
                    <p class="text-gray-600">Monitor and manage order deliveries</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Welcome back, <?= htmlspecialchars($_SESSION['noble_user'] ?? 'Admin') ?></p>
                    <p class="text-xs text-gray-400"><?= date('F j, Y • g:i A') ?></p>
                </div>
            </div>
        </div>

        <?php if (!$order_id): ?>
            <!-- Orders List View -->
            <div class="glass-card rounded-2xl shadow-xl p-8 slide-in">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800 flex items-center">
                        <span class="w-8 h-8 bg-brand-orange rounded-lg flex items-center justify-center text-white mr-3">📋</span>
                        All Orders
                    </h2>
                    <div class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        Total: <?= count($orders) ?> orders
                    </div>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl">📦</span>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No orders found</h3>
                        <p class="text-gray-500">Orders will appear here once they are created.</p>
                    </div>
                <?php else: ?>
                    <div class="grid gap-4">
                        <?php foreach ($orders as $index => $o): 
                            $oid = $o['id'];
                            $order = $conn->query("SELECT customer_name, created_at FROM orders WHERE id = $oid")->fetch_assoc();
                        ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300 hover:border-brand-orange/30 group">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-brand-orange to-orange-600 rounded-lg flex items-center justify-center text-white font-semibold">
                                            #<?= $oid ?>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 text-lg group-hover:text-brand-orange transition-colors">
                                                <?= htmlspecialchars($order['customer_name']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-500 flex items-center mt-1">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4m-6 0h6m-6 0a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V8a1 1 0 00-1-1H8z"/>
                                                </svg>
                                                Placed on <?= date("M d, Y • g:i A", strtotime($order['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <a href="?id=<?= $oid ?>" class="bg-brand-blue text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 group-hover:scale-105">
                                        <span>View Tracking</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Tracking Detail View -->
            <?php
            // Fetch order
            $order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
            if (!$order) die("<div class='bg-red-50 border border-red-200 rounded-xl p-6 text-center'><p class='text-red-600 font-medium'>Order not found.</p></div>");

            // Fetch items
            $items = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id")->fetch_all(MYSQLI_ASSOC);

            // Fetch tracking logs
            $tracking_logs = $conn->query("
                SELECT vt.place, vt.status, vt.timestamp,
                       vt.variant_color, vt.quantity,
                       vt.description1, vt.description2,
                       d.name AS driver_name, d.plate_number 
                FROM variant_tracking vt
                LEFT JOIN drivers d ON vt.driver_id = d.id
                WHERE vt.order_id = $order_id
                GROUP BY vt.place
                ORDER BY MIN(vt.id)
            ")->fetch_all(MYSQLI_ASSOC);

            // Define steps
            $default_steps = ['Pending', 'Ongoing', 'Arrival', 'Customs'];
            $extra_steps = array_slice(array_column($tracking_logs, 'place'), count($default_steps));
            $steps = array_merge($default_steps, $extra_steps);
            $total_logs = count($tracking_logs);
            ?>

            <div class="mb-6">
                <a href="monitortracking.php" class="inline-flex items-center text-brand-blue hover:text-blue-700 font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Order Selection
                </a>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Side - Order Details -->
                <div class="lg:col-span-2 space-y-6 left-panel">
                    
                    <!-- Order Header -->
                    <div class="glass-card shadow-xl p-8 slide-in">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    Order #<?= $order_id ?>
                                </h1>
                                <p class="text-gray-600">Customer: <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
                            </div>
                            <div class="text-right">
                                <div class="bg-gradient-to-r from-brand-orange to-orange-600 text-white px-4 py-2 rounded-lg">
                                    <p class="text-sm font-medium">Order Status</p>
                                    <p class="text-lg font-bold">
                                        <?= $total_logs > 0 ? ucfirst($tracking_logs[count($tracking_logs)-1]['status']) : 'Pending' ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg text-center border border-blue-100">
                                <div class="text-2xl font-bold text-brand-blue"><?= count($items) ?></div>
                                <div class="text-sm text-gray-600">Total Items</div>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-lg text-center border border-orange-100">
                                <div class="text-2xl font-bold text-brand-orange"><?= $total_logs ?></div>
                                <div class="text-sm text-gray-600">Tracking Steps</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg text-center border border-green-100">
                                <div class="text-2xl font-bold text-brand-green">
                                    <?= date("M d", strtotime($order['created_at'])) ?>
                                </div>
                                <div class="text-sm text-gray-600">Order Date</div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="glass-card  shadow-xl p-8 slide-in">
                        <h2 class="font-semibold text-gray-800 mb-6 flex items-center text-xl">
                            <span class="w-8 h-8 bg-brand-blue rounded-lg flex items-center justify-center text-white mr-3"></span>
                            Package Contents
                        </h2>
                        <?php if (empty($items)): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-xl">📦</span>
                                </div>
                                <p class="text-gray-500 italic">No items found for this order.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($items as $item): ?>
                                    <div class="bg-gradient-to-r from-white to-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <h3 class="font-semibold text-gray-900 text-lg"><?= htmlspecialchars($item['product_name']) ?></h3>
                                                <div class="flex items-center space-x-4 mt-2">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="w-4 h-4 rounded-full border-2 border-gray-300" style="background-color: <?= strtolower($item['variant_color']) ?>"></span>
                                                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($item['variant_color']) ?></span>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        Size: <span class="font-medium"><?= htmlspecialchars($item['size']) ?></span>
                                                    </div>
                                                </div>
                                                <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                                                    <div class="mt-3 text-sm text-gray-500 space-y-1">
                                                        <?php if (!empty($item['descrip6'])): ?>
                                                            <p><span class="font-medium">
                                                            </span> <?= htmlspecialchars($item['descrip6']) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['descrip7'])): ?>
                                                            <p><span class="font-medium">
                                                            </span> <?= htmlspecialchars($item['descrip7']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-6">
                                                <div class="bg-brand-orange text-white px-4 py-2 rounded-full text-sm font-semibold text-center">
                                                    Qty: <?= $item['quantity'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Customer Information -->
                    <div class="glass-card shadow-xl p-8 slide-in">
                        <h2 class="font-semibold text-gray-800 mb-6 flex items-center text-xl">
                            <span class="w-8 h-8 bg-brand-green rounded-lg flex items-center justify-center text-white mr-3"></span>
                            Customer Information
                        </h2>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Customer Name</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Order Date</p>
                                <p class="font-medium text-gray-900"><?= date("F j, Y • g:i A", strtotime($order['created_at'])) ?></p>
                            </div>
                            <?php if (!empty($order['phone'])): ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Phone</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($order['phone']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['address'])): ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Address</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($order['address']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Delivery Timeline -->
                <div class="lg:col-span-1 right-panel">
                    <div class="glass-card rounded-2xl shadow-xl p-6 slide-in sticky top-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="w-8 h-8 bg-brand-green rounded-lg flex items-center justify-center text-white mr-3"></span>
                            Delivery Timeline
                        </h2>

                        <?php if (empty($tracking_logs)): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-xl">📍</span>
                                </div>
                                <h3 class="text-sm font-medium text-gray-900 mb-2">No tracking data</h3>
                                <p class="text-xs text-gray-500">Updates will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-0">
                                <?php foreach ($tracking_logs as $index => $log): 
                                    $label = $steps[$index] ?? 'Step ' . ($index + 1);
                                    $reached = in_array($log['status'], ['reached', 'complete']);
                                    $is_complete = ($log['status'] === 'complete');
                                    $is_last = ($index === $total_logs - 1);
                                    $is_system_step = in_array($log['place'], ['Pending', 'Ongoing', 'Arrival', 'Customs']);
                                    $button_label = $is_last ? 'Complete' : 'Mark Reached';
                                ?>
                                    <div class="tracking-step flex items-start space-x-4 pb-6">
                                        <!-- Step Icon -->
                                        <div class="flex-shrink-0">
                                            <div class="step-icon <?= $is_complete ? 'bg-brand-green text-white' : ($reached ? 'bg-brand-orange text-white' : 'bg-gray-200 text-gray-500') ?>">
                                                <?php if ($is_complete): ?>
                                                    ✓
                                                <?php elseif ($reached): ?>
                                                    <?= $index + 1 ?>
                                                <?php else: ?>
                                                    <?= $index + 1 ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Step Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="bg-white rounded-lg p-4 border <?= $is_complete ? 'border-green-200 bg-green-50' : ($reached ? 'border-orange-200 bg-orange-50' : 'border-gray-200') ?> hover:shadow-sm transition-shadow">
                                                <div class="mb-2">
                                                    <h3 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($log['place']) ?></h3>
                                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full"><?= $label ?></span>
                                                </div>

                                                <!-- Status Badge -->
                                                <?php if ($reached): ?>
                                                    <div class="mb-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $is_complete ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' ?>">
                                                            <?= $is_complete ? '✓ Completed' : '📍 Reached' ?>
                                                        </span>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            <?= date('M d • g:i A', strtotime($log['timestamp'])) ?>
                                                        </p>
                                                    </div>
                                                <?php elseif (!$reached && $index === 0): ?>
                                                    <div class="mb-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 animate-pulse-soft">
                                                            ⏳ In Progress
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Compact Details -->
                                                <div class="space-y-1 text-xs text-gray-600">
                                                    <?php if (!$is_system_step && $log['driver_name']): ?>
                                                        <div class="flex justify-between">
                                                            <span>Driver:</span>
                                                            <span class="font-medium"><?= htmlspecialchars($log['driver_name']) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex justify-between">
                                                        <span>Color:</span>
                                                        <span class="font-medium"><?= htmlspecialchars($log['variant_color']) ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Qty:</span>
                                                        <span class="font-medium"><?= htmlspecialchars($log['quantity']) ?></span>
                                                    </div>
                                                </div>

                                                <!-- Action Button -->
                                                <?php if (!$reached): ?>
                                                    <div class="mt-3">
                                                        <form method="POST" action="mark_tracking_status.php">
                                                            <input type="hidden" name="place" value="<?= htmlspecialchars($log['place']) ?>">
                                                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                                            <input type="hidden" name="complete_delivery" value="<?= $is_last ? '1' : '0' ?>">
                                                            <button type="submit" class="w-full bg-brand-green text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-xs font-medium">
                                                                <?= $button_label ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Special Notice for Arrival -->
                                                <?php if ($log['place'] === 'Arrival' && $reached): ?>
                                                    <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-2">
                                                        <div class="text-xs text-blue-700">
                                                            <span class="font-medium">📍 Arrived in PH</span><br>
                                                            Standard delivery: 1–2 months from China
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>