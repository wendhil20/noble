// distanceCalculation.js - Transportify vehicle-based delivery calculations

// ✅ NEW: Handle delivery type switching
function initializeDeliveryTypeSelection() {
    const deliveryRadios = document.querySelectorAll('input[name="delivery_type"]');
    const deliverySection = document.getElementById('deliveryCalculationSection');
    const pickupSection = document.getElementById('pickupInformationSection');
    const continueBtn = document.getElementById('continueToPayment');
    
    deliveryRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'delivery') {
                // Show delivery calculation
                if (deliverySection) deliverySection.classList.remove('hidden');
                if (pickupSection) pickupSection.classList.add('hidden');
                
                // Hide vehicle details until calculation
                const vehicleDetails = document.getElementById('assignedVehicleDetails');
                if (vehicleDetails) {
                    vehicleDetails.classList.add('hidden');
                }
                
                // Require calculation
                if (continueBtn) {
                    continueBtn.disabled = true;
                    continueBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    continueBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
                }
                
                showNotification('Calculating delivery cost...', 'info');
                
                // ✅ Auto-trigger calculation for delivery
                if (typeof autoCalculateDelivery === 'function') {
                    setTimeout(() => {
                        autoCalculateDelivery();
                    }, 500);
                }
                
            } else if (this.value === 'pickup') {
                // Show pickup info
                if (deliverySection) deliverySection.classList.add('hidden');
                if (pickupSection) pickupSection.classList.remove('hidden');
                
                // Set delivery fee to 0
                const deliveryFeeInput = document.getElementById('deliveryFee');
                const deliveryDistanceInput = document.getElementById('deliveryDistance');
                if (deliveryFeeInput) deliveryFeeInput.value = '0';
                if (deliveryDistanceInput) deliveryDistanceInput.value = '0';
                
                // Update totals to zero delivery
                updateTotalsDisplay(0);
                
                // ✅ NEW: Calculate and show vehicle assignment for pickup too
                if (window.cartItemsData && window.cartItemsData.length > 0) {
                    console.log('Calculating vehicle assignment for pickup...');
                    
                    const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData);
                    
                    if (vehicleAssignment && vehicleAssignment.vehicle) {
                        // Show vehicle details with "For Information" message
                        showPickupVehicleDetails(vehicleAssignment.vehicle, vehicleAssignment);
                    }
                }
                
                // Enable continue button
                if (continueBtn) {
                    continueBtn.disabled = false;
                    continueBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }
                
                showNotification('Pick-up selected - No delivery fee!', 'success');
            }
        });
    });
    
    console.log('Delivery type selection initialized');
}

// ✅ NEW: Automatically calculate when address is selected
function autoCalculateDelivery() {
    if (!selectedAddress) {
        console.log('No address selected yet');
        return;
    }
    
    // ✅ NEW: Check if courier is selected
    const courierSelect = document.getElementById('courierSelection');
    if (!courierSelect || !courierSelect.value) {
        console.log('No courier selected yet');
        showNotification('Please select a courier service first.', 'warning');
        return;
    }
    
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked');
    if (!deliveryType || deliveryType.value !== 'delivery') {
        console.log('Not in delivery mode');
        return;
    }
    
    // Show loading indicator
    const loader = document.getElementById('autoCalculationLoader');
    if (loader) {
        loader.classList.remove('hidden');
    }
    
    // Hide any previous results
    const distanceResult = document.getElementById('distanceResult');
    if (distanceResult) {
        distanceResult.innerHTML = '';
    }
    
    const vehicleDetails = document.getElementById('assignedVehicleDetails');
    if (vehicleDetails) {
        vehicleDetails.classList.add('hidden');
    }
    
    const calculateBtn = document.getElementById('calculateDistance');
    if (calculateBtn) {
        console.log('Auto-triggering delivery calculation...');
        
        // Enable button temporarily to allow click
        calculateBtn.disabled = false;
        
        // Trigger calculation
        calculateBtn.click();
        
        // Hide loader after calculation (will be handled by the click event)
        setTimeout(() => {
            if (loader) {
                loader.classList.add('hidden');
            }
        }, 1000);
    }
}

