<?php
ob_start();
include '../../connection/connect.php';

// Helper function to check user roles
function hasAnyRole($roles)
{
    if (!isset($_SESSION['noble_lvl'])) return false;
    return in_array($_SESSION['noble_lvl'], $roles);
}

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

// Update activity time
$_SESSION['last_activity'] = time();

// ✅ Set noble_name, noble_lvl, and noble_id from DB if not already set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;     // ← Store the user's ID
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;   // ← Store the user's role
    } else {
        $_SESSION['noble_id'] = null;    // fallback ID
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest"; // fallback role
    }
    $stmt->close();
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble Home - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #ea580c;
            border-radius: 10px;
        }

        /* Smooth animations */
        .slide-down {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Active link indicator */
        .active-link {
            position: relative;
        }

        .active-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #ea580c, #f97316);
            border-radius: 2px;
        }

        /* Hover effects */
        .nav-item {
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            transform: translateY(-1px);
        }

        /* Dropdown improvements */
        .dropdown-menu {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .submenu-item {
            transition: all 0.2s ease;
        }

        .submenu-item:hover {
            background: rgba(251, 146, 60, 0.1);
            padding-left: 1.5rem;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

    <!-- Main Navigation -->
    <nav x-data="{ 
        mobileOpen: false, 
        activeDropdown: null,
        subMenu: null,
        secondaryMobileOpen: false 
    }"
        class="bg-white shadow-xl sticky top-0 z-50 border-b border-gray-200">

        <div class=" px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <!-- Logo Section -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg overflow-hidden shadow-md">
                        <img src="../img/logo/logo.png" alt="Noble Home Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">
                            Admin Panel
                        </span>
                        <p class="text-xs text-gray-500 -mt-1">Noble Home Management</p>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1">

                    <?php if (hasAnyRole(['superadmin'])): ?>
                        <a href="../client/dashboard.php"
                            class="nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == '../client/dashboard' ? 'text-orange-600 bg-orange-50 active-link' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v1H8V5z"></path>
                                </svg>
                                <span>Dashboard</span>
                            </div>
                        </a>
                    <?php endif; ?>


                    <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                        <!-- Products Dropdown -->
                        <div class="relative">
                            <button @click="activeDropdown = activeDropdown === 'products' ? null : 'products'"
                                class="nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 text-gray-700 hover:text-orange-500 hover:bg-gray-50 flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <span>Products</span>
                                <svg class="w-4 h-4 transition-transform duration-300"
                                    :class="activeDropdown === 'products' ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="activeDropdown === 'products'"
                                @click.away="activeDropdown = null"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-95"
                                x-cloak
                                class="absolute z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] max-h-[70vh] overflow-y-auto dropdown-menu shadow-xl rounded-xl overflow-hidden left-0 sm:left-auto sm:right-0 bg-white">
                                <div class="p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Product Management</h3>

                                    <div class="grid grid-cols-1 gap-3">
                                        <!-- Materials Section -->
                                        <div class="space-y-2">
                                            <h4 class="text-sm font-medium text-gray-600 mb-2">Materials & Inventory</h4>
                                            <a href="../shop/adminshop.php"
                                                class="submenu-item block px-4 py-3 text-gray-700 hover:text-orange-600 rounded-lg transition-all duration-200">
                                                <div class="flex items-center space-x-3">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                    <span>Upload Product</span>
                                                </div>
                                            </a>
                                            <a href="../shop/adminupdateshop.php"
                                                class="submenu-item block px-4 py-3 text-gray-700 hover:text-orange-600 rounded-lg transition-all duration-200">
                                                <div class="flex items-center space-x-3">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                        </path>
                                                    </svg>
                                                    <span>Update Product</span>
                                                </div>
                                            </a>
                                        </div>

                                        <!-- Orders Section -->
                                        <div class="space-y-2 border-t pt-3">
                                            <h4 class="text-sm font-medium text-gray-600 mb-2">Orders & Tracking</h4>

                                            <a href="../qrcodeperproduct/qrcodeitem"
                                                class="submenu-item block px-4 py-3 text-gray-700 hover:text-orange-600 rounded-lg transition-all duration-200">
                                                <div class="flex items-center space-x-3">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                                        </path>
                                                    </svg>
                                                    <span>Product QR Codes</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>


                    <?php if (hasAnyRole(['', 'superadmin'])): ?>
                        <!-- Client Management -->
                        <a href="../addclient/insertclient"
                            class="nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == 'insertclient' ? 'text-orange-600 bg-orange-50 active-link' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                <span>Client Management</span>
                            </div>
                        </a>
                    <?php endif; ?>


                    <?php if (hasAnyRole(['', 'superadmin'])): ?>
                        <!-- Inquiries -->
                        <a href="../chatadmin/admin_chat"
                            class="nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == '../chatadmin/admin_chat' ? 'text-orange-600 bg-orange-50 active-link' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <span>Inquiries</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if (hasAnyRole(['accountant', 'superadmin'])): ?>
                        <!-- Transactions -->
                        <a href="../transaction"
                            class="nav-item px-4 py-2 rounded-lg font-medium transition-all duration-300 
                              <?= $current_page == '../transaction' ? 'text-orange-600 bg-orange-50 active-link' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?>">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                <span>Transactions</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <!-- Settings/Profile -->
                    <div class="relative ml-4">
                        <button @click="activeDropdown = activeDropdown === 'profile' ? null : 'profile'"
                            class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 transition-all duration-300">
                            <div class="w-8 h-8 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </button>

                        <div x-show="activeDropdown === 'profile'"
                            @click.away="activeDropdown = null"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-1 transform scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-1 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            x-cloak
                            class="absolute right-0 mt-2 w-48 dropdown-menu shadow-xl rounded-xl overflow-hidden">
                            <div class="p-2">
                                <a href="../../loginpage/logout" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden">
                    <button @click="mobileOpen = !mobileOpen"
                        class="p-2 rounded-lg text-gray-700 hover:text-orange-500 hover:bg-gray-50 transition-all duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-y-4"
            x-transition:enter-end="opacity-1 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-1 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-4"
            x-cloak
            class="md:hidden bg-white border-t border-gray-200 custom-scrollbar max-h-screen overflow-y-auto">

            <div class="px-4 py-6 space-y-4">
                <?php if (hasAnyRole(['', 'superadmin'])): ?>
                    <a href="../client/dashboard"
                        class="flex items-center space-x-3 p-3 rounded-lg <?= $current_page == 'dashboard' ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?> transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                <?php endif; ?>


                <!-- Mobile Products Section -->
                <div class="space-y-2">
                    <button @click="activeDropdown = activeDropdown === 'mobile-products' ? null : 'mobile-products'"
                        class="flex items-center justify-between w-full p-3 rounded-lg text-gray-700 hover:text-orange-500 hover:bg-gray-50 transition-all duration-300">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <span>Products</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-300" :class="activeDropdown === 'mobile-products' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="activeDropdown === 'mobile-products'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-1 transform translate-y-0"
                        x-cloak
                        class="pl-8 space-y-2">
                        <?php if (hasAnyRole(['', 'superadmin'])): ?>
                            <a href="../shop/adminshop.php" class="block p-2 text-gray-600 hover:text-orange-600 transition-colors">Upload Product</a>
                            <a href="../shop/adminupdateshop.php" class="block p-2 text-gray-600 hover:text-orange-600 transition-colors">Update Product</a>
                            <a href="../orders/orders" class="block p-2 text-gray-600 hover:text-orange-600 transition-colors">Order Management</a>
                            <a href="../qrcodeperproduct/qrcodeitem" class="block p-2 text-gray-600 hover:text-orange-600 transition-colors">Product QR Codes</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (hasAnyRole(['', 'superadmin'])): ?>
                    <a href="../addclient/insertclient"
                        class="flex items-center space-x-3 p-3 rounded-lg <?= $current_page == 'insertclient' ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?> transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        <span>Client Management</span>
                    </a>
                <?php endif; ?>

                <?php if (hasAnyRole(['', 'superadmin'])): ?>
                    <a href="contact.php"
                        class="flex items-center space-x-3 p-3 rounded-lg <?= $current_page == 'contact.php' ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?> transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span>Inquiries</span>
                    </a>
                <?php endif; ?>

                <?php if (hasAnyRole(['', 'superadmin'])): ?>
                    <a href="quote.php"
                        class="flex items-center space-x-3 p-3 rounded-lg <?= $current_page == 'quote.php' ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:text-orange-500 hover:bg-gray-50' ?> transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>Transactions</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

<!-- Quick Action Bar -->
<div class="bg-gradient-to-r from-orange-50 to-red-50 border-b border-orange-200 py-3">
    <div class=" px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <!-- Left: Quick Actions -->
            <div class="w-full">
                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 space-y-2 sm:space-y-0">
                    <span class="text-sm font-medium text-gray-700">Quick Actions:</span>
                    <div class="flex flex-wrap gap-2">
                        <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                            <a href="../shop/adminshop.php"
                                class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span>Add Product</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasAnyRole(['superadmin', 'sales'])): ?>
                                <a href="../orders/unassigned_orders.php"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>Client List</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['superadmin', 'sales'])): ?>
                                <a href="../orders/ordering"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>Orders</span>
                                </a>
                            <?php endif; ?>
                            <?php if (hasAnyRole(['superadmin', 'logistic'])): ?>

                                <a href="../client/approvingorder"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Arrival Management</span>
                                </a>
                            <?php endif; ?>
                            <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>

                                <a href="../Specification/variants_list"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Specification Products</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                                <a href="../Specification/banner"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Banner Discount</span>
                                </a>
                            <?php endif; ?>

                                 <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                                <a href="../shop/add_category"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Add Category</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['superadmin', 'logistic'])): ?>

                                <a href="../client/add_driver"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Add Driver</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['superadmin', 'logistic'])): ?>

                                <a href="../client/add_tracking"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Add Tracking</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['superadmin', 'logistic'])): ?>

                                <a href="../client/monitortracking"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Monitor Tracking</span>
                                </a>
                            <?php endif; ?>

<?php if (hasAnyRole(['superadmin', 'supplier'])): ?>

                                <a href="../suppliermain/suppliers"
                                    class="inline-flex items-center space-x-2 px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm">
                                    <span>Profile</span>
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- Right: User Info -->
                <div class="flex items-center space-x-2 text-sm">
                    <?php if (isset($_SESSION['noble_lvl'])): ?>
                        <span class="text-gray-600">(<?= htmlspecialchars($_SESSION['noble_lvl']) ?>)</span>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['noble_name'])): ?>
                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['noble_name']) ?></span>
                    <?php endif; ?>
                    <div class="w-3 h-2 bg-green-400 rounded-full"></div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Auto-hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('[x-data]');
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(e.target)) {
                    Alpine.store('activeDropdown', null);
                }
            });
        });

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading states for navigation links
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('#')) {
                    const spinner = document.createElement('div');
                    spinner.className = 'inline-block w-4 h-4 border-2 border-orange-500 border-t-transparent rounded-full animate-spin ml-2';
                    this.appendChild(spinner);

                    setTimeout(() => {
                        if (spinner.parentNode) {
                            spinner.remove();
                        }
                    }, 3000);
                }
            });
        });
    </script>
</body>

</html>