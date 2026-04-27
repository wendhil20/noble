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
  const mainImage = document.getElementById("main-product-image");

  console.log("Initializing thumbnail gallery...");
  console.log("Main image element:", mainImage);

  document.querySelectorAll(".thumbnail-item").forEach((item, index) => {
    console.log(`Thumbnail ${index} found:`, item);

    item.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      console.log("Thumbnail clicked:", index);

      const clickedImg = this.querySelector("img");

      if (!clickedImg) {
        console.error("No image found in thumbnail item");
        return;
      }

      const thumbnailSrc = clickedImg.src;
      console.log("Thumbnail src:", thumbnailSrc);

      // Update main product image
      if (mainImage) {
        console.log(
          "Updating main image from:",
          mainImage.src,
          "to:",
          thumbnailSrc
        );
        mainImage.src = thumbnailSrc;

        // Also update the data-original-image if it exists
        mainImage.setAttribute("data-original-image", thumbnailSrc);
      } else {
        console.error("Main image element not found!");
      }

      // Update border styling on all thumbnails
      document.querySelectorAll(".thumbnail-item img").forEach((img) => {
        img.classList.remove("border-blue-500", "thumbnail-active");
        img.classList.add("border-transparent");
      });

      // Add active styling to clicked thumbnail
      clickedImg.classList.add("border-blue-500", "thumbnail-active");
      clickedImg.classList.remove("border-transparent");

      // Smooth scroll thumbnail into view
      this.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
        inline: "center",
      });
    });
  });

  // ===== DRAG TO SCROLL FUNCTIONALITY =====
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

