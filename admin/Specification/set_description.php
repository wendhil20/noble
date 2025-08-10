<?php
session_name("nobleadmin");

session_start();
include '../../connection/connect.php';
include 'link_product_ids.php'; // ✅ Function to link variant to product
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin


// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}


$id = $_GET['id'] ?? null;
$success = $error = null;

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['products', 'product_types', 'product_variants', 'product_colors'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// ✅ Fetch the variant
$variant = null;
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM product_variants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $variant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ✅ Manual link trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_now'])) {
    if ($variant) {
        $linkedId = link_variant_to_product($conn, $variant);
        if ($linkedId) {
            $success = " Variant linked to product successfully (Product ID: $linkedId)";

            // reload updated variant
            $stmt = $conn->prepare("SELECT * FROM product_variants WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $variant = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = " No matching product found with name: <strong>" . htmlspecialchars($variant['namevariant']) . "</strong>";
        }
    } else {
        $error = " Variant not found.";
    }
}

// ✅ Handle description save (only saves to product_variants)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $descrip = [];
    for ($i = 1; $i <= 10; $i++) {
        $descrip["descrip$i"] = trim($_POST["descrip$i"] ?? '');
    }

    // Update product_variants descriptions only
    $sql = "UPDATE product_variants SET 
        descrip1 = ?, descrip2 = ?, descrip3 = ?, descrip4 = ?, descrip5 = ?, 
        descrip6 = ?, descrip7 = ?, descrip8 = ?, descrip9 = ?, descrip10 = ? 
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssi",
        $descrip['descrip1'], $descrip['descrip2'], $descrip['descrip3'],
        $descrip['descrip4'], $descrip['descrip5'], $descrip['descrip6'],
        $descrip['descrip7'], $descrip['descrip8'], $descrip['descrip9'],
        $descrip['descrip10'], $id
    );

    if ($stmt->execute()) {
        $success = "Descriptions updated successfully!";
        $stmt->close();

        // Reload updated variant
        $stmtReload = $conn->prepare("SELECT * FROM product_variants WHERE id = ?");
        $stmtReload->bind_param("i", $id);
        $stmtReload->execute();
        $variant = $stmtReload->get_result()->fetch_assoc();
        $stmtReload->close();

        // Still try linking if not already linked
        if (empty($variant['product_id'])) {
            link_variant_to_product($conn, $variant);
        }

    } else {
        $error = " Error updating variant: " . $stmt->error;
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set Descriptions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

<?php include '../navbar/top.php'; ?>

<div class="bg-white shadow-md rounded-lg p-6 max-w-3xl mx-auto mt-10">
  <h2 class="text-2xl font-bold text-orange-700 mb-4">📝 Set Descriptions</h2>
  <p class="text-gray-700 mb-6">Variant ID: <span class="font-semibold"><?= htmlspecialchars($id) ?></span> |
    Name: <span class="text-orange-600"><?= htmlspecialchars($variant['namevariant'] ?? 'N/A') ?></span>
  </p>

 <p class="text-gray-700 mb-6">
  <span class="font-semibold text-orange-600">Note:</span> Description 1 is for <strong>Name</strong>, 2 for <strong>Size</strong>, Materials 3 for <strong>Color</strong>, 4 for <strong>Other Info</strong>, and 5 for <strong>Dispatch/Shipping</strong>.  
  Description 6 is for <strong>Unit</strong> and Description 7 is for <strong>Specification</strong>.
</p>

  <?php if ($success): ?>
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded border border-green-300"><?= $success ?></div>
  <?php elseif ($error): ?>
    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded border border-red-300"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($variant): ?>
    <!-- 🔗 Link Button -->
    <?php if (empty($variant['product_id'])): ?>
      <form method="POST" class="mb-4">
        <button type="submit" name="link_now" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
          update product
        </button>
      </form>
    <?php else: ?>
      <p class="mb-4 text-sm text-green-600 font-medium">
        Already linked to Product ID: <strong><?= $variant['product_id'] ?></strong>
      </p>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <?php for ($i = 1; $i <= 10; $i++): ?>
        <div>
          <label for="descrip<?= $i ?>" class="block text-sm font-medium text-gray-700 mb-1">
            Description <?= $i ?>
          </label>
          <input type="text" name="descrip<?= $i ?>" id="descrip<?= $i ?>"
            value="<?= htmlspecialchars($variant["descrip$i"] ?? '') ?>"
            class="w-full px-4 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
      <?php endfor; ?>

      <button type="submit" name="save"
        class="mt-4 bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded transition font-medium">
         Save Descriptions
      </button>
    </form>
  <?php else: ?>
    <p class="text-red-600">Variant not found.</p>
  <?php endif; ?>
</div>

</body>
</html>
