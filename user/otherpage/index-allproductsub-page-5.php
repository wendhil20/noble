<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Session restoration from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
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

if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Fetch all categories
$categories_query = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);

// Fetch on sale banners
$onsale_query = "SELECT * FROM onsalebanner ORDER BY uploaded_at DESC";
$onsale_result = $conn->query($onsale_query);
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

        .gradient-text {
            background: linear-gradient(135deg, #000000 0%, #434343 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sale-badge {
            animation: pulse-badge 2s ease-in-out infinite;
        }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .category-card {
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.85), rgba(50, 50, 50, 0.85));
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
        }

        .category-card:hover::before {
            opacity: 1;
        }

        .category-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        }

        .category-card:hover .category-content {
            transform: translateY(-10px);
            z-index: 2;
        }

        .category-card:hover .category-name {
            color: white;
        }

        .category-card:hover .view-btn {
            opacity: 1;
            transform: translateY(0);
        }

        .category-content {
            transition: all 0.4s ease;
            position: relative;
        }

        .view-btn {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.4s ease;
        }

        .subcategory-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
        }

        .subcategory-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
            background: linear-gradient(to bottom, #ffffff, #fff);
        }

        .banner-swiper {
            width: 100%;
            height: 600px;
            position: relative;
        }

        .banner-swiper::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 150px;
            background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);
            z-index: 1;
            pointer-events: none;
        }

        .banner-swiper .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .banner-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 1023px) {
            .banner-swiper { height: 450px; }
        }

        @media (max-width: 767px) {
            .banner-swiper { height: 350px; }
        }

        @media (max-width: 639px) {
            .banner-swiper { height: 280px; }
        }

        .sale-banner-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 3rem 2rem;
            z-index: 2;
        }

        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        .overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .floating-badge {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .discount-badge {
            background: #000;
            color: white;
            font-weight: 700;
        }

        .category-name {
            transition: color 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Hero Banner Swiper -->
    <?php if ($onsale_result->num_rows > 0): ?>
        <div class="w-full bg-black relative mb-12">
            <div class="swiper banner-swiper">
                <div class="swiper-wrapper">
                    <?php while ($banner = $onsale_result->fetch_assoc()): ?>
                        <div class="swiper-slide">
                            <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                alt="Sale Banner"
                                class="w-full h-full object-cover">
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="sale-banner-overlay">
                    <div class="max-w-7xl mx-auto px-4">
                        <div class="sale-badge inline-block bg-white text-black px-6 py-2 text-sm font-bold mb-4">
                            🔥 LIMITED TIME OFFER
                        </div>
                        <h1 class="text-white text-5xl md:text-7xl font-bold mb-4">
                            MEGA SALE
                        </h1>
                        <p class="text-gray-200 text-xl md:text-2xl mb-6">
                            Up to 70% OFF on Selected Items
                        </p>
                    </div>
                </div>

                <div class="swiper-button-prev !w-12 !h-12 !bg-white hover:!bg-black hover:!text-white !rounded-full !transition-all !duration-300 !flex !items-center !justify-center after:!content-[''] !shadow-xl !left-4 md:!left-8">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </div>
                <div class="swiper-button-next !w-12 !h-12 !bg-white hover:!bg-black hover:!text-white !rounded-full !transition-all !duration-300 !flex !items-center !justify-center after:!content-[''] !shadow-xl !right-4 md:!right-8">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="overlay fixed inset-0 bg-black bg-opacity-60 z-40 md:hidden" onclick="closeSidebar()"></div>

    <!-- Mobile Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 md:hidden overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 border-b border-gray-200 pb-4">
                <h2 class="text-2xl font-bold text-black">Categories</h2>
                <button onclick="closeSidebar()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-3">
                <?php
                $categories_result->data_seek(0);
                while ($category = $categories_result->fetch_assoc()):
                ?>
                    <button onclick="loadSubcategories(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>'); closeSidebar();"
                        class="category-btn-mobile w-full text-left p-4 border-2 border-gray-200 rounded-xl hover:border-black hover:bg-gray-50 transition-all duration-300 group"
                        data-category-id="<?= $category['id'] ?>">
                        <div class="flex items-center gap-3">
                            <?php if ($category['image_path']): ?>
                                <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                    alt="<?= htmlspecialchars($category['name']) ?>"
                                    class="w-14 h-14 object-contain rounded-lg bg-gray-50">
                            <?php else: ?>
                                <div class="w-14 h-14 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <span class="discount-badge text-xs px-2 py-1 rounded mb-1 inline-block">SALE</span>
                                <p class="font-semibold text-gray-900 uppercase text-sm"><?= htmlspecialchars($category['name']) ?></p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="mb-16 text-center">
            <div class="inline-block">
                <div class="floating-badge bg-black text-white text-sm font-bold px-6 py-2 rounded-full mb-4">
                    🏷️ EXCLUSIVE DEALS
                </div>
                <h1 class="text-5xl md:text-6xl font-bold text-black mb-4">
                    Sale Items
                </h1>
                <p class="text-gray-600 text-lg md:text-xl max-w-2xl mx-auto">
                    Discover amazing discounts across all categories
                </p>
                <div class="h-1 w-32 bg-black mx-auto mt-6"></div>
            </div>
            <button onclick="openSidebar()" class="md:hidden mt-6 bg-black text-white px-6 py-3 rounded-xl flex items-center gap-2 hover:bg-gray-800 transition-all duration-300 mx-auto shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Browse Categories
            </button>
        </div>

        <!-- Desktop Categories Grid -->
        <div class="mb-16 hidden md:block">
            <div class="flex items-center justify-center gap-3 mb-10">
                <div class="h-px w-16 bg-black"></div>
                <h2 class="text-3xl font-bold text-black">Sale Categories</h2>
                <div class="h-px w-16 bg-black"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php
                $categories_result->data_seek(0);
                while ($category = $categories_result->fetch_assoc()):
                ?>
                    <button onclick="loadSubcategories(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>')"
                        class="category-btn category-card group bg-white border-2 border-gray-200 rounded-2xl overflow-hidden"
                        data-category-id="<?= $category['id'] ?>">
                        
                        <?php if ($category['image_path']): ?>
                            <div class="aspect-[4/3] overflow-hidden bg-gray-50 relative">
                                <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                    alt="<?= htmlspecialchars($category['name']) ?>"
                                    class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-110">
                                
                                <div class="category-content absolute inset-0 flex items-end justify-center p-6">
                                    <div class="text-center w-full">
                                        <span class="discount-badge text-xs px-3 py-1 rounded-full mb-3 inline-block">SALE</span>
                                        <h3 class="category-name text-gray-900 text-xl font-bold mb-3 uppercase">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </h3>
                                        <div class="view-btn bg-white text-black px-6 py-2 rounded-full font-semibold inline-block shadow-lg">
                                            View Products →
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center relative">
                                <svg class="w-20 h-20 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                
                                <div class="category-content absolute inset-0 flex items-end justify-center p-6">
                                    <div class="text-center w-full">
                                        <span class="discount-badge text-xs px-3 py-1 rounded-full mb-3 inline-block">SALE</span>
                                        <h3 class="category-name text-gray-900 text-xl font-bold mb-3 uppercase">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </h3>
                                        <div class="view-btn bg-white text-black px-6 py-2 rounded-full font-semibold inline-block shadow-lg">
                                            View Products →
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </button>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Subcategories Section -->
        <div id="subcategories-section" class="hidden">
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-12">
                <div class="flex items-center justify-between mb-8 pb-6 border-b-2 border-black">
                    <div>
                        <span class="discount-badge text-xs px-3 py-1 rounded-full inline-block mb-2">ON SALE</span>
                        <h2 class="text-3xl font-bold text-black">
                            <span id="category-title">Subcategories</span>
                        </h2>
                    </div>
                    <button onclick="hideSubcategories()"
                        class="px-6 py-3 bg-black text-white rounded-xl hover:bg-gray-800 transition-all duration-300 font-semibold shadow-lg">
                        ✕ Close
                    </button>
                </div>

                <div id="subcategories-content" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                    <!-- Subcategories will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const bannerSwiper = new Swiper('.banner-swiper', {
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            speed: 1000,
        });

        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebar-overlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebar-overlay').classList.remove('open');
            document.body.style.overflow = '';
        }

        function loadSubcategories(categoryId, categoryName) {
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('border-black', 'shadow-xl');
                btn.classList.add('border-gray-200');
            });

            document.querySelectorAll('.category-btn-mobile').forEach(btn => {
                btn.classList.remove('border-black', 'bg-gray-50');
                btn.classList.add('border-gray-200');
            });

            const clickedBtn = document.querySelector(`.category-btn[data-category-id="${categoryId}"]`);
            if (clickedBtn) {
                clickedBtn.classList.remove('border-gray-200');
                clickedBtn.classList.add('border-black', 'shadow-xl');
            }

            const clickedMobileBtn = document.querySelector(`.category-btn-mobile[data-category-id="${categoryId}"]`);
            if (clickedMobileBtn) {
                clickedMobileBtn.classList.remove('border-gray-200');
                clickedMobileBtn.classList.add('border-black', 'bg-gray-50');
            }

            setTimeout(() => {
                document.getElementById('subcategories-section').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);

            const section = document.getElementById('subcategories-section');
            section.classList.remove('hidden');
            section.style.opacity = '0';
            setTimeout(() => {
                section.style.transition = 'opacity 0.5s';
                section.style.opacity = '1';
            }, 10);

            document.getElementById('category-title').textContent = categoryName;

            document.getElementById('subcategories-content').innerHTML = `
                <div class="col-span-full text-center py-16">
                    <div class="inline-block">
                        <svg class="animate-spin h-16 w-16 text-black" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600 mt-6 font-medium text-lg">Loading products...</p>
                </div>
            `;

            fetch(`allproduct-allproduct_get-page-3-A.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.subcategories.length > 0) {
                        let html = '';
                        data.subcategories.forEach(sub => {
                            const imagePath = sub.image_path ?
                                `../../uploads/${sub.subcategory_slug}/${sub.image_path}` :
                                null;

                            html += `
                                <a href="allproduct-allproductsub_variant-page-3-A.php?subcategory_id=${sub.id}&sale=1" 
                                   class="subcategory-card bg-white border-2 border-gray-200 rounded-2xl overflow-hidden hover:border-black cursor-pointer group">
                                    <div class="aspect-square overflow-hidden bg-gray-50 relative">
                                        ${imagePath 
                                            ? `<img src="${imagePath}" 
                                                    alt="${sub.subcategory_name}" 
                                                    class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500">
                                               <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent flex items-end justify-center p-4">
                                                   <div class="text-center">
                                                       <span class="discount-badge text-xs px-2 py-1 rounded-full mb-2 inline-block">SALE</span>
                                                       <h4 class="text-white text-sm font-bold uppercase drop-shadow-lg">
                                                           ${sub.subcategory_name}
                                                       </h4>
                                                   </div>
                                               </div>`
                                            : `<div class="w-full h-full flex items-center justify-center flex-col p-4">
                                                <svg class="w-16 h-16 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                </svg>
                                                <span class="discount-badge text-xs px-2 py-1 rounded-full mb-2 inline-block">SALE</span>
                                                <p class="text-gray-900 text-sm font-semibold text-center uppercase">${sub.subcategory_name}</p>
                                            </div>`
                                        }
                                    </div>
                                </a>
                            `;
                        });
                        document.getElementById('subcategories-content').innerHTML = html;
                    } else {
                        document.getElementById('subcategories-content').innerHTML = `
                            <div class="col-span-full text-center py-16">
                                <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="text-gray-600 text-xl font-medium">No products available</p>
                                <p class="text-gray-400 text-sm mt-2">Check back soon for new deals</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('subcategories-content').innerHTML = `
                        <div class="col-span-full text-center py-16">
                            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-gray-900 font-semibold text-lg">Failed to load products</p>
                            <p class="text-gray-500 text-sm mt-2">Please try again later</p>
                        </div>
                    `;
                });
        }

        function hideSubcategories() {
            const section = document.getElementById('subcategories-section');
            section.style.transition = 'opacity 0.3s';
            section.style.opacity = '0';
            setTimeout(() => {
                section.classList.add('hidden');
            }, 300);

            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('border-black', 'shadow-xl');
                btn.classList.add('border-gray-200');
            });

            document.querySelectorAll('.category-btn-mobile').forEach(btn => {
                btn.classList.remove('border-black', 'bg-gray-50');
                btn.classList.add('border-gray-200');
            });

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>
</body>

</html>