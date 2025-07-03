<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


$cart = $_SESSION['cart'] ?? [];
$total_cart_items = 0;

foreach ($cart as $item) {
  $total_cart_items += $item['quantity'];
}
?>

<?php $current_page = basename($_SERVER['PHP_SELF']); ?>

<!-- Tailwind + Alpine CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
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
    }, 3000);
  }
</script>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none;"
  class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]">
  <div class="bg-white rounded-lg p-8 flex items-center space-x-4 shadow-xl">
    <div class="loading-spinner"></div>
    <span class="text-gray-700 font-medium text-lg">Loading...</span>
  </div>
</div>

<div class="bg-black text-white py-2 text-sm">
  <div class=" px-4 flex justify-between items-center">
    <div class="flex items-center space-x-4">
      <span> Support: (02) 123-4567</span>
      <span> info@noblehome.com</span>
    </div>
    <div class="flex items-center space-x-4">
      <a href="javascript:void(0)" onclick="navigateWithLoading('help')"
        class="hover:text-orange-300 transition inline-flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Help
      </a>
      <a href="javascript:void(0)" onclick="navigateWithLoading('support')"
        class="hover:text-orange-300 transition inline-flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5zM8.25 12l7.5 0" />
        </svg>
        Support
      </a>
    </div>
  </div>
</div>

