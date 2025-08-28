<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist','superadmin']);

// Reset auto-increment if needed
$tables = ['categories'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    if ($max_id > 0) {
        $result2 = $conn->query("SELECT COUNT(*) AS count FROM $table WHERE id = $max_id");
        $row2 = $result2->fetch_assoc();
        if ((int)$row2['count'] === 0) {
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $max_id");
        }
    } else {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

// Session check
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}



$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["category_name"]);

    if (!empty($name)) {
        $check = $conn->prepare("SELECT * FROM categories WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "❌ Category already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $message = "✅ Category added successfully.";
            } else {
                $message = "❌ Error: " . $stmt->error;
            }
        }
    } else {
        $message = "⚠️ Please enter a category name.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Category</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include '../navbar/top.php'; ?>

<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow mt-10">
    <h2 class="text-2xl font-bold mb-4 text-center text-gray-700">Add New Category</h2>

    <?php if ($message): ?>
        <div class="mb-4 text-center text-sm <?= strpos($message, '✅') !== false ? 'text-green-600' : 'text-red-600' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Category Name</label>
            <input type="text" name="category_name" required class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="e.g. Furniture, Materials">
        </div>
        <div class="text-center">
            <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                Add Category
            </button>
        </div>
    </form>
</div>

</body>
</html>
