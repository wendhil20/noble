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

function createDirectory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ===== CATEGORY HANDLERS =====
if ($_POST) {
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        $category_type = trim($_POST['category_type']);
        
        if (!empty($category_name) && !empty($category_type)) {
            $category_dir = "../../uploads/categories/";
            createDirectory($category_dir);
            
            $image_path = null;
            $image_pathtwo = null;
            
            if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
                if (in_array($_FILES['category_image']['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($category_name));
                    $new_filename = $safe_name . '_' . time() . '.' . $file_extension;
                    if (move_uploaded_file($_FILES['category_image']['tmp_name'], $category_dir . $new_filename)) {
                        chmod($category_dir . $new_filename, 0644);
                        $image_path = $new_filename;
                    }
                }
            }
            
            if (isset($_FILES['category_image_two']) && $_FILES['category_image_two']['error'] == 0) {
                if (in_array($_FILES['category_image_two']['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $file_extension = pathinfo($_FILES['category_image_two']['name'], PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($category_name));
                    $new_filename = $safe_name . '_two_' . time() . '.' . $file_extension;
                    if (move_uploaded_file($_FILES['category_image_two']['tmp_name'], $category_dir . $new_filename)) {
                        chmod($category_dir . $new_filename, 0644);
                        $image_pathtwo = $new_filename;
                    }
                }
            }
            
            $sql = "INSERT INTO categories (name, type, image_path, image_pathtwo) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $category_name, $category_type, $image_path, $image_pathtwo);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category added successfully!";
            } else {
                $_SESSION['error'] = "Error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields required!";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['delete_category_id'];
        
        $get_sql = "SELECT image_path, image_pathtwo FROM categories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $category_id);
        $get_stmt->execute();
        $cat_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        // Get subcategories to delete their folders
        $get_subs_sql = "SELECT subcategory_slug FROM product_subcategories WHERE category_id = ?";
        $get_subs_stmt = $conn->prepare($get_subs_sql);
        $get_subs_stmt->bind_param("i", $category_id);
        $get_subs_stmt->execute();
        $subs_result = $get_subs_stmt->get_result();
        $slugs = [];
        while ($row = $subs_result->fetch_assoc()) {
            $slugs[] = $row['subcategory_slug'];
        }
        $get_subs_stmt->close();
        
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        
        if ($stmt->execute()) {
            $category_dir = "../../uploads/categories/";
            if ($cat_data['image_path'] && file_exists($category_dir . $cat_data['image_path'])) {
                unlink($category_dir . $cat_data['image_path']);
            }
            if ($cat_data['image_pathtwo'] && file_exists($category_dir . $cat_data['image_pathtwo'])) {
                unlink($category_dir . $cat_data['image_pathtwo']);
            }
            foreach ($slugs as $slug) {
                deleteDirectory("../../uploads/" . $slug . "/");
            }
            $_SESSION['message'] = "Category deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_category_image'])) {
        $category_id = $_POST['category_id'];
        
        $get_sql = "SELECT name, image_path FROM categories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $category_id);
        $get_stmt->execute();
        $cat_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        if (isset($_FILES['new_category_image']) && $_FILES['new_category_image']['error'] == 0) {
            $category_dir = "../../uploads/categories/";
            if ($cat_data['image_path'] && file_exists($category_dir . $cat_data['image_path'])) {
                unlink($category_dir . $cat_data['image_path']);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['new_category_image']['name'], PATHINFO_EXTENSION));
            $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($cat_data['name']));
            $new_filename = $safe_name . '_' . time() . '.' . $file_extension;
            
            if (move_uploaded_file($_FILES['new_category_image']['tmp_name'], $category_dir . $new_filename)) {
                chmod($category_dir . $new_filename, 0644);
                $update_sql = "UPDATE categories SET image_path = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_filename, $category_id);
                if ($update_stmt->execute()) {
                    $_SESSION['message'] = "Image updated!";
                } else {
                    $_SESSION['error'] = "Error: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_category_image_two'])) {
        $category_id = $_POST['category_id'];
        
        $get_sql = "SELECT name, image_pathtwo FROM categories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $category_id);
        $get_stmt->execute();
        $cat_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        if (isset($_FILES['new_category_image_two']) && $_FILES['new_category_image_two']['error'] == 0) {
            $category_dir = "../../uploads/categories/";
            if ($cat_data['image_pathtwo'] && file_exists($category_dir . $cat_data['image_pathtwo'])) {
                unlink($category_dir . $cat_data['image_pathtwo']);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['new_category_image_two']['name'], PATHINFO_EXTENSION));
            $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($cat_data['name']));
            $new_filename = $safe_name . '_two_' . time() . '.' . $file_extension;
            
            if (move_uploaded_file($_FILES['new_category_image_two']['tmp_name'], $category_dir . $new_filename)) {
                chmod($category_dir . $new_filename, 0644);
                $update_sql = "UPDATE categories SET image_pathtwo = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_filename, $category_id);
                if ($update_stmt->execute()) {
                    $_SESSION['message'] = "Image 2 updated!";
                } else {
                    $_SESSION['error'] = "Error: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_category_image_two'])) {
        $category_id = $_POST['category_id'];
        
        $get_sql = "SELECT image_pathtwo FROM categories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $category_id);
        $get_stmt->execute();
        $cat_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        $category_dir = "../../uploads/categories/";
        if ($cat_data['image_pathtwo'] && file_exists($category_dir . $cat_data['image_pathtwo'])) {
            unlink($category_dir . $cat_data['image_pathtwo']);
        }
        
        $update_sql = "UPDATE categories SET image_pathtwo = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $category_id);
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Image 2 deleted!";
        }
        $update_stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // ===== SUBCATEGORY HANDLERS =====
    if (isset($_POST['add_subcategory'])) {
        $category_id = $_POST['category_id'];
        $subcategory_name = trim($_POST['subcategory_name']);
        $subcategory_slug = trim($_POST['subcategory_slug']);
        
        if (!empty($subcategory_name) && !empty($subcategory_slug) && !empty($category_id)) {
            $check_sql = "SELECT id FROM product_subcategories WHERE subcategory_slug = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $subcategory_slug);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Slug already exists!";
                $check_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_stmt->close();
            
            $upload_dir = "../../uploads/" . $subcategory_slug . "/";
            createDirectory($upload_dir);
            
            $image_path = null;
            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] == 0) {
                if (in_array($_FILES['subcategory_image']['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $file_extension = pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $subcategory_slug . '_main.' . $file_extension;
                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_dir . $new_filename)) {
                        $image_path = $new_filename;
                    }
                }
            }
            
            $sql = "INSERT INTO product_subcategories (category_id, subcategory_name, subcategory_slug, image_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $category_id, $subcategory_name, $subcategory_slug, $image_path);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Subcategory added!";
            } else {
                $_SESSION['error'] = "Error: " . $conn->error;
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields required!";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_subcategory'])) {
        $subcategory_id = $_POST['delete_subcategory_id'];
        
        $get_sql = "SELECT subcategory_slug FROM product_subcategories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $subcategory_id);
        $get_stmt->execute();
        $row = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        $sql = "DELETE FROM product_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $subcategory_id);
        
        if ($stmt->execute()) {
            if ($row['subcategory_slug']) {
                deleteDirectory("../../uploads/" . $row['subcategory_slug'] . "/");
            }
            $_SESSION['message'] = "Subcategory deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_subcategory_image'])) {
        $subcategory_id = $_POST['subcategory_id'];
        
        $get_sql = "SELECT subcategory_slug, image_path FROM product_subcategories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $subcategory_id);
        $get_stmt->execute();
        $sub_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
            $upload_dir = "../../uploads/" . $sub_data['subcategory_slug'] . "/";
            if ($sub_data['image_path'] && file_exists($upload_dir . $sub_data['image_path'])) {
                unlink($upload_dir . $sub_data['image_path']);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
            $new_filename = $sub_data['subcategory_slug'] . '_main.' . $file_extension;
            
            if (move_uploaded_file($_FILES['new_image']['tmp_name'], $upload_dir . $new_filename)) {
                chmod($upload_dir . $new_filename, 0644);
                $update_sql = "UPDATE product_subcategories SET image_path = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_filename, $subcategory_id);
                if ($update_stmt->execute()) {
                    $_SESSION['message'] = "Image updated!";
                }
                $update_stmt->close();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // ===== SUB-SUBCATEGORY HANDLERS =====
    if (isset($_POST['add_sub_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $sub_subcategory_name = trim($_POST['sub_subcategory_name']);
        $sub_subcategory_slug = trim($_POST['sub_subcategory_slug']);
        
        if (!empty($sub_subcategory_name) && !empty($sub_subcategory_slug) && !empty($subcategory_id)) {
            $check_sql = "SELECT id FROM product_sub_subcategories WHERE sub_subcategory_slug = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $sub_subcategory_slug);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Slug already exists!";
                $check_stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $check_stmt->close();
            
            $upload_dir = "../../uploads/sub_subcategories/";
            createDirectory($upload_dir);
            
            $image_path = null;
            if (isset($_FILES['sub_subcategory_image']) && $_FILES['sub_subcategory_image']['error'] == 0) {
                if (in_array($_FILES['sub_subcategory_image']['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $file_extension = pathinfo($_FILES['sub_subcategory_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $sub_subcategory_slug . '_' . time() . '.' . $file_extension;
                    if (move_uploaded_file($_FILES['sub_subcategory_image']['tmp_name'], $upload_dir . $new_filename)) {
                        chmod($upload_dir . $new_filename, 0644);
                        $image_path = $new_filename;
                    }
                }
            }
            
            $sql = "INSERT INTO product_sub_subcategories (subcategory_id, sub_subcategory_name, sub_subcategory_slug, image_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $subcategory_id, $sub_subcategory_name, $sub_subcategory_slug, $image_path);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Sub-subcategory added!";
            } else {
                $_SESSION['error'] = "Error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields required!";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_sub_subcategory'])) {
        $sub_subcategory_id = $_POST['delete_sub_subcategory_id'];
        
        $get_sql = "SELECT image_path FROM product_sub_subcategories WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $sub_subcategory_id);
        $get_stmt->execute();
        $row = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        $sql = "DELETE FROM product_sub_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sub_subcategory_id);
        
        if ($stmt->execute()) {
            if ($row['image_path'] && file_exists("../../uploads/sub_subcategories/" . $row['image_path'])) {
                unlink("../../uploads/sub_subcategories/" . $row['image_path']);
            }
            $_SESSION['message'] = "Sub-subcategory deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ===== FETCH DATA =====
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

$display_sql = "SELECT 
    c.id as category_id,
    c.name as category_name,
    c.type as category_type,
    c.image_path as category_image,
    c.image_pathtwo as category_image_two,
    ps.id as subcategory_id,
    ps.subcategory_name,
    ps.subcategory_slug,
    ps.image_path as sub_image,
    pss.id as sub_subcategory_id,
    pss.sub_subcategory_name,
    pss.sub_subcategory_slug,
    pss.image_path as sub_sub_image
FROM categories c
LEFT JOIN product_subcategories ps ON c.id = ps.category_id
LEFT JOIN product_sub_subcategories pss ON ps.id = pss.subcategory_id
ORDER BY c.name, ps.subcategory_name, pss.sub_subcategory_name";

$display_result = $conn->query($display_sql);
$display_categories = [];

while ($row = $display_result->fetch_assoc()) {
    $cat_name = $row['category_name'];
    
    if (!isset($display_categories[$cat_name])) {
        $display_categories[$cat_name] = [
            'id' => $row['category_id'],
            'name' => $cat_name,
            'type' => $row['category_type'],
            'image_path' => $row['category_image'],
            'image_pathtwo' => $row['category_image_two'],
            'subcategories' => []
        ];
    }
    
    if ($row['subcategory_id']) {
        $sub_key = $row['subcategory_id'];
        
        if (!isset($display_categories[$cat_name]['subcategories'][$sub_key])) {
            $display_categories[$cat_name]['subcategories'][$sub_key] = [
                'id' => $row['subcategory_id'],
                'name' => $row['subcategory_name'],
                'slug' => $row['subcategory_slug'],
                'image_path' => $row['sub_image'],
                'sub_subcategories' => []
            ];
        }
        
        if ($row['sub_subcategory_id']) {
            $display_categories[$cat_name]['subcategories'][$sub_key]['sub_subcategories'][] = [
                'id' => $row['sub_subcategory_id'],
                'name' => $row['sub_subcategory_name'],
                'slug' => $row['sub_subcategory_slug'],
                'image_path' => $row['sub_sub_image']
            ];
        }
    }
}

foreach ($display_categories as &$category) {
    $category['subcategories'] = array_values($category['subcategories']);
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
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Category Management</h1>
            <a href="category-linking.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                Manage Links
            </a>
        </div>
        
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

        <!-- Add Category Form -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                <h2 class="text-xl font-semibold mb-4">Add Category</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="text" name="category_name" placeholder="Category Name" required class="w-full px-4 py-2 border rounded-lg">
                    <select name="category_type" required class="w-full px-4 py-2 border rounded-lg">
                        <option>Select Type</option>
                        <option value="hotel">Hotel</option>
                        <option value="office">Office</option>
                        <option value="residential">Residential</option>
                        <option value="retail">Retail</option>
                    </select>
                    <input type="file" name="category_image" accept="image/*" class="w-full px-4 py-2 border rounded-lg">
                    <input type="file" name="category_image_two" accept="image/*" class="w-full px-4 py-2 border rounded-lg">
                    <button type="submit" name="add_category" class="w-full bg-primary text-white px-4 py-2 rounded-lg">Add Category</button>
                </form>
            </div>

            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                <h2 class="text-xl font-semibold mb-4">Add Subcategory</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <select name="category_id" required class="w-full px-4 py-2 border rounded-lg">
                        <option>-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="subcategory_name" placeholder="Subcategory Name" required class="w-full px-4 py-2 border rounded-lg">
                    <input type="text" name="subcategory_slug" placeholder="Slug" required class="w-full px-4 py-2 border rounded-lg">
                    <input type="file" name="subcategory_image" accept="image/*" class="w-full px-4 py-2 border rounded-lg">
                    <button type="submit" name="add_subcategory" class="w-full bg-primary text-white px-4 py-2 rounded-lg">Add Subcategory</button>
                </form>
            </div>
        </div>

        <!-- Categories Display -->
        <div class="space-y-6">
            <?php foreach ($display_categories as $category): ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-primary text-white px-6 py-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold"><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</h3>
                        </div>
                        <div class="space-x-2">
                            <button onclick="toggleCatImageUpload(<?= $category['id'] ?>)" class="bg-blue-500 hover:bg-blue-600 px-3 py-1 rounded text-sm">Update Img 1</button>
                            <button onclick="toggleCatImageTwoUpload(<?= $category['id'] ?>)" class="bg-purple-500 hover:bg-purple-600 px-3 py-1 rounded text-sm">Update Img 2</button>
                            <?php if ($category['image_pathtwo']): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete image 2?')">
                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                    <button type="submit" name="delete_category_image_two" class="bg-orange-500 hover:bg-orange-600 px-3 py-1 rounded text-sm">Delete Img 2</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete category?')">
                                <input type="hidden" name="delete_category_id" value="<?= $category['id'] ?>">
                                <button type="submit" name="delete_category" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Category Image Upload Forms -->
                    <div id="cat-image-upload-<?= $category['id'] ?>" class="hidden bg-blue-100 p-4 border-b">
                        <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                            <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                            <input type="file" name="new_category_image" required class="flex-1 px-3 py-2 border rounded">
                            <button type="submit" name="update_category_image" class="bg-blue-500 text-white px-4 py-2 rounded">Upload</button>
                            <button type="button" onclick="toggleCatImageUpload(<?= $category['id'] ?>)" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                        </form>
                    </div>
                    
                    <div id="cat-image-two-upload-<?= $category['id'] ?>" class="hidden bg-purple-100 p-4 border-b">
                        <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                            <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                            <input type="file" name="new_category_image_two" required class="flex-1 px-3 py-2 border rounded">
                            <button type="submit" name="update_category_image_two" class="bg-purple-500 text-white px-4 py-2 rounded">Upload</button>
                            <button type="button" onclick="toggleCatImageTwoUpload(<?= $category['id'] ?>)" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                        </form>
                    </div>
                    
                    <div class="p-6">
                        <!-- Subcategories -->
                        <?php if (!empty($category['subcategories'])): ?>
                            <div class="space-y-4">
                                <?php foreach ($category['subcategories'] as $sub): ?>
                                    <div class="border border-gray-300 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <div>
                                                <h5 class="font-semibold text-gray-900"><?= htmlspecialchars($sub['name']) ?></h5>
                                                <p class="text-xs text-gray-500">Slug: <?= htmlspecialchars($sub['slug']) ?></p>
                                            </div>
                                            <div class="space-x-2">
                                                <button onclick="toggleSubImageUpload(<?= $sub['id'] ?>)" class="bg-blue-500 text-white px-3 py-1 rounded text-sm">Update Image</button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete subcategory?')">
                                                    <input type="hidden" name="delete_subcategory_id" value="<?= $sub['id'] ?>">
                                                    <button type="submit" name="delete_subcategory" class="bg-red-500 text-white px-3 py-1 rounded text-sm">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Sub Image Upload Form -->
                                        <div id="sub-image-upload-<?= $sub['id'] ?>" class="hidden bg-blue-100 p-3 rounded mb-3">
                                            <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                                                <input type="hidden" name="subcategory_id" value="<?= $sub['id'] ?>">
                                                <input type="file" name="new_image" required class="flex-1 px-3 py-2 border rounded text-sm">
                                                <button type="submit" name="update_subcategory_image" class="bg-blue-500 text-white px-3 py-2 rounded text-sm">Upload</button>
                                                <button type="button" onclick="toggleSubImageUpload(<?= $sub['id'] ?>)" class="bg-gray-500 text-white px-3 py-2 rounded text-sm">Cancel</button>
                                            </form>
                                        </div>

                                        <!-- Add Sub-Subcategory Form -->
                                        <div class="bg-purple-50 p-3 rounded mb-4">
                                            <h6 class="font-semibold text-xs mb-2">Add Sub-Subcategory</h6>
                                            <form method="POST" enctype="multipart/form-data" class="space-y-2">
                                                <input type="hidden" name="subcategory_id" value="<?= $sub['id'] ?>">
                                                <input type="text" name="sub_subcategory_name" placeholder="Name" required class="w-full px-2 py-1 border rounded text-xs">
                                                <input type="text" name="sub_subcategory_slug" placeholder="Slug" required class="w-full px-2 py-1 border rounded text-xs">
                                                <input type="file" name="sub_subcategory_image" class="w-full px-2 py-1 border rounded text-xs">
                                                <button type="submit" name="add_sub_subcategory" class="w-full bg-purple-500 text-white px-2 py-1 rounded text-xs">Add Sub-Subcategory</button>
                                            </form>
                                        </div>

                                        <!-- Sub-Subcategories List -->
                                        <?php if (!empty($sub['sub_subcategories'])): ?>
                                            <div class="space-y-2 bg-gray-50 p-3 rounded">
                                                <p class="font-semibold text-xs">Sub-Subcategories:</p>
                                                <?php foreach ($sub['sub_subcategories'] as $subsub): ?>
                                                    <div class="flex justify-between items-center p-2 bg-white rounded border border-gray-200">
                                                        <div>
                                                            <p class="font-medium text-xs"><?= htmlspecialchars($subsub['name']) ?></p>
                                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($subsub['slug']) ?></p>
                                                        </div>
                                                        <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                                                            <input type="hidden" name="delete_sub_subcategory_id" value="<?= $subsub['id'] ?>">
                                                            <button type="submit" name="delete_sub_subcategory" class="bg-red-500 text-white px-2 py-1 rounded text-xs">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 italic">No sub-subcategories</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 italic">No subcategories</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function toggleCatImageUpload(id) {
        document.getElementById('cat-image-upload-' + id).classList.toggle('hidden');
    }
    
    function toggleCatImageTwoUpload(id) {
        document.getElementById('cat-image-two-upload-' + id).classList.toggle('hidden');
    }
    
    function toggleSubImageUpload(id) {
        document.getElementById('sub-image-upload-' + id).classList.toggle('hidden');
    }
</script>

</body>
</html>

<?php $conn->close(); ?>