<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8' />
    <title>Mapbox Search - Coordinates Finder</title>
    <meta name='viewport' content='initial-scale=1,maximum-scale=1,user-scalable=no' />
    <script src='https://api.mapbox.com/mapbox-gl-js/v3.0.0/mapbox-gl.js'></script>
    <link href='https://api.mapbox.com/mapbox-gl-js/v3.0.0/mapbox-gl.css' rel='stylesheet' />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        #map {
            cursor: crosshair !important;
        }
        #map:active {
            cursor: pointer !important;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex h-screen">
    <!-- Map -->
    <div id="map" class="flex-1 relative"></div>
    
    <!-- Sidebar -->
    <div class="w-96 bg-white overflow-y-auto shadow-2xl z-10">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6 text-white sticky top-0 shadow-lg">
            <h2 class="text-2xl font-bold flex items-center gap-2">🔍 Search Address</h2>
            <p class="text-blue-100 text-sm mt-1">Find coordinates for any location</p>
        </div>
        
        <!-- Clickable Map Tip -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mx-6 mt-6 rounded-lg">
            <p class="text-blue-900 text-sm font-semibold">💡 Tip: Click anywhere on the map to auto-fill coordinates!</p>
            <p class="text-blue-700 text-xs mt-1">Or use the search box below to find locations</p>
        </div>
        
        <div class="p-6 space-y-6">
            <!-- Search Box with Autocomplete -->
            <div class="relative">
                <div class="flex gap-2">
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search address or place..." 
                        class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                        oninput="handleSearchInput()"
                    />
                    <button 
                        onclick="searchAddress()" 
                        class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition transform hover:scale-105 active:scale-95"
                    >
                        Go
                    </button>
                </div>
                
                <!-- Autocomplete Suggestions Dropdown -->
                <div id="suggestions" class="absolute top-full left-0 right-0 mt-2 bg-white border-2 border-gray-200 rounded-lg shadow-2xl max-h-64 overflow-y-auto z-50 hidden" style="display:none;">
                    <div id="suggestionsList"></div>
                </div>
            </div>
            
            <!-- Message Alert -->
            <div id="message" class="hidden p-3 rounded-lg text-sm font-medium animate-fade-in"></div>
            
            <!-- Coordinates Info Card -->
            <div id="coordinatesInfo" class="hidden bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-400 rounded-lg p-4 shadow-lg">
                <h3 class="text-blue-700 font-bold mb-4 flex items-center gap-2 text-lg">📍 Coordinates</h3>
                
                <div class="space-y-3">
                    <!-- Place Name -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-blue-500">
                        <label class="text-gray-600 font-semibold text-sm block">Place Name</label>
                        <p id="placeName" class="font-mono text-blue-600 font-bold break-all text-sm mt-1">-</p>
                    </div>
                    
                    <!-- Longitude -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-green-500">
                        <label class="text-gray-600 font-semibold text-sm block">Longitude (X)</label>
                        <div class="flex items-center justify-between mt-1">
                            <p id="longitude" class="font-mono text-green-600 font-bold text-sm flex-1">-</p>
                            <button onclick="copyToClipboard('longitude')" class="ml-2 px-3 py-1 bg-green-500 text-white text-xs font-bold rounded hover:bg-green-600 transition">Copy</button>
                        </div>
                    </div>
                    
                    <!-- Latitude -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-orange-500">
                        <label class="text-gray-600 font-semibold text-sm block">Latitude (Y)</label>
                        <div class="flex items-center justify-between mt-1">
                            <p id="latitude" class="font-mono text-orange-600 font-bold text-sm flex-1">-</p>
                            <button onclick="copyToClipboard('latitude')" class="ml-2 px-3 py-1 bg-green-500 text-white text-xs font-bold rounded hover:bg-green-600 transition">Copy</button>
                        </div>
                    </div>
                    
                    <!-- Full Coordinates -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-purple-500">
                        <label class="text-gray-600 font-semibold text-sm block">Full Coordinates</label>
                        <div class="flex items-center justify-between mt-1">
                            <p id="fullCoords" class="font-mono text-purple-600 font-bold text-xs break-all flex-1">-</p>
                            <button onclick="copyToClipboard('fullCoords')" class="ml-2 px-3 py-1 bg-green-500 text-white text-xs font-bold rounded hover:bg-green-600 transition whitespace-nowrap">Copy</button>
                        </div>
                    </div>
                    
                    <!-- Accuracy/Type -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-indigo-500">
                        <label class="text-gray-600 font-semibold text-sm block">Accuracy/Type</label>
                        <p id="accuracy" class="font-mono text-indigo-600 font-bold text-sm mt-1">-</p>
                    </div>
                </div>
            </div>
            
            <!-- Orders List -->
            <div class="mt-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">📦 Sample Orders</h3>
                <div id="ordersList" class="space-y-2 max-h-64 overflow-y-auto"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // ⚠️ REPLACE WITH YOUR NEW MAPBOX TOKEN (REGENERATE THIS!)
    mapboxgl.accessToken = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';
    
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [120.9742, 14.5533],
        zoom: 12
    });
    
    let currentMarker = null;
    let suggestionsTimeout;
    
    // Add instruction overlay on map
    map.on('load', () => {
        const mapElement = document.getElementById('map');
        const instruction = document.createElement('div');
        instruction.className = 'absolute top-4 left-4 bg-white px-4 py-2 rounded-lg shadow-lg text-sm font-semibold text-gray-700 flex items-center gap-2 z-20 animate-pulse';
        instruction.innerHTML = '👆 Click on map to select location';
        mapElement.appendChild(instruction);
        
        // Remove after 5 seconds
        setTimeout(() => {
            instruction.style.opacity = '0.5';
        }, 5000);
    });
    
    // Handle map clicks to auto-fill form
    map.on('click', async (e) => {
        const lng = e.lngLat.lng;
        const lat = e.lngLat.lat;
        
        showMessage('Getting location details...', '');
        
        try {
            // Reverse geocoding - get address from coordinates
            const response = await fetch(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${mapboxgl.accessToken}`
            );
            
            const data = await response.json();
            
            if (data.features && data.features.length > 0) {
                const feature = data.features[0];
                const placeName = feature.place_name;
                const relevance = Math.round(feature.relevance * 100);
                const placeType = feature.place_type[0];
                
                // Auto-fill the form
                document.getElementById('searchInput').value = placeName;
                document.getElementById('placeName').textContent = placeName;
                document.getElementById('longitude').textContent = lng.toFixed(8);
                document.getElementById('latitude').textContent = lat.toFixed(8);
                document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
                document.getElementById('accuracy').textContent = `${placeType.toUpperCase()} (${relevance}% match) - Map Click`;
                document.getElementById('coordinatesInfo').classList.remove('hidden');
                document.getElementById('suggestions').style.display = 'none';
                
                // Add marker
                addMarker(lng, lat, placeName);
                
                showMessage('✅ Location selected! Coordinates auto-filled.', 'success');
            } else {
                // If no address found, still show coordinates
                document.getElementById('placeName').textContent = 'Location (No address found)';
                document.getElementById('longitude').textContent = lng.toFixed(8);
                document.getElementById('latitude').textContent = lat.toFixed(8);
                document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
                document.getElementById('accuracy').textContent = 'Map Click - Unknown Location';
                document.getElementById('coordinatesInfo').classList.remove('hidden');
                
                addMarker(lng, lat, `${lat.toFixed(4)}, ${lng.toFixed(4)}`);
                showMessage('✅ Coordinates captured (address not found)', 'success');
            }
            
        } catch (error) {
            console.error('Reverse geocoding error:', error);
            
            // Still show coordinates even if reverse geocoding fails
            document.getElementById('placeName').textContent = 'Location (Click)';
            document.getElementById('longitude').textContent = lng.toFixed(8);
            document.getElementById('latitude').textContent = lat.toFixed(8);
            document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
            document.getElementById('accuracy').textContent = 'Map Click - Offline Mode';
            document.getElementById('coordinatesInfo').classList.remove('hidden');
            
            addMarker(lng, lat, 'Clicked Location');
            showMessage('✅ Coordinates captured (from map click)', 'success');
        }
    });
    let isClickingMap = false;
    
    // Map click event - auto fill coordinates
    map.on('click', async (e) => {
        const lng = e.lngLat.lng;
        const lat = e.lngLat.lat;
        
        showMessage('📍 Getting location details...', '');
        
        try {
            // Reverse geocoding - get address from coordinates
            const response = await fetch(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${mapboxgl.accessToken}`
            );
            
            const data = await response.json();
            let placeName = 'Unknown Location';
            
            if (data.features && data.features.length > 0) {
                placeName = data.features[0].place_name;
            }
            
            // Auto-fill the form
            document.getElementById('searchInput').value = placeName;
            document.getElementById('placeName').textContent = placeName;
            document.getElementById('longitude').textContent = lng.toFixed(8);
            document.getElementById('latitude').textContent = lat.toFixed(8);
            document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
            document.getElementById('accuracy').textContent = '📍 Clicked Location';
            document.getElementById('coordinatesInfo').classList.remove('hidden');
            document.getElementById('suggestions').style.display = 'none';
            
            // Add marker
            addMarker(lng, lat, placeName);
            
            // Center map
            map.flyTo({ center: [lng, lat], zoom: 15, duration: 1000 });
            
            showMessage('✅ Location selected! Click anywhere on the map to select another location.', 'success');
            
        } catch (error) {
            console.error('Reverse geocoding error:', error);
            // Still fill coordinates even if address lookup fails
            document.getElementById('searchInput').value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            document.getElementById('placeName').textContent = 'Coordinates Location';
            document.getElementById('longitude').textContent = lng.toFixed(8);
            document.getElementById('latitude').textContent = lat.toFixed(8);
            document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
            document.getElementById('accuracy').textContent = '📍 Clicked Location';
            document.getElementById('coordinatesInfo').classList.remove('hidden');
            addMarker(lng, lat, 'Selected Location');
            showMessage('✅ Location coordinates saved!', 'success');
        }
    });
    
    // Change cursor on map hover
    map.on('mouseenter', 'places-label', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'places-label', () => {
        map.getCanvas().style.cursor = 'grab';
    });
    map.getCanvas().style.cursor = 'crosshair';
    
    // Sample orders data
    const orders = [
        { id: 1, address: 'SM Mall of Asia', lng: 120.9742, lat: 14.5533, status: 'In Transit' },
        { id: 2, address: 'Robinsons Manila', lng: 120.9850, lat: 14.5950, status: 'Delivered' },
        { id: 3, address: 'Makati CBD', lng: 121.0055, lat: 14.5549, status: 'Pending' },
        { id: 4, address: 'Ayala Center Cebu', lng: 123.8854, lat: 10.3157, status: 'In Transit' },
        { id: 5, address: 'SM Lanang Davao', lng: 125.5892, lat: 7.0755, status: 'Delivered' },
    ];
    
    // Display orders list
    function displayOrders() {
        const ordersList = document.getElementById('ordersList');
        ordersList.innerHTML = '';
        orders.forEach(order => {
            const statusConfig = {
                'In Transit': { colors: 'from-yellow-100 to-yellow-50 border-l-yellow-500', badge: 'bg-yellow-300 text-yellow-900' },
                'Delivered': { colors: 'from-green-100 to-green-50 border-l-green-500', badge: 'bg-green-300 text-green-900' },
                'Pending': { colors: 'from-red-100 to-red-50 border-l-red-500', badge: 'bg-red-300 text-red-900' }
            };
            
            const config = statusConfig[order.status];
            const div = document.createElement('div');
            div.className = `bg-gradient-to-r ${config.colors} border-l-4 p-3 rounded-lg cursor-pointer hover:shadow-lg transition transform hover:scale-105 active:scale-95`;
            div.innerHTML = `
                <strong class="text-blue-700 text-sm block">#${order.id} - ${order.address}</strong>
                <div class="text-gray-600 text-xs mt-1">📍 ${order.lng.toFixed(4)}, ${order.lat.toFixed(4)}</div>
                <span class="inline-block mt-2 text-xs font-bold px-2 py-1 rounded ${config.badge}">${order.status}</span>
            `;
            div.onclick = () => selectOrder(order);
            ordersList.appendChild(div);
        });
    }
    
    // Handle real-time search input (autocomplete)
    async function handleSearchInput() {
        const query = document.getElementById('searchInput').value.trim();
        clearTimeout(suggestionsTimeout);
        
        if (!query || query.length < 2) {
            document.getElementById('suggestions').style.display = 'none';
            return;
        }
        
        suggestionsTimeout = setTimeout(() => {
            fetchSuggestions(query);
        }, 300);
    }
    
    // Fetch autocomplete suggestions
    async function fetchSuggestions(query) {
        try {
            const response = await fetch(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${mapboxgl.accessToken}&proximity=120.9742,14.5533&country=ph&limit=8`
            );
            
            const data = await response.json();
            
            if (!data.features || data.features.length === 0) {
                document.getElementById('suggestions').style.display = 'none';
                return;
            }
            
            displaySuggestions(data.features);
            
        } catch (error) {
            console.error('Suggestions error:', error);
        }
    }
    
    // Display suggestions dropdown
    function displaySuggestions(features) {
        const suggestionsList = document.getElementById('suggestionsList');
        suggestionsList.innerHTML = '';
        document.getElementById('suggestions').style.display = 'block';
        
        features.forEach((feature) => {
            const div = document.createElement('div');
            div.className = 'px-4 py-3 cursor-pointer hover:bg-blue-50 border-b last:border-b-0 transition flex items-center gap-3 text-sm animate-fade-in';
            
            const icon = getPlaceIcon(feature.place_type[0]);
            const relevance = Math.round(feature.relevance * 100);
            
            div.innerHTML = `
                <span class="text-xl">${icon}</span>
                <div class="flex-1">
                    <div class="font-semibold text-gray-800">${feature.place_name}</div>
                    <div class="text-xs text-gray-500">Match: ${relevance}%</div>
                </div>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">${feature.place_type[0]}</span>
            `;
            
            div.onclick = () => selectSearchResult(feature);
            suggestionsList.appendChild(div);
        });
    }
    
    // Get icon based on place type
    function getPlaceIcon(placeType) {
        const icons = {
            'address': '🏠',
            'place': '🏪',
            'poi': '🎯',
            'region': '🌍',
            'country': '🗺️'
        };
        return icons[placeType] || '📍';
    }
    
    // Select order
    function selectOrder(order) {
        document.getElementById('placeName').textContent = `Order #${order.id} - ${order.address}`;
        document.getElementById('longitude').textContent = order.lng.toFixed(8);
        document.getElementById('latitude').textContent = order.lat.toFixed(8);
        document.getElementById('fullCoords').textContent = `${order.lng.toFixed(8)}, ${order.lat.toFixed(8)}`;
        document.getElementById('accuracy').textContent = 'Sample Order Data';
        document.getElementById('coordinatesInfo').classList.remove('hidden');
        
        map.flyTo({ center: [order.lng, order.lat], zoom: 15, duration: 1000 });
        addMarker(order.lng, order.lat, `Order #${order.id}`);
    }
    
    // Search address (full search)
    async function searchAddress() {
        const query = document.getElementById('searchInput').value.trim();
        
        if (!query) {
            showMessage('Please enter an address', 'error');
            return;
        }
        
        showMessage('Searching...', '');
        document.getElementById('suggestions').style.display = 'none';
        
        try {
            const response = await fetch(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${mapboxgl.accessToken}&proximity=120.9742,14.5533&country=ph`
            );
            
            const data = await response.json();
            
            if (!data.features || data.features.length === 0) {
                showMessage('No results found. Try a different address.', 'error');
                return;
            }
            
            selectSearchResult(data.features[0]);
            showMessage('✅ Address found! Coordinates displayed.', 'success');
            
        } catch (error) {
            console.error('Search error:', error);
            showMessage('Error searching address', 'error');
        }
    }
    
    // Select search result
    function selectSearchResult(feature) {
        const coords = feature.geometry.coordinates;
        const lng = coords[0];
        const lat = coords[1];
        const relevance = Math.round(feature.relevance * 100);
        const placeType = feature.place_type[0];
        
        document.getElementById('placeName').textContent = feature.place_name;
        document.getElementById('longitude').textContent = lng.toFixed(8);
        document.getElementById('latitude').textContent = lat.toFixed(8);
        document.getElementById('fullCoords').textContent = `${lng.toFixed(8)}, ${lat.toFixed(8)}`;
        document.getElementById('accuracy').textContent = `${placeType.toUpperCase()} (${relevance}% match)`;
        document.getElementById('coordinatesInfo').classList.remove('hidden');
        document.getElementById('suggestions').style.display = 'none';
        document.getElementById('searchInput').value = feature.place_name;
        
        map.flyTo({ center: [lng, lat], zoom: 15, duration: 1000 });
        addMarker(lng, lat, feature.place_name);
    }
    
    // Add marker on map
    function addMarker(lng, lat, title) {
        if (currentMarker) currentMarker.remove();
        
        const popup = new mapboxgl.Popup()
            .setHTML(`
                <div class="font-semibold text-gray-800">${title}</div>
                <div class="text-xs text-gray-600 mt-1">📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
            `);
        
        currentMarker = new mapboxgl.Marker({ color: '#EF4444' })
            .setLngLat([lng, lat])
            .setPopup(popup)
            .addTo(map);
        
        popup.addTo(map);
    }
    
    // Copy to clipboard
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✓ Copied!';
            btn.classList.add('bg-green-600');
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('bg-green-600');
            }, 2000);
        });
    }
    
    // Show message
    function showMessage(msg, type) {
        const messageDiv = document.getElementById('message');
        messageDiv.textContent = msg;
        messageDiv.classList.remove('hidden', 'bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800', 'text-gray-600');
        
        if (type === 'error') {
            messageDiv.classList.add('bg-red-100', 'text-red-800');
        } else if (type === 'success') {
            messageDiv.classList.add('bg-green-100', 'text-green-800');
        } else {
            messageDiv.classList.add('text-gray-600');
        }
    }
    
    // Keyboard events
    document.getElementById('searchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('suggestions').style.display = 'none';
            searchAddress();
        }
    });
    
    // Close suggestions on outside click
    document.addEventListener('click', (e) => {
        const searchInput = document.getElementById('searchInput');
        const suggestions = document.getElementById('suggestions');
        
        if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
    
    // Initialize
    displayOrders();
    addMarker(120.9742, 14.5533, 'Manila');
</script>

</body>
</html>