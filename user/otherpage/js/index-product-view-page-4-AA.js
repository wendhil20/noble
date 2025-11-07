// ===== MAGNIFIER FUNCTIONALITY =====
document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("magnifier-container");
  const img = document.getElementById("main-product-image");
  const lens = document.getElementById("magnifier-lens");
  const previewPanel = document.getElementById("zoom-preview-panel");
  const previewContent = document.getElementById("zoom-preview-content");

  if (!container || !img || !lens || !previewPanel || !previewContent) return;

  const zoomLevel = 2.5;
  let isActive = false;

  container.addEventListener("mouseenter", function () {
    isActive = true;
    lens.classList.remove("hidden");
    lens.classList.add("active");
    previewPanel.classList.remove("hidden");
    previewPanel.classList.add("active");

    if (img.complete) {
      setupPreview();
    } else {
      img.addEventListener("load", setupPreview);
    }
  });

  container.addEventListener("mouseleave", function () {
    isActive = false;
    lens.classList.remove("active");
    previewPanel.classList.remove("active");

    setTimeout(() => {
      lens.classList.add("hidden");
      previewPanel.classList.add("hidden");
    }, 200);
  });

  function setupPreview() {
    if (!isActive) return;
    previewContent.style.backgroundImage = `url('${img.src}')`;
  }

  container.addEventListener("mousemove", function (e) {
    if (!isActive) return;

    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    let lensX = x - lens.offsetWidth / 2;
    let lensY = y - lens.offsetHeight / 2;

    lensX = Math.max(0, Math.min(lensX, rect.width - lens.offsetWidth));
    lensY = Math.max(0, Math.min(lensY, rect.height - lens.offsetHeight));

    lens.style.left = lensX + "px";
    lens.style.top = lensY + "px";

    const percentX = (x / rect.width) * 100;
    const percentY = (y / rect.height) * 100;

    previewContent.style.backgroundPosition = `${percentX}% ${percentY}%`;
    previewContent.style.backgroundSize = `${zoomLevel * 100}%`;
  });

  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.type === "attributes" && mutation.attributeName === "src") {
        if (isActive) {
          previewContent.style.backgroundImage = `url('${img.src}')`;
        }
      }
    });
  });

  observer.observe(img, {
    attributes: true,
    attributeFilter: ["src"],
  });
});

// ===== MOBILE SIDEBAR FUNCTIONALITY =====
document.addEventListener("DOMContentLoaded", function () {
  const sidebarToggle = document.getElementById("mobileSidebarToggle");
  const closeSidebar = document.getElementById("closeSidebar");
  const sidebarOverlay = document.getElementById("sidebarOverlay");
  const productOptions = document.getElementById("productOptionsContainer");

  function openSidebar() {
    productOptions.classList.add("sidebar-open");
    sidebarOverlay.classList.remove("hidden");
    document.body.style.overflow = "hidden";
  }

  function closeSidebarFunc() {
    productOptions.classList.remove("sidebar-open");
    sidebarOverlay.classList.add("hidden");
    document.body.style.overflow = "";
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", openSidebar);
  }

  if (closeSidebar) {
    closeSidebar.addEventListener("click", closeSidebarFunc);
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", closeSidebarFunc);
  }
});

// ===== THUMBNAIL GALLERY =====
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".thumbnail-item").forEach((item) => {
    item.addEventListener("click", () => {
      document.querySelectorAll(".thumbnail-item img").forEach((img) => {
        img.classList.remove("border-blue-500", "thumbnail-active");
        img.classList.add("border-transparent");
      });

      const clickedImg = item.querySelector("img");
      clickedImg.classList.add("border-blue-500", "thumbnail-active");
      clickedImg.classList.remove("border-transparent");

      item.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
        inline: "center",
      });
    });
  });

  let isDown = false;
  let startX;
  let scrollLeft;

  const container = document.querySelector(".thumbnail-container");

  if (container) {
    container.addEventListener("mousedown", (e) => {
      isDown = true;
      startX = e.pageX - container.offsetLeft;
      scrollLeft = container.scrollLeft;
      container.style.cursor = "grabbing";
    });

    container.addEventListener("mouseleave", () => {
      isDown = false;
      container.style.cursor = "grab";
    });

    container.addEventListener("mouseup", () => {
      isDown = false;
      container.style.cursor = "grab";
    });

    container.addEventListener("mousemove", (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - container.offsetLeft;
      const walk = (x - startX) * 2;
      container.scrollLeft = scrollLeft - walk;
    });
  }
});

// ===== CONTACT MODAL =====
document.addEventListener("DOMContentLoaded", function () {
  const contactBtn = document.getElementById("contactUsBtn");
  if (contactBtn) {
    contactBtn.disabled = true;
    contactBtn.classList.add("bg-gray-400");
    contactBtn.classList.remove("bg-black", "hover:bg-blue-600");
  }
});

