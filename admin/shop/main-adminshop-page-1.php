<?php
//main-adminshop-page-1.php - MERGED: Upload + Product List + Descriptions
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$_SESSION['last_activity'] = time();

// ── Current user info ────────────────────────────────────────────────────────
$current_user = $_SESSION['noble_user'];
$current_role = $_SESSION['noble_role'] ?? ''; // adjust key if your role session var differs

// Determine if the user can see ALL products
$is_superadmin = ($current_role === 'superadmin');

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['products', 'product_types', 'product_variants', 'product_colors'];
foreach ($tables as $table) {
    $result_ai = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row_ai = $result_ai->fetch_assoc();
    $max_id = (int) $row_ai['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// ✅ Fetch categories
$categoryQuery = "SELECT * FROM categories";
$categoryResult = mysqli_query($conn, $categoryQuery);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Shop - Noble Home</title>
    <style>
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-gray-100">

    <?php include '../navbar/top.php'; ?>

    <!-- ── WHO IS VIEWING BANNER ─────────────────────────────────────────── -->
    <div class="max-w-4xl mx-auto mt-4 px-2">
        <?php if ($is_superadmin): ?>
            <div class="flex items-center gap-2 bg-purple-50 border border-purple-200 text-purple-800 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-crown text-purple-500"></i>
                <span>Logged in as <strong><?= htmlspecialchars($current_user) ?></strong> (Superadmin) — viewing <strong>all products</strong></span>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2 bg-orange-50 border border-orange-200 text-orange-800 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-user text-orange-500"></i>
                <span>Logged in as <strong><?= htmlspecialchars($current_user) ?></strong> — showing only <strong>your uploaded products</strong></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===================================================== -->
    <!-- SECTION 1: UPLOAD PRODUCT                            -->
    <!-- ===================================================== -->
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow mt-4 mb-8">

        <!-- Product Upload Form -->
        <h2 class="text-3xl font-bold mb-8 text-gray-800">Upload New Product</h2>
        <form id="product-upload-form" action="upload_process-page-1-A.php" method="POST" enctype="multipart/form-data"
            class="space-y-6">

            <!-- Product Name -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Product Name <span
                        class="text-red-500">*</span></label>
                <input type="text" name="product_name"
                    class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Enter product name" required />
            </div>

            <!-- Main Image -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Main Image <span
                        class="text-red-500">*</span></label>
                <input type="file" name="main_image" accept="image/*"
                    class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                    onchange="previewMainImage(this)" required />
                <div id="main-image-preview" class="mt-3"></div>
            </div>

            <!-- Sub Images -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Sub Images <span
                        class="text-gray-500 text-sm font-normal">(Optional)</span></label>
                <div class="bg-gray-50 p-4 rounded border border-gray-200 space-y-3">
                    <div id="sub-images-section" class="space-y-3">
                        <div class="sub-image-item flex gap-3 items-start bg-white p-3 rounded border border-gray-200">
                            <input type="file" name="sub_images[]" accept="image/*"
                                class="flex-1 border border-gray-300 p-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                                onchange="previewSubImage(this)" />
                            <div class="sub-image-preview"></div>
                        </div>
                    </div>
                    <button type="button" onclick="addSubImage()"
                        class="w-full bg-green-600 text-white p-2 rounded font-semibold hover:bg-green-700 transition text-sm">
                        + Add Sub Image
                    </button>
                    <p class="text-xs text-gray-600">Supported: JPG, PNG, GIF, WebP | Recommended: 800x800px</p>
                </div>
            </div>

            <!-- Category -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Category <span
                        class="text-red-500">*</span></label>
                <select name="codename"
                    class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                    required>
                    <option value="">-- Select Category --</option>
                    <?php while ($row = mysqli_fetch_assoc($categoryResult)): ?>
                        <option value="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Quantity -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Quantity <span
                        class="text-red-500">*</span></label>
                <input type="number" name="quantity"
                    class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                    min="0" placeholder="0" required />
            </div>

            <!-- Description -->
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="4"
                    class="w-full border border-gray-300 p-3 rounded resize-none focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Write product description here..."></textarea>
            </div>

            <!-- ── EXTENDED DESCRIPTIONS ───────────────────────────── -->
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center gap-2">
                    <i class="fas fa-align-left text-gray-500 text-sm"></i>
                    <span class="font-semibold text-gray-700 text-sm">Extended Descriptions</span>
                    <span class="ml-auto text-xs text-gray-400">Optional — you can fill these in now or later from the
                        Product List tab</span>
                </div>

                <!-- Descrip1 -->
                <div class="p-5 border-b border-gray-100 border-l-4 border-l-yellow-400">
                    <label class="block text-sm font-bold text-gray-800 mb-1">
                        <i class="fas fa-align-left text-yellow-500 mr-1"></i>
                        Description 1 – Complete Product Details
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Full product info: name, size, color, materials, features,
                        shipping, etc.</p>
                    <textarea name="descrip1" id="upload-descrip1" rows="6"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 resize-vertical text-sm"
                        placeholder="Enter complete product description..."></textarea>
                    <p class="text-xs text-gray-400 mt-1">Chars: <span id="upload-cnt1"
                            class="font-semibold text-yellow-500">0</span></p>
                </div>

                <!-- Descrip6 -->
                <div class="p-5 border-b border-gray-100 border-l-4 border-l-indigo-400">
                    <label class="block text-sm font-bold text-gray-800 mb-1">
                        <i class="fas fa-box text-indigo-500 mr-1"></i>
                        Description 6 – Unit Information
                    </label>
                    <textarea name="descrip6" id="upload-descrip6" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-vertical text-sm"
                        placeholder="Enter unit information..."></textarea>
                    <p class="text-xs text-gray-400 mt-1">Chars: <span id="upload-cnt6"
                            class="font-semibold text-indigo-500">0</span></p>
                </div>

                <!-- Descrip7 -->
                <div class="p-5 border-l-4 border-l-cyan-400">
                    <label class="block text-sm font-bold text-gray-800 mb-1">
                        <i class="fas fa-cogs text-cyan-500 mr-1"></i>
                        Description 7 – Specifications
                    </label>
                    <p class="text-xs text-gray-500 mb-2">e.g., Box, Set, Bundle</p>
                    <textarea name="descrip7" id="upload-descrip7" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-vertical text-sm"
                        placeholder="Box, Set, Bundle..."></textarea>
                    <p class="text-xs text-gray-400 mt-1">Chars: <span id="upload-cnt7"
                            class="font-semibold text-cyan-500">0</span></p>
                </div>
            </div>

            <!-- Product Types & Variants -->
            <div id="types-section">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Product Types & Variants</h3>
                <p class="text-sm text-gray-600 mb-3">Add different types of this product with colors and variants</p>
            </div>

            <button type="button" onclick="addType()"
                class="w-full bg-indigo-600 text-white p-3 rounded font-semibold hover:bg-indigo-700 transition mb-6">
                + Add Product Type
            </button>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 bg-orange-600 text-white p-3 rounded font-semibold hover:bg-orange-700 transition">
                    Upload Product
                </button>
                <button type="reset"
                    class="flex-1 bg-gray-400 text-white p-3 rounded font-semibold hover:bg-gray-500 transition"
                    onclick="resetForm()">
                    Reset
                </button>
            </div>

            <a href="main-adminupdateshop-page-2.php"
                class="block text-center bg-gray-600 text-white p-3 rounded font-semibold hover:bg-gray-700 transition no-underline mt-3">
                Update Existing Product
            </a>
        </form>
    </div>


    <!-- ============================================================ -->
    <!-- JAVASCRIPT                                                   -->
    <!-- ============================================================ -->
    <script>
        // ── Superadmin: filter table rows by uploader ──────────────────
        function filterByUploader(uploader) {
            document.querySelectorAll('.product-row').forEach(row => {
                if (!uploader || row.dataset.uploader === uploader) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // ── UPLOAD FORM FUNCTIONS ──────────────────────────────────────
        let typeIndex = 0, subImageCounter = 1;

        function previewMainImage(input) {
            const preview = document.getElementById('main-image-preview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview border';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewSubImage(input) {
            const preview = input.parentElement.querySelector('.sub-image-preview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview border';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function addSubImage() {
            subImageCounter++;
            const section = document.getElementById('sub-images-section');
            const div = document.createElement('div');
            div.className = 'sub-image-item flex gap-2 mb-3 items-start p-3 bg-white rounded border';
            div.innerHTML = `
        <div class="flex-1">
            <input type="file" name="sub_images[]" accept="image/*" class="w-full border p-2 rounded" onchange="previewSubImage(this)" />
            <div class="sub-image-preview mt-2"></div>
        </div>
        <button type="button" onclick="removeSubImage(this)" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 whitespace-nowrap ml-2">Remove</button>
    `;
            section.appendChild(div);
        }

        function removeSubImage(button) {
            const items = document.querySelectorAll('.sub-image-item');
            if (items.length > 1) {
                button.closest('.sub-image-item').remove();
            } else {
                alert('At least one sub image input must remain.');
            }
        }

        function addType() {
            const section = document.getElementById('types-section');
            if (section.querySelector('[data-type-index]')) {
                alert('One product type only is allowed.');
                return;
            }
            typeIndex = 0;
            document.querySelector('button[onclick="addType()"]').classList.add('hidden');
            const wrapper = document.createElement('div');
            wrapper.className = 'mb-6 border p-4 rounded bg-gray-50 relative';
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
                    <input type="text" name="type_name[]" placeholder="e.g., Cotton, Leather, Premium" class="border p-2 w-full rounded" />
                </div>
                <div>
                    <label class="block font-medium mb-1">Type Image</label>
                    <input type="file" name="type_image[]" accept="image/*" class="w-full border p-2 rounded" />
                </div>
            </div>
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-2">Colors for this Type:</label>
            <div id="color-section-${typeIndex}" class="space-y-2">${colorRowHTML(typeIndex)}</div>
            <button type="button" onclick="addColor(${typeIndex})" class="bg-green-500 text-white px-3 py-1 rounded text-sm mt-2 hover:bg-green-600">+ Add Color</button>
        </div>
        <div>
            <label class="block font-medium mb-2">Product Variants (Size, Dimensions, Weight):</label>
            <div class="text-xs text-gray-600 mb-2">Add variants with their dimensions and weight.</div>
            <div id="variant-section-${typeIndex}" class="space-y-2">${variantRowHTML(typeIndex)}</div>
            <button type="button" onclick="addVariant(${typeIndex})" class="bg-blue-500 text-white px-3 py-1 rounded text-sm mt-2 hover:bg-blue-600">+ Add Variant</button>
        </div>
    `;
            section.appendChild(wrapper);
            typeIndex++;
        }

        function colorRowHTML(index) {
            return `
    <div class="color-row grid grid-cols-2 md:grid-cols-6 gap-2 items-center bg-green-50 p-3 rounded border">
        <input type="text" name="color_name[${index}][]" placeholder="Color Name" class="border p-2 rounded" />
        <input type="text" name="color_code[${index}][]" placeholder="#hex code" class="border p-2 rounded" />
        <div class="flex flex-col">
            <label class="text-xs text-gray-600 mb-1">Main Image</label>
            <input type="file" name="color_image[${index}][]" accept="image/*" class="border p-2 rounded text-xs" />
        </div>
        <div class="flex flex-col">
            <label class="text-xs text-gray-600 mb-1">Secondary Image</label>
            <input type="file" name="color_image2[${index}][]" accept="image/*" class="border p-2 rounded text-xs" />
        </div>
        <input type="number" step="0.01" name="color_price[${index}][]" placeholder="Price" class="border p-2 rounded" />
        <button type="button" onclick="removeColor(this)" class="bg-red-500 text-white px-2 py-1 rounded text-sm hover:bg-red-600">Remove</button>
    </div>`;
        }

        function variantRowHTML(index) {
            return `
    <div class="variant-row bg-blue-50 p-4 rounded border mb-3">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2">
            <div><label class="text-xs font-medium text-gray-600">Size Name</label>
                <input type="text" name="variant_size[${index}][]" placeholder="Size" class="border p-2 rounded w-full text-sm" /></div>
            <div><label class="text-xs font-medium text-gray-600">Variant Name</label>
                <input type="text" name="variant_namevariant[${index}][]" placeholder="Variant Name" class="border p-2 rounded w-full text-sm" /></div>
            <div><label class="text-xs font-medium text-gray-600">Original Price</label>
                <input type="number" step="0.01" name="variant_original_price[${index}][]" placeholder="Original Price" class="border p-2 rounded w-full text-sm" oninput="copyToBasePrice(this)" /></div>
            <div><label class="text-xs font-medium text-gray-600">Final Price</label>
                <input type="number" step="0.01" name="variant_price[${index}][]" placeholder="Final Price" class="border p-2 rounded w-full text-sm" /></div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-2">
            <div><label class="text-xs font-medium text-gray-600">Percentage (%)</label>
                <input type="number" name="variant_percent[${index}][]" placeholder="%" class="border p-2 rounded w-full text-sm" oninput="updatePriceFromPercent(this)" /></div>
            <div><label class="text-xs font-medium text-gray-600">Discount</label>
                <input type="number" step="0.01" name="variant_discount[${index}][]" placeholder="Discount" class="border p-2 rounded w-full text-sm" /></div>
        </div>
        <div class="bg-white p-3 rounded mb-2">
            <label class="text-xs font-semibold text-gray-700 block mb-2">📏 Dimensions</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div><label class="text-xs font-medium text-gray-600">Width</label>
                    <input type="number" step="0.01" name="variant_width[${index}][]" placeholder="Width" class="border p-2 rounded w-full text-sm" /></div>
                <div><label class="text-xs font-medium text-gray-600">Height</label>
                    <input type="number" step="0.01" name="variant_height[${index}][]" placeholder="Height" class="border p-2 rounded w-full text-sm" /></div>
                <div><label class="text-xs font-medium text-gray-600">Length</label>
                    <input type="number" step="0.01" name="variant_length[${index}][]" placeholder="Length" class="border p-2 rounded w-full text-sm" /></div>
                <div><label class="text-xs font-medium text-gray-600">Unit</label>
                    <select name="variant_dimension_unit[${index}][]" class="border p-2 rounded w-full text-sm">
                        <option value="mm">mm</option><option value="cm" selected>cm</option>
                        <option value="inches">inches</option><option value="m">m</option>
                    </select></div>
            </div>
        </div>
        <div class="bg-white p-3 rounded mb-2">
            <label class="text-xs font-semibold text-gray-700 block mb-2">⚖️ Weight</label>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-xs font-medium text-gray-600">Weight</label>
                    <input type="number" step="0.01" name="variant_weight[${index}][]" placeholder="Weight" class="border p-2 rounded w-full text-sm" /></div>
                <div><label class="text-xs font-medium text-gray-600">Unit</label>
                    <select name="variant_weight_unit[${index}][]" class="border p-2 rounded w-full text-sm">
                        <option value="g">g</option><option value="kg" selected>kg</option>
                        <option value="lbs">lbs</option><option value="oz">oz</option>
                    </select></div>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="button" onclick="removeVariant(this)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Remove Variant</button>
        </div>
    </div>`;
        }

        function addColor(index) {
            const section = document.getElementById(`color-section-${index}`);
            const div = document.createElement('div');
            div.innerHTML = colorRowHTML(index);
            section.appendChild(div);
        }

        function addVariant(index) {
            const section = document.getElementById(`variant-section-${index}`);
            const div = document.createElement('div');
            div.innerHTML = variantRowHTML(index);
            section.appendChild(div);
        }

        function removeColor(button) {
            const section = button.closest('[id^="color-section-"]');
            if (section.querySelectorAll('.color-row').length > 1) {
                button.closest('.color-row').remove();
            } else {
                alert('At least one color must remain.');
            }
        }

        function removeVariant(button) {
            const section = button.closest('[id^="variant-section-"]');
            if (section.querySelectorAll('.variant-row').length > 1) {
                button.closest('.variant-row').remove();
            } else {
                alert('At least one variant must remain.');
            }
        }

        function removeType(button) {
            button.closest('[data-type-index]').remove();
            document.querySelector('button[onclick="addType()"]').classList.remove('hidden');
        }

        function updatePriceFromPercent(percentInput) {
            const parent = percentInput.closest('.variant-row');
            const originalPriceInput = parent.querySelector('input[name^="variant_original_price"]');
            const priceInput = parent.querySelector('input[name^="variant_price"]');
            const basePrice = parseFloat(originalPriceInput.value) || parseFloat(priceInput.value) || 0;
            const percent = parseFloat(percentInput.value) || 0;
            if (basePrice > 0) {
                priceInput.value = (basePrice + (basePrice * percent / 100)).toFixed(2);
            }
        }

        function copyToBasePrice(originalPriceInput) {
            const parent = originalPriceInput.closest('.variant-row');
            const basePriceInput = parent.querySelector('input[name^="variant_price"]');
            basePriceInput.value = originalPriceInput.value;
            const percentInput = parent.querySelector('input[name^="variant_percent"]');
            if (percentInput && percentInput.value) updatePriceFromPercent(percentInput);
        }

        function resetForm() {
            if (confirm('Reset the entire form? All data will be lost.')) {
                document.getElementById('main-image-preview').innerHTML = '';
                document.getElementById('types-section').innerHTML = `
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Product Types & Variants</h3>
            <p class="text-sm text-gray-600 mb-3">Add different types of this product with colors and variants</p>`;
                document.getElementById('sub-images-section').innerHTML = `
            <div class="sub-image-item flex gap-3 items-start bg-white p-3 rounded border border-gray-200">
                <input type="file" name="sub_images[]" accept="image/*" class="flex-1 border border-gray-300 p-2 rounded" onchange="previewSubImage(this)" />
                <div class="sub-image-preview"></div>
            </div>`;
                typeIndex = 0; subImageCounter = 1;
                document.querySelector('button[onclick="addType()"]').classList.remove('hidden');
            }
        }

        document.getElementById('product-upload-form').addEventListener('submit', function (e) {
            const name = this.querySelector('input[name="product_name"]').value.trim();
            const img = this.querySelector('input[name="main_image"]').files.length;
            const cat = this.querySelector('select[name="codename"]').value;
            const qty = this.querySelector('input[name="quantity"]').value;
            const desc = this.querySelector('textarea[name="description"]').value.trim();
            if (!name || !img || !cat || !qty || !desc) {
                e.preventDefault();
                alert('Please fill in all required fields: Product Name, Main Image, Category, Quantity, and Description.');
                return;
            }
            if (parseInt(qty) < 0) { e.preventDefault(); alert('Quantity cannot be negative.'); return; }
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Uploading...';
            setTimeout(() => { btn.disabled = false; btn.textContent = 'Upload Product'; }, 30000);
        });

        // Auto-save draft (text fields only)
        setInterval(() => {
            const form = document.getElementById('product-upload-form');
            if (!form) return;
            const data = {};
            ['product_name', 'quantity', 'description'].forEach(key => {
                const el = form.querySelector(`[name="${key}"]`);
                if (el) data[key] = el.value;
            });
            localStorage.setItem('product_form_backup', JSON.stringify(data));
        }, 30000);
    </script>
</body>

</html>