<?php
// logistics_dashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if ($_POST['action'] == 'update_order_status') {
            $order_id = intval($_POST['order_id']);
            
            if ($order_id <= 0) {
                throw new Exception('Invalid order ID');
            }
            
            // Check if connection exists
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            // First verify the order exists and all items are delivered
            $check_query = "SELECT o.id, o.status,
                           COUNT(oi.id) as total_items,
                           SUM(CASE WHEN oi.tracking_status = 'delivered' THEN 1 ELSE 0 END) as delivered_items
                           FROM orders o
                           LEFT JOIN order_items oi ON o.id = oi.order_id
                           WHERE o.id = ?
                           GROUP BY o.id";
            
            $check_stmt = $conn->prepare($check_query);
            if (!$check_stmt) {
                throw new Exception('Failed to prepare verification query: ' . $conn->error);
            }
            
            $check_stmt->bind_param("i", $order_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $order_data = $check_result->fetch_assoc();
            
            if (!$order_data) {
                throw new Exception('Order not found');
            }
            
            if ($order_data['status'] === 'Completed') {
                throw new Exception('Order is already completed');
            }
            
            if ($order_data['total_items'] != $order_data['delivered_items'] || $order_data['delivered_items'] == 0) {
                throw new Exception('Not all items are delivered yet');
            }
            
            // Update order status to completed
            $update_order = $conn->prepare("UPDATE orders SET status = 'Completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?");
            if (!$update_order) {
                throw new Exception('Failed to prepare update query: ' . $conn->error);
            }
            
            $update_order->bind_param("i", $order_id);
            
            if ($update_order->execute()) {
                if ($update_order->affected_rows > 0) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Order #' . $order_id . ' marked as completed successfully']);
                } else {
                    throw new Exception('No rows were updated - order may not exist');
                }
            } else {
                throw new Exception('Failed to execute update query: ' . $update_order->error);
            }
            
        } else if ($_POST['action'] == 'get_order_details') {
            $order_id = intval($_POST['order_id']);
            
            if ($order_id <= 0) {
                throw new Exception('Invalid order ID');
            }
            
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            $query = "SELECT oi.*, o.customer_name, o.status as order_status, o.reference_no
                     FROM order_items oi 
                     JOIN orders o ON oi.order_id = o.id 
                     WHERE o.id = ?
                     ORDER BY oi.id";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare details query: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            
            if (empty($items)) {
                throw new Exception('No items found for this order');
            }
            
            echo json_encode(['success' => true, 'items' => $items]);
            
        } else {
            throw new Exception('Invalid action specified');
        }
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit();
}

// Get orders that have all items delivered but order status is not completed
$delivered_orders_query = "
    SELECT DISTINCT o.id, o.customer_name, o.email, o.mobile, o.total, o.final_total, 
           o.created_at, o.status, o.address, o.reference_no,
           COUNT(oi.id) as total_items,
           SUM(CASE WHEN oi.tracking_status = 'delivered' THEN 1 ELSE 0 END) as delivered_items
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status != 'Completed'
    GROUP BY o.id
    HAVING total_items = delivered_items AND delivered_items > 0
    ORDER BY o.created_at DESC
";

$delivered_orders = $conn->query($delivered_orders_query);

// Get recent completed orders for reference
$completed_orders_query = "
    SELECT o.id, o.customer_name, o.email, o.total, o.final_total, 
           o.created_at, o.completed_at, o.address, o.reference_no
    FROM orders o
    WHERE o.status = 'Completed'
    ORDER BY o.completed_at DESC
    LIMIT 10
";

