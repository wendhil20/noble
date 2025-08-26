<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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
  header('Location: ../google-callback.php'); // You may replace with `index.php` if default login
  exit;
}


// ✅ Enhanced input validation and sanitization
$selected_categories = isset($_GET['category']) && is_array($_GET['category']) ? $_GET['category'] : [];
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$per_page = isset($_GET['per_page']) ? max(12, min(100, intval($_GET['per_page']))) : 12;
$per_page_options = [12, 24, 36, 48];
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

$all_categories = [
  'furniture' => 'Furniture',
  'material' => 'Materials',
  'electrical' => 'Electrical',
  'lighting' => 'Lighting',
  'bedfurniture' => 'Bedroom Furniture',
  'aircon' => 'Air Conditioners',
  'doors' => 'Doors',
  'tiles' => 'Tiles',
  'windows' => 'Windows',
  'bathroom' => 'Bathroom Fixtures',
  'kitchen' => 'Kitchen Fixtures',
  'pipes' => 'Pipes',
  'aacblock' => 'AAC BLOCKS',
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

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Inter', sans-serif;
    }

    .playfair {
      font-family: 'Playfair Display', serif;
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .product-card {
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(229, 231, 235, 0.6);
      background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
    }

    .product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      border-color: rgba(249, 115, 22, 0.4);
    }

    .filter-section {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid rgba(226, 232, 240, 0.8);
      backdrop-filter: blur(10px);
    }

    .category-chip {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(226, 232, 240, 0.6);
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }

    .category-chip:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      border-color: rgba(249, 115, 22, 0.3);
    }

    .category-chip input:checked+.category-content {
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: white;
    }

    /* Mobile Filter Panel */
    .mobile-filter-panel {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.7);
      z-index: 50;
      backdrop-filter: blur(8px);
    }

    .mobile-filter-panel.active {
      display: flex;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .mobile-filter-content {
      background: white;
      margin: 1rem;
      border-radius: 1.5rem;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
      from {
        transform: translateY(2rem);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .search-input {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .search-input:focus {
      box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
      border-color: #f97316;
    }

    /* Enhanced Pagination Styles */
    .pagination-container {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .pagination-btn {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .pagination-btn:hover:not(.disabled) {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      border-color: rgba(249, 115, 22, 0.3);
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: white;
    }

    .pagination-btn.active {
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: white;
      border-color: #f97316;
      box-shadow: 0 8px 25px rgba(249, 115, 22, 0.3);
    }

    .pagination-btn.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: #f1f5f9;
      color: #94a3b8;
    }

    .pagination-info {
      background: linear-gradient(135deg, rgba(249, 115, 22, 0.1) 0%, rgba(234, 88, 12, 0.1) 100%);
      border: 1px solid rgba(249, 115, 22, 0.2);
    }

    .hero-gradient {

      position: relative;
      overflow: hidden;
    }

    .hero-gradient::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23ffffff" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="%23ffffff" opacity="0.05"/><circle cx="50" cy="10" r="1" fill="%23ffffff" opacity="0.05"/><circle cx="10" cy="60" r="1" fill="%23ffffff" opacity="0.05"/><circle cx="90" cy="40" r="1" fill="%23ffffff" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      opacity: 0.3;
    }

    @media (max-width: 1023px) {
      .desktop-filter {
        display: none;
      }
    }

    @media (min-width: 1024px) {
      .mobile-filter-toggle {
        display: none;
      }

      .sticky-filter {
        position: sticky;
        top: 2rem;
        max-height: calc(100vh - 4rem);
        overflow-y: auto;
      }
    }

    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
      background: rgba(241, 245, 249, 0.5);
      border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    }

    .rating-star {
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .rating-star:hover {
      transform: scale(1.15);
      filter: drop-shadow(0 2px 4px rgba(249, 115, 22, 0.3));
    }

    .product-image-container {
      overflow: hidden;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .product-image {
      transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .product-card:hover .product-image {
      transform: scale(1.08);
    }

    .stats-badge {
      background: linear-gradient(135deg, rgba(249, 115, 22, 0.1) 0%, rgba(234, 88, 12, 0.1) 100%);
      border: 1px solid rgba(249, 115, 22, 0.2);
    }

    .view-options {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid rgba(226, 232, 240, 0.8);
    }

    /* Page transition effect */
    .page-transition {
      opacity: 0;
      animation: fadeInUp 0.6s ease forwards;
    }

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
  </style>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'inter': ['Inter', 'sans-serif'],
            'playfair': ['Playfair Display', 'serif']
          },
          colors: {
            'primary': '#f97316',
            'primary-dark': '#ea580c'
          }
        }
      }
    }

    // JavaScript functions for your PHP integration
    function changePerPage(value) {
      const url = new URL(window.location);
      url.searchParams.set('per_page', value);
      url.searchParams.set('page', '1'); // Reset to page 1
      window.location.href = url.toString();
    }

    function changeSort(value) {
      const url = new URL(window.location);
      url.searchParams.set('sort', value);
      url.searchParams.set('page', '1'); // Reset to page 1
      window.location.href = url.toString();
    }

    function quickSort(value) {
      changeSort(value);
    }

    function clearAllFilters() {
      window.location.href = window.location.pathname;
    }

    function removeFilter(type, value) {
      const url = new URL(window.location);
      if (type === 'category') {
        const categories = url.searchParams.getAll('category[]');
        const filtered = categories.filter(cat => cat !== value);
        url.searchParams.delete('category[]');
        filtered.forEach(cat => url.searchParams.append('category[]', cat));
      } else if (type === 'search') {
        url.searchParams.delete('search');
      }
      url.searchParams.set('page', '1');
      window.location.href = url.toString();
    }

    // Mobile filter toggle
    document.addEventListener('DOMContentLoaded', function() {
      const mobileToggle = document.getElementById('mobileFilterToggle');
      const mobilePanel = document.getElementById('mobileFilterPanel');
      const closeBtn = document.getElementById('closeMobileFilter');

      if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
          mobilePanel.classList.add('active');
        });
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          mobilePanel.classList.remove('active');
        });
      }

      // Close on backdrop click
      if (mobilePanel) {
        mobilePanel.addEventListener('click', (e) => {
          if (e.target === mobilePanel) {
            mobilePanel.classList.remove('active');
          }
        });
      }
    });
  </script>
