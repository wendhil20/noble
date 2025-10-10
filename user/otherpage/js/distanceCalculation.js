// distanceCalculation.js - Transportify vehicle-based delivery calculations

function initializeDistanceCalculation() {
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const continueToPaymentBtn = document.getElementById('continueToPayment');

    if (calculateDistanceBtn) {
        calculateDistanceBtn.addEventListener('click', async function() {
            if (!selectedAddress) {
                showNotification('Please select a delivery address.', 'error');
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
                if (!deliverySettings) {
                    throw new Error('Delivery settings not loaded');
                }
                if (!window.cartItemsData || window.cartItemsData.length === 0) {
                    throw new Error('No cart items found');
                }

                let distance = 0;
                let routeData = {
                    distance: 0,
                    time: 0,
                    fallback: false
                };

                // Calculate distance
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

                // NEW: Automatic Transportify vehicle assignment
                const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData);
                
                if (!vehicleAssignment || !vehicleAssignment.vehicle) {
                    throw new Error('Unable to assign suitable delivery vehicle');
                }

                // Calculate delivery cost based on vehicle and distance
                const deliveryResult = calculateTransportifyDeliveryCostJS(distance, vehicleAssignment);

                console.log('Vehicle Assignment:', vehicleAssignment);
                console.log('Delivery Calculation:', deliveryResult);

                // Update UI
                updateDeliveryDisplay(deliveryResult, routeData, distance, vehicleAssignment);

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

                const successMessage = `Delivery calculated: ${vehicleAssignment.vehicle.vehicle_type} - ₱${deliveryResult.totalDeliveryCost.toFixed(2)}`;
                showNotification(successMessage, 'success');

            } catch (error) {
                console.error('Delivery calculation error:', error);

                let errorMessage = 'Error calculating delivery fee. ';
                if (error.message.includes('coordinates')) {
                    errorMessage += 'Invalid location data.';
                } else if (error.message.includes('address')) {
                    errorMessage += 'Please select a delivery address.';
                } else if (error.message.includes('vehicle')) {
                    errorMessage += 'Unable to assign delivery vehicle.';
                } else {
                    errorMessage += error.message || 'Please try again.';
                }

                showNotification(errorMessage, 'error');
            } finally {
                calculateDistanceBtn.textContent = 'Recalculate Distance';
                calculateDistanceBtn.disabled = false;
            }
        });
    }
}

// ✅ Enable continue button after successful calculation
if (continueToPaymentBtn) {
    continueToPaymentBtn.disabled = false;
    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
}

const successMessage = `Delivery calculated: ${vehicleAssignment.vehicle.vehicle_type} - ₱${deliveryResult.totalDeliveryCost.toFixed(2)}`;
showNotification(successMessage, 'success');

// ✅ NEW: Log completion status
console.log('✓ Delivery calculation complete and saved:', {
    distance: distance.toFixed(2) + ' km',
    fee: '₱' + deliveryResult.totalDeliveryCost.toFixed(2),
    vehicle: vehicleAssignment.vehicle.vehicle_type
});

/**
 * Calculate cubic meters from dimensions
 */
function calculateCubicMetersJS(width, height, length, unit, quantity = 1) {
    const meters = {
        'cm': 0.01,
        'm': 1,
        'mm': 0.001,
        'in': 0.0254,
        'ft': 0.3048
    };
    
    const multiplier = meters[unit.toLowerCase()] || 0.01;
    
    const widthM = width * multiplier;
    const heightM = height * multiplier;
    const lengthM = length * multiplier;
    
    return (widthM * heightM * lengthM) * quantity;
}

/**
 * Convert weight to kilograms
 */
function convertToKilogramsJS(weight, unit, quantity = 1) {
    const kgConversion = {
        'kg': 1,
        'g': 0.001,
        'lb': 0.453592,
        'oz': 0.0283495
    };
    
    const multiplier = kgConversion[unit.toLowerCase()] || 1;
    return (weight * multiplier) * quantity;
}

/**
 * Automatically assign Transportify vehicle based on cart items
 */
