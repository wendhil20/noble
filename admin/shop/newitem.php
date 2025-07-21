<?php
session_name("nobleadmin");

session_start();
include '../../connection/connect.php'; // your DB connection

include '../role/roleaccount.php';

require_role(['admin', 'superadmin']); // allow only admin and superadmin


// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}


// ✅ Handle update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['variant_id'], $_POST['status'])) {
    $id = (int)$_POST['variant_id'];
    $status = $_POST['status'] === 'new' ? 'new' : 'old'; // validate

    $update = $conn->prepare("UPDATE product_variants SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $id);
    $update->execute();
}

// ✅ Fetch all variants
$result = $conn->query("SELECT * FROM product_variants ORDER BY percent ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Variants Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-4 bg-gray-50 font-sans">

    <h1 class="text-2xl font-bold mb-4">Manage Product Variant Status</h1>

<table class="min-w-full bg-white border border-gray-200">
    <thead>
        <tr class="bg-gray-100 text-left">
            <th class="p-2 border-b">Name</th>
            <th class="p-2 border-b">Mark up Percent</th>
            <th class="p-2 border-b">Discount</th> <!-- ✅ Added -->
            <th class="p-2 border-b">Status</th>
            <th class="p-2 border-b">Update</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td class="p-2 border-b"><?= htmlspecialchars($row['namevariant']) ?></td>
                <td class="p-2 border-b"><?= $row['percent'] ?>%</td>
                <td class="p-2 border-b"><?= $row['discount'] ?>%</td> <!-- ✅ Added -->
                <td class="p-2 border-b text-<?= $row['status'] === 'new' ? 'green' : 'gray' ?>-600 font-medium">
                    <?= ucfirst($row['status']) ?>
                </td>
                <td class="p-2 border-b">
                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="variant_id" value="<?= $row['id'] ?>">
                        <select name="status" class="border rounded px-2 py-1 text-sm">
                            <option value="new" <?= $row['status'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="old" <?= $row['status'] === 'old' ? 'selected' : '' ?>>Old</option>
                        </select>
                        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                            Update
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>


</body>
</html>
