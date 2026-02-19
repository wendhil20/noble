<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']); // allow only admin and superadmin

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

include '../navbar/top.php';
// Get order counts
$pendingOrders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending' OR status IS NULL OR status = ''");
$ongoingOrders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Ongoing'");
$rejectedOrders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Rejected'");

$pendingCount = $pendingOrders->fetch_assoc()['count'];
$ongoingCount = $ongoingOrders->fetch_assoc()['count'];
$rejectedCount = $rejectedOrders->fetch_assoc()['count'];

$activeTab = $_GET['tab'] ?? 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Orders</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#fff7ed',
              100: '#ffedd5',
              200: '#fed7aa',
              300: '#fdba74',
              400: '#fb923c',
              500: '#f97316',
              600: '#ea580c',
              700: '#c2410c',
              800: '#9a3412',
              900: '#7c2d12',
            }
          }
        }
      }
    }
  </script>
  <style>
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
    }
    
    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }
      .print-area, .print-area * {
        visibility: visible;
      }
      .print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
      .no-print {
        display: none !important;
      }
    }

    /* Mobile Tab Sidebar */
    .mobile-tab-sidebar {
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-tab-backdrop {
      transition: opacity 0.3s ease-in-out;
    }

    /* Hide scrollbar but keep functionality */
    .hide-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .hide-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    /* Smooth scroll for mobile tabs */
    .mobile-tab-container {
      scroll-behavior: smooth;
    }
  </style>
</head>

<body class="bg-gray-50 font-roboto">

  <!-- Alpine.js Data Wrapper -->
  <div x-data="{ 
    mobileTabOpen: false,
    activeTab: '<?php echo $activeTab; ?>'
  }">

    <div class="container mx-auto p-4 md:p-6">
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-2xl md:text-3xl text-gray-800">Order Management</h1>
      <p class="text-gray-600 text-sm mt-1">Manage and track all customer orders</p>
    </div>

    <!-- Mobile Tab Button (Visible only on mobile) -->
    <div class="md:hidden mb-4">
      <button @click="mobileTabOpen = true"
              class="w-full flex items-center justify-between px-4 py-3 bg-white rounded-lg shadow-sm border border-gray-200">
        <span class="flex items-center space-x-2">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
          <span class="font-medium text-gray-700">
            <span x-show="activeTab === 'pending'">Pending Orders</span>
            <span x-show="activeTab === 'ongoing'">Ongoing Orders</span>
            <span x-show="activeTab === 'rejected'">Rejected Orders</span>
          </span>
        </span>
        <span class="text-sm px-2.5 py-1 rounded-full font-semibold"
              :class="{
                'bg-orange-100 text-orange-600': activeTab === 'pending',
                'bg-green-100 text-green-600': activeTab === 'ongoing',
                'bg-red-100 text-red-600': activeTab === 'rejected'
              }">
          <span x-show="activeTab === 'pending'"><?php echo $pendingCount; ?></span>
          <span x-show="activeTab === 'ongoing'"><?php echo $ongoingCount; ?></span>
          <span x-show="activeTab === 'rejected'"><?php echo $rejectedCount; ?></span>
        </span>
      </button>
    </div>

    <!-- Mobile Tab Sidebar Backdrop -->
    <div x-show="mobileTabOpen"
         @click="mobileTabOpen = false"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden mobile-tab-backdrop">
    </div>

    <!-- Mobile Tab Sidebar -->
    <aside x-show="mobileTabOpen"
           x-transition:enter="transition ease-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           @click.away="mobileTabOpen = false"
           x-cloak
           class="fixed left-0 top-0 h-full w-80 bg-white shadow-2xl z-50 md:hidden mobile-tab-sidebar overflow-y-auto">
      
      <!-- Sidebar Header -->
      <div class="sticky top-0 bg-white z-10 border-b border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-800">Order Tabs</h2>
          <button @click="mobileTabOpen = false" 
                  class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Sidebar Navigation -->
      <nav class="p-4 space-y-2">
        <!-- Pending Tab -->
        <button @click="activeTab = 'pending'; mobileTabOpen = false; showTab('pending')"
                class="w-full flex items-center justify-between px-4 py-4 rounded-xl font-medium transition-all duration-200"
                :class="activeTab === 'pending' 
                  ? 'bg-gradient-to-r from-orange-50 to-orange-100 text-orange-600 border-l-4 border-orange-500' 
                  : 'text-gray-700 hover:bg-gray-50'">
          <div class="flex items-center space-x-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Pending Orders</span>
          </div>
          <span class="px-3 py-1 text-xs font-semibold rounded-full"
                :class="activeTab === 'pending' 
                  ? 'bg-orange-200 text-orange-700' 
                  : 'bg-gray-200 text-gray-700'">
            <?php echo $pendingCount; ?>
          </span>
        </button>

        <!-- Ongoing Tab -->
        <button @click="activeTab = 'ongoing'; mobileTabOpen = false; showTab('ongoing')"
                class="w-full flex items-center justify-between px-4 py-4 rounded-xl font-medium transition-all duration-200"
                :class="activeTab === 'ongoing' 
                  ? 'bg-gradient-to-r from-green-50 to-green-100 text-green-600 border-l-4 border-green-500' 
                  : 'text-gray-700 hover:bg-gray-50'">
          <div class="flex items-center space-x-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <span>Ongoing Orders</span>
          </div>
          <span class="px-3 py-1 text-xs font-semibold rounded-full"
                :class="activeTab === 'ongoing' 
                  ? 'bg-green-200 text-green-700' 
                  : 'bg-gray-200 text-gray-700'">
            <?php echo $ongoingCount; ?>
          </span>
        </button>

        <!-- Rejected Tab -->
        <button @click="activeTab = 'rejected'; mobileTabOpen = false; showTab('rejected')"
                class="w-full flex items-center justify-between px-4 py-4 rounded-xl font-medium transition-all duration-200"
                :class="activeTab === 'rejected' 
                  ? 'bg-gradient-to-r from-red-50 to-red-100 text-red-600 border-l-4 border-red-500' 
                  : 'text-gray-700 hover:bg-gray-50'">
          <div class="flex items-center space-x-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Rejected Orders</span>
          </div>
          <span class="px-3 py-1 text-xs font-semibold rounded-full"
                :class="activeTab === 'rejected' 
                  ? 'bg-red-200 text-red-700' 
                  : 'bg-gray-200 text-gray-700'">
            <?php echo $rejectedCount; ?>
          </span>
        </button>

        <!-- Divider -->
        <div class="pt-4 border-t border-gray-200"></div>

        <!-- Quick Stats -->
        <div class="px-4 py-3 bg-gray-50 rounded-xl">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Order Statistics</p>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Total Pending</span>
              <span class="font-semibold text-orange-600"><?php echo $pendingCount; ?></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Total Ongoing</span>
              <span class="font-semibold text-green-600"><?php echo $ongoingCount; ?></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Total Rejected</span>
              <span class="font-semibold text-red-600"><?php echo $rejectedCount; ?></span>
            </div>
            <div class="pt-2 border-t border-gray-200 mt-2">
              <div class="flex justify-between items-center">
                <span class="text-sm font-semibold text-gray-700">Total Orders</span>
                <span class="font-bold text-gray-800"><?php echo $pendingCount + $ongoingCount + $rejectedCount; ?></span>
              </div>
            </div>
          </div>
        </div>
      </nav>
    </aside>

    <!-- Desktop Tabs Navigation (Hidden on mobile) -->
    <div class="hidden md:block bg-white rounded-lg shadow-sm mb-6">
      <div class="border-b border-gray-200">
        <nav class="flex space-x-0">
          <button onclick="showTab('pending')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'pending') ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              Pending
              <span class="ml-2 bg-orange-100 text-orange-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $pendingCount; ?>
              </span>
            </span>
          </button>
          
          <button onclick="showTab('ongoing')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'ongoing') ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
              Ongoing
              <span class="ml-2 bg-green-100 text-green-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $ongoingCount; ?>
              </span>
            </span>
          </button>
          
          <button onclick="showTab('rejected')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'rejected') ? 'border-red-500 text-red-600 bg-red-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              Rejected
              <span class="ml-2 bg-red-100 text-red-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                <?php echo $rejectedCount; ?>
              </span>
            </span>
          </button>
        </nav>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="bg-white rounded-lg shadow-sm">
      <!-- Pending Orders Tab -->
      <div id="pending-tab" class="tab-content <?php echo ($activeTab === 'pending') ? 'active' : ''; ?>">
        <?php include 'orders.php'; ?>
      </div>

      <!-- Ongoing Orders Tab -->
      <div id="ongoing-tab" class="tab-content <?php echo ($activeTab === 'ongoing') ? 'active' : ''; ?>">
        <?php include 'ongoing_orders.php'; ?>
      </div>

      <!-- Rejected Orders Tab -->
      <div id="rejected-tab" class="tab-content <?php echo ($activeTab === 'rejected') ? 'active' : ''; ?>">
        <?php include 'rejected_orders.php'; ?>
      </div>
    </div>
  </div>

  <!-- Hidden Print Area -->
  <div id="printArea" class="print-area hidden">
    <!-- Print content will be injected here -->
  </div>

  <script>
    // Define activeTab variable first
    const activeTab = '<?php echo $activeTab; ?>';
    
    // Main showTab function
    function showTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
      });
      
      // Reset all tab buttons to inactive state
      document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-orange-500', 'text-orange-600', 'bg-orange-50');
        button.classList.remove('border-green-500', 'text-green-600', 'bg-green-50');
        button.classList.remove('border-red-500', 'text-red-600', 'bg-red-50');
        button.classList.add('border-transparent', 'text-gray-500');
      });
      
      // Show the selected tab content
      const tabContent = document.getElementById(tabName + '-tab');
      if (tabContent) {
        tabContent.classList.add('active');
      }
      
      // Find and activate the clicked button
      const activeButton = document.querySelector(`button[onclick="showTab('${tabName}')"]`);
      if (activeButton) {
        activeButton.classList.remove('border-transparent', 'text-gray-500');
        
        if (tabName === 'pending') {
          activeButton.classList.add('border-orange-500', 'text-orange-600', 'bg-orange-50');
        } else if (tabName === 'ongoing') {
          activeButton.classList.add('border-green-500', 'text-green-600', 'bg-green-50');
        } else if (tabName === 'rejected') {
          activeButton.classList.add('border-red-500', 'text-red-600', 'bg-red-50');
        }
      }
    }

    function downloadExcel(orderId) {
      window.open('export_excel.php?order_id=' + orderId, '_blank');
    }

    // Print Order function
    function printOrder(orderId) {
      const printContent = createPrintContent(orderId);
      document.getElementById('printArea').innerHTML = printContent;
      window.print();
    }

    function createPrintContent(orderId) {
      return `
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
          <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #ea580c; margin-bottom: 10px;">NobleHome</h1>
            <h2 style="color: #333; margin-bottom: 20px;">Order Confirmation</h2>
          </div>
          
          <div style="border: 2px solid #10b981; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: #10b981; margin-bottom: 15px;">Order #${orderId}</h3>
            <p style="margin-bottom: 10px;"><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
            <p style="margin-bottom: 10px;"><strong>Status:</strong> <span style="color: #10b981;">Confirmed</span></p>
          </div>
          
          <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
            <p style="color: #666; font-size: 14px;">Thank you for choosing NobleHome!</p>
            <p style="color: #666; font-size: 14px;">For questions, contact us at: wendhil10@gmail.com</p>
          </div>
        </div>
      `;
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, active tab:', activeTab);
      
      // Set active tab on page load
      if (activeTab) {
        showTab(activeTab);
      }
    });
  </script>
</body>
</html>