<!-- Navigation -->
<nav x-data="{ mobileOpen: false }" class="bg-white shadow-lg sticky top-0 z-50 text-black font-bold">
  <div class=" px-4">
    <div class="flex justify-between items-center py-4">
      <!-- Logo -->
      <a href="javascript:void(0)" onclick="navigateWithLoading('index')"
        class="flex items-center space-x-3 hover:opacity-80 transition">
        <div class="w-10 h-10 rounded overflow-hidden">
          <img src="img/logo/logo.png" alt="Noble Home Logo" class="w-full h-full object-cover">
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-2xl font-bold text-orange-500">NobleHome</span>
          <span class="text-sm text-black">Construction</span>
        </div>
      </a>

      <!-- Desktop Links -->
      <div class="hidden md:flex space-x-6 items-center">
        <a href="javascript:void(0)" onclick="navigateWithLoading('index')"
          class="<?= $current_page == 'index' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition">
          Home
        </a>

        <!-- Products Dropdown -->
        <div x-data="{ open: false, selected: null }" class="relative">
          <button @click="open = !open" class="text-black hover:text-orange-500 transition">Products</button>

          <div x-show="open" @click.away="open = false" x-transition x-cloak
            class="absolute left-0 mt-2 bg-white shadow-lg rounded-lg flex w-[500px] z-50">
            <!-- Main Categories -->
            <div class="w-1/2 border-r p-4 space-y-2">
              <button @mouseenter="selected = 'materials'"
                class="block w-full text-left hover:text-orange-500">Construction Materials</button>
              <button @mouseenter="selected = 'furniture'"
                class="block w-full text-left hover:text-orange-500">Furniture</button>
            </div>

            <!-- Subpanel -->
            <div class="w-1/2 p-4">
              <template x-if="selected === 'materials'">
                <div>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500">temporary</a>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500">temporary</a>
                </div>
              </template>

              <template x-if="selected === 'furniture'">
                <div>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500">temporary</a>
                  <a href="javascript:void(0)" onclick="navigateWithLoading('')"
                    class="block hover:text-orange-500">temporary</a>
                </div>
              </template>
            </div>
          </div>
        </div>

        <a href="javascript:void(0)" onclick="navigateWithLoading('about')"
          class="<?= $current_page == 'about' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition">
          About
        </a>

        <a href="javascript:void(0)" onclick="navigateWithLoading('contact')"
          class="<?= $current_page == 'contact' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition">
          Contact
        </a>

        <a href="javascript:void(0)" onclick="navigateWithLoading('shop')"
          class="<?= $current_page == 'shop' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition inline-flex items-center gap-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l1 11a1 1 0 001 1h14a1 1 0 001-1l1-11M4 9V7a1 1 0 011-1h14a1 1 0 011 1v2M9 22V12h6v10" />
          </svg>
          Shop
        </a>

        <a href="javascript:void(0)" onclick="navigateWithLoading('cart_view')"
          class="<?= $current_page == 'cart/cart_view' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition inline-flex items-center gap-1 relative">
          <!-- Cart Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.35 2.7a1 1 0 00.9 1.5h11.1M16 21a1 1 0 100-2 1 1 0 000 2zm-8 0a1 1 0 100-2 1 1 0 000 2z" />
          </svg>
          <!-- Text -->
          Cart

          <span id="cart-count-bubble" class="cart-count absolute -top-2 -right-3 bg-red-500 text-white text-[10px] px-1 py-0.5 p-1 rounded-full font-bold leading-none <?= $total_cart_items > 0 ? '' : 'hidden' ?>">
            <span class="cart-count" data-cart-count><?= $total_cart_items ?></span>
          </span>


        </a>
        <!-- Wrapper Alpine Component -->
        <div x-data="{ loginOpen: false, registerOpen: false }">

          <?php if (isset($_SESSION['user_name'])): ?>
            <!-- ✅ Logged in -->
            <div class="text-black flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="text-sm"><strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></span>
              <a href="logout.php" class="text-red-500 text-xs hover:underline">Logout</a>
            </div>

          <?php else: ?>
            <!-- ✅ Not Logged in -->
            <div class="relative">

              <!-- Login Button -->
              <button @click="loginOpen = !loginOpen"
                class="text-black hover:text-orange-500 transition flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5.121 17.804A10.95 10.95 0 0112 15c2.385 0 4.579.832 6.314 2.204M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Login
              </button>

              <!-- Login Dropdown Form -->
              <div id="authDropdown" x-show="loginOpen" @click.away="loginOpen = false" x-transition x-cloak
                class="absolute right-0 mt-2 w-72 bg-white border border-gray-200 rounded-lg shadow-lg p-4 z-50">
                <form action="login.php" method="POST" class="space-y-3">
                  <div>
                    <label for="email" class="block text-sm font-medium text-gray-600">Email</label>
                    <input type="email" id="email" name="email" required
                      class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                  </div>

                  <div>
                    <label for="password" class="block text-sm font-medium text-gray-600">Password</label>
                    <input type="password" id="password" name="password" required
                      class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                  </div>

                  <div class="flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4">
                    <label for="remember" class="text-sm text-gray-600">Remember me</label>
                  </div>

                  <button type="submit"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg">
                    Log In
                  </button>

                  <div class="text-center text-sm mt-2">
                    <a href="forgot_password.php" class="text-orange-500 hover:underline">Forgot password?</a>
                  </div>

                  <div class="text-center text-sm mt-1">
                    <span>Don't have an account?</span>
                    <a href="#" @click.prevent="registerOpen = true; loginOpen = false"
                      class="text-orange-500 hover:underline font-medium">Register</a>
                  </div>

                  <!-- Google login button -->
                  <div class="text-center mt-4">
                    <a href="google-login.php"
                      class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg">

                      <!-- Google G logo SVG -->
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


          <!-- ✅ Register Modal -->
          <div x-show="registerOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50">
            <div @click.away="registerOpen = false"
              class="bg-white w-full max-w-md p-6 rounded-lg shadow-lg relative">

              <h2 class="text-lg font-semibold text-gray-700 mb-4">Create an Account</h2>
              <form action="register.php" method="POST" class="space-y-4">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-600">Full Name</label>
                  <input type="text" name="name" id="name" required
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                  <label for="email" class="block text-sm font-medium text-gray-600">Email</label>
                  <input type="email" name="email" id="email" required
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                  <label for="password" class="block text-sm font-medium text-gray-600">Password</label>
                  <input type="password" name="password" id="password" required
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                  <label for="confirm_password" class="block text-sm font-medium text-gray-600">Confirm Password</label>
                  <input type="password" name="confirm_password" id="confirm_password" required
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <button type="submit"
                  class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg">
                  Register
                </button>

                <div class="text-center text-sm mt-2">
                  <span>Already have an account?</span>
                  <a href="#" @click.prevent="registerOpen = false; loginOpen = true"
                    class="text-orange-500 hover:underline">Login</a>
                </div>
              </form>

              <!-- Close Button -->
              <button @click="registerOpen = false"
                class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>
          </div>
        </div>



      </div>

      <!-- Mobile Hamburger -->
      <div class="md:hidden">
        <button @click="mobileOpen = !mobileOpen" class="text-gray-700 focus:outline-none">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Dropdown -->
    <div x-show="mobileOpen" x-transition x-cloak class="md:hidden space-y-2 pb-4">
      <a href="javascript:void(0)" onclick="navigateWithLoading('index.php')"
        class="<?= $current_page == 'index.php' ? 'text-orange-600 underline font-bold' : 'text-gray-700' ?> block hover:text-orange-500 transition">
        Home
      </a>

      <!-- Mobile Products Dropdown -->
      <div x-data="{ open: false }">
        <button @click="open = !open" class="block w-full text-left text-gray-700 hover:text-orange-500 transition">Products</button>
        <div x-show="open" x-transition x-cloak class="pl-4 space-y-1">
          <a href="javascript:void(0)" onclick="navigateWithLoading('products/wpc.php')"
            class="block hover:text-orange-500">WPC Fluted</a>
          <a href="javascript:void(0)" onclick="navigateWithLoading('products/pvc.php')"
            class="block hover:text-orange-500">PVC Panels</a>
          <a href="javascript:void(0)" onclick="navigateWithLoading('products/ceiling-flat.php')"
            class="block hover:text-orange-500">Flat Ceiling</a>
          <a href="javascript:void(0)" onclick="navigateWithLoading('products/deck-composite.php')"
            class="block hover:text-orange-500">Composite Decking</a>
        </div>
      </div>

      <a href="javascript:void(0)" onclick="navigateWithLoading('about.php')"
        class="<?= $current_page == 'about.php' ? 'text-orange-600 underline font-bold' : 'text-gray-700' ?> block hover:text-orange-500 transition">
        About
      </a>
      <a href="javascript:void(0)" onclick="navigateWithLoading('contact.php')"
        class="<?= $current_page == 'contact.php' ? 'text-orange-600 underline font-bold' : 'text-gray-700' ?> block hover:text-orange-500 transition">
        Contact
      </a>
      <a href="javascript:void(0)" onclick="navigateWithLoading('quote.php')"
        class="<?= $current_page == 'quote.php' ? 'text-orange-600 underline font-bold' : 'text-gray-700' ?> block hover:text-orange-500 transition">
        Get Free Quote
      </a>
    </div>
  </div>
</nav>