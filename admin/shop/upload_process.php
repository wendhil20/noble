<?php
//upload_process.php
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

function saveImageToFolder($file, $targetDir = '../../uploads/') {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = uniqid('img_', true) . '.webp';
    $targetPath = $targetDir . $filename;
    $relativePath = 'uploads/' . $filename;

    $imageType = mime_content_type($file['tmp_name']);
    $sourceImage = null;

    switch ($imageType) {
        case 'image/jpeg':
        case 'image/jpg':
            $sourceImage = imagecreatefromjpeg($file['tmp_name']);
            break;

        case 'image/png':
            $sourceImage = imagecreatefrompng($file['tmp_name']);
            imagepalettetotruecolor($sourceImage); // Make true color for WebP
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;

        case 'image/gif':
            $sourceImage = imagecreatefromgif($file['tmp_name']);
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;

        case 'image/webp':
            // Already WebP, move directly
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $relativePath;
            }
            return null;

        default:
            return null; // Unsupported format
    }

    if ($sourceImage && imagewebp($sourceImage, $targetPath, 80)) {
        imagedestroy($sourceImage);
        return $relativePath;
    }

    return null;
}

function saveSubImages($subImagesFiles, $targetDir = '../../sub_images/') {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $subImagePaths = [];
    
    if (isset($subImagesFiles['name']) && is_array($subImagesFiles['name'])) {
        $totalSubImages = count($subImagesFiles['name']);
        
        for ($i = 0; $i < $totalSubImages; $i++) {
            // Skip empty files
            if (empty($subImagesFiles['name'][$i]) || $subImagesFiles['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Create file array for saveImageToFolder function
            $file = [
                'name' => $subImagesFiles['name'][$i],
                'tmp_name' => $subImagesFiles['tmp_name'][$i]
            ];
            
            // Save the sub image
            $savedPath = saveImageToFolder($file, $targetDir);
            if ($savedPath) {
                // Adjust path for sub_images folder
                $subImagePaths[] = str_replace('uploads/', '../sub_images/', $savedPath);
            }
        }
    }
    
    return $subImagePaths;
}

$tables = ['products', 'product_types', 'product_variants', 'product_colors'];

foreach ($tables as $table) {
    // Get the current highest ID that exists
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    // Reset AUTO_INCREMENT to max_id + 1
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Main product image
        $main_image = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $main_image = saveImageToFolder($_FILES['main_image']);
        }

        // Handle sub images
        $sub_images = [];
        if (isset($_FILES['sub_images'])) {
            $sub_images = saveSubImages($_FILES['sub_images']);
        }
        
        // Convert sub images array to JSON for database storage
        $sub_images_json = !empty($sub_images) ? json_encode($sub_images) : null;

        $_POST['quantity'] = (int)$_POST['quantity'];

        // Check if products table has sub_images column, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'sub_images'");
        if ($check_column->num_rows == 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sub_images TEXT NULL AFTER main_image");
        }

        // Check if product_variants table has product_id column, if not add it
        $check_variant_column = $conn->query("SHOW COLUMNS FROM product_variants LIKE 'product_id'");
        if ($check_variant_column->num_rows == 0) {
            $conn->query("ALTER TABLE product_variants ADD COLUMN product_id INT NOT NULL AFTER id");
            // Add foreign key constraint if it doesn't exist
            $conn->query("ALTER TABLE product_variants ADD CONSTRAINT FK_product_variants_product_id FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
        }

        // Insert product with sub images
        $stmt = $conn->prepare("INSERT INTO products (product_name, codename, quantity, main_image, sub_images, description) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception("Prepare failed for product insert: " . $conn->error);

        $stmt->bind_param("ssisss",
            $_POST['product_name'],
            $_POST['codename'],
            $_POST['quantity'],
            $main_image,
            $sub_images_json,
            $_POST['description']
        );
        $stmt->execute();
        $product_id = $conn->insert_id;
        $stmt->close();

        // Log sub images info for debugging
        if (!empty($sub_images)) {
            error_log("Sub images saved for product ID $product_id: " . implode(', ', $sub_images));
        }

        // Product types
        if (isset($_POST['type_name']) && is_array($_POST['type_name'])) {
            foreach ($_POST['type_name'] as $type_index => $type_name) {
                // Type image
                $type_image = null;
                if (isset($_FILES['type_image']['tmp_name'][$type_index]) &&
                    $_FILES['type_image']['error'][$type_index] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['type_image']['name'][$type_index],
                        'tmp_name' => $_FILES['type_image']['tmp_name'][$type_index]
                    ];
                    $type_image = saveImageToFolder($file);
                }

                $stmt = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed for product_types: " . $conn->error);
                $stmt->bind_param("iss", $product_id, $type_name, $type_image);
                $stmt->execute();
                $type_id = $stmt->insert_id;
                $stmt->close();

                // Product colors
                if (isset($_POST['color_name'][$type_index]) && is_array($_POST['color_name'][$type_index])) {
                    foreach ($_POST['color_name'][$type_index] as $color_index => $color_name) {
                        if (!empty($color_name)) {
                            $color_code = $_POST['color_code'][$type_index][$color_index] ?? '';
                            $color_price = (float)($_POST['color_price'][$type_index][$color_index] ?? 0);
                            $color_image = null;

                            if (isset($_FILES['color_image']['tmp_name'][$type_index][$color_index]) &&
                                $_FILES['color_image']['error'][$type_index][$color_index] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['color_image']['name'][$type_index][$color_index],
                                    'tmp_name' => $_FILES['color_image']['tmp_name'][$type_index][$color_index]
                                ];
                                $color_image = saveImageToFolder($file);
                            }

                            $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image) VALUES (?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Prepare failed for product_colors: " . $conn->error);
                            $stmt->bind_param("issds", $product_id, $color_name, $color_code, $color_price, $color_image);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                // Product variants - NOW WITH PRODUCT_ID
                if (isset($_POST['variant_size'][$type_index]) && is_array($_POST['variant_size'][$type_index])) {
                    foreach ($_POST['variant_size'][$type_index] as $variant_index => $size) {
                        $name_variant = $_POST['variant_namevariant'][$type_index][$variant_index] ?? '';
                        $color = $_POST['variant_color'][$type_index][$variant_index] ?? '';
                        $original_price = (float)($_POST['variant_original_price'][$type_index][$variant_index] ?? 0);
                        $price = (float)($_POST['variant_price'][$type_index][$variant_index] ?? 0);
                        $percent = (float)($_POST['variant_percent'][$type_index][$variant_index] ?? 0);
                        $discount = (float)($_POST['variant_discount'][$type_index][$variant_index] ?? 0);

                        if (!empty($size) || !empty($name_variant)) {
                            $final_price = $price + ($price * $percent / 100);
                            $variant_image = null;

                            if (isset($_FILES['variant_image']['tmp_name'][$type_index][$variant_index]) &&
                                $_FILES['variant_image']['error'][$type_index][$variant_index] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['variant_image']['name'][$type_index][$variant_index],
                                    'tmp_name' => $_FILES['variant_image']['tmp_name'][$type_index][$variant_index]
                                ];
                                $variant_image = saveImageToFolder($file);
                            }

                            // FIXED: Now includes product_id in the insert
                            $stmt = $conn->prepare("INSERT INTO product_variants (product_id, type_id, color, size, original_price, price, percent, discount, namevariant, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Prepare failed for product_variants: " . $conn->error);
                            $stmt->bind_param("iissddddss",
                                $product_id,      // ADD THIS - the missing product_id
                                $type_id,
                                $color,
                                $size,
                                $original_price,
                                $final_price,
                                $percent,
                                $discount,
                                $name_variant,
                                $variant_image
                            );
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }

        $conn->commit();
        
        // Success message with sub images count
        $sub_images_count = count($sub_images);
        $success_message = "Product uploaded successfully!";
        if ($sub_images_count > 0) {
            $success_message .= " ($sub_images_count sub images included)";
        }
        
        echo "<script>
            alert('$success_message'); 
            window.location.href='adminshop.php';
        </script>";
        
    } catch (Exception $e) {
        $conn->rollback();
        
        // Enhanced error message
        $error_message = "Error uploading product: " . $e->getMessage();
        error_log("Product upload error: " . $e->getMessage());
        
        echo "<script>
            alert('" . addslashes($error_message) . "'); 
            history.back();
        </script>";
    }
} else {
    header("Location: adminshop.php");
    exit();
}
?>