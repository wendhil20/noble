<?php
session_start();
include '../connection/connect.php';

// ✅ Restore user if session expired but remember_token exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];
  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
  }
  $stmt->close();
}

$user_id = $_SESSION['user_id'] ?? 0;
$cart_items = [];
$total_price = 0;
$notice = $_SESSION['checkout_notice'] ?? null;
unset($_SESSION['checkout_notice']);

// ✅ Fetch cart items from database
if ($user_id) {

  $stmt = $conn->prepare("
    SELECT c.*, t.type_image
    FROM user_cart_items c
    LEFT JOIN product_types t 
        ON t.product_id = c.product_id AND t.type_name = c.type_name
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
</head>

<body class="bg-gray-100 font-sans">

  <?php include 'navbar/top.php'; ?>

  <div class="px-4 py-4">
    <nav class="text-sm text-gray-600">
      <a href="index" class="hover:text-orange-600">Home</a>
      <span class="mx-2">/</span>
      <a href="shop" class="hover:text-orange-600">Shop</a>
      <span class="mx-2">/</span>
      <span class="text-orange-600 font-medium">Cart</span>
    </nav>
  </div>

  <div class="px-4 py-2">
    <div class="bg-white shadow-lg rounded-lg p-6">
      <h2 class="text-3xl font-bold text-orange-700 mb-6 flex items-center gap-2">🛒 Your Cart</h2>

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
                  <th class="py-3 px-4">Details</th>
                  <th class="py-3 px-4">Qty</th>
                  <th class="py-3 px-4">Unit Price</th>

                  <th class="py-3 px-4">Image</th>

                  <th class="py-3 px-4">Product Name</th>
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
                        <img src="data:image/jpeg;base64,<?= base64_encode($item['type_image']) ?>" class="w-16 h-16 object-contain rounded" alt="Product Image">
                      <?php else: ?>
                        <div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-gray-500 text-sm">No Image</div>
                      <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 font-semibold text-gray-800">
                      <?= htmlspecialchars($item['codename']) ?>
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
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded shadow transition">Update Cart</button>
            <div class="text-xl font-bold text-orange-700">
              Total: ₱<?= number_format($total_price, 2) ?>
            </div>
          </div>
        </form>
        <div class="mt-6 flex flex-wrap gap-3 justify-end">
          <a href="../shop.php" class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-2 rounded transition">Continue Shopping</a>
          <a href="checkout.php" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded transition">Proceed to Checkout</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>

</html>