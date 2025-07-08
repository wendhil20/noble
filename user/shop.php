<?php
session_start();
include '../connection/connect.php';

// ✅ Enhanced security: Restore user if session expired but remember_token exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];
  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ? AND token_expires > NOW()");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];

    // Update last login
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    $update_stmt->close();
  }
  $stmt->close();
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

// ✅ Enhanced categories
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
        Our <span class="text-orange-500">Premium</span> Collection
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

    <!-- Enhanced Showcase Carousel -->
    <div class="mb-12" data-aos="fade-up" data-aos-delay="200">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-auto lg:h-96">
        <!-- Main Carousel -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg overflow-hidden relative">
          <div class="relative h-full">
            <!-- Carousel slides with enhanced content -->
            <div class="carousel-slide absolute inset-0 opacity-100 transition-opacity duration-500">
              <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"
                alt="Living Room Collection"
                class="w-full h-2/3 object-cover">
              <div class="p-6 h-1/3 flex flex-col justify-center bg-gradient-to-r from-orange-50 to-white">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Premium Living Room Sets</h3>
                <p class="text-gray-600 text-sm mb-3">Transform your space with our luxury furniture collection</p>
                <div class="flex items-center space-x-2">
                  <span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-xs font-medium">New Arrivals</span>
                  <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-medium">20% Off</span>
                </div>
              </div>
            </div>

            <div class="carousel-slide absolute inset-0 opacity-0 transition-opacity duration-500">
              <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"
                alt="Bedroom Collection"
                class="w-full h-2/3 object-cover">
              <div class="p-6 h-1/3 flex flex-col justify-center bg-gradient-to-r from-blue-50 to-white">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Cozy Bedroom Collection</h3>
                <p class="text-gray-600 text-sm mb-3">Create your perfect sanctuary with our bedroom essentials</p>
                <div class="flex items-center space-x-2">
                  <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-xs font-medium">Comfort Plus</span>
                  <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-xs font-medium">Eco-Friendly</span>
                </div>
              </div>
            </div>

            <div class="carousel-slide absolute inset-0 opacity-0 transition-opacity duration-500">
              <img src="https://images.unsplash.com/photo-1449247709967-d4461a6a6103?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"
                alt="Kitchen Collection"
                class="w-full h-2/3 object-cover">
              <div class="p-6 h-1/3 flex flex-col justify-center bg-gradient-to-r from-purple-50 to-white">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Modern Kitchen Solutions</h3>
                <p class="text-gray-600 text-sm mb-3">Upgrade your culinary space with stylish functionality</p>
                <div class="flex items-center space-x-2">
                  <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-xs font-medium">Professional Grade</span>
                  <span class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-xs font-medium">Best Seller</span>
                </div>
              </div>
            </div>

            <!-- Enhanced carousel controls -->
            <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
              <button class="dot w-3 h-3 rounded-full bg-orange-500 transition-all duration-300 hover:bg-orange-600" onclick="showSlide(0)"></button>
              <button class="dot w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-75 transition-all duration-300" onclick="showSlide(1)"></button>
              <button class="dot w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-75 transition-all duration-300" onclick="showSlide(2)"></button>
            </div>

            <!-- Navigation arrows -->
            <button class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 hover:bg-opacity-75 text-white rounded-full p-2 transition-all duration-300" onclick="prevSlide()">
              <i class="fas fa-chevron-left"></i>
            </button>
            <button class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 hover:bg-opacity-75 text-white rounded-full p-2 transition-all duration-300" onclick="nextSlide()">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>

        <!-- Enhanced Promotion Cards -->
        <div class="flex flex-col gap-6">
          <div class="bg-white rounded-2xl shadow-lg overflow-hidden relative flex-1 group">
            <div class="absolute top-3 right-3 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-bold z-10 animate-pulse">
              50% OFF
            </div>
            <img src="https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
              alt="Chair Promotion"
              class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300">
            <div class="p-4">
              <h4 class="font-bold text-gray-900 text-base mb-2">Designer Chairs</h4>
              <p class="text-gray-600 text-sm mb-3">Limited time offer on premium seating</p>
              <button class="w-full bg-orange-500 text-white py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 text-sm font-medium">
                Shop Now
              </button>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg overflow-hidden relative flex-1 group">
            <div class="absolute top-3 right-3 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold z-10">
              NEW
            </div>
            <img src="https://images.unsplash.com/photo-1493663284031-b7e3aaa4c4bf?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
              alt="Table Promotion"
              class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300">
            <div class="p-4">
              <h4 class="font-bold text-gray-900 text-base mb-2">Wooden Tables</h4>
              <p class="text-gray-600 text-sm mb-3">Fresh arrivals in our wood collection</p>
              <button class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors duration-200 text-sm font-medium">
                Explore
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>


    <section class="py-[140px]">
      <!-- Enhanced Filters -->
      <div class="bg-white rounded-2xl shadow-lg p-6 " data-aos="fade-up" data-aos-delay="300">
        <form method="GET" class="space-y-6">
          <!-- Filter Header -->
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-900 flex items-center">
              <i class="fas fa-filter mr-2 text-orange-500"></i>
              Filter Products
            </h2>
            <div class="text-sm text-gray-500">
              <span class="font-medium"><?= number_format($total_products) ?></span> products found
            </div>
          </div>

          <!-- Categories with counts -->
          <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Categories</h3>
            <div class="flex flex-wrap gap-3">
              <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                <label class="filter-chip inline-flex items-center space-x-2 px-4 py-2 bg-gray-50 hover:bg-orange-50 rounded-full cursor-pointer transition-all duration-200 <?= in_array($cat_key, $selected_categories) ? 'bg-orange-100 border-orange-300' : 'border-gray-200' ?> border">
                  <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                    class="form-checkbox text-orange-500 border-gray-300 rounded focus:ring-2 focus:ring-orange-400 focus:ring-offset-0">
                  <span class="text-sm font-medium"><?= htmlspecialchars($cat_name) ?></span>
                  <span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded-full"><?= $category_counts[$cat_key] ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Search and Sort -->
          <div class="flex flex-col lg:flex-row gap-4 items-end">
            <!-- Search -->
            <div class="flex-1">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
              <div class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                  placeholder="Search by name or description..."
                  class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
              </div>
            </div>

            <!-- Sort -->
            <div class="w-full lg:w-48">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
              <select name="sort" class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
              </select>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full lg:w-auto bg-orange-500 text-white px-8 py-3 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium flex items-center justify-center">
              <i class="fas fa-search mr-2"></i>
              Apply Filters
            </button>
          </div>

          <!-- Active Filters -->
          <?php if (!empty($selected_categories) || !empty($search_keyword)): ?>
            <div class="border-t pt-4">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Active filters:</span>
                <?php foreach ($selected_categories as $cat): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                    <?= htmlspecialchars($all_categories[$cat] ?? $cat) ?>
                    <button type="button" class="ml-1 text-orange-500 hover:text-orange-700" onclick="removeFilter('category', '<?= $cat ?>')">
                      <i class="fas fa-times"></i>
                    </button>
                  </span>
                <?php endforeach; ?>
                <?php if (!empty($search_keyword)): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                    Search: "<?= htmlspecialchars($search_keyword) ?>"
                    <button type="button" class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeFilter('search', '')">
                      <i class="fas fa-times"></i>
                    </button>
                  </span>
                <?php endif; ?>
                <a href="?" class="text-sm text-red-500 hover:text-red-700 underline">Clear all</a>
              </div>
            </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Enhanced Product Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8 py-4" data-aos="fade-up" data-aos-delay="400">
        <?php while ($row = $products->fetch_assoc()): ?>
          <?php
          $product_id = (int)$row['id'];
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
            <a href="product_view.php?id=<?= $product_id ?>" class="block">
              <!-- Image Container -->
              <div class="relative aspect-square bg-gray-50 overflow-hidden">
                <?php if (!empty($row['main_image'])): ?>
                 <img src="../<?= htmlspecialchars($row['main_image']) ?>"
     alt="<?= htmlspecialchars($row['product_name']) ?>"
     class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
     loading="lazy">

                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                    <i class="fas fa-image text-4xl"></i>
                  </div>
                <?php endif; ?>

                <!-- Overlay -->
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                  <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <span class="bg-white text-gray-900 px-4 py-2 rounded-full text-sm font-medium">
                      <i class="fas fa-eye mr-2"></i>View Details
                    </span>
                  </div>
                </div>

                <!-- Category Badge -->
                <div class="absolute top-3 left-3">
                  <span class="bg-white bg-opacity-90 text-gray-700 px-3 py-1 rounded-full text-xs font-medium capitalize">
                    <?= htmlspecialchars($row['codename']) ?>
                  </span>
                </div>

                <!-- Wishlist Button -->
                <button class="absolute top-3 right-3 bg-white bg-opacity-90 hover:bg-opacity-100 text-gray-600 hover:text-red-500 p-2 rounded-full transition-all duration-200">
                  <i class="fas fa-heart"></i>
                </button>
              </div>

              <!-- Product Info -->
              <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-orange-500 transition-colors duration-200">
                  <?= htmlspecialchars($row['product_name']) ?>
                </h3>
                <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                  <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
                </p>

                <!-- Product Meta -->
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-xs font-medium">
                      <i class="fas fa-layer-group mr-1"></i>
                      <?= $variant_count ?> Variant<?= $variant_count !== 1 ? 's' : '' ?>
                    </span>
                  </div>
                  <span class="text-xs text-gray-400">ID: <?= $product_id ?></span>
                </div>

                <!-- Action Button -->
                <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <button class="w-full bg-orange-500 text-white py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 font-medium">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    View Product
                  </button>
                </div>
              </div>
            </a>
          </div>
        <?php endwhile; ?>
      </div>
    </section>
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


   <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

                <!-- Enhanced Branding Section -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <!-- Logo with glow and pulse -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                                <img src="img/logo/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Text Branding -->
                        <div>
                            <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

                        </div>
                    </div>


                    <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
                        Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
                    </p>

                    <!-- Contact Info -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">0968 591 6536</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Quick Links
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <nav class="space-y-3">
                        <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
                        <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
                        <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
                    </nav>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Our Services
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <ul class="space-y-3 text-gray-300">
                        <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

            <!-- Bottom Section -->
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                <!-- Copyright -->
                <div class="text-center lg:text-left">
                    <p class="text-gray-400 text-sm">
                        © 2025 Noble Home Construction. All rights reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Licensed & Insured | PCAB License No. 12345
                    </p>
                </div>

                <!-- Enhanced Social Media -->
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm mr-2">Follow us:</span>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                </div>

                <!-- Back to Top Button -->
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Background Pattern -->
        <div class="absolute bottom-0 right-0 opacity-5">
            <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
                <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
                <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
                <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
            </svg>
        </div>
    </footer>


  <!-- Enhanced JavaScript -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    // Initialize AOS
    AOS.init({
      duration: 800,
      once: true,
      offset: 100
    });

    // Enhanced Carousel functionality
    let currentSlide = 0;
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.dot');
    const totalSlides = slides.length;

    function showSlide(index) {
      // Hide all slides
      slides.forEach(slide => slide.classList.remove('opacity-100'));
      slides.forEach(slide => slide.classList.add('opacity-0'));

      // Update dots
      dots.forEach(dot => dot.classList.remove('bg-orange-500'));
      dots.forEach(dot => dot.classList.add('bg-white', 'bg-opacity-50'));

      // Show current slide
      slides[index].classList.remove('opacity-0');
      slides[index].classList.add('opacity-100');

      // Update current dot
      dots[index].classList.remove('bg-white', 'bg-opacity-50');
      dots[index].classList.add('bg-orange-500');

      currentSlide = index;
    }

    function nextSlide() {
      currentSlide = (currentSlide + 1) % totalSlides;
      showSlide(currentSlide);
    }

    function prevSlide() {
      currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
      showSlide(currentSlide);
    }

    // Auto-advance carousel
    setInterval(nextSlide, 5000);

    // Filter removal functions
    function removeFilter(filterType, filterValue) {
      const url = new URL(window.location);
      const params = new URLSearchParams(url.search);

      if (filterType === 'category') {
        const categories = params.getAll('category[]');
        const newCategories = categories.filter(cat => cat !== filterValue);
        params.delete('category[]');
        newCategories.forEach(cat => params.append('category[]', cat));
      } else if (filterType === 'search') {
        params.delete('search');
      }

      // Reset to page 1
      params.set('page', '1');

      window.location.search = params.toString();
    }

    // Enhanced form handling
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-submit form on filter change
      const filterForm = document.querySelector('form');
      const checkboxes = filterForm.querySelectorAll('input[type="checkbox"]');
      const sortSelect = filterForm.querySelector('select[name="sort"]');

      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          // Reset to page 1 when filters change
          const pageInput = filterForm.querySelector('input[name="page"]');
          if (pageInput) {
            pageInput.value = '1';
          } else {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'page';
            hiddenInput.value = '1';
            filterForm.appendChild(hiddenInput);
          }

          // Submit form with delay to show loading state
          setTimeout(() => {
            filterForm.submit();
          }, 100);
        });
      });

      if (sortSelect) {
        sortSelect.addEventListener('change', function() {
          filterForm.submit();
        });
      }

      // Enhanced search functionality
      const searchInput = filterForm.querySelector('input[name="search"]');
      let searchTimeout;

      if (searchInput) {
        searchInput.addEventListener('input', function() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            // Auto-submit after 1 second of no typing
            if (this.value.length >= 3 || this.value.length === 0) {
              filterForm.submit();
            }
          }, 1000);
        });
      }
    });

    // Loading state for better UX
    function showLoading() {
      const productGrid = document.querySelector('.grid');
      if (productGrid) {
        productGrid.style.opacity = '0.5';
        productGrid.style.pointerEvents = 'none';
      }
    }

    // Smooth scroll to top when pagination changes
    if (window.location.search.includes('page=')) {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }

    // Enhanced product card interactions
    document.querySelectorAll('.product-card').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px)';
      });

      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });

    // Wishlist functionality (placeholder)
    document.querySelectorAll('.fas.fa-heart').forEach(heart => {
      heart.closest('button').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const icon = this.querySelector('i');
        if (icon.classList.contains('fas')) {
          icon.classList.remove('fas');
          icon.classList.add('far');
          this.classList.remove('text-red-500');
          this.classList.add('text-gray-600');
        } else {
          icon.classList.remove('far');
          icon.classList.add('fas');
          this.classList.remove('text-gray-600');
          this.classList.add('text-red-500');
        }
      });
    });
  </script>
</body>

</html>