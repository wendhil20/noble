<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'logistic']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Handle delivery zone deletion
if (isset($_GET['delete']) && isset($_GET['zone_id'])) {
    $zone_id = mysqli_real_escape_string($conn, $_GET['zone_id']);

    $check_query = "SELECT zone_name FROM delivery_zones WHERE id = '$zone_id'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $zone_data = mysqli_fetch_assoc($check_result);
        $delete_query = "DELETE FROM delivery_zones WHERE id = '$zone_id'";

        if (mysqli_query($conn, $delete_query)) {
            $success_message = "Zone '{$zone_data['zone_name']}' deleted successfully!";
        } else {
            $error_message = "Error deleting zone: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Zone not found!";
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Get all delivery zones
$zones_query = "SELECT * FROM delivery_zones ORDER BY id ASC";
$zones_result = mysqli_query($conn, $zones_query);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zone_name = mysqli_real_escape_string($conn, $_POST['zone_name']);
    $zone_code = mysqli_real_escape_string($conn, $_POST['zone_code']);
    $base_fee = mysqli_real_escape_string($conn, $_POST['base_fee']);
    $included_km = mysqli_real_escape_string($conn, $_POST['included_km']);
    $per_km_rate = mysqli_real_escape_string($conn, $_POST['per_km_rate']);
    $is_free_delivery = isset($_POST['is_free_delivery']) ? 1 : 0;
    $location_name = mysqli_real_escape_string($conn, $_POST['location_name']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);

    if (isset($_POST['zone_id']) && $_POST['zone_id'] != '') {
        $zone_id = mysqli_real_escape_string($conn, $_POST['zone_id']);

        $query = "UPDATE delivery_zones SET 
                  zone_name = '$zone_name',
                  zone_code = '$zone_code',
                  base_fee = '$base_fee', 
                  included_km = '$included_km', 
                  per_km_rate = '$per_km_rate', 
                  is_free_delivery = '$is_free_delivery'
                  WHERE id = '$zone_id'";

        $success_msg = "Zone updated successfully!";
    } else {
        $check_code = "SELECT id FROM delivery_zones WHERE zone_code = '$zone_code'";
        $code_result = mysqli_query($conn, $check_code);

        if (mysqli_num_rows($code_result) > 0) {
            $error_message = "Zone code already exists! Please use a different code.";
        } else {
            $query = "INSERT INTO delivery_zones (zone_name, zone_code, base_fee, included_km, per_km_rate, is_free_delivery) 
                      VALUES ('$zone_name', '$zone_code', '$base_fee', '$included_km', '$per_km_rate', '$is_free_delivery')";

            $success_msg = "New zone added successfully!";
        }
    }

    if (!isset($error_message)) {
        $location_query = "SELECT * FROM delivery_settings LIMIT 1";
        $location_result = mysqli_query($conn, $location_query);

        if (mysqli_num_rows($location_result) > 0) {
            $location_update = "UPDATE delivery_settings SET 
                               location_name = '$location_name', 
                               latitude = '$latitude', 
                               longitude = '$longitude'";
            mysqli_query($conn, $location_update);
        } else {
            $location_insert = "INSERT INTO delivery_settings (location_name, latitude, longitude, base_fee, per_km_rate, total_km_base_fee) 
                               VALUES ('$location_name', '$latitude', '$longitude', 0, 0, 0)";
            mysqli_query($conn, $location_insert);
        }

        if (mysqli_query($conn, $query)) {
            $success_message = $success_msg;
            $zones_result = mysqli_query($conn, $zones_query);
        } else {
            $error_message = "Error: " . mysqli_error($conn);
        }
    }
}

// Get current location data
$location_query = "SELECT * FROM delivery_settings ORDER BY id DESC LIMIT 1";
$location_result = mysqli_query($conn, $location_query);
$existing_location = mysqli_fetch_assoc($location_result);

$is_edit_mode = isset($_GET['edit']) && isset($_GET['zone_id']);
$is_add_mode = isset($_GET['add']);
$edit_zone_id = isset($_GET['zone_id']) ? $_GET['zone_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Zones Management - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        #map, #view-map {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            z-index: 1;
        }

        #view-map {
            height: 300px;
        }

        .leaflet-control {
            z-index: 500 !important;
        }

        .leaflet-marker-icon {
            z-index: 600 !important;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1002;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s ease;
        }

        .search-result-item:hover {
            background-color: #f9fafb;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Delivery Management System</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 flex justify-between items-center" id="success-alert">
                <span><?php echo $success_message; ?></span>
                <button onclick="this.parentElement.style.display='none'" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 flex justify-between items-center" id="error-alert">
                <span><?php echo $error_message; ?></span>
                <button onclick="this.parentElement.style.display='none'" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!$is_edit_mode && !$is_add_mode): ?>
         
            <!-- Location Display -->
            <?php if ($existing_location): ?>
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Warehouse Location</h2>
                        <a href="?edit=1&zone_id=1" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                            Edit Location
                        </a>
                    </div>

                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($existing_location['location_name']); ?></h3>
                        <p class="text-gray-600 text-sm">Coordinates: <?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?></p>
                    </div>

                    <div id="view-map" class="rounded-lg overflow-hidden"></div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const viewMap = L.map('view-map').setView([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>], 15);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(viewMap);

                        L.marker([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>])
                            .addTo(viewMap)
                            .bindPopup('<?php echo htmlspecialchars($existing_location['location_name']); ?>')
                            .openPopup();
                    });

                    function confirmDelete(zoneId, zoneName) {
                        if (confirm(`Are you sure you want to delete the zone "${zoneName}"?\n\nThis action cannot be undone.`)) {
                            window.location.href = `?delete=1&zone_id=${zoneId}`;
                        }
                    }
                </script>
            <?php endif; ?>

        <?php elseif ($is_add_mode): ?>
            <!-- Add Zone Form -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-plus"></i> Add New Delivery Zone</h2>
                        <a href="?" class="text-gray-600 hover:text-gray-900 text-xl">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Zone Name <span class="text-red-600">*</span></label>
                            <input type="text" name="zone_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="e.g., Metro Manila, Quezon City" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Zone Code <span class="text-red-600">*</span></label>
                            <input type="text" name="zone_code" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="e.g., MM, QC, CAVITE" maxlength="10" required>
                        </div>

                        <div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_free_delivery" id="is_free_delivery" onchange="toggleFreeDelivery()" class="w-5 h-5">
                                <span class="text-sm font-semibold text-gray-700">Free Delivery Zone</span>
                            </label>
                        </div>

                        <div id="pricing-fields" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Base Fee <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">₱</span>
                                    <input type="number" name="base_fee" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" step="0.01"
                                        min="0" placeholder="0.00" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Included KM <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <input type="number" name="included_km" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" step="0.01"
                                        min="0" placeholder="0.00" required>
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">KM</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Per KM Rate <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">₱</span>
                                    <input type="number" name="per_km_rate" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" step="0.01"
                                        min="0" placeholder="0.00" required>
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">per KM</span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-6">

                        <h3 class="text-lg font-semibold text-gray-800">Warehouse Location</h3>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Location Name <span class="text-red-600">*</span></label>
                            <input type="text" name="location_name" id="location_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>" required>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Latitude <span class="text-red-600">*</span></label>
                                <input type="number" name="latitude" id="latitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" step="0.0000001"
                                    value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>" readonly required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Longitude <span class="text-red-600">*</span></label>
                                <input type="number" name="longitude" id="longitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" step="0.0000001"
                                    value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>" readonly required>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-plus"></i> Add Zone
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Location on Map</h3>
                    
                    <div class="relative mb-4 z-10">
                        <input type="text" id="map-search" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Search for a location...">
                        <div id="search-results" class="search-results" style="display: none;"></div>
                    </div>

                    <div id="map" class="rounded-lg overflow-hidden"></div>
                    <p class="text-gray-600 text-sm mt-4">Click on the map or search to select a location</p>
                </div>
            </div>

        <?php else: ?>
            <!-- Edit Zone Form -->
            <?php
            $edit_zone_query = "SELECT * FROM delivery_zones WHERE id = '$edit_zone_id'";
            $edit_zone_result = mysqli_query($conn, $edit_zone_query);
            $edit_zone = mysqli_fetch_assoc($edit_zone_result);

            if (!$edit_zone) {
                echo "<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-4'>Zone not found!</div>";
                echo "<a href='?' class='bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg'>Back to Zones</a>";
                exit();
            }
            ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-edit"></i> Edit <?php echo htmlspecialchars($edit_zone['zone_name']); ?></h2>
                        <a href="?" class="text-gray-600 hover:text-gray-900 text-xl">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="zone_id" value="<?php echo $edit_zone['id']; ?>">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Zone Name <span class="text-red-600">*</span></label>
                            <input type="text" name="zone_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value="<?php echo htmlspecialchars($edit_zone['zone_name']); ?>" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Zone Code <span class="text-red-600">*</span></label>
                            <input type="text" name="zone_code" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value="<?php echo htmlspecialchars($edit_zone['zone_code']); ?>" maxlength="10" required>
                        </div>

                        <div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_free_delivery" id="is_free_delivery" <?php echo $edit_zone['is_free_delivery'] ? 'checked' : ''; ?> onchange="toggleFreeDelivery()" class="w-5 h-5">
                                <span class="text-sm font-semibold text-gray-700">Free Delivery Zone</span>
                            </label>
                        </div>

                        <div id="pricing-fields" style="<?php echo $edit_zone['is_free_delivery'] ? 'display: none;' : ''; ?>" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Base Fee <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">₱</span>
                                    <input type="number" name="base_fee" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01"
                                        value="<?php echo $edit_zone['base_fee']; ?>" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Included KM <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <input type="number" name="included_km" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01"
                                        value="<?php echo $edit_zone['included_km']; ?>" required>
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">KM</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Per KM Rate <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">₱</span>
                                    <input type="number" name="per_km_rate" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01"
                                        value="<?php echo $edit_zone['per_km_rate']; ?>" required>
                                    <span class="bg-gray-100 px-4 py-2 rounded-lg flex items-center">per KM</span>
                                </div>
                            </div>
                        </div>

                        <?php if ($edit_zone_id == 1 || !$existing_location): ?>
                            <hr class="my-6">

                            <h3 class="text-lg font-semibold text-gray-800">Warehouse Location</h3>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Location Name <span class="text-red-600">*</span></label>
                                <input type="text" name="location_name" id="location_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Latitude <span class="text-red-600">*</span></label>
                                    <input type="number" name="latitude" id="latitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.0000001"
                                        value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>" readonly required>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Longitude <span class="text-red-600">*</span></label>
                                    <input type="number" name="longitude" id="longitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.0000001"
                                        value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="location_name" value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>">
                            <input type="hidden" name="latitude" value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>">
                            <input type="hidden" name="longitude" value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>">
                        <?php endif; ?>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-save"></i> Update Zone
                        </button>
                    </form>
                </div>

                <?php if ($edit_zone_id == 1 || !$existing_location): ?>
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Location on Map</h3>
                        
                        <div class="relative mb-4 z-10">
                            <input type="text" id="map-search" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Search for a location...">
                            <div id="search-results" class="search-results" style="display: none;"></div>
                        </div>

                        <div id="map" class="rounded-lg overflow-hidden"></div>
                        <p class="text-gray-600 text-sm mt-4">Click on the map or search to select a location</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- JavaScript for map and forms -->
        <script>
            let map, marker, searchTimeout;

            function toggleFreeDelivery() {
                const checkbox = document.getElementById('is_free_delivery');
                const pricingFields = document.getElementById('pricing-fields');
                const feeInput = document.querySelector('input[name="base_fee"]');
                const kmInput = document.querySelector('input[name="included_km"]');
                const rateInput = document.querySelector('input[name="per_km_rate"]');

                if (checkbox && checkbox.checked) {
                    if (pricingFields) pricingFields.style.display = 'none';
                    if (feeInput) feeInput.value = '0.00';
                    if (kmInput) kmInput.value = '0.00';
                    if (rateInput) rateInput.value = '0.00';

                    if (feeInput) feeInput.removeAttribute('required');
                    if (kmInput) kmInput.removeAttribute('required');
                    if (rateInput) rateInput.removeAttribute('required');
                } else {
                    if (pricingFields) pricingFields.style.display = 'block';
                    if (feeInput) feeInput.setAttribute('required', 'required');
                    if (kmInput) kmInput.setAttribute('required', 'required');
                    if (rateInput) rateInput.setAttribute('required', 'required');
                }
            }

            <?php if ($is_add_mode || ($is_edit_mode && ($edit_zone_id == 1 || !$existing_location))): ?>
                function initMap() {
                    <?php if ($existing_location): ?>
                        map = L.map('map').setView([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>], 15);
                        marker = L.marker([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>]).addTo(map);
                    <?php else: ?>
                        map = L.map('map').setView([14.6760, 121.0437], 13);
                    <?php endif; ?>

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    map.on('click', function(e) {
                        const lat = e.latlng.lat;
                        const lng = e.latlng.lng;

                        document.getElementById('latitude').value = lat.toFixed(8);
                        document.getElementById('longitude').value = lng.toFixed(8);

                        if (marker) marker.setLatLng(e.latlng);
                        else marker = L.marker(e.latlng).addTo(map);

                        reverseGeocode(lat, lng);
                    });
                }

                const mapSearchInput = document.getElementById('map-search');
                if (mapSearchInput) {
                    mapSearchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        const query = this.value.trim();
                        if (query.length < 3) {
                            hideSearchResults();
                            return;
                        }
                        searchTimeout = setTimeout(() => searchLocation(query), 300);
                    });
                }

                function searchLocation(query) {
                    const resultsDiv = document.getElementById('search-results');
                    if (!resultsDiv) return;

                    resultsDiv.innerHTML = '<div class="p-3"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                    resultsDiv.style.display = 'block';

                    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=8&countrycodes=ph&addressdetails=1`;

                    fetch(url, { headers: { 'User-Agent': 'DeliveryZoneManager/1.0' } })
                        .then(response => response.json())
                        .then(data => displaySearchResults(data))
                        .catch(() => showFallbackLocations());
                }

                function displaySearchResults(data) {
                    const resultsDiv = document.getElementById('search-results');
                    if (!resultsDiv) return;
                    resultsDiv.innerHTML = '';

                    if (data.length === 0) {
                        showFallbackLocations();
                        return;
                    }

                    data.forEach((item) => {
                        const div = document.createElement('div');
                        div.className = 'search-result-item';
                        div.innerHTML = `<div class="font-semibold">${item.name || 'Location'}</div><small class="text-gray-500">${item.display_name}</small>`;
                        div.onclick = () => selectSearchResult(item);
                        resultsDiv.appendChild(div);
                    });

                    resultsDiv.style.display = 'block';
                }

                function showFallbackLocations() {
                    const resultsDiv = document.getElementById('search-results');
                    if (!resultsDiv) return;

                    const locations = [
                        { name: 'Quezon City', lat: 14.6760, lon: 121.0437, display_name: 'Quezon City, Metro Manila, Philippines' },
                        { name: 'Manila', lat: 14.5995, lon: 120.9842, display_name: 'Manila, Metro Manila, Philippines' },
                        { name: 'Makati', lat: 14.5547, lon: 121.0244, display_name: 'Makati, Metro Manila, Philippines' }
                    ];

                    resultsDiv.innerHTML = '';
                    locations.forEach(location => {
                        const div = document.createElement('div');
                        div.className = 'search-result-item';
                        div.innerHTML = `<div class="font-semibold">${location.name}</div><small class="text-gray-500">${location.display_name}</small>`;
                        div.onclick = () => selectSearchResult(location);
                        resultsDiv.appendChild(div);
                    });

                    resultsDiv.style.display = 'block';
                }

                function selectSearchResult(item) {
                    const lat = parseFloat(item.lat);
                    const lng = parseFloat(item.lon);

                    if (map) {
                        map.setView([lat, lng], 16);
                        if (marker) marker.setLatLng([lat, lng]);
                        else marker = L.marker([lat, lng]).addTo(map);
                    }

                    document.getElementById('latitude').value = lat.toFixed(8);
                    document.getElementById('longitude').value = lng.toFixed(8);
                    document.getElementById('location_name').value = item.display_name;
                    document.getElementById('map-search').value = '';

                    hideSearchResults();
                }

                function hideSearchResults() {
                    const resultsDiv = document.getElementById('search-results');
                    if (resultsDiv) resultsDiv.style.display = 'none';
                }

                function reverseGeocode(lat, lng) {
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`)
                        .then(response => response.json())
                        .then(data => {
                            const nameField = document.getElementById('location_name');
                            if (data.display_name && nameField) nameField.value = data.display_name;
                        })
                        .catch(() => {});
                }

                document.addEventListener('DOMContentLoaded', () => {
                    if (document.getElementById('map')) initMap();
                });

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.relative')) hideSearchResults();
                });
            <?php endif; ?>

            document.addEventListener('DOMContentLoaded', function() {
                if (document.getElementById('is_free_delivery')) toggleFreeDelivery();
                
                const alerts = document.querySelectorAll('[id$="-alert"]');
                alerts.forEach(alert => setTimeout(() => alert.style.display = 'none', 5000));
            });
        </script>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

</body>

</html>