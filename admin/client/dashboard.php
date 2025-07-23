<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin']); // allow only admin and superadmin

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

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

$email_list = array_column($paginated_emails, 'email');

if (count($email_list) > 0) {
    $email_placeholders = str_repeat('?,', count($email_list) - 1) . '?';

    // Proceed with query using $email_placeholders
    $query = "SELECT o.*, 
                     ROW_NUMBER() OVER (PARTITION BY o.email ORDER BY o.created_at DESC) as rn
              FROM orders o 
              WHERE o.email IN ($email_placeholders) 
              ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($email_list)), ...$email_list);
    $stmt->execute();
    $all_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($all_results as $row) {
        if ($row['rn'] == 1) {
            $final_orders[] = $row;
        }
    }
}

// For filters (status only in this table)
$status_options = $conn->query("SELECT DISTINCT status FROM orders WHERE status IS NOT NULL ORDER BY status")->fetch_all(MYSQLI_ASSOC);

// Function to get order items
function getOrderItems($conn, $order_id)
{
    $query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get all orders for a specific email
function getOrdersByEmail($conn, $email)
{
    $query = "SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to check if email has multiple orders
function hasMultipleOrders($conn, $email)
{
    $query = "SELECT COUNT(*) as count FROM orders WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 1;
}

// Function to get specific order details
function getOrderDetails($conn, $order_id)
{
    $query = "SELECT * FROM orders WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
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

        // NEW: Toggle individual order items in the order list
        function toggleIndividualOrderItems(orderId, email) {
            const itemsDiv = document.getElementById('individual-items-' + orderId);
            const toggleBtn = document.getElementById('toggle-individual-' + orderId);
            const icon = toggleBtn.querySelector('i');

            if (itemsDiv.style.display === 'none' || itemsDiv.style.display === '') {
                // Load items via AJAX if not already loaded
                if (!itemsDiv.dataset.loaded) {
                    loadOrderItems(orderId, email);
                }
                itemsDiv.style.display = 'block';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                toggleBtn.title = 'Hide Order Items';
            } else {
                itemsDiv.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                toggleBtn.title = 'Show Order Items';
            }
        }

        // NEW: Load order items via AJAX
        function loadOrderItems(orderId, email) {
            const itemsDiv = document.getElementById('individual-items-' + orderId);
            const loadingDiv = document.getElementById('loading-' + orderId);

            if (loadingDiv) {
                loadingDiv.style.display = 'block';
            }

            fetch('get_order_items.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (loadingDiv) loadingDiv.style.display = 'none';

                    if (data.success && data.items.length > 0) {
                        let itemsHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            `;

                        data.items.forEach(item => {
                            itemsHTML += `
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 flex flex-col justify-between h-full">
                        <!-- Product Header -->
                        <div class="mb-3">
                            <div class="flex justify-between items-start mb-1">
                                <h6 class="font-semibold text-gray-800 text-sm leading-tight">
                                    ${item.product_name || 'Unnamed Product'}
                                </h6>
                                ${item.code ? `<span class="text-xs text-gray-500">${item.code}</span>` : ''}
                            </div>

                            ${item.name ? `
                                <div class="text-xs text-gray-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Variant: ${item.name}
                                </div>
                            ` : ''}

                            ${item.type_name ? `
                                <div class="text-xs text-gray-600">
                                    <i class="fas fa-tag mr-1"></i>
                                    Type: ${item.type_name}
                                </div>
                            ` : ''}

                            ${item.variant_color ? `
                                <div class="text-xs text-gray-600">
                                    <i class="fas fa-palette mr-1"></i>
                                    Color: ${item.variant_color}
                                </div>
                            ` : ''}
                        </div>

                        <!-- Price Details -->
                        <div class="mt-auto border-t pt-2 border-gray-100 text-xs text-gray-700">
                            <div class="flex justify-between">
                                <span>Qty:</span>
                                <span>${item.quantity || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Price:</span>
                                <span>₱${parseFloat(item.price || 0).toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between font-medium text-gray-800">
                                <span>Total:</span>
                                <span>₱${parseFloat(item.subtotal || 0).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
                        });

                        itemsHTML += '</div>';
                        itemsDiv.innerHTML = itemsHTML;

                    } else {
                        itemsDiv.innerHTML = '<p class="text-gray-500 text-xs mt-2">No items found for this order.</p>';
                    }

                    itemsDiv.dataset.loaded = 'true';
                })
                .catch(error => {
                    console.error('Error loading order items:', error);
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    itemsDiv.innerHTML = '<p class="text-red-500 text-xs mt-2">Error loading order items.</p>';
                });
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

        .individual-order-items {
            max-height: 200px;
            overflow-y: auto;
        }

        .order-card {
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-sans">

    <?php include '../navbar/top.php'; ?>

    <?php if (isset($_SESSION['access_denied'])): ?>
        <div id="access-denied-msg" class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 transition-opacity duration-500">
            <?= htmlspecialchars($_SESSION['access_denied']) ?>
        </div>
        <script>
            setTimeout(() => {
                const msg = document.getElementById('access-denied-msg');
                if (msg) {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500); // Fully remove after fade-out
                }
            }, 3000); // Hide after 3 seconds
        </script>
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
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-4 no-print">
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Unique Clients</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_records; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Current Page Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo !empty($final_orders) ? count($final_orders) : 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Current Page Value</p>
                        <p class="text-2xl font-bold text-gray-900">
                            ₱<?php
                                $page_total = 0;
                                if (!empty($final_orders)) {
                                    foreach ($final_orders as $order) {
                                        $page_total += $order['final_total'];
                                    }
                                }
                                echo number_format($page_total, 2);
                                ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden mt-2">
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
                                        <div class="flex space-x-2">
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
                                    <td class="px-4 py-3">₱<?php echo number_format($row['final_total'], 2); ?></td>
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
                                    <td colspan="9" class="px-4 py-3 bg-gray-50">
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

                                <?php if ($has_multiple_orders): ?>
                                    <tr id="orders-<?php echo base64_encode($row['email']); ?>" class="orders-row" style="display: none;">
                                        <td colspan="9" class="px-4 py-3 bg-slate-50">
                                            <div class="orders-container">
                                                <h4 class="font-semibold text-gray-800 mb-3">
                                                    <i class="fas fa-history mr-1"></i>
                                                    All Orders for <?php echo htmlspecialchars($row['email']); ?>:
                                                </h4>
                                                <?php
                                                $all_orders = getOrdersByEmail($conn, $row['email']);
                                                if (!empty($all_orders)): ?>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                        <?php foreach ($all_orders as $order): ?>
                                                            <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm order-card">
                                                                <div class="flex justify-between items-start mb-3">
                                                                    <div>
                                                                        <a href="order_details.php?id=<?php echo $order['id']; ?>"
                                                                            class="font-medium text-blue-600 hover:text-blue-800 hover:underline transition duration-200">
                                                                            Order #<?php echo $order['id']; ?>
                                                                        </a>
                                                                        <p class="text-xs text-gray-500">
                                                                            <?php echo date('M j, Y - g:i A', strtotime($order['created_at'])); ?>
                                                                        </p>
                                                                    </div>
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

                                                                <div class="space-y-2 mb-3">
                                                                    <div class="flex justify-between text-sm">
                                                                        <span class="text-gray-600">Customer:</span>
                                                                        <span class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                                    </div>
                                                                    <div class="flex justify-between text-sm">
                                                                        <span class="text-gray-600">Total:</span>
                                                                        <span class="font-medium text-green-600">₱<?php echo number_format($order['total'], 2); ?></span>
                                                                    </div>
                                                                    <div class="flex justify-between text-sm">
                                                                        <span class="text-gray-600">Address:</span>
                                                                        <span class="font-medium text-right max-w-xs truncate"><?php echo htmlspecialchars($order['address']); ?></span>
                                                                    </div>
                                                                </div>

                                                                <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                                                    <button id="toggle-individual-<?php echo $order['id']; ?>"
                                                                        onclick="toggleIndividualOrderItems(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['email']); ?>')"
                                                                        class="text-blue-600 hover:text-blue-800 text-sm transition duration-200"
                                                                        title="Show Order Items">
                                                                        <i class="fas fa-eye mr-1"></i>
                                                                        View Items
                                                                    </button>
                                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>"
                                                                        class="text-green-600 hover:text-green-800 text-sm transition duration-200"
                                                                        title="View Full Order Details">
                                                                        <i class="fas fa-external-link-alt mr-1"></i>
                                                                        Details
                                                                    </a>
                                                                    <span class="text-xs text-gray-500">
                                                                        <?php echo $order['mobile']; ?>
                                                                    </span>
                                                                </div>

                                                                <!-- Individual Order Items Container -->
                                                                <div id="individual-items-<?php echo $order['id']; ?>"
                                                                    class="individual-order-items mt-3 pt-3 border-t border-gray-100"
                                                                    style="display: none;">
                                                                    <div id="loading-<?php echo $order['id']; ?>"
                                                                        class="text-center py-2"
                                                                        style="display: none;">
                                                                        <div class="loading-spinner mx-auto"></div>
                                                                        <p class="text-xs text-gray-500 mt-1">Loading items...</p>
                                                                    </div>
                                                                    <!-- Items will be loaded here via AJAX -->
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
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                        <h3 class="text-lg font-medium mb-2">No Records Found</h3>
                                        <p class="text-sm">Try adjusting your search criteria or filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4 rounded-lg shadow-md no-print">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo ($page - 1) * $records_per_page + 1; ?></span>
                            to
                            <span class="font-medium"><?php echo min($page * $records_per_page, $total_records); ?></span>
                            of  
                            <span class="font-medium"><?php echo $total_records; ?></span>
                            results
                        </p>
                    </div>

                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        ...
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                                   <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>


    </div>

    <!-- Print Title (hidden by default, shown when printing) -->
    <div class="print-title hidden text-center mb-4">
        <h1 class="text-2xl font-bold">Client Information Report</h1>
        <p class="text-gray-600">Generated on <?php echo date('F j, Y'); ?></p>
    </div>

    <script>
        // Show items initially if the show_items parameter is set
        <?php if ($show_items === '1'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Update all toggle buttons to show minus icon
                const toggleButtons = document.querySelectorAll('[id^="toggle-"]');
                toggleButtons.forEach(button => {
                    const icon = button.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-plus');
                        icon.classList.add('fa-minus');
                        button.title = 'Hide Items';
                    }
                });
            });
        <?php endif; ?>

        // Auto-hide session messages
        setTimeout(() => {
            const accessMsg = document.getElementById('access-denied-msg');
            if (accessMsg) {
                accessMsg.style.opacity = '0';
                setTimeout(() => accessMsg.remove(), 500);
            }
        }, 5000);
    </script>

</body>

</html>