<?php
ob_start();
include ROOT_PATH . "/connection/connect.php";

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
    if (!is_array($subroles))
        $subroles = [$subroles];
    return in_array($_SESSION['noble_subrole'], $subroles);
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id']) || !isset($_SESSION['noble_subrole'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl, subrole, is_head FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl, $subrole, $is_head);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
        $_SESSION['noble_subrole'] = $subrole ?? '';
        $_SESSION['noble_is_head'] = (int) $is_head;
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
    <title>Noble Home - Admin Panel</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Alpine & Tailwind -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }

        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #fb923c;
            border-radius: 99px;
        }

        /* Active nav pill */
        .nav-active {
            background: #fff7ed;
            color: #ea580c !important;
        }

        /* Sidebar link hover slide */
        .sidebar-link {
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            padding-left: 1.25rem;
        }

        /* Dropdown animation */
        .dropdown-enter {
            animation: dropIn 0.18s cubic-bezier(0.34, 1.4, 0.64, 1) forwards;
        }

        @keyframes dropIn {
            from {
                opacity: 0;
                transform: translateY(-6px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Quick action hover */
        .qa-link {
            transition: all 0.15s ease;
        }

        .qa-link:hover {
            padding-left: 1.1rem;
            background: #fff7ed;
            color: #ea580c;
        }
    </style>
</head>

<body class="bg-gray-50" x-data="{
        sidebar: false,
        menu: null,
        toggleMenu(name) { this.menu = this.menu === name ? null : name }
      }">

    <?php $cur = basename($_SERVER['PHP_SELF']); ?>

    <!-- ════════════════════════════════════════════════
     MOBILE SIDEBAR BACKDROP
════════════════════════════════════════════════ -->
    <div x-show="sidebar" x-cloak @click="sidebar = false" x-transition:enter="transition-opacity duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 z-40 md:hidden backdrop-blur-[1px]">
    </div>

    <!-- ════════════════════════════════════════════════
     MOBILE SIDEBAR
════════════════════════════════════════════════ -->
    <aside x-show="sidebar" x-cloak x-transition:enter="transition-transform duration-300 ease-out"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-200 ease-in" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed left-0 top-0 h-full w-72 bg-white z-50 md:hidden flex flex-col shadow-2xl">

        <!-- Sidebar Header -->
        <div class="flex-shrink-0 border-b border-gray-100">
            <div class="flex items-center justify-between px-5 pt-5 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl overflow-hidden bg-orange-500 flex items-center justify-center">
                        <img src="<?= BASE_URL ?>/admin/img/logo/logo.png" alt="Logo"
                            class="w-full h-full object-cover">
                    </div>
                    <div>
                        <p class="text-sm font-bold text-orange-500 leading-tight">Admin Panel</p>
                        <p class="text-[10px] text-gray-400">Noble Home</p>
                    </div>
                </div>
                <button @click="sidebar = false"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 transition-colors">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <!-- User Card -->
            <div class="mx-4 mb-4 bg-gradient-to-br from-orange-50 to-red-50 border border-orange-100 rounded-xl p-3">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center shadow flex-shrink-0">
                        <i class="ri-user-line text-white text-base"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-gray-800 truncate uppercase">
                            <?= htmlspecialchars($_SESSION['noble_name']) ?>
                        </p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[10px] font-semibold text-orange-500 uppercase">
                                <?= htmlspecialchars($_SESSION['noble_lvl']) ?>
                            </span>
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Nav -->
        <nav class="flex-1 overflow-y-auto custom-scroll px-3 py-3 space-y-0.5">

            <?php if (hasAnyRole(['superadmin'])): ?>
                <a href="../client/dashboard.php" @click="sidebar = false"
                    class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium
                  <?= $cur == 'dashboard.php' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-600 hover:text-orange-600' ?>">
                    <i class="ri-dashboard-line text-base w-4 text-center"></i>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>

            <?php if (hasAnyRole(['superadmin', 'productspecialist'])): ?>
                <!-- Products accordion -->
                <div>
                    <button @click="toggleMenu('products')"
                        class="sidebar-link w-full flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:text-orange-600 hover:bg-orange-50 transition-all">
                        <div class="flex items-center gap-3">
                            <i class="ri-box-3-line text-base w-4 text-center"></i>
                            <span>Products</span>
                        </div>
                        <i class="ri-arrow-down-s-line text-base transition-transform duration-200"
                            :class="menu === 'products' ? 'rotate-180 text-orange-500' : ''"></i>
                    </button>
                    <div x-show="menu === 'products'" x-cloak x-transition:enter="transition-all duration-200 ease-out"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="ml-7 mt-0.5 pl-3 border-l-2 border-orange-100 space-y-0.5">
                        <a href="<?= BASE_URL ?>/addnewproduct" @click="sidebar = false"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-gray-500 hover:text-orange-600 hover:bg-orange-50 transition-all">
                            <i class="ri-add-circle-line text-sm"></i> Upload Product
                        </a>
                        <a href="<?= BASE_URL ?>/updateproduct" @click="sidebar = false"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-gray-500 hover:text-orange-600 hover:bg-orange-50 transition-all">
                            <i class="ri-edit-line text-sm"></i> Update Product
                        </a>
                        <a href="../qrcodeperproduct/qrcodeitem" @click="sidebar = false"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-gray-500 hover:text-orange-600 hover:bg-orange-50 transition-all">
                            <i class="ri-qr-code-line text-sm"></i> Product QR Codes
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (hasAnyRole(['superadmin'])): ?>
                <a href="../addclient/insertclient" @click="sidebar = false"
                    class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:text-orange-600 hover:bg-orange-50 transition-all">
                    <i class="ri-team-line text-base w-4 text-center"></i>
                    <span>Client Management</span>
                </a>
            <?php endif; ?>

            <?php if (hasAnyRole(['sales', 'superadmin'])): ?>
                <a href="<?= BASE_URL ?>/adminchatmain" @click="sidebar = false"
                    class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium
                  <?= $cur == 'adminchatmain' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-600 hover:text-orange-600 hover:bg-orange-50' ?> transition-all">
                    <i class="ri-message-3-line text-base w-4 text-center"></i>
                    <span>Inquiries</span>
                </a>
            <?php endif; ?>

            <div class="border-t border-gray-100 my-2"></div>

            <a href="../../loginpage/profile"
                class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:text-orange-600 hover:bg-orange-50 transition-all">
                <i class="ri-user-settings-line text-base w-4 text-center"></i>
                <span>Profile Settings</span>
            </a>
            <a href="<?= BASE_URL ?>/logoutadmin"
                class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition-all">
                <i class="ri-logout-box-line text-base w-4 text-center"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ════════════════════════════════════════════════
     DESKTOP NAVBAR
════════════════════════════════════════════════ -->
    <nav class="sticky top-0 z-30 bg-white border-b border-gray-100 shadow-sm">
        <div class="flex items-center justify-between h-14 px-5 gap-4">

            <!-- LEFT: Hamburger + Logo -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <!-- Mobile hamburger -->
                <button @click="sidebar = true"
                    class="md:hidden w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-orange-400 hover:bg-orange-50 hover:text-orange-500 transition-all">
                    <i class="ri-menu-line"></i>
                </button>

                <!-- Logo -->
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg overflow-hidden flex-shrink-0">
                        <img src="<?= BASE_URL ?>/admin/img/logo/logo.png" alt="Logo"
                            class="w-full h-full object-cover">
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-bold text-orange-500 leading-tight">Administration</p>
                        <p class="text-[10px] text-gray-400 leading-tight">Noble Home Management</p>
                    </div>
                </div>
            </div>

            <!-- CENTER: Desktop Nav Links -->
            <div class="hidden md:flex items-center gap-1 flex-1 justify-center">
                <?php if (hasAnyRole(['superadmin'])): ?>
                    <a href="<?= BASE_URL ?>/ownerdashboard"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all
                      <?= $cur == 'dashboard.php' ? 'nav-active' : 'text-gray-500 hover:bg-orange-50 hover:text-orange-600' ?>">
                        <i class="ri-dashboard-line text-sm"></i> Dashboard
                    </a>
                <?php endif; ?>

                <?php if (hasAnyRole(['sales', 'superadmin'])): ?>
                    <a href="<?= BASE_URL ?>/adminchatmain"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all
                      <?= $cur == 'adminchatmain' ? 'nav-active' : 'text-gray-500 hover:bg-orange-50 hover:text-orange-600' ?>">
                        <i class="ri-message-3-line text-sm"></i> Inquiries
                    </a>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Quick Actions + Profile -->
            <div class="flex items-center gap-2 flex-shrink-0">

                <!-- ── QUICK ACTIONS DROPDOWN ── -->
                <div class="relative">
                    <button @click="toggleMenu('quick')"
                        :class="menu === 'quick' ? 'border-orange-400 bg-orange-50 text-orange-600' : 'border-gray-200 text-gray-600 hover:border-orange-400 hover:bg-orange-50 hover:text-orange-600'"
                        class="flex items-center gap-2 px-3 h-9 rounded-lg border text-sm font-medium transition-all">
                        <i class="ri-apps-2-line text-base"></i>
                        <span class="hidden sm:inline">Quick Actions</span>
                        <i class="ri-arrow-down-s-line text-base transition-transform duration-200"
                            :class="menu === 'quick' ? 'rotate-180' : ''"></i>
                    </button>

                    <!-- Quick Actions Panel -->
                    <div x-show="menu === 'quick'" x-cloak @click.away="menu = null" class="dropdown-enter absolute right-0 top-full mt-2
                            w-64 bg-white rounded-2xl border border-gray-100
                            shadow-xl z-50 overflow-x-hidden max-h-[80vh] overflow-y-auto custom-scroll">

                        <div class="p-2 space-y-0.5">

                            <?php if (hasAnyRole(['superadmin'])): ?>
                                <!-- Section: Operational -->
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-2 pb-1">
                                    Operational</p>

                                <a href="<?= BASE_URL ?>/ownerdashboard"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-dashboard-line text-base text-orange-400 w-4"></i> Dashboard
                                </a>
                                <a href="<?= BASE_URL ?>/approvepurchaseorder"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-checkbox-multiple-line text-base text-orange-400 w-4"></i> Project Approval
                                </a>

                                <?php
                                $pendingCountSql = "SELECT COUNT(*) as c FROM po_attachments WHERE superadmin_approval_status = 'pending'";
                                $pendingRes = $conn->query($pendingCountSql);
                                $pendingCount = $pendingRes->fetch_assoc()['c'] ?? 0;
                                ?>
                                <a href="<?= BASE_URL ?>/superadminpoapproval"
                                    class="qa-link relative flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-file-list-line text-base text-orange-400 w-4"></i>
                                    Individual P.O Approval
                                    <?php if ($pendingCount > 0): ?>
                                        <span
                                            class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5 leading-none">
                                            <?= $pendingCount > 99 ? '99+' : $pendingCount ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <a href="<?= BASE_URL ?>/superadminaccountant"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-calculator-line text-base text-orange-400 w-4"></i> Accountant Dashboard
                                </a>
                                <a href="<?= BASE_URL ?>/superadminlogistic"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-truck-line text-base text-orange-400 w-4"></i> Logistic Dashboard
                                </a>
                                <a href="<?= BASE_URL ?>/warehousedashboard"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-store-2-line text-base text-orange-400 w-4"></i> Warehouse Dashboard
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['productspecialist'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Products</p>
                                <a href="<?= BASE_URL ?>/addnewproduct"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-add-circle-line text-base text-orange-400 w-4"></i> Upload Product
                                </a>
                                <a href="<?= BASE_URL ?>/updateproduct"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-edit-line text-base text-orange-400 w-4"></i> Update Product
                                </a>
                                <?php if ($_SESSION['noble_is_head'] == 1): ?>
                                    <a href="<?= BASE_URL ?>/banner"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-price-tag-3-line text-base text-orange-400 w-4"></i> Banner Discount
                                    </a>
                                    <a href="<?= BASE_URL ?>/supplierlist"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-truck-line text-base text-orange-400 w-4"></i> Supplier Management
                                    </a>
                                    <a href="<?= BASE_URL ?>/category"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-folder-line text-base text-orange-400 w-4"></i> Category Management
                                    </a>
                                    <a href="<?= BASE_URL ?>/addbestseller"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-star-line text-base text-orange-400 w-4"></i> Bestseller Management
                                    </a>
                                    <a href="../qrcodeperproduct/qrcodeitem.php"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-qr-code-line text-base text-orange-400 w-4"></i> Product QR Codes
                                    </a>
                                    <a href="<?= BASE_URL ?>/quantitymanagement"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-shopping-cart-line text-base text-orange-400 w-4"></i> Min. Order Quantity
                                    </a>
                                    <a href="<?= BASE_URL ?>/discountbanner"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-price-tag-3-line text-base text-orange-400 w-4"></i> Promotion Discount
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['sales'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Sales</p>
                                <a href="<?= BASE_URL ?>/unassignedorder"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-user-3-line text-base text-orange-400 w-4"></i> Client List
                                </a>
                                <a href="<?= BASE_URL ?>/backtracking"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="fa-solid fa-file-zipper text-base text-orange-400 w-4"></i> Backtracking Board
                                </a>
                                <a href="<?= BASE_URL ?>/ordermain"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="fa-solid fa-cart-arrow-down text-base text-orange-400 w-4"></i> Orders
                                </a>
                                <a href="<?= BASE_URL ?>/customizedwindow"
                                    class="qa-link relative flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-bar-chart-grouped-line text-base text-orange-400 w-4"></i>
                                    Customize Quotation
                                    <span id="quote-badge"
                                        class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5 leading-none hidden">
                                        <span id="quote-count">0</span>
                                    </span>
                                </a>
                                <a href="<?= BASE_URL ?>/targetpricemanagement"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="fa-solid fa-magnifying-glass-dollar text-base text-orange-400 w-4"></i> Target
                                    Price
                                </a>
                                <a href="<?= BASE_URL ?>/generatereferral"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="fa-solid fa-qrcode text-base text-orange-400 w-4"></i> Referral Code
                                </a>
                                <a href="../project_management/view_companies.php"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-task-line text-base text-orange-400 w-4"></i> Project Management
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['logistic'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Logistics</p>
                                <?php if (!hasSubrole(['dispatcher'])): ?>
                                    <a href="<?= BASE_URL ?>/logistic"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-dashboard-3-line text-base text-orange-400 w-4"></i> Dashboard
                                    </a>
                                    <a href="<?= BASE_URL ?>/qrscanner"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-qr-code-line text-base text-orange-400 w-4"></i> QR Scanner
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/logisticdispatcherdashboard"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-map-pin-line text-base text-orange-400 w-4"></i> Dispatcher Dashboard
                                </a>
                                <a href="<?= BASE_URL ?>/logistictranspoadd"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-truck-line text-base text-orange-400 w-4"></i> Add Courier Vehicle
                                </a>
                                <a href="../client/delivery_data_input"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-file-info-line text-base text-orange-400 w-4"></i> Delivery Info
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['hr'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Human Resources</p>
                                <a href="<?= BASE_URL ?>/assignhead"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-user-star-line text-base text-orange-400 w-4"></i> Head Management
                                </a>
                                <a href="<?= BASE_URL ?>/account"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-account-circle-line text-base text-orange-400 w-4"></i> Account Management
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['supplier'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Supplier</p>
                                <a href="../suppliermain/suppliers"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-building-line text-base text-orange-400 w-4"></i> Profile
                                </a>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['warehouse'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Warehouse</p>
                                <?php if (!hasSubrole(['warehouse_receiver', 'warehouse_staff'])): ?>
                                    <a href="<?= BASE_URL ?>/warehousedashboard"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-archive-line text-base text-orange-400 w-4"></i> Head Dashboard
                                    </a>
                                    <a href="../warehouse_management/warehouse_staff_management_main"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-list-check-2 text-base text-orange-400 w-4"></i> Assign Orders
                                    </a>
                                <?php endif; ?>
                                <?php if (!hasSubrole(['warehouse_staff'])): ?>
                                    <a href="<?= BASE_URL ?>/receiverlistmain"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-search-eye-line text-base text-orange-400 w-4"></i> Assigned Receive Items
                                    </a>
                                    <a href="<?= BASE_URL ?>/qrscanner"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-qr-code-line text-base text-orange-400 w-4"></i> QR Scanner
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['accountant'])): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pt-1 pb-1">
                                    Accountant</p>
                                <?php if (!hasSubrole(['document_controller'])): ?>
                                    <a href="<?= BASE_URL ?>/accountant"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-dashboard-line text-base text-orange-400 w-4"></i> Dashboard
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/accountantorderview"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-folder-shield-2-line text-base text-orange-400 w-4"></i> Document
                                    Controller
                                </a>
                                <?php if (!hasSubrole(['document_controller'])): ?>
                                    <a href="<?= BASE_URL ?>/accountantvieworder"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-file-chart-line text-base text-orange-400 w-4"></i> Project Approval
                                    </a>
                                    <a href="<?= BASE_URL ?>/accountantsalescommision"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-hand-coin-line text-base text-orange-400 w-4"></i> Commission Management
                                    </a>
                                    <a href="<?= BASE_URL ?>/accountantcommissionrelease"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="fa-solid fa-money-bills text-base text-orange-400 w-4"></i> Commission Release
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/accountantdocvieworder"
                                    class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                    <i class="ri-file-text-line text-base text-orange-400 w-4"></i> Project Document
                                </a>
                                <?php if (!hasSubrole(['document_controller'])): ?>
                                    <a href="<?= BASE_URL ?>/accountantdashboard"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-bar-chart-2-line text-base text-orange-400 w-4"></i> Revenue Accountant
                                    </a>
                                    <a href="<?= BASE_URL ?>/exportexcel"
                                        class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                        <i class="ri-file-excel-2-line text-base text-orange-400 w-4"></i> Project Excel
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div><!-- /p-2 -->
                    </div><!-- /quick dropdown -->
                </div><!-- /relative -->

                <!-- Vertical Divider -->
                <div class="w-px h-5 bg-gray-200 flex-shrink-0"></div>

                <!-- ── PROFILE DROPDOWN ── -->
                <div class="relative">
                    <button @click="toggleMenu('profile')"
                        :class="menu === 'profile' ? 'ring-2 ring-orange-400 ring-offset-1' : ''"
                        class="flex items-center gap-2.5 pl-2.5 pr-2 py-1.5 rounded-full border border-gray-200 hover:border-orange-400 hover:shadow-[0_0_0_3px_rgba(251,146,60,0.12)] transition-all">
                        <!-- Name (desktop) -->
                        <div class="hidden sm:block text-right leading-none">
                            <p class="text-xs font-bold text-gray-800 uppercase">
                                <?= htmlspecialchars($_SESSION['noble_name']) ?>
                            </p>
                            <div class="flex items-center justify-end gap-1 mt-0.5">
                                <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                                <span class="text-[10px] text-green-500 font-medium">Online</span>
                            </div>
                        </div>
                        <!-- Avatar -->
                        <div
                            class="w-8 h-8 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center shadow flex-shrink-0">
                            <i class="fa-solid fa-user-tie text-white text-xs"></i>
                        </div>
                    </button>

                    <!-- Profile Dropdown -->
                    <div x-show="menu === 'profile'" x-cloak @click.away="menu = null"
                        class="dropdown-enter absolute right-0 top-full mt-2 w-56 bg-white rounded-2xl border border-gray-100 shadow-xl z-50 overflow-hidden">

                        <!-- Header -->
                        <div class="px-4 py-3 bg-gradient-to-br from-orange-50 to-red-50 border-b border-orange-100">
                            <p class="text-xs font-bold text-gray-800 uppercase tracking-wide">
                                <?= htmlspecialchars($_SESSION['noble_name']) ?>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Noble Member</p>
                            <div
                                class="flex items-center gap-1.5 mt-2 bg-green-100 text-green-700 text-[10px] font-semibold px-2 py-1 rounded-full w-fit">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                Active now
                            </div>
                        </div>

                        <!-- Links -->
                        <div class="p-2 space-y-0.5">
                            <a href="../../loginpage/profile.php"
                                class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                <i class="ri-user-settings-line text-base text-orange-400 w-4"></i> Profile Settings
                            </a>
                            <a href="../../loginpage/security.php"
                                class="qa-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-600 transition-all">
                                <i class="ri-shield-check-line text-base text-orange-400 w-4"></i> Security
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="<?= BASE_URL ?>/logoutadmin"
                                class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-red-500 hover:bg-red-50 transition-all">
                                <i class="ri-logout-box-line text-base w-4"></i> Logout
                            </a>
                        </div>
                    </div>
                </div><!-- /profile -->

            </div><!-- /right controls -->
        </div>
    </nav>

    <!-- Quotation notification polling -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (hasAnyRole(['sales'])): ?>
                function checkQuoteNotifications() {
                    fetch('<?= BASE_URL ?>/main-get-request-details-page-1-A.php?action=get-notification-count')
                        .then(r => r.json())
                        .then(data => {
                            const badge = document.getElementById('quote-badge');
                            const count = document.getElementById('quote-count');
                            if (data.success && data.pending_count > 0) {
                                count.textContent = data.pending_count;
                                badge.classList.remove('hidden');
                            } else {
                                badge.classList.add('hidden');
                            }
                        })
                        .catch(() => { });
                }
                checkQuoteNotifications();
                setInterval(checkQuoteNotifications, 30000);
            <?php endif; ?>
        });
    </script>

</body>

</html>