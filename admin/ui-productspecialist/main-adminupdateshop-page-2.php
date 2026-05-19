<?php
//main-adminupdateshop-page-2.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: " . BASE_URL . "/main");
  exit();
}

// ── Current user info ────────────────────────────────────────────────────────
$current_user = $_SESSION['noble_user'];
$current_role = $_SESSION['noble_role'] ?? '';
$is_superadmin = ($current_role === 'superadmin');

// ── Ensure added_by column exists ────────────────────────────────────────────
$chk_col = $conn->query("SHOW COLUMNS FROM products LIKE 'added_by'");
if ($chk_col->num_rows == 0) {
  $conn->query("ALTER TABLE products ADD COLUMN added_by VARCHAR(100) NULL DEFAULT NULL");
}

// ARCHIVE/RESTORE/DELETE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  // ── BULK ARCHIVE ────────────────────────────────────────────────────────────
  if ($action === 'bulk_archive') {
    $bulkIds = $_POST['bulk_ids'] ?? [];
    if (empty($bulkIds)) {
      header("Location: " . $_SERVER['PHP_SELF']);
      exit();
    }

    $sanitizedIds = array_map('intval', $bulkIds);
    $sanitizedIds = array_filter($sanitizedIds, fn($id) => $id > 0);

    if (!$is_superadmin) {
      $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
      $types = str_repeat('i', count($sanitizedIds)) . 's';
      $params = array_merge(array_values($sanitizedIds), [$current_user]);
      $chk = $conn->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND added_by = ?");
      $chk->bind_param($types, ...$params);
      $chk->execute();
      $result = $chk->get_result();
      $sanitizedIds = [];
      while ($row = $result->fetch_assoc()) {
        $sanitizedIds[] = $row['id'];
      }
      $chk->close();
    }

    if (!empty($sanitizedIds)) {
      $placeholders = implode(',', $sanitizedIds);
      $conn->begin_transaction();
      $conn->query("UPDATE products SET is_archived = 1 WHERE id IN ($placeholders)");
      $conn->commit();
    }

    header("Location: " . BASE_URL . "/updateproduct");
    exit();
  }

  if ($action === 'bulk_restore') {
    $bulkIds = $_POST['bulk_ids'] ?? [];
    if (empty($bulkIds)) {
      header("Location: " . BASE_URL . "/updateproduct");
      exit();
    }

    $sanitizedIds = array_map('intval', $bulkIds);
    $sanitizedIds = array_filter($sanitizedIds, fn($id) => $id > 0);

    if (!$is_superadmin) {
      $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
      $types = str_repeat('i', count($sanitizedIds)) . 's';
      $params = array_merge(array_values($sanitizedIds), [$current_user]);
      $chk = $conn->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND added_by = ?");
      $chk->bind_param($types, ...$params);
      $chk->execute();
      $result = $chk->get_result();
      $sanitizedIds = [];
      while ($row = $result->fetch_assoc()) {
        $sanitizedIds[] = $row['id'];
      }
      $chk->close();
    }

    if (!empty($sanitizedIds)) {
      $placeholders = implode(',', $sanitizedIds);
      $conn->begin_transaction();
      $conn->query("UPDATE products SET is_archived = 0 WHERE id IN ($placeholders)");
      $conn->commit();
    }

    header("Location: " . BASE_URL . "/updateproduct?show=archived");
    exit();
  }

  // ── Single product actions ──────────────────────────────────────────────────
  $productId = (int) ($_POST['product_id'] ?? 0);

  if (!$is_superadmin) {
    $own_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND added_by = ?");
    $own_check->bind_param("is", $productId, $current_user);
    $own_check->execute();
    $own_result = $own_check->get_result();
    if ($own_result->num_rows === 0) {
      echo "<script>alert('Access denied. You can only manage your own products.'); history.back();</script>";
      exit();
    }
    $own_check->close();
  }

  try {
    $conn->begin_transaction();

    if ($action === 'archive') {
      $stmt = $conn->prepare("UPDATE products SET is_archived = 1 WHERE id = ?");
      $stmt->bind_param("i", $productId);
      $stmt->execute();
      $stmt->close();

    } elseif ($action === 'restore') {
      $stmt = $conn->prepare("UPDATE products SET is_archived = 0 WHERE id = ?");
      $stmt->bind_param("i", $productId);
      $stmt->execute();
      $stmt->close();

    } elseif ($action === 'permanent_delete') {
      if (!$is_superadmin) {
        echo "<script>alert('Access denied. Only superadmin can permanently delete products.'); history.back();</script>";
        exit();
      }
      $imagesToDelete = [];

      $res = $conn->prepare("SELECT main_image, sub_images FROM products WHERE id = ?");
      $res->bind_param("i", $productId);
      $res->execute();
      $res->bind_result($mainImage, $subImages);
      $res->fetch();
      if ($mainImage && file_exists("../../" . $mainImage))
        $imagesToDelete[] = "../../" . $mainImage;
      if (!empty($subImages)) {
        $subImagesArray = json_decode($subImages, true);
        if (is_array($subImagesArray)) {
          foreach ($subImagesArray as $subImage) {
            if (!empty($subImage)) {
              $cleanPath = ltrim($subImage, './');
              foreach (["../../" . $cleanPath, $subImage, "./" . $cleanPath] as $fp) {
                if (file_exists($fp)) {
                  $imagesToDelete[] = $fp;
                  break;
                }
              }
            }
          }
        }
      }
      $res->close();

      $res = $conn->prepare("SELECT image FROM product_colors WHERE product_id = ?");
      $res->bind_param("i", $productId);
      $res->execute();
      $result = $res->get_result();
      while ($row = $result->fetch_assoc()) {
        if (!empty($row['image']) && file_exists("../../" . $row['image']))
          $imagesToDelete[] = "../../" . $row['image'];
      }
      $res->close();

      $typeIds = [];
      $res = $conn->prepare("SELECT id, type_image FROM product_types WHERE product_id = ?");
      $res->bind_param("i", $productId);
      $res->execute();
      $result = $res->get_result();
      while ($row = $result->fetch_assoc()) {
        $typeIds[] = $row['id'];
        if (!empty($row['type_image']) && file_exists("../../" . $row['type_image']))
          $imagesToDelete[] = "../../" . $row['type_image'];
      }
      $res->close();

      foreach ($typeIds as $typeId) {
        $res = $conn->prepare("SELECT image FROM product_variants WHERE type_id = ?");
        $res->bind_param("i", $typeId);
        $res->execute();
        $result = $res->get_result();
        while ($row = $result->fetch_assoc()) {
          if (!empty($row['image']) && file_exists("../../" . $row['image']))
            $imagesToDelete[] = "../../" . $row['image'];
        }
        $res->close();
      }

      foreach ($imagesToDelete as $filePath)
        @unlink($filePath);

      foreach ($typeIds as $typeId) {
        $stmt = $conn->prepare("DELETE FROM product_variants WHERE type_id = ?");
        $stmt->bind_param("i", $typeId);
        $stmt->execute();
        $stmt->close();
      }

      $stmt1 = $conn->prepare("DELETE FROM product_colors WHERE product_id = ?");
      $stmt1->bind_param("i", $productId);
      $stmt1->execute();
      $stmt1->close();

      $stmt2 = $conn->prepare("DELETE FROM product_types WHERE product_id = ?");
      $stmt2->bind_param("i", $productId);
      $stmt2->execute();
      $stmt2->close();

      $stmt3 = $conn->prepare("DELETE FROM products WHERE id = ?");
      $stmt3->bind_param("i", $productId);
      $stmt3->execute();
      $stmt3->close();
    }

    $conn->commit();
    $redirect_show = isset($_POST['current_show']) ? $_POST['current_show'] : 'active';
    header("Location: " . BASE_URL . "/updateproduct?show=" . $redirect_show);
    exit();
  } catch (Exception $e) {
    $conn->rollback();
    echo "Error processing product: " . $e->getMessage();
  }
}

