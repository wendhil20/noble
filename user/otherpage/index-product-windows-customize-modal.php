<div id="customizeModal"
  class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-9999 hidden">
  <div class="bg-white rounded-2xl p-6 lg:p-8 max-w-2xl w-full mx-4 relative max-h-[90vh] overflow-y-auto">
 
    <!-- Close Button -->
    <button onclick="closeCustomizeModal()"
      class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
    </button>
 
    <!-- Modal Header -->
    <div class="mb-6">
      
      <div class="flex items-center gap-3 mb-2">
        <div>
          <h3 class="text-2xl lg:text-3xl font-bold text-gray-900">Customize Your Windows</h3>
          <p class="text-sm text-gray-600 mt-1">Get a personalized quote for your custom specifications</p>
        </div>
      </div>
    </div>
 
    <!-- Product Summary -->
    <div class="bg-linear-to-r from-blue-50 to-purple-50 p-4 rounded-lg mb-6 border border-blue-200">
      <h4 class="font-semibold text-gray-900 mb-2">Product Details</h4>
      <p id="customizeProductName" class="text-gray-700 font-medium mb-1"></p>
      <p id="customizeProductInfo" class="text-sm text-gray-600"></p>
    </div>
 
    <!-- Customize Form -->
    <form id="customizeForm" class="space-y-5" onsubmit="submitCustomizeForm(event)">
 
      <!-- Hidden product ID -->
      <input type="hidden" name="product_id" value="<?= (int) $product_id ?>">
 
      <!-- Customization Type -->
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-3">
          What would you like to customize?
        </label>
        <div class="space-y-2">
          <?php
          $customTypes = [
            'size'     => 'Custom Size',
            'color'    => 'Custom Color/Design',
            'material' => 'Different Material',
            'other'    => 'Other',
          ];
          foreach ($customTypes as $value => $label):
          ?>
            <label
              class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition">
              <input type="radio" name="customType" value="<?= $value ?>"
                <?= $value === 'size' ? 'checked' : '' ?>
                class="w-4 h-4 text-purple-600">
              <span class="ml-3 font-medium text-gray-700"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
 
      <!-- Specifications -->
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-2">Your Specifications</label>
        <textarea name="specifications"
          placeholder="Describe your custom requirements in detail (dimensions, colors, materials, quantity, etc.)"
          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 resize-none"
          rows="4" required></textarea>
      </div>
 
      <!-- Contact Information -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-2">Full Name</label>
          <input type="text" name="fullName" id="customizeFullName" placeholder="Your name"
            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 bg-gray-100 cursor-not-allowed"
            readonly required>
          <p class="text-xs text-gray-500 mt-1">From your account</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-2">Email</label>
          <input type="email" name="email" id="customizeEmail" placeholder="your@email.com"
            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 bg-gray-100 cursor-not-allowed"
            readonly required>
          <p class="text-xs text-gray-500 mt-1">From your account</p>
        </div>
      </div>
 
      <!-- Phone -->
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-2">Phone Number</label>
        <input type="tel" name="phone" placeholder="+63 9XX XXX XXXX"
          class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500"
          required>
      </div>
 
      <!-- Additional Message -->
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-2">
          Additional Message <span class="text-gray-400 font-normal">(Optional)</span>
        </label>
        <textarea name="message"
          placeholder="Any additional information you'd like to share..."
          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 resize-none"
          rows="3"></textarea>
      </div>
 
      <!-- Terms Checkbox -->
      <label
        class="flex items-start p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
        <input type="checkbox" name="agreeTerms" class="w-4 h-4 mt-1 text-purple-600" required>
        <span class="ml-3 text-sm text-gray-700">
          I agree to receive updates and quote details from Noble Home Construction
        </span>
      </label>
 
      <!-- Submit Buttons -->
      <div class="grid grid-cols-2 gap-3 pt-4">
        <button type="button" onclick="closeCustomizeModal()"
          class="py-3 bg-gray-200 hover:bg-gray-300 text-gray-900 font-semibold rounded-lg transition-colors">
          Cancel
        </button>
        <button type="submit"
          class="py-3 bg-black hover:bg-orange-500 text-white font-semibold rounded-lg transition-colors">
          <i class="fas fa-paper-plane mr-2"></i>Send Request
        </button>
      </div>
    </form>
 
    <!-- Direct Contact Options -->
    <div class="mt-6 pt-6 border-t border-gray-200">
      <p class="text-xs text-gray-600 text-center mb-3">Or contact us directly:</p>
      <div class="flex gap-2 justify-center">
        <a href="tel:+639922394563"
          class="flex items-center gap-2 px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-sm font-medium transition">
          <i class="fas fa-phone"></i> Call
        </a>
        <a href="https://wa.me/639922394563" target="_blank"
          class="flex items-center gap-2 px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm font-medium transition">
          <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="mailto:noblehomeconst.ph@gmail.com"
          class="flex items-center gap-2 px-4 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm font-medium transition">
          <i class="fas fa-envelope"></i> Email
        </a>
      </div>
    </div>
 
  </div>
</div>
 
<!-- ============================================================ -->
<!-- STYLES                                                       -->
<!-- ============================================================ -->
<style>
  #customizeModal {
    animation: customizeModalFadeIn 0.3s ease-out;
  }
 
  @keyframes customizeModalFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to   { opacity: 1; transform: scale(1); }
  }
 
  #customizeModal > div {
    animation: customizeSlideUp 0.3s ease-out;
  }
 
  @keyframes customizeSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
  }
 
  #customizeForm input:focus,
  #customizeForm textarea:focus {
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
  }
 
  #customizeForm textarea {
    font-family: inherit;
  }
</style>
 
<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->

<script>
  // Open customize modal
  function openCustomizeModal() {
    const modal        = document.getElementById('customizeModal');
    const productName  = document.querySelector('h1.text-xl')?.textContent || 'Product';
    const selectedColor = document.getElementById('selected_color')?.value || 'Not selected';
    const selectedSize  = document.querySelector('.variant-btn.selected')?.textContent?.trim() || 'Not selected';
 
    // Populate product summary
    document.getElementById('customizeProductName').textContent = productName;
    document.getElementById('customizeProductInfo').textContent =
      `Color: ${selectedColor} | Size: ${selectedSize}`;
 
    // Pre-fill user info from session meta tags
    const userEmail = document.querySelector('meta[name="user-email"]')?.getAttribute('content') || '';
    const userName  = document.querySelector('meta[name="user-name"]')?.getAttribute('content') || '';
 
    if (userName)  document.getElementById('customizeFullName').value = userName;
    if (userEmail) document.getElementById('customizeEmail').value    = userEmail;
 
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
 
  // Close customize modal
  function closeCustomizeModal() {
    document.getElementById('customizeModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
  }
 
  // Submit customize form via fetch
  function submitCustomizeForm(event) {
    event.preventDefault();
 
    const form        = document.getElementById('customizeForm');
    const formData    = new FormData(form);
 
    // Append current selections from main page
    formData.append('selected_color',   document.getElementById('selected_color')?.value   || '');
    formData.append('selected_variant', document.getElementById('selected_variant')?.value || '');
 
    fetch('index-customize_quote_handler-page-4-AA.php', {
      method : 'POST',
      body   : formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('✅ Your customization request has been sent! We will contact you shortly.');
          closeCustomizeModal();
          form.reset();
        } else {
          alert('❌ Error: ' + (data.message || 'Something went wrong'));
        }
      })
      .catch(err => {
        console.error('Customize form error:', err);
        alert('❌ Error sending request. Please try again or contact us directly.');
      });
  }
 
  // Close modal on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCustomizeModal();
  });
</script>  