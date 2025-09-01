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
    
    // Format phone number to +63 XXX XXX XXXX format
    if ($phone) {
        // Remove all non-digits
        $phone_digits = preg_replace('/\D/', '', $phone);
        
        // If it starts with 63, remove it
        if (substr($phone_digits, 0, 2) === '63') {
            $phone_digits = substr($phone_digits, 2);
        }
        
        // Ensure it's a 10-digit mobile number starting with 9
        if (strlen($phone_digits) === 10 && substr($phone_digits, 0, 1) === '9') {
            // Format as +63 XXX XXX XXXX
            $formatted_phone = '+63 ' . substr($phone_digits, 0, 3) . ' ' . substr($phone_digits, 3, 3) . ' ' . substr($phone_digits, 6);
            $phone = $formatted_phone;
        }
    }
    
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
        
        // Store the redirect script in a variable to output in the body
        $redirect_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (document.referrer && document.referrer !== window.location.href) {
                        window.location.href = document.referrer;
                    } else {
                        window.location.href = 'checkout.php';
                    }
                }, 2000);
            });
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
            position: relative;
        }
        
        .suggestion-item {
            transition: all 0.2s ease;
        }
        
        .suggestion-item:hover,
        .suggestion-item.active {
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

        /* Suggestions dropdown styling */
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-height: 15rem;
            overflow-y: auto;
            margin-top: 0.25rem;
            z-index: 40;
        }
        
        /* Fix navbar z-index issues */
        .leaflet-control-container {
            z-index: 1;
        }
        
        .leaflet-popup {
            z-index: 2;
        }
        
        .leaflet-control {
            z-index: 1;
        }
        
        /* Fix search suggestions z-index */
        #suggestions {
            z-index: 40;
        }
        
        /* Ensure map container stays below navbar */
        .map-container {
            position: relative;
            z-index: 1;
        }

        /* Phone input styling - Updated for +63 XXX XXX XXXX format */
        .phone-input {
            padding-left: 60px;
        }

        .phone-prefix {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-weight: 500;
            pointer-events: none;
        }

        /* Form field states */
        .form-field[readonly] {
            background-color: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .form-field:not([readonly]) {
            background-color: white;
            color: #1f2937;
            cursor: text;
        }
        
        .form-field:not([readonly]):focus {
            ring: 2px;
            ring-color: #3b82f6;
            border-color: transparent;
        }

        /* Location search priority */
        .location-first-notice {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
        }

        /* Responsive design improvements */
        @media (max-width: 1024px) {
            .map-container {
                height: 300px;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">
    
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-4">
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
                    <p class="text-gray-600">First, set your location on the map, then complete the details</p>
                </div>
            </div>
        </div>

        <!-- Location First Notice -->
        <div class="mb-6 location-first-notice rounded-lg p-4 fade-in">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-amber-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-800">Step 1: Set Your Location First</h3>
                    <p class="text-sm text-amber-700">Click on the map or search for your address to automatically fill the form fields below</p>
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
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                
                <!-- Left Column: Map and Search (Priority Section) -->
                <div class="space-y-6 fade-in">
                    
                    <!-- Address Search -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 relative">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            Search Your Address
                        </h2>
                        
                        <div class="relative">
                            <input
                                type="text"
                                id="addressSearch"
                                placeholder="Search for your address..."
                                class="w-full border border-gray-300 rounded-lg px-4 py-4 pr-12 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                autocomplete="off"
                            >
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2 flex items-center space-x-2">
                                <div class="loading-spinner" id="loadingSpinner"></div>
                                <svg class="w-5 h-5 text-gray-400" id="searchIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            
                            <!-- Suggestions Dropdown -->
                            <div id="suggestions" class="suggestions-dropdown hidden">
                                <!-- Suggestions will be populated here -->
                            </div>
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
                            Click to Set Your Exact Location
                        </h2>
                        <div id="map" class="map-container border-2 border-gray-200"></div>
                        <p class="text-sm text-gray-500 mt-3">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Click anywhere on the map to set your location - all address fields will be auto-filled
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
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                    placeholder="Set location first to enable editing"
                                >
                            </div>

                            <!-- Phone with Updated Format -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number/ Mobile Number *</label>
                                <input
                                    type="tel"
                                    name="phone"
                                    id="phoneInput"
                                    required
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                    placeholder="Set location first to enable editing"
                                    maxlength="16"
                                >
                                <p class="text-xs text-gray-500 mt-1">Format: +63 XXX XXX XXXX (e.g., +63 967 167 7760)</p>
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
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all resize-none cursor-not-allowed form-field"
                                    placeholder="Set location first to enable editing"
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
                                        readonly
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                        placeholder="Set location first"
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
                                        readonly
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                        placeholder="Set location first"
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
                                        readonly
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                        placeholder="Set location first"
                                    >
                                </div>
                            </div>

                            <!-- Country (Auto-filled) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Country
                                    <span class="text-xs text-blue-600 ml-1" id="countryAutoFillIndicator" style="display: none;">(Auto-filled from location)</span>
                                </label>
                                <input
                                    type="text"
                                    name="country"
                                    id="country"
                                    value="Philippines"
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-600 focus:outline-none transition-all cursor-not-allowed"
                                    placeholder="Country will be detected from location"
                                >
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    rows="3"
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all resize-none cursor-not-allowed form-field"
                                    placeholder="Set location first to add notes"
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
                                Add Delivery Address
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
            
            // Initialize map with proper z-index settings
            map = L.map('map', {
                zIndex: 1
            }).setView([defaultLat, defaultLng], 15);
            
            // Add tile layer
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
            
            // Click event for map - PRIORITY: Location first
            map.on('click', function(e) {
                updateMarkerPosition(e.latlng, true);
                showLocationSetSuccess();
            });
            
            // Drag event for marker
            marker.on('dragend', function(e) {
                updateMarkerPosition(e.target.getLatLng(), true);
                showLocationSetSuccess();
            });
            
            // Initialize search functionality
            initializeSearch();
            
            // Try to get user's current location on load
            getCurrentLocation();
        }
        
        // Enable form fields after location is set
        function enableFormFields() {
            const formFields = document.querySelectorAll('.form-field');
            
            formFields.forEach(field => {
                field.removeAttribute('readonly');
                field.classList.remove('bg-gray-50', 'text-gray-500', 'cursor-not-allowed');
                field.classList.add('bg-white', 'text-gray-900', 'cursor-text');
                
                // Update placeholder text
                const fieldName = field.getAttribute('name');
                switch(fieldName) {
                    case 'full_name':
                        field.placeholder = 'Enter your full name';
                        break;
                    case 'phone':
                        field.placeholder = '+63 9XX XXX XXXX';
                        break;
                    case 'address':
                        field.placeholder = 'Add details like unit/floor number, landmarks...';
                        break;
                    case 'city':
                        field.placeholder = 'City';
                        break;
                    case 'state':
                        field.placeholder = 'State/Province';
                        break;
                    case 'postal_code':
                        field.placeholder = 'Postal Code';
                        break;
                    case 'notes':
                        field.placeholder = 'Delivery instructions, landmarks, or other helpful information...';
                        break;
                }
                
                // Add focus ring styles
                field.addEventListener('focus', function() {
                    this.classList.add('ring-2', 'ring-blue-500', 'border-transparent');
                });
                
                field.addEventListener('blur', function() {
                    this.classList.remove('ring-2', 'ring-blue-500', 'border-transparent');
                });
            });
            
            // Show fields enabled notification
            const notice = document.createElement('div');
            notice.className = 'fixed top-32 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50 fade-in';
            notice.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Form fields are now editable!
                </div>
            `;
            document.body.appendChild(notice);
            
            setTimeout(() => {
                notice.remove();
            }, 3000);
        }
        
        // Show success message when location is set
        function showLocationSetSuccess() {
            // Remove existing notification
            const existingNotice = document.querySelector('.location-set-success');
            if (existingNotice) {
                existingNotice.remove();
            }
            
            // Enable form fields
            enableFormFields();
            
            // Create new success notification
            const notice = document.createElement('div');
            notice.className = 'location-set-success fixed top-20 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50 fade-in';
            notice.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    Location set! You can now edit the address details.
                </div>
            `;
            document.body.appendChild(notice);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                notice.remove();
            }, 3000);
        }
        
        // Initialize address search
        function initializeSearch() {
            const searchInput = document.getElementById('addressSearch');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const searchIcon = document.getElementById('searchIcon');
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }
                
                loadingSpinner.style.display = 'block';
                searchIcon.style.display = 'none';
                
                searchTimeout = setTimeout(() => {
                    searchAddress(query);
                }, 500);
            });
        }
        
        // Search for addresses
        async function searchAddress(query) {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const searchIcon = document.getElementById('searchIcon');
            
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1&countrycodes=ph`);
                const results = await response.json();
                showSuggestions(results);
            } catch (error) {
                console.error('Search error:', error);
                hideSuggestions();
            } finally {
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
                noResults.className = 'p-4 text-gray-500 text-center';
                noResults.textContent = 'No results found';
                suggestionsDiv.appendChild(noResults);
                suggestionsDiv.classList.remove('hidden');
                return;
            }
            
            places.forEach(place => {
                const suggestionItem = document.createElement('div');
                suggestionItem.className = 'suggestion-item p-4 cursor-pointer border-b border-gray-100 hover:bg-gray-50';
                
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
                        <div class="flex-1">
                            <div class="font-medium text-gray-900 text-sm">${mainAddress || displayName.split(',')[0]}</div>
                            <div class="text-xs text-gray-600">${locationDetails || displayName}</div>
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
        
        // Select a place from suggestions - PRIORITY: Location first
        function selectPlace(place, fromMapClick = false) {
            const lat = parseFloat(place.lat);
            const lng = parseFloat(place.lon);
            
            // Update map and marker position first
            if (!fromMapClick) {
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                showLocationSetSuccess();
            }
            
            // Update hidden coordinate fields
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Fill address form fields
            const address = place.address || {};
            
            if (!fromMapClick) {
                document.getElementById('addressSearch').value = place.display_name.split(',')[0];
            }
            
            // Auto-fill all fields including country
            fillAddressFields(address, place.display_name, fromMapClick);
        }
        
        // Enhanced function to fill address fields with auto country detection
        function fillAddressFields(address, displayName, fromMapClick = false) {
            const addressField = document.getElementById('address');
            const cityField = document.getElementById('city');
            const stateField = document.getElementById('state');
            const postalCodeField = document.getElementById('postal_code');
            const countryField = document.getElementById('country');
            
            // Build complete address
            const addressComponents = [
                address.house_number,
                address.road,
                address.suburb || address.neighbourhood || address.village
            ].filter(Boolean);
            
            if (addressComponents.length > 0) {
                addressField.value = addressComponents.join(' ');
                highlightField(addressField, 'addressAutoFillIndicator');
            } else if (displayName) {
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
            
            // Auto-fill country
            if (address.country) {
                let countryName = address.country;
                
                // Map common variations to proper names
                const countryMappings = {
                    'Philippines': 'Philippines',
                    'United States of America': 'United States',
                    'USA': 'United States',
                    'US': 'United States'
                };
                
                countryName = countryMappings[countryName] || countryName;
                countryField.value = countryName;
                countryField.classList.remove('bg-gray-50', 'text-gray-600');
                countryField.classList.add('bg-blue-50', 'text-blue-800');
                highlightField(countryField, 'countryAutoFillIndicator');
            }
        }
        
        // Highlight field with animation
        function highlightField(field, indicatorId) {
            field.classList.add('field-updated');
            
            const indicator = document.getElementById(indicatorId);
            if (indicator) {
                indicator.style.display = 'inline';
            }
            
            setTimeout(() => {
                field.classList.remove('field-updated');
            }, 1000);
        }
        
        // Update marker position and reverse geocode
        function updateMarkerPosition(latlng, shouldReverseGeocode = true) {
            marker.setLatLng(latlng);
            
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
        
        // Get user's current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        map.setView([lat, lng], 16);
                        updateMarkerPosition(L.latLng(lat, lng), true);
                        
                        // Show notification that location was detected
                        const notice = document.createElement('div');
                        notice.className = 'fixed top-20 right-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg shadow-lg z-50 fade-in';
                        notice.innerHTML = `
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Your location detected and set!
                            </div>
                        `;
                        document.body.appendChild(notice);
                        
                        setTimeout(() => {
                            notice.remove();
                        }, 4000);
                    },
                    function(error) {
                        console.log('Geolocation error:', error.message);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 600000
                    }
                );
            }
        }
        
        // Updated phone number formatting for +63 XXX XXX XXXX format
        function formatPhoneNumber(value) {
            // Remove all non-digits and the + symbol
            let cleaned = value.replace(/[^\d]/g, '');
            
            // Remove leading 63 if present
            if (cleaned.startsWith('63')) {
                cleaned = cleaned.substring(2);
            }
            
            // Ensure it starts with 9 for mobile numbers
            if (cleaned.length > 0 && !cleaned.startsWith('9')) {
                cleaned = '9' + cleaned.substring(1);
            }
            
            // Limit to 10 digits
            cleaned = cleaned.substring(0, 10);
            
            // Format as +63 XXX XXX XXXX
            if (cleaned.length === 0) {
                return '+63 ';
            } else if (cleaned.length <= 3) {
                return '+63 ' + cleaned;
            } else if (cleaned.length <= 6) {
                return '+63 ' + cleaned.substring(0, 3) + ' ' + cleaned.substring(3);
            } else {
                return '+63 ' + cleaned.substring(0, 3) + ' ' + cleaned.substring(3, 6) + ' ' + cleaned.substring(6);
            }
        }
        
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
            
            // Special validation for phone number format
            const phoneField = document.querySelector('[name="phone"]');
            const phoneValue = phoneField.value.trim();
            
            // Check if phone follows +63 XXX XXX XXXX format
            const phoneRegex = /^\+63 9\d{2} \d{3} \d{4}$/;
            if (phoneValue && !phoneRegex.test(phoneValue)) {
                phoneField.classList.add('border-red-500');
                isValid = false;
                
                // Show phone format error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded-lg fade-in';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Phone number must be in format: +63 9XX XXX XXXX (e.g., +63 967 167 7760)
                    </div>
                `;
                
                document.querySelector('form').insertBefore(errorDiv, document.querySelector('form').firstChild);
                
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
            } else {
                phoneField.classList.remove('border-red-500');
            }
            
            // Special validation for coordinates
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if (!lat || !lng) {
                // Show location required message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded-lg fade-in';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Please set your location on the map first by clicking on it or searching for your address.
                    </div>
                `;
                
                document.querySelector('form').insertBefore(errorDiv, document.querySelector('form').firstChild);
                
                // Scroll to map
                document.getElementById('map').scrollIntoView({ behavior: 'smooth' });
                
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
                
                isValid = false;
            }
            
            return isValid;
        }
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('addressSearch');
            const suggestionsDiv = document.getElementById('suggestions');
            
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                hideSuggestions();
            }
        });
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Phone number formatting with +63 prefix
            const phoneInput = document.getElementById('phoneInput');
            
            // Set initial value with +63 prefix
            if (!phoneInput.value || phoneInput.value.trim() === '') {
                phoneInput.value = '+63 ';
            }
            
            phoneInput.addEventListener('input', function(e) {
                e.target.value = formatPhoneNumber(e.target.value);
            });
            
            // Prevent deletion of +63 prefix
            phoneInput.addEventListener('keydown', function(e) {
                const cursorPosition = e.target.selectionStart;
                
                // Prevent deletion of +63 prefix
                if ((e.key === 'Backspace' || e.key === 'Delete') && cursorPosition <= 4) {
                    e.preventDefault();
                }
            });
            
            // Ensure +63 prefix is always present on focus
            phoneInput.addEventListener('focus', function(e) {
                if (!e.target.value.startsWith('+63 ')) {
                    e.target.value = '+63 ';
                }
                
                // Move cursor to end if at beginning
                setTimeout(() => {
                    if (e.target.selectionStart <= 4) {
                        e.target.setSelectionRange(e.target.value.length, e.target.value.length);
                    }
                }, 10);
            });
            
            // Form validation on submit
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg fade-in';
                    errorDiv.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            Please fill in all required fields correctly and set your location on the map.
                        </div>
                    `;
                    
                    this.insertBefore(errorDiv, this.firstChild);
                    this.scrollIntoView({ behavior: 'smooth' });
                    
                    setTimeout(() => {
                        errorDiv.remove();
                    }, 5000);
                }
            });
            
            // Keyboard navigation for search
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
    </script>

    <?php
    // Output the redirect script if there's a success message
    if (isset($redirect_script)) {
        echo $redirect_script;
    }
    ?>
</body>
</html>