</head>

<body class="bg-gray-50 font-inter text-gray-900">
<?php include '../navbar/top.php'; ?>

  <!-- Modern Breadcrumb -->
  <nav class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center space-x-3 py-4 text-sm">
        <a href="index" class="flex items-center text-primary hover:text-primary-dark transition-colors font-medium">
          <i class="fas fa-home mr-1.5"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
        <span class="font-semibold text-gray-700">Products</span>
        <!-- PHP: Add search breadcrumb if exists -->
        <?php if (!empty($search_keyword)): ?>
          <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
          <span class="text-gray-500">Search: "<?= htmlspecialchars($search_keyword) ?>"</span>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- Modern Hero Section -->
  <section class="hero-gradient relative">
    <div class="relative max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
      <div class="text-center" data-aos="fade-up">
        <h1 class="text-4xl lg:text-6xl font-playfair font-bold text-black mb-6">
          Premium <span class="text-orange-400">Collections</span>
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-10 leading-relaxed">
          Discover exceptional furniture and materials crafted with precision and designed for modern living
        </p>

        <!-- Trust Indicators -->
        <div class="flex flex-wrap justify-center gap-8 mb-12">
          <div class="flex items-center text-gray-700">
            <div class="w-12 h-12 bg-green-500 bg-opacity-20 rounded-full flex items-center justify-center mr-3">
              <i class="fas fa-shield-alt text-green-400 text-lg"></i>
            </div>
            <span class="font-medium">Quality Guaranteed</span>
          </div>
          <div class="flex items-center text-gray-700">
            <div class="w-12 h-12 bg-blue-500 bg-opacity-20 rounded-full flex items-center justify-center mr-3">
              <i class="fas fa-shipping-fast text-blue-400 text-lg"></i>
            </div>
            <span class="font-medium">Fast Delivery</span>
          </div>
          <div class="flex items-center text-gray-700">
            <div class="w-12 h-12 bg-yellow-500 bg-opacity-20 rounded-full flex items-center justify-center mr-3">
              <i class="fas fa-award text-yellow-400 text-lg"></i>
            </div>
            <span class="font-medium">Premium Materials</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="#products" class="inline-flex items-center justify-center bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
            <i class="fas fa-eye mr-2"></i>
            Explore Products
          </a>
          <a href="allproduct" class="inline-flex items-center justify-center bg-black bg-opacity-50 hover:bg-opacity-20 text-white font-semibold py-4 px-8 rounded-xl backdrop-blur-sm border border-white border-opacity-30 hover:border-opacity-50 transition-all duration-300">
            <i class="fas fa-th mr-2"></i>
            View All Products
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content -->
  <main class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-12" id="products">

    <!-- Mobile Filter Toggle -->
    <div class="mobile-filter-toggle mb-8">
      <button id="mobileFilterToggle" class="w-full bg-primary hover:bg-primary-dark text-white px-6 py-4 rounded-xl font-semibold flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300">
        <i class="fas fa-sliders-h mr-3"></i>
        Filter & Sort Products
        <i class="fas fa-chevron-down ml-3"></i>
      </button>
    </div>

    <!-- Mobile Filter Panel -->
    <div id="mobileFilterPanel" class="mobile-filter-panel">
      <div class="mobile-filter-content max-w-md w-full">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900 playfair">
              Filter Products
            </h2>
            <button type="button" class="text-gray-400 hover:text-gray-600 p-2" id="closeMobileFilter">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <!-- Mobile Filter Form -->
          <form method="GET" class="space-y-8" id="mobileFilterForm">
            <!-- Categories -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Categories</h3>
              <div class="space-y-3 max-h-64 overflow-y-auto custom-scrollbar">
                <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                  <label class="category-chip flex items-center justify-between p-4 rounded-xl cursor-pointer">
                    <div class="flex items-center space-x-3 category-content">
                      <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?> class="form-checkbox text-primary border-gray-300 rounded focus:ring-2 focus:ring-primary focus:ring-offset-0">
                      <span class="font-medium"><?= htmlspecialchars($cat_name) ?></span>
                    </div>
                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $category_counts[$cat_key] ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Search -->
            <div>
              <label class="block text-lg font-semibold text-gray-900 mb-4">Search</label>
              <div class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Search products..."
                  class="search-input w-full pl-12 pr-4 py-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
              </div>
            </div>

            <!-- Sort -->
            <div>
              <label class="block text-lg font-semibold text-gray-900 mb-4">Sort By</label>
              <select name="sort" class="w-full py-4 px-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
              </select>
            </div>

            <!-- Apply Buttons -->
            <div class="flex space-x-3">
              <button type="submit" class="flex-1 bg-primary text-white px-6 py-4 rounded-xl hover:bg-primary-dark transition-colors font-semibold">
                <i class="fas fa-search mr-2"></i>Apply Filters
              </button>
              <button type="button" onclick="clearAllFilters()" class="px-6 py-4 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors">
                Clear
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="flex flex-col lg:flex-row gap-8">

      <!-- Products Section -->
      <div class="flex-1 lg:order-1 page-transition">

        <!-- Products Header with Enhanced Controls -->
        <div class="view-options rounded-2xl p-6 mb-8 shadow-sm">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">

            <!-- Controls -->
            <div class="flex flex-col sm:flex-row items-center gap-4">
              
              <!-- Sort Selector -->
              <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Sort by:</label>
                <select onchange="changeSort(this.value)" class="py-2.5 px-4 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white text-sm font-medium min-w-[140px]">
                  <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                  <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12">
          <?php while ($row = $products->fetch_assoc()): ?>
            <?php
            $product_id = (int)$row['id'];
            // Get average rating
            $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM product_ratings WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $avg_rating = round($stmt->get_result()->fetch_assoc()['avg_rating'] ?? 0, 1);
            $stmt->close();

            // Get variant count
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

            <div class="product-card group rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300">
              <!-- Star Rating -->
              <!-- Star Rating Section (Replace the existing one) -->
              <div class="px-4 pt-4">
                <div class="rate-stars flex items-center gap-1" data-product-id="<?= $product_id ?>">
                  <!-- Interactive Star Buttons -->
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-button rating-star" data-rating="<?= $i ?>">
                      <?php if ($i <= floor($avg_rating)): ?>
                        <i class="fas fa-star text-yellow-400"></i>
                      <?php elseif ($i - $avg_rating < 1): ?>
                        <i class="fas fa-star-half-alt text-yellow-400"></i>
                      <?php else: ?>
                        <i class="far fa-star text-yellow-400"></i>
                      <?php endif; ?>
                    </button>
                  <?php endfor; ?>

                  <!-- Rating Display -->
                  <span class="ml-2 text-gray-600 text-xs avg-rating">(<?= $avg_rating ?>/5)</span>
                </div>
              </div>

              <!-- Product Link -->
              <a href="product_view.php?id=<?= $product_id ?>">
                <!-- Product Image -->
                <div class="product-image-container relative aspect-square mt-3">
                  <?php if (!empty($row['main_image'])): ?>
                    <img src="../../<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['product_name']) ?>" class="product-image w-full h-full object-contain" loading="lazy">
                  <?php else: ?>
                    <div class="product-image w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                      <i class="fas fa-image text-4xl"></i>
                    </div>
                  <?php endif; ?>

                  <!-- Overlay -->
                  <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                      <span class="bg-white text-gray-900 px-6 py-3 rounded-full font-semibold shadow-lg">
                        <i class="fas fa-eye mr-2"></i>View Details
                      </span>
                    </div>
                  </div>

                  <!-- Category Badge -->
                  <div class="absolute top-4 left-4">
                    <span class="bg-primary bg-opacity-90 text-white px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider">
                      <?= htmlspecialchars($row['codename']) ?>
                    </span>
                  </div>
                </div>

                <!-- Product Info -->
                <div class="p-6">
                  <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors line-clamp-2">
                    <?= htmlspecialchars($row['product_name']) ?>
                  </h3>
                  <p class="text-sm text-gray-600 line-clamp-2 mb-4">
                    <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
                  </p>

                  <!-- Variant Info -->
                  <div class="flex items-center justify-between mb-4">
                    <span class="stats-badge text-primary px-3 py-1.5 rounded-lg text-xs font-semibold">
                      <i class="fas fa-layer-group mr-1"></i>
                      <?= $variant_count ?> Variant<?= $variant_count !== 1 ? 's' : '' ?>
                    </span>
                  </div>

                  <!-- View Product Button -->
                  <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <button class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary-dark transition-colors font-semibold">
                      <i class="fas fa-shopping-cart mr-2"></i>View Product
                    </button>
                  </div>
                </div>
              </a>
            </div>
          <?php endwhile; ?>
        </div>

        <!-- Enhanced Pagination with Modern Design -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination-container rounded-2xl shadow-lg p-8" data-aos="fade-up">

            <!-- Pagination Info -->
            <div class="pagination-info rounded-xl p-4 mb-6 text-center">
              <div class="text-sm text-gray-700">
                Showing <span class="font-semibold text-primary"><?= ($page - 1) * $per_page + 1 ?></span> to
                <span class="font-semibold text-primary"><?= min($page * $per_page, $total_products) ?></span> of
                <span class="font-semibold text-primary"><?= number_format($total_products) ?></span> products
              </div>
              <div class="text-xs text-gray-500 mt-1">
                Page <?= $page ?> of <?= $total_pages ?>
              </div>
            </div>

            <!-- Pagination Controls -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">

              <!-- Previous Button -->
              <div class="flex-shrink-0">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                  class="pagination-btn inline-flex items-center px-6 py-3 text-sm font-semibold rounded-xl transition-all duration-300 <?= $page <= 1 ? 'disabled bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:text-white shadow-sm' ?>"
                  <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>
                  <i class="fas fa-chevron-left mr-2"></i>
                  Previous
                </a>
              </div>

              <!-- Page Numbers -->
              <div class="flex flex-wrap items-center justify-center gap-2">
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                // Show first page if not in range
                if ($start > 1) {
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pagination-btn w-12 h-12 flex items-center justify-center text-sm font-semibold rounded-xl bg-white text-gray-700 hover:text-white shadow-sm transition-all duration-300">1</a>';
                  if ($start > 2) {
                    echo '<span class="px-3 py-2 text-gray-400">...</span>';
                  }
                }

                // Show page numbers in range
                for ($i = $start; $i <= $end; $i++) {
                  $isActive = $i == $page;
                  $classes = $isActive ? 'pagination-btn active w-12 h-12 flex items-center justify-center text-sm font-semibold rounded-xl shadow-lg' : 'pagination-btn w-12 h-12 flex items-center justify-center text-sm font-semibold rounded-xl bg-white text-gray-700 hover:text-white shadow-sm';
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="' . $classes . ' transition-all duration-300">' . $i . '</a>';
                }

                // Show last page if not in range
                if ($end < $total_pages) {
                  if ($end < $total_pages - 1) {
                    echo '<span class="px-3 py-2 text-gray-400">...</span>';
                  }
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="pagination-btn w-12 h-12 flex items-center justify-center text-sm font-semibold rounded-xl bg-white text-gray-700 hover:text-white shadow-sm transition-all duration-300">' . $total_pages . '</a>';
                }
                ?>
              </div>

              <!-- Next Button -->
              <div class="flex-shrink-0">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>"
                  class="pagination-btn inline-flex items-center px-6 py-3 text-sm font-semibold rounded-xl transition-all duration-300 <?= $page >= $total_pages ? 'disabled bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:text-white shadow-sm' ?>"
                  <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>
                  Next
                  <i class="fas fa-chevron-right ml-2"></i>
                </a>
              </div>
            </div>

            <!-- Quick Jump -->
            <div class="mt-6 text-center">
              <div class="inline-flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                <label class="text-sm font-medium text-gray-700">Jump to page:</label>
                <input type="number" min="1" max="<?= $total_pages ?>" value="<?= $page ?>"
                  class="w-20 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-center"
                  onchange="jumpToPage(this.value)">
                <button onclick="jumpToPage(document.querySelector('input[type=number]').value)"
                  class="px-4 py-1.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors">
                  Go
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- No Products Found -->
        <?php if ($total_products == 0): ?>
          <div class="text-center py-20" data-aos="fade-up">
            <div class="max-w-md mx-auto">
              <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-32 h-32 flex items-center justify-center mx-auto mb-8">
                <i class="fas fa-search text-gray-400 text-4xl"></i>
              </div>
              <h3 class="text-3xl font-bold text-gray-900 mb-4 playfair">No Products Found</h3>
              <p class="text-gray-600 mb-8 leading-relaxed">
                We couldn't find any products matching your search criteria. Try adjusting your filters or search terms.
              </p>
              <div class="space-y-4">
                <a href="?" class="inline-block bg-primary text-white px-8 py-4 rounded-xl hover:bg-primary-dark transition-colors duration-300 font-semibold shadow-lg hover:shadow-xl">
                  <i class="fas fa-refresh mr-2"></i>
                  Clear All Filters
                </a>
                <p class="text-sm text-gray-500">
                  Or <a href="contact.php" class="text-primary hover:text-primary-dark underline font-medium">contact us</a> for custom product requests
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Desktop Filter Sidebar -->
      <div class="w-full lg:w-80 lg:order-2 desktop-filter">
        <div class="filter-section rounded-2xl shadow-lg sticky-filter">
          <div class="p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 playfair flex items-center">
              <i class="fas fa-sliders-h mr-3 text-primary"></i>
              Filter Products
            </h2>

            <!-- Desktop Filter Form -->
            <form method="GET" class="space-y-8" id="desktopFilterForm">
              <!-- Categories -->
              <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Categories</h3>
                <div class="space-y-3 max-h-80 overflow-y-auto custom-scrollbar">
                  <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                    <label class="category-chip flex items-center justify-between p-4 rounded-xl cursor-pointer transition-all duration-300">
                      <div class="flex items-center space-x-3 category-content">
                        <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                          class="form-checkbox text-primary border-gray-300 rounded focus:ring-2 focus:ring-primary focus:ring-offset-0">
                        <span class="font-medium"><?= htmlspecialchars($cat_name) ?></span>
                      </div>
                      <span class="text-xs bg-gray-100 px-2.5 py-1 rounded-full font-medium"><?= $category_counts[$cat_key] ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Search -->
              <div>
                <label class="block text-lg font-semibold text-gray-900 mb-4">Search Products</label>
                <div class="relative">
                  <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                    placeholder="Search by name or description..."
                    class="search-input w-full pl-12 pr-4 py-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                  <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
              </div>

              <!-- Sort -->
              <div>
                <label class="block text-lg font-semibold text-gray-900 mb-4">Sort By</label>
                <select name="sort" class="w-full py-4 px-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                  <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                  <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                  <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                  <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
              </div>

              <!-- Filter Actions -->
              <div class="space-y-4">
                <button type="submit" class="w-full bg-primary text-white px-6 py-4 rounded-xl hover:bg-primary-dark transition-colors duration-300 font-semibold shadow-lg hover:shadow-xl flex items-center justify-center">
                  <i class="fas fa-search mr-2"></i>
                  Apply Filters
                </button>
                <button type="button" onclick="clearAllFilters()" class="w-full px-6 py-4 border-2 border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all duration-300 font-medium">
                  Clear All Filters
                </button>
              </div>
            </form>

            <!-- Active Filters Display -->
            <?php if (!empty($selected_categories) || !empty($search_keyword)): ?>
              <div class="mt-8 pt-8 border-t border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Active Filters</h4>
                <div class="flex flex-wrap gap-2">
                  <?php foreach ($selected_categories as $cat): ?>
                    <span class="inline-flex items-center bg-gradient-to-r from-primary to-primary-dark text-white px-4 py-2 rounded-full text-sm font-medium">
                      <?= htmlspecialchars($all_categories[$cat] ?? $cat) ?>
                      <button type="button" onclick="removeFilter('category', '<?= $cat ?>')" class="ml-2 text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                  <?php endforeach; ?>

                  <?php if (!empty($search_keyword)): ?>
                    <span class="inline-flex items-center bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-full text-sm font-medium">
                      "<?= htmlspecialchars($search_keyword) ?>"
                      <button type="button" onclick="removeFilter('search', '')" class="ml-2 text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times"></i>
                      </button>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="mt-8 pt-8 border-t border-gray-200">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Quick Stats</h4>
              <div class="stats-badge rounded-xl p-6 space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Total Products:</span>
                  <span class="font-bold text-primary"><?= number_format($total_products) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Current Page:</span>
                  <span class="font-bold text-gray-900"><?= $page ?> of <?= $total_pages ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Per Page:</span>
                  <span class="font-bold text-gray-900"><?= $per_page ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Categories:</span>
                  <span class="font-bold text-gray-900"><?= count($all_categories) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
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

    // Bubble background animation
    (function() {
      const canvas = document.getElementById('bubble-bg-canvas');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      let width = 0,
        height = 0;

      function resize() {
        width = canvas.width = canvas.offsetWidth;
        height = canvas.height = canvas.offsetHeight;
      }
      window.addEventListener('resize', resize);
      resize();

      // Bubble properties
      const bubbleCount = 18;
      const bubbles = [];
      for (let i = 0; i < bubbleCount; i++) {
        const radius = Math.random() * 32 + 18;
        bubbles.push({
          x: Math.random() * width,
          y: Math.random() * height,
          r: radius,
          dx: (Math.random() - 0.5) * 1.2,
          dy: (Math.random() - 0.5) * 1.2,
          color: [
            'rgba(255,179,71,0.18)', // orange
            'rgba(255,255,255,0.13)', // white
            'rgba(255,204,128,0.15)', // light orange
            'rgba(255,255,255,0.09)', // white
            'rgba(255,179,71,0.12)' // orange
          ][Math.floor(Math.random() * 5)]
        });
      }

      function animate() {
        ctx.clearRect(0, 0, width, height);
        for (let b of bubbles) {
          // Move
          b.x += b.dx;
          b.y += b.dy;

          // Bounce on edges
          if (b.x - b.r < 0) {
            b.x = b.r;
            b.dx *= -1;
          }
          if (b.x + b.r > width) {
            b.x = width - b.r;
            b.dx *= -1;
          }
          if (b.y - b.r < 0) {
            b.y = b.r;
            b.dy *= -1;
          }
          if (b.y + b.r > height) {
            b.y = height - b.r;
            b.dy *= -1;
          }

          // Draw
          ctx.beginPath();
          ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
          ctx.fillStyle = b.color;
          ctx.shadowColor = b.color;
          ctx.shadowBlur = 16;
          ctx.fill();
          ctx.shadowBlur = 0;
        }
        requestAnimationFrame(animate);
      }
      animate();
    })();

    // Mobile Filter Toggle System
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

    // FIXED: Enhanced Rating System
    document.querySelectorAll('.rate-stars').forEach(function(starsContainer) {
      const productId = starsContainer.dataset.productId;
      const stars = starsContainer.querySelectorAll('.star-button');
      const avgRatingElement = starsContainer.querySelector('.avg-rating');

      stars.forEach(function(star, index) {
        star.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          const rating = parseInt(star.dataset.rating);

          // Send rating to server
          fetch('../rate/rate_product.php', {
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
          icon.className = 'fas fa-star text-yellow-400';
        } else {
          icon.className = 'far fa-star text-yellow-400';
        }
      });
    }

    function updateStarsDisplay(stars, average) {
      stars.forEach(function(star, index) {
        const starRating = index + 1;
        const icon = star.querySelector('i');

        if (starRating <= Math.floor(average)) {
          icon.className = 'fas fa-star text-yellow-400';
        } else if (starRating - average < 1) {
          icon.className = 'fas fa-star-half-alt text-yellow-400';
        } else {
          icon.className = 'far fa-star text-yellow-400';
        }
      });
    }

    // Quick Sort Function
    function quickSort(sortValue) {
      const url = new URL(window.location);
      url.searchParams.set('sort', sortValue);
      url.searchParams.set('page', '1'); // Reset to first page
      window.location.href = url.toString();
    }

    // Filter Management Functions
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

    // Clear Filters Button for Mobile
    document.getElementById('clearFilters')?.addEventListener('click', function() {
      clearAllFilters();
    });

    // Enhanced Notification System
    function showNotification(message, type = 'info') {
      // Remove existing notifications
      const existingNotifications = document.querySelectorAll('.notification');
      existingNotifications.forEach(notif => notif.remove());

      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

      // Set notification style based on type
      switch (type) {
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

    // Enhanced Image Loading with Error Handling
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
      img.addEventListener('error', function() {
        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgODBDOTQuNDc3MiA4MCA5MCA4NC40NzcyIDkwIDkwVjExMEM5MCA5NS41MjI4IDk0LjQ3NzIgMTAwIDEwMCAxMDBIMTIwQzEyNS41MjMgMTAwIDEzMCA5NS41MjI4IDEzMCA5MFY4MEMxMzAgNzQuNDc3MiAxMjUuNTIzIDcwIDEyMCA3MEgxMDBDOTQuNDc3MiA3MCA5MCA3NC40NzcyIDkwIDgwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K';
        this.alt = 'Image not available';
      });
    });

    // Smooth Scroll for Pagination
    document.querySelectorAll('a[href*="page="]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        // Small delay to allow page load, then scroll to top
        setTimeout(() => {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        }, 100);
      });
    });

    // Enhanced Search Input
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

    // Keyboard Shortcuts
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

    // Jump to page function
    function jumpToPage(page) {
      const maxPage = <?= $total_pages ?>;
      const pageNum = parseInt(page);

      if (pageNum >= 1 && pageNum <= maxPage) {
        const url = new URL(window.location);
        url.searchParams.set('page', pageNum);
        window.location.href = url.toString();
      } else {
        alert(`Please enter a page number between 1 and ${maxPage}`);
      }
    }

    // Enhanced filter functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-submit on category change
      const categoryCheckboxes = document.querySelectorAll('input[name="category[]"]');
      categoryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          // Optional: Auto-submit on change
          // this.form.submit();
        });
      });

      // Search input with delay
      let searchTimeout;
      const searchInputs = document.querySelectorAll('input[name="search"]');
      searchInputs.forEach(input => {
        input.addEventListener('input', function() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            // Optional: Auto-search after typing stops
            // this.form.submit();
          }, 500);
        });
      });

      // Page transition effects
      const productCards = document.querySelectorAll('.product-card');
      productCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });

    // Clear filters with confirmation
    function clearAllFilters() {
      if (confirm('Are you sure you want to clear all filters?')) {
        window.location.href = window.location.pathname;
      }
    }
  </script>


</body>

</html>