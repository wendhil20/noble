<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Check if data exists (only 1 record allowed)
$check_query = "SELECT * FROM delivery_settings ORDER BY id DESC LIMIT 1";
$check_result = mysqli_query($conn, $check_query);
$existing_data = mysqli_fetch_assoc($check_result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base_fee = mysqli_real_escape_string($conn, $_POST['base_fee']);
    $per_km_rate = mysqli_real_escape_string($conn, $_POST['per_km_rate']);
    $total_km_base_fee = mysqli_real_escape_string($conn, $_POST['total_km_base_fee']);
    $location_name = mysqli_real_escape_string($conn, $_POST['location_name']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
    
    if ($existing_data) {
        // Update existing record
        $query = "UPDATE delivery_settings SET 
                  base_fee = '$base_fee', 
                  per_km_rate = '$per_km_rate', 
                  total_km_base_fee = '$total_km_base_fee', 
                  location_name = '$location_name', 
                  latitude = '$latitude', 
                  longitude = '$longitude' 
                  WHERE id = " . $existing_data['id'];
    } else {
        // Insert new record
        $query = "INSERT INTO delivery_settings (base_fee, per_km_rate, total_km_base_fee, location_name, latitude, longitude) 
                  VALUES ('$base_fee', '$per_km_rate', '$total_km_base_fee', '$location_name', '$latitude', '$longitude')";
    }
    
    if (mysqli_query($conn, $query)) {
        $success_message = "Data saved successfully!";
        // Refresh data
        $check_result = mysqli_query($conn, $check_query);
        $existing_data = mysqli_fetch_assoc($check_result);
    } else {
        $error_message = "Error: " . mysqli_error($conn);
    }
}

$is_edit_mode = isset($_GET['edit']) && $existing_data;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Data Management - Noble Admin</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        #map {
            height: 400px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .data-view-card {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }

        .data-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .card-header {
            background-color: #ff6b35 !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <?php include '../navbar/top.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($existing_data && !$is_edit_mode): ?>
    <!-- View Mode -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="data-view-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>📍 Current Delivery Settings</h4>
                    <a href="?edit=1" class="btn btn-light">Edit Data</a>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="data-item">
                            <small>Base Fee</small>
                            <h5>₱<?php echo number_format($existing_data['base_fee'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="data-item">
                            <small>Per KM Rate</small>
                            <h5>₱<?php echo number_format($existing_data['per_km_rate'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="data-item">
                            <small>Total KM for Base Fee</small>
                            <h5><?php echo $existing_data['total_km_base_fee']; ?> KM</h5>
                        </div>
                    </div>
                </div>
                
                <div class="data-item">
                    <small>Location</small>
                    <h5><?php echo htmlspecialchars($existing_data['location_name']); ?></h5>
                    <small>Coordinates: <?php echo $existing_data['latitude']; ?>, <?php echo $existing_data['longitude']; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Map View (View Only) -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>📍 Location on Map</h5>
                </div>
                <div class="card-body">
                    <div id="view-map" style="height: 300px; border: 1px solid #ddd; border-radius: 5px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View-only map
        document.addEventListener('DOMContentLoaded', function() {
            const viewMap = L.map('view-map').setView([<?php echo $existing_data['latitude']; ?>, <?php echo $existing_data['longitude']; ?>], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(viewMap);
            
            L.marker([<?php echo $existing_data['latitude']; ?>, <?php echo $existing_data['longitude']; ?>])
                .addTo(viewMap)
                .bindPopup('<?php echo htmlspecialchars($existing_data['location_name']); ?>')
                .openPopup();
        });
    </script>

    <?php else: ?>
    <!-- Input/Edit Mode -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><?php echo $is_edit_mode ? 'Edit Delivery Data' : 'Input Delivery Data'; ?></h5>
                    <?php if ($is_edit_mode): ?>
                        <a href="?" class="btn btn-secondary btn-sm">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Base Fee:</label>
                            <input type="number" name="base_fee" class="form-control" step="0.01" 
                                   value="<?php echo $is_edit_mode ? $existing_data['base_fee'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Per KM Rate:</label>
                            <input type="number" name="per_km_rate" class="form-control" step="0.01" 
                                   value="<?php echo $is_edit_mode ? $existing_data['per_km_rate'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Total KM for Base Fee:</label>
                            <input type="number" name="total_km_base_fee" class="form-control" step="0.1" 
                                   value="<?php echo $is_edit_mode ? $existing_data['total_km_base_fee'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name:</label>
                            <input type="text" name="location_name" id="location_name" class="form-control" 
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($existing_data['location_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude:</label>
                                    <input type="number" name="latitude" id="latitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $is_edit_mode ? $existing_data['latitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude:</label>
                                    <input type="number" name="longitude" id="longitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $is_edit_mode ? $existing_data['longitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $is_edit_mode ? 'Update Data' : 'Save Data'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Select Location on Map</h5>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="mb-3 position-relative">
                        <input type="text" id="map-search" class="form-control" placeholder="Search for a location...">
                        <div id="search-results" class="search-results" style="display: none;"></div>
                    </div>
                    
                    <!-- Map -->
                    <div id="map"></div>
                    <small class="text-muted mt-2 d-block">Click on the map or search to select a location</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        let map, marker;

        // Initialize map
        function initMap() {
            <?php if ($is_edit_mode): ?>
                // Edit mode - center on existing location
                map = L.map('map').setView([<?php echo $existing_data['latitude']; ?>, <?php echo $existing_data['longitude']; ?>], 15);
                marker = L.marker([<?php echo $existing_data['latitude']; ?>, <?php echo $existing_data['longitude']; ?>]).addTo(map);
            <?php else: ?>
                // New input mode - center on Quezon City
                map = L.map('map').setView([14.6760, 121.0437], 13);
            <?php endif; ?>
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add click event to map
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Update coordinates
                document.getElementById('latitude').value = lat.toFixed(8);
                document.getElementById('longitude').value = lng.toFixed(8);
                
                // Add/update marker
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
                
                // Get location name
                reverseGeocode(lat, lng);
            });
        }

        // Search functionality
        let searchTimeout;
        document.getElementById('map-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 3) {
                document.getElementById('search-results').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchLocation(query);
            }, 500);
        });

        function searchLocation(query) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=ph`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('search-results');
                    resultsDiv.innerHTML = '';
                    
                    if (data.length === 0) {
                        resultsDiv.innerHTML = '<div class="search-result-item">No results found</div>';
                    } else {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
                            div.textContent = item.display_name;
                            div.onclick = () => {
                                selectSearchResult(item);
                            };
                            resultsDiv.appendChild(div);
                        });
                    }
                    
                    resultsDiv.style.display = 'block';
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }

        function selectSearchResult(item) {
            const lat = parseFloat(item.lat);
            const lng = parseFloat(item.lon);
            
            // Update map
            map.setView([lat, lng], 15);
            
            // Update marker
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
            
            // Update form
            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);
            document.getElementById('location_name').value = item.display_name;
            
            // Hide search results
            document.getElementById('search-results').style.display = 'none';
            document.getElementById('map-search').value = '';
        }

        function reverseGeocode(lat, lng) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        document.getElementById('location_name').value = data.display_name;
                    }
                })
                .catch(error => {
                    console.error('Reverse geocoding error:', error);
                });
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.position-relative')) {
                document.getElementById('search-results').style.display = 'none';
            }
        });
    </script>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

</body>
</html>