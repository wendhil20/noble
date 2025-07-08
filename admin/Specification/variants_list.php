<?php
include '../../connection/connect.php';

$result = $conn->query("SELECT id, namevariant FROM product_variants ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Product Variants</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

<?php include '../navbar/top.php'; ?> 

  <div class=" py-10 px-4">
    <h2 class="text-3xl font-bold text-orange-700 mb-6"> Product Variants Description</h2>

    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-orange-100 text-orange-800 uppercase text-xs">
          <tr>
            <th class="px-6 py-3 border-b border-gray-200">ID</th>
            <th class="px-6 py-3 border-b border-gray-200">Variant Name</th>
            <th class="px-6 py-3 border-b border-gray-200">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 text-gray-700">
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-orange-50 transition">
              <td class="px-6 py-4 font-semibold"><?= $row['id'] ?></td>
              <td class="px-6 py-4"><?= htmlspecialchars($row['namevariant'] ?? '-') ?></td>
              <td class="px-6 py-4">
                <a href="set_description.php?id=<?= $row['id'] ?>"
                   class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">
                  Set Descriptions
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
