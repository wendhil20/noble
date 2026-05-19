<?php
//update_product.php - UPDATED WITH STOCK HANDLING + DESCRIP1/6/7
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
  header("Location: " . BASE_URL . "/main");
  exit();
}

$_SESSION['last_activity'] = time();

$product_id = $_GET['id'] ?? null;

// ── Current user info ─────────────────────────────────────────────────────────
$current_user = $_SESSION['noble_user'];
$current_role = $_SESSION['noble_role'] ?? '';
$is_superadmin = ($current_role === 'superadmin');

// ── Ensure added_by column exists ─────────────────────────────────────────────
$chk_col = $conn->query("SHOW COLUMNS FROM products LIKE 'added_by'");
if ($chk_col->num_rows == 0) {
  $conn->query("ALTER TABLE products ADD COLUMN added_by VARCHAR(100) NULL DEFAULT NULL");
}

// ── Ownership check ───────────────────────────────────────────────────────────
if (!$is_superadmin) {
  $own_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND added_by = ?");
  $own_check->bind_param("is", $product_id, $current_user);
  $own_check->execute();
  if ($own_check->get_result()->num_rows === 0) {
    echo "<script>alert('Access denied. You can only edit your own products.'); window.location.href='main-adminupdateshop-page-2.php';</script>";
    exit();
  }
  $own_check->close();
}

if (!$product_id) {
  echo "Missing product ID.";
  exit;
}

$product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
$types = $conn->query("SELECT * FROM product_types WHERE product_id = $product_id");
$colors = $conn->query("SELECT * FROM product_colors WHERE product_id = $product_id");

$categories = $conn->query("SELECT * FROM categories ORDER BY name");

$existing_sub_images = [];
if (!empty($product['sub_images'])) {
  $decoded_sub_images = json_decode($product['sub_images'], true);
  if (is_array($decoded_sub_images)) {
    $existing_sub_images = $decoded_sub_images;
  }
}

