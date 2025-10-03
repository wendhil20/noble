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

/**
 * Auto-assigns categories for ALL product variants based on codenames
 * Also updates the codename in products table to match category
 */
function autoAssignAllCategories($conn) {
    $result = [
        'success' => false,
        'updated_count' => 0,
        'errors' => [],
        'details' => []
    ];
    
    try {
        // Update all variants at once - BOTH category_id AND category_name
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
        
        // Check for products without matching categories
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

// Get message and error from session (for PRG pattern)
$sync_message = '';
if (isset($_SESSION['sync_message'])) {
    $sync_message = $_SESSION['sync_message'];
    unset($_SESSION['sync_message']);
}

// Fetch category, subcategory, and delivery size lists
$category_result = $conn->query("SELECT * FROM categories");
$subcategory_result = $conn->query("SELECT * FROM product_subcategories ORDER BY subcategory_name");
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

        // FIXED: Update both product_variants category AND products codename
        if (!empty($_POST['bulk_category'])) {
            $catId = intval($_POST['bulk_category']);
            
            // First, get the category name from the categories table
            $catQuery = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $catQuery->bind_param("i", $catId);
            $catQuery->execute();
            $catResult = $catQuery->get_result();
            
            if ($catResult->num_rows > 0) {
                $catData = $catResult->fetch_assoc();
                $catName = $catData['name'];
                
                // Update BOTH category_id AND category_name fields in product_variants
                $stmt = $conn->prepare("UPDATE product_variants SET category_id = ?, category_name = ? WHERE id IN (" . implode(',', $ids) . ")");
                $stmt->bind_param("is", $catId, $catName);
                $stmt->execute();
                
                // NEW: Also update the codename in products table for all affected products
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

        // FIXED: Handle subcategory using product_subcategories table
        if (!empty($_POST['bulk_subcategory'])) {
            $subcatName = $_POST['bulk_subcategory'];
            // First get the ID of the subcategory by name from product_subcategories
            $subcatQuery = $conn->prepare("SELECT id FROM product_subcategories WHERE subcategory_name = ?");
            $subcatQuery->bind_param("s", $subcatName);
            $subcatQuery->execute();
            $subcatResult = $subcatQuery->get_result();
            
            if ($subcatResult->num_rows > 0) {
                $subcatData = $subcatResult->fetch_assoc();
                $subcatId = $subcatData['id'];
                
                // Update BOTH subcategory_id AND subcategory_name fields
                $stmt = $conn->prepare("UPDATE product_variants SET subcategory_id = ?, subcategory_name = ? WHERE id IN (" . implode(',', $ids) . ")");
                $stmt->bind_param("is", $subcatId, $subcatName);
                $stmt->execute();
            }
        }

        // NEW: Handle delivery size updates
        if (!empty($_POST['bulk_delivery_size'])) {
            $deliverySizeId = intval($_POST['bulk_delivery_size']);
            $stmt = $conn->prepare("UPDATE product_variants SET delivery_size_id = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("i", $deliverySizeId);
            $stmt->execute();
        }

        // ✅ NEW: Handle lead time bulk updates
        if (!empty($_POST['bulk_lead_count']) && !empty($_POST['bulk_lead_interval'])) {
            $leadCount = intval($_POST['bulk_lead_count']);
            $leadInterval = $_POST['bulk_lead_interval'];
            $leadGap = !empty($_POST['bulk_lead_gap']) ? intval($_POST['bulk_lead_gap']) : null;

            // Update lead time directly in product_variants table
            $stmt = $conn->prepare("UPDATE product_variants SET lead_count = ?, lead_interval = ?, lead_gap = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("isi", $leadCount, $leadInterval, $leadGap);
            $stmt->execute();
        }

        // FIXED: Auto-sync categories based on product codename - update both variants and products
        if (isset($_POST['auto_sync_categories'])) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Update product_variants with category info
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
        
        // Redirect to prevent form resubmission
        $_SESSION['sync_message'] = "Bulk update completed successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// AUTO-SYNC: Automatically sync categories for all variants where codename exists
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
    
    // Store message in session and redirect
    $_SESSION['sync_message'] = $message;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$status_filter = $_GET['status'] ?? '';
$origin_filter = $_GET['origin'] ?? '';
$category_filter = $_GET['category'] ?? '';
$subcategory_filter = $_GET['subcategory'] ?? '';
$delivery_size_filter = $_GET['delivery_size'] ?? '';
$leadtime_filter = $_GET['leadtime'] ?? ''; // ✅ NEW: Lead time filter

// Enhanced query that shows category mismatch status and includes delivery size info and lead time
$query = "
    SELECT 
        pv.*,
        p.codename,
        p.product_name,
        p.main_image,
        pv.lead_count,
        pv.lead_interval,
        pv.lead_gap,
        c1.name as current_category_name,
        c2.name as expected_category_name,
        c2.id as expected_category_id,
        ps.subcategory_name as current_subcategory_name,
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
    LEFT JOIN product_subcategories ps ON pv.subcategory_id = ps.id
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

// UPDATED: Update the subcategory filter to work with product_subcategories
if (!empty($subcategory_filter)) {
    // Convert name to ID for the query
    $subcatQuery = $conn->prepare("SELECT id FROM product_subcategories WHERE subcategory_name = ?");
    $subcatQuery->bind_param("s", $subcategory_filter);
    $subcatQuery->execute();
    $subcatResult = $subcatQuery->get_result();
    
    if ($subcatResult->num_rows > 0) {
        $subcatData = $subcatResult->fetch_assoc();
        $query .= " AND pv.subcategory_id = " . intval($subcatData['id']);
    }
}

// NEW: Add delivery size filter
if (is_numeric($delivery_size_filter)) {
    $query .= " AND pv.delivery_size_id = " . intval($delivery_size_filter);
}

// ✅ NEW: Add lead time filter
if ($leadtime_filter === 'with_leadtime') {
    $query .= " AND pv.lead_count IS NOT NULL AND pv.lead_interval IS NOT NULL";
} elseif ($leadtime_filter === 'without_leadtime') {
    $query .= " AND (pv.lead_count IS NULL OR pv.lead_interval IS NULL)";
}

// Add filter for category sync status
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

// Get counts for sync status
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

// ✅ NEW: Get lead time counts
$leadtime_count_query = "
    SELECT 
        SUM(CASE WHEN pv.lead_count IS NOT NULL AND pv.lead_interval IS NOT NULL THEN 1 ELSE 0 END) as with_leadtime_count,
        SUM(CASE WHEN pv.lead_count IS NULL OR pv.lead_interval IS NULL THEN 1 ELSE 0 END) as without_leadtime_count
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
";
$leadtime_count_result = $conn->query($leadtime_count_query);
$leadtime_counts = $leadtime_count_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Product Variants</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function toggleSelectAll(source) {
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      checkboxes.forEach(cb => {
        cb.checked = source.checked;
        
        // Update visual feedback for each card
        const cardId = 'card-' + cb.value;
        const card = document.getElementById(cardId);
        
        if (cb.checked) {
          card.classList.add('ring-2', 'ring-orange-500', 'bg-orange-50');
        } else {
          card.classList.remove('ring-2', 'ring-orange-500', 'bg-orange-50');
        }
      });
    }

    // Function to handle card clicks
    function toggleCardSelection(cardElement, checkboxId) {
      const checkbox = document.getElementById(checkboxId);
      checkbox.checked = !checkbox.checked;
      
      // Update visual feedback
      updateCardVisualState(cardElement, checkbox.checked);
      
      // Update "Select All" checkbox state
      updateSelectAllState();
    }

    // Function to update card visual state
    function updateCardVisualState(cardElement, isChecked) {
      if (isChecked) {
        cardElement.classList.add('ring-2', 'ring-orange-500', 'bg-orange-50');
      } else {
        cardElement.classList.remove('ring-2', 'ring-orange-500', 'bg-orange-50');
      }
    }

    // Function to update "Select All" checkbox state
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

    // Initialize card selection states on page load
    document.addEventListener('DOMContentLoaded', function() {
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      checkboxes.forEach(function(checkbox) {
        const cardId = 'card-' + checkbox.value;
        const card = document.getElementById(cardId);
        
        // Set initial visual state
        updateCardVisualState(card, checkbox.checked);
        
        // Add click event listener to checkbox to update visual state
        checkbox.addEventListener('change', function() {
          updateCardVisualState(card, this.checked);
          updateSelectAllState();
        });
      });
      
      // Initialize "Select All" state
      updateSelectAllState();
    });
  </script>
</head>
<body class="bg-gray-100 font-sans">
<?php include '../navbar/top.php'; ?>
<div class="max-w-full mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-orange-700 mb-6">Manage Product Variant Status, Origin, Category, Delivery Size & Lead Time</h1>

  <?php if ($sync_message): ?>
  <!-- Success Message -->
  <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
    <strong class="font-bold">Success!</strong>
    <span class="block sm:inline"><?= htmlspecialchars($sync_message) ?></span>
  </div>
  <?php endif; ?>

  <!-- Auto-Sync All Button -->
  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-blue-800 mb-1">Auto-Sync All Categories</h3>
        <p class="text-blue-600 text-sm">Automatically set categories for all product variants based on their product codename.</p>
      </div>
      <form method="POST" class="ml-4">
        <button type="submit" name="auto_sync_all" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium" 
                onclick="return confirm('This will update ALL product variants to match their product codename categories. Continue?')">
          Auto-Sync All Categories
        </button>
      </form>
    </div>
  </div>

  <!-- Category Sync Status Summary -->
  <div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Category Synchronization Status</h3>
    <div class="flex gap-4 text-sm mb-3">
      <span class="text-green-600">✓ Matched: <?= $counts['matched_count'] ?></span>
      <span class="text-red-600">✗ Mismatched: <?= $counts['mismatched_count'] ?></span>
      <span class="text-yellow-600">⚠ No Match: <?= $counts['no_match_count'] ?></span>
    </div>
    <!-- ✅ NEW: Lead Time Status -->
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Lead Time Status</h3>
    <div class="flex gap-4 text-sm">
      <span class="text-blue-600">⏰ With Lead Time: <?= $leadtime_counts['with_leadtime_count'] ?></span>
      <span class="text-gray-600">⚫ Without Lead Time: <?= $leadtime_counts['without_leadtime_count'] ?></span>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap gap-4 items-center mb-6">
    <div>
      <label class="text-sm font-medium text-gray-700">Status:</label>
      <select name="status" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
        <option value="old" <?= $status_filter === 'old' ? 'selected' : '' ?>>Old</option>
      </select>
    </div>

    <div>
      <label class="text-sm font-medium text-gray-700">Origin:</label>
      <select name="origin" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="local" <?= $origin_filter === 'local' ? 'selected' : '' ?>>Local</option>
        <option value="international" <?= $origin_filter === 'international' ? 'selected' : '' ?>>International</option>
      </select>
    </div>

    <div>
      <label class="text-sm font-medium text-gray-700">Category:</label>
      <select name="category" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
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

    <!-- UPDATED: Subcategory dropdown now uses product_subcategories -->
    <div>
      <label class="text-sm font-medium text-gray-700">Subcategory:</label>
      <select name="subcategory" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <?php
        $subcategory_result->data_seek(0);
        while ($sub = $subcategory_result->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($sub['subcategory_name']) ?>" <?= $subcategory_filter === $sub['subcategory_name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($sub['subcategory_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <!-- NEW: Delivery Size filter -->
    <div>
      <label class="text-sm font-medium text-gray-700">Delivery Size:</label>
      <select name="delivery_size" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
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

    <!-- ✅ NEW: Lead Time filter -->
    <div>
      <label class="text-sm font-medium text-gray-700">Lead Time:</label>
      <select name="leadtime" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="with_leadtime" <?= $leadtime_filter === 'with_leadtime' ? 'selected' : '' ?>>⏰ With Lead Time</option>
        <option value="without_leadtime" <?= $leadtime_filter === 'without_leadtime' ? 'selected' : '' ?>>⚫ Without Lead Time</option>
      </select>
    </div>

    <div>
      <label class="text-sm font-medium text-gray-700">Category Sync:</label>
      <select name="sync_status" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="matched" <?= $sync_filter === 'matched' ? 'selected' : '' ?>>✓ Matched</option>
        <option value="mismatched" <?= $sync_filter === 'mismatched' ? 'selected' : '' ?>>✗ Mismatched</option>
        <option value="no_match" <?= $sync_filter === 'no_match' ? 'selected' : '' ?>>⚠ No Match</option>
      </select>
    </div>

    <a href="?" class="text-blue-600 text-sm underline hover:text-blue-800">Reset Filters</a>
  </form>

  <!-- Bulk Update Form -->
  <form method="POST">
    <div class="mb-4 flex justify-between items-center">
      <label class="flex items-center gap-2">
        <input type="checkbox" onclick="toggleSelectAll(this)" class="form-checkbox">
        <span class="text-sm font-medium text-gray-700">Select All</span>
      </label>
      <div class="flex gap-2 flex-wrap">
        <select name="bulk_status" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Status</option>
          <option value="new">New</option>
          <option value="old">Old</option>
        </select>

        <select name="bulk_origin" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Origin</option>
          <option value="local">Local</option>
          <option value="international">International</option>
        </select>

        <select name="bulk_category" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Category</option>
          <?php
          $category_result->data_seek(0);
          while ($cat = $category_result->fetch_assoc()): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- UPDATED: Subcategory dropdown now uses product_subcategories -->
        <select name="bulk_subcategory" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Subcategory</option>
          <?php
          $subcategory_result->data_seek(0);
          while ($sub = $subcategory_result->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($sub['subcategory_name']) ?>"><?= htmlspecialchars($sub['subcategory_name']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- NEW: Delivery Size bulk update dropdown -->
        <select name="bulk_delivery_size" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Delivery Size</option>
          <?php
          $delivery_size_result->data_seek(0);
          while ($size = $delivery_size_result->fetch_assoc()): ?>
            <option value="<?= $size['id'] ?>"><?= htmlspecialchars($size['size_name']) ?> (<?= $size['percentage'] ?>%)</option>
          <?php endwhile; ?>
        </select>

        <!-- ✅ NEW: Lead Time bulk update inputs -->
        <input type="number" name="bulk_lead_count" placeholder="Lead Count" min="1" class="border rounded px-2 py-1 text-sm w-24">
        <select name="bulk_lead_interval" class="border rounded px-2 py-1 text-sm">
          <option value="">Lead Interval</option>
          <option value="day">Day</option>
          <option value="week">Week</option>
          <option value="month">Month</option>
          <option value="year">Year</option>
        </select>
        <input type="number" name="bulk_lead_gap" placeholder="Gap (Days)" min="0" class="border rounded px-2 py-1 text-sm w-20">

        <button type="submit" name="auto_sync_categories" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded text-sm" title="Auto-sync categories based on product codename">
          Auto-Sync Categories
        </button>

        <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-1 rounded text-sm">Apply</button>
      </div>
    </div>

    <!-- Variant Cards -->
    <div class="grid gap-3 grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div id="card-<?= $row['id'] ?>" 
             class="bg-white rounded-lg shadow-sm p-2 hover:shadow-md transition cursor-pointer select-none text-xs" 
             onclick="toggleCardSelection(this, 'checkbox-<?= $row['id'] ?>')">
          
          <!-- Hidden checkbox for form submission -->
          <input type="checkbox" 
                 name="bulk_ids[]" 
                 value="<?= $row['id'] ?>" 
                 class="variant-checkbox hidden" 
                 id="checkbox-<?= $row['id'] ?>">
          
          <!-- Visual selection indicator -->
          <div class="flex items-center mb-1">
            <div class="w-3 h-3 border border-gray-300 rounded mr-1 flex items-center justify-center selection-indicator">
              <div class="w-1.5 h-1.5 bg-orange-600 rounded-full hidden checkmark"></div>
            </div>
            <span class="text-xs font-medium text-gray-700 truncate">Select</span>
          </div>

          <!-- Product Image -->
          <div class="mb-2 aspect-square pointer-events-none">
            <?php if (!empty($row['main_image'])): ?>
              <img src="../../<?= htmlspecialchars($row['main_image']) ?>" 
                   alt="<?= htmlspecialchars($row['product_name']) ?>" 
                   class="w-full h-full object-contain rounded border bg-white">
            <?php else: ?>
              <div class="w-full h-full bg-gray-200 rounded border flex items-center justify-center">
                <span class="text-gray-500 text-xs">No Image</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Category Sync Status Indicator -->
          <div class="mb-1 pointer-events-none">
            <?php if ($row['category_sync_status'] === 'matched'): ?>
              <span class="text-xs bg-green-100 text-green-700 px-1 py-0.5 rounded">✓</span>
            <?php elseif ($row['category_sync_status'] === 'mismatched'): ?>
              <span class="text-xs bg-red-100 text-red-700 px-1 py-0.5 rounded">✗</span>
            <?php else: ?>
              <span class="text-xs bg-yellow-100 text-yellow-700 px-1 py-0.5 rounded">⚠</span>
            <?php endif; ?>
          </div>

          <h3 class="text-xs font-semibold text-gray-800 mb-1 pointer-events-none truncate" title="<?= htmlspecialchars($row['namevariant']) ?>">
            <?= htmlspecialchars($row['namevariant']) ?>
          </h3>

          <div class="text-xs text-blue-600 mb-0.5 pointer-events-none truncate" title="<?= htmlspecialchars($row['codename']) ?>">
            <?= htmlspecialchars($row['codename']) ?>
          </div>
          
          <div class="text-xs text-gray-600 mb-0.5 pointer-events-none">
            <span class="font-medium"><?= $row['percent'] ?>%</span> | <span class="font-medium"><?= $row['discount'] ?>%</span>
          </div>
          
          <!-- Category Information with Sync Status -->
          <div class="text-xs text-gray-600 mb-0.5 pointer-events-none truncate" title="Current: <?= htmlspecialchars($row['current_category_name'] ?: 'None') ?>">
            Cat: <span class="font-medium <?= $row['category_sync_status'] === 'mismatched' ? 'text-red-600' : '' ?>">
              <?= htmlspecialchars($row['current_category_name'] ?: 'None') ?>
            </span>
          </div>
          
          <?php if ($row['category_sync_status'] === 'mismatched'): ?>
            <div class="text-xs text-green-600 mb-0.5 pointer-events-none truncate" title="Expected: <?= htmlspecialchars($row['expected_category_name']) ?>">
              Exp: <span class="font-medium"><?= htmlspecialchars($row['expected_category_name']) ?></span>
            </div>
          <?php endif; ?>
          
          <?php if ($row['current_subcategory_name']): ?>
            <div class="text-xs text-gray-600 mb-1 pointer-events-none truncate" title="<?= htmlspecialchars($row['current_subcategory_name']) ?>">
              Sub: <span class="font-medium"><?= htmlspecialchars($row['current_subcategory_name']) ?></span>
            </div>
          <?php endif; ?>

          <!-- NEW: Delivery Size Information -->
          <?php if ($row['delivery_size_name']): ?>
            <div class="text-xs text-purple-600 mb-1 pointer-events-none truncate" title="<?= htmlspecialchars($row['delivery_size_name']) ?> - <?= $row['delivery_size_percentage'] ?>%">
              Size: <span class="font-medium"><?= htmlspecialchars($row['delivery_size_name']) ?></span> (<?= $row['delivery_size_percentage'] ?>%)
            </div>
          <?php endif; ?>

          <!-- ✅ NEW: Lead Time Information -->
          <?php if ($row['lead_count'] && $row['lead_interval']): ?>
            <div class="text-xs text-indigo-600 mb-1 pointer-events-none truncate" 
                 title="Lead Time: <?= $row['lead_count'] ?> <?= $row['lead_interval'] ?><?= $row['lead_count'] > 1 ? 's' : '' ?><?= $row['lead_gap'] ? ' + ' . $row['lead_gap'] . ' day' . ($row['lead_gap'] > 1 ? 's' : '') : '' ?>">
              ⏰: <span class="font-medium"><?= $row['lead_count'] ?><?= substr($row['lead_interval'], 0, 1) ?></span><?= $row['lead_gap'] ? '+' . $row['lead_gap'] . 'd' : '' ?>
            </div>
          <?php else: ?>
            <div class="text-xs text-gray-400 mb-1 pointer-events-none">
              ⚫ No Lead Time
            </div>
          <?php endif; ?>

          <div class="flex gap-1 mb-1 pointer-events-none">
            <span class="text-xs px-1 py-0.5 rounded 
              <?= $row['status'] === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-800' ?>">
              <?= ucfirst($row['status']) ?>
            </span>
            <span class="text-xs px-1 py-0.5 rounded 
              <?= $row['origin'] === 'international' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-800' ?>">
              <?= ucfirst($row['origin']) ?>
            </span>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </form>
</div>

<style>
/* Additional CSS for better visual feedback */
.bg-orange-50 .selection-indicator {
  border-color: #f97316;
  background-color: #f97316;
}

.bg-orange-50 .checkmark {
  display: block !important;
  background-color: white;
}
</style>

</body>
</html>

<?php $conn->close(); ?>