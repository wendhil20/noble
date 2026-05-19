<?php
//set_description-page-5-A.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin


// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  // Redirect to login page
  header("Location: " . BASE_URL . "/main");
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

// ✅ Fetch the product
$product = null;
if ($id) {
  $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// ✅ Handle description save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  $descrip1 = trim($_POST["descrip1"] ?? '');
  $descrip6 = trim($_POST["descrip6"] ?? '');
  $descrip7 = trim($_POST["descrip7"] ?? '');

  // Update products descriptions
  $sql = "UPDATE products SET descrip1 = ?, descrip6 = ?, descrip7 = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sssi", $descrip1, $descrip6, $descrip7, $id);

  if ($stmt->execute()) {
    $success = "Descriptions updated successfully!";
    $stmt->close();

    // Reload updated product
    $stmtReload = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmtReload->bind_param("i", $id);
    $stmtReload->execute();
    $product = $stmtReload->get_result()->fetch_assoc();
    $stmtReload->close();
  } else {
    $error = " Error updating product: " . $stmt->error;
    $stmt->close();
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set Descriptions - Noble Home</title>
</head>

<body class="bg-gray-50 min-h-screen font-sans">

  <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

  <!-- Header -->
  <header class="bg-white border-b border-gray-200">
    <div class="w-full px-6">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
            <i class="fas fa-pen-fancy text-white text-sm"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Product Descriptions</h1>
            <p class="text-sm text-gray-600">Edit product details and specifications</p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full px-6 py-8">

    <!-- Product Info Card -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-noble-orange">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-gray-600 mb-1">
            <i class="fas fa-hashtag mr-2 text-noble-orange"></i>
            <span class="font-medium">Product ID:</span> <?= htmlspecialchars($id) ?>
          </p>
          <p class="text-lg font-bold text-gray-900">
            <i class="fas fa-cube mr-2 text-noble-orange"></i>
            <?= htmlspecialchars($product['product_name'] ?? 'N/A') ?>
          </p>
        </div>
        <div class="text-right">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-noble-orange">
            <i class="fas fa-check-circle mr-1"></i>
            Ready to Edit
          </span>
        </div>
      </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
      <div class="flex items-start space-x-3">
        <i class="fas fa-info-circle text-blue-600 text-lg mt-0.5"></i>
        <div>
          <p class="font-semibold text-blue-900 text-sm mb-2">Description Guidelines:</p>
          <ul class="text-sm text-blue-800 space-y-1">
            <li><strong>Description 1:</strong> Complete Product Details & Information</li>
            <li><strong>Description 6:</strong> Unit Information</li>
            <li><strong>Description 7:</strong>Specifications</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
      <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl flex items-center animate-fade-in">
        <i class="fas fa-check-circle mr-3 text-green-600 text-lg"></i>
        <span><?= $success ?></span>
      </div>
    <?php elseif ($error): ?>
      <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl flex items-center animate-fade-in">
        <i class="fas fa-exclamation-triangle mr-3 text-red-600 text-lg"></i>
        <span><?= $error ?></span>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <?php if ($product): ?>

      <form method="POST" class="space-y-6">

        <!-- Description 1: Full Description -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-t-4 border-yellow-500">
          <label for="descrip1" class="block text-sm font-bold text-gray-900 mb-2">
            <i class="fas fa-align-left text-yellow-600 mr-2"></i>
            Description 1 - Product Details
          </label>
          <div class="mb-3">
            <p class="text-xs text-gray-600">
              <i class="fas fa-info-circle mr-1"></i>
              Write a comprehensive description of your product including name, size, color, materials, features, shipping info, and any other relevant details.
            </p>
          </div>
          <textarea name="descrip1" id="descrip1" rows="12"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent resize-vertical font-normal"
            placeholder="Enter your complete product description here..."><?= htmlspecialchars($product["descrip1"] ?? '') ?></textarea>
          <div class="flex items-center justify-between mt-3">
            <p class="text-xs text-gray-500">
              Character count: <span id="charCount1" class="font-semibold text-yellow-600">0</span> characters
            </p>
            <div class="text-xs text-gray-500">
              Word count: <span id="wordCount1" class="font-semibold text-yellow-600">0</span> words
            </div>
          </div>
        </div>

        <!-- Description 6: Unit -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-t-4 border-indigo-500">
          <label for="descrip6" class="block text-sm font-bold text-gray-900 mb-2">
            <i class="fas fa-box text-indigo-600 mr-2"></i>
            Description 6 - Unit Information
          </label>
          <textarea name="descrip6" id="descrip6" rows="8"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-vertical font-normal"
            placeholder="Enter unit information..."><?= htmlspecialchars($product["descrip6"] ?? '') ?></textarea>
          <div class="flex items-center justify-between mt-3">
            <p class="text-xs text-gray-500">
              Character count: <span id="charCount6" class="font-semibold text-indigo-600">0</span> characters
            </p>
            <div class="text-xs text-gray-500">
              Word count: <span id="wordCount6" class="font-semibold text-indigo-600">0</span> words
            </div>
          </div>
        </div>

        <!-- Description 7: Specifications -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-t-4 border-cyan-500">
          <label for="descrip7" class="block text-sm font-bold text-gray-900 mb-2">
            <i class="fas fa-cogs text-cyan-600 mr-2"></i>
            Description 7 - Unit of Measurement Specifications
          </label>
       
          <textarea name="descrip7" id="descrip7" rows="8"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent resize-vertical font-normal"
            placeholder="Enter packaging specifications... Box, Set ,Bundle"><?= htmlspecialchars($product["descrip7"] ?? '') ?></textarea>
          <div class="flex items-center justify-between mt-3">
            <p class="text-xs text-gray-500">
              Character count: <span id="charCount7" class="font-semibold text-cyan-600">0</span> characters
            </p>
            <div class="text-xs text-gray-500">
              Word count: <span id="wordCount7" class="font-semibold text-cyan-600">0</span> words
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-4 mt-8 pt-6 border-t border-gray-200">
          <button type="submit" name="save"
            class="inline-flex items-center px-6 py-3 bg-noble-orange hover:bg-noble-orange-dark text-white rounded-lg transition font-semibold shadow-sm hover:shadow-md">
            <i class="fas fa-save mr-2"></i>
            Save All Descriptions
          </button>
          <button type="reset"
            class="inline-flex items-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition font-semibold">
            <i class="fas fa-redo mr-2"></i>
            Reset
          </button>
          <a href="javascript:history.back()"
            class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition font-semibold">
            <i class="fas fa-arrow-left mr-2"></i>
            Back
          </a>
        </div>

      </form>

    <?php else: ?>
      <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <i class="fas fa-exclamation-triangle text-red-600 text-4xl mb-3"></i>
        <p class="text-red-700 font-semibold text-lg">Product not found</p>
        <p class="text-red-600 mt-2">The product you're looking for doesn't exist.</p>
      </div>
    <?php endif; ?>

  </main>

  <!-- Character & Word Counter Script -->
  <script>
    // Function to update counts for a specific textarea
    function setupCounter(fieldNum) {
      const textarea = document.getElementById(`descrip${fieldNum}`);
      const charCountSpan = document.getElementById(`charCount${fieldNum}`);
      const wordCountSpan = document.getElementById(`wordCount${fieldNum}`);
      
      if (!textarea || !charCountSpan || !wordCountSpan) return;
      
      function updateCounts() {
        const text = textarea.value;
        const charCount = text.length;
        const wordCount = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
        
        charCountSpan.textContent = charCount;
        wordCountSpan.textContent = wordCount;
      }
      
      // Set initial counts
      updateCounts();
      
      // Update on input
      textarea.addEventListener('input', updateCounts);
    }
    
    // Setup counters for all fields
    setupCounter(1);
    setupCounter(6);
    setupCounter(7);
  </script>

</body>

</html>