<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

/**
 * Calculate delivery date range based on lead time settings
 * @param int $leadCount - Number of intervals
 * @param string $leadInterval - Type of interval (day/week/month/year)
 * @param int $leadGap - Additional days gap
 * @return array ['start_date' => 'Oct 14, 2025', 'end_date' => 'Oct 21, 2025', 'display' => 'Oct 14, 2025 - Oct 21, 2025']
 */
function calculateDeliveryDateRange($leadCount, $leadInterval, $leadGap)
{
  if (empty($leadCount) || empty($leadInterval)) {
    return null;
  }

  $today = new DateTime();
  $startDate = clone $today;
  $endDate = clone $today;

  // Calculate start date (first delivery)
  switch ($leadInterval) {
    case 'day':
      $startDate->modify("+{$leadCount} days");
      break;
    case 'week':
      $daysToAdd = $leadCount * 7;
      $startDate->modify("+{$daysToAdd} days");
      break;
    case 'month':
      $startDate->modify("+{$leadCount} months");
      break;
    case 'year':
      $startDate->modify("+{$leadCount} years");
      break;
  }

  // Calculate end date (start date + gap)
  $endDate = clone $startDate;
  if ($leadGap > 0) {
    $endDate->modify("+{$leadGap} days");
  }

  return [
    'start_date' => $startDate->format('M d, Y'),
    'end_date' => $endDate->format('M d, Y'),
    'display' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
    'start_timestamp' => $startDate->getTimestamp(),
    'end_timestamp' => $endDate->getTimestamp()
  ];
}

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

$cart_items = [];
$total_price = 0; // Initialize here
$notice = $_SESSION['checkout_notice'] ?? null;
unset($_SESSION['checkout_notice']);

// ✅ Fetch cart items from database - FIXED: descrip6, descrip7 from products table
if ($user_id) {
  $stmt = $conn->prepare("
    SELECT 
        c.*, 
        t.type_image, 
        p.descrip6, 
        p.descrip7, 
        p.product_name,
        p.main_image,
        v.origin,
        v.discount,
        v.percent,
        v.status,
        v.lead_count,
        v.lead_interval,
        v.lead_gap
    FROM user_cart_items c
    LEFT JOIN product_types t 
        ON t.product_id = c.product_id AND t.type_name = c.type_name
    LEFT JOIN product_variants v 
        ON c.variant_id = v.id
    LEFT JOIN products p 
        ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.id DESC
");

  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  // ✅ FIXED: Calculate total price properly while fetching data
  while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    // Calculate total price correctly - only once here
    $item_total = floatval($row['price']) * intval($row['quantity']);
    $total_price += $item_total;
  }

  $stmt->close();
}

