<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token 
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture']     = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Session check 
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get category_id from URL parameter
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Get category information
$category_name = "All Categories";
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $category = $result->fetch_assoc();
        $category_name = $category['name'];
    }
    $stmt->close();
}

// Get subcategories based on category
if ($category_id > 0) {
    // Filter by specific category - UPDATED to include image_path
    $stmt = $conn->prepare("SELECT *, image_path FROM product_subcategories WHERE category_id = ? ORDER BY subcategory_name");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Show all subcategories - UPDATED to include image_path
    $sql = "SELECT *, image_path FROM product_subcategories ORDER BY subcategory_name";
    $result = $conn->query($sql);
}

$subcategories = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }
}

// Get all categories for the breadcrumb/filter
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
$all_categories = [];
if ($categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $all_categories[] = $row;
    }
}

// Select categories to display in swiper
$categories = [];
if ($category_id > 0) {
    // If a specific category is selected, show 3 random categories
    $other_categories = array_filter($all_categories, function($cat) use ($category_id) {
        return $cat['id'] != $category_id;
    });
    
    // Shuffle and take 3 random categories
    shuffle($other_categories);
    $categories = array_slice($other_categories, 0, 3);
    
    // Add the current category at the beginning
    $current_category = array_filter($all_categories, function($cat) use ($category_id) {
        return $cat['id'] == $category_id;
    });
    if (!empty($current_category)) {
        array_unshift($categories, array_values($current_category)[0]);
    }
} else {
    // Show first 4 categories initially
    $categories = array_slice($all_categories, 0, 4);
}

