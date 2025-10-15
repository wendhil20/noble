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
                    <div class="w-10 h-10 rounded-lg overflow-hidden">
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


                    <?php if (hasAnyRole(['sales', 'ksuperadmin'])): ?>
                        <!-- Inquiries -->
                        <a href="../chatadmin/admin_chatmain"
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
                                <a href="../../loginpage/profile" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                    Profile
                                </a>

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



        <!-- Quick Action Bar -->
        <div class="bg-white border-b border-orange-200 py-3">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <!-- Left: Quick Actions Dropdown -->
                    <div class="relative">
                        <button id="quickActionsBtn"
                            class="inline-flex items-center space-x-2 px-4 py-2 bg-white rounded-lg text-sm font-medium text-gray-700 hover:text-orange-600 hover:bg-orange-50 transition-all duration-200 shadow-sm border border-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Quick Actions</span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" id="dropdownIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="quickActionsDropdown"
                            class="absolute left-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 opacity-0 invisible transform scale-95 transition-all duration-200 origin-top-left">
                            <div class="py-2 max-h-96 overflow-y-auto">

                                <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                                    <!-- Product Management Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Product Management</div>
                                        <a href="../shop/adminshop.php"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            <span>Add Product</span>
                                        </a>
                                        <a href="../shop/adminupdateshop.php"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Update Product</span>
                                        </a>
                                        <a href="../Specification/variants_list"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span>Specification Products</span>
                                        </a>
                                        <a href="../Specification/banner"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4z" />
                                            </svg>
                                            <span>Banner Discount</span>
                                        </a>
                                        <a href="../supplier_management/suppliers_list"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span>Supplier Management</span>
                                        </a>
                                        <a href="../shop/navbar.php"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span>Category Management</span>
                                            
                                        </a>
                                             <a href="../shop/add_bestseller.php"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span>Bestseller Management</span>
                                        </a>
                                    </div>
                                    <hr class="my-2 border-gray-200">
                                <?php endif; ?>

                                <?php if (hasAnyRole(['superadmin', 'sales'])): ?>
                                    <!-- Sales Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Sales</div>
                                        <a href="../orders/unassigned_orders.php"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span>Client List</span>
                                        </a>
                                        <a href="../orders/ordering"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Orders</span>
                                        </a>
                                        <a href="../orders/dashboardorder"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Dashboard Sales</span>
                                        </a>
                                            <a href="../orders/add_tiercard"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>TierCard Management</span>
                                        </a>
                                    </div>
                                    <hr class="my-2 border-gray-200">
                                <?php endif; ?>

                                <?php if (hasAnyRole(['superadmin', 'logistic'])): ?>
                                    <!-- Logistics Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Logistics</div>
                                        <a href="../logistic_management/main_dashboard"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Dashboard</span>
                                        </a>

                                        <a href="../client/driver_management"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Add Driver</span>
                                        </a>
                                        <a href="../truck_management/transpo_add_vehicle"
   class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 13V7a2 2 0 012-2h10a2 2 0 012 2v6m-2 0h2l3 3v4a1 1 0 01-1 1h-1a3 3 0 01-6 0H9a3 3 0 01-6 0H2a1 1 0 01-1-1v-4l3-3h2z" />
    </svg>
    <span>Add Courier Vehicle</span>
</a>

                                        <a href="../client/add_tracking"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            </svg>
                                            <span>Add Tracking</span>
                                        </a>

                                        <a href="../client/monitortracking"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4" />
                                            </svg>
                                            <span>Monitor Tracking</span>
                                        </a>
                                        <a href="../client/delivery_data_input"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 2H14L20 8V20C20 20.552 19.552 21 19 21H5C4.448 21 4 20.552 4 20V4C4 3.448 4.448 3 5 3H7ZM14 2V8H20M12 12H12.01M12 16H12.01" />
                                            </svg>
                                            <span>Delivery Info Management</span>
                                        </a>

                                    </div>
                                    <hr class="my-2 border-gray-200">
                                <?php endif; ?>

                                <?php if (hasAnyRole(['superadmin', 'hr'])): ?>
                                    <!-- Supplier Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Human Resources</div>
                                        <a href="../hr/assign_head"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Head Management</span>
                                        </a>
                                    </div>

                                    <!-- Supplier Section -->
                                    <div class="px-3 py-2">

                                        <a href="../hr/account"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Account Management</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (hasAnyRole(['superadmin', 'supplier'])): ?>
                                    <!-- Supplier Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Supplier</div>
                                        <a href="../suppliermain/suppliers"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Profile</span>
                                        </a>
                                    </div>
                                <?php endif; ?>


                                <?php if (hasAnyRole(['superadmin', 'warehouse'])): ?>
                                    <!-- Supplier Section -->
                                    <div class="px-3 py-2">
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Warehouse</div>
                                        <a href="../warehouse_management/order_list"
                                            class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Assign Orders</span>
                                        </a>
                                    </div>
                                <?php endif; ?>



                                <?php if (hasAnyRole(['superadmin', 'accountant'])): ?>
    <div class="uppercase">
        <!-- Accountant Header -->
        <div class="px-3 py-2">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Accountant</div>

            <!-- Dashboard Accountant -->
        <div class="px-3 py-2">
            <a href="../accountant/accountant.php"
                class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h18v4H3V3zm0 6h8v12H3V9zm10 0h8v8h-8V9z" />
                </svg>
                <span>Dashboard Accountant</span>
            </a>
        </div>

            <!-- Revenue Accountant -->
            <a href="../accountant/accountantdashboard.php"
                class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 1.343-3 3 0 .863.376 1.64.978 2.182A2.996 2.996 0 0012 17v1m0-10V5m0 13v1m0 0a4 4 0 004-4h-2a2 2 0 11-4 0H8a4 4 0 004 4z" />
                </svg>
                <span>Revenue Accountant</span>
            </a>
        </div>

        <!-- Add QR Code -->
        <div class="px-3 py-2">
            <a href="../accountant/manage_qr_codes.php"
                class="flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-md transition-colors duration-150">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h6v6H3V3zm12 0h6v6h-6V3zM3 15h6v6H3v-6zm12 6v-3h3v-3h-3v-3h6v9h-6z" />
                </svg>
                <span>Add QR Code</span>
            </a>
        </div>
    </div>
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

    </nav>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quickActionsBtn = document.getElementById('quickActionsBtn');
            const quickActionsDropdown = document.getElementById('quickActionsDropdown');
            const dropdownIcon = document.getElementById('dropdownIcon');
            let isOpen = false;

            function toggleDropdown() {
                isOpen = !isOpen;

                if (isOpen) {
                    quickActionsDropdown.classList.remove('opacity-0', 'invisible', 'scale-95');
                    quickActionsDropdown.classList.add('opacity-100', 'visible', 'scale-100');
                    dropdownIcon.style.transform = 'rotate(180deg)';
                } else {
                    quickActionsDropdown.classList.add('opacity-0', 'invisible', 'scale-95');
                    quickActionsDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
                    dropdownIcon.style.transform = 'rotate(0deg)';
                }
            }

            quickActionsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown();
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (isOpen && !quickActionsBtn.contains(e.target) && !quickActionsDropdown.contains(e.target)) {
                    toggleDropdown();
                }
            });

            // Close dropdown when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isOpen) {
                    toggleDropdown();
                }
            });
        });

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