<?php
include ROOT_PATH . '/connection/connect.php';
include ROOT_PATH . '/user/navbar/main-tag-helpers.php';
$total_cart_items = 0;
$user_id = $_SESSION['user_id'] ?? null;

// Get cart items count and data
if ($user_id) {
  // PALITAN NG - COUNT ng products:
  $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_cart_items WHERE user_id = ?");
  $count_stmt->bind_param("i", $user_id);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $count_row = $count_result->fetch_assoc();
  $total_cart_items = $count_row['count'] ?? 0;
  $count_stmt->close();
}

$top_js = '../navbar/js/top-obf.js';
$cart_js = '../navbar/js/topcart.obfuscated.js';

// Fetch new products for sidebar
$newProductsQuery = "
    SELECT 
        p.id,
        p.product_name AS name,
        p.codename,
        p.quantity,
        p.price,
        p.description,
        p.main_image,
        p.category_id,
        COALESCE(c.name, 'Uncategorized') AS category_name,
        p.created_at,
        CASE 
            WHEN p.quantity > 0 THEN 'In Stock'
            WHEN p.quantity = 0 THEN 'Out of Stock'
            ELSE 'Unknown'
        END AS stock_status
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND p.quantity >= 0
    ORDER BY p.created_at DESC
    LIMIT 50
";

$newProductsResult = $conn->query($newProductsQuery);
$newProducts = [];
if ($newProductsResult && $newProductsResult->num_rows > 0) {
  while ($row = $newProductsResult->fetch_assoc()) {
    $newProducts[] = $row;
  }
}

// Handle AJAX request for new products count
if (isset($_GET['action']) && $_GET['action'] === 'get_new_products_count') {
  header('Content-Type: application/json');

  $newProductsCountQuery = "
        SELECT COUNT(*) as count 
        FROM products 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        AND quantity >= 0
    ";

  $result = $conn->query($newProductsCountQuery);
  $count = 0;

  if ($result && $row = $result->fetch_assoc()) {
    $count = (int) $row['count'];
  }

  echo json_encode(['count' => $count]);
  exit;
}

// Navigation data functions
function getNavigationData($conn)
{
  $sql = "SELECT 
        c.id as category_id,
        c.name as category_name,
        c.image_path as category_image_path,
        ps.id as subcategory_id,
        ps.subcategory_name,
        ps.subcategory_slug,
        ps.image_path as subcategory_image_path
    FROM categories c
    LEFT JOIN product_subcategories ps ON c.id = ps.category_id
    ORDER BY c.name, ps.subcategory_name";

  $result = $conn->query($sql);
  $navigation_data = [];

  if (!$result) {
    echo "SQL Error: " . $conn->error;
    return [];
  }

  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $category_slug = generateSlug($row['category_name']);

      if (!isset($navigation_data[$category_slug])) {
        $navigation_data[$category_slug] = [
          'id' => $row['category_id'],
          'name' => $row['category_name'],
          'slug' => $category_slug,
          'image_path' => $row['category_image_path'],
          'subcategories' => []
        ];
      }

      if ($row['subcategory_id']) {
        $sub_slug = !empty($row['subcategory_slug']) ?
          $row['subcategory_slug'] :
          generateSlug($row['subcategory_name']);

        $navigation_data[$category_slug]['subcategories'][] = [
          'id' => $row['subcategory_id'],
          'name' => $row['subcategory_name'],
          'slug' => $sub_slug,
          'image_path' => $row['subcategory_image_path']
        ];
      }
    }
  }

  return $navigation_data;
}

function generateSlug($string)
{
  $slug = strtolower(trim($string));
  $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
  $slug = preg_replace('/[\s-]+/', '-', $slug);
  return trim($slug, '-');
}

$display_categories = getNavigationData($conn);
$current_page = basename($_SERVER['PHP_SELF']);
$hidden_pages = ['help.php', 'about.php'];


$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$uri = preg_replace('#^noble/?#', '', $uri);
$uri = trim($uri, '/');
?>


<link rel="icon" type="image/png" href="<?= BASE_URL ?>/user/img/favicon.png">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
  rel="stylesheet" />

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link href="<?= BASE_URL ?>/output.css" rel="stylesheet">

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
  // Load AOS only if elements need it
  window.addEventListener('load', () => {
    if (document.querySelectorAll('[data-aos]').length > 0) {
      const aoScript = document.createElement('script');
      aoScript.src = 'https://unpkg.com/aos@2.3.4/dist/aos.js';
      aoScript.async = true;
      aoScript.onload = () => {
        if (window.AOS) AOS.init();
      };
      document.body.appendChild(aoScript);
    }

    // Load Lenis for smooth scroll
    const lenisScript = document.createElement('script');
    lenisScript.src = '';
    lenisScript.async = true;
    lenisScript.onload = () => {
      if (window.Lenis) {
        const lenis = new Lenis({
          duration: 3,
          easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
          direction: 'vertical',
          smooth: true
        });
        function raf(time) {
          lenis.raf(time);
          requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);
      }
    };
    document.body.appendChild(lenisScript);
  });
</script>

<style>
  * {
    font-family: 'Plus Jakarta Sans', sans-serif;
  }

  [x-cloak] {
    display: none !important;
  }

  .loading-spinner {
    border: 2px solid #f3f4f6;
    border-top: 2px solid #f97316;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    0% {
      transform: rotate(0deg);
    }

    100% {
      transform: rotate(360deg);
    }
  }

  /* Hide scrollbar by default, show on hover/scroll */
  .scroll-hidden::-webkit-scrollbar {
    height: 4px;
  }

  .scroll-hidden::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.3);
    border-radius: 2px;
  }

  .scroll-hidden {
    scrollbar-width: thin;
    /* Firefox */
    scrollbar-color: rgba(0, 0, 0, 0.3) transparent;
  }

  .scroll-hidden::-webkit-scrollbar {
    visibility: hidden;
  }

  .scroll-hidden:hover::-webkit-scrollbar {
    visibility: visible;
  }

  .scroll-hidden {
    scrollbar-width: none;
    /* Firefox */
    -ms-overflow-style: none;
    /* IE/Edge */
  }

  .scroll-hidden::-webkit-scrollbar {
    display: none;
    /* Chrome/Safari */
  }
</style>

<script>

  // Simple global loading functions
  function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
  }

  function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
  }

  function navigateWithLoading(url) {
    showLoading();

    // Navigate after short delay
    setTimeout(() => {
      window.location.href = url;
    }, 500);

    // Hide loading after 3 seconds as fallback (in case navigation fails)
    setTimeout(() => {
      hideLoading();
    }, 5000);
  }
</script>

<div id="loadingOverlay" style="display: none; z-index: 99999; position: fixed; inset: 0; background: rgba(0,0,0,0.5);"
  class="flex items-center justify-center">

  <!-- Truck Loader -->
  <div class="loader">
    <div class="truckWrapper">
      <div class="truckBody">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 198 93" class="trucksvg">
          <path stroke-width="3" stroke="#282828" fill="#F83D3D"
            d="M135 22.5H177.264C178.295 22.5 179.22 23.133 179.594 24.0939L192.33 56.8443C192.442 57.1332 192.5 57.4404 192.5 57.7504V89C192.5 90.3807 191.381 91.5 190 91.5H135C133.619 91.5 132.5 90.3807 132.5 89V25C132.5 23.6193 133.619 22.5 135 22.5Z">
          </path>
          <path stroke-width="3" stroke="#282828" fill="#7D7C7C"
            d="M146 33.5H181.741C182.779 33.5 183.709 34.1415 184.078 35.112L190.538 52.112C191.16 53.748 189.951 55.5 188.201 55.5H146C144.619 55.5 143.5 54.3807 143.5 53V36C143.5 34.6193 144.619 33.5 146 33.5Z">
          </path>
          <path stroke-width="2" stroke="#282828" fill="#282828"
            d="M150 65C150 65.39 149.763 65.8656 149.127 66.2893C148.499 66.7083 147.573 67 146.5 67C145.427 67 144.501 66.7083 143.873 66.2893C143.237 65.8656 143 65.39 143 65C143 64.61 143.237 64.1344 143.873 63.7107C144.501 63.2917 145.427 63 146.5 63C147.573 63 148.499 63.2917 149.127 63.7107C149.763 64.1344 150 64.61 150 65Z">
          </path>
          <rect stroke-width="2" stroke="#282828" fill="#FFFCAB" rx="1" height="7" width="5" y="63" x="187"></rect>
          <rect stroke-width="2" stroke="#282828" fill="#282828" rx="1" height="11" width="4" y="81" x="193"></rect>
          <rect stroke-width="3" stroke="#282828" fill="#DFDFDF" rx="2.5" height="90" width="121" y="1.5" x="6.5">
          </rect>
          <rect stroke-width="2" stroke="#282828" fill="#DFDFDF" rx="2" height="4" width="6" y="84" x="1"></rect>
        </svg>
      </div>

      <div class="truckTires">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg">
          <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
          <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg">
          <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
          <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
        </svg>
      </div>

      <div class="road"></div>

      <svg xml:space="preserve" viewBox="0 0 453.459 453.459" xmlns:xlink="http://www.w3.org/1999/xlink"
        xmlns="http://www.w3.org/2000/svg" version="1.1" class="lampPost">
        <path
          d="M252.882,0c-37.781,0-68.686,29.953-70.245,67.358h-6.917v8.954c-26.109,2.163-45.463,10.011-45.463,19.366h9.993">
        </path>
      </svg>
    </div>
  </div>