function openContactModal() {
  const colorData = window.productSelector
    ? window.productSelector.selectedColorData
    : null;
  const variantData = window.productSelector
    ? window.productSelector.selectedVariantData
    : null;
  const typeData =
    window.productSelector && window.productSelector.selectedTypeId
      ? document.getElementById("selected_type")?.value
      : null;

  const options = [];
  if (typeData) {
    options.push(`Type: ${typeData}`);
  }
  if (colorData) {
    options.push(`Color: ${colorData.name}`);
  }
  if (variantData) {
    options.push(`Size: ${variantData.size}`);
  }

  const totalPrice = window.productSelector
    ? window.productSelector.calculateTotalPrice().totalPrice
    : 0;

  const selectedOptionsElement = document.getElementById("selectedOptionsText");
  const selectedPriceElement = document.getElementById("selectedPriceText");
  const emailLink = document.getElementById("emailLink");

  if (selectedOptionsElement) {
    selectedOptionsElement.innerHTML =
      options.length > 0
        ? `<strong>Selected:</strong><br>${options.join("<br>")}`
        : "No specific options selected";
  }

  if (selectedPriceElement) {
    selectedPriceElement.textContent =
      totalPrice > 0 ? `Total: ₱${totalPrice.toFixed(2)}` : "";
  }

  if (emailLink) {
    const productName = document.querySelector("h1")?.textContent || "Product";
    const subject = `Windows Quote Request - ${productName}`;
    let body = `Hi, I'm interested in getting a quote for ${productName}.`;

    if (options.length > 0) {
      body += `\n\nSelected Options:\n${options.join("\n")}`;
    }

    if (totalPrice > 0) {
      body += `\nEstimated Total: ₱${totalPrice.toFixed(2)}`;
    }

    body += `\n\nPlease contact me with more details and final pricing.`;

    emailLink.href = `mailto:noblehomeconst.ph@gmail.com?subject=${encodeURIComponent(
      subject
    )}&body=${encodeURIComponent(body)}`;
  }

  const modal = document.getElementById("contactModal");
  if (modal) {
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
  }
}

function closeContactModal() {
  const modal = document.getElementById("contactModal");
  if (modal) {
    modal.classList.add("hidden");
    document.body.style.overflow = "auto";
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("contactModal");
  if (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeContactModal();
      }
    });
  }
});

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeContactModal();
  }
});

// ===== PRODUCT SELECTOR CLASS =====
class ProductSelector {
  constructor() {
    this.selectedTypeId = null;
    this.selectedVariantData = null;
    this.selectedColorData = null;
    this.basePrice =
      parseFloat(
        document.querySelector('[name="product_id"]')?.dataset?.basePrice
      ) || 0;
    this.hasTypes = document.querySelectorAll(".type-btn").length > 0;
    this.isWindows =
      document.querySelector('[name="is_windows"]')?.value === "1";

    this.initializeElements();
    this.bindEvents();
    this.initializeAllSectionsEnabled();
  }

  initializeElements() {
    this.elements = {
      mainImage: document.getElementById("main-product-image"),
      colorInfo: document.getElementById("selected-color-info"),
      variantContainer: document.getElementById("variant-container"),
      totalPrice: document.getElementById("totalPrice"),
      selectionStatus: document.getElementById("selectionStatus"),
      addToCartBtn: document.getElementById("addToCartBtn"),
      btnText: document.getElementById("btnText"),
      contactUsBtn: document.getElementById("contactUsBtn"),
      contactBtnText: document.getElementById("contactBtnText"),
      productForm: document.getElementById("productForm"),
      selectedColorId: document.getElementById("selected_color_id"),
      selectedColor: document.getElementById("selected_color"),
      selectedType: document.getElementById("selected_type"),
      selectedVariant: document.getElementById("selected_variant"),
      variantId: document.getElementById("variant_id"),
    };
  }

  bindEvents() {
    if (this.elements.productForm) {
      this.elements.productForm.addEventListener("submit", (e) =>
        this.handleFormSubmit(e)
      );
    }

    document.addEventListener("DOMContentLoaded", () => {
      const buttons = document.querySelectorAll(
        ".color-btn, .type-btn, .variant-btn"
      );
      buttons.forEach((button) => {
        button.addEventListener("touchend", function () {
          this.blur();
        });
      });
    });
  }

  initializeAllSectionsEnabled() {
    this.enableColorSelection();
    this.showAllVariantGroups();
    this.updateDisplay();
  }

  enableColorSelection() {
    const container = document.getElementById("color-selection-container");
    const message = document.getElementById("color-disabled-message");

    if (container && message) {
      container.classList.remove("opacity-50", "pointer-events-none");
      message.style.display = "none";
    }

    document.querySelectorAll(".color-btn").forEach((btn) => {
      btn.disabled = false;
      btn.classList.remove("opacity-50");
    });
  }

  showAllVariantGroups() {
    if (this.elements.variantContainer) {
      this.elements.variantContainer.style.display = "none";
    }

    document.querySelectorAll(".variant-group").forEach((group) => {
      group.classList.remove("hidden");
    });

    this.updateVariantAvailability();
  }

  updateVariantAvailability() {
    document.querySelectorAll(".variant-group").forEach((group) => {
      const variantButtons = group.querySelectorAll(".variant-btn");

      if (!this.selectedTypeId) {
        variantButtons.forEach((btn) => {
          btn.disabled = true;
          btn.classList.add("opacity-50", "cursor-not-allowed");
        });
      } else {
        const groupTypeId = group.id.replace("variants-", "");
        if (parseInt(groupTypeId) === parseInt(this.selectedTypeId)) {
          variantButtons.forEach((btn) => {
            btn.disabled = false;
            btn.classList.remove("opacity-50", "cursor-not-allowed");
          });
        } else {
          variantButtons.forEach((btn) => {
            btn.disabled = true;
            btn.classList.add("opacity-50", "cursor-not-allowed");
          });
        }
      }
    });
  }

  showVariants(typeId, typeName) {
    const clickedButton = event.currentTarget;
    const isCurrentlySelected = clickedButton.classList.contains("selected");

    if (isCurrentlySelected) {
      this.unselectType(clickedButton);
    } else {
      this.setTypeSelection(clickedButton, typeId, typeName);
    }

    this.updateDisplay();
  }

  setTypeSelection(button, typeId, typeName) {
    document.querySelectorAll(".type-btn").forEach((btn) => {
      btn.classList.remove(
        "selected",
        "border-orange-500",
        "bg-orange-50",
        "ring-1",
        "ring-orange-500"
      );
      btn.classList.add("border-gray-200", "bg-white");
    });

    button.classList.add(
      "selected",
      "border-orange-500",
      "bg-orange-50",
      "ring-1",
      "ring-orange-500"
    );
    button.classList.remove("border-gray-200", "bg-white");

    this.selectedTypeId = typeId;
    if (this.elements.selectedType) {
      this.elements.selectedType.value = typeName;
    }

    this.clearVariantSelection();
    this.updateVariantAvailability();
    this.updateDisplay();
  }

