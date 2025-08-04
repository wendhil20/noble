<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;

// Handle form submission
if ($_POST && isset($_POST['add_address'])) {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $country = $_POST['country'] ?? 'Philippines';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    // Prepare and execute the insert statement
    $sql = "INSERT INTO billing_addresses (user_id, full_name, phone, address, city, state, postal_code, country, latitude, longitude, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssdds", $user_id, $full_name, $phone, $address, $city, $state, $postal_code, $country, $latitude, $longitude, $notes);
    
    // Insert new billing address
    if ($stmt->execute()) {
        $new_address_id = $conn->insert_id;
        $success_message = "Billing address added successfully! Redirecting...";
        
        // Redirect after success
        echo "<script>
            setTimeout(function() {
                window.location.href = 'update_billing_add.php?id=" . $new_address_id . "&success=1';
            }, 2000);
        </script>";
    } else {
        $error_message = "Error adding billing address. Please try again.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Delivery Address - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFE15D',
                        neutral: '#1F2937',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444'
                    }
                }
            }
        }
    </script>
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    
    <style>
        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .suggestion-item {
            transition: all 0.2s ease;
        }
        
        .suggestion-item:hover {
            background-color: #f3f4f6;
            transform: translateX(4px);
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .field-updated {
            animation: fieldHighlight 1s ease-in-out;
        }
        
        @keyframes fieldHighlight {
            0% { background-color: #dbeafe; }
            100% { background-color: transparent; }
        }
        
        .geocode-loading {
            position: relative;
        }
        
        .geocode-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">
    
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <a href="profile.php" class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Add Delivery Address</h1>
                    <p class="text-gray-600">Add a new billing address to your account</p>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg fade-in">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <?= $success_message ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg fade-in">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <?= $error_message ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Left Column: Map and Search -->
                <div class="space-y-6 fade-in">
                    
                    <!-- Address Search -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            Search Address
                        </h2>
                        
                        <div class="relative">
                            <input
                                type="text"
                                id="addressSearch"
                                placeholder="Search for your address..."
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                autocomplete="off"
                            >
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2 flex items-center space-x-2">
                                <div class="loading-spinner" id="loadingSpinner"></div>
                                <svg class="w-5 h-5 text-gray-400" id="searchIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Suggestions Dropdown -->
                        <div id="suggestions" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>

                    <!-- Interactive Map -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </div>
                            Select Location on Map
                        </h2>
                        <div id="map" class="map-container border-2 border-gray-200"></div>
                        <p class="text-sm text-gray-500 mt-3">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Click on the map to set your exact location - address details will be filled automatically
                        </p>
                    </div>
                </div>

                <!-- Right Column: Address Form -->
                <div class="space-y-6 fade-in">
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            Address Details
                        </h2>

                        <div class="space-y-4">
                            <!-- Full Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input
                                    type="text"
                                    name="full_name"
                                    value="<?= htmlspecialchars($user_name) ?>"
                                    required
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    placeholder="Enter your full name"
                                >
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input
                                    type="tel"
                                    name="phone"
                                    required
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    placeholder="+63 xxx xxx xxxx"
                                >
                            </div>

                            <!-- Single Address Field -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Complete Address *
                                    <span class="text-xs text-blue-600 ml-1" id="addressAutoFillIndicator" style="display: none;">(Auto-filled from map)</span>
                                </label>
                                <textarea
                                    name="address"
                                    id="address"
                                    rows="3"
                                    required
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                    placeholder="Enter your complete address (Street, Building, Unit, etc.)"
                                ></textarea>
                            </div>

                            <!-- City, State, Postal Code -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        City *
                                        <span class="text-xs text-blue-600 ml-1" id="cityAutoFillIndicator" style="display: none;">(Auto-filled)</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="city"
                                        id="city"
                                        required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        placeholder="City"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        State/Province *
                                        <span class="text-xs text-blue-600 ml-1" id="stateAutoFillIndicator" style="display: none;">(Auto-filled)</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="state"
                                        id="state"
                                        required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        placeholder="State/Province"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Postal Code *
                                        <span class="text-xs text-blue-600 ml-1" id="postalAutoFillIndicator" style="display: none;">(Auto-filled)</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="postal_code"
                                        id="postal_code"
                                        required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        placeholder="Postal Code"
                                    >
                                </div>
                            </div>

                            <!-- Country -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                                <select
                                    name="country"
                                    id="country"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                >
                                    <option value="Philippines" selected>Philippines</option>
                                    <option value="United States">United States</option>
                                    <option value="Canada">Canada</option>
                                    <option value="Australia">Australia</option>
                                    <!-- Add more countries as needed -->
                                </select>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                <textarea
                                    name="notes"
                                    rows="3"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                    placeholder="Delivery instructions, landmarks, or other helpful information..."
                                ></textarea>
                            </div>

                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button
                                type="submit"
                                name="add_address"
                                class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Dilevery Address
                            </button>
                            <a
                                href="profile.php"
                                class="flex-1 bg-gray-100 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-200 transition-all duration-200 text-center"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        let map, marker;
        let searchTimeout;
        let isGeocoding = false;
        
        // Initialize Leaflet Map
        function initMap() {
            // Default location (Philippines - Manila)
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;
            
            // Initialize map
            map = L.map('map').setView([defaultLat, defaultLng], 15);
            
            // Add tile layer (using OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Custom marker icon
            const customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div style="background-color: #3B82F6; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            
            // Add marker
            marker = L.marker([defaultLat, defaultLng], { 
                icon: customIcon,
                draggable: true 
            }).addTo(map);
            
            // Click event for map
            map.on('click', function(e) {
                updateMarkerPosition(e.latlng, true);
            });
            
            // Drag event for marker
            marker.on('dragend', function(e) {
                updateMarkerPosition(e.target.getLatLng(), true);
            });
            
            // Initialize search functionality
            initializeSearch();
        }
        
        // Initialize address search
        function initializeSearch() {
            const searchInput = document.getElementById('addressSearch');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const searchIcon = document.getElementById('searchIcon');
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }
                
                // Show loading spinner
                loadingSpinner.style.display = 'block';
                searchIcon.style.display = 'none';
                
                // Debounce search
                searchTimeout = setTimeout(() => {
                    searchAddress(query);
                }, 500);
            });
        }
        
        // Search for addresses using Nominatim API
        async function searchAddress(query) {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const searchIcon = document.getElementById('searchIcon');
            
            try {
                // Using Nominatim API (free geocoding service)
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1&countrycodes=ph`);
                const results = await response.json();
                
                showSuggestions(results);
            } catch (error) {
                console.error('Search error:', error);
                hideSuggestions();
            } finally {
                // Hide loading spinner
                loadingSpinner.style.display = 'none';
                searchIcon.style.display = 'block';
            }
        }
        
        // Show search suggestions
        function showSuggestions(places) {
            const suggestionsDiv = document.getElementById('suggestions');
            
            suggestionsDiv.innerHTML = '';
            
            if (places.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'p-3 text-gray-500 text-center';
                noResults.textContent = 'No results found';
                suggestionsDiv.appendChild(noResults);
                suggestionsDiv.classList.remove('hidden');
                return;
            }
            
            places.forEach(place => {
                const suggestionItem = document.createElement('div');
                suggestionItem.className = 'suggestion-item p-3 cursor-pointer border-b border-gray-100 hover:bg-gray-50';
                
                // Extract address components
                const address = place.address || {};
                const displayName = place.display_name;
                const mainAddress = [
                    address.house_number,
                    address.road,
                    address.suburb || address.neighbourhood
                ].filter(Boolean).join(' ');
                
                const locationDetails = [
                    address.city || address.town || address.municipality,
                    address.state || address.province,
                    address.country
                ].filter(Boolean).join(', ');
                
                suggestionItem.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div>
                            <div class="font-medium text-gray-900">${mainAddress || displayName.split(',')[0]}</div>
                            <div class="text-sm text-gray-600">${locationDetails || displayName}</div>
                        </div>
                    </div>
                `;
                
                suggestionItem.addEventListener('click', function() {
                    selectPlace(place, false);
                    hideSuggestions();
                });
                
                suggestionsDiv.appendChild(suggestionItem);
            });
            
            suggestionsDiv.classList.remove('hidden');
        }
        
        // Hide suggestions
        function hideSuggestions() {
            document.getElementById('suggestions').classList.add('hidden');
        }
        
        // Select a place from suggestions
        function selectPlace(place, fromMapClick = false) {
            const lat = parseFloat(place.lat);
            const lng = parseFloat(place.lon);
            
            // Update map and marker position
            if (!fromMapClick) {
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
            }
            
            // Update hidden coordinate fields
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Fill address form fields with enhanced auto-fill
            const address = place.address || {};
            
            if (!fromMapClick) {
                // Clear search input and set selected address
                document.getElementById('addressSearch').value = place.display_name.split(',')[0];
            }
            
            // Auto-fill address fields with visual feedback
            fillAddressFields(address, place.display_name, fromMapClick);
        }
        
        // Enhanced function to fill address fields with better mapping
        function fillAddressFields(address, displayName, fromMapClick = false) {
            const addressField = document.getElementById('address');
            const cityField = document.getElementById('city');
            const stateField = document.getElementById('state');
            const postalCodeField = document.getElementById('postal_code');
            const countrySelect = document.getElementById('country');
            
            // Build complete address from components
            const addressComponents = [
                address.house_number,
                address.road,
                address.suburb || address.neighbourhood || address.village
            ].filter(Boolean);
            
            if (addressComponents.length > 0) {
                addressField.value = addressComponents.join(' ');
                highlightField(addressField, 'addressAutoFillIndicator');
            } else if (displayName) {
                // Use first part of display name if no structured address available
                const firstPart = displayName.split(',')[0];
                if (firstPart) {
                    addressField.value = firstPart;
                    highlightField(addressField, 'addressAutoFillIndicator');
                }
            }
            // Fill city
            const cityValue = address.city || address.town || address.municipality || address.county;
            if (cityValue) {
                cityField.value = cityValue;
                highlightField(cityField, 'cityAutoFillIndicator');
            }
            
            // Fill state/province
            const stateValue = address.state || address.province || address.region;
            if (stateValue) {
                stateField.value = stateValue;
                highlightField(stateField, 'stateAutoFillIndicator');
            }
            
            // Fill postal code
            if (address.postcode) {
                postalCodeField.value = address.postcode;
                highlightField(postalCodeField, 'postalAutoFillIndicator');
            }
            
            // Set country if available
            if (address.country) {
                const countryOptions = Array.from(countrySelect.options);
                const matchingOption = countryOptions.find(option => 
                    option.value.toLowerCase() === address.country.toLowerCase() ||
                    option.text.toLowerCase() === address.country.toLowerCase()
                );
                if (matchingOption) {
                    countrySelect.value = matchingOption.value;
                }
            }
        }
        
        // Highlight field with animation and show auto-fill indicator
        function highlightField(field, indicatorId) {
            field.classList.add('field-updated');
            
            // Show auto-fill indicator
            const indicator = document.getElementById(indicatorId);
            if (indicator) {
                indicator.style.display = 'inline';
            }
            
            // Remove animation class after animation completes
            setTimeout(() => {
                field.classList.remove('field-updated');
            }, 1000);
        }
        
        // Update marker position and reverse geocode
        function updateMarkerPosition(latlng, shouldReverseGeocode = true) {
            marker.setLatLng(latlng);
            
            // Update hidden coordinate fields
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
            
            if (shouldReverseGeocode && !isGeocoding) {
                reverseGeocode(latlng.lat, latlng.lng);
            }
        }
        
        // Reverse geocode to get address from coordinates
        async function reverseGeocode(lat, lng) {
            if (isGeocoding) return;
            
            isGeocoding = true;
            const addressField = document.getElementById('address');
            
            try {
                // Add loading state to address field
                addressField.classList.add('geocode-loading');
                
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`);
                const result = await response.json();
                
                if (result && result.address) {
                    selectPlace(result, true);
                }
            } catch (error) {
                console.error('Reverse geocoding error:', error);
            } finally {
                isGeocoding = false;
                addressField.classList.remove('geocode-loading');
            }
        }
        
        // Try to get user's current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        map.setView([lat, lng], 16);
                        updateMarkerPosition(L.latLng(lat, lng), true);
                    },
                    function(error) {
                        console.log('Geolocation error:', error.message);
                        // Keep default location
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 600000
                    }
                );
            }
        }
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('addressSearch');
            const suggestionsDiv = document.getElementById('suggestions');
            
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                hideSuggestions();
            }
        });
        
        // Form validation
        function validateForm() {
            const requiredFields = ['full_name', 'phone', 'address', 'city', 'state', 'postal_code'];
            let isValid = true;
            
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            return isValid;
        }
        
        // Add form validation on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg fade-in';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        Please fill in all required fields.
                    </div>
                `;
                
                // Insert error message at the top of the form
                const form = this;
                form.insertBefore(errorDiv, form.firstChild);
                
                // Scroll to top of form
                form.scrollIntoView({ behavior: 'smooth' });
                
                // Remove error message after 5 seconds
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
            }
        });
        
        // Phone number formatting (Philippines format)
        document.querySelector('[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
            
            // Format for Philippines numbers
            if (value.startsWith('63')) {
                // International format
                value = '+' + value.slice(0, 2) + ' ' + value.slice(2, 5) + ' ' + value.slice(5, 8) + ' ' + value.slice(8, 12);
            } else if (value.startsWith('09')) {
                // Local format starting with 09
                value = value.slice(0, 4) + ' ' + value.slice(4, 7) + ' ' + value.slice(7, 11);
            } else if (value.length > 0) {
                // Add +63 prefix for other numbers
                if (!value.startsWith('9')) {
                    value = '9' + value;
                }
                value = '+63 ' + value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
            }
            
            e.target.value = value;
        });
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            getCurrentLocation();
            
            // Add keyboard navigation for search
            document.getElementById('addressSearch').addEventListener('keydown', function(e) {
                const suggestionsDiv = document.getElementById('suggestions');
                const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');
                
                if (suggestions.length === 0) return;
                
                let currentActive = suggestionsDiv.querySelector('.suggestion-item.active');
                let currentIndex = Array.from(suggestions).indexOf(currentActive);
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (currentActive) currentActive.classList.remove('active');
                        currentIndex = (currentIndex + 1) % suggestions.length;
                        suggestions[currentIndex].classList.add('active');
                        suggestions[currentIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (currentActive) currentActive.classList.remove('active');
                        currentIndex = currentIndex <= 0 ? suggestions.length - 1 : currentIndex - 1;
                        suggestions[currentIndex].classList.add('active');
                        suggestions[currentIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (currentActive) {
                            currentActive.click();
                        }
                        break;
                        
                    case 'Escape':
                        hideSuggestions();
                        this.blur();
                        break;
                }
            });
        });
        
        // Auto-resize textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        // Apply auto-resize to textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                autoResize(this);
            });
        });
    </script>
</body>
</html>