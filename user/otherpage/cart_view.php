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

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? 'example@example.com';
$user_picture = $_SESSION['user_picture'] ?? null;


$user_id = $_SESSION['user_id'] ?? 0;
$cart_items = [];
$total_price = 0;
$notice = $_SESSION['checkout_notice'] ?? null;
unset($_SESSION['checkout_notice']);

// ✅ Fetch cart items from database
if ($user_id) {

  $stmt = $conn->prepare("
  SELECT c.*, t.type_image, v.descrip6, v.descrip7, v.origin
  FROM user_cart_items c
  LEFT JOIN product_types t 
      ON t.product_id = c.product_id AND t.type_name = c.type_name
  LEFT JOIN product_variants v
      ON c.variant_id = v.id
  WHERE c.user_id = ?
");


  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
  }
  $stmt->close();
}
?>




<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Cart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>

<body class="bg-gray-100 ">
  <!-- Hero Section -->
  <div class="gradient-bg text-white  relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none z-0">
      <svg width="100%" height="100%" class="w-full h-full" style="position:absolute;top:0;left:0;" xmlns="http://www.w3.org/2000/svg">
        <circle class="bubble bubble1" cx="10%" cy="80%" r="32" fill="#fff" fill-opacity="0.13" />
        <circle class="bubble bubble2" cx="25%" cy="90%" r="18" fill="#fff" fill-opacity="0.10" />
        <circle class="bubble bubble3" cx="40%" cy="85%" r="24" fill="#fff" fill-opacity="0.09" />
        <circle class="bubble bubble4" cx="60%" cy="92%" r="14" fill="#fff" fill-opacity="0.11" />
        <circle class="bubble bubble5" cx="75%" cy="88%" r="28" fill="#fff" fill-opacity="0.12" />
        <circle class="bubble bubble6" cx="90%" cy="80%" r="20" fill="#fff" fill-opacity="0.10" />
      </svg>
    </div>
    <?php include '../navbar/top.php'; ?>
    <div class="bg-orange-400 text-white py-5 z-0">
      <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-center mb-4"> Your Shopping Cart</h1>
        <p class="text-xl text-center opacity-90">Review your items and proceed to checkout</p>
      </div>
    </div>
    <style>
      body {
        font-family: 'Poppins', sans-serif;
      }

      /* Bouncing animation for bubbles */
      .bubble1 {
        animation: bubble-bounce1 7s ease-in-out infinite alternate;
      }

      .bubble2 {
        animation: bubble-bounce2 6s ease-in-out infinite alternate;
      }

      .bubble3 {
        animation: bubble-bounce3 8s ease-in-out infinite alternate;
      }

      .bubble4 {
        animation: bubble-bounce4 5.5s ease-in-out infinite alternate;
      }

      .bubble5 {
        animation: bubble-bounce5 7.5s ease-in-out infinite alternate;
      }

      .bubble6 {
        animation: bubble-bounce6 6.5s ease-in-out infinite alternate;
      }

      @keyframes bubble-bounce1 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-60px) scale(1.08);
        }
      }

      @keyframes bubble-bounce2 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-40px) scale(1.12);
        }
      }

      @keyframes bubble-bounce3 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-70px) scale(1.05);
        }
      }

      @keyframes bubble-bounce4 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-30px) scale(1.15);
        }
      }

      @keyframes bubble-bounce5 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-55px) scale(1.09);
        }
      }

      @keyframes bubble-bounce6 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-35px) scale(1.13);
        }
      }
    </style>
  </div>


  <nav class="bg-white border-b border-gray-200 px-4 py-3 ">
    <div class="">
      <div class="flex items-center space-x-2 text-sm">
        <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class="text-gray-600 font-medium">Cart</span>
        <?php if (!empty($search_keyword)): ?>
          <i class="fas fa-chevron-right text-gray-400"></i>
          <span class="text-gray-500">Search: "<?= htmlspecialchars($search_keyword) ?>"</span>
        <?php endif; ?>
      </div>
    </div>
  </nav>


  <div class="px-2 py-2">
    <div class="bg-white shadow-lg rounded-lg p-6">
      <h2 class="text-3xl font-bold text-orange-400 mb-6 flex items-center gap-2">Your Cart</h2>

      <?php if ($notice): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg shadow text-sm">
          <?= htmlspecialchars($notice) ?>
        </div>
      <?php endif; ?>

      <?php if (empty($cart_items)): ?>
        <p class="text-gray-600 text-lg">Your cart is currently empty.</p>
        <a href="shop" class="inline-block mt-4 bg-orange-400 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition">Continue Shopping</a>
      <?php else: ?>
        <form action="../cart/update_cart.php" method="POST">
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse ">

              <thead class="bg-orange-400 text-white uppercase text-xs rounded-lg">
                <tr>
                  <th class="py-3 px-4">Category</th>
                  <th class="py-3 px-4">Product</th>
                  <th class="py-3 px-4">Qty</th>
                  <th class="py-3 px-4">Unit Price</th>
                  <th class="py-3 px-4">Total</th>
                  <th class="py-3 px-4">Image</th>
                  <th class="py-3 px-4">Origin</th>
                  <th class="py-3 px-4">Remove</th>
                </tr>
              </thead>


              <tbody class="divide-y divide-gray-200">
                <?php foreach ($cart_items as $item):
                  $unit_price = floatval($item['price']);
                  $quantity = intval($item['quantity']);
                  $subtotal = $unit_price * $quantity;
                  $total_price += $subtotal;
                ?>
                  <tr>
                    <td class="py-3 px-4 font-semibold text-gray-800 uppercase"><?= htmlspecialchars($item['codename']) ?></td>
                    <td class="py-3 px-4 text-sm text-gray-700 space-y-1 uppercase ">
                      <div><span class="font-semibold">Variant:</span> <?= htmlspecialchars($item['variant_name'] ?: '—') ?></div>
                      <div><span class="font-semibold">Type:</span> <?= htmlspecialchars($item['type_name'] ?: '—') ?></div>
                      <div><span class="font-semibold">Size:</span> <?= htmlspecialchars($item['size'] ?: '—') ?></div>
                      <div><span class="font-semibold">Color:</span> <?= htmlspecialchars($item['color_name'] ?: '—') ?></div>
                      <div><span class="font-semibold"></span> <?= htmlspecialchars($item['descrip6'] ?? '—') ?></div>
                      <div><span class="font-semibold"></span> <?= htmlspecialchars($item['descrip7'] ?? '—') ?></div>
                    </td>

                    <td class="py-3 px-4">
                      <input type="number" name="quantities[<?= $item['id'] ?>]"
                        value="<?= $quantity ?>" min="1"
                        class="w-16 text-sm border border-gray-300 rounded px-2 py-1 text-center shadow-sm">
                    </td>

                    <td class="py-3 px-4 text-orange-600 font-medium">
                      ₱<?= number_format($unit_price, 2) ?>
                    </td>
                    <td class="py-3 px-4 text-green-600 font-bold">
                      ₱<?= number_format($subtotal, 2) ?>
                    </td>

                    <td class="py-3 px-4">
                      <?php if (!empty($item['type_image'])): ?>
                        <img src="../../<?= ($item['type_image']) ?>" class="w-16 h-16 object-contain rounded" alt="Product Image">
                      <?php else: ?>
                        <div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-gray-500 text-sm">No Image</div>
                      <?php endif; ?>
                    </td>

                    <!-- ADD THIS NEW CELL FOR ORIGIN -->
                    <td class="py-3 px-4">
                      <?php if (!empty($item['origin'])): ?>
                        <?php if ($item['origin'] === 'local'): ?>
                          <span class="text-blue-600 font-medium text-sm">Local</span>
                        <?php elseif ($item['origin'] === 'international'): ?>
                          <span class="text-red-600 font-medium text-sm">International</span>
                        <?php else: ?>
                          <span class="text-gray-600 font-medium text-sm"><?= htmlspecialchars($item['origin']) ?></span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-gray-400 text-sm">—</span>
                      <?php endif; ?>
                    </td>


                    <td class="py-3 px-4 align-middle">
                      <a href="../cart/remove_from_cart.php?key=<?= $item['id'] ?>"
                        class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 transition"
                        title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span class="text-sm hidden md:inline">Remove</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="mt-6 flex justify-between items-center flex-wrap gap-4">
            <button type="submit" class="bg-orange-400 hover:bg-orange-700 text-white px-5 py-2 rounded shadow transition">Update Cart</button>
            <div class="text-xl font-bold text-orange-700">
              Total: ₱<?= number_format($total_price, 2) ?>
            </div>
          </div>
        </form>
        <div class="mt-6 flex flex-wrap gap-3 justify-end">
          <a href="shop.php" class="bg-orange-400 hover:bg-gray-800 text-white px-5 py-2 rounded transition">Continue Shopping</a>
          <a href="checkout.php" class="bg-orange-400 hover:bg-green-700 text-white px-5 py-2 rounded transition">Proceed to Checkout</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <footer class="bg-black pattern-bg text-white py-16 relative overflow-hidden">
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
                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
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
</body>

</html>