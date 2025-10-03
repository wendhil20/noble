<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
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

$slug = $_GET['slug'] ?? '';

$stmt = $conn->prepare("SELECT * FROM bestseller WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$bestseller = $result->fetch_assoc();
$sections = $conn->query("SELECT * FROM bestsellertwo WHERE bestseller_id = {$bestseller['id']} ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($bestseller['title']) ?> - Noble Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .img-hover {
            transition: transform 0.3s ease;
        }

        .img-hover:hover {
            transform: scale(1.05);
        }
    </style>
</head>

<body class="">

    <?php include '../navbar/top.php'; ?>

   

<!-- Hero -->
<section class="relative min-h-[400px] lg:h-[50vh] bg-black overflow-hidden">
    <!-- Background Image -->
    <img src="<?= htmlspecialchars($bestseller['image']) ?>"
        alt="<?= htmlspecialchars($bestseller['title']) ?>"
        class="absolute inset-0 w-full h-full object-contain opacity-50">
    
    <!-- Gradient Overlay -->
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/30"></div>

    <?php
    // Fetch product data ONCE before using it
    $product = null;
    if (!empty($bestseller['product_id'])) {
        $product_id = (int)$bestseller['product_id'];
        $product_query = $conn->query("
            SELECT 
                pv.*,
                pt.type_name,
                p.product_name,
                p.main_image,
                pc.price AS color_price,
                p.id as product_id
            FROM product_variants pv
            INNER JOIN product_types pt ON pv.type_id = pt.id
            INNER JOIN products p ON pt.product_id = p.id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE p.id = $product_id
            LIMIT 1
        ");

        if ($product_query && $product_query->num_rows > 0) {
            $product = $product_query->fetch_assoc();
        }
    }
    ?>

    <!-- Content Container -->
    <div class="relative h-full flex flex-col">
        <div class="flex-1 flex items-center lg:items-end">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:pb-12">
                
                <!-- Title and Description -->
                <div class="max-w-4xl mx-auto text-center lg:text-left">
                    <!-- Bestseller Badge -->
                    <div class="flex justify-center lg:justify-start mb-3 sm:mb-4">
                        <span class="font-roboto inline-flex items-center gap-2 px-4 py-2 bg-red-500/90 backdrop-blur-sm text-white text-xs sm:text-sm font-semibold tracking-wider uppercase rounded-full">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                            Bestseller
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="font-roboto text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl text-white leading-tight mb-3 sm:mb-4 lg:mb-5">
                        <?= htmlspecialchars($bestseller['title']) ?>
                    </h1>

                    <!-- Description -->
                    <p class="font-roboto text-sm sm:text-base lg:text-lg text-gray-200 leading-relaxed max-w-3xl mx-auto lg:mx-0 mb-6 lg:mb-8">
                        <?= htmlspecialchars($bestseller['description']) ?>
                    </p>

                    <!-- View Button - Always show -->
                    <div class="flex justify-center lg:justify-start">
                        <?php if ($product): ?>
                            <form action="product_view" method="GET" class="w-full sm:w-auto max-w-md">
                                <input type="hidden" name="id" value="<?= (int)$product['product_id'] ?>">
                                <button type="submit" 
                                    class="w-full text-white px-8 py-3.5 lg:py-4 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center gap-3 text-sm sm:text-base shadow-lg hover:shadow-xl hover:scale-105 group">
                           <i class="fa-solid fa-bag-shopping"></i>
                                    View Product
                                    <svg class="w-4 h-4 lg:w-5 lg:h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Fallback button if no product -->
                            <button class="w-full sm:w-auto bg-gray-600 text-white px-8 py-3.5 lg:py-4 rounded-xl font-semibold cursor-not-allowed opacity-50 flex items-center justify-center gap-3 text-sm sm:text-base">
                                <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Product Not Available
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


    <!-- Content with Sidebar -->
    <section class="py-10">
        <div class="container mx-auto px-6">
            <?php if ($sections->num_rows > 0): ?>
                <?php while ($section = $sections->fetch_assoc()):
                    $images = json_decode($section['image'], true) ?: [];
                ?>

                    <div class="mb-12 last:mb-0">
                        <div class="max-w-7xl mx-auto">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                                <!-- Left: Text (2 columns) -->
                                <div class="lg:col-span-2">
                                    <?php if ($section['subtitle']): ?>
                                        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
                                            <?= htmlspecialchars($section['subtitle']) ?>
                                        </h2>
                                    <?php endif; ?>

                               <?php if ($section['content']): ?>
    <div class="text-gray-700 leading-relaxed space-y-3">
        <div id="section-content" 
             class="overflow-hidden relative" 
             style="max-height: 120px;">
            <?= nl2br(htmlspecialchars($section['content'])) ?>
        </div>

        <button id="toggle-content" 
                class="text-blue-500 font-semibold hover:underline mt-2">
            See more
        </button>
    </div>

    <script>
        const content = document.getElementById("section-content");
        const toggleBtn = document.getElementById("toggle-content");
        let expanded = false;

        toggleBtn.addEventListener("click", () => {
            if (!expanded) {
                content.style.maxHeight = "none";  // expand
                toggleBtn.textContent = "See less";
            } else {
                content.style.maxHeight = "120px"; // collapse
                toggleBtn.textContent = "See more";
            }
            expanded = !expanded;
        });
    </script>
<?php endif; ?>

                                </div>
                                <!-- Right: Images Sidebar (1 column) -->
                                <?php if (!empty($images)): ?>
                                    <div class="lg:col-span-1">
                                        <div class="space-y-3 lg:sticky lg:top-20">
                                            <?php foreach ($images as $img): ?>
                                                <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                                    <img src="<?= htmlspecialchars($img) ?>"
                                                        alt="Product"
                                                        class="w-full h-40 object-contain img-hover">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="inline-block p-5 bg-gray-100 rounded-full mb-4">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Details Yet</h3>
                    <p class="text-gray-500">Additional information coming soon</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
 <section class="py-8 bg-white overflow-hidden">
        <?php
        // Fetch categories with subcategory count
        $fetchCategoriesQuery = "SELECT c.id, c.name, c.image_path, COUNT(ps.id) as subcategory_count 
                  FROM categories c
                  LEFT JOIN product_subcategories ps ON c.id = ps.category_id
                  GROUP BY c.id, c.name, c.image_path
                  ORDER BY c.id ASC";
        $executeCategoryQuery = mysqli_query($conn, $fetchCategoriesQuery);
        $allCategoriesArray = [];

        if ($executeCategoryQuery) {
            while ($singleCategoryRow = mysqli_fetch_assoc($executeCategoryQuery)) {
                $allCategoriesArray[] = $singleCategoryRow;
            }
        }
        ?>

        <!-- Heading and description -->
        <div class="text-center mb-8 px-4">
            <h2 class="text-3xl sm:text-4xl font-light text-black mb-3">Shop by Categories</h2>
            <p class="text-gray-600 text-base sm:text-lg max-w-2xl mx-auto">
                Discover our wide range of home improvement products organized by category
            </p>
        </div>

        <!-- Categories Container -->
        <div class="relative px-4 sm:px-6 lg:px-8">
            <!-- Navigation Buttons - Desktop Only -->
            <button class="category-prev hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <button class="category-next hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <div class="swiper homepage-category-carousel !overflow-visible">
                <div class="swiper-wrapper pb-2">
                    <?php foreach ($allCategoriesArray as $currentCategory):
                        $currentCategorySlug = strtolower($currentCategory['name']);
                        $formattedCategoryName = ucwords(str_replace('_', ' ', $currentCategory['name']));

                        // Use image from database or placeholder
                        if (!empty($currentCategory['image_path'])) {
                            $finalCategoryImagePath = '../../uploads/categories/' . $currentCategory['image_path'];
                        } else {
                            $finalCategoryImagePath = '../../img/placeholder-category.png';
                        }

                        // Build link - always go to allproductsub_variant.php with category_id
                        $categoryPageLink = 'allproductsub_variant.php?category_id=' . $currentCategory['id'];
                    ?>
                        <div class="swiper-slide">
                            <a href="<?php echo htmlspecialchars($categoryPageLink); ?>" class="group block">
                                <div class="relative h-48 sm:h-52 lg:h-56 bg-gray-100 border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                    <!-- Loading Skeleton -->
                                    <div class="absolute inset-0 bg-gray-200 animate-pulse category-skeleton"></div>

                                    <!-- Image -->
                                    <img src="<?php echo htmlspecialchars($finalCategoryImagePath); ?>"
                                        alt="<?php echo htmlspecialchars($formattedCategoryName); ?>"
                                        class="relative w-full h-full object-contain opacity-0 transition-opacity duration-300"
                                        loading="lazy"
                                        onload="this.classList.remove('opacity-0'); this.parentElement.querySelector('.category-skeleton').style.display='none';"
                                        onerror="this.src='../../img/placeholder-category.png'; this.classList.remove('opacity-0'); this.parentElement.querySelector('.category-skeleton').style.display='none';">

                                    <!-- Gradient Overlay -->
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent pointer-events-none"></div>

                                    <!-- Category Name -->
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <span class="text-xl sm:text-2xl font-bold text-white drop-shadow-2xl text-center px-2 leading-tight"><?php echo htmlspecialchars($formattedCategoryName); ?></span>
                                    </div>

                                    <!-- Subcategory Count Badge -->
                                    <?php if ($currentCategory['subcategory_count'] > 0): ?>
                                        <div class="absolute top-2 right-2 bg-orange-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                            <?php echo $currentCategory['subcategory_count']; ?> types
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Mobile Swipe Indicator -->
            <div class="flex lg:hidden justify-center mt-4 gap-1">
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
            </div>
        </div>
    </section>

    <script>
        // Initialize Swiper after DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            const homepageCategorySwiper = new Swiper('.homepage-category-carousel', {
                slidesPerView: 1.5,
                spaceBetween: 12,
                centeredSlides: false,
                navigation: {
                    nextEl: '.category-next',
                    prevEl: '.category-prev',
                },
                breakpoints: {
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 16,
                    },
                    640: {
                        slidesPerView: 2.5,
                        spaceBetween: 16,
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 20,
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 24,
                    },
                    1280: {
                        slidesPerView: 6,
                        spaceBetween: 24,
                    }
                }
            });
        });
    </script>

    <?php include '../navbar/footer.php'; ?>

</body>

</html>