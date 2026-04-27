<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
    exit;
}

// Get parameters from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if (!$order_id || !$item_id) {
    header('Location: ' . BASE_URL . '/profile');
    exit;
}

$user_email = $_SESSION['user_email'] ?? null;

// Verify that the order belongs to the user and get item details
$stmt = $conn->prepare("
    SELECT o.id, o.customer_name, o.created_at, o.status as order_status,
           oi.id as item_id, oi.product_name, oi.variant_color, oi.size, 
           oi.quantity, oi.subtotal, oi.tracking_status, oi.origin
    FROM orders o 
    INNER JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.id = ? AND oi.id = ? AND o.email = ?
");
$stmt->bind_param("iis", $order_id, $item_id, $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/profile');
    exit;
}

$item_data = $result->fetch_assoc();
$stmt->close();

// Check if replacement request already exists
$stmt = $conn->prepare("SELECT * FROM replacement_requests WHERE order_item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$existing_request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing_request) {
    $error_message = "A replacement request already exists for this item.";
}

// Updated eligibility check - only requires item to be delivered
function isEligibleForReplacement($item) {
    $item_status = strtolower($item['tracking_status'] ?? 'processing');
    
    // Check if item is delivered - that's the only requirement
    return $item_status === 'delivered';
}

// Check eligibility
$is_eligible = isEligibleForReplacement($item_data);

if (!$is_eligible) {
    $error_message = "This item is not eligible for replacement. Items must be delivered before requesting a replacement.";
}

// Handle form submission
$success_message = '';
$form_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_request && $is_eligible) {
    $replacement_quantity = intval($_POST['replacement_quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $details = trim($_POST['details'] ?? '');
    
    // Validate form data
    if ($replacement_quantity <= 0 || $replacement_quantity > $item_data['quantity']) {
        $form_errors[] = "Invalid replacement quantity.";
    }
    
    if (empty($reason)) {
        $form_errors[] = "Please select a reason for replacement.";
    }

    // Handle file uploads - REQUIRE EXACTLY 3 IMAGES
    $uploaded_files = [];
    if (!isset($_FILES['defect_images']) || !is_array($_FILES['defect_images']['name']) || count($_FILES['defect_images']['name']) !== 3) {
        $form_errors[] = "You must upload exactly 3 images showing the defect/issue.";
    } else {
        $upload_dir = '../../uploads/defect_proof/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $form_errors[] = "Failed to create upload directory.";
            }
        }
        
        if (empty($form_errors)) {
            $valid_uploads = 0;
            
            for ($i = 0; $i < 3; $i++) {
                if ($_FILES['defect_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['defect_images']['tmp_name'][$i];
                    $file_name = $_FILES['defect_images']['name'][$i];
                    $file_size = $_FILES['defect_images']['size'][$i];
                    
                    // Validate file
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = mime_content_type($file_tmp);
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $form_errors[] = "Invalid file type for image " . ($i + 1) . ": $file_name. Only JPEG, PNG, GIF, and WEBP are allowed.";
                        continue;
                    }
                    
                    if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                        $form_errors[] = "Image " . ($i + 1) . " is too large: $file_name. Maximum size is 5MB.";
                        continue;
                    }
                    
                    // Generate unique filename with specific naming
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $image_labels = ['overview', 'closeup', 'detail']; // Specific labels for each image
                    $new_filename = 'defect_' . $order_id . '_' . $item_id . '_' . $image_labels[$i] . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $uploaded_files[$image_labels[$i]] = $new_filename;
                        $valid_uploads++;
                    } else {
                        $form_errors[] = "Failed to upload image " . ($i + 1) . ": $file_name";
                    }
                } else {
                    $form_errors[] = "Error uploading image " . ($i + 1) . ". Please try again.";
                }
            }
            
            // Check if all 3 images were uploaded successfully
            if ($valid_uploads !== 3) {
                $form_errors[] = "All 3 images must be uploaded successfully. Please try again.";
            }
        }
    }
    
    // If no errors, insert replacement request
    if (empty($form_errors)) {
        try {
            $conn->autocommit(false);
            
            $stmt = $conn->prepare("
                INSERT INTO replacement_requests 
                (order_id, order_item_id, user_email, reason, details, replacement_quantity, 
                 defect_image_overview, defect_image_closeup, defect_image_detail, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->bind_param("iisssisss", 
                $order_id, 
                $item_id, 
                $user_email, 
                $reason, 
                $details, 
                $replacement_quantity, 
                $uploaded_files['overview'],
                $uploaded_files['closeup'],
                $uploaded_files['detail']
            );
            
            if ($stmt->execute()) {
                $conn->commit();
                $success_message = "Replacement request submitted successfully! We will review your request and the provided images, then get back to you within 1-2 business days.";
            } else {
                $conn->rollback();
                $form_errors[] = "Failed to submit replacement request.";
            }
            
            $stmt->close();
            $conn->autocommit(true);
            
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(true);
            $form_errors[] = "An error occurred while processing your request.";
            error_log("Replacement request error: " . $e->getMessage());
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
    <title>Replacement Request - Order #<?= $order_id ?></title>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .animate-slide-in {
            animation: slideInUp 0.6s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f9fafb;
        }

        .file-drop-area:hover,
        .file-drop-area.drag-over {
            border-color: #FF6B35;
            background-color: #fef7f0;
        }

        .file-drop-area.has-files {
            border-color: #10B981;
            background-color: #f0fdf4;
        }

        .image-preview {
            max-width: 120px;
            max-height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .image-slot {
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .image-slot:hover {
            border-color: #FF6B35;
            background-color: #fef7f0;
        }

        .image-slot.filled {
            border-color: #10B981;
            background-color: #f0fdf4;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-btn {
            background: #f9fafb;
            border: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background: #e5e7eb;
        }

        .quantity-input {
            border: none;
            text-align: center;
            width: 60px;
            height: 40px;
            font-weight: 600;
        }

        .required-badge {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-poppins">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>

    <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-8 max-w-4xl">
        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <div class="flex items-center gap-3 sm:gap-4 mb-4">
                <a href="order_tracking.php?order_id=<?= $order_id ?>" class="flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Replacement Request</h1>
                    <p class="text-sm sm:text-base text-gray-600">Order #<?= $order_id ?></p>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <!-- Error Message -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 animate-slide-in">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-red-800">Cannot Process Request</h4>
                    <p class="text-xs text-red-700"><?= htmlspecialchars($error_message) ?></p>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Success Message -->
        <?php if ($success_message): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 animate-slide-in">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-green-800">Request Submitted</h4>
                    <p class="text-xs text-green-700"><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Errors -->
        <?php if (!empty($form_errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 animate-slide-in">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-red-800 mb-2">Please fix the following errors:</h4>
                    <ul class="text-xs text-red-700 space-y-1">
                        <?php foreach ($form_errors as $error): ?>
                        <li>• <?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Item Details Card -->
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">Item Details</h3>
            
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 text-base sm:text-lg"><?= htmlspecialchars($item_data['product_name']) ?></h4>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 mt-1">
                        <span>Color: <?= htmlspecialchars($item_data['variant_color']) ?></span>
                        <span>Size: <?= htmlspecialchars($item_data['size']) ?></span>
                        <span>Ordered Quantity: <?= $item_data['quantity'] ?></span>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                            <?= strtolower($item_data['origin']) === 'local' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                            <?= strtolower($item_data['origin']) === 'local' ? '🏠 Local' : '🌏 International' ?>
                        </span>
                    </div>
                </div>
                
                <div class="text-right">
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">₱<?= number_format($item_data['subtotal'], 2) ?></p>
                    <p class="text-sm text-gray-500">Total paid</p>
                </div>
            </div>
        </div>

        <!-- Replacement Form -->
        <?php if (!$success_message): ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-6" id="replacementForm">
            <!-- Replacement Quantity -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Replacement Quantity</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            How many items need replacement? (Max: <?= $item_data['quantity'] ?>)
                        </label>
                        
                        <div class="quantity-selector w-fit">
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                </svg>
                            </button>
                            <input type="number" 
                                   name="replacement_quantity" 
                                   id="replacement_quantity" 
                                   class="quantity-input" 
                                   min="1" 
                                   max="<?= $item_data['quantity'] ?>" 
                                   value="1" 
                                   required>
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reason and Details -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Replacement Reason</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for replacement *</label>
                        <select name="reason" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select a reason</option>
                            <option value="defective">Item is defective</option>
                            <option value="damaged">Item was damaged during shipping</option>
                            <option value="wrong_item">Wrong item received</option>
                            <option value="wrong_size">Wrong size</option>
                            <option value="not_as_described">Item not as described</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional details</label>
                        <textarea name="details" 
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" 
                                  rows="4" 
                                  placeholder="Please provide more details about the issue..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Image Upload - EXACTLY 3 REQUIRED -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
                <div class="flex items-center gap-3 mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Upload Evidence Images</h3>
                    <span class="required-badge">3 Required</span>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-yellow-800 mb-1">Important: 3 Images Required</h4>
                                <p class="text-sm text-yellow-700">You must upload exactly 3 clear photos to process your replacement request:</p>
                                <ul class="text-sm text-yellow-700 mt-2 space-y-1">
                                    <li><strong>1. Overall view:</strong> Full product showing the defect/issue</li>
                                    <li><strong>2. Close-up:</strong> Detailed view of the specific problem area</li>
                                    <li><strong>3. Additional detail:</strong> Another angle or supporting evidence</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Three Image Slots -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Image Slot 1 -->
                        <div class="image-slot" onclick="document.getElementById('image1').click()" id="slot1">
                            <div class="text-center" id="slot1-content">
                                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600 mb-1">Image 1: Overall View</p>
                                <p class="text-xs text-gray-500">Click to upload</p>
                            </div>
                            <input type="file" id="image1" name="defect_images[]" accept="image/*" class="hidden" onchange="handleImageUpload(1, this.files[0])">
                        </div>

                        <!-- Image Slot 2 -->
                        <div class="image-slot" onclick="document.getElementById('image2').click()" id="slot2">
                            <div class="text-center" id="slot2-content">
                                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600 mb-1">Image 2: Close-up</p>
                                <p class="text-xs text-gray-500">Click to upload</p>
                            </div>
                            <input type="file" id="image2" name="defect_images[]" accept="image/*" class="hidden" onchange="handleImageUpload(2, this.files[0])">
                        </div>

                        <!-- Image Slot 3 -->
                        <div class="image-slot" onclick="document.getElementById('image3').click()" id="slot3">
                            <div class="text-center" id="slot3-content">
                                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600 mb-1">Image 3: Detail</p>
                                <p class="text-xs text-gray-500">Click to upload</p>
                            </div>
                            <input type="file" id="image3" name="defect_images[]" accept="image/*" class="hidden" onchange="handleImageUpload(3, this.files[0])">
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div class="flex items-center justify-center gap-2" id="uploadProgress">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-gray-300" id="progress1"></div>
                            <div class="w-3 h-3 rounded-full bg-gray-300" id="progress2"></div>
                            <div class="w-3 h-3 rounded-full bg-gray-300" id="progress3"></div>
                        </div>
                        <span class="text-sm text-gray-600" id="progressText">0 of 3 images uploaded</span>
                    </div>

                    <p class="text-xs text-gray-500 text-center">
                        Accepted formats: PNG, JPG, GIF, WEBP • Maximum size: 5MB each
                    </p>
                </div>
            </div>

            <!-- Policy Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 sm:p-6 animate-slide-in">
                <h4 class="font-semibold text-blue-800 mb-2">Replacement Policy</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Items can be replaced within 7 days of delivery</li>
                    <li>• Replacement processing time: 1-2 days with proper documentation</li>
                    <li>• Original items must be returned in sellable condition</li>
                    <li>• Free return shipping for defective items</li>
                    <li>• 3 clear photos are required to verify the defect/issue</li>
                    <li>• You will receive email updates on your replacement status</li>
                </ul>
            </div>

            <!-- Submit Button -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                <a href="order_tracking.php?order_id=<?= $order_id ?>" 
                   class="flex-1 px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors text-center font-medium">
                    Cancel
                </a>
                <button type="submit" 
                        id="submitBtn"
                        class="flex-1 px-6 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed font-medium"
                        disabled>
                    Submit Replacement Request
                </button>
            </div>
        </form>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <?php include ROOT_PATH . '/user/navbar/footer.php'?>
    <script>
        let uploadedImages = 0;
        let imageFiles = [null, null, null];

        // Quantity selector functions
        function increaseQuantity() {
            const input = document.getElementById('replacement_quantity');
            const max = parseInt(input.max);
            const current = parseInt(input.value);
            
            if (current < max) {
                input.value = current + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('replacement_quantity');
            const min = parseInt(input.min);
            const current = parseInt(input.value);
            
            if (current > min) {
                input.value = current - 1;
            }
        }

        // Image upload handling
        function handleImageUpload(slotNumber, file) {
            if (!file) return;

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const fileType = file.type.toLowerCase();
            
            if (!allowedTypes.includes(fileType)) {
                alert('Please upload a valid image file (JPEG, PNG, GIF, or WEBP)');
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('Image is too large. Maximum size is 5MB.');
                return;
            }

            // If this slot already had an image, decrease the counter
            if (imageFiles[slotNumber - 1] !== null) {
                uploadedImages--;
            }

            // Store the file
            imageFiles[slotNumber - 1] = file;
            uploadedImages++;

            // Create image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const slot = document.getElementById(`slot${slotNumber}`);
                const content = document.getElementById(`slot${slotNumber}-content`);
                
                content.innerHTML = `
                    <div class="relative">
                        <img src="${e.target.result}" alt="Preview ${slotNumber}" class="w-full h-32 object-cover rounded-lg mb-2">
                        <div class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs cursor-pointer hover:bg-red-600" onclick="removeImage(${slotNumber}); event.stopPropagation();">
                            ×
                        </div>
                    </div>
                    <p class="text-sm font-medium text-green-600">✓ Image ${slotNumber} uploaded</p>
                    <p class="text-xs text-gray-500 truncate">${file.name}</p>
                `;
                
                slot.classList.add('filled');
            };
            reader.readAsDataURL(file);

            // Update progress
            updateProgress();
        }

        function removeImage(slotNumber) {
            // Clear the file
            imageFiles[slotNumber - 1] = null;
            uploadedImages--;
            
            // Clear the file input
            document.getElementById(`image${slotNumber}`).value = '';
            
            // Reset slot appearance
            const slot = document.getElementById(`slot${slotNumber}`);
            const content = document.getElementById(`slot${slotNumber}-content`);
            const labels = ['Overall View', 'Close-up', 'Detail'];
            const icons = [
                'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
                'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7',
                'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ];
            
            content.innerHTML = `
                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${icons[slotNumber - 1]}"/>
                </svg>
                <p class="text-sm font-medium text-gray-600 mb-1">Image ${slotNumber}: ${labels[slotNumber - 1]}</p>
                <p class="text-xs text-gray-500">Click to upload</p>
            `;
            
            slot.classList.remove('filled');
            
            // Update progress
            updateProgress();
        }

        function updateProgress() {
            // Update progress indicators
            for (let i = 1; i <= 3; i++) {
                const progressDot = document.getElementById(`progress${i}`);
                if (imageFiles[i - 1] !== null) {
                    progressDot.classList.remove('bg-gray-300');
                    progressDot.classList.add('bg-green-500');
                } else {
                    progressDot.classList.remove('bg-green-500');
                    progressDot.classList.add('bg-gray-300');
                }
            }
            
            // Update progress text
            document.getElementById('progressText').textContent = `${uploadedImages} of 3 images uploaded`;
            
            // Update submit button
            const submitBtn = document.getElementById('submitBtn');
            if (uploadedImages === 3) {
                submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                submitBtn.classList.add('bg-primary', 'hover:bg-primary/90', 'cursor-pointer');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Replacement Request';
            } else {
                submitBtn.classList.remove('bg-primary', 'hover:bg-primary/90', 'cursor-pointer');
                submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                submitBtn.disabled = true;
                submitBtn.textContent = `Upload ${3 - uploadedImages} more image${3 - uploadedImages !== 1 ? 's' : ''}`;
            }
        }

        // Form validation
        document.getElementById('replacementForm').addEventListener('submit', function(e) {
            // Check if exactly 3 images are uploaded
            if (uploadedImages !== 3) {
                e.preventDefault();
                alert('Please upload exactly 3 images to proceed with your replacement request.');
                return;
            }

            const quantity = parseInt(document.getElementById('replacement_quantity').value);
            const reason = document.querySelector('select[name="reason"]').value;
            
            if (quantity <= 0) {
                e.preventDefault();
                alert('Please select a valid replacement quantity.');
                return;
            }
            
            if (!reason) {
                e.preventDefault();
                alert('Please select a reason for replacement.');
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = 'Processing Request...';
            submitBtn.disabled = true;
            submitBtn.classList.add('cursor-not-allowed');
        });

        // Drag and drop functionality for all slots
        document.querySelectorAll('.image-slot').forEach((slot, index) => {
            const slotNumber = index + 1;
            
            slot.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!slot.classList.contains('filled')) {
                    slot.style.borderColor = '#FF6B35';
                    slot.style.backgroundColor = '#fef7f0';
                }
            });
            
            slot.addEventListener('dragleave', function(e) {
                e.preventDefault();
                if (!slot.classList.contains('filled')) {
                    slot.style.borderColor = '#e5e7eb';
                    slot.style.backgroundColor = '#f9fafb';
                }
            });
            
            slot.addEventListener('drop', function(e) {
                e.preventDefault();
                slot.style.borderColor = '#e5e7eb';
                slot.style.backgroundColor = '#f9fafb';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById(`image${slotNumber}`).files = files;
                    handleImageUpload(slotNumber, files[0]);
                }
            });
        });

        // Animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-slide-in');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease-out';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Initialize progress
            updateProgress();
        });
    </script>
</body>
</html>