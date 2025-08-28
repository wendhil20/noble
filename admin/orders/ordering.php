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
  <title>Admin - Orders</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
  </style>
</head>

<body class="bg-gray-100 font-sans">
 

  <div class="container mx-auto p-6">
    <!-- Orders Navigation Tabs -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
      <div class="border-b border-gray-200">
        <nav class="flex space-x-0">
          <button onclick="showTab('pending')" 
                  class="tab-button px-6 py-4 text-sm font-medium border-b-2 transition-colors duration-200 
                         <?php echo ($activeTab === 'pending') ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
            <span class="flex items-center">
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