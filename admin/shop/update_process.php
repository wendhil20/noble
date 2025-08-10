<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get product ID
$product_id = $_POST['product_id'] ?? null;

if (!$product_id) {
    echo "Missing product ID.";
    exit;
}

// Function to save image as WebP (consistent with insertion)
function saveImageAsWebP($tmp_name, $target_path) {
    $imageType = mime_content_type($tmp_name);
    $sourceImage = null;
    
    switch ($imageType) {
        case 'image/jpeg':
        case 'image/jpg':
            $sourceImage = imagecreatefromjpeg($tmp_name);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($tmp_name);
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($tmp_name);
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;
        case 'image/webp':
            // Already WebP, move directly
            return move_uploaded_file($tmp_name, $target_path);
        default:
            return false; // Unsupported format
    }
    
    if ($sourceImage && imagewebp($sourceImage, $target_path, 80)) {
        imagedestroy($sourceImage);
        return true;
    }
    
    return false;
}

try {
    $conn->begin_transaction();

    // Get current product data
    $current_product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
    
    if (!$current_product) {
        throw new Exception("Product not found");
    }

    // Handle Sub-Images Update and Deletion
    $final_sub_images = [];
    
    // Process existing sub-images
    if (isset($_POST['keep_sub_image'])) {
        $keep_sub_images = $_POST['keep_sub_image'];
        
        // Get current sub-images from database
        $current_sub_images = [];
        if (!empty($current_product['sub_images'])) {
            $decoded_sub_images = json_decode($current_product['sub_images'], true);
            if (is_array($decoded_sub_images)) {
                $current_sub_images = $decoded_sub_images;
            }
        }
        
        // Process deletions and keep remaining images
        foreach ($current_sub_images as $index => $sub_image_path) {
            if (isset($keep_sub_images[$index]) && $keep_sub_images[$index] == '1') {
                // Keep this image
                $final_sub_images[] = $sub_image_path;
            } else {
                // Delete this image file from filesystem
                
                // Clean the path - remove any ../ at the beginning and normalize
                $clean_path = ltrim($sub_image_path, './');
                
                // Construct full path - since we're in admin/products/ and need to go to sub_images/
                $full_path = "../../" . $clean_path;
                
                // Actually delete the file
                if (file_exists($full_path)) {
                    unlink($full_path);
                    echo "Deleted sub-image: " . basename($full_path) . "<br>";
                }
            }
        }
    }
    
    // Handle new sub-images upload
    if (isset($_FILES['new_sub_images']) && !empty($_FILES['new_sub_images']['name'][0])) {
        $upload_dir = "../../sub_images/"; // Keep consistent with insertion
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['new_sub_images']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name) && $_FILES['new_sub_images']['error'][$key] == 0) {
                $file_name = $_FILES['new_sub_images']['name'][$key];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                
                // Validate image
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    continue;
                }
                
                // Generate unique filename - match the insertion pattern
                $new_filename = 'img_' . uniqid('', true) . '.webp';
                $target_path = $upload_dir . $new_filename;
                
                // Convert and save as WebP (consistent with insertion)
                if (saveImageAsWebP($tmp_name, $target_path)) {
                    // Store relative path for database (consistent with insertion)
                    $final_sub_images[] = "../sub_images/" . $new_filename;
                    echo "Uploaded new sub-image: " . $new_filename . "<br>";
                }
            }
        }
    }

    // Handle Main Image Upload
    $main_image_path = $current_product['main_image']; // Keep current if no new upload
    
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        $upload_dir = "../../uploads/";
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['main_image']['name'];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        
        // Validate image
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $new_filename = uniqid('img_', true) . '.webp';
            $target_path = $upload_dir . $new_filename;
            
            // Convert and save as WebP
            if (saveImageAsWebP($_FILES['main_image']['tmp_name'], $target_path)) {
                // Delete old main image if it exists
                if (!empty($current_product['main_image']) && file_exists("../../" . $current_product['main_image'])) {
                    unlink("../../" . $current_product['main_image']);
                }
                
                $main_image_path = "uploads/" . $new_filename;
                echo "Uploaded new main image: " . $new_filename . "<br>";
            }
        }
    }

    // Update Product Basic Info
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $codename = $conn->real_escape_string($_POST['codename']);
    $quantity = intval($_POST['quantity']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $sub_images_json = $conn->real_escape_string(json_encode($final_sub_images));

    $update_product_sql = "UPDATE products SET 
        product_name = '$product_name',
        codename = '$codename',
        quantity = $quantity,
        description = '$description',
        main_image = '$main_image_path',
        sub_images = '$sub_images_json'
        WHERE id = $product_id";

    if (!$conn->query($update_product_sql)) {
        throw new Exception("Failed to update product: " . $conn->error);
    }

    // Handle Product Colors
    if (isset($_POST['color_id'])) {
        // First, handle deletions
        if (isset($_POST['delete_color'])) {
            foreach ($_POST['delete_color'] as $color_id) {
                // Get color image path before deletion
                $color_result = $conn->query("SELECT image FROM product_colors WHERE id = $color_id");
                if ($color_result && $color_row = $color_result->fetch_assoc()) {
                    if (!empty($color_row['image']) && file_exists("../../" . $color_row['image'])) {
                        unlink("../../" . $color_row['image']);
                    }
                }
                
                $conn->query("DELETE FROM product_colors WHERE id = $color_id");
                echo "Deleted color ID: $color_id<br>";
            }
        }

        // Process color updates and additions
        foreach ($_POST['color_id'] as $index => $color_id) {
            // Skip if marked for deletion
            if (isset($_POST['delete_color']) && in_array($color_id, $_POST['delete_color'])) {
                continue;
            }

            $color_name = $conn->real_escape_string($_POST['color_name'][$index]);
            $color_code = $conn->real_escape_string($_POST['color_code'][$index] ?? '');
            $color_price = floatval($_POST['color_price'][$index]);

            // Handle color image upload
            $color_image_path = '';
            
            if ($color_id !== 'new') {
                // Get existing image path
                $existing_color = $conn->query("SELECT image FROM product_colors WHERE id = $color_id")->fetch_assoc();
                $color_image_path = $existing_color['image'] ?? '';
            }

            if (isset($_FILES['color_image']['tmp_name'][$index]) && $_FILES['color_image']['error'][$index] == 0) {
                $upload_dir = "../../uploads/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $_FILES['color_image']['name'][$index];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $new_filename = uniqid('img_', true) . '_color.webp';
                    $target_path = $upload_dir . $new_filename;
                    
                    if (saveImageAsWebP($_FILES['color_image']['tmp_name'][$index], $target_path)) {
                        // Delete old color image if updating
                        if (!empty($color_image_path) && file_exists("../../" . $color_image_path)) {
                            unlink("../../" . $color_image_path);
                        }
                        
                        $color_image_path = "uploads/" . $new_filename;
                    }
                }
            }

            if ($color_id === 'new') {
                // Insert new color
                $insert_color_sql = "INSERT INTO product_colors (product_id, color_name, color_code, image, price) 
                    VALUES ($product_id, '$color_name', '$color_code', '$color_image_path', $color_price)";
                
                if (!$conn->query($insert_color_sql)) {
                    throw new Exception("Failed to insert color: " . $conn->error);
                }
                echo "Added new color: $color_name<br>";
            } else {
                // Update existing color
                $update_color_sql = "UPDATE product_colors SET 
                    color_name = '$color_name',
                    color_code = '$color_code',
                    image = '$color_image_path',
                    price = $color_price
                    WHERE id = $color_id";
                
                if (!$conn->query($update_color_sql)) {
                    throw new Exception("Failed to update color: " . $conn->error);
                }
                echo "Updated color: $color_name<br>";
            }
        }
    }

    // Handle Product Types and Variants
    if (isset($_POST['type_id'])) {
        // First, handle type deletions
        if (isset($_POST['delete_type'])) {
            foreach ($_POST['delete_type'] as $type_id) {
                // Delete variants first
                $conn->query("DELETE FROM product_variants WHERE type_id = $type_id");
                
                // Get type image path before deletion
                $type_result = $conn->query("SELECT type_image FROM product_types WHERE id = $type_id");
                if ($type_result && $type_row = $type_result->fetch_assoc()) {
                    if (!empty($type_row['type_image']) && file_exists("../../" . $type_row['type_image'])) {
                        unlink("../../" . $type_row['type_image']);
                    }
                }
                
                $conn->query("DELETE FROM product_types WHERE id = $type_id");
                echo "Deleted type ID: $type_id<br>";
            }
        }

        // Process type updates and additions
        foreach ($_POST['type_id'] as $index => $type_id) {
            // Skip if marked for deletion
            if (isset($_POST['delete_type']) && in_array($type_id, $_POST['delete_type'])) {
                continue;
            }

            $type_name = $conn->real_escape_string($_POST['type_name'][$index]);

            // Handle type image upload
            $type_image_path = '';
            
            if ($type_id !== 'new') {
                // Get existing image path
                $existing_type = $conn->query("SELECT type_image FROM product_types WHERE id = $type_id")->fetch_assoc();
                $type_image_path = $existing_type['type_image'] ?? '';
            }

            if (isset($_FILES['type_image']['tmp_name'][$index]) && $_FILES['type_image']['error'][$index] == 0) {
                $upload_dir = "../../uploads/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $_FILES['type_image']['name'][$index];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $new_filename = uniqid('img_', true) . '_type.webp';
                    $target_path = $upload_dir . $new_filename;
                    
                    if (saveImageAsWebP($_FILES['type_image']['tmp_name'][$index], $target_path)) {
                        // Delete old type image if updating
                        if (!empty($type_image_path) && file_exists("../../" . $type_image_path)) {
                            unlink("../../" . $type_image_path);
                        }
                        
                        $type_image_path = "uploads/" . $new_filename;
                    }
                }
            }

            $current_type_id = $type_id;
            
            if ($type_id === 'new') {
                // Insert new type
                $insert_type_sql = "INSERT INTO product_types (product_id, type_name, type_image) 
                    VALUES ($product_id, '$type_name', '$type_image_path')";
                
                if (!$conn->query($insert_type_sql)) {
                    throw new Exception("Failed to insert type: " . $conn->error);
                }
                
                $current_type_id = $conn->insert_id;
                echo "Added new type: $type_name (ID: $current_type_id)<br>";
            } else {
                // Update existing type
                $update_type_sql = "UPDATE product_types SET 
                    type_name = '$type_name',
                    type_image = '$type_image_path'
                    WHERE id = $type_id";
                
                if (!$conn->query($update_type_sql)) {
                    throw new Exception("Failed to update type: " . $conn->error);
                }
                echo "Updated type: $type_name<br>";
            }

            // Handle variants for this type
            if (isset($_POST['variant_id'][$index])) {
                // Handle variant deletions first
                if (isset($_POST['delete_variant'][$index])) {
                    foreach ($_POST['delete_variant'][$index] as $variant_id) {
                        // Get variant image before deletion
                        $variant_result = $conn->query("SELECT image FROM product_variants WHERE id = $variant_id");
                        if ($variant_result && $variant_row = $variant_result->fetch_assoc()) {
                            if (!empty($variant_row['image']) && file_exists("../../" . $variant_row['image'])) {
                                unlink("../../" . $variant_row['image']);
                            }
                        }
                        
                        $conn->query("DELETE FROM product_variants WHERE id = $variant_id");
                        echo "Deleted variant ID: $variant_id<br>";
                    }
                }

                // Process variant updates and additions
                foreach ($_POST['variant_id'][$index] as $v_index => $variant_id) {
                    // Skip if marked for deletion
                    if (isset($_POST['delete_variant'][$index]) && in_array($variant_id, $_POST['delete_variant'][$index])) {
                        continue;
                    }

                    $size = $conn->real_escape_string($_POST['variant_size'][$index][$v_index] ?? '');
                    $color = $conn->real_escape_string($_POST['variant_color'][$index][$v_index] ?? '');
                    $original_price = floatval($_POST['variant_original_price'][$index][$v_index] ?? 0);
                    $price = floatval($_POST['variant_price'][$index][$v_index] ?? 0);
                    $percent = floatval($_POST['variant_percent'][$index][$v_index] ?? 0);
                    $discount = floatval($_POST['variant_discount'][$index][$v_index] ?? 0);
                    $namevariant = $conn->real_escape_string($_POST['variant_namevariant'][$index][$v_index] ?? '');

                    // Calculate final price
                    $final_price = $price + ($price * $percent / 100);

                    // Handle variant image upload
                    $variant_image_path = '';
                    
                    if ($variant_id !== 'new') {
                        // Get existing image path
                        $existing_variant = $conn->query("SELECT image FROM product_variants WHERE id = $variant_id")->fetch_assoc();
                        $variant_image_path = $existing_variant['image'] ?? '';
                    }

                    if (isset($_FILES['variant_image']['tmp_name'][$index][$v_index]) && $_FILES['variant_image']['error'][$index][$v_index] == 0) {
                        $upload_dir = "../../uploads/";
                        
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_name = $_FILES['variant_image']['name'][$index][$v_index];
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        if (in_array(strtolower($file_extension), $allowed_extensions)) {
                            $new_filename = uniqid('img_', true) . '_variant.webp';
                            $target_path = $upload_dir . $new_filename;
                            
                            if (saveImageAsWebP($_FILES['variant_image']['tmp_name'][$index][$v_index], $target_path)) {
                                // Delete old variant image if updating
                                if (!empty($variant_image_path) && file_exists("../../" . $variant_image_path)) {
                                    unlink("../../" . $variant_image_path);
                                }
                                
                                $variant_image_path = "uploads/" . $new_filename;
                            }
                        }
                    }

                    if ($variant_id === 'new') {
                        // Insert new variant
                        $insert_variant_sql = "INSERT INTO product_variants (type_id, color, size, original_price, price, percent, discount, namevariant, image) 
                            VALUES ($current_type_id, '$color', '$size', $original_price, $final_price, $percent, $discount, '$namevariant', '$variant_image_path')";
                        
                        if (!$conn->query($insert_variant_sql)) {
                            throw new Exception("Failed to insert variant: " . $conn->error);
                        }
                        echo "Added new variant: $size for type $type_name<br>";
                    } else {
                        // Update existing variant
                        $update_variant_sql = "UPDATE product_variants SET 
                            color = '$color',
                            size = '$size',
                            original_price = $original_price,
                            price = $final_price,
                            percent = $percent,
                            discount = $discount,
                            namevariant = '$namevariant',
                            image = '$variant_image_path'
                            WHERE id = $variant_id";
                        
                        if (!$conn->query($update_variant_sql)) {
                            throw new Exception("Failed to update variant: " . $conn->error);
                        }
                        echo "Updated variant: $size<br>";
                    }
                }
            }
        }
    }

    $conn->commit();
    
    // Success message with sub images count
    $sub_images_count = count($final_sub_images);
    $success_message = "Product updated successfully!";
    if ($sub_images_count > 0) {
        $success_message .= " ($sub_images_count sub images included)";
    }
    
    echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>";
    echo $success_message;
    echo "</div>";
    
    echo "<script>";
    echo "setTimeout(function(){ window.location.href = 'adminupdateshop.php?id=$product_id'; }, 2000);";
    echo "</script>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>";
    echo "Error updating product: " . $e->getMessage();
    echo "</div>";
    
    echo "<script>";
    echo "setTimeout(function(){ window.location.href = 'adminupdateshop.php?id=$product_id'; }, 3000);";
    echo "</script>";
}

$conn->close();
?>