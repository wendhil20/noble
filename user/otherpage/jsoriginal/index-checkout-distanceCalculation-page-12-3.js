// distanceCalculation.js - Auto-calculate when delivery is selected


function showCapacityExceededModal(vehicleAssignment) {
    if (window.capacityModalShowing) return;
    window.capacityModalShowing = true;

    const existingModal = document.getElementById('capacityExceededModal');
    if (existingModal) existingModal.remove();

    const details = vehicleAssignment.exceedanceDetails;
    const vehicle = vehicleAssignment.vehicle;

    const modal = document.createElement('div');
    modal.id = 'capacityExceededModal';
    modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1rem;';

    modal.innerHTML = `
        <div style="background:var(--color-background-primary,#fff); border-radius:12px; border:0.5px solid rgba(0,0,0,0.12); width:100%; max-width:540px; max-height:90vh; overflow-y:auto;">
            
            <!-- Header -->
            <div style="background:#A32D2D; padding:1.25rem 1.5rem; display:flex; align-items:center; gap:12px; ">
                <div style="width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                   <i class="fa-solid fa-triangle-exclamation text-white"></i>
                </div>
                <div style="flex:1;">
                    <p style="color:#fff; font-size:15px; font-weight:500; margin:0;">Delivery capacity exceeded</p>
                    <p style="color:#F09595; font-size:12px; margin:0; margin-top:2px;">Order is too large for available vehicles</p>
                </div>
                <button onclick="closeCapacityModal()" style="background:rgba(255,255,255,0.15); border:none; border-radius:8px; padding:6px; cursor:pointer; display:flex; align-items:center;">
                    <svg width="16" height="16" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div style="padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:12px;">

                <!-- Alert -->
                <div style="background:#FCEBEB; border:0.5px solid #F7C1C1; border-left:3px solid #A32D2D; border-radius:0 8px 8px 0; padding:12px 14px; display:flex; gap:10px; align-items:flex-start;">
                    <svg width="16" height="16" fill="#A32D2D" viewBox="0 0 20 20" style="flex-shrink:0; margin-top:2px;">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p style="font-size:13px; font-weight:500; color:#791F1F; margin:0;">Unable to process delivery</p>
                        <p style="font-size:12px; color:#A32D2D; margin:4px 0 0;">Exceeds maximum capacity of largest vehicle: <strong>${vehicle.vehicle_type}</strong></p>
                    </div>
                </div>

                <!-- Capacity Comparison -->
                <div style="background:#f9f9f8; border-radius:8px; padding:14px;">
                    <p style="font-size:11px; font-weight:500; color:#888; margin:0 0 10px; text-transform:uppercase; letter-spacing:0.05em;">Capacity comparison</p>
                    <div style="display:flex; flex-direction:column; gap:10px;">

                        ${details.volumeExceeded ? `
                        <div style="background:#fff; border:0.5px solid #F7C1C1; border-radius:8px; padding:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span style="font-size:13px; font-weight:500; color:#333;">Volume (m³)</span>
                                <span style="font-size:11px; font-weight:500; background:#FCEBEB; color:#A32D2D; padding:2px 8px; border-radius:99px;">exceeded</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:12px; color:#666; margin-bottom:6px;">
                                <span>Your order: <strong style="color:#A32D2D;">${vehicleAssignment.totalCubicMeters.toFixed(3)} m³</strong></span>
                                <span>Max: ${parseFloat(vehicle.max_cubic_meter).toFixed(3)} m³</span>
                            </div>
                            <div style="height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden;">
                                <div style="height:100%; width:100%; background:#A32D2D; border-radius:99px;"></div>
                            </div>
                            <p style="font-size:11px; color:#A32D2D; margin:5px 0 0; text-align:right;">+${details.volumeOverage.toFixed(3)} m³ over limit (${((details.volumeOverage / parseFloat(vehicle.max_cubic_meter)) * 100).toFixed(1)}%)</p>
                        </div>
                        ` : ''}

                        ${details.weightExceeded ? `
                        <div style="background:#fff; border:0.5px solid #F7C1C1; border-radius:8px; padding:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span style="font-size:13px; font-weight:500; color:#333;">Weight (kg)</span>
                                <span style="font-size:11px; font-weight:500; background:#FCEBEB; color:#A32D2D; padding:2px 8px; border-radius:99px;">exceeded</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:12px; color:#666; margin-bottom:6px;">
                                <span>Your order: <strong style="color:#A32D2D;">${vehicleAssignment.totalWeightKg.toFixed(2)} kg</strong></span>
                                <span>Max: ${parseFloat(vehicle.max_weight_capacity).toFixed(2)} kg</span>
                            </div>
                            <div style="height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden;">
                                <div style="height:100%; width:100%; background:#A32D2D; border-radius:99px;"></div>
                            </div>
                            <p style="font-size:11px; color:#A32D2D; margin:5px 0 0; text-align:right;">+${details.weightOverage.toFixed(2)} kg over limit (${((details.weightOverage / parseFloat(vehicle.max_weight_capacity)) * 100).toFixed(1)}%)</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Solutions -->
                <div style="background:#f9f9f8; border-radius:8px; padding:14px;">
                    <p style="font-size:11px; font-weight:500; color:#888; margin:0 0 10px; text-transform:uppercase; letter-spacing:0.05em;">Available solutions</p>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        ${[
                            ['Reduce order quantity', 'Go back to cart and decrease item quantities.'],
                            ['Split into multiple orders', 'Place separate orders within vehicle limits.'],
                            ['Contact us for bulk delivery', 'We can arrange special options for large orders.']
                        ].map((item, i) => `
                        <div style="display:flex; gap:10px; align-items:flex-start;">
                            <div style="width:20px; height:20px; border-radius:50%; background:#185FA5; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:11px; font-weight:500; color:#fff;">${i+1}</div>
                            <div>
                                <p style="font-size:13px; font-weight:500; color:#222; margin:0;">${item[0]}</p>
                                <p style="font-size:12px; color:#666; margin:2px 0 0;">${item[1]}</p>
                            </div>
                        </div>`).join('')}
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div style="padding:12px 1.5rem; border-top:0.5px solid #eee; display:flex; gap:8px; justify-content:flex-end; border-radius:0 0 12px 12px;">
                 <a href="${window.BASE_URL || ''}/cartview" style="padding:8px 16px; font-size:13px; border-radius:8px; cursor:pointer; background:#d97706; color:#fff; border:none; text-decoration:none; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Modify cart
                </a>
                <button onclick="closeCapacityModal()" style="padding:8px 16px; font-size:13px; border-radius:8px; cursor:pointer; background:#f0f0f0; color:#444; border:0.5px solid #ddd;">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeCapacityModal();
    });

    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
            closeCapacityModal();
            document.removeEventListener('keydown', escHandler);
        }
    });
}

