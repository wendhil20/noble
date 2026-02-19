<?php
// manage_clients.php - Client Management for Sales
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$message = "";
$error = "";

// Handle form submission to add new client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $company_name = trim($_POST['company_name']);
    $company_address = trim($_POST['company_address']);
    $logo_path = null;
    
    // Validate required fields
    if (empty($company_name) || empty($company_address)) {
        $error = "Company name and address are required!";
    } else {
        // Handle logo upload (optional)
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/client_logos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                $full_path = $upload_dir . $new_filename;
                $logo_path = '../../uploads/client_logos/' . $new_filename;
                
                if (!move_uploaded_file($_FILES['company_logo']['tmp_name'], $full_path)) {
                    $error = "Failed to upload logo.";
                    $logo_path = null;
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            }
        }
        
        // Insert into database if no errors
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO companies (sales_user_id, company_name, company_address, logo_path, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $company_name, $company_address, $logo_path);
            
            if ($stmt->execute()) {
                $message = "Company profile added successfully!";
            } else {
                $error = "Failed to add company profile. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Fetch all companies (shared across all sales)
$companies = [];
$stmt = $conn->prepare("SELECT c.id, c.company_name, c.company_address, c.logo_path, c.created_at, c.sales_user_id, n.fullname as added_by FROM companies c LEFT JOIN nobleaccount n ON c.sales_user_id = n.id ORDER BY c.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Company Profile - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <!-- Mobile Layout -->
                <div class="flex flex-col space-y-3 sm:hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-2 rounded-lg">
                                <i class="fas fa-building text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-900">My Clients</h1>
                                <p class="text-xs text-gray-600">Manage companies</p>
                            </div>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Desktop Layout -->
                <div class="hidden sm:flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-lg">
                            <i class="fas fa-building text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Add Company Profile</h1>
                            <p class="text-gray-600 mt-1">Create a new company profile</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900">
                                <i class="fas fa-user text-primary-600 mr-1"></i>
                                <?php echo htmlspecialchars($fullname); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-shield-alt mr-1"></i>
                                <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-lg">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 mb-6 flex items-center animate-pulse">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Add New Client Form -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-plus-circle mr-2 sm:mr-3"></i>
                    Add New Profile
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    
                    <!-- Company Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building mr-1 text-blue-600"></i>Company Name *
                        </label>
                        <input type="text" name="company_name" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter company name">
                    </div>

                    <!-- Company Address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-blue-600"></i>Company Address *
                        </label>
                        <textarea name="company_address" required rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter company address"></textarea>
                    </div>

                    <!-- Company Logo (Optional) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-image mr-1 text-blue-600"></i>Company Logo (Optional)
                        </label>
                        <input type="file" name="company_logo" accept="image/*"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, GIF</p>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" name="add_client"
                            class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Client</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showNotification(message, type) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>