// ✅ NEW: Calculate and display order dimensions
function calculateOrderDimensions() {
    if (!window.cartItemsData || window.cartItemsData.length === 0) {
        console.warn('No cart items for dimension calculation');
        return;
    }
    
    let totalCubicMeters = 0;
    let totalWeightKg = 0;
    let totalWidthM = 0;
    let totalHeightM = 0;
    let totalLengthM = 0;
    
    window.cartItemsData.forEach((item, index) => {
        let width = parseFloat(item.width) || 30;
        let height = parseFloat(item.height) || 30;
        let length = parseFloat(item.length) || 30;
        let weight = parseFloat(item.weight) || 1;
        
        const dimensionUnit = item.dimension_unit || 'cm';
        const weightUnit = item.weight_unit || 'kg';
        const quantity = parseInt(item.quantity) || 1;
        
        // Calculate cubic meters and weight
        const itemCubicM = calculateCubicMetersJS(width, height, length, dimensionUnit, quantity);
        const itemWeightKg = convertToKilogramsJS(weight, weightUnit, quantity);
        
        totalCubicMeters += itemCubicM;
        totalWeightKg += itemWeightKg;
        
        // Convert dimensions to meters for total
        const meters = {
            'cm': 0.01, 'm': 1, 'mm': 0.001, 'in': 0.0254, 'ft': 0.3048
        };
        const multiplier = meters[dimensionUnit.toLowerCase()] || 0.01;
        
        totalWidthM += (width * multiplier * quantity);
        totalHeightM += (height * multiplier * quantity);
        totalLengthM += (length * multiplier * quantity);
    });
    
    // Update display
    const totalVolumeEl = document.getElementById('totalVolume');
    const totalWeightEl = document.getElementById('totalWeight');
    const totalWidthEl = document.getElementById('totalWidth');
    const totalHeightEl = document.getElementById('totalHeight');
    const totalLengthEl = document.getElementById('totalLength');
    
    if (totalVolumeEl) totalVolumeEl.textContent = totalCubicMeters.toFixed(3) + ' m³';
    if (totalWeightEl) totalWeightEl.textContent = totalWeightKg.toFixed(2) + ' kg';
    if (totalWidthEl) totalWidthEl.textContent = totalWidthM.toFixed(2) + ' m';
    if (totalHeightEl) totalHeightEl.textContent = totalHeightM.toFixed(2) + ' m';
    if (totalLengthEl) totalLengthEl.textContent = totalLengthM.toFixed(2) + ' m';
    
    console.log('Order Dimensions:', {
        volume: totalCubicMeters.toFixed(3) + ' m³',
        weight: totalWeightKg.toFixed(2) + ' kg',
        width: totalWidthM.toFixed(2) + ' m',
        height: totalHeightM.toFixed(2) + ' m',
        length: totalLengthM.toFixed(2) + ' m'
    });
}

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
    // ✅ Hide auto-calculation loader
    const loader = document.getElementById('autoCalculationLoader');
    if (loader) {
        loader.classList.add('hidden');
    }
    
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

                // ✅ NEW: Get selected courier
const courierSelect = document.getElementById('courierSelection');
const selectedCourier = courierSelect ? courierSelect.value : null;

if (!selectedCourier) {
    throw new Error('Please select a courier service first');
}

