<?php
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['admin', 'superadmin']); // allow only admin and superadmin


// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$show_items = isset($_GET['show_items']) ? $_GET['show_items'] : '';

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.address LIKE ? OR o.mobile LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get unique emails first to prevent duplicates
$unique_query = "SELECT DISTINCT o.email FROM orders o $where_clause";
$unique_stmt = $conn->prepare($unique_query);
if (!empty($params)) {
    $unique_stmt->bind_param($types, ...$params);
}
$unique_stmt->execute();
$unique_emails = $unique_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total record count (based on unique emails)
$total_records = count($unique_emails);
$total_pages = ceil($total_records / $records_per_page);

// Get paginated unique emails
$paginated_emails = array_slice($unique_emails, $offset, $records_per_page);

// Build email list for query
$email_list = array_column($paginated_emails, 'email');
$email_placeholders = str_repeat('?,', count($email_list) - 1) . '?';

// Fetch orders for unique emails only (get the latest order for each email)
$final_orders = [];
if (!empty($email_list)) {
    $query = "SELECT o.*, 
                     ROW_NUMBER() OVER (PARTITION BY o.email ORDER BY o.created_at DESC) as rn
              FROM orders o 
              WHERE o.email IN ($email_placeholders) 
              ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($email_list)), ...$email_list);
    $stmt->execute();
    $all_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Filter to get only the latest order for each email
    foreach ($all_results as $row) {
        if ($row['rn'] == 1) {
            $final_orders[] = $row;
        }
    }
}

// For filters (status only in this table)
$status_options = $conn->query("SELECT DISTINCT status FROM orders WHERE status IS NOT NULL ORDER BY status")->fetch_all(MYSQLI_ASSOC);

