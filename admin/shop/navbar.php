<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$message = "";
$error = "";

// Get message and error from session (for PRG pattern)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Function to create directory if it doesn't exist
function createDirectory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

// Function to delete directory and its contents
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// Reset auto-increment if needed
$tables = ['categories','product_subcategories'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    if ($max_id > 0) {
        $result2 = $conn->query("SELECT COUNT(*) AS count FROM $table WHERE id = $max_id");
        $row2 = $result2->fetch_assoc();
        if ((int)$row2['count'] === 0) {
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $max_id");
        }
    } else {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        
        if (!empty($category_name)) {
            $sql = "INSERT INTO categories (name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $category_name);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category '$category_name' added successfully!";
            } else {
                $_SESSION['error'] = "Error adding category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Category name cannot be empty!";
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['add_subcategory'])) {
        $category_id = $_POST['category_id'];
        $subcategory_name = trim($_POST['subcategory_name']);
        $subcategory_slug = trim($_POST['subcategory_slug']);
        
        if (!empty($subcategory_name) && !empty($subcategory_slug) && !empty($category_id)) {
            // Check if slug already exists
            $check_sql = "SELECT id FROM product_subcategories WHERE subcategory_slug = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $subcategory_slug);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = "Slug '$subcategory_slug' already exists! Please use a different slug.";
                $check_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_stmt->close();
            
            // Create directory for the slug
            $upload_dir = "../../uploads/" . $subcategory_slug . "/";
            if (!createDirectory($upload_dir)) {
                $_SESSION['error'] = "Error creating upload directory for slug '$subcategory_slug'";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            
            $image_path = null;
            
            // Handle image upload
            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['subcategory_image']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $subcategory_slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_path)) {
                        $image_path = $new_filename;
                    } else {
                        $_SESSION['error'] = "Error uploading image file.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                } else {
                    $_SESSION['error'] = "Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
            
            $sql = "INSERT INTO product_subcategories (category_id, subcategory_name, subcategory_slug, image_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $category_id, $subcategory_name, $subcategory_slug, $image_path);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Subcategory '$subcategory_name' added successfully!" . ($image_path ? " Image uploaded." : "");
            } else {
                $_SESSION['error'] = "Error adding subcategory: " . $conn->error;
                // Clean up directory if database insert failed
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required for subcategory!";
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['delete_category_id'];
        
        // Get all subcategories for this category to delete their directories
        $get_subs_sql = "SELECT subcategory_slug FROM product_subcategories WHERE category_id = ?";
        $get_subs_stmt = $conn->prepare($get_subs_sql);
        $get_subs_stmt->bind_param("i", $category_id);
        $get_subs_stmt->execute();
        $subs_result = $get_subs_stmt->get_result();
        
        $slugs_to_delete = [];
        while ($sub_row = $subs_result->fetch_assoc()) {
            $slugs_to_delete[] = $sub_row['subcategory_slug'];
        }
        $get_subs_stmt->close();
        
        // Since we have CASCADE DELETE, deleting category will automatically delete subcategories
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        
        if ($stmt->execute()) {
            // Delete all slug directories
            foreach ($slugs_to_delete as $slug) {
                $dir_to_delete = "../../uploads/" . $slug . "/";
                deleteDirectory($dir_to_delete);
            }
            $_SESSION['message'] = "Category and all its subcategories deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting category: " . $conn->error;
        }
        $stmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_subcategory'])) {
        $subcategory_id = $_POST['delete_subcategory_id'];
        
        // Get the slug before deleting to remove the directory
        $get_slug_sql = "SELECT subcategory_slug FROM product_subcategories WHERE id = ?";
        $get_slug_stmt = $conn->prepare($get_slug_sql);
        $get_slug_stmt->bind_param("i", $subcategory_id);
        $get_slug_stmt->execute();
        $slug_result = $get_slug_stmt->get_result();
        $slug_row = $slug_result->fetch_assoc();
        $slug_to_delete = $slug_row ? $slug_row['subcategory_slug'] : null;
        $get_slug_stmt->close();
        
        $sql = "DELETE FROM product_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $subcategory_id);
        
        if ($stmt->execute()) {
            // Delete the slug directory
            if ($slug_to_delete) {
                $dir_to_delete = "../../uploads/" . $slug_to_delete . "/";
                deleteDirectory($dir_to_delete);
            }
            $_SESSION['message'] = "Subcategory deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting subcategory: " . $conn->error;
        }
        $stmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_subcategory_image'])) {
        $subcategory_id = $_POST['subcategory_id'];
        
        // Get current subcategory details
        $get_sub_sql = "SELECT subcategory_slug, image_path FROM product_subcategories WHERE id = ?";
        $get_sub_stmt = $conn->prepare($get_sub_sql);
        $get_sub_stmt->bind_param("i", $subcategory_id);
        $get_sub_stmt->execute();
        $sub_result = $get_sub_stmt->get_result();
        $sub_data = $sub_result->fetch_assoc();
        $get_sub_stmt->close();
        
        if ($sub_data) {
            $slug = $sub_data['subcategory_slug'];
            $current_image = $sub_data['image_path'];
            $upload_dir = "../../uploads/" . $slug . "/";
            
            // Handle image upload
            if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['new_image']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    // Ensure directory exists and is writable
                    if (!createDirectory($upload_dir)) {
                        $_SESSION['error'] = "Error creating upload directory. Check permissions for: " . $upload_dir;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                    // Check if directory is writable
                    if (!is_writable($upload_dir)) {
                        $_SESSION['error'] = "Upload directory is not writable: " . $upload_dir . ". Please check folder permissions.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                    // Delete old image if exists
                    if ($current_image && file_exists($upload_dir . $current_image)) {
                        if (!unlink($upload_dir . $current_image)) {
                            $_SESSION['error'] = "Warning: Could not delete old image file.";
                        }
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
                    $new_filename = $slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Additional file validation
                    $max_file_size = 5 * 1024 * 1024; // 5MB
                    if ($_FILES['new_image']['size'] > $max_file_size) {
                        $_SESSION['error'] = "File too large. Maximum size is 5MB.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                    // Check if temp file exists and is readable
                    if (!file_exists($_FILES['new_image']['tmp_name']) || !is_readable($_FILES['new_image']['tmp_name'])) {
                        $_SESSION['error'] = "Error reading uploaded file. Please try again.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                    if (move_uploaded_file($_FILES['new_image']['tmp_name'], $upload_path)) {
                        // Set proper permissions on uploaded file
                        chmod($upload_path, 0644);
                        
                        // Update database
                        $update_sql = "UPDATE product_subcategories SET image_path = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("si", $new_filename, $subcategory_id);
                        
                        if ($update_stmt->execute()) {
                            $_SESSION['message'] = "Image updated successfully!";
                        } else {
                            $_SESSION['error'] = "Error updating image in database: " . $conn->error;
                        }
                        $update_stmt->close();
                    } else {
                        // More detailed error message
                        $error_details = "";
                        $upload_errors = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
                        ];
                        
                        if (isset($upload_errors[$_FILES['new_image']['error']])) {
                            $error_details = $upload_errors[$_FILES['new_image']['error']];
                        }
                        
                        $_SESSION['error'] = "Error uploading image file. " . $error_details . " Upload path: " . $upload_path;
                    }
                } else {
                    $_SESSION['error'] = "Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed. Detected type: " . $file_type;
                }
            } else {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive', 
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
                ];
                
                $error_msg = "Upload error";
                if (isset($_FILES['new_image']['error']) && isset($upload_errors[$_FILES['new_image']['error']])) {
                    $error_msg = $upload_errors[$_FILES['new_image']['error']];
                }
                $_SESSION['error'] = "No image file selected or upload error: " . $error_msg;
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all categories for dropdown and display
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get all categories with subcategories for display
$display_sql = "SELECT 
    c.id as category_id,
    c.name as category_name,
    ps.id as subcategory_id,
    ps.subcategory_name,
    ps.subcategory_slug,
    ps.image_path
FROM categories c
LEFT JOIN product_subcategories ps ON c.id = ps.category_id
ORDER BY c.name, ps.subcategory_name";

$display_result = $conn->query($display_sql);
$display_categories = [];
while ($row = $display_result->fetch_assoc()) {
    $category_name = $row['category_name'];
    if (!isset($display_categories[$category_name])) {
        $display_categories[$category_name] = [
            'id' => $row['category_id'],
            'name' => $category_name,
            'subcategories' => []
        ];
    }
    if ($row['subcategory_id']) {
        $display_categories[$category_name]['subcategories'][] = [
            'id' => $row['subcategory_id'],
            'name' => $row['subcategory_name'],
            'slug' => $row['subcategory_slug'],
            'image_path' => $row['image_path']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#e29a15ff',
                        'primary-dark': '#005a87',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">

<?php include '../navbar/top.php'; ?>
    <div class="max-w-6xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Category Management System</h1>
            
            <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Grid Layout for Forms -->
            <div class="grid md:grid-cols-2 gap-6 mb-8">
                <!-- Add Category Form -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Category</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="category_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Category Name
                            </label>
                            <input 
                                type="text" 
                                id="category_name" 
                                name="category_name" 
                                placeholder="e.g., Electronics, Furniture" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                required
                            >
                        </div>
                        <button 
                            type="submit" 
                            name="add_category"
                            class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                        >
                            Add Category
                        </button>
                    </form>
                </div>

                <!-- Add Subcategory Form -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Subcategory</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Category
                            </label>
                            <select 
                                id="category_id" 
                                name="category_id" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                required
                            >
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="subcategory_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Name
                            </label>
                            <input 
                                type="text" 
                                id="subcategory_name" 
                                name="subcategory_name" 
                                placeholder="e.g., Smartphones, Sofas" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                required
                            >
                        </div>
                        <div>
                            <label for="subcategory_slug" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Slug (URL-friendly)
                            </label>
                            <input 
                                type="text" 
                                id="subcategory_slug" 
                                name="subcategory_slug" 
                                placeholder="e.g., smartphones, sofas" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">Use lowercase letters, numbers, and hyphens only</p>
                        </div>
                        <div>
                            <label for="subcategory_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Image (Optional)
                            </label>
                            <input 
                                type="file" 
                                id="subcategory_image" 
                                name="subcategory_image" 
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            >
                            <p class="text-xs text-gray-500 mt-1">Supported formats: JPEG, PNG, GIF, WebP</p>
                        </div>
                        <button 
                            type="submit" 
                            name="add_subcategory"
                            class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                        >
                            Add Subcategory
                        </button>
                    </form>
                </div>
            </div>

            <!-- Display Categories and Subcategories -->
            <div class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Current Categories & Subcategories</h2>
                
                <?php if (empty($display_categories)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-lg">No categories found. Add some categories first!</div>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($display_categories as $category): ?>
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                                <div class="bg-primary text-white px-6 py-4 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold"><?= htmlspecialchars($category['name']) ?></h3>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure? This will delete all subcategories and their images too!')">
                                        <input type="hidden" name="delete_category_id" value="<?= $category['id'] ?>">
                                        <button 
                                            type="submit" 
                                            name="delete_category" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition duration-200"
                                        >
                                            Delete Category
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="p-6">
                                    <?php if (!empty($category['subcategories'])): ?>
                                        <div class="space-y-3">
                                            <?php foreach ($category['subcategories'] as $sub): ?>
                                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                    <div class="flex items-center space-x-4 flex-1">
                                                        <!-- Image thumbnail -->
                                                        <div class="w-16 h-16 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                                                            <?php if ($sub['image_path']): ?>
                                                                <img 
                                                                    src="../../uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>" 
                                                                    alt="<?= htmlspecialchars($sub['name']) ?>"
                                                                    class="w-full h-full object-cover"
                                                                    onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMS4zMzMzIDIxLjMzMzNIMjRWMjRIMjEuMzMzM1YyMS4zMzMzWiIgZmlsbD0iIzlDQTNBRiIvPgo8cGF0aCBkPSJNMTggNDBINDZWMzYuOTc3M0w0MS4zNDkzIDMxLjY1MDdMMzIuNjg0IDQxLjM0OTBMMJAGZUIY0IDI5LjI4TDE4IDQwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K'"
                                                                >
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Subcategory info -->
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($sub['name']) ?></div>
                                                            <div class="text-sm text-gray-500">Slug: <?= htmlspecialchars($sub['slug']) ?></div>
                                                            <div class="text-xs text-gray-400">
                                                                Directory: ../../uploads/<?= htmlspecialchars($sub['slug']) ?>/
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Action buttons -->
                                                    <div class="flex items-center space-x-2 flex-shrink-0">
                                                        <!-- Update image button -->
                                                        <button 
                                                            onclick="toggleImageUpload(<?= $sub['id'] ?>)" 
                                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium transition duration-200"
                                                        >
                                                            <?= $sub['image_path'] ? 'Update Image' : 'Add Image' ?>
                                                        </button>
                                                        
                                                        <!-- Delete subcategory button -->
                                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure? This will delete the subcategory and all its images!')">
                                                            <input type="hidden" name="delete_subcategory_id" value="<?= $sub['id'] ?>">
                                                            <button 
                                                                type="submit" 
                                                                name="delete_subcategory" 
                                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition duration-200"
                                                            >
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                
                                                <!-- Hidden image upload form -->
                                                <div id="image-upload-<?= $sub['id'] ?>" class="hidden mt-4 p-4 bg-blue-50 rounded-lg">
                                                    <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                                        <input type="hidden" name="subcategory_id" value="<?= $sub['id'] ?>">
                                                        <div class="flex-1">
                                                            <input 
                                                                type="file" 
                                                                name="new_image" 
                                                                accept="image/jpeg,image/png,image/gif,image/webp"
                                                                class="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                                                                required
                                                            >
                                                        </div>
                                                        <button 
                                                            type="submit" 
                                                            name="update_subcategory_image"
                                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium transition duration-200"
                                                        >
                                                            Upload
                                                        </button>
                                                        <button 
                                                            type="button" 
                                                            onclick="toggleImageUpload(<?= $sub['id'] ?>)"
                                                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm font-medium transition duration-200"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8">
                                            <div class="text-gray-400 italic">No subcategories yet</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-generate slug from subcategory name
        document.getElementById('subcategory_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('subcategory_slug').value = slug;
        });

        // Toggle image upload form
        function toggleImageUpload(subcategoryId) {
            const uploadForm = document.getElementById('image-upload-' + subcategoryId);
            if (uploadForm.classList.contains('hidden')) {
                uploadForm.classList.remove('hidden');
            } else {
                uploadForm.classList.add('hidden');
            }
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>