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


$driver_msg = '';

// Handle driver insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_driver'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $plate_number = trim($_POST['plate_number']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $government_id_number = trim($_POST['government_id_number']);
    $license_expiration_date = trim($_POST['license_expiration_date']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_number = trim($_POST['emergency_contact_number']);
    $employment_id = trim($_POST['employment_id']);
    $company_affiliation = trim($_POST['company_affiliation']);
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['driver_photo']) && $_FILES['driver_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/driver_photo_collection/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['driver_photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $photo_filename = 'driver_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $photo_path = $upload_dir . $photo_filename;
            
            if (!move_uploaded_file($_FILES['driver_photo']['tmp_name'], $photo_path)) {
                $photo_path = null;
                $driver_msg = "❌ Photo upload failed, but driver information was saved.";
            }
        }
    }

    // Validate required fields
    if ($first_name !== '' && $last_name !== '' && $plate_number !== '' && 
        $contact_number !== '' && $government_id_number !== '' && 
        $license_expiration_date !== '' && $emergency_contact_name !== '' && 
        $emergency_contact_number !== '') {
        
        $stmt = $conn->prepare("INSERT INTO driver_list (plate_number, first_name, middle_name, last_name, contact_number, email, address, government_id_number, license_expiration_date, emergency_contact_name, emergency_contact_number, employment_id, company_affiliation, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssssssssssss", 
            $plate_number, 
            $first_name, 
            $middle_name, 
            $last_name, 
            $contact_number, 
            $email, 
            $address, 
            $government_id_number, 
            $license_expiration_date, 
            $emergency_contact_name, 
            $emergency_contact_number, 
            $employment_id, 
            $company_affiliation, 
            $photo_path
        );
        
        if ($stmt->execute()) {
            $driver_msg = "✅ New driver added successfully!";
        } else {
            $driver_msg = "❌ Error adding driver: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $driver_msg = "❌ Please fill in all required fields.";
    }
}

