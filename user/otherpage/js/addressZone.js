// addressZone.js - Address selection and zone detection

function initializeAddressSelection() {
    const billingRadios = document.querySelectorAll('input[name="billing_address_id"]');
    const continueToDeliveryBtn = document.getElementById('continueToDelivery');
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const showMapBtn = document.getElementById('showMapModal');

    const mobileInput = document.getElementById('mobileInput');
    const addressInput = document.getElementById('addressInput');
    const zipcodeInput = document.getElementById('zipcodeInput');

    // Check if user has addresses from global config
    const hasAddresses = window.checkoutConfig?.hasAddresses || false;

    // Handle billing address selection
    billingRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                selectedAddress = {
                    latitude: parseFloat(this.dataset.latitude),
                    longitude: parseFloat(this.dataset.longitude),
                    address: this.dataset.address
                };

                // Clean and format mobile number
                let phone = this.dataset.phone;
                // Remove spaces, dashes, parentheses, plus signs
                phone = phone.replace(/[\s\-\(\)\+]/g, '');
                // Convert +63 format to 09 format
                if (phone.match(/^63([0-9]{10})$/)) {
                    phone = '0' + phone.substring(2);
                }

                // Populate the fields and enable them
                if (mobileInput) mobileInput.value = phone;
                if (addressInput) addressInput.value = this.dataset.address;
                if (zipcodeInput) zipcodeInput.value = this.dataset.postalCode;

                // Enable fields for form submission but keep them visually disabled
                if (mobileInput) {
                    mobileInput.disabled = false;
                    mobileInput.readOnly = true;
                }
                if (addressInput) {
                    addressInput.disabled = false;
                    addressInput.readOnly = true;
                }
                if (zipcodeInput) {
                    zipcodeInput.disabled = false;
                    zipcodeInput.readOnly = true;
                }

                // AUTO-DETECT AND SELECT DELIVERY ZONE BASED ON POSTAL CODE
                if (this.dataset.postalCode) {
                    autoDetectAndSelectZone(this.dataset.postalCode);
                }

                // Enable continue button and other controls
                if (continueToDeliveryBtn) {
                    continueToDeliveryBtn.disabled = false;
                    continueToDeliveryBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToDeliveryBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

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

                // Show success notification
                showNotification('Address selected successfully! Delivery zone auto-detected.', 'success');
            }
        });
    });
}

// Modified auto-detect function for display-only output
function autoDetectAndSelectZone(postalCode) {
    if (!postalCode) return;

    const postal_num = parseInt(postalCode);
    let targetZoneCode = 'LUZON'; // default fallback
    let detectedRegion = 'LUZON';

    // Enhanced postal code zone detection
    if (postal_num >= 1000 && postal_num <= 1800) {
        targetZoneCode = 'NCR';
        detectedRegion = 'NCR (Metro Manila)';
    } else if (postal_num >= 2000 && postal_num <= 3999) {
        targetZoneCode = 'LUZON';
        detectedRegion = 'LUZON';
    } else if (postal_num >= 4000 && postal_num <= 6999) {
        targetZoneCode = 'VISAYAS';
        detectedRegion = 'VISAYAS';
    } else if (postal_num >= 7000 && postal_num <= 9999) {
        targetZoneCode = 'MINDANAO';
        detectedRegion = 'MINDANAO';
    }

    // Get delivery zones from global config
    const availableZones = deliveryZones || [];

    // Find matching zone
    const matchedZone = availableZones.find(zone => zone.zone_code === targetZoneCode);

    if (matchedZone) {
        // Display the detected zone
        displayDetectedZone(matchedZone, detectedRegion, postalCode);

        // Set global selectedZone variable - VERY IMPORTANT!
        window.selectedZone = matchedZone;
        selectedZone = matchedZone; // Also set global without window prefix

        // Debug log to verify zone is set
        console.log('Zone detected and set:', matchedZone);

        // Auto-setup free delivery if NCR
        if (targetZoneCode === 'NCR') {
            setupFreeDelivery();
        }

        // Show notification
        let message = `${detectedRegion} zone auto-selected based on postal code ${postalCode}`;
        let messageType = 'info';

        if (targetZoneCode === 'NCR') {
            message += ' - FREE DELIVERY!';
            messageType = 'success';
        } else {
            message += ` - Base delivery: ₱${parseFloat(matchedZone.base_fee).toFixed(2)}`;
            messageType = 'info';
        }

        if (typeof showNotification === 'function') {
            showNotification(message, messageType);
        }

    } else {
        console.warn('No matching zone found for postal code:', postalCode);
        if (typeof showNotification === 'function') {
            showNotification(`Postal code ${postalCode} detected as ${detectedRegion}, but no matching delivery zone found.`, 'warning');
        }
    }
}

