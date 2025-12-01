<?php
//main-inspiration-add-page-2.php
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
$tables = ['admin_inspiration'];

foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}
$message = '';
$message_type = '';
$upload_dir = '../../uploads/';

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
  mkdir($upload_dir, 0755, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $type = trim($_POST['type'] ?? '');
  $category_id = intval($_POST['category_id'] ?? 0);
  
  // Validate required fields
  if (empty($name) || empty($type) || empty($category_id)) {
    $message = 'Name, type, and category are required.';
    $message_type = 'error';
  } else {
    try {
      // Function to handle multiple image uploads
      function handleMultipleUploads($files_array, $upload_dir) {
        $images = [];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_file_size = 15 * 1024 * 1024; // 15MB
        if (!empty($files_array['name'][0])) {
          $file_count = count($files_array['name']);
          for ($i = 0; $i < $file_count; $i++) {
            if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
              $file_type = mime_content_type($files_array['tmp_name'][$i]);
              if (!in_array($file_type, $allowed_types)) {
                continue;
              }
              if ($files_array['size'][$i] > $max_file_size) {
                continue;
              }
              $file_name = $files_array['name'][$i];
              $file_tmp = $files_array['tmp_name'][$i];
              $file_size = $files_array['size'][$i];
              
              $unique_name = time() . '_' . uniqid() . '_' . basename($file_name);
              $file_path = $upload_dir . $unique_name;
              
              if (move_uploaded_file($file_tmp, $file_path)) {
                $images[] = [
                  'filename' => $unique_name,
                  'original_name' => $file_name,
                  'path' => $file_path,
                  'size' => $file_size
                ];
              }
            }
          }
        }
        return !empty($images) ? json_encode($images) : null;
      }
      
      // Upload main image
      $main_image = null;
      $allowed_types = ['image/jpeg', 'image/png'];
      $max_file_size = 15 * 1024 * 1024; // 15MB
      if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $file_type = mime_content_type($_FILES['main_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
          $message = 'Main image must be PNG or JPEG only.';
          $message_type = 'error';
        } elseif ($_FILES['main_image']['size'] > $max_file_size) {
          $message = 'Main image must be 15MB or less.';
          $message_type = 'error';
        } else {
          $file_name = $_FILES['main_image']['name'];
          $file_tmp = $_FILES['main_image']['tmp_name'];
          $file_size = $_FILES['main_image']['size'];
          
          $unique_name = time() . '_' . uniqid() . '_' . basename($file_name);
          $file_path = $upload_dir . $unique_name;
          
          if (move_uploaded_file($file_tmp, $file_path)) {
            $main_image = $unique_name;
          }
        }
      }
      
      // Stop if there was an error with main image
      if ($message_type === 'error') {
        throw new Exception($message);
      }
      
      // Upload images for each section
      $image_1 = handleMultipleUploads($_FILES['image_1'] ?? [], $upload_dir);
      $image_2 = handleMultipleUploads($_FILES['image_2'] ?? [], $upload_dir);
      $image_3 = handleMultipleUploads($_FILES['image_3'] ?? [], $upload_dir);
      
      // Get descriptions
      $description_image_1 = trim($_POST['description_image_1'] ?? '');
      $description_image_2 = trim($_POST['description_image_2'] ?? '');
      $description_image_3 = trim($_POST['description_image_3'] ?? '');
      
      // Insert into database
      $query = "INSERT INTO admin_inspiration 
                (name, type, category_id, main_image, image_1, description_image_1, image_2, description_image_2, image_3, description_image_3) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      
      $stmt = $conn->prepare($query);
      $stmt->bind_param(
        "ssisssssss",
        $name,
        $type,
        $category_id,
        $main_image,
        $image_1,
        $description_image_1,
        $image_2,
        $description_image_2,
        $image_3,
        $description_image_3
      );
      
      if ($stmt->execute()) {
        $message = 'Inspiration added successfully!';
        $message_type = 'success';
        
        // IMPORTANT: Redirect after successful submission to prevent resubmission
        $_SESSION['success_message'] = $message;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
      } else {
        $message = 'Error adding inspiration: ' . $stmt->error;
        $message_type = 'error';
      }
      $stmt->close();
      
    } catch (Exception $e) {
      $message = 'Error: ' . $e->getMessage();
      $message_type = 'error';
    }
  }
}

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
  $message = $_SESSION['success_message'];
  $message_type = 'success';
  unset($_SESSION['success_message']);
}

