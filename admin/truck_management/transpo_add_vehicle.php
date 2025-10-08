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
    $vehicle_description = trim($_POST['vehicle_description']) ?: null;
    $base_fare = trim($_POST['base_fare']);
    $base_fare_distance = trim($_POST['base_fare_distance']);
    $add_per_km = trim($_POST['add_per_km']);
    $length = trim($_POST['length']) ?: null;
    $width = trim($_POST['width']) ?: null;
    $height = trim($_POST['height']) ?: null;
    $max_cubic_ft = trim($_POST['max_cubic_ft']) ?: null;
    $max_weight_capacity = trim($_POST['max_weight_capacity']) ?: null;

    // Validate required fields
    if ($vehicle_type !== '' && $base_fare !== '' && $base_fare_distance !== '' && $add_per_km !== '') {
        
        $stmt = $conn->prepare("INSERT INTO transportify_vehicle_list (vehicle_type, vehicle_description, base_fare, base_fare_distance, add_per_km, length, width, height, max_cubic_ft, max_weight_capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssdddddddd", 
            $vehicle_type,
            $vehicle_description,
            $base_fare,
            $base_fare_distance,
            $add_per_km,
            $length,
            $width,
            $height,
            $max_cubic_ft,
            $max_weight_capacity
        );
        
        if ($stmt->execute()) {
            $vehicle_msg = "✅ New vehicle type added successfully!";
        } else {
            $vehicle_msg = "❌ Error adding vehicle: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $vehicle_msg = "❌ Please fill in all required fields.";
    }
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
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Type *</label>
                            <select name="vehicle_type" required class="w-full border px-3 py-2 rounded mt-1">
                                <option value="">Select vehicle type</option>
                                <option value="Motorcycle">Motorcycle</option>
                                <option value="Sedan">Sedan</option>
                                <option value="MPV/SUV">MPV/SUV</option>
                                <option value="Light Van">Light Van</option>
                                <option value="L300/Van">L300/Van</option>
                                <option value="Small Pickup (Open)">Small Pickup (Open)</option>
                                <option value="Small Pickup (Enclosed)">Small Pickup (Enclosed)</option>
                                <option value="Pickup Truck (Open)">Pickup Truck (Open)</option>
                                <option value="Pickup Truck (Enclosed)">Pickup Truck (Enclosed)</option>
                                <option value="Small Truck">Small Truck (Closed Van)</option>
                                <option value="6w Truck">6-Wheeler Truck</option>
                                <option value="10w Truck">10-Wheeler Truck</option>
                                <option value="Wing Van">Wing Van</option>
                                <option value="Trailer">Trailer Truck</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Description</label>
                            <textarea name="vehicle_description" rows="2" class="w-full border px-3 py-2 rounded mt-1" placeholder="Brief description of the vehicle type"></textarea>
                        </div>
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
                            <label class="block text-sm font-medium text-gray-700">Base Fare Distance (km) *</label>
                            <input type="number" step="0.01" name="base_fare_distance" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Additional Per KM (₱) *</label>
                            <input type="number" step="0.01" name="add_per_km" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">💡 Example: Base fare ₱500 for 5km, then ₱20/km for additional distance</p>
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
                            <label class="block text-sm font-medium text-gray-700">Max Cubic Feet (ft³)</label>
                            <input type="number" step="0.01" name="max_cubic_ft" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00">
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
                                <th class="px-3 py-2 border-b">Description</th>
                                <th class="px-3 py-2 border-b">Base Fare</th>
                                <th class="px-3 py-2 border-b">Base Distance</th>
                                <th class="px-3 py-2 border-b">Per KM</th>
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
                                            <small class="text-gray-600">
                                                <?= htmlspecialchars($row['vehicle_description'] ?: 'No description') ?>
                                            </small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong>₱<?= number_format($row['base_fare'], 2) ?></strong>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?= number_format($row['base_fare_distance'], 2) ?> km
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            ₱<?= number_format($row['add_per_km'], 2) ?>
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
                                            <?php if ($row['max_cubic_ft']): ?>
                                                <small class="text-gray-600">
                                                    <?= number_format($row['max_cubic_ft'], 2) ?> ft³
                                                </small><br>
                                            <?php endif; ?>
                                            <?php if ($row['max_weight_capacity']): ?>
                                                <small class="text-gray-600">
                                                    <?= number_format($row['max_weight_capacity'], 2) ?> kg
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!$row['max_cubic_ft'] && !$row['max_weight_capacity']): ?>
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
                                    <td colspan="9" class="text-center py-6 text-gray-500">No vehicle types found.</td>
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

        // Auto-calculate cubic feet when dimensions are entered
        const lengthInput = document.querySelector('input[name="length"]');
        const widthInput = document.querySelector('input[name="width"]');
        const heightInput = document.querySelector('input[name="height"]');
        const cubicFtInput = document.querySelector('input[name="max_cubic_ft"]');

        function calculateCubicFeet() {
            const length = parseFloat(lengthInput.value) || 0;
            const width = parseFloat(widthInput.value) || 0;
            const height = parseFloat(heightInput.value) || 0;
            
            if (length > 0 && width > 0 && height > 0) {
                const cubicFt = (length * width * height).toFixed(2);
                cubicFtInput.value = cubicFt;
            }
        }

        lengthInput.addEventListener('input', calculateCubicFeet);
        widthInput.addEventListener('input', calculateCubicFeet);
        heightInput.addEventListener('input', calculateCubicFeet);
    </script>
</body>
</html>