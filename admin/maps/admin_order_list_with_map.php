<?php
include '../../connection/connect.php';

$orders = $conn->query("SELECT id, customer_name, address FROM orders ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Order List (with Map View)</title>
  <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
  <h1 class="text-2xl font-bold mb-6">📋 Order List (Click to View Location)</h1>

  <table class="w-full bg-white shadow-md rounded overflow-hidden">
    <thead class="bg-orange-600 text-white">
      <tr>
        <th class="py-2 px-4 text-left">Order ID</th>
        <th class="py-2 px-4 text-left">Customer</th>
        <th class="py-2 px-4 text-left">Address</th>
        <th class="py-2 px-4">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $orders->fetch_assoc()): ?>
        <tr class="border-b hover:bg-gray-50">
          <td class="py-2 px-4"><?= $row['id'] ?></td>
          <td class="py-2 px-4"><?= htmlspecialchars($row['customer_name']) ?></td>
          <td class="py-2 px-4"><?= htmlspecialchars($row['address']) ?></td>
          <td class="py-2 px-4 text-center">
            <a href="order_location.php?id=<?= $row['id'] ?>"
               class="bg-blue-600 text-white py-1 px-3 rounded hover:bg-blue-700"
               target="_blank">
              View Map
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
