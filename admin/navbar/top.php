<?php
ob_start();
include '../../connection/connect.php';

function hasAnyRole($roles)
{
    if (!isset($_SESSION['noble_lvl']))
        return false;
    return in_array($_SESSION['noble_lvl'], $roles);
}

function hasSubrole($subroles)
{
    if (!isset($_SESSION['noble_subrole']))
        return false;

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

// Sa loob ng session setup mo (kung saan mo nilo-load ang noble_name, noble_lvl, etc.)
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id']) || !isset($_SESSION['noble_subrole'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl, subrole, is_head FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl, $subrole, $is_head);  // ← dagdag is_head
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
        $_SESSION['noble_subrole'] = $subrole ?? '';
        $_SESSION['noble_is_head'] = (int) $is_head; // ← i-save sa session
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
        $_SESSION['noble_subrole'] = '';
        $_SESSION['noble_is_head'] = 0;
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
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden">
    </div>

    <!-- Mobile Sidebar -->
    <aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full" @click.away="sidebarOpen = false" x-cloak
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
                            <span
                                class="text-xl font-bold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">
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
                <div
                    class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-4 shadow-sm border border-orange-100">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-lg">
                            <i class="ri-user-line text-white text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate uppercase">
                                <?= htmlspecialchars($_SESSION['noble_name']) ?>
                            </p>
                            <div class="flex items-center space-x-2">
                                <span
                                    class="text-xs text-orange-600 font-medium uppercase"><?= htmlspecialchars($_SESSION['noble_lvl']) ?></span>
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
                <a href="../client/dashboard.php" @click="sidebarOpen = false"
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

                    <div x-show="activeDropdown === 'products'" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0" x-cloak
                        class="ml-4 space-y-1 pl-3 border-l-2 border-orange-200">
                        <a href="../shop/adminshop.php" @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-add-circle-line text-lg"></i>
                            <span>Upload Product</span>
                        </a>
                        <a href="../shop/adminupdateshop.php" @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-edit-line text-lg"></i>
                            <span>Update Product</span>
                        </a>
                        <a href="../qrcodeperproduct/qrcodeitem" @click="sidebarOpen = false"
                            class="submenu-item flex items-center space-x-3 px-4 py-2.5 text-sm text-gray-600 hover:text-orange-600 rounded-lg">
                            <i class="ri-qr-code-line text-lg"></i>
                            <span>Product QR Codes</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (hasAnyRole(['superadmin'])): ?>
                <a href="../addclient/insertclient" @click="sidebarOpen = false"
                    class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium <?= $current_page == 'insertclient' ? 'text-orange-600 active-link' : 'text-gray-700 hover:bg-gray-50' ?>">
                    <i class="ri-team-line text-xl"></i>
                    <span>Client Management</span>
                </a>
            <?php endif; ?>

            <?php if (hasAnyRole(['sales', 'superadmin'])): ?>
                <a href="../chatadmin/admin_chatmain" @click="sidebarOpen = false"
                    class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium <?= $current_page == 'admin_chatmain' ? 'text-orange-600 active-link' : 'text-gray-700 hover:bg-gray-50' ?>">
                    <i class="ri-message-3-line text-xl"></i>
                    <span>Inquiries</span>
                </a>
            <?php endif; ?>


            <!-- Divider -->
            <div class="pt-4 border-t border-gray-200 mt-4"></div>

            <!-- Profile & Settings -->
            <a href="../../loginpage/profile" @click="sidebarOpen = false"
                class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                <i class="ri-user-settings-line text-xl"></i>
                <span>Profile Settings</span>
            </a>

            <a href="../../loginpage/logout" @click="sidebarOpen = false"
                class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-red-600 hover:bg-red-50">
                <i class="ri-logout-box-line text-xl"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Desktop Navigation -->
    <nav class="bg-white border-b border-black/[0.06] shadow-[0_2px_16px_rgba(0,0,0,0.06)] sticky top-0 z-30">
        <div class="flex items-center justify-between h-[60px] px-6 gap-4">

            <!-- ── LOGO ── -->
            <div class="flex items-center gap-2.5 flex-shrink-0">
                <!-- Mobile hamburger -->
                <button @click="sidebarOpen = true"
                    class="md:hidden w-9 h-9 flex items-center justify-center rounded-[10px] border border-[#e8e4df] text-gray-500 hover:border-orange-400 hover:bg-orange-50 hover:text-orange-500 transition-all">
                    <i class="ri-menu-line text-lg"></i>
                </button>

                <div class="w-9 h-9 rounded-[10px] overflow-hidden shadow-sm flex-shrink-0">
                    <img src="../img/logo/logo.png" alt="Logo" class="w-full h-full object-cover">
                </div>
                <div class="hidden sm:flex flex-col leading-none">
                    <span
                        class="font-extrabold text-[17px] bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent tracking-tight"
                        style="font-family:'Syne',sans-serif;">Administration</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Noble Home Management</span>
                </div>
            </div>

            <!-- ── CENTER NAV LINKS ── -->
            <div class="hidden md:flex items-center gap-0.5 flex-1 justify-center">
                <?php if (hasAnyRole(['superadmin'])): ?>
                    <a href="../client/owner_dashboard.php"
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-[10px] text-[13.5px] font-medium transition-all
          <?= $current_page == 'dashboard.php' ? 'bg-orange-50 text-orange-600' : 'text-gray-500 hover:bg-orange-50 hover:text-orange-600' ?>">
                        <i class="ri-dashboard-line text-[15px]"></i> Dashboard
                    </a>
                <?php endif; ?>

                <?php if (hasAnyRole(['sales', 'superadmin'])): ?>
                    <a href="../chatadmin/admin_chatmain"
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-[10px] text-[13.5px] font-medium transition-all
          <?= $current_page == 'admin_chatmain' ? 'bg-orange-50 text-orange-600' : 'text-gray-500 hover:bg-orange-50 hover:text-orange-600' ?>">
                        <i class="ri-message-3-line text-[15px]"></i> Inquiries
                    </a>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT CONTROLS ── -->
            <div class="flex items-center gap-1.5 flex-shrink-0">

                <!-- Quick Actions -->
                <div class="static sm:relative">
                    <button @click="quickActionsOpen = !quickActionsOpen"
                        class="flex items-center gap-2 px-3 h-[38px] rounded-[10px] border border-[#e8e4df] bg-white text-[13px] font-medium text-gray-500 hover:border-orange-400 hover:bg-orange-50 hover:text-orange-600 hover:shadow-[0_0_0_4px_rgba(232,93,38,0.07)] transition-all"
                        :class="quickActionsOpen ? 'border-orange-400 bg-orange-50 text-orange-600' : ''">
                        <div
                            class="w-5 h-5 bg-gradient-to-br from-orange-500 to-red-600 rounded-[6px] flex items-center justify-center text-white text-[11px] flex-shrink-0">
                            <i class="ri-flashlight-line"></i>
                        </div>
                        <span class="hidden sm:inline">Quick Actions</span>
                        <i class="ri-arrow-down-s-line text-[15px] transition-transform duration-200"
                            :class="quickActionsOpen ? 'rotate-180' : ''"></i>
                    </button>

                    <!-- Quick Actions Panel (same code from previous response) -->
                    <div x-show="quickActionsOpen" @click.away="quickActionsOpen = false"
                        x-transition:enter="transition ease-[cubic-bezier(0.34,1.4,0.64,1)] duration-200"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1" x-cloak
                        class="absolute left-1/2 -translate-x-1/2 sm:left-0 sm:translate-x-0 top-full mt-2 w-[92vw] max-w-[300px] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.12)] border border-black/[0.06] overflow-hidden z-50 flex flex-col">
                        <!-- (ilagay dito ang Quick Actions dropdown content mula sa previous response) -->
                        <div class="py-2">

                            <?php if (hasAnyRole(['superadmin'])): ?>
                                <div class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        Operational Manager</div>
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
                                            <span
                                                class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center ">
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
                                <div class="mb-1">
                                    <div class="flex items-center gap-1.5 px-2.5 pt-2 pb-1">
                                        <div
                                            class="w-5 h-5 rounded-[5px] bg-violet-50 text-violet-500 flex items-center justify-center text-[11px]">
                                            <i class="ri-box-3-line"></i>
                                        </div>
                                        <span
                                            class="text-[10.5px] font-semibold uppercase tracking-[0.6px] text-gray-400">Product
                                            Management</span>
                                    </div>

                                    <!-- Always visible — kahit non-head -->
                                    <a href="../shop/main-adminshop-page-1.php"
                                        class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                        <div
                                            class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                            <i class="ri-add-circle-line"></i>
                                        </div>
                                        Add Product
                                    </a>

                                    <a href="../shop/main-adminupdateshop-page-2.php"
                                        class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                        <div
                                            class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                            <i class="ri-edit-line"></i>
                                        </div>
                                        Update Product
                                    </a>

                                    <!-- Head-only links -->
                                    <?php if ($_SESSION['noble_is_head'] == 1): ?>
                                        <a href="../shop/main-banner-page-6.php"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-price-tag-3-line"></i>
                                            </div>
                                            Banner Discount
                                        </a>

                                        <a href="../supplier_management/suppliers_list"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-truck-line"></i>
                                            </div>
                                            Supplier Management
                                        </a>

                                        <a href="../shop/main-category-product-page-3.php"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-folder-line"></i>
                                            </div>
                                            Category Management
                                        </a>

                                        <a href="../shop/main-add_bestseller-page-4.php"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-star-line"></i>
                                            </div>
                                            Bestseller Management
                                        </a>

                                        <a href="../qrcodeperproduct/qrcodeitem"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-qr-code-line"></i>
                                            </div>
                                            Product QR Codes
                                        </a>

                                        <a href="../shop/quantity-management"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-shopping-cart-line"></i>
                                            </div>
                                            Minimum Order Quantity
                                        </a>

                                        <a href="../shop/promotion-discount"
                                            class="qa-item flex items-center gap-2.5 px-2.5 py-2 rounded-[10px] text-[13px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                            <div
                                                class="w-[30px] h-[30px] rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center text-sm text-gray-500 group-hover:text-orange-500 transition-colors flex-shrink-0">
                                                <i class="ri-price-tag-3-line"></i>
                                            </div>
                                            Promotion Discount Banner
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="h-px bg-[#f5f0eb] mx-2 my-1"></div>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['sales'])): ?>
                                <div class="px-3 py-2">

                                    <!-- SALES HEADER WITH ICON -->
                                    <div
                                        class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        <i class="ri-store-2-line text-base"></i>
                                        <span>Sales</span>
                                    </div>

                                    <!-- Client List -->
                                    <a href="../orders/unassigned_orders.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-user-3-line text-lg"></i>
                                        <span>Client List</span>
                                    </a>

                                    <!-- Orders -->
                                    <a href="../orders/ordering" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-shopping-cart-2-line text-lg"></i>
                                        <span>Orders</span>
                                    </a>

                                    <!-- Dashboard Sales with Notification Badge -->
                                    <a href="../orders/main-dashboardorder-page-1" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
           hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150 relative">
                                        <i class="ri-bar-chart-grouped-line text-lg"></i>
                                        <span>Customize Quotation Request</span>

                                        <!-- Notification Badge -->
                                        <span id="quote-notification" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 
               flex items-center justify-center hidden">
                                            <span id="quote-count">0</span>
                                        </span>
                                    </a>

                                    <script>
                                        // Check notification count on page load
                                        document.addEventListener('DOMContentLoaded', function () {
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
                                    <a href="../orders/target-price-management.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-price-tag-3-line text-lg"></i>
                                        <span>Target Price Management</span>
                                    </a>

                                    <!-- Referral Code Generator -->
                                    <a href="../orders/generate_referral.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-gift-line text-lg"></i>
                                        <span>Referral Generate Code</span>
                                    </a>

                                    <!-- Project Management -->
                                    <a href="../project_management/view_companies.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
    hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">

                                        <i class="ri-task-line text-lg"></i>
                                        <span>Project Management</span>
                                    </a>
                                </div>
                            <?php endif; ?>


                            <?php if (hasAnyRole(['', 'logistic'])): ?>
                                <div class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        Logistics</div>
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
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        Human Resources</div>
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
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        Supplier</div>
                                    <a href="../suppliermain/suppliers"
                                        class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-building-line text-lg"></i>
                                        <span>Profile</span>
                                    </a>
                                </div>

                            <?php endif; ?>

                            <?php if (hasAnyRole(['warehouse'])): ?>
                                <div class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
                                        Warehouse</div>

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
                                    <div
                                        class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">
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
                                        <a href="../accountant/accountant_view_orders" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
              hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                            <i class="ri-money-dollar-circle-line text-lg"></i>
                                            <span>Dashboard</span>
                                        </a>
                                    <?php endif; ?>

                                    <a href="../accountant/document_controller_view_orders" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
           hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                        <i class="ri-file-text-line text-lg"></i>
                                        <span>Project Document</span>
                                    </a>


                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                        <a href="../accountant/accountantdashboard.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                            <i class="ri-money-dollar-circle-line text-lg"></i>
                                            <span>Revenue Accountant</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                        <!-- Add QR Code -->
                                        <a href="../accountant/manage_qr_codes.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
                  hover:bg-orange-50 hover:text-orange-600 rounded-lg transition-all duration-150">
                                            <i class="ri-qr-code-line text-lg"></i>
                                            <span>Add QR Code</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasAnyRole(['']) || !hasSubrole(['document_controller'])): ?>
                                        <!-- Project Excel -->
                                        <a href="../accountant/accountantexcel.php" class="quick-action-item flex items-center space-x-3 px-3 py-2.5 text-sm text-gray-700 
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

                <!-- Vertical divider -->
                <div class="w-px h-6 bg-[#e8e4df] flex-shrink-0"></div>

                <!-- Notifications -->
                <div class="relative" x-data="notificationPanel()">
                    <button
                        @click="activeDropdown = activeDropdown === 'notifications' ? null : 'notifications'; openDropdown();"
                        class="relative w-[38px] h-[38px] flex items-center justify-center rounded-[10px] border border-[#e8e4df] bg-white text-gray-500 hover:border-orange-400 hover:bg-orange-50 hover:text-orange-500 hover:shadow-[0_0_0_4px_rgba(232,93,38,0.07)] transition-all">
                        <span x-show="unreadCount > 0"
                            class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-orange-500 opacity-40 animate-ping"></span>
                        <i class="ri-notification-3-line text-[17px]"></i>
                        <span x-show="unreadCount > 0" x-text="unreadCount"
                            class="absolute -top-1.5 -right-1.5 min-w-[17px] h-[17px] px-0.5 bg-orange-500 text-white text-[9.5px] font-bold rounded-full flex items-center justify-center border-2 border-white leading-none"
                            style="font-family:'Syne',sans-serif;"></span>
                    </button>
                    <!-- (ilagay dito ang Notifications dropdown content mula sa previous response) -->

                    <!-- Notifications Panel -->
                    <div x-show="activeDropdown === 'notifications'" @click.away="activeDropdown = null"
                        x-transition:enter="transition ease-[cubic-bezier(0.34,1.4,0.64,1)] duration-200"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1" x-cloak
                        class="absolute right-0 top-full mt-2.5 w-[360px] bg-white rounded-[18px] shadow-[0_20px_60px_rgba(0,0,0,0.12),0_4px_16px_rgba(0,0,0,0.06)] border border-black/[0.06] overflow-hidden z-50 flex flex-col">

                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 py-3.5 border-b border-orange-50">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-[30px] h-[30px] bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center text-white text-sm flex-shrink-0">
                                    <i class="ri-notification-3-line"></i>
                                </div>
                                <span class="font-bold text-sm text-gray-900"
                                    style="font-family: 'Syne', sans-serif;">Notifications</span>
                                <span x-show="unreadCount > 0"
                                    class="text-[11px] font-semibold text-orange-600 bg-orange-50 border border-orange-200/50 px-2 py-0.5 rounded-full"
                                    x-text="unreadCount + ' new'"></span>
                            </div>
                            <div class="flex items-center gap-0.5">
                                <button @click="markAllAsRead()" x-show="unreadCount > 0" title="Mark all as read"
                                    class="w-[30px] h-[30px] rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all text-[15px]">
                                    <i class="ri-check-double-line"></i>
                                </button>
                                <button @click="deleteAll()" x-show="notifications.length > 0" title="Delete all"
                                    class="w-[30px] h-[30px] rounded-lg flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all text-[15px]">
                                    <i class="ri-delete-bin-2-line"></i>
                                </button>
                                <button @click="activeDropdown = null"
                                    class="w-[30px] h-[30px] rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all text-[15px]">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Notification List -->
                        <div class="overflow-y-auto max-h-[340px] flex-1"
                            style="scrollbar-width: thin; scrollbar-color: #e5e0d8 transparent;">

                            <!-- Loading -->
                            <div x-show="loading" class="py-10 text-center">
                                <div
                                    class="w-5 h-5 border-2 border-orange-100 border-t-orange-500 rounded-full animate-spin mx-auto mb-2">
                                </div>
                                <p class="text-xs text-gray-400">Loading...</p>
                            </div>

                            <!-- Empty -->
                            <div x-show="!loading && notifications.length === 0"
                                class="py-10 text-center text-gray-400">
                                <i class="ri-notification-off-line text-3xl mb-2 block"></i>
                                <p class="text-xs">No notifications yet</p>
                            </div>

                            <!-- Items -->
                            <template x-for="notif in notifications" :key="notif.id">
                                <div class="relative flex items-start gap-3 px-4 py-3 border-b border-[#f5f0eb] last:border-none cursor-pointer group transition-colors duration-150"
                                    :class="notif.is_read ? 'opacity-70 hover:bg-[#fdfaf7]' : 'bg-[#fff8f5] hover:bg-[#fff3ed]'">

                                    <!-- Unread dot -->
                                    <span x-show="!notif.is_read"
                                        class="absolute left-1.5 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-orange-500"></span>

                                    <!-- Icon -->
                                    <div class="w-9 h-9 rounded-[10px] flex items-center justify-center flex-shrink-0 text-base"
                                        :class="getIconBg(notif.color_class)">
                                        <i :class="notif.icon_class"></i>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-[13px] text-gray-900 leading-tight"
                                            x-text="notif.title"></p>
                                        <p class="text-[12px] text-gray-500 mt-0.5 leading-snug truncate"
                                            x-text="notif.message"></p>
                                        <p class="text-[11px] text-gray-300 mt-1" x-text="formatTime(notif.created_at)">
                                        </p>
                                    </div>

                                    <!-- Hover actions -->
                                    <div
                                        class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0 self-center">
                                        <button @click.stop="toggleRead(notif.id, notif.is_read)"
                                            :title="notif.is_read ? 'Mark as unread' : 'Mark as read'"
                                            class="w-[26px] h-[26px] rounded-md flex items-center justify-center text-gray-300 hover:bg-gray-100 hover:text-gray-500 transition-all text-[13px]">
                                            <i :class="notif.is_read ? 'ri-mail-line' : 'ri-mail-open-line'"></i>
                                        </button>
                                        <button @click.stop="deleteNotification(notif.id)" title="Delete"
                                            class="w-[26px] h-[26px] rounded-md flex items-center justify-center text-gray-300 hover:bg-red-50 hover:text-red-500 transition-all text-[13px]">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="px-4 py-2.5 border-t border-[#f5f0eb] text-center">
                            <a href="#" class="text-[12px] font-medium text-orange-500 hover:underline">View all
                                notifications →</a>
                        </div>
                    </div>
                </div>

                <!-- Vertical divider -->
                <div class="w-px h-6 bg-[#e8e4df] flex-shrink-0"></div>

                <!-- Profile -->
                <div class="relative" x-data>
                    <button @click="activeDropdown = activeDropdown === 'profile' ? null : 'profile'"
                        class="flex items-center gap-2.5 pl-3 pr-1.5 py-1.5 rounded-full border border-[#e8e4df] bg-white hover:border-orange-400 hover:shadow-[0_0_0_4px_rgba(232,93,38,0.07)] transition-all">
                        <div class="hidden sm:flex flex-col items-end leading-none">
                            <span class="text-[12.5px] font-bold text-gray-900 uppercase tracking-[0.3px]"
                                style="font-family:'Syne',sans-serif;"><?= htmlspecialchars($_SESSION['noble_name']) ?></span>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="text-[10.5px] text-green-600 font-medium">Online</span>
                            </div>
                        </div>
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center shadow-md flex-shrink-0">
                            <i class="ri-user-line text-white text-sm"></i>
                        </div>
                    </button>
                    <!-- (ilagay dito ang Profile dropdown content mula sa first response) -->

                    <!-- Dropdown Panel -->
                    <div x-show="activeDropdown === 'profile'" @click.away="activeDropdown = null"
                        x-transition:enter="transition ease-[cubic-bezier(0.34,1.4,0.64,1)] duration-200"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1" x-cloak
                        class="absolute right-0 mt-2.5 w-60 bg-white rounded-2xl shadow-[0_16px_48px_rgba(0,0,0,0.12)] border border-black/5 overflow-hidden z-50">

                        <!-- Header -->
                        <div
                            class="px-4 py-4 bg-gradient-to-br from-orange-50 to-red-50 border-b border-orange-100 relative overflow-hidden">
                            <!-- decorative blob -->
                            <div
                                class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-orange-200/30 blur-xl pointer-events-none">
                            </div>

                            <p class="font-bold text-sm text-gray-900 uppercase tracking-wide">
                                <?= htmlspecialchars($_SESSION['noble_name']) ?>
                            </p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Noble Member</p>
                            <div
                                class="flex items-center gap-1.5 mt-2 bg-green-100 text-green-700 text-[11px] font-medium px-2 py-1 rounded-full w-fit">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                Active now
                            </div>
                        </div>

                        <!-- Menu Items -->
                        <div class="p-2">
                            <a href="../../loginpage/profile"
                                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13.5px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                <span
                                    class="w-8 h-8 rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center transition-colors">
                                    <i class="ri-user-settings-line text-sm"></i>
                                </span>
                                Profile Settings
                            </a>

                            <a href="../../loginpage/security"
                                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13.5px] text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-all duration-150 group">
                                <span
                                    class="w-8 h-8 rounded-lg bg-gray-100 group-hover:bg-orange-100 flex items-center justify-center transition-colors">
                                    <i class="ri-shield-check-line text-sm"></i>
                                </span>
                                Security
                            </a>

                            <div class="my-1 border-t border-gray-100"></div>

                            <a href="../../loginpage/logout"
                                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13.5px] text-red-500 hover:bg-red-50 hover:text-red-600 transition-all duration-150 group">
                                <span
                                    class="w-8 h-8 rounded-lg bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors">
                                    <i class="ri-logout-box-line text-sm"></i>
                                </span>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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
                        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
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

                getIconBg(colorClass) {
                    const map = {
                        'bg-green-100': 'bg-emerald-50 text-emerald-500',
                        'bg-blue-100': 'bg-blue-50 text-blue-500',
                        'bg-orange-100': 'bg-orange-50 text-orange-500',
                        'bg-yellow-100': 'bg-yellow-50 text-yellow-500',
                        'bg-purple-100': 'bg-purple-50 text-purple-500',
                        'bg-red-100': 'bg-red-50 text-red-500',
                    };
                    return map[colorClass] || 'bg-gray-50 text-gray-400';
                },
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


</body>

</html>