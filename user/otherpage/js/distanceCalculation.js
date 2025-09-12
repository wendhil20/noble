// distanceCalculation.js - Distance and delivery calculations

function initializeDistanceCalculation() {
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const continueToPaymentBtn = document.getElementById('continueToPayment');

    if (calculateDistanceBtn) {
        calculateDistanceBtn.addEventListener('click', async function() {
            if (!selectedAddress || !selectedZone) {
                showNotification('Please select an address and delivery zone.', 'error');
                return;
            }

            // Handle free delivery zones without distance calculation
            if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                setupFreeDelivery();
                showNotification('Free delivery confirmed!', 'success');
                return;
            }

            // Show loading
            const originalText = calculateDistanceBtn.textContent;
            calculateDistanceBtn.textContent = 'Calculating...';
            calculateDistanceBtn.disabled = true;

            try {
                // Validate required data
                if (!selectedAddress) {
                    throw new Error('No delivery address selected');
                }
                if (!selectedZone) {
                    throw new Error('No delivery zone selected');
                }
                if (!deliverySettings) {
                    throw new Error('Delivery settings not loaded');
                }

                let distance = 0;
                let routeData = {
                    distance: 0,
                    time: 0,
                    fallback: false
                };

                // Only calculate distance for paid delivery zones
                const storeLatLng = {
                    lat: parseFloat(deliverySettings.latitude),
                    lng: parseFloat(deliverySettings.longitude)
                };
                const customerLatLng = {
                    lat: selectedAddress.latitude,
                    lng: selectedAddress.longitude
                };

                if (isNaN(storeLatLng.lat) || isNaN(storeLatLng.lng)) {
                    throw new Error('Invalid store coordinates');
                }
                if (isNaN(customerLatLng.lat) || isNaN(customerLatLng.lng)) {
                    throw new Error('Invalid customer address coordinates');
                }

                routeData = await calculateRoutingDistance(storeLatLng, customerLatLng);
                distance = routeData.distance || 0;

                // Calculate zone-based delivery
                const deliveryResult = calculateZoneBasedDeliveryJS(distance, selectedZone);

                if (!deliveryResult) {
                    throw new Error('Failed to calculate delivery costs');
                }

                // Update UI
                updateDeliveryDisplay(deliveryResult, routeData, distance);

                // Update hidden fields
                const deliveryDistanceInput = document.getElementById('deliveryDistance');
                const deliveryFeeInput = document.getElementById('deliveryFee');
                
                if (deliveryDistanceInput) {
                    deliveryDistanceInput.value = distance.toFixed(2);
                }
                if (deliveryFeeInput) {
                    deliveryFeeInput.value = deliveryResult.totalDeliveryCost.toFixed(2);
                }

                // Update totals
                updateTotalsDisplay(deliveryResult.totalDeliveryCost);

                // Enable continue button
                if (continueToPaymentBtn) {
                    continueToPaymentBtn.disabled = false;
                    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                const successMessage = `Delivery fee calculated: ₱${deliveryResult.totalDeliveryCost.toFixed(2)}`;
                showNotification(successMessage, 'success');

            } catch (error) {
                console.error('Delivery calculation error:', error);

                let errorMessage = 'Error calculating delivery fee. ';
                if (error.message.includes('coordinates')) {
                    errorMessage += 'Invalid location data.';
                } else if (error.message.includes('zone')) {
                    errorMessage += 'Please select a delivery zone.';
                } else if (error.message.includes('address')) {
                    errorMessage += 'Please select a delivery address.';
                } else {
                    errorMessage += 'Please try again or contact support.';
                }

                showNotification(errorMessage, 'error');
            } finally {
                calculateDistanceBtn.textContent = 'Recalculate Distance';
                calculateDistanceBtn.disabled = false;
            }
        });
    }
}

