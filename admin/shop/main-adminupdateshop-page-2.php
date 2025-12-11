<?php
//main-adminupdateshop-page-2.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

// DELETE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int) $_POST['delete_id'];

  try {
    $conn->begin_transaction();

    $imagesToDelete = [];

    // Main image and sub_images
    $res = $conn->prepare("SELECT main_image, sub_images FROM products WHERE id = ?");
    $res->bind_param("i", $deleteId);
    $res->execute();
    $res->bind_result($mainImage, $subImages);
    $res->fetch();
    if ($mainImage && file_exists("../../" . $mainImage)) $imagesToDelete[] = "../../" . $mainImage;

    if (!empty($subImages)) {
      $subImagesArray = json_decode($subImages, true);
      if (is_array($subImagesArray)) {
        foreach ($subImagesArray as $subImage) {
          if (!empty($subImage)) {
            $cleanPath = ltrim($subImage, './');
            $possiblePaths = [
              "../../" . $cleanPath,
              $subImage,
              "./" . $cleanPath,
            ];

            foreach ($possiblePaths as $filePath) {
              if (file_exists($filePath)) {
                $imagesToDelete[] = $filePath;
                break;
              }
            }
          }
        }
      }
    }
    $res->close();

    // Color images
    $res = $conn->prepare("SELECT image FROM product_colors WHERE product_id = ?");
    $res->bind_param("i", $deleteId);
    $res->execute();
    $result = $res->get_result();
    while ($row = $result->fetch_assoc()) {
      if (!empty($row['image']) && file_exists("../../" . $row['image'])) $imagesToDelete[] = "../../" . $row['image'];
    }
    $res->close();

    // Type images
    $typeIds = [];
    $res = $conn->prepare("SELECT id, type_image FROM product_types WHERE product_id = ?");
    $res->bind_param("i", $deleteId);
    $res->execute();
    $result = $res->get_result();
    while ($row = $result->fetch_assoc()) {
      $typeIds[] = $row['id'];
      if (!empty($row['type_image']) && file_exists("../../" . $row['type_image'])) $imagesToDelete[] = "../../" . $row['type_image'];
    }
    $res->close();

    foreach ($typeIds as $typeId) {
      $res = $conn->prepare("SELECT image FROM product_variants WHERE type_id = ?");
      $res->bind_param("i", $typeId);
      $res->execute();
      $result = $res->get_result();
      while ($row = $result->fetch_assoc()) {
        if (!empty($row['image']) && file_exists("../../" . $row['image'])) $imagesToDelete[] = "../../" . $row['image'];
      }
      $res->close();
    }

    // Delete all collected images
    foreach ($imagesToDelete as $filePath) @unlink($filePath);

    // Delete database records
    foreach ($typeIds as $typeId) {
      $stmt = $conn->prepare("DELETE FROM product_variants WHERE type_id = ?");
      $stmt->bind_param("i", $typeId);
      $stmt->execute();
      $stmt->close();
    }

    $stmt1 = $conn->prepare("DELETE FROM product_colors WHERE product_id = ?");
    $stmt1->bind_param("i", $deleteId);
    $stmt1->execute();
    $stmt1->close();

    $stmt2 = $conn->prepare("DELETE FROM product_types WHERE product_id = ?");
    $stmt2->bind_param("i", $deleteId);
    $stmt2->execute();
    $stmt2->close();

    $stmt3 = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt3->bind_param("i", $deleteId);
    $stmt3->execute();
    $stmt3->close();

    $conn->commit();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  } catch (Exception $e) {
    $conn->rollback();
    echo "Error deleting product: " . $e->getMessage();
  }
}

