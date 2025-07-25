<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['supplier', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time();

$tables = ['supplier_products', 'supplier_product_variants', 'supplier_variant_sizes'];

foreach ($tables as $table) {
    // Kunin ang current max id
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    if ($max_id > 0) {
        // Check if the max_id row exists
        $result2 = $conn->query("SELECT COUNT(*) AS count FROM $table WHERE id = $max_id");
        $row2 = $result2->fetch_assoc();

        if ((int)$row2['count'] === 0) {
            // Reset AUTO_INCREMENT to max_id
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $max_id");
        }
    } else {
        // Walang laman ang table, reset to 1
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

define('MAX_FILE_SIZE', 15 * 1024 * 1024); // 15MB

function save_as_webp($file_tmp, $file_name, $targetDir = '../uploads/') {
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($ext === 'gif') return false; // ❌ Reject GIFs
    if (!getimagesize($file_tmp)) return false; // ❌ Invalid image
    if (filesize($file_tmp) > MAX_FILE_SIZE) return false; // ❌ Too big

    $new_filename = uniqid() . '.webp';
    $output_path = $targetDir . $new_filename;

    // Create uploads directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $img = imagecreatefromjpeg($file_tmp);
            break;
        case 'png':
            $img = imagecreatefrompng($file_tmp);
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            break;
        default:
            return false;
    }

    if (!$img) return false;

    imagewebp($img, $output_path, 80);
    imagedestroy($img);

    return $output_path;
}

// Get user info from session - using the correct session key for your system
$user_identifier = $_SESSION['noble_user'] ?? null;

if (!$user_identifier) {
    echo "<h3>Session Debug:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    die("❌ User ID not found in session.");
}

// Check if user_identifier is email or numeric ID
$is_email = filter_var($user_identifier, FILTER_VALIDATE_EMAIL);

// Get the correct supplier_id from nobleaccount table
if ($is_email) {
    $stmt = $conn->prepare("SELECT supplier_id, id, email FROM nobleaccount WHERE email = ?");
    $stmt->bind_param("s", $user_identifier);
} else {
    $stmt = $conn->prepare("SELECT supplier_id, id, email FROM nobleaccount WHERE id = ?");
    $stmt->bind_param("i", $user_identifier);
}

$stmt->execute();
$result = $stmt->get_result();
$account_data = $result->fetch_assoc();

$supplier_id = null;
$user_id = $account_data['id'] ?? null;

if ($account_data && $account_data['supplier_id']) {
    $supplier_id = $account_data['supplier_id'];
} else {
    echo "<h3>Account Debug:</h3>";
    echo "<pre>";
    echo "User Identifier: " . $user_identifier . "\n";
    echo "Is Email: " . ($is_email ? 'Yes' : 'No') . "\n";
    echo "Account Data: ";
    print_r($account_data);
    echo "</pre>";
    
    echo "<h3>Available Supplier IDs:</h3>";
    $all_suppliers = $conn->prepare("SELECT supplier_id, email FROM nobleaccount WHERE supplier_id IS NOT NULL");
    $all_suppliers->execute();
    $all_result = $all_suppliers->get_result();
    while ($row = $all_result->fetch_assoc()) {
        echo "Email: " . $row['email'] . " - Supplier ID: " . $row['supplier_id'] . "<br>";
    }
    
    echo "<h3>Your Account Info:</h3>";
    echo "Email: " . ($account_data['email'] ?? 'Not found') . "<br>";
    echo "Account ID: " . ($account_data['id'] ?? 'Not found') . "<br>";
    echo "Supplier ID: " . ($account_data['supplier_id'] ?? 'Not set') . "<br>";
    
    die("❌ No valid supplier_id found for your user. Please contact admin to assign you a supplier ID.");
}

if (!$supplier_id) {
    die("❌ Could not determine supplier ID.");
}

// Validate POST data
if (empty($_POST['item_code']) || empty($_POST['product_name']) || empty($_POST['description'])) {
    die("❌ Missing required product information.");
}

// Upload main image
$main_image = '';
if (!empty($_FILES['main_image']['name'])) {
    $main_image = save_as_webp($_FILES['main_image']['tmp_name'], $_FILES['main_image']['name']);
    if (!$main_image) {
        die("❌ Invalid main image. Only JPG/PNG (max 15MB) allowed. GIF not allowed.");
    }
}

try {
    // Start transaction
    $conn->autocommit(false);

    // Insert product info
    $stmt = $conn->prepare("INSERT INTO supplier_products 
        (supplier_id, item_code, product_name, description, category, image) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    // Ensure all variables are properly initialized
    $category = $_POST['category'] ?? '';
    
    $stmt->bind_param("isssss", 
        $supplier_id, 
        $_POST['item_code'], 
        $_POST['product_name'], 
        $_POST['description'], 
        $category, 
        $main_image
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert product: " . $stmt->error);
    }
    
    $product_id = $stmt->insert_id;

    // Process variants
    if (
        isset($_POST['color'], $_POST['price']) &&
        is_array($_POST['color']) && is_array($_POST['price'])
    ) {
        $color_arr = $_POST['color'];
        $price_arr = $_POST['price'];
        
        // Size and stock are optional
        $size_arr = isset($_POST['size']) && is_array($_POST['size']) ? $_POST['size'] : [];
        $stock_arr = isset($_POST['stock']) && is_array($_POST['stock']) ? $_POST['stock'] : [];

        // Validate that required arrays have the same length
        $count = count($color_arr);
        if ($count !== count($price_arr)) {
            throw new Exception("Color and price arrays have mismatched lengths.");
        }
        
        // Ensure optional arrays match the count or are empty
        if (!empty($size_arr) && count($size_arr) !== $count) {
            throw new Exception("Size array length doesn't match color/price arrays.");
        }
        if (!empty($stock_arr) && count($stock_arr) !== $count) {
            throw new Exception("Stock array length doesn't match color/price arrays.");
        }

        for ($i = 0; $i < $count; $i++) {
            $color = trim($color_arr[$i]);
            $price = floatval($price_arr[$i]);
            
            // Get size and stock if available
            $size = !empty($size_arr[$i]) ? trim($size_arr[$i]) : '';
            $stock = !empty($stock_arr[$i]) ? intval($stock_arr[$i]) : 0;

            // Skip empty variants
            if (empty($color)) {
                continue;
            }

            // Initialize variant_image as empty string
            $variant_image = '';
            
            if (!empty($_FILES['variant_image']['name'][$i])) {
                $tmp_name = $_FILES['variant_image']['tmp_name'][$i];
                $original_name = $_FILES['variant_image']['name'][$i];

                $uploaded_path = save_as_webp($tmp_name, $original_name);
                if (!$uploaded_path) {
                    throw new Exception("Invalid variant image at index $i. Only JPG/PNG under 15MB allowed. GIFs not allowed.");
                }
                $variant_image = $uploaded_path;
            }

            // Insert into supplier_product_variants (product_id, color, price, image)
            $vstmt = $conn->prepare("INSERT INTO supplier_product_variants 
                (product_id, color, price, image) 
                VALUES (?, ?, ?, ?)");
            
            $vstmt->bind_param("isds", 
                $product_id, $color, $price, $variant_image);
            
            if (!$vstmt->execute()) {
                throw new Exception("Failed to insert variant $i: " . $vstmt->error);
            }
            
            $variant_id = $vstmt->insert_id;
            $vstmt->close();
            
            // If size and stock are provided, insert into supplier_variant_sizes
            if (!empty($size) && $stock > 0) {
                $size_stmt = $conn->prepare("INSERT INTO supplier_variant_sizes 
                    (variant_id, size, stock) 
                    VALUES (?, ?, ?)");
                
                $size_stmt->bind_param("isi", $variant_id, $size, $stock);
                
                if (!$size_stmt->execute()) {
                    throw new Exception("Failed to insert size for variant $i: " . $size_stmt->error);
                }
                $size_stmt->close();
            }
        }
    } else {
        throw new Exception("Missing or invalid variant data.");
    }

    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);

    // Success message
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Upload Success</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100'>
        <div class='max-w-2xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md text-center'>
            <div class='text-green-600 text-6xl mb-4'>✅</div>
            <h2 class='text-2xl font-bold text-green-600 mb-4'>Success!</h2>
            <p class='text-gray-700 mb-6'>Product and variants uploaded successfully!</p>
            <div class='space-x-4'>
                <a href='upload_form.php' class='inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded'>
                    Upload Another Product
                </a>
                <a href='view_products.php' class='inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded'>
                    View Products
                </a>
            </div>
        </div>
    </body>
    </html>";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $conn->autocommit(true);
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Upload Error</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100'>
        <div class='max-w-2xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md text-center'>
            <div class='text-red-600 text-6xl mb-4'>❌</div>
            <h2 class='text-2xl font-bold text-red-600 mb-4'>Upload Failed</h2>
            <p class='text-gray-700 mb-6'>" . htmlspecialchars($e->getMessage()) . "</p>
            <a href='upload_form.php' class='inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded'>
                Try Again
            </a>
        </div>
    </body>
    </html>";
}
?>