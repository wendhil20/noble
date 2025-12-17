<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/sale_page_error_log.txt');

session_name("nobleuser");
session_start();

// Check connection file
if (!file_exists('../../connection/connect.php')) {
    die('Database connection file not found');
}

include '../../connection/connect.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die('Database connection failed');
}

// Session restoration from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $_COOKIE['remember_token']);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION = array_merge($_SESSION, [
                    'user_id' => $user['id'],
                    'user_name' => $user['name'],
                    'user_email' => $user['email'] ?? '',
                    'user_mobile' => $user['mobile'] ?? ''
                ]);

                if (!empty($user['google_id'])) {
                    $_SESSION['google_logged_in'] = true;
                    $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Session restore error: " . $e->getMessage());
    }
}

// ✅ Allow guest access
$is_guest = !isset($_SESSION['user_id']);

// Fetch all categories with error handling
try {
    $categories_query = "SELECT * FROM categories ORDER BY name ASC";
    $categories_result = $conn->query($categories_query);
    
    if (!$categories_result) {
        throw new Exception("Categories query failed: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Categories fetch error: " . $e->getMessage());
    $categories_result = null;
}

// Fetch on sale banners with error handling
try {
    $onsale_query = "SELECT * FROM onsalebanner ORDER BY uploaded_at DESC";
    $onsale_result = $conn->query($onsale_query);
    
    if (!$onsale_result) {
        throw new Exception("Sale banners query failed: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Sale banners fetch error: " . $e->getMessage());
    $onsale_result = null;
}
?>
     
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Items - Best Deals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .sale-badge {
            animation: pulse 2s ease-in-out infinite;
        }

        .swiper-button-prev::after,
        .swiper-button-next::after {
            content: '' !important;
        }

        .swiper-button-prev,
        .swiper-button-next {
            background: rgba(255, 255, 255, 0.95) !important;
            border-radius: 8px;
            width: 40px !important;
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            color: #000 !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        .swiper-button-prev:hover,
        .swiper-button-next:hover {
            background: #000 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }

        .swiper-button-prev {
            left: 16px !important;
            right: auto !important;
        }

        .swiper-button-next {
            right: 16px !important;
            left: auto !important;
        }

        @media (max-width: 768px) {
            .swiper-button-prev,
            .swiper-button-next {
                width: 36px !important;
                height: 36px !important;
            }

            .swiper-button-prev {
                left: 12px !important;
            }

            .swiper-button-next {
                right: 12px !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    
    <?php 
    $navbar_path = '../navbar/top.php';
    if (file_exists($navbar_path)) {
        include $navbar_path;
    }
    ?>

    <!-- Hero Banner -->
    <?php if ($onsale_result && $onsale_result->num_rows > 0): ?>
        <div class="container mx-auto px-4 max-w-7xl mt-8 mb-16">
            <div class="swiper banner-swiper relative rounded-2xl overflow-hidden bg-gradient-to-br from-gray-900 to-gray-800 h-96 md:h-[500px]">
                <div class="swiper-wrapper">
                    <?php while ($banner = $onsale_result->fetch_assoc()): ?>
                        <div class="swiper-slide">
                            <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                alt="Sale Banner"
                                class="w-full h-full object-cover opacity-85"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="absolute inset-0 flex flex-col items-center justify-center text-center bg-gradient-to-br from-black/50 to-black/20 p-6 z-10">
                    <div class="inline-block bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-black px-4 py-2 rounded-full mb-4 uppercase tracking-wide shadow-lg sale-badge" style="font-family: 'Montserrat', sans-serif;">
                        OFFER
                    </div>
                    <h1 class="text-white text-4xl md:text-5xl font-bold mb-2" style="font-family: 'Montserrat', sans-serif; ">
                        SALE
                    </h1>
                    <p class="text-gray-200 text-sm md:text-base" style="font-family: 'Montserrat', sans-serif; ">
                        Up to 10% OFF on Selected Items
                    </p>
                </div>

                     <button class="swiper-button-prev">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button class="swiper-button-next">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Mobile Sidebar -->
    <div id="sidebar-overlay" class="overlay fixed inset-0 bg-black/50 z-40 hidden md:hidden opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeSidebar()"></div>
    <div id="sidebar" class="fixed top-0 left-0 h-full w-80 bg-white shadow-xl z-50 md:hidden overflow-y-auto -translate-x-full transition-transform duration-300">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <h2 class="text-xl font-bold">Categories</h2>
                <button onclick="closeSidebar()" class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-2">
                <?php
                if ($categories_result) {
                    $categories_result->data_seek(0);
                    while ($category = $categories_result->fetch_assoc()):
                ?>
                    <button onclick="loadSubcategories(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>'); closeSidebar();"
                        class="category-btn-mobile w-full text-left p-3 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200"
                        data-category-id="<?= $category['id'] ?>">
                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($category['name']) ?></p>
                    </button>
                <?php 
                    endwhile;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 max-w-7xl pb-10">
        <!-- Header -->
        <div class="mb-12 text-center" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
            <h1 class="text-4xl md:text-5xl font-semibold mb-3">
                Sale Collection
            </h1>
            <p class=" text-sm md:text-base mb-6">
                Discover amazing discounts on your favorite products
            </p>
            <button onclick="openSidebar()" class="md:hidden bg-black text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                Browse Categories
            </button>
        </div>

        <div class="h-px bg-gradient-to-r from-transparent via-black to-transparent mb-16"></div>

        <!-- Desktop Categories Grid -->
        <div class="hidden md:block mb-16">
            <h2 class="text-2xl font-bold  mb-8" style="font-family: 'Montserrat', sans-serif; color: #2f1200">Categories</h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php
                if ($categories_result) {
                    $categories_result->data_seek(0);
                    while ($category = $categories_result->fetch_assoc()):
                ?>
                    <button onclick="loadSubcategories(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>')"
                        class="category-btn group bg-white border border-gray-200 rounded-2xl overflow-hidden hover:-translate-y-2 hover:shadow-lg hover:border-black transition-all duration-300"
                        data-category-id="<?= $category['id'] ?>">
                        
                        <?php if (!empty($category['image_path'])): ?>
                            <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                alt="<?= htmlspecialchars($category['name']) ?>"
                                class="w-full aspect-square object-contain p-5 bg-gray-50 group-hover:scale-110 transition-transform duration-300"
                                onerror="this.style.display='none'">
                        <?php endif; ?>
                        
                        <div class="p-4 text-center">
                            <div class="inline-block bg-red-500  text-xs font-bold px-3 py-1 rounded-full mb-2 text-white" style="font-family: 'Montserrat', sans-serif;">SALE</div>
                            <h3 class=" text-sm font-semibold uppercase tracking-wide" style="font-family: 'Montserrat', sans-serif; color: #2f1200"><?= htmlspecialchars($category['name']) ?></h3>
                        </div>
                    </button>
                <?php 
                    endwhile;
                }
                ?>
            </div>
        </div>

        <!-- Subcategories Section -->
        <div id="subcategories-section" class="hidden">
            <div class="bg-white rounded-xl shadow-sm p-8 mb-8 border border-gray-200">
                <div class="flex items-center justify-between mb-8 pb-6 border-b border-gray-200">
                    <div>
                        <h2 class="text-2xl font-bold text-black" style="font-family: 'Montserrat', sans-serif;"> 
                            <span id="category-title">Subcategories</span>
                        </h2>
                    </div>
                    <button onclick="hideSubcategories()" class="bg-black text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                        Close ✕
                    </button>
                </div>

                <div id="subcategories-content" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <!-- Subcategories will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <?php 
    $footer_path = '../navbar/footer.php';
    if (file_exists($footer_path)) {
        include $footer_path;
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const swiperEl = document.querySelector('.banner-swiper');
        if (swiperEl) {
            new Swiper('.banner-swiper', {
                loop: true,
                autoplay: { delay: 5000, disableOnInteraction: false },
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                effect: 'fade',
                fadeEffect: { crossFade: true },
                speed: 800,
            });
        }

        function openSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            document.body.style.overflow = '';
        }

        function loadSubcategories(categoryId, categoryName) {
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('border-black');
                btn.classList.add('border-gray-200');
            });

            const section = document.getElementById('subcategories-section');
            document.getElementById('category-title').textContent = categoryName;

            setTimeout(() => section.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            section.classList.remove('hidden');

            const contentEl = document.getElementById('subcategories-content');
            contentEl.innerHTML = '<div class="col-span-full text-center py-12"><svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg><p class="text-gray-500 mt-3 text-sm">Loading...</p></div>';

            fetch(`allproduct-allproduct_get-page-3-A.php?category_id=${categoryId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.subcategories?.length > 0) {
                        let html = '';
                        data.subcategories.forEach(sub => {
                            const img = sub.image_path ? `../../uploads/${sub.subcategory_slug}/${sub.image_path}` : null;
                            html += `
                                <div class="subcategory-wrapper" data-subcategory-id="${sub.id}">
                                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:-translate-y-1.5 hover:shadow-md hover:border-black transition-all duration-300 cursor-pointer group" onclick="toggleSubSubcategories(${sub.id}, '${sub.subcategory_name}', '${sub.subcategory_slug}')">
                                        ${img ? `<img src="${img}" alt="${sub.subcategory_name}" class="w-full aspect-square object-contain p-4 bg-gray-50 " onerror="this.style.display='none'">` : '<div class="w-full aspect-square bg-gray-100 flex items-center justify-center"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>'}
                                        <div class="p-3 text-center border-t border-gray-100">
                                            <p class="text-xs font-semibold text-gray-900 uppercase tracking-wide">${sub.subcategory_name}</p>
                                        </div>
                                    </div>
                                    <div id="subsub-${sub.id}" class="hidden mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="space-y-2" id="subsub-list-${sub.id}">
                                            <div class="text-center py-3"><svg class="animate-spin h-6 w-6 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg></div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        contentEl.innerHTML = html;
                    } else {
                        contentEl.innerHTML = '<div class="col-span-full text-center py-12 text-gray-500"><p class="text-sm">No products available</p></div>';
                    }
                })
                .catch(e => {
                    console.error(e);
                    contentEl.innerHTML = '<div class="col-span-full text-center py-12 text-red-500"><p class="text-sm">Failed to load</p></div>';
                });
        }

        function hideSubcategories() {
            document.getElementById('subcategories-section').classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function toggleSubSubcategories(id, name, slug) {
            const el = document.getElementById(`subsub-${id}`);
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                const list = document.getElementById(`subsub-list-${id}`);
                if (list.querySelector('.animate-spin')) {
                    fetchSubSubcategories(id, slug);
                }
            } else {
                el.classList.add('hidden');
            }
        }

        function fetchSubSubcategories(id, slug) {
            const list = document.getElementById(`subsub-list-${id}`);
            list.innerHTML = '<div class="text-center py-3"><svg class="animate-spin h-6 w-6 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg></div>';

            fetch(`allproduct-allproduct_get-page-3-A.php?subcategory_id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.subsubcategories?.length > 0) {
                        let html = '';
                        data.subsubcategories.forEach(subsub => {
                            html += `<a href="allproduct-allproductsub_variant-page-3-A.php?sub_subcategory_id=${subsub.id}&sale=1" class="block p-2 bg-white border border-gray-200 rounded hover:border-black hover:bg-gray-100 transition-all"><p class="font-medium text-xs text-gray-900 uppercase">${subsub.sub_subcategory_name}</p></a>`;
                        });
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = '<div class="text-center py-3 text-gray-500 text-xs">No options</div>';
                    }
                })
                .catch(e => {
                    list.innerHTML = '<div class="text-center py-3 text-red-500 text-xs">Error loading</div>';
                });
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeSidebar();
                if (!document.getElementById('subcategories-section').classList.contains('hidden')) {
                    hideSubcategories();
                }
            }
        });
    </script>
</body>

</html>