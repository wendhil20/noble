<?php
//view-inspiration.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

$message = '';
$message_type = '';

// Handle status toggle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
  $id = intval($_POST['id'] ?? 0);
  
  if ($id > 0) {
    try {
      // Check current status of this item
      $check_query = "SELECT status FROM admin_inspiration WHERE id = ?";
      $stmt = $conn->prepare($check_query);
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $current_status = $row['status'] ?? 'off';
      $stmt->close();

      // If trying to turn ON, check if already 2 items are ON
      if ($current_status === 'off') {
        $count_query = "SELECT COUNT(*) as count FROM admin_inspiration WHERE status = 'on'";
        $count_result = $conn->query($count_query);
        $count_row = $count_result->fetch_assoc();
        
        if ($count_row['count'] >= 2) {
          $message = 'Cannot turn on: Maximum 2 items can be ON at a time.';
          $message_type = 'error';
        } else {
          // Turn ON
          $toggle_query = "UPDATE admin_inspiration SET status = 'on' WHERE id = ?";
          $stmt = $conn->prepare($toggle_query);
          $stmt->bind_param("i", $id);
          
          if ($stmt->execute()) {
            $message = 'Status updated successfully!';
            $message_type = 'success';
          } else {
            $message = 'Error updating status: ' . $stmt->error;
            $message_type = 'error';
          }
          $stmt->close();
        }
      } else {
        // Turn OFF
        $toggle_query = "UPDATE admin_inspiration SET status = 'off' WHERE id = ?";
        $stmt = $conn->prepare($toggle_query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
          $message = 'Status updated successfully!';
          $message_type = 'success';
        } else {
          $message = 'Error updating status: ' . $stmt->error;
          $message_type = 'error';
        }
        $stmt->close();
      }
    } catch (Exception $e) {
      $message = 'Error: ' . $e->getMessage();
      $message_type = 'error';
    }
  }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  
  if ($id > 0) {
    try {
      // Get the inspiration entry to delete associated files
      $get_query = "SELECT main_image, image_1, image_2, image_3 FROM admin_inspiration WHERE id = ?";
      $stmt = $conn->prepare($get_query);
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $stmt->close();

      if ($row) {
        $upload_dir = '../../uploads/';
        
        // Delete main image
        if (!empty($row['main_image'])) {
          $file_path = $upload_dir . $row['main_image'];
          if (file_exists($file_path)) {
            unlink($file_path);
          }
        }

        // Delete images from JSON
        foreach (['image_1', 'image_2', 'image_3'] as $key) {
          if (!empty($row[$key])) {
            $images = json_decode($row[$key], true);
            if (is_array($images)) {
              foreach ($images as $img) {
                $file_path = $upload_dir . $img['filename'];
                if (file_exists($file_path)) {
                  unlink($file_path);
                }
              }
            }
          }
        }

        // Delete from database
        $delete_query = "DELETE FROM admin_inspiration WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
          $message = 'Inspiration deleted successfully!';
          $message_type = 'success';
        } else {
          $message = 'Error deleting inspiration: ' . $stmt->error;
          $message_type = 'error';
        }
        $stmt->close();
      }
    } catch (Exception $e) {
      $message = 'Error: ' . $e->getMessage();
      $message_type = 'error';
    }
  }
}

// Fetch all inspiration entries
$inspirations = [];
$query = "SELECT ai.*, COALESCE(ai.status, 'off') as status, c.name as category_name FROM admin_inspiration ai 
          LEFT JOIN categories c ON ai.category_id = c.id 
          ORDER BY ai.id DESC";
$result = $conn->query($query);
if ($result) {
  $inspirations = $result->fetch_all(MYSQLI_ASSOC);
}

