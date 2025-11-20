// ===== FETCH STOCK FROM JUNCTION TABLE =====

/**
 * Fetch stock for a specific variant-color combination from product_variant_colors
 */
async function getVariantColorStock(variantId, colorId) {
  try {
    const response = await fetch('../index-get_stock-page-4-AA.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        variant_id: variantId,
        color_id: colorId
      })
    });

    const data = await response.json();
    
    if (data.success) {
      console.log(`Stock for variant ${variantId}, color ${colorId}: ${data.stock}`);
      return data.stock;
    } else {
      console.error('Failed to fetch stock:', data.message);
      return 0;
    }
  } catch (error) {
    console.error('Error fetching stock:', error);
    return 0;
  }
}

/**
 * Update variant buttons based on selected color's stock
 */
async function updateVariantStockByColor(colorId) {
  const variantButtons = document.querySelectorAll('.variant-btn');
  
  console.log(`Updating variant stock for color ID: ${colorId}`);
  
  for (const button of variantButtons) {
    const variantId = button.dataset.variantId;
    
    if (!variantId) continue;
    
    // Fetch stock from junction table
    const stock = await getVariantColorStock(variantId, colorId);
    
    // Update button dataset
    button.dataset.stock = stock;
    
    // Remove existing stock badges
    const existingBadge = button.querySelector('.stock-badge');
    const existingLowStock = button.querySelector('.low-stock-badge');
    if (existingBadge) existingBadge.remove();
    if (existingLowStock) existingLowStock.remove();
    
    console.log(`Variant ${variantId}: Stock = ${stock}`);
    
    if (stock <= 0) {
      // OUT OF STOCK
      button.disabled = true;
      button.classList.add('opacity-50', 'cursor-not-allowed', 'bg-red-50', 'border-red-300');
      button.classList.remove('hover:border-orange-500', 'hover:shadow-md');
      button.style.pointerEvents = 'none';
      
      const badge = document.createElement('div');
      badge.className = 'stock-badge absolute top-1 right-1 bg-red-500 text-white text-[8px] px-1.5 py-0.5 rounded-full font-bold z-10';
      badge.textContent = 'OUT';
      button.style.position = 'relative';
      button.appendChild(badge);
      
      console.log(`✗ Variant ${variantId} - OUT OF STOCK`);
      
    } else if (stock <= 5 && stock > 0) {
      // LOW STOCK
      button.disabled = false;
      button.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-red-50', 'border-red-300');
      button.classList.add('hover:border-orange-500', 'hover:shadow-md');
      button.style.pointerEvents = 'auto';
      
      const lowStockBadge = document.createElement('div');
      lowStockBadge.className = 'low-stock-badge absolute top-1 right-1 bg-yellow-500 text-white text-[8px] px-1 py-0.5 rounded-full font-bold z-10 animate-pulse';
      lowStockBadge.textContent = `${stock} left`;
      button.style.position = 'relative';
      button.appendChild(lowStockBadge);
      
      console.log(`⚠ Variant ${variantId} - LOW STOCK: ${stock}`);
      
    } else {
      // IN STOCK
      button.disabled = false;
      button.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-red-50', 'border-red-300');
      button.classList.add('hover:border-orange-500', 'hover:shadow-md');
      button.style.pointerEvents = 'auto';
      
      console.log(`✓ Variant ${variantId} - IN STOCK: ${stock}`);
    }
  }
}

/**
 * Override selectColorFromGrid to update stock based on color
 */
const originalSelectColorFromGrid = window.selectColorFromGrid || (() => {});

window.selectColorFromGrid = async function(colorId, colorName, price, image, colorCode) {
  // Call original function
  originalSelectColorFromGrid.call(this, colorId, colorName, price, image, colorCode);
  
  // Update variant stock based on this color
  console.log(`Color selected: ${colorName} (ID: ${colorId})`);
  await updateVariantStockByColor(colorId);
  
  // Check available variants
  checkAvailableVariants();
};

/**
 * Override selectVariant to validate stock before selection
 */
const originalSelectVariant = window.selectVariant || (() => {});

window.selectVariant = async function(button, size, color = null) {
  // Check if button is disabled (out of stock)
  if (button.disabled) {
    alert('This size variant is out of stock for the selected color');
    return false;
  }
  
  // Get stock info
  const variantId = button.dataset.variantId;
  const colorId = document.getElementById('selected_color_id')?.value;
  
  if (variantId && colorId) {
    const stock = await getVariantColorStock(variantId, colorId);
    button.dataset.stock = stock;
    
    if (stock <= 0) {
      alert('This combination is currently out of stock');
      button.disabled = true;
      return false;
    }
  }
  
  // Call original function
  return originalSelectVariant.call(this, button, size, color);
};

/**
 * Update checkVariantStock to work with junction table
 */
function checkVariantStockFromJunction() {
  const variantButtons = document.querySelectorAll('.variant-btn');
  const colorId = document.getElementById('selected_color_id')?.value;
  
  console.log(`Checking variant stock from junction table...`);
  console.log(`Selected color ID: ${colorId}`);
  
  if (!colorId) {
    console.log('No color selected yet');
    return;
  }
  
  // This will be called asynchronously
  updateVariantStockByColor(colorId);
}

// Export for global use
window.checkVariantStockFromJunction = checkVariantStockFromJunction;
window.getVariantColorStock = getVariantColorStock;
window.updateVariantStockByColor = updateVariantStockByColor;