  unselectType(button) {
    button.classList.remove(
      "selected",
      "border-orange-500",
      "bg-orange-50",
      "ring-2",
      "ring-orange-500"
    );
    button.classList.add("border-gray-200", "bg-white");

    this.selectedTypeId = null;
    if (this.elements.selectedType) {
      this.elements.selectedType.value = "";
    }

    this.clearVariantSelection();
    this.updateVariantAvailability();
    this.updateDisplay();
  }

  setColorFromGrid(colorId, colorName, price, image, colorCode) {
    this.selectedColorData = {
      id: colorId,
      name: colorName,
      price: parseFloat(price),
    };

    if (this.elements.selectedColorId) {
      this.elements.selectedColorId.value = colorId;
    }
    if (this.elements.selectedColor) {
      this.elements.selectedColor.value = colorName;
    }

    if (image) {
      let imagePath = image.startsWith("../../") ? image : `../../${image}`;

      if (this.elements.mainImage) {
        this.elements.mainImage.src = imagePath;
      }

      const sidebarImage = document.getElementById("sidebar-product-image");
      if (sidebarImage) {
        sidebarImage.src = imagePath;
      }
    }

    this.updateDisplay();
  }

  clearColorSelection() {
    this.selectedColorData = null;

    if (this.elements.selectedColorId) {
      this.elements.selectedColorId.value = "";
    }
    if (this.elements.selectedColor) {
      this.elements.selectedColor.value = "";
    }

    document.querySelectorAll(".color-btn").forEach((btn) => {
      btn.classList.remove("selected", "border-orange-500", "bg-orange-50");
      btn.classList.add("border-gray-200", "bg-white");
    });

    if (this.elements.mainImage) {
      const originalSrc =
        this.elements.mainImage.dataset.originalSrc ||
        this.elements.mainImage.src.split("?")[0];
      this.elements.mainImage.src = originalSrc;
    }

    this.updateDisplay();
  }

  selectVariant(button, size, color = null) {
    const isCurrentlySelected = button.classList.contains("selected");

    if (isCurrentlySelected) {
      this.unselectVariant(button);
    } else {
      this.setVariantSelection(button, size, color);
    }

    this.updateDisplay();
  }

  setVariantSelection(button, size, color = null) {
    document.querySelectorAll(".variant-btn").forEach((btn) => {
      btn.classList.remove(
        "selected",
        "border-orange-500",
        "bg-orange-50",
        "ring-1",
        "ring-orange-500"
      );
      btn.classList.add("border-gray-200", "bg-white");
    });

    button.classList.add(
      "selected",
      "border-orange-500",
      "bg-orange-50",
      "ring-1",
      "ring-orange-500"
    );
    button.classList.remove("border-gray-200", "bg-white");

    const price = parseFloat(button.dataset.price);
    const percent = parseFloat(button.dataset.percent);
    const discount = parseFloat(button.dataset.discount);
    const variantId = button.dataset.variantId;

    const priceWithMarkup = price + (price * percent) / 100;
    const finalPrice = priceWithMarkup - (priceWithMarkup * discount) / 100;

    this.selectedVariantData = {
      price,
      percent,
      discount,
      finalPrice,
      priceWithMarkup,
      variantId,
      size: size,
      color: color || "",
    };

    if (this.elements.selectedVariant) {
      this.elements.selectedVariant.value = size;
    }
    if (this.elements.variantId) {
      this.elements.variantId.value = variantId;
    }

    this.updateProductHeaderPrice();

    setTimeout(() => {
      if (typeof updateAllPriceDisplays === "function") {
        updateAllPriceDisplays();
      }
    }, 100);
  }

  unselectVariant(button) {
    button.classList.remove(
      "selected",
      "border-orange-500",
      "bg-orange-50",
      "ring-1",
      "ring-orange-500"
    );
    button.classList.add("border-gray-200", "bg-white");

    this.selectedVariantData = null;
    if (this.elements.selectedVariant) {
      this.elements.selectedVariant.value = "";
    }
    if (this.elements.variantId) {
      this.elements.variantId.value = "";
    }

    this.hideProductHeaderPrice();
  }

  clearVariantSelection() {
    this.selectedVariantData = null;
    if (this.elements.selectedVariant) {
      this.elements.selectedVariant.value = "";
    }
    if (this.elements.variantId) {
      this.elements.variantId.value = "";
    }
    document.querySelectorAll(".variant-btn").forEach((btn) => {
      btn.classList.remove(
        "selected",
        "border-orange-500",
        "bg-orange-50",
        "ring-1",
        "ring-orange-500"
      );
      btn.classList.add("border-gray-200", "bg-white");
    });
  }

  calculateTotalPrice() {
    let totalPrice = 0;
    const hasSelections = this.selectedColorData || this.selectedVariantData;

    if (hasSelections) {
      totalPrice = this.basePrice;

      if (this.selectedColorData) {
        totalPrice += this.selectedColorData.price;
      }

      if (this.selectedVariantData) {
        totalPrice = this.selectedVariantData.finalPrice;
        if (this.selectedColorData) {
          totalPrice += this.selectedColorData.price;
        }
      }
    }

    return {
      totalPrice,
      hasSelections,
    };
  }

