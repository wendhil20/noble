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

$message = '';
$message_type = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = intval($_GET['id'] ?? 0);

// ============ ADD ACTION ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    // ADD NEW PROMOTION
    if ($action_type === 'add') {
        $title = trim($_POST['title'] ?? '');
        $discount = trim($_POST['discount'] ?? '');
        $image = $_FILES['image'] ?? null;

        if (empty($title) || empty($discount) || !$image || $image['error'] !== UPLOAD_ERR_OK) {
            $message = 'All fields are required and image must be uploaded.';
            $message_type = 'error';
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($image['type'], $allowed_types)) {
                $message = 'Invalid image format. Only JPG, PNG, GIF, and WebP are allowed.';
                $message_type = 'error';
            } else if ($image['size'] > 5242880) {
                $message = 'Image size must not exceed 5MB.';
                $message_type = 'error';
            } else {
                $upload_dir = '../../uploads/promotion_banners/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                $image_filename = 'banner_' . time() . '_' . uniqid() . '.' . $file_extension;
                $image_path = $upload_dir . $image_filename;

                if (move_uploaded_file($image['tmp_name'], $image_path)) {
                    $query = "INSERT INTO promotion_discount (title, discount, image) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    
                    if ($stmt) {
                        $stmt->bind_param("sds", $title, $discount, $image_filename);
                        
                        if ($stmt->execute()) {
                            $message = 'Promotion banner added successfully!';
                            $message_type = 'success';
                            $action = 'list';
                        } else {
                            $message = 'Error inserting banner: ' . $stmt->error;
                            $message_type = 'error';
                            unlink($image_path);
                        }
                        $stmt->close();
                    } else {
                        $message = 'Database error: ' . $conn->error;
                        $message_type = 'error';
                        unlink($image_path);
                    }
                } else {
                    $message = 'Error uploading image.';
                    $message_type = 'error';
                }
            }
        }
    }

    // UPDATE PROMOTION
    else if ($action_type === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $discount = trim($_POST['discount'] ?? '');
        $image = $_FILES['image'] ?? null;

        if (empty($title) || empty($discount) || $id <= 0) {
            $message = 'Title and discount are required.';
            $message_type = 'error';
        } else {
            // Get current image
            $query = "SELECT image FROM promotion_discount WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $new_image_filename = $row['image'] ?? '';

            // Handle new image upload
            if ($image && $image['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($image['type'], $allowed_types)) {
                    $message = 'Invalid image format. Only JPG, PNG, GIF, and WebP are allowed.';
                    $message_type = 'error';
                } else if ($image['size'] > 5242880) {
                    $message = 'Image size must not exceed 5MB.';
                    $message_type = 'error';
                } else {
                    $upload_dir = '../../uploads/promotion_banners/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                    $new_image_filename = 'banner_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $image_path = $upload_dir . $new_image_filename;

                    if (move_uploaded_file($image['tmp_name'], $image_path)) {
                        $old_image_path = $upload_dir . $row['image'];
                        if (file_exists($old_image_path)) {
                            unlink($old_image_path);
                        }
                    } else {
                        $message = 'Error uploading image.';
                        $message_type = 'error';
                    }
                }
            }

            // Update database if no errors
            if ($message_type !== 'error') {
                $update_query = "UPDATE promotion_discount SET title = ?, discount = ?, image = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                
                if ($update_stmt) {
                    $update_stmt->bind_param("sdsi", $title, $discount, $new_image_filename, $id);
                    
                    if ($update_stmt->execute()) {
                        $message = 'Promotion updated successfully!';
                        $message_type = 'success';
                        $action = 'list';
                    } else {
                        $message = 'Error updating promotion: ' . $update_stmt->error;
                        $message_type = 'error';
                    }
                    $update_stmt->close();
                } else {
                    $message = 'Database error: ' . $conn->error;
                    $message_type = 'error';
                }
            }
        }
    }


}

// Fetch promotion for edit
$promotion = [];
if ($action === 'edit' && $id > 0) {
    $query = "SELECT * FROM promotion_discount WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $promotion = $result->fetch_assoc();
    } else {
        $message = 'Promotion not found!';
        $message_type = 'error';
        $action = 'list';
    }
    $stmt->close();
}

