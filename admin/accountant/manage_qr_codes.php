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

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_qr_code') {
    $payment_method = trim($_POST['payment_method'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($payment_method) || empty($account_name) || empty($account_number)) {
        $error_message = "Payment method, account name, and account number are required.";
    } elseif (!isset($_FILES['qr_code_image']) || $_FILES['qr_code_image']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Please upload a valid QR code image.";
    } else {
        // Handle file upload
        $upload_dir = '../../uploads/qr_codes/';
        
        // Create directory if it doesn't exist
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
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO payment_qr_codes (payment_method, account_name, account_number, qr_code_image, instructions, display_order, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt) {
                    $stmt->bind_param("sssssiii", $payment_method, $account_name, $account_number, $db_path, $instructions, $display_order, $is_active, $current_user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "QR code payment method added successfully!";
                        
                        // Send notification to superadmins
                        $notification_msg = $current_user['name'] . " added a new payment QR code: " . $payment_method;
                        $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) SELECT id, ?, 'qr_code_added', ?, NOW() FROM nobleaccount WHERE lvl = 'superadmin'");
                        if ($notify_stmt) {
                            $notify_stmt->bind_param("is", $current_user_id, $notification_msg);
                            $notify_stmt->execute();
                            $notify_stmt->close();
                        }
                    } else {
                        $error_message = "Database error: " . $stmt->error;
                        // Delete uploaded file if DB insert fails
                        unlink($upload_path);
                    }
                    $stmt->close();
                } else {
                    $error_message = "Database prepare error: " . $conn->error;
                    unlink($upload_path);
                }
            } else {
                $error_message = "Failed to upload file. Please check directory permissions.";
            }
        }
    }
}

// Handle toggle active status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $qr_id = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE payment_qr_codes SET is_active = NOT is_active, updated_by = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $current_user_id, $qr_id);
        if ($stmt->execute()) {
            $success_message = "Status updated successfully!";
        }
        $stmt->close();
    }
    header("Location: manage_qr_codes.php");
    exit();
}