  updateProductHeaderPrice() {
    if (!this.selectedVariantData) return;

    const priceDisplay = document.getElementById("product-price-display");
    const originalPriceContainer = document.getElementById(
      "original-price-container"
    );
    const originalPriceElement = document.getElementById("original-price");
    const finalPriceElement = document.getElementById("final-price");
    const discountBadge = document.getElementById("discount-badge");
    const discountPercent = document.getElementById("discount-percent");
    const selectedSizeText = document.getElementById("selected-size-text");

    if (!priceDisplay || !finalPriceElement) return;

    priceDisplay.classList.remove("hidden");

    let finalPrice = parseFloat(this.selectedVariantData.priceWithMarkup) || 0;

    if (this.selectedColorData && this.selectedColorData.price) {
      finalPrice += parseFloat(this.selectedColorData.price);
    }

    if (selectedSizeText && this.selectedVariantData.size) {
      selectedSizeText.textContent = this.selectedVariantData.size;
    }

    const discountValue = parseFloat(this.selectedVariantData.discount) || 0;

    if (discountValue > 0) {
      const originalPrice = finalPrice;
      const discountedPrice =
        originalPrice - (originalPrice * discountValue) / 100;

      if (originalPriceContainer && originalPriceElement) {
        originalPriceContainer.classList.remove("hidden");
        originalPriceElement.textContent = `₱${originalPrice.toFixed(2)}`;
      }

      finalPriceElement.textContent = `₱${discountedPrice.toFixed(2)}`;

      if (discountBadge && discountPercent) {
        discountBadge.classList.remove("hidden");
        discountPercent.textContent = discountValue.toFixed(0);
      }
    } else {
      if (originalPriceContainer) {
        originalPriceContainer.classList.add("hidden");
      }
      finalPriceElement.textContent = `₱${finalPrice.toFixed(2)}`;
      if (discountBadge) {
        discountBadge.classList.add("hidden");
      }
    }
  }

  hideProductHeaderPrice() {
    const priceDisplay = document.getElementById("product-price-display");
    if (priceDisplay) {
      priceDisplay.classList.add("hidden");
    }
  }

  updateTotalPrice() {
    const { totalPrice, hasSelections } = this.calculateTotalPrice();

    if (this.elements.totalPrice) {
      if (hasSelections) {
        this.elements.totalPrice.textContent = `₱${totalPrice.toFixed(2)}`;
      } else {
        this.elements.totalPrice.textContent = "₱0.00";
      }
    }

    const status = [];
    if (this.selectedTypeId && this.elements.selectedType)
      status.push(`Type: ${this.elements.selectedType.value}`);
    if (this.selectedColorData)
      status.push(`Color: ${this.selectedColorData.name}`);
    if (this.selectedVariantData)
      status.push(`Size: ${this.selectedVariantData.size}`);

    if (this.elements.selectionStatus) {
      this.elements.selectionStatus.textContent =
        status.length > 0
          ? status.join(", ")
          : "Select options to see total price";
    }
  }

  updatePurchaseButton() {
    const hasRequiredSelections =
      this.selectedTypeId && this.selectedColorData && this.selectedVariantData;

    if (this.isWindows) {
      const contactBtn = this.elements.contactUsBtn;
      const contactBtnText = this.elements.contactBtnText;

      if (contactBtn && contactBtnText) {
        if (hasRequiredSelections) {
          contactBtn.disabled = false;
          contactBtn.className =
            "w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300 bg-black hover:bg-blue-600 text-white";
          contactBtnText.innerHTML =
            '<i class="fas fa-phone mr-2"></i>Contact Us for Quote';
        } else {
          contactBtn.disabled = true;
          contactBtn.className =
            "w-full py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75";
          contactBtnText.innerHTML =
            '<i class="fas fa-phone mr-2"></i>Select all options to contact us';
        }
      }
    } else {
      const addToCartBtn = this.elements.addToCartBtn;
      const btnText = this.elements.btnText;

      if (addToCartBtn && btnText) {
        if (hasRequiredSelections) {
          addToCartBtn.disabled = false;
          addToCartBtn.className =
            "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-black hover:bg-orange-600 text-white";
          btnText.innerHTML =
            '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
        } else {
          addToCartBtn.disabled = true;
          addToCartBtn.className =
            "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75";
          btnText.innerHTML =
            '<i class="fas fa-shopping-cart mr-2"></i> Select first';
        }
      }
    }
  }

  updateDisplay() {
    this.updateTotalPrice();
    this.updatePurchaseButton();
    this.updateVariantAvailability();
  }

  validateSelections() {
    const errors = [];
    if (!this.selectedTypeId) errors.push("Please select an item type");
    if (!this.selectedColorData) errors.push("Please select a color");
    if (!this.selectedVariantData) errors.push("Please select a size");
    return errors;
  }

  handleFormSubmit(e) {
    e.preventDefault();
    const errors = this.validateSelections();
    if (errors.length > 0) {
      this.showNotification(errors.join(", "), "error");
      return;
    }
    this.submitToCart();
  }

  async submitToCart() {
    try {
      const formData = this.buildFormData();
      const response = await fetch("../cart/add_to_cart.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(
          data.message || "Product added to cart!",
          "success"
        );
        this.updateCartCount(data.cart_count);
      } else {
        if (data.message === "You must be logged in to pre-order.") {
          this.showNotification("Please log in to pre-order.", "error");
          const loginDropdown = document.querySelector("#authDropdown");
          if (loginDropdown) {
            const alpineData = Alpine?.$data(loginDropdown);
            if (alpineData) {
              alpineData.loginOpen = true;
              setTimeout(() => {
                const emailInput = loginDropdown.querySelector(
                  'input[type="email"]'
                );
                if (emailInput) emailInput.focus();
              }, 100);
            }
          }
        } else {
          throw new Error(data.message || "Add to cart failed.");
        }
      }
    } catch (error) {
      this.showNotification("" + error.message, "error");
      console.error("Add to cart error:", error);
    }
  }

  buildFormData() {
    const formData = new FormData();
    const productId = document.querySelector('[name="product_id"]')?.value;

    if (productId) formData.append("product_id", productId);

    if (this.selectedVariantData) {
      formData.append("variant_id", this.selectedVariantData.variantId);
      if (this.elements.selectedType) {
        formData.append("selected_type", this.elements.selectedType.value);
      }
      if (this.elements.selectedVariant) {
        formData.append(
          "selected_variant",
          this.elements.selectedVariant.value
        );
      }
    }

    if (this.selectedColorData) {
      formData.append("selected_color_id", this.selectedColorData.id);
      formData.append("selected_color", this.selectedColorData.name);
    }

    const quantityInput = document.getElementById("quantityInput");
    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
    formData.append("quantity", quantity);

    const additionalFields = ["is_windows", "base_price"];
    additionalFields.forEach((fieldName) => {
      const field = document.querySelector(`[name="${fieldName}"]`);
      if (field) formData.append(fieldName, field.value);
    });

    return formData;
  }

