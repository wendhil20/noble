<?php
//update_product.php - UPDATED WITH STOCK HANDLING
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

$_SESSION['last_activity'] = time();

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
  echo "Missing product ID.";
  exit;
}

// Fetch product with sub_images
$product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
$types = $conn->query("SELECT * FROM product_types WHERE product_id = $product_id");
$colors = $conn->query("SELECT * FROM product_colors WHERE product_id = $product_id");

// Fetch all categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Process existing sub images
$existing_sub_images = [];
if (!empty($product['sub_images'])) {
  $decoded_sub_images = json_decode($product['sub_images'], true);
  if (is_array($decoded_sub_images)) {
    $existing_sub_images = $decoded_sub_images;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Update Product</title>
  <script src="https://cdn.tailwindcss.com"></script>
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

  <?php include '../navbar/top.php'; ?>

  <div class="bg-white p-6 rounded-lg shadow mt-5 max-w-7xl mx-auto">
    <h2 class="text-2xl font-bold mb-4 text-orange-600">Update Product</h2>

    <form action="update_process-page-2-A.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="product_id" value="<?php echo $product_id; ?>" />

      <!-- Product Info -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Product Name</label>
        <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required class="w-full border p-2 rounded" />
      </div>

      <!-- Main Image Section -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Main Image</label>
        <?php if (!empty($product['main_image'])): ?>
          <div class="mb-2">
            <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
              class="h-20 w-20 object-contain rounded border"
              alt="Current Main Image">
            <p class="text-sm text-gray-600 mt-1">Current main image</p>
          </div>
        <?php endif; ?>
        <input type="file" name="main_image" accept="image/*" class="w-full border p-2 rounded" />
        <p class="text-xs text-gray-500 mt-1">Leave blank to keep current image</p>
      </div>

      <!-- Sub Images Section -->
      <div class="mb-6">
        <label class="block font-semibold mb-2">Sub Images</label>
        <div class="bg-gray-50 p-4 rounded border">

          <!-- Existing Sub Images -->
          <?php if (!empty($existing_sub_images)): ?>
            <div class="mb-4">
              <h4 class="font-medium text-gray-700 mb-2">Current Sub Images:</h4>
              <div class="flex flex-wrap gap-3" id="existing-sub-images">
                <?php foreach ($existing_sub_images as $index => $sub_image): ?>
                  <div class="sub-image-item" data-image-index="<?= $index ?>">
                    <img src="../../sub_images/<?= htmlspecialchars($sub_image) ?>"
                      class="image-preview border"
                      alt="Sub Image <?= $index + 1 ?>">
                    <button type="button"
                      class="remove-sub-image"
                      onclick="removeExistingSubImage(this, <?= $index ?>)"
                      title="Remove this image">×</button>
                    <button type="button"
                      class="restore-sub-image"
                      onclick="restoreExistingSubImage(this, <?= $index ?>)"
                      title="Restore this image">↻</button>
                    <input type="hidden" name="keep_sub_image[<?= $index ?>]" value="1">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- New Sub Images -->
          <div class="mb-4">
            <h4 class="font-medium text-gray-700 mb-2">Add New Sub Images:</h4>
            <div id="new-sub-images-section">
              <div class="new-sub-image-item flex gap-2 mb-3 items-start p-3 bg-white rounded border">
                <div class="flex-1">
                  <input type="file" name="new_sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewNewSubImage(this)" />
                  <div class="new-sub-image-preview mt-2"></div>
                </div>
                <button type="button" onclick="addNewSubImage()" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 whitespace-nowrap">
                  + Add More
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Dropdown -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Category</label>
        <select name="category" required class="w-full border p-2 rounded bg-white">
          <option value="">Select a Category</option>
          <?php
          $categories->data_seek(0);
          while ($category = $categories->fetch_assoc()):
          ?>
            <option value="<?= htmlspecialchars($category['name']) ?>"
              <?= (isset($product['codename']) && $product['codename'] == $category['name']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($category['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="mb-4">
        <label class="block font-semibold mb-1">Quantity</label>
        <input type="number" name="quantity" value="<?php echo htmlspecialchars($product['quantity']); ?>" required class="w-full border p-2 rounded" />
      </div>

      <div class="mb-4">
        <label class="block font-semibold mb-1">Description</label>
        <textarea name="description" rows="4" class="w-full border p-2 rounded" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
      </div>

      <!-- UPDATED COLOR SECTION in update_product-page-2-A.php -->
      <!-- Product Colors Section -->
      <div class="mb-6 border p-4 rounded bg-green-50">
        <h3 class="font-semibold text-lg text-gray-700 mb-3">Product Colors</h3>
        <div id="colors-section">
          <?php
          $colorIndex = 0;
          while ($color = $colors->fetch_assoc()) { ?>
            <div class="flex gap-2 mb-3 items-center bg-white p-3 rounded border">
              <input type="hidden" name="color_id[]" value="<?php echo $color['id']; ?>" />

              <!-- Delete checkbox -->
              <div class="flex items-center">
                <input type="checkbox" name="delete_color[]" value="<?php echo $color['id']; ?>" />
                <label class="text-sm text-gray-600 ml-1">Delete</label>
              </div>

              <!-- Color Name -->
              <input type="text" name="color_name[]" value="<?php echo htmlspecialchars($color['color_name']); ?>" placeholder="Color Name" class="border p-2 w-1/6 rounded" required />

              <!-- Color Code -->
              <input type="text" name="color_code[]" value="<?php echo htmlspecialchars($color['color_code']); ?>" placeholder="Color Code (#hex)" class="border p-2 w-1/6 rounded" />

              <!-- Image 1 -->
              <div class="w-1/6">
                <?php if (!empty($color['image'])): ?>
                  <img src="../../<?= htmlspecialchars($color['image']) ?>" alt="Color Image" class="w-12 h-12 object-contain rounded mb-1 border" />
                <?php endif; ?>
                <input type="file" name="color_image[]" accept="image/*" class="w-full text-xs" />
                <p class="text-xs text-gray-500 mt-1">Main Image</p>
              </div>

              <!-- Image 2 (NEW) -->
              <div class="w-1/6">
                <?php if (!empty($color['image2'])): ?>
                  <img src="../../<?= htmlspecialchars($color['image2']) ?>" alt="Color Image 2" class="w-12 h-12 object-contain rounded mb-1 border" />
                <?php endif; ?>
                <input type="file" name="color_image2[]" accept="image/*" class="w-full text-xs" />
                <p class="text-xs text-gray-500 mt-1">Secondary Image</p>
              </div>

              <!-- Price -->
              <input type="number" step="0.01" name="color_price[]" value="<?php echo htmlspecialchars($color['price']); ?>" placeholder="Color Price" class="border p-2 w-1/6 rounded" required />

              <!-- Stock -->
              <input type="number" name="color_stock[]" value="<?php echo htmlspecialchars($color['stock'] ?? 0); ?>" placeholder="Stock" class="border p-2 w-1/6 rounded" required />

              <!-- Remove Button -->
              <button type="button" onclick="removeColor(this)" class="text-red-500 text-sm">✕</button>
            </div>
          <?php
            $colorIndex++;
          } ?>
        </div>
        <button type="button" onclick="addColor()" class="bg-green-500 text-white px-2 py-1 rounded text-sm mt-2">+ Add Color</button>
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
          <div class="mb-6 border p-4 rounded bg-gray-50 relative" data-type-index="<?php echo $typeIndex; ?>">
            <div class="flex justify-between items-center mb-2">
              <h3 class="font-semibold text-lg text-gray-700">Product Type <?php echo $typeIndex + 1; ?></h3>
              <button type="button" onclick="removeType(this)" class="text-red-600 text-sm hover:underline">Remove Type</button>
            </div>

            <input type="hidden" name="type_id[]" value="<?php echo $type_id; ?>" />

            <div class="flex items-center gap-2 mt-1">
              <input type="checkbox" name="delete_type[]" value="<?php echo $type_id; ?>" />
              <label class="text-sm text-gray-600">Delete Type</label>
            </div>

            <div class="mb-3 flex gap-2 items-center">
              <input type="text" name="type_name[]" value="<?php echo htmlspecialchars($type['type_name']); ?>" placeholder="Type Name" class="border p-2 w-1/2 rounded" required />

              <div class="w-1/2">
                <?php if (!empty($type['type_image'])): ?>
                  <img src="../../<?php echo htmlspecialchars($type['type_image']); ?>"
                    alt="Type Image"
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
                <div class="bg-blue-50 p-4 rounded border mb-3">
                  <input type="hidden" name="variant_id[<?php echo $typeIndex; ?>][]" value="<?php echo $variant['id']; ?>" />

                  <!-- Row 1: Delete & Basic Info -->
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
                    <div class="flex items-center">
                      <input type="checkbox" name="delete_variant[<?php echo $typeIndex; ?>][]" value="<?php echo $variant['id']; ?>" />
                      <label class="text-sm text-gray-600 ml-1">Delete</label>
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">Size Name</label>
                      <input type="text" name="variant_size[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['size']); ?>"
                        placeholder="Size" class="border p-2 w-full rounded text-sm" />
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">Variant Name</label>
                      <input type="text" name="variant_namevariant[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['namevariant'] ?? ''); ?>"
                        placeholder="Name Variant" class="border p-2 w-full rounded text-sm" />
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">Original Price</label>
                      <input type="number" step="0.01"
                        name="variant_original_price[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['original_price'] ?? ''); ?>"
                        placeholder="Original Price"
                        class="border p-2 w-full rounded text-sm original-price-input"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>
                  </div>

                  <!-- Row 2: Pricing Calculations -->
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
                    <div>
                      <label class="text-xs font-medium text-gray-600">Markup %</label>
                      <input type="number" step="0.01"
                        name="variant_percent[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['percent'] ?? ''); ?>"
                        placeholder="Markup %"
                        class="border p-2 w-full rounded percent-input text-sm"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">After Markup</label>
                      <div class="markup-preview text-sm text-green-600 font-semibold border p-2 rounded bg-white">₱0.00</div>
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">Discount %</label>
                      <input type="number" step="0.01"
                        name="variant_discount[<?php echo $typeIndex; ?>][]"
                        value="<?php echo htmlspecialchars($variant['discount'] ?? ''); ?>"
                        placeholder="Discount %"
                        class="border p-2 w-full rounded discount-input text-sm"
                        data-variant-index="<?php echo $typeIndex; ?>" />
                    </div>

                    <div>
                      <label class="text-xs font-medium text-gray-600">Final Price</label>
                      <div class="final-preview text-sm text-red-600 font-semibold border p-2 rounded bg-white">₱0.00</div>
                    </div>
                  </div>

                  <input type="hidden"
                    name="variant_price[<?php echo $typeIndex; ?>][]"
                    value="<?php echo htmlspecialchars($variant['price']); ?>"
                    class="computed-price-input" />


                  <!-- Timer Discount Section WITH DURATION DISPLAY -->
<div class="bg-yellow-50 p-3 rounded mb-2 border-2 border-yellow-300">
  <label class="text-xs font-semibold text-gray-700 block mb-2">⏰ Timer Discount (Flash Sale)</label>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <!-- Timer Discount Percentage -->
    <div>
      <label class="text-xs font-medium text-gray-600">Timer Discount %</label>
      <input type="number"
        step="0.01"
        name="variant_timer_discount[<?php echo $typeIndex; ?>][]"
        value="<?php echo htmlspecialchars($variant['timer_discount_percent'] ?? '0'); ?>"
        placeholder="e.g., 20"
        class="border p-2 w-full rounded text-sm timer-discount-input"
        data-variant-index="<?php echo $typeIndex; ?>"
        min="0"
        max="100" />
      <p class="text-xs text-gray-500 mt-1">Extra discount during flash sale</p>
    </div>

    <!-- Timer Discount Active -->
    <div class="flex items-center">
      <input type="checkbox"
        name="variant_timer_active[<?php echo $typeIndex; ?>][]"
        value="1"
        <?php echo (!empty($variant['timer_discount_active']) ? 'checked' : ''); ?>
        class="mr-2"
        id="timer_active_<?php echo $typeIndex; ?>_<?php echo $variant['id'] ?? 'new'; ?>" />
      <label for="timer_active_<?php echo $typeIndex; ?>_<?php echo $variant['id'] ?? 'new'; ?>"
        class="text-sm font-medium text-gray-700">
        Enable Timer Discount
      </label>
    </div>

    <!-- Start Date/Time -->
    <div>
      <label class="text-xs font-medium text-gray-600">Start Date & Time</label>
      <input type="datetime-local"
        name="variant_timer_start[<?php echo $typeIndex; ?>][]"
        value="<?php echo !empty($variant['timer_discount_start']) ? date('Y-m-d\TH:i', strtotime($variant['timer_discount_start'])) : ''; ?>"
        class="border p-2 w-full rounded text-sm timer-start-input"
        data-variant-index="<?php echo $typeIndex; ?>"
        onchange="calculateDuration(this)" />
    </div>

    <!-- End Date/Time -->
    <div>
      <label class="text-xs font-medium text-gray-600">End Date & Time</label>
      <input type="datetime-local"
        name="variant_timer_end[<?php echo $typeIndex; ?>][]"
        value="<?php echo !empty($variant['timer_discount_end']) ? date('Y-m-d\TH:i', strtotime($variant['timer_discount_end'])) : ''; ?>"
        class="border p-2 w-full rounded text-sm timer-end-input"
        data-variant-index="<?php echo $typeIndex; ?>"
        onchange="calculateDuration(this)" />
    </div>
  </div>

  <!-- Duration Display Section -->
  <div class="mt-3 p-3 bg-white rounded border-2 border-orange-300">
    <div class="text-xs font-semibold text-gray-700 mb-2">📊 Sale Duration:</div>
    
    <div class="grid grid-cols-4 gap-2 mb-3">
      <!-- Days -->
      <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-2 rounded border border-blue-300">
        <div class="text-xl font-bold text-blue-600 duration-days">0</div>
        <div class="text-xs text-gray-600 font-semibold">Days</div>
      </div>

      <!-- Hours -->
      <div class="bg-gradient-to-br from-green-50 to-green-100 p-2 rounded border border-green-300">
        <div class="text-xl font-bold text-green-600 duration-hours">0</div>
        <div class="text-xs text-gray-600 font-semibold">Hours</div>
      </div>

      <!-- Minutes -->
      <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-2 rounded border border-purple-300">
        <div class="text-xl font-bold text-purple-600 duration-minutes">0</div>
        <div class="text-xs text-gray-600 font-semibold">Minutes</div>
      </div>

      <!-- Seconds -->
      <div class="bg-gradient-to-br from-pink-50 to-pink-100 p-2 rounded border border-pink-300">
        <div class="text-xl font-bold text-pink-600 duration-seconds">0</div>
        <div class="text-xs text-gray-600 font-semibold">Seconds</div>
      </div>
    </div>

    <!-- Total Duration Summary -->
    <div class="p-2 bg-gradient-to-r from-orange-50 to-yellow-50 rounded border border-orange-300">
      <div class="text-xs font-semibold text-gray-700">📅 Total Duration:</div>
      <div class="text-sm font-bold text-orange-600 mt-1 duration-total">Set start and end dates</div>
      <div class="text-xs text-red-500 mt-1 duration-warning" style="display:none;"></div>
    </div>
  </div>

  <!-- Timer Discount Preview -->
  <div class="mt-2 p-2 bg-white rounded border">
    <div class="flex justify-between items-center">
      <span class="text-xs text-gray-600">Price after timer discount:</span>
      <span class="timer-final-preview text-sm font-bold text-orange-600">₱0.00</span>
    </div>
  </div>
</div>

                  <!-- Row 3: Dimensions -->
                  <div class="bg-white p-3 rounded mb-2">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                      <div>
                        <label class="text-xs font-medium text-gray-600">Width</label>
                        <input type="number" step="0.01" name="variant_width[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['width'] ?? ''); ?>"
                          placeholder="Width" class="border p-2 w-full rounded text-sm" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">Height</label>
                        <input type="number" step="0.01" name="variant_height[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['height'] ?? ''); ?>"
                          placeholder="Height" class="border p-2 w-full rounded text-sm" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">Length</label>
                        <input type="number" step="0.01" name="variant_length[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['length'] ?? ''); ?>"
                          placeholder="Length" class="border p-2 w-full rounded text-sm" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">Unit</label>
                        <select name="variant_dimension_unit[<?php echo $typeIndex; ?>][]" class="border p-2 w-full rounded text-sm">
                          <option value="mm" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'mm' ? 'selected' : ''; ?>>mm</option>
                          <option value="cm" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'cm' ? 'selected' : ''; ?>>cm</option>
                          <option value="inches" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'inches' ? 'selected' : ''; ?>>inches</option>
                          <option value="m" <?php echo ($variant['dimension_unit'] ?? 'cm') == 'm' ? 'selected' : ''; ?>>m</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Row 4: Weight -->
                  <div class="bg-white p-3 rounded mb-2">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">⚖️ Weight</label>
                    <div class="grid grid-cols-2 gap-2">
                      <div>
                        <label class="text-xs font-medium text-gray-600">Weight</label>
                        <input type="number" step="0.01" name="variant_weight[<?php echo $typeIndex; ?>][]"
                          value="<?php echo htmlspecialchars($variant['weight'] ?? ''); ?>"
                          placeholder="Weight" class="border p-2 w-full rounded text-sm" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-600">Unit</label>
                        <select name="variant_weight_unit[<?php echo $typeIndex; ?>][]" class="border p-2 w-full rounded text-sm">
                          <option value="g" <?php echo ($variant['weight_unit'] ?? 'kg') == 'g' ? 'selected' : ''; ?>>g (Grams)</option>
                          <option value="kg" <?php echo ($variant['weight_unit'] ?? 'kg') == 'kg' ? 'selected' : ''; ?>>kg (Kilograms)</option>
                          <option value="lbs" <?php echo ($variant['weight_unit'] ?? 'kg') == 'lbs' ? 'selected' : ''; ?>>lbs (Pounds)</option>
                          <option value="oz" <?php echo ($variant['weight_unit'] ?? 'kg') == 'oz' ? 'selected' : ''; ?>>oz (Ounces)</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Row 5: Available Colors for this Variant with STOCK -->
                  <div class="bg-white p-3 rounded mb-2 border-2 border-purple-200">
                    <label class="text-xs font-semibold text-gray-700 block mb-2">🎨 Available Colors & Stock for this Variant</label>

                    <div id="variant-colors-section-<?php echo $typeIndex; ?>-<?php echo $variant_id; ?>" class="space-y-2 mb-3">
                      <?php
                      if ($variantColors && $variantColors->num_rows > 0):
                        while ($vc = $variantColors->fetch_assoc()):
                      ?>
                          <div class="flex gap-2 items-center bg-gray-50 p-2 rounded border variant-color-item">
                            <input type="hidden" name="variant_color_id[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
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

                            <span class="text-sm font-medium text-gray-700 min-w-24">
                              <?php echo htmlspecialchars($vc['color_name']); ?>
                            </span>

                            <!-- STOCK INPUT (NEW - PER SIZE+COLOR COMBO) -->
                            <input type="number"
                              name="variant_color_stock[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                              value="<?php echo (int)$vc['stock_quantity']; ?>"
                              placeholder="Stock"
                              class="border p-2 w-24 rounded text-sm font-semibold"
                              min="0" required />
                          </div>
                      <?php
                        endwhile;
                      endif;
                      ?>
                    </div>

                    <!-- Add new colors to variant -->
                    <div class="pt-2 border-t">
                      <p class="text-xs text-gray-600 mb-2">Add color options to this variant:</p>
                      <div id="new-variant-colors-<?php echo $typeIndex; ?>-<?php echo $variant_id; ?>" class="space-y-2">
                        <div class="flex gap-2 items-center">
                          <?php
                          // Get colors already assigned to this variant
                          $assignedColorsQuery = "SELECT color_id FROM product_variant_colors WHERE variant_id = $variant_id";
                          $assignedColorsResult = $conn->query($assignedColorsQuery);
                          $assignedColorIds = [];
                          while ($assignedColor = $assignedColorsResult->fetch_assoc()) {
                            $assignedColorIds[] = $assignedColor['color_id'];
                          }
                          ?>
                          <select name="new_variant_color[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                            class="border p-2 rounded text-sm flex-1">
                            <option value="">-- Select a Color --</option>
                            <?php
                            $allColorsQuery = "SELECT id, color_name, color_code FROM product_colors WHERE product_id = $product_id";
                            $allColors = $conn->query($allColorsQuery);

                            while ($color = $allColors->fetch_assoc()):
                              // Skip colors already assigned to this variant
                              if (!in_array($color['id'], $assignedColorIds)):
                            ?>
                                <option value="<?php echo $color['id']; ?>">
                                  <?php echo htmlspecialchars($color['color_name']); ?>
                                </option>
                            <?php
                              endif;
                            endwhile;
                            ?>
                          </select>

                          <!-- STOCK INPUT FOR NEW COLORS -->
                          <input type="number"
                            name="new_variant_color_stock[<?php echo $typeIndex; ?>][<?php echo $variant_id; ?>][]"
                            placeholder="Stock qty"
                            value="0"
                            class="border p-2 w-32 rounded text-sm font-semibold"
                            min="0" required />

                          <button type="button"
                            onclick="addColorToVariant(<?php echo $typeIndex; ?>, <?php echo $variant_id; ?>)"
                            class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600 whitespace-nowrap">
                            + Add
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Remove Button -->
                  <div class="flex justify-end">
                    <button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button>
                  </div>
                </div>
              <?php } ?>
            </div>
            <button type="button" onclick="addVariant(<?php echo $typeIndex; ?>)" class="text-sm text-blue-600 mt-1">+ Add Variant</button>
          </div>
        <?php $typeIndex++;
        } ?>
      </div>

      <div class="mt-4 flex gap-2">
        <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">Update Product</button>
        <button type="button" onclick="addType()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Add Type</button>
      </div>
    </form>
  </div>

  <script>
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
        // After markup
        const afterMarkup = originalPrice + (originalPrice * markup / 100);
        if (markupDisplay) markupDisplay.textContent = '₱' + afterMarkup.toFixed(2);

        // After regular discount
        const afterDiscount = afterMarkup - (afterMarkup * discount / 100);
        if (finalDisplay) finalDisplay.textContent = '₱' + afterDiscount.toFixed(2);
        if (computedPriceInput) computedPriceInput.value = afterDiscount.toFixed(2);

        // After timer discount (if any)
        const afterTimerDiscount = afterDiscount - (afterDiscount * timerDiscount / 100);
        if (timerFinalDisplay) {
          timerFinalDisplay.textContent = '₱' + afterTimerDiscount.toFixed(2);
          if (timerDiscount > 0) {
            timerFinalDisplay.classList.add('animate-pulse');
          } else {
            timerFinalDisplay.classList.remove('animate-pulse');
          }
        }
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

      if (confirm('Are you sure you want to remove this sub image? This will permanently delete the file.')) {
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
      if (notice) {
        notice.remove();
      }
    }

    function previewNewSubImage(input) {
      const preview = input.parentElement.querySelector('.new-sub-image-preview');
      preview.innerHTML = '';

      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const img = document.createElement('img');
          img.src = e.target.result;
          img.classList.add('image-preview', 'border');
          preview.appendChild(img);
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    function addNewSubImage() {
      const newSubImagesSection = document.getElementById('new-sub-images-section');
      const div = document.createElement('div');
      div.classList.add('new-sub-image-item', 'flex', 'gap-2', 'mb-3', 'items-start', 'p-3', 'bg-white', 'rounded', 'border');

      div.innerHTML = `
        <div class="flex-1">
          <input type="file" name="new_sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewNewSubImage(this)" />
          <div class="new-sub-image-preview mt-2"></div>
        </div>
        <button type="button" onclick="removeNewSubImage(this)" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 whitespace-nowrap">
          Remove
        </button>
      `;

      newSubImagesSection.appendChild(div);
    }

    function removeNewSubImage(button) {
      const newSubImageItems = document.querySelectorAll('.new-sub-image-item');
      if (newSubImageItems.length > 1) {
        button.closest('.new-sub-image-item').remove();
      } else {
        alert('At least one new sub image input must remain. You can leave it empty if no new sub images are needed.');
      }
    }

    function removeType(button) {
      button.closest('[data-type-index]').remove();
    }

    function removeVariant(button) {
      button.closest('.bg-blue-50').remove();
    }

    function removeColor(button) {
      button.closest('.flex.gap-2').remove();
    }

    // Updated addColor() function with image2 support
    function addColor() {
      const colorsSection = document.getElementById('colors-section');
      const div = document.createElement('div');
      div.className = 'flex gap-2 mb-3 items-center bg-white p-3 rounded border';

      div.innerHTML = `
    <input type="hidden" name="color_id[]" value="new" />
    <div class="flex items-center">
      <span class="text-sm text-gray-400 w-[50px]">New</span>
    </div>
    <input type="text" name="color_name[]" placeholder="Color Name" class="border p-2 w-1/6 rounded" required />
    <input type="text" name="color_code[]" placeholder="Color Code (#hex)" class="border p-2 w-1/6 rounded" />
    <div class="w-1/6">
      <input type="file" name="color_image[]" accept="image/*" class="w-full text-xs" required />
      <p class="text-xs text-gray-500 mt-1">Main Image</p>
    </div>
    <div class="w-1/6">
      <input type="file" name="color_image2[]" accept="image/*" class="w-full text-xs" />
      <p class="text-xs text-gray-500 mt-1">Secondary Image</p>
    </div>
    <input type="number" step="0.01" name="color_price[]" placeholder="Color Price" class="border p-2 w-1/6 rounded" required />
    <input type="number" name="color_stock[]" placeholder="Stock" value="0" class="border p-2 w-1/6 rounded" required />
    <button type="button" onclick="removeColor(this)" class="text-red-500 text-sm">✕</button>
  `;

      colorsSection.appendChild(div);
    }

    function addColorToVariant(typeIndex, variantId) {
      const newColorsSection = document.getElementById(`new-variant-colors-${typeIndex}-${variantId}`);
      const div = document.createElement('div');
      div.className = 'flex gap-2 items-center';

      const firstSelect = document.querySelector(`select[name="new_variant_color[${typeIndex}][${variantId}][]"]`);
      const colorOptions = Array.from(firstSelect.options).map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('');

      div.innerHTML = `
        <select name="new_variant_color[${typeIndex}][${variantId}][]" 
                class="border p-2 rounded text-sm flex-1">
          <option value="">-- Select a Color --</option>
          ${colorOptions}
        </select>
        
        <input type="number" 
               name="new_variant_color_stock[${typeIndex}][${variantId}][]" 
               placeholder="Stock qty" 
               value="0"
               class="border p-2 w-32 rounded text-sm font-semibold" 
               min="0" required />
        
        <button type="button" 
                onclick="removeColorFromVariant(this)" 
                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
          Remove
        </button>
      `;

      newColorsSection.appendChild(div);
    }

    function removeColorFromVariant(button) {
      button.closest('div').remove();
    }

    function addVariant(index) {
      const variantSection = document.getElementById('variant-section-' + index);
      const div = document.createElement('div');
      div.classList.add('bg-blue-50', 'p-4', 'rounded', 'border', 'mb-3');

      div.innerHTML = `
    <input type="hidden" name="variant_id[${index}][]" value="new" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
      <div class="flex items-center">
        <span class="text-sm text-gray-400">New</span>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Size/Type</label>
        <input type="text" name="variant_size[${index}][]" placeholder="Size" class="border p-2 w-full rounded text-sm" required />
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Variant Name</label>
        <input type="text" name="variant_namevariant[${index}][]" placeholder="Name Variant" class="border p-2 w-full rounded text-sm" />
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Original Price</label>
        <input type="number" step="0.01" name="variant_original_price[${index}][]" placeholder="Original Price" class="border p-2 w-full rounded text-sm original-price-input" data-variant-index="${index}" required />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
      <div>
        <label class="text-xs font-medium text-gray-600">Markup %</label>
        <input type="number" step="0.01" name="variant_percent[${index}][]" placeholder="Markup %" class="border p-2 w-full rounded percent-input text-sm" data-variant-index="${index}" />
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">After Markup</label>
        <div class="markup-preview text-sm text-green-600 font-semibold border p-2 rounded bg-white">₱0.00</div>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Discount %</label>
        <input type="number" step="0.01" name="variant_discount[${index}][]" placeholder="Discount %" class="border p-2 w-full rounded discount-input text-sm" data-variant-index="${index}" />
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Final Price</label>
        <div class="final-preview text-sm text-red-600 font-semibold border p-2 rounded bg-white">₱0.00</div>
      </div>
    </div>

    <input type="hidden" name="variant_price[${index}][]" value="0" class="computed-price-input" />

    <div class="bg-white p-3 rounded mb-2">
      <label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <div>
          <label class="text-xs font-medium text-gray-600">Width</label>
          <input type="number" step="0.01" name="variant_width[${index}][]" placeholder="Width" class="border p-2 w-full rounded text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Height</label>
          <input type="number" step="0.01" name="variant_height[${index}][]" placeholder="Height" class="border p-2 w-full rounded text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Length</label>
          <input type="number" step="0.01" name="variant_length[${index}][]" placeholder="Length" class="border p-2 w-full rounded text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Unit</label>
          <select name="variant_dimension_unit[${index}][]" class="border p-2 w-full rounded text-sm">
            <option value="mm">mm</option>
            <option value="cm" selected>cm</option>
            <option value="inches">inches</option>
            <option value="m">m</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-white p-3 rounded mb-2">
      <label class="text-xs font-semibold text-gray-700 block mb-2">⚖️ Weight</label>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="text-xs font-medium text-gray-600">Weight</label>
          <input type="number" step="0.01" name="variant_weight[${index}][]" placeholder="Weight" class="border p-2 w-full rounded text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Unit</label>
          <select name="variant_weight_unit[${index}][]" class="border p-2 w-full rounded text-sm">
            <option value="g">g (Grams)</option>
            <option value="kg" selected>kg (Kilograms)</option>
            <option value="lbs">lbs (Pounds)</option>
            <option value="oz">oz (Ounces)</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-white p-3 rounded mb-2 border-2 border-purple-200">
      <label class="text-xs font-semibold text-gray-700 block mb-2">🎨 Available Colors & Stock</label>
      <div id="new-variant-colors-${index}-new-combo" class="space-y-2">
        <div class="flex gap-2 items-center">
          <select name="new_variant_color[${index}][new-${Date.now()}][]" class="border p-2 rounded text-sm flex-1">
            <option value="">-- Select a Color --</option>
          </select>
          <input type="number" name="new_variant_color_stock[${index}][new-${Date.now()}][]" placeholder="Stock qty" value="0" class="border p-2 w-32 rounded text-sm font-semibold" min="0" required />
          <button type="button" class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600 whitespace-nowrap" onclick="addColorToVariant(${index}, 'new-combo')">+ Add</button>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button>
    </div>
  `;

      variantSection.appendChild(div);

      const originalPriceInput = div.querySelector('.original-price-input');
      const percentInput = div.querySelector('.percent-input');
      const discountInput = div.querySelector('.discount-input');
      const markupDisplay = div.querySelector('.markup-preview');
      const finalDisplay = div.querySelector('.final-preview');
      const computedPriceInput = div.querySelector('.computed-price-input');

      const hook = () => applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput);

      originalPriceInput.addEventListener('input', hook);
      percentInput.addEventListener('input', hook);
      discountInput.addEventListener('input', hook);
    }

    function addType() {
      const typesSection = document.getElementById('types-section');
      const div = document.createElement('div');
      div.className = 'mb-6 border p-4 rounded bg-gray-50 relative';
      div.setAttribute('data-type-index', typeIndex);

      div.innerHTML = `
      <div class="flex justify-between items-center mb-2">
        <h3 class="font-semibold text-lg text-gray-700">Product Type ${typeIndex + 1}</h3>
        <button type="button" onclick="removeType(this)" class="text-red-600 text-sm hover:underline">Remove Type</button>
      </div>
      <input type="hidden" name="type_id[]" value="new" />
      <div class="mb-3 flex gap-2 items-center">
        <input type="text" name="type_name[]" placeholder="Type Name" class="border p-2 w-1/2 rounded" required />
        <input type="file" name="type_image[]" accept="image/*" class="w-1/2" />
      </div>
      <label class="block font-medium mb-1">Variants (Sizes, etc.):</label>
      <div id="variant-section-${typeIndex}"></div>
      <button type="button" onclick="addVariant(${typeIndex})" class="text-sm text-blue-600 mt-1">+ Add Variant</button>
    `;

      typesSection.appendChild(div);
      typeIndex++;
    }

    // ✅ FIXED: Timer discount only applies if checkbox is CHECKED
    function applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput) {
      const container = originalPriceInput.closest('.bg-blue-50');
      const timerDiscountInput = container ? container.querySelector('.timer-discount-input') : null;
      const timerCheckbox = container ? container.querySelector('input[name*="timer_active"]') : null;

      const originalPrice = parseFloat(originalPriceInput.value) || 0;
      const percent = parseFloat(percentInput.value) || 0;
      const discount = parseFloat(discountInput.value) || 0;

      // ✅ ONLY apply timer discount if checkbox is CHECKED
      const timerDiscount = (timerCheckbox && timerCheckbox.checked && timerDiscountInput) ?
        (parseFloat(timerDiscountInput.value) || 0) :
        0;

      if (originalPrice > 0) {
        // Step 1: After markup
        const priceAfterMarkup = originalPrice + (originalPrice * percent / 100);
        markupDisplay.textContent = '₱' + priceAfterMarkup.toFixed(2);

        // Step 2: After regular discount
        const priceAfterDiscount = priceAfterMarkup - (priceAfterMarkup * discount / 100);

        // Step 3: Apply timer discount ONLY if enabled
        const finalPrice = priceAfterDiscount - (priceAfterDiscount * timerDiscount / 100);

        // ✅ UPDATE FINAL PRICE
        finalDisplay.textContent = '₱' + finalPrice.toFixed(2);

        if (computedPriceInput) {
          computedPriceInput.value = finalPrice.toFixed(2);
        }

        // Change color based on timer discount
        if (timerDiscount > 0 && timerCheckbox && timerCheckbox.checked) {
          finalDisplay.classList.add('text-orange-600', 'font-bold');
          finalDisplay.style.animation = 'pulse 2s ease-in-out infinite';
        } else {
          finalDisplay.classList.remove('text-orange-600', 'font-bold');
          finalDisplay.style.animation = '';
        }
      } else {
        markupDisplay.textContent = '₱0.00';
        finalDisplay.textContent = '₱0.00';
        if (computedPriceInput) {
          computedPriceInput.value = '0';
        }
      }
    }

    // ✅ UPDATED DOMContentLoaded - Listen to all inputs AND checkbox
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.bg-blue-50.p-4.rounded.border.mb-3').forEach((variantDiv) => {
        const originalPriceInput = variantDiv.querySelector('.original-price-input');
        const percentInput = variantDiv.querySelector('.percent-input');
        const discountInput = variantDiv.querySelector('.discount-input');
        const timerDiscountInput = variantDiv.querySelector('.timer-discount-input');
        const timerCheckbox = variantDiv.querySelector('input[name*="timer_active"]');
        const markupDisplay = variantDiv.querySelector('.markup-preview');
        const finalDisplay = variantDiv.querySelector('.final-preview');
        const computedPriceInput = variantDiv.querySelector('.computed-price-input');

        if (originalPriceInput && percentInput && markupDisplay && finalDisplay && computedPriceInput) {
          const hook = () => applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput);

          // Listen to all price inputs
          originalPriceInput.addEventListener('input', hook);
          percentInput.addEventListener('input', hook);
          if (discountInput) discountInput.addEventListener('input', hook);
          if (timerDiscountInput) timerDiscountInput.addEventListener('input', hook);

          // ✅ IMPORTANT: Listen to checkbox changes!
          if (timerCheckbox) {
            timerCheckbox.addEventListener('change', hook);
          }

          hook(); // Initialize on page load
        }
      });
    });

    // ✅ For new variants - same setup
    function setupVariantListeners(variantDiv) {
      const originalPriceInput = variantDiv.querySelector('.original-price-input');
      const percentInput = variantDiv.querySelector('.percent-input');
      const discountInput = variantDiv.querySelector('.discount-input');
      const timerDiscountInput = variantDiv.querySelector('.timer-discount-input');
      const timerCheckbox = variantDiv.querySelector('input[name*="timer_active"]');
      const markupDisplay = variantDiv.querySelector('.markup-preview');
      const finalDisplay = variantDiv.querySelector('.final-preview');
      const computedPriceInput = variantDiv.querySelector('.computed-price-input');

      if (originalPriceInput && percentInput && markupDisplay && finalDisplay && computedPriceInput) {
        const hook = () => applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput);

        originalPriceInput.addEventListener('input', hook);
        percentInput.addEventListener('input', hook);
        if (discountInput) discountInput.addEventListener('input', hook);
        if (timerDiscountInput) timerDiscountInput.addEventListener('input', hook);
        if (timerCheckbox) timerCheckbox.addEventListener('change', hook);

        hook();
      }
    }

     // Duration Calculator Function
  function calculateDuration(input) {
    const container = input.closest('.bg-yellow-50');
    const startInput = container.querySelector('.timer-start-input');
    const endInput = container.querySelector('.timer-end-input');
    
    const startValue = startInput.value;
    const endValue = endInput.value;

    // Get duration display elements
    const durationDays = container.querySelector('.duration-days');
    const durationHours = container.querySelector('.duration-hours');
    const durationMinutes = container.querySelector('.duration-minutes');
    const durationSeconds = container.querySelector('.duration-seconds');
    const durationTotal = container.querySelector('.duration-total');
    const durationWarning = container.querySelector('.duration-warning');

    // Reset if either field is empty
    if (!startValue || !endValue) {
      durationDays.textContent = '0';
      durationHours.textContent = '0';
      durationMinutes.textContent = '0';
      durationSeconds.textContent = '0';
      durationTotal.textContent = 'Set start and end dates';
      durationWarning.style.display = 'none';
      return;
    }

    // Parse datetime-local values
    const startDate = new Date(startValue);
    const endDate = new Date(endValue);

    // Validation
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
      durationTotal.textContent = '❌ Invalid date format';
      durationWarning.style.display = 'block';
      durationWarning.textContent = 'Please check your date/time inputs';
      return;
    }

    if (endDate <= startDate) {
      durationDays.textContent = '0';
      durationHours.textContent = '0';
      durationMinutes.textContent = '0';
      durationSeconds.textContent = '0';
      durationTotal.textContent = '❌ End time must be after start time';
      durationWarning.style.display = 'block';
      durationWarning.textContent = 'Adjust your end date/time';
      return;
    }

    // Calculate duration
    const diffMs = endDate - startDate;
    const diffSecs = Math.floor(diffMs / 1000);
    
    const days = Math.floor(diffSecs / (24 * 3600));
    const hours = Math.floor((diffSecs % (24 * 3600)) / 3600);
    const minutes = Math.floor((diffSecs % 3600) / 60);
    const seconds = diffSecs % 60;

    // Update display
    durationDays.textContent = days;
    durationHours.textContent = hours;
    durationMinutes.textContent = minutes;
    durationSeconds.textContent = seconds;

    // Format total duration text
    let durationText = [];
    if (days > 0) durationText.push(`${days} day${days > 1 ? 's' : ''}`);
    if (hours > 0) durationText.push(`${hours} hour${hours > 1 ? 's' : ''}`);
    if (minutes > 0) durationText.push(`${minutes} minute${minutes > 1 ? 's' : ''}`);
    if (seconds > 0 && days === 0 && hours === 0) durationText.push(`${seconds} second${seconds > 1 ? 's' : ''}`);

    const totalText = durationText.length > 0 ? durationText.join(', ') : 'Less than a second';
    durationTotal.textContent = '✅ ' + totalText;

    // Clear warning if valid
    durationWarning.style.display = 'none';
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.timer-start-input').forEach(input => {
      calculateDuration(input);
    });
  });
