<?php
//edit-inspiration.php
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
$upload_dir = '../../uploads/';
$show_message = false;

// Clear any old session messages on page load (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  unset($_SESSION['flash_message']);
  unset($_SESSION['flash_message_type']);
}

// Check if there's a flash message from previous POST
if (isset($_SESSION['flash_message']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $message = $_SESSION['flash_message'];
  $message_type = $_SESSION['flash_message_type'];
  $show_message = true;
  unset($_SESSION['flash_message']);
  unset($_SESSION['flash_message_type']);
}

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
  mkdir($upload_dir, 0755, true);
}

// Get inspiration ID from URL
$inspiration_id = intval($_GET['id'] ?? 0);

if ($inspiration_id === 0) {
  header("Location: view-inspiration.php");
  exit();
}

// Fetch existing inspiration data
$inspiration = null;
$fetch_query = "SELECT * FROM admin_inspiration WHERE id = ?";
$stmt = $conn->prepare($fetch_query);
$stmt->bind_param("i", $inspiration_id);
$stmt->execute();
$result = $stmt->get_result();
$inspiration = $result->fetch_assoc();
$stmt->close();

if (!$inspiration) {
  header("Location: view-inspiration.php");
  exit();
}

// Handle form submission - ONLY ON POST REQUEST
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
      
      // Handle main image upload (if new image provided)
      $main_image = $inspiration['main_image'];
      $allowed_types = ['image/jpeg', 'image/png'];
      $max_file_size = 15 * 1024 * 1024;
      
      if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $file_type = mime_content_type($_FILES['main_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
          $message = 'Main image must be PNG or JPEG only.';
          $message_type = 'error';
        } elseif ($_FILES['main_image']['size'] > $max_file_size) {
          $message = 'Main image must be 15MB or less.';
          $message_type = 'error';
        } else {
          if (!empty($inspiration['main_image'])) {
            $old_file = $upload_dir . $inspiration['main_image'];
            if (file_exists($old_file)) {
              unlink($old_file);
            }
          }
          
          $file_name = $_FILES['main_image']['name'];
          $file_tmp = $_FILES['main_image']['tmp_name'];
          
          $unique_name = time() . '_' . uniqid() . '_' . basename($file_name);
          $file_path = $upload_dir . $unique_name;
          
          if (move_uploaded_file($file_tmp, $file_path)) {
            $main_image = $unique_name;
          }
        }
      }
      
      if ($message_type === 'error') {
        throw new Exception($message);
      }
      
      // Initialize image variables with existing data
      $image_1 = $inspiration['image_1'];
      $image_2 = $inspiration['image_2'];
      $image_3 = $inspiration['image_3'];
      
      // Handle individual image deletions for each set
      for ($set_num = 1; $set_num <= 3; $set_num++) {
        $delete_filenames = $_POST["delete_image_{$set_num}"] ?? [];
        
        // Only process if there are actual deletions
        if (!empty($delete_filenames) && is_array($delete_filenames)) {
          $var_name = "image_{$set_num}";
          $current_images = !empty($inspiration[$var_name]) ? json_decode($inspiration[$var_name], true) : [];
          
          $remaining_images = [];
          if (is_array($current_images)) {
            foreach ($current_images as $img) {
              if (!in_array($img['filename'], $delete_filenames)) {
                $remaining_images[] = $img;
              } else {
                $file_path = $upload_dir . $img['filename'];
                if (file_exists($file_path)) {
                  @unlink($file_path);
                }
              }
            }
          }
          
          if (!empty($remaining_images)) {
            ${$var_name} = json_encode($remaining_images);
          } else {
            ${$var_name} = null;
          }
        }
      }
      
      // Handle new image uploads
      if (!empty($_FILES['image_1']['name'][0])) {
        $new_images = handleMultipleUploads($_FILES['image_1'] ?? [], $upload_dir);
        if ($new_images) {
          $existing = !empty($image_1) ? json_decode($image_1, true) : [];
          $new = json_decode($new_images, true);
          $image_1 = json_encode(array_merge($existing, $new));
        }
      }
      
      if (!empty($_FILES['image_2']['name'][0])) {
        $new_images = handleMultipleUploads($_FILES['image_2'] ?? [], $upload_dir);
        if ($new_images) {
          $existing = !empty($image_2) ? json_decode($image_2, true) : [];
          $new = json_decode($new_images, true);
          $image_2 = json_encode(array_merge($existing, $new));
        }
      }
      
      if (!empty($_FILES['image_3']['name'][0])) {
        $new_images = handleMultipleUploads($_FILES['image_3'] ?? [], $upload_dir);
        if ($new_images) {
          $existing = !empty($image_3) ? json_decode($image_3, true) : [];
          $new = json_decode($new_images, true);
          $image_3 = json_encode(array_merge($existing, $new));
        }
      }
      
      // Get descriptions
      $description_image_1 = trim($_POST['description_image_1'] ?? '');
      $description_image_2 = trim($_POST['description_image_2'] ?? '');
      $description_image_3 = trim($_POST['description_image_3'] ?? '');
      
      // Update database
      $query = "UPDATE admin_inspiration 
                SET name = ?, type = ?, category_id = ?, main_image = ?, 
                    image_1 = ?, description_image_1 = ?, 
                    image_2 = ?, description_image_2 = ?, 
                    image_3 = ?, description_image_3 = ?
                WHERE id = ?";
      
      $stmt = $conn->prepare($query);
      $stmt->bind_param(
        "ssisssssssi",
        $name,
        $type,
        $category_id,
        $main_image,
        $image_1,
        $description_image_1,
        $image_2,
        $description_image_2,
        $image_3,
        $description_image_3,
        $inspiration_id
      );
      
      if ($stmt->execute()) {
        $_SESSION['flash_message'] = 'Inspiration updated successfully!';
        $_SESSION['flash_message_type'] = 'success';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
        
        // Refresh inspiration data
        $fetch_query = "SELECT * FROM admin_inspiration WHERE id = ?";
        $stmt2 = $conn->prepare($fetch_query);
        $stmt2->bind_param("i", $inspiration_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $inspiration = $result->fetch_assoc();
        $stmt2->close();
      } else {
        $message = 'Error updating inspiration: ' . $stmt->error;
        $message_type = 'error';
        $show_message = true;
      }
      
    } catch (Exception $e) {
      $message = 'Error: ' . $e->getMessage();
      $message_type = 'error';
      $show_message = true;
    }
  }
}

