<?php
//replacement_requests.php
include '../../connection/connect.php';

session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']); // allow only admin and superadmin

// Set noble_name and noble_lvl from DB if not already set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
  $email = $_SESSION['noble_user'];
  $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->bind_result($name, $lvl);
  if ($stmt->fetch()) {
    $_SESSION['noble_name'] = $name;
    $_SESSION['noble_lvl'] = $lvl;
  } else {
    $_SESSION['noble_name'] = "Unknown User";
    $_SESSION['noble_lvl'] = "guest";
  }
  $stmt->close();
}

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
  header("Location: orders.php");
  exit();
}

// Verify order belongs to current employee
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT id, customer_name FROM orders WHERE id = ? AND emp_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $emp_id);
$stmt->execute();
$stmt->bind_result($verified_order_id, $customer_name);
if (!$stmt->fetch()) {
  header("Location: orders.php");
  exit();
}
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Replacement Requests - Order #<?php echo $order_id; ?></title>
  <style>
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.9);
    }
    
    .modal-content {
      margin: auto;
      display: block;
      width: 80%;
      max-width: 700px;
      max-height: 80%;
      object-fit: contain;
    }
    
    .close {
      position: absolute;
      top: 15px;
      right: 35px;
      color: #f1f1f1;
      font-size: 40px;
      font-weight: bold;
      transition: 0.3s;
      cursor: pointer;
    }
    
    .close:hover {
      color: #bbb;
    }
    
    .image-thumbnail {
      cursor: pointer;
      transition: transform 0.2s;
    }
    
    .image-thumbnail:hover {
      transform: scale(1.05);
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen">
  <?php include '../navbar/top.php' ?>
  <!-- Header Section -->
  <div class="bg-white shadow-lg border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-6">
        <div class="flex items-center space-x-4">
          <a href="ordering.php" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
            <i class="fas fa-arrow-left text-gray-600"></i>
          </a>
          <div class="bg-orange-600 p-3 rounded-xl shadow-lg">
            <i class="fas fa-exchange-alt text-white text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Replacement Requests</h1>
            <p class="text-gray-600 mt-1">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($customer_name); ?></p>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <div class="bg-orange-50 px-4 py-2 rounded-lg">
            <span class="text-orange-700 font-medium" id="requestCount">Loading...</span>
          </div>
          <button onclick="loadRequests()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <i class="fas fa-sync-alt"></i>
            <span>Refresh</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Alert Container -->
    <div id="alertContainer" class="mb-6"></div>

    <!-- Loading State -->
    <div id="loadingState" class="hidden">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
          <span class="ml-3 text-gray-600">Loading replacement requests...</span>
        </div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center space-x-2">
          <i class="fas fa-filter text-gray-400"></i>
          <span class="text-gray-700 font-medium">Filter by Status:</span>
        </div>
        <div class="flex space-x-2">
          <button onclick="filterRequests('all')" class="filter-btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors duration-200 active" data-filter="all">All</button>
          <button onclick="filterRequests('pending')" class="filter-btn bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg hover:bg-yellow-200 transition-colors duration-200" data-filter="pending">Pending</button>
          <button onclick="filterRequests('approved')" class="filter-btn bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors duration-200" data-filter="approved">Approved</button>
          <button onclick="filterRequests('rejected')" class="filter-btn bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors duration-200" data-filter="rejected">Rejected</button>
          <button onclick="filterRequests('processing')" class="filter-btn bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors duration-200" data-filter="processing">Processing</button>
          <button onclick="filterRequests('completed')" class="filter-btn bg-purple-100 text-purple-700 px-4 py-2 rounded-lg hover:bg-purple-200 transition-colors duration-200" data-filter="completed">Completed</button>
        </div>
      </div>
    </div>

    <!-- Requests Container -->
    <div id="requestsContainer" class="space-y-6"></div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
      <div class="text-gray-400 mb-4">
        <i class="fas fa-exchange-alt text-6xl"></i>
      </div>
      <h3 class="text-xl font-semibold text-gray-900 mb-2">No Replacement Requests Found</h3>
      <p class="text-gray-600">There are no replacement requests for this order.</p>
    </div>
  </div>

  <!-- Image Modal -->
  <div id="imageModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="text-center text-white mt-4 text-lg"></div>
  </div>

  <script>
    let allRequests = [];
    let currentFilter = 'all';
    const orderId = <?php echo $order_id; ?>;

    // Show alert message
    function showAlert(message, type = 'info') {
      const alertContainer = document.getElementById('alertContainer');
      const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle'
      };

      const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
      };

      alertContainer.innerHTML = `
        <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-sm animate-pulse">
          <div class="flex items-center">
            <i class="${icons[type]} text-xl mr-3"></i>
            <div>
              <p class="font-medium">${message}</p>
            </div>
          </div>
        </div>`;

      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 5000);
    }

    // Filter requests
    function filterRequests(status) {
      currentFilter = status;

      document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
        if (btn.dataset.filter === status) {
          btn.classList.add('active', 'bg-orange-100', 'text-orange-700', 'border-orange-200');
        }
      });

      renderRequests();
    }

    // Load replacement requests
    function loadRequests() {
      const loadingState = document.getElementById('loadingState');
      const requestsContainer = document.getElementById('requestsContainer');

      loadingState.classList.remove('hidden');
      requestsContainer.innerHTML = '';

      fetch(`fetch_replacement_requests.php?order_id=${orderId}`)
        .then(res => {
          if (!res.ok) throw new Error('Failed to fetch replacement requests');
          return res.json();
        })
        .then(requests => {
          allRequests = requests;
          updateRequestCount();
          renderRequests();
        })
        .catch(error => {
          console.error('Error loading requests:', error);
          showAlert('Failed to load replacement requests. Please try again.', 'error');
        })
        .finally(() => {
          loadingState.classList.add('hidden');
        });
    }

    // Update request count
    function updateRequestCount() {
      const requestCount = document.getElementById('requestCount');
      const total = allRequests.length;
      const pending = allRequests.filter(r => r.status === 'pending').length;
      const approved = allRequests.filter(r => r.status === 'approved').length;
      const rejected = allRequests.filter(r => r.status === 'rejected').length;

      requestCount.innerHTML = `${total} Total • ${pending} Pending • ${approved} Approved • ${rejected} Rejected`;
    }

    // Render requests
    function renderRequests() {
      const container = document.getElementById('requestsContainer');
      const emptyState = document.getElementById('emptyState');

      let filteredRequests = allRequests;
      if (currentFilter !== 'all') {
        filteredRequests = allRequests.filter(request => request.status === currentFilter);
      }

      if (filteredRequests.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }

      emptyState.classList.add('hidden');
      container.innerHTML = '';

      filteredRequests.forEach(request => {
        const statusConfig = {
          pending: { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: 'fas fa-clock', text: 'Pending' },
          approved: { class: 'bg-green-100 text-green-800 border-green-200', icon: 'fas fa-check-circle', text: 'Approved' },
          rejected: { class: 'bg-red-100 text-red-800 border-red-200', icon: 'fas fa-times-circle', text: 'Rejected' },
          processing: { class: 'bg-blue-100 text-blue-800 border-blue-200', icon: 'fas fa-cog fa-spin', text: 'Processing' },
          completed: { class: 'bg-purple-100 text-purple-800 border-purple-200', icon: 'fas fa-check-double', text: 'Completed' },
          cancelled: { class: 'bg-gray-100 text-gray-800 border-gray-200', icon: 'fas fa-ban', text: 'Cancelled' }
        };

        const reasonConfig = {
          defective: { icon: 'fas fa-tools', text: 'Defective Product' },
          damaged: { icon: 'fas fa-exclamation-triangle', text: 'Damaged in Transit' },
          wrong_item: { icon: 'fas fa-exchange-alt', text: 'Wrong Item Received' },
          wrong_size: { icon: 'fas fa-ruler', text: 'Wrong Size' },
          not_as_described: { icon: 'fas fa-info-circle', text: 'Not as Described' },
          other: { icon: 'fas fa-question-circle', text: 'Other Reason' }
        };

        const status = statusConfig[request.status] || statusConfig.pending;
        const reason = reasonConfig[request.reason] || reasonConfig.other;

        container.innerHTML += `
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <!-- Request Header -->
            <div class="flex justify-between items-start mb-4">
              <div class="flex items-center space-x-4">
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-3 rounded-xl shadow-lg">
                  <i class="fas fa-exchange-alt text-white text-xl"></i>
                </div>
                <div>
                  <h3 class="text-xl font-bold text-gray-900">Request #${request.id}</h3>
                  <p class="text-sm text-gray-600">Created: ${request.created_at}</p>
                  <p class="text-sm text-gray-600">Updated: ${request.updated_at}</p>
                </div>
              </div>
              <div class="flex items-center space-x-2 ${status.class} px-3 py-1 rounded-full border text-sm font-medium">
                <i class="${status.icon}"></i>
                <span>${status.text}</span>
              </div>
            </div>

            <!-- Product Information -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-box text-orange-600 mr-2"></i>
                    Product Details
                  </h4>
                  <p class="font-medium text-gray-900">${request.product_name}</p>
                  <p class="text-sm text-gray-600">Size: ${request.size} • Color: ${request.variant_color}</p>
                  <p class="text-sm text-gray-600">Code: ${request.codename}</p>
                  <p class="text-sm text-gray-600">Quantity Requested: ${request.replacement_quantity}</p>
                </div>
                <div>
                  <h4 class="font-semibold text-gray-900 mb-2 flex items-center">
                    <i class="${reason.icon} text-orange-600 mr-2"></i>
                    Replacement Reason
                  </h4>
                  <p class="font-medium text-orange-700">${reason.text}</p>
                  ${request.details ? `<p class="text-sm text-gray-600 mt-1">${request.details}</p>` : ''}
                </div>
              </div>
            </div>

            <!-- Images Section -->
            <div class="mb-4">
              <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                <i class="fas fa-images text-orange-600 mr-2"></i>
                Defect Images
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-700 mb-2">Overview</p>
                  <img src="../../uploads/defect_proof/${request.defect_image_overview}" 
                       alt="Overview Image" 
                       class="w-full h-48 object-cover rounded-lg border border-gray-200 image-thumbnail"
                       onclick="openModal(this.src, 'Overview Image')">
                </div>
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-700 mb-2">Close-up</p>
                  <img src="../../uploads/defect_proof/${request.defect_image_closeup}" 
                       alt="Close-up Image" 
                       class="w-full h-48 object-cover rounded-lg border border-gray-200 image-thumbnail"
                       onclick="openModal(this.src, 'Close-up Image')">
                </div>
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-700 mb-2">Detail</p>
                  <img src="../../uploads/defect_proof/${request.defect_image_detail}" 
                       alt="Detail Image" 
                       class="w-full h-48 object-cover rounded-lg border border-gray-200 image-thumbnail"
                       onclick="openModal(this.src, 'Detail Image')">
                </div>
              </div>
            </div>

            <!-- Admin Notes -->
            ${request.admin_notes ? `
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
              <h4 class="font-semibold text-blue-900 mb-2 flex items-center">
                <i class="fas fa-sticky-note text-blue-600 mr-2"></i>
                Admin Notes
              </h4>
              <p class="text-blue-800">${request.admin_notes}</p>
            </div>` : ''}

            <!-- Action Buttons -->
            ${request.status === 'pending' ? `
            <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
              <button onclick="updateRequestStatus(${request.id}, 'approved')" 
                      class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 text-sm">
                <i class="fas fa-check"></i>
                <span>Approve</span>
              </button>
              <button onclick="updateRequestStatus(${request.id}, 'rejected')" 
                      class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 text-sm">
                <i class="fas fa-times"></i>
                <span>Reject</span>
              </button>
            </div>` : ''}
          </div>`;
      });
    }

    // Open image modal
    function openModal(src, caption) {
      const modal = document.getElementById('imageModal');
      const modalImg = document.getElementById('modalImage');
      const modalCaption = document.getElementById('modalCaption');
      
      modal.style.display = 'block';
      modalImg.src = src;
      modalCaption.innerHTML = caption;
    }

    // Close image modal
    function closeModal() {
      document.getElementById('imageModal').style.display = 'none';
    }

    // Update request status
    function updateRequestStatus(requestId, status) {
      const action = status === 'approved' ? 'approve' : 'reject';
      
      if (!confirm(`Are you sure you want to ${action} this replacement request?`)) {
        return;
      }

      fetch('update_replacement_status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          request_id: requestId,
          status: status,
          admin_notes: '' // You can add a modal to collect notes if needed
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert(`Replacement request ${status} successfully!`, 'success');
          loadRequests();
        } else {
          showAlert(data.message || 'Failed to update request status', 'error');
        }
      })
      .catch(error => {
        console.error('Error updating request:', error);
        showAlert('Failed to update request status', 'error');
      });
    }

    // Close modal when clicking outside the image
    window.onclick = function(event) {
      const modal = document.getElementById('imageModal');
      if (event.target === modal) {
        closeModal();
      }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', loadRequests);
  </script>
</body>
</html>