// ✅ TIMER MONITORING - NO AUTO ACTIVATION
function monitorAllTimerDiscounts() {
  console.log('🚀 Starting timer discount monitoring...');
  
  const timerContainers = document.querySelectorAll('.bg-yellow-50');
  const monitoredVariants = new Map();
  
  console.log(`📊 Found ${timerContainers.length} timer sections`);
  
  timerContainers.forEach((container, index) => {
    const startInput = container.querySelector('.timer-start-input');
    const endInput = container.querySelector('.timer-end-input');
    const timerCheckbox = container.querySelector('input[name*="timer_active"]');
    const variantContainer = container.closest('.bg-blue-50');
    const variantIdInput = variantContainer.querySelector('input[name*="variant_id"]');
    
    if (!startInput || !endInput || !timerCheckbox || !variantContainer) {
      console.warn(`⚠️ Timer section ${index} missing required elements`);
      return;
    }
    
    const variantId = variantIdInput ? variantIdInput.value : null;
    
    // Skip new variants (not yet saved in database)
    if (!variantId || variantId === 'new') {
      console.log(`⏭️ Skipping new/unsaved variant`);
      return;
    }
    
    console.log(`✅ Monitoring variant ${variantId}`);
    
    monitoredVariants.set(variantId, {
      container: container,
      variantContainer: variantContainer,
      startInput: startInput,
      endInput: endInput,
      timerCheckbox: timerCheckbox,
      lastCheckTime: Date.now(),
      isExpired: false
    });
  });
  
  // ✅ CHECK EVERY 5 SECONDS
  setInterval(() => {
    monitoredVariants.forEach((data, variantId) => {
      const { container, timerCheckbox, startInput, endInput, variantContainer } = data;
      
      const startValue = startInput.value;
      const endValue = endInput.value;
      
      if (!startValue || !endValue) return;
      
      try {
        const startDate = new Date(startValue + ':00');
        const endDate = new Date(endValue + ':00');
        const now = new Date();
        
        const isCurrentlyActive = timerCheckbox.checked;
        
        // 🔴 TIMER EXPIRED - AUTO DEACTIVATE & RECALCULATE PRICE (ONLY if manually enabled)
        if (now >= endDate && isCurrentlyActive && !data.isExpired) {
          console.log(`⏰ VARIANT ${variantId}: Timer EXPIRED! Auto-deactivating...`);
          
          data.isExpired = true;
          timerCheckbox.checked = false;
          
          // 🔥 SAVE TO DATABASE & RECALCULATE PRICE
          saveTimerStatusToDatabase(variantId, 'deactivate', container, () => {
            console.log(`✅ Variant ${variantId} deactivated and saved to DB`);
            
            // ✨ RECALCULATE PRICES IMMEDIATELY
            recalculatePricesForVariant(variantContainer);
            
            // Visual feedback
            showTimerExpiredNotice(container);
          });
          
          return;
        }
        
        // Reset expired flag if timer is manually re-enabled
        if (isCurrentlyActive && now < endDate) {
          data.isExpired = false;
        }
        
        // 🟡 SHOW COUNTDOWN IF MANUALLY ENABLED
        if (isCurrentlyActive && now >= startDate && now < endDate) {
          const remainingMs = endDate - now;
          const remainingSeconds = Math.floor(remainingMs / 1000);
          
          const hours = Math.floor(remainingSeconds / 3600);
          const minutes = Math.floor((remainingSeconds % 3600) / 60);
          const secs = remainingSeconds % 60;
          
          const durationWarning = container.querySelector('.duration-warning');
          
          // Show countdown only last 5 minutes
          if (remainingSeconds < 300 && durationWarning) {
            durationWarning.style.display = 'block';
            durationWarning.innerHTML = `
              ⏰ <span style="color:#f97316; font-weight:bold;">
                EXPIRES IN: ${hours}h ${minutes}m ${secs}s
              </span>
            `;
          }
        }
        
      } catch (e) {
        console.error(`Error monitoring variant ${variantId}:`, e);
      }
    });
  }, 5000); // Check every 5 seconds
}

