<?php
// main-category-product-page.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$message = "";
$error = "";

// Auto-increment reset
$tables = ['categories', 'product_subcategories', 'product_sub_subcategories'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int) $row['max_id'];
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


include ROOT_PATH . "/user/navbar/main-tag-helpers.php";
$tag_options = get_tag_options($conn);

function createDirectory($path)
{
    if (!file_exists($path))
        return mkdir($path, 0755, true);
    return true;
}

function deleteDirectory($dir)
{
    if (!file_exists($dir))
        return true;
    if (!is_dir($dir))
        return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..')
            continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
            return false;
    }
    return rmdir($dir);
}

if ($_POST) {

    // ── Update Tag: Category ───────────────────────────
    if (isset($_POST['update_tag_category'])) {
        $id = (int) $_POST['category_id'];
        $tag = $_POST['tag'];
        $valid_tags = array_keys($tag_options); // galing na sa DB
if (in_array($tag, $valid_tags)) {
            $stmt = $conn->prepare("UPDATE categories SET tag = ? WHERE id = ?");
            $stmt->bind_param("si", $tag, $id);
            $_SESSION[$stmt->execute() ? 'message' : 'error'] = $stmt->execute()
                ? "Category tag updated!"
                : "Error: " . $conn->error;
            $stmt->close();
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // ── Update Tag: Subcategory ────────────────────────
    if (isset($_POST['update_tag_sub'])) {
        $id = (int) $_POST['sub_id'];
        $tag = $_POST['tag'];
    $valid_tags = array_keys($tag_options); // galing na sa DB
if (in_array($tag, $valid_tags)) {
            $stmt = $conn->prepare("UPDATE product_subcategories SET tag = ? WHERE id = ?");
            $stmt->bind_param("si", $tag, $id);
            $_SESSION[$stmt->execute() ? 'message' : 'error'] = $stmt->execute()
                ? "Subcategory tag updated!"
                : "Error: " . $conn->error;
            $stmt->close();
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // ── Update Tag: Sub-Subcategory ────────────────────
    if (isset($_POST['update_tag_subsub'])) {
        $id = (int) $_POST['subsub_id'];
        $tag = $_POST['tag'];
    $valid_tags = array_keys($tag_options); // galing na sa DB
if (in_array($tag, $valid_tags)) {
            $stmt = $conn->prepare("UPDATE product_sub_subcategories SET tag = ? WHERE id = ?");
            $stmt->bind_param("si", $tag, $id);
            $_SESSION[$stmt->execute() ? 'message' : 'error'] = $stmt->execute()
                ? "Sub-subcategory tag updated!"
                : "Error: " . $conn->error;
            $stmt->close();
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
          $category_dir = ROOT_PATH . "/uploads/categories/";
            createDirectory($category_dir);
            $image_path = null;
            if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['category_image']['type'], $allowed_types)) {
                    $ext = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($category_name));
                    $filename = $safe_name . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['category_image']['tmp_name'], $category_dir . $filename)) {
                        chmod($category_dir . $filename, 0644);
                        $image_path = $filename;
                    }
                }
            }
            $stmt = $conn->prepare("INSERT INTO categories (name, image_path, image_pathtwo) VALUES (?, ?, NULL)");
            $stmt->bind_param("ss", $category_name, $image_path);
            $_SESSION[$stmt->execute() ? 'message' : 'error'] = $stmt->execute()
                ? "Category '$category_name' added!"
                : "Error: " . $conn->error;
            $stmt->close();
        } else {
            $_SESSION['error'] = "Category name cannot be empty!";
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Add Subcategory
    if (isset($_POST['add_subcategory'])) {
        $category_id = $_POST['category_id'];
        $subcategory_name = trim($_POST['subcategory_name']);
        $subcategory_slug = trim($_POST['subcategory_slug']);
        if (!empty($subcategory_name) && !empty($subcategory_slug) && !empty($category_id)) {
            $get = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $get->bind_param("i", $category_id);
            $get->execute();
            $cat_name = $get->get_result()->fetch_assoc()['name'];
            $get->close();
            $cat_slug_part = strtolower(preg_replace('/[^a-z0-9\s-]/', '', str_replace(' ', '-', $cat_name)));
            $final_slug = $cat_slug_part . '-' . $subcategory_slug;

            $chk = $conn->prepare("SELECT id FROM product_subcategories WHERE category_id=? AND subcategory_name=?");
            $chk->bind_param("is", $category_id, $subcategory_name);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Subcategory '$subcategory_name' already exists!";
                $chk->close();
                header("Location: " . BASE_URL . "/category");
                exit();
            }
            $chk->close();

            $chk2 = $conn->prepare("SELECT id FROM product_subcategories WHERE subcategory_slug=?");
            $chk2->bind_param("s", $final_slug);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Slug combination already exists!";
                $chk2->close();
                header("Location: " . BASE_URL . "/category");
                exit();
            }
            $chk2->close();

            $upload_dir = ROOT_PATH . "/uploads/" . $final_slug . "/";
            createDirectory($upload_dir);
            $image_path = null;
            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] == 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['subcategory_image']['type'], $allowed)) {
                    $ext = pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION);
                    $filename = $final_slug . '_main.' . $ext;
                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_dir . $filename))
                        $image_path = $filename;
                }
            }
            $stmt = $conn->prepare("INSERT INTO product_subcategories (category_id, subcategory_name, subcategory_slug, image_path) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $category_id, $subcategory_name, $final_slug, $image_path);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Subcategory '$subcategory_name' added!";
            } else {
                $_SESSION['error'] = "Error: " . $conn->error;
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required!";
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Add Sub-Subcategory
    if (isset($_POST['add_sub_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $sub_subcategory_name = trim($_POST['sub_subcategory_name']);
        $sub_subcategory_slug = trim($_POST['sub_subcategory_slug']);
        if (!empty($sub_subcategory_name) && !empty($sub_subcategory_slug) && !empty($subcategory_id)) {
            $chk = $conn->prepare("SELECT id FROM product_sub_subcategories WHERE subcategory_id=? AND sub_subcategory_name=?");
            $chk->bind_param("is", $subcategory_id, $sub_subcategory_name);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Sub-subcategory '$sub_subcategory_name' already exists!";
                $chk->close();
                header("Location: " . BASE_URL . "/category");
                exit();
            }
            $chk->close();

            $chk2 = $conn->prepare("SELECT id FROM product_sub_subcategories WHERE sub_subcategory_slug=?");
            $chk2->bind_param("s", $sub_subcategory_slug);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Slug '$sub_subcategory_slug' already exists!";
                $chk2->close();
                header("Location: " . BASE_URL . "/category");
                exit();
            }
            $chk2->close();

            $get = $conn->prepare("SELECT subcategory_slug FROM product_subcategories WHERE id=?");
            $get->bind_param("i", $subcategory_id);
            $get->execute();
            $parent_slug = $get->get_result()->fetch_assoc()['subcategory_slug'];
            $get->close();

            $upload_dir = ROOT_PATH . "/uploads/" . $parent_slug . "/" . $sub_subcategory_slug . "/";
            createDirectory($upload_dir);
            $image_path = null;
            if (isset($_FILES['sub_subcategory_image']) && $_FILES['sub_subcategory_image']['error'] == 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['sub_subcategory_image']['type'], $allowed)) {
                    $ext = pathinfo($_FILES['sub_subcategory_image']['name'], PATHINFO_EXTENSION);
                    $filename = $sub_subcategory_slug . '_main.' . $ext;
                    if (move_uploaded_file($_FILES['sub_subcategory_image']['tmp_name'], $upload_dir . $filename))
                        $image_path = $filename;
                }
            }
            $stmt = $conn->prepare("INSERT INTO product_sub_subcategories (subcategory_id, sub_subcategory_name, sub_subcategory_slug, image_path) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $subcategory_id, $sub_subcategory_name, $sub_subcategory_slug, $image_path);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Sub-subcategory '$sub_subcategory_name' added!";
            } else {
                $_SESSION['error'] = "Error: " . $conn->error;
                deleteDirectory($upload_dir);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required!";
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Delete Sub-Subcategory
    if (isset($_POST['delete_sub_subcategory'])) {
        $id = (int) $_POST['delete_sub_subcategory_id'];
        $get = $conn->prepare("SELECT pss.sub_subcategory_slug, ps.subcategory_slug FROM product_sub_subcategories pss JOIN product_subcategories ps ON pss.subcategory_id=ps.id WHERE pss.id=?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();
        $stmt = $conn->prepare("DELETE FROM product_sub_subcategories WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($data)
                deleteDirectory(ROOT_PATH . "/uploads/" . $data['subcategory_slug'] . "/" . $data['sub_subcategory_slug'] . "/");
            $_SESSION['message'] = "Sub-subcategory deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Delete Subcategory
    if (isset($_POST['delete_subcategory'])) {
        $id = (int) $_POST['delete_subcategory_id'];
        $get = $conn->prepare("SELECT subcategory_slug FROM product_subcategories WHERE id=?");
        $get->bind_param("i", $id);
        $get->execute();
        $slug = $get->get_result()->fetch_assoc()['subcategory_slug'] ?? null;
        $get->close();
        $stmt = $conn->prepare("DELETE FROM product_subcategories WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($slug)
                deleteDirectory("../../uploads/" . $slug . "/");
            $_SESSION['message'] = "Subcategory deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Delete Category
    if (isset($_POST['delete_category'])) {
        $id = (int) $_POST['delete_category_id'];
        $get = $conn->prepare("SELECT image_path, image_pathtwo FROM categories WHERE id=?");
        $get->bind_param("i", $id);
        $get->execute();
        $cat = $get->get_result()->fetch_assoc();
        $get->close();
        $get2 = $conn->prepare("SELECT subcategory_slug FROM product_subcategories WHERE category_id=?");
        $get2->bind_param("i", $id);
        $get2->execute();
        $res2 = $get2->get_result();
        $slugs = [];
        while ($r = $res2->fetch_assoc())
            $slugs[] = $r['subcategory_slug'];
        $get2->close();
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($cat) {
                if ($cat['image_path'] && file_exists(ROOT_PATH . "/uploads/categories/" . $cat['image_path']))
                    unlink(ROOT_PATH . "/uploads/categories/" . $cat['image_path']);
                if ($cat['image_pathtwo'] && file_exists(ROOT_PATH . "/uploads/categories/" . $cat['image_pathtwo']))
                    unlink(ROOT_PATH . "/uploads/categories/" . $cat['image_pathtwo']);
            }
            foreach ($slugs as $slug)
                deleteDirectory(ROOT_PATH . "/uploads/" . $slug . "/");
            $_SESSION['message'] = "Category deleted!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
        $stmt->close();
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Update Subcategory Image
    if (isset($_POST['update_subcategory_image'])) {
        $id = (int) $_POST['subcategory_id'];
        $get = $conn->prepare("SELECT subcategory_slug, image_path FROM product_subcategories WHERE id=?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();
        if ($data && isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['new_image']['type'], $allowed)) {
                $upload_dir = ROOT_PATH . "/uploads/" . $final_slug . "/";

                createDirectory($upload_dir);
                if ($data['image_path'] && file_exists($upload_dir . $data['image_path']))
                    unlink($upload_dir . $data['image_path']);
                $ext = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
                $filename = $data['subcategory_slug'] . '_main.' . $ext;
                if (move_uploaded_file($_FILES['new_image']['tmp_name'], $upload_dir . $filename)) {
                    chmod($upload_dir . $filename, 0644);
                    $upd = $conn->prepare("UPDATE product_subcategories SET image_path=? WHERE id=?");
                    $upd->bind_param("si", $filename, $id);
                    $_SESSION[$upd->execute() ? 'message' : 'error'] = $upd->execute() ? "Image updated!" : "Error: " . $conn->error;
                    $upd->close();
                }
            }
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Update Sub-Subcategory Image
    if (isset($_POST['update_sub_subcategory_image'])) {
        $id = (int) $_POST['sub_subcategory_id'];
        $get = $conn->prepare("SELECT pss.sub_subcategory_slug, pss.image_path, ps.subcategory_slug FROM product_sub_subcategories pss JOIN product_subcategories ps ON pss.subcategory_id=ps.id WHERE pss.id=?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();
        if ($data && isset($_FILES['new_sub_sub_image']) && $_FILES['new_sub_sub_image']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['new_sub_sub_image']['type'], $allowed)) {
                $upload_dir = ROOT_PATH . "/uploads/" . $data['subcategory_slug'] . "/" . $data['sub_subcategory_slug'] . "/";
                createDirectory($upload_dir);
                if ($data['image_path'] && file_exists($upload_dir . $data['image_path']))
                    unlink($upload_dir . $data['image_path']);
                $ext = strtolower(pathinfo($_FILES['new_sub_sub_image']['name'], PATHINFO_EXTENSION));
                $filename = $data['sub_subcategory_slug'] . '_main.' . $ext;
                if (move_uploaded_file($_FILES['new_sub_sub_image']['tmp_name'], $upload_dir . $filename)) {
                    chmod($upload_dir . $filename, 0644);
                    $upd = $conn->prepare("UPDATE product_sub_subcategories SET image_path=? WHERE id=?");
                    $upd->bind_param("si", $filename, $id);
                    $_SESSION[$upd->execute() ? 'message' : 'error'] = $upd->execute() ? "Image updated!" : "Error: " . $conn->error;
                    $upd->close();
                }
            }
        }
        header("Location: " . BASE_URL . "/category");
        exit();
    }

    // Update Category Images
    foreach (['update_category_image' => ['image_path', 'new_category_image'], 'update_category_image_two' => ['image_pathtwo', 'new_category_image_two']] as $action => $cfg) {
        if (isset($_POST[$action])) {
            $id = (int) $_POST['category_id'];
            $col = $cfg[0];
            $file_key = $cfg[1];
            $get = $conn->prepare("SELECT $col FROM categories WHERE id=?");
            $get->bind_param("i", $id);
            $get->execute();
            $cur = $get->get_result()->fetch_assoc()[$col] ?? null;
            $get->close();
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES[$file_key]['type'], $allowed)) {
                    $upload_dir = "../../uploads/categories/";
                    createDirectory($upload_dir);
                    if ($cur && file_exists($upload_dir . $cur))
                        unlink($upload_dir . $cur);
                    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                    $suffix = ($col == 'image_pathtwo') ? '_two_' : '_';
                    $filename = 'category_' . $id . $suffix . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $filename)) {
                        chmod($upload_dir . $filename, 0644);
                        $upd = $conn->prepare("UPDATE categories SET $col=? WHERE id=?");
                        $upd->bind_param("si", $filename, $id);
                        $_SESSION[$upd->execute() ? 'message' : 'error'] = $upd->execute() ? "Image updated!" : "Error: " . $conn->error;
                        $upd->close();
                    }
                }
            }
            header("Location: " . BASE_URL . "/category");
            exit();
        }
    }
}

// ── Fetch data ──────────────────────────────────────────
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc())
    $categories[] = $row;

$subcategories_result = $conn->query("SELECT id, category_id, subcategory_name FROM product_subcategories ORDER BY subcategory_name");
$subcategories = [];
while ($row = $subcategories_result->fetch_assoc())
    $subcategories[] = $row;

// Display query — includes tag for all 3 levels
$display_sql = "
  SELECT
    c.id AS category_id, c.name AS category_name,
    c.image_path AS category_image, c.image_pathtwo AS category_image_two,
    c.tag AS category_tag,
    ps.id AS subcategory_id, ps.subcategory_name, ps.subcategory_slug,
    ps.image_path AS sub_image_path,
    ps.tag AS sub_tag,
    pss.id AS sub_subcategory_id, pss.sub_subcategory_name, pss.sub_subcategory_slug,
    pss.image_path AS sub_sub_image_path,
    pss.tag AS subsub_tag,
    COUNT(DISTINCT pssl.product_id) AS product_count
  FROM categories c
  LEFT JOIN product_subcategories ps ON c.id = ps.category_id
  LEFT JOIN product_sub_subcategories pss ON ps.id = pss.subcategory_id
  LEFT JOIN product_sub_subcategory_links pssl ON pss.id = pssl.sub_subcategory_id
  GROUP BY c.id, ps.id, pss.id
  ORDER BY c.name, ps.subcategory_name, pss.sub_subcategory_name
";

$display_result = $conn->query($display_sql);
$display_categories = [];
while ($row = $display_result->fetch_assoc()) {
    $cat_name = $row['category_name'];
    if (!isset($display_categories[$cat_name])) {
        $display_categories[$cat_name] = [
            'id' => $row['category_id'],
            'name' => $cat_name,
            'image_path' => $row['category_image'],
            'image_pathtwo' => $row['category_image_two'],
            'tag' => $row['category_tag'] ?? 'normal',
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
                'image_path' => $row['sub_image_path'],
                'tag' => $row['sub_tag'] ?? 'normal',
                'sub_subcategories' => []
            ];
        }
        if ($row['sub_subcategory_id']) {
            $display_categories[$cat_name]['subcategories'][$sub_key]['sub_subcategories'][] = [
                'id' => $row['sub_subcategory_id'],
                'name' => $row['sub_subcategory_name'],
                'slug' => $row['sub_subcategory_slug'],
                'image_path' => $row['sub_sub_image_path'],
                'parent_slug' => $row['subcategory_slug'],
                'product_count' => (int) $row['product_count'],
                'tag' => $row['subsub_tag'] ?? 'normal',
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
    <style>
        /* Tag select pill */
        .tag-select {
            font-size: 12px;
            font-weight: 600;
            border-radius: 99px;
            padding: 3px 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            cursor: pointer;
            outline: none;
            transition: box-shadow .15s;
            color: black;
        }

        .tag-select:focus {
            box-shadow: 0 0 0 2px #fb923c55;
        }

        .tag-select.tag-best_offer {
            background: #fff3e0;
            color: #e65100;
            border-color: #ffcc80;
        }

        .tag-select.tag-new_arrival {
            background: #e8f5e9;
            color: #1b5e20;
            border-color: #a5d6a7;
        }

        .tag-select.tag-sale {
            background: #fce4ec;
            color: #880e4f;
            border-color: #f48fb1;
        }

        .tag-select.tag-normal {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

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

            <!-- ── ADD FORMS ──────────────────────────────── -->
            <div class="grid md:grid-cols-3 gap-6 mb-8">

                <!-- Add Category -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Category</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                            <input type="text" name="category_name" placeholder="e.g., Electronics"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category Image
                                (Optional)</label>
                            <input type="file" name="category_image" accept="image/*"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" name="add_category"
                            class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition">
                            Add Category
                        </button>
                    </form>
                </div>

                <!-- Add Subcategory -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-blue-500">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Subcategory</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Category</label>
                            <select name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subcategory Name</label>
                            <input type="text" id="subcategory_name" name="subcategory_name"
                                placeholder="e.g., Smartphones"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subcategory Slug</label>
                            <input type="text" id="subcategory_slug" name="subcategory_slug"
                                placeholder="e.g., smartphones"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                            <p class="text-xs text-gray-500 mt-1">Lowercase, numbers, hyphens only</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Image (Optional)</label>
                            <input type="file" name="subcategory_image" accept="image/*"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" name="add_subcategory"
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition">
                            Add Subcategory
                        </button>
                    </form>
                </div>

                <!-- Add Sub-Subcategory -->
                <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-purple-500">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add Sub-Subcategory</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Category First</label>
                            <select class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                onchange="filterSubcategories(this.value)">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                                <option value="">-- Select Subcategory --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sub-Subcategory Name</label>
                            <input type="text" id="sub_subcategory_name" name="sub_subcategory_name"
                                placeholder="e.g., iPhone Models"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                            <input type="text" id="sub_subcategory_slug" name="sub_subcategory_slug"
                                placeholder="e.g., iphone-models"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                            <p class="text-xs text-gray-500 mt-1">Lowercase, numbers, hyphens only</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Image (Optional)</label>
                            <input type="file" name="sub_subcategory_image" accept="image/*"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" name="add_sub_subcategory"
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white font-medium py-2 px-4 rounded-lg transition">
                            Add Sub-Subcategory
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── HIERARCHY DISPLAY ──────────────────────────── -->
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Current Categories Hierarchy</h2>

            <?php if (empty($display_categories)): ?>
                <div class="text-center py-12 text-gray-400">No categories found.</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($display_categories as $category): ?>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">

                            <!-- Category Header -->
                            <div class="bg-primary text-white px-6 py-4">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center space-x-4">
                                        <?php if ($category['image_path'] || $category['image_pathtwo']): ?>
                                            <div class="flex space-x-2">
                                                <?php if ($category['image_path']): ?>
                                                    <img src="<?= BASE_URL ?>/uploads/categories/<?= htmlspecialchars($category['image_path']) ?>"
                                                        class="w-12 h-12 object-cover rounded">
                                                <?php endif; ?>
                                                <?php if ($category['image_pathtwo']): ?>
                                                    <img src="<?= BASE_URL ?>/uploads/categories/<?= htmlspecialchars($category['image_pathtwo']) ?>"
                                                        class="w-12 h-12 object-cover rounded">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h3 class="text-lg font-semib   old text-black"><?= htmlspecialchars($category['name']) ?>
                                            </h3>
                                            <!-- Tag selector for Category -->
                                            <form method="POST" class="inline mt-1">
                                                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                <select name="tag" onchange="this.form.submit()"
                                                    class="tag-select tag-<?= htmlspecialchars($category['tag']) ?>">
                                                    <?php foreach ($tag_options as $val => $opt): ?>
                                                        <option value="<?= $val ?>" <?= $category['tag'] === $val ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($opt['label']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="update_tag_category" value="1">
                                            </form>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button onclick="toggleCategoryImageUpload(<?= $category['id'] ?>)"
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                            <?= $category['image_path'] ? 'Update' : 'Add' ?> Image 1
                                        </button>
                                        <button onclick="toggleCategoryImageUploadTwo(<?= $category['id'] ?>)"
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                            <?= $category['image_pathtwo'] ? 'Update' : 'Add' ?> Image 2
                                        </button>
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('Delete category and all subcategories?')">
                                            <input type="hidden" name="delete_category_id" value="<?= $category['id'] ?>">
                                            <button type="submit" name="delete_category"
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Category Image Uploads -->
                            <div id="category-image-upload-<?= $category['id'] ?>" class="hidden p-4 bg-blue-50 m-4 rounded-lg">
                                <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                    <input type="file" name="new_category_image" accept="image/*"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded text-sm" required>
                                    <button type="submit" name="update_category_image"
                                        class="bg-blue-500 text-white px-4 py-2 rounded text-sm">Upload</button>
                                    <button type="button" onclick="toggleCategoryImageUpload(<?= $category['id'] ?>)"
                                        class="bg-gray-500 text-white px-4 py-2 rounded text-sm">Cancel</button>
                                </form>
                            </div>
                            <div id="category-image-upload-two-<?= $category['id'] ?>"
                                class="hidden p-4 bg-green-50 m-4 rounded-lg">
                                <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                    <input type="file" name="new_category_image_two" accept="image/*"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded text-sm" required>
                                    <button type="submit" name="update_category_image_two"
                                        class="bg-green-500 text-white px-4 py-2 rounded text-sm">Upload</button>
                                    <button type="button" onclick="toggleCategoryImageUploadTwo(<?= $category['id'] ?>)"
                                        class="bg-gray-500 text-white px-4 py-2 rounded text-sm">Cancel</button>
                                </form>
                            </div>

                            <!-- Subcategories -->
                            <div class="p-6">
                                <?php if (!empty($category['subcategories'])): ?>
                                    <div class="space-y-4">
                                        <?php foreach ($category['subcategories'] as $sub): ?>
                                            <div class="border-l-4 <?= $sub_border ?> pl-4">

                                                <!-- Subcategory Row -->
                                                <div class="flex items-center justify-between p-4 <?= $sub_bg ?> rounded-lg mb-2">
                                                    <div class="flex items-center space-x-4 flex-1">
                                                        <div class="w-16 h-16 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                                                            <?php if ($sub['image_path']): ?>
                                                                <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($sub['slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                                                    class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd"
                                                                            d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                                                                            clip-rule="evenodd" />
                                                                    </svg>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-gray-900 flex items-center gap-2 flex-wrap">
                                                                <?= htmlspecialchars($sub['name']) ?>
                                                                <?= tag_badge($sub['tag'], $conn) ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">Slug:
                                                                <?= htmlspecialchars($sub['slug']) ?></div>
                                                            <!-- Tag selector for Subcategory -->
                                                            <form method="POST" class="inline mt-1">
                                                                <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                                                <select name="tag" onchange="this.form.submit()"
                                                                    class="tag-select tag-<?= htmlspecialchars($sub['tag']) ?> mt-1">
                                                                    <?php foreach ($tag_options as $val => $opt): ?>
                                                                        <option value="<?= $val ?>" <?= $sub['tag'] === $val ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($opt['label']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <input type="hidden" name="update_tag_sub" value="1">
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center space-x-2 flex-shrink-0">
                                                        <button onclick="toggleImageUpload(<?= $sub['id'] ?>)"
                                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                                            <?= $sub['image_path'] ? 'Update' : 'Add' ?> Image
                                                        </button>
                                                        <form method="POST" class="inline"
                                                            onsubmit="return confirm('Delete subcategory?')">
                                                            <input type="hidden" name="delete_subcategory_id" value="<?= $sub['id'] ?>">
                                                            <button type="submit" name="delete_subcategory"
                                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <!-- Subcategory Image Upload -->
                                                <div id="image-upload-<?= $sub['id'] ?>" class="hidden mb-4 p-4 bg-blue-50 rounded-lg">
                                                    <form method="POST" enctype="multipart/form-data"
                                                        class="flex items-center space-x-4">
                                                        <input type="hidden" name="subcategory_id" value="<?= $sub['id'] ?>">
                                                        <input type="file" name="new_image" accept="image/*"
                                                            class="flex-1 px-3 py-2 border border-gray-300 rounded text-sm" required>
                                                        <button type="submit" name="update_subcategory_image"
                                                            class="bg-blue-500 text-white px-4 py-2 rounded text-sm">Upload</button>
                                                        <button type="button" onclick="toggleImageUpload(<?= $sub['id'] ?>)"
                                                            class="bg-gray-500 text-white px-4 py-2 rounded text-sm">Cancel</button>
                                                    </form>
                                                </div>

                                                <!-- Sub-Subcategories -->
                                                <?php if (!empty($sub['sub_subcategories'])): ?>
                                                    <div class="ml-8 space-y-2">
                                                        <?php foreach ($sub['sub_subcategories'] as $subsub): ?>
                                                            <?php
                                                            $ss_border = match ($subsub['tag']) {
                                                                'best_offer' => 'border-orange-400 bg-orange-50',
                                                                'new_arrival' => 'border-green-400 bg-green-50',
                                                                'sale' => 'border-pink-400 bg-pink-50',
                                                                default => 'border-purple-500 bg-purple-50',
                                                            };
                                                            ?>
                                                            <div
                                                                class="flex items-center justify-between p-3 rounded-lg border-l-4 <?= $ss_border ?>">
                                                                <div class="flex items-center space-x-3 flex-1">
                                                                    <div class="w-12 h-12 bg-gray-200 rounded overflow-hidden flex-shrink-0">
                                                                        <?php if ($subsub['image_path']): ?>
                                                                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($subsub['parent_slug']) ?>/<?= htmlspecialchars($subsub['slug']) ?>/<?= htmlspecialchars($subsub['image_path']) ?>"
                                                                                class="w-full h-full object-cover">
                                                                        <?php else: ?>
                                                                            <div
                                                                                class="w-full h-full flex items-center justify-center text-gray-400">
                                                                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd"
                                                                                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                                                                                        clip-rule="evenodd" />
                                                                                </svg>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <div
                                                                            class="font-medium text-gray-900 text-sm flex items-center gap-2 flex-wrap">
                                                                            <?= htmlspecialchars($subsub['name']) ?>
                                                                            <?= tag_badge($subsub['tag'], $conn) ?>
                                                                        </div>
                                                                        <div class="text-xs text-gray-500">
                                                                            Slug: <?= htmlspecialchars($subsub['slug']) ?>
                                                                            <span
                                                                                class="ml-2 bg-purple-100 text-purple-700 px-2 py-0.5 rounded">
                                                                                <?= $subsub['product_count'] ?> products
                                                                            </span>
                                                                        </div>
                                                                        <!-- Tag selector for Sub-Subcategory -->
                                                                        <form method="POST" class="inline mt-1">
                                                                            <input type="hidden" name="subsub_id" value="<?= $subsub['id'] ?>">
                                                                            <select name="tag" onchange="this.form.submit()"
                                                                                class="tag-select tag-<?= htmlspecialchars($subsub['tag']) ?> mt-1">
                                                                                <?php foreach ($tag_options as $val => $opt): ?>
                                                                                    <option value="<?= $val ?>" <?= $subsub['tag'] === $val ? 'selected' : '' ?>>
                                                                                        <?= htmlspecialchars($opt['label']) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                            <input type="hidden" name="update_tag_subsub" value="1">
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-2 flex-shrink-0">
                                                                    <button onclick="toggleSubSubImageUpload(<?= $subsub['id'] ?>)"
                                                                        class="bg-purple-500 hover:bg-purple-600 text-white px-2 py-1 rounded text-xs">
                                                                        <?= $subsub['image_path'] ? 'Update' : 'Add' ?> Image
                                                                    </button>
                                                                    <form method="POST" class="inline"
                                                                        onsubmit="return confirm('Delete sub-subcategory?')">
                                                                        <input type="hidden" name="delete_sub_subcategory_id"
                                                                            value="<?= $subsub['id'] ?>">
                                                                        <button type="submit" name="delete_sub_subcategory"
                                                                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>

                                                            <!-- Sub-Sub Image Upload -->
                                                            <div id="sub-sub-image-upload-<?= $subsub['id'] ?>"
                                                                class="hidden ml-12 mb-2 p-3 bg-purple-50 rounded-lg">
                                                                <form method="POST" enctype="multipart/form-data"
                                                                    class="flex items-center space-x-3">
                                                                    <input type="hidden" name="sub_subcategory_id" value="<?= $subsub['id'] ?>">
                                                                    <input type="file" name="new_sub_sub_image" accept="image/*"
                                                                        class="flex-1 px-3 py-2 border border-gray-300 rounded text-xs"
                                                                        required>
                                                                    <button type="submit" name="update_sub_subcategory_image"
                                                                        class="bg-purple-500 text-white px-3 py-1 rounded text-xs">Upload</button>
                                                                    <button type="button"
                                                                        onclick="toggleSubSubImageUpload(<?= $subsub['id'] ?>)"
                                                                        class="bg-gray-500 text-white px-3 py-1 rounded text-xs">Cancel</button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-400 italic">No subcategories yet</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const subcategoriesData = <?= json_encode($subcategories) ?>;

        document.getElementById('subcategory_name')?.addEventListener('input', function () {
            document.getElementById('subcategory_slug').value =
                this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        });
        document.getElementById('sub_subcategory_name')?.addEventListener('input', function () {
            document.getElementById('sub_subcategory_slug').value =
                this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        });

        function filterSubcategories(categoryId) {
            const sel = document.getElementById('subcategory_id');
            sel.innerHTML = '<option value="">-- Select Subcategory --</option>';
            if (categoryId) {
                subcategoriesData
                    .filter(s => s.category_id == categoryId)
                    .forEach(s => {
                        const o = document.createElement('option');
                        o.value = s.id; o.textContent = s.subcategory_name;
                        sel.appendChild(o);
                    });
            }
        }

        // Recolor tag selects on change (before form submits)
        document.querySelectorAll('.tag-select').forEach(sel => {
            sel.addEventListener('change', function () {
                this.className = 'tag-select tag-' + this.value;
            });
        });

        function toggleImageUpload(id) { document.getElementById('image-upload-' + id).classList.toggle('hidden'); }
        function toggleSubSubImageUpload(id) { document.getElementById('sub-sub-image-upload-' + id).classList.toggle('hidden'); }
        function toggleCategoryImageUpload(id) { document.getElementById('category-image-upload-' + id).classList.toggle('hidden'); }
        function toggleCategoryImageUploadTwo(id) { document.getElementById('category-image-upload-two-' + id).classList.toggle('hidden'); }
    </script>
</body>

</html>
<?php $conn->close(); ?>