// NEW: Automatic vehicle assignment with courier filter
const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData, selectedCourier);
                
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

                // Show assigned vehicle details
                if (vehicleAssignment && vehicleAssignment.vehicle) {
                    showAssignedVehicleDetails(vehicleAssignment.vehicle, vehicleAssignment);
                }

                // Enable continue button
                if (continueToPaymentBtn) {
                    continueToPaymentBtn.disabled = false;
                    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                
                // Show assigned vehicle details
                if (vehicleAssignment && vehicleAssignment.vehicle) {
                    showAssignedVehicleDetails(vehicleAssignment.vehicle, vehicleAssignment);
                }

                const successMessage = `Delivery calculated: ${vehicleAssignment.vehicle.vehicle_type} - ₱${deliveryResult.totalDeliveryCost.toFixed(2)}`;
                showNotification(successMessage, 'success');

            } catch (error) {
    console.error('Delivery calculation error:', error);
    
    // ✅ Hide loader on error
    const loader = document.getElementById('autoCalculationLoader');
    if (loader) {
        loader.classList.add('hidden');
    }

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
    // ✅ NEW: Add courier selection change handler
    const courierSelect = document.getElementById('courierSelection');
    if (courierSelect) {
        courierSelect.addEventListener('change', function() {
            const selectedCourier = this.value;
            const courierInfo = document.getElementById('courierInfo');
            const continuePaymentBtn = document.getElementById('continueToPayment');
            const calculateBtn = document.getElementById('calculateDistance');
            
            if (selectedCourier) {
    // Show courier info
    if (courierInfo) {
        const vehicleCount = window.couriersGrouped[selectedCourier]?.length || 0;
        document.getElementById('courierVehicleCount').textContent = vehicleCount;
        
        // ✅ NEW: Update courier name display
        const courierNameEl = document.getElementById('selectedCourierName');
        if (courierNameEl) {
            courierNameEl.textContent = selectedCourier;
        }
        
        courierInfo.classList.remove('hidden');
    }
                
                // Enable calculate button
                if (calculateBtn) {
                    calculateBtn.disabled = false;
                    calculateBtn.classList.remove('bg-gray-400');
                    calculateBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }
                
                // Reset calculation state
                if (continuePaymentBtn) {
                    continuePaymentBtn.disabled = true;
                    continuePaymentBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    continuePaymentBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
                }
                
                // Clear previous results
                const distanceResult = document.getElementById('distanceResult');
                if (distanceResult) {
                    distanceResult.innerHTML = '<div class="text-sm text-blue-600 italic">Courier selected. Click "Calculate Distance & Fee" to continue.</div>';
                }
                
                const vehicleDetails = document.getElementById('assignedVehicleDetails');
                if (vehicleDetails) {
                    vehicleDetails.classList.add('hidden');
                }
                
                showNotification(`Selected: ${selectedCourier}. Please calculate delivery.`, 'info');
                
                // ✅ Auto-trigger calculation if address is already selected
                if (selectedAddress && selectedAddress.latitude && selectedAddress.longitude) {
                    setTimeout(() => {
                        if (typeof autoCalculateDelivery === 'function') {
                            autoCalculateDelivery();
                        }
                    }, 500);
                }
            } else {
                // Hide courier info
                if (courierInfo) {
                    courierInfo.classList.add('hidden');
                }
                
                // Disable calculate button
                if (calculateBtn) {
                    calculateBtn.disabled = true;
                    calculateBtn.classList.add('bg-gray-400');
                    calculateBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                }
            }
        });
        
        console.log('✓ Courier selection handler initialized');
    }
}

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
function assignTransportifyVehicleJS(cartItems, selectedCourier = null) {
    console.log('=== Starting Vehicle Assignment ===');
    console.log('Selected Courier:', selectedCourier || 'None (using all vehicles)');
    
    let totalCubicMeters = 0;
    let totalWeightKg = 0;
    const itemVehicleData = [];
    
    // Calculate total volume and weight
    cartItems.forEach((item, index) => {
        let width = parseFloat(item.width) || 0;
        let height = parseFloat(item.height) || 0;
        let length = parseFloat(item.length) || 0;
        let weight = parseFloat(item.weight) || 0;
        
        if (width === 0 && height === 0 && length === 0) {
            console.warn(`Item ${index} (${item.variant_name}) has no dimensions. Using default: 30x30x30cm`);
            width = 30;
            height = 30;
            length = 30;
        }
        
        if (weight === 0) {
            const volumeCm3 = width * height * length;
            weight = Math.max(1, volumeCm3 / 10000);
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
    
    // ✅ NEW: Filter vehicles by selected courier
    let availableVehicles = [];
    
    if (selectedCourier && window.couriersGrouped && window.couriersGrouped[selectedCourier]) {
        availableVehicles = window.couriersGrouped[selectedCourier];
        console.log(`Filtering by courier: ${selectedCourier} (${availableVehicles.length} vehicles)`);
    } else {
        availableVehicles = window.transportifyVehicles || [];
        console.log(`Using all vehicles (${availableVehicles.length} total)`);
    }
    
    if (availableVehicles.length === 0) {
        console.error('No vehicles available for selected courier');
        return null;
    }
    
    // Sort by capacity (smallest first)
    availableVehicles.sort((a, b) => {
        const aCapacity = parseFloat(a.max_cubic_meter) || 0;
        const bCapacity = parseFloat(b.max_cubic_meter) || 0;
        return aCapacity - bCapacity;
    });
    
    // Find suitable vehicle (smallest that fits)
    let assignedVehicle = null;
    for (const vehicle of availableVehicles) {
        const maxCubicM = parseFloat(vehicle.max_cubic_meter) || 0;
        const maxWeightKg = parseFloat(vehicle.max_weight_capacity) || 0;
        
        if (totalCubicMeters <= maxCubicM && totalWeightKg <= maxWeightKg) {
            assignedVehicle = vehicle;
            console.log(`✓ Assigned Vehicle: ${vehicle.vehicle_type} from ${vehicle.courier_name} (Fits: ${maxCubicM}m³, ${maxWeightKg}kg)`);
            break;
        }
    }
    
    if (!assignedVehicle) {
        assignedVehicle = availableVehicles[availableVehicles.length - 1];
        console.warn(`⚠ No perfect fit. Using largest from ${selectedCourier || 'all couriers'}: ${assignedVehicle.vehicle_type}`);
    }
    
    return {
        vehicle: assignedVehicle,
        totalCubicMeters: totalCubicMeters,
        totalWeightKg: totalWeightKg,
        itemVehicleData: itemVehicleData,
        courierName: assignedVehicle.courier_name
    };
}

/**
 * Calculate Transportify delivery cost
 */
function calculateTransportifyDeliveryCostJS(distanceKm, vehicleAssignment) {
    const vehicle = vehicleAssignment.vehicle;
    
    const baseFare = parseFloat(vehicle.base_fare) || 0;
    const addPerKm = parseFloat(vehicle.add_per_km) || 0;
    const perKmRate = 0; // ✅ CHANGED: Start charging from 0 km instead of 1 km
    
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

      // ✅ NEW: Calculate expected delivery date based on latest lead time
let latestLeadTimeRange = null;
let hasLeadTime = false;
let hasMultipleProducts = window.cartItemsData && window.cartItemsData.length > 1;

if (window.cartItemsData && window.cartItemsData.length > 0) {
    window.cartItemsData.forEach(item => {
        const leadTimeRange = calculateLeadTimeRangeJS(
            item.lead_count,
            item.lead_interval,
            item.lead_gap
        );
        
        if (leadTimeRange && leadTimeRange.end_date) {
            hasLeadTime = true;
            if (!latestLeadTimeRange || leadTimeRange.end_date > latestLeadTimeRange.end_date) {
                latestLeadTimeRange = leadTimeRange;
            }
        }
    });
        /**
 * Calculate lead time range in JavaScript (mirrors PHP function)
 */
function calculateLeadTimeRangeJS(leadCount, leadInterval, leadGap) {
    if (!leadCount || !leadInterval) {
        return null;
    }
    
    const today = new Date();
    const startDate = new Date(today);
    
    // Calculate start date based on interval
    switch (leadInterval) {
        case 'day':
            startDate.setDate(startDate.getDate() + parseInt(leadCount));
            break;
        case 'week':
            startDate.setDate(startDate.getDate() + (parseInt(leadCount) * 7));
            break;
        case 'month':
            startDate.setMonth(startDate.getMonth() + parseInt(leadCount));
            break;
        case 'year':
            startDate.setFullYear(startDate.getFullYear() + parseInt(leadCount));
            break;
    }
    
    // Calculate end date (start date + gap)
    const endDate = new Date(startDate);
    if (leadGap && parseInt(leadGap) > 0) {
        endDate.setDate(endDate.getDate() + parseInt(leadGap));
    }
    
    return {
        start_date: startDate,
        end_date: endDate,
        display: startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
                 ' - ' + 
                 endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    };
}

// Export to global scope
window.calculateLeadTimeRangeJS = calculateLeadTimeRangeJS;
    }


    
    const vehicle = deliveryResult.vehicleInfo;
    const chargeableKm = deliveryResult.chargeableKm;
    
    distanceResultElement.innerHTML = `
    <div class="bg-blue-100 border border-blue-300 rounded p-4">
        <div class="font-bold text-blue-900 mb-3">🚚 ${vehicle.vehicle_type}</div>
        
        ${hasLeadTime && latestLeadTimeRange ? `
<div class="bg-green-50 border border-green-300 rounded p-3 mb-3">
    <div class="flex items-center gap-2 mb-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <span class="font-bold text-green-800">Expected Delivery By:</span>
    </div>
    <div class="text-lg font-bold text-green-700 mb-2">
        ${latestLeadTimeRange.start_date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        })} - ${latestLeadTimeRange.end_date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        })}
    </div>
    ${hasMultipleProducts ? `
    <div class="text-xs text-green-600 bg-green-100 rounded p-2">
        <div class="flex items-start gap-1">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><strong>Note:</strong> This is the latest estimated delivery date for your order. To receive items faster, you can order them separately instead of in a single order.</span>
        </div>
    </div>
    ` : ''}
