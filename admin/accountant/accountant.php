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

// Handle AJAX requests for order verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'verify_payment') {
        $order_id = intval($_POST['order_id']);
        
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'verified', confirmed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error verifying payment: ' . $conn->error]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'reject_payment') {
        $order_id = intval($_POST['order_id']);
        $rejection_reason = $_POST['rejection_reason'];
        
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected', rejection_reason = ?, rejection_date = CURRENT_TIMESTAMP, rejected_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $rejection_reason, $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment rejected successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error rejecting payment: ' . $conn->error]);
        }
        exit();
    }
}

// Get summary statistics
$pending_payments = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'")->fetch_assoc()['count'];
$verified_today = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()")->fetch_assoc()['count'];
$total_revenue_today = $conn->query("SELECT COALESCE(SUM(final_total), 0) as total FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()")->fetch_assoc()['total'];
$rejected_payments = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'rejected'")->fetch_assoc()['count'];

// Get orders for verification (pending payments with screenshots)
$orders_query = "
    SELECT o.*
    FROM orders o 
    WHERE o.payment_screenshot IS NOT NULL 
    ORDER BY 
        CASE o.payment_status 
            WHEN 'pending' THEN 1 
            WHEN 'verified' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        o.created_at DESC 
    LIMIT 50
";
$orders_result = $conn->query($orders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Noble Home</title>
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
    <!-- Header -->
    <header class="bg-white shadow-sm border-b-2 border-noble-orange">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator text-white text-sm"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['noble_user']); ?></span>
                    <a href="../logout.php" class="bg-noble-orange hover:bg-noble-orange-dark text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $pending_payments; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Verified Today</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $verified_today; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-noble-orange">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-peso-sign text-noble-orange text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Revenue Today</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($total_revenue_today, 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Rejected</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $rejected_payments; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Payment Verification Queue</h2>
                <p class="text-sm text-gray-600">Review and verify customer payments</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($orders_result->num_rows > 0): ?>
                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?php echo $order['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ₱<?php echo number_format($order['final_total'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?></div>
                                        <?php if ($order['bank_type']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['bank_type']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'verified' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $status = $order['payment_status'];
                                        ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$status]; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($order['payment_screenshot']): ?>
                                                <button onclick="viewScreenshot('../../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>')" 
                                                        class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['payment_status'] === 'pending'): ?>
                                                <button onclick="verifyPayment(<?php echo $order['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="rejectPayment(<?php echo $order['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" 
                                                    class="text-noble-orange hover:text-noble-orange-dark">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Screenshot Modal -->
    <div id="screenshotModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-3xl w-full max-h-96vh overflow-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Payment Screenshot</h3>
                    <button onclick="closeScreenshotModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <img id="screenshotImage" src="" alt="Payment Screenshot" class="max-w-full h-auto">
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Reject Payment</h3>
                    <button onclick="closeRejectionModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <textarea id="rejectionReason" placeholder="Please provide a reason for rejection..." 
                              class="w-full p-3 border border-gray-300 rounded-lg resize-none" rows="4"></textarea>
                    <div class="flex justify-end space-x-3 mt-4">
                        <button onclick="closeRejectionModal()" 
                                class="px-4 py-2 text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button onclick="submitRejection()" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Reject Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentOrderId = null;

        function viewScreenshot(screenshotPath) {
            document.getElementById('screenshotImage').src = screenshotPath;
            document.getElementById('screenshotModal').classList.remove('hidden');
        }

        function closeScreenshotModal() {
            document.getElementById('screenshotModal').classList.add('hidden');
        }

        function verifyPayment(orderId) {
            if (confirm('Are you sure you want to verify this payment?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_payment&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment verified successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }

        function rejectPayment(orderId) {
            currentOrderId = orderId;
            document.getElementById('rejectionModal').classList.remove('hidden');
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.getElementById('rejectionReason').value = '';
            currentOrderId = null;
        }

        function submitRejection() {
            const reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) {
                alert('Please provide a reason for rejection.');
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reject_payment&order_id=${currentOrderId}&rejection_reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment rejected successfully!');
                    closeRejectionModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function viewOrderDetails(orderId) {
            window.open('order_details.php?id=' + orderId, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        }

        // Close modals when clicking outside
        document.getElementById('screenshotModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScreenshotModal();
            }
        });

        document.getElementById('rejectionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectionModal();
            }
        });
    </script>
</body>
</html>