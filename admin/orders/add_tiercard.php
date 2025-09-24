<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales','superadmin']);

$message = "";
$edit_card = null;


// Reset auto-increment if needed
$tables = ['tiercard'];
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

// Handle delete with image unlink
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $card_id = $_GET['delete'];
    
    // Get image filename before deleting
    $stmt = $conn->prepare("SELECT card_image FROM tiercard WHERE id = ?");
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $image_file = $row['card_image'];
        
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM tiercard WHERE id = ?");
        $delete_stmt->bind_param("i", $card_id);
        
        if ($delete_stmt->execute()) {
            // Delete image file if exists
            if ($image_file && file_exists("../../uploads/" . $image_file)) {
                unlink("../../uploads/" . $image_file);
            }
            // Redirect to prevent resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=deleted");
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=delete_failed");
            exit();
        }
        $delete_stmt->close();
    }
    $stmt->close();
}

// Handle success and error messages from URL parameters
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = "<p class='text-green-600 font-medium'>✅ New tier card added successfully!</p>";
            break;
        case 'updated':
            $message = "<p class='text-green-600 font-medium'>✅ Tier card updated successfully!</p>";
            break;
        case 'deleted':
            $message = "<p class='text-green-600 font-medium'>✅ Tier card deleted successfully!</p>";
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'add_failed':
            $message = "<p class='text-red-600 font-medium'>❌ Error adding tier card!</p>";
            break;
        case 'update_failed':
            $message = "<p class='text-red-600 font-medium'>❌ Error updating tier card!</p>";
            break;
        case 'delete_failed':
            $message = "<p class='text-red-600 font-medium'>❌ Error deleting tier card!</p>";
            break;
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $card_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM tiercard WHERE id = ?");
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_card = $result->fetch_assoc();
    $stmt->close();
}

// Handle form submit (both add and update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_name = $_POST['card_name'];
    $card_discount = $_POST['card_discount'];
    $card_id = isset($_POST['card_id']) ? $_POST['card_id'] : null;
    
    // Handle image upload
    $card_image = null;
    $old_image = isset($_POST['old_image']) ? $_POST['old_image'] : null;
    
    if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = time() . '_' . basename($_FILES['card_image']['name']);
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['card_image']['tmp_name'], $targetPath)) {
            $card_image = $filename;
            
            // Delete old image if updating
            if ($old_image && file_exists($uploadDir . $old_image)) {
                unlink($uploadDir . $old_image);
            }
        }
    } else if ($card_id) {
        // Keep old image if no new image uploaded during update
        $card_image = $old_image;
    }
    
    if ($card_id) {
        // Update existing card
        $stmt = $conn->prepare("UPDATE tiercard SET card_name = ?, card_discount = ?, card_image = ? WHERE id = ?");
        $stmt->bind_param("sdsi", $card_name, $card_discount, $card_image, $card_id);
        
        if ($stmt->execute()) {
            // Redirect to prevent resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=updated");
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=update_failed");
            exit();
        }
    } else {
        // Insert new card
        $stmt = $conn->prepare("INSERT INTO tiercard (card_name, card_discount, card_image) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $card_name, $card_discount, $card_image);
        
        if ($stmt->execute()) {
            // Redirect to prevent resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=added");
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=add_failed");
            exit();
        }
    }
    
    $stmt->close();
}

// Fetch all tier cards
$cards_result = $conn->query("SELECT * FROM tiercard ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_card ? 'Edit' : 'Add'; ?> Tier Card</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function confirmDelete(cardName) {
            return confirm(`Are you sure you want to delete "${cardName}" tier card? This action cannot be undone.`);
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php include '../navbar/top.php'; ?>
    <div class="container mx-auto px-4 max-w-6xl mt-5">
        
        <!-- Add/Edit Form -->
        <div class="bg-white p-8 mb-8 max-w-md mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                <?php echo $edit_card ? 'Edit Tier Card' : 'Add Tier Card'; ?>
            </h2>
            
            <?php if ($message) echo "<div class='mb-4 text-center'>$message</div>"; ?>
            
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                <?php if ($edit_card): ?>
                    <input type="hidden" name="card_id" value="<?php echo $edit_card['id']; ?>">
                    <input type="hidden" name="old_image" value="<?php echo $edit_card['card_image']; ?>">
                <?php endif; ?>
                
                <!-- Card Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Name</label>
                    <input type="text" name="card_name" required
                        value="<?php echo $edit_card ? htmlspecialchars($edit_card['card_name']) : ''; ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" 
                        placeholder="e.g. Silver, Gold">
                </div>
                
                <!-- Discount -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount (%)</label>
                    <input type="number" name="card_discount" step="0.01" required
                        value="<?php echo $edit_card ? $edit_card['card_discount'] : ''; ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" 
                        placeholder="e.g. 10">
                </div>
                
                <!-- Card Image -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Image</label>
                    <?php if ($edit_card && $edit_card['card_image']): ?>
                        <div class="mb-2">
                            <img src="../../uploads/<?php echo $edit_card['card_image']; ?>" 
                                 alt="Current image" class="w-20 h-20 object-cover rounded border">
                            <p class="text-xs text-gray-500 mt-1">Current image</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="card_image" accept="image/*" 
                        <?php echo !$edit_card ? 'required' : ''; ?>
                        class="w-full px-3 py-2 border rounded-lg text-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <?php if ($edit_card): ?>
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
                    <?php endif; ?>
                </div>
                
                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="submit" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg transition duration-200">
                        <?php echo $edit_card ? 'Update' : 'Save'; ?> Tier Card
                    </button>
                    
                    <?php if ($edit_card): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" 
                           class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2.5 rounded-lg transition duration-200 text-center">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Tier Cards List -->
        <div class="bg-white  p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">Existing Tier Cards</h3>
            
            <?php if ($cards_result && $cards_result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Image</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Card Name</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Discount (%)</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($card = $cards_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <?php if ($card['card_image']): ?>
                                            <img src="../../uploads/<?php echo $card['card_image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($card['card_name']); ?>"
                                                 class="w-12 h-12 object-cover rounded border">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-gray-200 rounded border flex items-center justify-center">
                                                <span class="text-xs text-gray-500">No Image</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <?php echo htmlspecialchars($card['card_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <?php echo number_format($card['card_discount'], 2); ?>%
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center gap-2">
                                            <a href="?edit=<?php echo $card['id']; ?>" 
                                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium transition duration-200">
                                                Edit
                                            </a>
                                            <a href="?delete=<?php echo $card['id']; ?>" 
                                               onclick="return confirmDelete('<?php echo htmlspecialchars($card['card_name'], ENT_QUOTES); ?>')"
                                               class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition duration-200">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 text-6xl mb-4">📋</div>
                    <p class="text-gray-500 text-lg">No tier cards found</p>
                    <p class="text-gray-400">Add your first tier card using the form above</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</body>
</html>