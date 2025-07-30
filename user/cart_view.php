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
  <title>Your Cart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>

<body class="bg-gray-100 font-sans">
  <!-- Hero Section -->

  <?php include 'navbar/top.php'; ?>
  <div class="bg-orange-600 text-white py-5">
    <div class="container mx-auto px-4">
      <h1 class="text-4xl font-bold text-center mb-4"> Your Shopping Cart</h1>
      <p class="text-xl text-center opacity-90">Review your items and proceed to checkout</p>
    </div>
  </div>

 
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="max-w-7xl mx-auto">
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
      <h2 class="text-3xl font-bold text-orange-700 mb-6 flex items-center gap-2"> Your Cart</h2>

      <?php if ($notice): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg shadow text-sm">
          <?= htmlspecialchars($notice) ?>
        </div>
      <?php endif; ?>

      <?php if (empty($cart_items)): ?>
        <p class="text-gray-600 text-lg">Your cart is currently empty.</p>
        <a href="shop" class="inline-block mt-4 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition">Continue Shopping</a>
      <?php else: ?>
        <form action="cart/update_cart.php" method="POST">
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">

              <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
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

              </thead>
              <tbody class="divide-y divide-gray-200">
                <?php foreach ($cart_items as $item):
                  $unit_price = floatval($item['price']);
                  $quantity = intval($item['quantity']);
                  $subtotal = $unit_price * $quantity;
                  $total_price += $subtotal;
                ?>
                  <tr>
                    <td class="py-3 px-4 font-semibold text-gray-800"><?= htmlspecialchars($item['codename']) ?></td>
                    <td class="py-3 px-4 text-sm text-gray-700 space-y-1">
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
                        <img src="../<?= ($item['type_image']) ?>" class="w-16 h-16 object-contain rounded" alt="Product Image">
                      <?php else: ?>
                        <div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-gray-500 text-sm">No Image</div>
                      <?php endif; ?>
                    </td>

                    <!-- ADD THIS NEW CELL FOR ORIGIN -->
<td class="py-3 px-4">
  <?php if (!empty($item['origin'])): ?>
    <span class="text-blue-600 font-medium text-sm"><?= htmlspecialchars($item['origin']) ?></span>
  <?php else: ?>
    <span class="text-gray-400 text-sm">—</span>
  <?php endif; ?>
</td>

                    <td class="py-3 px-4 align-middle">
                      <a href="cart/remove_from_cart.php?key=<?= $item['id'] ?>"
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
            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2 rounded shadow transition">Update Cart</button>
            <div class="text-xl font-bold text-orange-700">
              Total: ₱<?= number_format($total_price, 2) ?>
            </div>
          </div>
        </form>
        <div class="mt-6 flex flex-wrap gap-3 justify-end">
          <a href="shop.php" class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-2 rounded transition">Continue Shopping</a>
          <a href="checkout.php" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded transition">Proceed to Checkout</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>

</html>