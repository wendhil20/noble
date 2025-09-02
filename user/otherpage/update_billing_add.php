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


    // CORRECTED Phone validation for Philippine mobile numbers - FINAL FIX
    if ($phone) {
        // Remove all non-digits
        $phone_digits = preg_replace('/\D/', '', $phone);

        // If it starts with 63, remove it to get mobile digits
        if (substr($phone_digits, 0, 2) === '63') {
            $mobile_digits = substr($phone_digits, 2);
        } else {
            $mobile_digits = $phone_digits;
        }

        // CORRECTED: Must be exactly 10 digits for mobile number
        if (strlen($mobile_digits) !== 10) {
            $digit_count = strlen($mobile_digits);
            if ($digit_count === 0) {
                $error_message = "Phone number is required. Please enter your complete mobile number.";
            } else if ($digit_count < 10) {
                $missing = 10 - $digit_count;
                $error_message = "Phone number is incomplete. You need {$missing} more digit" . ($missing > 1 ? 's' : '') . " for a complete mobile number. Current digits: {$digit_count}/10";
            } else {
                $error_message = "Phone number has too many digits. Please use exactly 10 digits after +63. Current digits: {$digit_count}/10";
            }
        }
        // Must start with 9 (Philippine mobile format)
        else if (substr($mobile_digits, 0, 1) !== '9') {
            $error_message = "Philippine mobile numbers must start with 9. Please enter a valid mobile number format: +63 9XXX XXX XXXX";
        }
        // Additional validation for realistic mobile number patterns
        else if (!preg_match('/^9\d{9}$/', $mobile_digits)) {
            $error_message = "Invalid mobile number format. Please enter a valid Philippine mobile number: +63 9XXX XXX XXXX";
        } else {
            // Format as +63 9XXX XXX XXXX (4-3-3 digit pattern)
            $formatted_phone = '+63 ' . substr($mobile_digits, 0, 4) . ' ' . substr($mobile_digits, 4, 3) . ' ' . substr($mobile_digits, 7, 3);
            $phone = $formatted_phone;

            // FIXED: Double-check the formatting - should be 12 digits total (63 + 10 mobile), not 13
            $verify_digits = preg_replace('/\D/', '', $phone);
            if (strlen($verify_digits) !== 12 || substr($verify_digits, 2) !== $mobile_digits) {
                $error_message = "Error formatting phone number. Please try again with format: +63 9XXX XXX XXXX";
            }
        }
    } else {
        $error_message = "Phone number is required. Please enter your mobile number.";
    }

    // Additional validation for other required fields
    if (!isset($error_message)) {
        $required_fields = [
            'full_name' => 'Full Name',
            'address' => 'Complete Address',
            'city' => 'City',
            'state' => 'State/Province',
            'postal_code' => 'Postal Code'
        ];

        foreach ($required_fields as $field => $label) {
            $value = $_POST[$field] ?? '';
            if (empty(trim($value))) {
                $error_message = "{$label} is required. Please fill in all required fields.";
                break;
            }
        }

        // Validate coordinates
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        if (empty($latitude) || empty($longitude)) {
            $error_message = "Location coordinates are required. Please set your location on the map first.";
        }
    }

    // Only proceed with database insert if validation passes
    if (!isset($error_message)) {
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
        if ($stmt) {
            $stmt->bind_param("isssssssdds", $user_id, $full_name, $phone, $address, $city, $state, $postal_code, $country, $latitude, $longitude, $notes);

            // Insert new billing address
            if ($stmt->execute()) {
                $new_address_id = $conn->insert_id;
                $success_message = "Billing address added successfully! Phone: {$phone} (11 digits verified) - Redirecting...";

                // Store the redirect script in a variable to output in the body
                $redirect_script = "
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Show success message with phone verification
                        console.log('Address added successfully with verified 11-digit phone: {$phone}');
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
                $error_message = "Error adding billing address to database. Please try again. SQL Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Database prepare error. Please contact support. Error: " . $conn->error;
        }
    }
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .field-updated {
            animation: fieldHighlight 1s ease-in-out;
        }

        @keyframes fieldHighlight {
            0% {
                background-color: #dbeafe;
            }

            100% {
                background-color: transparent;
            }
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
                                autocomplete="off">
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
                                    placeholder="Set location first to enable editing">
                            </div>

                            <!-- Phone with Updated Format -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input
                                    type="tel"
                                    name="phone"
                                    id="phoneInput"
                                    required
                                    readonly
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-500 focus:outline-none transition-all cursor-not-allowed form-field"
                                    placeholder="Set location first to enable editing"
                                    maxlength="13">
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
                                    placeholder="Set location first to enable editing"></textarea>
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
                                        placeholder="Set location first">
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
                                        placeholder="Set location first">
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
                                        placeholder="Set location first">
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
                                    placeholder="Country will be detected from location">
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
                                    placeholder="Set location first to add notes"></textarea>
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
                                class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-blue-600 hover:to-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Delivery Address
                            </button>
                            <a
                                href="profile.php"
                                class="flex-1 bg-gray-100 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-200 transition-all duration-200 text-center">
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

        // Enhanced Address Search Manager Class
        class AddressSearchManager {
            constructor() {
                this.searchTimeout = null;
                this.isSearching = false;
                this.selectedFromList = false;
            }

            // Initialize address search with improved Philippines support
            async searchAddress(query) {
                if (this.isSearching) return;

                this.isSearching = true;
                const loadingSpinner = document.getElementById('loadingSpinner');
                const searchIcon = document.getElementById('searchIcon');

                try {
                    loadingSpinner.style.display = 'block';
                    searchIcon.style.display = 'none';

                    // Try multiple geocoding services for better Philippines coverage
                    let results = [];

                    // Primary: Nominatim with Philippines focus
                    try {
                        const nominatimResponse = await fetch(
                            `https://nominatim.openstreetmap.org/search?` +
                            `format=json&q=${encodeURIComponent(query + ', Philippines')}&` +
                            `limit=3&addressdetails=1&countrycodes=ph&` +
                            `bounded=1&viewbox=116.5,4.5,127.5,21.5`
                        );
                        const nominatimResults = await nominatimResponse.json();
                        results = results.concat(nominatimResults.slice(0, 3));
                    } catch (error) {
                        console.warn('Nominatim search failed:', error);
                    }

                    // Secondary: Try broader search if no results
                    if (results.length === 0) {
                        try {
                            const broadResponse = await fetch(
                                `https://nominatim.openstreetmap.org/search?` +
                                `format=json&q=${encodeURIComponent(query)}&` +
                                `limit=5&addressdetails=1&countrycodes=ph`
                            );
                            const broadResults = await broadResponse.json();
                            results = results.concat(broadResults);
                        } catch (error) {
                            console.warn('Broad search failed:', error);
                        }
                    }

                    this.showSuggestions(results, query);

                } catch (error) {
                    console.error('Search error:', error);
                    this.showSearchError();
                } finally {
                    this.isSearching = false;
                    loadingSpinner.style.display = 'none';
                    searchIcon.style.display = 'block';
                }
            }

            // Enhanced suggestions display with better Philippines addresses
            showSuggestions(places, originalQuery) {
                const suggestionsDiv = document.getElementById('suggestions');
                suggestionsDiv.innerHTML = '';

                if (places.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'p-4 text-center';
                    noResults.innerHTML = `
                    <div class="text-gray-500 mb-2">No exact matches found for "${originalQuery}"</div>
                    <div class="text-sm text-gray-400">
                        Try searching with:
                        <ul class="mt-1 text-left">
                            <li>• Barangay name + City (e.g., "Barangay 1 Caloocan")</li>
                            <li>• Street name + City (e.g., "Rizal Avenue Manila")</li>
                            <li>• Landmark + City (e.g., "SM North EDSA")</li>
                        </ul>
                    </div>
                `;
                    suggestionsDiv.appendChild(noResults);
                    suggestionsDiv.classList.remove('hidden');
                    return;
                }

                places.forEach((place, index) => {
                    const suggestionItem = document.createElement('div');
                    suggestionItem.className = 'suggestion-item p-4 cursor-pointer border-b border-gray-100 hover:bg-gray-50 transition-colors';

                    const address = place.address || {};
                    const displayName = place.display_name;

                    // Build better address display for Philippines
                    let mainAddress = '';
                    let locationDetails = '';

                    if (address.house_number || address.road) {
                        mainAddress = [address.house_number, address.road].filter(Boolean).join(' ');
                    } else if (address.suburb || address.neighbourhood) {
                        mainAddress = address.suburb || address.neighbourhood;
                    } else {
                        mainAddress = displayName.split(',')[0];
                    }

                    // Build location hierarchy
                    const locationParts = [
                        address.village || address.suburb || address.neighbourhood,
                        address.city || address.town || address.municipality,
                        address.state || address.province,
                        'Philippines'
                    ].filter(Boolean);

                    locationDetails = locationParts.join(', ');

                    suggestionItem.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-blue-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900 text-sm">${mainAddress}</div>
                            <div class="text-xs text-gray-600 mt-1">${locationDetails}</div>
                            ${place.type ? `<div class="text-xs text-blue-600 mt-1">${this.formatPlaceType(place.type)}</div>` : ''}
                        </div>
                        <div class="text-xs text-gray-400 ml-2">Click to select</div>
                    </div>
                `;

                    suggestionItem.addEventListener('click', () => {
                        this.selectedFromList = true;
                        this.selectPlace(place);
                        this.hideSuggestions();
                    });

                    // Add data attribute for keyboard navigation
                    suggestionItem.setAttribute('data-index', index);

                    suggestionsDiv.appendChild(suggestionItem);
                });

                suggestionsDiv.classList.remove('hidden');
            }

            formatPlaceType(type) {
                const typeMap = {
                    'residential': 'Residential Area',
                    'commercial': 'Commercial Area',
                    'retail': 'Shopping Area',
                    'amenity': 'Amenity',
                    'building': 'Building',
                    'highway': 'Road/Highway'
                };
                return typeMap[type] || type.charAt(0).toUpperCase() + type.slice(1);
            }

            showSearchError() {
                const suggestionsDiv = document.getElementById('suggestions');
                suggestionsDiv.innerHTML = `
                <div class="p-4 text-center text-red-600">
                    <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>Search temporarily unavailable</div>
                    <div class="text-sm text-gray-500 mt-1">Please try again or set your location manually on the map</div>
                </div>
            `;
                suggestionsDiv.classList.remove('hidden');
            }

            selectPlace(place) {
                const lat = parseFloat(place.lat);
                const lng = parseFloat(place.lon);

                // Update map and marker
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);

                // Update coordinates
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;

                // Update search input
                const address = place.address || {};
                const mainAddress = [
                    address.house_number,
                    address.road,
                    address.suburb || address.neighbourhood
                ].filter(Boolean).join(' ') || place.display_name.split(',')[0];

                document.getElementById('addressSearch').value = mainAddress;

                // Fill form fields
                this.fillAddressFields(address, place.display_name);
                showLocationSetSuccess();
            }

            fillAddressFields(address, displayName) {
                // Fill address components
                const addressComponents = [
                    address.house_number,
                    address.road,
                    address.suburb || address.neighbourhood || address.village
                ].filter(Boolean);

                if (addressComponents.length > 0) {
                    document.getElementById('address').value = addressComponents.join(' ');
                    highlightField(document.getElementById('address'), 'addressAutoFillIndicator');
                } else {
                    document.getElementById('address').value = displayName.split(',')[0];
                    highlightField(document.getElementById('address'), 'addressAutoFillIndicator');
                }

                // Fill city
                const cityValue = address.city || address.town || address.municipality || address.county;
                if (cityValue) {
                    document.getElementById('city').value = cityValue;
                    highlightField(document.getElementById('city'), 'cityAutoFillIndicator');
                }

                // Fill state
                const stateValue = address.state || address.province || address.region;
                if (stateValue) {
                    document.getElementById('state').value = stateValue;
                    highlightField(document.getElementById('state'), 'stateAutoFillIndicator');
                }

                // Fill postal code
                if (address.postcode) {
                    document.getElementById('postal_code').value = address.postcode;
                    highlightField(document.getElementById('postal_code'), 'postalAutoFillIndicator');
                }

                // Set country
                document.getElementById('country').value = 'Philippines';
                highlightField(document.getElementById('country'), 'countryAutoFillIndicator');
            }

            hideSuggestions() {
                document.getElementById('suggestions').classList.add('hidden');
            }

            // Handle keyboard navigation
            handleKeyNavigation(e) {
                const suggestionsDiv = document.getElementById('suggestions');
                const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');

                if (suggestions.length === 0) return false;

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.navigateSuggestions(suggestions, 'down');
                        return true;

                    case 'ArrowUp':
                        e.preventDefault();
                        this.navigateSuggestions(suggestions, 'up');
                        return true;

                    case 'Enter':
                        e.preventDefault();
                        const activeSuggestion = suggestionsDiv.querySelector('.suggestion-item.active');
                        if (activeSuggestion && !this.selectedFromList) {
                            activeSuggestion.click();
                        }
                        return true;

                    case 'Escape':
                        this.hideSuggestions();
                        return true;
                }

                return false;
            }

            navigateSuggestions(suggestions, direction) {
                const currentActive = document.querySelector('.suggestion-item.active');
                let newIndex = 0;

                if (currentActive) {
                    const currentIndex = parseInt(currentActive.getAttribute('data-index'));
                    currentActive.classList.remove('active');

                    if (direction === 'down') {
                        newIndex = (currentIndex + 1) % suggestions.length;
                    } else {
                        newIndex = currentIndex === 0 ? suggestions.length - 1 : currentIndex - 1;
                    }
                }

                suggestions[newIndex].classList.add('active');
                suggestions[newIndex].scrollIntoView({
                    block: 'nearest'
                });
            }
        }

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
                switch (fieldName) {
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

        // Select a place from suggestions or map click
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
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 600000
                    }
                );
            }
        }

        // CORRECTED phone formatting function
        function formatPhoneNumber(value) {
            // Remove all non-digits
            let cleaned = value.replace(/[^\d]/g, '');

            // Remove leading 63 if present
            if (cleaned.startsWith('63')) {
                cleaned = cleaned.substring(2);
            }

            // Ensure it starts with 9 for mobile numbers
            if (cleaned.length > 0 && !cleaned.startsWith('9')) {
                cleaned = '9' + cleaned.substring(1);
            }

            // Limit to 10 digits (not 11) - CORRECTED
            cleaned = cleaned.substring(0, 10);

            // Format as +63 9XXX XXX XXXX - CORRECTED formatting
            if (cleaned.length === 0) {
                return '+63 ';
            } else if (cleaned.length <= 4) {
                return '+63 ' + cleaned;
            } else if (cleaned.length <= 7) {
                return '+63 ' + cleaned.substring(0, 4) + ' ' + cleaned.substring(4);
            } else {
                return '+63 ' + cleaned.substring(0, 4) + ' ' + cleaned.substring(4, 7) + ' ' + cleaned.substring(7);
            }
        }

        // Enhanced form validation with stricter phone validation
        // CORRECTED JavaScript phone validation function
        function validatePhoneNumber(phoneValue) {
            console.log('Validating phone:', phoneValue);

            // Check if phone is empty or just the prefix
            if (!phoneValue || phoneValue.trim() === '' || phoneValue.trim() === '+63' || phoneValue.trim() === '+63 ') {
                console.log('Phone validation failed: Empty or prefix only');
                return false;
            }

            // Remove all non-digits
            const digits = phoneValue.replace(/[^\d]/g, '');
            console.log('Phone digits extracted:', digits, 'Length:', digits.length);

            // CORRECTED: Must have exactly 12 digits (63 + 10 mobile digits), not 13
            if (digits.length !== 12) {
                console.log('Phone validation failed: Wrong digit count. Expected 12, got', digits.length);
                return false;
            }

            // Must start with 63 (Philippines country code)
            if (!digits.startsWith('63')) {
                console.log('Phone validation failed: Does not start with 63');
                return false;
            }

            // The mobile number part (after 63) must start with 9
            if (digits.charAt(2) !== '9') {
                console.log('Phone validation failed: Mobile part does not start with 9');
                return false;
            }

            // Must match the exact format: +63 9XXX XXX XXXX
            const phoneRegex = /^\+63 9\d{3} \d{3} \d{4}$/;
            const isValidFormat = phoneRegex.test(phoneValue.trim());

            console.log('Phone format validation result:', isValidFormat);
            console.log('Expected format: +63 9XXX XXX XXXX');
            return isValidFormat;
        }

        // CORRECTED enhanced form validation
        function enhancedValidateForm() {
            const requiredFields = ['full_name', 'phone', 'address', 'city', 'state', 'postal_code'];
            let isValid = true;
            let errorMessages = [];

            console.log('Starting enhanced form validation...');

            // Clear previous error states
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.classList.remove('border-red-500', 'border-red-400');
                }
            });

            // Check all required fields first
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                const fieldValue = field ? field.value.trim() : '';

                console.log(`Checking required field ${fieldName}:`, fieldValue);

                if (!field || !fieldValue) {
                    if (field) {
                        field.classList.add('border-red-500');
                    }
                    isValid = false;

                    const fieldLabels = {
                        'full_name': 'Full Name',
                        'phone': 'Phone Number',
                        'address': 'Complete Address',
                        'city': 'City',
                        'state': 'State/Province',
                        'postal_code': 'Postal Code'
                    };
                    errorMessages.push(`${fieldLabels[fieldName]} is required`);
                }
            });

            // CORRECTED phone validation
            const phoneField = document.querySelector('[name="phone"]');
            if (phoneField) {
                const phoneValue = phoneField.value.trim();

                console.log('Phone validation check:', {
                    phoneValue: phoneValue,
                    isEmpty: !phoneValue || phoneValue === '' || phoneValue === '+63' || phoneValue === '+63 ',
                    length: phoneValue.length
                });

                if (!phoneValue || phoneValue === '' || phoneValue === '+63' || phoneValue === '+63 ') {
                    console.log('Phone validation FAILED: Field is empty or contains only prefix');
                    phoneField.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('Phone number is required. Please enter your complete mobile number in format: +63 9XXX XXX XXXX');
                } else if (!validatePhoneNumber(phoneValue)) {
                    console.log('Phone validation FAILED: Invalid format or incomplete');
                    phoneField.classList.add('border-red-500');
                    isValid = false;

                    const digits = phoneValue.replace(/[^\d]/g, '');

                    if (digits.length === 0) {
                        errorMessages.push('Phone number is required. Please enter a valid Philippine mobile number.');
                    } else if (digits.length < 12) { // CORRECTED: changed from 13 to 12
                        const actualMobileDigits = Math.max(0, digits.length - 2);
                        const needed = 10 - actualMobileDigits;
                        errorMessages.push(`Phone number is incomplete. You need ${needed} more digit${needed > 1 ? 's' : ''} for a complete mobile number. Current mobile digits: ${actualMobileDigits}/10`);
                    } else if (digits.length > 12) { // CORRECTED: changed from 13 to 12
                        errorMessages.push('Phone number has too many digits. Please use format: +63 9XXX XXX XXXX');
                    } else if (!digits.startsWith('639')) {
                        if (!digits.startsWith('63')) {
                            errorMessages.push('Phone number must include Philippines country code +63');
                        } else {
                            errorMessages.push('Philippine mobile numbers must start with +63 9. Format: +63 9XXX XXX XXXX');
                        }
                    } else {
                        errorMessages.push('Phone number format is incorrect. Please use: +63 9XXX XXX XXXX (example: +63 9171 234 5678)');
                    }
                } else {
                    console.log('Phone validation PASSED');
                    phoneField.classList.remove('border-red-500', 'border-red-400');
                }
            }

            // Location validation
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;

            if (!lat || !lng || lat === '' || lng === '') {
                errorMessages.push('Please set your location on the map first by clicking on it or searching for your address');
                isValid = false;
            }

            console.log('Final form validation result:', {
                isValid,
                errorMessages
            });

            if (!isValid) {
                showEnhancedValidationErrors(errorMessages);

                const firstErrorField = document.querySelector('.border-red-500');
                if (firstErrorField) {
                    firstErrorField.focus();
                    firstErrorField.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }

            return isValid;
        }
        // LOCATION 3: Enhanced submit button event handler (around line 660-690 in your original code)

        // Enhanced form validation with STRICT submission prevention
        const form = document.querySelector('form');
        const submitButton = document.querySelector('button[type="submit"]');

        if (form && submitButton) {
            console.log('Setting up strict form validation...');

            // Remove any existing event listeners by cloning the button
            const newSubmitButton = submitButton.cloneNode(true);
            submitButton.parentNode.replaceChild(newSubmitButton, submitButton);

            // Add STRICT click handler to submit button
            newSubmitButton.addEventListener('click', function(e) {
                console.log('=== SUBMIT BUTTON CLICKED ===');

                // ALWAYS prevent the default behavior first
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // Remove any existing error messages
                const existingError = document.querySelector('.validation-error-message');
                if (existingError) {
                    existingError.remove();
                }

                console.log('Running strict form validation...');

                // Run STRICT validation
                const isFormValid = enhancedValidateForm();

                console.log('=== VALIDATION RESULT ===', {
                    isFormValid
                });

                if (isFormValid) {
                    console.log('✓ Form validation PASSED - submitting form');

                    // Show loading state
                    newSubmitButton.disabled = true;
                    newSubmitButton.innerHTML = `
                <svg class="w-5 h-5 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Adding Address...
            `;

                    // Submit the form
                    setTimeout(() => {
                        form.submit();
                    }, 100);

                } else {
                    console.log('✗ Form validation FAILED - submission blocked');

                    // Add visual feedback for failed submission
                    newSubmitButton.classList.add('bg-red-500', 'hover:bg-red-600');
                    newSubmitButton.innerHTML = `
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Please Fix Errors
            `;

                    // Reset button after 3 seconds
                    setTimeout(() => {
                        newSubmitButton.classList.remove('bg-red-500', 'hover:bg-red-600');
                        newSubmitButton.innerHTML = `
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Delivery Address
                `;
                    }, 3000);
                }

                // Always return false to prevent any default submission
                return false;
            });

            // BLOCK ALL form submit events as a backup
            form.addEventListener('submit', function(e) {
                console.log('Form submit event intercepted and blocked');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            });

            // BLOCK ALL other potential submission methods
            form.onsubmit = function() {
                console.log('Form onsubmit intercepted and blocked');
                return false;
            };

            console.log('✓ Strict form validation handlers attached successfully');
        } else {
            console.error('❌ Form or submit button not found!', {
                form,
                submitButton
            });
        }

        function showEnhancedValidationErrors(messages) {
            // Remove existing error messages
            const existingError = document.querySelector('.validation-error-message');
            if (existingError) {
                existingError.remove();
            }

            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error-message mb-6 bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg fade-in';

            let errorContent = `
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="flex-1">
                    <h4 class="font-semibold text-red-800 mb-3">Please correct the following errors:</h4>
                    <div class="space-y-2">
        `;

            messages.forEach((message, index) => {
                const isPhoneError = message.toLowerCase().includes('phone');
                const iconClass = isPhoneError ? 'text-yellow-600' : 'text-red-600';
                const icon = isPhoneError ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';

                errorContent += `
                <div class="flex items-start text-sm">
                    <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0 ${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${icon}
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            });

            errorContent += `
                    </div>
                </div>
            </div>
        `;

            errorDiv.innerHTML = errorContent;

            const form = document.querySelector('form');
            form.insertBefore(errorDiv, form.firstChild);

            // Scroll to error message
            errorDiv.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            // Auto remove after 10 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 10000);
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const addressManager = new AddressSearchManager();
            const searchInput = document.getElementById('addressSearch');

            // Initialize map
            initMap();

            // Enhanced search input handler
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                addressManager.selectedFromList = false; // Reset selection flag

                if (addressManager.searchTimeout) {
                    clearTimeout(addressManager.searchTimeout);
                }

                if (query.length < 3) {
                    addressManager.hideSuggestions();
                    return;
                }

                addressManager.searchTimeout = setTimeout(() => {
                    addressManager.searchAddress(query);
                }, 800); // Increased delay to reduce API calls
            });

            // Enhanced keyboard handling
            searchInput.addEventListener('keydown', function(e) {
                const handled = addressManager.handleKeyNavigation(e);

                if (e.key === 'Enter' && !handled) {
                    e.preventDefault();
                    // Don't auto-select, require manual selection
                    const suggestionsDiv = document.getElementById('suggestions');
                    if (!addressManager.selectedFromList && !suggestionsDiv.classList.contains('hidden')) {
                        // Show message to select from list
                        const notice = document.createElement('div');
                        notice.className = 'fixed top-32 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg shadow-lg z-50 fade-in';
                        notice.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5C3.312 17.333 4.308 19 5.85 19z"></path>
                            </svg>
                            Please click on a search result to select your address
                        </div>
                    `;
                        document.body.appendChild(notice);

                        setTimeout(() => {
                            notice.remove();
                        }, 4000);
                    }
                    return false;
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                const suggestionsDiv = document.getElementById('suggestions');

                if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    addressManager.hideSuggestions();
                }
            });

            // Enhanced phone input with real-time validation feedback
            // Try multiple possible IDs for phone input
            const phoneInput = document.getElementById('phoneInput') ||
                document.querySelector('input[name="phone"]') ||
                document.querySelector('input[type="tel"]');

            if (phoneInput) {
                console.log('Phone input found:', phoneInput); // Debug log

                // Set initial value
                if (!phoneInput.value || phoneInput.value.trim() === '') {
                    phoneInput.value = '+63 ';
                }

                phoneInput.addEventListener('input', function(e) {
                    console.log('Phone input changed:', e.target.value); // Debug log

                    const oldValue = e.target.value;
                    e.target.value = formatPhoneNumber(e.target.value);

                    // Real-time validation feedback
                    const isValid = validatePhoneNumber(e.target.value);
                    const digits = e.target.value.replace(/[^\d]/g, '');

                    console.log('Phone validation:', {
                        value: e.target.value,
                        digits: digits,
                        digitCount: digits.length,
                        isValid: isValid
                    }); // Debug log

                    // Remove existing validation classes
                    e.target.classList.remove('border-green-400', 'border-yellow-400', 'border-red-400', 'border-gray-300');

                    if (e.target.value === '+63 ' || e.target.value === '') {
                        // Empty state
                        e.target.classList.add('border-gray-300');
                    } else if (isValid) {
                        // Valid phone number
                        e.target.classList.add('border-green-400');
                        console.log('Phone is VALID'); // Debug log
                    } else if (digits.length < 13) {
                        // Incomplete
                        e.target.classList.add('border-yellow-400');
                        console.log('Phone is INCOMPLETE'); // Debug log
                    } else {
                        // Invalid
                        e.target.classList.add('border-red-400');
                        console.log('Phone is INVALID'); // Debug log
                    }
                });

                phoneInput.addEventListener('keydown', function(e) {
                    const cursorPosition = e.target.selectionStart;

                    // Prevent Enter key
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }

                    // Prevent deletion of +63 prefix
                    if ((e.key === 'Backspace' || e.key === 'Delete') && cursorPosition <= 4) {
                        e.preventDefault();
                    }
                });

                phoneInput.addEventListener('focus', function(e) {
                    if (!e.target.value.startsWith('+63 ')) {
                        e.target.value = '+63 ';
                    }

                    setTimeout(() => {
                        if (e.target.selectionStart <= 4) {
                            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
                        }
                    }, 10);
                });
            } else {
                console.error('Phone input not found! Available inputs:', document.querySelectorAll('input'));
            }

            // Prevent Enter key from submitting the form on any input field
            document.querySelectorAll('input, textarea').forEach(function(element) {
                element.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        // Only allow Enter for address search (handled separately above)
                        if (e.target.id !== 'addressSearch') {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            });

            // Enhanced form validation with STRICT submission prevention
            const form = document.querySelector('form');
            const submitButton = document.querySelector('button[type="submit"]');

            if (form && submitButton) {
                console.log('Setting up strict form validation...');

                // Remove any existing event listeners by cloning the button
                const newSubmitButton = submitButton.cloneNode(true);
                submitButton.parentNode.replaceChild(newSubmitButton, submitButton);

                // Add STRICT click handler to submit button
                newSubmitButton.addEventListener('click', function(e) {
                    console.log('=== SUBMIT BUTTON CLICKED ===');

                    // ALWAYS prevent the default behavior first
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    // Remove any existing error messages
                    const existingError = document.querySelector('.validation-error-message');
                    if (existingError) {
                        existingError.remove();
                    }

                    console.log('Running strict form validation...');

                    // Run STRICT validation
                    const isFormValid = enhancedValidateForm();

                    console.log('=== VALIDATION RESULT ===', {
                        isFormValid
                    });

                    if (isFormValid) {
                        console.log('✓ Form validation PASSED - submitting form');

                        // Show loading state
                        newSubmitButton.disabled = true;
                        newSubmitButton.innerHTML = `
                <svg class="w-5 h-5 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Adding Address...
            `;

                        // Submit the form
                        setTimeout(() => {
                            form.submit();
                        }, 100);

                    } else {
                        console.log('✗ Form validation FAILED - submission blocked');

                        // Add visual feedback for failed submission
                        newSubmitButton.classList.add('bg-red-500', 'hover:bg-red-600');
                        newSubmitButton.innerHTML = `
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Please Fix Errors
            `;

                        // Reset button after 3 seconds
                        setTimeout(() => {
                            newSubmitButton.classList.remove('bg-red-500', 'hover:bg-red-600');
                            newSubmitButton.innerHTML = `
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Delivery Address
                `;
                        }, 3000);
                    }

                    // Always return false to prevent any default submission
                    return false;
                });

                // BLOCK ALL form submit events as a backup
                form.addEventListener('submit', function(e) {
                    console.log('Form submit event intercepted and blocked');
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                });

                // BLOCK ALL other potential submission methods
                form.onsubmit = function() {
                    console.log('Form onsubmit intercepted and blocked');
                    return false;
                };

                console.log('✓ Strict form validation handlers attached successfully');
            } else {
                console.error('❌ Form or submit button not found!', {
                    form,
                    submitButton
                });
            }

            // Prevent form submission on Enter for address search input specifically
            document.getElementById('addressSearch').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    return false;
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