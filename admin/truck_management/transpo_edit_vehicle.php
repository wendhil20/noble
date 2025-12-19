<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin', 'logistic']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get vehicle ID from URL
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($vehicle_id <= 0) {
    $_SESSION['vehicle_msg'] = "❌ Invalid vehicle ID.";
    header("Location: transpo_add_vehicle.php");
    exit();
}

$vehicle_msg = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle'])) {
    $courier_name = trim($_POST['courier_name']);
    $vehicle_type = trim($_POST['vehicle_type']);
    $custom_vehicle_type = trim($_POST['custom_vehicle_type']);
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

    // Use custom vehicle type if "Other" is selected
    if ($vehicle_type === 'Other' && !empty($custom_vehicle_type)) {
        $vehicle_type = $custom_vehicle_type;
    }

    // Validate required fields
    if ($courier_name !== '' && $vehicle_type !== '' && $vehicle_type !== 'Other' && $base_fare !== '' && $add_per_km !== '' && $per_km_rate !== '') {
        
        $stmt = $conn->prepare("UPDATE transportify_vehicle_list SET courier_name = ?, vehicle_type = ?, vehicle_variant = ?, vehicle_description = ?, base_fare = ?, add_per_km = ?, per_km_rate = ?, length = ?, width = ?, height = ?, max_cubic_meter = ?, max_weight_capacity = ? WHERE id = ?");
        
        $stmt->bind_param("ssssddddddddi", 
            $courier_name,
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
            $max_weight_capacity,
            $vehicle_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['vehicle_msg'] = "✅ Vehicle type updated successfully!";
            header("Location: transpo_add_vehicle.php");
            exit();
        } else {
            $vehicle_msg = "❌ Error updating vehicle: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $vehicle_msg = "❌ Please fill in all required fields.";
    }
}

// Fetch vehicle data
$stmt = $conn->prepare("SELECT * FROM transportify_vehicle_list WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['vehicle_msg'] = "❌ Vehicle not found.";
    header("Location: transpo_add_vehicle.php");
    exit();
}

$vehicle = $result->fetch_assoc();
$stmt->close();

