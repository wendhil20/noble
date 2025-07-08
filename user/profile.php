<?php 
session_start();
include '../connection/connect.php';

// ✅ Restore session from remember_token (normal account or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        
        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: google-callback.php');
    exit;
}

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

// Fetch user's orders from database
$orders = [];
$pending_orders = [];
$all_orders = [];

if ($user_id) {
    // Get orders by email (since orders table uses email, not user_id)
    $stmt = $conn->prepare("SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
        
        // Separate pending orders
        if ($row['status'] === 'Pending') {
            $pending_orders[] = $row;
        }
        
        $all_orders[] = $row;
    }
    $stmt->close();
}

// Get order items for detailed view
function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFE15D',
                        neutral: '#1F2937',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                        'pulse-slow': 'pulse 3s infinite'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 0.9; }
            70% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">

<?php include 'navbar/top.php'; ?>

<div class="container mx-auto px-4 py-8">
    <!-- Hero Section with Profile -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary to-secondary p-8 mb-8 text-white animate-fade-in">
        <div class="absolute inset-0 bg-black/10"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-6">
            <div class="relative group">
                <div class="w-32 h-32 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center overflow-hidden border-4 border-white/30 group-hover:scale-105 transition-transform duration-300">
                    <?php if ($user_picture): ?>
                        <img src="<?php echo htmlspecialchars($user_picture); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-4xl font-bold text-white"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-success rounded-full border-4 border-white flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-4xl font-bold mb-2"><?php echo htmlspecialchars($user_name); ?></h1>
                <p class="text-xl text-white/90 mb-4"><?php echo htmlspecialchars($user_email); ?></p>
                <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                    <span class="px-4 py-2 bg-white/20 rounded-full text-sm backdrop-blur-sm">
                        Member since <?php echo date('M Y', strtotime($user_email ? '2024-01-01' : 'now')); ?>
                    </span>
                    <span class="px-4 py-2 bg-white/20 rounded-full text-sm backdrop-blur-sm">
                        <?php echo count($all_orders); ?> Orders
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Orders -->
        <div class="bg-white rounded-2xl p-6 shadow-lg card-hover animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Orders</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo count($all_orders); ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="bg-white rounded-2xl p-6 shadow-lg card-hover animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                    <p class="text-3xl font-bold text-warning"><?php echo count($pending_orders); ?></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Pending Orders Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl p-6 shadow-lg animate-fade-in">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Pending Orders</h3>
                </div>
                
                <div class="space-y-4">
                    <?php if (empty($pending_orders)): ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-500">All caught up!</p>
                            <p class="text-sm text-gray-400">No pending orders</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="border border-orange-200 rounded-lg p-4 bg-orange-50 hover:bg-orange-100 transition-colors cursor-pointer" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-900">Order #<?php echo $order['id']; ?></p>
                                        <p class="text-sm text-gray-600">₱<?php echo number_format($order['total'], 2); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                                        <span class="text-xs text-orange-600 font-medium">Pending</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Orders History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl p-6 shadow-lg animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Order list</h3>
                    </div>
                    <?php if (!empty($all_orders)): ?>
                        <span class="text-sm text-gray-500"><?php echo count($all_orders); ?> orders</span>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($all_orders)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">No orders yet</h4>
                        <p class="text-gray-600 mb-6">Start your shopping journey with us!</p>
                        <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary/90 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($all_orders as $order): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <span class="text-sm font-bold text-gray-600">#<?php echo $order['id']; ?></span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Order #<?php echo $order['id']; ?></p>
                                            <p class="text-sm text-gray-600"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-lg text-gray-900">₱<?php echo number_format($order['total'], 2); ?></p>
                                       <span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full
    <?php
        switch ($order['status']) {
            case 'Pending':
                echo 'bg-orange-100 text-orange-800';
                break;
            case 'Ongoing':
                echo 'bg-blue-100 text-blue-800';
                break;
            case 'Arrival':
                echo 'bg-purple-100 text-purple-800';
                break;
            case 'Departure':
                echo 'bg-yellow-100 text-yellow-800';
                break;
            case 'Complete':
                echo 'bg-green-100 text-green-800';
                break;
            default:
                echo 'bg-gray-100 text-gray-800';
                break;
        }
    ?>">
    <?php if ($order['status'] === 'Pending'): ?>
        <span class="w-1.5 h-1.5 bg-orange-500 rounded-full animate-pulse"></span>
    <?php elseif ($order['status'] === 'Complete'): ?>
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
        </svg>
    <?php endif; ?>
    <?php echo $order['status']; ?>
</span>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Order Details Modal -->
<div id="orderModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-3xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-slide-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Order Details</h3>
            <button onclick="closeModal()" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="orderDetails" class="space-y-6">
            <!-- Order details will be loaded here -->
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        </div>
    </div>
</div>




<script>
function viewOrder(orderId) {
    // Show modal with loading state
    document.getElementById('orderModal').classList.remove('hidden');
    document.getElementById('orderDetails').innerHTML = `
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
    `;
    
    // Load order details via AJAX
    fetch('get_order_details.php?order_id=' + orderId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('orderDetails').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('orderDetails').innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-red-600 font-medium">Error loading order details</p>
                    <p class="text-sm text-gray-500 mt-1">Please try again later</p>
                </div>
            `;
        });
}

function closeModal() {
    document.getElementById('orderModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('orderModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Add smooth scrolling and fade-in animations
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-fade-in');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

</body>
</html>