// Calculate total cart items count
$total_cart_items = count($cart_items);
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
  <title>Your Cart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-100 font-roboto">
  <?php include '../navbar/top.php'; ?>

  <!-- Hero Section -->
  <div class="gradient-bg text-white relative overflow-hidden">
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

    <div class="bg-black text-white py-5 sm:py-8 lg:py-12 z-0">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl lg:text-4xl text-center mb-2 sm:mb-4">Your Shopping Cart</h1>
        <p class="text-base sm:text-lg lg:text-xl text-center opacity-90">Review your items and proceed to checkout</p>
      </div>
    </div>
    <style>
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

      /* Custom styles for better mobile experience */
      .cart-table-mobile {
        display: none;
      }

      @media (max-width: 768px) {
        .cart-table-desktop {
          display: none;
        }

        .cart-table-mobile {
          display: block;
        }
      }

      .glass-effect {
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
      }

      .glow-effect {
        box-shadow: 0 0 30px rgba(251, 146, 60, 0.3);
      }

      .floating {
        animation: floating 3s ease-in-out infinite;
      }

      @keyframes floating {

        0%,
        100% {
          transform: translateY(0px);
        }

        50% {
          transform: translateY(-10px);
        }
      }

      .social-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(251, 146, 60, 0.4);
      }

      .link-hover:hover {
        transform: translateX(5px);
      }

      .pattern-bg {
        background-image:
          radial-gradient(circle at 25% 25%, rgba(251, 146, 60, 0.1) 0%, transparent 50%),
          radial-gradient(circle at 75% 75%, rgba(251, 146, 60, 0.05) 0%, transparent 50%);
      }

      /* Quantity Control Styles */
      .quantity-btn {
        transition: all 0.2s ease;
      }

      .quantity-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      .quantity-btn:active {
        transform: scale(0.95);
      }
    </style>
  </div>

  <!-- Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="container mx-auto">
      <div class="flex items-center space-x-2 text-sm">
        <a href="index-page-1-A-B-C-D-E" class="text-black hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class="text-gray-600 font-medium">Cart</span>
      </div>
    </div>
  </nav>

  <!-- Cart Content -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
    <div class=" p-4 sm:p-6 lg:p-8">
      <h2 class="text-2xl sm:text-3xl  text-black mb-4 sm:mb-6 flex items-center gap-2">
        <i class="fas fa-shopping-cart"></i>Your Cart
      </h2>

      <?php if ($notice): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg shadow text-sm">
          <?= htmlspecialchars($notice) ?>
        </div>
      <?php endif; ?>

      <!-- Empty Cart State -->
      <?php if (empty($cart_items)): ?>
        <div class="text-center py-12">
          <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-600 text-lg mb-4">Your cart is currently empty.</p>
          <a href="index-shop-page-2" class="inline-block bg-black hover:bg-orange-600 text-white px-6 py-3 rounded-lg transition-colors font-medium">
            <i class="fas fa-store mr-2"></i>Continue Shopping
          </a>
        </div>
      <?php else: ?>
        <!-- Cart Items - Desktop Table -->
        <div class="cart-table-desktop">
          <form action="../cart/update_cart.php" method="POST">
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-orange-400 text-white uppercase text-xs">
                  <tr>
                    <th class="py-3 px-4 rounded-tl-lg">Category</th>
                    <th class="py-3 px-4">Product</th>
                    <th class="py-3 px-4">Qty</th>
                    <th class="py-3 px-4">Unit Price</th>
                    <th class="py-3 px-4">Total</th>
                    <th class="py-3 px-4">Image</th>
                    <th class="py-3 px-4">Origin</th>
                    <th class="py-3 px-4">Get Items By</th>
                    <th class="py-3 px-4 rounded-tr-lg">Remove</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <?php foreach ($cart_items as $item):
                    $unit_price = floatval($item['price']);
                    $quantity = intval($item['quantity']);
                    $subtotal = $unit_price * $quantity;
                  ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="py-4 px-4 font-semibold text-gray-800 uppercase"><?= htmlspecialchars($item['codename']) ?></td>
                      <td class="py-4 px-4 text-sm text-gray-700 space-y-1 uppercase">
                        <div><span class="font-semibold">Variant:</span> <?= htmlspecialchars($item['variant_name'] ?: '—') ?></div>
                        <div><span class="font-semibold">Type:</span> <?= htmlspecialchars($item['type_name'] ?: '—') ?></div>
                        <div><span class="font-semibold">Size:</span> <?= htmlspecialchars($item['size'] ?: '—') ?></div>
                        <div><span class="font-semibold">Color:</span> <?= htmlspecialchars($item['color_name'] ?: '—') ?></div>
                        <div><span class="font-semibold"></span> <?= htmlspecialchars($item['descrip6'] ?? '—') ?></div>
                        <div><span class="font-semibold"></span> <?= htmlspecialchars($item['descrip7'] ?? '—') ?></div>
                      </td>
                      <td class="py-4 px-4">
                        <div class="flex items-center justify-center gap-1">
                          <!-- Minus Button -->
                          <button type="button"
                            onclick="updateQuantity(<?= $item['id'] ?>, -1)"
                            class="quantity-btn w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-l-lg flex items-center justify-center transition-colors border border-gray-300">
                            <i class="fas fa-minus text-xs"></i>
                          </button>

                          <!-- Quantity Display/Input - MADE EDITABLE -->
                          <input type="number"
                            id="qty_<?= $item['id'] ?>"
                            name="quantities[<?= $item['id'] ?>]"
                            value="<?= $quantity ?>"
                            min="1"
                            max="9999"
                            class="w-20 h-8 text-sm border-t border-b border-gray-300 text-center bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
                            onchange="handleManualQuantityChange(<?= $item['id'] ?>)"
                            onkeypress="handleQuantityKeypress(event, <?= $item['id'] ?>)"
                            onfocus="this.select()">

                          <!-- Plus Button -->
                          <button type="button"
                            onclick="updateQuantity(<?= $item['id'] ?>, 1)"
                            class="quantity-btn w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-r-lg flex items-center justify-center transition-colors border border-gray-300">
                            <i class="fas fa-plus text-xs"></i>
                          </button>
                        </div>

                        <!-- Status indicator container -->
                        <div id="qty_status_<?= $item['id'] ?>" class="text-center mt-1 text-xs h-4"></div>
                      </td>
                      <td class="py-4 px-4 text-orange-600 font-medium">₱<?= number_format($unit_price, 2) ?></td>
                      <td class="py-4 px-4 text-green-600 font-bold" id="subtotal_<?= $item['id'] ?>">₱<?= number_format($subtotal, 2) ?></td>
                      <td class="py-4 px-4">
                        <?php if (!empty($item['type_image'])): ?>
                          <img src="../../<?= ($item['type_image']) ?>" class="w-16 h-16 object-cover rounded-lg shadow-sm" alt="Product Image">
                        <?php else: ?>
                          <div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-gray-500 text-xs rounded-lg">No Image</div>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-4">
                        <?php if (!empty($item['origin'])): ?>
                          <?php if ($item['origin'] === 'local'): ?>
                            <span class="text-blue-600 font-medium text-sm bg-blue-50 px-2 py-1 rounded-full">Local</span>
                          <?php elseif ($item['origin'] === 'international'): ?>
                            <span class="text-red-600 font-medium text-sm bg-red-50 px-2 py-1 rounded-full">International</span>
                          <?php else: ?>
                            <span class="text-gray-600 font-medium text-sm bg-gray-50 px-2 py-1 rounded-full"><?= htmlspecialchars($item['origin']) ?></span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-gray-400 text-sm">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-4">
                        <?php
                        $deliveryDates = calculateDeliveryDateRange(
                          $item['lead_count'] ?? null,
                          $item['lead_interval'] ?? null,
                          $item['lead_gap'] ?? null
                        );

                        if ($deliveryDates):
                        ?>
                          <div class="text-sm">
                            <div class="font-semibold text-green-700">Get Items By:</div>
                            <div class="text-gray-700"><?= htmlspecialchars($deliveryDates['display']) ?></div>
                          </div>
                        <?php else: ?>
                          <span class="text-gray-400 text-sm">TBD</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-4 align-middle">
                        <a href="../cart/remove_from_cart.php?key=<?= $item['id'] ?>"
                          class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded transition-colors" title="Remove">
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                          <span class="hidden lg:inline text-sm">Remove</span>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Desktop Cart Actions -->
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
              <div class="text-xl lg:text-2xl font-bold text-black px-4 py-2 rounded-lg" id="cart_total_desktop">
                Total: ₱<?= number_format($total_price, 2) ?>
              </div>
            </div>
          </form>
        </div>

        <!-- Cart Items - Mobile Cards -->
        <div class="cart-table-mobile space-y-4">
          <form action="../cart/update_cart.php" method="POST">
            <?php foreach ($cart_items as $item):
              $unit_price = floatval($item['price']);
              $quantity = intval($item['quantity']);
              $subtotal = $unit_price * $quantity;
            ?>
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-4">
                <!-- Product Image and Basic Info -->
                <div class="flex items-start gap-4">
                  <?php if (!empty($item['type_image'])): ?>
                    <img src="../../<?= ($item['type_image']) ?>" class="w-20 h-20 object-cover rounded-lg shadow-sm flex-shrink-0" alt="Product Image">
                  <?php else: ?>
                    <div class="w-20 h-20 bg-gray-200 flex items-center justify-center text-gray-500 text-xs rounded-lg flex-shrink-0">No Image</div>
                  <?php endif; ?>

                  <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-800 uppercase text-sm mb-1"><?= htmlspecialchars($item['codename']) ?></h3>
                    <div class="space-y-1 text-xs text-gray-600">
                      <div><span class="font-medium">Variant:</span> <?= htmlspecialchars($item['variant_name'] ?: '—') ?></div>
                      <div><span class="font-medium">Type:</span> <?= htmlspecialchars($item['type_name'] ?: '—') ?></div>
                      <div><span class="font-medium">Size:</span> <?= htmlspecialchars($item['size'] ?: '—') ?></div>
                      <div><span class="font-medium">Color:</span> <?= htmlspecialchars($item['color_name'] ?: '—') ?></div>
                      <?php if (!empty($item['descrip6'])): ?>
                        <div><?= htmlspecialchars($item['descrip6']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($item['descrip7'])): ?>
                        <div><?= htmlspecialchars($item['descrip7']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Remove Button -->
                  <a href="../cart/remove_from_cart.php?key=<?= $item['id'] ?>"
                    class="text-red-600 hover:text-red-800 p-2 hover:bg-red-50 rounded transition-colors" title="Remove">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </a>
                </div>

                <!-- Origin Badge -->
                <div class="flex justify-between items-center">
                  <div>
                    <?php if (!empty($item['origin'])): ?>
                      <?php if ($item['origin'] === 'local'): ?>
                        <span class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded-full">Local</span>
                      <?php elseif ($item['origin'] === 'international'): ?>
                        <span class="text-red-600 font-medium text-xs bg-red-50 px-2 py-1 rounded-full">International</span>
                      <?php else: ?>
                        <span class="text-gray-600 font-medium text-xs bg-gray-50 px-2 py-1 rounded-full"><?= htmlspecialchars($item['origin']) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Delivery Date Range -->
                <?php
                $deliveryDates = calculateDeliveryDateRange(
                  $item['lead_count'] ?? null,
                  $item['lead_interval'] ?? null,
                  $item['lead_gap'] ?? null
                );

                if ($deliveryDates):
                ?>
                  <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="flex items-start gap-2">
                      <i class="fas fa-truck text-green-600 mt-0.5"></i>
                      <div>
                        <div class="text-xs font-semibold text-green-700 mb-1">Estimated Delivery</div>
                        <div class="text-sm text-gray-700"><?= htmlspecialchars($deliveryDates['display']) ?></div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Price and Quantity -->
                <div class="grid grid-cols-2 gap-4 pt-3 border-t border-gray-200">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Quantity</label>
                    <div class="flex items-center gap-1">
                      <!-- Minus Button -->
                      <button type="button"
                        onclick="updateQuantity(<?= $item['id'] ?>, -1)"
                        class="quantity-btn w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-l-lg flex items-center justify-center transition-colors border border-gray-300">
                        <i class="fas fa-minus text-xs"></i>
                      </button>

                      <!-- Editable Quantity Input -->
                      <input type="number"
                        id="qty_mobile_<?= $item['id'] ?>"
                        name="quantities[<?= $item['id'] ?>]"
                        value="<?= $quantity ?>"
                        min="1"
                        max="9999"
                        class="w-20 h-8 text-sm border-t border-b border-gray-300 text-center bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
                        onchange="handleManualQuantityChange(<?= $item['id'] ?>)"
                        onkeypress="handleQuantityKeypress(event, <?= $item['id'] ?>)"
                        onfocus="this.select()">

                      <!-- Plus Button -->
                      <button type="button"
                        onclick="updateQuantity(<?= $item['id'] ?>, 1)"
                        class="quantity-btn w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-r-lg flex items-center justify-center transition-colors border border-gray-300">
                        <i class="fas fa-plus text-xs"></i>
                      </button>
                    </div>

                    <!-- Status indicator for mobile -->
                    <div id="qty_status_mobile_<?= $item['id'] ?>" class="text-xs h-4 mt-1"></div>
                  </div>
                  <div class="text-right">
                    <div class="text-xs text-gray-600 mb-1">Unit Price</div>
                    <div class="text-sm font-medium text-orange-600">₱<?= number_format($unit_price, 2) ?></div>
                    <div class="text-xs text-gray-600 mt-1">Subtotal</div>
                    <div class="text-lg font-bold text-green-600" id="subtotal_mobile_<?= $item['id'] ?>">₱<?= number_format($subtotal, 2) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Mobile Cart Actions -->
            <div class="sticky bottom-0 bg-white border-t border-gray-200 p-4 -mx-4 mt-6">
              <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-xl font-bold text-orange-700 bg-orange-50 px-4 py-3 rounded-lg" id="cart_total_mobile">
                  Total: ₱<?= number_format($total_price, 2) ?>
                </div>
              </div>
            </div>
          </form>
        </div>

        <!-- Final Action Buttons -->
        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-end">
          <a href="index-shop-page-2.php" class="w-full sm:w-auto text-center bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition-colors font-medium">
            <i class="fas fa-store mr-2"></i>Continue Shopping
          </a>
          <a href="index-checkout-page-12.php" class="w-full sm:w-auto text-center bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg transition-colors font-medium">
            <i class="fas fa-credit-card mr-2"></i>Proceed to Checkout
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include '../navbar/footer.php'; ?>


  <!-- JavaScript for Quantity Controls -->
  <script>
    // Complete Enhanced JavaScript for Cart Quantity Controls with Manual Input

    // Store unit prices for each item
    const unitPrices = {};

    // Track pending updates to avoid multiple requests
    const pendingUpdates = new Set();

    // Initialize unit prices from PHP - replace this section with your PHP loop
    <?php foreach ($cart_items as $item): ?>
      unitPrices[<?= $item['id'] ?>] = <?= floatval($item['price']) ?>;
    <?php endforeach; ?>

    // Debounce function to limit API calls
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Enhanced update quantity function (works with plus/minus buttons)
    function updateQuantity(itemId, change) {
      const desktopInput = document.getElementById('qty_' + itemId);
      const mobileInput = document.getElementById('qty_mobile_' + itemId);

      // Get current value from either desktop or mobile input
      let currentValue = parseInt(desktopInput?.value || mobileInput?.value) || 1;
      let newValue = currentValue + change;

      // Ensure minimum quantity is 1 and maximum is 9999
      newValue = Math.max(1, Math.min(9999, newValue));

      // Update both desktop and mobile inputs
      if (desktopInput) desktopInput.value = newValue;
      if (mobileInput) mobileInput.value = newValue;

      // Update all form inputs with same item ID for consistency
      const allInputs = document.querySelectorAll('input[name="quantities[' + itemId + ']"]');
      allInputs.forEach(inp => inp.value = newValue);

      // Update display and save
      updateSubtotal(itemId, newValue);
      updateCartTotal();
      debouncedAutoSave(itemId, newValue);

      // Show brief feedback
      showValidationMessage(itemId, `Updated to ${newValue}`, "success");
    }

    // Handle manual quantity input changes (when user types directly)
    function handleManualQuantityChange(itemId) {
      const desktopInput = document.getElementById('qty_' + itemId);
      const mobileInput = document.getElementById('qty_mobile_' + itemId);

      // Get the value from whichever input triggered the change
      let inputValue = parseInt(desktopInput?.value || mobileInput?.value) || 1;
      let originalValue = inputValue;

      // Validate and constrain the value
      if (inputValue < 1) {
        inputValue = 1;
        showValidationMessage(itemId, "Minimum quantity is 1", "warning");
      } else if (inputValue > 9999) {
        inputValue = 9999;
        showValidationMessage(itemId, "Maximum quantity is 9999", "warning");
      } else if (inputValue > 100) {
        showValidationMessage(itemId, "Large quantity - please confirm", "info");
      } else if (inputValue !== originalValue) {
        showValidationMessage(itemId, `Quantity updated to ${inputValue}`, "success");
      }

      // Update both inputs to ensure consistency
      if (desktopInput) desktopInput.value = inputValue;
      if (mobileInput) mobileInput.value = inputValue;

      // Update all form inputs
      const allInputs = document.querySelectorAll('input[name="quantities[' + itemId + ']"]');
      allInputs.forEach(inp => inp.value = inputValue);

      // Update display and save
      updateSubtotal(itemId, inputValue);
      updateCartTotal();
      debouncedAutoSave(itemId, inputValue);
    }

    // Handle Enter key press and other key events in quantity input
    function handleQuantityKeypress(event, itemId) {
      // Handle Enter key - confirm the input
      if (event.key === 'Enter') {
        event.preventDefault();
        handleManualQuantityChange(itemId);
        event.target.blur(); // Remove focus to trigger change event
        return;
      }

      // Only allow numeric keys, backspace, delete, arrow keys, tab
      const allowedKeys = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'
      ];

      if (!allowedKeys.includes(event.key)) {
        event.preventDefault();
        showValidationMessage(itemId, "Numbers only", "warning");
      }
    }

    // Handle input events for real-time validation while typing
    function handleQuantityInput(event, itemId) {
      let value = event.target.value;

      // Remove any non-numeric characters except for temporary empty state
      if (value !== '' && (isNaN(value) || value < 0)) {
        event.target.value = Math.abs(parseInt(value)) || 1;
      }

      // Enforce maximum limit during typing
      if (parseInt(value) > 9999) {
        event.target.value = 9999;
        showValidationMessage(itemId, "Maximum quantity is 9999", "warning");
      }
    }

    // Show validation/status messages
    function showValidationMessage(itemId, message, type = "info") {
      const desktopStatus = document.getElementById('qty_status_' + itemId);
      const mobileStatus = document.getElementById('qty_status_mobile_' + itemId);

      let className = "text-blue-500"; // info
      let icon = "fas fa-info-circle";

      switch (type) {
        case "warning":
          className = "text-yellow-600";
          icon = "fas fa-exclamation-triangle";
          break;
        case "error":
          className = "text-red-500";
          icon = "fas fa-times-circle";
          break;
        case "success":
          className = "text-green-500";
          icon = "fas fa-check-circle";
          break;
        case "saving":
          className = "text-orange-500";
          icon = "fas fa-spinner fa-spin";
          break;
      }

      const statusHTML = `<i class="${icon}"></i> ${message}`;

      // Update desktop status
      if (desktopStatus) {
        desktopStatus.innerHTML = statusHTML;
        desktopStatus.className = `text-center mt-1 text-xs h-4 ${className}`;
      }

      // Update mobile status  
      if (mobileStatus) {
        mobileStatus.innerHTML = statusHTML;
        mobileStatus.className = `text-xs h-4 mt-1 ${className}`;
      }

      // Clear message after delay (except for saving status)
      if (type !== "saving") {
        setTimeout(() => {
          if (desktopStatus) desktopStatus.innerHTML = '';
          if (mobileStatus) mobileStatus.innerHTML = '';
        }, 3000);
      }
    }

    // Update subtotal display for a specific item
    function updateSubtotal(itemId, quantity) {
      const unitPrice = unitPrices[itemId] || 0;
      const subtotal = unitPrice * quantity;

      const formattedSubtotal = '₱' + subtotal.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      // Update desktop subtotal
      const desktopSubtotal = document.getElementById('subtotal_' + itemId);
      if (desktopSubtotal) {
        desktopSubtotal.textContent = formattedSubtotal;
      }

      // Update mobile subtotal
      const mobileSubtotal = document.getElementById('subtotal_mobile_' + itemId);
      if (mobileSubtotal) {
        mobileSubtotal.textContent = formattedSubtotal;
      }
    }

    // Update cart total by summing all items
    function updateCartTotal() {
      let total = 0;

      // Calculate total from all items
      Object.keys(unitPrices).forEach(itemId => {
        const desktopInput = document.getElementById('qty_' + itemId);
        const mobileInput = document.getElementById('qty_mobile_' + itemId);
        const quantity = parseInt(desktopInput?.value || mobileInput?.value) || 0;
        const unitPrice = unitPrices[itemId] || 0;
        total += unitPrice * quantity;
      });

      const formattedTotal = 'Total: ₱' + total.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      // Update both desktop and mobile totals
      const desktopTotal = document.getElementById('cart_total_desktop');
      const mobileTotal = document.getElementById('cart_total_mobile');

      if (desktopTotal) desktopTotal.textContent = formattedTotal;
      if (mobileTotal) mobileTotal.textContent = formattedTotal;
    }

    // Auto-save quantity to database with enhanced error handling
    async function autoSaveQuantity(itemId, quantity) {
      // Prevent multiple simultaneous requests for the same item
      if (pendingUpdates.has(itemId)) {
        return;
      }

      pendingUpdates.add(itemId);
      showValidationMessage(itemId, 'Saving...', 'saving');

      try {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('quantity', quantity);

        // Make the API call
        const response = await fetch('../cart/update_cart.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        // Check if the response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          throw new Error('Server did not return JSON response');
        }

        const data = await response.json();

        if (data.success) {
          // Show success message briefly
          showValidationMessage(itemId, 'Saved ✓', 'success');

          // Update cart totals from server response if provided
          if (data.total_price !== undefined) {
            updateServerTotal(data.total_price);
          }

          // Clear success message after shorter delay
          setTimeout(() => {
            const desktopStatus = document.getElementById('qty_status_' + itemId);
            const mobileStatus = document.getElementById('qty_status_mobile_' + itemId);
            if (desktopStatus) desktopStatus.innerHTML = '';
            if (mobileStatus) mobileStatus.innerHTML = '';
          }, 1500);

        } else {
          console.error('Failed to update quantity:', data.message);
          showValidationMessage(itemId, 'Save failed', 'error');
        }

      } catch (error) {
        console.error('Error updating quantity:', error);
        showValidationMessage(itemId, 'Connection error', 'error');

        // You can add a retry mechanism here if desired
        console.log('Auto-save failed for item', itemId, '. Please try again manually.');

      } finally {
        pendingUpdates.delete(itemId);
      }
    }

    // Update total from server response
    function updateServerTotal(serverTotal) {
      const formattedTotal = 'Total: ₱' + parseFloat(serverTotal).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const desktopTotal = document.getElementById('cart_total_desktop');
      const mobileTotal = document.getElementById('cart_total_mobile');

      if (desktopTotal) desktopTotal.textContent = formattedTotal;
      if (mobileTotal) mobileTotal.textContent = formattedTotal;
    }

    // Debounced version of auto-save (wait 1 second after last change)
    const debouncedAutoSave = debounce(autoSaveQuantity, 1000);

    // Batch update function (for manual update button if you want to keep it)
    function updateCartManually() {
      const form = document.querySelector('form[action="../cart/update_cart.php"]');
      if (form) {
        form.submit();
      }
    }

    // Initialize enhanced functionality on page load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Initializing enhanced quantity controls...');

      // Add enhanced event listeners to all quantity inputs
      Object.keys(unitPrices).forEach(itemId => {
        const desktopInput = document.getElementById('qty_' + itemId);
        const mobileInput = document.getElementById('qty_mobile_' + itemId);

        // Desktop input events
        if (desktopInput) {
          // Real-time input validation
          desktopInput.addEventListener('input', function(e) {
            handleQuantityInput(e, itemId);
          });

          // Focus event - select all text for easy editing
          desktopInput.addEventListener('focus', function() {
            this.select();
          });

          // Blur event - validate final value
          desktopInput.addEventListener('blur', function() {
            if (this.value === '' || this.value < 1) {
              this.value = 1;
              handleManualQuantityChange(itemId);
            }
          });
        }

        // Mobile input events (same as desktop)
        if (mobileInput) {
          mobileInput.addEventListener('input', function(e) {
            handleQuantityInput(e, itemId);
          });

          mobileInput.addEventListener('focus', function() {
            this.select();
          });

          mobileInput.addEventListener('blur', function() {
            if (this.value === '' || this.value < 1) {
              this.value = 1;
              handleManualQuantityChange(itemId);
            }
          });
        }
      });

      // Add keyboard shortcuts
      document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + Enter to proceed to checkout (if on cart page)
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
          const checkoutBtn = document.querySelector('a[href*="checkout"]');
          if (checkoutBtn) {
            window.location.href = checkoutBtn.href;
          }
        }
      });

      console.log('Enhanced quantity controls initialized successfully');
    });

    // Utility function to get all cart item IDs (useful for bulk operations)
    function getAllCartItemIds() {
      return Object.keys(unitPrices).map(id => parseInt(id));
    }

    // Utility function to get total items in cart
    function getTotalItemsInCart() {
      let totalItems = 0;
      Object.keys(unitPrices).forEach(itemId => {
        const desktopInput = document.getElementById('qty_' + itemId);
        const mobileInput = document.getElementById('qty_mobile_' + itemId);
        const quantity = parseInt(desktopInput?.value || mobileInput?.value) || 0;
        totalItems += quantity;
      });
      return totalItems;
    }

    // Utility function to validate all quantities
    function validateAllQuantities() {
      let isValid = true;
      Object.keys(unitPrices).forEach(itemId => {
        const desktopInput = document.getElementById('qty_' + itemId);
        const mobileInput = document.getElementById('qty_mobile_' + itemId);
        const quantity = parseInt(desktopInput?.value || mobileInput?.value) || 0;

        if (quantity < 1 || quantity > 9999) {
          isValid = false;
          showValidationMessage(itemId, 'Invalid quantity', 'error');
        }
      });
      return isValid;
    }

    // Export functions for external use if needed
    window.cartQuantityControls = {
      updateQuantity,
      handleManualQuantityChange,
      updateCartTotal,
      validateAllQuantities,
      getTotalItemsInCart,
      getAllCartItemIds
    };
  </script>
</body>

</html>