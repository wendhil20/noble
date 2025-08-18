<?php
session_name("nobleadmin");
session_start();
date_default_timezone_set('Asia/Manila');
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin']); // allow only admin and superadmin

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

$message = '';

// Auto-update expired orders
updateExpiredCountdowns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_ongoing'])) {
        $orderId = (int)$_POST['order_id'];
        $months = (int)($_POST['arrival_months'] ?? 0);
        $days = (int)($_POST['arrival_days'] ?? 0);
        $hours = (int)($_POST['arrival_hours'] ?? 0);

        if ($months == 0 && $days == 0 && $hours == 0) {
            $message = 'Error: Please specify arrival time.';
        } else {
            $result = setOrderOngoing($orderId, $months, $days, $hours);
            $message = $result['success'] ?
                "Successfully set order ID {$orderId} to Ongoing" :
                'Error: ' . $result['message'];
        }
    }

    if (isset($_POST['update_expired'])) {
        $result = updateExpiredCountdowns();
        $message = $result['success'] ?
            "Updated {$result['count']} expired orders" :
            'Error: ' . $result['message'];
    }

    if (isset($_POST['update_status'])) {
        $orderId = (int)$_POST['order_id'];
        $newStatus = $_POST['new_status'];
        $result = updateOrderStatus($orderId, $newStatus);
        $message = $result['success'] ?
            "Successfully updated order status" :
            'Error: ' . $result['message'];
    }

    if (isset($_POST['ajax_update_expired'])) {
        $result = updateExpiredCountdowns();
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

// Fixed setOrderOngoing function
function setOrderOngoing($orderId, $months, $days, $hours)
{
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Order not found');
        }

        $arrivalDate = new DateTime();
        if ($months > 0) $arrivalDate->add(new DateInterval("P{$months}M"));
        if ($days > 0) $arrivalDate->add(new DateInterval("P{$days}D"));
        if ($hours > 0) $arrivalDate->add(new DateInterval("PT{$hours}H"));

        $arrivalDateStr = $arrivalDate->format('Y-m-d H:i:s');

        // Update orders table - Only set final_total if it's NULL
        $updateStmt = $conn->prepare("
            UPDATE orders 
            SET status = 'Ongoing', 
                estimated_arrival_date = ?,
                final_total = COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0))
            WHERE id = ?
        ");
        $updateStmt->bind_param('si', $arrivalDateStr, $orderId);
        $updateStmt->execute();

        // Update variant_tracking table
        $variantStmt = $conn->prepare("UPDATE variant_tracking SET status = 'Ongoing' WHERE order_id = ?");
        $variantStmt->bind_param('i', $orderId);
        $variantStmt->execute();

        return ['success' => true, 'count' => $updateStmt->affected_rows];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fixed updateOrderStatus function
function updateOrderStatus($orderId, $newStatus)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?,
                final_total = COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0))
            WHERE id = ?
        ");
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();

        // Update variant_tracking table
        $variantStmt = $conn->prepare("UPDATE variant_tracking SET status = ? WHERE order_id = ?");
        $variantStmt->bind_param('si', $newStatus, $orderId);
        $variantStmt->execute();

        return ['success' => true, 'count' => $stmt->affected_rows];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fixed updateExpiredCountdowns function
function updateExpiredCountdowns()
{
    global $conn;

    try {
        $updateQuery = "
            UPDATE orders 
            SET status = 'Arrival',
                final_total = COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0))
            WHERE status = 'Ongoing' 
            AND estimated_arrival_date <= NOW()
        ";
        $conn->query($updateQuery);

        // Update variant_tracking table
        $variantUpdateQuery = "
            UPDATE variant_tracking
            SET status = 'Arrival'
            WHERE order_id IN (
                SELECT id FROM orders
                WHERE status = 'Arrival'
            )
        ";
        $conn->query($variantUpdateQuery);

        return ['success' => true, 'count' => $conn->affected_rows];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
// Get countdown orders
function getCountdownOrders()
{
    global $conn;

    $query = "
        SELECT 
            id,
            email,
            customer_name,
            estimated_arrival_date,
            COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0)) as total_amount,
            TIMESTAMPDIFF(SECOND, NOW(), estimated_arrival_date) as seconds_remaining,
            total,
            shipping_fee,
            discount
        FROM orders 
        WHERE status = 'Ongoing' AND estimated_arrival_date IS NOT NULL
        ORDER BY estimated_arrival_date ASC
    ";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get all orders
function getAllOrders()
{
    global $conn;

    $query = "
        SELECT id, customer_name, email, status, estimated_arrival_date, created_at,
               COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0)) as final_total,
               total, shipping_fee, discount
        FROM orders 
        ORDER BY created_at DESC
    ";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Order stats
