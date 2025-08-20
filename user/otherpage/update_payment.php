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
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND email = ? AND payment_status = 'rejected'");
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_number = $_POST['reference_number'] ?? null;
    $upload_success = false;
    $payment_screenshot = null;
    
    // Get current payment screenshot to delete later
    $old_screenshot = $order['payment_screenshot'] ?? null;
    
    // Handle file upload
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/payment_screenshots/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['payment_screenshot']['tmp_name'];
        $file_type = $_FILES['payment_screenshot']['type'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file_type, $allowed_types)) {
            // Generate unique filename
            $filename = 'payment_' . $order_id . '_' . time() . '.webp';
            $upload_path = $upload_dir . $filename;
            
            // Convert to WebP format
            try {
                switch ($file_type) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $image = imagecreatefromjpeg($file_tmp);
                        break;
                    case 'image/png':
                        $image = imagecreatefrompng($file_tmp);
                        break;
                    case 'image/gif':
                        $image = imagecreatefromgif($file_tmp);
                        break;
                }
                
                if ($image && imagewebp($image, $upload_path, 80)) {
                    $payment_screenshot = 'uploads/payment_screenshots/' . $filename;
                    $upload_success = true;
                    imagedestroy($image);
                    
                    // Delete old payment screenshot if it exists
                    if ($old_screenshot && file_exists('../../' . $old_screenshot)) {
                        unlink('../../' . $old_screenshot);
                    }
                }
            } catch (Exception $e) {
                $error_message = "Error converting image to WebP format.";
            }
        } else {
            $error_message = "Invalid file type. Please upload JPG, PNG, or GIF files only.";
        }
    } else {
        $error_message = "Please upload a payment screenshot.";
    }
    
    // Update database if upload was successful
    if ($upload_success) {
        try {
            $conn->autocommit(false); // Start transaction
            
            // Update orders table
            $update_query = "UPDATE orders SET 
                            payment_status = 'pending', 
                            payment_screenshot = ?, 
                            reference_number = ?, 
                            rejected_by = NULL, 
                            updated_at = NOW() 
                            WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssi", $payment_screenshot, $reference_number, $order_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success_message = "Payment information updated successfully! Your payment is now pending review.";
                
                // Redirect after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'profile.php?updated=success';
                    }, 2000);
                </script>";
            } else {
                $conn->rollback();
                $error_message = "Failed to update payment information. Please try again.";
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Database error occurred. Please try again.";
        }
        
        $conn->autocommit(true);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Payment - Order #<?= $order_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <p class="text-gray-600">Order #<?= $order_id ?> - ₱<?= number_format($order['final_total'], 2) ?></p>
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
        <?php if (isset($success_message)): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 animate-fade-in">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-green-800 font-medium"><?= $success_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 animate-fade-in">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-red-800 font-medium"><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Update Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8 animate-fade-in">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Payment Screenshot Upload -->
                <div>
                    <label class="block text-lg font-semibold text-gray-900 mb-3">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
                                Supported formats: JPG, PNG, GIF (Max 10MB) - Will be converted to WebP
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
                           value="<?= htmlspecialchars($order['reference_number'] ?? '') ?>"
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
                            <span class="font-semibold">#<?= $order_id ?></span>
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
                            class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-[1.02] font-semibold"
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