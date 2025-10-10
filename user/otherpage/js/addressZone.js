// addressZone.js - Address selection (zone logic removed)

function initializeAddressSelection() {
    console.log('Initializing address selection...');
    
    const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
    const continueToDeliveryBtn = document.getElementById('continueToDelivery');
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const showMapBtn = document.getElementById('showMapModal');

    const mobileInput = document.getElementById('mobileInput');
    const addressInput = document.getElementById('addressInput');
    const zipcodeInput = document.getElementById('zipcodeInput');

    if (billingRadios.length === 0) {
        console.warn('No billing address radios found');
        return;
    }

    // Handle billing address selection
    billingRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                console.log('Address selected:', this.dataset.address);
                
                // Store selected address globally
                selectedAddress = {
                    id: this.value,
                    latitude: parseFloat(this.dataset.latitude),
                    longitude: parseFloat(this.dataset.longitude),
                    address: this.dataset.address,
                    postalCode: this.dataset.postalCode,
                    fullName: this.dataset.fullName,
                    phone: this.dataset.phone
                };

                // Validate coordinates
                if (isNaN(selectedAddress.latitude) || isNaN(selectedAddress.longitude)) {
                    showNotification('Invalid address coordinates. Please select a different address.', 'error');
                    return;
                }

                // Clean and format mobile number
                let phone = this.dataset.phone || '';
                phone = phone.replace(/[\s\-\(\)\+]/g, '');
                
                // Convert +63 format to 09 format
                if (phone.match(/^63([0-9]{10})$/)) {
                    phone = '0' + phone.substring(2);
                }

                // Populate the fields
                if (mobileInput) {
                    mobileInput.value = phone;
                    mobileInput.disabled = false;
                    mobileInput.readOnly = true;
                }
                
                if (addressInput) {
                    addressInput.value = this.dataset.address || '';
                    addressInput.disabled = false;
                    addressInput.readOnly = true;
                }
                
                if (zipcodeInput) {
                    zipcodeInput.value = this.dataset.postalCode || '';
                    zipcodeInput.disabled = false;
                    zipcodeInput.readOnly = true;
                }

                // Enable Step 2 continue button
                if (continueToDeliveryBtn) {
                    continueToDeliveryBtn.disabled = false;
                    continueToDeliveryBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToDeliveryBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                // Enable Step 3 buttons
                if (calculateDistanceBtn) {
                    calculateDistanceBtn.disabled = false;
                    calculateDistanceBtn.classList.remove('bg-gray-400');
                    calculateDistanceBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }

                if (showMapBtn) {
                    showMapBtn.disabled = false;
                    showMapBtn.classList.remove('bg-gray-400');
                    showMapBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                }

                // Success notification
showNotification('Address selected successfully! You can proceed to delivery calculation.', 'success');
                
                console.log('✓ Address selection complete:', selectedAddress);
            }
        });
    });

    console.log('Address selection initialized with', billingRadios.length, 'addresses');
}

console.log('addressZone.js loaded (zone logic removed)');