<?php
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Reset AUTO_INCREMENT if tables are empty
$tables = ['products', 'product_colors', 'product_variants', 'product_types'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $product_id = $_POST['product_id'];

        // Update main product
        $main_image_update = "";
        $params = [$_POST['product_name'], $_POST['codename'], $_POST['quantity'], $_POST['description']];

        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $main_image = file_get_contents($_FILES['main_image']['tmp_name']);
            $main_image_update = ", main_image = ?";
            $params[] = $main_image;
        }

        $params[] = $product_id;

        $stmt = $conn->prepare("UPDATE products SET product_name = ?, codename = ?, quantity = ?, description = ? $main_image_update WHERE id = ?");
        $stmt->execute($params);

        // Delete selected colors
        if (isset($_POST['delete_color']) && is_array($_POST['delete_color'])) {
            foreach ($_POST['delete_color'] as $color_id) {
                $stmt = $conn->prepare("DELETE FROM product_colors WHERE id = ?");
                $stmt->execute([$color_id]);
            }
        }

        // Handle color update/insert
        if (isset($_POST['color_id']) && is_array($_POST['color_id'])) {
            foreach ($_POST['color_id'] as $index => $color_id) {
                $color_name = $_POST['color_name'][$index] ?? '';
                $color_code = $_POST['color_code'][$index] ?? '';
                $color_price = (float)($_POST['color_price'][$index] ?? 0);

                if (empty($color_name)) continue;

                $color_image = null;
                if (isset($_FILES['color_image']['tmp_name'][$index]) && $_FILES['color_image']['error'][$index] === UPLOAD_ERR_OK) {
                    $color_image = file_get_contents($_FILES['color_image']['tmp_name'][$index]);
                }

                if ($color_id === 'new') {
                    $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$product_id, $color_name, $color_code, $color_price, $color_image]);
                } else {
                    if ($color_image !== null) {
                        $stmt = $conn->prepare("UPDATE product_colors SET color_name = ?, color_code = ?, price = ?, image = ? WHERE id = ?");
                        $stmt->execute([$color_name, $color_code, $color_price, $color_image, $color_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE product_colors SET color_name = ?, color_code = ?, price = ? WHERE id = ?");
                        $stmt->execute([$color_name, $color_code, $color_price, $color_id]);
                    }
                }
            }
        }

        // Delete selected types (and their variants)
        if (isset($_POST['delete_type']) && is_array($_POST['delete_type'])) {
            foreach ($_POST['delete_type'] as $type_id) {
                $stmt = $conn->prepare("DELETE FROM product_variants WHERE type_id = ?");
                $stmt->execute([$type_id]);

                $stmt = $conn->prepare("DELETE FROM product_types WHERE id = ?");
                $stmt->execute([$type_id]);
            }
        }

        // Handle type update/insert and variants
        if (isset($_POST['type_id']) && is_array($_POST['type_id'])) {
            foreach ($_POST['type_id'] as $index => $type_id) {
                $type_name = $_POST['type_name'][$index] ?? '';
                if (empty($type_name)) continue;

                $type_image = null;
                if (isset($_FILES['type_image']['tmp_name'][$index]) && $_FILES['type_image']['error'][$index] === UPLOAD_ERR_OK) {
                    $type_image = file_get_contents($_FILES['type_image']['tmp_name'][$index]);
                }

                if ($type_id === 'new') {
                    $stmt = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, ?)");
                    $stmt->execute([$product_id, $type_name, $type_image]);
                    $type_id = $conn->insert_id;
                } else {
                    if ($type_image !== null) {
                        $stmt = $conn->prepare("UPDATE product_types SET type_name = ?, type_image = ? WHERE id = ?");
                        $stmt->execute([$type_name, $type_image, $type_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE product_types SET type_name = ? WHERE id = ?");
                        $stmt->execute([$type_name, $type_id]);
                    }
                }

                // Delete selected variants
                if (isset($_POST['delete_variant'][$index]) && is_array($_POST['delete_variant'][$index])) {
                    foreach ($_POST['delete_variant'][$index] as $variant_id) {
                        $stmt = $conn->prepare("DELETE FROM product_variants WHERE id = ?");
                        $stmt->execute([$variant_id]);
                    }
                }

                // Handle variant update/insert
                if (isset($_POST['variant_id'][$index]) && is_array($_POST['variant_id'][$index])) {
                    foreach ($_POST['variant_id'][$index] as $var_index => $variant_id) {
                        $size = $_POST['variant_size'][$index][$var_index] ?? '';
                        $price = (float)($_POST['variant_price'][$index][$var_index] ?? 0);
                        $percent = (float)($_POST['variant_percent'][$index][$var_index] ?? 0);
                        $discount = (float)($_POST['variant_discount'][$index][$var_index] ?? 0);
                        $name_variant = $_POST['variant_namevariant'][$index][$var_index] ?? '';

                        if (empty($size) && empty($name_variant)) continue;

                        $final_price = $price + ($price * $percent / 100);

                        if ($variant_id === 'new') {
                            $stmt = $conn->prepare("INSERT INTO product_variants (type_id, size, price, percent, discount, namevariant) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$type_id, $size, $final_price, $percent, $discount, $name_variant]);
                        } else {
                            $stmt = $conn->prepare("UPDATE product_variants SET size = ?, price = ?, percent = ?, discount = ?, namevariant = ? WHERE id = ?");
                            $stmt->execute([$size, $final_price, $percent, $discount, $name_variant, $variant_id]);
                        }
                    }
                }
            }
        }

        $conn->commit();
        echo "<script>alert('Product updated successfully!'); window.location.href='adminupdateshop.php?id=$product_id';</script>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); history.back();</script>";
    }
} else {
    header("Location: adminupdateshop.php");
    exit();
}
?>
