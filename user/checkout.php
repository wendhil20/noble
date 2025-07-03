<?php
session_start();
include '../connection/connect.php';

// ✅ Restore session from remember_token if needed
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
$error = null;

if ($user_id) {
    // ✅ Fetch cart items
    $stmt = $conn->prepare("SELECT * FROM user_cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total_price += $row['price'] * $row['quantity'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['customer_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $address = $_POST['address'];
    $zipcode = $_POST['zipcode'];

    if ($name && $email && $mobile && $address && $zipcode && !empty($cart_items)) {
        // ✅ Reset auto-increment if needed
        $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE order_items AUTO_INCREMENT = 1");

        // ✅ Save order
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssd", $name, $email, $mobile, $address, $zipcode, $total_price);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // ✅ Save each item
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_name, codename, type_name, variant_color, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $stmt->bind_param(
                "issssdii",
                $order_id,
                $item['product_name'] ?? $item['variant_name'], // fallback if product_name not present
                $item['codename'],
                $item['type_name'],
                $item['color_name'] ?: $item['variant_name'],
                $item['price'],
                $item['quantity'],
                $subtotal
            );
            $stmt->execute();
        }
        $stmt->close();

        // ✅ Clear cart
        $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['checkout_notice'] = 'Order placed successfully!';
        echo "<script>window.location.href = 'cart_view.php';</script>";
        exit;
    } else {
        $error = "Please complete all fields and ensure your cart is not empty.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">
    <?php include 'navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow mt-3 max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold text-orange-700 mb-6">Checkout</h2>

        <?php if (isset($error)): ?>
            <p class="text-red-600 mb-4"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">

            <!-- Customer Details -->
            <div><label class="block font-medium">Full Name</label>
                <input type="text" name="customer_name" required class="w-full border px-4 py-2 rounded" />
            </div>

            <div><label class="block font-medium">Email</label>
                <input type="email" name="email" required class="w-full border px-4 py-2 rounded" />
            </div>

            <div><label class="block font-medium">Mobile Number</label>
                <input type="tel" name="mobile" pattern="[0-9]{11}" required class="w-full border px-4 py-2 rounded" placeholder="e.g. 09171234567" />
            </div>

            <div><label class="block font-medium">Full Address</label>
                <textarea name="address" rows="3" required class="w-full border px-4 py-2 rounded resize-none"></textarea>
            </div>

            <div><label class="block font-medium">ZIP Code</label>
                <input type="text" name="zipcode" pattern="[0-9]{4}" required class="w-full border px-4 py-2 rounded" />
            </div>

            <!-- Order Summary -->
            <h3 class="text-xl font-semibold mt-6 mb-2">Order Summary</h3>
            <ul class="divide-y text-sm max-h-60 overflow-y-auto border rounded-md p-2 bg-white">
                <?php foreach ($cart_items as $item): ?>
                    <li class="py-2">
                        <?= htmlspecialchars($item['variant_name']) ?> — 
<?= htmlspecialchars($item['type_name']) ?> / 
<?= htmlspecialchars($item['size'] ?: 'N/A') ?> / 
<?= htmlspecialchars($item['color_name'] ?: '—') ?>

                        <span class="float-right">₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="text-right text-xl mt-4 font-bold text-green-700">
                Total: ₱<?= number_format($total_price, 2) ?>
            </div>

            <div class="text-sm text-gray-600 text-center mt-4">
                By placing your order, you agree to our
                <a href="terms.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Terms</a>
                and
                <a href="privacy_policy.php" class="text-blue-600 underline hover:text-blue-800" target="_blank">Privacy Policy</a>.
            </div>

            <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 mt-6">
                Place Order
            </button>

            <div class="mt-4">
                <a href="cart_view.php" class="inline-flex items-center gap-2 text-sm text-orange-700 font-medium px-4 py-2 rounded-lg bg-orange-100 hover:bg-orange-200 transition">
                    ← Back to Cart
                </a>
            </div>
        </form>
    </div>
</body>

</html>
