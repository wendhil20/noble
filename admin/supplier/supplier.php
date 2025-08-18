<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);

// Add new supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $company = $_POST['company'] ?? '';
    $address = $_POST['address'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = $conn->prepare("INSERT INTO adminsuppliers (company, address, status, mobile, email) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $company, $address, $status, $mobile, $email);
    $stmt->execute();
    header("Location: supplier.php?success=1");
    exit();
}

// Update supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_supplier'])) {
    $id = $_POST['id'];
    $company = $_POST['company'] ?? '';
    $address = $_POST['address'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = $conn->prepare("UPDATE adminsuppliers SET company = ?, address = ?, status = ?, mobile = ?, email = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $company, $address, $status, $mobile, $email, $id);
    $stmt->execute();
    header("Location: supplier.php?updated=1");
    exit();
}

$suppliers = $conn->query("SELECT * FROM adminsuppliers ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Suppliers</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include '../navbar/top.php'; ?>
<div class=" bg-white p-6 rounded shadow">
  <h2 class="text-2xl font-bold text-orange-500 mb-6">Supplier List</h2>

  <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">Supplier added successfully!</div>
  <?php elseif (isset($_GET['updated'])): ?>
    <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded mb-4">Supplier updated successfully!</div>
  <?php endif; ?>

<!-- Buttons Wrapper -->
<div class="mb-4 flex flex-wrap gap-2">

 <a href="../warehouse/warehouses.php"
     class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
   Back to Warehouse
  </a>
  <button onclick="document.getElementById('addModal').classList.remove('hidden')"
          class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
    Add Supplier
  </button>

 
</div>


  <!-- Supplier Table -->
  <div class="overflow-x-auto">
    <table class="min-w-full bg-white border border-gray-200">
      <thead class="bg-gray-100 text-gray-700">
        <tr>
          <th class="px-4 py-2 text-left">Company</th>
          <th class="px-4 py-2 text-left">Address</th>
          <th class="px-4 py-2 text-left">Mobile</th>
          <th class="px-4 py-2 text-left">Email</th>
          <th class="px-4 py-2 text-left">Status</th>
          <th class="px-4 py-2 text-left">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row = $suppliers->fetch_assoc()): ?>
          <tr class="border-t">
            <td class="px-4 py-2"><?php echo htmlspecialchars($row['company'] ?? ''); ?></td>
            <td class="px-4 py-2"><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
            <td class="px-4 py-2"><?php echo htmlspecialchars($row['mobile'] ?? ''); ?></td>
            <td class="px-4 py-2"><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
            <td class="px-4 py-2">
              <span class="inline-block px-2 py-1 text-sm rounded-full
                <?php echo $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo ucfirst($row['status']); ?>
              </span>
            </td>
            <td class="px-4 py-2">
              <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)"
                      class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">Edit</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Supplier Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white p-6 rounded-lg w-full max-w-lg">
    <h3 class="text-xl font-bold mb-4 text-orange-500">Add Supplier</h3>
    <form method="POST" action="">
      <input type="hidden" name="add_supplier" value="1" />
      
      <div class="mb-3">
        <label class="block mb-1 font-medium">Company</label>
        <input name="company" type="text" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Address</label>
        <textarea name="address" required class="w-full border border-gray-300 px-3 py-2 rounded"></textarea>
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Mobile</label>
        <input name="mobile" type="tel" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Email</label>
        <input name="email" type="email" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium">Status</label>
        <select name="status" class="w-full border border-gray-300 px-3 py-2 rounded">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Supplier Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white p-6 rounded-lg w-full max-w-lg">
    <h3 class="text-xl font-bold mb-4 text-blue-500">Edit Supplier</h3>
    <form method="POST" action="">
      <input type="hidden" name="update_supplier" value="1" />
      <input type="hidden" name="id" id="edit_id" />
      
      <div class="mb-3">
        <label class="block mb-1 font-medium">Company</label>
        <input name="company" id="edit_company" type="text" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Address</label>
        <textarea name="address" id="edit_address" required class="w-full border border-gray-300 px-3 py-2 rounded"></textarea>
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Mobile</label>
        <input name="mobile" id="edit_mobile" type="tel" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-3">
        <label class="block mb-1 font-medium">Email</label>
        <input name="email" id="edit_email" type="email" required class="w-full border border-gray-300 px-3 py-2 rounded" />
      </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium">Status</label>
        <select name="status" id="edit_status" class="w-full border border-gray-300 px-3 py-2 rounded">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_company').value = data.company;
    document.getElementById('edit_address').value = data.address;
    document.getElementById('edit_mobile').value = data.mobile;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('editModal').classList.remove('hidden');
  }
</script>

</body>
</html>
