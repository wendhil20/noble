<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$business_type_filter = isset($_GET['business_type']) ? $_GET['business_type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the WHERE clause and parameters
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(business_name LIKE ? OR primary_contact_name LIKE ? OR email_address LIKE ? OR country_region LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if (!empty($business_type_filter)) {
    $where_conditions[] = "business_type = ?";
    $params[] = $business_type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM supplier_list $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    $total_records = $count_result->fetch_assoc()['total'];
}

// Get suppliers with search and filters
$sql = "SELECT id, business_name, business_type, country_region, primary_contact_name, 
               job_title, phone_number, email_address, status, created_at, logo_path
        FROM supplier_list 
        $where_clause 
        ORDER BY business_name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $suppliers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $suppliers = $result->fetch_all(MYSQLI_ASSOC);
}

// Get unique business types for filter dropdown
$types_sql = "SELECT DISTINCT business_type FROM supplier_list ORDER BY business_type";
$types_result = $conn->query($types_sql);
$business_types = [];
while ($row = $types_result->fetch_assoc()) {
    $business_types[] = $row['business_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Directory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble': {
                            'primary': '#1e40af',
                            'secondary': '#3b82f6',
                            'accent': '#60a5fa'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Supplier Directory</h1>
            <p class="text-gray-600">Manage and browse your supplier network</p>
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-building mr-2"></i>
                    <?= number_format($total_records) ?> suppliers found
                </div>
                <a href="supplier_management.php" class="bg-noble-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Add Supplier
                </a>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search Input -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Suppliers</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search by name, contact, email, or region..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-noble-primary focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Business Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Business Type</label>
                        <select name="business_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-noble-primary focus:border-transparent">
                            <option value="">All Types</option>
                            <?php foreach ($business_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $business_type_filter == $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-noble-primary focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="submit" class="bg-noble-primary hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                    <a href="?" class="text-gray-600 hover:text-gray-800 px-4 py-2">Clear All</a>
                </div>
            </form>
        </div>

        <!-- Suppliers Grid -->
        <?php if (empty($suppliers)): ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-search text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No suppliers found</h3>
                <p class="text-gray-600">Try adjusting your search criteria or add a new supplier.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($suppliers as $supplier): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <!-- Supplier Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-start space-x-4 mb-3">
                                <!-- Logo Section -->
                                <div class="flex-shrink-0">
                                    <?php 
                                    $logo_path = !empty($supplier['logo_path']) ? '../../uploads/supplier_logos/' . basename($supplier['logo_path']) : '';
                                    $logo_exists = !empty($logo_path) && file_exists($logo_path);
                                    
                                    if ($logo_exists): ?>
                                        <img src="<?= htmlspecialchars($logo_path) ?>" 
                                             alt="<?= htmlspecialchars($supplier['business_name']) ?> logo"
                                             class="w-12 h-12 rounded-lg object-cover border-2 border-gray-200">
                                    <?php else: 
                                        // Create acronym from business name
                                        $words = explode(' ', trim($supplier['business_name']));
                                        $acronym = '';
                                        foreach ($words as $word) {
                                            if (!empty($word)) {
                                                $acronym .= strtoupper(substr($word, 0, 1));
                                                if (strlen($acronym) >= 2) break; // Limit to 2 characters
                                            }
                                        }
                                        if (empty($acronym)) {
                                            $acronym = strtoupper(substr($supplier['business_name'], 0, 2));
                                        }
                                        
                                        // Generate a consistent color based on business name
                                        $colors = [
                                            'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 
                                            'bg-yellow-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'
                                        ];
                                        $color_index = abs(crc32($supplier['business_name'])) % count($colors);
                                        $bg_color = $colors[$color_index];
                                    ?>
                                        <div class="w-12 h-12 rounded-lg <?= $bg_color ?> flex items-center justify-center text-white font-bold text-sm border-2 border-gray-200">
                                            <?= htmlspecialchars($acronym) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Business Info -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-1 line-clamp-2">
                                        <?= htmlspecialchars($supplier['business_name']) ?>
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?= $supplier['business_type'] == 'Manufacturer' ? 'bg-blue-100 text-blue-800' :
                                           ($supplier['business_type'] == 'Wholesaler' ? 'bg-green-100 text-green-800' :
                                           ($supplier['business_type'] == 'Distributor' ? 'bg-purple-100 text-purple-800' :
                                           ($supplier['business_type'] == 'Retailer' ? 'bg-yellow-100 text-yellow-800' :
                                           'bg-gray-100 text-gray-800'))) ?>">
                                        <?= htmlspecialchars($supplier['business_type']) ?>
                                    </span>
                                </div>

                                <!-- Status -->
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        <?= $supplier['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1 
                                            <?= $supplier['status'] == 'active' ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                                        <?= ucfirst($supplier['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Supplier Details -->
                        <div class="p-6 space-y-3">
                            <!-- Contact Person -->
                            <div class="flex items-center text-sm">
                                <i class="fas fa-user text-gray-400 w-4 text-center mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($supplier['primary_contact_name']) ?></p>
                                    <p class="text-gray-600"><?= htmlspecialchars($supplier['job_title']) ?></p>
                                </div>
                            </div>

                            <!-- Location -->
                            <div class="flex items-center text-sm">
                                <i class="fas fa-map-marker-alt text-gray-400 w-4 text-center mr-3"></i>
                                <span class="text-gray-700"><?= htmlspecialchars($supplier['country_region']) ?></span>
                            </div>

                            <!-- Email -->
                            <div class="flex items-center text-sm">
                                <i class="fas fa-envelope text-gray-400 w-4 text-center mr-3"></i>
                                <a href="mailto:<?= htmlspecialchars($supplier['email_address']) ?>" 
                                   class="text-noble-primary hover:text-blue-700 hover:underline truncate">
                                    <?= htmlspecialchars($supplier['email_address']) ?>
                                </a>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-center text-sm">
                                <i class="fas fa-phone text-gray-400 w-4 text-center mr-3"></i>
                                <a href="tel:<?= htmlspecialchars($supplier['phone_number']) ?>" 
                                   class="text-gray-700 hover:text-noble-primary">
                                    <?= htmlspecialchars($supplier['phone_number']) ?>
                                </a>
                            </div>

                            <!-- Created Date -->
                            <div class="flex items-center text-sm text-gray-500 pt-2 border-t border-gray-100">
                                <i class="fas fa-calendar text-gray-400 w-4 text-center mr-3"></i>
                                <span>Added <?= date('M j, Y', strtotime($supplier['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="px-6 pb-6">
                            <div class="flex space-x-2">
                                <a href="view_supplier.php?id=<?= $supplier['id'] ?>" class="flex-1 bg-noble-primary hover:bg-blue-700 text-white text-sm py-2 px-3 rounded-lg transition-colors duration-200 text-center">
    <i class="fas fa-eye mr-2"></i>View Details
</a>
                                <a href="edit_supplier.php?edit_id=<?= $supplier['id'] ?>" 
   class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 px-3 rounded-lg transition-colors duration-200 inline-flex items-center justify-center text-decoration-none">
    <i class="fas fa-edit mr-2"></i>Edit
</a>
                                <div class="relative">
                                    <button onclick="toggleDropdown(<?= $supplier['id'] ?>)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 px-3 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div id="dropdown-<?= $supplier['id'] ?>" class="hidden absolute right-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <div class="py-1">
                                            <a href="link_products.php?supplier_id=<?= $supplier['id'] ?>" 
                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-noble-primary transition-colors duration-200">
                                                <i class="fas fa-link mr-3 text-gray-400"></i>
                                                Link Products
                                            </a>
                                            <a href="edit_supplier.php?edit_id=<?= $supplier['id'] ?>" 
                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-noble-primary transition-colors duration-200">
                                                <i class="fas fa-edit mr-3 text-gray-400"></i>
                                                Edit Supplier
                                            </a>
                                            <a href="view_supplier.php?id=<?= $supplier['id'] ?>" 
                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-noble-primary transition-colors duration-200">
                                                <i class="fas fa-eye mr-3 text-gray-400"></i>
                                                View Details
                                            </a>
                                            <hr class="my-1 border-gray-200">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination placeholder (you can implement this if needed) -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    Showing <?= count($suppliers) ?> of <?= number_format($total_records) ?> suppliers
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form on filter change for better UX
        document.querySelectorAll('select[name="business_type"], select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Add some interactive effects
        document.querySelectorAll('.bg-white').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Dropdown functionality
        function toggleDropdown(supplierId) {
            const dropdown = document.getElementById('dropdown-' + supplierId);
            const allDropdowns = document.querySelectorAll('[id^="dropdown-"]');
            
            // Close all other dropdowns
            allDropdowns.forEach(dd => {
                if (dd.id !== 'dropdown-' + supplierId) {
                    dd.classList.add('hidden');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick*="toggleDropdown"]') && !event.target.closest('[id^="dropdown-"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });

        // Delete confirmation
        function confirmDelete(supplierId, supplierName) {
            if (confirm('Are you sure you want to delete "' + supplierName + '"? This action cannot be undone.')) {
                // You can implement the delete functionality here
                // For now, just redirect to a delete script
                window.location.href = 'delete_supplier.php?id=' + supplierId;
            }
        }
    </script>
</body>
</html>