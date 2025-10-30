// distanceCalculation.js - Auto-calculate when delivery is selected

console.log('✅ distanceCalculation.js loading...');

// ✅ INITIALIZATION: Ensure selectedAddress is set from session/config
(function initializeSelectedAddress() {
    console.log('🔧 Initializing selectedAddress from sources...');
    
    const sources = [
        window.selectedAddress,
        window.checkoutConfig?.customerAddress,
        window.customerAddress
    ];
    
    let validAddress = null;
    
    for (const source of sources) {
        if (source && source.latitude && source.longitude && 
            source.latitude !== 'null' && source.longitude !== 'null' &&
            source.latitude !== null && source.longitude !== null) {
            
            const lat = parseFloat(source.latitude);
            const lng = parseFloat(source.longitude);
            
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                validAddress = {
                    latitude: lat,
                    longitude: lng,
                    address: source.address || '',
                    zipcode: source.zipcode || '',
                    mobile: source.mobile || ''
                };
                console.log('✅ Found valid address:', { lat, lng });
                break;
            }
        }
    }
    
    if (validAddress) {
        window.selectedAddress = validAddress;
        console.log('✅ selectedAddress initialized');
    } else {
        console.warn('⚠️ No valid address found');
    }
    
    if (!window.deliverySettings && window.checkoutConfig?.deliverySettings) {
        window.deliverySettings = window.checkoutConfig.deliverySettings;
        console.log('✅ deliverySettings initialized');
    }
})();

// ✅ DELIVERY TYPE SELECTION
function initializeDeliveryTypeSelection() {
    console.log('🔧 Initializing delivery type selection...');
    
    const deliveryRadios = document.querySelectorAll('input[name="delivery_type"]');
    const deliverySection = document.getElementById('deliveryCalculationSection');
    const pickupSection = document.getElementById('pickupInformationSection');
    const continueBtn = document.getElementById('continueToPayment');
    
    deliveryRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('📢 Delivery type changed to:', this.value);
            
            if (this.value === 'delivery') {
                // Show delivery section
                if (deliverySection) deliverySection.classList.remove('hidden');
                if (pickupSection) pickupSection.classList.add('hidden');
                
                // Disable continue button
                if (continueBtn) {
                    continueBtn.disabled = true;
                    continueBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    continueBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
                }
                
                // Auto trigger calculation after short delay
                setTimeout(() => {
                    console.log('⏱️ Waiting 300ms before auto-calculate...');
                    const calculateBtn = document.getElementById('calculateDistance');
                    if (calculateBtn && !calculateBtn.disabled) {
                        console.log('🔄 Clicking calculate button...');
                        calculateBtn.click();
                    } else {
                        console.log('⚠️ Calculate button not ready:', calculateBtn?.disabled);
                    }
                }, 300);
                
            } else if (this.value === 'pickup') {
                if (deliverySection) deliverySection.classList.add('hidden');
                if (pickupSection) pickupSection.classList.remove('hidden');
                
                // Set delivery fee to 0
                const deliveryFeeInput = document.getElementById('deliveryFee');
                const deliveryDistanceInput = document.getElementById('deliveryDistance');
                if (deliveryFeeInput) deliveryFeeInput.value = '0';
                if (deliveryDistanceInput) deliveryDistanceInput.value = '0';
                
                // Clear vehicle details
                const vehicleDetails = document.getElementById('assignedVehicleDetails');
                if (vehicleDetails) vehicleDetails.classList.add('hidden');
                
                // Update totals
                if (typeof updateTotalsDisplay === 'function') {
                    updateTotalsDisplay(0);
                }
                
                // Calculate vehicle for pickup
                if (window.cartItemsData && window.cartItemsData.length > 0) {
                    const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData);
                    if (vehicleAssignment && vehicleAssignment.vehicle) {
                        showPickupVehicleDetails(vehicleAssignment.vehicle, vehicleAssignment);
                    }
                }
                
                // Enable continue button
                if (continueBtn) {
                    continueBtn.disabled = false;
                    continueBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }
            }
        });
    });
    
    console.log('✓ Delivery type selection initialized');
}