// Fetch all QR codes
$qr_codes_result = $conn->query("
    SELECT pqr.*, 
           cb.fullname as created_by_name,
           ub.fullname as updated_by_name
    FROM payment_qr_codes pqr
    LEFT JOIN nobleaccount cb ON pqr.created_by = cb.id
    LEFT JOIN nobleaccount ub ON pqr.updated_by = ub.id
    ORDER BY pqr.display_order ASC, pqr.created_at DESC
");

// Get statistics
$total_qr_codes = 0;
$active_qr_codes = 0;

$stats = $conn->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM payment_qr_codes");
if ($stats) {
    $stats_data = $stats->fetch_assoc();
    $total_qr_codes = $stats_data['total'] ?? 0;
    $active_qr_codes = $stats_data['active'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment QR Codes - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-noble-orange to-noble-orange-dark rounded-lg flex items-center justify-center shadow-md">
                        <i class="fas fa-qrcode text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Payment QR Code Management</h1>
                        <p class="text-sm text-gray-600">Manage payment methods and QR codes</p>
                    </div>
                </div>
                <button onclick="showAddModal()" class="inline-flex items-center px-4 py-2 bg-noble-orange text-white rounded-lg hover:bg-noble-orange-dark transition-colors shadow-md">
                    <i class="fas fa-plus mr-2"></i>
                    Add New QR Code
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-qrcode text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total QR Codes</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_qr_codes; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Methods</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $active_qr_codes; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inactive Methods</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_qr_codes - $active_qr_codes; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Codes Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($qr_codes_result && $qr_codes_result->num_rows > 0): ?>
                <?php while ($qr = $qr_codes_result->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow">
                        <!-- QR Code Image -->
                        <div class="relative h-64 bg-gray-100 flex items-center justify-center p-4">
                            <img src="../../<?php echo htmlspecialchars($qr['qr_code_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($qr['payment_method']); ?> QR Code"
                                 class="max-h-full max-w-full object-contain cursor-pointer"
                                 onclick="viewQRCode('../../<?php echo htmlspecialchars($qr['qr_code_image']); ?>', '<?php echo htmlspecialchars($qr['payment_method']); ?>')">
                            
                            <!-- Status Badge -->
                            <div class="absolute top-3 right-3">
                                <?php if ($qr['is_active']): ?>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 border border-green-200">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Display Order Badge -->
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-noble-orange text-white text-sm font-bold shadow-md">
                                    <?php echo $qr['display_order']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-5">
                            <!-- Payment Method -->
                            <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-credit-card text-noble-orange mr-2"></i>
                                <?php echo htmlspecialchars($qr['payment_method']); ?>
                            </h3>

                            <!-- Account Details -->
                            <div class="space-y-2 mb-4">
                                <div class="flex items-start">
                                    <i class="fas fa-user text-gray-400 mt-1 mr-2 text-sm"></i>
                                    <div>
                                        <p class="text-xs text-gray-500">Account Name</p>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($qr['account_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-phone text-gray-400 mt-1 mr-2 text-sm"></i>
                                    <div>
                                        <p class="text-xs text-gray-500">Account Number</p>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($qr['account_number']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Instructions -->
                            <?php if ($qr['instructions']): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Instructions</p>
                                    <p class="text-sm text-gray-700 line-clamp-2"><?php echo htmlspecialchars($qr['instructions']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Metadata -->
                            <div class="text-xs text-gray-500 mb-4 space-y-1 border-t pt-3">
                                <p><i class="fas fa-user-plus mr-1"></i> Created by: <?php echo htmlspecialchars($qr['created_by_name']); ?></p>
                                <p><i class="fas fa-calendar mr-1"></i> <?php echo date('M d, Y H:i', strtotime($qr['created_at'])); ?></p>
                                <?php if ($qr['updated_by_name']): ?>
                                    <p><i class="fas fa-edit mr-1"></i> Updated by: <?php echo htmlspecialchars($qr['updated_by_name']); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                <a href="?toggle_status=1&id=<?php echo $qr['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to <?php echo $qr['is_active'] ? 'deactivate' : 'activate'; ?> this payment method?')"
                                   class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-<?php echo $qr['is_active'] ? 'times' : 'check'; ?> mr-1"></i>
                                    <?php echo $qr['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="edit_qr_code.php?id=<?php echo $qr['id']; ?>" 
                                   class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-noble-orange hover:bg-noble-orange-dark transition-colors">
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-qrcode text-4xl text-gray-300"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No QR Codes Yet</h3>
                        <p class="text-gray-600 mb-6">Get started by adding your first payment QR code.</p>
                        <button onclick="showAddModal()" class="inline-flex items-center px-6 py-3 bg-noble-orange text-white rounded-lg hover:bg-noble-orange-dark transition-colors shadow-md">
                            <i class="fas fa-plus mr-2"></i>
                            Add QR Code
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add QR Code Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-8 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-xl bg-white">
            <div class="flex items-center justify-between pb-4 border-b">
                <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle text-noble-orange mr-3"></i>
                    Add New Payment QR Code
                </h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-5" id="qrCodeForm">
                <input type="hidden" name="action" value="add_qr_code">

                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-2">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select name="payment_method" id="payment_method" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                        <option value="">Select Payment Method</option>
                        <option value="GCash">GCash</option>
                        <option value="PayMaya">PayMaya</option>
                        <option value="QR Ph">QR Ph (InstaPay)</option>
                        <option value="BPI">BPI QR</option>
                        <option value="BDO">BDO QR</option>
                        <option value="AUB">AUB QR</option>
                        <option value="UnionBank">UnionBank QR</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Account Name -->
                <div>
                    <label for="account_name" class="block text-sm font-semibold text-gray-700 mb-2">
                        Account Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="account_name" id="account_name" required
                        placeholder="e.g., Noble Home Store"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                </div>

                <!-- Account Number -->
                <div>
                    <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2">
                        Account Number / Mobile Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="account_number" id="account_number" required
                        placeholder="e.g., 09171234567"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                </div>

                <!-- QR Code Image Upload -->
                <div>
                    <label for="qr_code_image" class="block text-sm font-semibold text-gray-700 mb-2">
                        QR Code Image <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-noble-orange transition-colors">
                        <div class="space-y-2 text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <div class="flex text-sm text-gray-600">
                                <label for="qr_code_image" class="relative cursor-pointer bg-white rounded-md font-medium text-noble-orange hover:text-noble-orange-dark">
                                    <span>Upload a file</span>
                                    <input id="qr_code_image" name="qr_code_image" type="file" class="sr-only" required accept="image/*" onchange="previewImage(this)">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                        </div>
                    </div>
                    <div id="imagePreview" class="mt-4 hidden">
                        <img id="previewImg" src="" alt="Preview" class="max-w-xs mx-auto rounded-lg border border-gray-300 shadow-sm">
                    </div>
                </div>

                <!-- Display Order -->
                <div>
                    <label for="display_order" class="block text-sm font-semibold text-gray-700 mb-2">
                        Display Order
                    </label>
                    <input type="number" name="display_order" id="display_order" value="0" min="0"
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
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent"></textarea>
                </div>

                <!-- Active Status -->
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" checked
                        class="h-5 w-5 text-noble-orange focus:ring-noble-orange border-gray-300 rounded">
                    <label for="is_active" class="ml-3 block text-sm font-medium text-gray-700">
                        Set as Active (visible to customers)
                    </label>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end pt-6 border-t space-x-3">
                    <button type="button" onclick="closeAddModal()"
                        class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-3 bg-noble-orange text-white rounded-lg hover:bg-noble-orange-dark focus:outline-none focus:ring-2 focus:ring-noble-orange transition-colors font-medium shadow-md">
                        <i class="fas fa-save mr-2"></i>
                        Save QR Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View QR Code Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-xl bg-white">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-xl font-bold text-gray-900" id="viewModalTitle">QR Code</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mt-4">
                <div class="flex justify-center p-6 bg-gray-50 rounded-lg">
                    <img id="viewQRImage" src="" alt="QR Code" class="max-w-full max-h-96 object-contain rounded-lg shadow-lg">
                </div>
                <div class="mt-4 flex justify-center space-x-3">
                    <a id="downloadQRLink" href="" download class="inline-flex items-center px-4 py-2 bg-noble-orange text-white rounded-lg hover:bg-noble-orange-dark transition-colors">
                        <i class="fas fa-download mr-2"></i>
                        Download QR Code
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show Add Modal
        function showAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('qrCodeForm').reset();
            document.getElementById('imagePreview').classList.add('hidden');
        }

        // Close Add Modal
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

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

        // View QR Code
        function viewQRCode(imagePath, paymentMethod) {
            document.getElementById('viewModalTitle').textContent = paymentMethod + ' QR Code';
            document.getElementById('viewQRImage').src = imagePath;
            document.getElementById('downloadQRLink').href = imagePath;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        // Close View Modal
        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeViewModal();
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const addModal = document.getElementById('addModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        });

        // Form validation
        document.getElementById('qrCodeForm').addEventListener('submit', function(e) {
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

        console.log('QR Code Management page loaded successfully');
    </script>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>