<?php
include '../../connection/connect.php';

$order_id = $_GET['order_id'] ?? 0;
$ref_no = $_GET['ref'] ?? '';

$order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
$client = $conn->query("SELECT * FROM client_info WHERE reference_no = '$ref_no'")->fetch_assoc();
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

      <div>
        <label class="block font-semibold text-sm">Client Name</label>
        <input type="text" value="<?= htmlspecialchars($client['name']) ?>" class="w-full border px-3 py-2 rounded" readonly>
      </div>

      <div>
        <label class="block font-semibold text-sm">Email</label>
        <input type="email" value="<?= htmlspecialchars($client['email']) ?>" class="w-full border px-3 py-2 rounded" readonly>
      </div>

      <div>
        <label class="block font-semibold text-sm">Amount Due</label>
        <input type="text" value="₱<?= number_format($order['total'] - ($order['total'] * $order['discount'] / 100) + $order['shipping_fee'], 2) ?>" class="w-full border px-3 py-2 rounded bg-yellow-50 font-bold text-green-700" readonly>
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
