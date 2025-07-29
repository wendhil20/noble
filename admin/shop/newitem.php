<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php'; // your DB connection
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']); // allow only admin and superadmin

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

// ✅ Handle update if form is submitted for status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['variant_id'], $_POST['status'])) {
        $id = (int)$_POST['variant_id'];
        $status = $_POST['status'] === 'new' ? 'new' : 'old';

        $update = $conn->prepare("UPDATE product_variants SET status = ? WHERE id = ?");
        $update->bind_param("si", $status, $id);
        $update->execute();
    }

    // ✅ Handle update for origin
    if (isset($_POST['variant_id_origin'], $_POST['origin'])) {
        $id = (int)$_POST['variant_id_origin'];
        $origin = ($_POST['origin'] === 'international') ? 'international' : 'local';

        $update_origin = $conn->prepare("UPDATE product_variants SET origin = ? WHERE id = ?");
        $update_origin->bind_param("si", $origin, $id);
        $update_origin->execute();
    }
}

// ✅ Fetch all variants
$result = $conn->query("SELECT * FROM product_variants ORDER BY percent ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Product Variants</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">
  <?php include '../navbar/top.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Product Variant Status & Origin</h1>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border border-gray-200 bg-white rounded-lg shadow-sm">
        <thead class="bg-gray-100 sticky top-0">
          <tr class="text-left text-gray-700 font-semibold">
            <th class="p-3 border-b">Name</th>
            <th class="p-3 border-b">Mark Up %</th>
            <th class="p-3 border-b">Discount %</th>
            <th class="p-3 border-b">Status</th>
            <th class="p-3 border-b">Origin</th>
            <th class="p-3 border-b text-center">Update Status</th>
            <th class="p-3 border-b text-center">Update Origin</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="p-3 border-b font-medium text-gray-900"><?= htmlspecialchars($row['namevariant']) ?></td>
            <td class="p-3 border-b text-gray-700"><?= $row['percent'] ?>%</td>
            <td class="p-3 border-b text-gray-700"><?= $row['discount'] ?>%</td>
            <td class="p-3 border-b">
              <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full
                <?= $row['status'] === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-800' ?>">
                <?= ucfirst($row['status']) ?>
              </span>
            </td>
            <td class="p-3 border-b">
              <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full
                <?= $row['origin'] === 'international' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-800' ?>">
                <?= ucfirst($row['origin']) ?>
              </span>
            </td>

            <!-- Status Update Form -->
            <td class="p-3 border-b">
              <form method="POST" class="flex items-center gap-2 justify-center">
                <input type="hidden" name="variant_id" value="<?= $row['id'] ?>">
                <select name="status" class="border rounded-md px-2 py-1 text-sm">
                  <option value="new" <?= $row['status'] === 'new' ? 'selected' : '' ?>>New</option>
                  <option value="old" <?= $row['status'] === 'old' ? 'selected' : '' ?>>Old</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 text-xs font-semibold">
                  Save
                </button>
              </form>
            </td>

            <!-- Origin Update Form -->
            <td class="p-3 border-b">
              <form method="POST" class="flex items-center gap-2 justify-center">
                <input type="hidden" name="variant_id_origin" value="<?= $row['id'] ?>">
                <select name="origin" class="border rounded-md px-2 py-1 text-sm">
                  <option value="local" <?= $row['origin'] === 'local' ? 'selected' : '' ?>>Local</option>
                  <option value="international" <?= $row['origin'] === 'international' ? 'selected' : '' ?>>International</option>
                </select>
                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 text-xs font-semibold">
                  Save
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