// Count how many are ON
$count_query = "SELECT COUNT(*) as on_count FROM admin_inspiration WHERE status = 'on'";
$count_result = $conn->query($count_query);
$count_row = $count_result->fetch_assoc();
$on_count = $count_row['on_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Inspiration</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <?php include '../navbar/top.php'; ?>
  
  <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-4xl font-bold text-gray-900">Manage Inspiration</h1>
          <p class="text-gray-600 mt-2">View, update, or delete inspiration entries</p>
        </div>
        <a href="main-inspiration-add-page-2.php" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
          + Add New Inspiration
        </a>
      </div>

      <!-- Alert Messages -->
      <?php if (!empty($message)): ?>
        <div id="alertMessage" class="mb-6 p-4 rounded-lg border-l-4 <?php echo $message_type === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 'bg-red-50 border-red-500 text-red-800'; ?>">
          <div class="flex">
            <div class="flex-shrink-0">
              <?php echo $message_type === 'success' ? '✓' : '✕'; ?>
            </div>
            <div class="ml-3">
              <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Content -->
      <?php if (empty($inspirations)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
          <p class="text-gray-600 mb-4">No inspiration entries found.</p>
          <a href="main-inspiration-add-page-2.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Create First Entry
          </a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($inspirations as $inspiration): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
              <!-- Main Image -->
              <div class="h-48 bg-gray-200 overflow-hidden">
                <?php if (!empty($inspiration['main_image'])): ?>
                  <img src="../../uploads/<?php echo htmlspecialchars($inspiration['main_image']); ?>" 
                       alt="Main Image" class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center text-gray-400">
                    <span>No Image</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Content -->
              <div class="p-6">
                <div class="flex justify-between items-start mb-2">
                  <h3 class="text-lg font-bold text-gray-900">
                    <?php echo htmlspecialchars($inspiration['name']); ?>
                  </h3>
                  <!-- Status Badge -->
                  <span class="<?php echo ($inspiration['status'] ?? 'off') === 'on' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs font-semibold">
                    <?php echo strtoupper($inspiration['status'] ?? 'off'); ?>
                  </span>
                </div>
                
                <div class="space-y-2 text-sm text-gray-600 mb-4">
                  <p><span class="font-semibold">Type:</span> <?php echo htmlspecialchars($inspiration['type']); ?></p>
                  <p><span class="font-semibold">Category:</span> <?php echo htmlspecialchars($inspiration['category_name'] ?? 'N/A'); ?></p>
                  <p><span class="font-semibold">ID:</span> #<?php echo $inspiration['id']; ?></p>
                </div>

                <!-- Image Sets Info -->
                <div class="bg-gray-50 p-3 rounded mb-4 text-xs">
                  <?php 
                    $hasImage1 = !empty($inspiration['image_1']);
                    $hasImage2 = !empty($inspiration['image_2']);
                    $hasImage3 = !empty($inspiration['image_3']);
                  ?>
                  <p class="mb-1"><span class="font-semibold">Sets:</span></p>
                  <div class="flex gap-2">
                    <span class="<?php echo $hasImage1 ? 'bg-blue-200 text-blue-800' : 'bg-gray-200 text-gray-600'; ?> px-2 py-1 rounded">
                      Set 1 <?php echo $hasImage1 ? '✓' : '✕'; ?>
                    </span>
                    <span class="<?php echo $hasImage2 ? 'bg-green-200 text-green-800' : 'bg-gray-200 text-gray-600'; ?> px-2 py-1 rounded">
                      Set 2 <?php echo $hasImage2 ? '✓' : '✕'; ?>
                    </span>
                    <span class="<?php echo $hasImage3 ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'; ?> px-2 py-1 rounded">
                      Set 3 <?php echo $hasImage3 ? '✓' : '✕'; ?>
                    </span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2">
                  <a href="main-inspiration-edit-page-3.php?id=<?php echo $inspiration['id']; ?>" 
                     class="flex-1 px-4 py-2 bg-blue-600 text-white text-center rounded hover:bg-blue-700 transition-colors text-sm">
                    ✎ Update
                  </a>
                  
                  <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?php echo $inspiration['id']; ?>">
                    <button type="submit" 
                            class="w-full px-4 py-2 <?php echo ($inspiration['status'] ?? 'off') === 'on' ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-600 hover:bg-yellow-700'; ?> text-white rounded transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            <?php echo ($inspiration['status'] === 'off' && $on_count >= 2) ? 'disabled' : ''; ?>>
                      <?php echo ($inspiration['status'] ?? 'off') === 'on' ? '⊙ On' : '⊗ Off'; ?>
                    </button>
                  </form>
                  
                  <button onclick="deleteConfirm(<?php echo $inspiration['id']; ?>)" 
                          class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors text-sm">
                    🗑 Delete
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-sm mx-4">
      <h3 class="text-lg font-bold text-gray-900 mb-4">Delete Inspiration?</h3>
      <p class="text-gray-600 mb-6">This action cannot be undone. All associated images will be deleted.</p>
      
      <form method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
        
        <div class="flex gap-3 justify-end">
          <button type="button" onclick="closeDeleteModal()" 
                  class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
            Cancel
          </button>
          <button type="submit" 
                  class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
            Delete
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function deleteConfirm(id) {
      document.getElementById('deleteId').value = id;
      document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.add('hidden');
    }

    // Auto-hide success message after 3 seconds
    <?php if ($message_type === 'success'): ?>
      setTimeout(() => {
        const alertBox = document.getElementById('alertMessage');
        if (alertBox) {
          alertBox.style.transition = 'opacity 0.5s';
          alertBox.style.opacity = '0';
          setTimeout(() => alertBox.remove(), 500);
        }
      }, 3000);
    <?php endif; ?>
  </script>
</body>
</html>