// Function to display the detected zone
function displayDetectedZone(zone, detectedRegion, postalCode) {
    const zoneInfo = document.getElementById('zoneInfo');
    const zoneDescription = document.getElementById('zoneDescription');
    const selectedZoneId = document.getElementById('selectedZoneId');

    // Set hidden input value
    if (selectedZoneId) {
        selectedZoneId.value = zone.id;
    }

    // Create display content
    let displayContent = `
        <div class="font-medium text-gray-800">${zone.zone_name}</div>
        <div class="text-sm text-gray-600">Region: ${detectedRegion}</div>
    `;

    if (zone.is_free_delivery === true || zone.is_free_delivery === 'true' || zone.zone_code === 'NCR') {
        displayContent += `<div class="text-green-600 text-sm font-medium mt-1">✓ FREE DELIVERY</div>`;
        if (zoneInfo) {
            zoneInfo.className = "text-gray-800 bg-green-50 p-2 rounded";
        }
    } else {
        displayContent += `
            <div class="text-gray-600 text-sm mt-1">
                Base Fee: ₱${parseFloat(zone.base_fee).toFixed(2)}
                ${zone.included_km ? ` (${zone.included_km}km included)` : ''}
            </div>
        `;
        if (zoneInfo) {
            zoneInfo.className = "text-gray-800 bg-blue-50 p-2 rounded";
        }
    }

    if (zoneInfo) {
        zoneInfo.innerHTML = displayContent;
    }

    // Update description
    if (zoneDescription) {
        zoneDescription.textContent = `Delivery zone automatically detected from postal code ${postalCode}`;
        zoneDescription.className = "text-xs text-green-600 mt-1";
    }
}

// Keep your existing setupFreeDelivery function (same functionality)
function setupFreeDelivery() {
    if (!window.selectedZone || (!window.selectedZone.is_free_delivery && window.selectedZone.zone_code !== 'NCR')) {
        return;
    }

    // Set delivery values to zero
    const deliveryFeeInput = document.getElementById('deliveryFee');
    const deliveryDistanceInput = document.getElementById('deliveryDistance');

    if (deliveryFeeInput) deliveryFeeInput.value = '0.00';
    if (deliveryDistanceInput) deliveryDistanceInput.value = '0.00';

    // Update totals display
    if (typeof updateTotalsDisplay === 'function') {
        updateTotalsDisplay(0);
    }

    // Update delivery display
    const distanceResultElement = document.getElementById('distanceResult');
    if (distanceResultElement) {
        distanceResultElement.innerHTML = `
            <div class="bg-green-100 border border-green-300 rounded p-3">
                <div class="font-medium text-green-800">FREE DELIVERY!</div>
                <div class="font-medium text-green-800">Zone: ${window.selectedZone.zone_name}</div>
                <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
            </div>
        `;
    }

    // Enable continue to payment button
    const continueToPaymentBtn = document.getElementById('continueToPayment');
    if (continueToPaymentBtn) {
        continueToPaymentBtn.disabled = false;
        continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
    }

    // Update individual item delivery displays to show free
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');

        if (deliveryPerItemElement) {
            deliveryPerItemElement.textContent = '₱0.00';
        }
        if (totalDeliveryForItemElement) {
            totalDeliveryForItemElement.textContent = '₱0.00';
        }
    });
}

// Function for manual override if needed (optional)
function setDeliveryZoneManual(zoneId) {
    const availableZones = deliveryZones || [];
    const selectedZone = availableZones.find(zone => zone.id === zoneId);
    
    if (selectedZone) {
        displayDetectedZone(selectedZone, selectedZone.zone_name, 'Manual Selection');
        window.selectedZone = selectedZone;

        if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
            setupFreeDelivery();
        }
    }
}

// Zone selection functions
function selectDeliveryZone(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        selectedZone = {
            id: option.value,
            zone_name: option.dataset.zoneName,
            zone_code: option.dataset.zoneCode,
            base_fee: option.dataset.baseFee,
            included_km: option.dataset.includedKm,
            per_km_rate: option.dataset.perKmRate,
            is_free_delivery: option.dataset.isFree === '1'
        };

        const description = document.getElementById('zoneDescription');
        if (description) {
            if (selectedZone.is_free_delivery) {
                description.innerHTML = `<span class="text-green-600 font-medium">🎉 Free delivery for ${selectedZone.zone_name}!</span>`;

                // Auto-setup free delivery when zone is selected
                setupFreeDelivery();

            } else {
                description.innerHTML = `<span class="text-blue-600">Base fee: ₱${parseFloat(selectedZone.base_fee).toFixed(2)} (includes ${selectedZone.included_km} km), ₱${parseFloat(selectedZone.per_km_rate).toFixed(2)} per additional km</span>`;
            }
        }
    } else {
        selectedZone = null;
        const description = document.getElementById('zoneDescription');
        if (description) {
            description.textContent = 'Select your delivery zone to calculate shipping costs';
        }
    }
}