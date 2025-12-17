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

// Table will be created manually in database

// Handle size deletion
if (isset($_GET['delete_size']) && isset($_GET['size_id'])) {
    $size_id = mysqli_real_escape_string($conn, $_GET['size_id']);
    
    $check_size_query = "SELECT size_name FROM delivery_sizes WHERE id = '$size_id'";
    $check_size_result = mysqli_query($conn, $check_size_query);
    
    if (mysqli_num_rows($check_size_result) > 0) {
        $size_data = mysqli_fetch_assoc($check_size_result);
        $delete_size_query = "DELETE FROM delivery_sizes WHERE id = '$size_id'";
        
        if (mysqli_query($conn, $delete_size_query)) {
            $success_message = "Size '{$size_data['size_name']}' deleted successfully!";
        } else {
            $error_message = "Error deleting size: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Size not found!";
    }
    
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
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

// Get all delivery zones and sizes
$zones_query = "SELECT * FROM delivery_zones ORDER BY id ASC";
$zones_result = mysqli_query($conn, $zones_query);

$sizes_query = "SELECT * FROM delivery_sizes ORDER BY percentage ASC";
$sizes_result = mysqli_query($conn, $sizes_query);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delivery size form
    if (isset($_POST['action']) && $_POST['action'] === 'manage_size') {
        $size_name = mysqli_real_escape_string($conn, $_POST['size_name']);
        $percentage = mysqli_real_escape_string($conn, $_POST['percentage']);
        
        if (isset($_POST['size_id']) && $_POST['size_id'] != '') {
            // Update existing size
            $size_id = mysqli_real_escape_string($conn, $_POST['size_id']);
            
            $query = "UPDATE delivery_sizes SET 
                      size_name = '$size_name',
                      percentage = '$percentage'
                      WHERE id = '$size_id'";
            
            $success_msg = "Size updated successfully!";
        } else {
            // Insert new size
            $check_name = "SELECT id FROM delivery_sizes WHERE size_name = '$size_name'";
            $name_result = mysqli_query($conn, $check_name);
            
            if (mysqli_num_rows($name_result) > 0) {
                $error_message = "Size name already exists! Please use a different name.";
            } else {
                $query = "INSERT INTO delivery_sizes (size_name, percentage) 
                          VALUES ('$size_name', '$percentage')";
                
                $success_msg = "New size added successfully!";
            }
        }
        
        if (!isset($error_message) && mysqli_query($conn, $query)) {
            $success_message = $success_msg;
            $sizes_result = mysqli_query($conn, $sizes_query);
        } else if (!isset($error_message)) {
            $error_message = "Error: " . mysqli_error($conn);
        }
    }
    // Handle delivery zone form
    else {
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
            // Update existing zone
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
            // Insert new zone
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
            // Also update location in delivery_settings
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
}

// Get current location data
$location_query = "SELECT * FROM delivery_settings ORDER BY id DESC LIMIT 1";
$location_result = mysqli_query($conn, $location_query);
$existing_location = mysqli_fetch_assoc($location_result);

$is_edit_mode = isset($_GET['edit']) && isset($_GET['zone_id']);
$is_add_mode = isset($_GET['add']);
$is_size_mode = isset($_GET['sizes']);
$is_edit_size_mode = isset($_GET['edit_size']) && isset($_GET['size_id']);
$is_add_size_mode = isset($_GET['add_size']);
$edit_zone_id = isset($_GET['zone_id']) ? $_GET['zone_id'] : null;
$edit_size_id = isset($_GET['size_id']) ? $_GET['size_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Zones Management - Noble Admin</title>
     <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
#map {
    height: 400px !important;
    width: 100% !important;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}

#view-map {
    height: 300px !important;
    width: 100% !important;
    position: relative;
    z-index: 1;
}

.leaflet-control {
    z-index: 500 !important;
}

.leaflet-marker-icon {
    z-index: 600 !important;
}
        
        .search-container {
            position: relative;
            z-index: 1001;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1002;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .zone-card {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .zone-card.free-delivery {
            background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
        }

        .size-card {
            background: linear-gradient(135deg, #6f42c1 0%, #8a63d2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .card-header {
            background-color: #ff6b35 !important;
            color: white !important;
        }

        .card-header.size-header {
            background-color: #6f42c1 !important;
        }

        .zone-details {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .delete-btn {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: white;
        }

        .delete-btn:hover {
            background: rgba(220, 53, 69, 0.8);
            color: white;
        }

        .nav-tabs .nav-link {
            color: #495057;
        }

        .nav-tabs .nav-link.active {
            background-color: #ff6b35;
            border-color: #ff6b35;
            color: white;
        }

        .percentage-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.1em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../navbar/top.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Delivery Management System</h2>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo (!$is_size_mode && !$is_add_size_mode && !$is_edit_size_mode) ? 'active' : ''; ?>" 
                    onclick="window.location.href='?'">
                <i class="fas fa-map-marked-alt"></i> Delivery Zones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($is_size_mode || $is_add_size_mode || $is_edit_size_mode) ? 'active' : ''; ?>" 
                    onclick="window.location.href='?sizes=1'">
                <i class="fas fa-boxes"></i> Package Sizes
            </button>
        </li>
    </ul>
    
    <?php if ($is_size_mode && !$is_add_size_mode && !$is_edit_size_mode): ?>
    <!-- Package Sizes Management -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>📦 Package Size Percentages</h4>
                <a href="?add_size=1" class="btn btn-purple" style="background-color: #6f42c1; border-color: #6f42c1; color: white;">
                    <i class="fas fa-plus"></i> Add New Size
                </a>
            </div>
            
            <?php 
            mysqli_data_seek($sizes_result, 0);
            if (mysqli_num_rows($sizes_result) == 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No package sizes found. <a href="?add_size=1">Add your first size</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php while ($size = mysqli_fetch_assoc($sizes_result)): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="size-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><?php echo htmlspecialchars($size['size_name']); ?></h5>
                                <div class="action-buttons">
                                    <a href="?edit_size=1&size_id=<?php echo $size['id']; ?>" class="btn btn-light btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm delete-btn" 
                                            onclick="confirmDeleteSize(<?php echo $size['id']; ?>, '<?php echo htmlspecialchars($size['size_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <div class="percentage-badge">
                                    <?php 
                                    // Display percentage without unnecessary decimals
                                    $displayPercentage = $size['percentage'];
                                    if ($displayPercentage == intval($displayPercentage)) {
                                        echo intval($displayPercentage);
                                    } else {
                                        echo number_format($displayPercentage, 1);
                                    }
                                    ?>%
                                </div>
                                <small class="d-block mt-2 opacity-75">Additional delivery fee percentage</small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($is_add_size_mode || $is_edit_size_mode): ?>
    <!-- Add/Edit Size Form -->
    <?php
    $edit_size = null;
    if ($is_edit_size_mode) {
        $edit_size_query = "SELECT * FROM delivery_sizes WHERE id = '$edit_size_id'";
        $edit_size_result = mysqli_query($conn, $edit_size_query);
        $edit_size = mysqli_fetch_assoc($edit_size_result);
        
        if (!$edit_size) {
            echo "<div class='alert alert-danger'>Size not found!</div>";
            echo "<a href='?sizes=1' class='btn btn-secondary'>Back to Sizes</a>";
            exit();
        }
    }
    ?>
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header size-header d-flex justify-content-between align-items-center">
                    <h5>
                        <i class="fas fa-<?php echo $is_add_size_mode ? 'plus' : 'edit'; ?>"></i> 
                        <?php echo $is_add_size_mode ? 'Add New Package Size' : 'Edit ' . htmlspecialchars($edit_size['size_name']); ?>
                    </h5>
                    <a href="?sizes=1" class="btn btn-light btn-sm">Cancel</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="manage_size">
                        <?php if ($is_edit_size_mode): ?>
                            <input type="hidden" name="size_id" value="<?php echo $edit_size['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Size Name: <span class="text-danger">*</span></label>
                            <input type="text" name="size_name" class="form-control" 
                                   value="<?php echo $edit_size ? htmlspecialchars($edit_size['size_name']) : ''; ?>"
                                   placeholder="e.g., Extra Small, Jumbo" required>
                            <div class="form-text">Enter a descriptive name for this package size</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Percentage: <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="percentage" class="form-control" step="0.1" min="0" max="100"
                                       value="<?php echo $edit_size ? $edit_size['percentage'] : ''; ?>"
                                       placeholder="0.0" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Additional percentage to add to delivery fee (0-100%)</div>
                        </div>
                        
                        <button type="submit" class="btn" style="background-color: #6f42c1; border-color: #6f42c1; color: white;">
                            <i class="fas fa-<?php echo $is_add_size_mode ? 'plus' : 'save'; ?>"></i> 
                            <?php echo $is_add_size_mode ? 'Add Size' : 'Update Size'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php elseif (!$is_edit_mode && !$is_add_mode): ?>
    

    <?php if ($existing_location): ?>
    <!-- Location Display -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>📍 Warehouse Location</h5>
                    <a href="?edit=1&zone_id=1" class="btn btn-light btn-sm">Edit Location</a>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($existing_location['location_name']); ?></h6>
                    <small class="text-muted">Coordinates: <?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?></small>
                    <div id="view-map" style="height: 300px; border: 1px solid #ddd; border-radius: 5px; margin-top: 15px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View-only map
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

        // Delete confirmation for zones
        function confirmDelete(zoneId, zoneName) {
            if (confirm(`Are you sure you want to delete the zone "${zoneName}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `?delete=1&zone_id=${zoneId}`;
            }
        }

        // Delete confirmation for sizes
        function confirmDeleteSize(sizeId, sizeName) {
            if (confirm(`Are you sure you want to delete the size "${sizeName}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `?delete_size=1&size_id=${sizeId}`;
            }
        }
    </script>
    <?php endif; ?>

    <?php elseif ($is_add_mode): ?>
    <!-- Add Mode - Rest of your existing add zone code -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-plus"></i> Add New Delivery Zone</h5>
                    <a href="?" class="btn btn-secondary btn-sm">Cancel</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Zone Name: <span class="text-danger">*</span></label>
                            <input type="text" name="zone_name" class="form-control" 
                                   placeholder="e.g., Metro Manila, Quezon City" required>
                            <div class="form-text">Enter a descriptive name for this delivery zone</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Zone Code: <span class="text-danger">*</span></label>
                            <input type="text" name="zone_code" class="form-control" 
                                   placeholder="e.g., MM, QC, CAVITE" maxlength="10" required>
                            <div class="form-text">Short unique code for this zone (max 10 characters)</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_free_delivery" 
                                       id="is_free_delivery" onchange="toggleFreeDelivery()">
                                <label class="form-check-label" for="is_free_delivery">
                                    <strong>Free Delivery Zone</strong>
                                </label>
                            </div>
                            <div class="form-text">Check this if delivery is free for this zone</div>
                        </div>
                        
                        <div id="pricing-fields">
                            <div class="mb-3">
                                <label class="form-label">Base Fee: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="base_fee" class="form-control" step="0.01" 
                                           min="0" placeholder="0.00" required>
                                </div>
                                <div class="form-text">Fixed delivery fee for this zone</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Included KM: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="included_km" class="form-control" step="0.01" 
                                           min="0" placeholder="0.00" required>
                                    <span class="input-group-text">KM</span>
                                </div>
                                <div class="form-text">Distance covered by the base fee</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Per KM Rate: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="per_km_rate" class="form-control" step="0.01" 
                                           min="0" placeholder="0.00" required>
                                    <span class="input-group-text">per KM</span>
                                </div>
                                <div class="form-text">Additional charge per kilometer beyond included KM</div>
                            </div>
                        </div>
                        
                        <!-- Location fields -->
                        <hr>
                        <h6>Warehouse Location</h6>
                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle"></i> This will be used as the starting point for distance calculations.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name: <span class="text-danger">*</span></label>
                            <input type="text" name="location_name" id="location_name" class="form-control" 
                                   value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude: <span class="text-danger">*</span></label>
                                    <input type="number" name="latitude" id="latitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude: <span class="text-danger">*</span></label>
                                    <input type="number" name="longitude" id="longitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Zone
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
                    <!-- Search with improved container -->
                    <div class="search-container mb-3">
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

    <?php else: ?>
    <!-- Edit Mode - Rest of your existing edit zone code -->
    <?php
    // Get the zone being edited
    $edit_zone_query = "SELECT * FROM delivery_zones WHERE id = '$edit_zone_id'";
    $edit_zone_result = mysqli_query($conn, $edit_zone_query);
    $edit_zone = mysqli_fetch_assoc($edit_zone_result);
    
    if (!$edit_zone) {
        echo "<div class='alert alert-danger'>Zone not found!</div>";
        echo "<a href='?' class='btn btn-secondary'>Back to Zones</a>";
        exit();
    }
    ?>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-edit"></i> Edit <?php echo htmlspecialchars($edit_zone['zone_name']); ?></h5>
                    <a href="?" class="btn btn-secondary btn-sm">Cancel</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="zone_id" value="<?php echo $edit_zone['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Zone Name: <span class="text-danger">*</span></label>
                            <input type="text" name="zone_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_zone['zone_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Zone Code: <span class="text-danger">*</span></label>
                            <input type="text" name="zone_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_zone['zone_code']); ?>" maxlength="10" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_free_delivery" id="is_free_delivery" 
                                       <?php echo $edit_zone['is_free_delivery'] ? 'checked' : ''; ?> onchange="toggleFreeDelivery()">
                                <label class="form-check-label" for="is_free_delivery">
                                    <strong>Free Delivery Zone</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div id="pricing-fields" style="<?php echo $edit_zone['is_free_delivery'] ? 'display: none;' : ''; ?>">
                            <div class="mb-3">
                                <label class="form-label">Base Fee: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="base_fee" class="form-control" step="0.01" 
                                           value="<?php echo $edit_zone['base_fee']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Included KM: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="included_km" class="form-control" step="0.01" 
                                           value="<?php echo $edit_zone['included_km']; ?>" required>
                                    <span class="input-group-text">KM</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Per KM Rate: <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="per_km_rate" class="form-control" step="0.01" 
                                           value="<?php echo $edit_zone['per_km_rate']; ?>" required>
                                    <span class="input-group-text">per KM</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Location fields (only show for first zone or if editing location) -->
                        <?php if ($edit_zone_id == 1 || !$existing_location): ?>
                        <hr>
                        <h6>Warehouse Location</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name: <span class="text-danger">*</span></label>
                            <input type="text" name="location_name" id="location_name" class="form-control" 
                                   value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude: <span class="text-danger">*</span></label>
                                    <input type="number" name="latitude" id="latitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude: <span class="text-danger">*</span></label>
                                    <input type="number" name="longitude" id="longitude" class="form-control" step="0.0000001" 
                                           value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>" readonly required>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="location_name" value="<?php echo $existing_location ? htmlspecialchars($existing_location['location_name']) : ''; ?>">
                        <input type="hidden" name="latitude" value="<?php echo $existing_location ? $existing_location['latitude'] : ''; ?>">
                        <input type="hidden" name="longitude" value="<?php echo $existing_location ? $existing_location['longitude'] : ''; ?>">
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Zone
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if ($edit_zone_id == 1 || !$existing_location): ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Select Location on Map</h5>
                </div>
                <div class="card-body">
                    <!-- Search with improved container -->
                    <div class="search-container mb-3">
                        <input type="text" id="map-search" class="form-control" placeholder="Search for a location...">
                        <div id="search-results" class="search-results" style="display: none;"></div>
                    </div>
                    
                    <!-- Map -->
                    <div id="map"></div>
                    <small class="text-muted mt-2 d-block">Click on the map or search to select a location</small>
                </div>
            </div>
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
                // Set values to 0 when free delivery is enabled
                if (feeInput) feeInput.value = '0.00';
                if (kmInput) kmInput.value = '0.00';
                if (rateInput) rateInput.value = '0.00';
                
                // Remove required attribute
                if (feeInput) feeInput.removeAttribute('required');
                if (kmInput) kmInput.removeAttribute('required');
                if (rateInput) rateInput.removeAttribute('required');
            } else {
                if (pricingFields) pricingFields.style.display = 'block';
                // Add required attribute back
                if (feeInput) feeInput.setAttribute('required', 'required');
                if (kmInput) kmInput.setAttribute('required', 'required');
                if (rateInput) rateInput.setAttribute('required', 'required');
            }
        }

        <?php if ($is_add_mode || ($is_edit_mode && ($edit_zone_id == 1 || !$existing_location))): ?>
        // Initialize map
        function initMap() {
            <?php if ($existing_location): ?>
                // Edit mode - center on existing location
                map = L.map('map').setView([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>], 15);
                marker = L.marker([<?php echo $existing_location['latitude']; ?>, <?php echo $existing_location['longitude']; ?>]).addTo(map);
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

        // Enhanced search functionality
        const mapSearchInput = document.getElementById('map-search');
        if (mapSearchInput) {
            mapSearchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 3) {
                    hideSearchResults();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchLocation(query);
                }, 300);
            });
        }

        function searchLocation(query) {
            const resultsDiv = document.getElementById('search-results');
            if (!resultsDiv) return;
            
            // Show loading state
            resultsDiv.innerHTML = '<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
            resultsDiv.style.display = 'block';
            
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=8&countrycodes=ph&addressdetails=1`;
            
            fetch(url, {
                headers: { 'User-Agent': 'DeliveryZoneManager/1.0' }
            })
            .then(response => response.json())
            .then(data => displaySearchResults(data, query))
            .catch(error => {
                console.error('Search error:', error);
                showFallbackLocations(query);
            });
        }
        
        function displaySearchResults(data, originalQuery) {
            const resultsDiv = document.getElementById('search-results');
            if (!resultsDiv) return;
            
            resultsDiv.innerHTML = '';
            
            if (data.length === 0) {
                showFallbackLocations(originalQuery);
                return;
            }
            
            data.forEach((item) => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.innerHTML = `
                    <div class="fw-semibold">${item.name || 'Location'}</div>
                    <small class="text-muted">${item.display_name}</small>
                `;
                
                div.onclick = () => selectSearchResult(item);
                resultsDiv.appendChild(div);
            });
            
            resultsDiv.style.display = 'block';
        }
        
        function showFallbackLocations(query) {
            const resultsDiv = document.getElementById('search-results');
            if (!resultsDiv) return;
            
            const fallbackLocations = [
                { name: 'Quezon City', lat: 14.6760, lon: 121.0437, display_name: 'Quezon City, Metro Manila, Philippines' },
                { name: 'Manila', lat: 14.5995, lon: 120.9842, display_name: 'Manila, Metro Manila, Philippines' },
                { name: 'Makati', lat: 14.5547, lon: 121.0244, display_name: 'Makati, Metro Manila, Philippines' },
                { name: 'BGC Taguig', lat: 14.5352, lon: 121.0553, display_name: 'Bonifacio Global City, Taguig, Metro Manila, Philippines' },
            ];
            
            const filtered = fallbackLocations.filter(loc => 
                loc.name.toLowerCase().includes(query.toLowerCase()) ||
                loc.display_name.toLowerCase().includes(query.toLowerCase())
            );
            
            resultsDiv.innerHTML = '';
            filtered.forEach(location => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.innerHTML = `
                    <div class="fw-semibold">${location.name}</div>
                    <small class="text-muted">${location.display_name}</small>
                `;
                div.onclick = () => selectSearchResult(location);
                resultsDiv.appendChild(div);
            });
            
            resultsDiv.style.display = 'block';
        }

        function selectSearchResult(item) {
            const lat = parseFloat(item.lat);
            const lng = parseFloat(item.lon);
            
            // Update map
            if (map) {
                map.setView([lat, lng], 16);
                
                // Update marker
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng]).addTo(map);
                }
            }
            
            // Update form
            const latField = document.getElementById('latitude');
            const lngField = document.getElementById('longitude');
            const nameField = document.getElementById('location_name');
            const searchField = document.getElementById('map-search');
            
            if (latField) latField.value = lat.toFixed(8);
            if (lngField) lngField.value = lng.toFixed(8);
            if (nameField) nameField.value = item.display_name;
            if (searchField) searchField.value = '';
            
            hideSearchResults();
        }

        function hideSearchResults() {
            const resultsDiv = document.getElementById('search-results');
            if (resultsDiv) resultsDiv.style.display = 'none';
        }

        function reverseGeocode(lat, lng) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const nameField = document.getElementById('location_name');
                    if (data.display_name && nameField) {
                        nameField.value = data.display_name;
                    }
                })
                .catch(error => console.error('Reverse geocoding error:', error));
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('map')) {
                initMap();
            }
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchResults();
            }
        });
        <?php endif; ?>

        // Initialize free delivery toggle on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('is_free_delivery')) {
                toggleFreeDelivery();
            }
        });

        // Enhanced delete confirmation with modals
        let deleteZoneId, deleteZoneName, deleteSizeId, deleteSizeName;

        // Zone deletion functions
        function confirmDelete(zoneId, zoneName) {
            // Simple confirm dialog as backup
            if (confirm(`Are you sure you want to delete the zone "${zoneName}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `?delete=1&zone_id=${zoneId}`;
                return;
            }
            
            // If Bootstrap is available, use modal
            if (typeof bootstrap !== 'undefined') {
                deleteZoneId = zoneId;
                deleteZoneName = zoneName;
                
                // Update modal content
                const modalBody = document.querySelector('#deleteModal .modal-body p');
                if (modalBody) {
                    modalBody.innerHTML = `Are you sure you want to delete the zone <strong>"${zoneName}"</strong>?`;
                }
                
                // Show modal
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            }
        }

        // Size deletion functions
        function confirmDeleteSize(sizeId, sizeName) {
            // Simple confirm dialog as backup
            if (confirm(`Are you sure you want to delete the size "${sizeName}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `?delete_size=1&size_id=${sizeId}`;
                return;
            }
            
            // If Bootstrap is available, use modal
            if (typeof bootstrap !== 'undefined') {
                deleteSizeId = sizeId;
                deleteSizeName = sizeName;
                
                // Update modal content
                const modalBody = document.querySelector('#deleteSizeModal .modal-body p');
                if (modalBody) {
                    modalBody.innerHTML = `Are you sure you want to delete the size <strong>"${sizeName}"</strong>?`;
                }
                
                // Show modal
                const deleteSizeModal = new bootstrap.Modal(document.getElementById('deleteSizeModal'));
                deleteSizeModal.show();
            }
        }

        // Handle actual zone deletion
        document.addEventListener('DOMContentLoaded', function() {
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    if (deleteZoneId) {
                        // Show loading state
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                        this.disabled = true;
                        
                        // Redirect to delete
                        window.location.href = `?delete=1&zone_id=${deleteZoneId}`;
                    }
                });
            }
        });

        // Handle actual size deletion
        document.addEventListener('DOMContentLoaded', function() {
            const confirmDeleteSizeBtn = document.getElementById('confirmDeleteSizeBtn');
            if (confirmDeleteSizeBtn) {
                confirmDeleteSizeBtn.addEventListener('click', function() {
                    if (deleteSizeId) {
                        // Show loading state
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                        this.disabled = true;
                        
                        // Redirect to delete
                        window.location.href = `?delete_size=1&size_id=${deleteSizeId}`;
                    }
                });
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const zoneCode = form.querySelector('input[name="zone_code"]');
                    if (zoneCode) {
                        // Convert to uppercase
                        zoneCode.value = zoneCode.value.toUpperCase();
                        
                        // Check for special characters
                        if (!/^[A-Z0-9_-]+$/.test(zoneCode.value)) {
                            e.preventDefault();
                            alert('Zone code can only contain letters, numbers, hyphens, and underscores.');
                            zoneCode.focus();
                            return false;
                        }
                    }
                    
                    // Validate coordinates if present
                    const lat = form.querySelector('input[name="latitude"]');
                    const lng = form.querySelector('input[name="longitude"]');
                    
                    if (lat && lng && (lat.value === '' || lng.value === '')) {
                        e.preventDefault();
                        alert('Please select a location on the map.');
                        return false;
                    }
                    
                    // Validate percentage for size forms
                    const percentage = form.querySelector('input[name="percentage"]');
                    if (percentage) {
                        const value = parseFloat(percentage.value);
                        if (value < 0 || value > 100) {
                            e.preventDefault();
                            alert('Percentage must be between 0 and 100.');
                            percentage.focus();
                            return false;
                        }
                    }
                });
            });
        });
    </script>
</div>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

</body>
</html>