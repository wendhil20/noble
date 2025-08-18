<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['supplier', 'superadmin']);

// Get the logged-in user's ID from session (now available from top.php)
$logged_in_user_id = $_SESSION['noble_id'] ?? null;

if (!$logged_in_user_id) {
    header("Location: ../../loginpage/index.php?error=user_not_found");
    exit();
}

// You can now directly use the session data or still fetch if you need supplier_id
$supplier_query = "SELECT id, email, fullname, supplier_id FROM nobleaccount WHERE id = ?";
$stmt = $conn->prepare($supplier_query);
$stmt->bind_param("i", $logged_in_user_id);
$stmt->execute();
$supplier_result = $stmt->get_result();

if ($supplier_result->num_rows === 0) {
    header("Location: dashboard.php?error=supplier_not_found");
    exit();
}

$supplier = $supplier_result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_POST && isset($_POST['add_supplier_address'])) {
    $supplier_id = $_POST['supplier_id'] ?? $_SESSION['noble_id']; // Get from hidden input or session
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $country = $_POST['country'] ?? 'Philippines';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $address_type = $_POST['address_type'] ?? 'main';
    
    // Validate required fields
    if (empty($phone) || empty($address) || empty($city) || empty($state) || empty($postal_code)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Prepare and execute the insert statement
        $sql = "INSERT INTO supplier_addresses (supplier_id, phone, address, city, state, postal_code, country, latitude, longitude, notes, address_type, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($sql);
$created_by = $_SESSION['noble_id']; // Keep this as the person who created the record
$stmt->bind_param("issssssddssi", $supplier_id, $phone, $address, $city, $state, $postal_code, $country, $latitude, $longitude, $notes, $address_type, $created_by);
        
        // Insert new supplier address
        if ($stmt->execute()) {
            $new_address_id = $conn->insert_id;
            $success_message = "Supplier address added successfully! Redirecting...";
            
            // Redirect after success
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'supplier_addresses.php?success=1';
                }, 2000);
            </script>";
        } else {
            $error_message = "Error adding supplier address. Please try again.";
        }
        $stmt->close();
    }
}

