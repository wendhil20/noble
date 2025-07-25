<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);

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
$_SESSION['last_activity'] = time();

// Default active page
$activePage = $_GET['page'] ?? 'suppliers';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Warehouse</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .tab-button.active {
      font-weight: bold;
    }
  </style>
</head>
<body class="bg-gray-100">
<?php include '../navbar/top.php'; ?>

<div class="px-4 sm:px-6 lg:px-8 mt-4">
  <div class="text-2xl font-bold text-orange-500 mb-2">Warehouse Management</div>

  <!-- Tab Navigation -->
  <nav class="flex flex-wrap gap-2 sm:gap-4 mb-6">
    <button data-page="suppliers" class="tab-button px-4 py-3 text-sm font-medium border-b-2 rounded-md <?php echo $activePage === 'suppliers' ? 'border-yellow-500 text-yellow-600 bg-yellow-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100'; ?>">
      <span class="flex items-center">
        <span class="ml-2">Supplier Management</span>
       
      </span>
    </button>

    <button data-page="pom" class="tab-button px-4 py-3 text-sm font-medium border-b-2 rounded-md <?php echo $activePage === 'pom' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100'; ?>">
      <span class="flex items-center">
        <span class="ml-2">Purchase Order Management</span>
        
      </span>
    </button>

    <button data-page="inventoryorders" class="tab-button px-4 py-3 text-sm font-medium border-b-2 rounded-md <?php echo $activePage === 'inventoryorders' ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100'; ?>">
      <span class="flex items-center">
        <span class="ml-2">Inventory Suppliers</span>
       
      </span>
    </button>
  </nav>

  <!-- Content Area -->
  <div id="content-area" class="bg-white p-6 rounded-lg shadow">
    Loading...
  </div>
</div>

<!-- JavaScript to load content -->
<script>
  const buttons = document.querySelectorAll(".tab-button");
  const contentArea = document.getElementById("content-area");

  async function loadPage(page) {
    try {
      const response = await fetch(page + ".php");
      const html = await response.text();
      contentArea.innerHTML = html;

      buttons.forEach(btn => btn.classList.remove("border-yellow-500", "border-blue-500", "border-green-500", "bg-yellow-50", "bg-blue-50", "bg-green-50", "text-yellow-600", "text-blue-600", "text-green-600"));
      const activeBtn = document.querySelector(`button[data-page="${page}"]`);
      if (page === 'suppliers') {
        activeBtn.classList.add("border-yellow-500", "bg-yellow-50", "text-yellow-600");
      } else if (page === 'pom') {
        activeBtn.classList.add("border-blue-500", "bg-blue-50", "text-blue-600");
      } else if (page === 'inventoryorders') {
        activeBtn.classList.add("border-green-500", "bg-green-50", "text-green-600");
      }
    } catch (error) {
      contentArea.innerHTML = '<div class="text-red-500">Error loading content. Please try again later.</div>';
    }
  }

  // Initial load
  loadPage("<?php echo $activePage; ?>");

  // Click event
  buttons.forEach(button => {
    button.addEventListener("click", () => {
      const page = button.getAttribute("data-page");
      loadPage(page);
      history.pushState(null, "", `?page=${page}`);
    });
  });
</script>

</body>
</html>
