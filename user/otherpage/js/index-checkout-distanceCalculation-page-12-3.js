// distanceCalculation.js - Auto-calculate when delivery is selected


function showCapacityExceededModal(vehicleAssignment) {
    // ✅ PREVENT DOUBLE CALLS with a flag
    if (window.capacityModalShowing) {
    return;
}
    
    // ✅ Set flag IMMEDIATELY
    window.capacityModalShowing = true;
    
    // ✅ Remove any existing modal
    const existingModal = document.getElementById('capacityExceededModal');
if (existingModal) {
    existingModal.remove();
}
    
    // ✅ Add style ONLY ONCE
    if (!document.getElementById('capacityModalStyle')) {
        const style = document.createElement('style');
        style.id = 'capacityModalStyle';
        style.textContent = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            .animate-slideIn {
                animation: slideIn 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    }
    
    const details = vehicleAssignment.exceedanceDetails;
    const vehicle = vehicleAssignment.vehicle;
    
    const modal = document.createElement('div');
    modal.id = 'capacityExceededModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-60 z-[9999] flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-slideIn">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-6 rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <div class="bg-white bg-opacity-20 rounded-full p-3">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">Delivery Capacity Exceeded</h2>
                        <p class="text-red-100 text-sm mt-1">Your order is too large for our available vehicles</p>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-6">
                <!-- Problem Statement -->
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold text-red-900">Unable to Process Delivery</p>
                            <p class="text-sm text-red-800 mt-1">Your order exceeds the maximum capacity of our largest available delivery vehicle: <strong>${vehicle.vehicle_type}</strong></p>
                        </div>
                    </div>
                </div>

                <!-- Capacity Details -->
                <div class="bg-gray-50 rounded-lg p-5">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Capacity Comparison
                    </h3>
                    
                    <div class="space-y-4">
                        ${details.volumeExceeded ? `
                        <div class="bg-white rounded-lg p-4 border-2 border-red-200">
                            <div class="flex justify-between items-center mb-3">
                                <span class="font-semibold text-gray-700">📦 Volume (Cubic Meters)</span>
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">EXCEEDED</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Your Order:</span>
                                    <span class="font-bold text-red-600">${vehicleAssignment.totalCubicMeters.toFixed(3)} m³</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Vehicle Maximum:</span>
                                    <span class="font-semibold text-gray-800">${parseFloat(vehicle.max_cubic_meter).toFixed(3)} m³</span>
                                </div>
                                <div class="pt-2 border-t border-red-200">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-700 font-medium">Over Limit By:</span>
                                        <span class="font-bold text-red-700">${details.volumeOverage.toFixed(3)} m³ (${((details.volumeOverage / parseFloat(vehicle.max_cubic_meter)) * 100).toFixed(1)}%)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-red-600 h-3 rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${details.weightExceeded ? `
                        <div class="bg-white rounded-lg p-4 border-2 border-red-200">
                            <div class="flex justify-between items-center mb-3">
                                <span class="font-semibold text-gray-700">⚖️ Weight (Kilograms)</span>
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">EXCEEDED</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Your Order:</span>
                                    <span class="font-bold text-red-600">${vehicleAssignment.totalWeightKg.toFixed(2)} kg</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Vehicle Maximum:</span>
                                    <span class="font-semibold text-gray-800">${parseFloat(vehicle.max_weight_capacity).toFixed(2)} kg</span>
                                </div>
                                <div class="pt-2 border-t border-red-200">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-700 font-medium">Over Limit By:</span>
                                        <span class="font-bold text-red-700">${details.weightOverage.toFixed(2)} kg (${((details.weightOverage / parseFloat(vehicle.max_weight_capacity)) * 100).toFixed(1)}%)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-red-600 h-3 rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${!details.volumeExceeded && !details.weightExceeded ? `
                        <div class="text-center text-gray-500 py-4">
                            <p>Capacity within limits</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Solutions -->
                <div class="bg-blue-50 rounded-lg p-5 border border-blue-200">
                    <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        Available Solutions
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <div class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 font-bold text-xs">1</div>
                            <div>
                                <p class="font-semibold text-blue-900">Reduce Order Quantity</p>
                                <p class="text-blue-800">Go back to your cart and decrease the quantity of items to fit within vehicle capacity.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 font-bold text-xs">2</div>
                            <div>
                                <p class="font-semibold text-blue-900">Split Into Multiple Orders</p>
                                <p class="text-blue-800">Place multiple separate orders to stay within vehicle limits for each delivery.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 font-bold text-xs">3</div>
                            <div>
                                <p class="font-semibold text-blue-900">Contact Us for Bulk Delivery</p>
                                <p class="text-blue-800">For large orders, we can arrange special bulk delivery options.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-lg p-4 border border-orange-200">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 mb-1">Need Help?</p>
                            <p class="text-sm text-gray-700">Contact our support team for bulk order arrangements:</p>
                            <div class="mt-2 space-y-1 text-sm">
                                <p class="text-orange-700 font-medium">📧 Email: <a href="mailto:support@noblehome.com" class="underline hover:text-orange-800">support@noblehome.com</a></p>
                                <p class="text-orange-700 font-medium">📞 Phone: <a href="tel:+1234567890" class="underline hover:text-orange-800">+123 456 7890</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex gap-3 justify-end border-t">
                <a href="index-cart_view-page-8.php" class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium flex items-center gap-2 shadow-md hover:shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Go to Cart
                </a>
                <button onclick="closeCapacityModal()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCapacityModal();
        }
    });
}

// ✅ NEW: Separate close function that resets the flag
function closeCapacityModal() {
    const modal = document.getElementById('capacityExceededModal');
    if (modal) {
        modal.remove();
    }
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
                            <a href="index-cart_view-page-8.php" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-semibold rounded-lg hover:bg-orange-700 transition shadow-md">
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
                <span>of ${maxCubicM.toFixed(2)} m³</span>
            </div>
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