include '../navbar/top.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier Address - Noble Home Admin</title>
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
            position: relative;
        }
        
        /* Fix map scroll issue */
        .map-container .leaflet-container {
            height: 100% !important;
            width: 100% !important;
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

        /* Phone input styling */
        .phone-input-container {
            position: relative;
        }
        
        .phone-placeholder {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            pointer-events: none;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        
        .phone-input:focus + .phone-placeholder,
        .phone-input:not(:placeholder-shown) + .phone-placeholder {
            transform: translateY(-50%) scale(0.8);
            top: 25%;
            color: #3B82F6;
        }

        /* Map responsiveness fixes */
        @media (max-width: 768px) {
            .map-container {
                height: 300px;
                margin: 0;
                border-radius: 0.5rem;
            }
            
            .grid-cols-1.lg\\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Prevent map from interfering with scroll */
        .leaflet-container {
            touch-action: pan-x pan-y;
        }

        /* Ensure proper z-index for suggestions */
        #suggestions {
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <a href="supplier_addresses.php" class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Add Supplier Address</h1>
                    <p class="text-gray-600">Add address for: <span class="font-semibold text-primary"><?= htmlspecialchars($supplier['email']) ?></span></p>
                    <p class="text-sm text-gray-500">Contact: <?= htmlspecialchars($supplier['fullname']) ?></p>
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
                                placeholder="Search for supplier address..."
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
                            Click on the map to set supplier location - address details will be filled automatically
                        </p>
                    </div>
                </div>

                <!-- Right Column: Address Form -->
                <div class="space-y-6 fade-in">
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            Supplier Address Details
                        </h2>

                        <div class="space-y-4">
                            <!-- Address Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address Type</label>
                                <select
                                    name="address_type"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                >
                                    <option value="main">Main Office</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="factory">Factory</option>
                                    <option value="branch">Branch Office</option>
                                    <option value="delivery">Delivery Point</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <!-- Phone with improved formatting -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <div class="phone-input-container">
                                    <input
                                        type="tel"
                                        name="phone"
                                        id="phone"
                                        required
                                        class="phone-input w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        placeholder=" "
                                        maxlength="20"
                                    >
                                    <div class="phone-placeholder">+63 9XX-XXX-XXXX</div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Format: +63 9XX-XXX-XXXX or 09XX-XXX-XXXX</p>
                            </div>

                            <!-- Complete Address -->
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
                                    placeholder="Enter complete supplier address (Street, Building, Unit, etc.)"
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
                                    <option value="China">China</option>
                                    <option value="Singapore">Singapore</option>
                                    <option value="Malaysia">Malaysia</option>
                                    <option value="Thailand">Thailand</option>
                                    <option value="Vietnam">Vietnam</option>
                                    <option value="Indonesia">Indonesia</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                <textarea
                                    name="notes"
                                    rows="3"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                    placeholder="Delivery instructions, operating hours, contact notes, or other important information..."
                                ></textarea>
                            </div>

                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="supplier_id" value="<?= $_SESSION['noble_id'] ?>">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button
                                type="submit"
                                name="add_supplier_address"
                                class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Supplier Address
                            </button>
                            <a
                                href="supplier_addresses.php"
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
        
        // Initialize Leaflet Map with improved responsiveness
        function initMap() {
            // Default location (Philippines - Manila)
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;
            
            // Initialize map with better options
            map = L.map('map', {
                zoomControl: true,
                scrollWheelZoom: false, // Disable scroll wheel zoom to prevent scroll issues
                doubleClickZoom: true,
                touchZoom: true,
                dragging: true
            }).setView([defaultLat, defaultLng], 15);
            
            // Enable scroll wheel zoom only when map is focused
            map.on('focus', function() {
                map.scrollWheelZoom.enable();
            });
            
            map.on('blur', function() {
                map.scrollWheelZoom.disable();
            });
            
            // Add tile layer (using OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Custom marker icon
            const customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div style="background-color: #F97316; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>`,
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
            
            // Resize map when window resizes
            window.addEventListener('resize', function() {
                setTimeout(function() {
                    map.invalidateSize();
                }, 100);
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
            const requiredFields = ['phone', 'address', 'city', 'state', 'postal_code'];
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
        
        // Enhanced phone number formatting with better UX
        function formatPhoneNumber(value) {
            // Remove all non-digits
            let cleaned = value.replace(/\D/g, '');
            
            // Handle different input patterns
            if (cleaned.startsWith('63')) {
                // International format: +639XXXXXXXXX
                if (cleaned.length <= 12) {
                    return cleaned.replace(/(\d{2})(\d{1})(\d{2})(\d{3})(\d{4})/, '+$1 $2$3-$4-$5');
                }
            } else if (cleaned.startsWith('09')) {
                // Local format: 09XXXXXXXXX
                if (cleaned.length <= 11) {
                    return cleaned.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
                }
            } else if (cleaned.startsWith('9') && cleaned.length <= 10) {
                // Format as +63 9XX-XXX-XXXX
                return cleaned.replace(/(\d{1})(\d{2})(\d{3})(\d{4})/, '+63 $1$2-$3-$4');
            } else if (cleaned.length > 0 && !cleaned.startsWith('0') && !cleaned.startsWith('63')) {
                // Add 09 prefix for numbers that don't start with 0 or 63
                cleaned = '09' + cleaned;
                if (cleaned.length <= 11) {
                    return cleaned.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
                }
            }
            
            return value; // Return original if no pattern matches
        }
        
        // Phone input handler with improved formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            const cursorPosition = e.target.selectionStart;
            const oldValue = e.target.value;
            const newValue = formatPhoneNumber(oldValue);
            
            if (newValue !== oldValue) {
                e.target.value = newValue;
                
                // Adjust cursor position
                let newCursorPosition = cursorPosition;
                if (newValue.length > oldValue.length) {
                    newCursorPosition += (newValue.length - oldValue.length);
                }
                
                // Set cursor position after a short delay
                setTimeout(() => {
                    e.target.setSelectionRange(newCursorPosition, newCursorPosition);
                }, 10);
            }
        });
        
        // Phone input validation
        document.getElementById('phone').addEventListener('blur', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                // Check if it's a valid Philippine number
                const isValid = (
                    (value.startsWith('63') && value.length === 12 && value.charAt(2) === '9') ||
                    (value.startsWith('09') && value.length === 11) ||
                    (value.startsWith('9') && value.length === 10)
                );
                
                if (!isValid) {
                    e.target.classList.add('border-red-500');
                    // Show error message
                    let errorMsg = e.target.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'error-message text-xs text-red-500 mt-1';
                        e.target.parentNode.appendChild(errorMsg);
                    }
                    errorMsg.textContent = 'Please enter a valid Philippine phone number';
                } else {
                    e.target.classList.remove('border-red-500');
                    // Remove error message
                    const errorMsg = e.target.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            }
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
            
            // Fix map container size on tab/window focus
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    setTimeout(function() {
                        if (map) {
                            map.invalidateSize();
                        }
                    }, 200);
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
        
        // Handle map container visibility issues
        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            const observer = new ResizeObserver(function(entries) {
                if (map) {
                    map.invalidateSize();
                }
            });
            observer.observe(mapContainer);
        }
    </script>
</body>
</html>