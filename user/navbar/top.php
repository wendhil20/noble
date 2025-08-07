<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include '../../connection/connect.php';

$cart = $_SESSION['cart'] ?? [];
$total_cart_items = 0;
$user_id = $_SESSION['user_id'] ?? null;
foreach ($cart as $item) {
  $total_cart_items += $item['quantity'];
}

// ✅ Retrieve user info (REMOVE the duplicate assignment that causes the warning)
// $user_id = $_SESSION['user_id']; // ❌ REMOVE THIS LINE - it causes the warning
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

// Get cart items count and data
if ($user_id) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_cart_items WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_cart_items = $count_row['count'] ?? 0;
    $count_stmt->close();
}

?>

<?php $current_page = basename($_SERVER['PHP_SELF']); ?>

<!-- Tailwind + Alpine CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
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
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          // Sans-serif fonts
          poppins: ['Poppins', 'sans-serif'],
          inter: ['Inter', 'sans-serif'],
          lato: ['Lato', 'sans-serif'],
          opensans: ['"Open Sans"', 'sans-serif'],
          source: ['"Source Sans Pro"', 'sans-serif'],
          raleway: ['Raleway', 'sans-serif'],
          nunito: ['Nunito', 'sans-serif'],
          mont: ['Montserrat', 'sans-serif'],
          roboto: ['Roboto', 'sans-serif'],
          quicksand: ['Quicksand', 'sans-serif'],
          work: ['"Work Sans"', 'sans-serif'],
          rubik: ['Rubik', 'sans-serif'],
          fira: ['"Fira Sans"', 'sans-serif'],
          ubuntu: ['Ubuntu', 'sans-serif'],
          barlow: ['Barlow', 'sans-serif'],
          manrope: ['Manrope', 'sans-serif'],
          dmsans: ['"DM Sans"', 'sans-serif'],
          space: ['"Space Grotesk"', 'sans-serif'],

          // Serif fonts
          merri: ['Merriweather', 'serif'],
          playfair: ['"Playfair Display"', 'serif'],
          libre: ['"Libre Baskerville"', 'serif'],
          crimson: ['"Crimson Text"', 'serif'],
          garamond: ['"EB Garamond"', 'serif'],
          lora: ['Lora', 'serif'],

          // Display/Decorative fonts
          vibes: ['"Great Vibes"', 'cursive'],
          dancing: ['"Dancing Script"', 'cursive'],
          pacifico: ['Pacifico', 'cursive'],
          lobster: ['Lobster', 'cursive'],
          oswald: ['Oswald', 'sans-serif'],
          bebas: ['"Bebas Neue"', 'sans-serif'],
          anton: ['Anton', 'sans-serif'],
        }
      }
    }
  }


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
    }, 3000);
  }
</script>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none;"
  class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]">

  <div class="rounded-2xl p-8 flex flex-col items-center space-y-5  relative">

    <!-- Spinner wrapper -->
    <div class="relative w-28 h-28 flex items-center justify-center">
      <!-- Rotating spinner around image -->
      <div class="absolute inset-0 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>

      <!-- Center image -->
      <img src="../img/logo.png" alt="Loading" class="bg-white w-20 h-20 object-contain rounded-full shadow-md z-10 p-2" />
    </div>
  </div>