function closeCapacityModal() {
    const modal = document.getElementById('capacityExceededModal');
    if (modal) modal.remove();
    window.capacityModalShowing = false;
}

// ✅ INITIALIZATION: Ensure selectedAddress is set from session/config
(function initializeSelectedAddress() {
    
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
                break;
            }
        }
    }
    
    if (validAddress) {
        window.selectedAddress = validAddress;
    } else {
        console.warn('⚠️ No valid address found');
    }
    
    if (!window.deliverySettings && window.checkoutConfig?.deliverySettings) {
        window.deliverySettings = window.checkoutConfig.deliverySettings;
    }
})();

// ✅ DELIVERY TYPE SELECTION
function initializeDeliveryTypeSelection() {
    
    const deliveryRadios = document.querySelectorAll('input[name="delivery_type"]');
    const deliverySection = document.getElementById('deliveryCalculationSection');
    const pickupSection = document.getElementById('pickupInformationSection');
    const continueBtn = document.getElementById('continueToPayment');
    
    deliveryRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            
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
                    const calculateBtn = document.getElementById('calculateDistance');
                    if (calculateBtn && !calculateBtn.disabled) {
                        calculateBtn.click();
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
}

// ✅ MAIN DISTANCE CALCULATION HANDLER
function initializeDistanceCalculation() {
    
    const calculateDistanceBtn = document.getElementById('calculateDistance');
    const continueToPaymentBtn = document.getElementById('continueToPayment');

    if (calculateDistanceBtn) {
        calculateDistanceBtn.addEventListener('click', async function() {
            
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

                const routeData = await calculateRoutingDistance(storeLatLng, customerLatLng);
                const distance = routeData.distance || 0;
                

                const courierSelect = document.getElementById('courierSelection');
                const selectedCourier = courierSelect ? courierSelect.value : null;

                if (!selectedCourier) throw new Error('No courier selected');


                const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData, selectedCourier);

if (!vehicleAssignment || !vehicleAssignment.vehicle) throw new Error('Unable to assign vehicle');

// ✅ CHECK IF CAPACITY EXCEEDED
if (vehicleAssignment.exceededCapacity) {
    console.error('❌ Order exceeds vehicle capacity');
    
    // Store vehicleAssignment in window for button access
    window.currentVehicleAssignment = vehicleAssignment;
    
    // Show inline error
    const distanceResultElement = document.getElementById('distanceResult');
    if (distanceResultElement) {
        const details = vehicleAssignment.exceedanceDetails;
        
        distanceResultElement.innerHTML = `
            <div class="p-5 bg-gradient-to-r from-red-50 to-red-100 border-2 border-red-400 rounded-xl shadow-lg">
                <div class="flex items-start gap-4">
                    <div class="bg-red-600 rounded-full p-2 flex-shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-bold text-red-900 text-lg mb-2">⚠️ Delivery Blocked</div>
                        <div class="text-sm text-red-800 mb-3">
                            Your order exceeds our largest vehicle capacity and cannot proceed to payment.
                        </div>
                        <div class="bg-white bg-opacity-60 rounded-lg p-3 text-xs space-y-1 mb-3">
                            ${details.volumeExceeded ? `
                            <div class="flex justify-between">
                                <span class="text-gray-700">📦 Volume:</span>
                                <span class="font-bold text-red-700">${vehicleAssignment.totalCubicMeters.toFixed(3)}m³ / ${parseFloat(vehicleAssignment.vehicle.max_cubic_meter).toFixed(3)}m³</span>
                            </div>` : ''}
                            ${details.weightExceeded ? `
                            <div class="flex justify-between">
                                <span class="text-gray-700">⚖️ Weight:</span>
                                <span class="font-bold text-red-700">${vehicleAssignment.totalWeightKg.toFixed(2)}kg / ${parseFloat(vehicleAssignment.vehicle.max_weight_capacity).toFixed(2)}kg</span>
                            </div>` : ''}
                        </div>
                        <div class="flex gap-2">
                            <a href="${window.BASE_URL || ''}/cartview" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-semibold rounded-lg hover:bg-orange-700 transition shadow-md">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Modify Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    // ✅ Show modal ONCE immediately - no setTimeout
    showCapacityExceededModal(vehicleAssignment);
    
    // Keep continue button DISABLED
    if (continueToPaymentBtn) {
        continueToPaymentBtn.disabled = true;
        continueToPaymentBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        continueToPaymentBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
    }
    
    return; // ✅ STOP HERE - Don't proceed with calculation
}

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

// ✅ ADD DIMENSION FIELDS
const totalWidth = Math.max(...window.cartItemsData.map(item => parseFloat(item.width) || 0));
const totalHeight = Math.max(...window.cartItemsData.map(item => parseFloat(item.height) || 0));
const totalLength = Math.max(...window.cartItemsData.map(item => parseFloat(item.length) || 0));

document.getElementById('totalWidth').value = totalWidth.toFixed(2);
document.getElementById('totalHeight').value = totalHeight.toFixed(2);
document.getElementById('totalLength').value = totalLength.toFixed(2);

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


            } catch (error) {
                console.error('❌ Error:', error.message);
                alert('Error calculating delivery: ' + error.message);
            } finally {
                calculateDistanceBtn.textContent = originalText;
                calculateDistanceBtn.disabled = false;
            }
        });
        
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
                if (calculateBtn) {
                    calculateBtn.disabled = false;
                    calculateBtn.classList.remove('bg-gray-400');
                    calculateBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                }
            }
        });
    }
}

// ✅ HELPER FUNCTIONS
function calculateCubicMetersJS(width, height, length, unit, quantity = 1) {
    // Return 0 if no dimensions provided
    if (!width || !height || !length) {
        return 0;
    }
    
    // Conversion factors to meters (matching database ENUM)
    const meters = {
        'mm': 0.001,      // millimeters to meters
        'cm': 0.01,       // centimeters to meters
        'inches': 0.0254, // inches to meters
        'm': 1            // meters (no conversion)
    };
    
    // Get multiplier, default to 0 if unit not found
    const multiplier = meters[unit.toLowerCase()] || 0;
    
    // If multiplier is 0 (invalid unit or no data), return 0
    if (multiplier === 0) {
        return 0;
    }
    
    return (width * multiplier) * (height * multiplier) * (length * multiplier) * quantity;
}

function convertToKilogramsJS(weight, unit, quantity = 1) {
    // Return 0 if no weight provided
    if (!weight) {
        return 0;
    }
    
    // Conversion factors to kilograms (matching database ENUM)
    const kgConversion = {
        'g': 0.001,      // grams to kg
        'kg': 1,         // kg (no conversion)
        'lbs': 0.453592, // pounds to kg
        'oz': 0.0283495  // ounces to kg
    };
    
    // Get multiplier, default to 0 if unit not found
    const multiplier = kgConversion[unit.toLowerCase()] || 0;
    
    // If multiplier is 0 (invalid unit or no data), return 0
    if (multiplier === 0) {
        return 0;
    }
    
    return (weight * multiplier) * quantity;
}

// ✅ VEHICLE ASSIGNMENT
function assignTransportifyVehicleJS(cartItems, selectedCourier = null) {
    
    let totalCubicMeters = 0;
    let totalWeightKg = 0;
    
    cartItems.forEach((item, index) => {
        // Get dimensions - use 0 if not provided (no fallback sizes)
        let width = parseFloat(item.width) || 0;
        let height = parseFloat(item.height) || 0;
        let length = parseFloat(item.length) || 0;
        let weight = parseFloat(item.weight) || 0;
        
        // Get units - use database defaults if not provided
        const dimensionUnit = item.dimension_unit || 'cm';
        const weightUnit = item.weight_unit || 'kg';
        const quantity = parseInt(item.quantity) || 1;
        
        // Calculate and add to totals (will be 0 if no data)
        const itemCubicM = calculateCubicMetersJS(width, height, length, dimensionUnit, quantity);
        const itemWeightKg = convertToKilogramsJS(weight, weightUnit, quantity);
        
        totalCubicMeters += itemCubicM;
        totalWeightKg += itemWeightKg;
        
        // Debug log for items with no dimensions
        if (itemCubicM === 0 && itemWeightKg === 0) {
            console.log(`⚠️ Item ${index + 1} has no dimensions/weight:`, item.variant_name || item.product_name);
        }
    });
    
    
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
    // Check if even the largest vehicle can't handle this order
    const largestVehicle = availableVehicles[availableVehicles.length - 1];
    const maxCubicM = parseFloat(largestVehicle.max_cubic_meter) || 0;
    const maxWeightKg = parseFloat(largestVehicle.max_weight_capacity) || 0;
    
    return {
        vehicle: largestVehicle,
        totalCubicMeters: totalCubicMeters,
        totalWeightKg: totalWeightKg,
        courierName: largestVehicle.courier_name,
        exceededCapacity: true, // ✅ NEW FLAG
        exceedanceDetails: {
            volumeExceeded: totalCubicMeters > maxCubicM,
            weightExceeded: totalWeightKg > maxWeightKg,
            volumeOverage: Math.max(0, totalCubicMeters - maxCubicM),
            weightOverage: Math.max(0, totalWeightKg - maxWeightKg)
        }
    };
}


return {
    vehicle: assignedVehicle,
    totalCubicMeters: totalCubicMeters,
    totalWeightKg: totalWeightKg,
    courierName: assignedVehicle.courier_name,
    exceededCapacity: false // ✅ NEW FLAG
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
    
    // ✅ CHECK FOR OVER-CAPACITY
    const volumeExceeded = orderCubicM > maxCubicM;
    const weightExceeded = orderWeightKg > maxWeightKg;
    
    const getBarColor = (p, exceeded) => {
        if (exceeded) return 'bg-red-600';
        if (p <= 50) return 'bg-green-500';
        if (p <= 75) return 'bg-yellow-500';
        if (p <= 90) return 'bg-orange-500';
        return 'bg-red-500';
    };
    
    contentContainer.innerHTML = `
    <div class="space-y-3">
        ${volumeExceeded || weightExceeded ? `
        <div class="bg-red-50 border-2 border-red-300 rounded-lg p-3 mb-3">
            <div class="flex items-center gap-2 text-red-900 font-bold mb-1">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                CAPACITY EXCEEDED
            </div>
            <div class="text-xs text-red-800">This order cannot be delivered. Please reduce quantity.</div>
        </div>
        ` : ''}
        
        <div class="font-semibold text-gray-800">${vehicle.vehicle_type}</div>
        
        <div class="bg-white rounded-lg p-3 border ${volumeExceeded ? 'border-red-300' : ''}">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium ${volumeExceeded ? 'text-red-700' : ''}">Volume: ${Math.min(volumePercentage, 999).toFixed(1)}%</span>
                ${volumeExceeded ? '<span class="text-xs text-red-600 font-bold">OVER LIMIT</span>' : ''}
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="${getBarColor(volumePercentage, volumeExceeded)} h-3 rounded-full" style="width: ${Math.min(volumePercentage, 100)}%"></div>
            </div>
            <div class="flex justify-between mt-1 text-xs ${volumeExceeded ? 'text-red-700 font-semibold' : 'text-gray-600'}">
    <span>${orderCubicM.toFixed(3)} m³</span>
    <span>of ${maxCubicM.toFixed(3)} m³</span>
</div>
${orderCubicM === 0 ? '<div class="text-xs text-orange-600 mt-1">⚠️ No dimension data provided</div>' : ''}
        </div>
        
        <div class="bg-white rounded-lg p-3 border ${weightExceeded ? 'border-red-300' : ''}">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium ${weightExceeded ? 'text-red-700' : ''}">Weight: ${Math.min(weightPercentage, 999).toFixed(1)}%</span>
                ${weightExceeded ? '<span class="text-xs text-red-600 font-bold">OVER LIMIT</span>' : ''}
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="${getBarColor(weightPercentage, weightExceeded)} h-3 rounded-full" style="width: ${Math.min(weightPercentage, 100)}%"></div>
            </div>
            <div class="flex justify-between mt-1 text-xs ${weightExceeded ? 'text-red-700 font-semibold' : 'text-gray-600'}">
    <span>${orderWeightKg.toFixed(2)} kg</span>
    <span>of ${maxWeightKg.toFixed(2)} kg</span>
</div>
${orderWeightKg === 0 ? '<div class="text-xs text-orange-600 mt-1">⚠️ No weight data provided</div>' : ''}
        </div>
    </div>`;
    
    detailsContainer.classList.remove('hidden');
    
    // ✅ Change container color if exceeded
    if (volumeExceeded || weightExceeded) {
        detailsContainer.classList.remove('bg-green-50');
        detailsContainer.classList.add('bg-red-50', 'border-red-300');
    }
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
window.showCapacityExceededModal = showCapacityExceededModal; // ✅ ADD THIS LINE
window.closeCapacityModal = closeCapacityModal; // ✅ ADD THIS LINE TOO
window.initializeDeliveryTypeSelection = initializeDeliveryTypeSelection;
window.initializeDistanceCalculation = initializeDistanceCalculation;
window.updateDeliveryDisplay = updateDeliveryDisplay;
window.showAssignedVehicleDetails = showAssignedVehicleDetails;
window.showPickupVehicleDetails = showPickupVehicleDetails;