// ✅ MAIN DISTANCE CALCULATION HANDLER
function initializeDistanceCalculation() {
    console.log('🔧 Initializing distance calculation...');
    
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const continueToPaymentBtn = document.getElementById('continueToPayment');

    if (calculateDistanceBtn) {
        calculateDistanceBtn.addEventListener('click', async function() {
            console.log('🚀 Calculate Distance clicked');
            
            if (!window.selectedAddress || !window.selectedAddress.latitude || !window.selectedAddress.longitude) {
                console.error('❌ Invalid address');
                alert('Invalid delivery address');
                return;
            }

            const originalText = calculateDistanceBtn.textContent;
            calculateDistanceBtn.textContent = 'Calculating...';
            calculateDistanceBtn.disabled = true;

            try {
                console.log('📍 Store coords:', window.deliverySettings?.latitude, window.deliverySettings?.longitude);
                console.log('📍 Customer coords:', window.selectedAddress.latitude, window.selectedAddress.longitude);

                if (!window.deliverySettings) throw new Error('Delivery settings not loaded');
                if (!window.cartItemsData || window.cartItemsData.length === 0) throw new Error('No cart items');

                const storeLatLng = {
                    lat: parseFloat(window.deliverySettings.latitude),
                    lng: parseFloat(window.deliverySettings.longitude)
                };
                const customerLatLng = {
                    lat: window.selectedAddress.latitude,
                    lng: window.selectedAddress.longitude
                };

                if (isNaN(storeLatLng.lat) || isNaN(storeLatLng.lng)) throw new Error('Invalid store coordinates');
                if (isNaN(customerLatLng.lat) || isNaN(customerLatLng.lng)) throw new Error('Invalid customer coordinates');

                console.log('🌐 Calculating route...');
                const routeData = await calculateRoutingDistance(storeLatLng, customerLatLng);
                const distance = routeData.distance || 0;
                
                console.log('📏 Distance:', distance, 'km');

                const courierSelect = document.getElementById('courierSelection');
                const selectedCourier = courierSelect ? courierSelect.value : null;

                if (!selectedCourier) throw new Error('No courier selected');

                console.log('🚚 Selected courier:', selectedCourier);

                const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData, selectedCourier);
                
                if (!vehicleAssignment || !vehicleAssignment.vehicle) throw new Error('Unable to assign vehicle');

                console.log('🚙 Assigned vehicle:', vehicleAssignment.vehicle.vehicle_type);

                const deliveryResult = calculateTransportifyDeliveryCostJS(distance, vehicleAssignment);

                console.log('💰 Delivery cost:', deliveryResult.totalDeliveryCost);

                updateDeliveryDisplay(deliveryResult, routeData, distance, vehicleAssignment);

                // Update hidden fields
                document.getElementById('deliveryDistance').value = distance.toFixed(2);
                document.getElementById('deliveryFee').value = deliveryResult.totalDeliveryCost.toFixed(2);
                document.getElementById('assignedVehicleId').value = vehicleAssignment.vehicle.id || '';
                document.getElementById('assignedVehicleType').value = vehicleAssignment.vehicle.vehicle_type || '';
                document.getElementById('totalCubicMeters').value = vehicleAssignment.totalCubicMeters.toFixed(3);
                document.getElementById('totalWeightKg').value = vehicleAssignment.totalWeightKg.toFixed(2);

                // Update totals
                if (typeof updateTotalsDisplay === 'function') {
                    updateTotalsDisplay(deliveryResult.totalDeliveryCost);
                }

                // Show vehicle details
                if (vehicleAssignment && vehicleAssignment.vehicle) {
                    showAssignedVehicleDetails(vehicleAssignment.vehicle, vehicleAssignment);
                }

                // Enable continue button
                if (continueToPaymentBtn) {
                    continueToPaymentBtn.disabled = false;
                    continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }

                console.log('✅ Delivery calculation SUCCESS');

            } catch (error) {
                console.error('❌ Error:', error.message);
                alert('Error calculating delivery: ' + error.message);
            } finally {
                calculateDistanceBtn.textContent = originalText;
                calculateDistanceBtn.disabled = false;
            }
        });
        
        console.log('✓ Calculate button handler attached');
    }
    
    // Courier selection - enable calculate button
    const courierSelect = document.getElementById('courierSelection');
    if (courierSelect) {
        // Check if courier is already selected (auto-selected on load)
        if (courierSelect.value) {
            const calculateBtn = document.getElementById('calculateDistance');
            if (calculateBtn) {
                calculateBtn.disabled = false;
                calculateBtn.classList.remove('bg-gray-400');
                calculateBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                console.log('✅ Calculate button enabled for pre-selected courier:', courierSelect.value);
            }
        }
        
        // Listen for courier changes
        courierSelect.addEventListener('change', function() {
            const calculateBtn = document.getElementById('calculateDistance');
            if (this.value) {
                console.log('✅ Courier selected:', this.value);
                if (calculateBtn) {
                    calculateBtn.disabled = false;
                    calculateBtn.classList.remove('bg-gray-400');
                    calculateBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }
            }
        });
    }
    
    console.log('✓ Distance calculation initialized');
}

// ✅ HELPER FUNCTIONS
function calculateCubicMetersJS(width, height, length, unit, quantity = 1) {
    const meters = { 'cm': 0.01, 'm': 1, 'mm': 0.001, 'in': 0.0254, 'ft': 0.3048 };
    const multiplier = meters[unit.toLowerCase()] || 0.01;
    return (width * multiplier) * (height * multiplier) * (length * multiplier) * quantity;
}

