<?php
session_name("nobleadmin");
session_start();
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

include '../../connection/connect.php';

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Get product info
$product_query = $conn->prepare("SELECT id, product_name, codename FROM products WHERE id = ?");
$product_query->bind_param("i", $product_id);
$product_query->execute();
$product = $product_query->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_sku') {
        $variant_id = intval($_POST['variant_id']);
        $sku_data = $_POST['sku_data'];
        
        // Try to validate as JSON first
        $decoded = json_decode($sku_data);
        
        // If it's valid JSON, save as-is
        // If not valid JSON, convert plain text to JSON format
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Convert plain text to JSON format
            $sku_data = json_encode(["notes" => $sku_data]);
        }
        
        $update = $conn->prepare("UPDATE product_variants SET sku_info = ? WHERE id = ?");
        $update->bind_param("si", $sku_data, $variant_id);
        
        if ($update->execute()) {
            $success_msg = "SKU information updated successfully!";
        } else {
            $error_msg = "Error updating SKU: " . $conn->error;
        }
    }
}

// Get all variants for this product with color and size
$variants_query = $conn->prepare("
    SELECT id, type_id, color, size, sku_info 
    FROM product_variants 
    WHERE product_id = ? 
    ORDER BY id ASC
");
$variants_query->bind_param("i", $product_id);
$variants_query->execute();
$variants = $variants_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage SKU - <?= htmlspecialchars($product['product_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

<?php include '../navbar/top.php'; ?>

<div class="py-10 px-4 max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="javascript:history.back()" class="text-orange-600 hover:text-orange-700 font-medium">
            ← Back to Products
        </a>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-3xl font-bold text-gray-800 mb-2">
            <?= htmlspecialchars($product['product_name']) ?>
        </h2>
        <p class="text-gray-600">Product ID: <?= $product['id'] ?> | Codename: <?= htmlspecialchars($product['codename'] ?? 'N/A') ?></p>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php while ($variant = $variants->fetch_assoc()): ?>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800">Variant ID: <?= $variant['id'] ?></h3>
                        <p class="text-sm text-gray-600">Type ID: <?= htmlspecialchars($variant['type_id']) ?></p>
                        
                        <!-- Display Color and Size -->
                        <div class="flex gap-4 mt-2">
                            <?php if (!empty($variant['color'])): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="10" r="8"/>
                                    </svg>
                                    Color: <?= htmlspecialchars($variant['color']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($variant['size'])): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                    </svg>
                                    Size: <?= htmlspecialchars($variant['size']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button onclick="toggleEdit(<?= $variant['id'] ?>)" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">
                        Edit SKU
                    </button>
                </div>

                <!-- Display current SKU info -->
                <div id="display-<?= $variant['id'] ?>" class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Current SKU Information:</h4>
                    <?php if (!empty($variant['sku_info'])): ?>
                        <?php 
                        $sku_data = json_decode($variant['sku_info'], true);
                        if ($sku_data): 
                        ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (count($sku_data) === 1 && isset($sku_data['notes'])): ?>
                                    <!-- Plain text display -->
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($sku_data['notes']) ?></div>
                                <?php else: ?>
                                    <!-- Structured JSON display -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <?php foreach ($sku_data as $key => $value): ?>
                                            <div class="flex">
                                                <span class="font-semibold text-gray-700 min-w-[120px]"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) ?>:</span>
                                                <span class="text-gray-600"><?= htmlspecialchars($value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-red-500 text-sm">Invalid JSON format</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm italic">No SKU information set</p>
                    <?php endif; ?>
                </div>

                <!-- Edit form (hidden by default) -->
                <form id="edit-<?= $variant['id'] ?>" method="POST" class="hidden">
                    <input type="hidden" name="action" value="update_sku">
                    <input type="hidden" name="variant_id" value="<?= $variant['id'] ?>">
                    
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        SKU Information:
                        <span class="text-xs text-gray-500 font-normal">(JSON format or plain text)</span>
                    </label>
                    <textarea name="sku_data" 
                              rows="10" 
                              class="w-full p-3 border border-gray-300 rounded-lg font-mono text-sm"
                              placeholder='JSON format: {"sku": "PROD-001", "barcode": "123456"}&#10;&#10;OR plain text:&#10;SKU: PROD-001&#10;Barcode: 123456&#10;Supplier: ABC Corp'><?php 
                              // Check if it's plain text format (only has 'notes' field)
                              if (!empty($variant['sku_info'])) {
                                  $sku_display = json_decode($variant['sku_info'], true);
                                  if ($sku_display && count($sku_display) === 1 && isset($sku_display['notes'])) {
                                      // Show plain text without JSON wrapper
                                      echo htmlspecialchars($sku_display['notes']);
                                  } else {
                                      // Show original JSON
                                      echo htmlspecialchars($variant['sku_info']);
                                  }
                              }
                              ?></textarea>
                    
                    <div class="mt-4 text-sm text-gray-600 bg-blue-50 p-3 rounded space-y-3">
                        <div>
                            <strong>Option 1 - JSON format (recommended):</strong>
                            <pre class="mt-2 text-xs bg-white p-2 rounded">{
  "sku": "<?= strtoupper(substr($product['codename'] ?? 'PROD', 0, 4)) ?>-<?= $variant['id'] ?>",
  "barcode": "1234567890123",
  "supplier": "Supplier Name",
  "notes": "Additional info"
}</pre>
                        </div>
                        <div>
                            <strong>Option 2 - Plain text (auto-converted):</strong>
                            <pre class="mt-2 text-xs bg-white p-2 rounded">SKU: <?= strtoupper(substr($product['codename'] ?? 'PROD', 0, 4)) ?>-<?= $variant['id'] ?>
Barcode: 1234567890123
Supplier: ABC Corporation
Weight: 500g
Any additional information here...</pre>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-4">
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md font-medium transition">
                            Save SKU Info
                        </button>
                        <button type="button" 
                                onclick="toggleEdit(<?= $variant['id'] ?>)" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md font-medium transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
function toggleEdit(variantId) {
    const display = document.getElementById('display-' + variantId);
    const form = document.getElementById('edit-' + variantId);
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        display.classList.add('hidden');
    } else {
        form.classList.add('hidden');
        display.classList.remove('hidden');
    }
}
</script>

</body>
</html>