</div>



<nav x-data="{ 
    mobileOpen: false, 
    loginOpen: false, 
    registerOpen: false,
    productsOpen: false,
    profileOpen: false,
    selectedCategory: null,
    newProductsModal: false,
    newProductsSidebarMobile: false
}" class="bg-white shadow-lg sticky top-0 z-50 text-black"
  style="font-family: 'Montserrat', sans-serif; color: #2f1200;">

  <div class="max-w-full mx-auto px-3 sm:px-4 lg:px-8">
    <div class="flex justify-between items-center py-4 px-3 sm:px-4 ">
      <div class="flex items-center space-x-6 sm:space-x-6 flex-1">

        <!-- Logo -->
        <a href="<?= BASE_URL ?>/" onclick="navigateWithLoading('<?= BASE_URL ?>/')"
          class="flex items-center space-x-2 sm:space-x-3 hover:opacity-80 transition duration-200 shrink-0">
          <div class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 overflow-hidden">
            <img src="<?= BASE_URL ?>/user/img/logo.png" alt="Noble Home Logo" class="w-full h-full object-contain">
          </div>

        </a>

        <a href="<?= BASE_URL ?>/15" onclick="navigateWithLoading('<?= BASE_URL ?>/15')"
          class="hidden xl:block text-lg text-black font-semibold hover:bg-gray-100 rounded-lg px-2 py-1">
          Find Professional
        </a>

        <a href="<?= BASE_URL ?>/18" onclick="navigateWithLoading('<?= BASE_URL ?>/18')"
          class="hidden xl:block text-lg text-black font-semibold hover:bg-gray-100 rounded-lg px-2 py-1">
          Inspiration
        </a>

        <!-- Shop Link with Three-Level Navigation (Updated for JSON Support) -->
        <?php
        // MariaDB-compatible query that handles JSON arrays in subcategory_name and sub_subcategory_ids
        $nav_query = "
  SELECT 
    c.id as category_id,
    c.name as category_name,
    c.image_path as category_image,
    ps.id as subcategory_id,
    ps.subcategory_name,
    ps.subcategory_slug,
    c.tag as category_tag,
ps.tag as subcategory_tag,
pss.tag as sub_subcategory_tag,
    ps.image_path as sub_image_path,
    pss.id as sub_subcategory_id,
    pss.sub_subcategory_name,
    pss.sub_subcategory_slug,
    pss.image_path as sub_sub_image_path,
    -- Count DISTINCT PRODUCTS with this category
    (SELECT COUNT(DISTINCT pv.product_id) 
     FROM product_variants pv 
     WHERE pv.category_id = c.id
    ) as category_product_count,
    -- Count distinct products with this subcategory (supports JSON arrays like [\"AAC block\",\"wall\"])
    (SELECT COUNT(DISTINCT pv.product_id)
     FROM product_variants pv
     WHERE pv.category_id = c.id
     AND (
       pv.subcategory_name LIKE CONCAT('%\"', ps.subcategory_name, '\"%')
       OR pv.subcategory_name LIKE CONCAT('%', ps.subcategory_name, '%')
       OR pv.subcategory_name = ps.subcategory_name
     )
    ) as subcategory_product_count,
    -- Count distinct products with this sub-subcategory (supports JSON arrays like [4,5,3,1,2])
    (SELECT COUNT(DISTINCT pv.product_id)
     FROM product_variants pv
     WHERE pv.category_id = c.id
     AND (
       pv.sub_subcategory_ids LIKE CONCAT('%\"', pss.id, '\"%')
       OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ',%')
       OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ',%')
       OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ']')
       OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ']')
       OR pv.sub_subcategory_id = pss.id
     )
    ) as sub_subcategory_product_count
  FROM categories c
  LEFT JOIN product_subcategories ps ON c.id = ps.category_id
  LEFT JOIN product_sub_subcategories pss ON ps.id = pss.subcategory_id
  WHERE c.id IS NOT NULL
  GROUP BY c.id, ps.id, pss.id
  ORDER BY c.name, ps.subcategory_name, pss.sub_subcategory_name