// Function to calculate distance using OSRM (same as map routing)
async function calculateRoutingDistance(storeLatLng, customerLatLng) {
    try {
        // First try OSRM routing
        const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false&geometries=geojson`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        const response = await fetch(url, {
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
            }
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`OSRM API error: ${response.status}`);
        }

        const data = await response.json();

        if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            const distanceKm = route.distance / 1000;
            const timeMinutes = Math.round(route.duration / 60);

            return {
                distance: distanceKm,
                time: timeMinutes,
                success: true,
                fallback: false
            };
        } else {
            throw new Error('No routes found in response');
        }
    } catch (error) {
        console.warn('OSRM routing failed, using fallback calculation:', error.message);

        // Validate coordinates before fallback calculation
        if (isNaN(storeLatLng.lat) || isNaN(storeLatLng.lng) || isNaN(customerLatLng.lat) || isNaN(customerLatLng.lng)) {
            throw new Error('Invalid coordinates provided for distance calculation');
        }

        // Fallback to Haversine distance
        const distance = calculateHaversineDistance(
            storeLatLng.lat, storeLatLng.lng,
            customerLatLng.lat, customerLatLng.lng
        );

        return {
            distance: distance,
            time: Math.round(distance * 3), // More realistic time estimate
            success: true,
            fallback: true
        };
    }
}

// JavaScript zone-based calculation
function calculateZoneBasedDeliveryJS(distance, zone) {
    if (zone.zone_code === 'NCR' || zone.is_free_delivery == 1 || zone.is_free_delivery === true) {
        return {
            totalDeliveryCost: 0,
            deliveryFeePerItem: 0,
            isFree: true
        };
    }

    // Calculate total items quantity
    let totalQuantity = 0;
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const quantityElement = cartItem.querySelector('.itemQuantity');
        if (quantityElement) {
            totalQuantity += parseInt(quantityElement.textContent) || 0;
        }
    });

    // Calculate zone fee
    const baseFee = parseFloat(zone.base_fee) || 0;
    const includedKm = parseFloat(zone.included_km) || 0;
    const perKmRate = parseFloat(zone.per_km_rate) || 0;

    let totalDeliveryFee = baseFee;
    if (distance > includedKm) {
        const extraKm = distance - includedKm;
        totalDeliveryFee += (extraKm * perKmRate);
    }

    const deliveryFeePerItem = totalQuantity > 0 ? totalDeliveryFee / totalQuantity : 0;

    return {
        totalDeliveryCost: totalDeliveryFee,
        deliveryFeePerItem: deliveryFeePerItem,
        isFree: false,
        totalQuantity: totalQuantity
    };
}

function updateDeliveryDisplay(deliveryResult, routeData, distance) {
    // Update distance result
    const distanceResultElement = document.getElementById('distanceResult');
    if (distanceResultElement) {
        if (deliveryResult.isFree) {
            distanceResultElement.innerHTML = `
                <div class="bg-green-100 border border-green-300 rounded p-3">
                    <div class="font-medium text-green-800">FREE DELIVERY!</div>
                    <div class="font-medium text-green-800">Zone: ${selectedZone.zone_name}</div>
                    <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
                </div>
            `;
        } else {
            distanceResultElement.innerHTML = `
                <div class="bg-blue-100 border border-blue-300 rounded p-3">
                    <div class="font-medium text-blue-800">Zone: ${selectedZone.zone_name}</div>
                    <div class="font-medium text-blue-800">Distance: ${distance.toFixed(2)} km</div>
                    <div class="font-medium text-blue-800">Est. Time: ${routeData.time} minutes</div>
                    <div class="font-medium text-blue-800">Total Delivery: ₱${deliveryResult.totalDeliveryCost.toFixed(2)}</div>
                    <div class="font-medium text-blue-800">Per Item: ₱${deliveryResult.deliveryFeePerItem.toFixed(2)}</div>
                </div>
            `;
        }
    }

    // Update individual item delivery displays
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    cartItems.forEach(cartItem => {
        const quantityElement = cartItem.querySelector('.itemQuantity');
        const quantity = quantityElement ? parseInt(quantityElement.textContent) || 0 : 0;

        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');

        const itemTotalDelivery = deliveryResult.deliveryFeePerItem * quantity;

        if (deliveryPerItemElement) {
            deliveryPerItemElement.textContent = `₱${deliveryResult.deliveryFeePerItem.toFixed(2)}`;
        }
        if (totalDeliveryForItemElement) {
            totalDeliveryForItemElement.textContent = `₱${itemTotalDelivery.toFixed(2)}`;
        }
    });
}

// Update your existing updateTotalsDisplay function to also update PayPal amount
function updateTotalsDisplay(deliveryCost) {
    const totals = calculateTotalsWithVAT(subtotal, deliveryCost);

    // Update displays (your existing code)
    const subtotalBeforeVATElement = document.getElementById('subtotalBeforeVAT');
    const totalDeliveryCostDisplayElement = document.getElementById('totalDeliveryCostDisplay');
    const vatAmountElement = document.getElementById('vatAmount');
    const grandTotalDisplayElement = document.getElementById('grandTotalDisplay');

    if (subtotalBeforeVATElement) {
        subtotalBeforeVATElement.textContent = `₱${totals.subtotalWithDelivery.toFixed(2)}`;
    }
    if (totalDeliveryCostDisplayElement) {
        totalDeliveryCostDisplayElement.textContent = `₱${deliveryCost.toFixed(2)}`;
    }
    if (vatAmountElement) {
        vatAmountElement.textContent = `₱${totals.vatAmount.toFixed(2)}`;
    }
    if (grandTotalDisplayElement) {
        grandTotalDisplayElement.textContent = `₱${totals.grandTotal.toFixed(2)}`;
    }

    // Update PayPal amount if PayPal is selected
    if (typeof updatePayPalAmount === 'function') {
        updatePayPalAmount();
    }

    // Update bank transfer amount if visible
    if (typeof updateBankPaymentAmount === 'function') {
        updateBankPaymentAmount();
    }
}

// Haversine formula to calculate distance between two coordinates
function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers

    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    const distance = R * c; // Distance in kilometers
    return distance;
}