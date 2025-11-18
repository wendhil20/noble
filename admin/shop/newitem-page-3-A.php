<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

function autoAssignAllCategories($conn) {
    $result = [
        'success' => false,
        'updated_count' => 0,
        'errors' => [],
        'details' => []
    ];
    
    try {
        $update_query = "
            UPDATE product_variants pv
            JOIN products p ON pv.product_id = p.id
            JOIN categories c ON p.codename = c.name
            SET pv.category_id = c.id, pv.category_name = c.name
            WHERE p.codename IS NOT NULL AND p.codename != ''
        ";
        
        if ($conn->query($update_query)) {
            $result['updated_count'] = $conn->affected_rows;
            $result['success'] = true;
        } else {
            $result['errors'][] = "Failed to update categories: " . $conn->error;
        }
        
        $orphan_query = "
            SELECT DISTINCT p.product_name, p.codename
            FROM products p
            JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN categories c ON p.codename = c.name
            WHERE p.codename IS NOT NULL 
            AND p.codename != ''
            AND c.id IS NULL
        ";
        
        $orphan_result = $conn->query($orphan_query);
        if ($orphan_result && $orphan_result->num_rows > 0) {
            while ($orphan = $orphan_result->fetch_assoc()) {
                $result['errors'][] = "No category found for codename: '{$orphan['codename']}' (Product: {$orphan['product_name']})";
            }
        }
        
    } catch (Exception $e) {
        $result['errors'][] = "Error: " . $e->getMessage();
    }
    
    return $result;
}

$sync_message = '';
if (isset($_SESSION['sync_message'])) {
    $sync_message = $_SESSION['sync_message'];
    unset($_SESSION['sync_message']);
}