function convertToKilogramsJS(weight, unit, quantity = 1) {
    const kgConversion = { 'kg': 1, 'g': 0.001, 'lb': 0.453592, 'oz': 0.0283495 };
    const multiplier = kgConversion[unit.toLowerCase()] || 1;
    return (weight * multiplier) * quantity;
}

// ✅ VEHICLE ASSIGNMENT
function assignTransportifyVehicleJS(cartItems, selectedCourier = null) {
    console.log('🔍 Assigning vehicle for courier:', selectedCourier);
    
    let totalCubicMeters = 0;
    let totalWeightKg = 0;
    
    cartItems.forEach((item, index) => {
        let width = parseFloat(item.width) || 30;
        let height = parseFloat(item.height) || 30;
        let length = parseFloat(item.length) || 30;
        let weight = parseFloat(item.weight) || 1;
        
        const dimensionUnit = item.dimension_unit || 'cm';
        const weightUnit = item.weight_unit || 'kg';
        const quantity = parseInt(item.quantity) || 1;
        
        totalCubicMeters += calculateCubicMetersJS(width, height, length, dimensionUnit, quantity);
        totalWeightKg += convertToKilogramsJS(weight, weightUnit, quantity);
    });
    
    console.log(`📊 Total: ${totalCubicMeters.toFixed(3)}m³, ${totalWeightKg.toFixed(2)}kg`);
    
    let availableVehicles = [];
    
    if (selectedCourier && window.couriersGrouped && window.couriersGrouped[selectedCourier]) {
        availableVehicles = window.couriersGrouped[selectedCourier];
    } else {
        availableVehicles = window.transportifyVehicles || [];
    }
    
    if (availableVehicles.length === 0) {
        console.error('❌ No vehicles available');
        return null;
    }
    
    availableVehicles.sort((a, b) => {
        const aCapacity = parseFloat(a.max_cubic_meter) || 0;
        const bCapacity = parseFloat(b.max_cubic_meter) || 0;
        return aCapacity - bCapacity;
    });
    
    let assignedVehicle = null;
    for (const vehicle of availableVehicles) {
        const maxCubicM = parseFloat(vehicle.max_cubic_meter) || 0;
        const maxWeightKg = parseFloat(vehicle.max_weight_capacity) || 0;
        
        if (totalCubicMeters <= maxCubicM && totalWeightKg <= maxWeightKg) {
            assignedVehicle = vehicle;
            break;
        }
    }
    
    if (!assignedVehicle) {
        assignedVehicle = availableVehicles[availableVehicles.length - 1];
    }
    
    console.log('✅ Vehicle assigned:', assignedVehicle.vehicle_type);
    
    return {
        vehicle: assignedVehicle,
        totalCubicMeters: totalCubicMeters,
        totalWeightKg: totalWeightKg,
        courierName: assignedVehicle.courier_name
    };
}

// ✅ DELIVERY COST CALCULATION
function calculateTransportifyDeliveryCostJS(distanceKm, vehicleAssignment) {
    const vehicle = vehicleAssignment.vehicle;
    const baseFare = parseFloat(vehicle.base_fare) || 0;
    const addPerKm = parseFloat(vehicle.add_per_km) || 0;
    
    let deliveryCost = baseFare + (distanceKm * addPerKm);
    
    return {
        totalDeliveryCost: deliveryCost,
        baseFare: baseFare,
        distanceKm: distanceKm,
        chargeableKm: distanceKm,
        perKmCharge: distanceKm * addPerKm,
        vehicleInfo: vehicle,
        vehicleData: vehicleAssignment
    };
}

// ✅ UPDATE DELIVERY DISPLAY
function updateDeliveryDisplay(deliveryResult, routeData, distance, vehicleAssignment) {
    const distanceResultElement = document.getElementById('distanceResult');
    if (!distanceResultElement) return;

    const vehicle = deliveryResult.vehicleInfo;
    
    distanceResultElement.innerHTML = `
    <div class="p-4 bg-blue-50 rounded-lg">
        <div class="font-bold text-blue-900 mb-3">${vehicle.vehicle_type} - Calculated</div>
        
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
                <div class="flex justify-between text-xs text-gray-600">
                    <span>Additional (${deliveryResult.chargeableKm.toFixed(2)} km × ₱${parseFloat(vehicle.add_per_km).toFixed(2)}):</span>
                    <span>₱${deliveryResult.perKmCharge.toFixed(2)}</span>
                </div>
                <div class="flex justify-between font-bold text-blue-900 border-t border-blue-200 pt-2 mt-2">
                    <span>Total Delivery:</span>
                    <span>₱${deliveryResult.totalDeliveryCost.toFixed(2)}</span>
                </div>
            </div>
        </div>
    </div>`;
}

