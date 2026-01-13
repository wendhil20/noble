<?php
//adminshop.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  // Redirect to login page
  header("Location: ../../loginpage/index.php");
  exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();
$categoryQuery = "SELECT * FROM categories";
$categoryResult = mysqli_query($conn, $categoryQuery);

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Product Upload</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .image-preview {
      max-width: 100px;
      max-height: 100px;
      object-fit: cover;
      border-radius: 4px;
    }
  </style>
</head>

<body class="bg-gray-100 ">

  <?php include '../navbar/top.php'; ?>

  <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg">
    <h2 class="text-3xl font-bold mb-8 text-gray-800">Upload Product</h2>

    <!-- CSV Import Section -->
    <div class="bg-blue-50 p-6 rounded-lg border-l-4 border-blue-600 mb-8">
      <h3 class="text-lg font-semibold text-blue-900 mb-3">Quick Import via CSV</h3>
      <form id="csv-import-form" action="import_csv.php" method="POST" enctype="multipart/form-data">
        <div class="flex gap-3">
          <input type="file" name="csv_file" accept=".csv" class="flex-1 border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required />
          <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-semibold hover:bg-blue-700 transition whitespace-nowrap">Import CSV</button>
        </div>
        <p class="text-sm text-gray-600 mt-2">Upload multiple products at once using a CSV file</p>
      </form>
    </div>

    <!-- Product Upload Form -->
    <form id="product-upload-form" action="upload_process-page-1-A.php" method="POST" enctype="multipart/form-data" class="space-y-6">

      <!-- Product Name -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Product Name <span class="text-red-500">*</span></label>
        <input type="text" name="product_name" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Enter product name" required />
      </div>

      <!-- Main Image -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Main Image <span class="text-red-500">*</span></label>
        <input type="file" name="main_image" accept="image/*" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" onchange="previewMainImage(this)" required />
        <div id="main-image-preview" class="mt-3"></div>
      </div>

      <!-- Sub Images -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Sub Images <span class="text-gray-500 text-sm font-normal">(Optional)</span></label>
        <div class="bg-gray-50 p-4 rounded border border-gray-200 space-y-3">
          <div id="sub-images-section" class="space-y-3">
            <div class="sub-image-item flex gap-3 items-start bg-white p-3 rounded border border-gray-200">
              <input type="file" name="sub_images[]" accept="image/*" class="flex-1 border border-gray-300 p-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" onchange="previewSubImage(this)" />
              <div class="sub-image-preview"></div>
            </div>
          </div>
          <button type="button" onclick="addSubImage()" class="w-full bg-green-600 text-white p-2 rounded font-semibold hover:bg-green-700 transition text-sm">+ Add Sub Image</button>
          <p class="text-xs text-gray-600">Supported: JPG, PNG, GIF, WebP | Recommended: 800x800px</p>
        </div>
      </div>

      <!-- Category -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
        <select name="codename" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
          <option value="">-- Select Category --</option>
          <?php while ($row = mysqli_fetch_assoc($categoryResult)): ?>
            <option value="<?= htmlspecialchars($row['name']) ?>">
              <?= htmlspecialchars($row['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Quantity <span class="text-red-500">*</span></label>
        <input type="number" name="quantity" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" min="0" placeholder="0" required />
      </div>

      <!-- Description -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">Description</label>
        <textarea name="description" rows="4" class="w-full border border-gray-300 p-3 rounded resize-none focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Write product description here..."></textarea>
      </div>

      <!-- Product Types & Variants -->
      <div id="types-section">
        <h3 class="text-lg font-semibold text-gray-700 mb-2">Product Types & Variants</h3>
        <p class="text-sm text-gray-600 mb-3">Add different types of this product with colors and variants</p>
      </div>

      <button type="button" onclick="addType()" class="w-full bg-indigo-600 text-white p-3 rounded font-semibold hover:bg-indigo-700 transition mb-6">+ Add Product Type</button>

      <!-- Buttons -->
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-orange-600 text-white p-3 rounded font-semibold hover:bg-orange-700 transition">Upload Product</button>
        <button type="reset" class="flex-1 bg-gray-400 text-white p-3 rounded font-semibold hover:bg-gray-500 transition" onclick="resetForm()">Reset</button>
      </div>

      <a href="main-adminupdateshop-page-2.php" class="block text-center bg-gray-600 text-white p-3 rounded font-semibold hover:bg-gray-700 transition no-underline mt-3">Update Existing Product</a>
    </form>
  </div>


  <script>
    let typeIndex = 0;
    let subImageCounter = 1;

    // Function to preview main image
    function previewMainImage(input) {
      const preview = document.getElementById('main-image-preview');
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

    // Function to preview sub image
    function previewSubImage(input) {
      const preview = input.parentElement.querySelector('.sub-image-preview');
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

    // Function to add sub-image input
    function addSubImage() {
      subImageCounter++;
      const subImagesSection = document.getElementById('sub-images-section');
      const div = document.createElement('div');
      div.classList.add('sub-image-item', 'flex', 'gap-2', 'mb-3', 'items-start', 'p-3', 'bg-white', 'rounded', 'border');

      div.innerHTML = `
        <div class="flex-1">
          <input type="file" name="sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewSubImage(this)" />
          <div class="sub-image-preview mt-2"></div>
        </div>
        <button type="button" onclick="removeSubImage(this)" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 whitespace-nowrap ml-2">
          Remove
        </button>
      `;

      subImagesSection.appendChild(div);
    }

    // Function to remove sub-image input
    function removeSubImage(button) {
      // Don't remove if it's the last remaining sub image input
      const subImageItems = document.querySelectorAll('.sub-image-item');
      if (subImageItems.length > 1) {
        button.closest('.sub-image-item').remove();
      } else {
        alert('At least one sub image input must remain. You can leave it empty if no sub images are needed.');
      }
    }

    // Function to add product type
    function addType() {
      const section = document.getElementById('types-section');
      const wrapper = document.createElement('div');
      wrapper.classList.add('mb-6', 'border', 'p-4', 'rounded', 'bg-gray-50', 'relative');
      wrapper.setAttribute('data-type-index', typeIndex);

      wrapper.innerHTML = `
        <div class="flex justify-between items-center mb-3">
          <h4 class="font-semibold text-lg text-gray-700">Product Type ${typeIndex + 1}</h4>
          <button type="button" onclick="removeType(this)" class="text-red-600 text-sm hover:underline font-semibold">Remove Type</button>
        </div>
        
        <div class="mb-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block font-medium mb-1">Type Name</label>
              <input type="text" name="type_name[]" placeholder="e.g., Cotton, Leather, Premium" class="border p-2 w-full rounded"  />
            </div>
            <div>
              <label class="block font-medium mb-1">Type Image</label>
              <input type="file" name="type_image[]" accept="image/*" class="w-full border p-2 rounded"  />
            </div>
          </div>
        </div>
        
        <!-- Colors Section -->
        <div class="mb-4">
          <label class="block font-medium mb-2">Colors for this Type:</label>
          <div id="color-section-${typeIndex}" class="space-y-2">
            ${colorRowHTML(typeIndex)}
          </div>
          <button type="button" onclick="addColor(${typeIndex})" class="bg-green-500 text-white px-3 py-1 rounded text-sm mt-2 hover:bg-green-600">+ Add Color</button>
        </div>
        
        <!-- Variants Section (Size, etc.) -->
<div>
  <label class="block font-medium mb-2">Product Variants (Size, Dimensions, Weight):</label>
  <div class="text-xs text-gray-600 mb-2">
    Add variants with their dimensions and weight. Useful for furniture and construction materials.
  </div>
  <div id="variant-section-${typeIndex}" class="space-y-2">
    ${variantRowHTML(typeIndex)}
  </div>
  <button type="button" onclick="addVariant(${typeIndex})" class="bg-blue-500 text-white px-3 py-1 rounded text-sm mt-2 hover:bg-blue-600">+ Add Variant</button>
</div>
      `;

      section.appendChild(wrapper);
      typeIndex++;
    }

    // Updated colorRowHTML function in adminshop.php
    // Replace the existing colorRowHTML with this:

    function colorRowHTML(index) {
      return `
    <div class="color-row grid grid-cols-2 md:grid-cols-6 gap-2 items-center bg-green-50 p-3 rounded border">
      <input type="text" name="color_name[${index}][]" placeholder="Color Name" class="border p-2 rounded"  />
      
      <input type="text" name="color_code[${index}][]" placeholder="#hex code" class="border p-2 rounded" />
      
      <div class="flex flex-col">
        <label class="text-xs text-gray-600 mb-1">Main Image</label>
        <input type="file" name="color_image[${index}][]" accept="image/*" class="border p-2 rounded text-xs" />
      </div>
      
      <div class="flex flex-col">
        <label class="text-xs text-gray-600 mb-1">Secondary Image</label>
        <input type="file" name="color_image2[${index}][]" accept="image/*" class="border p-2 rounded text-xs" />
      </div>
      
      <input type="number" step="0.01" name="color_price[${index}][]" placeholder="Price" class="border p-2 rounded"  />
      
      <button type="button" onclick="removeColor(this)" class="bg-red-500 text-white px-2 py-1 rounded text-sm hover:bg-red-600">Remove</button>
    </div>
  `;
    }

    // HTML template for variant row
    function variantRowHTML(index) {
      return `
    <div class="variant-row bg-blue-50 p-4 rounded border mb-3">
      <!-- Row 1: Basic Info -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
        <div>
          <label class="text-xs font-medium text-gray-600">Size Name</label>
          <input type="text" name="variant_size[${index}][]" placeholder="Size" class="border p-2 rounded w-full text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Variant Name</label>
          <input type="text" name="variant_namevariant[${index}][]" placeholder="Variant Name" class="border p-2 rounded w-full text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Original Price</label>
          <input type="number" step="0.01" name="variant_original_price[${index}][]" placeholder="Original Price" class="border p-2 rounded w-full text-sm" oninput="copyToBasePrice(this)" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Final Price</label>
          <input type="number" step="0.01" name="variant_price[${index}][]" placeholder="Final Price" class="border p-2 rounded w-full text-sm" />
        </div>
      </div>

      <!-- Row 2: Pricing -->
      <div class="grid grid-cols-2 md:grid-cols-2 gap-2 mb-2">
        <div>
          <label class="text-xs font-medium text-gray-600">Percentage (%)</label>
          <input type="number" name="variant_percent[${index}][]" placeholder="%" class="border p-2 rounded w-full text-sm" oninput="updatePriceFromPercent(this)" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-600">Discount</label>
          <input type="number" step="0.01" name="variant_discount[${index}][]" placeholder="Discount" class="border p-2 rounded w-full text-sm" />
        </div>
      </div>

      <!-- Row 3: Dimensions -->
      <div class="bg-white p-3 rounded mb-2">
        <label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
          <div>
            <label class="text-xs font-medium text-gray-600">Width</label>
            <input type="number" step="0.01" name="variant_width[${index}][]" placeholder="Width" class="border p-2 rounded w-full text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600">Height</label>
            <input type="number" step="0.01" name="variant_height[${index}][]" placeholder="Height" class="border p-2 rounded w-full text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600">Length</label>
            <input type="number" step="0.01" name="variant_length[${index}][]" placeholder="Length" class="border p-2 rounded w-full text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600">Unit</label>
            <select name="variant_dimension_unit[${index}][]" class="border p-2 rounded w-full text-sm">
              <option value="mm">mm (Millimeters)</option>
              <option value="cm" selected>cm (Centimeters)</option>
              <option value="inches">inches</option>
              <option value="m">m (Meters)</option>
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
            <input type="number" step="0.01" name="variant_weight[${index}][]" placeholder="Weight" class="border p-2 rounded w-full text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600">Unit</label>
            <select name="variant_weight_unit[${index}][]" class="border p-2 rounded w-full text-sm">
              <option value="g">g (Grams)</option>
              <option value="kg" selected>kg (Kilograms)</option>
              <option value="lbs">lbs (Pounds)</option>
              <option value="oz">oz (Ounces)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Remove Button -->
      <div class="flex justify-end">
        <button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button>
      </div>
    </div>
  `;
    }

    // Function to add color
    function addColor(index) {
      const colorSection = document.getElementById(`color-section-${index}`);
      const div = document.createElement('div');
      div.innerHTML = colorRowHTML(index);
      colorSection.appendChild(div);
    }

    // Function to add variant
    function addVariant(index) {
      const variantSection = document.getElementById(`variant-section-${index}`);
      const div = document.createElement('div');
      div.innerHTML = variantRowHTML(index);
      variantSection.appendChild(div);
    }

    // Function to remove color
    function removeColor(button) {
      const colorSection = button.closest('[id^="color-section-"]');
      const colorRows = colorSection.querySelectorAll('.color-row');

      if (colorRows.length > 1) {
        button.closest('.color-row').remove();
      } else {
        alert('At least one color must remain for this product type.');
      }
    }

    // Function to remove variant
    function removeVariant(button) {
      const variantSection = button.closest('[id^="variant-section-"]');
      const variantRows = variantSection.querySelectorAll('.variant-row');

      if (variantRows.length > 1) {
        button.closest('.variant-row').remove();
      } else {
        alert('At least one variant must remain for this product type.');
      }
    }

    // Function to remove product type
    function removeType(button) {
      const typesSection = document.getElementById('types-section');
      const typeWrappers = typesSection.querySelectorAll('[data-type-index]');

      if (typeWrappers.length > 0) {
        button.closest('[data-type-index]').remove();
      }
    }

    // Function to update price from percent
    function updatePriceFromPercent(percentInput) {
      const parent = percentInput.closest('.variant-row');
      const originalPriceInput = parent.querySelector('input[name^="variant_original_price"]');
      const priceInput = parent.querySelector('input[name^="variant_price"]');

      // Use original price as base, fall back to current price if original is empty
      const basePrice = parseFloat(originalPriceInput.value) || parseFloat(priceInput.value) || 0;
      const percent = parseFloat(percentInput.value) || 0;

      if (basePrice > 0) {
        const finalPrice = basePrice + (basePrice * percent / 100);
        priceInput.value = finalPrice.toFixed(2);

        // Create a visual indicator of the calculated price
        let indicator = parent.querySelector('.price-indicator');
        if (!indicator) {
          indicator = document.createElement('small');
          indicator.classList.add('price-indicator', 'text-gray-600', 'ml-1');
          percentInput.parentNode.appendChild(indicator);
        }
        indicator.textContent = `Final: ₱${finalPrice.toFixed(2)}`;
      }
    }

    // Function to copy original price to base price
    function copyToBasePrice(originalPriceInput) {
      const parent = originalPriceInput.closest('.variant-row');
      const basePriceInput = parent.querySelector('input[name^="variant_price"]');

      // Copy the original price value to base price
      basePriceInput.value = originalPriceInput.value;

      // Also trigger the percent calculation if there's a percent value
      const percentInput = parent.querySelector('input[name^="variant_percent"]');
      if (percentInput && percentInput.value) {
        updatePriceFromPercent(percentInput);
      }
    }

    // Function to reset form
    function resetForm() {
      if (confirm('Are you sure you want to reset the entire form? All data will be lost.')) {
        document.getElementById('main-image-preview').innerHTML = '';
        document.querySelectorAll('.sub-image-preview').forEach(preview => preview.innerHTML = '');
        document.getElementById('types-section').innerHTML = '<h3 class="text-lg font-semibold mb-2 text-gray-700">Product Types & Variants</h3><div class="text-sm text-gray-600 mb-3">Add different types of this product (e.g., different materials, styles, etc.) with their own colors and variants.</div>';

        // Reset sub images section to initial state
        const subImagesSection = document.getElementById('sub-images-section');
        subImagesSection.innerHTML = `
          <div class="sub-image-item flex gap-2 mb-3 items-start p-3 bg-white rounded border">
            <div class="flex-1">
              <input type="file" name="sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewSubImage(this)" />
              <div class="sub-image-preview mt-2"></div>
            </div>
            <button type="button" onclick="addSubImage()" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 whitespace-nowrap ml-2">
              + Add More
            </button>
          </div>
        `;

        typeIndex = 0;
        subImageCounter = 1;
      }
    }

    // FIXED: Add form validation ONLY to product upload form, NOT CSV import
    document.getElementById('product-upload-form').addEventListener('submit', function(e) {
      const productName = document.querySelector('#product-upload-form input[name="product_name"]').value.trim();
      const mainImage = document.querySelector('#product-upload-form input[name="main_image"]').files.length;
      const category = document.querySelector('#product-upload-form select[name="codename"]').value;
      const quantity = document.querySelector('#product-upload-form input[name="quantity"]').value;
      const description = document.querySelector('#product-upload-form textarea[name="description"]').value.trim();

      if (!productName || !mainImage || !category || !quantity || !description) {
        e.preventDefault();
        alert('Please fill in all required fields: Product Name, Main Image, Category, Quantity, and Description.');
        return false;
      }

      if (parseInt(quantity) < 0) {
        e.preventDefault();
        alert('Quantity cannot be negative.');
        return false;
      }

      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Uploading...';

      // Re-enable button after 30 seconds (in case of errors)
      setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Upload Product';
      }, 30000);
    });

    // CSV import form - NO VALIDATION
    document.getElementById('csv-import-form').addEventListener('submit', function(e) {
      // Allow CSV import without validation
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Importing...';

      // Re-enable button after 30 seconds (in case of errors)
      setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Import CSV';
      }, 30000);
    });

    // Auto-save form data to prevent data loss (optional) - only for product form
    function saveFormData() {
      const formData = new FormData(document.getElementById('product-upload-form'));
      const data = {};
      for (let [key, value] of formData.entries()) {
        if (key !== 'main_image' && !key.includes('image')) { // Don't save file data
          data[key] = value;
        }
      }
      localStorage.setItem('product_form_backup', JSON.stringify(data));
    }

    // Save form data every 30 seconds
    setInterval(saveFormData, 30000);
  </script>
</body>

</html>