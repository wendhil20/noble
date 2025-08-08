<?php
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

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
  session_unset();
  session_destroy();
  header("Location: ../../loginpage/index.php?timeout=true");
  exit();
}
$_SESSION['last_activity'] = time();

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
        // Clean the path - remove leading ../ if present
        $cleanPath = ltrim($subImage, './');
        
        // Try different path combinations
        $possiblePaths = [
          "../../" . $cleanPath,  // From current location
          $subImage,              // Original path as stored
          "./" . $cleanPath,      // Relative to current
        ];
        
        foreach ($possiblePaths as $filePath) {
          if (file_exists($filePath)) {
            $imagesToDelete[] = $filePath;
            break; // Found the correct path, no need to try others
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

$products = $conn->query("SELECT id, product_name, codename, quantity, main_image, created_at, updated_at FROM products ORDER BY product_name");

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Select Product to Update</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-full mx-auto bg-white p-6 rounded-lg shadow mt-5">
    <h2 class="text-2xl font-bold mb-6 text-orange-600">Select Product to Update</h2>

    <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
      <input
        type="text"
        id="searchInput"
        placeholder="Search by name or codename..."
        class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" />
      <select
        id="quantityFilter"
        class="w-full md:w-1/4 px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
        <option value="all">All Quantities</option>
        <option value="in-stock">In Stock</option>
        <option value="out-of-stock">Out of Stock</option>
      </select>
    </div>

    <?php if ($products->num_rows > 0): ?>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-6" id="productGrid">
       <?php while ($product = $products->fetch_assoc()): ?>
  <?php
    $createdAt = strtotime($product['created_at']);
    $updatedAt = strtotime($product['updated_at']);
 $isFirstTime = $createdAt === $updatedAt;
$canUpdate = $isFirstTime || ((time() - $updatedAt) >= (7 * 24 * 60 * 60));

  ?>
  <div class="bg-white border rounded-lg p-4 shadow product-card">
    <div class="w-full aspect-square bg-gray-100 rounded mb-3 flex items-center justify-center overflow-hidden">
      <?php if (!empty($product['main_image'])): ?>
        <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
             alt="Product Image"
             class="h-full w-full object-contain rounded" />
      <?php else: ?>
        <span class="text-gray-400 text-sm italic">No image</span>
      <?php endif; ?>
    </div>
    <div class="space-y-1 text-sm">
      <div class="font-bold text-orange-600 truncate product-name"><?= htmlspecialchars($product['product_name']) ?></div>
      <div class="text-gray-500 truncate product-code"><?= htmlspecialchars($product['codename']) ?></div>
      <div class="text-xs text-gray-600">
        Quantity: <span class="font-medium product-qty"><?= $product['quantity'] ?></span>
      </div>
      <div class="text-xs text-gray-600">
        Created: <span class="font-medium"><?= date('Y-m-d', $createdAt) ?></span>
      </div>
      <div class="text-xs text-gray-600">
        Last Updated: <span class="font-medium"><?= date('Y-m-d', $updatedAt) ?></span>
      </div>
    </div>
    <div class="mt-4 flex justify-between">
      <a href="update_product.php?id=<?= $product['id'] ?>"
         class="<?= $canUpdate ? 'bg-orange-600 hover:bg-orange-700' : 'bg-gray-400 cursor-not-allowed' ?> text-white px-3 py-1 rounded text-xs transition"
         <?= $canUpdate ? '' : 'onclick="return false;" title=\'You can only update this product after 1 week from last update.\'' ?>>
        Update
      </a>
      <form method="POST" onsubmit="return confirm('⚠️ This will permanently delete the product and all its images. Continue?');">
        <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
        <button type="submit"
                class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">
          Delete
        </button>
      </form>
    </div>
  </div>
<?php endwhile; ?>

      </div>
    <?php else: ?>
      <div class="text-center py-8">
        <p class="text-gray-600 text-lg">No products found.</p>
        <a href="../add_product/" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
          Add New Product
        </a>
      </div>
    <?php endif; ?>

    <div class="mt-8 text-center">
      <a href="adminshop" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 mr-4">
        Back to Dashboard
      </a>
      <a href="newitem" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        Update New Item
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

      cards.forEach(card => {
        const name = card.querySelector(".product-name").textContent.toLowerCase();
        const code = card.querySelector(".product-code").textContent.toLowerCase();
        const quantity = parseInt(card.querySelector(".product-qty").textContent);

        const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
        let matchesFilter = true;

        if (quantityValue === "in-stock") {
          matchesFilter = quantity > 0;
        } else if (quantityValue === "out-of-stock") {
          matchesFilter = quantity === 0;
        }

        card.style.display = matchesSearch && matchesFilter ? "" : "none";
      });
    }

    searchInput.addEventListener("input", filterCards);
    quantityFilter.addEventListener("change", filterCards);
  </script>

</body>
</html>