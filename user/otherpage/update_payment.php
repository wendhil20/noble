<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? null;

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    header('Location: profile.php');
    exit;
}

// Verify order belongs to user and has rejected payment status
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND email = ? AND payment_status = 'rejected' LIMIT 1");
$stmt->bind_param("is", $order_id, $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found or not rejected - redirect back
    header('Location: profile.php?error=order_not_found');
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// ---- Upload path configuration (used both for display and server operations) ----
$UPLOAD_WEB_REL = '../../uploads/payment_screenshots/'; // web path used in <img src="...">
$UPLOAD_DIR_FS_BASE = __DIR__ . '/../../uploads/payment_screenshots/'; // filesystem path relative to this file

// Ensure upload dir exists
if (!is_dir($UPLOAD_DIR_FS_BASE)) {
    @mkdir($UPLOAD_DIR_FS_BASE, 0755, true);
}
$UPLOAD_DIR_FS = realpath($UPLOAD_DIR_FS_BASE);
if ($UPLOAD_DIR_FS === false) {
    // fallback to path even if realpath fails
    $UPLOAD_DIR_FS = $UPLOAD_DIR_FS_BASE;
}
$UPLOAD_DIR_FS = rtrim($UPLOAD_DIR_FS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Initialize messages
$success_message = '';
$error_message = '';

// ---- Handle form submission (upload, delete old, update DB) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_number = trim($_POST['reference_number'] ?? '');
    // Handle empty string to NULL conversion in PHP instead of SQL
    $reference_number_value = !empty($reference_number) ? $reference_number : null;
    
    $upload_success = false;
    $stored_filename = null;

    // current DB value (may be filename or path)
    $old_screenshot = $order['payment_screenshot'] ?? null;

    // file rules
    $MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

    if (empty($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Please upload a payment screenshot.";
    } else {
        $file_tmp  = $_FILES['payment_screenshot']['tmp_name'];
        $file_size = $_FILES['payment_screenshot']['size'];

        if ($file_size > $MAX_FILE_SIZE) {
            $error_message = "File size must be less than 10MB.";
        } else {
            // validate mime
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            $image_info = @getimagesize($file_tmp);
            if (!$image_info || !in_array($mime, $allowed_mimes)) {
                $error_message = "Invalid image file. Please upload JPG, PNG or GIF.";
            } else {
                // generate safe basename
                $timestamp = time();
                $oid = intval($order_id);
                $base_filename = 'payment_' . $oid . '_' . $timestamp;

                // try convert to webp if possible
                $converted_to_webp = false;
                $webp_filename = $base_filename . '.webp';
                $webp_fullpath  = $UPLOAD_DIR_FS . $webp_filename;

                $img = false;
                if (function_exists('imagewebp')) {
                    switch ($mime) {
                        case 'image/jpeg': case 'image/jpg':
                            $img = @imagecreatefromjpeg($file_tmp); break;
                        case 'image/png':
                            $img = @imagecreatefrompng($file_tmp);
                            if ($img !== false) {
                                if (!imageistruecolor($img)) {
                                    $tmp = imagecreatetruecolor(imagesx($img), imagesy($img));
                                    imagecopy($tmp, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                                    imagedestroy($img);
                                    $img = $tmp;
                                }
                                imagealphablending($img, true);
                                imagesavealpha($img, true);
                            }
                            break;
                        case 'image/gif':
                            $img = @imagecreatefromgif($file_tmp); break;
                    }

                    if ($img !== false) {
                        if (@imagewebp($img, $webp_fullpath, 80)) {
                            $converted_to_webp = true;
                            imagedestroy($img);
                            $stored_filename = $webp_filename; // store basename only
                        } else {
                            imagedestroy($img);
                        }
                    }
                }

                // fallback: move original file
                if (!$converted_to_webp) {
                    $ext = '';
                    switch ($mime) {
                        case 'image/jpeg': case 'image/jpg': $ext = '.jpg'; break;
                        case 'image/png': $ext = '.png'; break;
                        case 'image/gif': $ext = '.gif'; break;
                    }
                    $orig_filename = $base_filename . $ext;
                    $orig_fullpath = $UPLOAD_DIR_FS . $orig_filename;

                    if (!move_uploaded_file($file_tmp, $orig_fullpath)) {
                        $error_message = "Failed to save uploaded file.";
                    } else {
                        $stored_filename = $orig_filename;
                    }
                }

                // verify saved file exists
                if (!empty($stored_filename)) {
                    $candidate_full = $UPLOAD_DIR_FS . $stored_filename;
                    if (!file_exists($candidate_full)) {
                        $error_message = "Uploaded file not found after save.";
                    } else {
                        $upload_success = true;

                        // delete old file if exists (old value may be filename or path)
                        if (!empty($old_screenshot)) {
                            $old_base = basename($old_screenshot);
                            $old_full = $UPLOAD_DIR_FS . $old_base;
                            if (file_exists($old_full) && is_file($old_full)) {
                                @unlink($old_full);
                            }
                        }
                    }
                }
            }
        }
    }

    // update DB if upload OK
    if ($upload_success && !empty($stored_filename)) {
        if (!($conn instanceof mysqli)) {
            $error_message = "Database connection error.";
        } else {
            try {
                $conn->autocommit(false);

                // FIXED: Removed NULLIF - handle NULL conversion in PHP above
                $update_query = "UPDATE orders SET 
                                    payment_status = 'pending', 
                                    payment_screenshot = ?, 
                                    reference_number = ?, 
                                    rejected_by = NULL, 
                                    updated_at = NOW() 
                                 WHERE id = ? AND email = ?";

                $stmt = $conn->prepare($update_query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                // Use the processed reference_number_value (can be NULL or string)
                $stmt->bind_param("ssis", $stored_filename, $reference_number_value, $order_id, $user_email);

                if (!$stmt->execute()) {
                    $conn->rollback();
                    $stmt->close();
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                if (!$conn->commit()) {
                    $stmt->close();
                    throw new Exception("Commit failed: " . $conn->error);
                }

                $stmt->close();
                $conn->autocommit(true);

                // success: redirect to profile
                header("Location: profile.php?updated=success");
                exit;

            } catch (Exception $e) {
                if ($conn instanceof mysqli) $conn->rollback();
                $error_message = "Database error occurred. " . $e->getMessage();
            } finally {
                if ($conn instanceof mysqli) $conn->autocommit(true);
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
       <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Update Payment - Order #<?= htmlspecialchars($order_id, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFE15D',
                        neutral: '#1F2937',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        .file-upload-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }
        
        .file-upload-area.dragover {
            border-color: #1d4ed8;
            background-color: #dbeafe;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 animate-fade-in">
            <div class="flex items-center gap-4 mb-4">
                <button onclick="window.history.back()" class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Update Payment Information</h1>
                    <p class="text-gray-600">Order #<?= htmlspecialchars($order_id, ENT_QUOTES) ?> - ₱<?= number_format($order['final_total'], 2) ?></p>
                </div>
            </div>
            
            <!-- Rejection Notice -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-red-800">Payment Rejected</h3>
                        <p class="text-sm text-red-600">Please upload a new payment screenshot with correct information.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 animate-fade-in">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-green-800 font-medium"><?= htmlspecialchars($success_message, ENT_QUOTES) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 animate-fade-in">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-red-800 font-medium"><?= htmlspecialchars($error_message, ENT_QUOTES) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Update Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8 animate-fade-in">

            <!-- Current screenshot preview (if exists) -->
            <?php
                $current_image_basename = !empty($order['payment_screenshot']) ? basename($order['payment_screenshot']) : '';
                $current_image_web = '';
                if (!empty($current_image_basename)) {
                    $current_image_web = $UPLOAD_WEB_REL . $current_image_basename;
                }
            ?>
            <?php if ($current_image_web && file_exists($UPLOAD_DIR_FS . $current_image_basename)): ?>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Current Screenshot</label>
                    <a href="<?= htmlspecialchars($current_image_web, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                        <img src="<?= htmlspecialchars($current_image_web, ENT_QUOTES) ?>" alt="Current screenshot" class="max-h-48 rounded-lg shadow-sm mt-2">
                    </a>
                    <p class="text-xs text-gray-500 mt-1">Click image to open full size.</p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Payment Screenshot Upload -->
                <div>
                    <label class="block text-lg font-semibold text-gray-900 mb-3">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 003-3V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Payment Screenshot *
                        </span>
                    </label>
                    
                    <div class="file-upload-area rounded-xl p-8 text-center cursor-pointer" id="fileUploadArea">
                        <input type="file" 
                               name="payment_screenshot" 
                               id="payment_screenshot" 
                               accept="image/*" 
                               required 
                               class="hidden" 
                               onchange="handleFileSelect(this)">
                        
                        <div id="uploadPlaceholder">
                            <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Upload Payment Screenshot</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Drag and drop your payment screenshot here, or click to browse
                            </p>
                            <p class="text-xs text-gray-500">
                                Supported formats: JPG, PNG, GIF (Max 10MB) - Will be converted to WebP if supported
                            </p>
                        </div>
                        
                        <div id="filePreview" class="hidden">
                            <img id="previewImage" class="max-w-full max-h-64 mx-auto rounded-lg shadow-md mb-4" alt="Payment Screenshot Preview">
                            <p id="fileName" class="text-sm font-medium text-gray-900"></p>
                            <p class="text-xs text-gray-500 mt-1">Click to change image</p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-600 mt-2">
                        <strong>Important:</strong> Make sure your payment screenshot clearly shows the transaction details, amount, and reference number.
                    </p>
                </div>

                <!-- Reference Number (Optional) -->
                <div>
                    <label for="reference_number" class="block text-lg font-semibold text-gray-900 mb-3">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                            </svg>
                            Reference Number (Optional)
                        </span>
                    </label>
                    <input type="text" 
                           id="reference_number" 
                           name="reference_number"
                           value="<?= htmlspecialchars($order['reference_number'] ?? '', ENT_QUOTES) ?>"
                           placeholder="Enter transaction reference number"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <p class="text-sm text-gray-600 mt-2">
                        Reference number from your bank or payment method (if available)
                    </p>
                </div>

                <!-- Order Summary -->
                <div class="bg-gray-50 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Order Summary
                    </h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order ID:</span>
                            <span class="font-semibold">#<?= htmlspecialchars($order_id, ENT_QUOTES) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="font-semibold text-lg">₱<?= number_format($order['final_total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order Date:</span>
                            <span class="font-semibold"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Current Status:</span>
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded-full font-medium">Payment Rejected</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Payment Instructions
                    </h3>
                    <div class="space-y-2 text-sm text-blue-800">
                        <p>• Take a clear screenshot of your payment confirmation</p>
                        <p>• Ensure the amount (₱<?= number_format($order['final_total'], 2) ?>) is clearly visible</p>
                        <p>• Include transaction date and reference number if available</p>
                        <p>• Make sure the image is not blurry or cropped</p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6">
                    <button type="submit" 
                            class="flex-1 bg-blue-500 text-white py-4 px-6 rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-[1.02] font-semibold"
                            id="submitBtn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span id="submitText">Update Payment Information</span>
                    </button>
                    
                    <a href="profile.php" 
                       class="flex-1 sm:flex-none bg-gray-200 text-gray-700 py-4 px-6 rounded-xl hover:bg-gray-300 transition-colors flex items-center justify-center gap-2 font-semibold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // File upload functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('payment_screenshot');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');

        // Click to upload
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        });

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size must be less than 10MB');
                    input.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload only JPG, PNG, or GIF files');
                    input.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    fileName.textContent = file.name;
                    uploadPlaceholder.classList.add('hidden');
                    filePreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitText.textContent = 'Updating...';
            submitBtn.classList.add('opacity-75');
            
            // Add loading spinner
            submitBtn.querySelector('svg').outerHTML = `
                <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            `;
        });

        // Show success message if redirected with success parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('updated') === 'success') {
            showNotification('Payment updated successfully!', 'success');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

            switch (type) {
                case 'success':
                    notification.className += ' bg-green-500 text-white';
                    break;
                case 'error':
                    notification.className += ' bg-red-500 text-white';
                    break;
                default:
                    notification.className += ' bg-blue-500 text-white';
            }

            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        ${type === 'success' ? 
                            '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' :
                            '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'
                        }
                    </div>
                    <span class="font-medium">${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>

</body>
</html>