// ✅ SHOW ASSIGNED VEHICLE DETAILS
function showAssignedVehicleDetails(vehicle, vehicleAssignment) {
    const detailsContainer = document.getElementById('assignedVehicleDetails');
    const contentContainer = document.getElementById('vehicleDetailsContent');
    
    if (!detailsContainer || !contentContainer) return;
    
    const orderCubicM = vehicleAssignment.totalCubicMeters;
    const orderWeightKg = vehicleAssignment.totalWeightKg;
    const maxCubicM = parseFloat(vehicle.max_cubic_meter);
    const maxWeightKg = parseFloat(vehicle.max_weight_capacity);
    
    const volumePercentage = Math.min((orderCubicM / maxCubicM) * 100, 100);
    const weightPercentage = Math.min((orderWeightKg / maxWeightKg) * 100, 100);
    
    const getBarColor = (p) => p <= 50 ? 'bg-green-500' : p <= 75 ? 'bg-yellow-500' : p <= 90 ? 'bg-orange-500' : 'bg-red-500';
    
    contentContainer.innerHTML = `
    <div class="space-y-3">
        <div class="font-semibold text-gray-800">${vehicle.vehicle_type}</div>
        
        <div class="bg-white rounded-lg p-3 border">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium">Volume: ${volumePercentage.toFixed(1)}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="${getBarColor(volumePercentage)} h-3 rounded-full" style="width: ${volumePercentage}%"></div>
            </div>
            <div class="flex justify-between mt-1 text-xs text-gray-600">
                <span>${orderCubicM.toFixed(3)} m³</span>
                <span>of ${maxCubicM.toFixed(2)} m³</span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-3 border">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium">Weight: ${weightPercentage.toFixed(1)}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="${getBarColor(weightPercentage)} h-3 rounded-full" style="width: ${weightPercentage}%"></div>
            </div>
            <div class="flex justify-between mt-1 text-xs text-gray-600">
                <span>${orderWeightKg.toFixed(2)} kg</span>
                <span>of ${maxWeightKg.toFixed(2)} kg</span>
            </div>
        </div>
    </div>`;
    
    detailsContainer.classList.remove('hidden');
}

// ✅ SHOW PICKUP VEHICLE DETAILS
function showPickupVehicleDetails(vehicle, vehicleAssignment) {
    const detailsContainer = document.getElementById('assignedVehicleDetails');
    const contentContainer = document.getElementById('vehicleDetailsContent');
    
    if (!detailsContainer || !contentContainer) return;
    
    contentContainer.innerHTML = `
        <div class="text-sm text-gray-600">
            Vehicle: ${vehicle.vehicle_type}
        </div>`;
    
    detailsContainer.classList.remove('hidden');
}

// ✅ ROUTING DISTANCE CALCULATION
async function calculateRoutingDistance(storeLatLng, customerLatLng) {
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${storeLatLng.lng},${storeLatLng.lat};${customerLatLng.lng},${customerLatLng.lat}?overview=false`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (!response.ok) throw new Error('OSRM error');

        const data = await response.json();

        if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            return {
                distance: route.distance / 1000,
                time: Math.round(route.duration / 60),
                success: true
            };
        }
        throw new Error('No route found');
    } catch (error) {
        console.warn('OSRM failed, using fallback');
        const distance = calculateHaversineDistance(
            storeLatLng.lat, storeLatLng.lng,
            customerLatLng.lat, customerLatLng.lng
        );
        return { distance: distance, time: Math.round(distance * 3), fallback: true };
    }
}

// ✅ HAVERSINE DISTANCE (Fallback)
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

// ✅ UPDATE TOTALS
function updateTotalsDisplay(deliveryCost) {
    const totalsElement = document.getElementById('grandTotalDisplay');
    if (totalsElement) {
        totalsElement.textContent = `₱${(parseFloat(window.checkoutConfig?.totalPrice || 0) + deliveryCost).toFixed(2)}`;
    }
}

// ✅ EXPORT FUNCTIONS
window.assignTransportifyVehicleJS = assignTransportifyVehicleJS;
window.calculateTransportifyDeliveryCostJS = calculateTransportifyDeliveryCostJS;
window.initializeDeliveryTypeSelection = initializeDeliveryTypeSelection;
window.initializeDistanceCalculation = initializeDistanceCalculation;
window.updateDeliveryDisplay = updateDeliveryDisplay;
window.showAssignedVehicleDetails = showAssignedVehicleDetails;
window.showPickupVehicleDetails = showPickupVehicleDetails;

console.log('✅ distanceCalculation.js loaded successfully');