// Fetch categories for dropdown
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
  $categories = $cat_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Inspiration</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <?php include '../navbar/top.php'; ?>
  
  <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900">Add New Inspiration</h1>
        <p class="text-gray-600 mt-2">Create a new inspiration entry with images and descriptions</p>
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

      <!-- Form Container -->
      <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md overflow-hidden" id="inspirationForm">
        
        <!-- Basic Info Section -->
        <div class="p-8 border-b border-gray-200 bg-white">
          <h2 class="text-2xl font-bold text-gray-900 mb-6">Basic Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Name <span class="text-red-600">*</span></label>
              <input type="text" name="name" required 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Inspiration name">
            </div>
            
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Type <span class="text-red-600">*</span></label>
              <input type="text" name="type" required 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type">
            </div>
            
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span class="text-red-600">*</span></label>
              <select name="category_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Main Image Section -->
        <div class="p-8 border-b border-gray-200 bg-blue-50">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Main Image</h2>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Main Image</label>
              <input type="file" name="main_image" id="main_image" accept="image/png, image/jpeg" required 
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
              <div id="preview_main" class="mt-2"></div>
            </div>
          </div>
        </div>

        <!-- Image Sets Table -->
        <div class="p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-6">Image Sets</h2>
          
          <div class="space-y-6">
            <!-- Image Set 1 -->
            <div class="border border-gray-200 rounded-lg p-6 bg-blue-50">
              <div class="grid grid-cols-3 gap-6">
                <div>
                  <h3 class="text-lg font-bold text-gray-900">Image Set 1</h3>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Images</label>
                  <div id="image_1_container">
                    <input type="file" name="image_1[]" id="image_1" accept="image/png, image/jpeg" multiple 
                      class="block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-500 file:text-white hover:file:bg-blue-600">
                    <div id="preview_1" class="mt-2 flex flex-wrap gap-2"></div>
                  </div>
                  <button type="button" onclick="addImageRow(1)" class="mt-3 px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">+ Add More</button>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                  <textarea name="description_image_1" 
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."></textarea>
                </div>
              </div>
            </div>

            <!-- Image Set 2 -->
            <div class="border border-gray-200 rounded-lg p-6 bg-green-50">
              <div class="grid grid-cols-3 gap-6">
                <div>
                  <h3 class="text-lg font-bold text-gray-900">Image Set 2</h3>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Images</label>
                  <div id="image_2_container">
                    <input type="file" name="image_2[]" id="image_2" accept="image/png, image/jpeg" multiple 
                      class="block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-green-500 file:text-white hover:file:bg-green-600">
                    <div id="preview_2" class="mt-2 flex flex-wrap gap-2"></div>
                  </div>
                  <button type="button" onclick="addImageRow(2)" class="mt-3 px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600">+ Add More</button>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                  <textarea name="description_image_2" 
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."></textarea>
                </div>
              </div>
            </div>

            <!-- Image Set 3 -->
            <div class="border border-gray-200 rounded-lg p-6 bg-purple-50">
              <div class="grid grid-cols-3 gap-6">
                <div>
                  <h3 class="text-lg font-bold text-gray-900">Image Set 3</h3>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Images</label>
                  <div id="image_3_container">
                    <input type="file" name="image_3[]" id="image_3" accept="image/png, image/jpeg" multiple 
                      class="block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-purple-500 file:text-white hover:file:bg-purple-600">
                    <div id="preview_3" class="mt-2 flex flex-wrap gap-2"></div>
                  </div>
                  <button type="button" onclick="addImageRow(3)" class="mt-3 px-4 py-2 text-sm bg-purple-500 text-white rounded hover:bg-purple-600">+ Add More</button>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                  <textarea name="description_image_3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="p-8 bg-gray-50 border-t border-gray-200 flex gap-4 justify-center">
          <button type="submit" class="px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
            + Add Inspiration
          </button>
          <button type="reset" class="px-8 py-3 bg-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-400">
            Clear Form
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let imageCount = { 1: 1, 2: 1, 3: 1 };

    // Add new image input row
    function addImageRow(setNum) {
      imageCount[setNum]++;
      const container = document.getElementById(`image_${setNum}_container`);
      const colors = ['blue', 'green', 'purple'];
      const color = colors[setNum - 1];
      
      const newInput = document.createElement('div');
      newInput.className = 'mt-3 flex gap-2';
      newInput.innerHTML = `
        <input type="file" name="image_${setNum}[]" accept="image/png, image/jpeg" multiple 
          class="flex-1 text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-${color}-500 file:text-white hover:file:bg-${color}-600">
        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600">Remove</button>
      `;
      container.appendChild(newInput);
    }

    // Handle image previews for each set
    function setupImagePreview(inputId, previewId) {
      const input = document.getElementById(inputId);
      const preview = document.getElementById(previewId);

      input.addEventListener('change', function() {
        preview.innerHTML = '';
        const files = this.files;

        if (files.length === 0) return;

        Array.from(files).forEach((file) => {
          const reader = new FileReader();
          
          reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'h-16 w-16 object-cover rounded border border-gray-300';
            img.title = file.name;
            preview.appendChild(img);
          };
          
          reader.readAsDataURL(file);
        });
      });
    }

    // Initialize all image sets
    setupImagePreview('main_image', 'preview_main');
    setupImagePreview('image_1', 'preview_1');
    setupImagePreview('image_2', 'preview_2');
    setupImagePreview('image_3', 'preview_3');

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