showNotification(message, type = "success") {
    const existingNotifications = document.querySelectorAll(
      ".notification-toast"
    );
    existingNotifications.forEach((notification) => notification.remove());

    const notification = document.createElement("div");
    notification.className = `notification-toast fixed top-4 right-4 z-[150] px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;

    if (type === "success") {
      notification.classList.add("bg-green-500", "text-white");
    } else if (type === "error") {
      notification.classList.add("bg-red-500", "text-white");
    } else {
      notification.classList.add("bg-blue-500", "text-white");
    }

    const iconClass =
      type === "success"
        ? "fas fa-check-circle"
        : type === "error"
        ? "fas fa-exclamation-circle"
        : "fas fa-info-circle";

    notification.innerHTML = `
      <div class="flex items-center">
        <i class="${iconClass} mr-3"></i>
        <span class="font-medium">${message}</span>
        <button class="ml-4 text-white hover:text-gray-200 focus:outline-none" onclick="this.parentElement.parentElement.remove()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.remove("translate-x-full");
    }, 100);

    setTimeout(() => {
      notification.classList.add("translate-x-full");
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 300);
    }, 5000);
  }

  updateCartCount(newCount) {
    const cartCountElements = document.querySelectorAll(
      ".cart-count, #cartCount"
    );
    cartCountElements.forEach((element) => {
      if (element) {
        element.textContent = newCount;
        element.classList.add("animate-bounce");
        setTimeout(() => {
          element.classList.remove("animate-bounce");
        }, 1000);
      }
    });

    const cartBadges = document.querySelectorAll(".cart-badge");
    cartBadges.forEach((badge) => {
      if (newCount > 0) {
        badge.classList.remove("hidden");
      } else {
        badge.classList.add("hidden");
      }
    });
  }

  resetAllSelections() {
    this.selectedTypeId = null;
    this.selectedColorData = null;
    this.selectedVariantData = null;

    document
      .querySelectorAll(".type-btn, .color-btn, .variant-btn")
      .forEach((btn) => {
        btn.classList.remove(
          "selected",
          "border-orange-500",
          "bg-orange-50",
          "ring-2",
          "ring-orange-500"
        );
        btn.classList.add("border-gray-200", "bg-white");
      });

    Object.values(this.elements).forEach((element) => {
      if (element && element.tagName === "INPUT" && element.type === "hidden") {
        element.value = "";
      }
    });

    this.initializeAllSectionsEnabled();
    this.updateDisplay();
  }

  getSelectionSummary() {
    return {
      hasType: !!this.selectedTypeId,
      hasColor: !!this.selectedColorData,
      hasVariant: !!this.selectedVariantData,
      isComplete: !!(
        this.selectedTypeId &&
        this.selectedColorData &&
        this.selectedVariantData
      ),
      typeId: this.selectedTypeId,
      typeName: this.elements.selectedType?.value || null,
      colorData: this.selectedColorData,
      variantData: this.selectedVariantData,
      totalPrice: this.calculateTotalPrice().totalPrice,
    };
  }
}

// ===== GLOBAL FUNCTIONS =====
function showVariants(typeId, typeName) {
  if (window.productSelector) {
    window.productSelector.showVariants(typeId, typeName);
  }
}

function selectVariant(button, size, color = null) {
  const isCurrentlySelected = button.classList.contains("selected");

  if (isCurrentlySelected) {
    button.classList.remove("selected", "border-orange-500", "bg-orange-50");
    button.classList.add("border-gray-200", "bg-white");

    window.productSelector.selectedVariantData = null;
    document.getElementById("selected_variant").value = "";
    document.getElementById("variant_id").value = "";

    hideAllPriceDisplays();
    updatePurchaseButton();
    return;
  }

  document.querySelectorAll(".variant-btn").forEach((btn) => {
    btn.classList.remove("selected", "border-orange-500", "bg-orange-50");
    btn.classList.add("border-gray-200", "bg-white");
  });

  button.classList.add("selected", "border-orange-500", "bg-orange-50");
  button.classList.remove("border-gray-200", "bg-white");

  const variantPrice = parseFloat(button.dataset.price) || 0;
  const percent = parseFloat(button.dataset.percent) || 0;
  const discount = parseFloat(button.dataset.discount) || 0;
  const variantId = button.dataset.variantId;

  let basePrice = variantPrice;

  if (window.productSelector && window.productSelector.selectedColorData) {
    basePrice += parseFloat(window.productSelector.selectedColorData.price) || 0;
  }

  const priceWithMarkup = basePrice + (basePrice * percent) / 100;
  const finalPrice = priceWithMarkup - (priceWithMarkup * discount) / 100;

  window.productSelector.selectedVariantData = {
    price: variantPrice,
    percent: percent,
    discount: discount,
    finalPrice: finalPrice,
    priceWithMarkup: priceWithMarkup,
    variantId: variantId,
    size: size,
    color: color || "",
  };

  document.getElementById("selected_variant").value = size;
  document.getElementById("variant_id").value = variantId;

  updateAllPriceDisplays();
}

