<?php
session_name("nobleadmin");
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get current user details
function resolve_current_user_details($conn) {
    if (!empty($_SESSION['current_user_details'])) {
        return $_SESSION['current_user_details'];
    }

    if (empty($_SESSION['noble_user'])) {
        return null;
    }

    $loginValue = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, email, lvl FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $loginValue, $loginValue);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userDetails = [
                'id' => (int)$row['id'],
                'name' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['lvl']
            ];
            $_SESSION['current_user_details'] = $userDetails;
            $stmt->close();
            return $userDetails;
        }
        $stmt->close();
    }
    return null;
}

$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: ../../loginpage/index.php");
    exit();
}

$current_user_id = $current_user['id'];

// Get QR code ID from URL
$qr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($qr_id <= 0) {
    $_SESSION['error_message'] = "Invalid QR code ID.";
    header("Location: manage_qr_codes.php");
    exit();
}

// Fetch QR code details
$stmt = $conn->prepare("SELECT * FROM payment_qr_codes WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $qr_id);
$stmt->execute();
$result = $stmt->get_result();
$qr_code = $result->fetch_assoc();
$stmt->close();

if (!$qr_code) {
    $_SESSION['error_message'] = "QR code not found.";
    header("Location: manage_qr_codes.php");
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_qr_code') {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $account_name = trim($_POST['account_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if (empty($payment_method) || empty($account_name) || empty($account_number)) {
            $error_message = "Payment method, account name, and account number are required.";
        } else {
            $new_image_path = $qr_code['qr_code_image']; // Keep existing image by default

            // Handle new file upload if provided
            if (isset($_FILES['qr_code_image']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/qr_codes/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file = $_FILES['qr_code_image'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
                } elseif ($file['size'] > 5242880) { // 5MB max
                    $error_message = "File size must be less than 5MB.";
                } else {
                    // Generate unique filename
                    $new_filename = 'qr_' . strtolower(str_replace(' ', '_', $payment_method)) . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    $db_path = 'uploads/qr_codes/' . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old image
                        if (file_exists('../../' . $qr_code['qr_code_image'])) {
                            unlink('../../' . $qr_code['qr_code_image']);
                        }
                        $new_image_path = $db_path;
                    } else {
                        $error_message = "Failed to upload new image. Please check directory permissions.";
                    }
                }
            }

            // Update database if no errors
            if (empty($error_message)) {
                $stmt = $conn->prepare("UPDATE payment_qr_codes SET payment_method = ?, account_name = ?, account_number = ?, qr_code_image = ?, instructions = ?, display_order = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                
                if ($stmt) {
                    $stmt->bind_param("sssssiiii", $payment_method, $account_name, $account_number, $new_image_path, $instructions, $display_order, $is_active, $current_user_id, $qr_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "QR code updated successfully!";
                        
                        // Send notification to superadmins
                        $notification_msg = $current_user['name'] . " updated the payment QR code: " . $payment_method;
                        $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) SELECT id, ?, 'qr_code_updated', ?, NOW() FROM nobleaccount WHERE lvl = 'superadmin'");
                        if ($notify_stmt) {
                            $notify_stmt->bind_param("is", $current_user_id, $notification_msg);
                            $notify_stmt->execute();
                            $notify_stmt->close();
                        }

                        // Refresh QR code data
                        $refresh_stmt = $conn->prepare("SELECT * FROM payment_qr_codes WHERE id = ? LIMIT 1");
                        $refresh_stmt->bind_param("i", $qr_id);
                        $refresh_stmt->execute();
                        $refresh_result = $refresh_stmt->get_result();
                        $qr_code = $refresh_result->fetch_assoc();
                        $refresh_stmt->close();
                    } else {
                        $error_message = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Database prepare error: " . $conn->error;
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_qr_code') {
        // Delete QR code
        $confirm_delete = $_POST['confirm_delete'] ?? '';
        
        if ($confirm_delete !== 'DELETE') {
            $error_message = "Please type DELETE to confirm deletion.";
        } else {
            // Delete image file
            if (file_exists('../../' . $qr_code['qr_code_image'])) {
                unlink('../../' . $qr_code['qr_code_image']);
            }

            // Delete from database
            $stmt = $conn->prepare("DELETE FROM payment_qr_codes WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $qr_id);
                if ($stmt->execute()) {
                    // Send notification
                    $notification_msg = $current_user['name'] . " deleted the payment QR code: " . $qr_code['payment_method'];
                    $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) SELECT id, ?, 'qr_code_deleted', ?, NOW() FROM nobleaccount WHERE lvl = 'superadmin'");
                    if ($notify_stmt) {
                        $notify_stmt->bind_param("is", $current_user_id, $notification_msg);
                        $notify_stmt->execute();
                        $notify_stmt->close();
                    }

                    $_SESSION['success_message'] = "QR code deleted successfully!";
                    header("Location: manage_qr_codes.php");
                    exit();
                } else {
                    $error_message = "Failed to delete QR code: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment QR Code - Noble Home</title>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <a href="manage_qr_codes.php" class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="w-10 h-10 bg-gradient-to-br from-noble-orange to-noble-orange-dark rounded-lg flex items-center justify-center shadow-md">
                        <i class="fas fa-edit text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Edit Payment QR Code</h1>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($qr_code['payment_method']); ?></p>
                    </div>
                </div>
                <button onclick="showDeleteModal()" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors shadow-md">
                    <i class="fas fa-trash mr-2"></i>
                    Delete QR Code
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-6 py-8">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between animate-fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2 text-lg"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between animate-fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2 text-lg"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Edit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-edit text-noble-orange mr-2"></i>
                        Edit QR Code Details
                    </h2>

                    <form method="POST" enctype="multipart/form-data" id="editQRForm" class="space-y-5">
                        <input type="hidden" name="action" value="update_qr_code">

                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-2">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select name="payment_method" id="payment_method" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                                <option value="">Select Payment Method</option>
                                <option value="GCash" <?php echo $qr_code['payment_method'] === 'GCash' ? 'selected' : ''; ?>>GCash</option>
                                <option value="PayMaya" <?php echo $qr_code['payment_method'] === 'PayMaya' ? 'selected' : ''; ?>>PayMaya</option>
                                <option value="QR Ph" <?php echo $qr_code['payment_method'] === 'QR Ph' ? 'selected' : ''; ?>>QR Ph (InstaPay)</option>
                                <option value="BPI" <?php echo $qr_code['payment_method'] === 'BPI' ? 'selected' : ''; ?>>BPI QR</option>
                                <option value="BDO" <?php echo $qr_code['payment_method'] === 'BDO' ? 'selected' : ''; ?>>BDO QR</option>
                                <option value="AUB" <?php echo $qr_code['payment_method'] === 'AUB' ? 'selected' : ''; ?>>AUB QR</option>
                                <option value="UnionBank" <?php echo $qr_code['payment_method'] === 'UnionBank' ? 'selected' : ''; ?>>UnionBank QR</option>
                                <option value="Other" <?php echo $qr_code['payment_method'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <!-- Account Name -->
                        <div>
                            <label for="account_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                Account Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="account_name" id="account_name" required
                                value="<?php echo htmlspecialchars($qr_code['account_name']); ?>"
                                placeholder="e.g., Noble Home Store"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                        </div>

                        <!-- Account Number -->
                        <div>
                            <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2">
                                Account Number / Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="account_number" id="account_number" required
                                value="<?php echo htmlspecialchars($qr_code['account_number']); ?>"
                                placeholder="e.g., 09171234567"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                        </div>

                        <!-- QR Code Image Upload -->
                        <div>
                            <label for="qr_code_image" class="block text-sm font-semibold text-gray-700 mb-2">
                                Replace QR Code Image (Optional)
                            </label>
                            <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-noble-orange transition-colors">
                                <div class="space-y-2 text-center">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="qr_code_image" class="relative cursor-pointer bg-white rounded-md font-medium text-noble-orange hover:text-noble-orange-dark">
                                            <span>Upload a new file</span>
                                            <input id="qr_code_image" name="qr_code_image" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                                    <p class="text-xs text-blue-600">Leave empty to keep current image</p>
                                </div>
                            </div>
                            <div id="imagePreview" class="mt-4 hidden">
                                <p class="text-sm font-medium text-gray-700 mb-2">New Image Preview:</p>
                                <img id="previewImg" src="" alt="Preview" class="max-w-xs mx-auto rounded-lg border border-gray-300 shadow-sm">
                            </div>
                        </div>

                        <!-- Display Order -->
                        <div>
                            <label for="display_order" class="block text-sm font-semibold text-gray-700 mb-2">
                                Display Order
                            </label>
                            <input type="number" name="display_order" id="display_order" 
                                value="<?php echo $qr_code['display_order']; ?>" min="0"
                                placeholder="0"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                            <p class="mt-1 text-xs text-gray-500">Lower numbers appear first</p>
                        </div>

                        <!-- Instructions -->
                        <div>
                            <label for="instructions" class="block text-sm font-semibold text-gray-700 mb-2">
                                Payment Instructions
                            </label>
                            <textarea name="instructions" id="instructions" rows="4"
                                placeholder="e.g., Scan this QR code to pay via GCash. Please send a screenshot of your payment confirmation."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent"><?php echo htmlspecialchars($qr_code['instructions']); ?></textarea>
                        </div>

                        <!-- Active Status -->
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                            <input type="checkbox" name="is_active" id="is_active" 
                                <?php echo $qr_code['is_active'] ? 'checked' : ''; ?>
                                class="h-5 w-5 text-noble-orange focus:ring-noble-orange border-gray-300 rounded">
                            <label for="is_active" class="ml-3 block text-sm font-medium text-gray-700">
                                <i class="fas fa-eye mr-1"></i>
                                Set as Active (visible to customers)
                            </label>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center justify-between pt-6 border-t">
                            <a href="manage_qr_codes.php" 
                                class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            <button type="submit"
                                class="px-6 py-3 bg-noble-orange text-white rounded-lg hover:bg-noble-orange-dark focus:outline-none focus:ring-2 focus:ring-noble-orange transition-colors font-medium shadow-md">
                                <i class="fas fa-save mr-2"></i>
                                Update QR Code
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar - Current QR Code Preview -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-6 sticky top-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-image text-noble-orange mr-2"></i>
                        Current QR Code
                    </h3>

                    <div class="mb-4">
                        <img src="../../<?php echo htmlspecialchars($qr_code['qr_code_image']); ?>" 
                             alt="Current QR Code" 
                             class="w-full rounded-lg border border-gray-300 shadow-sm cursor-pointer hover:shadow-lg transition-shadow"
                             onclick="viewFullSize('../../<?php echo htmlspecialchars($qr_code['qr_code_image']); ?>')">
                    </div>

                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Status:</span>
                            <?php if ($qr_code['is_active']): ?>
                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Display Order:</span>
                            <span class="font-medium text-gray-900"><?php echo $qr_code['display_order']; ?></span>
                        </div>
                    </div>

                    <div class="border-t pt-4 space-y-2 text-xs text-gray-500">
                        <p><i class="fas fa-user-plus mr-1"></i> Created by: <?php 
                            $creator_stmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ? LIMIT 1");
                            $creator_stmt->bind_param("i", $qr_code['created_by']);
                            $creator_stmt->execute();
                            $creator_result = $creator_stmt->get_result();
                            $creator = $creator_result->fetch_assoc();
                            echo htmlspecialchars($creator['fullname']);
                            $creator_stmt->close();
                        ?></p>
                        <p><i class="fas fa-calendar mr-1"></i> Created: <?php echo date('M d, Y H:i', strtotime($qr_code['created_at'])); ?></p>
                        <?php if ($qr_code['updated_by']): ?>
                            <p><i class="fas fa-edit mr-1"></i> Last updated by: <?php 
                                $updater_stmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ? LIMIT 1");
                                $updater_stmt->bind_param("i", $qr_code['updated_by']);
                                $updater_stmt->execute();
                                $updater_result = $updater_stmt->get_result();
                                $updater = $updater_result->fetch_assoc();
                                echo htmlspecialchars($updater['fullname']);
                                $updater_stmt->close();
                            ?></p>
                            <p><i class="fas fa-clock mr-1"></i> Updated: <?php echo date('M d, Y H:i', strtotime($qr_code['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <a href="../../<?php echo htmlspecialchars($qr_code['qr_code_image']); ?>" 
                           download
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Download QR Code
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-6 border w-11/12 md:w-1/2 lg:w-1/3 shadow-2xl rounded-xl bg-white">
            <div class="flex items-center justify-between pb-4 border-b">
                <h3 class="text-xl font-bold text-red-600 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Confirm Deletion
                </h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mt-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-red-800">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <strong>Warning:</strong> This action cannot be undone. The QR code image will be permanently deleted.
                    </p>
                </div>

                <p class="text-gray-700 mb-4">
                    You are about to delete: <strong><?php echo htmlspecialchars($qr_code['payment_method']); ?></strong>
                </p>

                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_qr_code">
                    
                    <div class="mb-4">
                        <label for="confirm_delete" class="block text-sm font-semibold text-gray-700 mb-2">
                            Type <code class="bg-gray-100 px-2 py-1 rounded text-red-600">DELETE</code> to confirm:
                        </label>
                        <input type="text" name="confirm_delete" id="confirm_delete" 
                            placeholder="Type DELETE"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>

                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()"
                            class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors font-medium shadow-md">
                            <i class="fas fa-trash mr-2"></i>
                            Delete Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Full Size Image Modal -->
    <div id="fullSizeModal" class="fixed inset-0 bg-gray-900 bg-opacity-90 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 w-11/12 md:w-3/4 lg:w-1/2">
            <div class="flex justify-end mb-2">
                <button onclick="closeFullSize()" class="text-white hover:text-gray-300 bg-black bg-opacity-50 rounded-full p-3">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="flex justify-center">
                <img id="fullSizeImage" src="" alt="Full Size QR Code" class="max-w-full max-h-screen object-contain rounded-lg shadow-2xl">
            </div>
        </div>
    </div>

    <script>
        // Preview uploaded image
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Show delete modal
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('confirm_delete').value = '';
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // View full size image
        function viewFullSize(imagePath) {
            document.getElementById('fullSizeImage').src = imagePath;
            document.getElementById('fullSizeModal').classList.remove('hidden');
        }

        // Close full size modal
        function closeFullSize() {
            document.getElementById('fullSizeModal').classList.add('hidden');
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
                closeFullSize();
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const fullSizeModal = document.getElementById('fullSizeModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === fullSizeModal) {
                closeFullSize();
            }
        });

        // Form validation
        document.getElementById('editQRForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('qr_code_image');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileSize = file.size / 1024 / 1024; // in MB
                
                if (fileSize > 5) {
                    e.preventDefault();
                    alert('File size must be less than 5MB');
                    return false;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Please upload a valid image file (JPG, PNG, or GIF)');
                    return false;
                }
            }
        });

        // Delete form validation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const confirmInput = document.getElementById('confirm_delete').value;
            
            if (confirmInput !== 'DELETE') {
                e.preventDefault();
                alert('Please type DELETE to confirm deletion');
                return false;
            }

            if (!confirm('Are you absolutely sure? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.animate-fade-in');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Warn user about unsaved changes
        let formChanged = false;
        const form = document.getElementById('editQRForm');
        const formElements = form.querySelectorAll('input, select, textarea');
        
        formElements.forEach(element => {
            element.addEventListener('change', function() {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });

        console.log('Edit QR Code page loaded successfully');
    </script>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        /* Smooth transitions */
        * {
            transition: all 0.2s ease-in-out;
        }

        /* Sticky sidebar */
        .sticky {
            position: sticky;
        }
    </style>
</body>
</html>