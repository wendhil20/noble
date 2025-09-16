// distanceCalculation.js - Distance and delivery calculations with percentage-based additional fees

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

                // Calculate distance for paid delivery zones
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

                // NEW: Calculate percentage-based delivery using cart item data
                const deliveryResult = calculatePercentageBasedDeliveryJS(distance, selectedZone);

                console.log('Delivery calculation result:', deliveryResult); // Debug log

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

                // Update individual item displays with percentage-based additional fees
                updateIndividualItemDeliveryDisplay(deliveryResult.itemDeliveryDetails || []);

                // Enable continue button
                if (continueToPaymentBtn) {
                    continueToPaymentBtn.disabled = false;
                    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                const successMessage = `Delivery fee calculated: ₱${deliveryResult.baseDeliveryFee.toFixed(2)} (base) + ₱${deliveryResult.additionalFees.toFixed(2)} (item fees) = ₱${deliveryResult.totalDeliveryCost.toFixed(2)} total`;
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

// NEW: Percentage-based delivery calculation with additional fees per item
function calculatePercentageBasedDeliveryJS(distance, zone) {
    console.log('=== Starting Percentage-Based Delivery Calculation ===');
    console.log('Distance:', distance, 'Zone:', zone);
    
    if (zone.zone_code === 'NCR' || zone.is_free_delivery == 1 || zone.is_free_delivery === true) {
        console.log('Free delivery zone detected');
        // Free delivery - create zero-cost details for each item
        const itemDeliveryDetails = [];
        const cartItems = document.querySelectorAll('[id^="cartItem"]');
        
        cartItems.forEach((cartItem, index) => {
            const quantityElement = cartItem.querySelector('.itemQuantity');
            const quantity = quantityElement ? parseInt(quantityElement.textContent) || 0 : 0;
            
            itemDeliveryDetails.push({
                itemIndex: index,
                quantity: quantity,
                deliveryFeePerItem: 0,
                itemTotalDelivery: 0,
                deliverySizePercentage: 0
            });
        });

        return {
            totalDeliveryCost: 0,
            baseDeliveryFee: 0,
            additionalFees: 0,
            isFree: true,
            itemDeliveryDetails: itemDeliveryDetails
        };
    }

    // Calculate base delivery fee (distance-based)
    const baseFee = parseFloat(zone.base_fee) || 0;
    const includedKm = parseFloat(zone.included_km) || 0;
    const perKmRate = parseFloat(zone.per_km_rate) || 0;

    let baseDeliveryFee = baseFee;
    if (distance > includedKm) {
        const extraKm = distance - includedKm;
        baseDeliveryFee += (extraKm * perKmRate);
    }

    console.log('Base delivery fee (distance-based):', baseDeliveryFee);

    // Extract delivery size data from cart items and calculate additional percentage fees
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    const itemData = [];
    let totalAdditionalFees = 0;

    cartItems.forEach((cartItem, index) => {
        const quantityElement = cartItem.querySelector('.itemQuantity');
        const quantity = quantityElement ? parseInt(quantityElement.textContent) || 0 : 0;
        
        // Extract delivery size percentage
        const deliverySizePercentage = getItemDeliverySizePercentage(cartItem, index);
        
        // Calculate additional fee per item based on percentage of base delivery cost
        const additionalFeePerItem = (baseDeliveryFee * deliverySizePercentage) / 100;
        const itemTotalAdditional = additionalFeePerItem * quantity;
        totalAdditionalFees += itemTotalAdditional;
        
        itemData.push({
            itemIndex: index,
            quantity: quantity,
            deliverySizePercentage: deliverySizePercentage,
            additionalFeePerItem: additionalFeePerItem,
            itemTotalAdditional: itemTotalAdditional
        });
        
        console.log(`Item ${index}: Qty=${quantity}, Percentage=${deliverySizePercentage}%, Additional per item=₱${additionalFeePerItem.toFixed(2)}, Total additional=₱${itemTotalAdditional.toFixed(2)}`);
    });

    console.log('Total additional fees:', totalAdditionalFees);

    // Calculate final total delivery cost
    const totalDeliveryCost = baseDeliveryFee + totalAdditionalFees;

    // Create item delivery details
    const itemDeliveryDetails = itemData.map(item => ({
        itemIndex: item.itemIndex,
        quantity: item.quantity,
        deliveryFeePerItem: item.additionalFeePerItem,
        itemTotalDelivery: item.itemTotalAdditional,
        deliverySizePercentage: item.deliverySizePercentage
    }));

    console.log('=== Percentage-Based Calculation Complete ===');
    console.log('Base delivery fee:', baseDeliveryFee);
    console.log('Total additional fees:', totalAdditionalFees);
    console.log('Final delivery cost:', totalDeliveryCost);
    
    return {
        totalDeliveryCost: totalDeliveryCost,
        baseDeliveryFee: baseDeliveryFee,
        additionalFees: totalAdditionalFees,
        isFree: false,
        itemDeliveryDetails: itemDeliveryDetails
    };
}

// Extract delivery size percentage from cart item (same as before)
function getItemDeliverySizePercentage(cartItem, index) {
    // Method 1: Check for data attributes (most reliable)
    if (cartItem.dataset && cartItem.dataset.deliverySizePercentage) {
        const percentage = parseFloat(cartItem.dataset.deliverySizePercentage);
        console.log(`Item ${index}: Found data attribute percentage: ${percentage}%`);
        return percentage || 5.0;
    }
    
    // Method 2: Check for hidden input with percentage
    const percentageInput = cartItem.querySelector('input[name*="delivery_size_percentage"], input[data-delivery-percentage]');
    if (percentageInput && percentageInput.value) {
        const percentage = parseFloat(percentageInput.value);
        console.log(`Item ${index}: Found hidden input percentage: ${percentage}%`);
        return percentage || 5.0;
    }
    
    // Method 3: Check for percentage in text content (from the display)
    const percentageText = cartItem.querySelector('.delivery-size-percentage');
    if (percentageText) {
        const textContent = percentageText.textContent || '';
        const percentageMatch = textContent.match(/\((\d+(?:\.\d+)?)%\)/);
        if (percentageMatch) {
            const percentage = parseFloat(percentageMatch[1]);
            console.log(`Item ${index}: Found text percentage: ${percentage}%`);
            return percentage || 5.0;
        }
    }
    
    // Method 4: Use global cart data if available
    if (window.cartItemsData && window.cartItemsData[index]) {
        const percentage = parseFloat(window.cartItemsData[index].delivery_size_percentage);
        console.log(`Item ${index}: Found global data percentage: ${percentage}%`);
        return percentage || 5.0;
    }
    
    // Default fallback
    console.warn(`Item ${index}: No percentage found, using default 5.0%`);
    return 5.0;
}

// UPDATED: Enhanced individual item delivery display with percentage-based additional fees
function updateIndividualItemDeliveryDisplay(itemDeliveryDetails) {
    console.log('Updating individual item displays:', itemDeliveryDetails);
    
    const cartItems = document.querySelectorAll('[id^="cartItem"]');
    
    cartItems.forEach((cartItem, index) => {
        const deliveryPerItemElement = cartItem.querySelector('.deliveryPerItem');
        const totalDeliveryForItemElement = cartItem.querySelector('.totalDeliveryForItem');
        const sizeAllocationElement = cartItem.querySelector('.sizeAllocationPercentage');
        const sizeAllocationInfo = cartItem.querySelector('.size-allocation-info');

        let deliveryPerItem = 0;
        let totalDeliveryForItem = 0;
        let allocationPercentage = 0;

        // If we have delivery details, use them
        if (itemDeliveryDetails && itemDeliveryDetails[index]) {
            const detail = itemDeliveryDetails[index];
            deliveryPerItem = detail.deliveryFeePerItem || 0;
            totalDeliveryForItem = detail.itemTotalDelivery || 0;
            allocationPercentage = detail.deliverySizePercentage || 0;
        }

        if (deliveryPerItemElement) {
            deliveryPerItemElement.textContent = `₱${deliveryPerItem.toFixed(2)}`;
        }
        if (totalDeliveryForItemElement) {
            totalDeliveryForItemElement.textContent = `₱${totalDeliveryForItem.toFixed(2)}`;
        }

        // Show percentage info for additional fees
        if (sizeAllocationElement && allocationPercentage > 0) {
            sizeAllocationElement.textContent = `${allocationPercentage.toFixed(1)}% additional`;
            if (sizeAllocationInfo) {
                sizeAllocationInfo.style.display = 'flex';
            }
        } else if (sizeAllocationInfo) {
            sizeAllocationInfo.style.display = 'none';
        }

        console.log(`Updated item ${index}: ₱${deliveryPerItem.toFixed(2)} additional per item, ₱${totalDeliveryForItem.toFixed(2)} total additional`);
    });
}

// UPDATED: Enhanced delivery display showing base + additional fees breakdown
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
            // Show breakdown of base delivery + additional percentage fees
            const totalItems = deliveryResult.itemDeliveryDetails.reduce((sum, detail) => sum + detail.quantity, 0);
            
            distanceResultElement.innerHTML = `
                <div class="bg-blue-100 border border-blue-300 rounded p-3">
                    <div class="font-medium text-blue-800">Zone: ${selectedZone.zone_name}</div>
                    <div class="font-medium text-blue-800">Distance: ${distance.toFixed(2)} km</div>
                    <div class="font-medium text-blue-800">Est. Time: ${routeData.time} minutes</div>
                    <div class="border-t border-blue-200 mt-2 pt-2 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Base Delivery Fee:</span>
                            <span>₱${deliveryResult.baseDeliveryFee.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Item Additional Fees:</span>
                            <span>₱${deliveryResult.additionalFees.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between font-medium text-blue-800 border-t border-blue-200 pt-1">
                            <span>Total Delivery:</span>
                            <span>₱${deliveryResult.totalDeliveryCost.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="text-sm text-blue-600 mt-2">
                        ${totalItems} items with percentage-based additional fees
                    </div>
                </div>
            `;
        }
    }
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

// LEGACY FUNCTION: Keep for backward compatibility (but use the new percentage-based one)
function calculateZoneBasedDeliveryJS(distance, zone) {
    console.warn('Using legacy calculateZoneBasedDeliveryJS - consider switching to calculatePercentageBasedDeliveryJS');
    return calculatePercentageBasedDeliveryJS(distance, zone);
}

// LEGACY FUNCTION: Redirect to new function
function calculateSizeBasedDeliveryJS(distance, zone) {
    console.warn('Using legacy calculateSizeBasedDeliveryJS - consider switching to calculatePercentageBasedDeliveryJS');
    return calculatePercentageBasedDeliveryJS(distance, zone);
}