<?php
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

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = trim($_POST['business_name']);
    $business_address = trim($_POST['business_address']);
    $business_type = $_POST['business_type'];
    $country_region = trim($_POST['country_region']);
    $primary_contact_name = trim($_POST['primary_contact_name']);
    $job_title = trim($_POST['job_title']);
    $phone_number = trim($_POST['phone_number']);
    $email_address = trim($_POST['email_address']);
    
    // Validation
    $errors = [];
    
    if (empty($business_name)) {
        $errors[] = "Business name is required";
    }
    
    if (empty($business_address)) {
        $errors[] = "Business address is required";
    }
    
    if (empty($business_type)) {
        $errors[] = "Business type is required";
    }
    
    if (empty($country_region)) {
        $errors[] = "Country/Region is required";
    }
    
    if (empty($primary_contact_name)) {
        $errors[] = "Primary contact name is required";
    }
    
    if (empty($job_title)) {
        $errors[] = "Job title is required";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($email_address)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address format";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM supplier_list WHERE email_address = ?");
        $check_email->bind_param("s", $email_address);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "A supplier with this email address already exists";
        }
        $check_email->close();
    }
    
    // Handle logo upload
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/supplier_logos/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Logo must be a valid image file (jpg, jpeg, png, gif, svg)";
        } else {
            // Generate unique filename
            $logo_filename = 'supplier_' . uniqid() . '.' . $file_extension;
            $logo_path = $upload_dir . $logo_filename;
            
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                $errors[] = "Failed to upload logo";
                $logo_path = null;
            } else {
                // Store relative path for database
                $logo_path = 'uploads/supplier_logos/' . $logo_filename;
            }
        }
    }
    
    // Insert into database if no errors
    if (empty($errors)) {
        $sql = "INSERT INTO supplier_list (business_name, business_address, business_type, country_region, 
                primary_contact_name, job_title, phone_number, email_address, logo_path, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $created_by = $_SESSION['noble_user']['id'] ?? null;
        
        $stmt->bind_param("sssssssssi", 
            $business_name, 
            $business_address, 
            $business_type, 
            $country_region,
            $primary_contact_name, 
            $job_title, 
            $phone_number, 
            $email_address, 
            $logo_path, 
            $created_by
        );
        
        if ($stmt->execute()) {
            $success_message = "Supplier added successfully!";
            // Clear form data
            $_POST = [];
        } else {
            $error_message = "Error adding supplier: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get business types for dropdown
$business_types = ['Manufacturer', 'Wholesaler', 'Distributor', 'Retailer', 'Service Provider', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Supplier</title>
   
</head>
<body class="bg-gray-50">

<?php include '../navbar/top.php'; ?>
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Add New Supplier</h1>
                        <p class="mt-2 text-gray-600">Add a new supplier to your database</p>
                    </div>
                    <a href="suppliers_list.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Suppliers
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800"><?php echo $success_message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800"><?php echo $error_message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <div class="bg-white shadow rounded-lg">
                <form method="POST" enctype="multipart/form-data" class="space-y-8 p-6">
                    <!-- Business Details Section -->
                    <div class="border-b border-gray-200 pb-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-building text-blue-500 mr-2"></i>
                            Business Details
                        </h2>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Business Name -->
                            <div class="sm:col-span-2">
                                <label for="business_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Business Name *
                                </label>
                                <input type="text" 
                                       id="business_name" 
                                       name="business_name" 
                                       value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>

                            <!-- Business Address -->
                            <div class="sm:col-span-2">
                                <label for="business_address" class="block text-sm font-medium text-gray-700 mb-1">
                                    Business Address *
                                </label>
                                <textarea id="business_address" 
                                          name="business_address" 
                                          rows="3" 
                                          required
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border"><?php echo htmlspecialchars($_POST['business_address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Business Type -->
                            <div>
                                <label for="business_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    Business Type *
                                </label>
                                <select id="business_type" 
                                        name="business_type" 
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select Business Type</option>
                                    <?php foreach ($business_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo (($_POST['business_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Country/Region -->
                            <div>
                                <label for="country_region" class="block text-sm font-medium text-gray-700 mb-1">
                                    Country / Region *
                                </label>
                                <input type="text" 
                                       id="country_region" 
                                       name="country_region" 
                                       value="<?php echo htmlspecialchars($_POST['country_region'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="border-b border-gray-200 pb-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-user text-blue-500 mr-2"></i>
                            Contact Information
                        </h2>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Primary Contact Name -->
                            <div>
                                <label for="primary_contact_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Primary Contact Name *
                                </label>
                                <input type="text" 
                                       id="primary_contact_name" 
                                       name="primary_contact_name" 
                                       value="<?php echo htmlspecialchars($_POST['primary_contact_name'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>

                            <!-- Job Title -->
                            <div>
                                <label for="job_title" class="block text-sm font-medium text-gray-700 mb-1">
                                    Job Title *
                                </label>
                                <input type="text" 
                                       id="job_title" 
                                       name="job_title" 
                                       value="<?php echo htmlspecialchars($_POST['job_title'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Number *
                                </label>
                                <input type="tel" 
                                       id="phone_number" 
                                       name="phone_number" 
                                       value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>

                            <!-- Email Address -->
                            <div>
                                <label for="email_address" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address *
                                </label>
                                <input type="email" 
                                       id="email_address" 
                                       name="email_address" 
                                       value="<?php echo htmlspecialchars($_POST['email_address'] ?? ''); ?>"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                            </div>
                        </div>
                    </div>

                    <!-- Logo Section -->
                    <div class="pb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-image text-blue-500 mr-2"></i>
                            Logo (Optional)
                        </h2>
                        
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <i class="fas fa-cloud-upload-alt mx-auto text-gray-400 text-3xl"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="logo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload a file</span>
                                        <input id="logo" name="logo" type="file" accept="image/*" class="sr-only">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF, SVG up to 10MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 pt-6">
                        <button type="button" 
                                onclick="window.location.href='suppliers_list.php'"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Add Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('File selected:', file.name);
                };
                reader.readAsDataURL(file);
            }
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>