// Fetch categories for dropdown
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
  $categories = $cat_result->fetch_all(MYSQLI_ASSOC);
}

// Helper function to decode images
function getImageArray($json_string) {
  if (empty($json_string)) return [];
  $decoded = json_decode($json_string, true);
  return is_array($decoded) ? $decoded : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Inspiration</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <?php include '../navbar/top.php'; ?>
  
  <!-- Alert Messages - Fixed at Top (Only show if from POST request) -->
  <?php if ($show_message && !empty($message)): ?>
    <div id="alertMessage" class="fixed top-0 left-0 right-0 p-4 rounded-lg border-l-4 <?php echo $message_type === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 'bg-red-50 border-red-500 text-red-800'; ?> z-50 shadow-lg">
      <div class="flex max-w-7xl mx-auto">
        <div class="flex-shrink-0">
          <?php echo $message_type === 'success' ? '✓' : '✕'; ?>
        </div>
        <div class="ml-3">
          <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8 flex items-center gap-4">
        <a href="main-inspiration-page-1.php" class="px-4 py-2 text-blue-600 hover:text-blue-800">← Back</a>
        <div>
          <h1 class="text-4xl font-bold text-gray-900">Edit Inspiration</h1>
          <p class="text-gray-600 mt-2">ID: #<?php echo $inspiration['id']; ?></p>
        </div>
      </div>

      <!-- Form Container -->
      <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md overflow-hidden" id="inspirationForm">
        
        <!-- Basic Info Section -->
        <div class="p-8 border-b border-gray-200 bg-white">
          <h2 class="text-2xl font-bold text-gray-900 mb-6">Basic Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Name <span class="text-red-600">*</span></label>
              <input type="text" name="name" required value="<?php echo htmlspecialchars($inspiration['name']); ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Inspiration name">
            </div>
            
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Type <span class="text-red-600">*</span></label>
              <input type="text" name="type" required value="<?php echo htmlspecialchars($inspiration['type']); ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type">
            </div>
            
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span class="text-red-600">*</span></label>
              <select name="category_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $inspiration['category_id']) ? 'selected' : ''; ?>>
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
          
          <!-- Current Image -->
          <?php if (!empty($inspiration['main_image'])): ?>
            <div class="mb-4">
              <p class="text-sm font-semibold text-gray-700 mb-2">Current Image:</p>
              <img src="../../uploads/<?php echo htmlspecialchars($inspiration['main_image']); ?>" 
                   alt="Main Image" class="h-32 w-32 object-cover rounded border border-gray-300">
            </div>
          <?php endif; ?>
          
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Upload New Main Image (Optional)</label>
              <input type="file" name="main_image" id="main_image" accept="image/png, image/jpeg" 
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
                  
                  <!-- Current Images -->
                  <?php if (!empty($inspiration['image_1'])): ?>
                    <div class="mb-3 flex flex-wrap gap-2" id="current_images_1">
                      <?php foreach (getImageArray($inspiration['image_1']) as $idx => $img): ?>
                        <div class="relative group">
                          <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>" 
                               alt="Image" class="h-16 w-16 object-cover rounded border border-gray-300" 
                               title="<?php echo htmlspecialchars($img['original_name']); ?>">
                          <button type="button" onclick="deleteIndividualImage('current_images_1', <?php echo $idx; ?>, 1, '<?php echo htmlspecialchars($img['filename']); ?>')" class="absolute top-0 right-0 hidden group-hover:block bg-red-600 text-white text-xs px-1 rounded-bl opacity-80 hover:opacity-100">✕</button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  
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
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."><?php echo htmlspecialchars($inspiration['description_image_1'] ?? ''); ?></textarea>
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
                  
                  <?php if (!empty($inspiration['image_2'])): ?>
                    <div class="mb-3 flex flex-wrap gap-2" id="current_images_2">
                      <?php foreach (getImageArray($inspiration['image_2']) as $idx => $img): ?>
                        <div class="relative group">
                          <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>" 
                               alt="Image" class="h-16 w-16 object-cover rounded border border-gray-300" 
                               title="<?php echo htmlspecialchars($img['original_name']); ?>">
                          <button type="button" onclick="deleteIndividualImage('current_images_2', <?php echo $idx; ?>, 2, '<?php echo htmlspecialchars($img['filename']); ?>')" class="absolute top-0 right-0 hidden group-hover:block bg-red-600 text-white text-xs px-1 rounded-bl opacity-80 hover:opacity-100">✕</button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  
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
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."><?php echo htmlspecialchars($inspiration['description_image_2'] ?? ''); ?></textarea>
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
                  
                  <?php if (!empty($inspiration['image_3'])): ?>
                    <div class="mb-3 flex flex-wrap gap-2" id="current_images_3">
                      <?php foreach (getImageArray($inspiration['image_3']) as $idx => $img): ?>
                        <div class="relative group">
                          <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>" 
                               alt="Image" class="h-16 w-16 object-cover rounded border border-gray-300" 
                               title="<?php echo htmlspecialchars($img['original_name']); ?>">
                          <button type="button" onclick="deleteIndividualImage('current_images_3', <?php echo $idx; ?>, 3, '<?php echo htmlspecialchars($img['filename']); ?>')" class="absolute top-0 right-0 hidden group-hover:block bg-red-600 text-white text-xs px-1 rounded-bl opacity-80 hover:opacity-100">✕</button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  
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
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm" rows="4" placeholder="Description for this image set..."><?php echo htmlspecialchars($inspiration['description_image_3'] ?? ''); ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="p-8 bg-gray-50 border-t border-gray-200 flex gap-4 justify-center">
          <button type="submit" class="px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
            ✓ Save Changes
          </button>
          <a href="main-inspiration-page-1.php" class="px-8 py-3 bg-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-400">
            ✕ Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

  <script>
    let imageCount = { 1: 1, 2: 1, 3: 1 };
    let imagesToDelete = { 1: [], 2: [], 3: [] };

    function deleteIndividualImage(containerId, index, setNum, filename) {
      // Add to deletion list
      if (!imagesToDelete[setNum].includes(filename)) {
        imagesToDelete[setNum].push(filename);
      }
      
      // Remove the image element from DOM
      const container = document.getElementById(containerId);
      const imageElements = container.querySelectorAll('.relative');
      if (imageElements[index]) {
        imageElements[index].remove();
      }
    }

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

    function setupImagePreview(inputId, previewId) {
      const input = document.getElementById(inputId);
      if (!input) return;
      
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
      }, false);
    }

    setupImagePreview('main_image', 'preview_main');
    setupImagePreview('image_1', 'preview_1');
    setupImagePreview('image_2', 'preview_2');
    setupImagePreview('image_3', 'preview_3');

    // Handle form submission - ADD HIDDEN INPUTS ONLY WHEN SUBMITTING
    document.getElementById('inspirationForm').addEventListener('submit', function(e) {
      // Remove any existing delete_image hidden inputs
      const existingHiddenInputs = this.querySelectorAll('input[type="hidden"][name^="delete_image_"]');
      existingHiddenInputs.forEach(input => input.remove());
      
      // Add fresh hidden inputs from imagesToDelete object ONLY WHEN SUBMITTING
      for (let setNum = 1; setNum <= 3; setNum++) {
        if (imagesToDelete[setNum].length > 0) {
          imagesToDelete[setNum].forEach(filename => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `delete_image_${setNum}[]`;
            hiddenInput.value = filename;
            this.appendChild(hiddenInput);
          });
        }
      }
    });

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