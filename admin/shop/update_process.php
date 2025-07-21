<?php
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Convert + Save image to .webp and return relative path
function saveImageToFolder($file, $targetDir = '../../uploads/') {
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

    $filename = uniqid('img_', true) . '.webp';
    $targetPath = $targetDir . $filename;
    $relativePath = 'uploads/' . $filename;

    $type = mime_content_type($file['tmp_name']);
    switch ($type) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $src = imagecreatefrompng($file['tmp_name']);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($file['tmp_name']);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/webp':
            if (move_uploaded_file($file['tmp_name'], $targetPath)) return $relativePath;
            return null;
        default:
            return null;
    }

    if ($src && imagewebp($src, $targetPath, 80)) {
        imagedestroy($src);
        return $relativePath;
    }
    return null;
}

$tables = ['products', 'product_types', 'product_variants', 'product_colors'];

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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $product_id = $_POST['product_id'];

        // 🔄 Update Product Info
        $params = [$_POST['product_name'], $_POST['codename'], $_POST['quantity'], $_POST['description']];
        $main_image_update = "";

        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            // Unlink old main image
            $oldImg = $conn->query("SELECT main_image FROM products WHERE id = $product_id")->fetch_assoc()['main_image'];
            if ($oldImg && file_exists("../../$oldImg")) unlink("../../$oldImg");

            $main_image = saveImageToFolder($_FILES['main_image']);
            $main_image_update = ", main_image = ?";
            $params[] = $main_image;
        }

        $params[] = $product_id;
        $stmt = $conn->prepare("UPDATE products SET product_name=?, codename=?, quantity=?, description=? $main_image_update WHERE id=?");
        $stmt->execute($params);

        // 🧹 Delete selected colors
        if (!empty($_POST['delete_color'])) {
            foreach ($_POST['delete_color'] as $color_id) {
                // Unlink color image
                $img = $conn->query("SELECT image FROM product_colors WHERE id = $color_id")->fetch_assoc()['image'];
                if ($img && file_exists("../../$img")) unlink("../../$img");

                $conn->query("DELETE FROM product_colors WHERE id = $color_id");
            }
        }

        // 🎨 Color Update / Insert
        foreach ($_POST['color_id'] ?? [] as $i => $color_id) {
            $name = $_POST['color_name'][$i] ?? '';
            if (!$name) continue;
            $code = $_POST['color_code'][$i] ?? '';
            $price = (float)($_POST['color_price'][$i] ?? 0);
            $image = null;

            if (isset($_FILES['color_image']['tmp_name'][$i]) && $_FILES['color_image']['error'][$i] === UPLOAD_ERR_OK) {
                // Unlink old if updating
                if ($color_id !== 'new') {
                    $img = $conn->query("SELECT image FROM product_colors WHERE id = $color_id")->fetch_assoc()['image'];
                    if ($img && file_exists("../../$img")) unlink("../../$img");
                }
                $image = saveImageToFolder([
                    'tmp_name' => $_FILES['color_image']['tmp_name'][$i]
                ]);
            }

            if ($color_id === 'new') {
                $stmt = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $name, $code, $price, $image]);
            } else {
                $stmt = $image
                    ? $conn->prepare("UPDATE product_colors SET color_name=?, color_code=?, price=?, image=? WHERE id=?")
                    : $conn->prepare("UPDATE product_colors SET color_name=?, color_code=?, price=? WHERE id=?");
                $image
                    ? $stmt->execute([$name, $code, $price, $image, $color_id])
                    : $stmt->execute([$name, $code, $price, $color_id]);
            }
        }

        // 🗑 Delete Types + Variants
        foreach ($_POST['delete_type'] ?? [] as $type_id) {
            // Unlink all type image
            $img = $conn->query("SELECT type_image FROM product_types WHERE id = $type_id")->fetch_assoc()['type_image'];
            if ($img && file_exists("../../$img")) unlink("../../$img");

            $res = $conn->query("SELECT image FROM product_variants WHERE type_id = $type_id");
            while ($row = $res->fetch_assoc()) {
                if ($row['image'] && file_exists("../../{$row['image']}")) unlink("../../{$row['image']}");
            }

            $conn->query("DELETE FROM product_variants WHERE type_id = $type_id");
            $conn->query("DELETE FROM product_types WHERE id = $type_id");
        }

        // 🔁 Handle Types & Variants
        foreach ($_POST['type_id'] ?? [] as $i => $type_id) {
            $name = $_POST['type_name'][$i] ?? '';
            if (!$name) continue;
            $image = null;

            if (isset($_FILES['type_image']['tmp_name'][$i]) && $_FILES['type_image']['error'][$i] === UPLOAD_ERR_OK) {
                if ($type_id !== 'new') {
                    $img = $conn->query("SELECT type_image FROM product_types WHERE id = $type_id")->fetch_assoc()['type_image'];
                    if ($img && file_exists("../../$img")) unlink("../../$img");
                }
                $image = saveImageToFolder([
                    'tmp_name' => $_FILES['type_image']['tmp_name'][$i]
                ]);
            }

            if ($type_id === 'new') {
                $stmt = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, ?)");
                $stmt->execute([$product_id, $name, $image]);
                $type_id = $conn->insert_id;
            } else {
                $stmt = $image
                    ? $conn->prepare("UPDATE product_types SET type_name=?, type_image=? WHERE id=?")
                    : $conn->prepare("UPDATE product_types SET type_name=? WHERE id=?");
                $image
                    ? $stmt->execute([$name, $image, $type_id])
                    : $stmt->execute([$name, $type_id]);
            }

            // Variants delete
            foreach ($_POST['delete_variant'][$i] ?? [] as $vid) {
                $img = $conn->query("SELECT image FROM product_variants WHERE id = $vid")->fetch_assoc()['image'];
                if ($img && file_exists("../../$img")) unlink("../../$img");
                $conn->query("DELETE FROM product_variants WHERE id = $vid");
            }

            // Variants update/add
            foreach ($_POST['variant_id'][$i] ?? [] as $v => $var_id) {
                $size = $_POST['variant_size'][$i][$v] ?? '';
                $base = (float)($_POST['variant_price'][$i][$v] ?? 0);
                $percent = (float)($_POST['variant_percent'][$i][$v] ?? 0);
                $discount = (float)($_POST['variant_discount'][$i][$v] ?? 0);
                $namev = $_POST['variant_namevariant'][$i][$v] ?? '';
                if (!$size && !$namev) continue;

                $price = $base + ($base * $percent / 100);
                $image = null;

                if (isset($_FILES['variant_image']['tmp_name'][$i][$v]) && $_FILES['variant_image']['error'][$i][$v] === UPLOAD_ERR_OK) {
                    if ($var_id !== 'new') {
                        $img = $conn->query("SELECT image FROM product_variants WHERE id = $var_id")->fetch_assoc()['image'];
                        if ($img && file_exists("../../$img")) unlink("../../$img");
                    }
                    $image = saveImageToFolder([
                        'tmp_name' => $_FILES['variant_image']['tmp_name'][$i][$v]
                    ]);
                }

                if ($var_id === 'new') {
                    $stmt = $conn->prepare("INSERT INTO product_variants (type_id, size, price, percent, discount, namevariant, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$type_id, $size, $price, $percent, $discount, $namev, $image]);
                } else {
                    $stmt = $image
                        ? $conn->prepare("UPDATE product_variants SET size=?, price=?, percent=?, discount=?, namevariant=?, image=? WHERE id=?")
                        : $conn->prepare("UPDATE product_variants SET size=?, price=?, percent=?, discount=?, namevariant=? WHERE id=?");

                    $image
                        ? $stmt->execute([$size, $price, $percent, $discount, $namev, $image, $var_id])
                        : $stmt->execute([$size, $price, $percent, $discount, $namev, $var_id]);
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

