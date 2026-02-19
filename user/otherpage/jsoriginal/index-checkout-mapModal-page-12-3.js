// mapModal.js - Map modal and visualization

function initializeMapModal() {
    const showMapBtn = document.getElementById('showMapModal');

    if (showMapBtn) {
        showMapBtn.addEventListener('click', function() {
            if (!selectedAddress || !deliverySettings) {
                showNotification('Please select an address first.', 'error');
                return;
            }

            // Prepare store data
            const storeData = {
                name: deliverySettings.location_name,
                latitude: parseFloat(deliverySettings.latitude),
                longitude: parseFloat(deliverySettings.longitude)
            };

            // Prepare customer data
            const customerData = {
                address: selectedAddress.address,
                latitude: selectedAddress.latitude,
                longitude: selectedAddress.longitude
            };

            // Create and show the map modal
            createMapModal(storeData, customerData, deliverySettings);
        });
    }
}

// Function to create and display the map modal with fixed layout
function createMapModal(storeData, customerData, deliverySettings) {
    // Remove existing modal if any
    const existingModal = document.getElementById('deliveryMapModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal HTML with improved layout
    const modalHTML = `
        <div id="deliveryMapModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" style="backdrop-filter: blur(4px);">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-orange-600 to-orange-700 text-white p-6 flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center gap-3">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Delivery Route Map
                            </h2>
                            <p class="text-orange-100 mt-1">View your delivery route and distance calculation</p>
                        </div>
                        <button onclick="closeDeliveryMapModal()" class="text-orange-200 hover:text-white transition-colors p-2 rounded-lg hover:bg-orange-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Content - Scrollable -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Route Information Panel -->
                    <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                        <div class="grid md:grid-cols-3 gap-6">
                            <!-- Store Location -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-2-13v6h0m-8-6v6h0M5 21h14"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-900">Store Location</h3>
                                </div>
                                <div class="space-y-2">
                                    <div class="text-sm text-gray-600" id="storeLocationName">${storeData.name}</div>
                                    <div class="text-xs text-gray-500" id="storeCoordinates">${storeData.latitude}, ${storeData.longitude}</div>
                                </div>
                            </div>

                            <!-- Delivery Address -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1v4a1 1 0 001 1m-6 0h6"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-900">Your Address</h3>
                                </div>

                                <div class="space-y-2">
                                    <div class="text-sm text-gray-600" id="customerAddress">${customerData.address}</div>
                                    <div class="text-xs text-gray-500" id="customerCoordinates">${customerData.latitude}, ${customerData.longitude}</div>
                                </div>
                            </div>

                            <!-- Route Summary -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-200">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-900">Route Info</h3>
                                </div>
                                <div class="space-y-2" id="routeInfoDisplay">
                                    <div class="text-sm text-gray-600">Calculating route...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div class="p-6">
                        <div class="bg-gray-100 rounded-lg overflow-hidden shadow-inner">
                            <div id="deliveryMapContainer" style="height: 500px; width: 100%;"></div>
                        </div>
                        
                        <!-- Map Controls -->
                        <div class="mt-4 flex justify-between items-center">
                            <div class="flex gap-2">
                                <button onclick="centerMapOnStore()" class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-2-13v6h0m-8-6v6h0M5 21h14"></path>
                                    </svg>
                                    Store
                                </button>
                                <button onclick="centerMapOnCustomer()" class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1v4a1 1 0 001 1m-6 0h6"></path>
                                    </svg>
                                    Your Address
                                </button>
                                <button onclick="fitMapToRoute()" class="bg-purple-600 text-white px-3 py-2 rounded text-sm hover:bg-purple-700 transition flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                                    </svg>
                                    Fit Route
                                </button>
                            </div>
                            <button onclick="closeDeliveryMapModal()" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition font-medium">
                                Close Map
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to page
    document.body.appendChild(document.createElement('div')).innerHTML = modalHTML;
    const modal = document.getElementById('deliveryMapModal');

    // Initialize the map
    setTimeout(() => {
        initializeDeliveryMap(storeData, customerData, deliverySettings);
    }, 100);
}

// Function to initialize the delivery map
async function initializeDeliveryMap(storeData, customerData, deliverySettings) {
    const mapContainer = document.getElementById('deliveryMapContainer');

    if (!mapContainer) return;

    // Create the map
    deliveryMap = L.map('deliveryMapContainer').setView([storeData.latitude, storeData.longitude], 13);

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(deliveryMap);

    // Store marker (green)
    storeMarker = L.marker([storeData.latitude, storeData.longitude], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: #10b981; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
                </svg>
            </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        })
    }).addTo(deliveryMap);

    storeMarker.bindPopup(`
        <div class="text-center">
            <div class="font-bold text-green-700">${storeData.name}</div>
            <div class="text-sm text-gray-600 mt-1">Store Location</div>
        </div>
    `);

    // Customer marker (blue)
    customerMarker = L.marker([customerData.latitude, customerData.longitude], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: #3b82f6; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                </svg>
            </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        })
    }).addTo(deliveryMap);

    customerMarker.bindPopup(`
        <div class="text-center">
            <div class="font-bold text-blue-700">Your Address</div>
            <div class="text-sm text-gray-600 mt-1">${customerData.address}</div>
        </div>
    `);

    // Calculate and display route
    try {
        const routeData = await calculateRoutingDistance({
            lat: storeData.latitude,
            lng: storeData.longitude
        }, {
            lat: customerData.latitude,
            lng: customerData.longitude
        });

        currentRouteData = routeData;

        // Update route info display
        const routeInfoDisplay = document.getElementById('routeInfoDisplay');
        if (routeInfoDisplay) {
            const fallbackNote = routeData.fallback ? '<div class="text-xs text-orange-600 mt-1">* Distance calculated using straight-line method</div>' : '';

            routeInfoDisplay.innerHTML = `
                <div class="text-sm">
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Distance:</span>
                        <span class="font-medium text-gray-800">${routeData.distance.toFixed(2)} km</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Est. Time:</span>
                        <span class="font-medium text-gray-800">${routeData.time} minutes</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Route Status:</span>
                        <span class="font-medium ${routeData.fallback ? 'text-orange-600' : 'text-green-600'}">
                            ${routeData.fallback ? 'Estimated' : 'Calculated'}
                        </span>
                    </div>
                    ${fallbackNote}
                </div>
            `;
        }

        // Add route to map if not using fallback
        if (!routeData.fallback && typeof L.Routing !== 'undefined') {
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(storeData.latitude, storeData.longitude),
                    L.latLng(customerData.latitude, customerData.longitude)
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                createMarker: function() {
                    return null;
                }, // Don't create default markers
                lineOptions: {
                    styles: [{
                        color: '#f97316',
                        weight: 6,
                        opacity: 0.8
                    }]
                },
                show: false // Hide the directions panel
            }).addTo(deliveryMap);
        } else {
            // Draw straight line if routing failed
            const straightLine = L.polyline([
                [storeData.latitude, storeData.longitude],
                [customerData.latitude, customerData.longitude]
            ], {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 5'
            }).addTo(deliveryMap);
        }

        // Fit map to show both markers
        fitMapToRoute();

    } catch (error) {
        console.error('Error displaying route on map:', error);

        const routeInfoDisplay = document.getElementById('routeInfoDisplay');
        if (routeInfoDisplay) {
            routeInfoDisplay.innerHTML = `
                <div class="text-sm text-red-600">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Unable to calculate precise route
                    </div>
                    <div class="text-xs mt-1">Distance will be estimated during checkout</div>
                </div>
            `;
        }

        // Still fit map to show both markers
        fitMapToRoute();
    }
}

// Map control functions
function centerMapOnStore() {
    if (deliveryMap && storeMarker) {
        deliveryMap.setView(storeMarker.getLatLng(), 15);
        storeMarker.openPopup();
    }
}

function centerMapOnCustomer() {
    if (deliveryMap && customerMarker) {
        deliveryMap.setView(customerMarker.getLatLng(), 15);
        customerMarker.openPopup();
    }
}

function fitMapToRoute() {
    if (deliveryMap && storeMarker && customerMarker) {
        const group = new L.featureGroup([storeMarker, customerMarker]);
        deliveryMap.fitBounds(group.getBounds().pad(0.1));
    }
}

// Function to close the map modal
function closeDeliveryMapModal() {
    const modal = document.getElementById('deliveryMapModal');
    if (modal) {
        modal.remove();
    }

    // Clean up map resources
    if (deliveryMap) {
        deliveryMap.remove();
        deliveryMap = null;
    }
    if (routingControl) {
        routingControl = null;
    }
    storeMarker = null;
    customerMarker = null;
    currentRouteData = null;
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('deliveryMapModal');
    if (modal && event.target === modal) {
        closeDeliveryMapModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('deliveryMapModal');
        if (modal) {
            closeDeliveryMapModal();
        }
    }
});