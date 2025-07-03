<?php
include '../connection/connect.php';

// Get selected categories from checkbox
$selected_categories = $_GET['category'] ?? [];
$search_keyword = $_GET['search'] ?? '';

$filter_sql = 'WHERE 1=1';

// Filter by category
if (!empty($selected_categories)) {
    $escaped = array_map(function ($item) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $item) . "'";
    }, $selected_categories);
    $filter_sql .= " AND codename IN (" . implode(',', $escaped) . ")";
}

// Filter by search
if (!empty($search_keyword)) {
    $search_safe = mysqli_real_escape_string($conn, $search_keyword);
    $filter_sql .= " AND product_name LIKE '%$search_safe%'";
}

// ✅ Include description in query
$query = $conn->query("SELECT id, product_name, main_image, description FROM products $filter_sql");

$all_categories = ['furniture', 'material']; // Extend as needed
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shop Products</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Optional fallback if line-clamp plugin is not included */
    .line-clamp-3 {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  </style>
</head>
<body class="bg-gray-100 font-sans">

  <?php include 'navbar/top.php'; ?>

  <div class="max-w-screen-xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-6 text-orange-600">Shop Products</h1>

    <!-- Filter + Search -->
    <form method="GET" class="mb-8 space-y-4">
      <div class="flex flex-wrap items-center gap-4">
        <?php foreach ($all_categories as $cat): ?>
          <label class="inline-flex items-center space-x-2">
            <input type="checkbox" name="category[]" value="<?= $cat ?>" <?= in_array($cat, $selected_categories) ? 'checked' : '' ?>
              class="form-checkbox h-5 w-5 text-orange-500 border-gray-300 rounded focus:ring-2 focus:ring-orange-500" />
            <span class="text-gray-800 capitalize"><?= htmlspecialchars($cat) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Search Input -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <input
          type="text"
          name="search"
          value="<?= htmlspecialchars($search_keyword) ?>"
          placeholder=" Search products..."
          class="w-full sm:w-64 border border-gray-300 px-4 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
        <button
          type="submit"
          class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
          Filter
        </button>
      </div>
    </form>

    <!-- Product Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
      <?php while ($row = $query->fetch_assoc()): ?>
        <?php
          $product_id = (int)$row['id'];
          $variant_count_result = $conn->query("
            SELECT COUNT(*) as total 
            FROM product_variants pv
            JOIN product_types pt ON pv.type_id = pt.id
            WHERE pt.product_id = $product_id
          ");
          $variant_count = $variant_count_result->fetch_assoc()['total'] ?? 0;
        ?>

        <a href="product_view.php?id=<?= $product_id ?>"
          class="relative bg-white rounded-2xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 p-4 group text-center flex flex-col justify-between h-full">

          <!-- Arrow Badge -->
          <div class="absolute top-2 right-2 bg-orange-500 text-white rounded-full p-1 shadow-md z-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>

          <!-- Image -->
          <?php if (!empty($row['main_image'])): ?>
            <div class="aspect-[4/5] bg-gray-50 rounded-lg overflow-hidden mb-3">
              <img
                src="data:image/jpeg;base64,<?= base64_encode($row['main_image']) ?>"
                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                alt="<?= htmlspecialchars($row['product_name']) ?>" />
            </div>
          <?php else: ?>
            <div class="w-full aspect-[4/5] flex items-center justify-center bg-gray-200 rounded-lg text-gray-500 text-sm mb-3">
              No Image
            </div>
          <?php endif; ?>

          <!-- Title -->
          <h2 class="text-base font-semibold text-gray-800 leading-snug break-words">
            <?= htmlspecialchars($row['product_name']) ?>
          </h2>

          <!-- Description -->
          <p class="text-xs text-gray-600 mt-1 line-clamp-3">
            <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
          </p>

          <!-- Variant Count -->
          <div class="mt-2">
            <span class="inline-block text-xs px-3 py-1 bg-orange-500 text-white rounded-full font-medium">
              <?= $variant_count ?> Variant<?= $variant_count !== 1 ? 's' : '' ?>
            </span>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>
