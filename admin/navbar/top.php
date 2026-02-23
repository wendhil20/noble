<?php
ob_start();
include '../../connection/connect.php';

function hasAnyRole($roles)
{
    if (!isset($_SESSION['noble_lvl'])) return false;
    return in_array($_SESSION['noble_lvl'], $roles);
}

function hasSubrole($subroles)
{
    if (!isset($_SESSION['noble_subrole'])) return false;

    // If single subrole passed as string, convert to array
    if (!is_array($subroles)) {
        $subroles = [$subroles];
    }

    // Check if user's subrole matches any of the provided subroles
    return in_array($_SESSION['noble_subrole'], $subroles);
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id']) || !isset($_SESSION['noble_subrole'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl, subrole FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl, $subrole);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
        $_SESSION['noble_subrole'] = $subrole ?? '';
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
        $_SESSION['noble_subrole'] = '';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble Home - Admin Panels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        [x-cloak] {
            display: none !important;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #ea580c;
            border-radius: 10px;
        }

        .nav-item {
            transition: all 0.3s ease;
        }

        .sidebar .nav-item:hover {
            transform: translateX(4px);
        }

        .active-link {
            background: linear-gradient(90deg, rgba(234, 88, 12, 0.15), transparent);
            border-left: 4px solid #ea580c;
            padding-left: calc(1rem - 4px);
        }

        .dropdown-menu {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .submenu-item {
            transition: all 0.2s ease;
        }

        .submenu-item:hover {
            background: rgba(251, 146, 60, 0.1);
            padding-left: 1.75rem;
        }

        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-backdrop {
            transition: opacity 0.3s ease-in-out;
        }

        @media (min-width: 768px) {
            .desktop-nav-item {
                position: relative;
            }

            .desktop-nav-item::after {
                content: '';
                position: absolute;
                bottom: -4px;
                left: 0;
                right: 0;
                height: 2px;
                background: linear-gradient(90deg, #ea580c, #f97316);
                border-radius: 2px;
                transform: scaleX(0);
                transition: transform 0.3s ease;
            }

            .desktop-nav-item.active::after {
                transform: scaleX(1);
            }

            .desktop-nav-item:hover {
                transform: translateY(-1px);
            }
        }

        .quick-action-item:hover {
            transform: translateX(4px);
        }
    </style>
</head>

<body class="bg-gray-50" x-data="{ 
    sidebarOpen: false, 
    activeDropdown: null,
    quickActionsOpen: false 
}">
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen"
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden">
    </div>

    <!-- Mobile Sidebar -->
    <aside x-show="sidebarOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        @click.away="sidebarOpen = false"
        x-cloak
        class="fixed left-0 top-0 h-full w-80 bg-white shadow-2xl z-50 md:hidden sidebar overflow-y-auto custom-scrollbar">

        <!-- Sidebar Header -->
        <div class="sticky top-0 bg-white z-10 border-b border-gray-200">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-11 h-11 rounded-xl overflow-hidden shadow-md">
                            <img src="../img/logo/logo.png" alt="Noble Home Logo" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <span class="text-xl font-bold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">
                                Admin Panel
                            </span>
                            <p class="text-xs text-gray-500">Management System</p>
                        </div>
                    </div>
                    <button @click="sidebarOpen = false" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="ri-close-line text-2xl text-gray-600"></i>
                    </button>
                </div>

                <!-- User Info Card -->
                <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-4 shadow-sm border border-orange-100">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-lg">
                            <i class="ri-user-line text-white text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate uppercase"><?= htmlspecialchars($_SESSION['noble_name']) ?></p>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs text-orange-600 font-medium uppercase"><?= htmlspecialchars($_SESSION['noble_lvl']) ?></span>
                                <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="p-4 space-y-2">
            <!-- Main Navigation Items -->
            <?php if (hasAnyRole(['superadmin'])): ?>
                <a href="../client/dashboard.php"
                    @click="sidebarOpen = false"
                    class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium <?= $current_page == 'dashboard.php' ? 'text-orange-600 active-link' : 'text-gray-700 hover:bg-gray-50' ?>">
                    <i class="ri-dashboard-line text-xl"></i>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>

            <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                <div class="space-y-1">
                    <button @click="activeDropdown = activeDropdown === 'products' ? null : 'products'"
                        class="nav-item w-full flex items-center justify-between px-4 py-3 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                        <div class="flex items-center space-x-3">
                            <i class="ri-box-3-line text-xl"></i>
                            <span>Products</span>
                        </div>
                        <i class="ri-arrow-down-s-line text-lg transition-transform duration-300"
                            :class="activeDropdown === 'products' ? 'rotate-180' : ''"></i>
                    </button>

                    <div x-show="activeDropdown === 'products'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-cloak
                        class="ml-4 space-y-1 pl-3 border-l-2 border-orange-200">
                        <a href="../shop/adminshop.php"
                            @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-add-circle-line text-lg"></i>
                            <span>Upload Product</span>
                        </a>
                        <a href="../shop/adminupdateshop.php"
                            @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-edit-line text-lg"></i>
                            <span>Update Product</span>
                        </a>
                        <a href="../qrcodeperproduct/qrcodeitem"
                            @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-qr-code-line text-lg"></i>
                            <span>Product QR Codes</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (hasAnyRole(['superadmin'])): ?>
                <a href="../addclient/insertclient"
                    @click="sidebarOpen = false"
                    class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium <?= $current_page == 'insertclient' ? 'text-orange-600 active-link' : 'text-gray-700 hover:bg-gray-50' ?>">
                    <i class="ri-team-line text-xl"></i>
                    <span>Client Management</span>
                </a>
            <?php endif; ?>

            <?php if (hasAnyRole(['sales', 'superadmin'])): ?>
                <a href="../chatadmin/admin_chatmain"
                    @click="sidebarOpen = false"
                    class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium <?= $current_page == 'admin_chatmain' ? 'text-orange-600 active-link' : 'text-gray-700 hover:bg-gray-50' ?>">
                    <i class="ri-message-3-line text-xl"></i>
                    <span>Inquiries</span>
                </a>
            <?php endif; ?>


            <!-- Divider -->
            <div class="pt-4 border-t border-gray-200 mt-4"></div>

            <!-- Profile & Settings -->
            <a href="../../loginpage/profile"
                @click="sidebarOpen = false"
                class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                <i class="ri-user-settings-line text-xl"></i>
                <span>Profile Settings</span>
            </a>

            <a href="../../loginpage/logout"
                @click="sidebarOpen = false"
                class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-red-600 hover:bg-red-50">
                <i class="ri-logout-box-line text-xl"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Desktop Navigation -->
    <nav class="bg-white shadow-xl sticky top-0 z-30 border-b border-gray-200">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <!-- Logo Section with Mobile Menu Button -->
                <div class="flex items-center space-x-3">
                    <!-- Mobile Menu Button -->
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition-all">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>

                    <div class="w-10 h-10 rounded-lg overflow-hidden shadow-sm">
                        <img src="../img/logo/logo.png" alt="Noble Home Logo" class="w-full h-full object-cover">
                    </div>
                    <div class="hidden sm:block">
                        <span class="text-2xl font-bold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">
                            Admin Panel
                        </span>
                        <p class="text-xs text-gray-500 -mt-1">Noble Home Management</p>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1">
                    <?php if (hasAnyRole(['superadmin'])): ?>
                        <a href="../client/owner_dashboard.php"
                            class="desktop-nav-item nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == 'dashboard.php' ? 'text-orange-600 bg-orange-50 active' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <i class="ri-dashboard-line text-lg"></i>
                                <span>Dashboard</span>
                            </div>
                        </a>
                    <?php endif; ?>



                    <?php if (hasAnyRole(['sales'])): ?>
                        <a href="../chatadmin/admin_chatmain"
                            class="desktop-nav-item nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == 'admin_chatmain' ? 'text-orange-600 bg-orange-50 active' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <i class="ri-message-3-line text-lg"></i>
                                <span>Inquiries</span>
                            </div>
                        </a>
                    <?php endif; ?>



                    <!-- Quick Action Bar -->
                    <div class="  py-3">
                        <div class="px-4 sm:px-6 lg:px-8">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div class="relative w-full md:w-auto">
                                    <button @click="quickActionsOpen = !quickActionsOpen"
                                        class="inline-flex items-center space-x-2 px-4 py-2.5 bg-white rounded-xl text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm ">

                                        <span>Quick Actions</span>
                                        <i class="ri-arrow-down-s-line text-lg transform transition-transform duration-200"
                                            :class="quickActionsOpen ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-show="quickActionsOpen"
                                        @click.away="quickActionsOpen = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        x-cloak
                                        class="absolute left-0 mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 max-h-[70vh] overflow-y-auto custom-scrollbar">
                                        <div class="py-2">

                                           <?php if (hasAnyRole(['superadmin'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Operational Manager</div>
                                                    <a href="../client/owner_dashboard.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-dashboard-line text-lg"></i>
                                                        <span>Dashboard</span>
                                                    </a>
                                                    <a href="../client/approve_purchase_orders.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-checkbox-multiple-line text-lg"></i>
                                                        <span>Project Approval</span>
                                                    </a>

                                                    <?php
                                                    // Get pending P.O. count
                                                    $pendingCountSql = "SELECT COUNT(*) as pending_count FROM po_attachments WHERE superadmin_approval_status = 'pending'";
                                                    $pendingResult = $conn->query($pendingCountSql);
                                                    $pendingCount = $pendingResult->fetch_assoc()['pending_count'] ?? 0;
                                                    ?>

                                                    <a href="../client/superadmin_po_approval.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150 relative">
                                                        <i class="ri-file-list-line text-lg"></i>
                                                        <span>Individual P.O Approval</span>

                                                        <?php if ($pendingCount > 0): ?>
                                                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center ">
                                                                <?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </a>

                                                    <a href="../client/superadmin_accountantdashboard.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-calculator-line text-lg"></i>
                                                        <span>Accountant Dashboard</span>
                                                    </a>

                                                    <a href="../client/superadmin_logisticdashboard.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-truck-line text-lg"></i>
                                                        <span>Logistic Dashboard</span>
                                                    </a>

                                                    
                                                    <a href="../client/superadmin_warehousedashboard.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-store-2-line text-lg"></i>
                                                        <span>Warehouse Dashboard</span>
                                                    </a>
                                                   
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['productspecialist'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Product Management</div>
                                                    <a href="../shop/main-adminshop-page-1.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-add-circle-line text-lg"></i>
                                                        <span>Add Product</span>
                                                    </a>
                                                    <a href="../shop/main-adminupdateshop-page-2.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-edit-line text-lg"></i>
                                                        <span>Update Product</span>
                                                    </a>
                                                    <a href="../shop/main-variants_list-page-5.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-file-list-3-line text-lg"></i>
                                                        <span>Specification Products</span>
                                                    </a>
                                                    <a href="../shop/main-banner-page-6.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-price-tag-3-line text-lg"></i>
                                                        <span>Banner Discount</span>
                                                    </a>
                                                    <a href="../supplier_management/suppliers_list"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-truck-line text-lg"></i>
                                                        <span>Supplier Management</span>
                                                    </a>
                                                    <a href="../shop/main-category-product-page-3.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-folder-line text-lg"></i>
                                                        <span>Category Management</span>
                                                    </a>
                                                    <a href="../shop/main-add_bestseller-page-4.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-star-line text-lg"></i>
                                                        <span>Bestseller Management</span>
                                                    </a>
                                                    <a href="../qrcodeperproduct/qrcodeitem"
                                                        class="submenu-item block px-4 py-3 text-gray-700 hover:text-orange-600 rounded-lg transition-all duration-200">
                                                        <div class="flex items-center space-x-3">
                                                            <i class="ri-qr-code-line text-xl"></i>
                                                            <span>Product QR Codes</span>
                                                        </div>
                                                    </a>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['sales'])): ?>
                                                <div class="px-3 py-2">

                                                    <!-- SALES HEADER WITH ICON -->
                                                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                                        <i class="ri-store-2-line text-base"></i>
                                                        <span>Sales</span>
                                                    </div>

                                                    <!-- Client List -->
                                                    <a href="../orders/unassigned_orders.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-user-3-line text-lg"></i>
                                                        <span>Client List</span>
                                                    </a>

                                                    <!-- Orders -->
                                                    <a href="../orders/ordering"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-shopping-cart-2-line text-lg"></i>
                                                        <span>Orders</span>
                                                    </a>

                                                    <!-- Dashboard Sales with Notification Badge -->
                                                    <a href="../orders/main-dashboardorder-page-1"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
           hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150 relative">
                                                        <i class="ri-bar-chart-grouped-line text-lg"></i>
                                                        <span>Customize Quotation Request</span>

                                                        <!-- Notification Badge -->
                                                        <span id="quote-notification"
                                                            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 
               flex items-center justify-center hidden">
                                                            <span id="quote-count">0</span>
                                                        </span>
                                                    </a>

                                                    <script>
                                                        // Check notification count on page load
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            checkQuoteNotifications();

                                                            // Check every 30 seconds for new requests
                                                            setInterval(checkQuoteNotifications, 30000);
                                                        });

                                                        function checkQuoteNotifications() {
                                                            fetch('../orders/main-get-request-details-page-1-A.php?action=get-notification-count')
                                                                .then(response => response.json())
                                                                .then(data => {
                                                                    if (data.success && data.pending_count > 0) {
                                                                        const badge = document.getElementById('quote-notification');
                                                                        const count = document.getElementById('quote-count');

                                                                        count.textContent = data.pending_count;
                                                                        badge.classList.remove('hidden');
                                                                    } else {
                                                                        document.getElementById('quote-notification').classList.add('hidden');
                                                                    }
                                                                })
                                                                .catch(error => console.error('Error fetching notification count:', error));
                                                        }
                                                    </script>


                                                    <!-- Target Price Management -->
                                                    <a href="../orders/target-price-management.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-price-tag-3-line text-lg"></i>
                                                        <span>Target Price Management</span>
                                                    </a>

                                                    <!-- Referral Code Generator -->
                                                    <a href="../orders/generate_referral.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-gift-line text-lg"></i>
                                                        <span>Referral Generate Code</span>
                                                    </a>

                                                    <!-- Project Management -->
                                                    <a href="../project_management/view_companies.php"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
    hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">

                                                        <i class="ri-task-line text-lg"></i>
                                                        <span>Project Management</span>
                                                    </a>


                                                </div>


                                            <?php endif; ?>


                                            <?php if (hasAnyRole(['', 'logistic'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Logistics</div>
                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['dispatcher'])): ?>
                                                        <a href="../logistic_management/logistic-main-dashboard-page-1"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-dashboard-3-line text-lg"></i>
                                                            <span>Dashboard</span>
                                                        </a>
                                                    <?php endif; ?>


                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['dispatcher', ''])): ?>
                                                        <a href="../warehouse_management/qr_scanner"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-qr-code-line text-lg"></i>
                                                            <span>QR Scanner</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole([''])): ?>
                                                        <a href="../logistic_management/logistic-dispatcher-dashboard-page-13.php"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-qr-code-line text-lg"></i>
                                                            <span>Dashboard Dispatcher</span>
                                                        </a>
                                                    <?php endif; ?>


                                                    <a href="../truck_management/transpo_add_vehicle"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-truck-line text-lg"></i>
                                                        <span>Add Courier Vehicle</span>
                                                    </a>

                                                    <a href="../client/delivery_data_input"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-file-info-line text-lg"></i>
                                                        <span>Delivery Info Management</span>
                                                    </a>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['hr'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Human Resources</div>
                                                    <a href="../hr/assign_head"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-user-star-line text-lg"></i>
                                                        <span>Head Management</span>
                                                    </a>
                                                    <a href="../hr/account"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-account-circle-line text-lg"></i>
                                                        <span>Account Management</span>
                                                    </a>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['supplier'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Supplier</div>
                                                    <a href="../suppliermain/suppliers"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-building-line text-lg"></i>
                                                        <span>Profile</span>
                                                    </a>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['warehouse'])): ?>
                                                <div class="px-3 py-2">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Warehouse</div>

                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['warehouse_receiver', 'warehouse_staff'])): ?>
                                                        <a href="../warehouse_management/warehouse_head_dashboard_main"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Head Dashboard</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['warehouse_receiver', ''])): ?>
                                                        <a href="../warehouse_management/warehouse_staff_management_main"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Assign Orders</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['warehouse_staff', ''])): ?>
                                                        <a href="../warehouse_management/receiver_po_list_main"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-search-eye-line text-lg"></i>
                                                            <span>Assigned Receive Items</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (hasAnyRole(['superadmin']) || !hasSubrole(['warehouse_staff', ''])): ?>
                                                        <a href="../warehouse_management/qr_scanner"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-qr-code-line text-lg"></i>
                                                            <span>QR Scanner</span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (hasAnyRole(['accountant'])): ?>
                                                <div class="px-3 py-2">

                                                    <!-- ACCOUNTANT HEADER WITH ICON -->
                                                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                                        <i class="ri-calculator-line text-base"></i>
                                                        <span>Accountant</span>
                                                    </div>

                                                    <!-- Revenue Accountant -->
                                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <a href="../accountant/accountant"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Dashboard</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Revenue Accountant -->
                                                    <?php if (hasAnyRole(['']) || !hasSubrole([''])): ?>
                                                        <a href="../accountant/accountant_view_orders"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Dashboard Document Controller</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <a href="../accountant/approve_purchase_orders_accountant"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Project Approval</span>
                                                        </a>
                                                    <?php endif; ?>

                                                      <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <a href="../accountant/accounting_sales_commission.php"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-archive-line text-lg"></i>
                                                            <span>Comminsion management</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- HIDDEN: Only show for superadmin -->
                                                    <?php if (hasAnyRole([''])): ?>
                                                        <a href="../accountant/accountant_view_orders"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
              hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-money-dollar-circle-line text-lg"></i>
                                                            <span>Dashboard</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <a href="../accountant/document_controller_view_orders"
                                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
           hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                        <i class="ri-file-text-line text-lg"></i>
                                                        <span>Project Document</span>
                                                    </a>


                                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <a href="../accountant/accountantdashboard.php"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-money-dollar-circle-line text-lg"></i>
                                                            <span>Revenue Accountant</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <!-- Add QR Code -->
                                                        <a href="../accountant/manage_qr_codes.php"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-qr-code-line text-lg"></i>
                                                            <span>Add QR Code</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                                        <!-- Project Excel -->
                                                        <a href="../accountant/accountantexcel.php"
                                                            class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                                            <i class="ri-file-excel-2-line text-lg"></i>
                                                            <span>Project Excel</span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications with Real-time AJAX - Instant Display -->
                    <div class="relative" x-data="notificationPanel()">
                        <button @click="activeDropdown = activeDropdown === 'notifications' ? null : 'notifications'; openDropdown();"
                            class="p-2 rounded-lg hover:bg-gray-50 transition-all duration-300 relative">
                            <i class="ri-notification-3-line text-2xl text-gray-700"></i>
                            <span x-show="unreadCount > 0" class="absolute top-1 right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                            <span x-show="unreadCount > 0" x-text="unreadCount" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center text-[10px] font-bold"></span>
                        </button>

                        <!-- Notifications Sidebar -->
                        <div x-show="activeDropdown === 'notifications'"
                            @click.away="activeDropdown = null"
                            x-transition:enter="transition ease-out duration-300 transform"
                            x-transition:enter-start="opacity-0 -translate-x-full"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-200 transform"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-full"
                            x-cloak
                            class="absolute right-0 top-full mt-2 w-96 max-h-96 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col z-50">

                            <!-- Header -->
                            <div class="sticky top-0 bg-orange-600 px-3 py-4 flex items-center justify-between z-10">
                                <h3 class="text-lg  text-white flex items-center space-x-2">
                                    <i class="ri-notification-3-line"></i>
                                    <span>Notifications</span>
                                    <span x-show="unreadCount > 0" class="ml-2 bg-white text-orange-600 px-2 py-0.5 rounded-full text-xs font-semibold" x-text="unreadCount"></span>
                                </h3>
                                <div class="flex items-center space-x-2">
                                    <!-- Mark all as read button -->
                                    <button @click="markAllAsRead()" x-show="unreadCount > 0"
                                        class="text-white hover:bg-white/20 p-1 rounded-lg transition-all" title="Mark all as read">
                                        <i class="ri-check-double-line text-lg"></i>
                                    </button>
                                    <!-- Delete all button -->
                                    <button @click="deleteAll()" x-show="notifications.length > 0"
                                        class="text-white hover:bg-white/20 p-1 rounded-lg transition-all" title="Delete all notifications">
                                        <i class="ri-delete-bin-2-line text-lg"></i>
                                    </button>
                                    <!-- Close button -->
                                    <button @click="activeDropdown = null" class="text-white hover:bg-white/20 p-1 rounded-lg transition-all">
                                        <i class="ri-close-line text-xl"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Notifications List -->
                            <div class="overflow-y-auto flex-1 custom-scrollbar">
                                <!-- Loading State -->
                                <div x-show="loading" class="px-6 py-8 text-center">
                                    <div class="inline-block animate-spin">
                                        <i class="ri-loader-4-line text-2xl text-orange-600"></i>
                                    </div>
                                    <p class="text-gray-600 mt-2 text-sm">Loading notifications...</p>
                                </div>

                                <!-- Empty State -->
                                <div x-show="!loading && notifications.length === 0" class="px-6 py-12 text-center">
                                    <i class="ri-notification-off-line text-4xl text-gray-300 mb-2"></i>
                                    <p class="text-gray-600 text-sm">No notifications yet</p>
                                </div>

                                <!-- Notification Items -->
                                <template x-for="notif in notifications" :key="notif.id">
                                    <div :class="notif.color_class" class="px-6 py-4 border-b border-gray-200 hover:bg-opacity-75 transition-all duration-200 cursor-pointer group">
                                        <div class="flex items-start space-x-3 justify-between">
                                            <div class="flex items-start space-x-3 flex-1">
                                                <div :class="notif.color_class" class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                                    <i :class="notif.icon_class" class="text-lg" :style="getIconColor(notif.color_class)"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-semibold text-gray-800 text-sm" x-text="notif.title"></p>
                                                    <p class="text-xs text-gray-600 mt-1" x-text="notif.message"></p>
                                                    <p class="text-xs text-gray-500 mt-2" x-text="formatTime(notif.created_at)"></p>
                                                </div>
                                            </div>

                                            <!-- Action Buttons (appear on hover) -->
                                            <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity ml-2 flex-shrink-0">
                                                <!-- Mark as read/unread -->
                                                <button @click.stop="toggleRead(notif.id, notif.is_read)"
                                                    class="p-1 hover:bg-white/50 rounded transition-all"
                                                    :title="notif.is_read ? 'Mark as unread' : 'Mark as read'">
                                                    <i :class="notif.is_read ? 'ri-mail-open-line' : 'ri-mail-line'" class="text-sm text-gray-600"></i>
                                                </button>
                                                <!-- Delete -->
                                                <button @click.stop="deleteNotification(notif.id)"
                                                    class="p-1 hover:bg-red-100 rounded transition-all"
                                                    title="Delete">
                                                    <i class="ri-delete-bin-line text-sm text-red-600"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Footer -->
                            <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-3 flex items-center justify-between">
                                <button @click="loadNotifications()" class="text-gray-600 hover:text-gray-800" title="Refresh">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <script>
                        function notificationPanel() {
                            return {
                                notifications: [],
                                unreadCount: 0,
                                loading: false,
                                pollInterval: null,
                                lastNotificationId: 0,

                                async init() {
                                    // ✅ INSTANT: Load notifications immediately on page load
                                    await this.loadNotifications();

                                    // Start polling every 3 seconds for new notifications
                                    this.pollInterval = setInterval(() => {
                                        this.loadNotifications();
                                    }, 3000);
                                },

                                openDropdown() {
                                    // Load fresh notifications when opening dropdown
                                    this.loadNotifications();
                                },

                                async loadNotifications() {
                                    try {
                                        const response = await fetch('../notification/main-get-notif-page-1.php');
                                        const data = await response.json();

                                        if (data.success) {
                                            const newNotifications = data.notifications;

                                            // Check if we have new notifications
                                            if (newNotifications.length > 0) {
                                                const latestId = newNotifications[0].id;

                                                // If we have a new notification not seen before
                                                if (latestId > this.lastNotificationId) {
                                                    // Play notification sound
                                                    this.playNotificationSound();

                                                    // Show browser notification if permitted
                                                    this.showBrowserNotification(newNotifications[0]);

                                                    this.lastNotificationId = latestId;
                                                }
                                            }

                                            this.notifications = newNotifications;
                                            this.unreadCount = data.unread_count;
                                        }
                                    } catch (error) {
                                        console.error('Error loading notifications:', error);
                                    }
                                },

                                async toggleRead(notifId, isRead) {
                                    try {
                                        const formData = new FormData();
                                        formData.append('action', 'mark_single');
                                        formData.append('notification_id', notifId);

                                        const response = await fetch('../notification/main-mark-notif-page-3.php', {
                                            method: 'POST',
                                            body: formData
                                        });

                                        const data = await response.json();
                                        if (data.success) {
                                            // ✅ INSTANT: Update count immediately
                                            this.unreadCount = data.unread_count;
                                            await this.loadNotifications();
                                        }
                                    } catch (error) {
                                        console.error('Error toggling read:', error);
                                    }
                                },

                                async markAllAsRead() {
                                    try {
                                        const formData = new FormData();
                                        formData.append('action', 'mark_all');

                                        const response = await fetch('../notification/main-mark-notif-page-3.php', {
                                            method: 'POST',
                                            body: formData
                                        });

                                        const data = await response.json();
                                        if (data.success) {
                                            // ✅ INSTANT: Set count to 0 immediately
                                            this.unreadCount = 0;
                                            await this.loadNotifications();
                                        }
                                    } catch (error) {
                                        console.error('Error marking all as read:', error);
                                    }
                                },

                                async deleteNotification(notifId) {
                                    if (!confirm('Delete this notification?')) return;

                                    try {
                                        const formData = new FormData();
                                        formData.append('action', 'delete');
                                        formData.append('notification_id', notifId);

                                        const response = await fetch('../notification/main-mark-notif-page-3.php', {
                                            method: 'POST',
                                            body: formData
                                        });

                                        const data = await response.json();
                                        if (data.success) {
                                            // ✅ INSTANT: Update count immediately
                                            this.unreadCount = data.unread_count;
                                            await this.loadNotifications();
                                        }
                                    } catch (error) {
                                        console.error('Error deleting notification:', error);
                                    }
                                },

                                async deleteAll() {
                                    if (!confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')) return;

                                    try {
                                        const formData = new FormData();
                                        formData.append('action', 'delete_all');

                                        const response = await fetch('../notification/main-mark-notif-page-3.php', {
                                            method: 'POST',
                                            body: formData
                                        });

                                        const data = await response.json();
                                        if (data.success) {
                                            // ✅ INSTANT: Clear everything
                                            this.unreadCount = 0;
                                            this.notifications = [];
                                            console.log('All notifications deleted:', data.affected_rows);
                                        } else {
                                            alert('Error: ' + data.message);
                                        }
                                    } catch (error) {
                                        console.error('Error deleting all notifications:', error);
                                        alert('Failed to delete notifications');
                                    }
                                },

                                playNotificationSound() {
                                    try {
                                        const audioContext = new(window.AudioContext || window.webkitAudioContext)();
                                        const oscillator = audioContext.createOscillator();
                                        const gainNode = audioContext.createGain();

                                        oscillator.connect(gainNode);
                                        gainNode.connect(audioContext.destination);

                                        oscillator.frequency.value = 800;
                                        oscillator.type = 'sine';

                                        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                                        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

                                        oscillator.start(audioContext.currentTime);
                                        oscillator.stop(audioContext.currentTime + 0.5);
                                    } catch (e) {
                                        // Silently fail if audio context is not available
                                    }
                                },

                                showBrowserNotification(notif) {
                                    if ('Notification' in window && Notification.permission === 'granted') {
                                        new Notification('Noble Home - ' + notif.title, {
                                            body: notif.message,
                                            icon: '../img/logo/logo.png',
                                            tag: 'noble-notification',
                                            requireInteraction: false
                                        });
                                    }
                                },

                                formatTime(dateString) {
                                    const date = new Date(dateString);
                                    const now = new Date();
                                    const diffMs = now - date;
                                    const diffMins = Math.floor(diffMs / 60000);
                                    const diffHours = Math.floor(diffMs / 3600000);
                                    const diffDays = Math.floor(diffMs / 86400000);

                                    if (diffMins < 1) return 'just now';
                                    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
                                    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                                    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

                                    return date.toLocaleDateString();
                                },

                                getIconColor(colorClass) {
                                    const colors = {
                                        'bg-green-100': '#16a34a',
                                        'bg-blue-100': '#2563eb',
                                        'bg-orange-100': '#ea580c',
                                        'bg-yellow-100': '#eab308',
                                        'bg-purple-100': '#a855f7'
                                    };
                                    return {
                                        color: colors[colorClass] || '#666'
                                    };
                                }
                            }
                        }

                        // ✅ Initialize notification panel on page load
                        document.addEventListener('DOMContentLoaded', () => {
                            const notifPanel = document.querySelector('[x-data*="notificationPanel"]')?.__x;
                            if (notifPanel) {
                                notifPanel.init();
                            }
                        });

                        // Request browser notification permission on page load
                        if ('Notification' in window && Notification.permission === 'default') {
                            Notification.requestPermission();
                        }
                    </script>


                    <!-- Profile Dropdown with User Info -->
                    <div class="relative ml-4">
                        <button @click="activeDropdown = activeDropdown === 'profile' ? null : 'profile'"
                            class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-50 transition-all duration-300">
                            <div class="hidden sm:flex flex-col items-end">
                                <span class="font-semibold text-sm text-black uppercase"><?= htmlspecialchars($_SESSION['noble_name']) ?></span>
                                <div class="flex items-center space-x-2 mt-0.5">
                                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                    <span class="text-xs text-gray-500">Online</span>
                                </div>
                            </div>
                            <div class="w-8 h-8 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-md">
                                <i class="ri-user-line text-white"></i>
                            </div>
                        </button>

                        <div x-show="activeDropdown === 'profile'"
                            @click.away="activeDropdown = null"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            x-cloak
                            class="absolute right-0 mt-2 w-56 dropdown-menu shadow-xl rounded-xl overflow-hidden">
                            <div class="p-4 bg-gradient-to-r from-orange-50 to-red-50 border-b border-orange-100">

                                <div class="flex items-center space-x-2 mt-2">
                                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                    <span class="text-xs text-green-600 font-medium">Active</span>
                                </div>
                            </div>
                            <div class="p-2">
                                <a href="../../loginpage/profile" class="flex items-center space-x-2 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-200">
                                    <i class="ri-user-settings-line"></i>
                                    <span>Profile Settings</span>
                                </a>
                                <a href="../../loginpage/logout" class="flex items-center space-x-2 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                    <i class="ri-logout-box-line"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile User Avatar -->
                <div class="md:hidden flex items-center">
                    <div class="w-9 h-9 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-md">
                        <i class="ri-user-line text-white text-sm"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</body>

</html>