</div>
<div class="bg-black text-white py-3 text-xs sm:text-sm">
        <div class="container mx-auto px-4">
            <!-- Mobile Layout (Stack vertically) -->
            <div class="flex flex-col gap-3 sm:hidden">
                <!-- Contact Info - Mobile -->
                <div class="flex flex-col items-center gap-1 text-center">
                    <a href="tel:(02)123-4567" class="hover:text-orange-300 transition">
                        Support: (02) 123-4567
                    </a>
                    <a href="mailto:info@noblehome.com" class="hover:text-orange-300 transition">
                        info@noblehome.com
                    </a>
                </div>
                
                <!-- Links - Mobile -->
                <div class="flex justify-center items-center gap-4">
                    <a href="javascript:void(0)" onclick="navigateWithLoading('help')"
                       class="hover:text-orange-300 transition inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Help
                    </a>
                    
                    <span class="text-gray-400">|</span>
                    
                    <a href="javascript:void(0)" onclick="navigateWithLoading('support')"
                       class="hover:text-orange-300 transition inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5zM8.25 12l7.5 0" />
                        </svg>
                        Support
                    </a>
                    
                    <span class="text-gray-400">|</span>
                    
                    <a href="../about/about.php" class="text-white hover:text-orange-300 transition">
                        About
                    </a>
                </div>
            </div>
            
            <!-- Desktop Layout (Side by side) -->
            <div class="hidden sm:flex sm:justify-between sm:items-center">
                <!-- Left: Contact Info -->
                <div class="flex items-center gap-4">
                    <a href="tel:(02)123-4567" class="hover:text-orange-300 transition">
                        Support: (02) 123-4567
                    </a>
                    <a href="mailto:info@noblehome.com" class="hover:text-orange-300 transition">
                        info@noblehome.com
                    </a>
                </div>
                
                <!-- Right: Links -->
                <div class="flex items-center gap-4">
                    <a href="javascript:void(0)" onclick="navigateWithLoading('help')"
                       class="hover:text-orange-300 transition inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Help
                    </a>
                    
                    <span class="text-gray-400">|</span>
                    
                    <a href="javascript:void(0)" onclick="navigateWithLoading('support')"
                       class="hover:text-orange-300 transition inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5zM8.25 12l7.5 0" />
                        </svg>
                        Support
                    </a>
                    
                    <span class="text-gray-400">|</span>
                    
                    <a href="../about/about.php" class="text-white hover:text-orange-300 transition">
                        About
                    </a>
                </div>
            </div>
        </div>
    </div>