// ── Fetch categories, subcategories, sub-subcategories ──────────────────────
$category_result = $conn->query("SELECT * FROM categories ORDER BY name");
$subcategory_result = $conn->query(
  "SELECT ps.*, c.name AS category_name
   FROM product_subcategories ps
   LEFT JOIN categories c ON ps.category_id = c.id
   ORDER BY ps.subcategory_name"
);
$sub_subcategory_result = $conn->query(
  "SELECT pss.*, ps.subcategory_name, ps.category_id
   FROM product_sub_subcategories pss
   LEFT JOIN product_subcategories ps ON pss.subcategory_id = ps.id
   ORDER BY pss.sub_subcategory_name"
);

$subcategories_with_category = [];
while ($sub = $subcategory_result->fetch_assoc()) {
  $subcategories_with_category[] = $sub;
}

$sub_subcategories_with_parent = [];
while ($subsub = $sub_subcategory_result->fetch_assoc()) {
  $sub_subcategories_with_parent[] = $subsub;
}

// ── Active filters ───────────────────────────────────────────────────────────
$showArchived = isset($_GET['show']) && $_GET['show'] === 'archived';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$subcategory_filter = isset($_GET['subcategory']) ? intval($_GET['subcategory']) : 0;
$sub_subcategory_filter = isset($_GET['sub_subcategory']) ? intval($_GET['sub_subcategory']) : 0;

