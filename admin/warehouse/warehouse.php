<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php'; 
require_once '../role/roleaccount.php'; 
require_role(['warehouse','superadmin']);

// Session Checks
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Warehouse Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include '../navbar/top.php'; ?>
  <div class="flex h-screen">

 <aside class="w-64 bg-white shadow-lg p-5 space-y-6">
  <h2 class="text-2xl font-bold text-orange-500 mb-6">📚 Warehouse</h2>

  <nav class="flex flex-col space-y-2">
    <a href="inventory.php"
       class="tab-button px-4 py-3 text-sm font-medium border-l-4 transition-colors duration-200
              <?php echo ($activePage === 'inventory') 
                  ? 'border-yellow-500 text-yellow-600 bg-yellow-50' 
                  : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300'; ?>">
      <span class="flex items-center justify-between">
        <span class="flex items-center">
          📦 <span class="ml-2">Real-time Stock</span>
        </span>
        <span class="ml-auto bg-yellow-100 text-yellow-600 text-xs font-semibold px-2 py-0.5 rounded-full">
          <?php echo $inventoryCount ?? 0; ?>
        </span>
      </span>
    </a>

    <a href="receiving.php"
       class="tab-button px-4 py-3 text-sm font-medium border-l-4 transition-colors duration-200
              <?php echo ($activePage === 'receiving') 
                  ? 'border-blue-500 text-blue-600 bg-blue-50' 
                  : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300'; ?>">
      <span class="flex items-center justify-between">
        <span class="flex items-center">
          🚚 <span class="ml-2">Incoming Shipments</span>
        </span>
        <span class="ml-auto bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full">
          <?php echo $receivingCount ?? 0; ?>
        </span>
      </span>
    </a>

    <a href="orders.php"
       class="tab-button px-4 py-3 text-sm font-medium border-l-4 transition-colors duration-200
              <?php echo ($activePage === 'orders') 
                  ? 'border-green-500 text-green-600 bg-green-50' 
                  : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300'; ?>">
      <span class="flex items-center justify-between">
        <span class="flex items-center">
          📝 <span class="ml-2">Orders to Process</span>
        </span>
        <span class="ml-auto bg-green-100 text-green-600 text-xs font-semibold px-2 py-0.5 rounded-full">
          <?php echo $ordersCount ?? 0; ?>
        </span>
      </span>
    </a>
  </nav>
</aside>

    <!-- Main Content -->
    <main class="flex-1 p-10 overflow-y-auto">
   
    </main>

  </div>

</body>
</html>
