<?php
//upload_process-page-1-A.php - UPDATED: saves descrip1, descrip6, descrip7 on upload + added_by tracking

include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']);

function saveImageToFolder($file, $targetDir = '../../uploads/')
{
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $timestamp = time();
    $filename = 'img_' . $timestamp . '_' . uniqid() . '.webp';
    $targetPath = $targetDir . $filename;
    $relativePath = 'uploads/' . $filename;

    $imageType = mime_content_type($file['tmp_name']);
    $sourceImage = null;

    switch ($imageType) {
        case 'image/jpeg':
        case 'image/jpg':
            $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
            if (!$sourceImage) return null;
            break;
        case 'image/png':
            $sourceImage = @imagecreatefrompng($file['tmp_name']);
            if (!$sourceImage) return null;
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;
        case 'image/gif':
            $sourceImage = @imagecreatefromgif($file['tmp_name']);
            if (!$sourceImage) return null;
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;
        case 'image/webp':
            $sourceImage = @imagecreatefromwebp($file['tmp_name']);
            if (!$sourceImage) {
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    return $relativePath;
                }
                return null;
            }
            break;
        default:
            return null;
    }

    if (!$sourceImage) return null;

    if (imagewebp($sourceImage, $targetPath, 80)) {
        imagedestroy($sourceImage);
        return $relativePath;
    }

    imagedestroy($sourceImage);
    return null;
}

