// addressZone.js - Address selection (zone logic removed)

function initializeAddressSelection() {
  console.log("Checking for address selection elements...");

  const billingRadios = document.querySelectorAll(
    'input[name="billing_address_id"]'
  );
  
  // Skip silently if not on address selection page (Step 2)
  if (billingRadios.length === 0) {
    console.log("⏭️ Not on address selection page - skipping initialization");
    return;
  }

  console.log("✅ Initializing address selection with", billingRadios.length, "addresses");

  const continueToDeliveryBtn = document.getElementById("continueBtn");
  const calculateDistanceBtn = document.getElementById("calculateDistance");
  const showMapBtn = document.getElementById("showMapModal");

  const mobileInput = document.getElementById("mobileInput");
  const addressInput = document.getElementById("addressInput");
  const zipcodeInput = document.getElementById("zipcodeInput");

  // Handle billing address selection
  billingRadios.forEach((radio) => {
    radio.addEventListener("change", function () {
      if (this.checked) {
        console.log("📍 Address selected:", this.dataset.address);

        // Store selected address globally
        window.selectedAddress = {
          id: this.value,
          latitude: parseFloat(this.dataset.latitude),
          longitude: parseFloat(this.dataset.longitude),
          address: this.dataset.address,
          postalCode: this.dataset.postalCode,
          fullName: this.dataset.fullName,
          phone: this.dataset.phone,
        };

        // Validate coordinates
        if (
          isNaN(window.selectedAddress.latitude) ||
          isNaN(window.selectedAddress.longitude)
        ) {
          alert("Invalid address coordinates. Please select a different address or update this one.");
          console.error('❌ Invalid coordinates:', window.selectedAddress);
          return;
        }

        // Clean and format mobile number
        let phone = this.dataset.phone || "";
        phone = phone.replace(/[\s\-\(\)\+]/g, "");

        // Convert +63 format to 09 format
        if (phone.match(/^63([0-9]{10})$/)) {
          phone = "0" + phone.substring(2);
        }

        // Populate the fields
        if (mobileInput) {
          mobileInput.value = phone;
          mobileInput.disabled = false;
          mobileInput.readOnly = true;
        }

        if (addressInput) {
          addressInput.value = this.dataset.address || "";
          addressInput.disabled = false;
          addressInput.readOnly = true;
        }

        if (zipcodeInput) {
          zipcodeInput.value = this.dataset.postalCode || "";
          zipcodeInput.disabled = false;
          zipcodeInput.readOnly = true;
        }

        // Enable continue button for Step 2
        if (continueToDeliveryBtn) {
          continueToDeliveryBtn.disabled = false;
          continueToDeliveryBtn.classList.remove(
            "bg-gray-400",
            "cursor-not-allowed",
            "opacity-50"
          );
          continueToDeliveryBtn.classList.add(
            "bg-orange-600",
            "hover:bg-orange-700"
          );
          console.log('✓ Continue button enabled');
        }

        // Enable Step 3 buttons (if they exist - for future use)
        if (calculateDistanceBtn) {
          calculateDistanceBtn.disabled = false;
          calculateDistanceBtn.classList.remove("bg-gray-400");
          calculateDistanceBtn.classList.add(
            "bg-orange-600",
            "hover:bg-orange-700"
          );
        }

        if (showMapBtn) {
          showMapBtn.disabled = false;
          showMapBtn.classList.remove("bg-gray-400");
          showMapBtn.classList.add("bg-green-600", "hover:bg-green-700");
        }

        console.log("✅ Address selection complete:", {
          id: window.selectedAddress.id,
          lat: window.selectedAddress.latitude,
          lng: window.selectedAddress.longitude,
          address: window.selectedAddress.address.substring(0, 50) + '...'
        });
      }
    });
  });

  console.log("✅ Address selection event listeners attached");
}

// Re-trigger calculation when returning to address selection
function recheckSelectedAddress() {
  const selectedRadio = document.querySelector(
    'input[name="billing_address_id"]:checked'
  );
  
  if (!selectedRadio) {
    console.log("⏭️ No address selected to recheck");
    return;
  }
  
  console.log("🔄 Re-checking previously selected address...");

  // Store the address data again
  window.selectedAddress = {
    id: selectedRadio.value,
    latitude: parseFloat(selectedRadio.dataset.latitude),
    longitude: parseFloat(selectedRadio.dataset.longitude),
    address: selectedRadio.dataset.address,
    postalCode: selectedRadio.dataset.postalCode,
    fullName: selectedRadio.dataset.fullName,
    phone: selectedRadio.dataset.phone,
  };

  console.log("✅ Address data restored:", window.selectedAddress);
}

// Export to global scope
window.initializeAddressSelection = initializeAddressSelection;
window.recheckSelectedAddress = recheckSelectedAddress;

console.log("✅ addressZone.js loaded successfully");