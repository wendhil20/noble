<?php
// order_history.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Pagination settings
$orders_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $orders_per_page;

// Count total orders
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $orders_per_page);
$count_stmt->close();

// Count qualifying orders (₱20,000+, Completed status only)
$tier_stmt = $conn->prepare("
    SELECT COUNT(*) as qualifying_orders 
    FROM orders 
    WHERE user_id = ? 
    AND total >= 20000 
    AND status = 'Completed'
");
$tier_stmt->bind_param("i", $user_id);
$tier_stmt->execute();
$tier_result = $tier_stmt->get_result();
$qualifying_orders = $tier_result->fetch_assoc()['qualifying_orders'];
$tier_stmt->close();

// Calculate tier information
function calculateUserTier($qualifying_orders, $conn) {
    $tier_info = [
        'tier_name' => 'Member',
        'discount_percent' => 0,
        'progress' => ($qualifying_orders / 25) * 100,
        'next_target' => 'Silver',
        'orders_needed' => max(0, 25 - $qualifying_orders),
        'benefits' => ['Standard Support', 'Regular Promotions'],
        'card_image' => null
    ];
    
    if ($qualifying_orders >= 75) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'platinum' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Platinum',
                'discount_percent' => $card['card_discount'],
                'progress' => 100,
                'next_target' => null,
                'orders_needed' => 0,
                'benefits' => ['Priority Support', 'Maximum Discount', 'Exclusive Access', 'Premium Benefits'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 50) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'gold' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Gold',
                'discount_percent' => $card['card_discount'],
                'progress' => ($qualifying_orders / 75) * 100,
                'next_target' => 'Platinum',
                'orders_needed' => 75 - $qualifying_orders,
                'benefits' => ['Enhanced Support', 'Special Discounts', 'Priority Access', 'Exclusive Offers'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 25) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'silver' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Silver',
                'discount_percent' => $card['card_discount'],
                'progress' => ($qualifying_orders / 50) * 100,
                'next_target' => 'Gold',
                'orders_needed' => 50 - $qualifying_orders,
                'benefits' => ['Priority Support', 'Member Discounts', 'Special Offers'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    }
    
    return $tier_info;
}

$user_tier = calculateUserTier($qualifying_orders, $conn);

// Fetch orders with pagination
$stmt = $conn->prepare("
    SELECT 
        id, reference_no, customer_name, email, mobile, address,
        mode_payment, total, status, created_at
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $orders_per_page, $offset);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

function getStatusBadge($status) {
    $status = strtolower($status ?? 'pending');
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'processing' => 'bg-blue-100 text-blue-800 border-blue-300',
        'confirmed' => 'bg-green-100 text-green-800 border-green-300',
        'shipped' => 'bg-purple-100 text-purple-800 border-purple-300',
        'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'cancelled' => 'bg-red-100 text-red-800 border-red-300'
    ];

    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    return "<span class='px-3 py-1 rounded-lg text-sm font-medium border {$class}'>" . ucfirst($status) . "</span>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order History - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s ease;
        }
        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            transition: width 1s ease-out;
        }
        .order-row:hover {
            background-color: #f8fafc;
        }
        .tier-qualifying {
            border-left: 4px solid #059669;
        }
        .high-value {
            border-left: 4px solid #d97706;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Breadcrumb -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-home mr-1"></i>Dashboard
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-600">Order Management</span>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-900 font-medium">Order History</span>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- User Profile with Tier Card Background -->
        <div class="relative  overflow-hidden mb-6" style="min-height: 200px;">
            <!-- Tier Card Background -->
            <?php if ($user_tier['card_image']): ?>
                <div class="absolute inset-0 bg-cover bg-center" 
                     style="background-image: url('../../uploads/<?= $user_tier['card_image'] ?>');">
                    <div class="absolute inset-0 bg-black bg-opacity-40"></div>
                </div>
            <?php else: ?>
                <div class="absolute inset-0 ">
                    <div class="absolute inset-0 "></div>
                </div>
            <?php endif; ?>
            
            <!-- Profile Content -->
            <div class="relative z-10 text-white p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <!-- User Info Section -->
                    <div class="flex items-center gap-6 text-black">
                        <!-- Avatar/Profile Picture Placeholder -->
                        <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm border-2 border-black border-opacity-30">
                            <i class="fas fa-user text-3xl text-black opacity-80 "></i>
                        </div>
                        
                        <!-- User Details -->
                        <div>
                            <h2 class="text-2xl font-bold mb-1">
                                <?php 
                                // Get user name from session or database
                                echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Member';
                                ?>
                            </h2>
                            <div class="text-lg font-medium text-black text-opacity-90 mb-2">
                                <?= $user_tier['tier_name'] ?>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-black text-opacity-80">
                                <span><i class="fas fa-shopping-cart mr-1"></i><?= $total_orders ?> Total Orders</span>
                                <span><i class="fas fa-star mr-1"></i><?= $qualifying_orders ?> Qualifying</span>
                                <span><i class="fas fa-percent mr-1"></i><?= number_format($user_tier['discount_percent'], 1) ?>% Discount</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tier Progress -->
                    <div class="mt-6 lg:mt-0 lg:w-80 text-black">
                        <?php if ($user_tier['next_target']): ?>
                            <div class="  p-4  border-opacity-30">
                                <div class="text-sm mb-2 flex justify-between">
                                    <span class="font-medium">Progress to <?= $user_tier['next_target'] ?></span>
                                    <span class="font-bold"><?= number_format($user_tier['progress'], 1) ?>%</span>
                                </div>
                                <div class="w-full bg-white bg-opacity-30 rounded-full h-3 mb-2 border border-black border-opacity-20">
                                    <div class="bg-white h-3 rounded-full progress-bar shadow-sm" 
                                         style="width: <?= min(100, $user_tier['progress']) ?>%"></div>
                                </div>
                                <p class="text-sm text-black text-opacity-90">
                                    <?= $user_tier['orders_needed'] ?> more orders needed
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-30 text-center">
                                <i class="fas fa-trophy text-2xl mb-2 text-yellow-300"></i>
                                <p class="font-bold">Maximum Tier Achieved</p>
                                <p class="text-sm text-white text-opacity-90">Highest membership level</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Benefits</h3>
                <div class="space-y-3">
                    <?php foreach ($user_tier['benefits'] as $benefit): ?>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                            <span class="text-gray-700"><?= $benefit ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tier Requirements</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-5 bg-gray-200 rounded flex items-center justify-center">
                                <i class="fas fa-user text-xs text-gray-600"></i>
                            </div>
                            <span class="text-gray-700">Member (0+ orders)</span>
                        </div>
                        <span class="text-sm text-gray-500">0% discount</span>
                    </div>
                    
                    <?php
                    $tier_cards_stmt = $conn->query("SELECT * FROM tiercard ORDER BY card_discount ASC");
                    $tier_requirements = [25, 50, 75];
                    $tier_names = ['Silver', 'Gold', 'Platinum'];
                    $tier_index = 0;
                    
                    while ($tier_card = $tier_cards_stmt->fetch_assoc()): ?>
                    <div class="flex justify-between items-center py-2 <?= $tier_index < 2 ? 'border-b border-gray-200' : '' ?>">
                        <div class="flex items-center gap-3">
                            <?php if ($tier_card['card_image']): ?>
                                <img src="../../uploads/<?= $tier_card['card_image'] ?>" 
                                     alt="<?= $tier_card['card_name'] ?> Card" 
                                     class="w-8 h-5 object-contain rounded">
                            <?php else: ?>
                                <div class="w-8 h-5 bg-gray-200 rounded flex items-center justify-center">
                                    <i class="fas fa-credit-card text-xs text-gray-600"></i>
                                </div>
                            <?php endif; ?>
                            <span class="text-gray-700"><?= ucfirst($tier_card['card_name']) ?> (<?= $tier_requirements[$tier_index] ?>+ orders)</span>
                        </div>
                        <span class="text-sm text-gray-500"><?= number_format($tier_card['card_discount'], 0) ?>% discount</span>
                    </div>
                    <?php $tier_index++; endwhile; ?>
                </div>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Only completed orders ≥ ₱20,000 count toward tier progression
                    </p>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Orders</label>
                    <input type="text" id="searchOrders" 
                           placeholder="Reference number, customer..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="shipped">Shipped</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select id="tierFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Orders</option>
                        <option value="qualifying">Tier Qualifying</option>
                        <option value="high-value">High Value (₱20K+)</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="applyFilters" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="card p-12 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-shopping-bag text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Orders Found</h3>
                <p class="text-gray-600 mb-6">You haven't placed any orders yet.</p>
                <a href="../products/" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-shopping-cart"></i>Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4" id="ordersContainer">
                <?php foreach ($orders as $order): ?>
                    <?php 
                    $is_high_value = $order['total'] >= 20000;
                    $is_completed = strtolower($order['status']) === 'completed';
                    $is_qualifying = $is_high_value && $is_completed;
                    ?>
                    <div class="card order-row <?= $is_qualifying ? 'tier-qualifying' : ($is_high_value ? 'high-value' : '') ?> p-6"
                         data-reference="<?= strtolower($order['reference_no']) ?>"
                         data-customer="<?= strtolower($order['customer_name']) ?>"
                         data-status="<?= strtolower($order['status'] ?? 'pending') ?>"
                         data-qualifying="<?= $is_qualifying ? 'true' : 'false' ?>"
                         data-high-value="<?= $is_high_value ? 'true' : 'false' ?>"
                         data-completed="<?= $is_completed ? 'true' : 'false' ?>">
                        
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
                            <div class="flex items-center gap-4 flex-wrap">
                                <h4 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($order['reference_no']) ?></h4>
                                <?= getStatusBadge($order['status'] ?? 'pending') ?>
                                
                                <?php if ($is_qualifying): ?>
                                    <span class="px-3 py-1 rounded-lg text-sm font-medium bg-green-100 text-green-800 border border-green-300">
                                        <i class="fas fa-star mr-1"></i>Tier Qualifying
                                    </span>
                                <?php elseif ($is_high_value && !$is_completed): ?>
                                    <span class="px-3 py-1 rounded-lg text-sm font-medium bg-amber-100 text-amber-800 border border-amber-300">
                                        <i class="fas fa-clock mr-1"></i>High Value
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-2xl font-bold <?= $is_qualifying ? 'text-green-700' : ($is_high_value ? 'text-amber-700' : 'text-gray-700') ?>">
                                    ₱<?= number_format($order['total'], 2) ?>
                                </div>
                                <?php if ($is_qualifying): ?>
                                    <div class="text-sm text-green-600 font-medium">+1 Tier Point</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                            <div>
                                <div class="text-sm text-gray-600">Customer</div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">Order Date</div>
                                <div class="font-medium text-gray-900"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">Payment Method</div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($order['mode_payment']) ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">Contact</div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($order['mobile']) ?></div>
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="text-sm text-gray-600 mb-1">Delivery Address</div>
                            <div class="text-gray-900"><?= htmlspecialchars($order['address']) ?></div>
                        </div>

                        <div class="flex justify-end">
                            <a href="order_receipt.php?order_id=<?= $order['id'] ?>"
                               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
                                <i class="fas fa-file-invoice"></i>View Receipt
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" 
                               class="px-3 py-2 text-sm font-medium border <?= $i == $page ? 'text-white bg-blue-600 border-blue-600' : 'text-gray-600 bg-white border-gray-300 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

             
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchOrders');
            const statusFilter = document.getElementById('statusFilter');
            const tierFilter = document.getElementById('tierFilter');
            const applyButton = document.getElementById('applyFilters');
            const ordersContainer = document.getElementById('ordersContainer');
            
            function filterOrders() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();
                const tierValue = tierFilter.value.toLowerCase();
                const orders = ordersContainer.querySelectorAll('.order-row');
                
                let visibleCount = 0;
                
                orders.forEach(order => {
                    const reference = order.dataset.reference;
                    const customer = order.dataset.customer;
                    const status = order.dataset.status;
                    const isQualifying = order.dataset.qualifying === 'true';
                    const isHighValue = order.dataset.highValue === 'true';
                    const isCompleted = order.dataset.completed === 'true';
                    
                    let showOrder = true;
                    
                    if (searchTerm && !reference.includes(searchTerm) && !customer.includes(searchTerm)) {
                        showOrder = false;
                    }
                    
                    if (statusValue && status !== statusValue) {
                        showOrder = false;
                    }
                    
                    if (tierValue) {
                        switch (tierValue) {
                            case 'qualifying':
                                if (!isQualifying) showOrder = false;
                                break;
                            case 'high-value':
                                if (!isHighValue) showOrder = false;
                                break;
                            case 'completed':
                                if (!isCompleted) showOrder = false;
                                break;
                        }
                    }
                    
                    order.style.display = showOrder ? 'block' : 'none';
                    if (showOrder) visibleCount++;
                });
                
                showNoResultsMessage(visibleCount === 0);
            }
            
            function showNoResultsMessage(show) {
                let noResultsDiv = document.getElementById('noResultsMessage');
                
                if (show && !noResultsDiv) {
                    noResultsDiv = document.createElement('div');
                    noResultsDiv.id = 'noResultsMessage';
                    noResultsDiv.className = 'card p-12 text-center mt-6';
                    noResultsDiv.innerHTML = `
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-search text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Orders Found</h3>
                        <p class="text-gray-600 mb-4">No orders match your search criteria.</p>
                        <button onclick="clearAllFilters()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
                            Clear Filters
                        </button>
                    `;
                    ordersContainer.appendChild(noResultsDiv);
                } else if (!show && noResultsDiv) {
                    noResultsDiv.remove();
                }
            }
            
            searchInput.addEventListener('input', filterOrders);
            statusFilter.addEventListener('change', filterOrders);
            tierFilter.addEventListener('change', filterOrders);
            applyButton.addEventListener('click', filterOrders);
            
            window.clearAllFilters = function() {
                searchInput.value = '';
                statusFilter.value = '';
                tierFilter.value = '';
                filterOrders();
            };
        });
    </script>
</body>
</html>