// Function to get order items
function getOrderItems($conn, $order_id) {
    $query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get all orders for a specific email
function getOrdersByEmail($conn, $email) {
    $query = "SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to check if email has multiple orders
function hasMultipleOrders($conn, $email) {
    $query = "SELECT COUNT(*) as count FROM orders WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 1;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Client Information Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            const alertBox = document.getElementById('alertBox');
            if (alertBox) alertBox.style.display = 'none';
        }, 5000);

        // Toggle order items visibility
        function toggleOrderItems(orderId) {
            const itemsRow = document.getElementById('items-' + orderId);
            const toggleBtn = document.getElementById('toggle-' + orderId);
            const icon = toggleBtn.querySelector('i');
            
            if (itemsRow.style.display === 'none' || itemsRow.style.display === '') {
                itemsRow.style.display = 'table-row';
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
                toggleBtn.title = 'Hide Items';
            } else {
                itemsRow.style.display = 'none';
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
                toggleBtn.title = 'Show Items';
            }
        }

        // Toggle order list visibility for duplicate emails
        function toggleOrderList(email) {
            const orderListRow = document.getElementById('orders-' + btoa(email));
            const toggleBtn = document.getElementById('toggle-orders-' + btoa(email));
            const icon = toggleBtn.querySelector('i');
            
            if (orderListRow.style.display === 'none' || orderListRow.style.display === '') {
                orderListRow.style.display = 'table-row';
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
                toggleBtn.title = 'Hide Order List';
            } else {
                orderListRow.style.display = 'none';
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
                toggleBtn.title = 'Show Order List';
            }
        }

        // Export to CSV function (enhanced with items)
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tr:not(.items-row):not(.orders-row)'));
            const csv = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    const text = cell.textContent.trim();
                    return text.includes(',') ? `"${text}"` : text;
                }).join(',');
            }).join('\n');

            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'client_info_' + new Date().toISOString().split('T')[0] + '.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Print function
        function printTable() {
            window.print();
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }
    </script>
    <style>
        @media print {
            .no-print {
                display: none;
            }

            .print-title {
                display: block !important;
            }
        }
        
        .items-row {
            background-color: #f8fafc;
        }
        
        .orders-row {
            background-color: #f1f5f9;
        }
        
        .items-container {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .orders-container {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-sans">

    <?php include '../navbar/top.php'; ?>

    <!-- Access Denied Alert -->
    <?php if (isset($_SESSION['access_denied'])): ?>
        <div id="alertBox" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md max-w-md mx-auto mt-6 text-sm shadow-md">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <div>
                    <strong class="font-bold">Access Denied:</strong>
                    <span class="block"><?php echo htmlspecialchars($_SESSION['access_denied']); ?></span>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['access_denied']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="px-4 sm:px-6 lg:px-8 mt-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Client Information Management</h1>
            <p class="text-gray-600">Manage and view unique client records with order details</p>
        </div>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 no-print">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="search" name="search"
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name, email, address, or contact..."
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Status</option>
                            <?php foreach ($status_options as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['status']); ?>"
                                    <?php echo $status_filter === $option['status'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Show Items Option -->
                    <div>
                        <label for="show_items" class="block text-sm font-medium text-gray-700 mb-1">Display Options</label>
                        <select id="show_items" name="show_items" class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Hide Items</option>
                            <option value="1" <?php echo $show_items === '1' ? 'selected' : ''; ?>>Show All Items</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <button type="button" onclick="resetFilters()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-undo mr-2"></i>Reset
                    </button>
                    <button type="button" onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                    <button type="button" onclick="printTable()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition duration-200">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </form>
        </div>

       
        <!-- Table Container -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left text-gray-700">
                    <thead class="bg-blue-600 text-white">
                        <tr>
                            <th class="px-4 py-3">Actions</th>
                            <th class="px-4 py-3">Latest Order #</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Mobile</th>
                            <th class="px-4 py-3">Address</th>
                            <th class="px-4 py-3">Zipcode</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Status</th>
                            
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($final_orders)): ?>
                            <?php foreach ($final_orders as $row): ?>
                                <?php $has_multiple_orders = hasMultipleOrders($conn, $row['email']); ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2 ">
                                            <button id="toggle-<?php echo $row['id']; ?>" 
                                                    onclick="toggleOrderItems(<?php echo $row['id']; ?>)"
                                                    class="text-blue-600 hover:text-blue-800 transition duration-200" 
                                                    title="Show Items">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <?php if ($has_multiple_orders): ?>
                                                <button id="toggle-orders-<?php echo base64_encode($row['email']); ?>" 
                                                        onclick="toggleOrderList('<?php echo htmlspecialchars($row['email']); ?>')"
                                                        class="text-green-600 hover:text-green-800 transition duration-200" 
                                                        title="Show Order List">
                                                    <i class="fas fa-list"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <span class="font-medium text-blue-600">#<?php echo $row['id']; ?></span>
                                            <?php if ($has_multiple_orders): ?>
                                                <span class="ml-2 px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">
                                                    Multiple Orders
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row['mobile']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row['zipcode']); ?></td>
                                    <td class="px-4 py-3 ">₱<?php echo number_format($row['total'], 2); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium 
                        <?php
                                switch (strtolower($row['status'])) {
                                    case 'pending':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'confirmed':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'cancelled':
                                        echo 'bg-red-100 text-red-800';
                                        break;
                                    case 'arrival':
                                        echo 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'departure':
                                        echo 'bg-purple-100 text-purple-800';
                                        break;
                                    case 'complete':
                                        echo 'bg-gray-200 text-gray-800';
                                        break;
                                    default:
                                        echo 'bg-gray-100 text-gray-600';
                                }
                        ?>">
                                            <?php echo htmlspecialchars(ucwords($row['status'])); ?>
                                        </span>
                                    </td>
                                
                                </tr>
                                
                                <!-- Order Items Row -->
                                <tr id="items-<?php echo $row['id']; ?>" class="items-row" style="display: <?php echo $show_items === '1' ? 'table-row' : 'none'; ?>;">
                                    <td colspan="10" class="px-4 py-3 bg-gray-50">
                                        <div class="items-container">
                                            <h4 class="font-semibold text-gray-800 mb-2">
                                                <i class="fas fa-shopping-cart mr-1"></i>
                                                Order Items for Order #<?php echo $row['id']; ?>:
                                            </h4>
                                            <?php 
                                            $order_items = getOrderItems($conn, $row['id']);
                                            if (!empty($order_items)): ?>
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                    <?php foreach ($order_items as $item): ?>
                                                        <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                                                            <div class="flex justify-between items-start mb-2">
                                                                <h5 class="font-medium text-gray-800 text-sm">
                                                                    <?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?>
                                                                </h5>
                                                                <?php if (!empty($item['code'])): ?>
                                                                    <span class="text-xs text-gray-500">
                                                                        <?php echo htmlspecialchars($item['code']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <?php if (!empty($item['variant_color'])): ?>
                                                                <div class="text-xs text-gray-600 mb-1">
                                                                    <i class="fas fa-palette mr-1"></i>
                                                                    Color: <?php echo htmlspecialchars($item['variant_color']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($item['type_name'])): ?>
                                                                <div class="text-xs text-gray-600 mb-1">
                                                                    <i class="fas fa-tag mr-1"></i>
                                                                    Type: <?php echo htmlspecialchars($item['type_name']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($item['name'])): ?>
                                                                <div class="text-xs text-gray-600 mb-1">
                                                                    <i class="fas fa-info-circle mr-1"></i>
                                                                    Name: <?php echo htmlspecialchars($item['name']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-100">
                                                                <div class="text-xs text-gray-600">
                                                                    Qty: <?php echo $item['quantity'] ?? 0; ?>
                                                                </div>
                                                                <div class="text-xs text-gray-600">
                                                                    ₱<?php echo number_format($item['price'] ?? 0, 2); ?>
                                                                </div>
                                                                <div class="text-sm font-medium text-gray-800">
                                                                    ₱<?php echo number_format($item['subtotal'] ?? 0, 2); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-gray-500 text-sm">No items found for this order.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Order List Row (only shown if multiple orders exist) -->
                                <?php if ($has_multiple_orders): ?>
                                    <tr id="orders-<?php echo base64_encode($row['email']); ?>" class="orders-row" style="display: none;">
                                        <td colspan="10" class="px-4 py-3 bg-slate-50">
                                            <div class="orders-container">
                                                <h4 class="font-semibold text-gray-800 mb-2">
                                                    <i class="fas fa-list mr-1"></i>
                                                    All Orders for <?php echo htmlspecialchars($row['email']); ?>:
                                                </h4>
                                                <?php 
                                                $all_orders = getOrdersByEmail($conn, $row['email']);
                                                if (!empty($all_orders)): ?>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        <?php foreach ($all_orders as $order): ?>
                                                            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                                                                <div class="flex justify-between items-start mb-2">
                                                                    <h5 class="font-medium text-gray-800 text-sm">
                                                                        Order #<?php echo $order['id']; ?>
                                                                    </h5>
                                                                    <span class="px-2 py-1 rounded-full text-xs font-medium 
                                                                    <?php
                                                                    switch (strtolower($order['status'])) {
                                                                        case 'pending':
                                                                            echo 'bg-yellow-100 text-yellow-800';
                                                                            break;
                                                                        case 'confirmed':
                                                                            echo 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'cancelled':
                                                                            echo 'bg-red-100 text-red-800';
                                                                            break;
                                                                        case 'arrival':
                                                                            echo 'bg-blue-100 text-blue-800';
                                                                            break;
                                                                        case 'departure':
                                                                            echo 'bg-purple-100 text-purple-800';
                                                                            break;
                                                                        case 'complete':
                                                                            echo 'bg-gray-200 text-gray-800';
                                                                            break;
                                                                        default:
                                                                            echo 'bg-gray-100 text-gray-600';
                                                                    }
                                                                    ?>">
                                                                        <?php echo htmlspecialchars(ucwords($order['status'])); ?>
                                                                    </span>
                                                                </div>
                                                                
                                                                <div class="text-xs text-gray-600 mb-1">
                                                                    <i class="fas fa-calendar mr-1"></i>
                                                                    Order Date: <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                                                </div>
                                                                
                                                                <div class="text-xs text-gray-600 mb-1">
                                                                    <i class="fas fa-truck mr-1"></i>
                                                                    Est. Arrival: <?php echo date('M d, Y', strtotime($order['estimated_arrival_date'])); ?>
                                                                </div>
                                                                
                                                                <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-100">
                                                                    <div class="text-xs text-gray-600">
                                                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                                                    </div>
                                                                    <div class="text-sm font-medium text-gray-800">
                                                                        ₱<?php echo number_format($order['total'], 2); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-gray-500 text-sm">No orders found for this email.</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-12 text-gray-500">
                                    <i class="fas fa-inbox text-2xl mb-2"></i><br>
                                    No unique clients found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mt-6 no-print">
                <div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                    <div class="flex space-x-1">
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                class="px-3 py-2 text-sm border rounded-md transition duration-200 <?php echo $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next Page -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Jump to Page -->
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Go to page:</span>
                        <input type="number" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>"
                            class="w-16 px-2 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="window.location.href = '?<?php echo http_build_query(array_merge($_GET, ['page' => ''])); ?>' + this.value">
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Print Title (Hidden by default, shown when printing) -->
    <div class="print-title hidden text-center mb-4">
        <h1 class="text-2xl font-bold">Client Information Report</h1>
        <p class="text-sm text-gray-600">Generated on <?php echo date('F j, Y'); ?></p>
    </div>

</body>

</html>