$archivedCondition = $showArchived ? "p.is_archived = 1" : "p.is_archived = 0";

// ── Build products query ─────────────────────────────────────────────────────
$productsQuery = "
  SELECT
    p.id, p.product_name, p.codename, p.quantity, p.main_image,
    p.created_at, p.updated_at, p.is_archived, p.added_by,
    COALESCE(SUM(pc.stock), 0)           AS total_color_stock,
    COALESCE(SUM(pvc.stock_quantity), 0) AS total_size_stock,
    COUNT(DISTINCT pc.id)                AS color_count,
    COUNT(DISTINCT pv.id)                AS size_count,
    cat.id                               AS category_id,
    cat.name                             AS category_name
  FROM products p
  LEFT JOIN product_colors pc          ON p.id = pc.product_id
  LEFT JOIN product_variants pv        ON p.id = pv.product_id
  LEFT JOIN product_variant_colors pvc ON pv.id = pvc.variant_id
  LEFT JOIN categories cat             ON p.codename = cat.name
  WHERE $archivedCondition
";

if (!$is_superadmin) {
  $safe_user = $conn->real_escape_string($current_user);
  $productsQuery .= " AND p.added_by = '$safe_user'";
}
if ($category_filter > 0) {
  $productsQuery .= " AND cat.id = " . $category_filter;
}
if ($subcategory_filter > 0) {
  $productsQuery .= " AND EXISTS (
    SELECT 1 FROM product_variants pvf
    WHERE pvf.product_id = p.id AND pvf.subcategory_id = " . $subcategory_filter . "
  )";
}
if ($sub_subcategory_filter > 0) {
  $productsQuery .= " AND EXISTS (
    SELECT 1 FROM product_variants pvf2
    WHERE pvf2.product_id = p.id
      AND (pvf2.sub_subcategory_id = " . $sub_subcategory_filter . "
           OR FIND_IN_SET(" . $sub_subcategory_filter . ", REPLACE(REPLACE(REPLACE(REPLACE(pvf2.sub_subcategory_ids, '[', ''), ']', ''), '\"', ''), ' ', '')))
  )";
}

$productsQuery .= "
  GROUP BY p.id, p.product_name, p.codename, p.quantity,
           p.main_image, p.created_at, p.updated_at, p.is_archived, p.added_by,
           cat.id, cat.name
  ORDER BY p.product_name
";

$products = $conn->query($productsQuery);
if (!$products)
  die("Query error: " . $conn->error);

// ── Pre-fetch subcategory & sub-subcategory names per product ─────────────────
$subcatMap = [];
$subSubcatMap = [];

