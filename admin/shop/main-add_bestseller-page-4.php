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

$tables = ['bestseller', 'bestsellertwo'];

foreach ($tables as $table) {
  $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
  $row = $result->fetch_assoc();
  $max_id = (int)$row['max_id'];
  $next_id = $max_id > 0 ? $max_id + 1 : 1;
  $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

/* -------------------------------
   BESTSELLER (MAIN TABLE) CRUD
--------------------------------*/
if (isset($_POST['add_bestseller'])) {
  $title = $_POST['title'];
  $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
  $description = $_POST['description'];

  $image = null;
  if (!empty($_FILES['image']['name'])) {
    $targetDir = "../../uploads/";
    $filename = time() . "_" . basename($_FILES["image"]["name"]);
    $targetFile = $targetDir . $filename;
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
      $image = "../../uploads/" . $filename;
    }
  }

  $stmt = $conn->prepare("INSERT INTO bestseller (title, slug, description, image) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $title, $slug, $description, $image);
  $stmt->execute();

  $_SESSION['msg'] = "✅ Bestseller added!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

if (isset($_GET['delete_bestseller'])) {
  $id = (int)$_GET['delete_bestseller'];
  $old = $conn->query("SELECT image FROM bestseller WHERE id=$id")->fetch_assoc();
  if ($old && $old['image'] && file_exists($old['image'])) {
    unlink($old['image']);
  }
  $conn->query("DELETE FROM bestseller WHERE id=$id");

  $_SESSION['msg'] = "🗑️ Bestseller deleted!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

/* -------------------------------
   BESTSELLERTWO (DETAILS TABLE) CRUD
--------------------------------*/
if (isset($_POST['add_section'])) {
  $bestseller_id = $_POST['bestseller_id'];
  $subtitle = $_POST['subtitle'];
  $content = $_POST['content'];
  $id = $_POST['id'] ?? null;

  $images = [];

  if (!empty($_FILES['section_images']['name'][0])) {
    $targetDir = "../../uploads/";
    foreach ($_FILES['section_images']['name'] as $key => $name) {
      if (!empty($name)) {
        $filename = time() . "_" . basename($name);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['section_images']['tmp_name'][$key], $targetFile)) {
          $images[] = "../../uploads/" . $filename;
        }
      }
    }
  }

  if ($id) {
    $old = $conn->query("SELECT image FROM bestsellertwo WHERE id=$id")->fetch_assoc();
    $oldImages = $old && $old['image'] ? json_decode($old['image'], true) : [];
    if (!$images) {
      $images = $oldImages;
    } else {
      $images = array_merge($oldImages, $images);
    }

    $imagesJson = json_encode($images);
    $stmt = $conn->prepare("UPDATE bestsellertwo SET bestseller_id=?, subtitle=?, content=?, image=? WHERE id=?");
    $stmt->bind_param("isssi", $bestseller_id, $subtitle, $content, $imagesJson, $id);
    $stmt->execute();
    $_SESSION['msg'] = "✅ Section updated!";
  } else {
    $imagesJson = json_encode($images);
    $stmt = $conn->prepare("INSERT INTO bestsellertwo (bestseller_id, subtitle, content, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $bestseller_id, $subtitle, $content, $imagesJson);
    $stmt->execute();
    $_SESSION['msg'] = "✅ Section added!";
  }

  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Update section (bestsellertwo)
if (isset($_POST['update_section'])) {
  $section_id = (int)$_POST['section_id'];
  $bestseller_id = (int)$_POST['bestseller_id'];
  $subtitle = $_POST['subtitle'];
  $content = $_POST['content'];

  $stmt = $conn->prepare("UPDATE bestsellertwo SET bestseller_id=?, subtitle=?, content=? WHERE id=?");
  $stmt->bind_param("issi", $bestseller_id, $subtitle, $content, $section_id);
  $stmt->execute();

  $_SESSION['msg'] = "✅ Section updated!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}


// Add more images to existing section
if (isset($_POST['add_more_images'])) {
  $section_id = (int)$_POST['section_id'];

  $newImages = [];
  if (!empty($_FILES['more_images']['name'][0])) {
    $targetDir = "../../uploads/";
    foreach ($_FILES['more_images']['name'] as $key => $name) {
      if (!empty($name)) {
        $filename = time() . "_" . basename($name);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['more_images']['tmp_name'][$key], $targetFile)) {
          $newImages[] = "../../uploads/" . $filename;
        }
      }
    }
  }

  if (!empty($newImages)) {
    $old = $conn->query("SELECT image FROM bestsellertwo WHERE id=$section_id")->fetch_assoc();
    $oldImages = $old && $old['image'] ? json_decode($old['image'], true) : [];
    $allImages = array_merge($oldImages, $newImages);
    $imagesJson = json_encode($allImages);

    $stmt = $conn->prepare("UPDATE bestsellertwo SET image=? WHERE id=?");
    $stmt->bind_param("si", $imagesJson, $section_id);
    $stmt->execute();
    $_SESSION['msg'] = "✅ Images added!";
  }

  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Update product link
if (isset($_POST['update_products'])) {
  $bestseller_id = (int)$_POST['bestseller_id'];
  $product_id = isset($_POST['product_id']) && $_POST['product_id'] != '' ? (int)$_POST['product_id'] : NULL;

  $stmt = $conn->prepare("UPDATE bestseller SET product_id=? WHERE id=?");
  $stmt->bind_param("ii", $product_id, $bestseller_id);
  $stmt->execute();

  $_SESSION['msg'] = "✅ Product updated!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}


// Update bestseller
if (isset($_POST['update_bestseller'])) {
  $id = (int)$_POST['bestseller_id'];
  $title = $_POST['title'];
  $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
  $description = $_POST['description'];
  $old_image = $_POST['old_image'];

  $image = $old_image;

  if (!empty($_FILES['image']['name'])) {
    $targetDir = "../../uploads/";
    $filename = time() . "_" . basename($_FILES["image"]["name"]);
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
      if ($old_image && file_exists($old_image)) {
        unlink($old_image);
      }
      $image = "../../uploads/" . $filename;
    }
  }

  $stmt = $conn->prepare("UPDATE bestseller SET title=?, slug=?, description=?, image=? WHERE id=?");
  $stmt->bind_param("ssssi", $title, $slug, $description, $image, $id);
  $stmt->execute();

  $_SESSION['msg'] = "✅ Bestseller updated!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Update product links
if (isset($_POST['update_products'])) {
  $bestseller_id = (int)$_POST['bestseller_id'];
  $product_ids = isset($_POST['product_id']) ? json_encode($_POST['product_id']) : json_encode([]);

  $stmt = $conn->prepare("UPDATE bestseller SET product_id=? WHERE id=?");
  $stmt->bind_param("si", $product_ids, $bestseller_id);
  $stmt->execute();

  $_SESSION['msg'] = "✅ Products updated!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Delete individual image from section
if (isset($_GET['delete_image'])) {
  $section_id = (int)$_GET['section_id'];
  $image_index = (int)$_GET['delete_image'];

  $old = $conn->query("SELECT image FROM bestsellertwo WHERE id=$section_id")->fetch_assoc();
  if ($old && $old['image']) {
    $images = json_decode($old['image'], true);
    if (isset($images[$image_index])) {
      if (file_exists($images[$image_index])) {
        unlink($images[$image_index]);
      }
      array_splice($images, $image_index, 1);
      $imagesJson = json_encode($images);

      $stmt = $conn->prepare("UPDATE bestsellertwo SET image=? WHERE id=?");
      $stmt->bind_param("si", $imagesJson, $section_id);
      $stmt->execute();
      $_SESSION['msg'] = "🗑️ Image deleted!";
    }
  }

  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

if (isset($_GET['delete_section'])) {
  $id = (int)$_GET['delete_section'];
  $old = $conn->query("SELECT image FROM bestsellertwo WHERE id=$id")->fetch_assoc();
  if ($old && $old['image']) {
    $imgs = json_decode($old['image'], true);
    if (is_array($imgs)) {
      foreach ($imgs as $img) {
        if (file_exists($img)) unlink($img);
      }
    }
  }
  $conn->query("DELETE FROM bestsellertwo WHERE id=$id");

  $_SESSION['msg'] = "🗑️ Section deleted!";
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

$msg = "";
if (isset($_SESSION['msg'])) {
  $msg = $_SESSION['msg'];
  unset($_SESSION['msg']);
}

/* -------------------------------
   FETCH DATA FOR LISTS
--------------------------------*/
$bestsellers = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
$sections = $conn->query("SELECT bt.*, b.title as bestseller_title 
                          FROM bestsellertwo bt 
                          JOIN bestseller b ON bt.bestseller_id=b.id 
                          ORDER BY bt.id DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Bestsellers</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>

<body class="bg-gray-100">

  <?php include '../navbar/top.php'; ?>

  <div class="max-w-6xl mx-auto mt-8 mb-12">

    <?php if (!empty($msg)) : ?>
      <div class="mb-4 p-3 bg-green-100 text-green-700 rounded"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Bestseller Form -->
    <div class="bg-white p-6 rounded shadow mb-10">
      <h2 class="text-xl font-bold mb-4">Add Bestseller (Main)</h2>
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="add_bestseller" value="1">

        <div>
          <label class="block">Title</label>
          <input type="text" name="title" class="w-full border p-2 rounded" required>
        </div>

        <div>
          <label class="block">Description</label>
          <textarea name="description" class="w-full border p-2 rounded"></textarea>
        </div>

        <div>
          <label class="block">Image</label>
          <input type="file" name="image" accept="image/*">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Bestseller</button>
      </form>
    </div>

    <!-- Bestseller List -->
    <div class="bg-white p-6 rounded shadow mb-10">
      <h2 class="text-xl font-bold mb-4">Bestsellers List</h2>
      <div class="space-y-4">
        <?php
        $bestsellers_edit = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
        while ($row = $bestsellers_edit->fetch_assoc()) :
        ?>
          <div class="border rounded-lg p-4 bg-gray-50">
            <div class="flex justify-between items-start mb-3">
              <div class="flex-1">
                <p class="text-xs text-gray-500 mb-1">ID: <?= $row['id'] ?> | Slug: <?= $row['slug'] ?></p>
                <h3 class="font-bold text-lg"><?= $row['title'] ?></h3>
                <p class="text-sm text-gray-600 mt-1"><?= substr($row['description'], 0, 100) ?>...</p>
              </div>
              <div class="flex gap-2 ml-4">
                <?php if ($row['image']) : ?>
                  <img src="<?= $row['image'] ?>" class="w-16 h-16 object-contain rounded">
                <?php endif; ?>
              </div>
            </div>

            <div class="flex gap-2">
              <button onclick="document.getElementById('edit-bestseller-<?= $row['id'] ?>').classList.toggle('hidden')"
                class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                Edit
              </button>
              <button onclick="document.getElementById('products-<?= $row['id'] ?>').classList.toggle('hidden')"
                class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                Products
              </button>
              <a href="?delete_bestseller=<?= $row['id'] ?>"
                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
                onclick="return confirm('Delete this bestseller?')">
                Delete
              </a>
            </div>

            <!-- Products Form (Hidden) -->
            <!-- Products Form (Hidden) -->
            <div id="products-<?= $row['id'] ?>" class="hidden bg-white p-4 rounded border mt-4">
              <h4 class="font-semibold mb-3">Select Product for <?= $row['title'] ?></h4>
              <form method="POST" class="space-y-3">
                <input type="hidden" name="update_products" value="1">
                <input type="hidden" name="bestseller_id" value="<?= $row['id'] ?>">

                <div>
                  <label class="block text-sm font-medium mb-2">Choose Product</label>
                  <select name="product_id" class="w-full border p-2 rounded" required>
                    <option value="">-- Select Product --</option>
                    <?php
                    $all_products = $conn->query("SELECT id, product_name, main_image FROM products ORDER BY product_name ASC");
                    while ($prod = $all_products->fetch_assoc()) : ?>
                      <option value="<?= $prod['id'] ?>" <?= $row['product_id'] == $prod['id'] ? 'selected' : '' ?>>
                        <?= $prod['product_name'] ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div class="flex gap-2">
                  <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Save Product
                  </button>
                  <button type="button" onclick="document.getElementById('products-<?= $row['id'] ?>').classList.add('hidden')"
                    class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
                    Cancel
                  </button>
                </div>
              </form>
            </div>

            <!-- Edit Form (Hidden) -->
            <div id="edit-bestseller-<?= $row['id'] ?>" class="hidden bg-white p-4 rounded border mt-4">
              <h4 class="font-semibold mb-3">Edit Bestseller</h4>
              <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="update_bestseller" value="1">
                <input type="hidden" name="bestseller_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="old_image" value="<?= $row['image'] ?>">

                <div>
                  <label class="block text-sm font-medium mb-1">Title</label>
                  <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>"
                    class="w-full border p-2 rounded" required>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">Description</label>
                  <textarea name="description" rows="3" class="w-full border p-2 rounded"><?= htmlspecialchars($row['description']) ?></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">
                    Change Image (leave empty to keep current)
                  </label>
                  <input type="file" name="image" accept="image/*" class="w-full border p-2 rounded">
                </div>

                <div class="flex gap-2">
                  <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Update
                  </button>
                  <button type="button" onclick="document.getElementById('edit-bestseller-<?= $row['id'] ?>').classList.add('hidden')"
                    class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Bestseller Section Form -->
    <div class="bg-white p-6 rounded shadow mb-10">
      <h2 class="text-xl font-bold mb-4">Add Section (bestsellertwo)</h2>
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="add_section" value="1">

        <div>
          <label class="block">Bestseller</label>
          <select name="bestseller_id" class="w-full border p-2 rounded" required>
            <option value="">-- Select --</option>
            <?php
            $bs = $conn->query("SELECT id, title FROM bestseller ORDER BY title ASC");
            while ($r = $bs->fetch_assoc()) : ?>
              <option value="<?= $r['id'] ?>"><?= $r['title'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label class="block">Subtitle</label>
          <input type="text" name="subtitle" class="w-full border p-2 rounded">
        </div>

        <div>
          <label class="block">Content</label>
          <textarea name="content" class="w-full border p-2 rounded" rows="4"></textarea>
        </div>

        <div>
          <label class="block">Images (multiple)</label>
          <input type="file" name="section_images[]" accept="image/*" multiple>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Section</button>
      </form>
    </div>

    <!-- Section List -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-bold mb-4">Sections List</h2>
      <div class="space-y-6">
        <?php while ($row = $sections->fetch_assoc()) : ?>
          <div class="border rounded-lg p-4 bg-gray-50">

            <!-- Section Info & Actions -->
            <div class="flex justify-between items-start mb-4">
              <div>
                <p class="text-xs text-gray-500">ID: <?= $row['id'] ?> | <?= $row['bestseller_title'] ?></p>
                <h3 class="font-bold text-lg"><?= $row['subtitle'] ?></h3>
              </div>
              <div class="flex gap-2">
                <button onclick="document.getElementById('edit-<?= $row['id'] ?>').classList.toggle('hidden')"
                  class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                  Edit
                </button>
                <button onclick="document.getElementById('add-images-<?= $row['id'] ?>').classList.toggle('hidden')"
                  class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                  + Images
                </button>
                <a href="?delete_section=<?= $row['id'] ?>"
                  class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
                  onclick="return confirm('Delete this section?')">
                  Delete
                </a>
              </div>
            </div>

            <!-- Edit Form (Hidden) -->
            <div id="edit-<?= $row['id'] ?>" class="hidden bg-white p-4 rounded border mb-4">
              <h4 class="font-semibold mb-3">Edit Section</h4>
              <form method="POST" class="space-y-3">
                <input type="hidden" name="update_section" value="1">
                <input type="hidden" name="section_id" value="<?= $row['id'] ?>">

                <div>
                  <label class="block text-sm font-medium mb-1">Bestseller</label>
                  <select name="bestseller_id" class="w-full border p-2 rounded" required>
                    <?php
                    $bs2 = $conn->query("SELECT id, title FROM bestseller ORDER BY title ASC");
                    while ($r2 = $bs2->fetch_assoc()) : ?>
                      <option value="<?= $r2['id'] ?>" <?= $r2['id'] == $row['bestseller_id'] ? 'selected' : '' ?>>
                        <?= $r2['title'] ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">Subtitle</label>
                  <input type="text" name="subtitle" value="<?= htmlspecialchars($row['subtitle']) ?>"
                    class="w-full border p-2 rounded">
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">Content</label>
                  <textarea name="content" rows="4" class="w-full border p-2 rounded"><?= htmlspecialchars($row['content']) ?></textarea>
                </div>

                <div class="flex gap-2">
                  <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Update
                  </button>
                  <button type="button" onclick="document.getElementById('edit-<?= $row['id'] ?>').classList.add('hidden')"
                    class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
                    Cancel
                  </button>
                </div>
              </form>
            </div>

            <!-- Content Preview -->
            <p class="text-gray-700 text-sm mb-4"><?= nl2br(htmlspecialchars(substr($row['content'], 0, 200))) ?>...</p>

            <!-- Add More Images Form (Hidden) -->
            <div id="add-images-<?= $row['id'] ?>" class="hidden bg-white p-4 rounded border mb-4">
              <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                <input type="hidden" name="add_more_images" value="1">
                <input type="hidden" name="section_id" value="<?= $row['id'] ?>">
                <input type="file" name="more_images[]" accept="image/*" multiple required
                  class="flex-1 border p-2 rounded">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                  Upload
                </button>
              </form>
            </div>

            <!-- Images Grid -->
            <?php if ($row['image']) :
              $imgs = json_decode($row['image'], true);
              if (is_array($imgs) && !empty($imgs)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                  <?php foreach ($imgs as $index => $img): ?>
                    <div class="relative group">
                      <img src="<?= $img ?>" class="w-full h-24 object-contain rounded border">
                      <a href="?delete_image=<?= $index ?>&section_id=<?= $row['id'] ?>"
                        class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded opacity-0 group-hover:opacity-100 transition"
                        onclick="return confirm('Delete this image?')">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-gray-400 text-sm italic">No images yet</p>
            <?php endif;
            endif; ?>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
  
</body>

</html>