// Get variant count for SKU button
$variant_count_result = $conn->query("SELECT COUNT(*) as cnt FROM product_variants WHERE product_id = $product_id");
$variant_count_row = $variant_count_result->fetch_assoc();
$variant_count = (int) $variant_count_row['cnt'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Update Product</title>
  <style>
    .sub-image-item {
      position: relative;
      display: inline-block;
      transition: all 0.3s ease;
    }

    .remove-sub-image {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ef4444;
      color: white;
      border: none;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .remove-sub-image:hover {
      background: #dc2626;
    }

    .restore-sub-image {
      position: absolute;
      top: -8px;
      left: -8px;
      background: #10b981;
      color: white;
      border: none;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      font-size: 12px;
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
    }

    .restore-sub-image:hover {
      background: #059669;
    }

    .image-preview {
      max-width: 80px;
      max-height: 80px;
      object-fit: cover;
      border-radius: 4px;
    }

    .marked-for-deletion {
      opacity: 0.5;
      border: 2px dashed #ef4444 !important;
    }

    .marked-for-deletion .restore-sub-image {
      display: flex;
    }
  </style>
</head>

<body class="bg-gray-100">

  <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

  <div class="bg-white p-6 rounded-lg shadow mt-5 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-orange-600">Update Product</h2>
      <a href="set_sku-page-5-A.php?product_id=<?= $product_id ?>"
        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
        <i class="fas fa-barcode"></i>
        SKU (<?= $variant_count ?>)
      </a>
    </div>

    <form action="<?= BASE_URL; ?>/updateprocess" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="product_id" value="<?php echo $product_id; ?>" />

      <!-- Product Name -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Product Name:</label>
        <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>"
          required class="w-full border border-gray-400 p-2 rounded" />
      </div>

      <!-- Main Image -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Main Image</label>
        <?php if (!empty($product['main_image'])): ?>
          <div class="mb-2">
            <img src="<?=BASE_URL?>/<?= htmlspecialchars($product['main_image']) ?>"
              class="h-20 w-20 object-contain rounded border border-gray-400" alt="Current Main Image">
            <p class="text-sm text-gray-600 mt-1">Current main image</p>
          </div>
        <?php endif; ?>
        <input type="file" name="main_image" accept="image/*" class="w-full border border-gray-400 p-2 rounded" />
        <p class="text-xs text-gray-500 mt-1">Leave blank to keep current image</p>
      </div>

      <!-- Sub Images -->
      <div class="mb-6">
        <label class="block font-semibold mb-2">Sub Images:</label>
        <div class="bg-gray-100 p-4 rounded">
          <?php if (!empty($existing_sub_images)): ?>
            <div class="mb-4">
              <h4 class="font-medium text-gray-700 mb-2">Current Sub Images:</h4>
              <div class="flex flex-wrap gap-3" id="existing-sub-images">
                <?php foreach ($existing_sub_images as $index => $sub_image): ?>
                  <div class="sub-image-item" data-image-index="<?= $index ?>">
                    <img src="<?=BASE_URL?>/sub_images/<?= htmlspecialchars($sub_image) ?>"
                      class="image-preview border border-gray-400" alt="Sub Image <?= $index + 1 ?>">
                    <button type="button" class="remove-sub-image" onclick="removeExistingSubImage(this, <?= $index ?>)"
                      title="Remove"><i class="fa-regular fa-circle-xmark"></i></button>
                    <button type="button" class="restore-sub-image" onclick="restoreExistingSubImage(this, <?= $index ?>)"
                      title="Restore">↻</button>
                    <input type="hidden" name="keep_sub_image[<?= $index ?>]" value="1">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="mb-4">
            <h4 class="font-medium text-gray-700 mb-2">Add New Sub Images:</h4>
            <div id="new-sub-images-section">
              <div class="new-sub-image-item flex gap-2 items-start  bg-white rounded  border-gray-400">
                <div class="flex-1">
                  <input type="file" name="new_sub_images[]" accept="image/*"
                    class="w-full border border-gray-400 p-2 rounded" onchange="previewNewSubImage(this)" />
                  <div class="new-sub-image-preview mt-2"></div>
                </div>
                <button type="button" onclick="addNewSubImage()"
                  class="bg-black text-white px-3 py-2 rounded hover:bg-green-600 whitespace-nowrap font-semibold">Add
                  More</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4 p-2">
        <label class="block font-semibold mb-1">Category:</label>
        <select name="category" required class="w-full  p-2 rounded bg-gray-100">
          <option value="">Select a Category</option>
          <?php $categories->data_seek(0);
          while ($category = $categories->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($category['name']) ?>"
              <?= (strtolower(trim($product['codename'])) === strtolower(trim($category['name']))) ? 'selected' : '' ?>>
              <?= htmlspecialchars($category['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>


      <!-- Quantity -->
      <div class="mb-4 p-2">
        <label class="block font-semibold mb-1">prevention duplicate always input 1:</label>
        <input type="number" name="quantity" value="<?php echo htmlspecialchars($product['quantity']); ?>" required
          class="w-full bg-gray-100 p-2 rounded" />
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Description</label>
        <textarea name="description" rows="4" class="w-full bg-gray-100 p-2 rounded"
          required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
      </div>

      <!-- ── EXTENDED DESCRIPTIONS ─────────────────────────────── -->
      <div class="border border-gray-200 rounded-xl overflow-hidden mb-6">
        <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center gap-2">
          <span class="font-semibold text-gray-700 text-sm">📝 Extended Descriptions</span>
          <span class="ml-auto text-xs text-gray-400">Fill in the detailed product info below</span>
        </div>

        <!-- Descrip1 -->
        <div class="p-5 border-b border-gray-100 border-l-4 border-l-yellow-400">
          <label class="block text-sm font-bold text-gray-800 mb-1">
            Description 1 – Complete Product Details
          </label>
          <p class="text-xs text-gray-500 mb-2">Full product info: name, size, color, materials, features, shipping,
            etc.</p>
          <textarea name="descrip1" id="update-descrip1" rows="6"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 resize-vertical text-sm"
            placeholder="Enter complete product description..."><?= htmlspecialchars($product['descrip1'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Chars: <span id="update-cnt1"
              class="font-semibold text-yellow-500">0</span></p>
        </div>

        <!-- Descrip6 -->
        <div class="p-5 border-b border-gray-100 border-l-4 border-l-indigo-400">
          <label class="block text-sm font-bold text-gray-800 mb-1">
            Description 6 – Unit Information
          </label>
          <textarea name="descrip6" id="update-descrip6" rows="4"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-vertical text-sm"
            placeholder="Enter unit information..."><?= htmlspecialchars($product['descrip6'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Chars: <span id="update-cnt6"
              class="font-semibold text-indigo-500">0</span></p>
        </div>

        <!-- Descrip7 -->
        <div class="p-5 border-l-4 border-l-cyan-400">
          <label class="block text-sm font-bold text-gray-800 mb-1">
            Description 7 – Specifications
          </label>
          <p class="text-xs text-gray-500 mb-2">e.g., Box, Set, Bundle</p>
          <textarea name="descrip7" id="update-descrip7" rows="4"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-vertical text-sm"
            placeholder="Box, Set, Bundle..."><?= htmlspecialchars($product['descrip7'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Chars: <span id="update-cnt7"
              class="font-semibold text-cyan-500">0</span></p>
        </div>
      </div>
      <!-- Product Colors -->
      <div class="mb-6 bg-white border border-gray-200 rounded-xl overflow-hidden">

        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
          <h3 class="text-sm font-semibold text-gray-700">Product Colors</h3>
          <button type="button" onclick="addColor()"
            class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
            + Add color
          </button>
        </div>

        <div id="colors-section" class="divide-y divide-gray-100">
          <?php $colorIndex = 0;
          while ($color = $colors->fetch_assoc()): ?>

            <div class="flex flex-wrap items-start gap-4 px-5 py-4 hover:bg-gray-50 transition">

              <input type="hidden" name="color_id[]" value="<?= $color['id'] ?>" />

              <!-- Color swatch + name + hex -->
              <div class="flex items-center gap-3 flex-1 min-w-48">
                <div class="flex flex-col gap-1.5 flex-1">
                  <input type="text" name="color_name[]" value="<?= htmlspecialchars($color['color_name']) ?>"
                    placeholder="Color name"
                    class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                    required />
                  <input type="text" name="color_code[]" value="<?= htmlspecialchars($color['color_code']) ?>"
                    placeholder="#hex code"
                    class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-1.5 font-mono focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                    oninput="this.previousElementSibling.previousElementSibling.style.backgroundColor = this.value || '#e5e7eb'" />
                </div>
              </div>

              <!-- Main image -->
              <div class="flex flex-col gap-1 w-32">
                <p class="text-xs font-medium text-gray-500">Main image</p>
                <?php if (!empty($color['image'])): ?>
                  <img src="<?=BASE_URL?>/<?= htmlspecialchars($color['image']) ?>"
                    class="w-12 h-12 object-contain rounded-lg border border-gray-200 bg-gray-50 mb-1" alt="Main" />
                <?php endif; ?>
                <input type="file" name="color_image[]" accept="image/*"
                  class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200" />
              </div>

              <!-- Secondary image -->
              <div class="flex flex-col gap-1 w-32">
                <p class="text-xs font-medium text-gray-500">Secondary image</p>
                <?php if (!empty($color['image2'])): ?>
                  <img src="<?=BASE_URL?>/<?= htmlspecialchars($color['image2']) ?>"
                    class="w-12 h-12 object-contain rounded-lg border border-gray-200 bg-gray-50 mb-1" alt="Secondary" />
                <?php endif; ?>
                <input type="file" name="color_image2[]" accept="image/*"
                  class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200" />
              </div>

              <!-- Delete -->
              <div class="flex flex-col items-center gap-1.5 pt-1">
                <button type="button" onclick="removeColor(this)"
                  class="text-gray-400 hover:text-red-500 transition p-1 rounded-lg hover:bg-red-50" title="Remove color">
                  <i class="fa-regular fa-circle-xmark text-lg"></i>
                </button>
                <label class="flex items-center gap-1 cursor-pointer">
                  <input type="checkbox" name="delete_color[]" value="<?= $color['id'] ?>"
                    class="w-3 h-3 accent-red-500" />
                  <span class="text-xs text-gray-400">Delete</span>
                </label>
              </div>

               <!-- Hidden fields para hindi mawala yung price at stock -->
              <input type="hidden" name="color_price[]" value="<?= htmlspecialchars($color['price'] ?? 0) ?>">
              <input type="hidden" name="color_stock[]" value="<?= htmlspecialchars($color['stock'] ?? 0) ?>">

            </div>

            <?php $colorIndex++; endwhile; ?>
        </div>

      </div>

      <!-- Product Types -->
      <div id="types-section">
        <?php
        $typeIndex = 0;
        $types->data_seek(0);
        while ($type = $types->fetch_assoc()) {
          $type_id = $type['id'];
          $variants = $conn->query("SELECT * FROM product_variants WHERE type_id = $type_id");
          ?>
          <div class="mb-6 p-4 rounded bg-gray-100 relative" data-type-index="<?php echo $typeIndex; ?>">
            <div class="flex justify-between items-center mb-2">
              <h3 class="font-semibold text-lg text-gray-700">Product Type <?php echo $typeIndex + 1; ?></h3>
              <button type="button" onclick="removeType(this)" class="text-red-600 text-sm hover:underline">Remove
                Type</button>
            </div>
            <input type="hidden" name="type_id[]" value="<?php echo $type_id; ?>" />
            <div class="flex items-center gap-2 mt-1">
              <input type="checkbox" name="delete_type[]" value="<?php echo $type_id; ?>" />
              <label class="text-sm text-gray-600">Delete Type</label>
            </div>
            <div class="mb-3 flex gap-2 items-center">
              <input type="text" name="type_name[]" value="<?php echo htmlspecialchars($type['type_name']); ?>"
                placeholder="Type Name" class="border border-gray-400 p-2 w-1/2 rounded" required />
              <div class="w-1/2">
                <?php if (!empty($type['type_image'])): ?>
                  <img src="<?=BASE_URL?>/<?php echo htmlspecialchars($type['type_image']); ?>" alt="Type Image"
                    class="w-20 h-20 object-contain rounded mb-1 border" />
                <?php endif; ?>
                <input type="file" name="type_image[]" accept="image/*" class="w-full" />
              </div>
            </div>

            <label class="block font-medium mb-1">Variants (Sizes, etc.):</label>
            <div id="variant-section-<?php echo $typeIndex; ?>">
              <?php while ($variant = $variants->fetch_assoc()) {
                $variant_id = $variant['id'];
                $variantColorQuery = "SELECT pvc.*, pc.id as color_id, pc.color_name, pc.color_code 
                                     FROM product_variant_colors pvc 
                                     JOIN product_colors pc ON pvc.color_id = pc.id 
                                     WHERE pvc.variant_id = $variant_id";
                $variantColors = $conn->query($variantColorQuery);
                ?>
                <div class="bg-gray-100 p-4 rounded border border-gray-400 mb-3">
                  <input type="hidden" name="variant_id[<?php echo $typeIndex; ?>][]"
                    value="<?php echo $variant['id']; ?>" />
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
                    <div class="flex items-center">
                      <input type="checkbox" name="delete_variant[<?php echo $typeIndex; ?>][]"
                        value="<?php echo $variant['id']; ?>" />
                      <label class="text-sm text-gray-600 ml-1">Delete</label>
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">Size Name</label>
                      <input type="text" name="variant_size[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['size']); ?>" placeholder="Size"
                        class="border border-gray-400 p-2 w-full rounded text-sm" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">Variant Name</label>
                      <input type="text" name="variant_namevariant[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['namevariant'] ?? ''); ?>" placeholder="Name Variant"
                        class="border border-gray-400 p-2 w-full rounded text-sm" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">Original Price</label>
                      <input type="number" step="0.01" name="variant_original_price[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['original_price'] ?? ''); ?>"
                        placeholder="Original Price" class="border border-gray-400 p-2 w-full rounded text-sm original-price-input"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>
                  </div>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
                    <div>
                      <label class="text-xs font-medium text-gray-600">Markup %</label>
                      <input type="number" step="0.01" name="variant_percent[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['percent'] ?? ''); ?>" placeholder="Markup %"
                        class="border border-gray-400 p-2 w-full rounded percent-input text-sm"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">After Markup</label>
                      <div class="markup-preview text-sm text-green-600 font-semibold border border-gray-400 p-2 rounded bg-white">₱0.00
                      </div>
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">Discount %</label>
                      <input type="number" step="0.01" name="variant_discount[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['discount'] ?? ''); ?>" placeholder="Discount %"
                        class="border border-gray-400 p-2 w-full rounded discount-input text-sm"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-600">Final Price</label>
                      <div class="final-preview text-sm text-red-60 0 font-semibold border border-gray-400 p-2 rounded bg-white">₱0.00</div>
                    </div>
                  </div>
                  <input type="hidden" name="variant_price[<?php echo $typeIndex; ?>][]"
                    value="<?php echo htmlspecialchars($variant['price']); ?>" class="computed-price-input" />

                  <!-- Timer Discount -->
                  <div class="bg-yellow-50 p-3 rounded mb-2 border-2 border-yellow-300">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">⏰ Timer Discount (Flash Sale)</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="text-xs font-medium text-gray-600">Timer Discount %</label>
                        <input type="number" step="0.01" name="variant_timer_discount[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['timer_discount_percent'] ?? '0'); ?>"
                          placeholder="e.g., 20" class="border border-gray-400 p-2 w-full rounded text-sm timer-discount-input"
                          data-variant-index="<?php echo $typeIndex; ?>" min="0" max="100" />
                      </div>
                      <div class="flex items-center">
                        <input type="checkbox" name="variant_timer_active[<?php echo $typeIndex; ?>][]" value="1" <?php echo (!empty($variant['timer_discount_active']) ? 'checked' : ''); ?> class="mr-2"
                          id="timer_active_<?php echo $typeIndex; ?>_<?php echo $variant['id'] ?? 'new'; ?>" />
                        <label for="timer_active_<?php echo $typeIndex; ?>_<?php echo $variant['id'] ?? 'new'; ?>"
                          class="text-sm font-medium text-gray-700">Enable Timer Discount</label>
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">Start Date & Time</label>
                        <input type="datetime-local" name="variant_timer_start[<?php echo $typeIndex; ?>][]"
                          value="<?php echo !empty($variant['timer_discount_start']) ? date('Y-m-d\TH:i', strtotime($variant['timer_discount_start'])) : ''; ?>"
                          class="border border-gray-400 p-2 w-full rounded text-sm timer-start-input"
                          data-variant-index="<?php echo $typeIndex; ?>" onchange="calculateDuration(this)" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">End Date & Time</label>
                        <input type="datetime-local" name="variant_timer_end[<?php echo $typeIndex; ?>][]"
                          value="<?php echo !empty($variant['timer_discount_end']) ? date('Y-m-d\TH:i', strtotime($variant['timer_discount_end'])) : ''; ?>"
                          class="border border-gray-400 p-2 w-full rounded text-sm timer-end-input"
                          data-variant-index="<?php echo $typeIndex; ?>" onchange="calculateDuration(this)" />
                      </div>
                    </div>
                    <div class="mt-3 p-3 bg-white rounded border-2 border-orange-300">
                      <div class="text-xs font-semibold text-gray-700 mb-2">📊 Sale Duration:</div>
                      <div class="grid grid-cols-4 gap-2 mb-3">
                        <div class="bg-blue-100 p-2 rounded border border-blue-300">
                          <div class="text-xl font-bold text-blue-600 duration-days">0</div>
                          <div class="text-xs text-gray-600 font-semibold">Days</div>
                        </div>
                        <div class="bg-green-100 p-2 rounded border border-green-300">
                          <div class="text-xl font-bold text-green-600 duration-hours">0</div>
                          <div class="text-xs text-gray-600 font-semibold">Hours</div>
                        </div>
                        <div class="bg-purple-100 p-2 rounded border border-purple-300">
                          <div class="text-xl font-bold text-purple-600 duration-minutes">0</div>
                          <div class="text-xs text-gray-600 font-semibold">Minutes</div>
                        </div>
                        <div class="bg-pink-100 p-2 rounded border border-pink-300">
                          <div class="text-xl font-bold text-pink-600 duration-seconds">0</div>
                          <div class="text-xs text-gray-600 font-semibold">Seconds</div>
                        </div>
                      </div>
                      <div class="p-2 bg-orange-100 rounded border border-orange-300">
                        <div class="text-xs font-semibold text-gray-700">📅 Total Duration:</div>
                        <div class="text-sm font-semibold text-orange-600 mt-1 duration-total">Set start and end dates</div>
                        <div class="text-xs text-red-500 mt-1 duration-warning" style="display:none;"></div>
                      </div>
                    </div>
                    <div class="mt-2 p-2 bg-white rounded border">
                      <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-600">Price after timer discount:</span>
                        <span class="timer-final-preview text-sm font-bold text-orange-600">₱0.00</span>
                      </div>
                    </div>
                  </div>

                  <!-- Dimensions -->
                  <div class="bg-white p-3 rounded mb-2">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                      <div><label class="text-xs font-medium text-gray-600">Width</label><input type="number" step="0.01"
                          name="variant_width[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['width'] ?? ''); ?>" placeholder="Width"
                          class="border border-gray-400 p-2 w-full rounded text-sm" /></div>
                      <div><label class="text-xs font-medium text-gray-600">Height</label><input type="number" step="0.01"
                          name="variant_height[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['height'] ?? ''); ?>" placeholder="Height"
                          class="border border-gray-400 p-2 w-full rounded text-sm" /></div>
                      <div><label class="text-xs font-medium text-gray-600">Length</label><input type="number" step="0.01"
                          name="variant_length[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['length'] ?? ''); ?>" placeholder="Length"
                          class="border border-gray-400 p-2 w-full rounded text-sm" /></div>
                      <div><label class="text-xs font-medium text-gray-600">Unit</label>
                        <select name="variant_dimension_unit[<?php echo $typeIndex; ?>][]"
                          class="border border-gray-400 p-2 w-full rounded text-sm">
                          <option value="mm" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'mm' ? 'selected' : ''; ?>>mm
                          </option>
                          <option value="cm" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'cm' ? 'selected' : ''; ?>>cm
                          </option>
                          <option value="inches" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'inches' ? 'selected' : ''; ?>>inches</option>
                          <option value="m" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'm' ? 'selected' : ''; ?>>m
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Weight -->
                  <div class="bg-white p-3 rounded mb-2">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">⚖️ Weight</label>
                    <div class="grid grid-cols-2 gap-2">
                      <div><label class="text-xs font-medium text-gray-600">Weight</label><input type="number" step="0.01"
                          name="variant_weight[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['weight'] ?? ''); ?>" placeholder="Weight"
                          class="border border-gray-400 p-2 w-full rounded text-sm" /></div>
                      <div><label class="text-xs font-medium text-gray-600">Unit</label>
                        <select name="variant_weight_unit[<?php echo $typeIndex; ?>][]"
                          class="border border-gray-400 p-2 w-full rounded text-sm">
                          <option value="g" <?php echo ($variant['weight_unit'] ?? 'kg') == 'g' ? 'selected' : ''; ?>>g
                            (Grams)</option>
                          <option value="kg" <?php echo ($variant['weight_unit'] ?? 'kg') == 'kg' ? 'selected' : ''; ?>>kg
                            (Kilograms)</option>
                          <option value="lbs" <?php echo ($variant['weight_unit'] ?? 'kg') == 'lbs' ? 'selected' : ''; ?>>lbs
                            (Pounds)</option>
                          <option value="oz" <?php echo ($variant['weight_unit'] ?? 'kg') == 'oz' ? 'selected' : ''; ?>>oz
                            (Ounces)</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Variant Colors -->
                  <div class="bg-white p-3 rounded mb-2 border-2 border-purple-200">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">🎨 Available Colors & Stock for this
                      Variant</label>
                    <div id="variant-colors-section-<?php echo $typeIndex; ?>-<?php echo $variant_id; ?>"
                      class="space-y-2 mb-3">
                      <?php if ($variantColors && $variantColors->num_rows > 0):
                        while ($vc = $variantColors->fetch_assoc()): ?>
                          <div class="flex gap-2 items-center bg-gray-50 p-2 rounded border variant-color-item">
                            <input type="hidden"
                              name="variant_color_id[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                              value="<?php echo $vc['id']; ?>" />
                            <div class="flex items-center">
                              <input type="checkbox"
                                name="delete_variant_color[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                                value="<?php echo $vc['id']; ?>" />
                              <label class="text-xs text-gray-600 ml-1">Delete</label>
                            </div>
                            <div class="w-8 h-8 rounded border"
                              style="background-color: <?php echo htmlspecialchars($vc['color_code']); ?>"
                              title="<?php echo htmlspecialchars($vc['color_name']); ?>"></div>
                            <span
                              class="text-sm font-medium text-gray-700 min-w-24"><?php echo htmlspecialchars($vc['color_name']); ?></span>
                            <input type="number"
                              name="variant_color_stock[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                              value="<?php echo (int) $vc['stock_quantity']; ?>" placeholder="Stock"
                              class="border p-2 w-24 rounded text-sm font-semibold" min="0" required />
                          </div>
                        <?php endwhile; endif; ?>
                    </div>
                    <div class="pt-2 border-t">
                      <p class="text-xs text-gray-600 mb-2">Add color options to this variant:</p>
                      <div id="new-variant-colors-<?php echo $typeIndex; ?>-<?php echo $variant_id; ?>" class="space-y-2">
                        <div class="flex gap-2 items-center">
                          <?php
                          $assignedColorsQuery = "SELECT color_id FROM product_variant_colors WHERE variant_id = $variant_id";
                          $assignedColorsResult = $conn->query($assignedColorsQuery);
                          $assignedColorIds = [];
                          while ($ac = $assignedColorsResult->fetch_assoc()) {
                            $assignedColorIds[] = $ac['color_id'];
                          }
                          ?>
                          <select name="new_variant_color[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                            class="border p-2 rounded text-sm flex-1">
                            <option value="">-- Select a Color --</option>
                            <?php $allColors = $conn->query("SELECT id, color_name, color_code FROM product_colors WHERE product_id = $product_id");
                            while ($c = $allColors->fetch_assoc()):
                              if (!in_array($c['id'], $assignedColorIds)): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['color_name']); ?>
                                </option>
                              <?php endif; endwhile; ?>
                          </select>
                          <input type="number"
                            name="new_variant_color_stock[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                            placeholder="Stock qty" value="0" class="border p-2 w-32 rounded text-sm font-semibold" min="0"
                            required />
                          <button type="button"
                            onclick="addColorToVariant(<?php echo $typeIndex; ?>, <?php echo $variant_id; ?>)"
                            class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600 whitespace-nowrap">+
                            Add</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="flex justify-end">
                    <button type="button" onclick="removeVariant(this)"
                      class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button>
                  </div>
                </div>
              <?php } ?>
            </div>
            <button type="button" onclick="addVariant(<?php echo $typeIndex; ?>)" class="text-sm text-blue-600 mt-1">+ Add
              Variant</button>
          </div>
          <?php $typeIndex++;
        } ?>
      </div>

      <div class="mt-4 flex gap-2">
        <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">Update
          Product</button>
        <button type="button" onclick="addType()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+
          Add Type</button>
      </div>
    </form>
  </div>

  <script>
    // ── CHAR COUNTERS FOR EXTENDED DESCRIPTIONS ─────────────────
    ['1', '6', '7'].forEach(n => {
      const ta = document.getElementById('update-descrip' + n);
      const sp = document.getElementById('update-cnt' + n);
      if (ta && sp) {
        sp.textContent = ta.value.length;
        ta.addEventListener('input', () => sp.textContent = ta.value.length);
      }
    });

    function calculatePrices(container) {
      const originalPrice = parseFloat(container.querySelector('.original-price-input')?.value) || 0;
      const markup = parseFloat(container.querySelector('.percent-input')?.value) || 0;
      const discount = parseFloat(container.querySelector('.discount-input')?.value) || 0;
      const timerDiscount = parseFloat(container.querySelector('.timer-discount-input')?.value) || 0;
      const markupDisplay = container.querySelector('.markup-preview');
      const finalDisplay = container.querySelector('.final-preview');
      const timerFinalDisplay = container.querySelector('.timer-final-preview');
      const computedPriceInput = container.querySelector('.computed-price-input');
      if (originalPrice > 0) {
        const afterMarkup = originalPrice + (originalPrice * markup / 100);
        if (markupDisplay) markupDisplay.textContent = '₱' + afterMarkup.toFixed(2);
        const afterDiscount = afterMarkup - (afterMarkup * discount / 100);
        if (finalDisplay) finalDisplay.textContent = '₱' + afterDiscount.toFixed(2);
        if (computedPriceInput) computedPriceInput.value = afterDiscount.toFixed(2);
        const afterTimerDiscount = afterDiscount - (afterDiscount * timerDiscount / 100);
        if (timerFinalDisplay) { timerFinalDisplay.textContent = '₱' + afterTimerDiscount.toFixed(2); if (timerDiscount > 0) { timerFinalDisplay.classList.add('animate-pulse'); } else { timerFinalDisplay.classList.remove('animate-pulse'); } }
      } else {
        if (markupDisplay) markupDisplay.textContent = '₱0.00';
        if (finalDisplay) finalDisplay.textContent = '₱0.00';
        if (timerFinalDisplay) timerFinalDisplay.textContent = '₱0.00';
        if (computedPriceInput) computedPriceInput.value = '0';
      }
    }

    let typeIndex = <?php echo $typeIndex; ?>;

    function removeExistingSubImage(button, imageIndex) {
      const imageItem = button.closest('.sub-image-item');
      const keepInput = imageItem.querySelector('input[name*="keep_sub_image"]');
      if (confirm('Are you sure you want to remove this sub image?')) {
        keepInput.value = '0';
        imageItem.classList.add('marked-for-deletion');
        if (!imageItem.querySelector('.deletion-notice')) {
          const notice = document.createElement('div');
          notice.className = 'deletion-notice text-xs text-red-600 text-center mt-1 font-semibold';
          notice.textContent = 'Will be deleted on save';
          imageItem.appendChild(notice);
        }
      }
    }

    function restoreExistingSubImage(button, imageIndex) {
      const imageItem = button.closest('.sub-image-item');
      const keepInput = imageItem.querySelector('input[name*="keep_sub_image"]');
      keepInput.value = '1';
      imageItem.classList.remove('marked-for-deletion');
      const notice = imageItem.querySelector('.deletion-notice');
      if (notice) notice.remove();
    }

    function previewNewSubImage(input) {
      const preview = input.parentElement.querySelector('.new-sub-image-preview');
      preview.innerHTML = '';
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) { const img = document.createElement('img'); img.src = e.target.result; img.classList.add('image-preview', 'border'); preview.appendChild(img); };
        reader.readAsDataURL(input.files[0]);
      }
    }

    function addNewSubImage() {
      const section = document.getElementById('new-sub-images-section');
      const div = document.createElement('div');
      div.classList.add('new-sub-image-item', 'flex', 'gap-2', 'mb-1', 'items-start', 'p-1', 'bg-white', 'rounded', 'border');
      div.innerHTML = `<div class="flex-1"><input type="file" name="new_sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewNewSubImage(this)" /><div class="new-sub-image-preview mt-2"></div></div><button type="button" onclick="removeNewSubImage(this)" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 whitespace-nowrap">Remove</button>`;
      section.appendChild(div);
    }

    function removeNewSubImage(button) {
      const items = document.querySelectorAll('.new-sub-image-item');
      if (items.length > 1) { button.closest('.new-sub-image-item').remove(); }
      else { alert('At least one new sub image input must remain.'); }
    }

    function removeType(button) { button.closest('[data-type-index]').remove(); }
    function removeVariant(button) { button.closest('.bg-blue-50').remove(); }
    function removeColor(button) { button.closest('.flex.gap-2').remove(); }

    function addColor() {
      const colorsSection = document.getElementById('colors-section');
      const div = document.createElement('div');
      div.className = 'flex gap-2 mb-3 items-center bg-white p-3 rounded border';
      div.innerHTML = `<input type="hidden" name="color_id[]" value="new" /><div class="flex items-center"><span class="text-sm text-gray-400 w-[50px]">New</span></div><input type="text" name="color_name[]" placeholder="Color Name" class="border p-2 w-1/6 rounded" required /><input type="text" name="color_code[]" placeholder="Color Code (#hex)" class="border p-2 w-1/6 rounded" /><div class="w-1/6"><input type="file" name="color_image[]" accept="image/*" class="w-full text-xs" /><p class="text-xs text-gray-500 mt-1">Main Image</p></div><div class="w-1/6"><input type="file" name="color_image2[]" accept="image/*" class="w-full text-xs" /><p class="text-xs text-gray-500 mt-1">Secondary Image</p></div><input type="number" step="0.01" name="color_price[]" placeholder="Color Price" class="border p-2 w-1/6 rounded" /><input type="number" name="color_stock[]" placeholder="Stock" value="0" class="border p-2 w-1/6 rounded"  /><button type="button" onclick="removeColor(this)" class="text-red-500 text-sm">✕</button>`;
      colorsSection.appendChild(div);
    }

    function addColorToVariant(typeIndex, variantId) {
      const section = document.getElementById(`new-variant-colors-${typeIndex}-${variantId}`);
      const div = document.createElement('div');
      div.className = 'flex gap-2 items-center';
      const firstSelect = document.querySelector(`select[name="new_variant_color[${typeIndex}][${variantId}][]"]`);
      const colorOptions = Array.from(firstSelect.options).map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('');
      div.innerHTML = `<select name="new_variant_color[${typeIndex}][${variantId}][]" class="border p-2 rounded text-sm flex-1"><option value="">-- Select a Color --</option>${colorOptions}</select><input type="number" name="new_variant_color_stock[${typeIndex}][${variantId}][]" placeholder="Stock qty" value="0" class="border p-2 w-32 rounded text-sm font-semibold" min="0" required /><button type="button" onclick="removeColorFromVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove</button>`;
      section.appendChild(div);
    }

    function removeColorFromVariant(button) { button.closest('div').remove(); }

    function addVariant(index) {
      const variantSection = document.getElementById('variant-section-' + index);
      const div = document.createElement('div');
      div.classList.add('bg-blue-50', 'p-4', 'rounded', 'border', 'mb-3');
      div.innerHTML = `<input type="hidden" name="variant_id[${index}][]" value="new" /><div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2"><div class="flex items-center"><span class="text-sm text-gray-400">New</span></div><div><label class="text-xs font-medium text-gray-600">Size/Type</label><input type="text" name="variant_size[${index}][]" placeholder="Size" class="border p-2 w-full rounded text-sm" required /></div><div><label class="text-xs font-medium text-gray-600">Variant Name</label><input type="text" name="variant_namevariant[${index}][]" placeholder="Name Variant" class="border p-2 w-full rounded text-sm" /></div><div><label class="text-xs font-medium text-gray-600">Original Price</label><input type="number" step="0.01" name="variant_original_price[${index}][]" placeholder="Original Price" class="border p-2 w-full rounded text-sm original-price-input" data-variant-index="${index}" required /></div></div><div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2"><div><label class="text-xs font-medium text-gray-600">Markup %</label><input type="number" step="0.01" name="variant_percent[${index}][]" placeholder="Markup %" class="border p-2 w-full rounded percent-input text-sm" data-variant-index="${index}" /></div><div><label class="text-xs font-medium text-gray-600">After Markup</label><div class="markup-preview text-sm text-green-600 font-semibold border p-2 rounded bg-white">₱0.00</div></div><div><label class="text-xs font-medium text-gray-600">Discount %</label><input type="number" step="0.01" name="variant_discount[${index}][]" placeholder="Discount %" class="border p-2 w-full rounded discount-input text-sm" data-variant-index="${index}" /></div><div><label class="text-xs font-medium text-gray-600">Final Price</label><div class="final-preview text-sm text-red-600 font-semibold border p-2 rounded bg-white">₱0.00</div></div></div><input type="hidden" name="variant_price[${index}][]" value="0" class="computed-price-input" /><div class="bg-white p-3 rounded mb-2"><label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label><div class="grid grid-cols-2 md:grid-cols-4 gap-2"><div><label class="text-xs font-medium text-gray-600">Width</label><input type="number" step="0.01" name="variant_width[${index}][]" placeholder="Width" class="border p-2 w-full rounded text-sm" /></div><div><label class="text-xs font-medium text-gray-600">Height</label><input type="number" step="0.01" name="variant_height[${index}][]" placeholder="Height" class="border p-2 w-full rounded text-sm" /></div><div><label class="text-xs font-medium text-gray-600">Length</label><input type="number" step="0.01" name="variant_length[${index}][]" placeholder="Length" class="border p-2 w-full rounded text-sm" /></div><div><label class="text-xs font-medium text-gray-600">Unit</label><select name="variant_dimension_unit[${index}][]" class="border p-2 w-full rounded text-sm"><option value="mm">mm</option><option value="cm" selected>cm</option><option value="inches">inches</option><option value="m">m</option></select></div></div></div><div class="bg-white p-3 rounded mb-2"><label class="text-xs font-semibold text-gray-700 block mb-2">⚖️ Weight</label><div class="grid grid-cols-2 gap-2"><div><label class="text-xs font-medium text-gray-600">Weight</label><input type="number" step="0.01" name="variant_weight[${index}][]" placeholder="Weight" class="border p-2 w-full rounded text-sm" /></div><div><label class="text-xs font-medium text-gray-600">Unit</label><select name="variant_weight_unit[${index}][]" class="border p-2 w-full rounded text-sm"><option value="g">g (Grams)</option><option value="kg" selected>kg (Kilograms)</option><option value="lbs">lbs (Pounds)</option><option value="oz">oz (Ounces)</option></select></div></div></div><div class="flex justify-end"><button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button></div>`;
      variantSection.appendChild(div);
      const op = div.querySelector('.original-price-input'), pct = div.querySelector('.percent-input'), disc = div.querySelector('.discount-input');
      const hook = () => applyMarkup(op, pct, disc, div.querySelector('.markup-preview'), div.querySelector('.final-preview'), div.querySelector('.computed-price-input'));
      op.addEventListener('input', hook); pct.addEventListener('input', hook); disc.addEventListener('input', hook);
    }

    function addType() {
      const typesSection = document.getElementById('types-section');
      const div = document.createElement('div');
      div.className = 'mb-6 border p-4 rounded bg-gray-50 relative';
      div.setAttribute('data-type-index', typeIndex);
      div.innerHTML = `<div class="flex justify-between items-center mb-2"><h3 class="font-semibold text-lg text-gray-700">Product Type ${typeIndex + 1}</h3><button type="button" onclick="removeType(this)" class="text-red-600 text-sm hover:underline">Remove Type</button></div><input type="hidden" name="type_id[]" value="new" /><div class="mb-3 flex gap-2 items-center"><input type="text" name="type_name[]" placeholder="Type Name" class="border p-2 w-1/2 rounded" required /><input type="file" name="type_image[]" accept="image/*" class="w-1/2" /></div><label class="block font-medium mb-1">Variants (Sizes, etc.):</label><div id="variant-section-${typeIndex}"></div><button type="button" onclick="addVariant(${typeIndex})" class="text-sm text-blue-600 mt-1">+ Add Variant</button>`;
      typesSection.appendChild(div);
      typeIndex++;
    }

    function applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput) {
      const container = originalPriceInput.closest('.bg-blue-50');
      const timerDiscountInput = container ? container.querySelector('.timer-discount-input') : null;
      const timerCheckbox = container ? container.querySelector('input[name*="timer_active"]') : null;
      const originalPrice = parseFloat(originalPriceInput.value) || 0;
      const percent = parseFloat(percentInput.value) || 0;
      const discount = parseFloat(discountInput.value) || 0;
      const timerDiscount = (timerCheckbox && timerCheckbox.checked && timerDiscountInput) ? (parseFloat(timerDiscountInput.value) || 0) : 0;
      if (originalPrice > 0) {
        const priceAfterMarkup = originalPrice + (originalPrice * percent / 100);
        markupDisplay.textContent = '₱' + priceAfterMarkup.toFixed(2);
        const priceAfterDiscount = priceAfterMarkup - (priceAfterMarkup * discount / 100);
        const finalPrice = priceAfterDiscount - (priceAfterDiscount * timerDiscount / 100);
        finalDisplay.textContent = '₱' + finalPrice.toFixed(2);
        if (computedPriceInput) computedPriceInput.value = finalPrice.toFixed(2);
        if (timerDiscount > 0 && timerCheckbox && timerCheckbox.checked) { finalDisplay.classList.add('text-orange-600', 'font-bold'); } else { finalDisplay.classList.remove('text-orange-600', 'font-bold'); }
      } else {
        markupDisplay.textContent = '₱0.00'; finalDisplay.textContent = '₱0.00';
        if (computedPriceInput) computedPriceInput.value = '0';
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.bg-blue-50.p-4.rounded.border.mb-3').forEach((variantDiv) => {
        const op = variantDiv.querySelector('.original-price-input'), pct = variantDiv.querySelector('.percent-input'), disc = variantDiv.querySelector('.discount-input'), timerDisc = variantDiv.querySelector('.timer-discount-input'), timerCb = variantDiv.querySelector('input[name*="timer_active"]'), mkp = variantDiv.querySelector('.markup-preview'), fin = variantDiv.querySelector('.final-preview'), comp = variantDiv.querySelector('.computed-price-input');
        if (op && pct && mkp && fin && comp) {
          const hook = () => applyMarkup(op, pct, disc, mkp, fin, comp);
          op.addEventListener('input', hook); pct.addEventListener('input', hook);
          if (disc) disc.addEventListener('input', hook);
          if (timerDisc) timerDisc.addEventListener('input', hook);
          if (timerCb) timerCb.addEventListener('change', hook);
          hook();
        }
      });
      document.querySelectorAll('.timer-start-input').forEach(input => { calculateDuration(input); });
      setTimeout(() => { monitorAllTimerDiscounts(); }, 500);
    });

    function calculateDuration(input) {
      const container = input.closest('.bg-yellow-50');
      const startInput = container.querySelector('.timer-start-input');
      const endInput = container.querySelector('.timer-end-input');
      const durationDays = container.querySelector('.duration-days'), durationHours = container.querySelector('.duration-hours'), durationMinutes = container.querySelector('.duration-minutes'), durationSeconds = container.querySelector('.duration-seconds'), durationTotal = container.querySelector('.duration-total'), durationWarning = container.querySelector('.duration-warning');
      if (!startInput.value || !endInput.value) { durationDays.textContent = durationHours.textContent = durationMinutes.textContent = durationSeconds.textContent = '0'; durationTotal.textContent = 'Set start and end dates'; durationWarning.style.display = 'none'; return; }
      const startDate = new Date(startInput.value), endDate = new Date(endInput.value);
      if (endDate <= startDate) { durationDays.textContent = durationHours.textContent = durationMinutes.textContent = durationSeconds.textContent = '0'; durationTotal.textContent = '❌ End time must be after start time'; durationWarning.style.display = 'block'; durationWarning.textContent = 'Adjust your end date/time'; return; }
      const diffSecs = Math.floor((endDate - startDate) / 1000);
      const days = Math.floor(diffSecs / 86400), hours = Math.floor((diffSecs % 86400) / 3600), minutes = Math.floor((diffSecs % 3600) / 60), seconds = diffSecs % 60;
      durationDays.textContent = days; durationHours.textContent = hours; durationMinutes.textContent = minutes; durationSeconds.textContent = seconds;
      let txt = []; if (days > 0) txt.push(`${days} day${days > 1 ? 's' : ''}`); if (hours > 0) txt.push(`${hours} hour${hours > 1 ? 's' : ''}`); if (minutes > 0) txt.push(`${minutes} minute${minutes > 1 ? 's' : ''}`); if (seconds > 0 && days === 0 && hours === 0) txt.push(`${seconds} second${seconds > 1 ? 's' : ''}`);
      durationTotal.textContent = '✅ ' + (txt.length > 0 ? txt.join(', ') : 'Less than a second'); durationWarning.style.display = 'none';
    }

    function monitorAllTimerDiscounts() {
      const timerContainers = document.querySelectorAll('.bg-yellow-50');
      const monitoredVariants = new Map();
      timerContainers.forEach((container, index) => {
        const startInput = container.querySelector('.timer-start-input'), endInput = container.querySelector('.timer-end-input'), timerCheckbox = container.querySelector('input[name*="timer_active"]'), variantContainer = container.closest('.bg-blue-50'), variantIdInput = variantContainer ? variantContainer.querySelector('input[name*="variant_id"]') : null;
        if (!startInput || !endInput || !timerCheckbox || !variantContainer) return;
        const variantId = variantIdInput ? variantIdInput.value : null;
        if (!variantId || variantId === 'new') return;
        monitoredVariants.set(variantId, { container, variantContainer, startInput, endInput, timerCheckbox, isExpired: false });
      });
      setInterval(() => {
        monitoredVariants.forEach((data, variantId) => {
          const { container, timerCheckbox, startInput, endInput, variantContainer } = data;
          if (!startInput.value || !endInput.value) return;
          try {
            const endDate = new Date(endInput.value), now = new Date();
            if (now >= endDate && timerCheckbox.checked && !data.isExpired) {
              data.isExpired = true; timerCheckbox.checked = false;
              const fd = new FormData(); fd.append('variant_id', variantId); fd.append('action', 'deactivate');
              fetch('main-update-auto_deactivate_timer-page-2-A.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) { recalculatePricesForVariant(variantContainer); showTimerExpiredNotice(container); } }).catch(e => console.error(e));
            }
            if (timerCheckbox.checked && now < endDate) data.isExpired = false;
          } catch (e) { }
        });
      }, 5000);
    }

    function recalculatePricesForVariant(variantContainer) {
      const op = variantContainer.querySelector('.original-price-input'), pct = variantContainer.querySelector('.percent-input'), disc = variantContainer.querySelector('.discount-input'), mkp = variantContainer.querySelector('.markup-preview'), fin = variantContainer.querySelector('.final-preview'), comp = variantContainer.querySelector('.computed-price-input');
      if (op && pct && mkp && fin) applyMarkup(op, pct, disc, mkp, fin, comp);
    }

    function showTimerExpiredNotice(container) {
      container.style.opacity = '0.7'; container.style.borderColor = '#f87171'; container.style.backgroundColor = '#fef2f2';
      const dw = container.querySelector('.duration-warning');
      if (dw) { dw.style.display = 'block'; dw.innerHTML = `⏰ <span style="color:#ef4444;font-weight:bold;">✅ EXPIRED - Timer discount auto-deactivated.</span>`; }
    }
  </script>
</body>

</html>