<?php
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Insert main product
        $main_image = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $main_image = file_get_contents($_FILES['main_image']['tmp_name']);
        }

        $_POST['quantity'] = (int)$_POST['quantity'];

        $stmt = $conn->prepare("INSERT INTO products (product_name, codename, quantity, main_image, description) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception("Prepare failed for product insert: " . $conn->error);

        $stmt->bind_param("ssibs", 
            $_POST['product_name'],
            $_POST['codename'],
            $_POST['quantity'],
            $main_image,
            $_POST['description']
        );
        $stmt->send_long_data(3, $main_image); // send binary data
        $stmt->execute();
        $product_id = $conn->insert_id;
        $stmt->close();

        // Handle product types
        if (isset($_POST['type_name']) && is_array($_POST['type_name'])) {
            foreach ($_POST['type_name'] as $type_index => $type_name) {

                $type_image = null;
                if (isset($_FILES['type_image']['tmp_name'][$type_index]) && 
                    $_FILES['type_image']['error'][$type_index] === UPLOAD_ERR_OK) {
                    $type_image = file_get_contents($_FILES['type_image']['tmp_name'][$type_index]);
                }

                $stmt = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed for product_types: " . $conn->error);

                $stmt->bind_param("isb", $product_id, $type_name, $type_image);
                $stmt->send_long_data(2, $type_image);
                $stmt->execute();
                $type_id = $stmt->insert_id;
                $stmt->close();

                // Product colors for the product (not per type)
                if (isset($_POST['color_name'][$type_index]) && is_array($_POST['color_name'][$type_index])) {
                    foreach ($_POST['color_name'][$type_index] as $color_index => $color_name) {
                        if (!empty($color_name)) {
                            $color_code = $_POST['color_code'][$type_index][$color_index] ?? '';
                            $color_price = (float)($_POST['color_price'][$type_index][$color_index] ?? 0);
                            $color_image = null;

                            if (isset($_FILES['color_image']['tmp_name'][$type_index][$color_index]) && 
                                $_FILES['color_image']['error'][$type_index][$color_index] === UPLOAD_ERR_OK) {
                                $color_image = file_get_contents($_FILES['color_image']['tmp_name'][$type_index][$color_index]);
                            }

                            $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image) VALUES (?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Prepare failed for product_colors: " . $conn->error);

                            $stmt->bind_param("issdb", $product_id, $color_name, $color_code, $color_price, $color_image);
                            $stmt->send_long_data(4, $color_image);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                // Product variants for this type
                if (isset($_POST['variant_size'][$type_index]) && is_array($_POST['variant_size'][$type_index])) {
                    foreach ($_POST['variant_size'][$type_index] as $variant_index => $size) {
                        $name_variant = $_POST['variant_namevariant'][$type_index][$variant_index] ?? '';
                        $color = $_POST['variant_color'][$type_index][$variant_index] ?? '';
                        $price = (float)($_POST['variant_price'][$type_index][$variant_index] ?? 0);
                        $percent = (float)($_POST['variant_percent'][$type_index][$variant_index] ?? 0);
                        $discount = (float)($_POST['variant_discount'][$type_index][$variant_index] ?? 0);

                        if (!empty($size) || !empty($name_variant)) {
                            $final_price = $price + ($price * $percent / 100);

                            $variant_image = null;
                            if (isset($_FILES['variant_image']['tmp_name'][$type_index][$variant_index]) && 
                                $_FILES['variant_image']['error'][$type_index][$variant_index] === UPLOAD_ERR_OK) {
                                $variant_image = file_get_contents($_FILES['variant_image']['tmp_name'][$type_index][$variant_index]);
                            }

                            $stmt = $conn->prepare("INSERT INTO product_variants (type_id, color, size, price, percent, discount, namevariant, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) throw new Exception("Prepare failed for product_variants: " . $conn->error);

                            $stmt->bind_param("issdddss", 
                                $type_id,
                                $color,
                                $size,
                                $final_price,
                                $percent,
                                $discount,
                                $name_variant,
                                $variant_image
                            );
                            $stmt->send_long_data(7, $variant_image);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }

        $conn->commit();
        echo "<script>alert('Product uploaded successfully!'); window.location.href='adminshop.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    }
} else {
    header("Location: adminshop.php");
    exit();
}
?>