$category_result = $conn->query("SELECT * FROM categories ORDER BY name");
$subcategory_result = $conn->query("SELECT ps.*, c.name as category_name FROM product_subcategories ps LEFT JOIN categories c ON ps.category_id = c.id ORDER BY ps.subcategory_name");
$sub_subcategory_result = $conn->query("SELECT pss.*, ps.subcategory_name, ps.category_id FROM product_sub_subcategories pss LEFT JOIN product_subcategories ps ON pss.subcategory_id = ps.id ORDER BY pss.sub_subcategory_name");
$delivery_size_result = $conn->query("SELECT * FROM delivery_sizes ORDER BY percentage ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['bulk_ids'])) {
        $ids = array_map('intval', $_POST['bulk_ids']);

        if (isset($_POST['bulk_status'])) {
            $status = $_POST['bulk_status'] === 'new' ? 'new' : 'old';
            $stmt = $conn->prepare("UPDATE product_variants SET status = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("s", $status);
            $stmt->execute();
        }

        if (isset($_POST['bulk_origin'])) {
            $origin = $_POST['bulk_origin'] === 'international' ? 'international' : 'local';
            $stmt = $conn->prepare("UPDATE product_variants SET origin = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("s", $origin);
            $stmt->execute();
        }

        if (!empty($_POST['bulk_category'])) {
            $catId = intval($_POST['bulk_category']);
            
            $catQuery = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $catQuery->bind_param("i", $catId);
            $catQuery->execute();
            $catResult = $catQuery->get_result();
            
            if ($catResult->num_rows > 0) {
                $catData = $catResult->fetch_assoc();
                $catName = $catData['name'];
                
                $stmt = $conn->prepare("UPDATE product_variants SET category_id = ?, category_name = ? WHERE id IN (" . implode(',', $ids) . ")");
                $stmt->bind_param("is", $catId, $catName);
                $stmt->execute();
                
                $productUpdateQuery = "
                    UPDATE products p
                    JOIN product_variants pv ON p.id = pv.product_id
                    SET p.codename = ?
                    WHERE pv.id IN (" . implode(',', $ids) . ")
                ";
                $stmt2 = $conn->prepare($productUpdateQuery);
                $stmt2->bind_param("s", $catName);
                $stmt2->execute();
            }
        }

        // SUBCATEGORY - MULTIPLE SUPPORT
        if (!empty($_POST['bulk_subcategory'])) {
            $subcatIds = $_POST['bulk_subcategory'];
            $appendMode = isset($_POST['append_subcategory']) && $_POST['append_subcategory'] === '1';
            
            if (is_array($subcatIds)) {
                $cleanIds = array_map('intval', $subcatIds);
                
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $subcatQuery = $conn->prepare("SELECT id, subcategory_name FROM product_subcategories WHERE id IN ($placeholders)");
                $subcatQuery->bind_param(str_repeat('i', count($cleanIds)), ...$cleanIds);
                $subcatQuery->execute();
                $subcatResult = $subcatQuery->get_result();
                
                $subcatNames = [];
                while ($row = $subcatResult->fetch_assoc()) {
                    $subcatNames[] = $row['subcategory_name'];
                }
                
                if ($appendMode) {
                    foreach ($ids as $variantId) {
                        $getQuery = $conn->prepare("SELECT subcategory_name FROM product_variants WHERE id = ?");
                        $getQuery->bind_param("i", $variantId);
                        $getQuery->execute();
                        $result = $getQuery->get_result();
                        $row = $result->fetch_assoc();
                        
                        $existingNames = [];
                        if (!empty($row['subcategory_name'])) {
                            $decoded = json_decode($row['subcategory_name'], true);
                            if (is_array($decoded)) {
                                $existingNames = $decoded;
                            } else {
                                $existingNames = [$row['subcategory_name']];
                            }
                        }
                        
                        $mergedNames = array_unique(array_merge($existingNames, $subcatNames));
                        $jsonNames = json_encode(array_values($mergedNames));
                        $primaryId = $cleanIds[0];
                        
                        $updateStmt = $conn->prepare("UPDATE product_variants SET subcategory_id = ?, subcategory_name = ? WHERE id = ?");
                        $updateStmt->bind_param("isi", $primaryId, $jsonNames, $variantId);
                        $updateStmt->execute();
                    }
                } else {
                    $jsonNames = json_encode($subcatNames);
                    $primaryId = $cleanIds[0];
                    
                    $stmt = $conn->prepare("UPDATE product_variants SET subcategory_id = ?, subcategory_name = ? WHERE id IN (" . implode(',', $ids) . ")");
                    $stmt->bind_param("is", $primaryId, $jsonNames);
                    $stmt->execute();
                }
            } else {
                $subcatId = intval($subcatIds);
                
                $subcatQuery = $conn->prepare("SELECT subcategory_name FROM product_subcategories WHERE id = ?");
                $subcatQuery->bind_param("i", $subcatId);
                $subcatQuery->execute();
                $subcatResult = $subcatQuery->get_result();
                
                if ($subcatResult->num_rows > 0) {
                    $subcatData = $subcatResult->fetch_assoc();
                    $subcatName = $subcatData['subcategory_name'];
                    $jsonName = json_encode([$subcatName]);
                    
                    $stmt = $conn->prepare("UPDATE product_variants SET subcategory_id = ?, subcategory_name = ? WHERE id IN (" . implode(',', $ids) . ")");
                    $stmt->bind_param("is", $subcatId, $jsonName);
                    $stmt->execute();
                }
            }
        }

        // SUB-SUBCATEGORY
        if (!empty($_POST['bulk_sub_subcategory'])) {
            $subSubcatIds = $_POST['bulk_sub_subcategory'];
            $appendMode = isset($_POST['append_sub_subcategory']) && $_POST['append_sub_subcategory'] === '1';
            
            if (is_array($subSubcatIds)) {
                $cleanIds = array_map('intval', $subSubcatIds);
                
                if ($appendMode) {
                    foreach ($ids as $variantId) {
                        $getQuery = $conn->prepare("SELECT sub_subcategory_ids FROM product_variants WHERE id = ?");
                        $getQuery->bind_param("i", $variantId);
                        $getQuery->execute();
                        $result = $getQuery->get_result();
                        $row = $result->fetch_assoc();
                        
                        $existingIds = [];
                        if (!empty($row['sub_subcategory_ids'])) {
                            $existingIds = json_decode($row['sub_subcategory_ids'], true) ?: [];
                        }
                        
                        $mergedIds = array_unique(array_merge($existingIds, $cleanIds));
                        $jsonIds = json_encode(array_values($mergedIds));
                        $primaryId = $mergedIds[0];
                        
                        $updateStmt = $conn->prepare("UPDATE product_variants SET sub_subcategory_id = ?, sub_subcategory_ids = ? WHERE id = ?");
                        $updateStmt->bind_param("isi", $primaryId, $jsonIds, $variantId);
                        $updateStmt->execute();
                    }
                } else {
                    $jsonIds = json_encode($cleanIds);
                    $primaryId = $cleanIds[0];
                    
                    $stmt = $conn->prepare("UPDATE product_variants SET sub_subcategory_id = ?, sub_subcategory_ids = ? WHERE id IN (" . implode(',', $ids) . ")");
                    $stmt->bind_param("is", $primaryId, $jsonIds);
                    $stmt->execute();
                }
            } else {
                $subSubcatId = intval($subSubcatIds);
                $jsonSingle = json_encode([$subSubcatId]);
                
                $stmt = $conn->prepare("UPDATE product_variants SET sub_subcategory_id = ?, sub_subcategory_ids = ? WHERE id IN (" . implode(',', $ids) . ")");
                $stmt->bind_param("is", $subSubcatId, $jsonSingle);
                $stmt->execute();
            }
        }

        if (!empty($_POST['bulk_delivery_size'])) {
            $deliverySizeId = intval($_POST['bulk_delivery_size']);
            $stmt = $conn->prepare("UPDATE product_variants SET delivery_size_id = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("i", $deliverySizeId);
            $stmt->execute();
        }

        if (!empty($_POST['bulk_lead_count']) && !empty($_POST['bulk_lead_interval'])) {
            $leadCount = intval($_POST['bulk_lead_count']);
            $leadInterval = $_POST['bulk_lead_interval'];
            $leadGap = !empty($_POST['bulk_lead_gap']) ? intval($_POST['bulk_lead_gap']) : null;

            $stmt = $conn->prepare("UPDATE product_variants SET lead_count = ?, lead_interval = ?, lead_gap = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("isi", $leadCount, $leadInterval, $leadGap);
            $stmt->execute();
        }

        if (isset($_POST['auto_sync_categories'])) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $update_query = "
                UPDATE product_variants pv
                JOIN products p ON pv.product_id = p.id
                JOIN categories c ON p.codename = c.name
                SET pv.category_id = c.id, pv.category_name = c.name
                WHERE pv.id IN ($placeholders)
            ";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
        }
        
        $_SESSION['sync_message'] = "Bulk update completed successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

if (isset($_POST['auto_sync_all'])) {
    $auto_sync_result = autoAssignAllCategories($conn);
    
    if ($auto_sync_result['success']) {
        $message = "Successfully updated {$auto_sync_result['updated_count']} product variant categories!";
        if (!empty($auto_sync_result['errors'])) {
            $message .= " Note: " . count($auto_sync_result['errors']) . " warnings found.";
        }
    } else {
        $message = "Error occurred during synchronization: " . implode(', ', $auto_sync_result['errors']);
    }
    
    $_SESSION['sync_message'] = $message;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$status_filter = $_GET['status'] ?? '';
$origin_filter = $_GET['origin'] ?? '';
$category_filter = $_GET['category'] ?? '';
$subcategory_filter = $_GET['subcategory'] ?? '';
$sub_subcategory_filter = $_GET['sub_subcategory'] ?? '';
$delivery_size_filter = $_GET['delivery_size'] ?? '';
$leadtime_filter = $_GET['leadtime'] ?? '';

$query = "
    SELECT 
        pv.*,
        p.codename,
        p.product_name,
        p.main_image,
        pv.lead_count,
        pv.lead_interval,
        pv.lead_gap,
        pv.size,
        pv.color,
        c1.name as current_category_name,
        c2.name as expected_category_name,
        c2.id as expected_category_id,
        (SELECT GROUP_CONCAT(pss2.sub_subcategory_name SEPARATOR ', ')
         FROM product_sub_subcategories pss2
         WHERE FIND_IN_SET(pss2.id, REPLACE(REPLACE(REPLACE(pv.sub_subcategory_ids, '[', ''), ']', ''), '\"', ''))) as all_sub_subcategory_names,
        ds.size_name as delivery_size_name,
        ds.percentage as delivery_size_percentage,
        CASE 
            WHEN pv.category_id = c2.id THEN 'matched'
            WHEN c2.id IS NULL THEN 'no_match'
            ELSE 'mismatched'
        END as category_sync_status
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN categories c1 ON pv.category_id = c1.id
    LEFT JOIN categories c2 ON p.codename = c2.name
    LEFT JOIN delivery_sizes ds ON pv.delivery_size_id = ds.id
    WHERE 1=1
";

if ($status_filter === 'new' || $status_filter === 'old') {
    $query .= " AND pv.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($origin_filter === 'local' || $origin_filter === 'international') {
    $query .= " AND pv.origin = '" . $conn->real_escape_string($origin_filter) . "'";
}
if (is_numeric($category_filter)) {
    $query .= " AND pv.category_id = " . intval($category_filter);
}
if (is_numeric($subcategory_filter)) {
    $query .= " AND pv.subcategory_id = " . intval($subcategory_filter);
}
if (is_numeric($sub_subcategory_filter)) {
    $query .= " AND (pv.sub_subcategory_id = " . intval($sub_subcategory_filter) . 
              " OR pv.sub_subcategory_ids LIKE '%\"" . intval($sub_subcategory_filter) . "\"%')";
}
if (is_numeric($delivery_size_filter)) {
    $query .= " AND pv.delivery_size_id = " . intval($delivery_size_filter);
}
if ($leadtime_filter === 'with_leadtime') {
    $query .= " AND pv.lead_count IS NOT NULL AND pv.lead_interval IS NOT NULL";
} elseif ($leadtime_filter === 'without_leadtime') {
    $query .= " AND (pv.lead_count IS NULL OR pv.lead_interval IS NULL)";
}

$sync_filter = $_GET['sync_status'] ?? '';
if ($sync_filter === 'mismatched') {
    $query .= " AND pv.category_id != c2.id AND c2.id IS NOT NULL";
} elseif ($sync_filter === 'matched') {
    $query .= " AND pv.category_id = c2.id";
} elseif ($sync_filter === 'no_match') {
    $query .= " AND c2.id IS NULL";
}

$query .= " ORDER BY pv.percent ASC";
$result = $conn->query($query);

$count_query = "
    SELECT 
        SUM(CASE WHEN pv.category_id = c2.id THEN 1 ELSE 0 END) as matched_count,
        SUM(CASE WHEN pv.category_id != c2.id AND c2.id IS NOT NULL THEN 1 ELSE 0 END) as mismatched_count,
        SUM(CASE WHEN c2.id IS NULL THEN 1 ELSE 0 END) as no_match_count
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN categories c2 ON p.codename = c2.name
";
$count_result = $conn->query($count_query);
$counts = $count_result->fetch_assoc();

$leadtime_count_query = "
    SELECT 
        SUM(CASE WHEN pv.lead_count IS NOT NULL AND pv.lead_interval IS NOT NULL THEN 1 ELSE 0 END) as with_leadtime_count,
        SUM(CASE WHEN pv.lead_count IS NULL OR pv.lead_interval IS NULL THEN 1 ELSE 0 END) as without_leadtime_count
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
";
$leadtime_count_result = $conn->query($leadtime_count_query);
$leadtime_counts = $leadtime_count_result->fetch_assoc();

$subcategories_with_category = [];
$subcategory_result->data_seek(0);
while ($sub = $subcategory_result->fetch_assoc()) {
    $subcategories_with_category[] = $sub;
}

$sub_subcategories_with_parent = [];
$sub_subcategory_result->data_seek(0);
while ($subsub = $sub_subcategory_result->fetch_assoc()) {
    $sub_subcategories_with_parent[] = $subsub;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Product Variants - List View</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    const subcategoriesData = <?= json_encode($subcategories_with_category) ?>;
    const subSubcategoriesData = <?= json_encode($sub_subcategories_with_parent) ?>;

    function toggleSelectAll(source) {
      document.querySelectorAll('.variant-checkbox').forEach(cb => {
        cb.checked = source.checked;
        updateRowVisualState(cb.value, source.checked);
      });
      updateSelectAllState();
    }

    function toggleRowSelection(checkboxId) {
      const checkbox = document.getElementById(checkboxId);
      checkbox.checked = !checkbox.checked;
      updateRowVisualState(checkbox.value, checkbox.checked);
      updateSelectAllState();
    }

    function updateRowVisualState(variantId, isChecked) {
      const row = document.getElementById('row-' + variantId);
      if (row) {
        row.classList.toggle('bg-orange-50', isChecked);
        row.classList.toggle('ring-2', isChecked);
        row.classList.toggle('ring-orange-400', isChecked);
      }
    }

    function updateSelectAllState() {
      const selectAllCheckbox = document.querySelector('input[onclick*="toggleSelectAll"]');
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      const checkedBoxes = document.querySelectorAll('.variant-checkbox:checked');
      
      if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
      } else if (checkedBoxes.length === checkboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
      } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
      }
    }

    function filterSubcategories(selectElement, targetId) {
      const categoryId = selectElement.value;
      const subcategorySelect = document.getElementById(targetId);
      
      subcategorySelect.innerHTML = '<option value="">Select Subcategories</option>';
      
      if (categoryId) {
        const filtered = subcategoriesData.filter(sub => sub.category_id == categoryId);
        filtered.forEach(sub => {
          const option = document.createElement('option');
          option.value = sub.id;
          option.textContent = sub.subcategory_name;
          subcategorySelect.appendChild(option);
        });
      }
      
      const subSubcategorySelect = document.getElementById(targetId.replace('subcategory', 'sub_subcategory'));
      if (subSubcategorySelect) {
        subSubcategorySelect.innerHTML = '<option value="">Select Sub-Subcategories</option>';
      }
    }

    function filterSubSubcategories(selectElement, targetId) {
      const subcategoryId = selectElement.value;
      const subSubcategorySelect = document.getElementById(targetId);
      
      subSubcategorySelect.innerHTML = '<option value="">Select Sub-Subcategories</option>';
      
      if (subcategoryId) {
        const filtered = subSubcategoriesData.filter(subsub => subsub.subcategory_id == subcategoryId);
        filtered.forEach(subsub => {
          const option = document.createElement('option');
          option.value = subsub.id;
          option.textContent = subsub.sub_subcategory_name;
          subSubcategorySelect.appendChild(option);
        });
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      checkboxes.forEach(function(checkbox) {
        updateRowVisualState(checkbox.value, checkbox.checked);
        
        checkbox.addEventListener('change', function() {
          updateRowVisualState(this.value, this.checked);
          updateSelectAllState();
        });
      });
      
      updateSelectAllState();
    });
  </script>
</head>
<body class="bg-gray-100 font-sans">
<?php include '../navbar/top.php'; ?>
<div class="max-w-full mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-orange-700 mb-6">Manage Product Variants (List View)</h1>

  <?php if ($sync_message): ?>
  <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
    <strong class="font-bold">Success!</strong>
    <span class="block sm:inline"><?= htmlspecialchars($sync_message) ?></span>
  </div>
  <?php endif; ?>

  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h3 class="text-lg font-semibold text-blue-800 mb-1">Auto-Sync All Categories</h3>
        <p class="text-blue-600 text-sm">Automatically set categories for all product variants based on their product codename.</p>
      </div>
      <form method="POST">
        <button type="submit" name="auto_sync_all" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium whitespace-nowrap" 
                onclick="return confirm('This will update ALL product variants to match their product codename categories. Continue?')">
          Auto-Sync All
        </button>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Category Synchronization Status</h3>
    <div class="flex gap-6 text-sm mb-3 flex-wrap">
      <span class="text-green-600 font-medium">✓ Matched: <?= $counts['matched_count'] ?></span>
      <span class="text-red-600 font-medium">✗ Mismatched: <?= $counts['mismatched_count'] ?></span>
      <span class="text-yellow-600 font-medium">⚠ No Match: <?= $counts['no_match_count'] ?></span>
    </div>
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Lead Time Status</h3>
    <div class="flex gap-6 text-sm flex-wrap">
      <span class="text-blue-600 font-medium">⏰ With Lead Time: <?= $leadtime_counts['with_leadtime_count'] ?></span>
      <span class="text-gray-600 font-medium">⚫ Without Lead Time: <?= $leadtime_counts['without_leadtime_count'] ?></span>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- LEFT: FILTERS CONTAINER -->
    <form method="GET" class="bg-white rounded-lg shadow-md p-4">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Filters</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
          <select name="status" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
            <option value="old" <?= $status_filter === 'old' ? 'selected' : '' ?>>Old</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Origin:</label>
          <select name="origin" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <option value="local" <?= $origin_filter === 'local' ? 'selected' : '' ?>>Local</option>
            <option value="international" <?= $origin_filter === 'international' ? 'selected' : '' ?>>International</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category:</label>
          <select name="category" id="filter_category" onchange="filterSubcategories(this, 'filter_subcategory'); this.form.submit();" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <?php
            $category_result->data_seek(0);
            while ($cat = $category_result->fetch_assoc()): ?>
              <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subcategory:</label>
          <select name="subcategory" id="filter_subcategory" onchange="filterSubSubcategories(this, 'filter_sub_subcategory'); this.form.submit();" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <?php foreach ($subcategories_with_category as $sub): ?>
              <option value="<?= $sub['id'] ?>" data-category-id="<?= $sub['category_id'] ?>" <?= $subcategory_filter == $sub['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sub['subcategory_name']) ?> <?php if (!empty($sub['category_name'])): ?>(<?= htmlspecialchars($sub['category_name']) ?>)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sub-Subcategory:</label>
          <select name="sub_subcategory" id="filter_sub_subcategory" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <?php foreach ($sub_subcategories_with_parent as $subsub): ?>
              <option value="<?= $subsub['id'] ?>" data-subcategory-id="<?= $subsub['subcategory_id'] ?>" <?= $sub_subcategory_filter == $subsub['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($subsub['sub_subcategory_name']) ?> <?php if (!empty($subsub['subcategory_name'])): ?>(<?= htmlspecialchars($subsub['subcategory_name']) ?>)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Size:</label>
          <select name="delivery_size" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <?php
            $delivery_size_result->data_seek(0);
            while ($size = $delivery_size_result->fetch_assoc()): ?>
              <option value="<?= $size['id'] ?>" <?= $delivery_size_filter == $size['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($size['size_name']) ?> (<?= $size['percentage'] ?>%)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lead Time:</label>
          <select name="leadtime" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <option value="with_leadtime" <?= $leadtime_filter === 'with_leadtime' ? 'selected' : '' ?>>⏰ With Lead Time</option>
            <option value="without_leadtime" <?= $leadtime_filter === 'without_leadtime' ? 'selected' : '' ?>>⚫ Without Lead Time</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category Sync:</label>
          <select name="sync_status" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">All</option>
            <option value="matched" <?= $sync_filter === 'matched' ? 'selected' : '' ?>>✓ Matched</option>
            <option value="mismatched" <?= $sync_filter === 'mismatched' ? 'selected' : '' ?>>✗ Mismatched</option>
            <option value="no_match" <?= $sync_filter === 'no_match' ? 'selected' : '' ?>>⚠ No Match</option>
          </select>
        </div>

        <div>
          <a href="?" class="text-blue-600 text-sm underline hover:text-blue-800">Reset Filters</a>
        </div>
      </div>
    </form>

    <!-- RIGHT: BULK ACTIONS CONTAINER -->
    <form method="POST" class="bg-white rounded-lg shadow-md p-4">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Bulk Actions</h3>
      <div class="space-y-4">
    

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select name="bulk_status" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <option value="new">New</option>
            <option value="old">Old</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Origin</label>
          <select name="bulk_origin" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <option value="local">Local</option>
            <option value="international">International</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
          <select name="bulk_category" id="bulk_category" onchange="filterSubcategories(this, 'bulk_subcategory')" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <?php
            $category_result->data_seek(0);
            while ($cat = $category_result->fetch_assoc()): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subcategories</label>
          <select name="bulk_subcategory[]" id="bulk_subcategory" multiple size="2" onchange="filterSubSubcategories(this, 'bulk_sub_subcategory')" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <?php foreach ($subcategories_with_category as $sub): ?>
              <option value="<?= $sub['id'] ?>" data-category-id="<?= $sub['category_id'] ?>">
                <?= htmlspecialchars($sub['subcategory_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label class="flex items-center gap-2 mt-1">
            <input type="checkbox" name="append_subcategory" value="1" class="w-3 h-3 form-checkbox">
            <span class="text-xs text-gray-700">Add to existing</span>
          </label>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sub-Subcategories</label>
          <select name="bulk_sub_subcategory[]" id="bulk_sub_subcategory" multiple size="2" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <?php foreach ($sub_subcategories_with_parent as $subsub): ?>
              <option value="<?= $subsub['id'] ?>" data-subcategory-id="<?= $subsub['subcategory_id'] ?>">
                <?= htmlspecialchars($subsub['sub_subcategory_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label class="flex items-center gap-2 mt-1">
            <input type="checkbox" name="append_sub_subcategory" value="1" class="w-3 h-3 form-checkbox">
            <span class="text-xs text-gray-700">Add to existing</span>
          </label>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Size</label>
          <select name="bulk_delivery_size" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <?php
            $delivery_size_result->data_seek(0);
            while ($size = $delivery_size_result->fetch_assoc()): ?>
              <option value="<?= $size['id'] ?>"><?= htmlspecialchars($size['size_name']) ?> (<?= $size['percentage'] ?>%)</option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lead Count</label>
          <input type="number" name="bulk_lead_count" placeholder="Leave empty" min="1" class="border rounded px-3 py-2 text-sm w-full">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lead Interval</label>
          <select name="bulk_lead_interval" class="border rounded px-3 py-2 text-sm w-full">
            <option value="">No Change</option>
            <option value="day">Day</option>
            <option value="week">Week</option>
            <option value="month">Month</option>
            <option value="year">Year</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lead Gap (Days)</label>
          <input type="number" name="bulk_lead_gap" placeholder="Leave empty" min="0" class="border rounded px-3 py-2 text-sm w-full">
        </div>

        <div class="flex gap-2 pt-4 border-t">
          <button type="submit" name="auto_sync_categories" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium flex-1">
            Auto-Sync
          </button>
          <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded text-sm font-medium flex-1">
            Apply
          </button>
        </div>
      </div>
    </form>
  </div>


      <table class="w-full bg-white rounded-lg shadow-md">
        <thead class="bg-gray-200 sticky top-0">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800 w-12">
              <input type="checkbox" class="w-4 h-4 form-checkbox">
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Image</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Variant Name</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Product</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Color / Size</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Category Sync</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Category</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Subcategories</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Sub-Subcategories</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Delivery Size</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Lead Time</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Status / Origin</th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-800">Percent / Discount</th>
          </tr>
        </thead>
        <tbody>
              <div class="flex items-center gap-2 pb-4 border-b">
          <input type="checkbox" onclick="toggleSelectAll(this)" class="w-4 h-4 form-checkbox">
          <span class="text-sm font-medium text-gray-700">Select All</span>
        </div>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr id="row-<?= $row['id'] ?>" class="border-b hover:bg-gray-50 transition cursor-pointer" onclick="toggleRowSelection('checkbox-<?= $row['id'] ?>')">
            <td class="px-4 py-3">
              <input type="checkbox" name="bulk_ids[]" value="<?= $row['id'] ?>" class="variant-checkbox w-4 h-4 form-checkbox" id="checkbox-<?= $row['id'] ?>" onclick="event.stopPropagation()">
            </td>
            <td class="px-4 py-3">
              <?php if (!empty($row['main_image'])): ?>
                <img src="../../<?= htmlspecialchars($row['main_image']) ?>" alt="Product" class="h-12 w-12 object-contain rounded border">
              <?php else: ?>
                <div class="h-12 w-12 bg-gray-200 rounded border flex items-center justify-center text-xs text-gray-500">No Image</div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-800">
              <div class="font-medium truncate" title="<?= htmlspecialchars($row['namevariant']) ?>">
                <?= htmlspecialchars($row['namevariant']) ?>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <div class="truncate" title="<?= htmlspecialchars($row['product_name']) ?>">
                <?= htmlspecialchars($row['product_name']) ?>
              </div>
              <div class="text-xs text-blue-600" title="<?= htmlspecialchars($row['codename']) ?>">
                <?= htmlspecialchars($row['codename']) ?>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <?php if (!empty($row['color'])): ?>
                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">🎨 <?= htmlspecialchars(substr($row['color'], 0, 15)) ?><?= strlen($row['color']) > 15 ? '...' : '' ?></span>
              <?php endif; ?>
              <?php if (!empty($row['size'])): ?>
                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs ml-1">📏 <?= htmlspecialchars($row['size']) ?></span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm text-center">
              <?php if ($row['category_sync_status'] === 'matched'): ?>
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">✓ Matched</span>
              <?php elseif ($row['category_sync_status'] === 'mismatched'): ?>
                <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-medium">✗ Mismatched</span>
              <?php else: ?>
                <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-medium">⚠ No Match</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm">
              <div class="font-medium text-gray-800">
                <?= htmlspecialchars($row['current_category_name'] ?: 'None') ?>
              </div>
              <?php if ($row['category_sync_status'] === 'mismatched'): ?>
                <div class="text-xs text-green-600">
                  Expected: <?= htmlspecialchars($row['expected_category_name']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm">
              <?php 
              $subcatArray = [];
              if (!empty($row['subcategory_name'])) {
                $decoded = json_decode($row['subcategory_name'], true);
                $subcatArray = is_array($decoded) ? $decoded : [$row['subcategory_name']];
              }
              if (!empty($subcatArray)): ?>
                <div class="flex flex-wrap gap-1">
                  <?php foreach ($subcatArray as $subcat): ?>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">
                      <?= htmlspecialchars(substr($subcat, 0, 12)) ?><?= strlen($subcat) > 12 ? '...' : '' ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="text-gray-500 text-xs">-</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm">
              <?php 
              $subSubArray = [];
              if (!empty($row['all_sub_subcategory_names'])) {
                $subSubArray = explode(', ', $row['all_sub_subcategory_names']);
              }
              if (!empty($subSubArray)): ?>
                <div class="flex flex-wrap gap-1">
                  <?php foreach ($subSubArray as $subsub): ?>
                    <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs">
                      <?= htmlspecialchars(substr($subsub, 0, 12)) ?><?= strlen($subsub) > 12 ? '...' : '' ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="text-gray-500 text-xs">-</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm text-indigo-600">
              <?php if ($row['delivery_size_name']): ?>
                <div><?= htmlspecialchars($row['delivery_size_name']) ?></div>
                <div class="text-xs text-gray-600"><?= $row['delivery_size_percentage'] ?>%</div>
              <?php else: ?>
                <span class="text-gray-500 text-xs">-</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm">
              <?php if ($row['lead_count'] && $row['lead_interval']): ?>
                <div class="text-teal-600 font-medium">
                  ⏰ <?= $row['lead_count'] ?><?= substr($row['lead_interval'], 0, 1) ?>
                  <?php if ($row['lead_gap']): ?> +<?= $row['lead_gap'] ?>d<?php endif; ?>
                </div>
              <?php else: ?>
                <span class="text-gray-400 text-xs">⚫ No Lead Time</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm">
              <span class="text-xs px-2 py-1 rounded 
                <?= $row['status'] === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-800' ?>">
                <?= ucfirst($row['status']) ?>
              </span>
              <span class="text-xs px-2 py-1 rounded ml-1
                <?= $row['origin'] === 'international' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-800' ?>">
                <?= ucfirst($row['origin']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700">
              <span class="font-medium"><?php echo $row['percent']; ?>%</span> / <span class="font-medium"><?php echo $row['discount']; ?>%</span>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<style>
  select[multiple] {
    padding: 4px;
    min-height: 80px;
  }

  select[multiple]:focus {
    outline: 2px solid #f97316;
    outline-offset: 2px;
  }

  select[multiple] option {
    padding: 2px 4px;
    margin: 1px 0;
  }

  select[multiple] option:checked {
    background: linear-gradient(#f97316, #f97316);
    color: white;
  }
</style>

</body>
</html>

<?php $conn->close(); ?>