// ✅ FUNCTION: Save Timer Status sa Database WITH PRICE UPDATE
function saveTimerStatusToDatabase(variantId, action, container, callback) {
  const formData = new FormData();
  formData.append('variant_id', variantId);
  formData.append('action', action); // 'deactivate' or 'activate'
  
  fetch('main-update-auto_deactivate_timer-page-2-A.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log(`✅ ${action.toUpperCase()} saved to database:`, data);
      
      if (callback) {
        callback();
      }
    } else {
      console.error('❌ Failed to save timer status:', data);
    }
  })
  .catch(error => {
    console.error('❌ AJAX Error:', error);
  });
}

// ✅ HELPER: Recalculate prices for a variant
function recalculatePricesForVariant(variantContainer) {
  console.log('🔄 Recalculating prices for variant...');
  
  const originalPriceInput = variantContainer.querySelector('.original-price-input');
  const percentInput = variantContainer.querySelector('.percent-input');
  const discountInput = variantContainer.querySelector('.discount-input');
  const markupDisplay = variantContainer.querySelector('.markup-preview');
  const finalDisplay = variantContainer.querySelector('.final-preview');
  const computedPriceInput = variantContainer.querySelector('.computed-price-input');
  
  if (originalPriceInput && percentInput && markupDisplay && finalDisplay) {
    console.log('✨ Running applyMarkup function...');
    applyMarkup(originalPriceInput, percentInput, discountInput, markupDisplay, finalDisplay, computedPriceInput);
    console.log('✅ Prices recalculated');
  }
}

// ✅ Visual feedback - Timer Expired
function showTimerExpiredNotice(container) {
  container.style.opacity = '0.7';
  container.style.borderColor = '#f87171';
  container.style.backgroundColor = '#fef2f2';
  
  const durationWarning = container.querySelector('.duration-warning');
  if (durationWarning) {
    durationWarning.style.display = 'block';
    durationWarning.innerHTML = `
      ⏰ <span style="color:#ef4444; font-weight:bold;">
        ✅ EXPIRED - Timer discount has been auto-deactivated. Price updated.
      </span>
    `;
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    monitorAllTimerDiscounts();
  }, 500);
});
  </script>

</body>

</html>