$activeIndex = 0;
foreach ($categories as $i => $cat) {
    if ($category_id == $cat['id']) {
        $activeIndex = $i;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Subcategories - <?php echo htmlspecialchars($category_name); ?></title>
     <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#ce8422ff',
                        secondary: '#64748B'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen font-mont">
    <?php include '../navbar/top.php'; ?>

    <!-- Main Container -->
    <div class="container mx-auto px-4 py-8 max-w-8xl">
        <!-- Breadcrumb -->
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="categories.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Categories
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2"><?php echo htmlspecialchars($category_name); ?></span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Header with Category Filter -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <?php echo $category_id > 0 ? htmlspecialchars($category_name) . ' - Subcategories' : 'All Product Subcategories'; ?>
            </h1>
            <p class="text-gray-600 mb-6">Browse through our product subcategories</p>

            <!-- Enhanced Category Filter Swiper -->
            <div class="relative mb-6">
                <div class="category-filter-container bg-white rounded-xl shadow-sm border border-gray-100 p-4 overflow-hidden">
                    <div class="swiper categorySwiper">
                        <div class="swiper-wrapper py-2">
                            <!-- Category slides -->
                            <?php foreach ($categories as $cat): ?>
                                <div class="swiper-slide !w-auto flex-shrink-0">
                                    <a href="?category_id=<?php echo $cat['id']; ?>"
                                        class="category-pill <?php echo $category_id == $cat['id'] ? 'active' : ''; ?>">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- "View All" button if showing limited categories -->
                            <?php if ($category_id > 0 || count($all_categories) > 4): ?>
                                <div class="swiper-slide !w-auto flex-shrink-0">
                                    <button onclick="showAllCategories()" 
                                        class="category-pill view-all-btn bg-orange-400 text-white border-purple-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        View All
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Enhanced Navigation buttons - Initially Hidden -->
                    <div class="category-nav-prev absolute left-2 top-1/2 -translate-y-1/2 z-10 bg-white shadow-lg rounded-full p-2 cursor-pointer opacity-0 invisible transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </div>
                    <div class="category-nav-next absolute right-2 top-1/2 -translate-y-1/2 z-10 bg-white shadow-lg rounded-full p-2 cursor-pointer opacity-0 invisible transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>
            </div>

          
        </div>

        <!-- Enhanced Styles -->
        <style>
            /* Enhanced Category Pills */
            .category-pill {
                display: inline-flex;
                align-items: center;
                padding: 12px 24px;
                margin: 0 6px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                white-space: nowrap;
                min-width: fit-content;
            }

            .category-pill::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                transition: left 0.5s;
            }

            .category-pill:hover::before {
                left: 100%;
            }

            .category-pill:hover {
                transform: translateY(-2px);
                border-color: #d47b07ff;
                color: #d47b07ff;
            }

            .category-pill.active {
                background: linear-gradient(135deg, #d47b07ff 0%, #d47b07ff 100%);
                color: white;
                border-color: #d47b07ff;
            }

            .category-pill.active:hover {
                background: linear-gradient(135deg, #d47b07ff 0%, #d47b07ff 100%);
                transform: translateY(-2px);
            }

            /* View All button special styling */
            .view-all-btn {
                background: linear-gradient(135deg, #d47b07ff 0%, #d47b07ff 100%) !important;
                border-color: #d47b07ff !important;
            }

            .view-all-btn:hover {
                background: linear-gradient(135deg, #d47b07ff 0%, #d47b07ff 100%) !important;
            }

            /* Enhanced Container */
            .category-filter-container {
                position: relative;
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                backdrop-filter: blur(10px);
            }

            /* Enhanced Swiper */
            .categorySwiper {
                overflow: visible;
                padding: 0 20px;
            }

            .categorySwiper .swiper-wrapper {
                align-items: center;
            }

            /* Enhanced Navigation Buttons - Initially Hidden */
            .category-nav-prev,
            .category-nav-next {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid #e2e8f0;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 10;
                transition: all 0.3s ease;
                color: #64748b;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .category-nav-prev {
                left: 8px;
            }

            .category-nav-next {
                right: 8px;
            }

            .category-nav-prev:hover,
            .category-nav-next:hover {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                transform: translateY(-50%) scale(1.1);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            }

            .category-nav-prev.swiper-button-disabled,
            .category-nav-next.swiper-button-disabled {
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none;
            }

            /* Show navigation buttons only when interacting */
            .category-filter-container.show-nav .category-nav-prev,
            .category-filter-container.show-nav .category-nav-next {
                opacity: 1;
                visibility: visible;
            }

            /* View Toggle Buttons */
            .view-toggle {
                padding: 8px;
                border-radius: 6px;
                transition: all 0.2s ease;
                color: #64748b;
                background: transparent;
                border: none;
                cursor: pointer;
            }

            .view-toggle:hover {
                background: #f1f5f9;
                color: #3b82f6;
            }

            .view-toggle.active {
                background: #3b82f6;
                color: white;
            }

            /* From Uiverse.io by WhiteNervosa - Enhanced subcategory names */
            .subcategory-link {
                font-size: 18px;
                font-family: inherit;
                font-weight: 700;
                cursor: pointer;
                position: relative;
                border: none;
                background: none;
                text-transform: capitalize;
                transition-timing-function: cubic-bezier(0.25, 0.8, 0.25, 1);
                transition-duration: 400ms;
                transition-property: color, transform;
                text-decoration: none;
                display: inline-block;
            }

            .subcategory-link:focus,
            .subcategory-link:hover {
                color: #e08a09ff;
                transform: translateY(-2px);
            }

            .subcategory-link:focus:after,
            .subcategory-link:hover:after {
                width: 100%;
                left: 0%;
            }

            .subcategory-link:after {
                content: "";
                pointer-events: none;
                bottom: -4px;
                left: 50%;
                position: absolute;
                width: 0%;
                height: 3px;
                background: linear-gradient(90deg, #e08a09ff, #e08a09ff);
                border-radius: 2px;
                transition-timing-function: cubic-bezier(0.25, 0.8, 0.25, 1);
                transition-duration: 400ms;
                transition-property: width, left;
            }

            /* Enhanced subcategory cards */
            .subcategory-card {
               
                padding: 24px 16px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }

            .subcategory-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                transform: scaleX(0);
                transition: transform 0.3s ease;
            }

            .subcategory-card:hover {
                transform: translateY(-4px);
                
            }

            .subcategory-card:hover::before {
                transform: scaleX(1);
            }

            /* Modal Styles */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .modal-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            .modal-content {
                background: white;
                border-radius: 16px;
                padding: 24px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                transform: scale(0.9) translateY(20px);
                transition: transform 0.3s ease;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }

            .modal-overlay.active .modal-content {
                transform: scale(1) translateY(0);
            }

            /* Mobile Responsive */
            @media (max-width: 768px) {
                .categorySwiper {
                    padding: 0 10px;
                }

                .category-nav-prev,
                .category-nav-next {
                    width: 32px;
                    height: 32px;
                }

                .category-nav-prev {
                    left: 4px;
                }

                .category-nav-next {
                    right: 4px;
                }

                .category-pill {
                    padding: 10px 20px;
                    font-size: 13px;
                    margin: 0 3px;
                }

                /* Show navigation buttons on mobile after interaction */
                .category-filter-container.show-nav .category-nav-prev,
                .category-filter-container.show-nav .category-nav-next {
                    opacity: 1;
                    visibility: visible;
                }
            }
        </style>

        <!-- Modal for All Categories -->
        <div id="categoriesModal" class="modal-overlay">
            <div class="modal-content">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">All Categories</h2>
                    <button onclick="hideAllCategories()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" id="allCategoriesGrid">
                    <?php foreach ($all_categories as $cat): ?>
                        <a href="?category_id=<?php echo $cat['id']; ?>"
                            class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-all duration-200 group <?php echo $category_id == $cat['id'] ? 'bg-blue-100 border-blue-300' : ''; ?>">
                            <span class="font-medium text-gray-700 group-hover:text-blue-600"><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <!-- Replace your existing subcategories grid section with this updated version: -->
<div id="subcategoriesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($subcategories as $index => $subcategory): ?>
        <div class="subcategory-card group">
            <!-- Image Section -->
            <div class="mb-4 relative overflow-hidden rounded-lg bg-gray-100">
                <?php if (!empty($subcategory['image_path'])): ?>
                    <!-- Display subcategory image -->
                    <div class="aspect-square">
                        <img 
                            src="../../uploads/<?php echo htmlspecialchars($subcategory['subcategory_slug']); ?>/<?php echo htmlspecialchars($subcategory['image_path']); ?>" 
                            alt="<?php echo htmlspecialchars($subcategory['subcategory_name']); ?>"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300\'><svg class=\'w-12 h-12 text-gray-400\' fill=\'currentColor\' viewBox=\'0 0 20 20\'><path fill-rule=\'evenodd\' d=\'M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z\' clip-rule=\'evenodd\' /></svg></div>'"
                        >
                    </div>
                <?php else: ?>
                    <!-- Placeholder for subcategories without images -->
                    <div class="aspect-square flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300">
                        <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                <?php endif; ?>
                
                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center opacity-0 group-hover:opacity-100">
                    <div class="bg-white bg-opacity-90 px-3 py-1 rounded-full transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                        <span class="text-sm font-medium text-gray-800">View Products</span>
                    </div>
                </div>
            </div>

            <!-- Card Body -->
            <div class="text-center">                 
                <!-- Clickable subcategory name with hover effect -->
                <a href="allproductsub_variant.php?subcategory_id=<?php echo $subcategory['id']; ?>"
                    class="subcategory-link text-lg mb-3 block">
                    <?php echo htmlspecialchars($subcategory['subcategory_name']); ?>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

        <!-- Empty State -->
        <?php if (empty($subcategories)): ?>
            <div class="text-center py-16">
                <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3">No Subcategories Found</h3>
                <p class="text-gray-500 mb-6 max-w-md mx-auto">
                    <?php echo $category_id > 0 ? 'There are no subcategories in ' . htmlspecialchars($category_name) . ' at the moment.' : 'There are no subcategories available at the moment.'; ?>
                </p>
                <?php if ($category_id > 0): ?>
                    <a href="?category_id=0" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full text-white bg-orange-400 hover:from-blue-700 hover:to-blue-800 transition-all duration-200 transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                        View All Categories
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Back to Top Button -->
    <button id="backToTop" class="fixed bottom-6 right-6 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 invisible transform hover:scale-110">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
        // Initialize Enhanced Swiper
        const categorySwiper = new Swiper('.categorySwiper', {
            slidesPerView: 'auto',
            spaceBetween: 8,
            freeMode: false,
            centeredSlides: false,
            navigation: {
                nextEl: '.category-nav-next',
                prevEl: '.category-nav-prev',
            },
            breakpoints: {
                320: {
                    slidesPerView: 'auto',
                    spaceBetween: 4,
                },
                768: {
                    slidesPerView: 'auto',
                    spaceBetween: 6,
                },
                1024: {
                    slidesPerView: 'auto',
                    spaceBetween: 8,
                }
            },
            on: {
                init: function() {
                    this.update();
                    checkNavigationVisibility(this);
                },
                slideChange: function() {
                    checkNavigationVisibility(this);
                },
                touchStart: function() {
                    showNavigationButtons();
                },
                touchEnd: function() {
                    // Keep navigation visible for a moment after touch
                    setTimeout(() => {
                        if (!isUserInteracting) {
                            hideNavigationButtons();
                        }
                    }, 2000);
                }
            }
        });

        // Variables to track user interaction
        let isUserInteracting = false;
        let interactionTimeout;

        // Function to show navigation buttons
        function showNavigationButtons() {
            const container = document.querySelector('.category-filter-container');
            container.classList.add('show-nav');
            isUserInteracting = true;
            
            // Clear existing timeout
            if (interactionTimeout) {
                clearTimeout(interactionTimeout);
            }
        }

        // Function to hide navigation buttons
        function hideNavigationButtons() {
            const container = document.querySelector('.category-filter-container');
            container.classList.remove('show-nav');
            isUserInteracting = false;
        }

        // Function to check navigation button visibility based on swiper state
        function checkNavigationVisibility(swiper) {
            const prevBtn = document.querySelector('.category-nav-prev');
            const nextBtn = document.querySelector('.category-nav-next');
            const container = document.querySelector('.category-filter-container');
            
            // Only show buttons if user is interacting and buttons are needed
            if (container.classList.contains('show-nav')) {
                if (swiper.isBeginning) {
                    prevBtn.style.opacity = '0';
                    prevBtn.style.visibility = 'hidden';
                } else {
                    prevBtn.style.opacity = '1';
                    prevBtn.style.visibility = 'visible';
                }
                
                if (swiper.isEnd) {
                    nextBtn.style.opacity = '0';
                    nextBtn.style.visibility = 'hidden';
                } else {
                    nextBtn.style.opacity = '1';
                    nextBtn.style.visibility = 'visible';
                }
            }
        }

        // Add event listeners for mouse interactions
        const categoryContainer = document.querySelector('.category-filter-container');
        
        // Show navigation on hover (desktop)
        categoryContainer.addEventListener('mouseenter', () => {
            if (window.innerWidth >= 768) { // Only on desktop
                showNavigationButtons();
                checkNavigationVisibility(categorySwiper);
            }
        });

        // Hide navigation when mouse leaves (desktop)
        categoryContainer.addEventListener('mouseleave', () => {
            if (window.innerWidth >= 768) { // Only on desktop
                interactionTimeout = setTimeout(() => {
                    hideNavigationButtons();
                }, 1000); // 1 second delay before hiding
            }
        });

        // Show navigation on touch/click (mobile)
        categoryContainer.addEventListener('touchstart', () => {
            showNavigationButtons();
            checkNavigationVisibility(categorySwiper);
        });

        // Show navigation when clicking on category pills
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                showNavigationButtons();
                checkNavigationVisibility(categorySwiper);
            });
        });

        // Show navigation when swiper is being dragged
        categorySwiper.on('touchStart', () => {
            showNavigationButtons();
        });

        categorySwiper.on('touchEnd', () => {
            // Hide navigation after a delay if no more interaction
            interactionTimeout = setTimeout(() => {
                if (!isUserInteracting) {
                    hideNavigationButtons();
                }
            }, 3000); // 3 seconds delay
        });

        // Reset interaction state on window blur/focus
        window.addEventListener('blur', () => {
            hideNavigationButtons();
        });

        // Show all categories modal
        function showAllCategories() {
            document.getElementById('categoriesModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Hide all categories modal
        function hideAllCategories() {
            document.getElementById('categoriesModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking overlay
        document.getElementById('categoriesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAllCategories();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideAllCategories();
            }
        });

        // View toggle functionality
        document.querySelectorAll('.view-toggle').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.view-toggle').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const grid = document.getElementById('subcategoriesGrid');
                if (this.dataset.view === 'list') {
                    grid.className = 'space-y-4';
                } else {
                    grid.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6';
                }
            });
        });

        // Enhanced back to top button functionality
        const backToTopButton = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.add('opacity-0', 'invisible');
                backToTopButton.classList.remove('opacity-100', 'visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Add subtle animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = Math.random() * 0.3 + 's';
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);

        // Observe subcategory cards
        document.querySelectorAll('.subcategory-card').forEach(card => {
            observer.observe(card);
        });

        // Add CSS animation for fade in
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in {
                animation: fadeInUp 0.6s ease-out forwards;
            }
            
            /* Smooth category pill transitions */
            .category-pill {
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            
            /* Enhanced hover effects for category pills */
            .category-pill:hover {
                transform: translateY(-3px) scale(1.05);
            }
            
            /* Loading animation for new categories */
            @keyframes slideInFromRight {
                from {
                    opacity: 0;
                    transform: translateX(30px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            .category-pill.new {
                animation: slideInFromRight 0.5s ease-out forwards;
            }
        `;
        document.head.appendChild(style);

        // Add click animation to category pills
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', function(e) {
                // Create ripple effect
                const rect = this.getBoundingClientRect();
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';
                ripple.style.width = '20px';
                ripple.style.height = '20px';
                
                this.style.position = 'relative';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation CSS
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);

        // Initialize swiper after page load
        window.addEventListener('load', function() {
            categorySwiper.update();
            checkNavigationVisibility(categorySwiper);
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            // Reset navigation visibility on resize
            if (window.innerWidth < 768) {
                // Mobile: hide navigation unless actively interacting
                if (!isUserInteracting) {
                    hideNavigationButtons();
                }
            }
            categorySwiper.update();
            checkNavigationVisibility(categorySwiper);
        });
    </script>
</body>

</html>