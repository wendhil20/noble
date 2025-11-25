<?php
// add_purchase_order.php - Add New Purchase Order
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

// Get company_id from URL
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id <= 0) {
    header("Location: view_companies.php");
    exit();
}

// Get company details
$stmt = $conn->prepare("SELECT company_name, company_address, logo_path FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stmt->bind_result($company_name, $company_address, $logo_path);
if (!$stmt->fetch()) {
    header("Location: view_companies.php");
    exit();
}
$stmt->close();

$error = "";

// Handle form submission to add new purchase order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_po'])) {
    $po_number = trim($_POST['po_number']);
    $po_date = trim($_POST['po_date']);
    $ship_to = trim($_POST['ship_to']);
    $target_delivery = trim($_POST['target_delivery']);
    $payment_terms = trim($_POST['payment_terms']);
    $attachment_path = null;
    
    // Validate required fields
    if (empty($po_number) || empty($po_date) || empty($ship_to) || empty($target_delivery) || empty($payment_terms)) {
        $error = "All fields are required!";
    } elseif (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $error = "File attachment is required!";
    } else {
        // Handle file attachment (required)
        $upload_dir = __DIR__ . '/../../uploads/purchase_orders/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'po_' . time() . '_' . uniqid() . '.' . $file_extension;
            $full_path = $upload_dir . $new_filename;
            $attachment_path = '../../uploads/purchase_orders/' . $new_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $full_path)) {
                $error = "Failed to upload attachment.";
                $attachment_path = null;
            }
        } else {
            $error = "Invalid file type. Only PDF, Excel, and Word documents are allowed.";
        }
        
        // Insert into database if no errors
        if (empty($error)) {
            $status = 'pending'; // Automatically set as pending
            $stmt = $conn->prepare("INSERT INTO purchase_orders (company_id, sales_user_id, po_number, po_date, ship_to, target_delivery_date, payment_terms, attachment_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisssssss", $company_id, $user_id, $po_number, $po_date, $ship_to, $target_delivery, $payment_terms, $attachment_path, $status);
            
            if ($stmt->execute()) {
                $_SESSION['po_success'] = "Purchase Order added successfully!";
                header("Location: purchase_orders.php?company_id=" . $company_id);
                exit();
            } else {
                $error = "Failed to add purchase order. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Purchase Order - <?php echo htmlspecialchars($company_name); ?></title>
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
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <!-- Back Button -->
                        <a href="purchase_orders.php?company_id=<?php echo $company_id; ?>" class="bg-gray-200 hover:bg-gray-300 p-2 rounded-lg transition">
                            <i class="fas fa-arrow-left text-gray-700"></i>
                        </a>
                        
                        <!-- Company Logo -->
                        <?php if (!empty($logo_path) && file_exists($logo_path)): ?>
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                                alt="<?php echo htmlspecialchars($company_name); ?>"
                                class="h-12 w-12 object-contain rounded-lg border border-gray-200">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-blue-600 text-xl"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">
                                Add Purchase Order
                            </h1>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($company_name); ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-medium text-gray-900">
                                <i class="fas fa-user text-primary-600 mr-1"></i>
                                <?php echo htmlspecialchars($fullname); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-shield-alt mr-1"></i>
                                <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                            </div>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm sm:text-lg">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">
        
        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Add New Purchase Order Form -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-2 sm:mr-3"></i>
                    Purchase Order Details
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Purchase Order Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-1 text-green-600"></i>Purchase Order # *
                            </label>
                            <input type="text" name="po_number" required
                                value="<?php echo isset($_POST['po_number']) ? htmlspecialchars($_POST['po_number']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Enter PO number">
                        </div>

                        <!-- Purchase Order Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-1 text-green-600"></i>Purchase Order Date *
                            </label>
                            <input type="date" name="po_date" required
                                value="<?php echo isset($_POST['po_date']) ? htmlspecialchars($_POST['po_date']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Ship To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-shipping-fast mr-1 text-green-600"></i>Ship To: *
                        </label>
                        <textarea name="ship_to" required rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter shipping address"><?php echo isset($_POST['ship_to']) ? htmlspecialchars($_POST['ship_to']) : ''; ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Target Delivery Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-truck mr-1 text-green-600"></i>Target Delivery Date *
                            </label>
                            <input type="date" name="target_delivery" required
                                value="<?php echo isset($_POST['target_delivery']) ? htmlspecialchars($_POST['target_delivery']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>

                        <!-- Payment Terms -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Payment Terms *
                            </label>
                            <input type="text" name="payment_terms" required
                                value="<?php echo isset($_POST['payment_terms']) ? htmlspecialchars($_POST['payment_terms']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="e.g., Net 30, COD, 50% deposit">
                        </div>
                    </div>

                    <!-- Attachment (Required) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-paperclip mr-1 text-green-600"></i>Attachment (Required) *
                        </label>
                        <input type="file" name="attachment" required accept=".pdf,.xlsx,.xls,.doc,.docx"
                            class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 hover:border-green-400 transition-colors">
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            PDF, Excel, or Word documents only. Maximum file size: 10MB
                        </p>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                            <div>
                                <p class="text-sm text-blue-800 font-medium">Status Information</p>
                                <p class="text-xs text-blue-700 mt-1">
                                    This purchase order will be automatically set to <strong>"Pending"</strong> status upon creation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex gap-3 pt-4">
                        <a href="purchase_orders.php?company_id=<?php echo $company_id; ?>"
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg transition-colors duration-200 font-medium text-center">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" name="add_po"
                            class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center justify-center space-x-2 font-medium">
                            <i class="fas fa-check"></i>
                            <span>Submit Purchase Order</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>