function assignTransportifyVehicleJS(cartItems) {
    console.log('=== Starting Vehicle Assignment ===');
    
    let totalCubicMeters = 0;
    let totalWeightKg = 0;
    const itemVehicleData = [];
    
    // Calculate total volume and weight
    cartItems.forEach((item, index) => {
    // ✅ Use defaults if dimensions are missing or zero
    let width = parseFloat(item.width) || 0;
    let height = parseFloat(item.height) || 0;
    let length = parseFloat(item.length) || 0;
    let weight = parseFloat(item.weight) || 0;
    
    // ⚠️ If all dimensions are zero, use default small package size
    if (width === 0 && height === 0 && length === 0) {
        console.warn(`Item ${index} (${item.variant_name}) has no dimensions. Using default: 30x30x30cm`);
        width = 30;
        height = 30;
        length = 30;
    }
    
    // ⚠️ If weight is zero, estimate based on volume (assume 1kg per 10,000 cm³)
    if (weight === 0) {
        const volumeCm3 = width * height * length;
        weight = Math.max(1, volumeCm3 / 10000); // Minimum 1kg
        console.warn(`Item ${index} (${item.variant_name}) has no weight. Estimated: ${weight.toFixed(2)}kg`);
    }
    
    const dimensionUnit = item.dimension_unit || 'cm';
    const weightUnit = item.weight_unit || 'kg';
    const quantity = parseInt(item.quantity) || 1;
        
        const itemCubicM = calculateCubicMetersJS(width, height, length, dimensionUnit, quantity);
        const itemWeightKg = convertToKilogramsJS(weight, weightUnit, quantity);
        
        totalCubicMeters += itemCubicM;
        totalWeightKg += itemWeightKg;
        
        itemVehicleData.push({
            itemIndex: index,
            variantName: item.variant_name || item.product_name,
            quantity: quantity,
            cubicMeters: itemCubicM,
            weightKg: itemWeightKg,
            dimensions: `${width}×${height}×${length} ${dimensionUnit}`,
            weight: `${weight} ${weightUnit}`
        });
        
        console.log(`Item ${index}: ${item.variant_name}, Volume: ${itemCubicM.toFixed(3)}m³, Weight: ${itemWeightKg.toFixed(2)}kg`);
    });
    
    console.log(`Total Volume: ${totalCubicMeters.toFixed(3)}m³, Total Weight: ${totalWeightKg.toFixed(2)}kg`);
    
    // Get available vehicles from window config
    const availableVehicles = window.transportifyVehicles || [];
    
    if (availableVehicles.length === 0) {
        console.error('No Transportify vehicles available');
        return null;
    }
    
    // Find suitable vehicle (smallest that fits)
    let assignedVehicle = null;
    for (const vehicle of availableVehicles) {
        const maxCubicM = parseFloat(vehicle.max_cubic_meter) || 0;
        const maxWeightKg = parseFloat(vehicle.max_weight_capacity) || 0;
        
        if (totalCubicMeters <= maxCubicM && totalWeightKg <= maxWeightKg) {
            assignedVehicle = vehicle;
            console.log(`✓ Assigned Vehicle: ${vehicle.vehicle_type} (Fits: ${maxCubicM}m³, ${maxWeightKg}kg)`);
            break;
        }
    }
    
    if (!assignedVehicle) {
        // Use largest available vehicle
        assignedVehicle = availableVehicles[availableVehicles.length - 1];
        console.warn(`⚠ No perfect fit. Using largest: ${assignedVehicle.vehicle_type}`);
    }
    
    return {
        vehicle: assignedVehicle,
        totalCubicMeters: totalCubicMeters,
        totalWeightKg: totalWeightKg,
        itemVehicleData: itemVehicleData
    };
}

/**
 * Calculate Transportify delivery cost
 */
function calculateTransportifyDeliveryCostJS(distanceKm, vehicleAssignment) {
    const vehicle = vehicleAssignment.vehicle;
    
    const baseFare = parseFloat(vehicle.base_fare) || 0;
    const addPerKm = parseFloat(vehicle.add_per_km) || 0;
    const perKmRate = parseFloat(vehicle.per_km_rate) || 1; // Start charging at this km
    
    let deliveryCost = baseFare;
    let chargeableKm = 0;
    let perKmCharge = 0;
    
    if (distanceKm > perKmRate) {
        chargeableKm = distanceKm - perKmRate;
        perKmCharge = chargeableKm * addPerKm;
        deliveryCost += perKmCharge;
    }
    
    console.log(`Delivery Cost: Base ₱${baseFare} + (${chargeableKm.toFixed(2)}km × ₱${addPerKm}) = ₱${deliveryCost.toFixed(2)}`);
    
    return {
        totalDeliveryCost: deliveryCost,
        baseFare: baseFare,
        distanceKm: distanceKm,
        chargeableKm: chargeableKm,
        perKmCharge: perKmCharge,
        vehicleInfo: vehicle,
        vehicleData: vehicleAssignment
    };
}

