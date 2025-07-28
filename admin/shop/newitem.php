<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php'; // your DB connection
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']); // allow only admin and superadmin


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
  <meta charset="UTF-8" />
  <title>Product Variants Status</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6"> Manage Product Variant Status</h1>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border border-gray-200 bg-white rounded-lg shadow-sm">
        <thead class="bg-gray-100 sticky top-0">
          <tr class="text-left text-gray-700 font-semibold">
            <th class="p-3 border-b">Name</th>
            <th class="p-3 border-b">Mark Up %</th>
            <th class="p-3 border-b">Discount %</th>
            <th class="p-3 border-b">Status</th>
            <th class="p-3 border-b text-center">Update</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="p-3 border-b font-medium text-gray-900"><?= htmlspecialchars($row['namevariant']) ?></td>
            <td class="p-3 border-b text-gray-700"><?= $row['percent'] ?>%</td>
            <td class="p-3 border-b text-gray-700"><?= $row['discount'] ?>%</td>
            <td class="p-3 border-b">
              <?php if ($row['status'] === 'new'): ?>
                <span class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">New</span>
              <?php else: ?>
                <span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-200 text-gray-800 rounded-full">Old</span>
              <?php endif; ?>
            </td>
            <td class="p-3 border-b">
              <form method="POST" class="flex items-center gap-2 justify-center">
                <input type="hidden" name="variant_id" value="<?= $row['id'] ?>">
                <select name="status" class="border rounded-md px-2 py-1 text-sm text-gray-700 focus:ring-blue-500 focus:border-blue-500">
                  <option value="new" <?= $row['status'] === 'new' ? 'selected' : '' ?>>New</option>
                  <option value="old" <?= $row['status'] === 'old' ? 'selected' : '' ?>>Old</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 text-xs font-semibold">
                  Update
                </button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
