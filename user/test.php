<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Address Search</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <style>
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 20rem;
            overflow-y: auto;
            margin-top: 0.5rem;
            z-index: 50;
        }

        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
        }

        .suggestion-item:hover,
        .suggestion-item.active {
            background-color: #dbeafe;
            padding-left: 20px;
        }

        .suggestion-item.active {
            background-color: #3b82f6;
            color: white;
        }

        .suggestion-item.active .location-text {
            color: #dbeafe;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen p-6">

    <div class="max-w-3xl mx-auto">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Real-time Address Search</h1>
        <p class="text-gray-600 mb-8">Type instantly - suggestions appear as you type (like Google Maps)</p>

        <div class="bg-white rounded-2xl shadow-lg p-8">
            <!-- Search Input -->
            <div class="mb-8">
                <label class="block text-sm font-semibold text-gray-700 mb-3">Search Your Address</label>
                <div class="relative">
                    <input
                        type="text"
                        id="addressSearch"
                        placeholder="Try: 'SM North EDSA', 'Barangay 1 Caloocan', 'Makati'..."
                        class="w-full px-5 py-4 text-lg border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:outline-none transition-all"
                        autocomplete="off">
                    
                    <!-- Loading Spinner -->
                    <div id="loadingSpinner" class="absolute right-4 top-1/2 transform -translate-y-1/2" style="display: none;">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Search Icon -->
                    <svg id="searchIcon" class="absolute right-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>

                    <!-- Suggestions Dropdown -->
                    <div id="suggestions" class="suggestions-dropdown hidden"></div>
                </div>
            </div>

            <!-- Map -->
            <div class="mb-8">
                <label class="block text-sm font-semibold text-gray-700 mb-3">Or Click on Map</label>
                <div id="map" class="map-container border-2 border-gray-300 rounded-xl"></div>
            </div>

            <!-- Selected Address Display -->
            <div id="resultContainer" style="display: none;">
                <div class="bg-green-50 border-2 border-green-300 rounded-lg p-6">
                    <h3 class="text-lg font-bold text-green-900 mb-4">✓ Address Selected</h3>
                    
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-green-700 font-semibold">Full Address:</p>
                            <p class="text-green-900" id="resultAddress"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-green-700 font-semibold">City:</p>
                                <p class="text-green-900" id="resultCity"></p>
                            </div>
                            <div>
                                <p class="text-green-700 font-semibold">Province:</p>
                                <p class="text-green-900" id="resultState"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-green-700 font-semibold">Coordinates:</p>
                                <p class="text-green-900 text-xs" id="resultCoords"></p>
                            </div>
                            <div>
                                <p class="text-green-700 font-semibold">Postal Code:</p>
                                <p class="text-green-900" id="resultPostal"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="mt-8 bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 mb-2">💡 Search Tips:</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>✓ Start typing - suggestions appear instantly (no 800ms delay!)</li>
                <li>✓ Arrow keys to navigate, Enter to select</li>
                <li>✓ Try: "Quezon City", "Makati", "BGY 1", "Rizal Ave Manila"</li>
            </ul>
        </div>
    </div>

    <script>
        let map, marker;
        let currentResults = [];
        let highlightedIndex = -1;
        let abortController = null;

        // Initialize Map
        function initMap() {
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;

            map = L.map('map').setView([defaultLat, defaultLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            const customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: '<div style="background-color: #3B82F6; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            marker = L.marker([defaultLat, defaultLng], { icon: customIcon, draggable: true }).addTo(map);

            map.on('click', (e) => {
                selectLocation(e.latlng.lat, e.latlng.lng);
            });

            marker.on('dragend', () => {
                selectLocation(marker.getLatLng().lat, marker.getLatLng().lng);
            });

            // Try to get user location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.setView([lat, lng], 14);
                    marker.setLatLng([lat, lng]);
                });
            }
        }

        // REAL-TIME Search - NO DELAY!
        document.getElementById('addressSearch').addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            highlightedIndex = -1;

            // Abort previous request
            if (abortController) {
                abortController.abort();
            }

            if (query.length < 2) {
                document.getElementById('suggestions').classList.add('hidden');
                return;
            }

            // Show loading spinner
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('searchIcon').style.display = 'none';

            try {
                abortController = new AbortController();

                // Search with Philippines restriction
                const url = 'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
                    q: `${query}, Philippines`,
                    format: 'json',
                    addressdetails: 1,
                    limit: 8,
                    countrycodes: 'ph',
                    viewbox: '119.0,4.5,131.0,21.0',
                    bounded: 1,
                });

                const response = await fetch(url, { signal: abortController.signal });
                currentResults = await response.json();

                // If no results, try without Philippines suffix
                if (currentResults.length === 0) {
                    const url2 = 'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
                        q: query,
                        format: 'json',
                        addressdetails: 1,
                        limit: 8,
                        countrycodes: 'ph',
                    });
                    currentResults = await fetch(url2, { signal: abortController.signal }).then(r => r.json());
                }

                displaySuggestions(currentResults);

            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Search error:', error);
                }
            } finally {
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('searchIcon').style.display = 'block';
            }
        });

        // Display Suggestions
        function displaySuggestions(results) {
            const suggestionsDiv = document.getElementById('suggestions');
            suggestionsDiv.innerHTML = '';

            if (results.length === 0) {
                suggestionsDiv.innerHTML = `
                    <div class="p-4 text-center text-gray-600">
                        <p class="font-medium">No results found</p>
                        <p class="text-xs text-gray-500 mt-1">Try: City name, Barangay, Street, or Landmark</p>
                    </div>
                `;
                suggestionsDiv.classList.remove('hidden');
                return;
            }

            results.forEach((place, index) => {
                const address = place.address || {};
                const mainName = address.road || address.suburb || address.neighbourhood || place.name || place.display_name.split(',')[0];
                const locationDetails = [address.city || address.municipality, address.state || address.province].filter(Boolean).join(', ');

                const item = document.createElement('div');
                item.className = 'suggestion-item';
                item.setAttribute('data-index', index);
                item.innerHTML = `
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        <div>
                            <div class="font-semibold text-gray-900">${mainName}</div>
                            <div class="text-xs location-text text-gray-600">${locationDetails}</div>
                        </div>
                    </div>
                `;

                item.addEventListener('click', () => selectAddress(place));
                suggestionsDiv.appendChild(item);
            });

            suggestionsDiv.classList.remove('hidden');
        }

        // Select Address
        function selectAddress(place) {
            const address = place.address || {};
            const lat = parseFloat(place.lat);
            const lng = parseFloat(place.lon);

            // Update map
            map.setView([lat, lng], 16);
            marker.setLatLng([lat, lng]);

            // Populate form
            const fullAddress = [address.house_number, address.road, address.suburb || address.neighbourhood].filter(Boolean).join(' ') || place.display_name.split(',')[0];
            
            document.getElementById('addressSearch').value = fullAddress;
            document.getElementById('suggestions').classList.add('hidden');

            // Show results
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('resultAddress').textContent = fullAddress;
            document.getElementById('resultCity').textContent = address.city || address.municipality || 'N/A';
            document.getElementById('resultState').textContent = address.state || address.province || 'N/A';
            document.getElementById('resultPostal').textContent = address.postcode || 'N/A';
            document.getElementById('resultCoords').textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;

            // Scroll to results
            document.getElementById('resultContainer').scrollIntoView({ behavior: 'smooth' });
        }

        // Click on map
        function selectLocation(lat, lng) {
            marker.setLatLng([lat, lng]);
            reverseGeocode(lat, lng);
        }

        // Reverse Geocode (click on map)
        async function reverseGeocode(lat, lng) {
            try {
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;
                const response = await fetch(url);
                const data = await response.json();
                selectAddress(data);
            } catch (error) {
                console.error('Reverse geocode error:', error);
            }
        }

        // Keyboard Navigation
        document.getElementById('addressSearch').addEventListener('keydown', (e) => {
            if (currentResults.length === 0) return;

            const suggestionsDiv = document.getElementById('suggestions');
            const items = suggestionsDiv.querySelectorAll('.suggestion-item');

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                    updateHighlight(items);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    highlightedIndex = Math.max(highlightedIndex - 1, -1);
                    updateHighlight(items);
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (highlightedIndex >= 0) {
                        selectAddress(currentResults[highlightedIndex]);
                    }
                    break;
                case 'Escape':
                    suggestionsDiv.classList.add('hidden');
                    break;
            }
        });

        function updateHighlight(items) {
            items.forEach((item, i) => {
                item.classList.toggle('active', i === highlightedIndex);
            });
            if (highlightedIndex >= 0) {
                items[highlightedIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', initMap);
    </script>

</body>
</html>