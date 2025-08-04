<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['bulk_ids'])) {
        $ids = array_map('intval', $_POST['bulk_ids']);

        if (isset($_POST['bulk_status'])) {
            $status = $_POST['bulk_status'] === 'new' ? 'new' : 'old';
            $stmt = $conn->prepare("UPDATE product_variants SET status = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("s", $status);
            $stmt->execute();
        }

        if (isset($_POST['bulk_origin'])) {
            $origin = $_POST['bulk_origin'] === 'international' ? 'international' : 'local';
            $stmt = $conn->prepare("UPDATE product_variants SET origin = ? WHERE id IN (" . implode(',', $ids) . ")");
            $stmt->bind_param("s", $origin);
            $stmt->execute();
        }
    }
}

$status_filter = $_GET['status'] ?? '';
$origin_filter = $_GET['origin'] ?? '';

$query = "SELECT * FROM product_variants WHERE 1=1";
if ($status_filter === 'new' || $status_filter === 'old') {
    $query .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($origin_filter === 'local' || $origin_filter === 'international') {
    $query .= " AND origin = '" . $conn->real_escape_string($origin_filter) . "'";
}
$query .= " ORDER BY percent ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Product Variants</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function toggleSelectAll(source) {
      const checkboxes = document.querySelectorAll('.variant-checkbox');
      checkboxes.forEach(cb => cb.checked = source.checked);
    }
  </script>
</head>
<body class="bg-gray-100 font-sans">
<?php include '../navbar/top.php'; ?>
<div class="max-w-full mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-orange-700 mb-6">Manage Product Variant Status & Origin</h1>
  <form method="GET" class="flex flex-wrap gap-4 items-center mb-6">
    <div>
      <label class="text-sm font-medium text-gray-700">Status:</label>
      <select name="status" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
        <option value="old" <?= $status_filter === 'old' ? 'selected' : '' ?>>Old</option>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium text-gray-700">Origin:</label>
      <select name="origin" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm">
        <option value="">All</option>
        <option value="local" <?= $origin_filter === 'local' ? 'selected' : '' ?>>Local</option>
        <option value="international" <?= $origin_filter === 'international' ? 'selected' : '' ?>>International</option>
      </select>
    </div>
    <a href="?" class="text-blue-600 text-sm underline hover:text-blue-800">Reset Filters</a>
  </form>

  <form method="POST">
    <div class="mb-4 flex justify-between items-center">
      <label class="flex items-center gap-2">
        <input type="checkbox" onclick="toggleSelectAll(this)" class="form-checkbox">
        <span class="text-sm font-medium text-gray-700">Select All</span>
      </label>
      <div class="flex gap-2">
        <select name="bulk_status" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Status</option>
          <option value="new">New</option>
          <option value="old">Old</option>
        </select>
        <select name="bulk_origin" class="border rounded px-2 py-1 text-sm">
          <option value="">Change Origin</option>
          <option value="local">Local</option>
          <option value="international">International</option>
        </select>
        <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-1 rounded text-sm">Apply</button>
      </div>
    </div>

    <div class="grid gap-6 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition">
          <label class="flex items-center mb-2">
            <input type="checkbox" name="bulk_ids[]" value="<?= $row['id'] ?>" class="variant-checkbox mr-2">
            <span class="text-sm font-medium text-gray-800">Select</span>
          </label>

          <h2 class="text-lg font-semibold text-gray-800 mb-2">
            <?= htmlspecialchars($row['namevariant']) ?>
          </h2>

          <div class="text-sm text-gray-600 mb-1">Mark Up: <span class="font-medium"><?= $row['percent'] ?>%</span></div>
          <div class="text-sm text-gray-600 mb-3">Discount: <span class="font-medium"><?= $row['discount'] ?>%</span></div>

          <div class="mb-2">
            <span class="text-xs font-semibold inline-block px-2 py-1 rounded-full 
              <?= $row['status'] === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-800' ?>">
              Status: <?= ucfirst($row['status']) ?>
            </span>
          </div>

          <div class="mb-3">
            <span class="text-xs font-semibold inline-block px-2 py-1 rounded-full 
              <?= $row['origin'] === 'international' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-800' ?>">
              Origin: <?= ucfirst($row['origin']) ?>
            </span>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </form>
</div>
</body>
</html>