/**
 * Update delivery display with vehicle info
 */
function updateDeliveryDisplay(deliveryResult, routeData, distance, vehicleAssignment) {
    const distanceResultElement = document.getElementById('distanceResult');
    if (!distanceResultElement) return;
    
    const vehicle = deliveryResult.vehicleInfo;
    const chargeableKm = deliveryResult.chargeableKm;
    
    distanceResultElement.innerHTML = `
        <div class="bg-blue-100 border border-blue-300 rounded p-4">
            <div class="font-bold text-blue-900 mb-3">🚚 ${vehicle.vehicle_type}</div>
            
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-700">Distance:</span>
                    <span class="font-medium">${distance.toFixed(2)} km</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700">Est. Time:</span>
                    <span class="font-medium">${routeData.time} minutes</span>
                </div>
                
                <div class="border-t border-blue-200 pt-2 mt-2">
                    <div class="flex justify-between">
                        <span class="text-gray-700">Base Fare:</span>
                        <span class="font-medium">₱${deliveryResult.baseFare.toFixed(2)}</span>
                    </div>
                    ${chargeableKm > 0 ? `
                    <div class="flex justify-between text-xs text-gray-600">
                        <span>Additional (${chargeableKm.toFixed(2)} km × ₱${parseFloat(vehicle.add_per_km).toFixed(2)}):</span>
                        <span>₱${deliveryResult.perKmCharge.toFixed(2)}</span>
                    </div>
                    ` : `
                    <div class="text-xs text-green-600">
                        ✓ Within ${vehicle.per_km_rate}km base coverage
                    </div>
                    `}
                    <div class="flex justify-between font-bold text-blue-900 border-t border-blue-200 pt-2 mt-2">
                        <span>Total Delivery:</span>
                        <span>₱${deliveryResult.totalDeliveryCost.toFixed(2)}</span>
                    </div>
                </div>
                
                <div class="border-t border-blue-200 pt-2 mt-2 text-xs text-gray-600">
                    <div class="font-medium mb-1">Vehicle Capacity:</div>
                    <div>Max: ${vehicle.max_cubic_meter}m³, ${vehicle.max_weight_capacity}kg</div>
                    <div>Your Load: ${vehicleAssignment.totalCubicMeters.toFixed(3)}m³, ${vehicleAssignment.totalWeightKg.toFixed(2)}kg</div>
                </div>
            </div>
        </div>
    `;
}

// Keep existing distance calculation functions
async function calculateRoutingDistance(storeLatLng, customerLatLng) {
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false&geometries=geojson`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch(url, {
            signal: controller.signal,
            headers: { 'Accept': 'application/json' }
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`OSRM API error: ${response.status}`);
        }

        const data = await response.json();

        if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            return {
                distance: route.distance / 1000,
                time: Math.round(route.duration / 60),
                success: true,
                fallback: false
            };
        } else {
            throw new Error('No routes found');
        }
    } catch (error) {
        console.warn('OSRM failed, using fallback:', error.message);
        
        const distance = calculateHaversineDistance(
            storeLatLng.lat, storeLatLng.lng,
            customerLatLng.lat, customerLatLng.lng
        );

        return {
            distance: distance,
            time: Math.round(distance * 3),
            success: true,
            fallback: true
        };
    }
}

function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function updateTotalsDisplay(deliveryCost) {
    const totals = calculateTotalsWithVAT(subtotal, deliveryCost);

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
    
    // Update PayMongo amount if PayMongo is selected
    const paymongoAmountElement = document.getElementById('paymongoAmount');
    if (paymongoAmountElement) {
        paymongoAmountElement.textContent = `₱${totals.grandTotal.toFixed(2)}`;
    }
}