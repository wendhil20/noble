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

function autoAssignAllCategories($conn)
{
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
  <title>Manage Product Variants - Multiple Subcategories</title>
  <script>
    const subcategoriesData = <?= json_encode($subcategories_with_category) ?>;
    const subSubcategoriesData = <?= json_encode($sub_subcategories_with_parent) ?>;

    let isSelecting = false;
    let startIndex = -1;
    let selectionStartState = false;

    function toggleSelectAll(source) {
      document.querySelectorAll('.variant-checkbox').forEach(cb => {
        cb.checked = source.checked;
        const row = document.getElementById('row-' + cb.value);
        updateRowVisualState(row, source.checked);
      });
      ['select-all', 'select-all-table'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el !== source) el.checked = source.checked;
      });
      updateSelectedCount();
    }

    function toggleCheckbox(id) {
      const cb = document.getElementById('checkbox-' + id);
      if (!cb) return;
      cb.checked = !cb.checked;
      const row = document.getElementById('row-' + id);
      updateRowVisualState(row, cb.checked);
      updateSelectAllState();
      updateSelectedCount();
    }

    function toggleRowSelection(event, checkboxId) {
      if (event.target.type === 'checkbox') return;
      const checkbox = document.getElementById(checkboxId);
      checkbox.checked = !checkbox.checked;
      const row = event.currentTarget;
      updateRowVisualState(row, checkbox.checked);
      updateSelectAllState();
      updateSelectedCount();
    }

    function updateRowVisualState(rowElement, isChecked) {
      if (!rowElement) return;
      if (isChecked) {
        rowElement.classList.add('bg-orange-100', 'ring-2', 'ring-orange-500');
      } else {
        rowElement.classList.remove('bg-orange-100', 'ring-2', 'ring-orange-500');
      }
    }

    function updateSelectAllState() {
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      const checkedBoxes = document.querySelectorAll('.variant-checkbox:checked');
      ['select-all', 'select-all-table'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (checkedBoxes.length === 0) {
          el.indeterminate = false;
          el.checked = false;
        } else if (checkedBoxes.length === checkboxes.length) {
          el.indeterminate = false;
          el.checked = true;
        } else {
          el.indeterminate = true;
        }
      });
    }

    function updateSelectedCount() {
      const checked = document.querySelectorAll('.variant-checkbox:checked').length;
      const el = document.getElementById('selected-count');
      if (el) el.textContent = checked > 0 ? checked + ' item' + (checked > 1 ? 's' : '') + ' selected' : '';
    }

    function filterSubcategories(selectElement, targetId) {
      const categoryId = selectElement.value;
      const subcategorySelect = document.getElementById(targetId);
      subcategorySelect.innerHTML = '<option value="">Select Subcategories</option>';
      if (categoryId) {
        subcategoriesData
          .filter(sub => sub.category_id == categoryId)
          .forEach(sub => {
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
        subSubcategoriesData
          .filter(subsub => subsub.subcategory_id == subcategoryId)
          .forEach(subsub => {
            const option = document.createElement('option');
            option.value = subsub.id;
            option.textContent = subsub.sub_subcategory_name;
            subSubcategorySelect.appendChild(option);
          });
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      const checkboxes = document.querySelectorAll('.variant-checkbox');

      checkboxes.forEach(function (checkbox, index) {
        checkbox.addEventListener('mousedown', function (e) {
          e.preventDefault();
          isSelecting = true;
          startIndex = index;
          selectionStartState = !checkbox.checked;
          checkbox.checked = selectionStartState;
          updateRowVisualState(document.getElementById('row-' + checkbox.value), selectionStartState);
        });

        checkbox.addEventListener('mouseenter', function () {
          if (isSelecting && startIndex !== -1) {
            const currentIndex = Array.from(checkboxes).indexOf(this);
            const minIndex = Math.min(startIndex, currentIndex);
            const maxIndex = Math.max(startIndex, currentIndex);
            checkboxes.forEach(function (cb, idx) {
              if (idx >= minIndex && idx <= maxIndex) {
                cb.checked = selectionStartState;
                updateRowVisualState(document.getElementById('row-' + cb.value), selectionStartState);
              }
            });
          }
        });

        checkbox.addEventListener('change', function () {
          updateRowVisualState(document.getElementById('row-' + this.value), this.checked);
          updateSelectAllState();
          updateSelectedCount();
        });
      });

      document.addEventListener('mouseup', function () {
        if (isSelecting) {
          isSelecting = false;
          startIndex = -1;
          updateSelectAllState();
          updateSelectedCount();
        }
      });

      updateSelectAllState();
    });
  </script>
</head>

<body class="bg-gray-100 font-sans">
  <?php include '../navbar/top.php'; ?>
  <div class="max-w-full mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-orange-700 mb-6">Manage Product Variants (Multiple Subcategories Support)</h1>

    <?php if ($sync_message): ?>
  <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6">
    <span class="text-green-500 mt-0.5">✓</span>
    <span class="text-sm"><?= htmlspecialchars($sync_message) ?></span>
  </div>
<?php endif; ?>

<!-- Stats + Auto-sync row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

  <!-- Category sync status -->
  <div class="bg-white border border-gray-200 rounded-xl p-4">
    <p class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wide">Category sync</p>
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-sm text-gray-700">
          <span class="w-2 h-2 rounded-full bg-green-500"></span> Matched
        </span>
        <span class="text-sm font-semibold text-green-600"><?= $counts['matched_count'] ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-sm text-gray-700">
          <span class="w-2 h-2 rounded-full bg-red-500"></span> Mismatched
        </span>
        <span class="text-sm font-semibold text-red-600"><?= $counts['mismatched_count'] ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-sm text-gray-700">
          <span class="w-2 h-2 rounded-full bg-yellow-400"></span> No match
        </span>
        <span class="text-sm font-semibold text-yellow-600"><?= $counts['no_match_count'] ?></span>
      </div>
    </div>
  </div>

  <!-- Lead time status -->
  <div class="bg-white border border-gray-200 rounded-xl p-4">
    <p class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wide">Lead time</p>
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-sm text-gray-700">
          <span class="w-2 h-2 rounded-full bg-blue-500"></span> With lead time
        </span>
        <span class="text-sm font-semibold text-blue-600"><?= $leadtime_counts['with_leadtime_count'] ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-sm text-gray-700">
          <span class="w-2 h-2 rounded-full bg-gray-400"></span> Without lead time
        </span>
        <span class="text-sm font-semibold text-gray-600"><?= $leadtime_counts['without_leadtime_count'] ?></span>
      </div>
    </div>
  </div>

  <!-- Auto-sync all -->
  <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-between gap-4">
    <div>
      <p class="text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Auto-sync all</p>
      <p class="text-sm text-gray-600">Set categories for all variants based on product codename.</p>
    </div>
    <form method="POST">
      <button type="submit" name="auto_sync_all"
        onclick="return confirm('This will update ALL product variants to match their product codename categories. Continue?')"
        class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        ⟳ Sync all categories
      </button>
    </form>
  </div>

</div>

<!-- Tip -->
<div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 mb-6">
  <span class="text-amber-500 text-sm">💡</span>
  <p class="text-sm text-amber-800">Drag across checkboxes to select or deselect multiple rows quickly.</p>
</div>

    <form method="GET" id="filterForm">
      <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <span class="text-sm font-medium text-gray-700">Filters</span>
          <a href="?"
            class="inline-flex items-center gap-1.5 text-xs text-gray-500 border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition">
            ↺ Reset all
          </a>
        </div>

        <!-- Filter Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Status</label>
            <select name="status" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
              <option value="old" <?= $status_filter === 'old' ? 'selected' : '' ?>>Old</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Origin</label>
            <select name="origin" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <option value="local" <?= $origin_filter === 'local' ? 'selected' : '' ?>>Local</option>
              <option value="international" <?= $origin_filter === 'international' ? 'selected' : '' ?>>International
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Category</label>
            <select name="category" id="filter_category"
              onchange="filterSubcategories(this, 'filter_subcategory'); this.form.submit();"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <?php $category_result->data_seek(0);
              while ($cat = $category_result->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Subcategory</label>
            <select name="subcategory" id="filter_subcategory"
              onchange="filterSubSubcategories(this, 'filter_sub_subcategory'); this.form.submit();"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <?php foreach ($subcategories_with_category as $sub): ?>
                <option value="<?= $sub['id'] ?>" data-category-id="<?= $sub['category_id'] ?>"
                  <?= $subcategory_filter == $sub['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sub['subcategory_name']) ?>
                  <?php if (!empty($sub['category_name'])): ?>(<?= htmlspecialchars($sub['category_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Sub-subcategory</label>
            <select name="sub_subcategory" id="filter_sub_subcategory" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <?php foreach ($sub_subcategories_with_parent as $subsub): ?>
                <option value="<?= $subsub['id'] ?>" data-subcategory-id="<?= $subsub['subcategory_id'] ?>"
                  <?= $sub_subcategory_filter == $subsub['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($subsub['sub_subcategory_name']) ?>
                  <?php if (!empty($subsub['subcategory_name'])): ?>(<?= htmlspecialchars($subsub['subcategory_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Delivery size</label>
            <select name="delivery_size" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <?php $delivery_size_result->data_seek(0);
              while ($size = $delivery_size_result->fetch_assoc()): ?>
                <option value="<?= $size['id'] ?>" <?= $delivery_size_filter == $size['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($size['size_name']) ?> (<?= $size['percentage'] ?>%)
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Lead time</label>
            <select name="leadtime" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <option value="with_leadtime" <?= $leadtime_filter === 'with_leadtime' ? 'selected' : '' ?>>With lead time
              </option>
              <option value="without_leadtime" <?= $leadtime_filter === 'without_leadtime' ? 'selected' : '' ?>>Without
                lead time</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Category sync</label>
            <select name="sync_status" onchange="this.form.submit()"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">All</option>
              <option value="matched" <?= $sync_filter === 'matched' ? 'selected' : '' ?>>Matched</option>
              <option value="mismatched" <?= $sync_filter === 'mismatched' ? 'selected' : '' ?>>Mismatched</option>
              <option value="no_match" <?= $sync_filter === 'no_match' ? 'selected' : '' ?>>No match</option>
            </select>
          </div>

        </div>

        <!-- Active filter badges -->
        <?php
        $active = [];
        if ($status_filter)
          $active[] = ['label' => 'Status', 'val' => ucfirst($status_filter), 'key' => 'status'];
        if ($origin_filter)
          $active[] = ['label' => 'Origin', 'val' => ucfirst($origin_filter), 'key' => 'origin'];
        if ($category_filter) {
          $category_result->data_seek(0);
          while ($c = $category_result->fetch_assoc()) {
            if ($c['id'] == $category_filter) {
              $active[] = ['label' => 'Category', 'val' => $c['name'], 'key' => 'category'];
              break;
            }
          }
        }
        if ($subcategory_filter) {
          foreach ($subcategories_with_category as $s) {
            if ($s['id'] == $subcategory_filter) {
              $active[] = ['label' => 'Subcategory', 'val' => $s['subcategory_name'], 'key' => 'subcategory'];
              break;
            }
          }
        }
        if ($sub_subcategory_filter) {
          foreach ($sub_subcategories_with_parent as $ss) {
            if ($ss['id'] == $sub_subcategory_filter) {
              $active[] = ['label' => 'Sub-subcategory', 'val' => $ss['sub_subcategory_name'], 'key' => 'sub_subcategory'];
              break;
            }
          }
        }
        if ($delivery_size_filter) {
          $delivery_size_result->data_seek(0);
          while ($ds = $delivery_size_result->fetch_assoc()) {
            if ($ds['id'] == $delivery_size_filter) {
              $active[] = ['label' => 'Delivery size', 'val' => $ds['size_name'], 'key' => 'delivery_size'];
              break;
            }
          }
        }
        if ($leadtime_filter)
          $active[] = ['label' => 'Lead time', 'val' => $leadtime_filter === 'with_leadtime' ? 'With lead time' : 'Without lead time', 'key' => 'leadtime'];
        if ($sync_filter)
          $active[] = ['label' => 'Category sync', 'val' => ucfirst(str_replace('_', ' ', $sync_filter)), 'key' => 'sync_status'];
        ?>

        <?php if (!empty($active)): ?>
          <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
            <?php foreach ($active as $f):
              $params = $_GET;
              unset($params[$f['key']]);
              $clearUrl = '?' . http_build_query($params);
              ?>
              <span
                class="inline-flex items-center gap-1.5 text-xs bg-orange-50 text-orange-700 border border-orange-200 rounded-full px-3 py-1 font-medium">
                <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['val']) ?>
                <a href="<?= $clearUrl ?>"
                  class="text-orange-400 hover:text-orange-600 font-bold leading-none transition">&times;</a>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </form>

    <form method="POST">

      <!-- Bulk Actions Bar -->
      <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4">
        <div class="flex items-center gap-3 mb-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="select-all" class="w-4 h-4 accent-orange-500" onchange="toggleSelectAll(this)">
            <span class="text-sm font-medium text-gray-700">Select All</span>
          </label>
          <span class="text-xs text-gray-400" id="selected-count"></span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">

          <!-- Status -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Status</label>
            <select name="bulk_status"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change status</option>
              <option value="new">New</option>
              <option value="old">Old</option>
            </select>
          </div>

          <!-- Origin -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Origin</label>
            <select name="bulk_origin"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change origin</option>
              <option value="local">Local</option>
              <option value="international">International</option>
            </select>
          </div>

          <!-- Category -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Category</label>
            <select name="bulk_category" id="bulk_category" onchange="filterSubcategories(this, 'bulk_subcategory')"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change category</option>
              <?php $category_result->data_seek(0);
              while ($cat = $category_result->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Subcategory -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Subcategory</label>
            <select name="bulk_subcategory[]" id="bulk_subcategory" multiple size="1"
              onchange="filterSubSubcategories(this, 'bulk_sub_subcategory')" title="Hold Ctrl/Cmd to select multiple"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change subcategory</option>
              <?php foreach ($subcategories_with_category as $sub): ?>
                <option value="<?= $sub['id'] ?>" data-category-id="<?= $sub['category_id'] ?>">
                  <?= htmlspecialchars($sub['subcategory_name']) ?>
                  <?php if (!empty($sub['category_name'])): ?>(<?= htmlspecialchars($sub['category_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="checkbox" name="append_subcategory" value="1" class="w-3 h-3 accent-orange-500">
              <span class="text-xs text-gray-500">Add to existing</span>
            </label>
          </div>

          <!-- Sub-subcategory -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Sub-subcategory</label>
            <select name="bulk_sub_subcategory[]" id="bulk_sub_subcategory" multiple size="1"
              title="Hold Ctrl/Cmd to select multiple"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change sub-subcategory</option>
              <?php foreach ($sub_subcategories_with_parent as $subsub): ?>
                <option value="<?= $subsub['id'] ?>" data-subcategory-id="<?= $subsub['subcategory_id'] ?>">
                  <?= htmlspecialchars($subsub['sub_subcategory_name']) ?>
                  <?php if (!empty($subsub['subcategory_name'])): ?>(<?= htmlspecialchars($subsub['subcategory_name']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="checkbox" name="append_sub_subcategory" value="1" class="w-3 h-3 accent-orange-500">
              <span class="text-xs text-gray-500">Add to existing</span>
            </label>
          </div>

          <!-- Delivery Size -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Delivery size</label>
            <select name="bulk_delivery_size"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Change size</option>
              <?php $delivery_size_result->data_seek(0);
              while ($size = $delivery_size_result->fetch_assoc()): ?>
                <option value="<?= $size['id'] ?>"><?= htmlspecialchars($size['size_name']) ?>
                  (<?= $size['percentage'] ?>%)</option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Lead Time -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Lead count</label>
            <input type="number" name="bulk_lead_count" placeholder="e.g. 3" min="1"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Lead interval</label>
            <select name="bulk_lead_interval"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent cursor-pointer">
              <option value="">Interval</option>
              <option value="day">Day</option>
              <option value="week">Week</option>
              <option value="month">Month</option>
              <option value="year">Year</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500">Gap (days)</label>
            <input type="number" name="bulk_lead_gap" placeholder="e.g. 0" min="0"
              class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" />
          </div>

        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
          <button type="submit" name="auto_sync_categories" title="Auto-sync categories based on product codename"
            class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            ⟳ Auto-sync categories
          </button>
          <button type="submit"
            class="inline-flex items-center gap-1.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            Apply to selected
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b-2 border-gray-200 sticky top-0">
              <tr>
                <th class="px-4 py-3 w-10">
                  <input type="checkbox" id="select-all-table" class="w-4 h-4 accent-orange-500"
                    onchange="toggleSelectAll(this)">
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Image</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Sync</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Product name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Variant</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Subcategories</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Sub-subcategories</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Discount</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Delivery</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Lead time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Origin</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr id="row-<?= $row['id'] ?>" class="hover:bg-orange-50 transition cursor-pointer"
                  onclick="toggleCheckbox(<?= $row['id'] ?>)">

                  <!-- Checkbox -->
                  <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                    <input type="checkbox" id="checkbox-<?= $row['id'] ?>" name="bulk_ids[]" value="<?= $row['id'] ?>"
                      class="variant-checkbox w-4 h-4 accent-orange-500" onchange="updateSelectedCount()">
                  </td>

                  <!-- Image -->
                  <td class="px-4 py-3">
                    <?php if (!empty($row['main_image'])): ?>
                      <img src="../../<?= htmlspecialchars($row['main_image']) ?>"
                        alt="<?= htmlspecialchars($row['product_name']) ?>" class="h-10 w-10 object-contain rounded-lg">
                    <?php else: ?>
                      <div
                        class="h-10 w-10 bg-gray-100 rounded-lg border flex items-center justify-center text-gray-400 text-xs">
                        —</div>
                    <?php endif; ?>
                  </td>

                  <!-- Sync status -->
                  <td class="px-4 py-3 text-center">
                    <?php if ($row['category_sync_status'] === 'matched'): ?>
                      <span
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-600 text-xs font-bold">✓</span>
                    <?php elseif ($row['category_sync_status'] === 'mismatched'): ?>
                      <span
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-600 text-xs font-bold">✗</span>
                    <?php else: ?>
                      <span
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-600 text-xs font-bold">!</span>
                    <?php endif; ?>
                  </td>

                  <!-- Product name -->
                  <td class="px-4 py-3">
                    <span class="font-medium text-gray-800 text-xs"><?= htmlspecialchars($row['product_name']) ?></span>
                  </td>

                  <!-- Code -->
                  <td class="px-4 py-3">
                    <span
                      class="font-mono text-xs bg-gray-100 text-blue-600 px-2 py-0.5 rounded"><?= htmlspecialchars($row['codename']) ?></span>
                  </td>

                  <!-- Variant -->
                  <td class="px-4 py-3">
                    <div class="text-xs text-gray-700 font-medium"><?= htmlspecialchars($row['namevariant']) ?></div>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <?php if (!empty($row['color'])): ?>
                        <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">
                          <?= htmlspecialchars(substr($row['color'], 0, 12)) ?>     <?= strlen($row['color']) > 12 ? '…' : '' ?>
                        </span>
                      <?php endif; ?>
                      <?php if (!empty($row['size'])): ?>
                        <span
                          class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"><?= htmlspecialchars($row['size']) ?></span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Category -->
                  <td class="px-4 py-3">
                    <span
                      class="text-xs font-medium <?= $row['category_sync_status'] === 'mismatched' ? 'text-red-600' : 'text-gray-700' ?>">
                      <?= htmlspecialchars($row['current_category_name'] ?: 'None') ?>
                    </span>
                    <?php if ($row['category_sync_status'] === 'mismatched'): ?>
                      <div class="text-xs text-green-600 mt-0.5">→ <?= htmlspecialchars($row['expected_category_name']) ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- Subcategories -->
                  <td class="px-4 py-3">
                    <?php
                    $subcatArray = [];
                    if (!empty($row['subcategory_name'])) {
                      $decoded = json_decode($row['subcategory_name'], true);
                      $subcatArray = is_array($decoded) ? $decoded : [$row['subcategory_name']];
                    }
                    ?>
                    <div class="flex flex-wrap gap-1">
                      <?php foreach (array_slice($subcatArray, 0, 2) as $subcatName): ?>
                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">
                          <?= htmlspecialchars(substr($subcatName, 0, 10)) ?>     <?= strlen($subcatName) > 10 ? '…' : '' ?>
                        </span>
                      <?php endforeach; ?>
                      <?php if (count($subcatArray) > 2): ?>
                        <span class="text-xs text-blue-500 font-medium">+<?= count($subcatArray) - 2 ?></span>
                      <?php endif; ?>
                      <?php if (empty($subcatArray)): ?>
                        <span class="text-gray-400 text-xs">—</span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Sub-subcategories -->
                  <td class="px-4 py-3">
                    <?php
                    $subSubArray = [];
                    if (!empty($row['sub_subcategory_ids'])) {
                      $subSubIds = json_decode($row['sub_subcategory_ids'], true);
                      if (is_array($subSubIds) && count($subSubIds) > 0 && !empty($row['all_sub_subcategory_names'])) {
                        $subSubArray = explode(', ', $row['all_sub_subcategory_names']);
                      }
                    }
                    ?>
                    <div class="flex flex-wrap gap-1">
                      <?php foreach (array_slice($subSubArray, 0, 2) as $subSubName): ?>
                        <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">
                          <?= htmlspecialchars(substr($subSubName, 0, 10)) ?>     <?= strlen($subSubName) > 10 ? '…' : '' ?>
                        </span>
                      <?php endforeach; ?>
                      <?php if (count($subSubArray) > 2): ?>
                        <span class="text-xs text-purple-500 font-medium">+<?= count($subSubArray) - 2 ?></span>
                      <?php endif; ?>
                      <?php if (empty($subSubArray)): ?>
                        <span class="text-gray-400 text-xs">—</span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Discount -->
                  <td class="px-4 py-3">
                    <div class="flex gap-1">
                      <span
                        class="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded font-medium"><?= $row['percent'] ?>%</span>
                      <span
                        class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium"><?= $row['discount'] ?>%</span>
                    </div>
                  </td>

                  <!-- Delivery size -->
                  <td class="px-4 py-3">
                    <?php if ($row['delivery_size_name']): ?>
                      <div class="text-xs font-medium text-indigo-600"><?= htmlspecialchars($row['delivery_size_name']) ?>
                      </div>
                      <div class="text-xs text-gray-400"><?= $row['delivery_size_percentage'] ?>%</div>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Lead time -->
                  <td class="px-4 py-3">
                    <?php if ($row['lead_count'] && $row['lead_interval']): ?>
                      <span class="text-xs font-medium text-teal-600">
                        <?= $row['lead_count'] ?>
                        <?= $row['lead_interval'] ?>     <?= $row['lead_gap'] ? ' +' . $row['lead_gap'] . 'd' : '' ?>
                      </span>
                    <?php else: ?>
                      <span class="text-xs text-gray-400">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Origin -->
                  <td class="px-4 py-3">
                    <span
                      class="text-xs font-medium px-2 py-0.5 rounded-full
                  <?= $row['origin'] === 'international' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?>">
                      <?= ucfirst($row['origin']) ?>
                    </span>
                  </td>

                  <!-- Item status -->
                  <td class="px-4 py-3">
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                  <?= $row['status'] === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                      <?= ucfirst($row['status']) ?>
                    </span>
                  </td>

                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </form>

  </div>

  <style>
    .bg-orange-50 .selection-indicator {
      border-color: #f97316;
      background-color: #f97316;
    }

    .bg-orange-50 .checkmark {
      display: block !important;
      background-color: white;
    }

    select[multiple] {
      padding: 4px;
      min-height: 32px;
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