// Check if vehicle type is custom (not in predefined list)
$predefined_types = ['Sedan', 'MPV/SUV', 'Small Pickup', 'Light Van', 'L300/Van', 'Closed Van', 'Open Truck', '6w Fwd Truck', 'Wing Van', 'Motorcycle', 'Bicycle'];
$is_custom = !in_array($vehicle['vehicle_type'], $predefined_types);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Vehicle Type</title>
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
        #customVehicleTypeDiv {
            display: <?= $is_custom ? 'block' : 'none' ?>;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-orange-600">Edit Vehicle Type</h2>
                <a href="transpo_add_vehicle.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition text-sm">
                    ← Back to List
                </a>
            </div>

            <?php if (!empty($vehicle_msg)): ?>
                <div class="mb-4 text-sm px-4 py-2 rounded <?= str_starts_with($vehicle_msg, '✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                    <?= $vehicle_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="update_vehicle" value="1">
                
                <!-- Courier Name Section -->
                <div class="bg-purple-50 p-4 rounded-lg border-l-4 border-purple-400">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">🚚 Courier Information</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Courier Name *</label>
                        <input type="text" name="courier_name" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., Lalamove, Grab Express, MrSpeedy" value="<?= htmlspecialchars($vehicle['courier_name'] ?? '') ?>">
                    </div>
                </div>

                <!-- Basic Vehicle Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Vehicle Type Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Type *</label>
                            <select name="vehicle_type" id="vehicleTypeSelect" required class="w-full border px-3 py-2 rounded mt-1">
                                <option value="">Select vehicle type</option>
                                <option value="Sedan" <?= $vehicle['vehicle_type'] === 'Sedan' ? 'selected' : '' ?>>Sedan</option>
                                <option value="MPV/SUV" <?= $vehicle['vehicle_type'] === 'MPV/SUV' ? 'selected' : '' ?>>MPV/SUV</option>
                                <option value="Small Pickup" <?= $vehicle['vehicle_type'] === 'Small Pickup' ? 'selected' : '' ?>>Small Pickup</option>
                                <option value="Light Van" <?= $vehicle['vehicle_type'] === 'Light Van' ? 'selected' : '' ?>>Light Van</option>
                                <option value="L300/Van" <?= $vehicle['vehicle_type'] === 'L300/Van' ? 'selected' : '' ?>>L300/Van</option>
                                <option value="Closed Van" <?= $vehicle['vehicle_type'] === 'Closed Van' ? 'selected' : '' ?>>Closed Van</option>
                                <option value="Open Truck" <?= $vehicle['vehicle_type'] === 'Open Truck' ? 'selected' : '' ?>>Open Truck</option>
                                <option value="6w Fwd Truck" <?= $vehicle['vehicle_type'] === '6w Fwd Truck' ? 'selected' : '' ?>>6w Fwd Truck</option>
                                <option value="Wing Van" <?= $vehicle['vehicle_type'] === 'Wing Van' ? 'selected' : '' ?>>Wing Van</option>
                                <option value="Motorcycle" <?= $vehicle['vehicle_type'] === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                                <option value="Bicycle" <?= $vehicle['vehicle_type'] === 'Bicycle' ? 'selected' : '' ?>>Bicycle</option>
                                <option value="Other" <?= $is_custom ? 'selected' : '' ?>>➕ Other (Custom)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Variant</label>
                            <input type="text" name="vehicle_variant" class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., XL, Standard, Heavy Duty" value="<?= htmlspecialchars($vehicle['vehicle_variant'] ?? '') ?>">
                        </div>
                    </div>
                    <div id="customVehicleTypeDiv" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Custom Vehicle Type *</label>
                        <input type="text" name="custom_vehicle_type" id="customVehicleTypeInput" class="w-full border px-3 py-2 rounded mt-1" placeholder="Enter your custom vehicle type (e.g., E-Bike, Cargo Van, etc.)" value="<?= $is_custom ? htmlspecialchars($vehicle['vehicle_type']) : '' ?>">
                        <p class="text-xs text-gray-500 mt-1">💡 Type your custom vehicle name here when "Other (Custom)" is selected</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Vehicle Description</label>
                        <textarea name="vehicle_description" rows="2" class="w-full border px-3 py-2 rounded mt-1" placeholder="Brief description of the vehicle type"><?= htmlspecialchars($vehicle['vehicle_description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Pricing Information Section -->
                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-400">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">💰 Pricing Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Base Fare (₱) *</label>
                            <input type="number" step="0.01" name="base_fare" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['base_fare']) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Additional Per KM (₱) *</label>
                            <input type="number" step="0.01" name="add_per_km" required class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['add_per_km']) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Per KM Rate Starts At (km) *</label>
                            <input type="number" step="0.01" name="per_km_rate" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., 1 or 40" value="<?= htmlspecialchars($vehicle['per_km_rate']) ?>">
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
                            <input type="number" step="0.01" name="length" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['length'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Width (ft)</label>
                            <input type="number" step="0.01" name="width" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['width'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Height (ft)</label>
                            <input type="number" step="0.01" name="height" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['height'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Capacity Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">📦 Capacity Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Cubic Meter (m³)</label>
                            <input type="number" step="0.01" name="max_cubic_meter" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['max_cubic_meter'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Weight Capacity (kg)</label>
                            <input type="number" step="0.01" name="max_weight_capacity" class="w-full border px-3 py-2 rounded mt-1" placeholder="0.00" value="<?= htmlspecialchars($vehicle['max_weight_capacity'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 transition font-semibold">
                        💾 Update Vehicle Type
                    </button>
                    <a href="transpo_add_vehicle.php" class="bg-gray-500 text-white px-6 py-3 rounded hover:bg-gray-600 transition font-semibold inline-block">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/hide custom vehicle type input
        const vehicleTypeSelect = document.getElementById('vehicleTypeSelect');
        const customVehicleTypeDiv = document.getElementById('customVehicleTypeDiv');
        const customVehicleTypeInput = document.getElementById('customVehicleTypeInput');

        vehicleTypeSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                customVehicleTypeDiv.style.display = 'block';
                customVehicleTypeInput.required = true;
            } else {
                customVehicleTypeDiv.style.display = 'none';
                customVehicleTypeInput.required = false;
                customVehicleTypeInput.value = '';
            }
        });

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