$subcatRows = $conn->query("
  SELECT pv.product_id, ps.subcategory_name
  FROM product_variants pv
  JOIN product_subcategories ps ON ps.id = pv.subcategory_id
  WHERE pv.subcategory_id IS NOT NULL
  GROUP BY pv.product_id, ps.subcategory_name
  ORDER BY ps.subcategory_name
");
if ($subcatRows) {
  while ($r = $subcatRows->fetch_assoc())
    $subcatMap[$r['product_id']][] = $r['subcategory_name'];
}

$subSubRows = $conn->query("
  SELECT pv.product_id, pss.sub_subcategory_name
  FROM product_variants pv
  JOIN product_sub_subcategories pss ON pss.id = pv.sub_subcategory_id
  WHERE pv.sub_subcategory_id IS NOT NULL
  GROUP BY pv.product_id, pss.sub_subcategory_name
  ORDER BY pss.sub_subcategory_name
");
if ($subSubRows) {
  while ($r = $subSubRows->fetch_assoc())
    $subSubcatMap[$r['product_id']][$r['sub_subcategory_name']] = true;
}

$subSubJsonRows = $conn->query("
  SELECT pv.product_id, pss.sub_subcategory_name
  FROM product_variants pv
  JOIN product_sub_subcategories pss
    ON FIND_IN_SET(pss.id, REPLACE(REPLACE(REPLACE(REPLACE(pv.sub_subcategory_ids, '[', ''), ']', ''), '\"', ''), ' ', ''))
  WHERE pv.sub_subcategory_ids IS NOT NULL
    AND pv.sub_subcategory_ids != ''
    AND pv.sub_subcategory_ids != '[]'
  GROUP BY pv.product_id, pss.sub_subcategory_name
  ORDER BY pss.sub_subcategory_name
");
if ($subSubJsonRows) {
  while ($r = $subSubJsonRows->fetch_assoc())
    $subSubcatMap[$r['product_id']][$r['sub_subcategory_name']] = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Products</title>
  <style>
    .table-row-hover {
      transition: all 0.2s ease;
    }

    .table-row-hover:hover {
      background-color: #faf8f6;
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

    .archived-row {
      opacity: 0.6;
      background-color: #fef3f2;
    }

    select[multiple] {
      padding: 4px;
      min-height: 32px;
    }

    select[multiple] option:checked {
      background: linear-gradient(#f97316, #f97316);
      color: white;
    }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-roboto">
  <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

  <!-- ══════════════════════════════════════════════════════════════════════
       BULK ARCHIVE FORM — completely separate, outside the table
       JS will inject hidden inputs before submit
  ══════════════════════════════════════════════════════════════════════ -->
  <form method="POST" id="bulkForm">
    <input type="hidden" name="action" value="<?= $showArchived ? 'bulk_restore' : 'bulk_archive' ?>">
    <!-- bulk_ids[] inputs are injected here by submitBulk() -->
  </form>

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
          <a href="<?= BASE_URL ?>/updateallproduct"
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition shadow-sm">
            <i class="fas fa-edit"></i> Update All Product
          </a>
          <a href="<?= BASE_URL ?>/addnewproduct"
            class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition shadow-sm">
            Back
          </a>
        </div>
      </div>
    </div>

    <!-- Archive Status Tabs -->
    <div class="mb-6 flex gap-2 border-b border-gray-200">
      <a href="?show=active"
        class="px-4 py-3 font-medium text-sm <?= !$showArchived ? 'border-b-2 border-orange-600 text-orange-600' : 'text-gray-600 hover:text-gray-900' ?> transition">
        <i class="fas fa-eye mr-2"></i>Active Products
      </a>
      <a href="?show=archived"
        class="px-4 py-3 font-medium text-sm <?= $showArchived ? 'border-b-2 border-orange-600 text-orange-600' : 'text-gray-600 hover:text-gray-900' ?> transition">
        <i class="fas fa-archive mr-2"></i>Archived Products
      </a>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-200">
      <form method="GET" id="filterForm">
        <input type="hidden" name="show" value="<?= $showArchived ? 'archived' : 'active' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">

          <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-search text-orange-500 mr-2"></i>Search Product
            </label>
            <input type="text" id="searchInput" placeholder="Search by name or code..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-filter text-orange-500 mr-2"></i>Stock Status
            </label>
            <select id="quantityFilter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
              <option value="all">All Products</option>
              <option value="in-stock">In Stock</option>
              <option value="out-of-stock">Out of Stock</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-tag text-orange-500 mr-2"></i>Category
            </label>
            <select name="category" id="filterCategory" onchange="onCategoryChange(this)"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
              <option value="">All Categories</option>
              <?php $category_result->data_seek(0);
              while ($cat = $category_result->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-sitemap text-orange-500 mr-2"></i>Subcategory
            </label>
            <select name="subcategory" id="filterSubcategory" onchange="onSubcategoryChange(this)"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
              <option value="">All Subcategories</option>
              <?php foreach ($subcategories_with_category as $sub): ?>
                <option value="<?= $sub['id'] ?>" data-category-id="<?= $sub['category_id'] ?>"
                  <?= $subcategory_filter == $sub['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sub['subcategory_name']) ?>
                  <?php if (!empty($sub['category_name'])): ?>(<?= htmlspecialchars($sub['category_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-layer-group text-orange-500 mr-2"></i>Sub-Subcategory
            </label>
            <select name="sub_subcategory" id="filterSubSubcategory"
              onchange="document.getElementById('filterForm').submit()"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
              <option value="">All Sub-Subcategories</option>
              <?php foreach ($sub_subcategories_with_parent as $subsub): ?>
                <option value="<?= $subsub['id'] ?>" data-subcategory-id="<?= $subsub['subcategory_id'] ?>"
                  <?= $sub_subcategory_filter == $subsub['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($subsub['sub_subcategory_name']) ?>
                  <?php if (!empty($subsub['subcategory_name'])): ?>(<?= htmlspecialchars($subsub['subcategory_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-barcode text-orange-500 mr-2"></i>Product Code
            </label>
            <select id="codeFilter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
              <option value="all">All Codes</option>
            </select>
          </div>

          <div class="flex items-end">
            <a href="?show=<?= $showArchived ? 'archived' : 'active' ?>"
              class="w-full text-center px-4 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 text-sm transition">
              <i class="fas fa-times mr-1"></i>Reset Filters
            </a>
          </div>

        </div>
      </form>
    </div>

    <!-- Products Table -->
    <?php if ($products && $products->num_rows > 0): ?>

      <!-- Bulk Action Bar — NOT inside any form -->
      <div id="bulk-bar"
        class="hidden mb-4 flex items-center gap-3 bg-yellow-50 border border-yellow-300 px-4 py-3 rounded-lg">
        <span class="text-sm font-semibold text-yellow-800">
          <span id="selected-count">0</span> selected
        </span>
        <button type="button" onclick="submitBulk()"
          class="<?= $showArchived ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-600 hover:bg-yellow-700' ?> text-white px-4 py-1.5 rounded text-sm font-medium transition flex items-center gap-1">
          <i class="fas fa-<?= $showArchived ? 'redo' : 'archive' ?>"></i>
          <?= $showArchived ? 'Restore Selected' : 'Archive Selected' ?>
        </button>
        <button type="button" onclick="clearSelection()" class="text-sm text-gray-500 hover:text-gray-700 underline">
          Clear
        </button>
      </div>

      <!-- Table — standalone, no wrapping form -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="sticky-header bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
              <tr>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">
                  <input type="checkbox" id="select-all" onclick="toggleAll(this)" class="w-4 h-4 cursor-pointer"
                    title="Select All">
                </th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Image</th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Product Name</th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Product Code</th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Category</th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Subcategories</th>
                <th class="px-4 py-4 text-left font-semibold text-gray-700">Sub-Subcategories</th>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">Stock</th>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">Status</th>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">Created</th>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">Updated</th>
                <th class="px-4 py-4 text-center font-semibold text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody id="productTableBody">
              <?php while ($product = $products->fetch_assoc()): ?>
                <?php
                $createdAt = strtotime($product['created_at']);
                $updatedAt = strtotime($product['updated_at']);
                $totalStock = $product['total_color_stock'] + $product['total_size_stock'];
                $isInStock = ($totalStock > 0);
                $subcatNames = isset($subcatMap[$product['id']]) ? array_unique($subcatMap[$product['id']]) : [];
                $subSubcatNames = isset($subSubcatMap[$product['id']]) ? array_keys($subSubcatMap[$product['id']]) : [];
                ?>
                <tr
                  class="table-row-hover border-b border-gray-100 product-row <?= $product['is_archived'] ? 'archived-row' : '' ?>"
                  data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>"
                  data-code="<?= htmlspecialchars(strtolower($product['codename'])) ?>" data-stock="<?= $totalStock ?>">

                  <!-- Checkbox — plain input, no wrapping form needed -->
                  <td class="px-4 py-4 text-center">
                    
                      <input type="checkbox" value="<?= $product['id'] ?>" class="bulk-checkbox w-4 h-4 cursor-pointer"
                        onchange="updateBulkBar()">
                    
                  </td>

                  <!-- Image -->
                  <td class="px-4 py-4">
                    <div class="flex items-center justify-center relative">
                      <?php if (!empty($product['main_image'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($product['main_image']) ?>"
                          alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image-cell" />
                      <?php else: ?>
                        <div class="w-14 h-14 bg-gray-200 rounded flex items-center justify-center">
                          <i class="fas fa-image text-gray-400 text-lg"></i>
                        </div>
                      <?php endif; ?>
                      <?php if ($product['is_archived']): ?>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded">
                          <i class="fas fa-lock text-white text-sm"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Product Name -->
                  <td class="px-4 py-4">
                    <span class="font-medium text-gray-900 product-name"
                      title="<?= htmlspecialchars($product['product_name']) ?>">
                      <?= htmlspecialchars(substr($product['product_name'], 0, 25)) . (strlen($product['product_name']) > 25 ? '...' : '') ?>
                    </span>
                  </td>

                  <!-- Product Code -->
                  <td class="px-4 py-4">
                    <span class="text-black font-mono text-xs bg-gray-100 px-2 py-1 rounded product-code uppercase"
                      title="<?= htmlspecialchars($product['codename']) ?>">
                      <?= htmlspecialchars($product['codename']) ?>
                    </span>
                  </td>

                  <!-- Category -->
                  <td class="px-4 py-4">
                    <?php if (!empty($product['category_name'])): ?>
                      <span class="inline-block bg-orange-100 text-orange-700 px-2 py-1 rounded text-xs font-medium">
                        <?= htmlspecialchars($product['category_name']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Subcategories -->
                  <td class="px-4 py-4">
                    <?php if (!empty($subcatNames)): ?>
                      <div class="flex flex-wrap gap-1">
                        <?php foreach (array_slice($subcatNames, 0, 2) as $subcatName): ?>
                          <span class="inline-block bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-xs">
                            <?= htmlspecialchars(strlen($subcatName) > 12 ? substr($subcatName, 0, 12) . '…' : $subcatName) ?>
                          </span>
                        <?php endforeach; ?>
                        <?php if (count($subcatNames) > 2): ?>
                          <span class="text-blue-600 font-semibold text-xs">+<?= count($subcatNames) - 2 ?> more</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Sub-Subcategories -->
                  <td class="px-4 py-4">
                    <?php if (!empty($subSubcatNames)): ?>
                      <div class="flex flex-wrap gap-1">
                        <?php foreach (array_slice($subSubcatNames, 0, 2) as $subSubName): ?>
                          <span class="inline-block bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-xs">
                            <?= htmlspecialchars(strlen($subSubName) > 12 ? substr($subSubName, 0, 12) . '…' : $subSubName) ?>
                          </span>
                        <?php endforeach; ?>
                        <?php if (count($subSubcatNames) > 2): ?>
                          <span class="text-purple-600 font-semibold text-xs">+<?= count($subSubcatNames) - 2 ?> more</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Stock -->
                  <td class="px-4 py-4 text-center">
                    <span
                      class="inline-flex items-center justify-center gap-1 bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-semibold size-stock">
                      <?= $product['total_size_stock'] ?>
                    </span>
                  </td>

                  <!-- Status Badge -->
                  <td class="px-4 py-4 text-center">
                    <span
                      class="<?= $isInStock ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs font-semibold px-3 py-1 rounded-full flex items-center justify-center gap-1 stock-badge mx-auto w-fit">
                      <i class="fas fa-<?= $isInStock ? 'check-circle' : 'times-circle' ?>"></i>
                      <?= $isInStock ? 'In Stock' : 'Out of Stock' ?>
                    </span>
                  </td>

                  <!-- Created Date -->
                  <td class="px-4 py-4 text-center text-gray-600 text-xs">
                    <i class="fas fa-calendar-alt text-orange-500 mr-1"></i>
                    <?= date('M d, Y', $createdAt) ?>
                  </td>

                  <!-- Updated Date -->
                  <td class="px-4 py-4 text-center text-gray-600 text-xs">
                    <i class="fas fa-sync text-blue-500 mr-1"></i>
                    <?= date('M d, Y', $updatedAt) ?>
                  </td>

                  <!-- Actions — each button has its own mini inline form, no nesting issues -->
                  <td class="px-4 py-4">
                    <div class="flex gap-2 justify-center flex-wrap">

                      <?php if (!$product['is_archived']): ?>
                        <a href="<?= BASE_URL ?>/updateproducts?id=<?= $product['id'] ?>"
                          class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded text-xs font-medium transition flex items-center gap-1 whitespace-nowrap"
                          title="Edit Product">
                          <i class="fas fa-edit"></i> Edit
                        </a>
                      <?php endif; ?>

                      <!-- Archive / Restore button -->
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="action" value="<?= $product['is_archived'] ? 'restore' : 'archive' ?>">
                        <input type="hidden" name="current_show" value="<?= $showArchived ? 'archived' : 'active' ?>">
                        <!-- ← dagdag ito -->
                        <button type="submit"
                          class="<?= $product['is_archived'] ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-600 hover:bg-yellow-700' ?> text-white px-3 py-1.5 rounded text-xs font-medium transition flex items-center gap-1 whitespace-nowrap">
                          <i class="fas fa-<?= $product['is_archived'] ? 'redo' : 'archive' ?>"></i>
                          <?= $product['is_archived'] ? 'Restore' : 'Archive' ?>
                        </button>
                      </form>

                      <?php if ($is_superadmin): ?>

                        <!-- Permanent Delete button -->
                        <form method="POST" style="display:inline"
                          onsubmit="return confirm('⚠️ This will permanently delete the product. Archive it instead if you want to restore later.\n\nContinue with permanent deletion?');">
                          <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                          <input type="hidden" name="action" value="permanent_delete">
                          <input type="hidden" name="current_show" value="<?= $showArchived ? 'archived' : 'active' ?>">
                          <!-- ← dagdag ito -->
                          <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-medium transition flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-trash"></i> Delete
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
        <i class="fas fa-inbox text-gray-300 text-5xl mb-4 block"></i>
        <p class="text-gray-600 text-lg font-medium mb-2">
          <?= $showArchived ? 'No Archived Products' : 'No Products Found' ?>
        </p>
        <p class="text-gray-500 mb-6">
          <?= $showArchived ? 'Your archived products will appear here' : 'Try adjusting your filters or add a new product' ?>
        </p>
        <?php if (!$showArchived): ?>
          <a href="main-adminshop-page-1.php"
            class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg font-medium transition">
            <i class="fas fa-plus mr-2"></i>Add Your First Product
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <script>
    // ── Cascading filter data ────────────────────────────────────────────────
    const subcategoriesData = <?= json_encode($subcategories_with_category) ?>;
    const subSubcategoriesData = <?= json_encode($sub_subcategories_with_parent) ?>;

    function onCategoryChange(sel) {
      const catId = sel.value;
      const subSel = document.getElementById('filterSubcategory');
      subSel.innerHTML = '<option value="">All Subcategories</option>';
      subcategoriesData
        .filter(s => !catId || s.category_id == catId)
        .forEach(s => {
          const o = document.createElement('option');
          o.value = s.id;
          o.textContent = s.subcategory_name + (s.category_name ? ' (' + s.category_name + ')' : '');
          subSel.appendChild(o);
        });
      document.getElementById('filterSubSubcategory').innerHTML = '<option value="">All Sub-Subcategories</option>';
      document.getElementById('filterForm').submit();
    }

    function onSubcategoryChange(sel) {
      const subcatId = sel.value;
      const subSubSel = document.getElementById('filterSubSubcategory');
      subSubSel.innerHTML = '<option value="">All Sub-Subcategories</option>';
      subSubcategoriesData
        .filter(ss => !subcatId || ss.subcategory_id == subcatId)
        .forEach(ss => {
          const o = document.createElement('option');
          o.value = ss.id;
          o.textContent = ss.sub_subcategory_name + (ss.subcategory_name ? ' (' + ss.subcategory_name + ')' : '');
          subSubSel.appendChild(o);
        });
      document.getElementById('filterForm').submit();
    }

    // ── Client-side search / stock / code filters ────────────────────────────
    const searchInput = document.getElementById("searchInput");
    const quantityFilter = document.getElementById("quantityFilter");
    const codeFilter = document.getElementById("codeFilter");
    const rows = document.querySelectorAll(".product-row");

    (function populateCodeFilter() {
      const codes = new Set();
      rows.forEach(row => {
        const code = row.dataset.code.trim().toUpperCase();
        if (code) codes.add(code);
      });
      [...codes].sort().forEach(code => {
        const opt = document.createElement("option");
        opt.value = code.toLowerCase();
        opt.textContent = code;
        codeFilter.appendChild(opt);
      });
    })();

    function filterRows() {
      const searchTerm = searchInput.value.toLowerCase();
      const quantityValue = quantityFilter.value;
      const codeValue = codeFilter.value;
      let visible = 0;

      rows.forEach(row => {
        const name = row.dataset.name;
        const code = row.dataset.code;
        const stock = parseInt(row.dataset.stock);

        const matchSearch = name.includes(searchTerm) || code.includes(searchTerm);
        const matchStock = quantityValue === 'all' ? true : quantityValue === 'in-stock' ? stock > 0 : stock === 0;
        const matchCode = codeValue === 'all' || code.includes(codeValue);

        if (matchSearch && matchStock && matchCode) {
          row.style.display = '';
          visible++;
        } else {
          row.style.display = 'none';
        }
      });

      const tbody = document.getElementById("productTableBody");
      const noResult = tbody.querySelector(".no-results");
      if (visible === 0 && !noResult) {
        const tr = document.createElement("tr");
        tr.className = "no-results";
        tr.innerHTML = '<td colspan="12" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-search mr-2"></i>No products found</td>';
        tbody.appendChild(tr);
      } else if (visible > 0 && noResult) {
        noResult.remove();
      }
    }

    // ── Bulk select helpers ──────────────────────────────────────────────────
    function toggleAll(cb) {
      document.querySelectorAll('.bulk-checkbox').forEach(c => c.checked = cb.checked);
      updateBulkBar();
    }

    function updateBulkBar() {
      const checked = document.querySelectorAll('.bulk-checkbox:checked');
      const bar = document.getElementById('bulk-bar');
      document.getElementById('selected-count').textContent = checked.length;
      bar.classList.toggle('hidden', checked.length === 0);
    }

    function clearSelection() {
      document.querySelectorAll('.bulk-checkbox').forEach(c => c.checked = false);
      const selectAll = document.getElementById('select-all');
      if (selectAll) selectAll.checked = false;
      updateBulkBar();
    }

    // ── BULK SUBMIT ──────────────────────────────────────────────────────────
    // Collects checked IDs, injects them into the standalone #bulkForm, then submits.
    // This avoids nested-form issues completely.
    function submitBulk() {
      const checked = document.querySelectorAll('.bulk-checkbox:checked');
      if (checked.length === 0) return;
      const action = <?= json_encode($showArchived ? 'Restore' : 'Archive') ?>;
      if (!confirm(action + ' ' + checked.length + ' selected product(s)?')) return;

      const form = document.getElementById('bulkForm');

      // Remove any previously injected inputs
      form.querySelectorAll('input[name="bulk_ids[]"]').forEach(el => el.remove());

      // Inject one hidden input per selected product
      checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_ids[]';
        input.value = cb.value;
        form.appendChild(input);
      });

      form.submit();
    }

    searchInput.addEventListener("input", filterRows);
    quantityFilter.addEventListener("change", filterRows);
    codeFilter.addEventListener("change", filterRows);
  </script>

</body>

</html>