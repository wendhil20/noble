<?php
include '../connection/connect.php';


if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];


  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
  }
}

$product_id = $_GET['id'] ?? 0;

if (!$product_id || !is_numeric($product_id) || $product_id <= 0) {
  echo "Invalid product ID.";
  exit;
}

// Fetch product
$stmt = $conn->prepare("SELECT id, product_name, codename, quantity, price, main_image, description FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
  echo "Product not found.";
  exit;
}

// Fetch product colors
$stmt = $conn->prepare("SELECT id, color_name, color_code, price, image FROM product_colors WHERE product_id = ? ORDER BY color_name");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$colors_result = $stmt->get_result();
$product_colors = [];
while ($row = $colors_result->fetch_assoc()) {
  $product_colors[] = $row;
}

// Fetch product types and variants
$stmt = $conn->prepare("SELECT pt.*, pv.id as variant_id, pv.namevariant, pv.color, pv.size, pv.price as variant_price, pv.percent, pv.discount, pv.image as variant_image FROM product_types pt LEFT JOIN product_variants pv ON pt.id = pv.type_id WHERE pt.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$types_result = $stmt->get_result();

$types_data = [];
while ($row = $types_result->fetch_assoc()) {
  $type_name = $row['type_name'];
  if (!isset($types_data[$type_name])) {
    $types_data[$type_name] = [
      'id' => $row['id'],
      'name' => $type_name,
      'image' => $row['type_image'],
      'variants' => []
    ];
  }
  if ($row['variant_id']) {
    $types_data[$type_name]['variants'][] = $row;
  }
}

$codename = 'furniture';
$stmt = $conn->prepare("SELECT id, product_name, codename, quantity, main_image FROM products WHERE codename = ? AND id != ?");
$stmt->bind_param("si", $codename, $product_id);
$stmt->execute();
$related_products = $stmt->get_result();

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($product['product_name']) ?> - Noble Home</title>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .selected {
      border-color: #f97316;
      background: #fed7aa;
    }

    .color-selected {
      border-color: #f97316;
      border-width: 3px;
      box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.2);
    }

    .fade-in {
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .color-swatch {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #e5e7eb;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .color-swatch:hover {
      transform: scale(1.1);
      border-color: #9ca3af;
    }
  </style>
</head>

<body class="bg-gray-50">
  <?php include 'navbar/top.php'; ?>

  <!-- Breadcrumb -->
  <nav class="px-4 py-4 text-sm">
    <a href="index" class="text-blue-600">Home</a> /
    <a href="shop" class="text-blue-600">Products</a> /
    <span><?= htmlspecialchars($product['product_name']) ?></span>
  </nav>

  <div class="px-4 pb-8 min-h-screen flex flex-col">
    <div class="bg-white rounded-lg shadow-lg overflow-hidden flex-grow">
      <div class="grid md:grid-cols-2 gap-8 p-8 items-start">

        <!-- Product Image Section -->
        <div class="text-center">
          <div class="aspect-square max-w-md mx-auto mb-4 relative">
            <img id="main-product-image"
              src="data:image/jpeg;base64,<?= base64_encode($product['main_image']) ?>"
              class="w-full h-full object-contain rounded-lg"
              alt="<?= htmlspecialchars($product['product_name']) ?>">
          </div>

          <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($product['product_name']) ?></h1>
          <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars($product['description'] ?? 'No description available.') ?></p>

          <div class="flex gap-2 justify-center">
            <a href="product_specs.php?id=<?= $product['id'] ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
              View Details
            </a>
            <button onclick="shareProduct()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
              Share
            </button>
          </div>
        </div>

        <!-- Product Options -->
        <div class="space-y-6 flex flex-col h-full">

          <div>
            <h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($product['product_name']) ?></h2>
            <div class="flex gap-2 text-sm mb-4">
              <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded">
                Code: <?= htmlspecialchars($product['codename']) ?>
              </span>
              <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Furniture</span>
            </div>
          </div>

          <!-- Color Selection -->
          <?php if (!empty($product_colors)): ?>
            <div>
              <h3 class="font-semibold mb-3">Available Colors</h3>
              <div class="flex flex-wrap gap-3 mb-2">
                <?php foreach ($product_colors as $color): ?>
                  <div class="text-center">
                    <button type="button"
                      onclick="selectColor(this, '<?= addslashes($color['color_name']) ?>', <?= $color['price'] ?>, <?= $color['id'] ?>)"
                      class="color-btn color-swatch hover:shadow-md"
                      style="background-color: <?= htmlspecialchars($color['color_code']) ?>;"
                      data-color-id="<?= $color['id'] ?>"
                      data-color-name="<?= htmlspecialchars($color['color_name']) ?>"
                      data-color-price="<?= $color['price'] ?>"
                      title="<?= htmlspecialchars($color['color_name']) ?>">
                      <?php if ($color['image']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($color['image']) ?>"
                          class="w-full h-full object-cover rounded-full opacity-0 hover:opacity-100 transition-opacity"
                          alt="<?= htmlspecialchars($color['color_name']) ?>">
                      <?php endif; ?>
                    </button>
                    <span class="text-xs text-gray-600 mt-1 block"><?= htmlspecialchars($color['color_name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
              <div id="selected-color-info" class="text-sm text-gray-500 italic">Select a color to see pricing</div>
            </div>
          <?php endif; ?>

          <!-- Type Selection -->
          <?php if (!empty($types_data)): ?>
            <div>
              <h3 class="font-semibold mb-3">Select Type</h3>
              <div class="grid grid-cols-3 gap-3">
                <?php foreach ($types_data as $index => $type): ?>
                  <button type="button"
                    onclick="showVariants(<?= $type['id'] ?>, '<?= addslashes($type['name']) ?>')"
                    class="type-btn border-2 rounded-lg p-3 hover:border-orange-300 transition-all">
                    <div class="aspect-square bg-gray-100 rounded mb-2 overflow-hidden">
                      <?php if ($type['image']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($type['image']) ?>"
                          class="w-full h-full object-contain" alt="<?= htmlspecialchars($type['name']) ?>">
                      <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                      <?php endif; ?>
                    </div>
                    <span class="text-sm font-medium"><?= htmlspecialchars($type['name']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Variant Selection -->
          <div>
            <h3 class="font-semibold mb-3">Sizes</h3>
            <div id="variant-container" class="text-gray-500">Please select a product type first.</div>

            <?php foreach ($types_data as $type): ?>
              <div id="variants-<?= $type['id'] ?>" class="variant-group hidden">
                <?php if (!empty($type['variants'])): ?>
                  <div class="grid grid-cols-3 gap-3">
                    <?php foreach ($type['variants'] as $variant):
                      $price = floatval($variant['variant_price']);
                      $percent = floatval($variant['percent']);
                      $discount = floatval($variant['discount'] ?? 0);
                      $priceWithMarkup = $price + ($price * $percent / 100);
                      $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                    ?>
                      <button type="button"
                        onclick="selectVariant(this, '<?= addslashes($variant['color']) ?>')"
                        class="variant-btn border rounded-md p-2 hover:border-orange-300 relative text-xs"
                        data-price="<?= $price ?>"
                        data-percent="<?= $percent ?>"
                        data-discount="<?= $discount ?>"
                        data-variant-id="<?= $variant['variant_id'] ?>">

                        <?php if ($discount > 0): ?>
                          <span class="absolute top-1 right-1 bg-red-500 text-white text-[10px] px-1 rounded">
                            <?= number_format($discount, 0) ?>% OFF
                          </span>
                        <?php endif; ?>

                        <div class="text-center">
                          <div class="font-semibold"><?= htmlspecialchars($variant['namevariant']) ?></div>
                          <div class="text-gray-600"><?= htmlspecialchars($variant['size']) ?></div>
                          <div class="mt-0.5">
                            <?php if ($discount > 0): ?>
                              <span class="text-[11px] text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></span>
                              <span class="text-red-600 font-bold text-sm">₱<?= number_format($finalPrice, 2) ?></span>
                            <?php else: ?>
                              <span class="font-bold text-sm text-red-500">₱<?= number_format($finalPrice, 2) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="text-gray-500">No variants available for this type.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>


          <!-- Purchase Section -->
          <div class="border-t pt-6 mt-auto">
            <?php $is_logged_in = isset($_SESSION['user_id']); ?>

            <form id="productForm" class="space-y-4 max-w-2xl mx-auto" method="POST">
              <input type="hidden" name="product_id" value="<?= $product_id ?>" />
              <input type="hidden" name="selected_color_id" id="selected_color_id">
              <input type="hidden" name="selected_color" id="selected_color">
              <input type="hidden" name="selected_type" id="selected_type">
              <input type="hidden" name="selected_variant" id="selected_variant">
              <input type="hidden" name="variant_id" id="variant_id">

              <!-- Total Price Section -->
              <div class="bg-green-50 rounded-lg p-4 border">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                  <div>
                    <p class="text-sm text-gray-600">Total Price</p>
                    <p id="totalPrice" class="text-xl font-bold text-green-600">₱0.00</p>
                  </div>
                  <div id="selectionStatus" class="text-sm text-gray-500 text-right">
                    <?= $is_logged_in ? 'Select options' : 'Please log in to pre-order' ?>
                  </div>
                </div>
              </div>

              <!-- Add to Cart Button -->
              <button type="submit" id="addToCartBtn"
                <?= !$is_logged_in ? 'disabled' : '' ?>
                class="w-full <?= $is_logged_in ? 'bg-gray-400' : 'bg-red-400' ?> text-white font-bold py-3 rounded-lg disabled:cursor-not-allowed transition-all hover:opacity-90">
                <span id="btnText">
                  <?= $is_logged_in ? 'Select Options to Pre-Order' : 'Login to Pre-Order' ?>
                </span>
              </button>
            </form>

          </div>
        </div>
      </div>
    </div>

    <!-- Related Products -->
    <?php if ($related_products->num_rows > 0): ?>
      <section class="mt-8">
        <h2 class="text-2xl font-bold text-center mb-6">Related Products</h2>

        <!-- Swiper Container -->
        <div class="swiper related-swiper">
          <div class="swiper-wrapper">
            <?php while ($row = $related_products->fetch_assoc()): ?>
              <div class="swiper-slide">
                <a href="product_view.php?id=<?= $row['id'] ?>"
                  class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition-shadow block h-full">
                  <div class="aspect-square mb-3 overflow-hidden rounded">
                    <?php if ($row['main_image']): ?>
                      <img src="data:image/jpeg;base64,<?= base64_encode($row['main_image']) ?>"
                        class="w-full h-full object-contain" alt="<?= htmlspecialchars($row['product_name']) ?>">
                    <?php else: ?>
                      <div class="w-full h-full bg-gray-200 flex items-center justify-center">No Image</div>
                    <?php endif; ?>
                  </div>
                  <h3 class="font-bold mb-2 text-sm"><?= htmlspecialchars($row['product_name']) ?></h3>
                  <div class="text-xs text-gray-600">
                    <span class="bg-orange-100 px-2 py-1 rounded"><?= htmlspecialchars($row['codename']) ?></span>
                  </div>
                </a>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

  </div>


  <script>

    // Initialize Swiper for related products
    const swiper = new Swiper('.related-swiper', {
      slidesPerView: 2,
      spaceBetween: 10,
      loop: true,
      autoplay: {
        delay: 2500,
        disableOnInteraction: false,
      },
      breakpoints: {
        640: {
          slidesPerView: 2,
          spaceBetween: 15,
        },
        768: {
          slidesPerView: 3,
          spaceBetween: 20,
        },
        1024: {
          slidesPerView: 4,
          spaceBetween: 25,
        },
        1280: {
          slidesPerView: 5,
          spaceBetween: 30,
        },
      }
    });

    // Product Selection State Management
    class ProductSelector {
      constructor() {
        this.selectedTypeId = null;
        this.selectedVariantData = null;
        this.selectedColorData = null;
        this.basePrice = parseFloat(document.querySelector('[name="product_id"]').dataset.basePrice) || 0;
        this.hasTypes = document.querySelectorAll('.type-btn').length > 0;

        this.initializeElements();
        this.bindEvents();
      }

      initializeElements() {
        this.elements = {
          mainImage: document.getElementById('main-product-image'),
          colorInfo: document.getElementById('selected-color-info'),
          variantContainer: document.getElementById('variant-container'),
          totalPrice: document.getElementById('totalPrice'),
          selectionStatus: document.getElementById('selectionStatus'),
          addToCartBtn: document.getElementById('addToCartBtn'),
          btnText: document.getElementById('btnText'),
          productForm: document.getElementById('productForm'),
          // Hidden inputs
          selectedColorId: document.getElementById('selected_color_id'),
          selectedColor: document.getElementById('selected_color'),
          selectedType: document.getElementById('selected_type'),
          selectedVariant: document.getElementById('selected_variant'),
          variantId: document.getElementById('variant_id')
        };
      }

      bindEvents() {
        // Form submission
        this.elements.productForm.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Touch event handling for mobile
        document.addEventListener('DOMContentLoaded', () => {
          const buttons = document.querySelectorAll('.color-btn, .type-btn, .variant-btn');
          buttons.forEach(button => {
            button.addEventListener('touchend', function() {
              this.blur();
            });
          });
        });
      }

      selectColor(button, colorName, colorPrice, colorId) {
        const isCurrentlySelected = button.classList.contains('color-selected');

        if (isCurrentlySelected) {
          this.unselectColor(button);
        } else {
          this.setColorSelection(button, colorName, colorPrice, colorId);
        }

        this.updateDisplay();
      }

      unselectColor(button) {
        button.classList.remove('color-selected');
        this.selectedColorData = null;

        // Clear hidden fields
        this.elements.selectedColorId.value = '';
        this.elements.selectedColor.value = '';

        // Reset to original product image
        this.elements.mainImage.src = this.elements.mainImage.dataset.originalSrc || this.elements.mainImage.src;
        this.elements.colorInfo.innerHTML = 'Select a color to see pricing';
      }

      setColorSelection(button, colorName, colorPrice, colorId) {
        // Remove previous selection
        document.querySelectorAll('.color-btn').forEach(btn => {
          btn.classList.remove('color-selected');
        });

        // Set new selection
        button.classList.add('color-selected');

        this.selectedColorData = {
          id: colorId,
          name: colorName,
          price: parseFloat(colorPrice)
        };

        // Update hidden fields
        this.elements.selectedColorId.value = colorId;
        this.elements.selectedColor.value = colorName;

        // Update display
        this.elements.colorInfo.innerHTML =
          `Selected: <strong>${colorName}</strong> - Additional ₱${parseFloat(colorPrice).toFixed(2)}`;

        // Update image if available
        const colorImage = button.querySelector('img');
        if (colorImage && colorImage.src && !colorImage.src.includes('opacity-0')) {
          this.elements.mainImage.src = colorImage.src;
        }
      }

      showVariants(typeId, typeName) {
        const clickedButton = event.currentTarget;
        const isCurrentlySelected = clickedButton.classList.contains('selected');

        if (isCurrentlySelected) {
          this.unselectType(clickedButton);
        } else {
          this.setTypeSelection(clickedButton, typeId, typeName);
        }

        this.updateDisplay();
      }

      unselectType(button) {
        button.classList.remove('selected');
        this.selectedTypeId = null;
        this.elements.selectedType.value = '';

        // Hide all variant groups
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Clear variant selection
        this.clearVariantSelection();

        // Show default message
        this.elements.variantContainer.textContent = 'Please select a product type first.';
      }

      setTypeSelection(button, typeId, typeName) {
        // Hide all variant groups
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Show selected variant group
        const variantGroup = document.getElementById(`variants-${typeId}`);
        if (variantGroup) {
          variantGroup.classList.remove('hidden');
          variantGroup.classList.add('fade-in');
        }

        // Update type selection
        document.querySelectorAll('.type-btn').forEach(btn => {
          btn.classList.remove('selected');
        });
        button.classList.add('selected');

        this.selectedTypeId = typeId;
        this.elements.selectedType.value = typeName;

        // Clear previous variant selection
        this.clearVariantSelection();
      }

      selectVariant(button, color) {
        const isCurrentlySelected = button.classList.contains('selected');

        if (isCurrentlySelected) {
          this.unselectVariant(button);
        } else {
          this.setVariantSelection(button, color);
        }

        this.updateDisplay();
      }

      unselectVariant(button) {
        button.classList.remove('selected');
        this.selectedVariantData = null;
        this.elements.selectedVariant.value = '';
        this.elements.variantId.value = '';
      }

      setVariantSelection(button, color) {
        // Remove previous selection
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected');
        });
        button.classList.add('selected');

        const price = parseFloat(button.dataset.price);
        const percent = parseFloat(button.dataset.percent);
        const discount = parseFloat(button.dataset.discount);
        const variantId = button.dataset.variantId;

        const priceWithMarkup = price + (price * percent / 100);
        const finalPrice = priceWithMarkup - (priceWithMarkup * discount / 100);

        this.selectedVariantData = {
          price,
          percent,
          discount,
          finalPrice,
          priceWithMarkup,
          variantId,
          color
        };

        // Update hidden fields
        this.elements.selectedVariant.value = color;
        this.elements.variantId.value = variantId;
      }

      clearVariantSelection() {
        this.selectedVariantData = null;
        this.elements.selectedVariant.value = '';
        this.elements.variantId.value = '';
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected');
        });
      }

      calculateTotalPrice() {
        let totalPrice = 0;
        const hasSelections = this.selectedColorData || this.selectedVariantData;

        if (hasSelections) {
          totalPrice = this.basePrice;

          // Add color price if selected
          if (this.selectedColorData) {
            totalPrice += this.selectedColorData.price;
          }

          // Use variant price if selected
          if (this.selectedVariantData) {
            totalPrice = this.selectedVariantData.finalPrice;
            if (this.selectedColorData) {
              totalPrice += this.selectedColorData.price;
            }
          }
        }

        return {
          totalPrice,
          hasSelections
        };
      }

      updateTotalPrice() {
        const {
          totalPrice,
          hasSelections
        } = this.calculateTotalPrice();

        if (hasSelections) {
          this.elements.totalPrice.textContent = `₱${totalPrice.toFixed(2)}`;
        } else {
          this.elements.totalPrice.textContent = 'Select options';
        }

        // Update selection status
        const status = [];
        if (this.selectedColorData) status.push(`Color: ${this.selectedColorData.name}`);
        if (this.selectedVariantData) status.push(`Variant: ${this.selectedVariantData.color}`);

        this.elements.selectionStatus.textContent =
          status.length > 0 ? status.join(', ') : 'Select options';
      }

      updatePurchaseButton() {
        const hasRequiredSelections = this.selectedColorData &&
          (!this.hasTypes || (this.selectedTypeId && this.selectedVariantData));

        if (hasRequiredSelections) {
          this.elements.addToCartBtn.disabled = false;
          this.elements.addToCartBtn.className = 'w-full bg-gradient-to-r from-orange-500 to-red-500 text-white font-bold py-3 rounded-lg hover:from-orange-600 hover:to-red-600 transition-all';
          this.elements.btnText.textContent = 'Add to Pre-Order';
        } else {
          this.elements.addToCartBtn.disabled = true;
          this.elements.addToCartBtn.className = 'w-full bg-gray-400 text-white font-bold py-3 rounded-lg disabled:cursor-not-allowed';
          this.elements.btnText.textContent = 'Select Options to Pre-Order';
        }
      }

      updateDisplay() {
        this.updateTotalPrice();
        this.updatePurchaseButton();
      }

      validateSelections() {
        const errors = [];

        if (!this.selectedColorData) {
          errors.push('Please select a color');
        }

        if (this.hasTypes) {
          if (!this.selectedTypeId) {
            errors.push('Please select a product type');
          }
          if (!this.selectedVariantData) {
            errors.push('Please select a variant');
          }
        }

        return errors;
      }

      handleFormSubmit(e) {
        e.preventDefault();

        const errors = this.validateSelections();
        if (errors.length > 0) {
          this.showNotification(errors.join(', '), 'error');
          return;
        }

        this.submitToCart();
      }

async submitToCart() {
  try {
    const formData = this.buildFormData();

    const response = await fetch('cart/add_to_cart.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      this.showNotification(data.message || 'Product added to cart!', 'success');
      this.updateCartCount(data.cart_count);

      if (data.item_added) {
        console.log('Item added:', data.item_added);
      }
    } else {
      // 🚫 User is not logged in
      if (data.message === 'You must be logged in to pre-order.') {
        this.showNotification('Please log in to pre-order.', 'error');

        // ✅ Open Alpine login dropdown safely
        const loginDropdown = document.querySelector('#authDropdown');
        if (loginDropdown) {
          const alpineData = Alpine?.$data(loginDropdown);
          if (alpineData) {
            alpineData.loginOpen = true;

            // Optional: focus email field after dropdown appears
            setTimeout(() => {
              const emailInput = loginDropdown.querySelector('input[type="email"]');
              if (emailInput) emailInput.focus();
            }, 100);
          }
        }
      } else {
        // Other errors
        throw new Error(data.message || 'Add to cart failed.');
      }
    }

  } catch (error) {
    this.showNotification('Error: ' + error.message, 'error');
    console.error('Add to cart error:', error);
  }
}

      buildFormData() {
        const formData = new FormData();
        const productId = document.querySelector('[name="product_id"]').value;

        formData.append('product_id', productId);

        if (this.selectedVariantData) {
          formData.append('variant_id', this.selectedVariantData.variantId);
          formData.append('selected_type', this.elements.selectedType.value);
          formData.append('selected_variant', this.elements.selectedVariant.value);
          formData.append('variant_price', this.selectedVariantData.finalPrice);
        }

        if (this.selectedColorData) {
          formData.append('selected_color_id', this.selectedColorData.id);
          formData.append('selected_color_name', this.selectedColorData.name);
          formData.append('color_price', this.selectedColorData.price);
        }

        const {
          totalPrice
        } = this.calculateTotalPrice();
        formData.append('total_price', totalPrice);

        return formData;
      }

      showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const bgColor = {
          success: 'bg-green-500',
          error: 'bg-red-500',
          info: 'bg-blue-500'
        } [type] || 'bg-blue-500';

        notification.className = `fixed top-4 right-4 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 3000);
      }

      updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
        cartCountElements.forEach(element => {
          element.textContent = count;
          element.style.display = count > 0 ? 'inline' : 'none';
        });

        const cartBubble = document.getElementById('cart-count-bubble');
        if (cartBubble) {
          if (count > 0) {
            cartBubble.classList.remove('hidden');
            cartBubble.style.display = 'inline';
          } else {
            cartBubble.classList.add('hidden');
            cartBubble.style.display = 'none';
          }
        }
      }
    }

    // Initialize the product selector
    const productSelector = new ProductSelector();

    // Global functions for onclick handlers
    function selectColor(button, colorName, colorPrice, colorId) {
      productSelector.selectColor(button, colorName, colorPrice, colorId);
    }

    function showVariants(typeId, typeName) {
      productSelector.showVariants(typeId, typeName);
    }

    function selectVariant(button, color) {
      productSelector.selectVariant(button, color);
    }

    function shareProduct() {
      const productName = document.querySelector('h1').textContent;

      if (navigator.share) {
        navigator.share({
          title: productName,
          url: window.location.href
        }).catch(err => {
          console.log('Error sharing:', err);
          fallbackShare();
        });
      } else {
        fallbackShare();
      }
    }

    function fallbackShare() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href)
          .then(() => {
            productSelector.showNotification('Link copied to clipboard!', 'success');
          })
          .catch(() => {
            productSelector.showNotification('Could not copy link', 'error');
          });
      } else {
        productSelector.showNotification('Sharing not supported', 'error');
      }
    }

    // Store original image src for reset functionality
    document.addEventListener('DOMContentLoaded', function() {
      const mainImage = document.getElementById('main-product-image');
      if (mainImage) {
        mainImage.dataset.originalSrc = mainImage.src;
      }
    });
  </script>
</body>

</html>