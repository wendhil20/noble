<?php
include '../../connection/connect.php';

$id = $_GET['id'] ?? null;
$success = $error = null;

// Fetch variant info
$variant = null;
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM product_variants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $variant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Save logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $descrip = [];
    for ($i = 1; $i <= 10; $i++) {
        $descrip["descrip$i"] = $_POST["descrip$i"] ?? '';
    }

    // Update product_variants
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

        // ✅ Update the corresponding product's unit/specification/description based on product_name
        if ($variant && isset($variant['namevariant'])) {
            $productName = $variant['namevariant'];
            $unit = $descrip['descrip6'];
            $spec = $descrip['descrip7'];
            $prod_desc = "Unit: $unit | Specification: $spec";

            $stmt2 = $conn->prepare("UPDATE products SET unit = ?, specification = ?, description = ? WHERE product_name = ?");
            $stmt2->bind_param("ssss", $unit, $spec, $prod_desc, $productName);
            if ($stmt2->execute()) {
                $success .= " Product table also updated!";
            } else {
                $error = "Failed to update product: " . $stmt2->error;
            }
            $stmt2->close();
        }
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
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
    <span class="font-semibold text-orange-600">Note:</span> Description 6 is for <strong>Unit</strong> and Description 7 is for <strong>Specification</strong>.
  </p>

  <?php if ($success): ?>
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded border border-green-300"><?= $success ?></div>
  <?php elseif ($error): ?>
    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded border border-red-300"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($variant): ?>
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
