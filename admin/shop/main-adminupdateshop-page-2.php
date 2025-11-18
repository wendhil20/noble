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

// ✅ FIXED QUERY - Get product with color and variant (size) stock
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
    COALESCE(SUM(pv.stock), 0) as total_size_stock,
    COUNT(DISTINCT pc.id) as color_count,
    COUNT(DISTINCT pv.id) as size_count
  FROM products p
  LEFT JOIN product_colors pc ON p.id = pc.product_id
  LEFT JOIN product_variants pv ON p.id = pv.product_id
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
    .product-card {
      transition: all 0.3s ease;
      border: 1px solid #e5e7eb;
    }
    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(249, 115, 22, 0.15);
      border-color: #fb923c;
    }
    .badge-stock {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-roboto">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-100">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-search text-gray-400 mr-2"></i>Search Product
          </label>
          <input
            type="text"
            id="searchInput"
            placeholder="Search by name or code..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-box-open text-gray-400 mr-2"></i>Stock Status
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

    <!-- Products Grid -->
    <?php if ($products->num_rows > 0): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="productGrid">
        <?php while ($product = $products->fetch_assoc()): ?>
          <?php
          $createdAt = strtotime($product['created_at']);
          $updatedAt = strtotime($product['updated_at']);
          // ✅ Check if color OR size has stock
          $isInStock = ($product['total_color_stock'] > 0 || $product['total_size_stock'] > 0);
          ?>
          <div class="product-card bg-white rounded-xl overflow-hidden shadow-sm">
            <!-- Image Section -->
            <div class="relative w-full aspect-square bg-gradient-to-br from-gray-100 to-gray-50 flex items-center justify-center overflow-hidden group">
              <?php if (!empty($product['main_image'])): ?>
                <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
                  alt="Product Image"
                  class="h-full w-full object-contain group-hover:scale-105 transition duration-300" />
              <?php else: ?>
                <div class="text-center">
                  <i class="fas fa-image text-gray-300 text-3xl mb-2"></i>
                  <span class="text-gray-400 text-xs italic block">No image</span>
                </div>
              <?php endif; ?>
              
              <!-- Stock Badge -->
              <div class="absolute top-2 right-2">
                <span class="<?= $isInStock ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs font-semibold px-3 py-1 rounded-full flex items-center gap-1 badge-stock">
                  <i class="fas fa-<?= $isInStock ? 'check-circle' : 'times-circle' ?>"></i>
                  <?= $isInStock ? 'In Stock' : 'Out' ?>
                </span>
              </div>
            </div>

            <!-- Content Section -->
            <div class="p-4">
              <h3 class="font-bold text-gray-900 text-sm truncate product-name mb-1" title="<?= htmlspecialchars($product['product_name']) ?>">
                <?= htmlspecialchars($product['product_name']) ?>
              </h3>
              <p class="text-xs text-gray-500 truncate product-code mb-3" title="<?= htmlspecialchars($product['codename']) ?>">
                <?= htmlspecialchars($product['codename']) ?>
              </p>

              <!-- Info Grid -->
              <div class="space-y-2 mb-4 text-xs">
                <!-- ✅ Display color stock -->
                <div class="flex items-center justify-between">
                  <span class="text-gray-600"><i class="fas fa-palette mr-1"></i>Color Stock:</span>
                  <span class="font-semibold text-blue-600 product-qty"><?= $product['total_color_stock'] ?></span>
                </div>
                
                <!-- ✅ Display size stock -->
                <div class="flex items-center justify-between">
                  <span class="text-gray-600"><i class="fas fa-ruler mr-1"></i>Size Stock:</span>
                  <span class="font-semibold text-green-600 product-qty"><?= $product['total_size_stock'] ?></span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-gray-600"><i class="fas fa-calendar mr-1"></i>Created:</span>
                  <span class="font-medium text-gray-700"><?= date('M d', $createdAt) ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-600"><i class="fas fa-sync mr-1"></i>Updated:</span>
                  <span class="font-medium text-gray-700"><?= date('M d', $updatedAt) ?></span>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex flex-col gap-2 mt-4">
                <div class="flex gap-2">
                  <a href="update_product-page-2-A.php?id=<?= $product['id'] ?>"
                    class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-2 py-2 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1">
                    <i class="fas fa-edit"></i>
                    Edit
                  </a>
                  <form method="POST" class="flex-1" onsubmit="return confirm('⚠️ Delete this product permanently?');">
                    <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                    <button type="submit"
                      class="w-full bg-red-600 hover:bg-red-700 text-white px-2 py-2 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1">
                      <i class="fas fa-trash"></i>
                      Delete
                    </button>
                  </form>
                </div>
                <!-- STOCK BUTTON -->
                <a href="main-modify-stock-product-page-7.php?id=<?= $product['id'] ?>"
                  class="w-full bg-green-600 hover:bg-green-700 text-white px-2 py-2 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1">
                  <i class="fas fa-box-open"></i>
                  Manage Stock
                </a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
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

    <!-- Footer Navigation -->
    <div class="mt-10 flex justify-center">
      <a href="main-adminshop-page-1" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition shadow-sm">
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
      </a>
    </div>
  </div>

  <script>
    const searchInput = document.getElementById("searchInput");
    const quantityFilter = document.getElementById("quantityFilter");
    const cards = document.querySelectorAll(".product-card");

    function filterCards() {
      const searchTerm = searchInput.value.toLowerCase();
      const quantityValue = quantityFilter.value;
      let visibleCount = 0;

      cards.forEach(card => {
        const name = card.querySelector(".product-name").textContent.toLowerCase();
        const code = card.querySelector(".product-code").textContent.toLowerCase();
        const stockElements = card.querySelectorAll(".product-qty");
        const colorStock = parseInt(stockElements[0].textContent);
        const sizeStock = parseInt(stockElements[1].textContent);
        const totalStock = colorStock + sizeStock;

        const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
        let matchesFilter = true;

        if (quantityValue === "in-stock") {
          matchesFilter = totalStock > 0;
        } else if (quantityValue === "out-of-stock") {
          matchesFilter = totalStock === 0;
        }

        if (matchesSearch && matchesFilter) {
          card.style.display = "";
          visibleCount++;
        } else {
          card.style.display = "none";
        }
      });
    }

    searchInput.addEventListener("input", filterCards);
    quantityFilter.addEventListener("change", filterCards);
  </script>

</body>

</html>