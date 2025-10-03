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

  <div class=" bg-white p-6 rounded-lg shadow mt-5">
    <h2 class="text-2xl font-bold mb-4 text-orange-600">Add Product</h2>
    
    <!-- CSV Import Section - NO VALIDATION -->
    <div class="bg-white p-6 rounded-lg shadow mt-5">
      <h2 class="text-2xl font-bold mb-4 text-orange-600">Import Products via CSV</h2>
      <form id="csv-import-form" action="import_csv.php" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
          <label class="block font-semibold mb-1">CSV File</label>
          <input type="file" name="csv_file" accept=".csv" class="w-full border p-2 rounded">
        </div>
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Import CSV</button>
      </form>
    </div>

    <!-- Product Upload Form -->
    <form id="product-upload-form" action="upload_process.php" method="POST" enctype="multipart/form-data" class="mt-5">
      
      <!-- Product Name -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Product Name</label>
        <input type="text" name="product_name"  class="w-full border p-2 rounded" />
      </div>

      <!-- Main Image -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Main Image</label>
        <input type="file" name="main_image" accept="image/*"  class="w-full border p-2 rounded" onchange="previewMainImage(this)" />
        <div id="main-image-preview" class="mt-2"></div>
      </div>

      <!-- Sub Images Section -->
      <div class="mb-4">
        <label class="block font-semibold mb-2">Sub Images (Optional)</label>
        <div class="bg-gray-50 p-4 rounded border">
          <div id="sub-images-section">
            <!-- Initial sub image input -->
            <div class="sub-image-item flex gap-2 mb-3 items-start p-3 bg-white rounded border">
              <div class="flex-1">
                <input type="file" name="sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewSubImage(this)" />
                <div class="sub-image-preview mt-2"></div>
              </div>
              <button type="button" onclick="addSubImage()" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 whitespace-nowrap ml-2">
                + Add More
              </button>
            </div>
          </div>
          <div class="mt-2 text-sm text-gray-600">
            <p>• You can add unlimited sub images</p>
            <p>• Supported formats: JPG, PNG, GIF, WebP</p>
            <p>• Recommended size: 800x800px or higher</p>
          </div>
        </div>
      </div>

      <!-- Category -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Category</label>
        <select name="codename"  class="w-full border p-2 rounded">
          <option value="">-- Select Category --</option>
          <?php while ($row = mysqli_fetch_assoc($categoryResult)): ?>
            <option value="<?= htmlspecialchars($row['name']) ?>">
              <?= htmlspecialchars($row['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Quantity -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Quantity</label>
        <input type="number" name="quantity"  class="w-full border p-2 rounded" min="0" />
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Description</label>
        <textarea name="description" rows="4" class="w-full border p-2 rounded resize-none" placeholder="Write product description here..." ></textarea>
      </div>

      <!-- Dynamic Types + Variants -->
      <div id="types-section" class="mb-4">
        <h3 class="text-lg font-semibold mb-2 text-gray-700">Product Types & Variants</h3>
        <div class="text-sm text-gray-600 mb-3">
          Add different types of this product (e.g., different materials, styles, etc.) with their own colors and variants.
        </div>
        <!-- Dynamic product types will appear here -->
      </div>

      <button type="button" onclick="addType()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mb-4">+ Add Product Type</button>

      <!-- Submit Buttons -->
      <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-orange-600 text-white px-6 py-3 rounded hover:bg-orange-700 font-semibold">
          Upload Product
        </button>
        <button type="reset" class="bg-gray-500 text-white px-6 py-3 rounded hover:bg-gray-600 font-semibold" onclick="resetForm()">
          Reset Form
        </button>
      </div>

      <!-- Update Product Link -->
      <div class="mt-4">
        <a href="adminupdateshop" class="inline-block bg-orange-600 text-white px-6 py-3 rounded hover:bg-orange-700 font-semibold text-decoration-none">
          Update Existing Product
        </a>
      </div>
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
          <label class="block font-medium mb-2">Other Variants for this Type:</label>
          <div id="variant-section-${typeIndex}" class="space-y-2">
            ${variantRowHTML(typeIndex)}
          </div>
          <button type="button" onclick="addVariant(${typeIndex})" class="bg-blue-500 text-white px-3 py-1 rounded text-sm mt-2 hover:bg-blue-600">+ Add Variant</button>
        </div>
      `;

      section.appendChild(wrapper);
      typeIndex++;
    }

    // HTML template for color row
    function colorRowHTML(index) {
      return `
        <div class="color-row grid grid-cols-2 md:grid-cols-5 gap-2 items-center bg-green-50 p-3 rounded border">
          <input type="text" name="color_name[${index}][]" placeholder="Color Name" class="border p-2 rounded"  />
          <input type="text" name="color_code[${index}][]" placeholder="#hex code" class="border p-2 rounded" />
          <input type="file" name="color_image[${index}][]" accept="image/*" class="border p-2 rounded" />
          <input type="number" step="0.01" name="color_price[${index}][]" placeholder="Price" class="border p-2 rounded"  />
          <button type="button" onclick="removeColor(this)" class="bg-red-500 text-white px-2 py-1 rounded text-sm hover:bg-red-600">Remove</button>
        </div>
      `;
    }

    // HTML template for variant row
    function variantRowHTML(index) {
      return `
        <div class="variant-row grid grid-cols-2 md:grid-cols-6 gap-2 items-center bg-blue-50 p-3 rounded border">
          <input type="text" name="variant_size[${index}][]" placeholder="Size/Type" class="border p-2 rounded" />
          <input type="number" step="0.01" name="variant_original_price[${index}][]" placeholder="Original Price" class="border p-2 rounded"  oninput="copyToBasePrice(this)" />
          <input type="number" step="0.01" name="variant_price[${index}][]" placeholder="Base Price" class="border p-2 rounded" />
          <input type="number" name="variant_percent[${index}][]" placeholder="%" class="border p-2 rounded" oninput="updatePriceFromPercent(this)" />
          <input type="text" name="variant_namevariant[${index}][]" placeholder="Variant Name" class="border p-2 rounded" />
          <input type="number" step="0.01" name="variant_discount[${index}][]" placeholder="Discount" class="border p-2 rounded" />
          <button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-2 py-1 rounded text-sm hover:bg-red-600">Remove</button>
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