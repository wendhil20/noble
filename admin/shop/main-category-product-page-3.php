<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$message = "";
$error = "";

// Auto-increment reset for all tables
$tables = ['categories', 'product_subcategories', 'product_sub_subcategories'];

foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

function createDirectory($path)
{
    if (!file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

if ($_POST) {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);

        if (!empty($category_name)) {
            $category_dir = "../../uploads/categories/";
            createDirectory($category_dir);

            $image_path = null;
            $image_pathtwo = null;

            if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['category_image']['type'];

                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($category_name));
                    $new_filename = $safe_name . '_' . time() . '.' . $file_extension;
                    $upload_path = $category_dir . $new_filename;

                    if (move_uploaded_file($_FILES['category_image']['tmp_name'], $upload_path)) {
                        chmod($upload_path, 0644);
                        $image_path = $new_filename;
                    } else {
                        $_SESSION['error'] = "Error uploading category image.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                }
            }

            $sql = "INSERT INTO categories (name, image_path, image_pathtwo) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $category_name, $image_path, $image_pathtwo);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Category '$category_name' added successfully!";
            } else {
                $_SESSION['error'] = "Error adding category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Category name cannot be empty!";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Add Subcategory
    if (isset($_POST['add_subcategory'])) {
        $category_id = $_POST['category_id'];
        $subcategory_name = trim($_POST['subcategory_name']);
        $subcategory_slug = trim($_POST['subcategory_slug']);

        if (!empty($subcategory_name) && !empty($subcategory_slug) && !empty($category_id)) {
            // Get category name for slug prefix
            $get_cat_sql = "SELECT name FROM categories WHERE id = ?";
            $get_cat_stmt = $conn->prepare($get_cat_sql);
            $get_cat_stmt->bind_param("i", $category_id);
            $get_cat_stmt->execute();
            $cat_result = $get_cat_stmt->get_result();
            $cat_data = $cat_result->fetch_assoc();
            $category_name = $cat_data['name'];
            $get_cat_stmt->close();

            // Create auto slug: category-name + subcategory-slug
            $category_slug_part = strtolower(preg_replace('/[^a-z0-9\s-]/', '', str_replace(' ', '-', $category_name)));
            $final_slug = $category_slug_part . '-' . $subcategory_slug;

            // Check if same name already exists in this category
            $check_name_sql = "SELECT id FROM product_subcategories WHERE category_id = ? AND subcategory_name = ?";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bind_param("is", $category_id, $subcategory_name);
            $check_name_stmt->execute();
            $check_name_result = $check_name_stmt->get_result();

            if ($check_name_result->num_rows > 0) {
                $_SESSION['error'] = "Subcategory '$subcategory_name' already exists in this category!";
                $check_name_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_name_stmt->close();

            // Check if generated slug already exists
            $check_slug_sql = "SELECT id FROM product_subcategories WHERE subcategory_slug = ?";
            $check_slug_stmt = $conn->prepare($check_slug_sql);
            $check_slug_stmt->bind_param("s", $final_slug);
            $check_slug_stmt->execute();
            $check_slug_result = $check_slug_stmt->get_result();

            if ($check_slug_result->num_rows > 0) {
                $_SESSION['error'] = "This combination already exists! Try a different subcategory slug.";
                $check_slug_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_slug_stmt->close();

            $upload_dir = "../../uploads/" . $final_slug . "/";
            if (!createDirectory($upload_dir)) {
                $_SESSION['error'] = "Error creating upload directory.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }

            $image_path = null;

            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['subcategory_image']['type'];

                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $final_slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_path)) {
                        $image_path = $new_filename;
                    }
                }
            }

            $sql = "INSERT INTO product_subcategories (category_id, subcategory_name, subcategory_slug, image_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $category_id, $subcategory_name, $final_slug, $image_path);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Subcategory '$subcategory_name' added successfully!";
            } else {
                $_SESSION['error'] = "Error adding subcategory: " . $conn->error;
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required!";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Add Sub-Subcategory
    if (isset($_POST['add_sub_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $sub_subcategory_name = trim($_POST['sub_subcategory_name']);
        $sub_subcategory_slug = trim($_POST['sub_subcategory_slug']);

        if (!empty($sub_subcategory_name) && !empty($sub_subcategory_slug) && !empty($subcategory_id)) {
            // Check if same sub-subcategory name already exists in this subcategory
            $check_name_sql = "SELECT id FROM product_sub_subcategories WHERE subcategory_id = ? AND sub_subcategory_name = ?";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->bind_param("is", $subcategory_id, $sub_subcategory_name);
            $check_name_stmt->execute();
            $check_name_result = $check_name_stmt->get_result();

            if ($check_name_result->num_rows > 0) {
                $_SESSION['error'] = "Sub-subcategory '$sub_subcategory_name' already exists in this subcategory!";
                $check_name_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_name_stmt->close();

            // Check if slug already exists globally
            $check_slug_sql = "SELECT id FROM product_sub_subcategories WHERE sub_subcategory_slug = ?";
            $check_slug_stmt = $conn->prepare($check_slug_sql);
            $check_slug_stmt->bind_param("s", $sub_subcategory_slug);
            $check_slug_stmt->execute();
            $check_slug_result = $check_slug_stmt->get_result();

            if ($check_slug_result->num_rows > 0) {
                $_SESSION['error'] = "Slug '$sub_subcategory_slug' already exists! Please use a different slug.";
                $check_slug_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_slug_stmt->close();

            $get_parent_slug = "SELECT subcategory_slug FROM product_subcategories WHERE id = ?";
            $get_parent_stmt = $conn->prepare($get_parent_slug);
            $get_parent_stmt->bind_param("i", $subcategory_id);
            $get_parent_stmt->execute();
            $parent_result = $get_parent_stmt->get_result();
            $parent_data = $parent_result->fetch_assoc();
            $parent_slug = $parent_data['subcategory_slug'];
            $get_parent_stmt->close();

            $upload_dir = "../../uploads/" . $parent_slug . "/" . $sub_subcategory_slug . "/";
            if (!createDirectory($upload_dir)) {
                $_SESSION['error'] = "Error creating upload directory.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }

            $image_path = null;

            if (isset($_FILES['sub_subcategory_image']) && $_FILES['sub_subcategory_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['sub_subcategory_image']['type'];

                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['sub_subcategory_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $sub_subcategory_slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['sub_subcategory_image']['tmp_name'], $upload_path)) {
                        $image_path = $new_filename;
                    }
                }
            }

            $sql = "INSERT INTO product_sub_subcategories (subcategory_id, sub_subcategory_name, sub_subcategory_slug, image_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $subcategory_id, $sub_subcategory_name, $sub_subcategory_slug, $image_path);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Sub-subcategory '$sub_subcategory_name' added successfully!";
            } else {
                $_SESSION['error'] = "Error adding sub-subcategory: " . $conn->error;
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required!";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Delete Sub-Subcategory
    if (isset($_POST['delete_sub_subcategory'])) {
        $sub_subcategory_id = $_POST['delete_sub_subcategory_id'];

        $get_data_sql = "SELECT pss.sub_subcategory_slug, ps.subcategory_slug 
                         FROM product_sub_subcategories pss
                         JOIN product_subcategories ps ON pss.subcategory_id = ps.id
                         WHERE pss.id = ?";
        $get_data_stmt = $conn->prepare($get_data_sql);
        $get_data_stmt->bind_param("i", $sub_subcategory_id);
        $get_data_stmt->execute();
        $data_result = $get_data_stmt->get_result();
        $data_row = $data_result->fetch_assoc();
        $sub_sub_slug = $data_row ? $data_row['sub_subcategory_slug'] : null;
        $parent_slug = $data_row ? $data_row['subcategory_slug'] : null;
        $get_data_stmt->close();

        $sql = "DELETE FROM product_sub_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sub_subcategory_id);

        if ($stmt->execute()) {
            if ($sub_sub_slug && $parent_slug) {
                $dir_to_delete = "../../uploads/" . $parent_slug . "/" . $sub_sub_slug . "/";
                deleteDirectory($dir_to_delete);
            }
            $_SESSION['message'] = "Sub-subcategory deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting sub-subcategory: " . $conn->error;
        }
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Update Sub-Subcategory Image
    if (isset($_POST['update_sub_subcategory_image'])) {
        $sub_subcategory_id = $_POST['sub_subcategory_id'];

        $get_data_sql = "SELECT pss.sub_subcategory_slug, pss.image_path, ps.subcategory_slug 
                         FROM product_sub_subcategories pss
                         JOIN product_subcategories ps ON pss.subcategory_id = ps.id
                         WHERE pss.id = ?";
        $get_data_stmt = $conn->prepare($get_data_sql);
        $get_data_stmt->bind_param("i", $sub_subcategory_id);
        $get_data_stmt->execute();
        $data_result = $get_data_stmt->get_result();
        $data = $data_result->fetch_assoc();
        $get_data_stmt->close();

        if ($data) {
            $slug = $data['sub_subcategory_slug'];
            $parent_slug = $data['subcategory_slug'];
            $current_image = $data['image_path'];
            $upload_dir = "../../uploads/" . $parent_slug . "/" . $slug . "/";

            if (isset($_FILES['new_sub_sub_image']) && $_FILES['new_sub_sub_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['new_sub_sub_image']['type'];

                if (in_array($file_type, $allowed_types)) {
                    if (!createDirectory($upload_dir)) {
                        $_SESSION['error'] = "Error creating upload directory.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }

                    if ($current_image && file_exists($upload_dir . $current_image)) {
                        unlink($upload_dir . $current_image);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['new_sub_sub_image']['name'], PATHINFO_EXTENSION));
                    $new_filename = $slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['new_sub_sub_image']['tmp_name'], $upload_path)) {
                        chmod($upload_path, 0644);

                        $update_sql = "UPDATE product_sub_subcategories SET image_path = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("si", $new_filename, $sub_subcategory_id);

                        if ($update_stmt->execute()) {
                            $_SESSION['message'] = "Sub-subcategory image updated successfully!";
                        } else {
                            $_SESSION['error'] = "Error updating image: " . $conn->error;
                        }
                        $update_stmt->close();
                    } else {
                        $_SESSION['error'] = "Error uploading image.";
                    }
                } else {
                    $_SESSION['error'] = "Invalid image type.";
                }
            } else {
                $_SESSION['error'] = "No image file selected.";
            }
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Delete Category
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['delete_category_id'];

        $get_cat_sql = "SELECT image_path, image_pathtwo FROM categories WHERE id = ?";
        $get_cat_stmt = $conn->prepare($get_cat_sql);
        $get_cat_stmt->bind_param("i", $category_id);
        $get_cat_stmt->execute();
        $cat_result = $get_cat_stmt->get_result();
        $cat_data = $cat_result->fetch_assoc();
        $category_image = $cat_data ? $cat_data['image_path'] : null;
        $category_image_two = $cat_data ? $cat_data['image_pathtwo'] : null;
        $get_cat_stmt->close();

        $get_subs_sql = "SELECT subcategory_slug FROM product_subcategories WHERE category_id = ?";
        $get_subs_stmt = $conn->prepare($get_subs_sql);
        $get_subs_stmt->bind_param("i", $category_id);
        $get_subs_stmt->execute();
        $subs_result = $get_subs_stmt->get_result();

        $slugs_to_delete = [];
        while ($sub_row = $subs_result->fetch_assoc()) {
            $slugs_to_delete[] = $sub_row['subcategory_slug'];
        }
        $get_subs_stmt->close();

        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);

        if ($stmt->execute()) {
            if ($category_image && file_exists("../../uploads/categories/" . $category_image)) {
                unlink("../../uploads/categories/" . $category_image);
            }
            if ($category_image_two && file_exists("../../uploads/categories/" . $category_image_two)) {
                unlink("../../uploads/categories/" . $category_image_two);
            }

            foreach ($slugs_to_delete as $slug) {
                $dir_to_delete = "../../uploads/" . $slug . "/";
                deleteDirectory($dir_to_delete);
            }
            $_SESSION['message'] = "Category deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting category: " . $conn->error;
        }
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Delete Subcategory
    if (isset($_POST['delete_subcategory'])) {
        $subcategory_id = $_POST['delete_subcategory_id'];

        $get_slug_sql = "SELECT subcategory_slug FROM product_subcategories WHERE id = ?";
        $get_slug_stmt = $conn->prepare($get_slug_sql);
        $get_slug_stmt->bind_param("i", $subcategory_id);
        $get_slug_stmt->execute();
        $slug_result = $get_slug_stmt->get_result();
        $slug_row = $slug_result->fetch_assoc();
        $slug_to_delete = $slug_row ? $slug_row['subcategory_slug'] : null;
        $get_slug_stmt->close();

        $sql = "DELETE FROM product_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $subcategory_id);

        if ($stmt->execute()) {
            if ($slug_to_delete) {
                $dir_to_delete = "../../uploads/" . $slug_to_delete . "/";
                deleteDirectory($dir_to_delete);
            }
            $_SESSION['message'] = "Subcategory deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting subcategory: " . $conn->error;
        }
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Update Subcategory Image
    if (isset($_POST['update_subcategory_image'])) {
        $subcategory_id = $_POST['subcategory_id'];

        $get_sub_sql = "SELECT subcategory_slug, image_path FROM product_subcategories WHERE id = ?";
        $get_sub_stmt = $conn->prepare($get_sub_sql);
        $get_sub_stmt->bind_param("i", $subcategory_id);
        $get_sub_stmt->execute();
        $sub_result = $get_sub_stmt->get_result();
        $sub_data = $sub_result->fetch_assoc();
        $get_sub_stmt->close();

        if ($sub_data) {
            $slug = $sub_data['subcategory_slug'];
            $current_image = $sub_data['image_path'];
            $upload_dir = "../../uploads/" . $slug . "/";

            if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['new_image']['type'];

                if (in_array($file_type, $allowed_types)) {
                    if (!createDirectory($upload_dir)) {
                        $_SESSION['error'] = "Error creating upload directory.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }

                    if ($current_image && file_exists($upload_dir . $current_image)) {
                        unlink($upload_dir . $current_image);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
                    $new_filename = $slug . '_main.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['new_image']['tmp_name'], $upload_path)) {
                        chmod($upload_path, 0644);

                        $update_sql = "UPDATE product_subcategories SET image_path = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("si", $new_filename, $subcategory_id);

                        if ($update_stmt->execute()) {
                            $_SESSION['message'] = "Image updated successfully!";
                        } else {
                            $_SESSION['error'] = "Error updating image: " . $conn->error;
                        }
                        $update_stmt->close();
                    } else {
                        $_SESSION['error'] = "Error uploading image.";
                    }
                } else {
                    $_SESSION['error'] = "Invalid image type.";
                }
            } else {
                $_SESSION['error'] = "No image file selected.";
            }
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch categories
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch subcategories
$subcategories_result = $conn->query("SELECT id, category_id, subcategory_name FROM product_subcategories ORDER BY subcategory_name");
$subcategories = [];
while ($row = $subcategories_result->fetch_assoc()) {
    $subcategories[] = $row;
}

// Around line 450, update the display_sql to include product counts
$display_sql = "SELECT 
    c.id as category_id,
    c.name as category_name,
    c.image_path as category_image,
    c.image_pathtwo as category_image_two,
    ps.id as subcategory_id,
    ps.subcategory_name,
    ps.subcategory_slug,
    ps.image_path as sub_image_path,
    pss.id as sub_subcategory_id,
    pss.sub_subcategory_name,
    pss.sub_subcategory_slug,
    pss.image_path as sub_sub_image_path,
    COUNT(DISTINCT pssl.product_id) as product_count
FROM categories c
LEFT JOIN product_subcategories ps ON c.id = ps.category_id
LEFT JOIN product_sub_subcategories pss ON ps.id = pss.subcategory_id
LEFT JOIN product_sub_subcategory_links pssl ON pss.id = pssl.sub_subcategory_id
GROUP BY c.id, ps.id, pss.id
ORDER BY c.name, ps.subcategory_name, pss.sub_subcategory_name";

$display_result = $conn->query($display_sql);
$display_categories = [];
while ($row = $display_result->fetch_assoc()) {
    $category_name = $row['category_name'];
    if (!isset($display_categories[$category_name])) {
        $display_categories[$category_name] = [
            'id' => $row['category_id'],
            'name' => $category_name,
            'image_path' => $row['category_image'],
            'image_pathtwo' => $row['category_image_two'],
            'subcategories' => []
        ];
    }
    if ($row['subcategory_id']) {
        $sub_key = $row['subcategory_id'];
        if (!isset($display_categories[$category_name]['subcategories'][$sub_key])) {
            $display_categories[$category_name]['subcategories'][$sub_key] = [
                'id' => $row['subcategory_id'],
                'name' => $row['subcategory_name'],
                'slug' => $row['subcategory_slug'],
                'image_path' => $row['sub_image_path'],
                'sub_subcategories' => []
            ];
        }
        if ($row['sub_subcategory_id']) {
            $display_categories[$category_name]['subcategories'][$sub_key]['sub_subcategories'][] = [
                'id' => $row['sub_subcategory_id'],
                'name' => $row['sub_subcategory_name'],
                'slug' => $row['sub_subcategory_slug'],
                'image_path' => $row['sub_sub_image_path'],
                'parent_slug' => $row['subcategory_slug']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#e29a15ff',
                        'primary-dark': '#005a87',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100 font-sans">

    <?php include '../navbar/top.php'; ?>
    <div class="max-w-7xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Category Management System</h1>

            <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <!-- Add Category -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Category</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="category_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Category Name
                            </label>
                            <input
                                type="text"
                                id="category_name"
                                name="category_name"
                                placeholder="e.g., Electronics"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                required>
                        </div>
                        <div>
                            <label for="category_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Category Image (Optional)
                            </label>
                            <input
                                type="file"
                                id="category_image"
                                name="category_image"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button
                            type="submit"
                            name="add_category"
                            class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                            Add Category
                        </button>
                    </form>
                </div>

                <!-- Add Subcategory -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-blue-500">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Subcategory</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Category
                            </label>
                            <select
                                id="category_id"
                                name="category_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="subcategory_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Name
                            </label>
                            <input
                                type="text"
                                id="subcategory_name"
                                name="subcategory_name"
                                placeholder="e.g., Smartphones"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                        </div>
                        <div>
                            <label for="subcategory_slug" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Slug
                            </label>
                            <input
                                type="text"
                                id="subcategory_slug"
                                name="subcategory_slug"
                                placeholder="e.g., smartphones"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                            <p class="text-xs text-gray-500 mt-1">Lowercase, numbers, hyphens only</p>
                        </div>
                        <div>
                            <label for="subcategory_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory Image (Optional)
                            </label>
                            <input
                                type="file"
                                id="subcategory_image"
                                name="subcategory_image"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button
                            type="submit"
                            name="add_subcategory"
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                            Add Subcategory
                        </button>
                    </form>
                </div>

                <!-- Add Sub-Subcategory -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-purple-500">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add Sub-Subcategory</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4" id="subSubForm">
                        <div>
                            <label for="sub_category_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Category First
                            </label>
                            <select
                                id="sub_category_select"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                onchange="filterSubcategories(this.value)">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="subcategory_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Subcategory
                            </label>
                            <select
                                id="subcategory_id"
                                name="subcategory_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                required>
                                <option value="">-- Select Subcategory --</option>
                            </select>
                        </div>
                        <div>
                            <label for="sub_subcategory_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Sub-Subcategory Name
                            </label>
                            <input
                                type="text"
                                id="sub_subcategory_name"
                                name="sub_subcategory_name"
                                placeholder="e.g., iPhone Models"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                required>
                        </div>
                        <div>
                            <label for="sub_subcategory_slug" class="block text-sm font-medium text-gray-700 mb-2">
                                Sub-Subcategory Slug
                            </label>
                            <input
                                type="text"
                                id="sub_subcategory_slug"
                                name="sub_subcategory_slug"
                                placeholder="e.g., iphone-models"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                required>
                            <p class="text-xs text-gray-500 mt-1">Lowercase, numbers, hyphens only</p>
                        </div>
                        <div>
                            <label for="sub_subcategory_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Sub-Subcategory Image (Optional)
                            </label>
                            <input
                                type="file"
                                id="sub_subcategory_image"
                                name="sub_subcategory_image"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button
                            type="submit"
                            name="add_sub_subcategory"
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                            Add Sub-Subcategory
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Current Categories Hierarchy</h2>

                <?php if (empty($display_categories)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-lg">No categories found.</div>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($display_categories as $category): ?>
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                                <div class="bg-primary text-white px-6 py-4">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center space-x-4">
                                            <?php if ($category['image_path'] || $category['image_pathtwo']): ?>
                                                <div class="flex space-x-2">
                                                    <?php if ($category['image_path']): ?>
                                                        <img
                                                            src="../../uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                                            alt="<?= htmlspecialchars($category['name']) ?>"
                                                            class="w-12 h-12 object-cover rounded">
                                                    <?php endif; ?>
                                                    <?php if ($category['image_pathtwo']): ?>
                                                        <img
                                                            src="../../uploads/categories/<?= htmlspecialchars($category['image_pathtwo']) ?>"
                                                            alt="<?= htmlspecialchars($category['name']) ?> 2"
                                                            class="w-12 h-12 object-cover rounded">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <h3 class="text-lg font-semibold"><?= htmlspecialchars($category['name']) ?></h3>
                                        </div>
                                        <div class="flex space-x-2">
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete category and all subcategories?')">
                                                <input type="hidden" name="delete_category_id" value="<?= $category['id'] ?>">
                                                <button
                                                    type="submit"
                                                    name="delete_category"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                                    Delete Category
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6">
                                    <?php if (!empty($category['subcategories'])): ?>
                                        <div class="space-y-4">
                                            <?php foreach ($category['subcategories'] as $sub): ?>
                                                <div class="border-l-4 border-blue-500 pl-4">
                                                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg mb-2">
                                                        <div class="flex items-center space-x-4 flex-1">
                                                            <div class="w-16 h-16 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                                                                <?php if ($sub['image_path']): ?>
                                                                    <img
                                                                        src="../../uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                                                        alt="<?= htmlspecialchars($sub['name']) ?>"
                                                                        class="w-full h-full object-cover">
                                                                <?php else: ?>
                                                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="flex-1 min-w-0">
                                                                <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($sub['name']) ?></div>
                                                                <div class="text-sm text-gray-500">Slug: <?= htmlspecialchars($sub['slug']) ?></div>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center space-x-2 flex-shrink-0">
                                                            <button
                                                                onclick="toggleImageUpload(<?= $sub['id'] ?>)"
                                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium">
                                                                <?= $sub['image_path'] ? 'Update' : 'Add' ?> Image
                                                            </button>

                                                            <form method="POST" class="inline" onsubmit="return confirm('Delete subcategory?')">
                                                                <input type="hidden" name="delete_subcategory_id" value="<?= $sub['id'] ?>">
                                                                <button
                                                                    type="submit"
                                                                    name="delete_subcategory"
                                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium">
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <div id="image-upload-<?= $sub['id'] ?>" class="hidden mb-4 p-4 bg-blue-50 rounded-lg">
                                                        <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                                            <input type="hidden" name="subcategory_id" value="<?= $sub['id'] ?>">
                                                            <input
                                                                type="file"
                                                                name="new_image"
                                                                accept="image/jpeg,image/png,image/gif,image/webp"
                                                                class="flex-1 px-3 py-2 border border-gray-300 rounded text-sm"
                                                                required>
                                                            <button
                                                                type="submit"
                                                                name="update_subcategory_image"
                                                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                                                Upload
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onclick="toggleImageUpload(<?= $sub['id'] ?>)"
                                                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <?php if (!empty($sub['sub_subcategories'])): ?>
                                                        <div class="ml-8 space-y-2">
                                                            <?php foreach ($sub['sub_subcategories'] as $subsub): ?>
                                                                <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                                                                    <div class="flex items-center space-x-3 flex-1">
                                                                        <div class="w-12 h-12 bg-gray-200 rounded overflow-hidden flex-shrink-0">
                                                                            <?php if ($subsub['image_path']): ?>
                                                                                <img
                                                                                    src="../../uploads/<?= htmlspecialchars($subsub['parent_slug']) ?>/<?= htmlspecialchars($subsub['slug']) ?>/<?= htmlspecialchars($subsub['image_path']) ?>"
                                                                                    alt="<?= htmlspecialchars($subsub['name']) ?>"
                                                                                    class="w-full h-full object-cover">
                                                                            <?php else: ?>
                                                                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                                                    </svg>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <div class="flex-1">
                                                                            <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($subsub['name']) ?></div>
                                                                            <div class="text-xs text-gray-500">
                                                                                Slug: <?= htmlspecialchars($subsub['slug']) ?>
                                                                                <span class="ml-2 bg-purple-100 text-purple-700 px-2 py-0.5 rounded">
                                                                                    <?= $subsub['product_count'] ?? 0 ?> products
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="flex items-center space-x-2">
                                                                        <button
                                                                            onclick="toggleSubSubImageUpload(<?= $subsub['id'] ?>)"
                                                                            class="bg-purple-500 hover:bg-purple-600 text-white px-2 py-1 rounded text-xs font-medium">
                                                                            <?= $subsub['image_path'] ? 'Update' : 'Add' ?> Image
                                                                        </button>

                                                                        <form method="POST" class="inline" onsubmit="return confirm('Delete sub-subcategory?')">
                                                                            <input type="hidden" name="delete_sub_subcategory_id" value="<?= $subsub['id'] ?>">
                                                                            <button
                                                                                type="submit"
                                                                                name="delete_sub_subcategory"
                                                                                class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs font-medium">
                                                                                Delete
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>

                                                                <div id="sub-sub-image-upload-<?= $subsub['id'] ?>" class="hidden ml-12 mb-2 p-3 bg-purple-50 rounded-lg">
                                                                    <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-3">
                                                                        <input type="hidden" name="sub_subcategory_id" value="<?= $subsub['id'] ?>">
                                                                        <input
                                                                            type="file"
                                                                            name="new_sub_sub_image"
                                                                            accept="image/jpeg,image/png,image/gif,image/webp"
                                                                            class="flex-1 px-3 py-2 border border-gray-300 rounded text-xs"
                                                                            required>
                                                                        <button
                                                                            type="submit"
                                                                            name="update_sub_subcategory_image"
                                                                            class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-xs">
                                                                            Upload
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onclick="toggleSubSubImageUpload(<?= $subsub['id'] ?>)"
                                                                            class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs">
                                                                            Cancel
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8">
                                            <div class="text-gray-400 italic">No subcategories yet</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Subcategories data for filtering
        const subcategoriesData = <?= json_encode($subcategories) ?>;

        // Auto-generate slug for subcategory
        document.getElementById('subcategory_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('subcategory_slug').value = slug;
        });

        // Auto-generate slug for sub-subcategory
        document.getElementById('sub_subcategory_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('sub_subcategory_slug').value = slug;
        });

        // Filter subcategories based on selected category
        function filterSubcategories(categoryId) {
            const subcategorySelect = document.getElementById('subcategory_id');
            subcategorySelect.innerHTML = '<option value="">-- Select Subcategory --</option>';

            if (categoryId) {
                const filtered = subcategoriesData.filter(sub => sub.category_id == categoryId);
                filtered.forEach(sub => {
                    const option = document.createElement('option');
                    option.value = sub.id;
                    option.textContent = sub.subcategory_name;
                    subcategorySelect.appendChild(option);
                });
            }
        }

        function toggleImageUpload(subcategoryId) {
            const uploadForm = document.getElementById('image-upload-' + subcategoryId);
            uploadForm.classList.toggle('hidden');
        }

        function toggleSubSubImageUpload(subSubcategoryId) {
            const uploadForm = document.getElementById('sub-sub-image-upload-' + subSubcategoryId);
            uploadForm.classList.toggle('hidden');
        }
    </script>
</body>

</html>

<?php $conn->close(); ?>