function getOrderStats()
{
    global $conn;

    $query = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(COALESCE(final_total, total + COALESCE(shipping_fee, 0) - COALESCE(discount, 0))) as total_amount
        FROM orders 
        GROUP BY status
    ";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get data
$countdownOrders = getCountdownOrders();
$allOrders = getAllOrders();
$orderStats = getOrderStats();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .countdown-timer {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .expired {
            color: #DC2626;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .status-badge {
            @apply px-3 py-1 text-xs font-medium rounded-full;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>

<body class="bg-gray-50">

    <?php include '../navbar/top.php'; ?>
    <div class="min-h-screen">

        <div class="container mx-auto px-6 py-8">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg border-l-4 <?php echo strpos($message, 'Error:') === 0 ? 'bg-red-50 border-red-500 text-red-700' : 'bg-green-50 border-green-500 text-green-700'; ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <?php if (strpos($message, 'Error:') === 0): ?>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            <?php else: ?>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            <?php endif; ?>
                        </svg>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Set Ongoing Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border">
                <h2 class="text-2xl font-semibold mb-6 text-gray-800">Set time</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2 text-gray-700">Order ID:</label>
                        <input type="number" name="order_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Months:</label>
                        <input type="number" name="arrival_months" min="0" max="12" value="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Days:</label>
                        <input type="number" name="arrival_days" min="0" max="31" value="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Hours:</label>
                        <input type="number" name="arrival_hours" min="0" max="23" value="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="set_ongoing"
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg w-full font-medium transition-all duration-200 transform hover:scale-105">
                            Set arrival time
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <!-- Countdown Orders - MODIFIED to show individual orders -->
                <div class="bg-white rounded-xl shadow-lg p-6 border">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Active Countdowns</h2>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            <?php echo count($countdownOrders); ?> orders
                        </span>
                    </div>

                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php if (count($countdownOrders) > 0): ?>
                            <?php foreach ($countdownOrders as $order): ?>
                                <div class="border border-gray-200 p-4 rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold">
                                                    ID: <?php echo $order['id']; ?>
                                                </span>
                                                <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></h4>
                                            </div>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($order['email']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="Arrival">
                                                <button type="submit" name="update_status" value="1"
                                                    onclick="return confirm('Mark Order ID <?php echo $order['id']; ?> as Arrival?')"
                                                    class="bg-purple-600 text-white text-xs px-3 py-1 rounded hover:bg-purple-700 transition">
                                                    Mark Arrival
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-700">Total Amount:</span>
                                            <span class="font-bold text-lg">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                        </div>

                                        <div class="text-center">
                                            <div class="countdown-timer <?php echo $order['seconds_remaining'] <= 0 ? 'expired text-red-600' : 'text-blue-600'; ?>"
                                                data-seconds="<?php echo $order['seconds_remaining']; ?>"
                                                data-order-id="<?php echo $order['id']; ?>">
                                                <?php
                                                $seconds = $order['seconds_remaining'];
                                                if ($seconds > 0) {
                                                    $days = floor($seconds / 86400);
                                                    $hours = floor(($seconds % 86400) / 3600);
                                                    $minutes = floor(($seconds % 3600) / 60);
                                                    $secs = $seconds % 60;
                                                    echo "{$days}d {$hours}h {$minutes}m {$secs}s";
                                                } else {
                                                    echo "EXPIRED";
                                                }
                                                ?>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Target: <?php echo date('M d, Y H:i', strtotime($order['estimated_arrival_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-gray-500">No active countdowns.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Orders -->
                <div class="bg-white rounded-xl shadow-lg p-6 border">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">All Orders</h2>
                        <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                            <?php echo count($allOrders); ?> total
                        </span>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">ID</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">Customer</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">Email</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">Total</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">Status</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allOrders as $order): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-3 font-medium"><?php echo $order['id']; ?></td>
                                        <td class="px-3 py-3"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td class="px-3 py-3 text-gray-600"><?php echo htmlspecialchars($order['email']); ?></td>
                                        <td class="px-3 py-3 font-medium">₱<?php echo number_format($order['final_total'], 2); ?></td>
                                        <td class="px-3 py-3">
                                            <span class="status-badge <?php
                                                                        echo match (strtolower($order['status'])) {
                                                                            'pending' => 'bg-orange-100 text-orange-800',
                                                                            'ongoing' => 'bg-blue-100 text-blue-800',
                                                                            'arrival' => 'bg-purple-100 text-purple-800',
                                                                            'departure' => 'bg-indigo-100 text-indigo-800',
                                                                            'complete' => 'bg-green-100 text-green-800',
                                                                            'rejected' => 'bg-red-100 text-red-800',
                                                                            default => 'bg-gray-100 text-gray-800'
                                                                        };
                                                                        ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="new_status" onchange="this.form.submit()"
                                                    class="text-xs px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                    <option value="">Change Status</option>
                                                    <option value="Pending">Pending</option>
                                                    <option value="Ongoing">Ongoing</option>
                                                    <option value="Arrival">Arrival</option>
                                                    <option value="Departure">Departure</option>
                                                    <option value="Complete">Complete</option>
                                                    <option value="Rejected">Rejected</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let hasExpiredOrders = false;

        function updateCountdowns() {
            document.querySelectorAll('.countdown-timer').forEach(timer => {
                let seconds = parseInt(timer.dataset.seconds);
                if (seconds > 0) {
                    seconds--;
                    timer.dataset.seconds = seconds;

                    const days = Math.floor(seconds / 86400);
                    const hours = Math.floor((seconds % 86400) / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = seconds % 60;

                    timer.textContent = `${days}d ${hours}h ${minutes}m ${secs}s`;
                } else {
                    if (!timer.classList.contains('expired')) {
                        timer.textContent = 'EXPIRED';
                        timer.classList.add('expired');
                        timer.classList.remove('text-blue-600');
                        timer.classList.add('text-red-600');
                        hasExpiredOrders = true;
                    }
                }
            });

            // If there are expired orders, update the database
            if (hasExpiredOrders) {
                updateExpiredOrders();
                hasExpiredOrders = false;
            }
        }

        function updateExpiredOrders() {
            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_update_expired=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        // Show notification
                        showNotification(`${data.count} orders updated to Arrival status`);

                        // Reload the page to show updated status
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error updating expired orders:', error);
                });
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Start the countdown
        setInterval(updateCountdowns, 1000);

     
    </script>
</body>

</html>