";


        $nav_result = $conn->query($nav_query);

        if (!$nav_result) {
          die("Query Error: " . $conn->error);
        }

        $nav_categories = [];

        while ($row = $nav_result->fetch_assoc()) {
          $cat_id = $row['category_id'];
          $sub_id = $row['subcategory_id'];

          if (!isset($nav_categories[$cat_id])) {
            $nav_categories[$cat_id] = [
              'id' => $row['category_id'],
              'name' => $row['category_name'],
              'image_path' => $row['category_image'],
              'product_count' => (int) $row['category_product_count'],
              'tag' => $row['category_tag'] ?? 'normal',
              'subcategories' => []
            ];
          }

          if ($sub_id && !isset($nav_categories[$cat_id]['subcategories'][$sub_id])) {
            $nav_categories[$cat_id]['subcategories'][$sub_id] = [
              'id' => $row['subcategory_id'],
              'name' => $row['subcategory_name'],
              'slug' => $row['subcategory_slug'],
              'image_path' => $row['sub_image_path'],
              'product_count' => (int) $row['subcategory_product_count'],
              'tag' => $row['subcategory_tag'] ?? 'normal',
              'sub_subcategories' => []
            ];
          }

          if ($row['sub_subcategory_id']) {
            $nav_categories[$cat_id]['subcategories'][$sub_id]['sub_subcategories'][] = [
              'id' => $row['sub_subcategory_id'],
              'name' => $row['sub_subcategory_name'],
              'slug' => $row['sub_subcategory_slug'],
              'image_path' => $row['sub_sub_image_path'],
              'tag' => $row['sub_subcategory_tag'] ?? 'normal',
              'parent_slug' => $row['subcategory_slug'],
              'product_count' => (int) $row['sub_subcategory_product_count']
            ];
          }
        }
        ?>

        <div x-data="{ 
  productsOpen: false, 
  selectedCategory: null, 
  selectedSubcategory: null,
  hoverTimeout: null,
  searchTerm: '',
  isSearching: false
}" class="hidden xl:inline-flex items-center">

          <a href="javascript:void(0)" onclick="navigateWithLoading('<?= BASE_URL ?>/shop')" @mouseenter="
    clearTimeout(hoverTimeout);
    hoverTimeout = setTimeout(() => { productsOpen = true; }, 150);
  " @mouseleave="
    clearTimeout(hoverTimeout);
    hoverTimeout = setTimeout(() => { 
      productsOpen = false; 
      selectedCategory = null; 
      selectedSubcategory = null;
      searchTerm = '';
    }, 200);
  " class="hover:bg-gray-100 rounded-lg px-2 py-1 font-semibold flex items-center gap-2 <?= basename($_SERVER['PHP_SELF']) == 'index-shop-page-2.php' ? 'text-orange-600 underline ' : 'text-black' ?> hover:text-orange-500 transition text-lg relative">

            Products
            <svg class="w-4 h-4 transition-transform" :class="productsOpen ? 'rotate-180' : ''" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </a>

          <!-- Overlay -->
          <div x-show="productsOpen" @click="productsOpen = false" class="fixed inset-0 z-40"
            style="top: 80px; pointer-events: none; background: transparent;" x-cloak>
          </div>

          <!-- Dropdown Menu - Three Columns with Search -->
          <div x-show="productsOpen" @mouseenter="clearTimeout(hoverTimeout); productsOpen = true;" @mouseleave="hoverTimeout = setTimeout(() => { 
      productsOpen = false; 
      selectedCategory = null; 
      selectedSubcategory = null;
      searchTerm = '';
    }, 200);" x-transition x-cloak
            class="fixed left-[150px] right-0 bg-white shadow-lg z-50 border border-gray-200 max-w-6xl mx-auto"
            style="top: 80px; max-height: 600px;" @click.outside="productsOpen = false"
            @keydown.escape="productsOpen = false">


            <div class="h-full flex" style="max-height: 550px;">

              <!-- COLUMN 1: Categories -->
              <div class="w-1/3 border-r border-gray-200 p-2 bg-gray-50 overflow-y-scroll scrollbar-thin"
                style="max-height: 550px;" @wheel.stop>

                <div class="flex items-center justify-between mb-2 px-1 sticky top-0 bg-gray-50 pb-1 z-10">
                  <h3 class="text-xs uppercase">
                    Categories</h3>
                  <a href="<?= BASE_URL ?>/shop" class="text-[11px] hover:text-orange-600 font-medium transition-all">
                    View All →
                  </a>
                </div>

                <div class="space-y-0.5">
                  <?php if (!empty($nav_categories)): ?>
                    <?php foreach ($nav_categories as $category): ?>
                      <button
                        x-show="searchTerm === '' || '<?= strtolower($category['name']) ?>'.includes(searchTerm.toLowerCase())"
                        @click="
                  selectedCategory = 'cat_<?= $category['id'] ?>';
                  selectedSubcategory = null;
                  $nextTick(() => {
                    if ($refs.subcategoryPanel) {
                      $refs.subcategoryPanel.scrollTop = 0;
                    }
                  });
                " class="w-full p-1.5 rounded hover:bg-white text-left transition-all duration-200 group flex items-center gap-1.5"
                        :class="selectedCategory === 'cat_<?= $category['id'] ?>' ? 'bg-white border-l-2 border-orange-500 shadow-sm' : ''">

                        <?php if (!empty($category['image_path'])): ?>
                          <div class="shrink-0">
                            <img src="<?= BASE_URL ?>/uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                              alt="<?= htmlspecialchars($category['name']) ?>" class="w-8 h-8 object-contain rounded"
                              loading="lazy" onerror="this.style.display='none'">
                          </div>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                          <div class=" text-sm group-hover:text-orange-500 truncate uppercase"
                            :class="selectedCategory === 'cat_<?= $category['id'] ?>' ? 'text-orange-500 font-medium' : 'text-gray-800'">
                            <?= htmlspecialchars($category['name']) ?>
                          </div>
                          <?= nav_tag_badge($category['tag'], $conn) ?>
                        </div>

                        <svg class="w-3 h-3 shrink-0 transition-colors"
                          :class="selectedCategory === 'cat_<?= $category['id'] ?>' ? 'text-orange-500' : 'text-gray-400'"
                          fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="text-center py-4">
                      <p class="text-gray-500 italic text-xs">No categories available</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- COLUMN 2: Subcategories -->
              <div class="w-1/3 border-r border-gray-200 p-2 bg-gray-50 overflow-y-scroll scrollbar-thin"
                style="max-height: 550px;" @wheel.stop x-ref="subcategoryPanel">

                <!-- Default State -->
                <div x-show="!selectedCategory && searchTerm === ''" class="flex items-center justify-center py-12">
                  <div class="text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <p class="text-gray-500 text-xs">Select a category</p>
                  </div>
                </div>

                <!-- Subcategories List -->
                <?php if (!empty($nav_categories)): ?>
                  <?php foreach ($nav_categories as $category): ?>
                    <div x-show="selectedCategory === 'cat_<?= $category['id'] ?>'"
                      x-transition:enter="transition ease-out duration-200"
                      x-transition:enter-start="opacity-0 translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
                      class="space-y-0.5" x-cloak>

                      <div class="mb-2 sticky top-0 bg-gray-50 pb-1 z-10">
                        <h4 class="text-xs uppercase font-medium">
                          <?= htmlspecialchars($category['name']) ?> Types
                        </h4>
                      </div>

                      <?php if (!empty($category['subcategories'])): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                          <button
                            x-show="searchTerm === '' || '<?= strtolower($sub['name']) ?>'.includes(searchTerm.toLowerCase())"
                            @click="
                      selectedSubcategory = 'sub_<?= $sub['id'] ?>';
                      $nextTick(() => {
                        if ($refs.subSubPanel) {
                          $refs.subSubPanel.scrollTop = 0;
                        }
                      });
                    "
                            class="w-full flex items-center gap-2 p-1.5 rounded hover:bg-white transition-all duration-200 group text-left"
                            :class="selectedSubcategory === 'sub_<?= $sub['id'] ?>' ? 'bg-white border-l-2 border-orange-500 shadow-sm' : ''">

                            <?php if (!empty($sub['image_path'])): ?>
                              <div class="shrink-0">
                                <img
                                  src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                  alt="<?= htmlspecialchars($sub['name']) ?>" class="w-9 h-9 object-cover rounded" loading="lazy"
                                  onerror="this.style.display='none'">
                              </div>
                            <?php endif; ?>

                            <div class="flex-1 min-w-0">
                              <div class="text-sm group-hover:text-orange-500 transition truncate uppercase"
                                :class="selectedSubcategory === 'sub_<?= $sub['id'] ?>' ? 'text-orange-500 font-medium' : 'text-gray-800'">
                                <?= htmlspecialchars($sub['name']) ?>
                              </div>
                              <?= nav_tag_badge($category['tag'], $conn) ?>
                            </div>

                            <svg class="w-3 h-3 text-gray-400 group-hover:text-orange-500 transition shrink-0"
                              :class="selectedSubcategory === 'sub_<?= $sub['id'] ?>' ? 'text-orange-500' : ''" fill="none"
                              stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                          </button>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="text-center py-4">
                          <p class="text-gray-500 italic text-xs">No products available</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- COLUMN 3: Sub-Subcategories -->
              <div class="w-1/3 p-2 bg-white overflow-y-scroll scrollbar-thin" style="max-height: 550px;" @wheel.stop
                x-ref="subSubPanel">

                <!-- Default State -->
                <div x-show="!selectedSubcategory && searchTerm === ''" class="flex items-center justify-center py-12">
                  <div class="text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="text-gray-500 text-xs">Select a product type</p>
                  </div>
                </div>

                <!-- Sub-Subcategories List -->
                <?php if (!empty($nav_categories)): ?>
                  <?php foreach ($nav_categories as $category): ?>
                    <?php if (!empty($category['subcategories'])): ?>
                      <?php foreach ($category['subcategories'] as $sub): ?>
                        <div x-show="selectedSubcategory === 'sub_<?= $sub['id'] ?>'"
                          x-transition:enter="transition ease-out duration-200"
                          x-transition:enter-start="opacity-0 translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
                          class="space-y-0.5" x-cloak>

                          <div class="mb-2 sticky top-0 bg-white pb-1 z-10">
                            <h4 class="text-xs uppercase font-medium">
                              <?= htmlspecialchars($sub['name']) ?> Collections
                            </h4>
                          </div>

                          <?php if (!empty($sub['sub_subcategories'])): ?>
                            <?php foreach ($sub['sub_subcategories'] as $subsub): ?>
                              <a href="<?= BASE_URL ?>/productsubviews?sub_subcategory_id=<?= $subsub['id'] ?>"
                                x-show="searchTerm === '' || '<?= strtolower($subsub['name']) ?>'.includes(searchTerm.toLowerCase())"
                                class="flex items-center gap-2 p-1.5 rounded hover:bg-purple-50 transition-all duration-200 group cursor-pointer border border-transparent hover:border-orange-200">

                                <?php if (!empty($subsub['image_path'])): ?>
                                  <div class="shrink-0">
                                    <img
                                      src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($subsub['parent_slug']) ?>/<?= htmlspecialchars($subsub['slug']) ?>/<?= htmlspecialchars($subsub['image_path']) ?>"
                                      alt="<?= htmlspecialchars($subsub['name']) ?>" class="w-10 h-10 object-cover rounded"
                                      loading="lazy" onerror="this.style.display='none'">
                                  </div>
                                <?php endif; ?>

                                <div class="flex-1 min-w-0">
                                  <div
                                    class="text-sm group-hover:text-orange-600 transition font-medium text-gray-800 truncate uppercase">
                                    <?= htmlspecialchars($subsub['name']) ?>
                                  </div>
                                  <?= nav_tag_badge($subsub['tag'], $conn) ?>
                                </div>

                                <svg class="w-3 h-3 text-gray-400 group-hover:text-orange-600 transition shrink-0" fill="none"
                                  stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                              </a>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="text-center py-4">
                              <p class="text-gray-500 italic text-xs">No collections available</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <style>
          [x-cloak] {
            display: none !important;
          }

          .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
          }

          .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
          }

          .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
          }

          .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #555;
          }

          .overflow-y-scroll {
            overflow-y: scroll !important;
            -webkit-overflow-scrolling: touch;
          }
        </style>

      </div>


      <!-- Mobile Cart & User Icons (visible on mobile before hamburger) -->
      <div class="flex items-center space-x-3 lg:hidden">

        <!-- Mobile Cart Icon -->
        <a href="javascript:void(0)" onclick="navigateWithLoading('<?= BASE_URL ?>/cartview')"
          class="relative p-1 hover:bg-gray-100 rounded-full transition">
          <i class="fa-solid fa-cart-plus text-black"></i>
          <span
            class="cart-count absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full font-bold leading-none <?= $total_cart_items > 0 ? '' : 'hidden' ?>">
          </span>
        </a>

        <!-- Mobile User Icon -->
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="relative">
            <button @click="profileOpen = !profileOpen" class="flex items-center focus:outline-none p-1">
              <div class="w-7 h-7 rounded-full overflow-hidden border border-gray-300 bg-gray-100">
                <?php if (!empty($_SESSION['user_picture'])): ?>
                  <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>" alt="Profile"
                    class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-orange-100">
                    <span class="text-xs font-bold text-orange-800 font-mont">
                      <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
            </button>

            <!-- Mobile Profile Dropdown -->
            <div x-show="profileOpen" x-cloak @click.outside="profileOpen = false" x-transition
              class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-md shadow-lg z-50">
              <div class="py-2 px-3 text-sm text-gray-700 border-b">
                <span class="block truncate"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
              </div>
              <a href="<?= BASE_URL ?>/profile"
                class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                Profile
              </a>
              <a href="<?= BASE_URL ?>/logout"
                class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <button @click="loginOpen = true" class="p-2 hover:bg-gray-100 rounded-full transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        <?php endif; ?>

        <!-- Mobile Hamburger -->
        <button @click="mobileOpen = !mobileOpen"
          class="text-gray-700 focus:outline-none p-2 hover:bg-gray-100 rounded-lg transition">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              :d="mobileOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'" />
          </svg>
        </button>
      </div>

      <!-- Desktop Navigation -->
      <div class="hidden lg:flex space-x-6 items-center">

        <?php if (count($newProducts) > 0): ?>
          <button @click="newProductsModal = true"
            class="hidden xl:flex relative text-black hover:text-orange-500 transition font-semibold uppercase text-sm group">
            <i class="fa-solid fa-box-open text-xl"></i>
            <span
              class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full px-1.5 py-0.5 text-[10px] leading-none min-w-[18px] text-center">
              <?php echo count($newProducts); ?>
            </span>

            <!-- Tooltip -->
            <span class="absolute top-full left-1/2 -translate-x-1/2 mt-2 
                 bg-gray-900 text-white text-xs font-medium uppercase tracking-wide
                 px-2.5 py-1 rounded whitespace-nowrap
                 opacity-0 group-hover:opacity-100 transition-opacity duration-150
                 pointer-events-none z-50">
              New Products
              <!-- Arrow -->
              <span class="absolute bottom-full left-1/2 -translate-x-1/2 
                   border-4 border-transparent border-b-gray-900"></span>
            </span>
          </button>
        <?php endif; ?>
        <!-- MORE DROPDOWN - Show on medium screens when space is tight -->
        <div x-data="{ moreOpen: false, searchModalOpen: false }" class="relative xl:hidden">
          <button @click="moreOpen = !moreOpen"
            class="text-black hover:text-orange-500 transition font-mont text-sm flex items-center gap-1 relative"> <svg
              class="w-4 h-4 transition-transform" :class="moreOpen ? 'rotate-180' : ''" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
            More
            <?php if (count($newProducts) > 0): ?>
              <span
                class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full px-1.5 py-0.5 text-[8px] leading-none min-w-[16px] text-center">
                <?php echo count($newProducts); ?>
              </span>
            <?php endif; ?>

          </button>

          <!-- Dropdown Menu -->
          <div x-show="moreOpen" x-cloak @click.away="moreOpen = false"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
            class="absolute right-0 mt-2 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50">

            <!-- Search in Dropdown -->
            <button @click="searchModalOpen = true; moreOpen = false"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition border-b border-gray-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              Search Products
            </button>

            <!-- New Products in Dropdown -->
            <?php if (count($newProducts) > 0): ?>
              <button @click="newProductsModal = true; moreOpen = false"
                class="flex items-center justify-between w-full px-4 py-3 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition border-b border-gray-100">
                <div class="flex items-center gap-3 ">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                  </svg>
                  New Products
                </div>
                <span class="bg-red-600 text-white rounded-full px-1.5 py-0.5 text-[10px] font-bold">
                  <?php echo count($newProducts); ?>
                </span>
              </button>
            <?php endif; ?>


            <!-- Inspiration -->
            <a href="<?= BASE_URL ?>/inspiration"
              class="flex items-center gap-3 px-4 py-3 text-md text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition border-b border-gray-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
              Inspiration
            </a>

            <!-- Find Professionals -->
            <a href="<?= BASE_URL ?>/findprofessional"
              class="flex items-center gap-3 px-4 py-3 text-md text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Find Professionals
            </a>
          </div>

          <!-- Search Modal (triggered from More dropdown) -->
          <div x-show="searchModalOpen" x-cloak @click.self="searchModalOpen = false"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-9999 bg-black bg-opacity-50 flex items-start justify-center p-2 pt-20">

            <!-- Modal Content -->
            <div x-show="searchModalOpen" x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
              x-transition:enter-end="opacity-100 scale-100 translate-y-0"
              x-transition:leave="transition ease-in duration-200"
              x-transition:leave-start="opacity-100 scale-100 translate-y-0"
              x-transition:leave-end="opacity-0 scale-95 -translate-y-4" @click.stop x-data="{
        search: '',
        results: [],
        isLoading: false,
        fetchResults() {
          if (this.search.trim().length < 1) {
            this.results = [];
            return;
          }
          this.isLoading = true;
          fetch(`<?= BASE_URL ?>/search?search=${encodeURIComponent(this.search)}`)
            .then(res => res.json())
            .then(data => {
              this.results = data;
              this.isLoading = false;
            })
            .catch(() => {
              this.results = [];
              this.isLoading = false;
            });
        }
       }" x-init="$nextTick(() => $refs.searchInput && $refs.searchInput.focus())"
              class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">

              <!-- Modal Header -->
              <div class="flex justify-between items-center p-2 sm:p-3 border-b bg-black text-white shrink-0">
                <div class="flex items-center gap-3">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <h2 class="text-lg sm:text-xl ">Search Products</h2>
                </div>
                <button @click="searchModalOpen = false"
                  class="text-white hover:text-orange-200 text-2xl sm:text-3xl w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-full hover:bg-white/20 transition-all">
                  ×
                </button>
              </div>

              <!-- Search Input Section -->
              <div class="p-2 border-b bg-gray-50 shrink-0">
                <div
                  class="flex items-center gap-2 bg-white border border-gray-300 rounded-full px-3 py-2 focus-within:border-orange-500 transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                  </svg>

                  <input type="text" x-ref="searchInput" x-model="search" @input.debounce.300ms="fetchResults"
                    @keydown.escape="searchModalOpen = false" placeholder="Search products..."
                    class="flex-1 bg-transparent focus:outline-none text-sm text-gray-700 placeholder-gray-400"
                    autocomplete="off">

                  <!-- Clear Button -->
                  <button x-show="search.length > 0" @click="search = ''; results = []; $refs.searchInput.focus()"
                    class="text-gray-400 hover:text-gray-600 transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>

                  <!-- Search Button -->
                  <button @click="fetchResults"
                    class="bg-black hover:bg-orange-600 text-white px-4 py-1.5 rounded-full text-xs font-medium transition-all shrink-0">
                    Search
                  </button>
                </div>

                <!-- Search Tips -->
                <div x-show="search.length === 0 && results.length === 0" class="mt-4">
                  <p class="text-xs text-gray-500 mb-2">Search Tips:</p>
                  <div class="flex flex-wrap gap-2">
                    <span class="text-xs bg-white px-3 py-1 rounded-full text-gray-600 border">Try "paint"</span>
                    <span class="text-xs bg-white px-3 py-1 rounded-full text-gray-600 border">Try "tiles"</span>
                    <span class="text-xs bg-white px-3 py-1 rounded-full text-gray-600 border">Try "table"</span>
                  </div>
                </div>
              </div>

              <!-- Results Section -->
              <div class="flex-1 overflow-y-auto p-4 sm:p-6" style="-webkit-overflow-scrolling: touch;">
                <!-- Loading State -->
                <div x-show="isLoading" class="flex flex-col items-center justify-center py-12">
                  <div class="loading-spinner mb-4"></div>
                  <p class="text-sm text-gray-500">Searching...</p>
                </div>

                <!-- Results -->
                <div x-show="!isLoading && results.length > 0" class="space-y-2">
                  <p class="text-sm text-gray-500 mb-4">
                    Found <span x-text="results.length"></span> result(s)
                  </p>
                  <template x-for="item in results" :key="item.id">
                    <a :href="'<?= BASE_URL ?>/shop?search=' + encodeURIComponent(item.product_name)"
                      class="flex items-center gap-4 p-3 hover:bg-orange-50 rounded-lg transition-all border border-transparent hover:border-orange-200 group">
                      <div class="shrink-0">
                        <img :src="item.main_image" alt=""
                          class="w-16 h-16 object-contain rounded-lg border border-gray-200 group-hover:border-orange-300 transition">
                      </div>
                      <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-gray-900 group-hover:text-orange-600 transition truncate"
                          x-text="item.product_name"></h3>
                        <p class="text-xs text-gray-500 mt-1" x-text="item.category_name || 'Product'"></p>
                      </div>
                      <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-500 transition shrink-0" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                    </a>
                  </template>
                </div>

                <!-- No Results -->
                <div x-show="!isLoading && search.length > 0 && results.length === 0"
                  class="flex flex-col items-center justify-center py-12">
                  <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <p class="text-gray-500 font-medium mb-2">No products found</p>
                  <p class="text-sm text-gray-400">Try different keywords</p>
                </div>

                <!-- Empty State -->
                <div x-show="!isLoading && search.length === 0" class="flex flex-col items-center justify-center py-12">
                  <svg class="w-20 h-20 text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <p class="text-gray-500 font-medium mb-2">Start typing to search</p>
                  <p class="text-sm text-gray-400 text-center">Search for products, categories</p>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="p-4 bg-gray-50 border-t shrink-0">
                <div class="flex items-center justify-between text-xs text-gray-500">
                  <span>Press <kbd class="px-2 py-1 bg-white border rounded">ESC</kbd> to close</span>
                  <span x-show="results.length > 0">
                    <span x-text="results.length"></span> results
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- Search Bar with History - With localStorage -->
        <div x-data="{
  search: '',
  results: [],
  searchHistory: [],
  showHistory: false,
  showDropdown: false,
  init() {
      // Load from localStorage on init
      const saved = localStorage.getItem('searchHistory');
      if (saved) {
          this.searchHistory = JSON.parse(saved);
      }
  },
  fetchResults() {
      if (this.search.trim().length < 1) {
          this.results = [];
          this.showDropdown = false;
          return;
      }
      fetch(`<?= BASE_URL ?>/search?search=${encodeURIComponent(this.search)}`)
          .then(res => res.json())
          .then(data => {
              this.results = data;
              this.showDropdown = data.length > 0;
          })
          .catch(() => {
              this.results = [];
              this.showDropdown = false;
          });
  },
  saveSearch(query) {
      if (!query.trim()) return;
      this.searchHistory = [query, ...this.searchHistory.filter(h => h !== query)].slice(0, 10);
      // Save to localStorage
      localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
  },
  clearHistory() {
      this.searchHistory = [];
      localStorage.removeItem('searchHistory');
  },
  removeHistoryItem(query) {
      this.searchHistory = this.searchHistory.filter(h => h !== query);
      localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
  },
  performSearch(query) {
      if (!query.trim()) return;
      this.search = query;
      this.saveSearch(query);
      this.showHistory = false;
      this.showDropdown = false;
      window.location.href = 'index-shop-page-2.php?search=' + encodeURIComponent(query);
  }
 }" @click.away="showHistory = false; showDropdown = false"
          class="relative w-64 md:w-96 font-mont hidden xl:block flex-1 max-w-2xl">
          <div class="flex items-center w-full border border-gray-300 rounded overflow-hidden shadow-sm bg-white p-1">
            <!-- Search Icon -->
            <div class="pl-3 pr-2 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
              </svg>
            </div>

            <!-- Input -->
            <input type="text" x-model="search" @input.debounce.300ms="fetchResults"
              @focus="showHistory = true; if(search.trim().length >= 1) fetchResults()"
              @keydown.enter="performSearch(search)" placeholder="Search for products..."
              class="flex-1 py-2 text-sm text-gray-700 placeholder-gray-400 bg-transparent focus:outline-none"
              autocomplete="off">

            <!-- Search Button -->
            <button @click="performSearch(search)"
              class="px-6 py-2 text-white text-sm font-medium transition rounded-sm" style="background-color: #f97316;">
              Search
            </button>
          </div>

          <!-- Search History Dropdown -->
          <div x-show="showHistory && searchHistory.length > 0 && !showDropdown && search.trim().length === 0" x-cloak
            class="absolute z-50 bg-white shadow-lg rounded mt-2 w-full border border-gray-200">

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200">
              <span class="text-xs text-gray-500 font-medium">Search History</span>
              <button @click.stop="clearHistory()"
                class="text-xs text-orange-500 hover:text-orange-600 font-medium uppercase">
                Clear
              </button>
            </div>

            <!-- History Items -->
            <ul class="max-h-60 overflow-y-auto">
              <template x-for="(item, index) in searchHistory" :key="index">
                <li class="group hover:bg-gray-50 transition">
                  <div class="flex items-center justify-between px-4 py-2.5">
                    <button @click.stop="performSearch(item)" type="button"
                      class="flex items-center gap-2 flex-1 text-sm text-gray-700 text-left">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span x-text="item"></span>
                    </button>
                    <button @click.stop="removeHistoryItem(item)" type="button"
                      class="opacity-0 group-hover:opacity-100 transition p-1 hover:bg-gray-200 rounded">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </li>
              </template>
            </ul>
          </div>

          <!-- Search Results Dropdown -->
          <div x-show="showDropdown && results.length > 0" x-cloak
            class="absolute z-50 bg-white shadow-lg rounded mt-2 w-full max-h-80 overflow-y-auto border border-gray-200">
            <ul>
              <template x-for="item in results" :key="item.id">
                <li class="border-b last:border-0">
                  <a :href="'<?= BASE_URL ?>/shop?search=' + encodeURIComponent(item.product_name)"
                    @click="saveSearch(item.product_name)"
                    class="flex items-center gap-3 px-4 py-2 hover:bg-orange-100 text-sm text-gray-700">
                    <img :src="'<?= BASE_URL ?>/user/uploads/' + item.main_image" alt=""
                      class="w-10 h-10 object-contain rounded border border-gray-300">
                    <span x-text="item.product_name"></span>
                  </a>
                </li>
              </template>
            </ul>
          </div>
        </div>

        <!-- Desktop cart icon -->

        <?php include ROOT_PATH . '/user/navbar/cart-sidebar.php'; ?>

        <a href="javascript:void(0)" onclick="openCartSidebar()" id="cartNavIcon"
          class="relative group flex items-center justify-center w-9 h-9 rounded-lg transition text-black hover:bg-orange-50 hover:text-orange-500">
          <i class="fa-solid fa-cart-shopping text-[18px]"></i>

          <!-- Red dot badge -->
          <span id="cartNavBadge" style="display:none;"
            class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white">
          </span>

          <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
            Cart
          </span>
        </a>

        <a href="javascript:void(0)" onclick="navigateWithLoading('<?= BASE_URL ?>/order')"
          class="p-2 rounded-lg hover:bg-orange-50 <?= strpos($_SERVER['REQUEST_URI'], 'index-profile-page-6') !== false ? 'text-orange-600 underline ' : 'text-black' ?> hover:text-orange-500 transition text-md flex items-center gap-3 relative group">
          <i class="fa-solid fa-clipboard"></i>
          <!-- Tooltip -->
          <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
            Orders
          </span>
        </a>

        <div class="flex items-center gap-2" x-data="notificationSystem" x-init="init()">
          <!-- Notifications -->
          <div class="relative">
            <div class="relative group">
              <button @click="notifOpen = !notifOpen; if (notifOpen) markAsRead()"
                class="relative text-gray-600 hover:text-orange-500 mt-1" aria-label="Toggle notifications dropdown">
                <i class="fas fa-bell text-xl"></i>
                <template x-if="unreadCount > 0">
                  <span
                    class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 flex items-center justify-center rounded-full"
                    x-text="unreadCount">
                  </span>
                </template>
              </button>

              <!-- Tooltip -->
              <span
                class="absolute top-full mt-2 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
                Notifications
              </span>
            </div>

            <!-- Notification Dropdown -->
            <div x-show="notifOpen" x-cloak x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 translate-y-1" @click.outside="notifOpen = false"
              class="absolute right-0 mt-2 w-72 bg-white rounded-lg border border-gray-400 shadow-lg z-50">
              <div class="flex justify-between items-center p-3 border-b"
                style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                <span>Notifications</span>
                <button class="text-sm text-red-500 hover:text-red-700" @click.prevent="clearNotifications()"
                  aria-label="Clear all notifications">
                  Clear All
                </button>
              </div>
              <ul class="max-h-60 overflow-y-auto">
                <template x-for="notif in notifications" :key="notif.id">
                  <li class="p-3 hover:bg-gray-50 cursor-pointer">
                    <p class="text-sm text-black text-light" x-text="notif.message"></p>
                    <span class="text-xs text-gray-400" x-text="formatDateTime(notif.created_at)"></span>
                  </li>
                </template>
                <template x-if="notifications.length === 0">
                  <li class="p-3 text-sm text-gray-500">
                    No new notifications.
                  </li>
                </template>
              </ul>
            </div>
          </div>

        </div>

        <!-- User Authentication -->
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="relative">
            <div class="relative group">
              <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 focus:outline-none">
                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100">
                  <?php if (!empty($_SESSION['user_picture'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>" alt="Profile"
                      class="w-full h-full object-contain">
                  <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-orange-100">
                      <span class="text-xs font-bold text-orange-800 font-mont">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </div>
              </button>

              <!-- Tooltip -->
              <div
                class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-3 py-1.5 bg-gray-800 text-white text-xs font-mont rounded-md whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
                Profile
                <!-- Arrow sa taas -->
                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45"></div>
              </div>
            </div>

            <!-- ADD THIS: Backdrop overlay -->
            <div x-show="profileOpen" @click="profileOpen = false" x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
              x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
              x-transition:leave-end="opacity-0" x-cloak class="fixed inset-0 bg-black/40 z-40" style="top: 80px;">
            </div>


            <div x-show="profileOpen" x-cloak @click.outside="profileOpen = false" x-transition
              class="absolute right-0 mt-2 w-45 bg-white border border-gray-200 rounded-md shadow-lg z-50">

              <div class="py-2 px-3 text-sm text-gray-800 border-b bg-gray-50 rounded-sm">
                <span class="block truncate font-medium">
                  <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
              </div>

              <a href="<?= BASE_URL ?>/profile"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-circle-user text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Profile</span>
              </a>

              <a href="<?= BASE_URL ?>/history"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-cart-flatbed-suitcase text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Order History</span>
              </a>

              <a href="<?= BASE_URL ?>/chat"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-headset text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Customer Service</span>
              </a>

              <a href="<?= BASE_URL ?>/logout"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-arrow-right-from-bracket text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Logout</span>
              </a>

            </div>
          </div>
        <?php else: ?>


          <!-- ========== LOGIN BUTTON (shows in navbar) ========== -->
          <div class="flex items-center gap-2">
            <div class="hidden sm:flex items-center gap-2 px-2.5 py-1 bg-gray-100 rounded-full border border-gray-300">
              <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-xs text-gray-600 font-medium">Guest</span>
            </div>

            <button @click="loginOpen = true"
              class="flex items-center gap-1 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded-lg transition font-medium">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="hidden sm:inline">Login</span>
            </button>
          </div>


        <?php endif; ?>
      </div>
    </div>

    <script>
      function openGooglePopup(url) {
        const width = 500;
        const height = 620;
        const left = Math.round((screen.width - width) / 2);
        const top = Math.round((screen.height - height) / 2);

        const popup = window.open(
          url,
          'googleLoginPopup',
          `width=${width},height=${height},top=${top},left=${left},` +
          `toolbar=no,menubar=no,scrollbars=yes,resizable=no,status=no,location=no`
        );

        if (!popup || popup.closed || typeof popup.closed === 'undefined') {
          // Popup blocked - fallback to redirect
          window.location.href = url;
          return;
        }

        // ✅ Listen for postMessage from popup (works even with COOP)
        function onMessage(event) {
          if (event.data === 'google-login-success') {
            window.removeEventListener('message', onMessage);
            clearInterval(pollTimer);
            popup.close();
            window.location.reload();
          }
        }
        window.addEventListener('message', onMessage);

        // ✅ Fallback: poll if popup closed without postMessage
        const pollTimer = setInterval(function () {
          if (popup.closed) {
            clearInterval(pollTimer);
            window.removeEventListener('message', onMessage);
            window.location.reload();
          }
        }, 500);
      }
    </script>

    <!-- Mobile Sidebar -->
    <div x-show="mobileOpen" x-cloak @click.self="mobileOpen = false"
      x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
      class="lg:hidden fixed inset-0 z-9999 bg-black/30 bg-opacity-50">

      <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed left-0 top-0 h-full w-80 max-w-[85vw] bg-white shadow-2xl overflow-y-auto">

        <!-- Sidebar Header -->
        <div class="sticky top-0 bg-white p-4 flex items-center justify-between z-10">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 overflow-hidden">
              <img src="<?= BASE_URL ?>/user/img/logo.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
              <span class="block text-lg text-orange-500"
                style="font-family: 'Montserrat', sans-serif;">NobleHome</span>
              <span class="block text-xs " style="font-family: 'Montserrat', sans-serif;">Depot</span>
            </div>
          </div>
          <button @click="mobileOpen = false" class="text-gray-700 hover:bg-gray-100 rounded-full p-2 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- User Profile Section (if logged in) -->
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="border-b border-gray-200 p-4 bg-gray-50">
            <div class="flex items-center space-x-3">
              <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-orange-400 bg-gray-100">
                <?php if (!empty($_SESSION['user_picture'])): ?>
                  <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>" alt="Profile"
                    class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-orange-100">
                    <span class="text-lg font-bold text-orange-800">
                      <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold truncate" style="font-family: 'Montserrat', sans-serif;">
                  <?= htmlspecialchars($_SESSION['user_name']) ?>
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Mobile Search -->
        <div x-data="{
             search: '',
             results: [],
             searchOpen: false,
             fetchResults() {
                 if (this.search.trim() === '') {
                   this.results = [];
                   this.searchOpen = false;
                   return;
                 }
                 fetch(`<?= BASE_URL ?>/search?search=${encodeURIComponent(this.search)}`)
                     .then(res => res.json())
                     .then(data => {
                         this.results = data;
                         this.searchOpen = true;
                     });
             }
             }" class="p-4 border-b border-gray-200" style="font-family: 'Montserrat', sans-serif;">
          <div class="relative">
            <input type="text" x-model="search" @input.debounce.300ms="fetchResults" @keydown.enter="fetchResults"
              placeholder="Search products..."
              class="w-full border border-gray-300 pl-10 pr-4 py-3 rounded-lg text-sm outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200"
              style="font-family: 'Montserrat', sans-serif;">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5A7 7 0 11 1 9a7 7 0 0112 0z" />
            </svg>
          </div>

          <div x-show="searchOpen && results.length > 0" x-cloak x-transition @click.away="searchOpen = false"
            class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
            <template x-for="item in results" :key="item.id">
              <a :href="'<?= BASE_URL ?>/shop?search=' + encodeURIComponent(item.product_name)"
                class="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 border-b last:border-0">
                <img :src="'<?= BASE_URL ?>/user/uploads/' + item.main_image" alt=""
                  class="w-10 h-10 object-contain rounded border border-gray-200">
                <span x-text="item.product_name" class="text-sm text-gray-700 flex-1"></span>
              </a>
            </template>
          </div>
        </div>

        <!-- Navigation Links -->
        <div class="py-2">
          <!-- NEW PRODUCTS BUTTON - Mobile -->
          <?php if (count($newProducts) > 0): ?>
            <button @click="newProductsSidebarMobile = true; mobileOpen = false"
              class="flex items-center justify-between w-full px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                </svg>
                <span class="font-medium">New Products</span>
              </div>
              <span class="bg-red-600 text-white rounded-full px-2 py-0.5 text-xs font-bold">
                <?php echo count($newProducts); ?>
              </span>
            </button>
          <?php endif; ?>

          <!-- INSPIRATION LINK - Mobile -->
          <a href="<?= BASE_URL ?>/inspiration"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Inspiration</span>
          </a>

          <!-- FIND PROFESSIONALS LINK - Mobile -->
          <a href="<?= BASE_URL ?>/findprofessional"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Find
              Professionals</span>
          </a>


          <!-- Orders -->
          <a href="javascript:void(0)" onclick="navigateWithLoading('<?= BASE_URL ?>/order')"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Orders</span>
          </a>

          <!-- Shop -->
          <a href="javascript:void(0)" onclick="navigateWithLoading('<?= BASE_URL ?>/shop')"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <i class="fa-solid fa-cart-shopping"></i>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Products</span>
          </a>

          <!-- Products Accordion -->
          <div x-data="{ productsOpen: false, selectedCategory: null }" class="border-t border-gray-200">
            <button @click="productsOpen = !productsOpen"
              class="flex items-center justify-between w-full px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <div class="flex items-center gap-3">
                <i class="fa-solid fa-angles-down"></i>
                <span class="font-medium"
                  style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Categories</span>
              </div>
              <svg class="w-4 h-4 transition-transform" :class="productsOpen ? 'rotate-180' : ''" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Categories List -->
            <div x-show="productsOpen" x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="opacity-0 transform scale-95 -translate-y-2"
              x-transition:enter-end="opacity-100 transform scale-100 translate-y-0"
              x-transition:leave="transition ease-in duration-200"
              x-transition:leave-start="opacity-100 transform scale-100 translate-y-0"
              x-transition:leave-end="opacity-0 transform scale-95 -translate-y-2">
              <?php if (!empty($display_categories)): ?>
                <?php foreach ($display_categories as $catKey => $category): ?>
                  <div x-data="{ subOpen: false }" class="bg-gray-50">
                    <button @click="subOpen = !subOpen"
                      class="flex items-center justify-between w-full px-6 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
                      <div class="flex items-center gap-2">
                        <?php if (!empty($category['image_path'])): ?>
                          <img src="<?= BASE_URL ?>/uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                            alt="<?= htmlspecialchars($category['name']) ?>" class="w-6 h-6 object-cover rounded"
                            onerror="this.style.display='none'">
                        <?php endif; ?>
                        <span class="uppercase"
                          style="font-family: 'Montserrat', sans-serif; color: #2f1200;"><?= htmlspecialchars($category['name']) ?></span>
                      </div>
                      <svg class="w-3 h-3 transition-transform" :class="subOpen ? 'rotate-180' : ''" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </button>

                    <!-- Subcategories -->
                    <div x-show="subOpen" x-transition:enter="transition ease-out duration-300"
                      x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-96"
                      x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 max-h-96"
                      x-transition:leave-end="opacity-0 max-h-0" class="bg-white overflow-hidden">
                      <?php if (!empty($category['subcategories'])): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                          <a href="<?= BASE_URL ?>/productsubviews?subcategory_id=<?= $sub['id'] ?>"
                            class="flex items-center gap-2 px-10 py-2 text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition">
                            <?php if (!empty($sub['image_path'])): ?>
                              <img
                                src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                alt="<?= htmlspecialchars($sub['name']) ?>" class="w-5 h-5 object-contain rounded"
                                onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="uppercase"
                              style="font-family: 'Montserrat', sans-serif; color: #2f1200;"><?= htmlspecialchars($sub['name']) ?></span>
                          </a>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <p class="px-10 py-2 text-xs text-gray-500 italic">No subcategories</p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="px-6 py-3 text-sm text-gray-500 italic">No categories available</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Messages -->
          <a href="<?= BASE_URL ?>/chat"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition border-t border-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Messages</span>
          </a>

          <!-- Notifications - Mobile Responsive -->
          <div x-data="notificationSystem" x-init="init()" class="relative">
            <!-- Button with notification icon -->
            <button @click="notifOpen = !notifOpen; if (notifOpen) markAsRead()"
              class="flex items-center justify-between w-full px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition lg:hidden relative">
              <div class="flex items-center gap-3 flex-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="font-medium text-sm"
                  style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Notifications</span>
              </div>

              <!-- Badge positioned on button -->
              <span x-show="unreadCount > 0" x-text="unreadCount"
                class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full font-semibold min-w-[20px] text-center shrink-0"></span>
            </button>

            <!-- Mobile Notification Dropdown - Positioned inside sidebar flow -->
            <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false"
              x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
              x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
              class="lg:hidden bg-white border border-t-0 border-gray-200 max-h-80 overflow-hidden flex flex-col">

              <!-- Header -->
              <div class="flex justify-between items-center px-4 py-3 border-b bg-orange-50 shrink-0">
                <span class="text-sm font-semibold text-gray-800">Your Notifications</span>
                <button @click.stop="clearNotifications()"
                  class="text-xs text-orange-500 hover:text-orange-700 font-medium transition">
                  Clear All
                </button>
              </div>

              <!-- Notifications List -->
              <ul class="overflow-y-auto flex-1">
                <template x-for="notif in notifications" :key="notif.id">
                  <li class="border-b last:border-0">
                    <div class="px-4 py-3 hover:bg-orange-50 transition cursor-pointer">
                      <p class="text-sm text-gray-800 font-medium mb-1" x-text="notif.message"></p>
                      <span class="text-xs text-gray-400" x-text="formatDateTime(notif.created_at)"></span>
                    </div>
                  </li>
                </template>

                <!-- Empty State -->
                <template x-if="notifications.length === 0">
                  <li class="py-8 px-4 text-center">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="text-sm text-gray-500 mt-2">No notifications yet</p>
                  </li>
                </template>
              </ul>

              <!-- Footer Close -->
              <div class="px-4 py-2 bg-gray-50 border-t shrink-0">
                <button @click.stop="notifOpen = false"
                  class="w-full text-xs text-gray-600 hover:text-gray-800 py-1.5 font-medium transition">
                  Close
                </button>
              </div>
            </div>
          </div>

          <style>
            /* Smooth scrollbar for notification list */
            ul::-webkit-scrollbar {
              width: 4px;
            }

            ul::-webkit-scrollbar-track {
              background: #f9fafb;
            }

            ul::-webkit-scrollbar-thumb {
              background: #fed7aa;
              border-radius: 2px;
            }

            ul::-webkit-scrollbar-thumb:hover {
              background: #fb923c;
            }

            /* Firefox scrollbar */
            ul {
              scrollbar-width: thin;
              scrollbar-color: #fed7aa #f9fafb;
            }

            /* Hide on desktop */
            @media (min-width: 1024px) {
              .lg\:hidden {
                display: none !important;
              }
            }
          </style>
        </div>

        <!-- Auth Section (if not logged in) -->
        <?php if (!isset($_SESSION['user_name'])): ?>
          <div class="border-t border-gray-200 p-4 space-y-3">
            <button @click="loginOpen = true; mobileOpen = false"
              class="w-full py-3 px-4 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
              Login
            </button>
            <button @click="registerOpen = true; mobileOpen = false"
              class="w-full py-3 px-4 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-50 transition font-medium">
              Register
            </button>
          </div>
        <?php else: ?>
          <!-- Logout Button -->
          <div class="border-t border-gray-200 p-4">
            <a href="<?= BASE_URL ?>/profile"
              class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition rounded-lg mb-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Profile</span>
            </a>
            <a href="<?= BASE_URL ?>/logout"
              class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 transition rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Logout</span>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>


    <?php if (count($newProducts) > 0): ?>

<!-- Mobile New Products Sidebar -->

<!-- Overlay -->
<div
  x-show="newProductsSidebarMobile"
  x-cloak
  @click="newProductsSidebarMobile = false"
  x-transition:enter="transition ease-out duration-200"
  x-transition:enter-start="opacity-0"
  x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-150"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
  class="lg:hidden fixed inset-0 z-[99998] bg-black/50">
</div>

<!-- Drawer Panel -->
<div
  x-show="newProductsSidebarMobile"
  x-cloak
  x-transition:enter="transition ease-out duration-300 transform"
  x-transition:enter-start="translate-x-full"
  x-transition:enter-end="translate-x-0"
  x-transition:leave="transition ease-in duration-200 transform"
  x-transition:leave-start="translate-x-0"
  x-transition:leave-end="translate-x-full"
  class="lg:hidden fixed right-0 top-0 h-full w-[92vw] max-w-md bg-white z-[99999] flex flex-col shadow-2xl">

  <!-- Header -->
  <div class="flex items-center justify-between px-4 py-3.5 bg-black shrink-0">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center"
        style="background: rgba(249,115,22,0.15); border: 1px solid rgba(249,115,22,0.25);">
        <i class="fa-solid fa-box-open text-orange-500 text-xs"></i>
      </div>
      <div>
        <h2 class="text-white text-sm font-semibold leading-tight">New Arrivals</h2>
        <p class="text-white/40 text-[10px]">
          <?= count($newProducts) ?> product<?= count($newProducts) > 1 ? 's' : '' ?> added recently
        </p>
      </div>
    </div>
    <button
      @click="newProductsSidebarMobile = false"
      class="w-8 h-8 rounded-lg flex items-center justify-center bg-white/10 hover:bg-white/20 transition-colors"
      aria-label="Close">
      <svg width="13" height="13" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <!-- Product List -->
  <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
    <?php foreach ($newProducts as $product): ?>
      <div class="flex items-center gap-3 px-4 py-3.5 hover:bg-orange-50/60 transition-colors">

        <!-- Image -->
        <div class="relative shrink-0">
          <div class="w-16 h-16 rounded-xl overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center">
            <?php if (!empty($product['main_image'])): ?>
              <img
                src="<?= BASE_URL ?>/<?= htmlspecialchars($product['main_image']) ?>"
                alt="<?= htmlspecialchars($product['name']) ?>"
                class="w-full h-full object-contain"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="w-full h-full hidden items-center justify-center">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 16M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
            <?php else: ?>
              <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 16M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            <?php endif; ?>
          </div>
          <span class="absolute -top-1 -left-1 bg-red-500 text-white text-[8px] font-bold px-1 py-0.5 rounded uppercase tracking-wide">
            NEW
          </span>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <!-- Name -->
          <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide truncate mb-1">
            <?= htmlspecialchars($product['name']) ?>
          </h3>

          <!-- Meta row -->
          <div class="flex items-center gap-1.5 mb-2">
            <span class="text-[11px] text-gray-400 truncate max-w-[100px]">
              <?= htmlspecialchars($product['codename']) ?>
            </span>
            <span class="text-gray-300 text-[9px]">•</span>
            <?php if ($product['stock_status'] === 'In Stock'): ?>
              <span class="text-[11px] text-green-600 font-medium">● In Stock</span>
            <?php else: ?>
              <span class="text-[11px] text-red-500 font-medium">● Out of Stock</span>
            <?php endif; ?>
          </div>

          <!-- Date + View -->
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] text-gray-400">
              <i class="fa-regular fa-calendar text-[9px] mr-0.5"></i>
              <?= date('M j, Y', strtotime($product['created_at'])) ?>
            </span>
            <form action="<?= BASE_URL ?>/productview" method="GET">
              <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
              <button
                type="submit"
                class="text-xs font-semibold text-orange-500 border border-orange-400 px-3 py-1 rounded-lg hover:bg-orange-500 hover:text-white transition-colors shrink-0">
                View →
              </button>
            </form>
          </div>
        </div>

      </div>
    <?php endforeach; ?>
  </div>

  <!-- Footer -->
  <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 shrink-0">
    <button
      onclick="window.location.href='<?= BASE_URL ?>/shop'"
      class="w-full bg-black hover:bg-orange-500 text-white text-sm font-semibold py-2.5 rounded-xl flex items-center justify-center gap-2 transition-colors">
      <i class="fa-solid fa-store text-xs"></i>
      Browse All Products
    </button>
  </div>

</div>

<?php endif; ?>

    <div x-show="registerOpen" x-cloak x-transition
      class="fixed inset-0 z-9999 flex items-center justify-center bg-black/50 p-4"
      style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
      <div class="bg-white w-full max-w-md max-h-[95vh] overflow-y-auto rounded-lg shadow-lg relative">

        <!-- Modal Header -->
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-800">Create Account</h2>
          <button @click="registerOpen = false" class="text-gray-500 hover:text-gray-800 text-2xl font-bold p-1">
            &times;
          </button>
        </div>

        <!-- Modal Content -->
        <div class="p-6">
          <form action="<?= BASE_URL ?>/register" method="POST" class="space-y-4">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
              <input type="text" name="name" id="name" required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
              <input type="email" name="email" id="email" autocomplete="current-email"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="you@example.com">
            </div>

            <div>
              <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
              <input type="tel" name="mobile" id="mobile" pattern="^09\d{9}$" maxlength="11"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="09123456789">
              <p class="text-xs text-gray-500 mt-1">Format: 09XXXXXXXXX</p>
            </div>

            <div>
              <label for="reg_password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
              <input type="password" name="password" id="reg_password" required minlength="6"
                autocomplete="password-com"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div>
              <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm
                Password</label>
              <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                autocomplete="new-password"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <button type="submit"
              class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-4 rounded-lg transition">
              Create Account
            </button>

            <div class="text-center pt-4 border-t border-gray-200">
              <span class="text-sm text-gray-600">Already have an account?</span>
              <a href="#" @click.prevent="registerOpen = false; loginOpen = true"
                class="text-orange-500 hover:underline font-medium text-sm">Login here</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include ROOT_PATH . '/user/navbar/partials/new-products-modal.php'; ?>
  <?php include ROOT_PATH . '/user/navbar/partials/login-modal.php'; ?>
</nav>

<script>
  const BASE_URL = "<?= BASE_URL ?>";
</script>

<script src="<?= BASE_URL ?>/user/navbar/js/top-obf.js?v=<?= file_exists(ROOT_PATH . '/user/navbar/js/top-obf.js') ? md5_file(ROOT_PATH . '/user/navbar/js/top-obf.js') : '1' ?>"></script>

<script>
  // Wait para ready na lahat bago mag-initFCM
  window.addEventListener('load', function () {
    <?php if (isset($user_id) && $user_id): ?>
      setTimeout(function () {
        if (typeof initFCM === 'function') {
          if (typeof socket !== 'undefined') {
            initFCM(<?= intval($user_id) ?>, socket);
          } else {
            initFCM(<?= intval($user_id) ?>, null);
          }
        }
      }, 2000); // 2 segundo para ready na ang socket
    <?php endif; ?>
  });

  function showGuestLoginAlert() {
    showNotification('Please login to proceed', 'info');

    setTimeout(() => {
      document.querySelector('button[\\@click="loginOpen = true"]')?.click() ||
        document.querySelector('button[\\@click="loginOpen = !loginOpen"]')?.click();
    }, 500);
  }


  document.addEventListener("alpine:init", () => {
    Alpine.data("notificationSystem", () => ({
      notifOpen: false,
      notifications: [],
      unreadCount: 0,
      pollTimer: null,
      isPageVisible: true,
      isFetching: false,

      fetchNotifications() {
        // Don't fetch if page hidden
        if (!this.isPageVisible) return;

        // Prevent concurrent fetches
        if (this.isFetching) return;

        this.isFetching = true;

        fetch("<?= BASE_URL ?>/getnotif", {
          credentials: 'include',
          signal: AbortSignal.timeout(10000)
        })
          .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
          })
          .then(data => {
            this.notifications = data.notifications || [];
            this.unreadCount = data.unread_count || 0;

            // IMPORTANT: Only schedule next fetch if may data
            if (this.unreadCount > 0) {
              this.scheduleNextPoll(5000); // Check every 5 seconds when there's data
            } else {
              // Don't schedule anything - stop polling
            }
          })
          .catch(error => {
            console.error('[NOTIF] Error:', error);
            // Try again in 30 seconds on error
            this.scheduleNextPoll(30000);
          })
          .finally(() => {
            this.isFetching = false;
          });
      },

      scheduleNextPoll(interval = 5000) {
        // Clear existing timer
        if (this.pollTimer) {
          clearTimeout(this.pollTimer);
          this.pollTimer = null;
        }

        if (!this.isPageVisible) return;

        // Schedule next poll
        this.pollTimer = setTimeout(() => {
          this.fetchNotifications();
        }, interval);
      },

      markAsRead() {
        fetch("<?= BASE_URL ?>/getmark", {
          method: "POST",
          credentials: 'include'
        })
          .then(res => res.json())
          .then(data => {
            this.unreadCount = 0;
            
          })
          .catch(error => console.error('[NOTIF] Error:', error));
      },

      clearNotifications() {
        if (!confirm('Clear all notifications?')) return;

        fetch("<?= BASE_URL ?>/clearall", {
          method: "POST",
          credentials: 'include'
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              this.notifications = [];
              this.unreadCount = 0;
              this.notifOpen = false;
            }
          })
          .catch(error => console.error('[NOTIF] Error:', error));
      },

      formatDateTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 172800) return 'Yesterday';

        const options = {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
          hour12: true
        };
        return date.toLocaleString('en-US', options);
      },

      init() {
       
        // Initial fetch
        this.fetchNotifications();

        // Handle page visibility
        document.addEventListener('visibilitychange', () => {
          this.isPageVisible = !document.hidden;

          if (this.isPageVisible) {
            console.log('[NOTIF] Page visible - fetching notifications');
            this.lastFetchTime = 0;
            this.fetchNotifications();
          } else {
            console.log('[NOTIF] Page hidden - clearing polls');
            if (this.pollTimer) {
              clearTimeout(this.pollTimer);
              this.pollTimer = null;
            }
          }
        });

        // Cleanup
        window.addEventListener('beforeunload', () => {
          if (this.pollTimer) clearTimeout(this.pollTimer);
        });
      }
    }));
  });
</script>