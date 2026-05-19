<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['superadmin', 'logistic']); // allow only admin and superadmin
// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: " . BASE_URL . "/main");
    exit();
}

$vehicle_msg = '';

// Handle vehicle insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_vehicle'])) {
    $plate_number = trim($_POST['plate_number']);
    $truck_type = trim($_POST['truck_type']);
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = trim($_POST['year']);
    $weight_capacity = trim($_POST['weight_capacity']) ?: null;
    $volume_capacity = trim($_POST['volume_capacity']) ?: null;
    $capacity_unit_weight = trim($_POST['capacity_unit_weight']);
    $capacity_unit_volume = trim($_POST['capacity_unit_volume']);
    $truck_identification_number = trim($_POST['truck_identification_number']) ?: null;
    $vin_number = trim($_POST['vin_number']) ?: null;
    $registration_number = trim($_POST['registration_number']);
    $registration_expiration_date = trim($_POST['registration_expiration_date']);
    $insurance_provider = trim($_POST['insurance_provider']) ?: null;
    $insurance_policy_number = trim($_POST['insurance_policy_number']) ?: null;
    $insurance_expiration_date = trim($_POST['insurance_expiration_date']) ?: null;
    $fuel_type = trim($_POST['fuel_type']);
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']) ?: null;
    
    // Handle unavailable days
    $unavailable_days = isset($_POST['unavailable_days']) ? implode(',', $_POST['unavailable_days']) : null;
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['truck_photo']) && $_FILES['truck_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/truck_photo_collection/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['truck_photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $photo_filename = 'truck_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $photo_path = $upload_dir . $photo_filename;
            
            if (!move_uploaded_file($_FILES['truck_photo']['tmp_name'], $photo_path)) {
                $photo_path = null;
                $vehicle_msg = "❌ Photo upload failed, but vehicle information was saved.";
            }
        }
    }

    // Validate required fields
    if ($plate_number !== '' && $truck_type !== '' && $make !== '' && 
        $model !== '' && $year !== '' && $registration_number !== '' && 
        $registration_expiration_date !== '') {
        
        $stmt = $conn->prepare("INSERT INTO vehicle_list (plate_number, truck_type, make, model, year, weight_capacity, volume_capacity, capacity_unit_weight, capacity_unit_volume, truck_identification_number, vin_number, registration_number, registration_expiration_date, insurance_provider, insurance_policy_number, insurance_expiration_date, fuel_type, status, notes, photo_path, unavailable_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssddssssssssssssss", 
            $plate_number,
            $truck_type,
            $make,
            $model,
            $year,
            $weight_capacity,
            $volume_capacity,
            $capacity_unit_weight,
            $capacity_unit_volume,
            $truck_identification_number,
            $vin_number,
            $registration_number,
            $registration_expiration_date,
            $insurance_provider,
            $insurance_policy_number,
            $insurance_expiration_date,
            $fuel_type,
            $status,
            $notes,
            $photo_path,
            $unavailable_days
        );
        
        if ($stmt->execute()) {
            $vehicle_msg = "✅ New vehicle added successfully!";
        } else {
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                $vehicle_msg = " Plate number already exists in the system.";
            } else {
                $vehicle_msg = " Error adding vehicle: " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $vehicle_msg = " Please fill in all required fields.";
    }
}

// Function to get day names from stored comma-separated values
function getDayNames($dayString) {
    if (empty($dayString)) return 'All days available';
    
    $dayMap = [
        'monday' => 'Mon',
        'tuesday' => 'Tue', 
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
        'sunday' => 'Sun'
    ];
    
    $days = explode(',', $dayString);
    $dayNames = array_map(function($day) use ($dayMap) {
        return $dayMap[trim($day)] ?? $day;
    }, $days);
    
    return 'Not available: ' . implode(', ', $dayNames);
}