// Fetch all promotions for list view
$promotions = [];
if ($action === 'list') {
    $query = "SELECT * FROM promotion_discount ORDER BY created_at DESC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $promotions[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Discount Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-linear-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <?php if ($action === 'list'): ?>
    <!-- LIST VIEW -->
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900">Promotion Discounts</h1>
                    <p class="text-gray-600 mt-2">Manage all your promotion banners and discounts</p>
                </div>
                <a href="?action=add" class="inline-flex items-center gap-2 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition shadow-lg">
                    <i class="fas fa-plus text-lg"></i>
                    Add New Promotion
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="mb-6 border-l-4 p-6 rounded-lg <?php echo $message_type === 'success' 
                    ? 'bg-green-50 border-green-500' 
                    : 'bg-red-50 border-red-500'; ?>">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 text-2xl">
                            <?php if ($message_type === 'success'): ?>
                                <i class="fas fa-check-circle text-green-600"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle text-red-600"></i>
                            <?php endif; ?>
                        </div>
                        <p class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> font-semibold">
                            <?php echo htmlspecialchars($message); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Promotions Grid -->
            <?php if (count($promotions) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($promotions as $promo): ?>
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition duration-300 group">
                            <!-- Image -->
                            <div class="relative h-48 bg-gray-200 overflow-hidden">
                                <img 
                                    src="../../uploads/promotion_banners/<?php echo htmlspecialchars($promo['image']); ?>" 
                                    alt="<?php echo htmlspecialchars($promo['title']); ?>"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                >
                                <!-- Discount Badge -->
                                <div class="absolute top-3 left-3 bg-red-500 text-white px-4 py-2 rounded-lg font-bold text-lg">
                                    <?php echo htmlspecialchars($promo['discount']); ?>%
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-5">
                                <h3 class="text-lg font-bold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($promo['title']); ?>
                                </h3>
                                <p class="text-xs text-gray-600 mb-4">
                                    Created: <?php echo date('M d, Y', strtotime($promo['created_at'])); ?>
                                </p>

                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <a 
                                        href="?action=edit&id=<?php echo $promo['id']; ?>"
                                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-3 rounded-lg transition text-center text-sm flex items-center justify-center gap-2"
                                    >
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <div class="text-6xl text-gray-400 mx-auto mb-4"><i class="fas fa-image"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Promotions Yet</h3>
                    <p class="text-gray-600 mb-6">Create your first promotion banner to get started.</p>
                    <a href="?action=add" class="inline-flex items-center gap-2 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition">
                        <i class="fas fa-plus text-lg"></i>
                        Create Promotion
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($action === 'add'): ?>
    <!-- ADD VIEW -->
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-2xl">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-linear-to-br from-blue-500 to-indigo-600 rounded-lg text-2xl text-white">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900">Add Promotion Banner</h1>
                        <p class="text-gray-600 mt-1">Create a new promotional banner to showcase your offers</p>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Alert Messages -->
                <?php if ($message && $action === 'add'): ?>
                    <div class="border-l-4 p-6 <?php echo $message_type === 'success' 
                        ? 'bg-green-50 border-green-500' 
                        : 'bg-red-50 border-red-500'; ?>">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5">
                                <?php if ($message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php endif; ?>
                            </div>
                            <p class="<?php echo $message_type === 'success' ? 'text-green-700 font-semibold' : 'text-red-700 font-semibold'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" id="bannerForm" class="p-8 space-y-8">
                    <input type="hidden" name="action_type" value="add">

                    <!-- Title Field -->
                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-900 mb-3">
                            Banner Title
                            <span class="text-red-500 ml-1">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            placeholder="e.g., Summer Sale 2024" 
                            required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-gray-900 placeholder-gray-500"
                        >
                        <p class="text-xs text-gray-600 mt-2">Enter a clear, descriptive title for your promotion</p>
                    </div>

                    <!-- Discount Field -->
                    <div>
                        <label for="discount" class="block text-sm font-semibold text-gray-900 mb-3">
                            Discount Percentage
                            <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                id="discount" 
                                name="discount" 
                                placeholder="0" 
                                step="0.01" 
                                min="0" 
                                max="100" 
                                required 
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-gray-900 pr-10"
                            >
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-600 font-semibold pointer-events-none">%</span>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">Enter a value between 0 and 100</p>
                    </div>

                    <!-- Image Upload Field -->
                    <div>
                        <label for="image" class="block text-sm font-semibold text-gray-900 mb-3">
                            Banner Image
                            <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 hover:border-blue-400 hover:bg-blue-50/30 transition-colors duration-200 cursor-pointer group">
                            <input 
                                type="file" 
                                id="image" 
                                name="image" 
                                accept="image/jpeg,image/png,image/gif,image/webp" 
                                required 
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                onchange="previewImage(event)"
                            >
                            <div class="text-center pointer-events-none">
                                <div class="text-5xl mb-3 group-hover:scale-110 transition"><i class="fas fa-cloud-upload-alt text-gray-400"></i></div>
                                <p class="text-gray-900 font-semibold text-lg">Click to upload or drag and drop</p>
                                <p class="text-gray-600 text-sm mt-2">PNG, JPG, GIF, WebP • Maximum 5MB</p>
                            </div>
                        </div>
                        <div id="previewContainer" class="mt-6 hidden">
                            <p class="text-xs font-semibold text-gray-700 mb-3">Preview:</p>
                            <div class="relative inline-block">
                                <img id="imagePreview" class="max-w-sm rounded-lg shadow-lg border-2 border-gray-200" alt="Banner preview">
                                <button 
                                    type="button" 
                                    onclick="removeImage()" 
                                    class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-2 shadow-lg transition w-8 h-8 flex items-center justify-center"
                                >
                                    <i class="fas fa-times text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-4 pt-6 border-t border-gray-200">
                        <button 
                            type="submit" 
                            class="flex-1 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 active:scale-95 text-white font-bold py-3 px-4 rounded-lg transition transform duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-save"></i>
                            Add Banner
                        </button>
                        <a 
                            href="?action=list" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 active:scale-95 text-gray-800 font-bold py-3 px-4 rounded-lg transition text-center duration-200 flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Info Box -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex gap-3">
                    <div class="text-2xl shrink-0"><i class="fas fa-lightbulb text-blue-600"></i></div>
                    <div>
                        <h3 class="font-semibold text-blue-900">Tip for best results</h3>
                        <p class="text-blue-800 text-sm mt-1">Use high-quality images with 16:9 aspect ratio for optimal display on all devices. Keep titles concise and discount percentages clear.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($action === 'edit'): ?>
    <!-- EDIT VIEW -->
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-2xl">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-linear-to-br from-blue-500 to-indigo-600 rounded-lg text-2xl text-white">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900">Edit Promotion</h1>
                        <p class="text-gray-600 mt-1">Update your promotional banner details</p>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Alert Messages -->
                <?php if ($message && $action === 'edit'): ?>
                    <div class="border-l-4 p-6 <?php echo $message_type === 'success' 
                        ? 'bg-green-50 border-green-500' 
                        : 'bg-red-50 border-red-500'; ?>">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5">
                                <?php if ($message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php endif; ?>
                            </div>
                            <p class="<?php echo $message_type === 'success' ? 'text-green-700 font-semibold' : 'text-red-700 font-semibold'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" id="editForm" class="p-8 space-y-8">
                    <input type="hidden" name="action_type" value="update">
                    <input type="hidden" name="id" value="<?php echo $promotion['id']; ?>">

                    <!-- Title Field -->
                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-900 mb-3">
                            Banner Title
                            <span class="text-red-500 ml-1">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            placeholder="e.g., Summer Sale 2024" 
                            required 
                            value="<?php echo htmlspecialchars($promotion['title'] ?? ''); ?>"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-gray-900 placeholder-gray-500"
                        >
                        <p class="text-xs text-gray-600 mt-2">Enter a clear, descriptive title for your promotion</p>
                    </div>

                    <!-- Discount Field -->
                    <div>
                        <label for="discount" class="block text-sm font-semibold text-gray-900 mb-3">
                            Discount Percentage
                            <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                id="discount" 
                                name="discount" 
                                placeholder="0" 
                                step="0.01" 
                                min="0" 
                                max="100" 
                                required 
                                value="<?php echo htmlspecialchars($promotion['discount'] ?? ''); ?>"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-gray-900 pr-10"
                            >
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-600 font-semibold pointer-events-none">%</span>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">Enter a value between 0 and 100</p>
                    </div>

                    <!-- Image Upload Field -->
                    <div>
                        <label for="image" class="block text-sm font-semibold text-gray-900 mb-3">
                            Banner Image (Optional)
                            <span class="text-gray-500 text-sm ml-1">Leave blank to keep current image</span>
                        </label>
                        <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 hover:border-blue-400 hover:bg-blue-50/30 transition-colors duration-200 cursor-pointer group">
                            <input 
                                type="file" 
                                id="image" 
                                name="image" 
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                onchange="previewImage(event)"
                            >
                            <div class="text-center pointer-events-none">
                                <div class="text-5xl mb-3 group-hover:scale-110 transition"><i class="fas fa-image text-gray-400"></i></div>
                                <p class="text-gray-900 font-semibold text-lg">Click to upload or drag and drop</p>
                                <p class="text-gray-600 text-sm mt-2">PNG, JPG, GIF, WebP • Maximum 5MB</p>
                            </div>
                        </div>

                        <!-- Current Image Preview -->
                        <div class="mt-6">
                            <p class="text-xs font-semibold text-gray-700 mb-3">Current Image:</p>
                            <div class="relative inline-block">
                                <img 
                                    id="currentImage"
                                    src="../../uploads/promotion_banners/<?php echo htmlspecialchars($promotion['image'] ?? ''); ?>" 
                                    alt="Current banner"
                                    class="max-w-sm rounded-lg shadow-lg border-2 border-gray-200"
                                >
                            </div>
                        </div>

                        <!-- New Image Preview -->
                        <div id="previewContainer" class="mt-6 hidden">
                            <p class="text-xs font-semibold text-gray-700 mb-3">New Image Preview:</p>
                            <div class="relative inline-block">
                                <img id="imagePreview" class="max-w-sm rounded-lg shadow-lg border-2 border-blue-200" alt="New banner preview">
                                <button 
                                    type="button" 
                                    onclick="removeImage()" 
                                    class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-2 shadow-lg transition w-8 h-8 flex items-center justify-center"
                                >
                                    <i class="fas fa-times text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-4 pt-6 border-t border-gray-200">
                        <button 
                            type="submit" 
                            class="flex-1 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 active:scale-95 text-white font-bold py-3 px-4 rounded-lg transition transform duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-save"></i>
                            Update Promotion
                        </button>
                        <a 
                            href="?action=list" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 active:scale-95 text-gray-800 font-bold py-3 px-4 rounded-lg transition text-center duration-200 flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Info Box -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex gap-3">
                    <div class="text-2xl shrink-0"><i class="fas fa-lightbulb text-blue-600"></i></div>
                    <div>
                        <h3 class="font-semibold text-blue-900">Editing tips</h3>
                        <p class="text-blue-800 text-sm mt-1">You can update the title and discount percentage without changing the image. If you want a new image, upload it above and the old one will be replaced automatically.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const container = document.getElementById('previewContainer');
            
            if (file) {
                if (file.size > 5242880) {
                    alert('Image size exceeds 5MB. Please choose a smaller file.');
                    event.target.value = '';
                    container.classList.add('hidden');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                container.classList.add('hidden');
            }
        }

        function removeImage() {
            document.getElementById('image').value = '';
            document.getElementById('previewContainer').classList.add('hidden');
        }

        const uploadArea = document.querySelector('.border-dashed');
        if (uploadArea) {
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-blue-400', 'bg-blue-50');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
                const files = e.dataTransfer.files;
                document.getElementById('image').files = files;
                previewImage({ target: { files: files } });
            });
        }
    </script>
</body>
</html>