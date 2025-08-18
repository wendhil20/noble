<?php
session_name("nobleadmin");
session_start();
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

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

<div class="py-10 px-4">
  <h2 class="text-3xl font-bold text-orange-700 mb-6">Product Variants Description</h2>

  <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="bg-white p-5 rounded-xl shadow hover:shadow-lg transition">
        <div class="text-sm text-gray-500 mb-2">Variant ID: <?= $row['id'] ?></div>
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
          <?= htmlspecialchars($row['namevariant'] ?? '-') ?>
        </h3>
        <a href="set_description.php?id=<?= $row['id'] ?>"
           class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">
          Set Descriptions
        </a>
      </div>
    <?php endwhile; ?>
  </div>
</div>

</body>
</html>
