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
    <title>Categories & Subcategories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <style>
        .category-card {
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .category-card:hover::before {
            left: 100%;
        }

        .subcategory-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .subcategory-card:hover {
            transform: translateY(-4px);
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

        .banner-swiper {
            width: 100%;
            height: 500px;
        }

        .banner-swiper .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
        }

        .banner-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (min-width: 1920px) {
            .banner-swiper {
                height: 600px;
            }
        }

        @media (max-width: 1919px) {
            .banner-swiper {
                height: 500px;
            }
        }

        @media (max-width: 1439px) {
            .banner-swiper {
                height: 450px;
            }
        }

        @media (max-width: 1279px) {
            .banner-swiper {
                height: 400px;
            }
        }

        @media (max-width: 1023px) {
            .banner-swiper {
                height: 350px;
            }
        }

        @media (max-width: 767px) {
            .banner-swiper {
                height: 280px;
            }
        }

        @media (max-width: 639px) {
            .banner-swiper {
                height: 240px;
            }
        }

        @media (max-width: 479px) {
            .banner-swiper {
                height: 200px;
            }
        }

        @media (max-width: 374px) {
            .banner-swiper {
                height: 180px;
            }
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            content: '';
        }

        .swiper-pagination-bullet {
            background: white;
            opacity: 0.7;
        }

        .swiper-pagination-bullet-active {
            background: #dc2626;
            opacity: 1;
        }
    </style>
</head>

<body class="bg-white min-h-screen font-roboto">
    <?php include '../navbar/top.php'; ?>

    <!-- On Sale Banner Swiper -->
    <?php if ($onsale_result->num_rows > 0): ?>
        <div class="w-full bg-gray-100 mb-8">
            <div class="swiper banner-swiper">
                <div class="swiper-wrapper">
                    <?php while ($banner = $onsale_result->fetch_assoc()): ?>
                        <div class="swiper-slide">
                            <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                alt="On Sale Banner"
                                class="w-full h-full object-cover">
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="swiper-button-prev !w-10 !h-10 !bg-black/70 hover:!bg-red-600 !rounded-full !transition-all !duration-300 hover:!scale-110 !flex !items-center !justify-center after:!content-[''] !shadow-lg !-left-2 md:!left-[100px]">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </div>
                <div class="swiper-button-next !w-10 !h-10 !bg-black/70 hover:!bg-red-600 !rounded-full !transition-all !duration-300 hover:!scale-110 !flex !items-center !justify-center after:!content-[''] !shadow-lg !-right-2 md:!right-[100px]">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
                <div class="swiper-pagination"></div>
            </div>

            <section class="bg-black text-white py-1">
                <div class="max-w-6xl mx-auto px-1 text-center md:text-left">
                    <div class="inline-flex flex-wrap gap-3 items-center">
                        <p class="text-base md:text-lg text-black"></p>
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="overlay fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden" onclick="closeSidebar()"></div>

    <!-- Mobile Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 md:hidden overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl text-black">Sale Categories</h2>
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
                        class="category-btn-mobile w-full text-left p-4 border-2 border-gray-200 rounded-lg hover:border-red-600 hover:bg-red-50 transition-all duration-200 group"
                        data-category-id="<?= $category['id'] ?>">
                        <div class="flex items-center gap-3">
                            <?php if ($category['image_path']): ?>
                                <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                    alt="<?= htmlspecialchars($category['name']) ?>"
                                    class="w-12 h-12 object-contain rounded">
                            <?php else: ?>
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded mb-1 inline-block">SALE</span>
                                <p class="font-semibold text-gray-900 text-sm uppercase"><?= htmlspecialchars($category['name']) ?></p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div class="mb-12">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl text-black mb-2">Sale Items</h1>
                    <p class="text-gray-600 text-lg mt-2">Browse discounted products by category</p>
                    <div class="h-1 w-24 bg-red-600 mt-3"></div>
                </div>
                <button onclick="openSidebar()" class="md:hidden bg-red-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-red-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Categories
                </button>
            </div>
        </div>

        <!-- Desktop Categories Section -->
        <div class="mb-12 hidden md:block">
            <div class="flex items-center gap-3 mb-6">
                <h2 class="text-2xl text-black">Sale Categories</h2>
                <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">ON SALE</span>
            </div>

            <div class="relative">
                <button onclick="slideCategories('prev')"
                    class="absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-white border-2 border-black rounded-full p-3 hover:bg-black hover:text-white transition-all duration-300 shadow-lg -ml-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="overflow-hidden px-8">
                    <div id="categories-slider" class="flex transition-transform duration-500 ease-in-out gap-6">
                        <?php
                        $categories_result->data_seek(0);
                        while ($category = $categories_result->fetch_assoc()):
                        ?>
                            <button onclick="loadSubcategories(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>')"
                                class="category-btn category-card group flex-shrink-0 w-[calc(33.333%-1rem)] border-2 border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:border-black hover:shadow-2xl relative"
                                data-category-id="<?= $category['id'] ?>"
                                style="min-width: calc(33.333% - 1rem);">

                                <?php if ($category['image_path']): ?>
                                    <div class="aspect-[4/3] overflow-hidden bg-gray-50 relative">
                                        <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                            alt="<?= htmlspecialchars($category['name']) ?>"
                                            class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500">

                                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end justify-center p-4">
                                            <div class="text-center">
                                                <span class="bg-red-600 text-white text-xs px-2 py-1 rounded mb-2 inline-block">SALE</span>
                                                <h3 class="text-white text-lg drop-shadow-lg uppercase font-light">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center relative">
                                        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>

                                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent flex items-end justify-center p-4">
                                            <div class="text-center">
                                                <span class="bg-red-600 text-white text-xs px-2 py-1 rounded mb-2 inline-block">SALE</span>
                                                <h3 class="text-white text-lg drop-shadow-lg uppercase font-light">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </button>
                        <?php endwhile; ?>
                    </div>
                </div>

                <button onclick="slideCategories('next')"
                    class="absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-white border-2 border-black rounded-full p-3 hover:bg-black hover:text-white transition-all duration-300 shadow-lg -mr-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Subcategories Section -->
        <div id="subcategories-section" class="hidden">
            <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-red-600">
                <div>
                    <h2 class="text-2xl font-semibold text-black">
                        <span id="category-title">Subcategories</span> <span class="text-red-600 text-lg">- On Sale</span>
                    </h2>
                </div>
                <button onclick="hideSubcategories()"
                    class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors duration-300 text-sm font-medium">
                    ✕ Close
                </button>
            </div>

            <div id="subcategories-content" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-12">
                <!-- Subcategories will be loaded here -->
            </div>
        </div>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const bannerSwiper = new Swiper('.banner-swiper', {
            loop: true,
            autoplay: {
                delay: 3500,
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
            speed: 800,
        });

        let currentSlide = 0;
        const itemsPerView = 3;

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

        function slideCategories(direction) {
            const slider = document.getElementById('categories-slider');
            const totalItems = slider.children.length;
            const maxSlide = Math.ceil(totalItems / itemsPerView) - 1;

            if (direction === 'next') {
                currentSlide = currentSlide >= maxSlide ? 0 : currentSlide + 1;
            } else {
                currentSlide = currentSlide <= 0 ? maxSlide : currentSlide - 1;
            }

            slider.style.transform = `translateX(-${currentSlide * 100}%)`;
        }

        function loadSubcategories(categoryId, categoryName) {
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('border-black', 'bg-gray-50', 'shadow-xl');
                btn.classList.add('border-gray-200');
            });

            document.querySelectorAll('.category-btn-mobile').forEach(btn => {
                btn.classList.remove('border-red-600', 'bg-red-50');
                btn.classList.add('border-gray-200');
            });

            const clickedBtn = document.querySelector(`.category-btn[data-category-id="${categoryId}"]`);
            if (clickedBtn) {
                clickedBtn.classList.remove('border-gray-200');
                clickedBtn.classList.add('border-black', 'bg-gray-50', 'shadow-xl');
            }

            const clickedMobileBtn = document.querySelector(`.category-btn-mobile[data-category-id="${categoryId}"]`);
            if (clickedMobileBtn) {
                clickedMobileBtn.classList.remove('border-gray-200');
                clickedMobileBtn.classList.add('border-red-600', 'bg-red-50');
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
                        <svg class="animate-spin h-12 w-12 text-black" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600 mt-4 font-medium">Loading subcategories...</p>
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

                            // ✅ IMPORTANT: Add &sale=1 parameter here for SALE items
                            html += `
                                <a href="allproduct-allproductsub_variant-page-3-A.php?subcategory_id=${sub.id}&sale=1" 
                                   class="subcategory-card bg-white border-2 border-gray-200 rounded-xl overflow-hidden hover:border-black hover:shadow-xl cursor-pointer group">
                                    <div class="aspect-square overflow-hidden bg-gray-50 relative">
                                        ${imagePath 
                                            ? `<img src="${imagePath}" 
                                                    alt="${sub.subcategory_name}" 
                                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500">
                                               <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent flex items-end justify-center p-3">
                                                   <div class="text-center">
                                                       <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded mb-2 inline-block">SALE</span>
                                                       <h4 class="text-white text-sm drop-shadow-lg font-medium uppercase">
                                                           ${sub.subcategory_name}
                                                       </h4>
                                                   </div>
                                               </div>`
                                            : `<div class="w-full h-full flex items-center justify-center flex-col">
                                                <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                </svg>
                                                <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded mb-2 inline-block">SALE</span>
                                                <p class="text-gray-600 text-sm font-medium text-center px-2">${sub.subcategory_name}</p>
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
                                <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="text-gray-500 text-lg">No subcategories found</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('subcategories-content').innerHTML = `
                        <div class="col-span-full text-center py-16">
                            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-gray-700 font-medium">Error loading subcategories</p>
                            <p class="text-gray-500 text-sm mt-2">Please try again</p>
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
                btn.classList.remove('border-black', 'bg-gray-50', 'shadow-xl');
                btn.classList.add('border-gray-200');
            });

            document.querySelectorAll('.category-btn-mobile').forEach(btn => {
                btn.classList.remove('border-red-600', 'bg-red-50');
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