function updateAllPriceDisplays() {
  if (!window.productSelector || !window.productSelector.selectedVariantData) {
    hideAllPriceDisplays();
    return;
  }

  const variant = window.productSelector.selectedVariantData;
  const quantity = parseInt(document.getElementById("quantityInput")?.value) || 1;

  updateProductHeaderPrice(variant);
  updateQuantityPreview(variant, quantity);
  updateTotalPrice(variant, quantity);
  updatePurchaseButton();
}

function updateProductHeaderPrice(variant) {
  const priceDisplay = document.getElementById("product-price-display");
  const originalPriceElement = document.getElementById("original-price");
  const finalPriceElement = document.getElementById("final-price");
  const discountBadge = document.getElementById("discount-badge");
  const discountPercent = document.getElementById("discount-percent");
  const selectedSizeText = document.getElementById("selected-size-text");

  if (!priceDisplay || !finalPriceElement) return;

  priceDisplay.classList.remove("hidden");

  if (selectedSizeText && variant.size) {
    selectedSizeText.textContent = variant.size;
  }

  if (variant.discount > 0) {
    if (originalPriceElement) {
      document.getElementById("original-price-container")?.classList.remove("hidden");
      originalPriceElement.textContent = `₱${variant.priceWithMarkup.toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    }

    finalPriceElement.textContent = `₱${variant.finalPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    if (discountBadge && discountPercent) {
      discountBadge.classList.remove("hidden");
      discountPercent.textContent = Math.round(variant.discount);
    }
  } else {
    document.getElementById("original-price-container")?.classList.add("hidden");
    finalPriceElement.textContent = `₱${variant.priceWithMarkup.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    if (discountBadge) discountBadge.classList.add("hidden");
  }
}

function updateQuantityPreview(variant, quantity) {
  const previewContainer = document.getElementById("quantityPricePreview");
  const previewQty = document.getElementById("previewQty");
  const previewTotal = document.getElementById("previewTotal");

  if (!previewContainer || !previewQty || !previewTotal) return;

  const unitPrice = variant.finalPrice;
  const totalPrice = unitPrice * quantity;

  previewQty.textContent = quantity;
  previewTotal.textContent = `₱${totalPrice.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  previewContainer.classList.remove("hidden");
}

function updateTotalPrice(variant, quantity) {
  const totalPriceElement = document.getElementById("totalPrice");
  const selectionStatus = document.getElementById("selectionStatus");

  if (!totalPriceElement) return;

  const totalPrice = variant.finalPrice * quantity;

  totalPriceElement.textContent = `₱${totalPrice.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  if (selectionStatus) {
    const status = [];
    if (window.productSelector.selectedTypeId) {
      status.push(`Type: ${document.getElementById("selected_type")?.value}`);
    }
    if (window.productSelector.selectedColorData) {
      status.push(`Color: ${window.productSelector.selectedColorData.name}`);
    }
    if (variant) {
      status.push(`Size: ${variant.size}`);
    }
    selectionStatus.textContent = status.join(", ");
  }
}

function hideAllPriceDisplays() {
  document.getElementById("product-price-display")?.classList.add("hidden");
  document.getElementById("quantityPricePreview")?.classList.add("hidden");
  document.getElementById("totalPrice").textContent = "₱0.00";
  document.getElementById("selectionStatus").textContent = "Complete steps 1-3 to see price";
}

function updatePurchaseButton() {
  const hasRequiredSelections =
    window.productSelector.selectedTypeId &&
    window.productSelector.selectedColorData &&
    window.productSelector.selectedVariantData;

  const isWindows = document.querySelector('[name="is_windows"]')?.value === "1";

  if (isWindows) {
    const contactBtn = document.getElementById("contactUsBtn");
    if (contactBtn) {
      if (hasRequiredSelections) {
        contactBtn.disabled = false;
        contactBtn.className = "w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300 bg-black hover:bg-blue-600 text-white";
        document.getElementById("contactBtnText").innerHTML = '<i class="fas fa-phone mr-2"></i>Contact Us for Quote';
      } else {
        contactBtn.disabled = true;
        contactBtn.className = "w-full py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
        document.getElementById("contactBtnText").innerHTML = '<i class="fas fa-phone mr-2"></i>Select all options first';
      }
    }
  } else {
    const addToCartBtn = document.getElementById("addToCartBtn");
    if (addToCartBtn) {
      if (hasRequiredSelections) {
        addToCartBtn.disabled = false;
        addToCartBtn.className = "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-black hover:bg-orange-600 text-white";
        document.getElementById("btnText").innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
      } else {
        addToCartBtn.disabled = true;
        addToCartBtn.className = "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
        document.getElementById("btnText").innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Select all options first';
      }
    }
  }
}

function validateQuantity() {
  const input = document.getElementById("quantityInput");
  let val = parseInt(input.value) || 1;
  val = Math.max(1, Math.min(9999, val));
  input.value = val;

  if (window.productSelector?.selectedVariantData) {
    updateAllPriceDisplays();
  }
}

function selectColorFromGrid(colorId, colorName, price, image, colorCode) {
  document.querySelectorAll(".color-btn").forEach((btn) => {
    btn.classList.remove("selected", "border-orange-500", "bg-orange-50");
    btn.classList.add("border-gray-200", "bg-white");
  });

  const clickedButton = event.currentTarget;
  clickedButton.classList.add("selected", "border-orange-500", "bg-orange-50");
  clickedButton.classList.remove("border-gray-200", "bg-white");

  if (!window.productSelector) return;

  window.productSelector.selectedColorData = {
    id: colorId,
    name: colorName,
    price: parseFloat(price) || 0,
  };

  if (window.productSelector.elements.selectedColorId) {
    window.productSelector.elements.selectedColorId.value = colorId;
  }
  if (window.productSelector.elements.selectedColor) {
    window.productSelector.elements.selectedColor.value = colorName;
  }

  if (image) {
    let imagePath = image.startsWith("../../") ? image : `../../${image}`;
    if (window.productSelector.elements.mainImage) {
      window.productSelector.elements.mainImage.src = imagePath;
    }
    const sidebarImage = document.getElementById("sidebar-product-image");
    if (sidebarImage) {
      sidebarImage.src = imagePath;
    }
  }

  const selectedSizeBtn = document.querySelector(".variant-btn.selected");
  if (selectedSizeBtn && window.productSelector.selectedVariantData) {
    const variantPrice = parseFloat(selectedSizeBtn.dataset.price) || 0;
    const percent = parseFloat(selectedSizeBtn.dataset.percent) || 0;
    const discount = parseFloat(selectedSizeBtn.dataset.discount) || 0;
    const variantId = selectedSizeBtn.dataset.variantId;

    let finalBasePrice = variantPrice + (parseFloat(price) || 0);
    const priceWithMarkup = finalBasePrice + (finalBasePrice * percent) / 100;
    const finalPrice = priceWithMarkup - (priceWithMarkup * discount) / 100;

    window.productSelector.selectedVariantData = {
      price: variantPrice,
      percent: percent,
      discount: discount,
      finalPrice: finalPrice,
      priceWithMarkup: priceWithMarkup,
      variantId: variantId,
      size: selectedSizeBtn.querySelector(".text-gray-700").textContent.trim(),
      color: colorName,
    };

    updateAllPriceDisplays();
  }
}

function shareProduct() {
  const productName = document.querySelector("h1")?.textContent || "Product";
  const currentUrl = window.location.href;

  if (navigator.share) {
    navigator
      .share({
        title: productName,
        text: `Check out this product: ${productName}`,
        url: currentUrl,
      })
      .catch((err) => console.log("Error sharing:", err));
  } else {
    navigator.clipboard
      .writeText(currentUrl)
      .then(() => {
        if (window.productSelector) {
          window.productSelector.showNotification(
            "Product link copied to clipboard!",
            "success"
          );
        } else {
          alert("Product link copied to clipboard!");
        }
      })
      .catch((err) => {
        console.error("Failed to copy: ", err);
        alert("Could not copy link. Please copy manually: " + currentUrl);
      });
  }
}

// ===== QUANTITY FUNCTIONS =====
function increaseQuantity() {
  const input = document.getElementById("quantityInput");
  const val = parseInt(input.value) || 1;
  if (val < 9999) {
    input.value = val + 1;
    validateQuantity();
  }
}

function decreaseQuantity() {
  const input = document.getElementById("quantityInput");
  const val = parseInt(input.value) || 1;
  if (val > 1) {
    input.value = val - 1;
    validateQuantity();
  }
}

function setQuantity(amount) {
  const input = document.getElementById("quantityInput");
  if (amount >= 1 && amount <= 9999) {
    input.value = amount;
    validateQuantity();
  }
}

// ===== INITIALIZE =====
document.addEventListener("DOMContentLoaded", function () {
  if (typeof ProductSelector !== "undefined") {
    window.productSelector = new ProductSelector();

    const mainImage = document.getElementById("main-product-image");
    if (mainImage) {
      mainImage.dataset.originalSrc = mainImage.src;
    }

    console.log("ProductSelector initialized - All sections visible");
  } else {
    console.error("ProductSelector class not found");
  }

  const quantityInput = document.getElementById("quantityInput");
  if (quantityInput) {
    quantityInput.addEventListener("keydown", function (e) {
      e.stopPropagation();
      if (e.key === "Enter") {
        e.preventDefault();
        this.blur();
        return false;
      }
    });

    quantityInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        return false;
      }
    });

    quantityInput.addEventListener("input", validateQuantity);
    quantityInput.addEventListener("change", validateQuantity);
  }
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener("keydown", function (e) {
  const activeElement = document.activeElement;

  if (
    activeElement &&
    (activeElement.tagName === "INPUT" ||
      activeElement.tagName === "TEXTAREA" ||
      activeElement.isContentEditable)
  ) {
    return;
  }

  if (e.key === "Escape") {
    if (typeof closeContactModal === "function") {
      closeContactModal();
    }
  }

  if (e.key >= "1" && e.key <= "9" && !e.ctrlKey && !e.altKey && !e.metaKey) {
    const variantButtons = document.querySelectorAll(".variant-btn:not([disabled])");
    const index = parseInt(e.key) - 1;
    if (variantButtons[index]) {
      e.preventDefault();
      e.stopPropagation();
      variantButtons[index].click();
    }
  }
});

window.debugProductSelector = function () {
  if (window.productSelector) {
    console.log("ProductSelector State:", window.productSelector.getSelectionSummary());
  } else {
    console.log("ProductSelector not initialized");
  }
};

// ===== CALCULATOR FUNCTIONS =====
function updateCalculatorFromVariant(button) {
  const width = parseFloat(button.dataset.width) || 0;
  const height = parseFloat(button.dataset.height) || 0;
  const length = parseFloat(button.dataset.length) || 0;
  const size = button.querySelector('.text-gray-700').textContent.trim();

  // Convert mm to meters
  const widthM = width / 1000;
  const heightM = height / 1000;
  const areaPerPiece = widthM * heightM;

  // Store dimensions in global scope
  window.selectedVariantDimensions = {
    width,
    height,
    length,
    size,
    areaPerPiece
  };

  // Show calculator section
  const calcSection = document.getElementById('calculatorSection');
  if (calcSection) {
    calcSection.classList.remove('hidden');
  }

  // Set size display
  const sizeDisplay = document.getElementById('selectedSizeDisplay');
  if (sizeDisplay) {
    sizeDisplay.textContent = size;
  }

  // Update dimension displays
  const widthEl = document.getElementById('calcWidth');
  const heightEl = document.getElementById('calcHeight');
  const lengthEl = document.getElementById('calcLength');
  const areaEl = document.getElementById('calcAreaPerPiece');

  if (widthEl) widthEl.textContent = width;
  if (heightEl) heightEl.textContent = height;
  if (lengthEl) lengthEl.textContent = length;
  if (areaEl) areaEl.textContent = areaPerPiece.toFixed(4) + ' m²';

  // Clear previous calculations
  const areaInput = document.getElementById('userArea');
  const piecesDisplay = document.getElementById('piecesFromArea');
  const resultsSection = document.getElementById('userCalculationResults');

  if (areaInput) areaInput.value = '';
  if (piecesDisplay) piecesDisplay.textContent = '0';
  if (resultsSection) resultsSection.classList.add('hidden');
}

function calculateFromArea() {
  const areaInput = document.getElementById('userArea');
  const piecesDisplay = document.getElementById('piecesFromArea');
  const resultsSection = document.getElementById('userCalculationResults');
  const adhesiveEl = document.getElementById('userAdhesiveNeeded');
  const bracketsEl = document.getElementById('userBracketsNeeded');

  if (!areaInput || !piecesDisplay) return;

  const area = parseFloat(areaInput.value);

  // Validation
  if (!area || area <= 0) {
    piecesDisplay.textContent = '0';
    if (resultsSection) resultsSection.classList.add('hidden');
    return;
  }

  if (!window.selectedVariantDimensions || !window.selectedVariantDimensions.areaPerPiece || window.selectedVariantDimensions.areaPerPiece <= 0) {
    piecesDisplay.textContent = '0';
    if (resultsSection) resultsSection.classList.add('hidden');
    return;
  }

  // Calculate pieces needed
  const piecesNeeded = Math.ceil(area / window.selectedVariantDimensions.areaPerPiece);

  // Calculate additional materials
  const adhesiveNeeded = (area * 0.30).toFixed(2);
  const bracketsNeeded = Math.ceil(piecesNeeded * 0.25);

  // Update display
  piecesDisplay.textContent = piecesNeeded.toLocaleString();

  if (adhesiveEl) adhesiveEl.textContent = adhesiveNeeded;
  if (bracketsEl) bracketsEl.textContent = bracketsNeeded.toLocaleString();
  if (resultsSection) resultsSection.classList.remove('hidden');
}

function clearCalculator() {
  const calcSection = document.getElementById('calculatorSection');
  const areaInput = document.getElementById('userArea');
  const piecesDisplay = document.getElementById('piecesFromArea');
  const resultsSection = document.getElementById('userCalculationResults');

  if (calcSection) calcSection.classList.add('hidden');
  if (areaInput) areaInput.value = '';
  if (piecesDisplay) piecesDisplay.textContent = '0';
  if (resultsSection) resultsSection.classList.add('hidden');

  window.selectedVariantDimensions = {
    width: 0,
    height: 0,
    length: 0,
    size: '',
    areaPerPiece: 0
  };
}

// ===== SKU INFO FUNCTIONS =====
function showSkuInfo(button) {
  const display = document.getElementById('sku-info-display');
  const content = document.getElementById('sku-info-content');
  const toggleBtn = document.getElementById('toggle-sku-btn');
  const skuInfoJson = button.getAttribute('data-sku-info');

  // Reset expansion state
  content.classList.remove('collapsed', 'expanded');
  toggleBtn.classList.add('hidden');

  if (!skuInfoJson) {
    display.classList.add('hidden');
    return;
  }

  try {
    const skuInfo = JSON.parse(skuInfoJson);
    let html = '';

    if (Object.keys(skuInfo).length === 1 && skuInfo.notes) {
      html = `
        <div class="bg-white p-4 rounded-lg">
          <div class="whitespace-pre-wrap text-gray-800">${escapeHtml(skuInfo.notes)}</div>
        </div>
      `;
    } else {
      html = '<div class="bg-white p-4 rounded-lg"><div class="space-y-3">';
      for (const [key, value] of Object.entries(skuInfo)) {
        const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
        html += `
          <div class="flex items-start border-b border-gray-100 pb-2 last:border-0 last:pb-0">
            <span class="text-sm font-semibold text-orange-600 min-w-[120px]">${escapeHtml(label)}:</span>
            <span class="text-sm text-gray-800 flex-1">${escapeHtml(value)}</span>
          </div>
        `;
      }
      html += '</div></div>';
    }

    content.innerHTML = html;
    display.classList.remove('hidden');

    setTimeout(() => {
      if (content.scrollHeight > 120) {
        content.classList.add('collapsed');
        toggleBtn.classList.remove('hidden');
        updateToggleButton();
      }
    }, 50);

  } catch (e) {
    console.error('Error parsing SKU info:', e);
    display.classList.add('hidden');
  }
}

function toggleSkuContent() {
  const content = document.getElementById('sku-info-content');
  const currentlyExpanded = content.classList.contains('expanded');

  if (currentlyExpanded) {
    content.classList.remove('expanded');
    content.classList.add('collapsed');
  } else {
    content.classList.remove('collapsed');
    content.classList.add('expanded');
  }

  updateToggleButton();
}

function updateToggleButton() {
  const toggleText = document.getElementById('toggle-sku-text');
  const toggleIcon = document.getElementById('toggle-sku-icon');
  const content = document.getElementById('sku-info-content');
  const currentlyExpanded = content.classList.contains('expanded');

  if (currentlyExpanded) {
    toggleText.textContent = 'See Less';
    toggleIcon.classList.remove('fa-chevron-down');
    toggleIcon.classList.add('fa-chevron-up');
  } else {
    toggleText.textContent = 'See More';
    toggleIcon.classList.remove('fa-chevron-up');
    toggleIcon.classList.add('fa-chevron-down');
  }
}

function hideSkuInfo() {
  const display = document.getElementById('sku-info-display');
  display.classList.add('hidden');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}