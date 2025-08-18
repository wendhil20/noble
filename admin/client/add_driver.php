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

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

$driver_msg = '';

// Handle driver insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_driver'])) {
    $name = trim($_POST['driver_name']);
    $plate = trim($_POST['plate_number']);

    if ($name !== '' && $plate !== '') {
        $stmt = $conn->prepare("INSERT INTO drivers (name, plate_number) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $plate);
        $stmt->execute();
        $stmt->close();
        $driver_msg = " New driver added!";
    } else {
        $driver_msg = " Please fill in both name and plate number.";
    }
}

// Fetch drivers list
$drivers = $conn->query("SELECT * FROM drivers ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Driver</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>


    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4 text-orange-600">Add New Driver</h2>

        <?php if (!empty($driver_msg)): ?>
            <div class="mb-4 text-sm px-4 py-2 rounded <?= str_starts_with($driver_msg, '✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <?= $driver_msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 mb-6">
            <input type="hidden" name="new_driver" value="1">
            <div>
                <label class="block text-sm font-medium text-gray-700">Driver Name</label>
                <input type="text" name="driver_name" required class="w-full border px-3 py-2 rounded mt-1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Plate Number</label>
                <input type="text" name="plate_number" required class="w-full border px-3 py-2 rounded mt-1">
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                Add Driver
            </button>
        </form>

        <h3 class="text-lg font-semibold text-gray-800 mb-2">Driver List</h3>
        <table class="w-full text-sm text-left border border-gray-300">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border-b">#</th>
                    <th class="px-3 py-2 border-b">Name</th>
                    <th class="px-3 py-2 border-b">Plate Number</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($drivers->num_rows > 0): ?>
                    <?php while ($row = $drivers->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 border-b"><?= $row['id'] ?></td>
                            <td class="px-3 py-2 border-b"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="px-3 py-2 border-b"><?= htmlspecialchars($row['plate_number']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-3 text-gray-500">No drivers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