// Fetch drivers list with enhanced information
$drivers = $conn->query("SELECT *, 
    CONCAT(first_name, 
           CASE WHEN middle_name IS NOT NULL AND middle_name != '' 
                THEN CONCAT(' ', middle_name, ' ') 
                ELSE ' ' 
           END, 
           last_name) AS full_name,
    CASE 
        WHEN license_expiration_date < CURDATE() THEN 'EXPIRED'
        WHEN license_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'EXPIRING_SOON'
        ELSE 'VALID'
    END as license_status
    FROM driver_list ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Driver - Enhanced</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .license-expired { color: #dc2626; font-weight: bold; }
        .license-expiring { color: #d97706; font-weight: bold; }
        .license-valid { color: #16a34a; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4 text-orange-600">Add New Driver</h2>

            <?php if (!empty($driver_msg)): ?>
                <div class="mb-4 text-sm px-4 py-2 rounded <?= str_starts_with($driver_msg, '✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                    <?= $driver_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4 mb-8">
                <input type="hidden" name="new_driver" value="1">
                
                <!-- Personal Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name *</label>
                            <input type="text" name="first_name" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name *</label>
                            <input type="text" name="last_name" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Contact Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contact Number *</label>
                            <input type="tel" name="contact_number" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., +639123456789">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" class="w-full border px-3 py-2 rounded mt-1" placeholder="example@email.com">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Address / Residence</label>
                        <textarea name="address" rows="3" class="w-full border px-3 py-2 rounded mt-1" placeholder="Complete address"></textarea>
                    </div>
                </div>

                <!-- Vehicle & License Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Vehicle & License Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Plate Number *</label>
                            <input type="text" name="plate_number" required class="w-full border px-3 py-2 rounded mt-1" placeholder="e.g., ABC-1234">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Government ID / Driver's License No. *</label>
                            <input type="text" name="government_id_number" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">License Expiration Date *</label>
                        <input type="date" name="license_expiration_date" required class="w-full border px-3 py-2 rounded mt-1">
                    </div>
                </div>

                <!-- Emergency Contact Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Emergency Contact</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Emergency Contact Name *</label>
                            <input type="text" name="emergency_contact_name" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Emergency Contact Number *</label>
                            <input type="tel" name="emergency_contact_number" required class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                </div>

                <!-- Employment Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Employment Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employment ID</label>
                            <input type="text" name="employment_id" class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Company Affiliation</label>
                            <input type="text" name="company_affiliation" class="w-full border px-3 py-2 rounded mt-1">
                        </div>
                    </div>
                </div>

                <!-- Photo Upload Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Photo</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Driver Photo</label>
                        <input type="file" name="driver_photo" accept="image/*" class="w-full border px-3 py-2 rounded mt-1">
                        <p class="text-xs text-gray-500 mt-1">Optional. Accepted formats: JPG, PNG, GIF. Max size: 5MB</p>
                    </div>
                </div>

                <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 transition font-semibold">
                    Add Driver
                </button>
            </form>

            <!-- Driver List Section -->
            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Driver List</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border border-gray-300">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-3 py-2 border-b">#</th>
                                <th class="px-3 py-2 border-b">Photo</th>
                                <th class="px-3 py-2 border-b">Full Name</th>
                                <th class="px-3 py-2 border-b">Plate Number</th>
                                <th class="px-3 py-2 border-b">Contact</th>
                                <th class="px-3 py-2 border-b">License Status</th>
                                <th class="px-3 py-2 border-b">Emergency Contact</th>
                                <th class="px-3 py-2 border-b">Company</th>
                                <th class="px-3 py-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($drivers->num_rows > 0): ?>
                                <?php while ($row = $drivers->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 border-b"><?= $row['id'] ?></td>
                                        <td class="px-3 py-2 border-b">
                                            <?php if ($row['photo_path'] && file_exists($row['photo_path'])): ?>
                                                <img src="<?= $row['photo_path'] ?>" alt="Driver Photo" class="w-12 h-12 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center text-xs">No Photo</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                                            <small class="text-gray-600"><?= htmlspecialchars($row['email'] ?: 'No email') ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b font-mono"><?= htmlspecialchars($row['plate_number']) ?></td>
                                        <td class="px-3 py-2 border-b"><?= htmlspecialchars($row['contact_number'] ?: 'N/A') ?></td>
                                        <td class="px-3 py-2 border-b">
                                            <span class="license-<?= strtolower(str_replace('_', '-', $row['license_status'])) ?>">
                                                <?= $row['license_status'] ?>
                                            </span><br>
                                            <small class="text-gray-600"><?= $row['license_expiration_date'] ? date('M d, Y', strtotime($row['license_expiration_date'])) : 'N/A' ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <strong><?= htmlspecialchars($row['emergency_contact_name'] ?: 'N/A') ?></strong><br>
                                            <small class="text-gray-600"><?= htmlspecialchars($row['emergency_contact_number'] ?: 'N/A') ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <?= htmlspecialchars($row['company_affiliation'] ?: 'N/A') ?><br>
                                            <small class="text-gray-600">ID: <?= htmlspecialchars($row['employment_id'] ?: 'N/A') ?></small>
                                        </td>
                                        <td class="px-3 py-2 border-b">
                                            <button class="text-blue-600 hover:text-blue-800 text-xs mr-2" onclick="viewDriver(<?= $row['id'] ?>)">View</button>
                                            <button class="text-green-600 hover:text-green-800 text-xs mr-2" onclick="editDriver(<?= $row['id'] ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-6 text-gray-500">No drivers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewDriver(id) {
            // Implement view driver details functionality
            alert('View driver details for ID: ' + id);
        }

        function editDriver(id) {
            // Implement edit driver functionality
            alert('Edit driver for ID: ' + id);
        }
    </script>
</body>
</html>