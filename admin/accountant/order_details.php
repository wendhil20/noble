<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    die("Invalid order ID");
}

// Get order details
$order_query = "SELECT * FROM orders WHERE id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    die("Order not found");
}

// Get order items
$items_query = "SELECT * FROM order_items WHERE order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo $order['id']; ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
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
                        <h1 class="text-2xl font-bold text-gray-900">Order Details #<?php echo $order['id']; ?></h1>
                        <p class="text-gray-600">Complete order information and payment details</p>
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
                    <i class="fas fa-user text-noble-orange mr-2"></i>
                    Customer Information
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
                    <i class="fas fa-info-circle text-noble-orange mr-2"></i>
                    Order Status
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Order Status</label>
                        <?php
                        $status_colors = [
                            'Pending' => 'bg-yellow-100 text-yellow-800',
                            'Confirmed' => 'bg-blue-100 text-blue-800',
                            'Processing' => 'bg-purple-100 text-purple-800',
                            'Shipped' => 'bg-indigo-100 text-indigo-800',
                            'Delivered' => 'bg-green-100 text-green-800',
                            'Cancelled' => 'bg-red-100 text-red-800'
                        ];
                        $status = $order['status'];
                        ?>
                        <p><span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php echo $status_colors[$status] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </span></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Payment Status</label>
                        <?php
                        $payment_status_colors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'verified' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $payment_status = $order['payment_status'];
                        ?>
                        <p><span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php echo $payment_status_colors[$payment_status]; ?>">
                            <?php echo ucfirst($payment_status); ?>
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

        <!-- Payment Information -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-credit-card text-noble-orange mr-2"></i>
                Payment Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment Method</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?></p>
                </div>
                <?php if ($order['bank_type']): ?>
                <div>
                    <label class="text-sm font-medium text-gray-500">Bank Type</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($order['bank_type']); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label class="text-sm font-medium text-gray-500">Reference Number</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?></p>
                </div>
                <?php if ($order['payment_screenshot']): ?>
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment Screenshot</label>
                    <div class="mt-2">
                        <button onclick="viewScreenshot('../../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-eye mr-1"></i>View Screenshot
                        </button>
                    </div>
                </div>
                <?php endif; ?>
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
                <i class="fas fa-calculator text-noble-orange mr-2"></i>
                Order Summary
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($order['subtotal'] ?: $order['total'], 2); ?></p>
                    <p class="text-sm text-gray-600">Subtotal</p>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-blue-600">₱<?php echo number_format($order['discount'], 2); ?></p>
                    <p class="text-sm text-gray-600">Discount</p>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($order['delivery_fee'] ?: $order['shipping_fee'], 2); ?></p>
                    <p class="text-sm text-gray-600">Delivery Fee</p>
                </div>
                <div class="text-center p-4 bg-noble-orange rounded-lg">
                    <p class="text-2xl font-bold text-white">₱<?php echo number_format($order['final_total'], 2); ?></p>
                    <p class="text-sm text-orange-100">Final Total</p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-shopping-cart text-noble-orange mr-2"></i>
                Order Items
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($items_result->num_rows > 0): ?>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </div>
                                        <?php if ($item['codename']): ?>
                                        <div class="text-sm text-gray-500">Code: <?php echo htmlspecialchars($item['codename']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($item['type_name']): ?>
                                        <div class="text-sm text-gray-900">Type: <?php echo htmlspecialchars($item['type_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['variant_color']): ?>
                                        <div class="text-sm text-gray-500">Color: <?php echo htmlspecialchars($item['variant_color']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['size']): ?>
                                        <div class="text-sm text-gray-500">Size: <?php echo htmlspecialchars($item['size']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['origin']): ?>
                                        <div class="text-sm text-gray-500">Origin: <?php echo htmlspecialchars($item['origin']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        ₱<?php echo number_format($item['price'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo $item['quantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        ₱<?php echo number_format($item['subtotal'], 2); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No items found</td>
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
        function viewScreenshot(screenshotPath) {
            document.getElementById('screenshotImage').src = screenshotPath;
            document.getElementById('screenshotModal').classList.remove('hidden');
        }

        function closeScreenshotModal() {
            document.getElementById('screenshotModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('screenshotModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScreenshotModal();
            }
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeScreenshotModal();
            }
        });
    </script>
</body>
</html>