function saveSubImages($subImagesFiles, $targetDir = ROOT_PATH . '/sub_images/')
{
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $subImagePaths = [];

    if (isset($subImagesFiles['name']) && is_array($subImagesFiles['name'])) {
        $totalSubImages = count($subImagesFiles['name']);

        for ($i = 0; $i < $totalSubImages; $i++) {
            if (empty($subImagesFiles['name'][$i]) || $subImagesFiles['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $timestamp = time();
            $filename = 'sub_' . $timestamp . '_' . uniqid() . '.webp';
            $targetPath = $targetDir . $filename;
            $relativePath = 'sub_images/' . $filename;

            $file = [
                'name'     => $subImagesFiles['name'][$i],
                'tmp_name' => $subImagesFiles['tmp_name'][$i]
            ];

            $imageType = mime_content_type($file['tmp_name']);
            $sourceImage = null;

            switch ($imageType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
                    if (!$sourceImage) continue 2;
                    break;
                case 'image/png':
                    $sourceImage = @imagecreatefrompng($file['tmp_name']);
                    if (!$sourceImage) continue 2;
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                    break;
                case 'image/gif':
                    $sourceImage = @imagecreatefromgif($file['tmp_name']);
                    if (!$sourceImage) continue 2;
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                    break;
                case 'image/webp':
                    $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                    if (!$sourceImage) {
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $subImagePaths[] = $relativePath;
                        }
                        continue 2;
                    }
                    break;
                default:
                    continue 2;
            }

            if (!$sourceImage) continue;

            if (imagewebp($sourceImage, $targetPath, 80)) {
                imagedestroy($sourceImage);
                $subImagePaths[] = $relativePath;
            } else {
                imagedestroy($sourceImage);
            }
        }
    }

    return $subImagePaths;
}

// Reset AUTO_INCREMENT
$tables = ['products', 'product_types', 'product_variants', 'product_colors'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row    = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // ── Get the current logged-in user ──────────────────────────────
        $added_by = $_SESSION['noble_user'] ?? null;
        if (!$added_by) {
            throw new Exception("Session expired. Please log in again.");
        }

        // Main product image
        $main_image = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $main_image = saveImageToFolder($_FILES['main_image']);
        }

        // Sub images
        $sub_images = [];
        if (isset($_FILES['sub_images'])) {
            $sub_images = saveSubImages($_FILES['sub_images']);
        }
        $sub_images_json = !empty($sub_images) ? json_encode($sub_images) : null;

        $_POST['quantity'] = (int)$_POST['quantity'];

        // ── Ensure columns exist ────────────────────────────────────────

        $check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'sub_images'");
        if ($check_column->num_rows == 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sub_images TEXT NULL AFTER main_image");
        }

        foreach (['descrip1', 'descrip6', 'descrip7'] as $col) {
            $chk = $conn->query("SHOW COLUMNS FROM products LIKE '$col'");
            if ($chk->num_rows == 0) {
                $conn->query("ALTER TABLE products ADD COLUMN $col TEXT NULL");
            }
        }

        // ── added_by column ─────────────────────────────────────────────
        $chk_added_by = $conn->query("SHOW COLUMNS FROM products LIKE 'added_by'");
        if ($chk_added_by->num_rows == 0) {
            $conn->query("ALTER TABLE products ADD COLUMN added_by VARCHAR(100) NULL AFTER descrip7");
        }

        $check_variant_column = $conn->query("SHOW COLUMNS FROM product_variants LIKE 'product_id'");
        if ($check_variant_column->num_rows == 0) {
            $conn->query("ALTER TABLE product_variants ADD COLUMN product_id INT NOT NULL AFTER id");
            $conn->query("ALTER TABLE product_variants ADD CONSTRAINT FK_product_variants_product_id FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
        }

        $dimension_columns = ['width', 'height', 'length', 'dimension_unit', 'weight', 'weight_unit'];
        foreach ($dimension_columns as $column) {
            $check_col = $conn->query("SHOW COLUMNS FROM product_variants LIKE '$column'");
            if ($check_col->num_rows == 0) {
                if ($column == 'dimension_unit') {
                    $conn->query("ALTER TABLE product_variants ADD COLUMN $column VARCHAR(10) DEFAULT 'cm' AFTER length");
                } elseif ($column == 'weight_unit') {
                    $conn->query("ALTER TABLE product_variants ADD COLUMN $column VARCHAR(10) DEFAULT 'kg' AFTER weight");
                } elseif (in_array($column, ['width', 'height', 'length', 'weight'])) {
                    $position = ($column == 'width') ? 'AFTER discount' : (($column == 'height') ? 'AFTER width' : (($column == 'length') ? 'AFTER height' : 'AFTER dimension_unit'));
                    $conn->query("ALTER TABLE product_variants ADD COLUMN $column DECIMAL(10,2) DEFAULT NULL $position");
                }
            }
        }

        $check_image2 = $conn->query("SHOW COLUMNS FROM product_colors LIKE 'image2'");
        if ($check_image2->num_rows == 0) {
            $conn->query("ALTER TABLE product_colors ADD COLUMN image2 VARCHAR(255) NULL DEFAULT NULL AFTER image");
        }

        // ── Insert product (includes added_by) ──────────────────────────
        $descrip1_val = trim($_POST['descrip1'] ?? '');
        $descrip6_val = trim($_POST['descrip6'] ?? '');
        $descrip7_val = trim($_POST['descrip7'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO products 
                (product_name, codename, quantity, main_image, sub_images, description, descrip1, descrip6, descrip7, added_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        $stmt->bind_param(
            "ssisssssss",
            $_POST['product_name'],
            $_POST['codename'],
            $_POST['quantity'],
            $main_image,
            $sub_images_json,
            $_POST['description'],
            $descrip1_val,
            $descrip6_val,
            $descrip7_val,
            $added_by
        );
        $stmt->execute();
        $product_id   = $conn->insert_id;
        $product_name = $_POST['product_name'];
        $stmt->close();

        // ── Product types ───────────────────────────────────────────────
        if (isset($_POST['type_name']) && is_array($_POST['type_name'])) {
            foreach ($_POST['type_name'] as $type_index => $type_name) {
                $type_image = null;
                if (
                    isset($_FILES['type_image']['tmp_name'][$type_index]) &&
                    $_FILES['type_image']['error'][$type_index] === UPLOAD_ERR_OK
                ) {
                    $file = [
                        'name'     => $_FILES['type_image']['name'][$type_index],
                        'tmp_name' => $_FILES['type_image']['tmp_name'][$type_index]
                    ];
                    $type_image = saveImageToFolder($file);
                }

                $stmt = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Type insert failed: " . $conn->error);
                $stmt->bind_param("iss", $product_id, $type_name, $type_image);
                $stmt->execute();
                $type_id = $stmt->insert_id;
                $stmt->close();

                // Colors
                if (isset($_POST['color_name'][$type_index]) && is_array($_POST['color_name'][$type_index])) {
                    foreach ($_POST['color_name'][$type_index] as $color_index => $color_name) {
                        if (!empty($color_name)) {
                            $color_code  = $_POST['color_code'][$type_index][$color_index]  ?? '';
                            $color_price = (float)($_POST['color_price'][$type_index][$color_index] ?? 0);
                            $color_image  = null;
                            $color_image2 = null;

                            if (
                                isset($_FILES['color_image']['tmp_name'][$type_index][$color_index]) &&
                                $_FILES['color_image']['error'][$type_index][$color_index] === UPLOAD_ERR_OK
                            ) {
                                $file = [
                                    'name'     => $_FILES['color_image']['name'][$type_index][$color_index],
                                    'tmp_name' => $_FILES['color_image']['tmp_name'][$type_index][$color_index]
                                ];
                                $color_image = saveImageToFolder($file);
                            }

                            if (
                                isset($_FILES['color_image2']['tmp_name'][$type_index][$color_index]) &&
                                $_FILES['color_image2']['error'][$type_index][$color_index] === UPLOAD_ERR_OK
                            ) {
                                $file = [
                                    'name'     => $_FILES['color_image2']['name'][$type_index][$color_index],
                                    'tmp_name' => $_FILES['color_image2']['tmp_name'][$type_index][$color_index]
                                ];
                                $color_image2 = saveImageToFolder($file);
                            }

                            $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image, image2) VALUES (?, ?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Color insert failed: " . $conn->error);
                            $stmt->bind_param("issdss", $product_id, $color_name, $color_code, $color_price, $color_image, $color_image2);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                // Variants
                if (isset($_POST['variant_size'][$type_index]) && is_array($_POST['variant_size'][$type_index])) {
                    foreach ($_POST['variant_size'][$type_index] as $variant_index => $size) {
                        $name_variant   = $_POST['variant_namevariant'][$type_index][$variant_index]   ?? '';
                        $color          = $_POST['variant_color'][$type_index][$variant_index]          ?? '';
                        $original_price = (float)($_POST['variant_original_price'][$type_index][$variant_index] ?? 0);
                        $price          = (float)($_POST['variant_price'][$type_index][$variant_index]          ?? 0);
                        $percent        = (float)($_POST['variant_percent'][$type_index][$variant_index]        ?? 0);
                        $discount       = (float)($_POST['variant_discount'][$type_index][$variant_index]       ?? 0);
                        $width          = !empty($_POST['variant_width'][$type_index][$variant_index])   ? (float)$_POST['variant_width'][$type_index][$variant_index]   : null;
                        $height         = !empty($_POST['variant_height'][$type_index][$variant_index])  ? (float)$_POST['variant_height'][$type_index][$variant_index]  : null;
                        $length         = !empty($_POST['variant_length'][$type_index][$variant_index])  ? (float)$_POST['variant_length'][$type_index][$variant_index]  : null;
                        $dimension_unit = $_POST['variant_dimension_unit'][$type_index][$variant_index]  ?? 'cm';
                        $weight         = !empty($_POST['variant_weight'][$type_index][$variant_index])  ? (float)$_POST['variant_weight'][$type_index][$variant_index]  : null;
                        $weight_unit    = $_POST['variant_weight_unit'][$type_index][$variant_index]     ?? 'kg';

                        if (!empty($size) || !empty($name_variant)) {
                            $final_price   = $price + ($price * $percent / 100);
                            $variant_image = null;

                            if (
                                isset($_FILES['variant_image']['tmp_name'][$type_index][$variant_index]) &&
                                $_FILES['variant_image']['error'][$type_index][$variant_index] === UPLOAD_ERR_OK
                            ) {
                                $file = [
                                    'name'     => $_FILES['variant_image']['name'][$type_index][$variant_index],
                                    'tmp_name' => $_FILES['variant_image']['tmp_name'][$type_index][$variant_index]
                                ];
                                $variant_image = saveImageToFolder($file);
                            }

                            $stmt = $conn->prepare("INSERT INTO product_variants (product_id, type_id, color, size, original_price, price, percent, discount, namevariant, image, width, height, length, dimension_unit, weight, weight_unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Variant insert failed: " . $conn->error);
                            $stmt->bind_param(
                                "iissddddssdddsds",
                                $product_id, $type_id, $color, $size,
                                $original_price, $final_price, $percent, $discount,
                                $name_variant, $variant_image,
                                $width, $height, $length, $dimension_unit,
                                $weight, $weight_unit
                            );
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }

        $conn->commit();
        header("Location: " . BASE_URL . "/addnewproduct?id=$product_id&success=1");
        exit();


    } catch (Exception $e) {
        $conn->rollback();
        error_log("Upload error: " . $e->getMessage());
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "'); 
            history.back();
        </script>";
    }
} else {
    header("Location: " . BASE_URL . "/addnewproduct");
    exit();
}