</div>
` : ''}
        
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
                ${deliveryResult.chargeableKm > 0 ? `
                <div class="flex justify-between text-xs text-gray-600">
                    <span>Additional (${deliveryResult.chargeableKm.toFixed(2)} km × ₱${parseFloat(vehicle.add_per_km).toFixed(2)}):</span>
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
        </div>
    </div>
`;
    
    // ✅ NEW: Show assigned vehicle details with dimensions comparison
    showAssignedVehicleDetails(vehicle, vehicleAssignment);
}

// ✅ SIMPLIFIED: Display assigned vehicle details
function showAssignedVehicleDetails(vehicle, vehicleAssignment) {
    const detailsContainer = document.getElementById('assignedVehicleDetails');
    const contentContainer = document.getElementById('vehicleDetailsContent');
    
    if (!detailsContainer || !contentContainer) return;
    
    const orderCubicM = vehicleAssignment.totalCubicMeters;
    const orderWeightKg = vehicleAssignment.totalWeightKg;
    const maxCubicM = parseFloat(vehicle.max_cubic_meter);
    const maxWeightKg = parseFloat(vehicle.max_weight_capacity);
    
    // Calculate percentages
    const volumePercentage = Math.min((orderCubicM / maxCubicM) * 100, 100);
    const weightPercentage = Math.min((orderWeightKg / maxWeightKg) * 100, 100);
    
    // Determine bar colors based on usage
    const getBarColor = (percentage) => {
        if (percentage <= 50) return 'bg-green-500';
        if (percentage <= 75) return 'bg-yellow-500';
        if (percentage <= 90) return 'bg-orange-500';
        return 'bg-red-500';
    };
    
    const volumeBarColor = getBarColor(volumePercentage);
    const weightBarColor = getBarColor(weightPercentage);
    
    // ✅ Enhanced display with progress bars
    contentContainer.innerHTML = `
    <div class="space-y-4">
        <!-- ✅ NEW: Show Courier Name -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-2">
            <div class="flex items-center justify-center text-sm">
                <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                </svg>
                <span class="font-semibold text-blue-700">${vehicle.courier_name || 'Unknown Courier'}</span>
            </div>
        </div>
        
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span class="font-semibold text-gray-800">${vehicle.vehicle_type}</span>
            </div>
            <span class="text-xs text-gray-500">${vehicle.vehicle_description || 'Vehicle'}</span>
        </div>
            
            <!-- Volume Progress Bar -->
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Volume Capacity</span>
                    <span class="text-xs font-semibold ${volumePercentage > 90 ? 'text-red-600' : 'text-gray-600'}">
                        ${volumePercentage.toFixed(1)}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="${volumeBarColor} h-3 rounded-full transition-all duration-500 ease-out" 
                         style="width: ${volumePercentage}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-xs">
                    <span class="text-green-700 font-semibold">${orderCubicM.toFixed(3)} m³</span>
                    <span class="text-gray-500">of ${maxCubicM.toFixed(2)} m³</span>
                </div>
            </div>
            
            <!-- Weight Progress Bar -->
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Weight Capacity</span>
                    <span class="text-xs font-semibold ${weightPercentage > 90 ? 'text-red-600' : 'text-gray-600'}">
                        ${weightPercentage.toFixed(1)}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="${weightBarColor} h-3 rounded-full transition-all duration-500 ease-out" 
                         style="width: ${weightPercentage}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-xs">
                    <span class="text-green-700 font-semibold">${orderWeightKg.toFixed(2)} kg</span>
                    <span class="text-gray-500">of ${maxWeightKg.toFixed(2)} kg</span>
                </div>
            </div>
            
            ${volumePercentage > 100 || weightPercentage > 100 ? `
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <div class="flex items-center text-red-700 text-xs">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span class="font-semibold">Warning: Load exceeds vehicle capacity!</span>
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    detailsContainer.classList.remove('hidden');
}

// ✅ NEW: Show vehicle details for pickup mode (informational only)
function showPickupVehicleDetails(vehicle, vehicleAssignment) {
    const detailsContainer = document.getElementById('assignedVehicleDetails');
    const contentContainer = document.getElementById('vehicleDetailsContent');
    
    if (!detailsContainer || !contentContainer) return;
    
    const orderCubicM = vehicleAssignment.totalCubicMeters;
    const orderWeightKg = vehicleAssignment.totalWeightKg;
    const maxCubicM = parseFloat(vehicle.max_cubic_meter);
    const maxWeightKg = parseFloat(vehicle.max_weight_capacity);
    
    // Calculate percentages
    const volumePercentage = Math.min((orderCubicM / maxCubicM) * 100, 100);
    const weightPercentage = Math.min((orderWeightKg / maxWeightKg) * 100, 100);
    
    // Determine bar colors based on usage
    const getBarColor = (percentage) => {
        if (percentage <= 50) return 'bg-green-500';
        if (percentage <= 75) return 'bg-yellow-500';
        if (percentage <= 90) return 'bg-orange-500';
        return 'bg-red-500';
    };
    
    const volumeBarColor = getBarColor(volumePercentage);
    const weightBarColor = getBarColor(weightPercentage);
    
    // ✅ Pickup-specific display with informational message
    contentContainer.innerHTML = `
        <div class="space-y-4">
            <!-- Info Banner for Pickup -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <div class="flex items-center text-blue-800 text-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <div class="font-semibold">For Your Information</div>
                        <div class="text-xs">This vehicle would be used if you chose delivery instead</div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                    <span class="font-semibold text-gray-800">${vehicle.vehicle_type}</span>
                </div>
                <span class="text-xs text-gray-500">${vehicle.vehicle_description || 'Vehicle'}</span>
            </div>
            
            <!-- Volume Progress Bar -->
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Order Volume</span>
                    <span class="text-xs font-semibold ${volumePercentage > 90 ? 'text-red-600' : 'text-gray-600'}">
                        ${volumePercentage.toFixed(1)}% of capacity
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="${volumeBarColor} h-3 rounded-full transition-all duration-500 ease-out" 
                         style="width: ${volumePercentage}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-xs">
                    <span class="text-gray-700 font-semibold">${orderCubicM.toFixed(3)} m³</span>
                    <span class="text-gray-500">Max: ${maxCubicM.toFixed(2)} m³</span>
                </div>
            </div>
            
            <!-- Weight Progress Bar -->
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Order Weight</span>
                    <span class="text-xs font-semibold ${weightPercentage > 90 ? 'text-red-600' : 'text-gray-600'}">
                        ${weightPercentage.toFixed(1)}% of capacity
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="${weightBarColor} h-3 rounded-full transition-all duration-500 ease-out" 
                         style="width: ${weightPercentage}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-xs">
                    <span class="text-gray-700 font-semibold">${orderWeightKg.toFixed(2)} kg</span>
                    <span class="text-gray-500">Max: ${maxWeightKg.toFixed(2)} kg</span>
                </div>
            </div>
            
            ${volumePercentage > 100 || weightPercentage > 100 ? `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="flex items-center text-yellow-700 text-xs">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span class="font-semibold">Note: Your order would require special handling for delivery</span>
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    detailsContainer.classList.remove('hidden');
    console.log('✓ Pickup vehicle details displayed');
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


    // ✅ Export functions to global scope for use in other scripts
window.assignTransportifyVehicleJS = assignTransportifyVehicleJS;
window.calculateTransportifyDeliveryCostJS = calculateTransportifyDeliveryCostJS;
window.calculateCubicMetersJS = calculateCubicMetersJS;
window.convertToKilogramsJS = convertToKilogramsJS;
window.autoCalculateDelivery = autoCalculateDelivery; // ✅ NEW

console.log('✓ Distance calculation functions exported to global scope');
}