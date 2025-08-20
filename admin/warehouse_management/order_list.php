<?php
//ordering.php - Orders List Page
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query
$whereConditions = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $whereConditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $whereConditions[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.id LIKE ?)";
    $searchParam = "%$search_query%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get orders with item counts
$ordersQuery = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        COUNT(oi.id) as item_count,
        SUM(CASE WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 1 ELSE 0 END) as assigned_linked_count,
        SUM(CASE WHEN oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL THEN 1 ELSE 0 END) as assigned_manual_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($ordersQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts for filter buttons
$statusCountsQuery = "
    SELECT 
        status,
        COUNT(*) as count
    FROM orders 
    GROUP BY status
";
$statusCounts = $conn->query($statusCountsQuery)->fetch_all(MYSQLI_ASSOC);
$statusCountsArray = [];
$totalOrders = 0;
foreach ($statusCounts as $status) {
    $statusCountsArray[$status['status']] = $status['count'];
    $totalOrders += $status['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Management - P.O System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-shopping-cart text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Orders Management</h1>
                        <p class="text-gray-600 mt-1">Manage and assign suppliers to orders</p>
                    </div>
                </div>
                <div class="bg-primary-50 px-4 py-2 rounded-lg">
                    <span class="text-primary-700 font-medium"><?php echo count($orders); ?> Orders</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Status Filter Buttons -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo empty($status_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        All Orders (<?php echo $totalOrders; ?>)
                    </a>
                    <?php foreach ($statusCountsArray as $status => $count): ?>
                    <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo $status_filter === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <?php echo ucfirst($status); ?> (<?php echo $count; ?>)
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search and Date Filters -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Order ID, Customer, Email..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
                
                <!-- Preserve status filter -->
                <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
            <?php
            $assignedItems = $order['assigned_linked_count'] + $order['assigned_manual_count'];
            $assignmentPercentage = $order['item_count'] > 0 ? round(($assignedItems / $order['item_count']) * 100) : 0;
            ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <!-- Order Info -->
                        <div class="flex items-start space-x-4">
                            <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg">
                                <i class="fas fa-receipt text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1">
                                    Order #<?php echo $order['id']; ?>
                                </h3>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-2"></i>
                                        <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2"></i>
                                        <span><?php echo htmlspecialchars($order['email']); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar mr-2"></i>
                                        <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Stats -->
                        <div class="text-right space-y-2">
                            <div class="text-lg font-bold text-primary-700">
                                ₱<?php echo number_format($order['total'], 2); ?>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($order['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    <?php echo $order['item_count']; ?> items
                                </span>
                            </div>
                            
                            <!-- Assignment Progress -->
                            <div class="text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-gray-600">Supplier Assignment</span>
                                    <span class="font-medium <?php echo $assignmentPercentage === 100 ? 'text-green-600' : ($assignmentPercentage > 0 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                        <?php echo $assignmentPercentage; ?>%
                                    </span>
                                </div>
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full <?php echo $assignmentPercentage === 100 ? 'bg-green-500' : ($assignmentPercentage > 0 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                         style="width: <?php echo $assignmentPercentage; ?>%"></div>
                                </div>
                                <div class="mt-1 text-gray-500">
                                    <?php echo $assignedItems; ?>/<?php echo $order['item_count']; ?> assigned
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2 ml-4">
                            <a href="po_management.php?order_id=<?php echo $order['id']; ?>" 
                               class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                <i class="fas fa-cogs"></i>
                                <span>Manage P.O.</span>
                            </a>
                            
                            <?php if ($assignmentPercentage === 100): ?>
                            <a href="generate_po.php?order_id=<?php echo $order['id']; ?>" 
                               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                <i class="fas fa-file-invoice"></i>
                                <span>Generate P.O.</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div class="text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4"></i>
                <h3 class="text-lg font-medium mb-2">No Orders Found</h3>
                <p class="text-sm">
                    <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                        No orders match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        There are no orders in the system yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                <a href="ordering.php" class="inline-block mt-4 text-primary-600 hover:text-primary-700 font-medium">
                    Clear filters and view all orders
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when status filter buttons are clicked
        document.addEventListener('DOMContentLoaded', function() {
            // Add any additional JavaScript functionality here
        });
    </script>
</body>
</html>