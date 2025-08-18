<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "User not logged in.";
    exit();
}

$user_id = $_SESSION['user_id'];

$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
if (!$user) {
    echo "User not found.";
    exit();
}

$user_email = $user['email'];

$order = $conn->query("SELECT * FROM orders WHERE email = '$user_email' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$order) {
    echo "No orders found.";
    exit();
}

$order_id = $order['id']; // Important!
$ref_no = $order['reference_no'] ?? 'N/A';
$amount_due = $order['amount_due'] ?? 0.00;

?>

<!DOCTYPE html>
<html>
<head>
  <title>Upload Payment Proof</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6 font-sans">
  <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h1 class="text-xl font-bold mb-4 text-green-700">Payment Details for Order #<?= $order_id ?></h1>

    <form action="upload_proof.php" method="POST" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="order_id" value="<?= $order_id ?>">
      <input type="hidden" name="ref_no" value="<?= htmlspecialchars($ref_no) ?>">
      <input type="hidden" name="user_id" value="<?= $user_id ?>">

      <div>
        <label class="block font-semibold text-sm">Client Name</label>
        <input type="text" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border px-3 py-2 rounded" readonly>
      </div>

      <div>
        <label class="block font-semibold text-sm">Email</label>
        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full border px-3 py-2 rounded" readonly>
      </div>

      <div>
        <label class="block font-semibold text-sm">Amount Due</label>
        <input type="text" value="₱<?= number_format($amount_due, 2) ?>" class="w-full border px-3 py-2 rounded bg-yellow-50 font-bold text-green-700" readonly>
      </div>

      <div>
        <label class="block font-semibold text-sm">Upload Proof of Payment</label>
        <input type="file" name="payment_proof" class="w-full border px-3 py-2 rounded" required>
      </div>

      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded shadow">
        Submit Payment Proof
      </button>
    </form>
  </div>
</body>
</html>
