<?php
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// ✅ Restore session from remember_token (email or mobile-based or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // 🔐 Store essential user session info
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        // 👤 Check if it's a Google account (optional)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }

    $stmt->close();
}

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
    // Not logged in — redirect to login or Google auth
    header('Location: google-callback.php'); // You may replace with `index.php` if default login
    exit;
}



// ✅ Enhanced input validation and sanitization
$selected_categories = isset($_GET['category']) && is_array($_GET['category']) ? $_GET['category'] : [];
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$per_page = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// ✅ Improved filtering with prepared statements
$where_conditions = ['1=1'];
$params = [];
$types = '';

// Category filter
if (!empty($selected_categories)) {
  $placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
  $where_conditions[] = "codename IN ($placeholders)";
  $params = array_merge($params, $selected_categories);
  $types .= str_repeat('s', count($selected_categories));
}

// Search filter
if (!empty($search_keyword)) {
  $where_conditions[] = "(product_name LIKE ? OR description LIKE ?)";
  $search_param = '%' . $search_keyword . '%';
  $params[] = $search_param;
  $params[] = $search_param;
  $types .= 'ss';
}

// ✅ Enhanced sorting options
$sort_options = [
  'name_asc' => 'product_name ASC',
  'name_desc' => 'product_name DESC',
  'newest' => 'id DESC',
  'oldest' => 'id ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'product_name ASC';

// ✅ Build query with pagination
$where_clause = implode(' AND ', $where_conditions);
$count_query = "SELECT COUNT(*) as total FROM products WHERE $where_clause";
$main_query = "SELECT id, product_name, main_image, description, codename FROM products WHERE $where_clause ORDER BY $order_by LIMIT ? OFFSET ?";

// Count total products
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
  $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Get products with pagination
$stmt = $conn->prepare($main_query);
$final_params = $params;
$final_params[] = $per_page;
$final_params[] = $offset;
$final_types = $types . 'ii';
if (!empty($final_params)) {
  $stmt->bind_param($final_types, ...$final_params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_products / $per_page);

//git Enhanced categories
$all_categories = [
  'furniture' => 'Furniture',
  'material' => 'Materials',
  'decoration' => 'Decorations',
  'lighting' => 'Lighting',
  'outdoor' => 'Outdoor'
];

// Get category counts
$category_counts = [];
foreach ($all_categories as $cat_key => $cat_name) {
  $cat_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE codename = ?");
  $cat_stmt->bind_param("s", $cat_key);
  $cat_stmt->execute();
  $category_counts[$cat_key] = $cat_stmt->get_result()->fetch_assoc()['count'];
  $cat_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Products - Noble Home</title>
  <meta name="description" content="Explore our premium collection of furniture, materials, and home décor items.">

  <!-- Enhanced CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

  <style>
    .line-clamp-3 {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .glass-effect {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .product-card {
      transition: all 0.3s ease;
    }

    .product-card:hover {
      transform: translateY(-5px);
    }

    .filter-chip {
      transition: all 0.2s ease;
    }

    .filter-chip:hover {
      transform: scale(1.05);
    }

    .pagination-btn {
      transition: all 0.2s ease;
    }

    .pagination-btn:hover:not(:disabled) {
      transform: translateY(-2px);
    }

    .loading-skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: loading 1.5s infinite;
    }

    @keyframes loading {
      0% {
        background-position: 200% 0;
      }

      100% {
        background-position: -200% 0;
      }
    }

    /* ✅ FIXED: Mobile Filter System */
    .mobile-filter-panel {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 50;
      overflow-y: auto;
    }

    .mobile-filter-panel.active {
      display: block;
    }

    .mobile-filter-content {
      background: white;
      margin: 2rem 1rem;
      border-radius: 1rem;
      max-height: calc(100vh - 4rem);
      overflow-y: auto;
    }

    /* Desktop filter - always visible on large screens */
    .desktop-filter {
      display: block;
    }

    /* Mobile behavior */
    @media (max-width: 1023px) {
      .desktop-filter {
        display: none;
      }
      
      .mobile-filter-toggle {
        display: block;
      }
    }

    /* Large screen behavior */
    @media (min-width: 1024px) {
      .mobile-filter-toggle {
        display: none;
      }
      
      .desktop-filter {
        display: block;
      }
      
      .mobile-filter-panel {
        display: none !important;
      }
    }

    /* Sticky filter sidebar for desktop */
    @media (min-width: 1024px) {
      .sticky-filter {
        position: sticky;
        top: 2rem;
        max-height: calc(100vh - 4rem);
        overflow-y: auto;
      }
    }

    /* Custom scrollbar for filter */
    .sticky-filter::-webkit-scrollbar,
    .mobile-filter-content::-webkit-scrollbar {
      width: 6px;
    }

    .sticky-filter::-webkit-scrollbar-track,
    .mobile-filter-content::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }

    .sticky-filter::-webkit-scrollbar-thumb,
    .mobile-filter-content::-webkit-scrollbar-thumb {
      background: #f97316;
      border-radius: 3px;
    }

    .sticky-filter::-webkit-scrollbar-thumb:hover,
    .mobile-filter-content::-webkit-scrollbar-thumb:hover {
      background: #ea580c;
    }

    /* Animation for mobile filter */
    .mobile-filter-panel {
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .mobile-filter-panel.active {
      opacity: 1;
    }

    .mobile-filter-content {
      transform: translateY(2rem);
      transition: transform 0.3s ease;
    }

    .mobile-filter-panel.active .mobile-filter-content {
      transform: translateY(0);
    }
  </style>
</head>

<body class="bg-gray-50 font-sans text-gray-800">

  <?php include 'navbar/top.php'; ?>

  <!-- Enhanced Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center space-x-2 text-sm">
        <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class="text-gray-600 font-medium">Products</span>
        <?php if (!empty($search_keyword)): ?>
          <i class="fas fa-chevron-right text-gray-400"></i>
          <span class="text-gray-500">Search: "<?= htmlspecialchars($search_keyword) ?>"</span>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <div class=" px-4 py-8">
    <!-- Enhanced Header -->
    <div class="text-center mb-12" data-aos="fade-up">
      <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-4">
        Our <span class="text-orange-500">Premium</span> Collections
      </h1>
      <p class="text-gray-600 text-lg max-w-2xl mx-auto">
        Discover exceptional furniture and materials crafted with precision and designed for modern living
      </p>
      <div class="mt-6 flex items-center justify-center space-x-4 text-sm text-gray-500">
        <span class="flex items-center">
          <i class="fas fa-check-circle text-green-500 mr-1"></i>
          Quality Guaranteed
        </span>
        <span class="flex items-center">
          <i class="fas fa-shipping-fast text-blue-500 mr-1"></i>
          Fast Delivery
        </span>
        <span class="flex items-center">
          <i class="fas fa-award text-yellow-500 mr-1"></i>
          Premium Materials
        </span>
      </div>
    </div>

    <!-- ✅ FIXED: Mobile Filter Toggle Button -->
    <div class="mobile-filter-toggle mb-6">
      <button id="mobileFilterToggle" class="w-full bg-orange-500 text-white px-4 py-3 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium flex items-center justify-center">
        <i class="fas fa-filter mr-2"></i>
        Show Filters
        <i class="fas fa-chevron-down ml-2"></i>
      </button>
    </div>

    <!-- ✅ FIXED: Mobile Filter Panel (Overlay) -->
    <div id="mobileFilterPanel" class="mobile-filter-panel">
      <div class="mobile-filter-content">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 flex items-center">
              <i class="fas fa-filter mr-2 text-orange-500"></i>
              Filter Products
            </h2>
            <button type="button" class="text-gray-500 hover:text-gray-700 p-2" id="closeMobileFilter">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <!-- Mobile Filter Form -->
          <form method="GET" class="space-y-6" id="mobileFilterForm">
            <!-- Categories -->
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Categories</h3>
              <div class="space-y-2">
                <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                  <label class="filter-chip flex items-center justify-between p-3 bg-gray-50 hover:bg-orange-50 rounded-lg cursor-pointer transition-all duration-200 <?= in_array($cat_key, $selected_categories) ? 'bg-orange-100 border-orange-300' : 'border-gray-200' ?> border">
                    <div class="flex items-center space-x-3">
                      <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                        class="form-checkbox text-orange-500 border-gray-300 rounded focus:ring-2 focus:ring-orange-400 focus:ring-offset-0">
                      <span class="text-sm font-medium"><?= htmlspecialchars($cat_name) ?></span>
                    </div>
                    <span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded-full"><?= $category_counts[$cat_key] ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Search -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
              <div class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                  placeholder="Search by name or description..."
                  class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
              </div>
            </div>

            <!-- Sort -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
              <select name="sort" class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
              </select>
            </div>

            <!-- Apply Button -->
            <div class="flex space-x-3">
              <button type="submit" class="flex-1 bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium flex items-center justify-center">
                <i class="fas fa-search mr-2"></i>
                Apply Filters
              </button>
              <button type="button" id="clearFilters" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                Clear All
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      
      <!-- Products Section (LEFT) -->
      <div class="flex-1 lg:order-1">
        <!-- Products Header -->
        <div class="flex items-center justify-between mb-6">
          <div class="text-sm text-gray-600">
            <span class="font-medium"><?= number_format($total_products) ?></span> products found
          </div>
          
          <!-- Quick Sort (Mobile) -->
          <div class="lg:hidden">
            <select name="sort" class="py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm" onchange="quickSort(this.value)">
              <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
              <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
              <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
              <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            </select>
          </div>
        </div>

        <!-- Product Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <?php while ($row = $products->fetch_assoc()): ?>
            <?php
              $product_id = (int)$row['id'];
              $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM product_ratings WHERE product_id = ?");
              $stmt->bind_param("i", $product_id);
              $stmt->execute();
              $avg_rating = round($stmt->get_result()->fetch_assoc()['avg_rating'] ?? 0, 1);
              $stmt->close();

              $variant_stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM product_variants pv
                JOIN product_types pt ON pv.type_id = pt.id
                WHERE pt.product_id = ?
              ");
              $variant_stmt->bind_param("i", $product_id);
              $variant_stmt->execute();
              $variant_count = $variant_stmt->get_result()->fetch_assoc()['total'] ?? 0;
              $variant_stmt->close();
            ?>

            <div class="product-card group bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">

              <!-- Star Rating -->
              <div class="px-6 pt-4">
                <div class="flex items-center gap-1 text-yellow-400 text-sm rate-stars" data-product-id="<?= $product_id ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-button hover:scale-110 transition" data-rating="<?= $i ?>">
                      <?php if ($i <= floor($avg_rating)): ?>
                        <i class="fas fa-star"></i>
                      <?php elseif ($i - $avg_rating < 1): ?>
                        <i class="fas fa-star-half-alt"></i>
                      <?php else: ?>
                        <i class="far fa-star"></i>
                      <?php endif; ?>
                    </button>
                  <?php endfor; ?>
                  <span class="ml-2 text-gray-600 text-xs avg-rating">(<?= $avg_rating ?>/5)</span>
                </div>
              </div>

              <!-- Product Content -->
              <a href="product_view.php?id=<?= $product_id ?>">
                <div class="relative aspect-square bg-gray-50 overflow-hidden mt-3">
                  <?php if (!empty($row['main_image'])): ?>
                    <img src="../<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300" loading="lazy">
                  <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                      <i class="fas fa-image text-4xl"></i>
                    </div>
                  <?php endif; ?>

                  <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                      <span class="bg-white text-gray-900 px-4 py-2 rounded-full text-sm font-medium">
                        <i class="fas fa-eye mr-2"></i>View Details
                      </span>
                    </div>
                  </div>

                  <div class="absolute top-3 left-3">
                    <span class="bg-white bg-opacity-90 text-gray-700 px-3 py-1 rounded-full text-xs font-medium capitalize">
                      <?= htmlspecialchars($row['codename']) ?>
                    </span>
                  </div>
                </div>

                <div class="p-6">
                  <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-orange-500 transition-colors duration-200">
                    <?= htmlspecialchars($row['product_name']) ?>
                  </h3>
                  <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                    <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
                  </p>

                  <div class="flex items-center justify-between">
                    <span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-xs font-medium">
                      <i class="fas fa-layer-group mr-1"></i>
                      <?= $variant_count ?> Variant<?= $variant_count !== 1 ? 's' : '' ?>
                    </span>
                    <span class="text-xs text-gray-400">ID: <?= $product_id ?></span>
                  </div>

                  <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <button class="w-full bg-orange-500 text-white py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium">
                      <i class="fas fa-shopping-cart mr-2"></i>View Product
                    </button>
                  </div>
                </div>
              </a>
            </div>
          <?php endwhile; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="flex flex-col sm:flex-row items-center justify-between bg-white rounded-2xl shadow-lg p-6" data-aos="fade-up">
            <div class="text-sm text-gray-700 mb-4 sm:mb-0">
              Showing <span class="font-medium"><?= ($page - 1) * $per_page + 1 ?></span> to
              <span class="font-medium"><?= min($page * $per_page, $total_products) ?></span> of
              <span class="font-medium"><?= number_format($total_products) ?></span> products
            </div>

            <div class="flex items-center space-x-2">
              <!-- Previous Button -->
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                class="pagination-btn px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>
                <i class="fas fa-chevron-left mr-1"></i>
                Previous
              </a>

              <!-- Page Numbers -->
              <div class="flex items-center space-x-1">
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                if ($start > 1) {
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">1</a>';
                  if ($start > 2) {
                    echo '<span class="px-3 py-2 text-sm text-gray-500">...</span>';
                  }
                }

                for ($i = $start; $i <= $end; $i++) {
                  $active = $i == $page ? 'bg-orange-500 text-white border-orange-500' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50';
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="px-3 py-2 text-sm font-medium ' . $active . ' border rounded-lg transition-all duration-200">' . $i . '</a>';
                }

                if ($end < $total_pages) {
                  if ($end < $total_pages - 1) {
                    echo '<span class="px-3 py-2 text-sm text-gray-500">...</span>';
                  }
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">' . $total_pages . '</a>';
                }
                ?>
              </div>

              <!-- Next Button -->
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>"
                class="pagination-btn px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>
                Next
                <i class="fas fa-chevron-right ml-1"></i>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- No Products Found -->
        <?php if ($total_products == 0): ?>
          <div class="text-center py-16" data-aos="fade-up">
            <div class="max-w-md mx-auto">
              <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-search text-gray-400 text-3xl"></i>
              </div>
              <h3 class="text-2xl font-bold text-gray-900 mb-4">No Products Found</h3>
              <p class="text-gray-600 mb-6">
                We couldn't find any products matching your search criteria. Try adjusting your filters or search terms.
              </p>
              <div class="space-y-3">
                <a href="?" class="inline-block bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium">
                  <i class="fas fa-refresh mr-2"></i>
                  Clear All Filters
                </a>
                <p class="text-sm text-gray-500">
                  Or <a href="contact.php" class="text-orange-500 hover:text-orange-700 underline">contact us</a> for custom product requests
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

   <!-- ✅ FIXED: Desktop Filter Sidebar (RIGHT) -->
      <div class="w-full lg:w-80 lg:order-2 desktop-filter">
        <div class="bg-white rounded-2xl shadow-lg sticky-filter">
          <div class="p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
              <i class="fas fa-filter mr-2 text-orange-500"></i>
              Filter Products
            </h2>

            <!-- Desktop Filter Form -->
            <form method="GET" class="space-y-6" id="desktopFilterForm">
              <!-- Categories -->
              <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Categories</h3>
                <div class="space-y-3">
                  <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                    <label class="filter-chip flex items-center justify-between p-3 bg-gray-50 hover:bg-orange-50 rounded-lg cursor-pointer transition-all duration-200 <?= in_array($cat_key, $selected_categories) ? 'bg-orange-100 border-orange-300' : 'border-gray-200' ?> border">
                      <div class="flex items-center space-x-3">
                        <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                          class="form-checkbox text-orange-500 border-gray-300 rounded focus:ring-2 focus:ring-orange-400 focus:ring-offset-0">
                        <span class="text-sm font-medium"><?= htmlspecialchars($cat_name) ?></span>
                      </div>
                      <span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded-full"><?= $category_counts[$cat_key] ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Search -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">Search Products</label>
                <div class="relative">
                  <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                    placeholder="Search by name or description..."
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                  <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
              </div>

              <!-- Sort -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">Sort By</label>
                <select name="sort" class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                  <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                  <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                  <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                  <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
              </div>

              <!-- Filter Actions -->
              <div class="space-y-3">
                <button type="submit" class="w-full bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium flex items-center justify-center">
                  <i class="fas fa-search mr-2"></i>
                  Apply Filters
                </button>
                <button type="button" onclick="clearAllFilters()" class="w-full px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                  Clear All Filters
                </button>
              </div>
            </form>

            <!-- Active Filters Display -->
            <?php if (!empty($selected_categories) || !empty($search_keyword)): ?>
              <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Active Filters</h4>
                <div class="flex flex-wrap gap-2">
                  <?php foreach ($selected_categories as $cat): ?>
                    <span class="inline-flex items-center bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-medium">
                      <?= htmlspecialchars($all_categories[$cat] ?? $cat) ?>
                      <button type="button" onclick="removeFilter('category', '<?= $cat ?>')" class="ml-2 text-orange-500 hover:text-orange-700">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                  <?php endforeach; ?>
                  
                  <?php if (!empty($search_keyword)): ?>
                    <span class="inline-flex items-center bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">
                      "<?= htmlspecialchars($search_keyword) ?>"
                      <button type="button" onclick="removeFilter('search', '')" class="ml-2 text-blue-500 hover:text-blue-700">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="mt-6 pt-6 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-700 mb-3">Quick Stats</h4>
              <div class="space-y-2 text-sm text-gray-600">
                <div class="flex justify-between">
                  <span>Total Products:</span>
                  <span class="font-medium"><?= number_format($total_products) ?></span>
                </div>
                <div class="flex justify-between">
                  <span>Current Page:</span>
                  <span class="font-medium"><?= $page ?> of <?= $total_pages ?></span>
                </div>
                <div class="flex justify-between">
                  <span>Per Page:</span>
                  <span class="font-medium"><?= $per_page ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Scripts -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    // Initialize AOS
    AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true,
      offset: 100
    });

    // ✅ Mobile Filter Toggle System
    const mobileFilterToggle = document.getElementById('mobileFilterToggle');
    const mobileFilterPanel = document.getElementById('mobileFilterPanel');
    const closeMobileFilter = document.getElementById('closeMobileFilter');

    // Open mobile filter
    mobileFilterToggle?.addEventListener('click', function() {
      mobileFilterPanel.classList.add('active');
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
    });

    // Close mobile filter
    function closeMobileFilterPanel() {
      mobileFilterPanel.classList.remove('active');
      document.body.style.overflow = ''; // Restore scrolling
    }

    closeMobileFilter?.addEventListener('click', closeMobileFilterPanel);

    // Close on overlay click
    mobileFilterPanel?.addEventListener('click', function(e) {
      if (e.target === mobileFilterPanel) {
        closeMobileFilterPanel();
      }
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && mobileFilterPanel.classList.contains('active')) {
        closeMobileFilterPanel();
      }
    });

    // ✅ Enhanced Rating System
    document.querySelectorAll('.rate-stars').forEach(function(starsContainer) {
      const productId = starsContainer.dataset.productId;
      const stars = starsContainer.querySelectorAll('.star-button');
      const avgRatingElement = starsContainer.querySelector('.avg-rating');

      stars.forEach(function(star, index) {
        star.addEventListener('click', function() {
          const rating = parseInt(star.dataset.rating);
          
          // Send rating to server
          fetch('rate_product.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              product_id: productId,
              rating: rating
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update stars display
              updateStarsDisplay(stars, data.new_average);
              avgRatingElement.textContent = `(${data.new_average}/5)`;
              
              // Show success message
              showNotification('Rating submitted successfully!', 'success');
            } else {
              showNotification(data.message || 'Failed to submit rating', 'error');
            }
          })
          .catch(error => {
            console.error('Rating error:', error);
            showNotification('Failed to submit rating', 'error');
          });
        });

        // Hover effect
        star.addEventListener('mouseenter', function() {
          const rating = parseInt(star.dataset.rating);
          highlightStars(stars, rating);
        });
      });

      // Reset on mouse leave
      starsContainer.addEventListener('mouseleave', function() {
        const currentRating = parseFloat(avgRatingElement.textContent.match(/\(([\d.]+)/)?.[1] || 0);
        updateStarsDisplay(stars, currentRating);
      });
    });

    function highlightStars(stars, rating) {
      stars.forEach(function(star, index) {
        const starRating = index + 1;
        const icon = star.querySelector('i');
        
        if (starRating <= rating) {
          icon.className = 'fas fa-star';
        } else {
          icon.className = 'far fa-star';
        }
      });
    }

    function updateStarsDisplay(stars, average) {
      stars.forEach(function(star, index) {
        const starRating = index + 1;
        const icon = star.querySelector('i');
        
        if (starRating <= Math.floor(average)) {
          icon.className = 'fas fa-star';
        } else if (starRating - average < 1) {
          icon.className = 'fas fa-star-half-alt';
        } else {
          icon.className = 'far fa-star';
        }
      });
    }

    // ✅ Quick Sort Function
    function quickSort(sortValue) {
      const url = new URL(window.location);
      url.searchParams.set('sort', sortValue);
      url.searchParams.set('page', '1'); // Reset to first page
      window.location.href = url.toString();
    }

    // ✅ Filter Management Functions
    function removeFilter(type, value) {
      const url = new URL(window.location);
      
      if (type === 'category') {
        const categories = url.searchParams.getAll('category[]');
        const filteredCategories = categories.filter(cat => cat !== value);
        
        url.searchParams.delete('category[]');
        filteredCategories.forEach(cat => url.searchParams.append('category[]', cat));
      } else if (type === 'search') {
        url.searchParams.delete('search');
      }
      
      url.searchParams.set('page', '1'); // Reset to first page
      window.location.href = url.toString();
    }

    function clearAllFilters() {
      const url = new URL(window.location);
      url.searchParams.delete('category[]');
      url.searchParams.delete('search');
      url.searchParams.delete('sort');
      url.searchParams.delete('page');
      window.location.href = url.toString();
    }

    // ✅ Clear Filters Button for Mobile
    document.getElementById('clearFilters')?.addEventListener('click', function() {
      clearAllFilters();
    });

    // ✅ Enhanced Notification System
    function showNotification(message, type = 'info') {
      // Remove existing notifications
      const existingNotifications = document.querySelectorAll('.notification');
      existingNotifications.forEach(notif => notif.remove());

      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
      
      // Set notification style based on type
      switch(type) {
        case 'success':
          notification.classList.add('bg-green-500', 'text-white');
          break;
        case 'error':
          notification.classList.add('bg-red-500', 'text-white');
          break;
        case 'warning':
          notification.classList.add('bg-yellow-500', 'text-white');
          break;
        default:
          notification.classList.add('bg-blue-500', 'text-white');
      }

      notification.innerHTML = `
        <div class="flex items-center space-x-3">
          <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
          <span>${message}</span>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;

      document.body.appendChild(notification);

      // Animate in
      setTimeout(() => {
        notification.classList.remove('translate-x-full');
      }, 100);

      // Auto remove after 5 seconds
      setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
      }, 5000);
    }

    // ✅ Enhanced Image Loading with Error Handling
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
      img.addEventListener('error', function() {
        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgODBDOTQuNDc3MiA4MCA5MCA4NC40NzcyIDkwIDkwVjExMEM5MCA5NS41MjI4IDk0LjQ3NzIgMTAwIDEwMCAxMDBIMTIwQzEyNS41MjMgMTAwIDEzMCA5NS41MjI4IDEzMCA5MFY4MEMxMzAgNzQuNDc3MiAxMjUuNTIzIDcwIDEyMCA3MEgxMDBDOTQuNDc3MiA3MCA5MCA3NC40NzcyIDkwIDgwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K';
        this.alt = 'Image not available';
      });
    });

    // ✅ Smooth Scroll for Pagination
    document.querySelectorAll('a[href*="page="]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        // Small delay to allow page load, then scroll to top
        setTimeout(() => {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 100);
      });
    });

    // ✅ Enhanced Search Input
    const searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(function(input) {
      let searchTimeout;
      
      input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        // Auto-submit after user stops typing for 1 second (optional)
        searchTimeout = setTimeout(() => {
          // Uncomment the line below for auto-search
          // this.closest('form').submit();
        }, 1000);
      });

      // Clear search button
      if (input.value) {
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600';
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.addEventListener('click', function() {
          input.value = '';
          input.focus();
        });
        
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(clearBtn);
      }
    });

    // ✅ Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K to focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
      
      // Escape to clear search
      if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="search"]:focus');
        if (searchInput) {
          searchInput.value = '';
          searchInput.blur();
        }
      }
    });

    // ✅ Analytics and Performance Tracking
    console.log('🏠 Noble Home - Product Catalog Loaded');
    console.log(`📊 Performance: ${performance.now().toFixed(2)}ms`);
    console.log(`🛍️ Products: ${<?= $total_products ?>} total, ${<?= $products->num_rows ?>} displayed`);
  </script>
</body>
</html>