// ✅ UPDATED QUERY - Get stock from product_variant_colors (stock_quantity)
$products = $conn->query("
  SELECT 
    p.id, 
    p.product_name, 
    p.codename, 
    p.quantity, 
    p.main_image, 
    p.created_at, 
    p.updated_at,
    COALESCE(SUM(pc.stock), 0) as total_color_stock,
    COALESCE(SUM(pvc.stock_quantity), 0) as total_size_stock,
    COUNT(DISTINCT pc.id) as color_count,
    COUNT(DISTINCT pv.id) as size_count
  FROM products p
  LEFT JOIN product_colors pc ON p.id = pc.product_id
  LEFT JOIN product_variants pv ON p.id = pv.product_id
  LEFT JOIN product_variant_colors pvc ON pv.id = pvc.variant_id
  GROUP BY p.id, p.product_name, p.codename, p.quantity, p.main_image, p.created_at, p.updated_at
  ORDER BY p.product_name
");

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Products</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .table-row-hover {
      transition: all 0.2s ease;
    }
    .table-row-hover:hover {
      background-color: #faf8f6;
    }
    .stock-badge {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }
    .sticky-header {
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .product-image-cell {
      width: 60px;
      height: 60px;
      object-fit: contain;
      background: #f9fafb;
      border-radius: 0.5rem;
    }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-roboto">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="mb-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-4xl font-bold text-gray-900 flex items-center gap-3">
            <i class="fas fa-box text-orange-600"></i>
            Manage Products
          </h1>
          <p class="text-gray-600 mt-2">View, update, or delete your products</p>
        </div>
        <div class="flex gap-3 flex-wrap">
          <a href="newitem-page-3-A.php" 
             class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition shadow-sm">
            <i class="fas fa-edit"></i>
            Update New Item
          </a>
             <a href="main-adminshop-page-1" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition shadow-sm">
        
        Back 
      </a>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-200">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-search text-orange-500 mr-2"></i>Search Product
          </label>
          <input
            type="text"
            id="searchInput"
            placeholder="Search by name or code..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-filter text-orange-500 mr-2"></i>Stock Status
          </label>
          <select
            id="quantityFilter"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
            <option value="all">All Products</option>
            <option value="in-stock">In Stock</option>
            <option value="out-of-stock">Out of Stock</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Products Table -->
    <?php if ($products->num_rows > 0): ?>
      <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="sticky-header bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
              <tr>
                <th class="px-6 py-4 text-left font-semibold text-gray-700">Image</th>
                <th class="px-6 py-4 text-left font-semibold text-gray-700">Product Name</th>
                <th class="px-6 py-4 text-left font-semibold text-gray-700">Product Code</th>
                <th class="px-6 py-4 text-center font-semibold text-gray-700">Stock</th>
                <th class="px-6 py-4 text-center font-semibold text-gray-700">Status</th>
                <th class="px-6 py-4 text-center font-semibold text-gray-700">Created</th>
                <th class="px-6 py-4 text-center font-semibold text-gray-700">Updated</th>
                <th class="px-6 py-4 text-center font-semibold text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody id="productTableBody">
              <?php while ($product = $products->fetch_assoc()): ?>
                <?php
                $createdAt = strtotime($product['created_at']);
                $updatedAt = strtotime($product['updated_at']);
                
                // ✅ UPDATED LOGIC - Check if ANY color OR size has stock
                $totalStock = $product['total_color_stock'] + $product['total_size_stock'];
                $isInStock = ($totalStock > 0); // Simple check: if total stock > 0, then IN STOCK
                
                // Alternative detailed check (optional):
                // $isInStock = ($product['total_color_stock'] > 0 || $product['total_size_stock'] > 0);
                ?>
                <tr class="table-row-hover border-b border-gray-100 product-row " 
                    data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>"
                    data-code="<?= htmlspecialchars(strtolower($product['codename'])) ?>"
                    data-stock="<?= $totalStock ?>">
                  
                  <!-- Product Image -->
                  <td class="px-6 py-4">
                    <div class="flex items-center justify-center">
                      <?php if (!empty($product['main_image'])): ?>
                        <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
                          alt="<?= htmlspecialchars($product['product_name']) ?>"
                          class="product-image-cell" />
                      <?php else: ?>
                        <div class="w-14 h-14 bg-gray-200 rounded flex items-center justify-center">
                          <i class="fas fa-image text-gray-400 text-lg"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Product Name -->
                  <td class="px-6 py-4">
                    <span class="font-medium text-gray-900 product-name" title="<?= htmlspecialchars($product['product_name']) ?>">
                      <?= htmlspecialchars(substr($product['product_name'], 0, 25)) . (strlen($product['product_name']) > 25 ? '...' : '') ?>
                    </span>
                  </td>

                  <!-- Product Code -->
                  <td class="px-6 py-4">
                    <span class="text-black font-mono text-xs bg-gray-100 px-2 py-1 rounded product-code uppercase" title="<?= htmlspecialchars($product['codename']) ?>">
                      <?= htmlspecialchars($product['codename']) ?>
                    </span>
                  </td>


                  <!-- Size Stock -->
                  <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center gap-1 bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-semibold size-stock">
                      <?= $product['total_size_stock'] ?>
                    </span>
                  </td>

                  <!-- Status Badge -->
                  <td class="px-6 py-4 text-center">
                    <span class="<?= $isInStock ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs font-semibold px-3 py-1 rounded-full flex items-center justify-center gap-1 stock-badge mx-auto w-fit">
                      <i class="fas fa-<?= $isInStock ? 'check-circle' : 'times-circle' ?>"></i>
                      <?= $isInStock ? 'In Stock' : 'Out of Stock' ?>
                    </span>
                  </td>

                  <!-- Created Date -->
                  <td class="px-6 py-4 text-center text-gray-600 text-xs">
                    <i class="fas fa-calendar-alt text-orange-500 mr-1"></i>
                    <?= date('M d, Y', $createdAt) ?>
                  </td>

                  <!-- Updated Date -->
                  <td class="px-6 py-4 text-center text-gray-600 text-xs">
                    <i class="fas fa-sync text-blue-500 mr-1"></i>
                    <?= date('M d, Y', $updatedAt) ?>
                  </td>

                  <!-- Actions -->
                  <td class="px-6 py-4">
                    <div class="flex gap-2 justify-center flex-wrap">
                      <a href="update_product-page-2-A.php?id=<?= $product['id'] ?>"
                        class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded text-xs font-medium transition flex items-center gap-1 whitespace-nowrap"
                        title="Edit Product">
                        <i class="fas fa-edit"></i>
                        Edit
                      </a>
                
                      <form method="POST" class="inline" onsubmit="return confirm('⚠️ Delete this product permanently?');">
                        <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                        <button type="submit"
                          class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-medium transition flex items-center gap-1 whitespace-nowrap"
                          title="Delete Product">
                          <i class="fas fa-trash"></i>
                          Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <!-- Empty State -->
      <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
        <i class="fas fa-inbox text-gray-300 text-5xl mb-4 block"></i>
        <p class="text-gray-600 text-lg font-medium mb-2">No Products Yet</p>
        <p class="text-gray-500 mb-6">Start adding products to your store</p>
        <a href="additem-page.php" class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg font-medium transition">
          <i class="fas fa-plus mr-2"></i>Add Your First Product
        </a>
      </div>
    <?php endif; ?>

  </div>

  <script>
    const searchInput = document.getElementById("searchInput");
    const quantityFilter = document.getElementById("quantityFilter");
    const rows = document.querySelectorAll(".product-row");

    function filterRows() {
      const searchTerm = searchInput.value.toLowerCase();
      const quantityValue = quantityFilter.value;
      let visibleCount = 0;

      rows.forEach(row => {
        const name = row.dataset.name;
        const code = row.dataset.code;
        const stock = parseInt(row.dataset.stock);

        const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
        let matchesFilter = true;

        if (quantityValue === "in-stock") {
          matchesFilter = stock > 0;
        } else if (quantityValue === "out-of-stock") {
          matchesFilter = stock === 0;
        }

        if (matchesSearch && matchesFilter) {
          row.style.display = "";
          visibleCount++;
        } else {
          row.style.display = "none";
        }
      });

      // Show no results message if needed
      const tbody = document.getElementById("productTableBody");
      const noResults = tbody.querySelector(".no-results");
      if (visibleCount === 0 && !noResults) {
        const tr = document.createElement("tr");
        tr.className = "no-results";
        tr.innerHTML = '<td colspan="10" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-search mr-2"></i>No products found</td>';
        tbody.appendChild(tr);
      } else if (visibleCount > 0 && noResults) {
        noResults.remove();
      }
    }

    searchInput.addEventListener("input", filterRows);
    quantityFilter.addEventListener("change", filterRows);
  </script>

</body>

</html>