$completed_orders = $conn->query($completed_orders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Dashboard - Order Completion</title>

    <!-- Tailwind CDN (for quick prototyping) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fff8f3',
                            100: '#fff0e6',
                            200: '#ffd8b8',
                            300: '#ffbf88',
                            400: '#ffa64f',
                            500: '#ff8a1a', /* primary orange */
                            600: '#f97316',
                            700: '#dd5f12',
                            800: '#b84a0d',
                            900: '#7a2f06'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Keep bootstrap JS for modal & tooltip behavior (we won't use Bootstrap CSS) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* Small helper to keep the table compact and readable */
        .table-fixed-compact td, .table-fixed-compact th { padding: 0.65rem 0.75rem; }
    </style>
</head>
<body class="bg-gradient-to-b from-brand-50 to-white text-slate-700 min-h-screen">
    <div class="max-w-7xl mx-auto p-6">
        <!-- Header -->
        <header class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 flex items-center justify-center rounded-lg bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow">
                    <i class="fas fa-truck fa-lg"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-800">Logistics Dashboard</h1>
                    <p class="text-sm text-slate-500">Order Completion • All items delivered</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-slate-200 shadow-sm text-sm hover:shadow"> 
                    <i class="fas fa-filter text-slate-500"></i>
                    <span class="text-slate-600">Filters</span>
                </button>
                <div class="text-right">
                    <div class="text-xs text-slate-500">Welcome,</div>
                    <div class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($_SESSION['noble_user'] ?? 'User'); ?></div>
                </div>
            </div>
        </header>

        <!-- Stats -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="rounded-lg bg-white p-4 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-slate-500">Orders Ready</div>
                        <div class="text-2xl font-semibold text-slate-800"><?php echo $delivered_orders->num_rows; ?></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="text-xs px-2 py-1 rounded bg-brand-100 text-brand-700">All items delivered</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg p-4 shadow bg-gradient-to-r from-brand-400 to-brand-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm opacity-90">Recently Completed</div>
                        <div class="text-2xl font-semibold"><?php echo $completed_orders->num_rows; ?>+</div>
                    </div>
                    <div class="text-right text-xs opacity-80">Last 10</div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-4 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-slate-500">Completed Today</div>
                        <div id="completedToday" class="text-2xl font-semibold text-slate-800">0</div>
                    </div>
                    <div>
                        <button class="px-3 py-1 rounded bg-brand-50 text-brand-700 text-sm border border-brand-100">View Report</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Orders ready table -->
        <section class="mb-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-brand-50 to-white">
                    <h2 class="font-medium text-slate-800">Orders Ready for Completion</h2>
                    <p class="text-sm text-slate-500">Orders where every item has a delivered tracking status</p>
                </div>

                <div class="p-4">
                    <?php if ($delivered_orders->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full table-fixed-compact text-sm">
                                <thead class="text-slate-600 bg-slate-50">
                                    <tr>
                                        <th class="text-left">Order</th>
                                        <th class="text-left">Reference</th>
                                        <th class="text-left">Customer</th>
                                        <th class="text-left">Contact</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-left">Order Date</th>
                                        <th class="text-left">Status</th>
                                        <th class="text-left">Items</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $delivered_orders->fetch_assoc()): ?>
                                        <tr class="border-b last:border-b-0 hover:bg-slate-50 transition" data-order-id="<?php echo $order['id']; ?>">
                                            <td class="py-3">
                                                <div class="font-semibold text-slate-800">#<?php echo $order['id']; ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['reference_no'] ?? 'N/A'); ?></td>
                                            <td>
                                                <div class="text-slate-700 font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($order['email']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['mobile']); ?></td>
                                            <td class="text-right">
                                                <div class="font-semibold">₱<?php echo number_format($order['final_total'], 2); ?></div>
                                                <div class="text-xs text-slate-400">Total: ₱<?php echo number_format($order['total'], 2); ?></div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                                    <i class="fas fa-hourglass-half mr-2"></i>
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-2"></i>
                                                    <?php echo $order['delivered_items']; ?>/<?php echo $order['total_items']; ?> Delivered
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button class="btn-complete inline-flex items-center gap-2 px-3 py-1 rounded shadow text-white bg-brand-500 hover:bg-brand-600 text-sm" onclick="markAsCompleted(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-check-double"></i>
                                                        <span>Complete</span>
                                                    </button>
                                                    <button class="inline-flex items-center gap-2 px-3 py-1 rounded border text-sm bg-white" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-eye text-slate-600"></i>
                                                        <span>Details</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-slate-500">
                            <i class="fas fa-clipboard-check fa-2x mb-3"></i>
                            <h3 class="text-lg font-medium">No orders ready for completion</h3>
                            <p class="mt-2">All orders are either already completed or have pending deliveries.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Recently completed -->
        <section>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-brand-50 to-white">
                    <h3 class="font-medium text-slate-800">Recently Completed Orders</h3>
                </div>
                <div class="p-4">
                    <?php if ($completed_orders->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm table-fixed-compact">
                                <thead class="text-slate-600 bg-slate-50">
                                    <tr>
                                        <th class="text-left">Order</th>
                                        <th class="text-left">Reference</th>
                                        <th class="text-left">Customer</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-left">Order Date</th>
                                        <th class="text-left">Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $completed_orders->fetch_assoc()): ?>
                                        <tr class="border-b last:border-b-0">
                                            <td class="py-3 font-semibold">#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['reference_no'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td class="text-right">₱<?php echo number_format($order['final_total'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo $order['completed_at'] ? date('M d, Y H:i', strtotime($order['completed_at'])) : 'N/A'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-slate-500 py-6">No recently completed orders found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>

    <!-- Order Details Modal (uses Bootstrap JS to toggle but styled with Tailwind) -->
    <div class="fixed inset-0 z-50 hidden items-center justify-center px-4" id="orderDetailsModal" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/40"></div>
        <div class="relative max-w-3xl w-full bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h5 class="text-lg font-semibold">Order Details</h5>
                <button class="text-slate-500 hover:text-slate-700" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4" id="orderDetailsContent">
                <div class="text-center py-10">
                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <div class="fixed right-6 top-6 z-60 space-y-2" id="alertContainer"></div>

    <script>
        // Modal helpers (simple, no bootstrap dependency for showing/hiding visually)
        function openModal() {
            const modal = document.getElementById('orderDetailsModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeModal() {
            const modal = document.getElementById('orderDetailsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // showAlert: small toast generator
        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            const id = 'toast-' + Date.now();
            const bg = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-rose-50 border-rose-200 text-rose-800';
            const el = document.createElement('div');
            el.id = id;
            el.className = `border ${bg} px-4 py-3 rounded shadow-sm max-w-xs`;
            el.innerHTML = `<div class="text-sm">${message}</div>`;
            container.appendChild(el);
            setTimeout(() => { el.remove(); }, 4500);
        }

        // Keep the existing AJAX functions but adapt modal show to our simple modal
        function markAsCompleted(orderId) {
            if (!confirm('Are you sure you want to mark this order as completed? This action cannot be undone.')) return;
            const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
            const button = row.querySelector('.btn-complete');
            const originalButtonContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            button.disabled = true;
            row.classList.add('opacity-60');

            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', orderId);

            fetch(window.location.href, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => { row.remove(); updateCompletedTodayCounter(); }, 900);
                    } else {
                        showAlert('error', data.message || 'Unknown error');
                        resetButton();
                    }
                } catch (e) {
                    console.error('Invalid JSON', text);
                    showAlert('error', 'Server returned invalid response');
                    resetButton();
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('error', 'Network error');
                resetButton();
            });

            function resetButton() {
                button.innerHTML = originalButtonContent;
                button.disabled = false;
                row.classList.remove('opacity-60');
            }
        }

        function viewOrderDetails(orderId) {
            document.getElementById('orderDetailsContent').innerHTML = '<div class="text-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div></div>';
            openModal();

            const formData = new FormData();
            formData.append('action', 'get_order_details');
            formData.append('order_id', orderId);

            fetch(window.location.href, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        let itemsHtml = `
                            <h6 class="mb-3 font-medium">Order #${orderId} - Items Details</h6>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-slate-600 bg-slate-50">
                                        <tr>
                                            <th class="text-left p-2">Product</th>
                                            <th class="text-left p-2">Variant</th>
                                            <th class="text-center p-2">Quantity</th>
                                            <th class="text-right p-2">Price</th>
                                            <th class="text-left p-2">Tracking Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                        data.items.forEach(item => {
                            itemsHtml += `
                                <tr class="border-b">
                                    <td class="p-2"><div class=\"font-medium\">${item.product_name || 'N/A'}</div><div class=\"text-xs text-slate-400\">${item.codename || ''}</div></td>
                                    <td class="p-2">${item.variant_color || 'N/A'}<br><small>${item.size || ''}</small></td>
                                    <td class="p-2 text-center">${item.quantity}</td>
                                    <td class="p-2 text-right">₱${parseFloat(item.price || 0).toFixed(2)}</td>
                                    <td class="p-2"> <span class=\"inline-flex items-center px-2 py-1 rounded text-xs font-medium ${item.tracking_status === 'delivered' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}\"> ${item.tracking_status || 'N/A'} </span></td>
                                </tr>`;
                        });

                        itemsHtml += `</tbody></table></div>`;
                        document.getElementById('orderDetailsContent').innerHTML = itemsHtml;
                    } else {
                        document.getElementById('orderDetailsContent').innerHTML = `<div class=\"p-4 text-sm text-rose-600\">Error: ${data.message || 'Failed to load order details'}</div>`;
                    }
                } catch (e) {
                    console.error('JSON parse error', text);
                    document.getElementById('orderDetailsContent').innerHTML = '<div class="p-4 text-sm text-rose-600">Server returned invalid response.</div>';
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('orderDetailsContent').innerHTML = `<div class="p-4 text-sm text-rose-600">Network error: ${err.message}</div>`;
            });
        }

        function updateCompletedTodayCounter() {
            const counter = document.getElementById('completedToday');
            const current = parseInt(counter.textContent) || 0;
            counter.textContent = current + 1;
        }

        // Auto-refresh after 5 minutes
        setTimeout(() => { location.reload(); }, 300000);

        // close modal on ESC
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
    </script>
</body>
</html>