// Fetch vehicles list with enhanced information
$vehicles = $conn->query("SELECT *, 
    CONCAT(make, ' ', model, ' (', year, ')') AS vehicle_info,
    CONCAT(
        CASE 
            WHEN weight_capacity IS NOT NULL 
            THEN CONCAT(weight_capacity, ' ', capacity_unit_weight) 
            ELSE '' 
        END,
        CASE 
            WHEN weight_capacity IS NOT NULL AND volume_capacity IS NOT NULL 
            THEN ' / ' 
            ELSE '' 
        END,
        CASE 
            WHEN volume_capacity IS NOT NULL 
            THEN CONCAT(volume_capacity, ' ', capacity_unit_volume) 
            ELSE '' 
        END
    ) AS capacity_info,
    CASE 
        WHEN registration_expiration_date < CURDATE() THEN 'EXPIRED'
        WHEN registration_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'EXPIRING_SOON'
        ELSE 'VALID'
    END as registration_status,
    CASE 
        WHEN insurance_expiration_date IS NULL THEN 'NO_INSURANCE'
        WHEN insurance_expiration_date < CURDATE() THEN 'EXPIRED'
        WHEN insurance_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'EXPIRING_SOON'
        ELSE 'VALID'
    END as insurance_status
    FROM vehicle_list ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Vehicle/Truck</title>
    
    <style>
        .status-expired { color: #dc2626; font-weight: bold; }
        .status-expiring { color: #d97706; font-weight: bold; }
        .status-valid { color: #16a34a; }
        .status-no-insurance { color: #6b7280; font-style: italic; }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .checkbox-item:hover {
            background-color: #f3f4f6;
        }
        .checkbox-item input:checked + label {
            color: #dc2626;
            font-weight: 600;
        }
        .checkbox-item input {
            margin-right: 8px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include ROOT_PATH . '/admin/navbar/top.php';?>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4 text-orange-600">Add New Vehicle/Truck</h2>

            <?php if (!empty($vehicle_msg)): ?>
                <div class="mb-4 text-sm px-4 py-2 rounded <?= str_starts_with($vehicle_msg, '✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                    <?= $vehicle_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4 mb-8">
                <input type="hidden" name="new_vehicle" value="1">
                
                <!-- Basic Vehicle Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Basic Vehicle Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Plate Number *</label>
                            <input type="text" name="plate_number" required class="w-full border px-3 py-2 rounded mt-1 uppercase" placeholder="e.g., ABC-1234">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Truck Type *</label>
                            <select name="truck_type" required class="w-full border px-3 py-2 rounded mt-1">
                                <option value="">Select truck type</option>
                                <option value="6-wheeler">6-wheeler</option>
                                <option value="10-wheeler">10-wheeler</option>
                                <option value="van">Van</option>
                                <option value="closed truck">Closed Truck</option>
                                <option value="trailer truck">Trailer Truck</option>
                                <option value="mini truck">Mini Truck</option>
                                <option value="delivery van">Delivery Van</option>
                                <option value="refrigerated truck">Refrigerated Truck</option>
                                <option value="flatbed truck">Flatbed Truck</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Make *</label>
                            <input type="text" name="make" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., Isuzu, Mitsubishi">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Model *</label>
                            <input type="text" name="model" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., Forward, Fuso">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year *</label>
                            <input type="number" name="year" required min="1990" max="2030" class="w-full border px-3 py-2 rounded mt-1" placeholder="2024">
                        </div>
                    </div>
                </div>

                <!-- Availability Schedule Section -->
                <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-400">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">🚫 Vehicle Availability Schedule</h3>
                    <p class="text-sm text-gray-600 mb-3">Select the days when this vehicle will <strong>NOT be available</strong> for use:</p>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="monday" name="unavailable_days[]" value="monday">
                            <label for="monday" class="cursor-pointer">Monday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="tuesday" name="unavailable_days[]" value="tuesday">
                            <label for="tuesday" class="cursor-pointer">Tuesday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="wednesday" name="unavailable_days[]" value="wednesday">
                            <label for="wednesday" class="cursor-pointer">Wednesday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="thursday" name="unavailable_days[]" value="thursday">
                            <label for="thursday" class="cursor-pointer">Thursday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="friday" name="unavailable_days[]" value="friday">
                            <label for="friday" class="cursor-pointer">Friday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="saturday" name="unavailable_days[]" value="saturday">
                            <label for="saturday" class="cursor-pointer">Saturday</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="sunday" name="unavailable_days[]" value="sunday">
                            <label for="sunday" class="cursor-pointer">Sunday</label>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">💡 Tip: Leave all unchecked if the vehicle is available every day of the week.</p>
                </div>

                <!-- Capacity Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Capacity Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Weight Capacity</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.01" name="weight_capacity" class="flex-1 border px-3 py-2 rounded mt-1" placeholder="5.00">
                                <select name="capacity_unit_weight" class="border px-3 py-2 rounded mt-1">
                                    <option value="tons">Tons</option>
                                    <option value="kg">Kg</option>
                                    <option value="lbs">Lbs</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Volume Capacity</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.01" name="volume_capacity" class="flex-1 border px-3 py-2 rounded mt-1" placeholder="25.00">
                                <select name="capacity_unit_volume" class="border px-3 py-2 rounded mt-1">
                                    <option value="cubic meters">m³</option>
                                    <option value="cubic feet">ft³</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Identification Numbers Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Identification Numbers</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Truck ID / Body Number</label>
                            <input type="text" name="truck_identification_number" class="w-full border px-3 py-2 rounded mt-1" placeholder="Fleet ID or Body Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">VIN Number</label>
                            <input type="text" name="vin_number" class="w-full border px-3 py-2 rounded mt-1" placeholder="Vehicle Identification Number">
                        </div>
                    </div>
                </div>

                <!-- Registration Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Registration Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Registration Number (OR/CR) *</label>
                            <input type="text" name="registration_number" required class="w-full border px-3 py-2 rounded mt-1" placeholder="OR/CR Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Registration Expiration Date *</label>
                            <input type="date" name="registration_expiration_date" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                </div>

                <!-- Insurance Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Insurance Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Insurance Provider</label>
                            <input type="text" name="insurance_provider" class="w-full border px-3 py-2 rounded mt-1" placeholder="Insurance Company Name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Policy Number</label>
                            <input type="text" name="insurance_policy_number" class="w-full border px-3 py-2 rounded mt-1" placeholder="Policy Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Insurance Expiration Date</label>
                            <input type="date" name="insurance_expiration_date" class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Additional Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fuel Type</label>
                            <select name="fuel_type" class="w-full border px-3 py-2 rounded mt-1">
                                <option value="diesel">Diesel</option>
                                <option value="gasoline">Gasoline</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="electric">Electric</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="w-full border px-3 py-2 rounded mt-1">
                                <option value="active">Active</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="out_of_service">Out of Service</option>
                                <option value="retired">Retired</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" rows="3" class="w-full border px-3 py-2 rounded mt-1" placeholder="Additional notes about the vehicle"></textarea>
                    </div>
                </div>

                <!-- Photo Upload Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Vehicle Photo</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Truck Photo</label>
                        <input type="file" name="truck_photo" accept="image/*" class="w-full border px-3 py-2 rounded mt-1">
                        <p class="text-xs text-gray-500 mt-1">Optional. Accepted formats: JPG, PNG, GIF. Max size: 5MB</p>
                    </div>
                </div>

                <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 transition font-semibold">
                    Add Vehicle
                </button>
            </form>

            <!-- Vehicle List Section -->
            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Vehicle List</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border border-gray-300">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-3 py-2 border-b">#</th>
                                <th class="px-3 py-2 border-b">Photo</th>
                                <th class="px-3 py-2 border-b">Plate Number</th>
                                <th class="px-3 py-2 border-b">Vehicle Info</th>
                                <th class="px-3 py-2 border-b">Type</th>
                                <th class="px-3 py-2 border-b">Capacity</th>
                                <th class="px-3 py-2 border-b">Availability</th>
                                <th class="px-3 py-2 border-b">Registration</th>
                                <th class="px-3 py-2 border-b">Insurance</th>
                                <th class="px-3 py-2 border-b">Status</th>
                                <th class="px-3 py-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vehicles->num_rows > 0): ?>
                                <?php while ($row = $vehicles->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 border-b"><?= $row['id'] ?></td>
                                        <td class="px-3 py-2 border-b">
                                            <?php if ($row['photo_path'] && file_exists($row['photo_path'])): ?>
                                                <img src="<?= $row['photo_path'] ?>" alt="Truck Photo" class="w-16 h-12 rounded object-cover">
                                            <?php else: ?>
                                                <div class="w-16 h-12 bg-gray-300 rounded flex items-center justify-center text-xs">No Photo</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong class="font-mono"><?= htmlspecialchars($row['plate_number']) ?></strong><br>
                                            <small class="text-gray-600"><?= htmlspecialchars($row['truck_identification_number'] ?: 'No Fleet ID') ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong><?= htmlspecialchars($row['vehicle_info']) ?></strong><br>
                                            <small class="text-gray-600"><?= htmlspecialchars($row['fuel_type']) ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['truck_type']))) ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?= htmlspecialchars($row['capacity_info'] ?: 'Not specified') ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <small class="text-xs <?= empty($row['unavailable_days']) ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= getDayNames($row['unavailable_days']) ?>
                                            </small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="status-<?= strtolower(str_replace('_', '-', $row['registration_status'])) ?>">
                                                <?= str_replace('_', ' ', $row['registration_status']) ?>
                                            </span><br>
                                            <small class="text-gray-600"><?= date('M d, Y', strtotime($row['registration_expiration_date'])) ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="status-<?= strtolower(str_replace('_', '-', $row['insurance_status'])) ?>">
                                                <?= str_replace('_', ' ', $row['insurance_status']) ?>
                                            </span><br>
                                            <small class="text-gray-600">
                                                <?= $row['insurance_expiration_date'] ? date('M d, Y', strtotime($row['insurance_expiration_date'])) : 'N/A' ?>
                                            </small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="px-2 py-1 rounded text-xs <?= 
                                                $row['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                                ($row['status'] === 'maintenance' ? 'bg-yellow-100 text-yellow-800' : 
                                                ($row['status'] === 'out_of_service' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) 
                                            ?>">
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <button class="text-blue-600 hover:text-blue-800 text-xs mr-2" onclick="viewVehicle(<?= $row['id'] ?>)">View</button>
                                            <button class="text-green-600 hover:text-green-800 text-xs mr-2" onclick="editVehicle(<?= $row['id'] ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-6 text-gray-500">No vehicles found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewVehicle(id) {
            // Implement view vehicle details functionality
            alert('View vehicle details for ID: ' + id);
        }

        function editVehicle(id) {
            // Implement edit vehicle functionality
            alert('Edit vehicle for ID: ' + id);
        }

        // Auto-uppercase plate number input
        document.querySelector('input[name="plate_number"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });

        // Auto-calculate current year for year field
        document.addEventListener('DOMContentLoaded', function() {
            const yearInput = document.querySelector('input[name="year"]');
            const currentYear = new Date().getFullYear();
            yearInput.setAttribute('max', currentYear + 1);
            if (!yearInput.value) {
                yearInput.setAttribute('placeholder', currentYear);
            }
        });

        // Enhanced checkbox interaction
        document.querySelectorAll('.checkbox-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });

        // Visual feedback for selected unavailable days
        document.querySelectorAll('input[name="unavailable_days[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.checkbox-item');
                if (this.checked) {
                    item.style.backgroundColor = '#fee2e2';
                    item.style.borderColor = '#dc2626';
                } else {
                    item.style.backgroundColor = '';
                    item.style.borderColor = '#d1d5db';
                }
            });
        });
        
    </script>
</body>
</html>