// ===== AUTO-HIDE SINGLE OPTION STEPS WITH STOCK DISPLAY =====

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

    this.isSubmitting = false;
    window.isCartSubmitting = false;

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
  }

  initializeAllSectionsEnabled() {
    this.enableColorSelection();
    this.showAllVariantGroups();

    // ✅ CHECK STOCK HERE
    checkVariantStock();

    this.autoSelectSingleOptions();
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

  autoSelectSingleOptions() {
    const typeButtons = document.querySelectorAll(".type-btn");
    const typeSection =
      document.querySelector('[id^="tab-"] .step-section') ||
      document.querySelectorAll(".step-section")[0];

    // ✅ AUTO-SELECT SINGLE TYPE & SHOW STOCK
    if (typeButtons.length === 1) {
      const singleType = typeButtons[0];
      const typeName =
        singleType.querySelector("span")?.textContent.trim() || "";
      const typeId = singleType
        .getAttribute("onclick")
        ?.match(/showVariants\((\d+)/)?.[1];

      if (typeId) {
        this.setTypeSelection(singleType, parseInt(typeId), typeName);
        this.hideSectionByButton(singleType);
        this.showStockIndicatorForHiddenType(typeName);
      }
    }

    const colorButtons = document.querySelectorAll(
      ".color-btn:not([disabled])"
    );
    const colorSection = this.findSectionByTitle("Choose Color");

    // ✅ AUTO-SELECT SINGLE COLOR & SHOW STOCK
    if (colorButtons.length === 1) {
      const singleColor = colorButtons[0];
      const colorId = singleColor.dataset.colorId;
      const colorName = singleColor.dataset.colorName;
      const price = singleColor.dataset.price;
      const image = singleColor.dataset.image;
      const colorCode = singleColor.dataset.colorCode;

      if (colorId) {
        this.setColorFromGrid(colorId, colorName, price, image, colorCode);
        singleColor.classList.add(
          "selected",
          "border-orange-500",
          "bg-orange-50"
        );
        if (colorSection) {
          colorSection.style.display = "none";
          // ✅ SHOW STOCK INDICATOR
          this.showStockIndicatorForHiddenColor(colorName);
        }
      }
    }

    setTimeout(() => {
      this.checkAndAutoSelectVariant();
    }, 100);
  }

  checkAndAutoSelectVariant() {
    if (!this.selectedTypeId) return;

    const visibleVariantButtons = document.querySelectorAll(
      `#variants-${this.selectedTypeId} .variant-btn:not([disabled])`
    );
    const variantSection = this.findSectionByTitle("Choose Size");

    // ✅ AUTO-SELECT SINGLE SIZE & SHOW STOCK
    if (visibleVariantButtons.length === 1) {
      const singleVariant = visibleVariantButtons[0];
      const size = singleVariant
        .querySelector(".text-gray-700")
        ?.textContent.trim();

      if (size) {
        this.setVariantSelection(singleVariant, size, null);
        if (variantSection) {
          variantSection.style.display = "none";
          // ✅ SHOW STOCK INDICATOR
          this.showStockIndicatorForHiddenVariant(size);
        }
      }
    }
  }

  // ✅ NEW: Show stock indicator when type is hidden
  showStockIndicatorForHiddenType(typeName) {
    let container = document.getElementById("hidden-type-indicator");
    if (!container) {
      container = document.createElement("div");
      container.id = "hidden-type-indicator";
      container.className = "";

      const typeSection =
        this.findSectionByTitle("Choose Color") ||
        document.querySelector(".step-section");
      if (typeSection) {
        typeSection.parentNode.insertBefore(container, typeSection);
      }
    }

    container.innerHTML = `
      <div class="text-sm ">
        <strong>Selected: </strong> <span class="" style="font-family: 'Montserrat', sans-serif; color: #2f1200">${typeName}</span>
      </div>
    `;
  }

  // ✅ NEW: Show stock indicator when color is hidden
  showStockIndicatorForHiddenColor(colorName) {
    let container = document.getElementById("hidden-color-indicator");
    if (!container) {
      container = document.createElement("div");
      container.id = "hidden-color-indicator";
      container.className = "";

      const sizeSection =
        this.findSectionByTitle("Choose Size") ||
        document.querySelector(".step-section:last-of-type");
      if (sizeSection) {
        sizeSection.parentNode.insertBefore(container, sizeSection);
      }
    }

    container.innerHTML = `
      <div class="text-sm ">
        <strong>Color:</strong> <span class="" style="font-family: 'Montserrat', sans-serif; color: #2f1200">${colorName}</span>
      </div>
    `;
  }

  showStockIndicatorForHiddenVariant(size) {
    let container = document.getElementById("hidden-size-indicator");
    if (!container) {
      container = document.createElement("div");
      container.id = "hidden-size-indicator";
      container.className = "p-3 mb-2";

      const quantitySection =
        document.getElementById("quantityInput")?.closest("div") ||
        document.querySelector(".step-section:last-of-type");
      if (quantitySection) {
        quantitySection.parentNode.insertBefore(container, quantitySection);
      }
    }

    const selectedVariantBtn = document.querySelector(".variant-btn.selected");

    if (!selectedVariantBtn) {
      container.innerHTML = "";
      return;
    }

    const stock = parseInt(selectedVariantBtn.dataset.stock || 0);
    const discount = parseFloat(selectedVariantBtn.dataset.discount || 0);
    const variantId = selectedVariantBtn.dataset.variantId || "";

    // ✅ GET PRICE DATA
    const originalPrice = parseFloat(selectedVariantBtn.dataset.price || 0);
    const percent = parseFloat(selectedVariantBtn.dataset.percent || 0);

    // Calculate after markup
    const afterMarkup = originalPrice + (originalPrice * percent) / 100;

    // Calculate after regular discount
    const afterRegularDiscount = afterMarkup - (afterMarkup * discount) / 100;

    const timerBadge = selectedVariantBtn.querySelector(".timer-badge");

    let stockDisplay = "";
    if (stock <= 0) {
      stockDisplay =
        '<span class="ml-2 inline-block px-2 py-1 bg-red-100 text-red-700 text-xs rounded font-semibold">OUT OF STOCK</span>';
    } else if (stock <= 5) {
      stockDisplay = `<span class="ml-2 inline-block px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded font-semibold">${stock} left</span>`;
    } else {
      stockDisplay = `<span class="ml-2 inline-block px-2 py-1 bg-green-100 text-green-700 text-xs rounded font-semibold">${stock} in stock</span>`;
    }

    let discountDisplay = "";
    if (discount > 0 && !timerBadge) {
      discountDisplay = `<span class="ml-2 inline-block px-2 py-1 bg-red-500 text-white text-xs rounded font-bold">-${Math.round(
        discount
      )}% OFF</span>`;
    }

    // ✅ BUILD FLASH SALE TIMER DISPLAY WITH PRICE BREAKDOWN
    let flashSaleDisplay = "";
    if (timerBadge) {
      const timerEndTime = parseInt(timerBadge.dataset.endTime || 0);
      const timerVariantId = timerBadge.dataset.variantId || variantId;

      if (timerEndTime > 0) {
        const now = Math.floor(Date.now() / 1000);
        const remaining = timerEndTime - now;

        if (remaining > 0) {
          const currentTimerText =
            timerBadge.querySelector(".timer-display")?.textContent ||
            "00:00:00";

          // ✅ GET TIMER DISCOUNT FROM BADGE
          const timerDiscount = parseFloat(
            selectedVariantBtn.dataset.timerDiscount || 0
          );
          const afterTimerDiscount =
            afterRegularDiscount - (afterRegularDiscount * timerDiscount) / 100;

          flashSaleDisplay = `
          <div class="mt-3 bg-red-700 text-white p-3 rounded-lg shadow-lg">
            <!-- Timer Header -->
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <i class="fas fa-fire-alt text-yellow-300 animate-pulse"></i>
                <span class="font-bold text-sm text-white" style="font-family: 'Montserrat', sans-serif;">FLASH SALE ACTIVE</span>
              </div>
              <span class="timer-display font-mono tracking-wider text-xs bg-black/30 px-2 py-1 rounded" 
                    id="timer-hidden-${timerVariantId}">
                ${currentTimerText},
              </span>
            </div>

            <!-- Price Breakdown -->
            <div class="space-y-1 text-xs bg-white/10 p-2 rounded">
              <div class="flex justify-between items-center">
                <span class="opacity-75">Price:</span>
                <span class="font-semibold">₱${afterMarkup.toLocaleString(
                  "en-PH",
                  { minimumFractionDigits: 2 }
                )}</span>
              </div>
              
              ${
                discount > 0
                  ? `
                <div class="flex justify-between items-center">
                  <span class="opacity-75">Regular Discount (-${Math.round(
                    discount
                  )}%):</span>
                  <span class="font-semibold line-through">₱${afterRegularDiscount.toLocaleString(
                    "en-PH",
                    { minimumFractionDigits: 2 }
                  )}</span>
                </div>
              `
                  : ""
              }
              
              <div class="border-t border-white/20 pt-1 mt-1"></div>
              
              <div class="flex justify-between items-center">
                <span class="text-white font-bold" style="font-family: 'Montserrat', sans-serif; ">Flash Sale (-${Math.round(
                  timerDiscount
                )}%):</span>
               
              </div>
            </div>

            <!-- Savings Display -->
            <div class="mt-2 text-center bg-yellow-400  font-black text-xs px-2 py-1 rounded" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
              YOU SAVE ₱${(
                afterRegularDiscount - afterTimerDiscount
              ).toLocaleString("en-PH", { minimumFractionDigits: 2 })}!
            </div>
          </div>
        `;

          // Start countdown timer
          setTimeout(() => {
            const timerElement = document.getElementById(
              `timer-hidden-${timerVariantId}`
            );
            if (timerElement) {
              const endTime = parseInt(
                timerElement.closest(".timer-badge")?.dataset?.endTime ||
                  timerEndTime
              );
              const timerInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = endTime - now;

                if (remaining <= 0) {
                  timerElement.textContent = "EXPIRED";
                  clearInterval(timerInterval);
                  return;
                }

                const days = Math.floor(remaining / 86400);
                const hours = Math.floor((remaining % 86400) / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                const totalHours = days * 24 + hours;

                timerElement.textContent = `${String(totalHours).padStart(
                  2,
                  "0"
                )}:${String(minutes).padStart(2, "0")}:${String(
                  seconds
                ).padStart(2, "0")}`;
              }, 1000);
            }
          }, 100);
        } else {
          flashSaleDisplay = `
          <div class="mt-2 bg-gray-400 text-white text-xs font-bold px-3 py-2 rounded-lg text-center">
            <i class="fas fa-clock mr-1"></i> FLASH SALE EXPIRED
          </div>
        `;
        }
      }
    }

    container.innerHTML = `
    <div class="text-sm">
      <div class="flex flex-wrap items-center gap-2 mb-2" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
        <strong>Size Selected:</strong> 
        <span class="font-semibold">${size}</span>
        ${discountDisplay}
        ${stockDisplay}
      </div>
      ${flashSaleDisplay}
    </div>
  `;
  }

  findSectionByTitle(title) {
    const sections = document.querySelectorAll(".step-section");
    for (let section of sections) {
      const heading = section.querySelector("h3");
      if (heading && heading.textContent.includes(title)) {
        return section;
      }
    }
    return null;
  }

  hideSectionByButton(button) {
    let parent = button.parentElement;
    while (parent) {
      if (parent.classList.contains("step-section")) {
        parent.style.display = "none";
        break;
      }
      parent = parent.parentElement;
    }
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
            // Don't override stock-based disabled state
            if (!btn.dataset.stock || parseInt(btn.dataset.stock) > 0) {
              btn.disabled = false;
              btn.classList.remove("opacity-50", "cursor-not-allowed");
            }
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

    setTimeout(() => {
      this.checkAndAutoSelectVariant();
    }, 50);

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

    // ✅ CHECK STOCK AFTER TYPE SELECTED
    setTimeout(() => {
      checkVariantStock();
      checkAvailableVariants();
    }, 100);

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

  // ✅ FIXED: setColorFromGrid in ProductSelector class
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

if (image && image.trim() !== '') {
  // image from DB = "uploads/img_xxx.webp"
  // BASE_URL = "/noble"
  let imagePath = `${BASE_URL}/${image}`;
  
  if (this.elements.mainImage) {
    this.elements.mainImage.src = imagePath;
  }
  const sidebarImage = document.getElementById("sidebar-product-image");
  if (sidebarImage) {
    sidebarImage.src = imagePath;
  }
} else {
  // No color image - restore original type/product image
  const originalSrc = this.elements.mainImage?.dataset.originalImage;
  if (originalSrc && this.elements.mainImage) {
    this.elements.mainImage.src = originalSrc;
  }
}

    // ✅ If variant is already selected, update the price display
    if (this.selectedVariantData) {
      this.updateProductHeaderPrice();
      // ✅ Also update the total price in the purchase section
      this.updateTotalPrice();

      // ✅ Trigger full price update
      setTimeout(() => {
        updateAllPriceDisplays();
      }, 50);
    }

    // ✅ CHECK STOCK AFTER COLOR SELECTED
    setTimeout(() => {
      checkVariantStock();
      checkAvailableVariants();
      this.checkAndAutoSelectVariant();
    }, 50);

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

  // ✅ FIXED: setVariantSelection in ProductSelector class
  setVariantSelection(button, size, color = null) {
    // ✅ CHECK STOCK FIRST
    const stock = parseInt(button.dataset.stock || 0);
    if (stock <= 0) {
      this.showNotification("This variant is out of stock", "error");
      disableAddToCartButton();
      return;
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

    button.classList.add(
      "selected",
      "border-orange-500",
      "bg-orange-50",
      "ring-1",
      "ring-orange-500"
    );
    button.classList.remove("border-gray-200", "bg-white");
const originalPrice = parseFloat(button.dataset.price);
const percent = parseFloat(button.dataset.percent) || 0;
const discount = parseFloat(button.dataset.discount) || 0;
const variantId = button.dataset.variantId;

// ✅ Price from DB is already the final discounted price — use directly
const finalPrice = parseFloat(button.dataset.price);
const timerBadge = button.querySelector('.timer-badge');
let timerDiscount = 0;

if (timerBadge) {
    const endTime = parseInt(timerBadge.dataset.endTime || 0);
    const now = Math.floor(Date.now() / 1000);
    if (endTime > now) {
        timerDiscount = parseFloat(button.dataset.timerDiscount || 0);
    }
}

this.selectedVariantData = {
    price: finalPrice,
    originalPrice: originalPrice,
    percent: percent,
    discount: discount,
    timerDiscount: timerDiscount,
    variantId: variantId,
    size: size,
    color: color || "",
    stock: stock,
};

    if (this.elements.selectedVariant) {
      this.elements.selectedVariant.value = size;
    }
    if (this.elements.variantId) {
      this.elements.variantId.value = variantId;
    }

    // ✅ Update all displays
    this.updateProductHeaderPrice();
    this.updateTotalPrice();

    setTimeout(() => {
      if (typeof updateAllPriceDisplays === "function") {
        updateAllPriceDisplays(); // ← This is key!
      }
      validateSelectedVariant();

      if (stock <= 0) {
        disableAddToCartButton();
      } else {
        this.updatePurchaseButton();
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

  // ✅ FIXED: Corrected calculateTotalPrice() function
  calculateTotalPrice() {
    let totalPrice = 0;
    const hasSelections = this.selectedColorData || this.selectedVariantData;

    if (hasSelections) {
      if (this.selectedVariantData) {
        // Get the quantity from input
        const quantityInput = document.getElementById("quantityInput");
        const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

        // ✅ CRITICAL FIX: Use final price from variant data (already calculated)
        let unitPrice = parseFloat(this.selectedVariantData.price) || 0;

        // Add color price
        if (this.selectedColorData && this.selectedColorData.price) {
          unitPrice += parseFloat(this.selectedColorData.price);
        }

        // Round to 2 decimals
        unitPrice = Math.round(unitPrice * 100) / 100;

        // Multiply by quantity
        totalPrice = Math.round(unitPrice * quantity * 100) / 100;
      } else {
        totalPrice = this.basePrice;
      }
    }

    return { totalPrice, hasSelections };
  }

  // ✅ Update updateProductHeaderPrice() - NO RECALCULATION!
  updateProductHeaderPrice() {
    if (!this.selectedVariantData) {
      this.hideProductHeaderPrice();
      return;
    }

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

    // ✅ USE FINAL PRICE DIRECTLY - NO RECALCULATION!
    let displayPrice = parseFloat(this.selectedVariantData.price) || 0;

    // Add color price
    if (this.selectedColorData && this.selectedColorData.price) {
      displayPrice += parseFloat(this.selectedColorData.price);
    }

    if (selectedSizeText && this.selectedVariantData.size) {
      selectedSizeText.textContent = this.selectedVariantData.size;
    }

    // Show discount badge only if there WAS a discount applied
    const discountValue = parseFloat(this.selectedVariantData.discount) || 0;

    if (discountValue > 0) {
      if (originalPriceContainer && originalPriceElement) {
        originalPriceContainer.classList.remove("hidden");

        // Show original price BEFORE discount (for information)
        const originalWithMarkup =
          this.selectedVariantData.originalPrice +
          (this.selectedVariantData.originalPrice *
            this.selectedVariantData.percent) /
            100;
        let priceBeforeDiscount = originalWithMarkup;

        if (this.selectedColorData && this.selectedColorData.price) {
          priceBeforeDiscount += parseFloat(this.selectedColorData.price);
        }

        originalPriceElement.textContent = `₱${priceBeforeDiscount.toLocaleString(
          "en-PH",
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`;
      }

      if (discountBadge && discountPercent) {
        discountBadge.classList.remove("hidden");
        discountPercent.textContent = Math.round(discountValue);
      }
    } else {
      if (originalPriceContainer) {
        originalPriceContainer.classList.add("hidden");
      }
      if (discountBadge) {
        discountBadge.classList.add("hidden");
      }
    }

    finalPriceElement.textContent = `₱${displayPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    this.updateTotalPrice();
  }

  hideProductHeaderPrice() {
    const priceDisplay = document.getElementById("product-price-display");
    if (priceDisplay) {
      priceDisplay.classList.add("hidden");
    }
  }

  // ✅ FIXED: updateTotalPrice() function
  updateTotalPrice() {
    const { totalPrice, hasSelections } = this.calculateTotalPrice();

    if (this.elements.totalPrice) {
      if (hasSelections) {
        this.elements.totalPrice.textContent = `₱${totalPrice.toLocaleString(
          "en-PH",
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`;
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
    // ✅ SAFETY CHECK: Make sure productSelector exists
    if (!window.productSelector) {
      console.warn("ProductSelector not initialized yet");
      return;
    }

    const hasRequiredSelections =
      this.selectedTypeId && this.selectedColorData && this.selectedVariantData;

    // ✅ CHECK STOCK
    let isOutOfStock = false;
    if (hasRequiredSelections) {
      const selectedBtn = document.querySelector(".variant-btn.selected");
      if (selectedBtn) {
        const stock = parseInt(selectedBtn.dataset.stock || 0);
        isOutOfStock = stock <= 0;
      }
    }

    const isWindows =
      document.querySelector('[name="is_windows"]')?.value === "1";

    if (isWindows) {
      const contactBtn = this.elements.contactUsBtn;
      const contactBtnText = this.elements.contactBtnText;

      if (contactBtn && contactBtnText) {
        if (hasRequiredSelections && !isOutOfStock) {
          contactBtn.disabled = false;
          contactBtn.className =
            "w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300 bg-black hover:bg-blue-600 text-white";
          contactBtnText.innerHTML =
            '<i class="fas fa-phone mr-2"></i>Contact Us for Quote';
        } else {
          contactBtn.disabled = true;
          contactBtn.className =
            "w-full py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
          const msg = isOutOfStock
            ? "Selected option is out of stock"
            : "Select all options first";
          contactBtnText.innerHTML = `<i class="fas fa-phone mr-2"></i>${msg}`;
        }
      }
    } else {
      const addToCartBtn = this.elements.addToCartBtn;
      const btnText = this.elements.btnText;

      if (addToCartBtn && btnText) {
        if (hasRequiredSelections && !isOutOfStock) {
          addToCartBtn.disabled = false;
          addToCartBtn.className =
            "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-black hover:bg-orange-600 text-white";
          btnText.innerHTML =
            '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
        } else {
          addToCartBtn.disabled = true;
          addToCartBtn.className =
            "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
          const msg = isOutOfStock
            ? "Out of Stock"
            : "Select first";
          btnText.innerHTML = `<i class="fas fa-shopping-cart mr-2"></i> ${msg}`;
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

    // ✅ ADD STOCK VALIDATION
    if (this.selectedVariantData) {
      const selectedBtn = document.querySelector(".variant-btn.selected");
      if (selectedBtn) {
        const stock = parseInt(selectedBtn.dataset.stock || 0);
        if (stock <= 0) {
          errors.push("Selected variant is out of stock");
        }
      }
    }

    return errors;
  }

  handleFormSubmit(e) {
    e.preventDefault();
    e.stopPropagation();

    if (window.isCartSubmitting || this.isSubmitting) {
      return;
    }

    const errors = this.validateSelections();
    if (errors.length > 0) {
      this.showNotification(errors.join(", "), "error");
      return;
    }

    this.submitToCart();
  }

  async submitToCart() {
    try {
      this.isSubmitting = true;
      window.isCartSubmitting = true;
      const addToCartBtn = this.elements.addToCartBtn;
      if (addToCartBtn) {
        addToCartBtn.disabled = true;
        addToCartBtn.style.opacity = "0.5";
      }

      const formData = this.buildFormData();
      const response = await fetch(`${BASE_URL}/addcart`, {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
              if (typeof refreshCart === 'function') {
            refreshCart();
        }
        this.showNotification(
          data.message || "Product added to cart!",
          "success"
        );
       
       // ✅ Direct badge update - no extra fetch
        if (window.updateCartBadge) window.updateCartBadge(data.cart_count);
      } else {
        if (data.message === "You must be logged in to pre-order.") {
          this.showNotification("Please log in to pre-order.", "error");
        } else {
          throw new Error(data.message || "Add to cart failed.");
        }
      }
    } catch (error) {
      this.showNotification("" + error.message, "error");
      console.error("Add to cart error:", error);
    } finally {
      this.isSubmitting = false;
      window.isCartSubmitting = false;

      const addToCartBtn = this.elements.addToCartBtn;
      if (
        addToCartBtn &&
        this.selectedTypeId &&
        this.selectedColorData &&
        this.selectedVariantData
      ) {
        addToCartBtn.disabled = false;
        addToCartBtn.style.opacity = "1";
      }
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
    let quantity = 1;

    if (quantityInput) {
      const val = parseInt(quantityInput.value);
      if (!isNaN(val) && val > 0) {
        quantity = val;
      }
    }

    formData.append("quantity", quantity);

    return formData;
  }

  showNotification(message, type = "success") {
    const existingNotifications = document.querySelectorAll(
      ".notification-toast"
    );
    existingNotifications.forEach((notification) => notification.remove());

    const notification = document.createElement("div");
    notification.className = `notification-toast fixed bottom-20 right-4 z-[150] px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;

    if (type === "success") {
      notification.classList.add("bg-green-500", "text-white");
    } else if (type === "error") {
      notification.classList.add("bg-red-500", "text-white");
    }

    const iconClass =
      type === "success" ? "fas fa-check-circle" : "fas fa-exclamation-circle";

    notification.innerHTML = `
      <div class="flex items-center">
        <i class="${iconClass} mr-3"></i>
        <span class="font-medium">${message}</span>
        <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
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
    // const cartCountElements = document.querySelectorAll(
    //   ".cart-count, #cartCount"
    // );
    // cartCountElements.forEach((element) => {
    //   if (element) {
    //     element.textContent = newCount;
    //     element.classList.add("animate-bounce");
    //     setTimeout(() => {
    //       element.classList.remove("animate-bounce");
    //     }, 1000);
    //   }
    // });
  }
}

// ===== STOCK CHECK FUNCTIONS =====

function checkVariantStock() {
  const variantButtons = document.querySelectorAll(".variant-btn");
  console.log(`Checking stock for ${variantButtons.length} variants`);

  variantButtons.forEach((button, index) => {
    const stock = parseInt(button.dataset.stock || 0);
    const size = button.dataset.size || "Unknown";
    const variantId = button.dataset.variantId || "N/A";

    console.log(
      `Variant ${index}: Size=${size}, Stock=${stock}, ID=${variantId}`
    );

    // Remove existing badges
    const existingBadge = button.querySelector(".stock-badge");
    const existingLowStock = button.querySelector(".low-stock-badge");
    if (existingBadge) existingBadge.remove();
    if (existingLowStock) existingLowStock.remove();

    if (stock <= 0) {
      // OUT OF STOCK
      button.disabled = true;
      button.classList.add(
        "opacity-50",
        "cursor-not-allowed",
        "bg-red-50",
        "border-red-300"
      );
      button.classList.remove(
        "hover:border-orange-500",
        "hover:shadow-md",
        "selected"
      );
      button.style.pointerEvents = "none";

      const badge = document.createElement("div");
      badge.className =
        "stock-badge absolute top-1 right-1 bg-red-500 text-white text-[8px] px-1.5 py-0.5 rounded-full font-bold z-10";
      badge.textContent = "OUT";
      button.style.position = "relative";
      button.appendChild(badge);

      console.log(`✗ ${size} - OUT OF STOCK`);

      // ✅ IF THIS VARIANT IS CURRENTLY SELECTED, DISABLE ADD TO CART
      if (button.classList.contains("selected")) {
        disableAddToCartButton();
      }
    } else if (stock <= 5 && stock > 0) {
      // LOW STOCK
      button.disabled = false;
      button.classList.remove(
        "opacity-50",
        "cursor-not-allowed",
        "bg-red-50",
        "border-red-300"
      );
      button.classList.add("hover:border-orange-500", "hover:shadow-md");
      button.style.pointerEvents = "auto";

      const lowStockBadge = document.createElement("div");
      lowStockBadge.className =
        "low-stock-badge absolute top-1 right-1 bg-yellow-500 text-white text-[8px] px-1 py-0.5 rounded-full font-bold z-10 animate-pulse";
      lowStockBadge.textContent = `${stock} left`;
      button.style.position = "relative";
      button.appendChild(lowStockBadge);

      console.log(`⚠ ${size} - LOW STOCK: ${stock} remaining`);
    } else {
      // IN STOCK
      button.disabled = false;
      button.classList.remove(
        "opacity-50",
        "cursor-not-allowed",
        "bg-red-50",
        "border-red-300"
      );
      button.classList.add("hover:border-orange-500", "hover:shadow-md");
      button.style.pointerEvents = "auto";

      console.log(`✓ ${size} - IN STOCK: ${stock} available`);
    }
  });

  // ✅ AFTER CHECKING ALL VARIANTS, UPDATE BUTTON STATE
  setTimeout(() => {
    if (window.productSelector && window.productSelector.updatePurchaseButton) {
      window.productSelector.updatePurchaseButton();
    }
  }, 100);
}

function checkAvailableVariants() {
  const variantButtons = document.querySelectorAll(
    ".variant-btn:not([disabled])"
  );
  const variantContainer = document.getElementById("variant-container");

  console.log(`Available variants: ${variantButtons.length}`);

  if (variantButtons.length === 0 && variantContainer) {
    variantContainer.innerHTML = `
      <div class="text-center p-6 bg-red-50 rounded-lg border-2 border-red-200">
        <i class="fas fa-exclamation-circle text-red-500 text-3xl mb-2 block"></i>
        <p class="text-red-700 font-semibold">All variants out of stock</p>
        <p class="text-red-600 text-sm mt-1">Please check back later or contact us</p>
      </div>
    `;
  }
}

function validateSelectedVariant() {
  const selectedVariantId = document.getElementById("variant_id")?.value;
  const addToCartBtn = document.getElementById("addToCartBtn");

  if (!selectedVariantId) {
    console.log("No variant selected");
    return false;
  }

  const selectedButton = document.querySelector(
    `[data-variant-id="${selectedVariantId}"]`
  );
  console.log("Selected variant button:", selectedButton);

  if (selectedButton) {
    const stock = parseInt(selectedButton.dataset.stock || 0);

    if (stock <= 0 || selectedButton.disabled) {
      console.log("Selected variant is disabled/out of stock");
      disableAddToCartButton();
      return false;
    }
  }

  console.log("Selected variant is available");
  return true;
}

function injectStockStyles() {
  if (!document.querySelector("style[data-stock-styles]")) {
    const styleElement = document.createElement("style");
    styleElement.setAttribute("data-stock-styles", "true");
    styleElement.textContent = `
      .variant-btn[disabled] {
        opacity: 0.6 !important;
        cursor: not-allowed !important;
        background-color: #fee2e2 !important;
        border-color: #fca5a5 !important;
        pointer-events: none !important;
      }
      
      .variant-btn[disabled]:hover {
        transform: none !important;
        box-shadow: none !important;
        border-color: #fca5a5 !important;
      }
      
      .variant-btn[disabled] .text-gray-700 {
        color: #991b1b !important;
        text-decoration: line-through;
      }
      
      .variant-btn:not([disabled]) {
        cursor: pointer;
        pointer-events: auto;
      }
      
      .low-stock-badge {
        animation: pulse 2s infinite;
      }
      
      .stock-badge {
        z-index: 50;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
      }
      
      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
      }
    `;
    document.head.appendChild(styleElement);
    console.log("Stock styles injected");
  }
}

document.addEventListener("DOMContentLoaded", function () {
  console.log("=== INITIALIZING STOCK CHECK ===");

  if (typeof ProductSelector !== "undefined") {
    // ✅ Initialize ProductSelector FIRST
    window.productSelector = new ProductSelector();

    const mainImage = document.getElementById("main-product-image");
    if (mainImage) {
      mainImage.dataset.originalSrc = mainImage.src;
    }

    // ✅ THEN inject styles and check stock
    injectStockStyles();

    // ✅ Use setTimeout to ensure DOM is fully ready
    setTimeout(() => {
      checkVariantStock();
      checkAvailableVariants();
      validateSelectedVariant();
      // ✅ IMMEDIATE STOCK CHECK FOR SELECTED VARIANT
      const selectedVariant = document.querySelector(".variant-btn.selected");
      if (selectedVariant) {
        const stock = parseInt(selectedVariant.dataset.stock || 0);
        if (stock <= 0) {
          disableAddToCartButton();
        }
      }

      console.log("=== STOCK CHECK COMPLETE ===");
    }, 100);
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

    quantityInput.addEventListener("input", validateQuantity);
    quantityInput.addEventListener("change", validateQuantity);
  }
});

// Reinitialize stock check after selections
function reinitializeStockCheck() {
  setTimeout(() => {
    checkVariantStock();
    checkAvailableVariants();
    validateSelectedVariant();
  }, 100);
}

// ===== GLOBAL FUNCTIONS =====
function showVariants(typeId, typeName) {
  if (window.productSelector) {
    window.productSelector.showVariants(typeId, typeName);
  }
}

// ✅ FIXED: selectColorFromGrid function - should trigger price update
function selectColorFromGrid(colorId, colorName, price, image, colorCode) {
  // Remove selected class from all colors
  document.querySelectorAll(".color-btn").forEach((btn) => {
    btn.classList.remove("selected", "border-orange-500", "bg-orange-50");
    btn.classList.add("border-gray-200", "bg-white");
  });

  // Add selected class to clicked button
  const clickedButton = event.currentTarget;
  clickedButton.classList.add("selected", "border-orange-500", "bg-orange-50");
  clickedButton.classList.remove("border-gray-200", "bg-white");

  if (!window.productSelector) return;

  // Call setColorFromGrid to store the color data
  window.productSelector.setColorFromGrid(
    colorId,
    colorName,
    price,
    image,
    colorCode
  );

  // ✅ CRITICAL: Trigger price update after color selection
  setTimeout(() => {
    if (window.productSelector?.selectedVariantData) {
      updateAllPriceDisplays(); // Update prices immediately
    }
  }, 50);
}

// ✅ FIXED: selectVariant function - make sure it updates prices
function selectVariant(button, size, color = null) {
  // ✅ PREVENT SELECTION IF OUT OF STOCK
  if (button.disabled) {
    const stock = parseInt(button.dataset.stock || 0);
    const msg =
      stock <= 0
        ? "This size is out of stock for the selected color"
        : "This size variant is currently unavailable";

    window.productSelector?.showNotification(msg, "error");
    return false;
  }

  if (window.productSelector) {
    window.productSelector.selectVariant(button, size, color);
  }

  // ✅ Trigger price update after variant selection
  setTimeout(() => {
    validateSelectedVariant();
    updateAllPriceDisplays(); // ← Add this line
  }, 100);
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

// ✅ FIXED: updateAllPriceDisplays - Make sure it's calculating correctly
function updateAllPriceDisplays() {
  if (!window.productSelector || !window.productSelector.selectedVariantData) {
    hideAllPriceDisplays();
    return;
  }

  const variant = window.productSelector.selectedVariantData;
  const quantityInput = document.getElementById("quantityInput");
  const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
  const colorPrice = window.productSelector.selectedColorData?.price || 0;

  console.log("📊 Price Calculation Debug:", {
    variantPrice: variant.price,
    colorPrice: colorPrice,
    quantity: quantity,
    total: (variant.price + colorPrice) * quantity,
  });

  // Calculate unit price (variant price + color price)
  const unitPrice =
    Math.round((parseFloat(variant.price) + parseFloat(colorPrice)) * 100) /
    100;
  const totalPrice = Math.round(unitPrice * quantity * 100) / 100;

  console.log("💰 Final Prices:", { unitPrice, totalPrice, quantity });

  // Update header (unit price)
  const finalPriceElement = document.getElementById("final-price");
  if (finalPriceElement) {
    finalPriceElement.textContent = `₱${unitPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  // Update quantity preview
  const previewContainer = document.getElementById("quantityPricePreview");
  const previewQty = document.getElementById("previewQty");
  const previewTotal = document.getElementById("previewTotal");

  if (previewQty && previewTotal && previewContainer) {
    previewQty.textContent = quantity;
    previewTotal.textContent = `₱${totalPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
    previewContainer.classList.remove("hidden");
  }

  // Update total price section
  const totalPriceElement = document.getElementById("totalPrice");
  if (totalPriceElement) {
    totalPriceElement.textContent = `₱${totalPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  updatePurchaseButton();
}

// ✅ UPDATE PRODUCT HEADER PRICE
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

  // ✅ CORRECT CALCULATION (NO VAT HERE - VAT is for checkout only):
  // 1. Start with original price
  let basePrice = parseFloat(variant.price) || 0;

  // 2. Apply markup
  const markupPercent = parseFloat(variant.percent) || 0;
  basePrice = basePrice + (basePrice * markupPercent) / 100;

  // 3. Apply discount
  const discountValue = parseFloat(variant.discount) || 0;
  let finalPrice = basePrice;

  if (discountValue > 0) {
    finalPrice = basePrice - (basePrice * discountValue) / 100;
  }

  // 4. Add color price (NO VAT - this is just add-on price)
  if (window.productSelector?.selectedColorData?.price) {
    finalPrice += parseFloat(window.productSelector.selectedColorData.price);
  }

  // ✅ ROUND to 2 decimals ONLY - NO VAT multiplication
  finalPrice = Math.round(finalPrice * 100) / 100;

  // Display original price if discount exists
  if (discountValue > 0) {
    if (originalPriceElement && originalPriceElement.parentElement) {
      originalPriceElement.parentElement.classList.remove("hidden");

      // Show price BEFORE discount (after markup, before discount)
      const priceBeforeDiscount =
        basePrice + (window.productSelector?.selectedColorData?.price || 0);

      originalPriceElement.textContent = `₱${priceBeforeDiscount.toLocaleString(
        "en-PH",
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`;
    }

    finalPriceElement.textContent = `₱${finalPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    if (discountBadge && discountPercent) {
      discountBadge.classList.remove("hidden");
      discountPercent.textContent = Math.round(discountValue);
    }
  } else {
    if (originalPriceElement && originalPriceElement.parentElement) {
      originalPriceElement.parentElement.classList.add("hidden");
    }

    finalPriceElement.textContent = `₱${finalPrice.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    if (discountBadge) {
      discountBadge.classList.add("hidden");
    }
  }

  this.updateTotalPrice?.();
}

// ✅ HELPER: Format display price
function formatPrice(amount) {
  return `₱${parseFloat(amount).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

// ✅ HELPER: Hide all price displays
function hideAllPriceDisplays() {
  document.getElementById("product-price-display")?.classList.add("hidden");
  document.getElementById("quantityPricePreview")?.classList.add("hidden");
  const totalPrice = document.getElementById("totalPrice");
  if (totalPrice) totalPrice.textContent = "₱0.00";

  const selectionStatus = document.getElementById("selectionStatus");
  if (selectionStatus)
    selectionStatus.textContent = "Complete steps 1-3 to see price";
}

// ✅ UPDATE QUANTITY PREVIEW (NO VAT - just product price)
function updateQuantityPreview(variant, quantity) {
  const previewContainer = document.getElementById("quantityPricePreview");
  const previewQty = document.getElementById("previewQty");
  const previewTotal = document.getElementById("previewTotal");

  if (!previewContainer || !previewQty || !previewTotal) return;

  // ✅ SAME CALCULATION - NO VAT
  let basePrice = parseFloat(variant.price) || 0;
  const markupPercent = parseFloat(variant.percent) || 0;
  basePrice = basePrice + (basePrice * markupPercent) / 100;

  const discountValue = parseFloat(variant.discount) || 0;
  let unitPrice = basePrice;

  if (discountValue > 0) {
    unitPrice = basePrice - (basePrice * discountValue) / 100;
  }

  if (window.productSelector?.selectedColorData?.price) {
    unitPrice += parseFloat(window.productSelector.selectedColorData.price);
  }

  // ✅ ROUND properly
  unitPrice = Math.round(unitPrice * 100) / 100;
  const totalPrice = Math.round(unitPrice * quantity * 100) / 100;

  previewQty.textContent = quantity;
  previewTotal.textContent = `₱${totalPrice.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  previewContainer.classList.remove("hidden");
}

// ✅ UPDATE TOTAL PRICE (NO VAT - just product price)
function updateTotalPrice(variant, quantity) {
  const totalPriceElement = document.getElementById("totalPrice");
  const selectionStatus = document.getElementById("selectionStatus");

  if (!totalPriceElement) return;

  // ✅ SAME CALCULATION - NO VAT
  let basePrice = parseFloat(variant.price) || 0;
  const markupPercent = parseFloat(variant.percent) || 0;
  basePrice = basePrice + (basePrice * markupPercent) / 100;

  const discountValue = parseFloat(variant.discount) || 0;
  let unitPrice = basePrice;

  if (discountValue > 0) {
    unitPrice = basePrice - (basePrice * discountValue) / 100;
  }

  if (window.productSelector?.selectedColorData?.price) {
    unitPrice += parseFloat(window.productSelector.selectedColorData.price);
  }

  // ✅ ROUND properly
  unitPrice = Math.round(unitPrice * 100) / 100;
  const totalPrice = Math.round(unitPrice * quantity * 100) / 100;

  totalPriceElement.textContent = `₱${totalPrice.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  if (selectionStatus) {
    const status = [];
    if (window.productSelector?.selectedTypeId) {
      status.push(`Type: ${document.getElementById("selected_type")?.value}`);
    }
    if (window.productSelector?.selectedColorData) {
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
  document.getElementById("selectionStatus").textContent =
    "Complete steps 1-3 to see price";
}

function updatePurchaseButton() {
  // ✅ SAFETY CHECK
  if (!window.productSelector) {
    console.warn("ProductSelector not ready");
    return;
  }

  const hasRequiredSelections =
    window.productSelector.selectedTypeId &&
    window.productSelector.selectedColorData &&
    window.productSelector.selectedVariantData;

  // ✅ CHECK STOCK
  let isOutOfStock = false;
  if (hasRequiredSelections) {
    const selectedBtn = document.querySelector(".variant-btn.selected");
    if (selectedBtn) {
      const stock = parseInt(selectedBtn.dataset.stock || 0);
      isOutOfStock = stock <= 0;
    }
  }

  const isWindows =
    document.querySelector('[name="is_windows"]')?.value === "1";

  if (isWindows) {
    const contactBtn = document.getElementById("contactUsBtn");
    const contactBtnText = document.getElementById("contactBtnText");

    if (contactBtn && contactBtnText) {
      if (hasRequiredSelections && !isOutOfStock) {
        contactBtn.disabled = false;
        contactBtn.className =
          "w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300 bg-black hover:bg-blue-600 text-white";
        contactBtnText.innerHTML =
          '<i class="fas fa-phone mr-2"></i>Contact Us for Quote';
      } else {
        contactBtn.disabled = true;
        contactBtn.className =
          "w-full py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
        const msg = isOutOfStock
          ? "Selected option is out of stock"
          : "Select first";
        contactBtnText.innerHTML = `<i class="fas fa-phone mr-2"></i>${msg}`;
      }
    }
  } else {
    const addToCartBtn = document.getElementById("addToCartBtn");
    const btnText = document.getElementById("btnText");

    if (addToCartBtn && btnText) {
      if (hasRequiredSelections && !isOutOfStock) {
        addToCartBtn.disabled = false;
        addToCartBtn.className =
          "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-black hover:bg-orange-600 text-white";
        btnText.innerHTML =
          '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
      } else {
        addToCartBtn.disabled = true;
        addToCartBtn.className =
          "flex-1 py-3 lg:py-4 text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed";
        const msg = isOutOfStock ? "Out of Stock" : "Select first";
        btnText.innerHTML = `<i class="fas fa-shopping-cart mr-2"></i> ${msg}`;
      }
    }
  }
}

// ===== SKU INFO FUNCTIONS =====
function showSkuInfo(button) {
  const display = document.getElementById("sku-info-display");
  const content = document.getElementById("sku-info-content");
  const toggleBtn = document.getElementById("toggle-sku-btn");
  const skuInfoJson = button.getAttribute("data-sku-info");

  content.classList.remove("collapsed", "expanded");
  toggleBtn.classList.add("hidden");

  if (!skuInfoJson) {
    display.classList.add("hidden");
    return;
  }

  try {
    const skuInfo = JSON.parse(skuInfoJson);
    let html = "";

    if (Object.keys(skuInfo).length === 1 && skuInfo.notes) {
      html = `<div class="bg-white p-4 rounded-lg"><div class="whitespace-pre-wrap text-gray-800">${escapeHtml(
        skuInfo.notes
      )}</div></div>`;
    } else {
      html = '<div class="bg-white p-4 rounded-lg"><div class="space-y-3">';
      for (const [key, value] of Object.entries(skuInfo)) {
        const label =
          key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, " ");
        html += `<div class="flex items-start border-b border-gray-100 pb-2 last:border-0 last:pb-0">
          <span class="text-sm font-semibold text-orange-600 min-w-[120px]">${escapeHtml(
            label
          )}:</span>
          <span class="text-sm text-gray-800 flex-1">${escapeHtml(value)}</span>
        </div>`;
      }
      html += "</div></div>";
    }

    content.innerHTML = html;
    display.classList.remove("hidden");

    setTimeout(() => {
      if (content.scrollHeight > 120) {
        content.classList.add("collapsed");
        toggleBtn.classList.remove("hidden");
        updateToggleButton();
      }
    }, 50);
  } catch (e) {
    console.error("Error parsing SKU info:", e);
    display.classList.add("hidden");
  }
}

function toggleSkuContent() {
  const content = document.getElementById("sku-info-content");
  const currentlyExpanded = content.classList.contains("expanded");

  if (currentlyExpanded) {
    content.classList.remove("expanded");
    content.classList.add("collapsed");
  } else {
    content.classList.remove("collapsed");
    content.classList.add("expanded");
  }

  updateToggleButton();
}

function updateToggleButton() {
  const toggleText = document.getElementById("toggle-sku-text");
  const toggleIcon = document.getElementById("toggle-sku-icon");
  const content = document.getElementById("sku-info-content");
  const currentlyExpanded = content.classList.contains("expanded");

  if (currentlyExpanded) {
    toggleText.textContent = "See Less";
    toggleIcon.classList.remove("fa-chevron-down");
    toggleIcon.classList.add("fa-chevron-up");
  } else {
    toggleText.textContent = "See More";
    toggleIcon.classList.remove("fa-chevron-up");
    toggleIcon.classList.add("fa-chevron-down");
  }
}

function hideSkuInfo() {
  const display = document.getElementById("sku-info-display");
  display.classList.add("hidden");
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// ===== CALCULATOR FUNCTIONS (FIXED) =====

function updateCalculatorFromVariant(button) {
  const width = parseFloat(button.dataset.width) || 0;
  const height = parseFloat(button.dataset.height) || 0;
  const length = parseFloat(button.dataset.length) || 0;
  const size = button.querySelector(".text-gray-700").textContent.trim();

  const widthM = width / 1000;
  const heightM = height / 1000;
  const areaPerPiece = widthM * heightM;

  window.selectedVariantDimensions = {
    width,
    height,
    length,
    size,
    areaPerPiece,
  };

  const calcSection = document.getElementById("calculatorSection");
  if (calcSection) {
    calcSection.classList.remove("hidden");
  }

  const sizeDisplay = document.getElementById("selectedSizeDisplay");
  if (sizeDisplay) {
    sizeDisplay.textContent = size;
  }

  const widthEl = document.getElementById("calcWidth");
  const heightEl = document.getElementById("calcHeight");
  const lengthEl = document.getElementById("calcLength");
  const areaEl = document.getElementById("calcAreaPerPiece");

  if (widthEl) widthEl.textContent = width;
  if (heightEl) heightEl.textContent = height;
  if (lengthEl) lengthEl.textContent = length;
  if (areaEl) areaEl.textContent = areaPerPiece.toFixed(4) + " m²";

  const areaInput = document.getElementById("userArea");
  const piecesDisplay = document.getElementById("piecesFromArea");
  const resultsSection = document.getElementById("userCalculationResults");

  if (areaInput) areaInput.value = "";
  if (piecesDisplay) piecesDisplay.textContent = "0";
  if (resultsSection) resultsSection.classList.add("hidden");
}

// ===== FIXED: calculateFromArea() =====
function calculateFromArea() {
  const areaInput = document.getElementById("userArea");
  const piecesDisplay = document.getElementById("piecesFromArea");
  const resultsSection = document.getElementById("userCalculationResults");
  const adhesiveKgEl = document.getElementById("userAdhesiveNeededKg");
  const adhesiveBagsEl = document.getElementById("userAdhesiveBags");
  const adhesiveWholeBagsEl = document.getElementById("userAdhesiveWholeBags");
  const bracketsEl = document.getElementById("userBracketsNeeded");

  if (!areaInput || !piecesDisplay) return;

  const area = parseFloat(areaInput.value);

  // Validation
  if (!area || area <= 0) {
    piecesDisplay.textContent = "0";
    if (resultsSection) resultsSection.classList.add("hidden");
    return;
  }

  if (
    !window.selectedVariantDimensions ||
    !window.selectedVariantDimensions.areaPerPiece ||
    window.selectedVariantDimensions.areaPerPiece <= 0
  ) {
    piecesDisplay.textContent = "0";
    if (resultsSection) resultsSection.classList.add("hidden");
    return;
  }

  // ===== CALCULATE PIECES =====
  const piecesNeeded = Math.ceil(
    area / window.selectedVariantDimensions.areaPerPiece
  );

  // ===== ADHESIVE CALCULATION =====
  // For AAC blocks: 3 kg per sqm
  const adhesiveRateKgPerSqm = 3.0;
  // Bag weight: 25 kg
  const bagWeightKg = 25.0;

  const adhesiveKgNeeded = area * adhesiveRateKgPerSqm;
  const adhesiveBagsNeededFloat = adhesiveKgNeeded / bagWeightKg;
  const adhesiveWholeBags = Math.ceil(adhesiveBagsNeededFloat);

  // ===== BRACKETS CALCULATION =====
  // 6 brackets per sqm (based on area, not pieces)
  const bracketRatePerSqm = 6;
  const bracketsNeeded = Math.round(area * bracketRatePerSqm);

  // ===== UPDATE DISPLAY =====
  piecesDisplay.textContent = piecesNeeded.toLocaleString();

  if (adhesiveKgEl) {
    adhesiveKgEl.textContent = adhesiveKgNeeded.toFixed(2);
  }

  if (adhesiveBagsEl) {
    adhesiveBagsEl.textContent = adhesiveBagsNeededFloat.toFixed(2);
  }

  if (adhesiveWholeBagsEl) {
    adhesiveWholeBagsEl.textContent = adhesiveWholeBags;
  }

  if (bracketsEl) {
    bracketsEl.textContent = bracketsNeeded.toLocaleString();
  }

  if (resultsSection) {
    resultsSection.classList.remove("hidden");
  }
}

function clearCalculator() {
  const calcSection = document.getElementById("calculatorSection");
  const areaInput = document.getElementById("userArea");
  const piecesDisplay = document.getElementById("piecesFromArea");
  const resultsSection = document.getElementById("userCalculationResults");
  const adhesiveKgEl = document.getElementById("userAdhesiveNeededKg");
  const adhesiveBagsEl = document.getElementById("userAdhesiveBags");
  const adhesiveWholeBagsEl = document.getElementById("userAdhesiveWholeBags");
  const bracketsEl = document.getElementById("userBracketsNeeded");

  if (calcSection) calcSection.classList.add("hidden");
  if (areaInput) areaInput.value = "";
  if (piecesDisplay) piecesDisplay.textContent = "0";
  if (resultsSection) resultsSection.classList.add("hidden");

  if (adhesiveKgEl) adhesiveKgEl.textContent = "0";
  if (adhesiveBagsEl) adhesiveBagsEl.textContent = "0";
  if (adhesiveWholeBagsEl) adhesiveWholeBagsEl.textContent = "0";
  if (bracketsEl) bracketsEl.textContent = "0";

  window.selectedVariantDimensions = {
    width: 0,
    height: 0,
    length: 0,
    size: "",
    areaPerPiece: 0,
  };
}


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
    const variantButtons = document.querySelectorAll(
      ".variant-btn:not([disabled])"
    );
    const index = parseInt(e.key) - 1;
    if (variantButtons[index]) {
      e.preventDefault();
      e.stopPropagation();
      variantButtons[index].click();
    }
  }
});

// ✅ Ensure updateAllPriceDisplays is called on quantity change
document.addEventListener("DOMContentLoaded", function () {
  const quantityInput = document.getElementById("quantityInput");

  if (quantityInput) {
    quantityInput.addEventListener("input", function () {
      console.log("📝 Quantity changed, updating prices...");
      if (window.productSelector?.selectedVariantData) {
        updateAllPriceDisplays();
      }
    });

    quantityInput.addEventListener("change", function () {
      console.log("✅ Quantity finalized");
      if (window.productSelector?.selectedVariantData) {
        updateAllPriceDisplays();
      }
    });
  }
});

