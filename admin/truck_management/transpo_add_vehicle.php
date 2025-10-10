<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin', 'logistic']); // allow only admin and superadmin
// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

$vehicle_msg = '';

// Handle vehicle insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_vehicle'])) {
    $vehicle_type = trim($_POST['vehicle_type']);
    $vehicle_variant = trim($_POST['vehicle_variant']) ?: null;
    $vehicle_description = trim($_POST['vehicle_description']) ?: null;
    $base_fare = trim($_POST['base_fare']);
    $add_per_km = trim($_POST['add_per_km']);
    $per_km_rate = trim($_POST['per_km_rate']);
    $length = trim($_POST['length']) ?: null;
    $width = trim($_POST['width']) ?: null;
    $height = trim($_POST['height']) ?: null;
    $max_cubic_meter = trim($_POST['max_cubic_meter']) ?: null;
    $max_weight_capacity = trim($_POST['max_weight_capacity']) ?: null;

    // Validate required fields
    if ($vehicle_type !== '' && $base_fare !== '' && $add_per_km !== '' && $per_km_rate !== '') {
        
        $stmt = $conn->prepare("INSERT INTO transportify_vehicle_list (vehicle_type, vehicle_variant, vehicle_description, base_fare, add_per_km, per_km_rate, length, width, height, max_cubic_meter, max_weight_capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssdddddddd", 
            $vehicle_type,
            $vehicle_variant,
            $vehicle_description,
            $base_fare,
            $add_per_km,
            $per_km_rate,
            $length,
            $width,
            $height,
            $max_cubic_meter,
            $max_weight_capacity
        );
        
        if ($stmt->execute()) {
            $_SESSION['vehicle_msg'] = "✅ New vehicle type added successfully!";
        } else {
            $_SESSION['vehicle_msg'] = "❌ Error adding vehicle: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['vehicle_msg'] = "❌ Please fill in all required fields.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get message from session and clear it
if (isset($_SESSION['vehicle_msg'])) {
    $vehicle_msg = $_SESSION['vehicle_msg'];
    unset($_SESSION['vehicle_msg']);
}

// Fetch vehicles list
$vehicles = $conn->query("SELECT * FROM transportify_vehicle_list ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Transportify Vehicle Type</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .dimension-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 768px) {
            .dimension-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4 text-orange-600">Add New Transportify Vehicle Type</h2>

            <?php if (!empty($vehicle_msg)): ?>
                <div class="mb-4 text-sm px-4 py-2 rounded <?= str_starts_with($vehicle_msg, '✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                    <?= $vehicle_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 mb-8">
                <input type="hidden" name="new_vehicle" value="1">
                
                <!-- Basic Vehicle Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Vehicle Type Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Type *</label>
                            <select name="vehicle_type" required class="w-full border px-3 py-2 rounded mt-1">
                                <option value="">Select vehicle type</option>
                                <option value="Sedan">Sedan</option>
                                <option value="MPV/SUV">MPV/SUV</option>
                                <option value="Small Pickup">Small Pickup</option>
                                <option value="Light Van">Light Van</option>
                                <option value="L300/Van">L300/Van</option>
                                <option value="Closed Van">Closed Van</option>
                                <option value="Open Truck">Open Truck</option>
                                <option value="6w Fwd Truck">6w Fwd Truck</option>
                                <option value="Wing Van">Wing Van</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Variant</label>
                            <input type="text" name="vehicle_variant" class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., XL, Standard, Heavy Duty">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Vehicle Description</label>
                        <textarea name="vehicle_description" rows="2" class="w-full border px-3 py-2 rounded mt-1" placeholder="Brief description of the vehicle type"></textarea>
                    </div>
                </div>

                <!-- Pricing Information Section -->
                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-400">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">💰 Pricing Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Base Fare (₱) *</label>
                            <input type="number" step="0.01" name="base_fare" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Additional Per KM (₱) *</label>
                            <input type="number" step="0.01" name="add_per_km" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Per KM Rate Starts At (km) *</label>
                            <input type="number" step="0.01" name="per_km_rate" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., 1 or 40">
                        </div>
                    </div>
                    <div class="bg-white p-3 rounded mt-3 border border-blue-200">
                        <p class="text-xs text-gray-700 font-semibold mb-2">💡 Pricing Examples:</p>
                        <p class="text-xs text-gray-600 mb-1">
                            <strong>Option 1 (1 km):</strong> Base fare ₱500 + (₱20 × distance_km). Example: 10km trip = ₱500 + (₱20 × 10) = ₱700
                        </p>
                        <p class="text-xs text-gray-600">
                            <strong>Option 40 (40 km):</strong> Base fare ₱2000 for first 40km, then ₱30/km after. Example: 50km trip = ₱2000 + (₱30 × 10) = ₱2300
                        </p>
                    </div>
                </div>

                <!-- Dimensions Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">📏 Vehicle Dimensions (in feet)</h3>
                    <div class="dimension-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Length (ft)</label>
                            <input type="number" step="0.01" name="length" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Width (ft)</label>
                            <input type="number" step="0.01" name="width" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Height (ft)</label>
                            <input type="number" step="0.01" name="height" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <!-- Capacity Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">📦 Capacity Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Cubic Meter (m³)</label>
                            <input type="number" step="0.01" name="max_cubic_meter" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Weight Capacity (kg)</label>
                            <input type="number" step="0.01" name="max_weight_capacity" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 transition font-semibold">
                    Add Vehicle Type
                </button>
            </form>

            <!-- Vehicle List Section -->
            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Vehicle Type List</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border border-gray-300">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-3 py-2 border-b">#</th>
                                <th class="px-3 py-2 border-b">Vehicle Type</th>
                                <th class="px-3 py-2 border-b">Variant</th>
                                <th class="px-3 py-2 border-b">Description</th>
                                <th class="px-3 py-2 border-b">Base Fare</th>
                                <th class="px-3 py-2 border-b">Per KM</th>
                                <th class="px-3 py-2 border-b">Rate Starts</th>
                                <th class="px-3 py-2 border-b">Dimensions (L×W×H)</th>
                                <th class="px-3 py-2 border-b">Max Capacity</th>
                                <th class="px-3 py-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vehicles->num_rows > 0): ?>
                                <?php while ($row = $vehicles->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 border-b"><?= $row['id'] ?></td>
                                        <td class="px-3 py-2 border-b">
                                            <strong><?= htmlspecialchars($row['vehicle_type']) ?></strong>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="text-gray-600">
                                                <?= htmlspecialchars($row['vehicle_variant'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <small class="text-gray-600">
                                                <?= htmlspecialchars($row['vehicle_description'] ?: 'No description') ?>
                                            </small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong>₱<?= number_format($row['base_fare'], 2) ?></strong>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            ₱<?= number_format($row['add_per_km'], 2) ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="px-2 py-1 rounded text-xs <?= $row['per_km_rate'] == 1 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                <?= $row['per_km_rate'] ?> km
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?php if ($row['length'] || $row['width'] || $row['height']): ?>
                                                <small class="text-gray-600">
                                                    <?= number_format($row['length'] ?: 0, 2) ?> × 
                                                    <?= number_format($row['width'] ?: 0, 2) ?> × 
                                                    <?= number_format($row['height'] ?: 0, 2) ?> ft
                                                </small>
                                            <?php else: ?>
                                                <small class="text-gray-400">Not specified</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?php if ($row['max_cubic_meter']): ?>
                                                <small class="text-gray-600">
                                                    <?= number_format($row['max_cubic_meter'], 2) ?> m³
                                                </small><br>
                                            <?php endif; ?>
                                            <?php if ($row['max_weight_capacity']): ?>
                                                <small class="text-gray-600">
                                                    <?= number_format($row['max_weight_capacity'], 2) ?> kg
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!$row['max_cubic_meter'] && !$row['max_weight_capacity']): ?>
                                                <small class="text-gray-400">Not specified</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <button class="text-blue-600 hover:text-blue-800 text-xs mr-2" onclick="viewVehicle(<?= $row['id'] ?>)">View</button>
                                            <button class="text-green-600 hover:text-green-800 text-xs mr-2" onclick="editVehicle(<?= $row['id'] ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-6 text-gray-500">No vehicle types found.</td>
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

        // Auto-calculate cubic meter when dimensions are entered
        const lengthInput = document.querySelector('input[name="length"]');
        const widthInput = document.querySelector('input[name="width"]');
        const heightInput = document.querySelector('input[name="height"]');
        const cubicMeterInput = document.querySelector('input[name="max_cubic_meter"]');

        function calculateCubicMeter() {
            const length = parseFloat(lengthInput.value) || 0;
            const width = parseFloat(widthInput.value) || 0;
            const height = parseFloat(heightInput.value) || 0;
            
            if (length > 0 && width > 0 && height > 0) {
                // Convert feet to meters (1 foot = 0.3048 meters)
                const lengthM = length * 0.3048;
                const widthM = width * 0.3048;
                const heightM = height * 0.3048;
                const cubicM = (lengthM * widthM * heightM).toFixed(2);
                cubicMeterInput.value = cubicM;
            }
        }

        lengthInput.addEventListener('input', calculateCubicMeter);
        widthInput.addEventListener('input', calculateCubicMeter);
        heightInput.addEventListener('input', calculateCubicMeter);
    </script>
</body>
</html>