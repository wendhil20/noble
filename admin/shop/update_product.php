<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  // Redirect to login page
  header("Location: ../../loginpage/index.php");
  exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
  // Destroy session and redirect to login
  session_unset();
  session_destroy();
  header("Location: ../../loginpage/index.php?timeout=true");
  exit();
}

// Update last activity time
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

  <div class="bg-white p-6 rounded-lg shadow mt-5">
    <h2 class="text-2xl font-bold mb-4 text-orange-600">Update Product</h2>

    <form action="update_process.php" method="POST" enctype="multipart/form-data">
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

          <div class="text-sm text-gray-600">
            <p>• You can remove existing sub images by clicking the × button</p>
            <p>• Restore removed images by clicking the ↻ button before saving</p>
            <p>• Add new sub images using the file inputs below</p>
            <p>• Leave file inputs empty if you don't want to add new images</p>
          </div>
        </div>
      </div>

      <!-- REPLACED CODENAME WITH CATEGORY DROPDOWN -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Category</label>
        <select name="category" required class="w-full border p-2 rounded bg-white">
          <option value="">Select a Category</option>
          <?php while ($category = $categories->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($category['name']) ?>" 
                    <?= (isset($product['category']) && $product['category'] == $category['name']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($category['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1">Choose the category this product belongs to</p>
      </div>

      <div class="mb-4">
        <label class="block font-semibold mb-1">Quantity</label>
        <input type="number" name="quantity" value="<?php echo htmlspecialchars($product['quantity']); ?>" required class="w-full border p-2 rounded" />
      </div>

      <div class="mb-4">
        <label class="block font-semibold mb-1">Description</label>
        <textarea name="description" rows="4" class="w-full border p-2 rounded" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
      </div>

      <!-- Product Colors Section -->
      <div class="mb-6 border p-4 rounded bg-green-50">
        <h3 class="font-semibold text-lg text-gray-700 mb-3">Product Colors</h3>
        <div id="colors-section">
          <?php
          $colorIndex = 0;
          while ($color = $colors->fetch_assoc()) { ?>
            <div class="flex gap-2 mb-2 items-center bg-white p-2 rounded border">
              <input type="hidden" name="color_id[]" value="<?php echo $color['id']; ?>" />

              <!-- Delete checkbox -->
              <div class="flex items-center">
                <input type="checkbox" name="delete_color[]" value="<?php echo $color['id']; ?>" />
                <label class="text-sm text-gray-600 ml-1">Delete</label>
              </div>

              <!-- Color Name -->
              <input type="text" name="color_name[]" value="<?php echo htmlspecialchars($color['color_name']); ?>" placeholder="Color Name" class="border p-2 w-1/5 rounded" required />

              <!-- Color Code -->
              <input type="text" name="color_code[]" value="<?php echo htmlspecialchars($color['color_code']); ?>" placeholder="Color Code (#hex)" class="border p-2 w-1/5 rounded" />

              <!-- Image -->
              <div class="w-1/5">
                <?php if (!empty($color['image'])): ?>
                  <img src="../../<?= htmlspecialchars($color['image']) ?>" alt="Color Image" class="w-12 h-12 object-contain rounded mb-1 border" />
                <?php endif; ?>
                <input type="file" name="color_image[]" accept="image/*" class="w-full text-xs" />
              </div>

              <!-- Price -->
              <input type="number" step="0.01" name="color_price[]" value="<?php echo htmlspecialchars($color['price']); ?>" placeholder="Color Price" class="border p-2 w-1/5 rounded" required />

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
        $types->data_seek(0); // Reset result set
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
              <?php while ($variant = $variants->fetch_assoc()) { ?>
                <div class="flex gap-2 mb-2 items-center bg-blue-50 p-2 rounded">
                  <input type="hidden" name="variant_id[<?php echo $typeIndex; ?>][]" value="<?php echo $variant['id']; ?>" />

                  <!-- Delete -->
                  <div class="flex items-center">
                    <input type="checkbox" name="delete_variant[<?php echo $typeIndex; ?>][]" value="<?php echo $variant['id']; ?>" />
                    <label class="text-sm text-gray-600 ml-1">Del</label>
                  </div>

                  <!-- Size -->
                  <input type="text" name="variant_size[<?php echo $typeIndex; ?>][]" value="<?php echo htmlspecialchars($variant['size']); ?>" placeholder="Size" class="border p-2 w-1/6 rounded" />

                  <!-- Base Price -->
                  <input
                    type="number"
                    step="0.01"
                    name="variant_price[<?php echo $typeIndex; ?>][]"
                    value="<?php echo htmlspecialchars($variant['price']); ?>"
                    placeholder="Base Price"
                    class="border p-2 w-1/6 rounded computed-price" />

                  <!-- Markup % -->
                  <input
                    type="number"
                    step="0.01"
                    name="variant_percent[<?php echo $typeIndex; ?>][]"
                    value="<?php echo htmlspecialchars($variant['percent'] ?? ''); ?>"
                    placeholder="Markup %"
                    class="border p-2 w-1/6 rounded percent-input" />

                  <!-- Markup Display -->
                  <div class="markup-preview text-sm text-green-600 w-1/6 font-semibold">₱0.00</div>

                  <!-- Discount -->
                  <input
                    type="number"
                    step="0.01"
                    name="variant_discount[<?php echo $typeIndex; ?>][]"
                    value="<?php echo htmlspecialchars($variant['discount'] ?? ''); ?>"
                    placeholder="Discount %"
                    class="border p-2 w-1/6 rounded discount-input" />

                  <!-- Final Price Display -->
                  <div class="final-preview text-sm text-red-600 w-1/6 font-semibold">₱0.00</div>

                  <!-- Name Variant -->
                  <input type="text" name="variant_namevariant[<?php echo $typeIndex; ?>][]" value="<?php echo htmlspecialchars($variant['namevariant'] ?? ''); ?>" placeholder="Name Variant" class="border p-2 w-1/6 rounded" />

                  <!-- Remove Button -->
                  <button type="button" onclick="removeVariant(this)" class="text-red-500 text-sm">✕</button>
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
    let typeIndex = <?php echo $typeIndex; ?>;

    // Function to remove existing sub image
    function removeExistingSubImage(button, imageIndex) {
      const imageItem = button.closest('.sub-image-item');
      const keepInput = imageItem.querySelector('input[name*="keep_sub_image"]');
      
      if (confirm('Are you sure you want to remove this sub image? This will permanently delete the file.')) {
        // Set the keep input to 0 to mark for deletion
        keepInput.value = '0';
        
        // Add visual indication that it will be deleted
        imageItem.classList.add('marked-for-deletion');
        
        // Add a "Will be deleted" label
        if (!imageItem.querySelector('.deletion-notice')) {
          const notice = document.createElement('div');
          notice.className = 'deletion-notice text-xs text-red-600 text-center mt-1 font-semibold';
          notice.textContent = 'Will be deleted on save';
          imageItem.appendChild(notice);
        }
      }
    }

    // Function to restore sub-image if user changes mind
    function restoreExistingSubImage(button, imageIndex) {
      const imageItem = button.closest('.sub-image-item');
      const keepInput = imageItem.querySelector('input[name*="keep_sub_image"]');
      
      // Restore the image
      keepInput.value = '1';
      imageItem.classList.remove('marked-for-deletion');
      
      // Remove deletion notice
      const notice = imageItem.querySelector('.deletion-notice');
      if (notice) {
        notice.remove();
      }
    }

    // Function to preview new sub image
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

    // Function to add new sub image input
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

    // Function to remove new sub image input
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
      button.parentElement.remove();
    }

    function removeColor(button) {
      button.parentElement.remove();
    }

    function addColor() {
      const colorsSection = document.getElementById('colors-section');
      const div = document.createElement('div');
      div.className = 'flex gap-2 mb-2 items-center bg-white p-2 rounded border';

      div.innerHTML = `
        <input type="hidden" name="color_id[]" value="new" />
        
        <!-- Delete placeholder for new colors -->
        <div class="flex items-center">
          <span class="text-sm text-gray-400 w-[50px]">New</span>
        </div>
        
        <!-- Color Name -->
        <input type="text" name="color_name[]" placeholder="Color Name" class="border p-2 w-1/5 rounded" required />
        
        <!-- Color Code -->
        <input type="text" name="color_code[]" placeholder="Color Code (#hex)" class="border p-2 w-1/5 rounded" />
        
        <!-- Image -->
        <div class="w-1/5">
          <input type="file" name="color_image[]" accept="image/*" class="w-full text-xs" required />
        </div>
        
        <!-- Price -->
        <input type="number" step="0.01" name="color_price[]" placeholder="Color Price" class="border p-2 w-1/5 rounded" required />
        
        <!-- Remove Button -->
        <button type="button" onclick="removeColor(this)" class="text-red-500 text-sm">✕</button>
      `;

      colorsSection.appendChild(div);
    }

    function createInput(type, name, placeholder, className = '', value = '') {
      const input = document.createElement('input');
      input.type = type;
      input.name = name;
      input.placeholder = placeholder;
      input.className = `border p-2 rounded ${className}`;
      if (value) {
        input.value = value;
      }
      return input;
    }

    function getLastVariantData(index) {
      const variantSection = document.getElementById('variant-section-' + index);
      const lastVariant = variantSection.querySelector('.flex:last-child');

      if (!lastVariant) return null;

      return {
        size: lastVariant.querySelector('input[name*="variant_size"]')?.value || '',
        price: lastVariant.querySelector('input[name*="variant_price"]')?.value || '',
        percent: lastVariant.querySelector('input[name*="variant_percent"]')?.value || '',
        discount: lastVariant.querySelector('input[name*="variant_discount"]')?.value || '',
        namevariant: lastVariant.querySelector('input[name*="variant_namevariant"]')?.value || ''
      };
    }

    function addVariant(index) {
      const variantSection = document.getElementById('variant-section-' + index);
      const lastData = getLastVariantData(index);

      const div = document.createElement('div');
      div.classList.add('flex', 'gap-2', 'mb-2', 'items-center', 'bg-blue-50', 'p-2', 'rounded');

      // Hidden ID
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = `variant_id[${index}][]`;
      hiddenInput.value = 'new';
      div.appendChild(hiddenInput);

      // Delete placeholder
      const deleteLabel = document.createElement('div');
      deleteLabel.innerHTML = `<span class="text-sm text-gray-400 w-[40px] inline-block">New</span>`;
      div.appendChild(deleteLabel);

      // Size
      const sizeInput = createInput('text', `variant_size[${index}][]`, 'Size', 'w-1/6');
      div.appendChild(sizeInput);

      // Base Price
      const priceInput = createInput('number', `variant_price[${index}][]`, 'Base Price', 'w-1/6 computed-price');
      priceInput.step = '0.01';
      div.appendChild(priceInput);

      // Markup %
      const percentInput = createInput('number', `variant_percent[${index}][]`, 'Markup %', 'w-1/6 percent-input');
      percentInput.step = '0.01';
      div.appendChild(percentInput);

      // Markup Preview
      const markupDisplay = document.createElement('div');
      markupDisplay.className = 'markup-preview text-sm text-green-600 w-1/6 font-semibold';
      markupDisplay.textContent = '₱0.00';
      div.appendChild(markupDisplay);

      // Discount %
      const discountInput = createInput('number', `variant_discount[${index}][]`, 'Discount %', 'w-1/6 discount-input');
      discountInput.step = '0.01';
      div.appendChild(discountInput);

      // Final Price Preview
      const finalDisplay = document.createElement('div');
      finalDisplay.className = 'final-preview text-sm text-red-600 w-1/6 font-semibold';
      finalDisplay.textContent = '₱0.00';
      div.appendChild(finalDisplay);

      // Name Variant
      const nameVariantInput = createInput('text', `variant_namevariant[${index}][]`, 'Name Variant', 'w-1/6');
      div.appendChild(nameVariantInput);

      // Remove Button
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'text-red-500 text-sm';
      removeButton.innerHTML = '✕';
      removeButton.onclick = () => removeVariant(removeButton);
      div.appendChild(removeButton);

      variantSection.appendChild(div);

      // Add event listeners for price calculation
      const hook = () => applyMarkup(priceInput, percentInput, discountInput, markupDisplay, finalDisplay);
      priceInput.addEventListener('input', hook);
      percentInput.addEventListener('input', hook);
      discountInput.addEventListener('input', hook);

      // Calculate initial markup and final price
      applyMarkup(priceInput, percentInput, discountInput, markupDisplay, finalDisplay);

      // Focus on the size input
      sizeInput.focus();
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

    function applyMarkup(priceInput, percentInput, discountInput, markupDisplay, finalDisplay) {
      const base = parseFloat(priceInput.value);
      const percent = parseFloat(percentInput.value);
      const discount = parseFloat(discountInput?.value || 0);

      if (!isNaN(base) && !isNaN(percent)) {
        const computed = base + (base * percent / 100);
        markupDisplay.textContent = '₱' + computed.toFixed(2);

        if (!isNaN(discount)) {
          const final = computed - (computed * discount / 100);
          finalDisplay.textContent = '₱' + final.toFixed(2);
        } else {
          finalDisplay.textContent = '₱' + computed.toFixed(2);
        }
      } else {
        markupDisplay.textContent = '₱0.00';
        finalDisplay.textContent = '₱0.00';
      }
    }

    // Hook inputs on page load (existing data)
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.percent-input').forEach((percentInput) => {
        const parent = percentInput.closest('.flex');
        const priceInput = parent.querySelector('.computed-price');
        const discountInput = parent.querySelector('.discount-input');
        const markupDisplay = parent.querySelector('.markup-preview');
        const finalDisplay = parent.querySelector('.final-preview');

        if (priceInput && markupDisplay && finalDisplay) {
          const hook = () => applyMarkup(priceInput, percentInput, discountInput, markupDisplay, finalDisplay);

          priceInput.addEventListener('input', hook);
          percentInput.addEventListener('input', hook);
          if (discountInput) discountInput.addEventListener('input', hook);

          // Trigger initial display
          hook();
        }
      });
    });
  </script>

</body>

</html>