<!-- Navigation -->
<nav x-data="{ 
    mobileOpen: false, 
    loginOpen: false, 
    registerOpen: false,
    productsOpen: false,
    profileOpen: false,
    selectedCategory: null
}" class="bg-white shadow-lg sticky top-0 z-50 text-black font-bold">

  <div class="max-w-full mx-auto px-3 sm:px-4 lg:px-6">
    <div class="flex justify-between items-center py-3 sm:py-4">

      <!-- Logo - Made more compact on mobile -->
      <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/index.php')"
        class="flex items-center space-x-2 sm:space-x-3 hover:opacity-80 transition duration-200 flex-shrink-0">

        <div class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 overflow-hidden">
          <img src="../img/logo.png" alt="Noble Home Logo" class="w-full h-full object-contain">
        </div>
        <div class="leading-snug">
          <span class="block text-sm sm:text-lg lg:text-xl font-extrabold text-orange-400 tracking-tight font-mont">
            NobleHome
          </span>
          <span class="block text-xs text-gray-800 tracking-tight font-semibold font-mont">
            Depot
          </span>
        </div>
      </a>

      <!-- Mobile Cart & User Icons (visible on mobile before hamburger) -->
      <div class="flex items-center space-x-3 lg:hidden">

        <!-- Mobile Cart Icon -->
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/cart_view')"
          class="relative p-2 hover:bg-gray-100 rounded-full transition">
          <img src="../img/ecommerce.png" alt="Cart" class="w-5 h-5 object-contain" />
          <span class="cart-count absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full font-bold leading-none <?= $total_cart_items > 0 ? '' : 'hidden' ?>">
            <span class="cart-count" data-cart-count><?= $total_cart_items ?></span>
          </span>
        </a>

        <!-- Mobile User Icon -->
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="relative">
            <button @click="profileOpen = !profileOpen" class="flex items-center focus:outline-none p-1">
              <div class="w-7 h-7 rounded-full overflow-hidden border border-gray-300 bg-gray-100">
                <?php if (!empty($_SESSION['user_picture'])): ?>
                  <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>" alt="Profile" class="w-full h-full object-cover">
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
        <button @click="mobileOpen = !mobileOpen" class="text-gray-700 focus:outline-none p-2 hover:bg-gray-100 rounded-lg transition">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              :d="mobileOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'" />
          </svg>
        </button>
      </div>

      <!-- Desktop Navigation -->
      <div class="hidden lg:flex space-x-6 items-center uppercase">

        <!-- Search -->
        <div x-data="{
            search: '',
            results: [],
            fetchResults() {
                if (this.search.trim() === '') return;
                fetch(`../otherpage/search_ajax.php?search=${encodeURIComponent(this.search)}`)
                    .then(res => res.json())
                    .then(data => {
                        this.results = data;
                    });
            }
        }" class="relative">

          <div class="flex items-center space-x-2 font-mont">
            <input
              type="text"
              x-model="search"
              @keydown.enter="fetchResults"
              placeholder="Search products..."
              class="border border-gray-300 px-3 py-1.5 rounded w-48 md:w-64 text-sm outline-orange-500">
            <button
              @click="fetchResults"
              class="bg-orange-400 text-white px-4 py-1.5 rounded text-sm hover:bg-orange-600 transition">
              Search
            </button>
          </div>

          <div
            x-show="results.length > 0"
            x-cloak
            @click.away="results = []"
            class="absolute z-50 bg-white shadow-lg rounded mt-2 w-64 md:w-96 max-h-80 overflow-y-auto border border-gray-200">
            <ul>
              <template x-for="item in results" :key="item.id">
                <li class="border-b last:border-0">
                  <a
                    class="flex items-center gap-3 px-4 py-2 hover:bg-orange-100 text-sm text-gray-700"
                    :href="'../otherpage/shop.php?search=' + encodeURIComponent(item.product_name)">
                    <img :src="item.main_image" alt="" class="w-10 h-10 object-contain rounded border border-gray-300">
                    <span x-text="item.product_name"></span>
                  </a>
                </li>
              </template>
            </ul>
          </div>
        </div>

        <!-- Products Dropdown -->
        <div class="relative">
          <button @click="productsOpen = !productsOpen" class="text-black hover:text-orange-500 transition font-mont uppercase">Products</button>

          <div x-show="productsOpen" @click.away="productsOpen = false" x-transition x-cloak
            class="absolute left-0 mt-2 bg-white shadow-lg rounded-lg flex w-80 z-50">
            <div class="w-1/2 border-r p-4 space-y-2 font-mont">
              <button @mouseenter="selectedCategory = 'materials'"
                class="block w-full text-left hover:text-orange-500 text-sm">Materials</button>
              <button @mouseenter="selectedCategory = 'furniture'"
                class="block w-full text-left hover:text-orange-500 text-sm">Furniture</button>
            </div>

            <div class="w-1/2 p-4 font-mont">
              <template x-if="selectedCategory === 'materials'">
                <div class="space-y-1">
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500 text-sm">WPC Panels</a>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500 text-sm">PVC Panels</a>
                </div>
              </template>

              <template x-if="selectedCategory === 'furniture'">
                <div class="space-y-1">
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500 text-sm">Chairs</a>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500 text-sm">Tables</a>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Profile Link -->
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/profile')"
          class="<?= $current_page == 'profile' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition font-mont">
          Profile
        </a>

        <!-- Shop Link -->
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/shop')"
          class="<?= $current_page == 'shop' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition inline-flex items-center gap-1 font-mont">
          <img src="../img/shopping-cart.png" alt="Shop Icon" class="w-5 h-5 object-contain" />
          Shop
        </a>

        <!-- Cart Link with Hover Modal -->
