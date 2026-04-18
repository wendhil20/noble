<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include '../../connection/connect.php';

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
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../../output.css" rel="stylesheet">
<link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
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
    lenisScript.src = 'https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.19/bundled/lenis.min.js';
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

<!-- Loader CSS -->
<style>
  .loader {
    width: fit-content;
    height: fit-content;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .truckWrapper {
    width: 200px;
    height: 100px;
    display: flex;
    flex-direction: column;
    position: relative;
    align-items: center;
    justify-content: flex-end;
    overflow-x: hidden;
  }

  /* truck body bounce */
  .truckBody {
    width: 130px;
    height: fit-content;
    margin-bottom: 6px;
    animation: motion 1s linear infinite;
  }

  @keyframes motion {
    0% {
      transform: translateY(0px);
    }

    50% {
      transform: translateY(3px);
    }

    100% {
      transform: translateY(0px);
    }
  }

  /* truck tires */
  .truckTires {
    width: 130px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px 0 15px;
    position: absolute;
    bottom: 0;
  }

  .truckTires svg {
    width: 24px;
  }

  .road {
    width: 100%;
    height: 1.5px;
    background-color: #282828;
    position: relative;
    bottom: 0;
    align-self: flex-end;
    border-radius: 3px;
  }

  .road::before {
    content: "";
    position: absolute;
    width: 20px;
    height: 100%;
    background-color: #282828;
    right: -50%;
    border-radius: 3px;
    animation: roadAnimation 1.4s linear infinite;
    border-left: 10px solid white;
  }

  .road::after {
    content: "";
    position: absolute;
    width: 10px;
    height: 100%;
    background-color: #282828;
    right: -65%;
    border-radius: 3px;
    animation: roadAnimation 1.4s linear infinite;
    border-left: 4px solid white;
  }

  .lampPost {
    position: absolute;
    bottom: 0;
    right: -90%;
    height: 90px;
    animation: roadAnimation 1.4s linear infinite;
  }

  @keyframes roadAnimation {
    0% {
      transform: translateX(0px);
    }

    100% {
      transform: translateX(-350px);
    }
  }
</style>

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
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-page-1-A-B-C-D-E')"
          class="flex items-center space-x-2 sm:space-x-3 hover:opacity-80 transition duration-200 shrink-0">
          <div class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 overflow-hidden">
            <img src="../img/logo.png" alt="Noble Home Logo" class="w-full h-full object-contain">
          </div>

        </a>
        <a href="../otherpage/index-findpropage-page-10.php"
          class="hidden xl:block text-lg text-black font-semibold hover:bg-gray-100 rounded-lg px-2 py-1">
          Find Professional
        </a>

        <a href="../otherpage/index-inspirationpage-page-11.php"
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
              'sub_subcategories' => []
            ];
          }

          if ($row['sub_subcategory_id']) {
            $nav_categories[$cat_id]['subcategories'][$sub_id]['sub_subcategories'][] = [
              'id' => $row['sub_subcategory_id'],
              'name' => $row['sub_subcategory_name'],
              'slug' => $row['sub_subcategory_slug'],
              'image_path' => $row['sub_sub_image_path'],
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

          <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-shop-page-2')" @mouseenter="
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
                  <a href="../otherpage/index-shop-page-2.php"
                    class="text-[11px] hover:text-orange-600 font-medium transition-all">
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
                            <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                              alt="<?= htmlspecialchars($category['name']) ?>" class="w-8 h-8 object-contain rounded"
                              loading="lazy" onerror="this.style.display='none'">
                          </div>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                          <div class=" text-sm group-hover:text-orange-500 truncate uppercase"
                            :class="selectedCategory === 'cat_<?= $category['id'] ?>' ? 'text-orange-500 font-medium' : 'text-gray-800'">
                            <?= htmlspecialchars($category['name']) ?>
                          </div>

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
                                  src="../../uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                  alt="<?= htmlspecialchars($sub['name']) ?>" class="w-9 h-9 object-cover rounded" loading="lazy"
                                  onerror="this.style.display='none'">
                              </div>
                            <?php endif; ?>

                            <div class="flex-1 min-w-0">
                              <div class="text-sm group-hover:text-orange-500 transition truncate uppercase"
                                :class="selectedSubcategory === 'sub_<?= $sub['id'] ?>' ? 'text-orange-500 font-medium' : 'text-gray-800'">
                                <?= htmlspecialchars($sub['name']) ?>
                              </div>

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
                              <a href="../otherpage/allproduct-allproductsub_variant-page-3-A.php?sub_subcategory_id=<?= $subsub['id'] ?>"
                                x-show="searchTerm === '' || '<?= strtolower($subsub['name']) ?>'.includes(searchTerm.toLowerCase())"
                                class="flex items-center gap-2 p-1.5 rounded hover:bg-purple-50 transition-all duration-200 group cursor-pointer border border-transparent hover:border-orange-200">

                                <?php if (!empty($subsub['image_path'])): ?>
                                  <div class="shrink-0">
                                    <img
                                      src="../../uploads/<?= htmlspecialchars($subsub['parent_slug']) ?>/<?= htmlspecialchars($subsub['slug']) ?>/<?= htmlspecialchars($subsub['image_path']) ?>"
                                      alt="<?= htmlspecialchars($subsub['name']) ?>" class="w-10 h-10 object-cover rounded"
                                      loading="lazy" onerror="this.style.display='none'">
                                  </div>
                                <?php endif; ?>

                                <div class="flex-1 min-w-0">
                                  <div
                                    class="text-sm group-hover:text-orange-600 transition font-medium text-gray-800 truncate uppercase">
                                    <?= htmlspecialchars($subsub['name']) ?>
                                  </div>

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
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-cart_view-page-8')"
          class="relative p-2 hover:bg-gray-100 rounded-full transition">
          <img src="../img/ecommerce.png" alt="Cart" class="w-5 h-5 object-contain" />
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
              <a href="../otherpage/index-profilepersonal-page-7.php"
                class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                Profile
              </a>
              <a href="../logout.php" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
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
            <a href="../otherpage/index-inspirationpage-page-11.php"
              class="flex items-center gap-3 px-4 py-3 text-md text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition border-b border-gray-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
              Inspiration
            </a>

            <!-- Find Professionals -->
            <a href="../otherpage/index-findpropage-page-10.php"
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
          fetch(`backend-search_ajax-A.php?search=${encodeURIComponent(this.search)}`)
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
                    <a :href="'index-shop-page-2.php?search=' + encodeURIComponent(item.product_name)"
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



        <style>
          /* Loading Spinner */
          .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #f97316;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
          }

          @keyframes spin {
            0% {
              transform: rotate(0deg);
            }

            100% {
              transform: rotate(360deg);
            }
          }

          /* Keyboard shortcut styling */
          kbd {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
          }

          /* Smooth scrolling */
          .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: rgba(249, 115, 22, 0.3) transparent;
          }

          .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
          }

          .overflow-y-auto::-webkit-scrollbar-track {
            background: transparent;
          }

          .overflow-y-auto::-webkit-scrollbar-thumb {
            background-color: rgba(249, 115, 22, 0.3);
            border-radius: 4px;
          }

          .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background-color: rgba(249, 115, 22, 0.5);
          }
        </style>
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
      fetch(`backend-search_ajax-A.php?search=${encodeURIComponent(this.search)}`)
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
                  <a :href="'index-shop-page-2.php?search=' + encodeURIComponent(item.product_name)"
                    @click="saveSearch(item.product_name)"
                    class="flex items-center gap-3 px-4 py-2 hover:bg-orange-100 text-sm text-gray-700">
                    <img :src="item.main_image" alt="" class="w-10 h-10 object-contain rounded border border-gray-300">
                    <span x-text="item.product_name"></span>
                  </a>
                </li>
              </template>
            </ul>
          </div>
        </div>



        <!-- Cart Link with Hover Modal -->
        <div class="relative" id="cart-container">
          <?php if (!in_array($current_page, $hidden_pages)): ?>
            <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-cart_view-page-8')"
              class="<?= $current_page == 'index-cart_view-page-8.php' ? 'text-orange-600 underline ' : 'text-black' ?> transition inline-flex items-center relative  p-2 rounded-lg hover:bg-gray-100 group"
              id="cart-link">
              <i class="fas fa-shopping-cart fa-md"></i>
              <!-- Cart -->
              <span id="cart-count-bubble"
                class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full <?= $total_cart_items > 0 ? '' : 'hidden' ?>"></span>

              <!-- Tooltip -->
              <span
                class="absolute top-1/2 -translate-y-1/2 left-full ml-2 bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
                Cart
              </span>
            </a>
          <?php endif; ?>

          <!-- Cart Hover Modal -->
          <div id="cart-modal"
            class="cart-modal fixed right-4 top-16 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-9999 max-h-[80vh] overflow-hidden max-w-[calc(100vw-2rem)] opacity-0 invisible">
            <!-- Modal Header -->
            <div class="bg-black text-white p-4 rounded-t-xl">
              <div class="flex items-center justify-between">
                <h3 class=" text-lg flex items-center gap-2" style="font-family: 'Montserrat', sans-serif;">
                  <i class="fas fa-shopping-cart"></i>
                  Your Cart
                </h3>
                <div class="flex items-center gap-2">
                  <span class="bg-white/20 px-2 py-1 rounded-full text-sm " id="modal-cart-count">
                    <?= $total_cart_items ?> items
                  </span>
                </div>
              </div>
            </div>

            <!-- Loading Indicator -->
            <div id="cart-loading" class="hidden p-4 text-center">
              <i class="fas fa-spinner fa-spin text-orange-500 text-xl"></i>
              <p class="text-sm text-gray-500 mt-2">Updating cart...</p>
            </div>

            <!-- Cart Items -->
            <div class="max-h-60 sm:max-h-64 overflow-y-auto p-3 sm:p-4" id="cart-items-container">

              <?php
              // ===== GUEST CART =====
              if (!$user_id && isset($_SESSION['guest_cart']) && count($_SESSION['guest_cart']) > 0):
                ?>
                <div class="space-y-3">
                  <?php
                  $guest_total = 0;
                  foreach ($_SESSION['guest_cart'] as $item):
                    $unit_price = floatval($item['price']);
                    $quantity = intval($item['quantity']);
                    $guest_total += $unit_price * $quantity;
                    ?>
                    <div
                      class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
                      <!-- Product Image - UPDATED FOR GUESTS -->
                      <?php
                      // Try to fetch product image from database for guests
                      $guest_img_stmt = $conn->prepare("SELECT main_image FROM products WHERE id = ? LIMIT 1");
                      $product_id = intval($item['product_id']);
                      $guest_img_stmt->bind_param("i", $product_id);
                      $guest_img_stmt->execute();
                      $guest_img_result = $guest_img_stmt->get_result();
                      $guest_img_row = $guest_img_result->fetch_assoc();
                      $guest_img_stmt->close();

                      $has_image = !empty($guest_img_row['main_image']);
                      ?>

                      <?php if ($has_image): ?>
                        <img src="../../<?= htmlspecialchars($guest_img_row['main_image']) ?>"
                          alt="<?= htmlspecialchars($item['product_name']) ?>"
                          class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg shrink-0 bg-gray-50">
                      <?php else: ?>
                        <div
                          class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-200 rounded-lg flex items-center justify-center shrink-0">
                          <i class="fas fa-image text-gray-400 text-xs"></i>
                        </div>
                      <?php endif; ?>

                      <div class="flex-1 min-w-0">
                        <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate">
                          <?= htmlspecialchars($item['product_name']) ?>
                        </h4>
                        <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                          <?= htmlspecialchars($item['variant_name'] ?: '') ?>
                          <?= !empty($item['color_name']) ? ', ' . htmlspecialchars($item['color_name']) : '' ?>
                          <?= !empty($item['size']) ? ', ' . htmlspecialchars($item['size']) : '' ?>
                        </p>

                        <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                          <p class="text-[9px] sm:text-[10px] text-gray-400 truncate mt-1">
                            <?= htmlspecialchars($item['descrip6'] ?: '') ?>
                            <?= !empty($item['descrip6']) && !empty($item['descrip7']) ? ' • ' : '' ?>
                            <?= htmlspecialchars($item['descrip7'] ?: '') ?>
                          </p>
                        <?php endif; ?>

                        <div class="flex items-center justify-between mt-1">
                          <span class="text-xs sm:text-sm text-orange-600">₱<?= number_format($unit_price, 2) ?></span>
                          <span class="text-[10px] sm:text-xs text-gray-500">Qty: <?= $quantity ?></span>
                        </div>
                      </div>

                      <!-- Remove Button (Disabled for guests) -->
                      <button onclick="showGuestLoginAlert()" class="text-gray-400 cursor-not-allowed p-1 shrink-0"
                        title="Login to remove items">
                        <i class="fas fa-times text-xs"></i>
                      </button>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php
                // ===== LOGGED IN USER CART (existing code) =====
              elseif ($user_id && $total_cart_items > 0):
                ?>
                <div class="space-y-3">
                  <?php
                  $modal_stmt = $conn->prepare("
          SELECT 
            c.*, 
            t.type_image, 
            p.descrip6, 
            p.descrip7,
            p.product_name,
            p.main_image,
            pc.image as pc_image
          FROM user_cart_items c
          LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
          LEFT JOIN products p ON c.product_id = p.id
          LEFT JOIN product_colors pc ON pc.id = c.color_id
          WHERE c.user_id = ?
         ");
                  $modal_stmt->bind_param("i", $user_id);
                  $modal_stmt->execute();
                  $modal_result = $modal_stmt->get_result();

                  while ($item = $modal_result->fetch_assoc()):
                    $unit_price = floatval($item['price']);
                    $quantity = intval($item['quantity']);
                    ?>
                    <div
                      class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
                      <?php if (!empty($item['pc_image'])): ?>
                        <img src="../../<?= htmlspecialchars($item['pc_image']) ?>" alt="Product"
                          class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg shrink-0">
                      <?php elseif (!empty($item['type_image'])): ?>
                        <img src="../../<?= htmlspecialchars($item['type_image']) ?>" alt="Product"
                          class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg shrink-0">
                      <?php elseif (!empty($item['main_image'])): ?>
                        <img src="../../<?= htmlspecialchars($item['main_image']) ?>" alt="Product"
                          class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg shrink-0">
                      <?php else: ?>
                        <div
                          class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-200 rounded-lg flex items-center justify-center shrink-0">
                          <i class="fas fa-image text-gray-400 text-xs"></i>
                        </div>
                      <?php endif; ?>

                      <div class="flex-1 min-w-0">
                        <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate">
                          <?= htmlspecialchars($item['product_name'] ?: $item['codename']) ?>
                        </h4>
                        <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                          <?= htmlspecialchars($item['variant_name'] ?: '') ?>
                          <?= !empty($item['color_name']) ? ', ' . htmlspecialchars($item['color_name']) : '' ?>
                          <?= !empty($item['size']) ? ', ' . htmlspecialchars($item['size']) : '' ?>
                        </p>

                        <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                          <p class="text-[9px] sm:text-[10px] text-gray-400 truncate mt-1">
                            <?= htmlspecialchars($item['descrip6'] ?: '') ?>
                            <?= !empty($item['descrip6']) && !empty($item['descrip7']) ? ' • ' : '' ?>
                            <?= htmlspecialchars($item['descrip7'] ?: '') ?>
                          </p>
                        <?php endif; ?>

                        <div class="flex items-center justify-between mt-1">
                          <span class="text-xs sm:text-sm text-orange-600">₱<?= number_format($unit_price, 2) ?></span>
                          <span class="text-[10px] sm:text-xs text-gray-500">Qty: <?= $quantity ?></span>
                        </div>
                      </div>

                      <a href="javascript:void(0)" onclick="removeFromCart(<?= $item['id'] ?>)"
                        class="text-red-500 hover:text-red-700 transition p-1 shrink-0">
                        <i class="fas fa-times text-xs"></i>
                      </a>
                    </div>
                    <?php
                  endwhile;
                  $modal_stmt->close();
                  ?>
                </div>

              <?php else: ?>
                <!-- Empty Cart -->
                <div class="text-center py-8">
                  <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
                  <p class="text-gray-500 text-sm">Your cart is empty</p>
                  <a href="index-shop-page-2.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm">
                    Start Shopping
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <!-- Modal Footer -->
            <?php

            // Guest cart footer
            if (!$user_id && isset($_SESSION['guest_cart']) && count($_SESSION['guest_cart']) > 0):

              foreach ($_SESSION['guest_cart'] as $item) {
                $footer_total += floatval($item['price']) * intval($item['quantity']);
              }
              ?>
              <div class="border-t border-gray-200 p-3 sm:p-4 bg-linear-to-r from-orange-50 to-orange-100 rounded-b-xl"
                id="cart-footer">
                <!-- Total Price -->
                <div class="flex justify-between items-center mb-3">
                  <span class="text-sm text-gray-700">Total:</span>
                  <span class="text-base sm:text-lg text-orange-600" id="cart-total">
                    ₱<?= number_format($footer_total, 2) ?>
                  </span>
                </div>

                <!-- Guest Alert Message -->
                <div class="mb-3 p-2 bg-orange-200 border border-orange-400 rounded text-xs text-orange-800">
                  <i class="fas fa-info-circle mr-1"></i>
                  <strong>Guest Mode:</strong> Login to proceed with checkout
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-2">
                  <button onclick="navigateWithLoading('../otherpage/index-cart_view-page-8')"
                    class="bg-black hover:bg-gray-800 text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition">
                    View Cart
                  </button>
                  <button onclick="showGuestLoginAlert()"
                    class="bg-gray-400 cursor-not-allowed text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition opacity-60">
                    Login to Checkout
                  </button>
                </div>
              </div>

              <?php
              // Logged in user footer (existing code)
            elseif ($user_id && $total_cart_items > 0):

              ?>
              <div class="border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl" id="cart-footer">
                <!-- Total Price -->
                <div class="flex justify-between items-center mb-3">
                  <span class="text-sm text-gray-700">Total:</span>
                  <span class="text-base sm:text-lg text-orange-600" id="cart-total">
                    ₱<?php
                    $total_stmt = $conn->prepare("SELECT SUM(price * quantity) as total FROM user_cart_items WHERE user_id = ?");
                    $total_stmt->bind_param("i", $user_id);
                    $total_stmt->execute();
                    $total_result = $total_stmt->get_result();
                    $total_row = $total_result->fetch_assoc();
                    echo number_format($total_row['total'] ?? 0, 2);
                    $total_stmt->close();
                    ?>
                  </span>
                </div>
                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-2">
                  <a href="../otherpage/index-cart_view-page-8.php"
                    class="bg-black hover:bg-gray-800 text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition">
                    View Cart
                  </a>
                  <a href="javascript:void(0)" onclick="proceedToCheckout()"
                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition">
                    Checkout
                  </a>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </div>


        <style>
          .cart-modal {
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-10px);
            transition: all 0.3s ease-in-out;
            z-index: 9999 !important;
            display: none;
          }

          .cart-modal.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0);
            display: block;
          }

          .cart-item-slide {
            animation: slideInRight 0.3s ease-out forwards;
          }

          @keyframes slideInRight {
            from {
              opacity: 0;
              transform: translateX(20px);
            }

            to {
              opacity: 1;
              transform: translateX(0);
            }
          }

          #cart-items-container {
            max-height: 400px;
            /* Increase from 240px/256px */
            overflow-y: auto;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: #d1d5db #f3f4f6;
          }

          /* WebKit browsers scrollbar */
          #cart-items-container::-webkit-scrollbar {
            width: 6px;
          }

          #cart-items-container::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 10px;
          }

          #cart-items-container::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 10px;
          }

          #cart-items-container::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
          }

          /* Mobile responsive */
          @media (max-width: 640px) {
            #cart-items-container {
              max-height: 350px;
            }
          }

          @media (max-width: 480px) {
            #cart-items-container {
              max-height: 300px;
            }
          }

          @media (max-width: 375px) {
            #cart-items-container {
              max-height: 250px;
            }
          }

          /* Responsive positioning */
          @media (max-width: 640px) {
            .cart-modal {
              right: 0.5rem !important;
              left: 0.5rem !important;
              width: auto !important;
              max-width: none !important;
              top: 4rem !important;
            }
          }

          @media (max-width: 480px) {
            .cart-modal {
              right: 0.25rem !important;
              left: 0.25rem !important;
              top: 3.5rem !important;
              max-height: 85vh !important;
            }

            /* Adjust padding for mobile */
            .cart-modal .p-4 {
              padding: 0.75rem !important;
            }

            .cart-modal .p-3 {
              padding: 0.5rem !important;
            }

            /* Make cart items more compact on mobile */
            .cart-modal .space-y-3 {
              gap: 0.5rem;
            }

            .cart-modal .space-y-3>*+* {
              margin-top: 0.5rem;
            }
          }

          @media (max-width: 375px) {
            .cart-modal {
              right: 0.125rem !important;
              left: 0.125rem !important;
              max-height: 80vh !important;
            }

            /* Further reduce spacing for very small screens */
            #cart-items-container {
              max-height: 12rem !important;
              /* Reduce max height */
            }
          }

          /* Ensure modal appears above all other elements */
          .cart-modal {
            position: fixed !important;
          }

          /* Button hover effects */
          #refresh-cart-btn:hover i {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
          }

          #refresh-cart-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
          }

          /* Smooth scrolling for cart items */
          #cart-items-container {
            scroll-behavior: smooth;
          }

          /* Add subtle gradient fade at bottom when scrolling */
          #cart-items-container::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, rgba(255, 255, 255, 0.8));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
          }

          #cart-items-container.has-scroll::after {
            opacity: 1;
          }

          /* Responsive text sizes */
          @media (max-width: 640px) {
            .cart-modal h3 {
              font-size: 1rem !important;
            }

            .cart-modal .text-lg {
              font-size: 1rem !important;
            }

            .cart-modal .text-base {
              font-size: 0.875rem !important;
            }
          }

          @media (max-width: 480px) {
            .cart-modal h3 {
              font-size: 0.875rem !important;
            }

            .cart-modal .font-bold.text-lg {
              font-size: 0.875rem !important;
            }
          }

          /* Improve touch targets for mobile */
          @media (max-width: 640px) {

            .cart-modal a,
            .cart-modal button {
              min-height: 44px;
              display: flex;
              align-items: center;
              justify-content: center;
            }

            /* Remove item button */
            .cart-modal .fa-times {
              padding: 0.5rem;
            }
          }
        </style>

        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-profile-page-6')"
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
                <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-300 bg-gray-100">
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
            <div x-show="profileOpen" x-cloak @click.outside="profileOpen = false" x-transition
              class="absolute right-0 mt-2 w-45 bg-white border border-gray-200 rounded-md shadow-lg z-50">

              <div class="py-2 px-3 text-sm text-gray-800 border-b bg-gray-50 rounded-sm">
                <span class="block truncate font-medium">
                  <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
              </div>

              <a href="../otherpage/index-profilepersonal-page-7.php"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-circle-user text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Profile</span>
              </a>

              <a href="../otherpage/index-order_history-page-13.php"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-cart-flatbed-suitcase text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Order History</span>
              </a>

              <div x-data="chatNotif" x-init="init()">
                <a href="../otherpage/index-chat_main-page-9.php"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 relative">
                  <i class="fa-solid fa-headset text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                  <span>Customer Service</span>
                  <template x-if="unreadCount > 0">
                    <span
                      class="ml-auto bg-red-500 text-white text-xs w-4 h-4 flex items-center justify-center rounded-full"
                      x-text="unreadCount">
                    </span>
                  </template>
                </a>
              </div>

              <a href="../logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-arrow-right-from-bracket text-sm text-center bg-gray-300 p-2 rounded-lg"></i>
                <span>Logout</span>
              </a>

            </div>
          </div>
        <?php else: ?>
          <!-- ===== GUEST MODE ===== -->
          <div class="relative">
            <!-- Guest Badge + Login Button Group -->
            <div class="flex items-center gap-2">
              <!-- Guest Badge -->
              <div class="hidden sm:flex items-center gap-2 px-2.5 py-1 bg-gray-100 rounded-full border border-gray-300">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-xs text-gray-600 font-medium">Guest</span>
              </div>

              <!-- Login Button -->
              <button @click="loginOpen = !loginOpen"
                class="flex items-center gap-1 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded-lg transition font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="hidden sm:inline">Login</span>
              </button>
            </div>

            <!-- Backdrop overlay - sa loob ng nav, bago ang Desktop Login Dropdown -->
            <div x-show="loginOpen" @click="loginOpen = false" x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
              x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
              x-transition:leave-end="opacity-0" x-cloak class="fixed inset-0 bg-black/40 bg-opacity-10 z-20"
              style="top: 80px;">
            </div>

            <!-- Desktop Login Dropdown -->
            <div x-show="loginOpen" @click.away="loginOpen = false" x-transition x-cloak
              class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg p-6 z-50  ">

              <h2 class="text-xl font-bold text-gray-800 mb-4">Login</h2>

              <form x-data="loginForm()" @submit.prevent="handleLogin($event)">
                <!-- Email/Mobile Input -->
                <div class="mb-4">
                  <label for="login_input" class="block text-sm font-medium text-gray-600 mb-2">Email or Mobile</label>
                  <input type="text" id="login_input" name="login" x-model="loginInput" @input="checkLoginType"
                    placeholder="you@example.com or 09123456789" required
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <!-- Password field (shown for mobile or after OTP verified for email) -->
                <div x-show="(isMobile) || (isEmail && otpVerified)" x-transition class="mb-4">
                  <label for="password" class="block text-sm font-medium text-gray-600 mb-2">Password</label>
                  <input type="password" id="password" name="password" x-model="password" autocomplete="current-password"
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <!-- OTP Send Button (shown for email before OTP is sent) -->
                <div x-show="isEmail && !otpSent && !otpVerified" x-transition>
                  <label class="block text-sm font-medium text-gray-600 mb-2">OTP Verification</label>
                  <button type="button" @click="sendOTP" :disabled="otpLoading || resendCooldown > 0"
                    class="w-full bg-black hover:bg-red-700 disabled:bg-black text-white px-4 py-3 rounded mb-2 flex items-center justify-center space-x-2">

                    <!-- Show "Send OTP" -->
                    <template x-if="!otpLoading && resendCooldown === 0">
                      <span>Send OTP</span>
                    </template>

                    <!-- Show animated spinner + "Loading..." -->
                    <template x-if="otpLoading">
                      <div class="flex items-center space-x-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                          viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                          </circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Verifying...</span>
                      </div>
                    </template>

                    <!-- Show "Resend in Xs" -->
                    <template x-if="!otpLoading && resendCooldown > 0">
                      <span>Resend in <span x-text="resendCooldown"></span>s</span>
                    </template>
                  </button>
                </div>

                <!-- OTP Input Section (shown after OTP is sent but not verified) -->
                <div x-show="otpSent && !otpVerified" x-transition class="mb-4">
                  <label class="block text-sm font-medium text-gray-600 mb-2">Enter OTP</label>
                  <p class="text-xs text-gray-500 mb-2">We sent a verification code to your email</p>

                  <input type="text" x-model="otp" maxlength="6"
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 mb-3 text-center text-lg tracking-widest"
                    placeholder="000000">

                  <div class="flex gap-2">
                    <button type="button" @click="cancelOTP"
                      class="flex-1 py-2 bg-gray-300 rounded hover:bg-gray-400 text-sm">Cancel</button>
                    <button type="button" @click="verifyOTP" :disabled="!otp || otp.length < 4"
                      class="flex-1 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 disabled:bg-orange-300 text-sm">
                      Verify
                    </button>
                  </div>

                  <!-- Resend OTP section -->
                  <div class="mt-3 text-center">
                    <template x-if="resendCooldown > 0">
                      <p class="text-sm text-gray-500">Resend in <span x-text="resendCooldown"></span>s</p>
                    </template>
                    <template x-if="resendCooldown === 0">
                      <button @click="sendOTP" class="text-blue-500 hover:underline text-sm" type="button">
                        Resend OTP
                      </button>
                    </template>
                  </div>
                </div>

                <!-- Remember Me (for mobile only) -->
                <div class="flex items-center gap-2 mb-4" x-show="isMobile">
                  <input type="checkbox" id="remember" name="remember" class="h-4 w-4">
                  <label for="remember" class="text-sm text-gray-600">Remember me</label>
                </div>

                <!-- Login Button (shown for mobile or after OTP verified for email) -->
                <button type="submit" :disabled="submitLoading" x-show="(isMobile || (isEmail && otpVerified))"
                  class="w-full mb-4 bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white font-semibold py-2 px-4 rounded-lg">
                  <span x-show="!submitLoading">Log In</span>
                  <span x-show="submitLoading">Logging in...</span>
                </button>

                <!-- Error/Success Messages -->
                <div x-show="errorMessage" x-transition
                  class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                  <span x-text="errorMessage"></span>
                </div>

                <div x-show="successMessage" x-transition
                  class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                  <span x-text="successMessage"></span>
                </div>

                <!-- Additional Links -->
                <div class="text-center text-xs mb-2">
                  <a href="../forgot_password" class="text-orange-500 hover:underline">Forgot password?</a>
                </div>

                <div class="text-center text-xs mb-4">
                  <span>Don't have an account?</span>
                  <a href="#" @click.prevent="registerOpen = true; loginOpen = false"
                    class="text-orange-500 hover:underline font-medium">Register</a>
                </div>

                <!-- Google Login -->
                <div class="text-center">
                  <a href="javascript:void(0)" onclick="openGooglePopup('../google-login.php')"
                    class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg">
                    <svg class="w-5 h-5 bg-white rounded-full p-[2px]" viewBox="0 0 48 48">
                      <path fill="#EA4335"
                        d="M24 9.5c3.5 0 6.3 1.2 8.3 3.2l6.2-6.2C34.8 2.6 29.7 0 24 0 14.8 0 6.8 5.9 3.2 14.1l7.3 5.7C12.7 13.2 17.9 9.5 24 9.5z" />
                      <path fill="#34A853"
                        d="M24 48c6.5 0 12-2.1 16.1-5.7l-7.4-6.1C30.5 38.7 27.5 40 24 40c-6 0-11.2-3.7-13.4-8.8l-7.2 5.5C6.3 43.8 14.6 48 24 48z" />
                      <path fill="#FBBC05"
                        d="M43.6 20H24v8.4h11.3c-1.1 3.2-3.4 5.8-6.5 7.6l7.4 6.1c4.3-4 6.8-9.9 6.8-17.1 0-1.2-.1-2.3-.3-3.4z" />
                      <path fill="#4285F4"
                        d="M10.6 29.6C9.7 27.2 9.2 24.7 9.2 22s.5-5.2 1.4-7.6l-7.4-5.7C1.1 13.6 0 17.7 0 22c0 4.2 1.1 8.3 3.2 11.8l7.4-4.2z" />
                    </svg>
                    Login with Google
                  </a>
                </div>
              </form>
            </div>
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
  const pollTimer = setInterval(function() {
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
              <img src="../img/logo.png" alt="Logo" class="w-full h-full object-contain">
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
                 fetch(`../otherpage/backend-search_ajax-A.php?search=${encodeURIComponent(this.search)}`)
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
              <a :href="'../otherpage/index-shop-page-2.php?search=' + encodeURIComponent(item.product_name)"
                class="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 border-b last:border-0">
                <img :src="item.main_image" alt="" class="w-10 h-10 object-contain rounded border border-gray-200">
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
          <a href="../otherpage/index-inspirationpage-page-11.php"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Inspiration</span>
          </a>

          <!-- FIND PROFESSIONALS LINK - Mobile -->
          <a href="../otherpage/index-findpropage-page-10.php"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Find
              Professionals</span>
          </a>


          <!-- Orders -->
          <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-profile-page-6')"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Orders</span>
          </a>

          <!-- Shop -->
          <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index-shop-page-2')"
            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
            <img src="../img/shopping-cart.png" alt="Shop" class="w-5 h-5 object-contain" />
            <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Products</span>
          </a>

          <!-- Products Accordion -->
          <div x-data="{ productsOpen: false, selectedCategory: null }" class="border-t border-gray-200">
            <button @click="productsOpen = !productsOpen"
              class="flex items-center justify-between w-full px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Products</span>
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
                          <img src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
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
                          <a href="../otherpage/allproduct-allproductsub_variant-page-3-A.php?subcategory_id=<?= $sub['id'] ?>"
                            class="flex items-center gap-2 px-10 py-2 text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition">
                            <?php if (!empty($sub['image_path'])): ?>
                              <img
                                src="../../uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
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
          <a href="../otherpage/index-chat_main-page-9.php"
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
            <a href="../otherpage/index-profilepersonal-page-7.php"
              class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition rounded-lg mb-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span class="font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Profile</span>
            </a>
            <a href="../logout.php"
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


    <!-- New Products Sidebar for Mobile -->
    <?php if (count($newProducts) > 0): ?>
      <!-- Overlay -->
      <div x-show="newProductsSidebarMobile" x-cloak @click="newProductsSidebarMobile = false"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="lg:hidden fixed inset-0 z-9999 bg-black bg-opacity-50">
      </div>

      <!-- Sidebar Panel -->
      <div x-show="newProductsSidebarMobile" x-cloak x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="lg:hidden fixed left-0 top-0 h-full w-80 max-w-[85vw] bg-white shadow-2xl z-10000 flex flex-col">

        <!-- Sidebar Header -->
        <div class="flex justify-between items-center p-4 border-b bg-black text-white shrink-0">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
            </svg>
            <h2 class="text-base font-semibold">New Products</h2>
            <span class="bg-red-600 text-white rounded-full px-2 py-0.5 text-xs font-bold ml-2">
              <?php echo count($newProducts); ?>
            </span>
          </div>
          <button @click="newProductsSidebarMobile = false"
            class="text-white hover:text-orange-400 text-2xl font-bold w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-all">
            ×
          </button>
        </div>

        <!-- Sidebar Content - Scrollable -->
        <div class="flex-1 overflow-y-auto p-4" style="-webkit-overflow-scrolling: touch;">
          <div class="space-y-4">
            <?php foreach ($newProducts as $product): ?>
              <div
                class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-all duration-300 hover:border-orange-400">
                <div class="flex gap-3">
                  <!-- Product Image -->
                  <div class="relative shrink-0">
                    <?php if (!empty($product['main_image'])): ?>
                      <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-20 h-20 object-contain rounded"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div class="w-20 h-20 bg-gray-200 rounded hidden items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd"
                            d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                            clip-rule="evenodd" />
                        </svg>
                      </div>
                    <?php else: ?>
                      <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd"
                            d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                            clip-rule="evenodd" />
                        </svg>
                      </div>
                    <?php endif; ?>

                    <!-- NEW Badge -->
                    <span
                      class="absolute -top-1 -right-1 bg-red-600 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full shadow">
                      NEW
                    </span>
                  </div>

                  <!-- Product Info -->
                  <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-sm text-gray-900 mb-1 line-clamp-2">
                      <?php echo htmlspecialchars($product['name']); ?>
                    </h3>

                    <?php if (!empty($product['description'])): ?>
                      <p class="text-xs text-gray-600 mb-2 line-clamp-2">
                        <?php echo htmlspecialchars($product['description']); ?>
                      </p>
                    <?php endif; ?>

                    <!-- Category & Date -->
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                      <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <?php echo htmlspecialchars($product['category_name']); ?>
                      </span>
                      <span>•</span>
                      <span><?php echo date('M j', strtotime($product['created_at'])); ?></span>
                    </div>

                    <!-- Stock Status -->
                    <?php if ($product['stock_status'] === 'In Stock'): ?>
                      <span
                        class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-0.5 rounded-full mb-2">
                        <span class="w-1.5 h-1.5 bg-green-600 rounded-full"></span>
                        In Stock
                      </span>
                    <?php else: ?>
                      <span
                        class="inline-flex items-center gap-1 text-xs text-red-600 bg-red-50 px-2 py-0.5 rounded-full mb-2">
                        <span class="w-1.5 h-1.5 bg-red-600 rounded-full"></span>
                        Out of Stock
                      </span>
                    <?php endif; ?>

                    <!-- Action Button -->
                    <form action="index-product_view-page-4-AA" method="GET" class="mt-2">
                      <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                      <button type="submit"
                        class="w-full bg-black hover:bg-gray-800 text-white text-xs py-2 px-3 rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-eye"></i>
                        <span>View Product</span>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 bg-gray-50 border-t shrink-0">
          <button onclick="window.location.href='allproduct'"
            class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            View All Products
          </button>
        </div>
      </div>

      <style>
        /* Line clamp utilities */
        .line-clamp-2 {
          display: -webkit-box;
          -webkit-line-clamp: 2;
          line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }
      </style>
    <?php endif; ?>

    <!-- Login Modal - Full Screen on Mobile -->
    <div x-show="loginOpen" x-cloak x-transition
      class="fixed inset-0 z-9999 flex items-center justify-center bg-black/40 bg-opacity-50 p-4 lg:hidden">

      <div class="bg-white w-full max-w-md max-h-[95vh] overflow-y-auto rounded-lg shadow-lg relative">

        <!-- Modal Header -->
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-800">Logins</h2>
          <button @click="loginOpen = false" class="text-gray-500 hover:text-gray-800 text-2xl font-bold p-1">
            &times;
          </button>
        </div>

        <!-- Modal Content -->
        <div class="p-6">
          <form x-data="loginForm()" @submit.prevent="handleLogin($event)" class="space-y-4">
            <div>
              <label for="mobile_login" class="block text-sm font-medium text-gray-600 mb-2">Email or Mobile</label>
              <input type="text" id="mobile_login" name="login" x-model="loginInput" @input="checkLoginType"
                autocomplete="mob-and-email" placeholder="you@example.com or 09123456789" required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div x-show="(isMobile) || (isEmail && otpVerified)" x-transition class="space-y-2">
              <label for="mobile_password" class="block text-sm font-medium text-gray-600">Password</label>
              <input type="password" id="mobile_password" name="password" x-model="password"
                autocomplete="password-auto"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div x-show="isEmail && !otpSent && !otpVerified" x-transition class="space-y-2">
              <label class="block text-sm font-medium text-gray-600">OTP Verification</label>
              <button type="button" @click="sendOTP" :disabled="otpLoading || resendCooldown > 0"
                class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center space-x-2">

                <template x-if="!otpLoading && resendCooldown === 0">
                  <span>Send OTP</span>
                </template>

                <template x-if="otpLoading">
                  <div class="flex items-center space-x-2">
                    <!-- Spinner -->
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Verifying...</span>
                  </div>
                </template>

                <template x-if="!otpLoading && resendCooldown > 0">
                  <span>Resend in <span x-text="resendCooldown"></span>s</span>
                </template>
              </button>
            </div>


            <div x-show="otpSent && !otpVerified" x-transition class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600 mb-2">Enter OTP</label>
                <p class="text-xs text-gray-500 mb-3">We sent a verification code to your email</p>
                <input type="text" x-model="otp" maxlength="6"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-center text-lg tracking-widest"
                  placeholder="000000">
              </div>

              <div class="flex gap-3">
                <button type="button" @click="cancelOTP"
                  class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">Cancel</button>
                <button type="button" @click="verifyOTP" :disabled="!otp || otp.length < 4"
                  class="flex-1 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:bg-orange-300 transition font-medium">
                  Verify
                </button>
              </div>

              <div class="text-center">
                <template x-if="resendCooldown > 0">
                  <p class="text-sm text-gray-500">Resend in <span x-text="resendCooldown"></span>s</p>
                </template>
                <template x-if="resendCooldown === 0">
                  <button @click="sendOTP" class="text-blue-500 hover:underline text-sm font-medium" type="button">
                    Resend OTP
                  </button>
                </template>
              </div>
            </div>

            <div class="flex items-center gap-2" x-show="isMobile">
              <input type="checkbox" id="mobile_remember" name="remember" class="h-4 w-4 text-orange-500 rounded">
              <label for="mobile_remember" class="text-sm text-gray-600">Remember me</label>
            </div>

            <button type="submit" :disabled="submitLoading"
              class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white font-semibold py-3 px-4 rounded-lg transition"
              x-show="(isMobile) || (isEmail && otpVerified)">
              <span x-show="!submitLoading">Log In</span>
              <span x-show="submitLoading">Logging in...</span>
            </button>

            <div x-show="errorMessage" x-transition
              class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
              <span x-text="errorMessage"></span>
            </div>

            <div x-show="successMessage" x-transition
              class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
              <span x-text="successMessage"></span>
            </div>

            <div class="text-center space-y-3 pt-4 border-t border-gray-200">
              <div>
                <a href="../forgot_password.php" class="text-orange-500 hover:underline text-sm font-medium">Forgot
                  password?</a>
              </div>
              <div>
                <span class="text-sm text-gray-600">Don't have an account?</span>
                <a href="#" @click.prevent="registerOpen = true; loginOpen = false"
                  class="text-orange-500 hover:underline font-medium text-sm">Register</a>
              </div>
            </div>

            <div class="pt-4 border-t border-gray-200">
              <a href="javascript:void(0)" onclick="openGooglePopup('../google-login.php')"
                class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-lg transition">
                <svg class="w-5 h-5 bg-white rounded-full p-[2px]" viewBox="0 0 48 48">
                  <path fill="#EA4335"
                    d="M24 9.5c3.5 0 6.3 1.2 8.3 3.2l6.2-6.2C34.8 2.6 29.7 0 24 0 14.8 0 6.8 5.9 3.2 14.1l7.3 5.7C12.7 13.2 17.9 9.5 24 9.5z" />
                  <path fill="#34A853"
                    d="M24 48c6.5 0 12-2.1 16.1-5.7l-7.4-6.1C30.5 38.7 27.5 40 24 40c-6 0-11.2-3.7-13.4-8.8l-7.2 5.5C6.3 43.8 14.6 48 24 48z" />
                  <path fill="#FBBC05"
                    d="M43.6 20H24v8.4h11.3c-1.1 3.2-3.4 5.8-6.5 7.6l7.4 6.1c4.3-4 6.8-9.9 6.8-17.1 0-1.2-.1-2.3-.3-3.4z" />
                  <path fill="#4285F4"
                    d="M10.6 29.6C9.7 27.2 9.2 24.7 9.2 22s.5-5.2 1.4-7.6l-7.4-5.7C1.1 13.6 0 17.7 0 22c0 4.2 1.1 8.3 3.2 11.8l7.4-4.2z" />
                </svg>
                Login with Google
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>

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
          <form action="../register.php" method="POST" class="space-y-4">
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


  <section>
    <!-- New Products Modal - List Style with Scroll Buttons -->
    <?php if (count($newProducts) > 0): ?>
      <!-- Modal Overlay -->
      <div x-show="newProductsModal" x-cloak @click.self="newProductsModal = false"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-9999 bg-black bg-opacity-50 flex items-center justify-center p-4">

        <!-- Modal Content -->
        <div x-show="newProductsModal" x-transition:enter="transition ease-out duration-300"
          x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
          x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
          x-transition:leave-end="opacity-0 scale-95" @click.stop x-data="{ showScrollUp: false, showScrollDown: true }"
          class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col relative">

          <!-- Modal Header -->
          <div class="flex justify-between items-center p-4 border-b bg-black text-white shrink-0">
            <div class="flex items-center gap-3">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
              </svg>
              <div>
                <h2 class="text-base sm:text-lg">New Products</h2>
                <p class="text-xs text-white">Latest additions to our store</p>
              </div>
            </div>
            <button @click="newProductsModal = false"
              class="text-white text-2xl w-8 h-8 flex items-center justify-center transition-all">
              <i class="fa-solid fa-circle-xmark"></i>
            </button>
          </div>

          <!-- Scroll Up Button -->
          <button x-show="showScrollUp" x-transition @click="$refs.modalBody.scrollBy({ top: -300, behavior: 'smooth' })"
            class="absolute top-20 right-4 z-10 bg-black hover:bg-orange-600 text-white p-2 rounded-full shadow-lg transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
          </button>

          <!-- Modal Body - List View -->
          <div x-ref="modalBody" @scroll="
          showScrollUp = $el.scrollTop > 100;
          showScrollDown = $el.scrollTop < ($el.scrollHeight - $el.clientHeight - 100);
        " class="flex-1 overflow-y-auto" style="-webkit-overflow-scrolling: touch;">
            <div class="divide-y divide-gray-200">
              <?php foreach ($newProducts as $index => $product): ?>
                <div class="p-4 hover:bg-orange-50 transition-all duration-200 group">
                  <div class="flex gap-4">
                    <!-- Product Image -->
                    <div class="shrink-0 relative">
                      <?php if (!empty($product['main_image'])): ?>
                        <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                          alt="<?php echo htmlspecialchars($product['name']); ?>"
                          class="w-20 h-20 sm:w-24 sm:h-24 object-contain rounded-lg border border-gray-200 group-hover:border-orange-300 transition"
                          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div
                          class="w-20 h-20 sm:w-24 sm:h-24 bg-gray-100 rounded-lg hidden items-center justify-center border border-gray-200">
                          <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                              clip-rule="evenodd" />
                          </svg>
                        </div>
                      <?php else: ?>
                        <div
                          class="w-20 h-20 sm:w-24 sm:h-24 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                          <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                              clip-rule="evenodd" />
                          </svg>
                        </div>
                      <?php endif; ?>

                      <!-- NEW Badge -->
                      <span
                        class="absolute -top-1 -right-1 bg-red-600 text-white text-[9px] px-1.5 py-0.5 rounded-full shadow-sm">
                        NEW
                      </span>
                    </div>

                    <!-- Product Info -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-start justify-between gap-2 mb-2">
                        <h3
                          class="uppercase text-sm sm:text-base text-gray-900 group-hover:text-orange-600 transition line-clamp-2">
                          <?php echo htmlspecialchars($product['name']); ?>
                        </h3>

                        <!-- Stock Status Badge -->
                        <?php if ($product['stock_status'] === 'In Stock'): ?>
                          <span
                            class="inline-flex items-center gap-1 text-[10px] text-green-600 bg-green-50 px-2 py-0.5 rounded-full whitespace-nowrap shrink-0">
                            <span class="w-1.5 h-1.5 bg-green-600 rounded-full"></span>
                            In Stock
                          </span>
                        <?php else: ?>
                          <span
                            class="inline-flex items-center gap-1 text-[10px] text-red-600 bg-red-50 px-2 py-0.5 rounded-full whitespace-nowrap shrink-0">
                            <span class="w-1.5 h-1.5 bg-red-600 rounded-full"></span>
                            Out of Stock
                          </span>
                        <?php endif; ?>
                      </div>


                      <!-- Meta Info -->
                      <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
                        <span class="flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                          </svg>
                          <?php echo htmlspecialchars($product['codename']); ?>
                        </span>
                        <span class="text-gray-300">•</span>
                        <span class="flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                        </span>
                      </div>

                      <!-- Action Button -->
                      <form action="index-product_view-page-4-AA" method="GET">
                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                        <button type="submit"
                          class="inline-flex items-center gap-2 bg-black hover:bg-orange-600 text-white text-xs py-2 px-4 transition-all">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                          </svg>
                          View Details
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Scroll Down Button -->
          <button x-show="showScrollDown" x-transition @click="$refs.modalBody.scrollBy({ top: 300, behavior: 'smooth' })"
            class="absolute bottom-20 right-4 z-10 bg-black hover:bg-orange-600 text-white p-2 rounded-full shadow-lg transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Modal Footer -->
          <div class="p-4 bg-gray-50 border-t shrink-0">
            <div class="flex flex-col sm:flex-row gap-2">
              <button onclick="window.location.href='../otherpage/index-allproduct-page-3.php'"
                class="flex-1 bg-black hover:bg-orange-600 text-white py-2.5 px-4 transition-all flex items-center justify-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                View All Products
              </button>
              <button @click="newProductsModal = false"
                class="sm:w-auto px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 transition-all text-sm">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>

      <style>
        /* Smooth scrollbar for modal */
        .overflow-y-auto::-webkit-scrollbar {
          width: 6px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
          background: #f9fafb;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
          background: #d1d5db;
          border-radius: 3px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
          background: #9ca3af;
        }

        /* Line clamp utilities */
        .line-clamp-2 {
          display: -webkit-box;
          -webkit-line-clamp: 2;
          line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }
      </style>

      <script>
        //Auto-refresh new products count
        setInterval(function () {
          fetch(window.location.pathname + '?action=get_new_products_count')
            .then(response => {
              const contentType = response.headers.get('content-type');
              if (contentType && contentType.includes('application/json')) {
                return response.json();
              }
              throw new Error('Not JSON');
            })
            .then(data => {
              const badge = document.querySelector('button[\\@click="newProductsModal = true"] span');
              if (badge && data.count !== undefined) {
                badge.textContent = data.count;
                badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
              }
            })
            .catch(error => {
              console.log('Silent error:', error);
            });
        }, 30000); // Every 30 seconds
      </script>
    <?php endif; ?>
  </section>

</nav>



<script src="../navbar/js/top-obf.js?v=<?= file_exists($top_js) ? md5_file($top_js) : '1' ?>"></script>
<script src="../navbar/js/topcart.obfuscated.js?v=<?= file_exists($cart_js) ? md5_file($cart_js) : '1' ?>"></script>
<script src="../navbar/js/noble-fcm.js?v=<?= filemtime('../navbar/js/noble-fcm.js') ?>"></script>

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
</script>

<script>

  function showGuestLoginAlert() {
    showNotification('Please login to proceed', 'info');

    setTimeout(() => {
      document.querySelector('button[\\@click="loginOpen = true"]')?.click() ||
        document.querySelector('button[\\@click="loginOpen = !loginOpen"]')?.click();
    }, 500);
  }

  lucide.createIcons(); // initialize icons

  // ✅ SIMPLE FIX - Only fetch when there's data
  // Replace existing notification system with this

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

        fetch("../navbar/topcheck_getnotif.php", {
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
              console.log(`[NOTIF] Found ${this.unreadCount} unread - will check again in 5s`);
              this.scheduleNextPoll(5000); // Check every 5 seconds when there's data
            } else {
              console.log(`[NOTIF] No notifications - stopping polls`);
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
        fetch("../navbar/topcheck_getmarked.php", {
          method: "POST",
          credentials: 'include'
        })
          .then(res => res.json())
          .then(data => {
            this.unreadCount = 0;
            console.log('[NOTIF] Marked as read');
          })
          .catch(error => console.error('[NOTIF] Error:', error));
      },

      clearNotifications() {
        if (!confirm('Clear all notifications?')) return;

        fetch("../navbar/topcheck_clearall.php", {
          method: "POST",
          credentials: 'include'
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              this.notifications = [];
              this.unreadCount = 0;
              this.notifOpen = false;
              console.log('[NOTIF] All cleared');
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
        console.log('[NOTIF] System initialized - will only poll when notifs exist');

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

    // Chat notification system - same logic
    Alpine.data('chatNotif', () => ({
      unreadCount: 0,
      pollTimer: null,
      isPageVisible: true,
      isFetching: false,

      fetchUnread() {
        if (!this.isPageVisible) return;
        if (this.isFetching) return;

        this.isFetching = true;

        fetch('../otherpage/chat-chat_get_unread-page-9-A.php', {
          credentials: 'include',
          signal: AbortSignal.timeout(10000)
        })
          .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
          })
          .then(data => {
            this.unreadCount = data.unread_count || 0;

            // Only poll if may unread messages
            if (this.unreadCount > 0) {
              console.log(`[CHAT] Found ${this.unreadCount} unread - checking again in 5s`);
              this.scheduleNextPoll(5000);
            } else {
              console.log(`[CHAT] No unread messages - stopping polls`);
            }
          })
          .catch(error => {
            console.error('[CHAT] Error:', error);
            this.scheduleNextPoll(30000);
          })
          .finally(() => {
            this.isFetching = false;
          });
      },

      scheduleNextPoll(interval = 5000) {
        if (this.pollTimer) {
          clearTimeout(this.pollTimer);
          this.pollTimer = null;
        }

        if (!this.isPageVisible) return;

        this.pollTimer = setTimeout(() => {
          this.fetchUnread();
        }, interval);
      },

      init() {
        console.log('[CHAT] System initialized - will only poll when unread exist');

        this.fetchUnread();

        document.addEventListener('visibilitychange', () => {
          this.isPageVisible = !document.hidden;

          if (this.isPageVisible) {
            console.log('[CHAT] Page visible - fetching unread');
            this.fetchUnread();
          } else {
            console.log('[CHAT] Page hidden - clearing polls');
            if (this.pollTimer) {
              clearTimeout(this.pollTimer);
              this.pollTimer = null;
            }
          }
        });

        window.addEventListener('beforeunload', () => {
          if (this.pollTimer) clearTimeout(this.pollTimer);
        });
      },
    }));
  });
</script>