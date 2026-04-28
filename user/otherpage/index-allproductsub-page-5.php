<?php

session_name("nobleuser");
session_start();

include ROOT_PATH . '/connection/connect.php';


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
        error_log($e->getMessage());
    }
}

$is_guest = !isset($_SESSION['user_id']);

try {
    $categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    $categories_array = [];
    if ($categories_result) {
        while ($row = $categories_result->fetch_assoc()) {
            $categories_array[] = $row;
        }
    }
} catch (Exception $e) {
    $categories_result = null;
    $categories_array = [];
}

try {
    $onsale_result = $conn->query("SELECT * FROM onsalebanner ORDER BY uploaded_at DESC");
} catch (Exception $e) {
    $onsale_result = null;
}

try {
    $material_query = "
        SELECT pv.*, pv.origin,
            pt.type_name, pt.type_image, pt.product_id,
            p.product_name, p.codename, p.main_image, p.description,
            p.view_count, p.unique_view_count,
            AVG(r.rating) AS avg_rating,
            COUNT(DISTINCT r.id) AS rating_count,
            COALESCE(SUM(si.quantity), 0) AS total_sold,
            pc.id AS color_id, pc.color_name AS color,
            pc.color_code, pc.price AS color_price
        FROM product_variants pv
        INNER JOIN product_types pt ON pv.type_id = pt.id
        INNER JOIN products p ON pt.product_id = p.id
        LEFT JOIN product_ratings r ON r.product_id = p.id
        LEFT JOIN sold_items si ON si.product_id = p.id
        LEFT JOIN product_colors pc
            ON pc.product_id = p.id
           AND pc.id = (SELECT MIN(pc2.id) FROM product_colors pc2 WHERE pc2.product_id = p.id)
        WHERE pv.discount > 0 AND p.is_archived = 0
        GROUP BY pv.id
        ORDER BY p.view_count DESC, RAND()
        LIMIT 10";
    $material_results = mysqli_query($conn, $material_query);
    $maxDiscount = 0;
    if ($material_results && mysqli_num_rows($material_results) > 0) {
        $tmpR = mysqli_query($conn, "SELECT MAX(discount) as md FROM product_variants WHERE discount > 0");
        if ($tmpR) {
            $maxDiscount = (float) (mysqli_fetch_assoc($tmpR)['md'] ?? 0);
        }
    }
} catch (Exception $e) {
    $material_results = null;
    $maxDiscount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Collection — Noble Home</title>
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <style>
        /* Category grid: hide on mobile (use sidebar button instead) */
        @media (max-width: 639px) {
            .cat-grid-wrap {
                display: none !important;
            }
        }

        /* Sub-subcategory hover color fix */
        .subsub-a:hover {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #b91c1c;
        }
    </style>
</head>

<body class="bg-stone-50 text-stone-900 antialiased">

    <!-- ── HERO ────────────────────────────────── -->
    <?php if ($onsale_result && $onsale_result->num_rows > 0): ?>
        <div class="relative w-full h-48 md:h-[320px] overflow-hidden bg-stone-900" id="heroWrap">
            <!-- slides -->
            <div class="relative w-full h-full" id="heroSlides">
                <?php $first = true;
                while ($banner = $onsale_result->fetch_assoc()): ?>
                    <div
                        class="hero-slide absolute inset-0 transition-opacity duration-1000 <?= $first ? 'opacity-100' : 'opacity-0' ?>">
                        <img src="<?= BASE_URL ?>/uploads/<?= basename($banner['filename']) ?>" alt="Sale Banner"
                            class="w-full h-full object-cover opacity-50" onerror="this.src='<?= BASE_URL ?>/uploads/placeholder.jpg'">
                    </div>
                    <?php $first = false; endwhile; ?>
            </div>

            <!-- overlay text -->
            <div
                class="absolute inset-0 flex flex-col items-center justify-center z-10 text-center pointer-events-none px-4">
                <span
                    class="inline-block bg-red-700 text-white text-[10px] font-bold tracking-[0.15em] uppercase px-3 py-1 rounded-sm mb-3">
                    Limited Offers
                </span>
                <h1 class="font-serif text-5xl md:text-7xl text-white leading-none tracking-tight">
                    Sale <em class="italic text-amber-400">Season</em>
                </h1>
                <p class="mt-2 text-xs text-white/70 tracking-[0.15em] uppercase">
                    Up to <?= number_format($maxDiscount, 0) ?>% off selected items
                </p>
            </div>

            <!-- nav arrows -->
            <button onclick="saleHeroSlide(-1)"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white/15 backdrop-blur border border-white/25 flex items-center justify-center hover:bg-white/30 transition-colors">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button onclick="saleHeroSlide(1)"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white/15 backdrop-blur border border-white/25 flex items-center justify-center hover:bg-white/30 transition-colors">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    <?php endif; ?>

    <!-- ── MOBILE SIDEBAR OVERLAY ──────────────── -->
    <div id="mOverlay" onclick="salCloseMSidebar()" class="hidden fixed inset-0 bg-black/45 z-[100]"></div>

    <!-- ── MOBILE SIDEBAR ──────────────────────── -->
    <div id="mSidebar"
        class="fixed top-0 left-0 h-full w-72 bg-stone-50 z-[101] overflow-y-auto -translate-x-full transition-transform duration-300 ease-in-out px-5 py-6">
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-stone-200">
            <h3 class="font-serif text-lg font-normal">Categories</h3>
            <button onclick="salCloseMSidebar()" class="text-stone-500 hover:text-stone-900 text-lg">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php foreach ($categories_array as $cat): ?>
            <div onclick="salLoadSubs(<?= $cat['id'] ?>,'<?= addslashes(htmlspecialchars($cat['name'])) ?>');salCloseMSidebar();"
                class="px-3 py-2.5 border border-stone-200 rounded-md cursor-pointer text-sm font-medium mb-1.5 hover:border-stone-900 hover:bg-stone-100 transition-colors">
                <?= htmlspecialchars($cat['name']) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── MAIN PAGE ────────────────────────────── -->
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 pb-20 md:pb-6">
        <div class="flex items-center gap-4 mt-12 mb-6">
            <div class="flex-1 h-px bg-stone-200"></div>
            <h2 class="font-serif text-2xl font-normal whitespace-nowrap">
                Shop by <em class="italic text-red-700">Category</em>
            </h2>
            <div class="flex-1 h-px bg-stone-200"></div>
        </div>

        <!-- Desktop Category Grid -->
        <div class="cat-grid-wrap flex flex-wrap justify-center gap-3" id="catGrid">
            <?php foreach ($categories_array as $cat): ?>
                <div id="cat-<?= $cat['id'] ?>"
                    onclick="salLoadSubs(<?= $cat['id'] ?>,'<?= addslashes(htmlspecialchars($cat['name'])) ?>')"
                    class="cat-card w-[130px] bg-white shadow-lg rounded-lg overflow-hidden cursor-pointer text-center hover:-translate-y-0.5 hover:shadow-lg hover:border-stone-900 transition-all duration-200">
                    <?php if (!empty($cat['image_path'])): ?>
                        <div class="w-full h-28 bg-stone-100 flex items-center justify-center p-3">
                            <img src="<?= BASE_URL ?>/uploads/categories/<?= htmlspecialchars($cat['image_path']) ?>"
                                alt="<?= htmlspecialchars($cat['name']) ?>" class="w-20 h-20 object-contain"
                                onerror="this.parentElement.style.display='none'">
                        </div>
                    <?php else: ?>
                        <div class="w-full h-28 bg-stone-100 flex items-center justify-center">
                            <div class="w-8 h-8 rounded-full bg-stone-200"></div>
                        </div>
                    <?php endif; ?>
                    <div class="px-1.5 py-2 border-t border-stone-100">
                        <div class="w-1.5 h-1.5 rounded-full bg-red-600 mx-auto mb-1"></div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-stone-900 leading-tight">
                            <?= htmlspecialchars($cat['name']) ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Subcategory Drawer -->
        <div id="subDrawer" class="hidden mt-4 mb-2 rounded-xl p-5 animate-[fadeDown_0.2s_ease]">
            <div class="flex items-center justify-between mb-4">
                <span id="drawerTitle" class="font-serif text-lg font-normal uppercase">—</span>
                <button onclick="salCloseDrawer()"
                    class="text-stone-500 hover:bg-gray-200 text-[11px] font-semibold uppercase tracking-wider p-2">
                    Close ✕
                </button>
            </div>
            <!-- Sub cards -->
            <div id="subGrid" class="flex flex-row flex-wrap gap-2">
                <div class="flex gap-1 p-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:150ms]"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:300ms]"></span>
                </div>
            </div>
            <!-- Sub-sub list -->
            <div id="subSubWrap"></div>
        </div>

        <!-- Divider -->
        <div class="w-full h-px bg-gradient-to-r from-transparent via-stone-200 to-transparent my-10"></div>

        <!-- ── TOP SALES ──────────────────────────── -->
        <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
            <div class="flex items-center gap-4 mb-6 mt-4">
                <h2 class="font-serif text-2xl font-normal whitespace-nowrap">
                    Top <em class="italic text-red-700">Sales</em>
                </h2>
                <div class="flex-1 h-px bg-stone-200"></div>
            </div>

            <!-- Sale cards swiper wrapper -->
            <div class="relative">
                <!-- Prev/Next arrows -->
                <button id="salePrev"
                    class="absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white border border-stone-200 flex items-center justify-center shadow hover:bg-stone-900 hover:border-stone-900 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button id="saleNext"
                    class="absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white border border-stone-200 flex items-center justify-center shadow hover:bg-stone-900 hover:border-stone-900 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <div class="swiper sale-swiper overflow-hidden px-0.5 py-1">
                    <div class="swiper-wrapper">
                        <?php mysqli_data_seek($material_results, 0);
                        while ($row = mysqli_fetch_assoc($material_results)):
                            $fp = (float) ($row['price'] ?? 0) + (float) ($row['color_price'] ?? 0);
                            $disc = (float) ($row['discount'] ?? 0);
                            // Use main_image as fallback if type_image is empty
                            $imgSrc = !empty($row['type_image']) ? $row['type_image'] : $row['main_image'];
                            ?>
                            <div class="swiper-slide p-3">
                                <div
                                    class="group bg-white rounded-xl overflow-hidden hover:-translate-y-1 hover:shadow-xl transition-all duration-200 cursor-pointer shadow-lg">
                                    <!-- Image -->
                                    <div class="relative h-36 bg-stone-100 flex items-center justify-center p-3 overflow-hidden">
                                        <?php if (!empty($imgSrc)): ?>
                                            <img src="<?= BASE_URL ?>/<?= ltrim(htmlspecialchars($imgSrc), '/') ?>"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                                loading="lazy"
                                                onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span class="text-stone-400 text-xs">No image</span>
                                        <?php endif; ?>
                                        <?php if ($disc > 0): ?>
                                            <span
                                                class="absolute top-2 right-2 bg-red-700 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-sm tracking-wide">
                                                −<?= number_format($disc, 0) ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Body -->
                                    <div class="p-3">
                                        <p class="text-[11px] font-medium text-stone-900 leading-snug line-clamp-2 mb-1.5">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                            <?= !empty($row['size']) ? ' · ' . htmlspecialchars($row['size']) : '' ?>
                                        </p>
                                        <p class="font-serif text-base text-stone-900">₱<?= number_format($fp, 2) ?></p>
                                        <?php if ($disc > 0): ?>
                                            <p class="text-[10px] text-red-700 font-semibold uppercase tracking-wide mt-0.5">
                                                Save <?= number_format($disc, 0) ?>%
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-[9px] text-stone-400 mt-1.5">
                                            <?= number_format((int) ($row['view_count'] ?? 0)) ?> views ·
                                            <?= number_format((int) ($row['total_sold'] ?? 0)) ?> sold
                                        </p>
                                        <form action="<?= BASE_URL ?>/productview" method="GET">
                                            <input type="hidden" name="id" value="<?= (int) $row['product_id'] ?>">
                                            <button type="submit"
                                                class="mt-2.5 w-full py-1.5 bg-red-500 text-white text-[10px] font-bold uppercase tracking-widest rounded hover:bg-red-700 transition-colors">
                                                View Product
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /main -->

    <!-- ── MOBILE BROWSE BUTTON ────────────────── -->
    <button onclick="salOpenMSidebar()"
        class="md:hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 bg-stone-900 text-white text-[11px] font-bold uppercase tracking-widest px-6 py-3 rounded-full shadow-xl hover:bg-red-700 transition-colors">
        <i class="fas fa-th-large"></i> Browse Categories
    </button>

    <?php
    $footer_path = ROOT_PATH . '/user/navbar/footer.php';
    if (file_exists($footer_path))
        include $footer_path;
    ?>

    <script>
        // ── HERO SLIDESHOW ────────────────────────────
        // Using unique function name to avoid conflict with navbar's heroSlide
        (function () {
            const slides = document.querySelectorAll('.hero-slide');
            if (!slides.length) return;
            let cur = 0;
            function go(dir) {
                slides[cur].classList.replace('opacity-100', 'opacity-0');
                cur = (cur + dir + slides.length) % slides.length;
                slides[cur].classList.replace('opacity-0', 'opacity-100');
            }
            window.saleHeroSlide = go;
            if (slides.length > 1) setInterval(() => go(1), 5000);
        })();

        // ── SALE SWIPER ───────────────────────────────
        const saleSwiper = new Swiper('.sale-swiper', {
            loop: false,
            spaceBetween: 12,
            navigation: { nextEl: '#saleNext', prevEl: '#salePrev' },
            breakpoints: {
                320: { slidesPerView: 2 },
                640: { slidesPerView: 3 },
                768: { slidesPerView: 4 },
                1024: { slidesPerView: 5 },
                1280: { slidesPerView: 6 },
            }
        });

        // ── CATEGORY / SUBCATEGORY LOGIC ──────────────
        // Using unique variable name to avoid conflict with navbar's BASE const
        var SALE_BASE_URL = '<?= BASE_URL ?>';

        function salLoadSubs(catId, catName) {
            var drawer = document.getElementById('subDrawer');
            var prevActive = document.querySelector('.cat-card.active');
            document.querySelectorAll('.cat-card').forEach(function(c) {
                c.classList.remove('active', 'border-red-600', 'shadow-[0_0_0_2px_#fde8e6]');
            });
            document.getElementById('subSubWrap').innerHTML = '';

            if (prevActive && prevActive.id === ('cat-' + catId) && !drawer.classList.contains('hidden')) {
                salCloseDrawer(); return;
            }

            var activeCard = document.getElementById('cat-' + catId);
            if (activeCard) {
                activeCard.classList.add('active', '!border-red-600', 'shadow-[0_0_0_2px_#fde8e6]');
            }
            document.getElementById('drawerTitle').textContent = catName;
            document.getElementById('subGrid').innerHTML = '<div class="flex gap-1 p-5"><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce"></span><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:150ms]"></span><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:300ms]"></span></div>';
            drawer.classList.remove('hidden');
            drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            fetch(SALE_BASE_URL + '/productsubview?category_id=' + catId)
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.text();
                })
                .then(function(text) {
                    var data;
                    try { data = JSON.parse(text); }
                    catch (e) {
                        document.getElementById('subGrid').innerHTML =
                            '<p class="text-red-600 text-xs p-3">JSON error: ' + text.substring(0, 200) + '</p>';
                        return;
                    }
                    if (data.success && data.subcategories && data.subcategories.length) {
                        var html = '';
                        data.subcategories.forEach(function(sub) {
                            var img = sub.image_path
                                ? SALE_BASE_URL + '/uploads/' + sub.subcategory_slug + '/' + sub.image_path
                                : null;
                            html += '<div onclick="salLoadSubSubs(' + sub.id + ',\'' + sub.subcategory_slug + '\')" class="w-[130px] bg-white rounded-lg p-3 text-center cursor-pointer hover:shadow-lg hover:bg-gray-50 transition-colors">';
                            if (img) {
                                html += '<img src="' + img + '" alt="' + sub.subcategory_name + '" class="w-20 h-20 object-contain mx-auto mb-2" onerror="this.style.display=\'none\'">';
                            }
                            html += '<p class="text-[11px] font-semibold uppercase tracking-wide text-stone-900 leading-tight">' + sub.subcategory_name + '</p></div>';
                        });
                        document.getElementById('subGrid').innerHTML = html;
                    } else {
                        document.getElementById('subGrid').innerHTML =
                            '<p class="text-stone-400 text-xs p-3">No subcategories found.</p>';
                    }
                })
                .catch(function(err) {
                    document.getElementById('subGrid').innerHTML =
                        '<p class="text-red-600 text-xs p-3">Error: ' + err.message + '</p>';
                });
        }

        function salLoadSubSubs(subId, slug) {
            var wrap = document.getElementById('subSubWrap');
            wrap.innerHTML = '<div class="flex gap-1 mt-3 pt-3 border-t border-stone-200"><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce"></span><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:150ms]"></span><span class="w-1.5 h-1.5 rounded-full bg-stone-400 animate-bounce [animation-delay:300ms]"></span></div>';

            fetch(SALE_BASE_URL + '/productsubview?subcategory_id=' + subId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.subsubcategories && data.subsubcategories.length) {
                        var html = '<div class="grid grid-cols-[repeat(auto-fill,minmax(120px,1fr))] gap-1.5 mt-3 pt-3 border-t border-stone-200">';
                        data.subsubcategories.forEach(function(ss) {
                            html += '<a href="' + SALE_BASE_URL + '/productsubviews?sub_subcategory_id=' + ss.id + '&sale=1" class="block px-2.5 py-1.5 bg-white rounded text-[10px] font-semibold uppercase tracking-wider text-stone-900 hover:bg-red-50 hover:border-red-600 hover:text-red-700 transition-colors">' + ss.sub_subcategory_name + '</a>';
                        });
                        html += '</div>';
                        wrap.innerHTML = html;
                    } else {
                        wrap.innerHTML = '<p class="text-stone-400 text-[11px] mt-3">No collections found.</p>';
                    }
                })
                .catch(function() {
                    wrap.innerHTML = '<p class="text-red-600 text-[11px] mt-3">Error loading.</p>';
                });
        }

        function salCloseDrawer() {
            document.getElementById('subDrawer').classList.add('hidden');
            document.querySelectorAll('.cat-card').forEach(function(c) {
                c.classList.remove('active', '!border-red-600', 'shadow-[0_0_0_2px_#fde8e6]');
            });
            document.getElementById('subSubWrap').innerHTML = '';
        }

        function salOpenMSidebar() {
            document.getElementById('mSidebar').classList.remove('-translate-x-full');
            document.getElementById('mOverlay').classList.remove('hidden');
        }

        function salCloseMSidebar() {
            document.getElementById('mSidebar').classList.add('-translate-x-full');
            document.getElementById('mOverlay').classList.add('hidden');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { salCloseMSidebar(); salCloseDrawer(); }
        });
    </script>
</body>

</html>