<div class="relative" id="cart-container">
  <a href="javascript:void(0)"
    onclick="navigateWithLoading('../otherpage/cart_view')"
    class="<?= $current_page == 'cart/cart_view' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition inline-flex items-center gap-1 relative font-mont p-2 rounded-lg hover:bg-orange-50"
    id="cart-link">
    <img src="../img/ecommerce.png" alt="Cart Icon" class="w-5 h-5 object-contain" />
    Cart
    <span id="cart-count-bubble" class="cart-count absolute -top-1 -right-2 bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold leading-none <?= $total_cart_items > 0 ? '' : 'hidden' ?>">
      <span class="cart-count" data-cart-count><?= $total_cart_items ?></span>
    </span>
  </a>

  <!-- Cart Hover Modal -->
  <div id="cart-modal" class="cart-modal fixed right-4 top-16 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-[9999] max-h-[80vh] overflow-hidden max-w-[calc(100vw-2rem)] opacity-0 invisible">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4 rounded-t-xl">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-lg flex items-center gap-2">
          <i class="fas fa-shopping-cart"></i>
          Your Cart
        </h3>
        <div class="flex items-center gap-2">
          <span class="bg-white/20 px-2 py-1 rounded-full text-sm font-medium" id="modal-cart-count">
            <?= $total_cart_items ?> items
          </span>
          <!-- Refresh Button -->
          <button onclick="refreshCart()" id="refresh-cart-btn" class="bg-white/20 hover:bg-white/30 p-1.5 rounded-full transition-all duration-200" title="Refresh Cart">
            <i class="fas fa-sync-alt text-sm"></i>
          </button>
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
      <?php if ($total_cart_items > 0): ?>
        <div class="space-y-3">
          <?php
          // Fetch cart items for modal display
          $modal_stmt = $conn->prepare("
                SELECT c.*, t.type_image, v.descrip6, v.descrip7
                FROM user_cart_items c
                LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
                LEFT JOIN product_variants v ON c.variant_id = v.id
                WHERE c.user_id = ?
            ");
          $modal_stmt->bind_param("i", $user_id);
          $modal_stmt->execute();
          $modal_result = $modal_stmt->get_result();

          while ($item = $modal_result->fetch_assoc()):
            $unit_price = floatval($item['price']);
            $quantity = intval($item['quantity']);
          ?>
            <div class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
              <?php if (!empty($item['type_image'])): ?>
                <img src="../../<?= htmlspecialchars($item['type_image']) ?>" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-cover rounded-lg flex-shrink-0">
              <?php else: ?>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                  <i class="fas fa-image text-gray-400 text-xs"></i>
                </div>
              <?php endif; ?>

              <div class="flex-1 min-w-0">
                <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate"><?= htmlspecialchars($item['codename']) ?></h4>
                <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                  <?= htmlspecialchars($item['variant_name'] ?: '') ?>
                  <?= !empty($item['color_name']) ? ', ' . htmlspecialchars($item['color_name']) : '' ?>
                  <?= !empty($item['size']) ? ', ' . htmlspecialchars($item['size']) : '' ?>
                </p>
                <div class="flex items-center justify-between mt-1">
                  <span class="text-xs sm:text-sm font-semibold text-orange-600">₱<?= number_format($unit_price, 2) ?></span>
                  <span class="text-[10px] sm:text-xs text-gray-500">Qty: <?= $quantity ?></span>
                </div>
              </div>

              <a href="javascript:void(0)" onclick="removeFromCart(<?= $item['id'] ?>)" class="text-red-500 hover:text-red-700 transition p-1 flex-shrink-0">
                <i class="fas fa-times text-xs"></i>
              </a>
            </div>
          <?php endwhile;
          $modal_stmt->close();
          ?>
        </div>

        <!-- Show all items, no limit indicator needed -->
      <?php else: ?>
        <!-- Empty Cart -->
        <div class="text-center py-8">
          <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
          <p class="text-gray-500 text-sm">Your cart is empty</p>
          <a href="shop.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">
            Start Shopping
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Modal Footer -->
    <?php if ($total_cart_items > 0): ?>
      <div class="border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl" id="cart-footer">
        <!-- Total Price -->
        <div class="flex justify-between items-center mb-3">
          <span class="font-medium text-sm text-gray-700">Total:</span>
          <span class="font-bold text-base sm:text-lg text-orange-600" id="cart-total">
            ₱<?php
              // Calculate total for modal
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
          <a href="../otherpage/cart_view.php"
            class="bg-white border border-orange-500 text-orange-600 px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-center hover:bg-orange-50 transition">
            View Cart
          </a>
          <a href="checkout.php"
            class="bg-orange-500 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-center hover:bg-orange-600 transition">
            Checkout
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="topcart-obf.js"></script>

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

  /* Hide scrollbar for cart items container */
  #cart-items-container {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
  }

  #cart-items-container::-webkit-scrollbar {
    display: none; /* WebKit */
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

    .cart-modal .space-y-3 > * + * {
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
      max-height: 12rem !important; /* Reduce max height */
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

        <!-- User Authentication -->
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="relative">
            <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 focus:outline-none">
              <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-300 bg-gray-100">
                <?php if (!empty($_SESSION['user_picture'])): ?>
                  <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-orange-100">
                    <span class="text-xs font-bold text-orange-800 font-mont">
                      <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
            </button>

            <div x-show="profileOpen" x-cloak @click.outside="profileOpen = false" x-transition
              class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-md shadow-lg z-50">
              <div class="py-2 px-2 text-sm text-gray-700 border-b max-w-full overflow-x-auto whitespace-nowrap">
                <span class="block w-max"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
              </div>
              <a href="../logout.php" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="relative">
            <button @click="loginOpen = !loginOpen" class="text-black hover:text-orange-500 transition flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Login
            </button>

            <!-- Desktop Login Dropdown -->
            <div x-show="loginOpen" @click.away="loginOpen = false" x-transition x-cloak
              class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg p-6 z-50">

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
                  <input type="password" id="password" name="password" x-model="password"
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <!-- OTP Send Button (shown for email before OTP is sent) -->
                <div x-show="isEmail && !otpSent && !otpVerified" x-transition>
                  <label class="block text-sm font-medium text-gray-600 mb-2">OTP Verification</label>
                  <button
                    type="button"
                    @click="sendOTP"
                    :disabled="otpLoading || resendCooldown > 0"
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
                          <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
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
                <button
                  type="submit"
                  :disabled="submitLoading"
                  x-show="(isMobile || (isEmail && otpVerified))"
                  class="w-full mb-4 bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white font-semibold py-2 px-4 rounded-lg">
                  <span x-show="!submitLoading">Log In</span>
                  <span x-show="submitLoading">Logging in...</span>
                </button>

                <!-- Error/Success Messages -->
                <div x-show="errorMessage" x-transition class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                  <span x-text="errorMessage"></span>
                </div>

                <div x-show="successMessage" x-transition class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                  <span x-text="successMessage"></span>
                </div>

                <!-- Additional Links -->
                <div class="text-center text-xs mb-2">
                  <a href="forgot_password" class="text-orange-500 hover:underline">Forgot password?</a>
                </div>

                <div class="text-center text-xs mb-4">
                  <span>Don't have an account?</span>
                  <a href="#" @click.prevent="registerOpen = true; loginOpen = false" class="text-orange-500 hover:underline font-medium">Register</a>
                </div>

                <!-- Google Login -->
                <div class="text-center">
                  <a href="../google-login.php"
                    class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg">
                    <svg class="w-5 h-5 bg-white rounded-full p-[2px]" viewBox="0 0 48 48">
                      <path fill="#EA4335" d="M24 9.5c3.5 0 6.3 1.2 8.3 3.2l6.2-6.2C34.8 2.6 29.7 0 24 0 14.8 0 6.8 5.9 3.2 14.1l7.3 5.7C12.7 13.2 17.9 9.5 24 9.5z" />
                      <path fill="#34A853" d="M24 48c6.5 0 12-2.1 16.1-5.7l-7.4-6.1C30.5 38.7 27.5 40 24 40c-6 0-11.2-3.7-13.4-8.8l-7.2 5.5C6.3 43.8 14.6 48 24 48z" />
                      <path fill="#FBBC05" d="M43.6 20H24v8.4h11.3c-1.1 3.2-3.4 5.8-6.5 7.6l7.4 6.1c4.3-4 6.8-9.9 6.8-17.1 0-1.2-.1-2.3-.3-3.4z" />
                      <path fill="#4285F4" d="M10.6 29.6C9.7 27.2 9.2 24.7 9.2 22s.5-5.2 1.4-7.6l-7.4-5.7C1.1 13.6 0 17.7 0 22c0 4.2 1.1 8.3 3.2 11.8l7.4-4.2z" />
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

    <!-- Mobile Menu -->
    <div x-show="mobileOpen" x-cloak @click.away="mobileOpen = false" x-transition
      class="lg:hidden border-t border-gray-200 py-4 space-y-4 bg-white">

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
              fetch(`../otherpage/search_ajax.php?search=${encodeURIComponent(this.search)}`)
                  .then(res => res.json())
                  .then(data => {
                      this.results = data;
                      this.searchOpen = true;
                  });
          }
      }" class="px-4">
        <div class="flex items-center space-x-2">
          <input
            type="text"
            x-model="search"
            @input="fetchResults"
            @keydown.enter="fetchResults"
            @focus="searchOpen = true"
            placeholder="Search products..."
            class="flex-1 border border-gray-300 px-3 py-3 rounded-lg text-sm outline-orange-500 focus:border-orange-500">
          <button
            @click="fetchResults"
            class="bg-orange-400 text-white px-4 py-3 rounded-lg text-sm hover:bg-orange-600 transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5A7 7 0 11 1 9a7 7 0 0112 0z"></path>
            </svg>
          </button>
        </div>

        <div x-show="searchOpen && results.length > 0" x-cloak x-transition @click.away="searchOpen = false"
          class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
          <template x-for="item in results" :key="item.id">
            <a :href="'../otherpage/shop.php?search=' + encodeURIComponent(item.product_name)"
              class="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 border-b last:border-0">
              <img :src="item.main_image" alt="" class="w-10 h-10 object-contain rounded border border-gray-200">
              <span x-text="item.product_name" class="text-sm text-gray-700 flex-1"></span>
            </a>
          </template>
        </div>
      </div>

      <!-- Mobile Links -->
      <div class="space-y-1 px-4">
        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/profile')"
          class="block py-3 px-2 text-gray-700 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition font-mont">Profile</a>

        <a href="javascript:void(0)" onclick="navigateWithLoading('../otherpage/shop')"
          class="flex items-center gap-3 py-3 px-2 text-gray-700 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition font-mont">
          <img src="../img/shopping-cart.png" alt="Shop" class="w-5 h-5 object-contain" />
          Shop
        </a>

        <!-- Mobile Products -->
        <div class="py-2">
          <button @click="productsOpen = !productsOpen"
            class="w-full text-left py-3 px-2 text-gray-700 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition font-mont flex items-center justify-between">
            <span>Products</span>
            <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': productsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <div x-show="productsOpen" x-cloak x-transition class="mt-2 ml-4 space-y-2">
            <div>
              <button @click="selectedCategory = selectedCategory === 'materials' ? null : 'materials'"
                class="flex items-center justify-between w-full text-left py-2 px-3 text-sm text-gray-600 hover:text-orange-500 hover:bg-orange-50 rounded">
                <span>Materials</span>
                <svg class="w-3 h-3 transform transition-transform" :class="{ 'rotate-180': selectedCategory === 'materials' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
              <div x-show="selectedCategory === 'materials'" x-cloak x-transition class="ml-4 mt-1 space-y-1">
                <a href="#" class="block py-2 px-3 text-xs text-gray-500 hover:text-orange-500 hover:bg-orange-50 rounded">WPC Panels</a>
                <a href="#" class="block py-2 px-3 text-xs text-gray-500 hover:text-orange-500 hover:bg-orange-50 rounded">PVC Panels</a>
              </div>
            </div>

            <div>
              <button @click="selectedCategory = selectedCategory === 'furniture' ? null : 'furniture'"
                class="flex items-center justify-between w-full text-left py-2 px-3 text-sm text-gray-600 hover:text-orange-500 hover:bg-orange-50 rounded">
                <span>Furniture</span>
                <svg class="w-3 h-3 transform transition-transform" :class="{ 'rotate-180': selectedCategory === 'furniture' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
              <div x-show="selectedCategory === 'furniture'" x-cloak x-transition class="ml-4 mt-1 space-y-1">
                <a href="#" class="block py-2 px-3 text-xs text-gray-500 hover:text-orange-500 hover:bg-orange-50 rounded">Chairs</a>
                <a href="#" class="block py-2 px-3 text-xs text-gray-500 hover:text-orange-500 hover:bg-orange-50 rounded">Tables</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Auth Section (only show if not logged in) -->
      <?php if (!isset($_SESSION['user_name'])): ?>
        <div class="border-t border-gray-200 pt-4 px-4">
          <div class="space-y-2">
            <button @click="loginOpen = true; mobileOpen = false"
              class="w-full py-3 px-4 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
              Login
            </button>
            <button @click="registerOpen = true; mobileOpen = false"
              class="w-full py-3 px-4 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-50 transition font-medium">
              Register
            </button>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Login Modal - Full Screen on Mobile -->
  <div x-show="loginOpen" x-cloak x-transition
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 p-4 lg:hidden">

    <div class="bg-white w-full max-w-md max-h-[95vh] overflow-y-auto rounded-lg shadow-lg relative">

      <!-- Modal Header -->
      <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800">Login</h2>
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
              placeholder="you@example.com or 09123456789" required
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
          </div>

          <div x-show="(isMobile) || (isEmail && otpVerified)" x-transition class="space-y-2">
            <label for="mobile_password" class="block text-sm font-medium text-gray-600">Password</label>
            <input type="password" id="mobile_password" name="password" x-model="password"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
          </div>

          <div x-show="isEmail && !otpSent && !otpVerified" x-transition class="space-y-2">
            <label class="block text-sm font-medium text-gray-600">OTP Verification</label>
            <button
              type="button"
              @click="sendOTP"
              :disabled="otpLoading || resendCooldown > 0"
              class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center space-x-2">

              <template x-if="!otpLoading && resendCooldown === 0">
                <span>Send OTP</span>
              </template>

              <template x-if="otpLoading">
                <div class="flex items-center space-x-2">
                  <!-- Spinner -->
                  <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                      stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
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

          <div x-show="errorMessage" x-transition class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
            <span x-text="errorMessage"></span>
          </div>

          <div x-show="successMessage" x-transition class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            <span x-text="successMessage"></span>
          </div>

          <div class="text-center space-y-3 pt-4 border-t border-gray-200">
            <div>
              <a href="forgot_password" class="text-orange-500 hover:underline text-sm font-medium">Forgot password?</a>
            </div>
            <div>
              <span class="text-sm text-gray-600">Don't have an account?</span>
              <a href="#" @click.prevent="registerOpen = true; loginOpen = false"
                class="text-orange-500 hover:underline font-medium text-sm">Register</a>
            </div>
          </div>

          <div class="pt-4 border-t border-gray-200">
            <a href="../google-login.php"
              class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-lg transition">
              <svg class="w-5 h-5 bg-white rounded-full p-[2px]" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.5 0 6.3 1.2 8.3 3.2l6.2-6.2C34.8 2.6 29.7 0 24 0 14.8 0 6.8 5.9 3.2 14.1l7.3 5.7C12.7 13.2 17.9 9.5 24 9.5z" />
                <path fill="#34A853" d="M24 48c6.5 0 12-2.1 16.1-5.7l-7.4-6.1C30.5 38.7 27.5 40 24 40c-6 0-11.2-3.7-13.4-8.8l-7.2 5.5C6.3 43.8 14.6 48 24 48z" />
                <path fill="#FBBC05" d="M43.6 20H24v8.4h11.3c-1.1 3.2-3.4 5.8-6.5 7.6l7.4 6.1c4.3-4 6.8-9.9 6.8-17.1 0-1.2-.1-2.3-.3-3.4z" />
                <path fill="#4285F4" d="M10.6 29.6C9.7 27.2 9.2 24.7 9.2 22s.5-5.2 1.4-7.6l-7.4-5.7C1.1 13.6 0 17.7 0 22c0 4.2 1.1 8.3 3.2 11.8l7.4-4.2z" />
              </svg>
              Login with Google
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Register Modal -->
  <div x-show="registerOpen" x-cloak x-transition
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 p-4">
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
            <input type="email" name="email" id="email"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
              placeholder="you@example.com">
          </div>

          <div>
            <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
            <input type="tel" name="mobile" id="mobile"
              pattern="^09\d{9}$" maxlength="11"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
              placeholder="09123456789">
            <p class="text-xs text-gray-500 mt-1">Format: 09XXXXXXXXX</p>
          </div>

          <div>
            <label for="reg_password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
            <input type="password" name="password" id="reg_password" required minlength="6"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
          </div>

          <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
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
</nav>

<script src="top-obf.js"></script>